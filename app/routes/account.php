<?php
declare(strict_types=1);

function account_routes(): array
{
    return [
        ['GET', '/signin/verify', 'mfa_page'],
        ['POST', '/signin/verify', 'mfa_post'],
        ['GET', '/signin/sso', 'sso_start'],
        ['GET', '/signin/sso/callback', 'sso_callback'],
        ['GET', '/account/two-step', 'two_step_page'],
        ['POST', '/account/two-step', 'two_step_post'],
        ['GET', '/account/terms', 'terms_page'],
        ['POST', '/account/terms', 'terms_post'],
    ];
}

function account_home(array $u): string
{
    return $u['role'] === 'admin' ? '/admin' : '/portal';
}

/** Where a signed-in person goes next: terms first, then two-step setup, then where they were heading. */
function after_signin(array $u): never
{
    $to = (string)($_SESSION['after_login'] ?? '');
    unset($_SESSION['after_login']);
    $home = account_home($u);
    if (!str_starts_with($to, $home)) $to = $home;
    if (terms_outstanding($u)) { $_SESSION['after_login'] = $to; redirect('/account/terms'); }
    if (needs_2fa_enrolment($u)) { $_SESSION['after_login'] = $to; redirect('/account/two-step'); }
    redirect($to);
}

function card_page(string $title, string $intro, string $inner): string
{
    no_store();
    return render_layout($title . ' | ' . brand_name(), '', page_head('', $title, $intro) . '<section class="band"><div class="wrap narrow">' . $inner . '</div></section>', ['noindex' => true]);
}

function mfa_page(string $err = ''): string
{
    if (empty($_SESSION['mfa_uid'])) redirect('/portal/login');
    $e = $err ? '<div class="flash err" role="alert">' . e($err) . '</div>' : '';
    return card_page('Two-step sign-in', 'Enter the 6-digit code from your authenticator app.',
        '<form method="post" action="/signin/verify" class="card">' . csrf_field() . $e
        . field('code', 'Code', '', ['required' => true, 'attrs' => ' inputmode="numeric" autocomplete="one-time-code" maxlength="14" autofocus'])
        . '<button class="btn" type="submit">Verify</button><p class="small">Lost your phone? Enter one of your recovery codes instead (for example ABCDE-FGHJK).</p></form>');
}

function mfa_post(): string
{
    $r = attempt_mfa(p('code', 20));
    if (is_string($r)) return mfa_page($r);
    after_signin($r);
}

function sso_callback(): string
{
    no_store();
    try {
        if (g('error') !== '') throw new RuntimeException('The provider declined the sign-in: ' . mb_substr(g('error_description', 200) ?: g('error'), 0, 200));
        $c = sso_finish(g('code', 2000), g('state', 200));
        $u = one("SELECT * FROM users WHERE role = 'admin' AND (sso_subject = ? OR email = ?) ORDER BY sso_subject = ? DESC LIMIT 1", [$c['sub'], $c['email'], $c['sub']]);
        if (!$u && setting('sso_auto_create') === '1') {
            $roleKey = setting('sso_default_role', 'viewer');
            if ($roleKey === 'owner' || !one('SELECT rkey FROM roles WHERE rkey = ?', [$roleKey])) $roleKey = 'viewer';
            $id = insert('users', ['role' => 'admin', 'role_key' => $roleKey, 'client_id' => null, 'name' => mb_substr($c['name'], 0, 120), 'email' => $c['email'], 'password_hash' => null, 'active' => 1, 'sso_subject' => $c['sub'], 'created_at' => now()]);
            audit('people.joined_by_sso', $c['email'] . ' as ' . $roleKey, $id);
            $u = one('SELECT * FROM users WHERE id = ?', [$id]);
        }
        if (!$u || !(int)$u['active']) throw new RuntimeException('There is no active staff account for ' . $c['email'] . '. Ask an administrator to add you.');
        if (!$u['sso_subject']) q('UPDATE users SET sso_subject = ? WHERE id = ?', [$c['sub'], $u['id']]);
        login_user($u);
        $_SESSION['sso'] = 1;
        audit('signin', 'single sign-on', (int)$u['id']);
        after_signin($u);
    } catch (Throwable $ex) {
        // Every error is logged in full. Sign-in problems (RuntimeException) are explained to the
        // visitor; anything unexpected shows no internal detail.
        log_line('app.log', 'single sign-on failed: ' . get_class($ex) . ': ' . $ex->getMessage());
        $known = $ex instanceof RuntimeException;
        audit('signin.sso_failed', $known ? $ex->getMessage() : 'unexpected error');
        $why = $known ? $ex->getMessage() : 'Something went wrong on our side. Try again, or sign in with your password.';
        return card_page('Single sign-on did not work', $why, '<div class="actions"><a class="btn" href="/admin/login">Back to sign in</a></div>');
    }
}

