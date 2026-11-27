<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('APP', __DIR__);
// Tests define C4C_TEST_CONFIG before loading this file; everywhere else app/config.php is used.
$CONFIG = defined('C4C_TEST_CONFIG') ? C4C_TEST_CONFIG : (is_file(APP . '/config.php') ? require APP . '/config.php' : null);
define('STORAGE', is_array($CONFIG) && isset($CONFIG['storage']) ? (string)$CONFIG['storage'] : ROOT . '/storage');

function cfg(string $k, mixed $default = null): mixed
{
    global $CONFIG;
    return is_array($CONFIG) && array_key_exists($k, $CONFIG) ? $CONFIG[$k] : $default;
}

// Errors are never shown to visitors, not even in debug mode: they go to the log only.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE . '/logs/php-error.log');
mb_internal_encoding('UTF-8');
date_default_timezone_set((string)cfg('timezone', 'UTC'));

require APP . '/lib/helpers.php';
require APP . '/lib/db.php';
require APP . '/lib/auth.php';
require APP . '/lib/mail.php';
require APP . '/lib/markdown.php';
require APP . '/lib/view.php';
require APP . '/lib/assessment.php';
require APP . '/lib/crypto.php';
require APP . '/lib/rbac.php';
require APP . '/lib/migrate.php';
require APP . '/lib/system.php';
require APP . '/lib/oidc.php';

define('APP_VERSION', trim((string)@file_get_contents(ROOT . '/VERSION')) ?: '0.0.0');

// The config file holds the database password and the app key: only the hosting
// account (which PHP runs as) may read it. Tighten it if a deploy or upload loosened it.
if (is_file(APP . '/config.php') && (fileperms(APP . '/config.php') & 0o077)) @chmod(APP . '/config.php', 0o600);

// Framing policy from the first moment of every web request (also sent with the full set in send_security_headers()).
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: frame-ancestors 'none'");
}

foreach (['logs', 'sessions', 'uploads'] as $d) {
    if (!is_dir(STORAGE . "/$d")) @mkdir(STORAGE . "/$d", 0750, true);
}
