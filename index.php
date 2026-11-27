<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$path = $path === '/' ? '/' : rtrim($path, '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

send_security_headers();

if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    http_response_code(405);
    exit;
}

if ($CONFIG === null) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Call4Counsel is being set up. Copy app/config.sample.php to app/config.php and fill it in.\n";
    exit;
}

if ($method === 'POST' || isset($_COOKIE[session_cookie_name()])) start_session();
if ($method === 'POST') csrf_check();

$installed = is_file(STORAGE . '/installed.lock');
if (!$installed && $path !== '/install') {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Retry-After: 3600');
    echo "Call4Counsel is being set up. Please check back shortly.\n";
    exit;
}
if ($installed) ensure_schema_current();
if ($path === '/health') health_endpoint();
if ($path === '/.well-known/security.txt') security_txt();
if (setting('maintenance_mode') === '1' && !preg_match('#^/(admin|portal/login|account|signin|assets)#', $path) && (current_user()['role'] ?? '') !== 'admin') {
    http_response_code(503);
    header('Retry-After: 1800');
    echo render_layout('Back shortly | ' . brand_name(), '', page_head('', 'Back shortly', setting('maintenance_message', 'We are making improvements and will be back shortly.')), ['noindex' => true]);
    exit;
}

require APP . '/routes/public.php';
require APP . '/routes/admin.php';
require APP . '/routes/portal.php';
require APP . '/routes/install.php';
require APP . '/routes/account.php';
require APP . '/routes/settings.php';

$routes = array_merge(public_routes(), account_routes(), settings_routes(), admin_routes(), portal_routes(), install_routes());

try {
    foreach ($routes as [$m, $pattern, $handler]) {
        if ($m !== $method && !($m === 'GET' && $method === 'HEAD')) continue;
        if (preg_match('#^' . $pattern . '$#', $path, $args)) {
            array_shift($args);
            $out = $handler(...$args);
            if (is_string($out)) echo $out;
            exit;
        }
    }
    abort(404);
} catch (Throwable $ex) {
    error_log(get_class($ex) . ': ' . $ex->getMessage() . ' at ' . $ex->getFile() . ':' . $ex->getLine());
    http_response_code(500);
    echo render_layout('Something went wrong', '', page_head('', 'Something went wrong', 'Please try again in a moment. If it keeps happening, email us.'), ['noindex' => true]);
}
