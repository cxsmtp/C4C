<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if (cfg('db_driver', 'mysql') === 'sqlite') {
        $pdo = new PDO('sqlite:' . cfg('db_path', STORAGE . '/c4c.sqlite'), null, null, $opts);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', cfg('db_host', '127.0.0.1'), (int)cfg('db_port', 3306), cfg('db_name'));
        $pdo = new PDO($dsn, cfg('db_user'), cfg('db_pass'), $opts);
    }
    return $pdo;
}

function is_sqlite(): bool
{
    return cfg('db_driver', 'mysql') === 'sqlite';
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute(array_values($params));
    return $st;
}

function one(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = []): mixed
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

/** Table and column names here always come from code, never from user input. */
function insert(string $table, array $data): int
{
    $cols = array_keys($data);
    q('INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', array_values($data));
    return (int)db()->lastInsertId();
}

function update(string $table, int $id, array $data): void
{
    if (!$data) return;
    $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
    q("UPDATE $table SET $set WHERE id = ?", [...array_values($data), $id]);
}

/** The settings read once per request. Returned by reference so writes update it. */
function &settings_cache(): ?array
{
    static $cache = null;
    return $cache;
}

function settings_cache_reset(): void
{
    $c = &settings_cache();
    $c = null;
}

function setting(string $k, string $default = ''): string
{
    $cache = &settings_cache();
    if ($cache === null) {
        try {
            $loaded = [];
            foreach (all('SELECT k, v FROM settings') as $r) $loaded[$r['k']] = (string)$r['v'];
            $cache = $loaded;
        } catch (PDOException $ex) {
            // The settings table does not exist before installation; use the default and try again later.
            log_line('app.log', 'settings not readable yet: ' . $ex->getCode());
            return $default;
        }
    }
    return $cache[$k] ?? $default;
}

function set_setting(string $k, string $v): void
{
    $cache = &settings_cache();
    if ($cache !== null) $cache[$k] = $v;
    if (is_sqlite()) q('INSERT INTO settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v', [$k, $v]);
    else q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, $v]);
}

function schema(): array
{
    $sq = is_sqlite();
    $id = $sq ? 'id INTEGER PRIMARY KEY AUTOINCREMENT' : 'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
    $long = $sq ? 'TEXT' : 'MEDIUMTEXT';
    $tail = $sq ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $t = fn(string $name, string $cols) => "CREATE TABLE IF NOT EXISTS $name ($cols)$tail";
    return [
        $t('settings', 'k VARCHAR(64) NOT NULL PRIMARY KEY, v TEXT'),
        $t('clients', "$id, company VARCHAR(200) NOT NULL, contact_name VARCHAR(120), email VARCHAR(191), phone VARCHAR(60), notes TEXT, created_at VARCHAR(19)"),
        $t('users', "$id, role VARCHAR(10) NOT NULL, client_id INT NULL, name VARCHAR(120) NOT NULL, email VARCHAR(191) NOT NULL UNIQUE, password_hash VARCHAR(255) NULL, active INT NOT NULL DEFAULT 1, invite_hash VARCHAR(64) NULL, invite_expires VARCHAR(19) NULL, last_login VARCHAR(19) NULL, created_at VARCHAR(19)"),
        $t('categories', "$id, kind VARCHAR(10) NOT NULL, slug VARCHAR(191) NOT NULL UNIQUE, name VARCHAR(200) NOT NULL, blurb TEXT, icon VARCHAR(30), sort INT NOT NULL DEFAULT 0"),
        $t('items', "$id, category_id INT NULL, kind VARCHAR(10) NOT NULL, slug VARCHAR(191) NOT NULL UNIQUE, name VARCHAR(200) NOT NULL, summary TEXT, body $long, deliverables TEXT, duration VARCHAR(120), price_note VARCHAR(200), vendor VARCHAR(200), tags VARCHAR(500), featured INT NOT NULL DEFAULT 0, published INT NOT NULL DEFAULT 1, sort INT NOT NULL DEFAULT 0, updated_at VARCHAR(19)"),
        $t('posts', "$id, slug VARCHAR(191) NOT NULL UNIQUE, title VARCHAR(255) NOT NULL, topic VARCHAR(120), excerpt TEXT, body $long, author VARCHAR(120), published INT NOT NULL DEFAULT 0, published_at VARCHAR(19), updated_at VARCHAR(19)"),
        $t('case_studies', "$id, slug VARCHAR(191) NOT NULL UNIQUE, title VARCHAR(255) NOT NULL, sector VARCHAR(120), practice VARCHAR(120), engagement VARCHAR(200), challenge TEXT, approach TEXT, outcome TEXT, is_sample INT NOT NULL DEFAULT 1, published INT NOT NULL DEFAULT 1, sort INT NOT NULL DEFAULT 0"),
        $t('jobs', "$id, slug VARCHAR(191) NOT NULL UNIQUE, title VARCHAR(200) NOT NULL, team VARCHAR(120), location VARCHAR(120), summary TEXT, body $long, published INT NOT NULL DEFAULT 1, sort INT NOT NULL DEFAULT 0"),
        $t('pages', "$id, slug VARCHAR(191) NOT NULL UNIQUE, title VARCHAR(255) NOT NULL, lead TEXT, body $long, updated_at VARCHAR(19)"),
        $t('enquiries', "$id, type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'new', name VARCHAR(120), email VARCHAR(191), company VARCHAR(200), phone VARCHAR(60), subject VARCHAR(255), message TEXT, data $long, token VARCHAR(64) NULL UNIQUE, client_id INT NULL, notes TEXT, ip_hash VARCHAR(64), created_at VARCHAR(19), updated_at VARCHAR(19)"),
        $t('quotes', "$id, client_id INT NOT NULL, enquiry_id INT NULL, number VARCHAR(30), title VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'draft', currency VARCHAR(3) NOT NULL DEFAULT 'USD', tax_rate DECIMAL(6,2) NOT NULL DEFAULT 0, notes TEXT, valid_until VARCHAR(10), client_comment TEXT, created_at VARCHAR(19), updated_at VARCHAR(19), responded_at VARCHAR(19)"),
        $t('quote_lines', "$id, quote_id INT NOT NULL, item_id INT NULL, description VARCHAR(255) NOT NULL, qty DECIMAL(12,2) NOT NULL DEFAULT 1, unit_price DECIMAL(12,2) NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0"),
        $t('documents', "$id, client_id INT NOT NULL, title VARCHAR(255) NOT NULL, filename VARCHAR(255) NOT NULL, stored VARCHAR(80) NOT NULL, size INT NOT NULL, created_at VARCHAR(19)"),
        $t('throttle', "$id, bucket VARCHAR(40) NOT NULL, k VARCHAR(64) NOT NULL, ts INT NOT NULL"),
        $t('audit', "$id, user_id INT NULL, action VARCHAR(80) NOT NULL, detail VARCHAR(255), ip_hash VARCHAR(64), created_at VARCHAR(19)"),
        'CREATE INDEX ' . ($sq ? 'IF NOT EXISTS ' : '') . 'throttle_idx ON throttle (bucket, k, ts)',
        'CREATE INDEX ' . ($sq ? 'IF NOT EXISTS ' : '') . 'enq_status_idx ON enquiries (status, created_at)',
    ];
}

function install_schema(): void
{
    foreach (schema() as $sql) {
        try {
            db()->exec($sql);
        } catch (PDOException $ex) {
            // MySQL has no CREATE INDEX IF NOT EXISTS; a duplicate index is fine.
            if (!str_contains($ex->getMessage(), 'Duplicate key name')) throw $ex;
        }
    }
}
