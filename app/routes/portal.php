<?php
declare(strict_types=1);

function portal_routes(): array
{
    return [
        ['GET', '/portal/login', 'portal_login_page'],
        ['POST', '/portal/login', 'portal_login_post'],
        ['POST', '/portal/logout', 'any_logout'],
        ['GET', '/portal/forgot', 'portal_forgot_page'],
        ['POST', '/portal/forgot', 'portal_forgot_post'],
        ['GET', '/portal/set-password/([a-f0-9]{64})', 'portal_set_password_page'],
        ['POST', '/portal/set-password/([a-f0-9]{64})', 'portal_set_password_post'],
        ['GET', '/portal', 'portal_home'],
        ['GET', '/portal/quotes/(\d+)', 'portal_quote'],
        ['POST', '/portal/quotes/(\d+)', 'portal_quote_respond'],
        ['GET', '/portal/documents/(\d+)', 'portal_download'],
        ['POST', '/portal/request', 'portal_request'],
        ['GET', '/portal/account', 'portal_account'],
        ['POST', '/portal/account', 'account_password_post'],
    ];
}

function portal_layout(string $title, string $body): string
{
    $u = current_user();
    $client = one('SELECT company FROM clients WHERE id = ?', [(int)($u['client_id'] ?? 0)]);
    $bar = '<div class="portal-bar"><div><strong>' . e($client['company'] ?? '') . '</strong> &middot; ' . e($u['name'] ?? '') . '</div><nav><a href="/portal">Overview</a><a href="/portal/account">Password</a>'
        . (setting('signin_client_2fa', 'optional') !== 'off' ? '<a href="/account/two-step">Two-step sign-in</a>' : '')
        . '<form method="post" action="/portal/logout" class="inline">' . csrf_field() . '<button class="linkish" type="submit">Sign out</button></form></nav></div>';
    return render_layout($title . ' | Client portal', '', '<section class="band"><div class="wrap">' . $bar . '<h1>' . e($title) . '</h1>' . flashes() . $body . '</div></section>', ['noindex' => true, 'section' => '/portal']);
}

function portal_login_page(): string
{
    no_store();
    $u = current_user();
    if ($u) redirect($u['role'] === 'admin' ? '/admin' : '/portal');
    return login_form('/portal/login', 'Client portal', 'Sign in to see your quotes, reports and assessment results.');
}

function portal_login_post(): string
{
    no_store();
    $r = attempt_login(p('email', 191), (string)($_POST['password'] ?? ''), 'client');
    if ($r === 'mfa') redirect('/signin/verify');
    if (is_string($r)) return login_form('/portal/login', 'Client portal', 'Sign in to see your quotes, reports and assessment results.', $r, p('email', 191));
    after_signin($r);
}

function portal_forgot_page(string $done = ''): string
{
    no_store();
    $body = $done
        ? '<p class="note">' . e($done) . '</p>'
        : '<form method="post" action="/portal/forgot" class="card">' . csrf_field() . field('email', 'Your account email', '', ['type' => 'email', 'required' => true, 'attrs' => ' autocomplete="email"'])
            . '<button class="btn" type="submit">Email me a link</button></form>';
    return render_layout('Reset your password | Call4Counsel', '', page_head('', 'Reset your password', 'We will email you a link to set a new password.')
        . '<section class="band"><div class="wrap narrow">' . $body . '</div></section>', ['noindex' => true]);
}

