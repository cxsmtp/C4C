<?php
// Router for PHP's built-in server in tests and CI. Production uses .htaccess.
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/assets/[A-Za-z0-9/._-]+$#', $p) && is_file(dirname(__DIR__) . $p)) return false;
require dirname(__DIR__) . '/index.php';
