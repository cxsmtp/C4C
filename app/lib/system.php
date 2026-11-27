<?php
declare(strict_types=1);

function brand_name(): string
{
    $n = setting('brand_name', 'Call4Counsel');
    return $n !== '' ? $n : 'Call4Counsel';
}

function brand_accent(): string
{
    $c = setting('brand_accent', '#1d4ed8');
    return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtolower($c) : '#1d4ed8';
}

function latest_migration(): string
{
    $keys = array_keys(migrations());
    return end($keys);
}

/** Applies pending database updates once per release, under a lock. Cheap when nothing is pending. */
function ensure_schema_current(): void
{
    $marker = STORAGE . '/schema.version';
    if (@file_get_contents($marker) === latest_migration()) return;
    $applied = run_migrations();
    if ($applied) {
        log_line('app.log', 'database updated: ' . implode(', ', $applied));
        try { audit('system.migrated', implode(', ', $applied), null); } catch (Throwable) {}
    }
    @file_put_contents($marker, latest_migration(), LOCK_EX);
}

// ---------------- health ----------------

/** Each check: [area, label, status ok|warn|fail, detail]. */
function health_checks(): array
{
    $c = [];
    $add = function (string $area, string $label, string $status, string $detail) use (&$c) { $c[] = compact('area', 'label', 'status', 'detail'); };

    $add('Runtime', 'PHP version', version_compare(PHP_VERSION, '8.1', '>=') ? 'ok' : 'fail', PHP_VERSION . ' (8.1 or later required, 8.3 recommended)');
    $missing = array_filter(['pdo', is_sqlite() ? 'pdo_sqlite' : 'pdo_mysql', 'sodium', 'mbstring', 'fileinfo', 'openssl', 'json'], fn($e) => !extension_loaded($e));
    $add('Runtime', 'PHP extensions', $missing ? 'fail' : 'ok', $missing ? 'Missing: ' . implode(', ', $missing) : 'All required extensions are loaded');
    $add('Runtime', 'Error display', ini_get('display_errors') && ini_get('display_errors') !== '0' ? 'warn' : 'ok', cfg('debug') ? 'debug is on in app/config.php; turn it off in production' : 'Errors are logged, not shown');
    $add('Runtime', 'OPcache', function_exists('opcache_get_status') && @opcache_get_status(false) ? 'ok' : 'warn', function_exists('opcache_get_status') && @opcache_get_status(false) ? 'Enabled' : 'Not enabled; turn it on in hPanel for faster pages');

    $t = microtime(true);
    try {
        val('SELECT 1');
        $ms = (int)round((microtime(true) - $t) * 1000);
        $add('Database', 'Connection', $ms < 200 ? 'ok' : 'warn', (is_sqlite() ? 'SQLite' : 'MySQL ' . db()->getAttribute(PDO::ATTR_SERVER_VERSION)) . ", {$ms} ms");
    } catch (Throwable $ex) {
        $add('Database', 'Connection', 'fail', 'Cannot connect');
    }
    $pending = count(pending_migrations());
    $add('Database', 'Schema', $pending ? 'warn' : 'ok', $pending ? "$pending update(s) pending" : 'Up to date (' . latest_migration() . ')');

    foreach (['storage' => STORAGE, 'uploads' => STORAGE . '/uploads', 'backups' => backup_dir(), 'logs' => STORAGE . '/logs'] as $n => $d) {
        $add('Storage', ucfirst($n) . ' folder', is_dir($d) && is_writable($d) ? 'ok' : 'fail', is_dir($d) && is_writable($d) ? 'Writable' : "$d is not writable");
    }
    $free = @disk_free_space(STORAGE);
    if ($free !== false) $add('Storage', 'Free disk space', $free > 1e9 ? 'ok' : ($free > 2e8 ? 'warn' : 'fail'), number_format($free / 1e9, 1) . ' GB free');

    $cfgPerm = @fileperms(APP . '/config.php');
    $add('Security', 'Config file permissions', $cfgPerm !== false && ($cfgPerm & 0o007) === 0 ? 'ok' : 'warn', $cfgPerm !== false ? sprintf('%o', $cfgPerm & 0o777) . ' (600 or 640 recommended)' : 'Not found');
    $add('Security', 'Application key', strlen((string)cfg('app_key')) >= 32 && cfg('app_key') !== 'CHANGE-ME' ? 'ok' : 'fail', strlen((string)cfg('app_key')) >= 32 ? 'Set' : 'app_key must be a long random value');
    $add('Security', 'Install key removed', (string)cfg('install_key', '') === '' ? 'ok' : 'warn', (string)cfg('install_key', '') === '' ? 'Removed' : 'Remove install_key from app/config.php');
    $add('Security', 'HTTPS', str_starts_with(base_url(), 'https://') ? 'ok' : 'fail', base_url());
    $staff2fa = (int)val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1 AND totp_enabled = 0 AND (sso_subject IS NULL OR sso_subject = '')");
    $add('Security', 'Two-step sign-in for staff', $staff2fa ? 'warn' : 'ok', $staff2fa ? "$staff2fa staff account(s) without two-step sign-in" : 'Every staff account uses it');
    [$chainOk, $rows] = audit_verify();
    $add('Security', 'Audit log integrity', $chainOk ? 'ok' : 'fail', $chainOk ? "$rows entries, chain intact" : 'The chain is broken: an entry was changed or removed');

    $smtp = (bool)(setting('smtp_host') ?: cfg('smtp_host'));
    $lastTest = setting('mail_last_test');
    $add('Email', 'Sending', $smtp ? ($lastTest && str_starts_with($lastTest, 'ok') ? 'ok' : 'warn') : 'warn', $smtp ? ($lastTest ? 'Last test: ' . $lastTest : 'SMTP set but not tested; send a test email') : 'Using PHP mail(); set SMTP for reliable delivery');
    $fail = (int)val("SELECT COUNT(*) FROM mail_log WHERE status = 'failed' AND created_at > ?", [gmdate('Y-m-d H:i:s', time() - 86400)]);
    $add('Email', 'Failures in the last 24 hours', $fail ? 'warn' : 'ok', (string)$fail);

    $last = last_backup();
    $age = $last ? (time() - $last['time']) / 3600 : null;
    $add('Backups', 'Latest backup', $age === null ? 'warn' : ($age < 30 ? 'ok' : ($age < 72 ? 'warn' : 'fail')), $last ? $last['name'] . ', ' . round($age, 1) . ' hours ago' : 'No backup yet');
    $cron = (int)setting('cron_last_run', '0');
    $add('Backups', 'Scheduled jobs (cron)', $cron && time() - $cron < 7200 ? 'ok' : 'warn', $cron ? 'Last ran ' . gmdate('Y-m-d H:i', $cron) . ' UTC' : 'Never ran. Add the cron job shown on this page.');
    return $c;
}

