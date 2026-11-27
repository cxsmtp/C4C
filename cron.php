<?php
// Scheduled jobs. Run hourly from hPanel > Advanced > Cron Jobs:
//   /opt/alt/php83/usr/bin/php -d extension=sodium.so /path/to/cron.php
// (Hostinger's default /usr/bin/php does not load sodium, which backups need.)
// Web access is refused here and blocked in .htaccess.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/app/bootstrap.php';
if ($CONFIG === null || !is_file(STORAGE . '/installed.lock')) {
    fwrite(STDERR, "Not installed yet.\n");
    exit(1);
}
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
try {
    foreach (run_scheduled_jobs() as $line) echo gmdate('c'), ' ', $line, "\n";
} catch (Throwable $ex) {
    log_line('app.log', 'cron failed: ' . $ex->getMessage());
    fwrite(STDERR, 'cron failed: ' . $ex->getMessage() . "\n");
    exit(1);
}
