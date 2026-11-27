<?php
// Unit and integration tests. Run: php tests/run.php
// Uses a throwaway SQLite database; never touches app/config.php or real data.
declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/c4c-test-' . bin2hex(random_bytes(4));
mkdir($tmp);
mkdir("$tmp/storage");
define('C4C_TEST_CONFIG', ['storage' => "$tmp/storage", 'base_url' => 'https://test.example', 'db_driver' => 'sqlite', 'db_path' => "$tmp/db.sqlite", 'app_key' => bin2hex(random_bytes(32)), 'install_key' => '']);
require __DIR__ . '/../app/bootstrap.php';
foreach (glob(APP . '/routes/*.php') as $f) require_once $f;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$passed = $failed = 0;
function t(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        $passed++;
        echo "  ok   $name\n";
    } catch (Throwable $ex) {
        $failed++;
        echo "  FAIL $name: " . $ex->getMessage() . "\n";
    }
}
function same(mixed $want, mixed $got, string $msg = ''): void
{
    if ($want !== $got) throw new RuntimeException(($msg ? "$msg: " : '') . 'expected ' . var_export($want, true) . ', got ' . var_export($got, true));
}
function yes(bool $v, string $msg = 'expected true'): void
{
    if (!$v) throw new RuntimeException($msg);
}

echo "Database\n";
t('migrations apply from empty and are idempotent', function () {
    $a = run_migrations();
    same(count(migrations()), count($a));
    same([], run_migrations());
    same([], pending_migrations());
    require_once APP . '/seed.php';
    seed_content();
    yes((int)val('SELECT COUNT(*) FROM items') > 30, 'catalogue seeded');
    yes((int)val('SELECT COUNT(*) FROM roles') === 6, 'six built-in roles');
});

echo "Output safety\n";
t('e() escapes HTML and quotes', fn() => same('&lt;script&gt;&quot;x&quot;&apos;', e('<script>"x"\'')));
t('markdown escapes raw HTML', fn() => yes(!str_contains(md('<img src=x onerror=alert(1)>'), '<img'), 'raw HTML rendered'));
t('markdown drops javascript: links', fn() => yes(!str_contains(md('[x](javascript:alert(1))'), 'href'), 'javascript link kept'));
t('markdown keeps https links with rel', fn() => yes(str_contains(md('[x](https://owasp.org)'), 'rel="noopener noreferrer"')));
t('markdown lists and headings', fn() => same("<h2>A</h2>\n<ul>\n<li><strong>b</strong></li>\n</ul>", md("## A\n- **b**")));

echo "Crypto and two-step sign-in\n";
t('seal and unseal round trip', fn() => same('s3cret', unseal(seal('s3cret'))));
t('tampered seal does not open', function () { $s = seal('x'); $s[10] = $s[10] === 'A' ? 'B' : 'A'; same('', unseal($s)); });
t('TOTP matches RFC 6238 test vector', fn() => same('287082', totp_code(base32_encode('12345678901234567890'), intdiv(59, 30))));
t('TOTP accepts current code once', function () {
    $sec = base32_encode(random_bytes(20));
    $step = intdiv(time(), 30);
    $got = totp_verify($sec, totp_code($sec, $step));
    yes($got !== null);
    same(null, totp_verify($sec, totp_code($sec, $step), $step), 'replay accepted');
});
t('recovery codes are 10, look-alike free', function () {
    $c = new_recovery_codes();
    same(10, count($c));
    foreach ($c as $x) yes((bool)preg_match('/^[A-HJKMNP-Z2-9]{5}-[A-HJKMNP-Z2-9]{5}$/', $x), "bad code $x");
});

echo "Passwords\n";
t('short password refused', fn() => yes(password_problem('short', 'short') !== null));
t('mismatch refused', fn() => yes(password_problem('a-long-passphrase-1', 'a-long-passphrase-2') !== null));
t('email name refused', fn() => yes(password_problem('jane.doe-is-great-9', 'jane.doe-is-great-9', 'jane.doe@x.com') !== null));
t('good passphrase accepted', fn() => same(null, password_problem('mauve-otter-climbs-41', 'mauve-otter-climbs-41', 'a@b.com')));