// ---------- two-step sign-in ----------

function two_step_page(array $err = [], ?array $codes = null): string
{
    $u = current_user();
    if (!$u) redirect('/portal/login');
    no_store();
    $e = $err ? '<div class="flash err" role="alert">' . implode('<br>', array_map('e', $err)) . '</div>' : '';
    if ($codes) {
        $list = '<ol class="codes">' . implode('', array_map(fn($c) => '<li><code>' . e($c) . '</code></li>', $codes)) . '</ol>';
        return card_page('Save your recovery codes', 'Each code works once if you lose your phone. They are shown only now.',
            '<div class="card">' . $list . '<p class="small">Store them in your password manager or print them. Anyone with a code and your password can sign in.</p>'
            . '<div class="actions"><button class="btn ghost" type="button" data-copy=".codes">Copy codes</button><a class="btn" href="' . e((string)($_SESSION['after_login'] ?? account_home($u))) . '">I have saved them</a></div></div>');
    }
    if ((int)$u['totp_enabled'] === 1) {
        $left = count(json_decode((string)$u['recovery_codes'], true) ?: []);
        $required = $u['role'] === 'admin' ? setting('signin_require_2fa', '1') === '1' : setting('signin_client_2fa') === 'required';
        return card_page('Two-step sign-in', 'Two-step sign-in is on for your account.', $e
            . '<div class="card"><p>You have <strong>' . $left . '</strong> unused recovery codes.</p>'
            . '<form method="post" action="/account/two-step">' . csrf_field() . '<input type="hidden" name="do" value="codes">'
            . field('password', 'Your password, to confirm', '', ['type' => 'password', 'required' => true, 'attrs' => ' autocomplete="current-password"'])
            . '<button class="btn small" type="submit">Make new recovery codes</button></form>'
            . (!$required ? '<form method="post" action="/account/two-step" class="gap" data-confirm="Turn off two-step sign-in? Your account will be protected by your password alone.">' . csrf_field() . '<input type="hidden" name="do" value="off">'
                . field('password', 'Your password, to confirm', '', ['type' => 'password', 'required' => true, 'attrs' => ' autocomplete="current-password" id="pw-off"'])
                . '<button class="btn small danger" type="submit">Turn off two-step sign-in</button></form>' : '<p class="small">Your organisation requires two-step sign-in, so it cannot be turned off.</p>')
            . '<p class="gap"><a href="' . account_home($u) . '">&larr; Back</a></p></div>');
    }
    if (empty($_SESSION['totp_new'])) $_SESSION['totp_new'] = base32_encode(random_bytes(20));
    $secret = $_SESSION['totp_new'];
    $uri = totp_uri($secret, $u['email']);
    return card_page('Set up two-step sign-in', 'Use an authenticator app such as Microsoft Authenticator, Google Authenticator, 1Password or Authy.', $e
        . '<div class="card"><ol class="setup-steps"><li><strong>Scan this code</strong> with the app.<div class="qr" data-qr="' . e($uri) . '" role="img" aria-label="QR code for your authenticator app"></div>'
        . '<p class="small">Cannot scan? Enter this key: <code class="key">' . e(trim(chunk_split($secret, 4, ' '))) . '</code></p></li>'
        . '<li><strong>Enter the 6-digit code</strong> the app shows.<form method="post" action="/account/two-step">' . csrf_field() . '<input type="hidden" name="do" value="enable">'
        . field('code', 'Code', '', ['required' => true, 'attrs' => ' inputmode="numeric" autocomplete="one-time-code" maxlength="7"'])
        . '<button class="btn" type="submit">Turn on two-step sign-in</button></form></li></ol></div>'
        . '<script src="/assets/vendor/qrcode-1.4.4.js"></script>');
}