function health_summary(array $checks): string
{
    $s = array_column($checks, 'status');
    return in_array('fail', $s, true) ? 'fail' : (in_array('warn', $s, true) ? 'warn' : 'ok');
}

/** Public /health: just enough for an uptime monitor, nothing sensitive. */
function health_endpoint(): never
{
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    $ok = true;
    try { val('SELECT 1'); } catch (Throwable) { $ok = false; }
    http_response_code($ok ? 200 : 503);
    echo json_encode(['status' => $ok ? 'ok' : 'down', 'version' => APP_VERSION, 'time' => gmdate('c')]);
    exit;
}

function security_txt(): never
{
    header('Content-Type: text/plain; charset=utf-8');
    $mail = setting('help_support_email', contact_email());
    echo "Contact: mailto:$mail\nExpires: " . gmdate('Y-m-d\T00:00:00\Z', strtotime('+1 year')) . "\nPreferred-Languages: en\nCanonical: " . base_url() . "/.well-known/security.txt\nPolicy: " . base_url() . "/terms\n";
    exit;
}

// ---------------- backups ----------------

function backup_dir(): string
{
    $d = STORAGE . '/backups';
    if (!is_dir($d)) @mkdir($d, 0750, true);
    return $d;
}

function backup_tables(): array
{
    return ['settings', 'roles', 'teams', 'users', 'clients', 'categories', 'items', 'posts', 'case_studies', 'jobs', 'pages',
        'enquiries', 'quotes', 'quote_lines', 'documents', 'audit', 'mail_log', 'schema_migrations'];
}

/**
 * Writes a compressed, encrypted snapshot of every table (JSON, portable between MySQL
 * and SQLite). Shared documents stay in storage/uploads and are listed in the manifest.
 */
