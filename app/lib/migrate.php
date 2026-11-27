<?php
declare(strict_types=1);

/**
 * Versioned database migrations. Each runs once, in order, and is recorded in
 * schema_migrations. Settings > Updates lists pending ones; install and cron apply them.
 */
function migrations(): array
{
    $sq = is_sqlite();
    $id = $sq ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : 'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
    $tail = $sq ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $t = fn(string $n, string $c) => "CREATE TABLE IF NOT EXISTS $n ($c)$tail";
    $idx = fn(string $name, string $on) => 'CREATE INDEX ' . ($sq ? 'IF NOT EXISTS ' : '') . "$name ON $on";
    return [
        '2026.11.27.1' => ['Base schema: content, enquiries, clients, quotes, documents', fn() => schema()],
        '2026.11.27.2' => ['People, teams, roles, two-step sign-in, audit chain, email log', fn() => [
            $t('roles', 'rkey VARCHAR(40) NOT NULL PRIMARY KEY, name VARCHAR(80) NOT NULL, description VARCHAR(255), permissions TEXT, builtin INT NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0'),
            $t('teams', "$id, name VARCHAR(120) NOT NULL, parent_id INT NULL, leader_id INT NULL, description VARCHAR(255), created_at VARCHAR(19)"),
            $t('mail_log', "$id, recipients VARCHAR(1000), subject VARCHAR(255), status VARCHAR(10), error VARCHAR(255), created_at VARCHAR(19)"),
            $t('schema_migrations', 'version VARCHAR(40) NOT NULL PRIMARY KEY, applied_at VARCHAR(19)'),
            'ALTER TABLE users ADD COLUMN role_key VARCHAR(40) NULL',
            'ALTER TABLE users ADD COLUMN team_id INT NULL',
            'ALTER TABLE users ADD COLUMN manager_id INT NULL',
            'ALTER TABLE users ADD COLUMN totp_secret VARCHAR(255) NULL',
            'ALTER TABLE users ADD COLUMN totp_enabled INT NOT NULL DEFAULT 0',
            'ALTER TABLE users ADD COLUMN totp_last_step INT NOT NULL DEFAULT 0',
            'ALTER TABLE users ADD COLUMN recovery_codes TEXT NULL',
            'ALTER TABLE users ADD COLUMN session_version INT NOT NULL DEFAULT 1',
            'ALTER TABLE users ADD COLUMN locked_until VARCHAR(19) NULL',
            'ALTER TABLE users ADD COLUMN password_changed_at VARCHAR(19) NULL',
            'ALTER TABLE users ADD COLUMN sso_subject VARCHAR(255) NULL',
            'ALTER TABLE users ADD COLUMN terms_hash VARCHAR(64) NULL',
            'ALTER TABLE users ADD COLUMN terms_at VARCHAR(19) NULL',
            'ALTER TABLE audit ADD COLUMN prev_hash VARCHAR(64) NULL',
            'ALTER TABLE audit ADD COLUMN hash VARCHAR(64) NULL',
            'ALTER TABLE enquiries ADD COLUMN assigned_to INT NULL',
            'ALTER TABLE enquiries ADD COLUMN team_id INT NULL',
            $idx('mail_log_idx', 'mail_log (created_at)'),
            $idx('audit_action_idx', 'audit (action, created_at)'),
            $idx('items_pub_idx', 'items (published, kind, category_id)'),
            $idx('posts_pub_idx', 'posts (published, published_at)'),
            $idx('enq_email_idx', 'enquiries (email)'),
            $idx('users_client_idx', 'users (client_id, role)'),
        ], function () {
            foreach (builtin_roles() as $k => [$name, $desc, $perms]) {
                if (!one('SELECT rkey FROM roles WHERE rkey = ?', [$k])) {
                    insert('roles', ['rkey' => $k, 'name' => $name, 'description' => $desc, 'permissions' => json_encode($perms), 'builtin' => 1, 'sort' => array_search($k, array_keys(builtin_roles()), true)]);
                }
            }
            // Existing admins become Owners so nobody loses access.
            q("UPDATE users SET role_key = 'owner' WHERE role = 'admin' AND (role_key IS NULL OR role_key = '')");
            q('UPDATE users SET password_changed_at = created_at WHERE password_changed_at IS NULL');
            $defaults = [
                'brand_name' => 'Call4Counsel', 'brand_tagline' => 'Application security, AI & agentic security and IT services',
                'brand_accent' => '#ff3d9a', 'brand_footer' => 'Application security, AI and agentic security, and IT services, with the products to back them. Senior people, clear advice, work that lasts.',
                'signin_min_password' => '12', 'signin_require_2fa' => '1', 'signin_lockout_attempts' => '5', 'signin_lockout_minutes' => '15',
                'signin_idle_minutes' => '30', 'signin_absolute_hours' => '12', 'signin_staff_domains' => '', 'signin_client_2fa' => 'optional',
                'smtp_host' => '', 'smtp_port' => '465', 'smtp_secure' => 'ssl', 'smtp_user' => '', 'smtp_pass' => '', 'mail_from' => 'noreply@call4counsel.com', 'mail_from_name' => 'Call4Counsel website',
                'sso_enabled' => '0', 'sso_provider' => 'microsoft', 'sso_issuer' => '', 'sso_client_id' => '', 'sso_client_secret' => '', 'sso_allowed_domains' => '', 'sso_auto_create' => '0', 'sso_default_role' => 'viewer', 'sso_password_login' => '1',
                'https_hsts_max_age' => '31536000', 'https_hsts_subdomains' => '1', 'https_hsts_preload' => '0',
                'retention_enquiry_days' => '730', 'retention_audit_days' => '0', 'retention_mail_days' => '180',
                'backup_keep' => '14', 'backup_encrypt' => '1', 'maintenance_mode' => '0', 'maintenance_message' => 'We are making improvements and will be back shortly.',
                'pricing_rates' => "Senior consultant|1200|day\nConsultant|900|day\nIT engineer|600|day\nManaged IT|45|user/month",
                'pricing_currencies' => 'USD, EUR, GBP, INR, AED', 'pricing_tax_rate' => '0', 'pricing_tax_label' => 'Tax', 'pricing_quote_valid_days' => '30',
                'estimate_enabled' => '0', 'estimate_note' => 'Indicative ranges only. Your proposal will confirm a fixed price after a scoping call.',
                'help_support_email' => 'hello@call4counsel.com', 'help_support_hours' => 'Monday to Friday, 09:00 to 18:00', 'help_docs_url' => '',
            ];
            foreach ($defaults as $k => $v) if (val('SELECT COUNT(*) FROM settings WHERE k = ?', [$k]) == 0) set_setting($k, $v);
        }],
        '2026.11.27.3' => ['Light professional theme: blue replaces the pink accent', fn() => [], function () {
            if (setting('brand_accent') === '' || strtolower(setting('brand_accent')) === '#ff3d9a') set_setting('brand_accent', '#1d4ed8');
        }],
    ];
}