echo "Roles and permissions\n";
t('owner holds every permission', fn() => same(all_permissions(), role_permissions('owner')));
t('administrator cannot manage roles', fn() => yes(!in_array('roles.manage', role_permissions('admin'), true)));
t('nobody grants a role beyond their own', function () {
    $sales = ['id' => 99, 'role' => 'admin', 'role_key' => 'sales'];
    $g = grantable_roles($sales);
    yes(!isset($g['owner']) && !isset($g['admin']), 'sales could grant owner/admin');
});
t('last owner cannot be removed', function () {
    $id = insert('users', ['role' => 'admin', 'role_key' => 'owner', 'name' => 'O', 'email' => 'o@x.com', 'active' => 1, 'created_at' => now()]);
    $other = insert('users', ['role' => 'admin', 'role_key' => 'owner', 'name' => 'P', 'email' => 'p@x.com', 'active' => 1, 'created_at' => now()]);
    $me = one('SELECT * FROM users WHERE id = ?', [$other]);
    $u = one('SELECT * FROM users WHERE id = ?', [$id]);
    same(null, person_guard($me, $u, 'off'), 'two owners: allowed');
    q('UPDATE users SET active = 0 WHERE id = ?', [$other]);
    yes(person_guard($me, $u, 'off') !== null, 'last owner turned off');
});
t('nobody changes their own access', function () {
    $me = one("SELECT * FROM users WHERE email = 'o@x.com'");
    yes(person_guard($me, $me, 'off') !== null);
});

echo "Audit chain\n";
t('chain verifies and detects tampering', function () {
    audit('test.one', 'a', null);
    audit('test.two', 'b', null);
    yes(audit_verify()[0], 'fresh chain broken');
    q("UPDATE audit SET detail = 'changed' WHERE action = 'test.one'");
    yes(!audit_verify()[0], 'tampering not detected');
    q("UPDATE audit SET detail = 'a' WHERE action = 'test.one'");
});

echo "Assessment\n";
t('autonomous agents with production access are critical', function () {
    $a = ['size' => 'mid', 'sector' => 'saas', 'ai_use' => ['agents'], 'autonomy' => 'auto', 'ai_data' => ['prod'], 'ai_controls' => [], 'sdlc' => ['no_dev'], 'it' => [], 'compliance' => [], 'priority' => 'ai_launch'];
    $r = assessment_analyse($a);
    same('critical', $r['findings'][0]['sev']);
    $svc = recommend_items($r['need'], 'service', 3);
    yes(in_array('ai-agentic-security', array_column(all('SELECT c.slug FROM items i JOIN categories c ON c.id = i.category_id WHERE i.id = ?', [$svc[0]['id']]), 'slug'), true), 'top service is not AI security');
});
t('mature organisation gets few findings', function () {
    $a = ['size' => 'mid', 'sector' => 'saas', 'ai_use' => ['none'], 'autonomy' => 'none', 'ai_data' => ['public'], 'ai_controls' => [],
        'sdlc' => ['sast', 'sca', 'secrets', 'dast', 'threat', 'pentest', 'cicd'], 'it' => ['mfa', 'endpoint', 'backup', 'cloud', 'm365', 'helpdesk', 'pam', 'ir'], 'compliance' => ['none'], 'priority' => 'unsure'];
    same(0, count(assessment_analyse($a)['findings']));
});

echo "Backups\n";
t('backup round trip restores data and keeps the audit log', function () {
    $name = create_backup('test');
    $auditBefore = (int)val('SELECT COUNT(*) FROM audit');
    q("UPDATE settings SET v = 'changed' WHERE k = 'brand_name'");
    restore_backup($name);
    same('Call4Counsel', (string)val("SELECT v FROM settings WHERE k = 'brand_name'"));
    yes((int)val('SELECT COUNT(*) FROM audit') >= $auditBefore, 'audit rewound');
});
t('backup names are validated', function () {
    try { backup_path('../../app/config.php'); throw new LogicException('accepted'); } catch (RuntimeException) {}
});

echo "Estimates\n";
t('weeks convert to an indicative range', function () {
    set_setting('pricing_rates', "Senior consultant|1000|day");
    set_setting('estimate_rate', 'Senior consultant');
    same([10000.0, 15000.0, 'USD'], estimate_for(['kind' => 'service', 'duration' => '2 to 3 weeks']));
    same(null, estimate_for(['kind' => 'product', 'duration' => '2 weeks']));
});

exec('rm -rf ' . escapeshellarg($tmp));
echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