function create_backup(string $reason = 'manual'): string
{
    $data = ['format' => 'c4c-backup-1', 'version' => APP_VERSION, 'schema' => latest_migration(), 'created_at' => now(), 'reason' => $reason, 'tables' => []];
    foreach (backup_tables() as $t) {
        if (table_exists($t)) $data['tables'][$t] = all("SELECT * FROM $t");
    }
    $data['uploads'] = array_map(fn($d) => ['id' => $d['id'], 'stored' => $d['stored'], 'size' => $d['size']], $data['tables']['documents'] ?? []);
    $gz = gzencode(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 6);
    $enc = setting('backup_encrypt', '1') === '1';
    $name = 'c4c-' . gmdate('Ymd-His') . '-' . $reason . ($enc ? '.c4cb' : '.json.gz');
    $path = backup_dir() . '/' . $name;
    file_put_contents($path, $enc ? seal($gz) : $gz, LOCK_EX);
    @chmod($path, 0640);
    prune_backups();
    log_line('app.log', "backup created: $name");
    return $name;
}

function list_backups(): array
{
    $out = [];
    foreach (glob(backup_dir() . '/c4c-*') ?: [] as $f) {
        $out[] = ['name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f)];
    }
    usort($out, fn($a, $b) => $b['time'] <=> $a['time']);
    return $out;
}

function last_backup(): ?array
{
    return list_backups()[0] ?? null;
}

function backup_path(string $name): string
{
    if (!preg_match('/^c4c-\d{8}-\d{6}-[a-z-]+\.(c4cb|json\.gz)$/', $name)) throw new RuntimeException('bad backup name');
    $p = backup_dir() . '/' . $name;
    if (!is_file($p)) throw new RuntimeException('backup not found');
    return $p;
}

function prune_backups(): void
{
    $keep = max(3, (int)setting('backup_keep', '14'));
    foreach (array_slice(list_backups(), $keep) as $b) @unlink(backup_dir() . '/' . $b['name']);
}

function read_backup(string $name): array
{
    $raw = file_get_contents(backup_path($name));
    $gz = str_ends_with($name, '.c4cb') ? unseal($raw) : $raw;
    $json = $gz !== '' ? @gzdecode($gz) : false;
    $data = $json !== false ? json_decode($json, true) : null;
    if (!is_array($data) || ($data['format'] ?? '') !== 'c4c-backup-1') throw new RuntimeException('This backup cannot be read with the current app key.');
    return $data;
}

/** Replaces all data with the backup's. A safety backup is taken first. */
function restore_backup(string $name): string
{
    $data = read_backup($name);
    $safety = create_backup('pre-restore');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($data['tables'] as $t => $rows) {
            // The audit log is append-only: a restore never rewinds it.
            if ($t === 'audit' || !in_array($t, backup_tables(), true) || !table_exists($t)) continue;
            $pdo->exec("DELETE FROM $t");
            foreach ($rows as $r) insert($t, $r);
        }
        $pdo->commit();
        settings_cache_reset();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    @unlink(STORAGE . '/schema.version');
    return $safety;
}

// ---------------- scheduled jobs (cron.php) ----------------

function run_scheduled_jobs(): array
{
    $done = [];
    ensure_schema_current();
    $last = last_backup();
    if (!$last || time() - $last['time'] > 23 * 3600) $done[] = 'backup ' . create_backup('daily');
    q('DELETE FROM throttle WHERE ts < ?', [time() - 86400]);
    $done[] = 'throttle cleaned';
    if (($d = (int)setting('retention_enquiry_days', '730')) > 0) {
        $n = q("DELETE FROM enquiries WHERE created_at < ? AND status IN ('closed', 'spam') AND client_id IS NULL", [gmdate('Y-m-d H:i:s', time() - $d * 86400)])->rowCount();
        if ($n) { $done[] = "$n old enquiries removed"; audit('retention.enquiries', "$n removed", null); }
    }
    if (($d = (int)setting('retention_mail_days', '180')) > 0) q('DELETE FROM mail_log WHERE created_at < ?', [gmdate('Y-m-d H:i:s', time() - $d * 86400)]);
    q("UPDATE quotes SET status = 'expired', updated_at = ? WHERE status = 'sent' AND valid_until IS NOT NULL AND valid_until < ?", [now(), gmdate('Y-m-d')]);
    foreach (glob(STORAGE . '/sessions/sess_*') ?: [] as $f) if (filemtime($f) < time() - 86400) @unlink($f);
    foreach (glob(STORAGE . '/logs/*.log') ?: [] as $f) if (filesize($f) > 5e6) @rename($f, $f . '.' . gmdate('Ymd'));
    set_setting('cron_last_run', (string)time());
    return $done;
}