function portal_forgot_post(): string
{
    no_store();
    $email = strtolower(p('email', 191));
    // Same response whether or not the account exists, and rate-limited.
    if (valid_email($email) && !throttle('forgot-ip', ip_hash(), 5, 3600) && !throttle('forgot-user', $email, 3, 3600)) {
        $u = one('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
        if ($u) {
            $token = issue_invite((int)$u['id']);
            send_mail([$u['email']], 'Reset your Call4Counsel password',
                "Hello {$u['name']},\n\nUse this link to set a new password. It works once and expires in 7 days:\n\n" . base_url() . "/portal/set-password/$token\n\nIf you did not ask for this, you can ignore this email; your password has not changed.\n\nCall4Counsel");
            audit('password.reset_requested', $email);
        }
    }
    return portal_forgot_page('If that email belongs to an account, a link to reset the password is on its way. Check your inbox and spam folder.');
}

function set_password_page(string $token, array $err = []): string
{
    no_store();
    $u = user_by_invite($token);
    if (!$u) {
        return render_layout('Link expired | Call4Counsel', '', page_head('', 'This link has expired', 'Password links work once and expire after 7 days. Ask for a new one.')
            . '<section class="band"><div class="wrap actions"><a class="btn" href="/portal/forgot">Get a new link</a></div></section>', ['noindex' => true]);
    }
    $e = $err ? '<div class="flash err" role="alert">' . implode('<br>', array_map('e', $err)) . '</div>' : '';
    return render_layout('Set your password | Call4Counsel', '', page_head('', 'Set your password', 'Hello ' . $u['name'] . '. Choose a password of at least ' . min_password() . ' characters.')
        . '<section class="band"><div class="wrap narrow"><form method="post" action="/portal/set-password/' . e($token) . '" class="card">' . csrf_field() . $e
        . '<input type="hidden" name="username" value="' . e($u['email']) . '" autocomplete="username">'
        . field('password', 'New password', '', ['type' => 'password', 'required' => true, 'attrs' => ' autocomplete="new-password" minlength="' . min_password() . '"'])
        . field('confirm', 'Confirm password', '', ['type' => 'password', 'required' => true, 'attrs' => ' autocomplete="new-password"'])
        . '<button class="btn" type="submit">Save and sign in</button></form></div></section>', ['noindex' => true]);
}

function portal_set_password_page(string $token): string
{
    header('Referrer-Policy: no-referrer');
    return set_password_page($token);
}

function portal_set_password_post(string $token): string
{
    header('Referrer-Policy: no-referrer');
    $u = user_by_invite($token);
    if (!$u) return set_password_page($token);
    if ($pe = password_problem((string)($_POST['password'] ?? ''), (string)($_POST['confirm'] ?? ''), $u['email'])) return set_password_page($token, [$pe]);
    set_password((int)$u['id'], (string)$_POST['password']);
    $u = one('SELECT * FROM users WHERE id = ?', [$u['id']]);
    login_user($u);
    audit('password.set');
    flash('ok', 'Password saved. Welcome.');
    after_signin($u);
}

function portal_home(): string
{
    $u = require_role('client');
    $cid = (int)$u['client_id'];
    $quotes = all("SELECT * FROM quotes WHERE client_id = ? AND status <> 'draft' ORDER BY id DESC", [$cid]);
    $qh = '';
    foreach ($quotes as $qt) {
        [, , $total] = quote_total((int)$qt['id'], (float)$qt['tax_rate']);
        $qh .= '<tr><td><a href="/portal/quotes/' . (int)$qt['id'] . '">' . e($qt['number']) . '</a></td><td>' . e($qt['title']) . '</td><td>' . money($total, $qt['currency']) . '</td><td>' . badge($qt['status']) . '</td></tr>';
    }
    $qh = $qh ? '<div class="table-wrap"><table><thead><tr><th>Number</th><th>Title</th><th>Total</th><th>Status</th></tr></thead><tbody>' . $qh . '</tbody></table></div>' : '<p class="note">No quotes yet.</p>';
    $docs = all('SELECT * FROM documents WHERE client_id = ? ORDER BY id DESC', [$cid]);
    $dh = '';
    foreach ($docs as $d) $dh .= '<li><a href="/portal/documents/' . (int)$d['id'] . '">' . e($d['title']) . '</a> <span class="small">' . e($d['filename']) . ' &middot; ' . e(fmt_date($d['created_at'])) . '</span></li>';
    $dh = $dh ? '<ul class="doc-list">' . $dh . '</ul>' : '<p class="note">Reports and documents we share with you will appear here.</p>';
    $enq = all('SELECT * FROM enquiries WHERE client_id = ? ORDER BY id DESC LIMIT 20', [$cid]);
    $eh = '';
    foreach ($enq as $q) {
        $link = $q['type'] === 'assessment' && $q['token'] ? '<a href="/assessment/result/' . e($q['token']) . '">' . e($q['subject']) . '</a>' : e($q['subject']);
        $eh .= '<li>' . $link . ' <span class="small">' . e(fmt_date($q['created_at'])) . '</span></li>';
    }
    $eh = $eh ? '<ul class="doc-list">' . $eh . '</ul>' : '<p class="note">Nothing yet. <a href="/assessment">Take the AI &amp; process assessment</a>.</p>';
    $req = '<form method="post" action="/portal/request" class="card">' . csrf_field() . '<h3>Ask us for something</h3>'
        . field('subject', 'Subject', '', ['required' => true, 'attrs' => ' maxlength="200"']) . field('message', 'Details', '', ['type' => 'textarea', 'required' => true, 'attrs' => ' maxlength="5000"'])
        . '<button class="btn small" type="submit">Send request</button></form>';
    return portal_layout('Your client portal', '<div class="split"><div><h2>Quotes</h2>' . $qh . '<h2>Reports and documents</h2>' . $dh . '<h2>Your requests and assessments</h2>' . $eh . '</div><div>' . $req . '</div></div>');
}

function portal_own_quote(string $id): array
{
    $u = require_role('client');
    $qt = one("SELECT * FROM quotes WHERE id = ? AND client_id = ? AND status <> 'draft'", [(int)$id, (int)$u['client_id']]);
    if (!$qt) abort();
    return [$u, $qt];
}

function portal_quote(string $id): string
{
    [, $qt] = portal_own_quote($id);
    $lines = all('SELECT * FROM quote_lines WHERE quote_id = ? ORDER BY sort, id', [$qt['id']]);
    $h = '<div class="table-wrap"><table><thead><tr><th>Item</th><th>Qty</th><th>Unit price</th><th>Amount</th></tr></thead><tbody>';
    foreach ($lines as $l) $h .= '<tr><td>' . e($l['description']) . '</td><td>' . e((string)(float)$l['qty']) . '</td><td>' . money((float)$l['unit_price'], $qt['currency']) . '</td><td>' . money((float)$l['qty'] * (float)$l['unit_price'], $qt['currency']) . '</td></tr>';
    [$sub, $tax, $total] = quote_total((int)$qt['id'], (float)$qt['tax_rate']);
    $h .= '</tbody><tfoot><tr><td colspan="3">Subtotal</td><td>' . money($sub, $qt['currency']) . '</td></tr>'
        . ($tax > 0 ? '<tr><td colspan="3">Tax (' . e((string)(float)$qt['tax_rate']) . '%)</td><td>' . money($tax, $qt['currency']) . '</td></tr>' : '')
        . '<tr><td colspan="3"><strong>Total</strong></td><td><strong>' . money($total, $qt['currency']) . '</strong></td></tr></tfoot></table></div>';
    $expired = $qt['valid_until'] && $qt['valid_until'] < gmdate('Y-m-d');
    $respond = '';
    if ($qt['status'] === 'sent' && !$expired) {
        $respond = '<form method="post" action="/portal/quotes/' . (int)$qt['id'] . '" class="card">' . csrf_field() . '<h3>Your decision</h3>'
            . field('comment', 'Comment or purchase order number (optional)', '', ['type' => 'textarea', 'attrs' => ' maxlength="2000"'])
            . '<div class="actions"><button class="btn" name="decision" value="accepted" type="submit">Accept quote</button><button class="btn ghost" name="decision" value="declined" type="submit">Decline</button></div>'
            . '<p class="small">Accepting confirms you want to go ahead. We will be in touch to confirm next steps and invoicing.</p></form>';
    } elseif ($expired && $qt['status'] === 'sent') {
        $respond = '<p class="note">This quote has expired. <a href="/contact">Ask us to refresh it.</a></p>';
    }
    return portal_layout('Quote ' . $qt['number'], '<p>' . e($qt['title']) . ' &middot; ' . badge($qt['status']) . ($qt['valid_until'] ? ' &middot; Valid until ' . e(fmt_date($qt['valid_until'])) : '') . '</p>'
        . $h . ($qt['notes'] ? '<h3>Notes and terms</h3><div class="prose">' . md($qt['notes']) . '</div>' : '') . $respond);
}

function portal_quote_respond(string $id): never
{
    [$u, $qt] = portal_own_quote($id);
    $d = p('decision', 10);
    if ($qt['status'] !== 'sent' || !in_array($d, ['accepted', 'declined'], true) || ($qt['valid_until'] && $qt['valid_until'] < gmdate('Y-m-d'))) {
        flash('err', 'This quote can no longer be changed.');
        redirect('/portal/quotes/' . (int)$qt['id']);
    }
    $comment = p('comment', 2000);
    q('UPDATE quotes SET status = ?, client_comment = ?, responded_at = ?, updated_at = ? WHERE id = ?', [$d, $comment, now(), now(), $qt['id']]);
    audit('quote.' . $d, '#' . $qt['id']);
    send_mail(notify_recipients(), "Quote {$qt['number']} $d by client", "{$u['name']} ({$u['email']}) has $d quote {$qt['number']} \"{$qt['title']}\".\n\nComment: " . ($comment ?: '-') . "\n\n" . base_url() . '/admin/quotes/' . (int)$qt['id'], $u['email']);
    flash('ok', $d === 'accepted' ? 'Thank you. We have your acceptance and will be in touch shortly.' : 'Thank you for letting us know.');
    redirect('/portal/quotes/' . (int)$qt['id']);
}

function portal_download(string $id): never
{
    $u = require_role('client');
    $d = one('SELECT * FROM documents WHERE id = ? AND client_id = ?', [(int)$id, (int)$u['client_id']]);
    if (!$d) abort();
    audit('document.download', '#' . $d['id']);
    send_document($d);
}

function portal_request(): never
{
    $u = require_role('client');
    $subject = p('subject', 200);
    $msg = p('message', 5000);
    if ($subject === '' || $msg === '') {
        flash('err', 'Add a subject and some details.');
        redirect('/portal');
    }
    if (throttle('portal-request', (string)$u['id'], 10, 3600)) {
        flash('err', 'You have sent several requests in the last hour. Please email us instead.');
        redirect('/portal');
    }
    $client = one('SELECT * FROM clients WHERE id = ?', [$u['client_id']]);
    save_enquiry('portal', ['name' => $u['name'], 'email' => $u['email'], 'company' => $client['company'] ?? '', 'phone' => ''], $subject, $msg);
    flash('ok', 'Request sent. We will reply soon.');
    redirect('/portal');
}

function portal_account(): string
{
    require_role('client');
    return portal_layout('My password', password_form('/portal/account', 'Change password'));
}
