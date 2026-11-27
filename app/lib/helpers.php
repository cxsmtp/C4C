<?php
declare(strict_types=1);

/** HTML-escape for text and attribute contexts. */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (cfg('trust_proxy_https', false) && (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) return true;
    return (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function base_url(): string
{
    return rtrim((string)cfg('base_url', (is_https() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost')), '/');
}

function send_security_headers(): void
{
    header_remove('X-Powered-By');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; "
        . "font-src 'self'; img-src 'self' data:; connect-src 'self'; form-action 'self'; "
        . "frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    if (is_https()) {
        $age = (int)setting('https_hsts_max_age', '31536000');
        if ($age > 0) header('Strict-Transport-Security: max-age=' . $age . (setting('https_hsts_subdomains', '1') === '1' ? '; includeSubDomains' : '') . (setting('https_hsts_preload') === '1' ? '; preload' : ''));
    }
    header('Cross-Origin-Resource-Policy: same-origin');
}

function no_store(): void
{
    header('Cache-Control: no-store, private');
    header('X-Robots-Tag: noindex, nofollow');
}

function session_cookie_name(): string
{
    return is_https() ? '__Host-c4c' : 'c4c';
}

/** Sessions start only when needed (a form, a sign-in or a quote list), so plain visits set no cookie. */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE || headers_sent()) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '7200');
    $dir = STORAGE . '/sessions';
    if (is_dir($dir) && is_writable($dir)) session_save_path($dir);
    session_name(session_cookie_name());
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** True when the browser says this request came from a page on this site. */
function same_site_request(): bool
{
    $site = (string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
    $origin = rtrim((string)($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
    if ($site !== '') return $site === 'same-origin' && ($origin === '' || $origin === base_url());
    return $origin !== '' && $origin === base_url();
}

function csrf_check(): void
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    // Adding to the quote list only changes the visitor's own list; it needs no session token.
    if ($path === '/quote/add' && same_site_request()) return;
    $sent = (string)($_POST['_csrf'] ?? '');
    // Browsers send Origin on cross-site posts; it must be this site.
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $originBad = $origin !== '' && $origin !== 'null' && rtrim($origin, '/') !== base_url();
    if ($originBad || $sent === '' || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(400);
        echo render_layout('Session expired', '', page_head('', 'Please try again',
            'Your session expired or the form was opened in another tab. Go back, refresh the page and send it again.'), ['noindex' => true]);
        exit;
    }
}

/** Honeypot and minimum fill time for public forms. Returns true if it looks automated. */
function bot_fields(): string
{
    $_SESSION['form_ts'] = time();
    return '<div class="hp" aria-hidden="true"><label>Leave this empty<input name="website" tabindex="-1" autocomplete="off"></label></div>';
}

function looks_like_bot(): bool
{
    if (trim((string)($_POST['website'] ?? '')) !== '') return true;
    $ts = (int)($_SESSION['form_ts'] ?? 0);
    return $ts === 0 || (time() - $ts) < 3;
}

function redirect(string $to, int $code = 303): never
{
    // Only same-site relative paths.
    if (!str_starts_with($to, '/') || str_starts_with($to, '//')) $to = '/';
    header('Location: ' . $to, true, $code);
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function flashes(): string
{
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as [$t, $m]) {
        $out .= '<div class="flash ' . e($t) . '" role="status">' . e($m) . '</div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

function p(string $k, int $max = 5000): string
{
    $v = $_POST[$k] ?? '';
    if (!is_string($v)) return '';
    $v = trim(str_replace("\0", '', $v));
    return mb_substr($v, 0, $max);
}

function p_list(string $k, array $allowed): array
{
    $v = $_POST[$k] ?? [];
    if (!is_array($v)) $v = [$v];
    return array_values(array_intersect(array_map('strval', $v), $allowed));
}

function g(string $k, int $max = 200): string
{
    $v = $_GET[$k] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function valid_email(string $s): bool
{
    return strlen($s) <= 191 && filter_var($s, FILTER_VALIDATE_EMAIL) !== false && !preg_match('/[\r\n]/', $s);
}

function slugify(string $s): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s) ?? '', '-'));
    return substr($s, 0, 120) ?: 'item';
}

function now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function fmt_date(?string $d, string $f = 'j M Y'): string
{
    if (!$d) return '';
    $t = strtotime($d . ' UTC');
    return $t ? date($f, $t) : '';
}

function ip_hash(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0');
    return hash_hmac('sha256', $ip, (string)cfg('app_key', 'dev'));
}

/** Records one event and reports whether the limit for the window is exceeded. */
function throttle(string $bucket, string $key, int $limit, int $window, bool $record = true): bool
{
    $k = hash('sha256', $key);
    $since = time() - $window;
    if (random_int(1, 50) === 1) q('DELETE FROM throttle WHERE ts < ?', [time() - 86400]);
    $n = (int)val('SELECT COUNT(*) FROM throttle WHERE bucket = ? AND k = ? AND ts > ?', [$bucket, $k, $since]);
    if ($record) q('INSERT INTO throttle (bucket, k, ts) VALUES (?, ?, ?)', [$bucket, $k, time()]);
    return $n >= $limit;
}

function abort(int $code = 404): never
{
    http_response_code($code);
    if ($code === 403) {
        try { audit('refused: ' . parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), 'Your role does not allow that.'); } catch (Throwable) {}
    }
    $msg = $code === 403 ? ['Not allowed', 'Your role does not allow that. Ask an Owner if you need it.'] : ['Off the map', 'We could not find that page. It may have moved or never existed.'];
    echo render_layout($msg[0] . ' | ' . site_name(), '', page_head('', $msg[0], $msg[1])
        . '<section class="band"><div class="wrap actions"><a class="btn" href="/">Back to home</a><a class="btn ghost" href="/contact">Contact us</a></div></section>', ['noindex' => true]);
    exit;
}

function money(float $n, string $cur): string
{
    return e($cur) . ' ' . number_format($n, 2);
}

/**
 * Appends to the audit log. Each row carries the hash of the row before it, so any
 * edit or deletion breaks the chain and shows up in Settings > Audit log.
 */
function audit(string $action, string $detail = '', ?int $userId = null): void
{
    $userId ??= (current_user()['id'] ?? null);
    $row = ['user_id' => $userId, 'action' => mb_substr($action, 0, 80), 'detail' => mb_substr($detail, 0, 255), 'ip_hash' => ip_hash(), 'created_at' => now()];
    try {
        $prev = (string)(val('SELECT hash FROM audit ORDER BY id DESC LIMIT 1') ?? '');
        $row['prev_hash'] = $prev;
        $row['hash'] = audit_hash($prev, $row);
    } catch (Throwable) {
        // Before the audit chain migration ran.
    }
    insert('audit', $row);
}

function audit_hash(string $prev, array $r): string
{
    return hash_hmac('sha256', implode('|', [$prev, (string)$r['user_id'], $r['action'], (string)$r['detail'], (string)$r['ip_hash'], $r['created_at']]), (string)cfg('app_key', 'dev'));
}

/** Returns [ok, rows checked, first broken id]. */
function audit_verify(): array
{
    $prev = null;
    $n = 0;
    foreach (q('SELECT * FROM audit WHERE hash IS NOT NULL ORDER BY id') as $r) {
        $n++;
        if ($prev !== null && $r['prev_hash'] !== $prev) return [false, $n, (int)$r['id']];
        if (!hash_equals(audit_hash((string)$r['prev_hash'], $r), (string)$r['hash'])) return [false, $n, (int)$r['id']];
        $prev = $r['hash'];
    }
    return [true, $n, null];
}

function log_line(string $file, string $line): void
{
    @file_put_contents(STORAGE . '/logs/' . $file, '[' . now() . '] ' . str_replace(["\r", "\n"], ' ', $line) . "\n", FILE_APPEND | LOCK_EX);
}