function two_step_post(): string
{
    $u = current_user();
    if (!$u) redirect('/portal/login');
    $do = p('do', 10);
    if ($do === 'enable') {
        $secret = (string)($_SESSION['totp_new'] ?? '');
        $step = $secret ? totp_verify($secret, p('code', 10)) : null;
        if ($step === null) return two_step_page(['That code is not right. Check the time on your phone is set automatically, then try the newest code.']);
        $codes = new_recovery_codes();
        q('UPDATE users SET totp_secret = ?, totp_enabled = 1, totp_last_step = ?, recovery_codes = ? WHERE id = ?',
            [seal($secret), $step, json_encode(array_map(fn($c) => recovery_hash((int)$u['id'], $c), $codes)), $u['id']]);
        unset($_SESSION['totp_new']);
        audit('account.two_step_on');
        return two_step_page([], $codes);
    }
    if (!password_verify((string)($_POST['password'] ?? ''), (string)$u['password_hash'])) return two_step_page(['Your password is not right.']);
    if ($do === 'codes') {
        $codes = new_recovery_codes();
        q('UPDATE users SET recovery_codes = ? WHERE id = ?', [json_encode(array_map(fn($c) => recovery_hash((int)$u['id'], $c), $codes)), $u['id']]);
        audit('account.recovery_codes_renewed');
        return two_step_page([], $codes);
    }
    if ($do === 'off') {
        $required = $u['role'] === 'admin' ? setting('signin_require_2fa', '1') === '1' : setting('signin_client_2fa') === 'required';
        if ($required) return two_step_page(['Your organisation requires two-step sign-in.']);
        q('UPDATE users SET totp_enabled = 0, totp_secret = NULL, recovery_codes = NULL WHERE id = ?', [$u['id']]);
        audit('account.two_step_off');
        flash('ok', 'Two-step sign-in is off.');
        redirect(account_home($u));
    }
    abort(400);
}

// ---------- terms ----------

function terms_hash(): string
{
    return hash('sha256', (string)val("SELECT body FROM pages WHERE slug = 'terms'"));
}

function terms_outstanding(array $u): bool
{
    return !hash_equals(terms_hash(), (string)($u['terms_hash'] ?? ''));
}

function terms_page(): string
{
    $u = current_user();
    if (!$u) redirect('/portal/login');
    $p = one("SELECT * FROM pages WHERE slug = 'terms'");
    return card_page('Before you continue', $u['terms_hash'] ? 'Our terms have changed since you last accepted them.' : 'Please read and accept our terms.',
        '<div class="card"><div class="prose terms-box" tabindex="0">' . md((string)($p['body'] ?? '')) . '</div>'
        . '<form method="post" action="/account/terms">' . csrf_field() . '<input type="hidden" name="hash" value="' . e(terms_hash()) . '">'
        . '<label class="check"><input type="checkbox" name="agree" value="1" required> <span>I have read and accept these terms' . ($u['role'] === 'client' ? ' on behalf of my organisation, and I am authorised to do so' : '') . '.</span></label>'
        . '<button class="btn" type="submit">Accept and continue</button></form></div>');
}

function terms_post(): never
{
    $u = current_user();
    if (!$u) redirect('/portal/login');
    if (p('agree') !== '1' || !hash_equals(terms_hash(), p('hash', 64))) redirect('/account/terms');
    q('UPDATE users SET terms_hash = ?, terms_at = ? WHERE id = ?', [terms_hash(), now(), $u['id']]);
    audit('terms.accepted', substr(terms_hash(), 0, 16));
    $u['terms_hash'] = terms_hash();
    after_signin($u);
}