function applied_migrations(): array
{
    try {
        return array_column(all('SELECT version, applied_at FROM schema_migrations ORDER BY version'), 'applied_at', 'version');
    } catch (Throwable) {
        return [];
    }
}

function pending_migrations(): array
{
    $done = applied_migrations();
    // A database created before migrations existed already has the base schema.
    if (!$done && table_exists('enquiries')) $done['2026.11.27.1'] = 'legacy';
    return array_diff_key(migrations(), $done);
}

function table_exists(string $t): bool
{
    try {
        db()->query("SELECT 1 FROM $t LIMIT 1");
        return true;
    } catch (Throwable) {
        return false;
    }
}

/** Applies pending migrations; returns the versions applied. */
function run_migrations(): array
{
    $applied = [];
    $lock = fopen(STORAGE . '/migrate.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Another update is running.');
    try {
        $tail = is_sqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        db()->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(40) NOT NULL PRIMARY KEY, applied_at VARCHAR(19))$tail");
        foreach (pending_migrations() as $ver => $m) {
            $stmts = $m[1]();
            foreach ((array)$stmts as $sql) {
                try {
                    db()->exec($sql);
                } catch (PDOException $ex) {
                    // Re-running after a partial failure: columns or indexes may already exist.
                    if (!preg_match('/duplicate column|Duplicate column|Duplicate key name|already exists/i', $ex->getMessage())) throw $ex;
                }
            }
            if (isset($m[2])) ($m[2])();
            q('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)', [$ver, now()]);
            $applied[] = $ver;
        }
        if (!isset(applied_migrations()['2026.11.27.1'])) q('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)', ['2026.11.27.1', now()]);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    return $applied;
}
