<?php
declare(strict_types=1);

/**
 * One-time setup: creates the tables, loads the starter content and the first admin.
 * It only works while storage/installed.lock is absent and needs the install_key from
 * app/config.php. Afterwards /install returns 404.
 */
function install_routes(): array
{
    if (is_file(STORAGE . '/installed.lock')) return [];
    return [
        ['GET', '/install', 'install_page'],
        ['POST', '/install', 'install_post'],
    ];
}

function install_page(array $err = []): string
{
    no_store();
    if ((string)cfg('install_key', '') === '') abort();
    $e = $err ? '<div class="flash err" role="alert">' . implode('<br>', array_map('e', $err)) . '</div>' : '';
    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>Set up Call4Counsel</title><link rel="stylesheet" href="/assets/site.css"></head><body><main id="main"><section class="band"><div class="wrap narrow">'
        . '<h1>Set up Call4Counsel</h1><p>This creates the database tables, the starter content and your admin account. It can only run once.</p>'
        . '<form method="post" action="/install" class="card">' . csrf_field() . $e
        . field('install_key', 'Install key (from app/config.php)', '', ['type' => 'password', 'required' => true, 'attrs' => ' autocomplete="off"'])
        . field('name', 'Your name', '', ['required' => true]) . field('email', 'Your admin email', '', ['type' => 'email', 'required' => true])
        . field('password', 'Admin password (at least 12 characters)', '', ['type' => 'password', 'required' => true, 'attrs' => ' autocomplete="new-password"'])
        . field('confirm', 'Confirm password', '', ['type' => 'password', 'required' => true, 'attrs' => ' autocomplete="new-password"'])
        . '<button class="btn" type="submit">Install</button></form></div></section></main></body></html>';
    return $html;
}

function install_post(): string
{
    no_store();
    $key = (string)cfg('install_key', '');
    if ($key === '') abort();
    run_migrations(); // idempotent; the throttle table must exist before it is used
    $err = [];
    if (throttle('install', ip_hash(), 10, 3600)) $err[] = 'Too many attempts. Try again later.';
    elseif (!hash_equals($key, (string)($_POST['install_key'] ?? ''))) $err[] = 'The install key is not right.';
    $email = strtolower(p('email', 191));
    $name = p('name', 120);
    if (!valid_email($email)) $err[] = 'Enter a valid email.';
    if ($name === '') $err[] = 'Enter your name.';
    if ($pe = password_problem((string)($_POST['password'] ?? ''), (string)($_POST['confirm'] ?? ''), $email)) $err[] = $pe;
    if ($err) return install_page($err);

    require_once APP . '/seed.php';
    seed_content();
    if (!one('SELECT id FROM users WHERE email = ?', [$email])) {
        insert('users', ['role' => 'admin', 'role_key' => 'owner', 'client_id' => null, 'name' => $name, 'email' => $email, 'password_hash' => password_hash((string)$_POST['password'], PASSWORD_DEFAULT), 'password_changed_at' => now(), 'active' => 1, 'created_at' => now()]);
    }
    file_put_contents(STORAGE . '/installed.lock', now() . "\n", LOCK_EX);
    @file_put_contents(STORAGE . '/schema.version', latest_migration(), LOCK_EX);
    $u = one('SELECT * FROM users WHERE email = ?', [$email]);
    login_user($u);
    audit('install');
    flash('ok', 'Installed. Remove install_key from app/config.php, then work through Settings → Backups & health → Ready for production.');
    after_signin($u);
}
