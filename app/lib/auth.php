<?php
declare(strict_types=1);

function min_password(): int
{
    return max(12, min(64, (int)setting('signin_min_password', '12')));
}

/** Kept for older call sites. */
const MIN_PASSWORD = 12;

function current_user(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    $u = null;
    if (session_status() !== PHP_SESSION_ACTIVE) return null;
    $id = (int)($_SESSION['uid'] ?? 0);
    if (!$id) return null;
    $idle = max(5, (int)setting('signin_idle_minutes', '30')) * 60;
    $absolute = max(1, (int)setting('signin_absolute_hours', '12')) * 3600;
    if (time() - (int)($_SESSION['seen'] ?? 0) > $idle || time() - (int)($_SESSION['since'] ?? 0) > $absolute) {
        logout_user();
        return null;
    }
    $row = one('SELECT * FROM users WHERE id = ? AND active = 1', [$id]);
    // A password change, "sign out everywhere" or a reset invalidates other sessions.
    if (!$row || !hash_equals((string)($_SESSION['sv'] ?? ''), session_stamp($row))) {
        logout_user();
        return null;
    }
    $_SESSION['seen'] = time();
    return $u = $row;
}

function session_stamp(array $user): string
{
    return hash_hmac('sha256', $user['id'] . '|' . $user['password_hash'] . '|' . ($user['session_version'] ?? 1), (string)cfg('app_key', 'dev'));
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    unset($_SESSION['mfa_uid'], $_SESSION['mfa_at']);
    $_SESSION['uid'] = (int)$user['id'];
    $_SESSION['seen'] = $_SESSION['since'] = time();
    $_SESSION['sv'] = session_stamp($user);
    unset($_SESSION['csrf']);
    q('UPDATE users SET last_login = ?, locked_until = NULL WHERE id = ?', [now(), $user['id']]);
}

function logout_user(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    unset($_SESSION['uid'], $_SESSION['seen'], $_SESSION['since'], $_SESSION['sv'], $_SESSION['mfa_uid'], $_SESSION['mfa_at']);
    session_regenerate_id(true);
}

/** Ends every session of a user, everywhere. */
function revoke_sessions(int $userId): void
{
    q('UPDATE users SET session_version = session_version + 1 WHERE id = ?', [$userId]);
}

function staff_domain_allowed(string $email): bool
{
    $domains = array_filter(array_map('trim', explode(',', strtolower(setting('signin_staff_domains')))));
    if (!$domains) return true;
    return in_array(substr(strrchr($email, '@') ?: '', 1), $domains, true);
}

/**
 * Checks a password. Returns the user, the string 'mfa' when a second step is needed,
 * or an error message.
 */
function attempt_login(string $email, string $password, string $role): array|string
{
    $email = strtolower(trim($email));
    $generic = 'That email and password do not match an active account.';
    $attempts = max(3, (int)setting('signin_lockout_attempts', '5'));
    $minutes = max(1, (int)setting('signin_lockout_minutes', '15'));
    if (throttle('login-ip', ip_hash(), 30, 900, false)) return 'Too many attempts from your network. Wait 15 minutes and try again.';
    $user = one('SELECT * FROM users WHERE email = ? AND role = ? AND active = 1', [$email, $role]);
    if ($user && $user['locked_until'] && strtotime($user['locked_until'] . ' UTC') > time()) {
        return "This account is locked after too many failed attempts. Try again in $minutes minutes, or ask an administrator to unlock it.";
    }
    if ($role === 'admin' && setting('sso_enabled') === '1' && setting('sso_password_login', '1') !== '1') {
        return 'Sign in with your company account (single sign-on).';
    }
    // Verify against a dummy hash when the user does not exist, so timing does not reveal accounts.
    $hash = ($user['password_hash'] ?? '') ?: '$2y$10$ks9odqrCHp3L0UIwP7wvAeh.fbaMsQjK8afNTIuSgzhjJz6N7Mszm';
    $ok = password_verify($password, $hash) && $user && $user['password_hash'];
    if (!$ok) {
        throttle('login-ip', ip_hash(), 0, 900);
        if ($user && throttle('login-user', (string)$user['id'], $attempts - 1, $minutes * 60)) {
            q('UPDATE users SET locked_until = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() + $minutes * 60), $user['id']]);
            audit('signin.locked', $email, (int)$user['id']);
        }
        audit('signin.failed', $role . ' ' . $email);
        return $generic;
    }
    if ($role === 'admin' && !staff_domain_allowed($email)) return 'Staff accounts must use an approved company email domain.';
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        $user = one('SELECT * FROM users WHERE id = ?', [$user['id']]);
    }
    if ((int)$user['totp_enabled'] === 1) {
        session_regenerate_id(true);
        $_SESSION['mfa_uid'] = (int)$user['id'];
        $_SESSION['mfa_at'] = time();
        return 'mfa';
    }
    login_user($user);
    audit('signin', $role);
    return $user;
}

/** Second step: a code from the authenticator app or a one-time recovery code. */
function attempt_mfa(string $code): array|string
{
    $uid = (int)($_SESSION['mfa_uid'] ?? 0);
    if (!$uid || time() - (int)($_SESSION['mfa_at'] ?? 0) > 300) return 'That took too long. Sign in again.';
    if (throttle('mfa', (string)$uid, 5, 300)) return 'Too many codes tried. Wait 5 minutes and sign in again.';
    $u = one('SELECT * FROM users WHERE id = ? AND active = 1', [$uid]);
    if (!$u) return 'Sign in again.';
    $code = trim($code);
    $step = totp_verify(unseal($u['totp_secret']), $code, (int)$u['totp_last_step']);
    if ($step !== null) {
        q('UPDATE users SET totp_last_step = ? WHERE id = ?', [$step, $uid]);
    } else {
        $codes = json_decode((string)$u['recovery_codes'], true) ?: [];
        $h = recovery_hash($uid, $code);
        $match = null;
        foreach ($codes as $i => $stored) if (hash_equals((string)$stored, $h)) { $match = $i; break; }
        if ($match === null) {
            throttle('login-user', (string)$uid, 99, 900);
            audit('signin.mfa_failed', '', $uid);
            return 'That code is not right.';
        }
        unset($codes[$match]);
        q('UPDATE users SET recovery_codes = ? WHERE id = ?', [json_encode(array_values($codes)), $uid]);
        audit('signin.recovery_code_used', count($codes) . ' left', $uid);
    }
    login_user($u);
    audit('signin', $u['role'] . ' with two-step');
    return $u;
}

function needs_2fa_enrolment(array $u): bool
{
    if ((int)$u['totp_enabled'] === 1 || !empty($_SESSION['sso'])) return false;
    if ($u['role'] === 'admin') return setting('signin_require_2fa', '1') === '1';
    return setting('signin_client_2fa', 'optional') === 'required';
}

function require_role(string $role): array
{
    $u = current_user();
    if (!$u || $u['role'] !== $role) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
        redirect($role === 'admin' ? '/admin/login' : '/portal/login');
    }
    no_store();
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (needs_2fa_enrolment($u) && !in_array($path, ['/account/two-step', '/admin/logout', '/portal/logout'], true)) {
        flash('err', 'Set up two-step sign-in to continue. It protects your account even if your password leaks.');
        redirect('/account/two-step');
    }
    return $u;
}

function password_problem(string $pw, string $confirm, string $email = ''): ?string
{
    $min = min_password();
    if (strlen($pw) < $min) return "Use at least $min characters.";
    if (strlen($pw) > 200) return 'That password is too long.';
    if ($pw !== $confirm) return 'The two passwords do not match.';
    $lower = strtolower($pw);
    if (preg_match('/^(.)\1+$/', $pw) || count(array_unique(str_split($pw))) < 5) return 'Choose a less predictable password.';
    foreach (['password', 'qwerty', '123456', 'call4counsel', 'letmein', 'welcome', 'admin'] as $w) {
        if (str_contains($lower, $w) && strlen($pw) < $min + 6) return 'Avoid common words such as "' . $w . '", or make the password longer.';
    }
    $local = strtolower((string)strstr($email, '@', true));
    if (strlen($local) >= 3 && str_contains($lower, $local)) return 'Do not include your email name in the password.';
    return null;
}

function set_password(int $userId, string $pw): void
{
    q('UPDATE users SET password_hash = ?, invite_hash = NULL, invite_expires = NULL, password_changed_at = ?, session_version = session_version + 1 WHERE id = ?',
        [password_hash($pw, PASSWORD_DEFAULT), now(), $userId]);
}

/** Creates a one-time link for setting a password. Returns the raw token. */
function issue_invite(int $userId): string
{
    $token = bin2hex(random_bytes(32));
    q('UPDATE users SET invite_hash = ?, invite_expires = ? WHERE id = ?', [hash('sha256', $token), gmdate('Y-m-d H:i:s', time() + 7 * 86400), $userId]);
    return $token;
}

function user_by_invite(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $u = one('SELECT * FROM users WHERE invite_hash = ? AND active = 1', [hash('sha256', $token)]);
    if (!$u || strtotime($u['invite_expires'] . ' UTC') < time()) return null;
    return $u;
}
