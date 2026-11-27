<?php
declare(strict_types=1);

/**
 * Settings, grouped as in FTR Command Center: People, Pricing, Records and System.
 * Each link shows only to people who hold the permission it needs.
 */
function settings_routes(): array
{
    return [
        ['GET', '/admin/settings', 'settings_home'],
        ['GET', '/admin/settings/people', 'st_people'],
        ['POST', '/admin/settings/people', 'st_people_add'],
        ['POST', '/admin/settings/people/bulk', 'st_people_bulk'],
        ['GET', '/admin/settings/people/(\d+)', 'st_person'],
        ['POST', '/admin/settings/people/(\d+)', 'st_person_save'],
        ['POST', '/admin/settings/people/(\d+)/(invite|unlock|reset2fa|signout|access|remove)', 'st_person_action'],
        ['GET', '/admin/settings/teams', 'st_teams'],
        ['POST', '/admin/settings/teams', 'st_teams_save'],
        ['POST', '/admin/settings/teams/(\d+)/delete', 'st_team_delete'],
        ['GET', '/admin/settings/roles', 'st_roles'],
        ['POST', '/admin/settings/roles', 'st_roles_save'],
        ['POST', '/admin/settings/roles/new', 'st_role_new'],
        ['POST', '/admin/settings/roles/([a-z0-9-]+)/delete', 'st_role_delete'],
        ['GET', '/admin/settings/audit', 'st_audit'],
        ['GET', '/admin/settings/audit.csv', 'st_audit_csv'],
        ['POST', '/admin/settings/audit/verify', 'st_audit_verify'],
        ['GET', '/admin/settings/email-log', 'st_email_log'],
        ['POST', '/admin/settings/email/test', 'st_email_test'],
        ['POST', '/admin/settings/sso/test', 'st_sso_test'],
        ['GET', '/admin/settings/updates', 'st_updates'],
        ['POST', '/admin/settings/updates', 'st_updates_apply'],
        ['GET', '/admin/settings/backups', 'st_backups'],
        ['POST', '/admin/settings/backups/run', 'st_backup_run'],
        ['GET', '/admin/settings/backups/download', 'st_backup_download'],
        ['POST', '/admin/settings/backups/restore', 'st_backup_restore'],
        ['GET', '/admin/settings/backups/report', 'st_troubleshooting'],
        ['POST', '/admin/settings/backups/as-meant', 'st_readiness_as_meant'],
        ['POST', '/admin/settings/backups', 'st_backups_settings_save'],
        ['POST', '/admin/settings/help-settings', 'st_help_settings_save'],
        ['GET', '/admin/settings/help', 'st_help'],
        ['POST', '/admin/settings/help', 'st_help_post'],
        ['GET', '/admin/settings/about', 'st_about'],
        ['GET', '/admin/settings/([a-z-]+)', 'st_form_page'],
        ['POST', '/admin/settings/([a-z-]+)', 'st_form_save'],
        ['GET', '/brand.css', 'brand_css'],
    ];
}

function settings_nav(): array
{
    return [
        'People' => [['people', 'People', 'people.view'], ['teams', 'Teams', 'people.view'], ['roles', 'Roles & permissions', 'people.view'], ['sign-in', 'Sign-in', 'settings.manage']],
        'Pricing' => [['rates', 'Rates & currencies', 'pricing.manage'], ['estimate', 'Quote estimate', 'pricing.manage']],
        'Records' => [['audit', 'Audit log', 'audit.view'], ['email-log', 'Email log', 'audit.view']],
        'System' => [['general', 'General', 'settings.manage'], ['branding', 'Branding', 'settings.manage'], ['email', 'Email (SMTP)', 'settings.manage'],
            ['sso', 'Single sign-on', 'settings.manage'], ['https', 'HTTPS', 'settings.manage'], ['updates', 'Updates', 'system.updates'],
            ['backups', 'Backups & health', 'system.backups'], ['help', 'Get help', ''], ['about', 'About', '']],
    ];
}

function settings_layout(string $slug, string $title, string $intro, string $body): string
{
    $nav = '';
    foreach (settings_nav() as $group => $links) {
        $items = '';
        foreach ($links as [$s, $label, $perm]) {
            if ($perm !== '' && !can($perm)) continue;
            $items .= '<a href="/admin/settings/' . $s . '"' . ($s === $slug ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
        }
        if ($items) $nav .= '<div class="st-group"><p class="st-label">' . e($group) . '</p>' . $items . '</div>';
    }
    return admin_layout($title, '<div class="settings"><nav class="st-nav" aria-label="Settings"><button class="menu-toggle" type="button" aria-expanded="false" aria-controls="st-menu">Settings: ' . e($title) . '</button><div class="menu-body" id="st-menu">' . $nav . '</div></nav><div class="st-main">'
        . ($intro ? '<p class="lead st-intro">' . e($intro) . '</p>' : '') . $body . '</div></div>', 'settings');
}

function settings_home(): never
{
    require_role('admin');
    foreach (settings_nav() as $links) foreach ($links as [$s, , $perm]) if ($perm === '' || can($perm)) redirect('/admin/settings/' . $s);
    abort(403);
}

function chip(string $status, string $text = ''): string
{
    $label = $text !== '' ? $text : ['ok' => 'Ready', 'warn' => 'To do', 'fail' => 'Problem'][$status] ?? $status;
    return '<span class="chip chip-' . e($status) . '">' . e($label) . '</span>';
}

function empty_state(string $title, string $text): string
{
    return '<div class="empty"><b>' . e($title) . '</b><p>' . e($text) . '</p></div>';
}

function refuse(string $action, string $msg, string $back): never
{
    audit('refused: ' . $action, $msg);
    flash('err', $msg);
    redirect($back);
}

// =====================================================================
// Simple settings pages share one form engine
// =====================================================================

/** [key => [label, type, extra]] where extra holds options, help, min/max. */
function settings_forms(): array
{
    $roleOpts = [];
    try {
        foreach (roles_list() as $r) if ($r['rkey'] !== 'owner') $roleOpts[$r['rkey']] = $r['name'];
    } catch (PDOException $ex) {
        log_line('app.log', 'roles not readable for the settings form: ' . $ex->getCode());
        $roleOpts = ['viewer' => 'Viewer'];
    }
    $currencies = array_map('trim', explode(',', setting('pricing_currencies', 'USD')));
    return [
        'sign-in' => ['Sign-in', 'settings.manage', 'How staff and clients sign in, and how long they stay signed in.', [
            'signin_min_password' => ['Shortest password allowed', 'int', ['min' => 12, 'max' => 64, 'help' => 'Characters. 12 or more. Common words and the email name are always refused.']],
            'signin_require_2fa' => ['Staff must use two-step sign-in', 'bool', ['help' => 'Staff who sign in with single sign-on rely on their company account instead.']],
            'signin_client_2fa' => ['Two-step sign-in for client portal users', 'select', ['options' => ['off' => 'Not offered', 'optional' => 'Optional', 'required' => 'Required']]],
            'signin_lockout_attempts' => ['Lock an account after this many wrong passwords', 'int', ['min' => 3, 'max' => 20]],
            'signin_lockout_minutes' => ['Lock it for (minutes)', 'int', ['min' => 1, 'max' => 1440]],
            'signin_idle_minutes' => ['Sign out after this many idle minutes', 'int', ['min' => 5, 'max' => 720]],
            'signin_absolute_hours' => ['Sign out after this many hours, however active', 'int', ['min' => 1, 'max' => 168]],
            'signin_staff_domains' => ['Staff email domains', 'domains', ['help' => 'Comma separated, for example call4counsel.com. Leave empty to allow any domain.']],
        ]],
        'rates' => ['Rates & currencies', 'pricing.manage', 'The rate card used for estimates and quotes, and how quotes are numbered and taxed.', [
            'pricing_rates' => ['Rate card', 'rates', ['help' => 'One per line: name | amount | unit. For example: Senior consultant | 1200 | day']],
            'default_currency' => ['Default currency', 'select', ['options' => array_combine($currencies, $currencies)]],
            'pricing_currencies' => ['Currencies you quote in', 'currencies', ['help' => 'Three-letter codes, comma separated.']],
            'pricing_tax_label' => ['Tax name', 'text', ['help' => 'For example VAT or GST.']],
            'pricing_tax_rate' => ['Default tax rate (%)', 'decimal', ['min' => 0, 'max' => 100]],
            'pricing_quote_valid_days' => ['Quotes are valid for (days)', 'int', ['min' => 1, 'max' => 365]],
            'quote_prefix' => ['Quote number prefix', 'text', ['pattern' => '/^[A-Z0-9-]{1,12}$/', 'help' => 'Capital letters, numbers and hyphens.']],
        ]],
        'estimate' => ['Quote estimate', 'pricing.manage', 'Indicative price ranges worked out from each service\'s typical duration and your rate card.', [
            'estimate_enabled' => ['Show indicative ranges on service pages and assessment results', 'bool', []],
            'estimate_rate' => ['Day rate to use', 'select', ['options' => array_combine(array_column(rate_card(), 'name'), array_column(rate_card(), 'name')) ?: ['' => '(add a day rate first)']]],
            'estimate_note' => ['Note shown next to estimates', 'textarea', []],
        ]],
        'general' => ['General', 'settings.manage', 'Who you are, where enquiries go, and how long records are kept.', [
            'notify_emails' => ['Send new enquiries to', 'emails', ['help' => 'Comma separated. Every address gets every enquiry, quote decision and assessment.']],
            'contact_email' => ['Public contact email', 'email', []],
            'contact_phone' => ['Public phone number', 'text', ['help' => 'Optional.']],
            'company_legal_name' => ['Registered company name', 'text', ['help' => 'Shown in the footer. Builds trust with new customers.']],
            'company_registration' => ['Registration or tax number', 'text', []],
            'company_address' => ['Registered address', 'textarea', []],
            'environment' => ['This installation is', 'select', ['options' => ['production' => 'Production', 'staging' => 'Staging']]],
            'retention_enquiry_days' => ['Delete closed and spam enquiries after (days)', 'int', ['min' => 0, 'max' => 3650, 'help' => '0 keeps them forever. Enquiries linked to a client are kept.']],
            'retention_mail_days' => ['Keep the email log for (days)', 'int', ['min' => 7, 'max' => 3650]],
            'maintenance_mode' => ['Maintenance mode', 'bool', ['help' => 'Visitors see the message below. Staff can still sign in.']],
            'maintenance_message' => ['Maintenance message', 'text', []],
        ]],
        'branding' => ['Branding', 'settings.manage', 'How the site names and presents itself.', [
            'brand_name' => ['Brand name', 'text', ['max' => 60]],
            'brand_tagline' => ['Tagline', 'text', ['max' => 140]],
            'brand_accent' => ['Accent colour', 'color', ['help' => 'Used for buttons and links. Choose a dark, saturated colour so white text on it stays readable.']],
            'brand_footer' => ['Footer description', 'textarea', ['max' => 400]],
        ]],
        'email' => ['Email (SMTP)', 'settings.manage', 'How the site sends enquiry notifications, invitations and password links.', [
            'smtp_host' => ['SMTP server', 'host', ['help' => 'For Hostinger email: smtp.hostinger.com']],
            'smtp_port' => ['Port', 'select', ['options' => ['465' => '465 (implicit TLS)', '587' => '587 (STARTTLS)']]],
            'smtp_user' => ['Username', 'text', ['help' => 'Usually the full mailbox address.']],
            'smtp_pass' => ['Password', 'secret', []],
            'mail_from' => ['From address', 'email', ['help' => 'Must be a mailbox on your domain, such as noreply@call4counsel.com.']],
            'mail_from_name' => ['From name', 'text', []],
        ]],
        'sso' => ['Single sign-on', 'settings.manage', 'Let staff sign in with their Microsoft or Google company account (OpenID Connect).', [
            'sso_enabled' => ['Turn on single sign-on', 'bool', []],
            'sso_provider' => ['Provider', 'select', ['options' => ['microsoft' => 'Microsoft Entra ID', 'google' => 'Google Workspace', 'custom' => 'Other OpenID Connect provider']]],
            'sso_issuer' => ['Issuer URL', 'url', ['help' => 'Microsoft: https://login.microsoftonline.com/<tenant ID>/v2.0. Google: leave empty.']],
            'sso_client_id' => ['Client ID', 'text', []],
            'sso_client_secret' => ['Client secret', 'secret', []],
            'sso_allowed_domains' => ['Allowed email domains', 'domains', ['help' => 'Comma separated. Leave empty to accept any account the provider signs in.']],
            'sso_auto_create' => ['Add people on their first sign-in', 'bool', []],
            'sso_default_role' => ['Role for people added this way', 'select', ['options' => $roleOpts]],
            'sso_password_login' => ['Staff may also sign in with a password', 'bool', ['help' => 'Keep this on until single sign-on is tested, so nobody is locked out.']],
        ]],
        'https' => ['HTTPS', 'settings.manage', 'Hostinger issues and renews the certificate. These settings control how strictly browsers must use HTTPS.', [
            'https_hsts_max_age' => ['Remember HTTPS-only for (seconds)', 'select', ['options' => ['0' => 'Off', '86400' => '1 day (testing)', '15552000' => '180 days', '31536000' => '1 year (recommended)', '63072000' => '2 years']]],
            'https_hsts_subdomains' => ['Include subdomains', 'bool', ['help' => 'Only if every subdomain of call4counsel.com serves HTTPS.']],
            'https_hsts_preload' => ['Ask browsers to preload', 'bool', ['help' => 'Needs 1 year or more and subdomains. Hard to undo: only turn on when certain.']],
        ]],
    ];
}

function rate_card(): array
{
    $out = [];
    foreach (lines_of(setting('pricing_rates')) as $l) {
        $p = array_map('trim', explode('|', $l));
        if (count($p) === 3 && is_numeric($p[1])) $out[] = ['name' => $p[0], 'amount' => (float)$p[1], 'unit' => $p[2]];
    }
    return $out;
}

function st_field(string $k, array $def, string $value): string
{
    [$label, $type, $x] = $def;
    $help = $x['help'] ?? null;
    return match ($type) {
        'bool' => '<label class="check switch"><input type="checkbox" name="' . e($k) . '" value="1"' . ($value === '1' ? ' checked' : '') . '> <span>' . e($label) . ($help ? '<span class="help block">' . e($help) . '</span>' : '') . '</span></label>',
        'select' => field($k, $label, $value, ['type' => 'select', 'options' => $x['options'], 'help' => $help]),
        'textarea', 'rates' => field($k, $label, $value, ['type' => 'textarea', 'help' => $help]),
        'int', 'decimal' => field($k, $label, $value, ['type' => 'number', 'help' => $help, 'attrs' => ' min="' . ($x['min'] ?? 0) . '" max="' . ($x['max'] ?? 999999) . '" step="' . ($type === 'int' ? '1' : '0.01') . '"']),
        'color' => field($k, $label, $value, ['type' => 'color', 'help' => $help]),
        'email' => field($k, $label, $value, ['type' => 'email', 'help' => $help]),
        'url' => field($k, $label, $value, ['type' => 'url', 'help' => $help, 'attrs' => ' placeholder="https://"']),
        'secret' => field($k, $label, '', ['type' => 'password', 'help' => ($value !== '' ? 'Kept. Type a new one to replace it.' : 'Not set.'), 'attrs' => ' autocomplete="new-password"']),
        default => field($k, $label, $value, ['help' => $help, 'attrs' => ' maxlength="' . ($x['max'] ?? 500) . '"']),
    };
}

function st_form_page(string $slug, array $errors = []): string
{
    $forms = settings_forms();
    if (!isset($forms[$slug])) abort();
    [$title, $perm, $intro, $fields] = $forms[$slug];
    require_perm($perm);
    $f = '';
    foreach ($fields as $k => $def) $f .= st_field($k, $def, setting($k, $k === 'environment' ? 'production' : ''));
    $err = $errors ? '<div class="flash err" role="alert">' . implode('<br>', array_map('e', $errors)) . '</div>' : '';
    $extra = match ($slug) {
        'email' => '<form method="post" action="/admin/settings/email/test" class="card inline-form">' . csrf_field() . '<h3>Test</h3><p class="small">Connects, signs in and sends a message to every address in General &rarr; Send new enquiries to. Last result: '
            . e(setting('mail_last_test', 'never')) . '</p><button class="btn" type="submit">Test connection and send</button></form>',
        'sso' => '<div class="card inline-form"><h3>Details for your identity provider</h3><dl class="facts"><dt>Redirect URI</dt><dd><code>' . e(base_url()) . '/signin/sso/callback</code></dd><dt>Sign-in URL</dt><dd><code>' . e(base_url()) . '/signin/sso</code></dd><dt>Scopes</dt><dd><code>openid email profile</code></dd></dl>'
            . '<form method="post" action="/admin/settings/sso/test">' . csrf_field() . '<button class="btn small ghost" type="submit">Check the provider configuration</button></form></div>',
        'https' => https_status_card(),
        'estimate' => estimate_preview(),
        'sign-in' => '<div class="card inline-form"><h3>How people sign in now</h3>' . ticks([
            'Passwords of at least ' . min_password() . ' characters, never stored in readable form',
            'Locked for ' . setting('signin_lockout_minutes', '15') . ' minutes after ' . setting('signin_lockout_attempts', '5') . ' wrong passwords',
            'Signed out after ' . setting('signin_idle_minutes', '30') . ' idle minutes, and after ' . setting('signin_absolute_hours', '12') . ' hours in any case',
            'Two-step sign-in for staff: ' . (setting('signin_require_2fa', '1') === '1' ? 'required' : 'optional') . '; for clients: ' . setting('signin_client_2fa', 'optional'),
            'Single sign-on: ' . (sso_ready() ? 'on' : 'off'),
        ]) . '</div>',
        default => '',
    };
    return settings_layout($slug, $title, $intro, $err . '<form method="post" action="/admin/settings/' . e($slug) . '" class="card edit">' . csrf_field() . $f
        . '<div class="actions"><button class="btn" type="submit">Save changes</button></div></form>' . $extra);
}

function st_form_save(string $slug): string
{
    $forms = settings_forms();
    if (!isset($forms[$slug])) abort();
    [$title, $perm, , $fields] = $forms[$slug];
    require_perm($perm);
    $errors = [];
    $save = [];
    foreach ($fields as $k => [$label, $type, $x]) {
        $v = p($k, $type === 'textarea' || $type === 'rates' ? 5000 : 500);
        switch ($type) {
            case 'bool': $v = isset($_POST[$k]) ? '1' : '0'; break;
            case 'int':
                if (!preg_match('/^\d+$/', $v) || (int)$v < ($x['min'] ?? 0) || (int)$v > ($x['max'] ?? PHP_INT_MAX)) { $errors[] = "$label must be a whole number from " . ($x['min'] ?? 0) . ' to ' . ($x['max'] ?? '') . '.'; continue 2; }
                break;
            case 'decimal':
                if (!is_numeric($v) || (float)$v < ($x['min'] ?? 0) || (float)$v > ($x['max'] ?? PHP_INT_MAX)) { $errors[] = "$label is out of range."; continue 2; }
                $v = (string)round((float)$v, 2);
                break;
            case 'select': if (!array_key_exists($v, $x['options'])) { $errors[] = "Choose a valid value for $label."; continue 2; } break;
            case 'email': if ($v !== '' && !valid_email($v)) { $errors[] = "$label is not a valid email."; continue 2; } break;
            case 'emails':
                $list = array_values(array_unique(array_filter(preg_split('/[\s,;]+/', strtolower($v)) ?: [])));
                $bad = array_filter($list, fn($m) => !valid_email($m));
                if ($bad || !$list) { $errors[] = "$label: " . ($bad ? 'check ' . implode(', ', $bad) : 'add at least one address') . '.'; continue 2; }
                $v = implode(', ', $list);
                break;
            case 'domains':
                $list = array_values(array_unique(array_filter(preg_split('/[\s,;]+/', strtolower($v)) ?: [])));
                foreach ($list as $d) if (!preg_match('/^(?=.{3,253}$)([a-z0-9-]+\.)+[a-z]{2,}$/', $d)) { $errors[] = "$label: $d is not a domain."; continue 3; }
                $v = implode(', ', $list);
                break;
            case 'currencies':
                $list = array_values(array_unique(array_filter(array_map(fn($c) => strtoupper(trim($c)), explode(',', $v)))));
                foreach ($list as $c) if (!preg_match('/^[A-Z]{3}$/', $c)) { $errors[] = "$c is not a three-letter currency code."; continue 3; }
                if (!$list) { $errors[] = 'Add at least one currency.'; continue 2; }
                $v = implode(', ', $list);
                break;
            case 'color': if (!preg_match('/^#[0-9a-fA-F]{6}$/', $v)) { $errors[] = "$label must be a colour like #1d4ed8."; continue 2; } $v = strtolower($v); break;
            case 'host': if ($v !== '' && !preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$/i', $v)) { $errors[] = "$label must be a host name."; continue 2; } break;
            case 'url': if ($v !== '' && !preg_match('#^https://[^\s<>"]+$#', $v)) { $errors[] = "$label must start with https://"; continue 2; } $v = rtrim($v, '/'); break;
            case 'rates':
                foreach (lines_of($v) as $l) {
                    $p = array_map('trim', explode('|', $l));
                    if (count($p) !== 3 || !is_numeric($p[1]) || (float)$p[1] < 0 || $p[0] === '') { $errors[] = "Rate card line \"$l\" must be: name | amount | unit."; continue 3; }
                }
                break;
            case 'secret':
                if ($v === '') continue 2; // kept
                $v = seal($v);
                break;
            default:
                if (isset($x['pattern']) && !preg_match($x['pattern'], $v)) { $errors[] = "$label: " . ($x['help'] ?? 'not valid') ; continue 2; }
                if (mb_strlen($v) > ($x['max'] ?? 500)) { $errors[] = "$label is too long."; continue 2; }
        }
        $save[$k] = $v;
    }
    if ($slug === 'https' && ($save['https_hsts_preload'] ?? '0') === '1' && ((int)($save['https_hsts_max_age'] ?? 0) < 31536000 || ($save['https_hsts_subdomains'] ?? '0') !== '1')) {
        $errors[] = 'Preload needs at least 1 year and subdomains included.';
    }
    if ($slug === 'sso' && ($save['sso_enabled'] ?? '0') === '1' && ($save['sso_password_login'] ?? '1') === '0' && !sso_ready()) {
        $errors[] = 'Finish and check the single sign-on settings before turning password sign-in off.';
    }
    if ($errors) return st_form_page($slug, $errors);
    $changed = [];
    foreach ($save as $k => $v) {
        if (setting($k) !== $v) { set_setting($k, $v); $changed[] = $k; }
    }
    if ($slug === 'email' && $changed) set_setting('mail_last_test', 'not tested since the last change');
    if ($slug === 'sso' && $changed) set_setting('sso_discovery_cache', '');
    audit('settings.' . $slug, $changed ? implode(', ', $changed) : 'no change');
    flash('ok', $changed ? 'Saved.' : 'Nothing changed.');
    redirect('/admin/settings/' . $slug);
}

function brand_css(): string
{
    header('Content-Type: text/css; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    $a = brand_accent();
    return ":root{--accent:$a}\n";
}

// =====================================================================
// PEOPLE
// =====================================================================

function staff_status(array $u): string
{
    if (!(int)$u['active']) return chip('fail', 'Off');
    if ($u['locked_until'] && strtotime($u['locked_until'] . ' UTC') > time()) return chip('warn', 'Locked until ' . fmt_date($u['locked_until'], 'H:i'));
    if (!$u['password_hash'] && !$u['sso_subject']) return chip('warn', 'Must set password');
    return chip('ok', 'Active');
}

function teams_options(bool $none = true): array
{
    $o = $none ? ['' => '(no team)'] : [];
    foreach (all('SELECT id, name FROM teams ORDER BY name') as $t) $o[(string)$t['id']] = $t['name'];
    return $o;
}

function st_people(): string
{
    $me = require_perm('people.view');
    $role = g('role');
    $team = g('team');
    $status = g('status');
    $where = ["role = 'admin'"];
    $args = [];
    if ($role !== '') { $where[] = 'role_key = ?'; $args[] = $role; }
    if ($team !== '') { $where[] = 'team_id = ?'; $args[] = (int)$team; }
    $rows = all('SELECT * FROM users WHERE ' . implode(' AND ', $where) . ' ORDER BY name', $args);
    if ($status === 'off') $rows = array_filter($rows, fn($u) => !(int)$u['active']);
    if ($status === 'active') $rows = array_filter($rows, fn($u) => (int)$u['active']);
    if ($status === 'no2fa') $rows = array_filter($rows, fn($u) => !(int)$u['totp_enabled'] && !$u['sso_subject']);
    $roles = array_column(roles_list(), 'name', 'rkey');
    $teams = teams_options(false);
    $names = array_column(all("SELECT id, name FROM users WHERE role = 'admin'"), 'name', 'id');
    $manage = can('people.manage');
    $filters = '<form class="filters" method="get">' . field('role', 'Role', $role, ['type' => 'select', 'options' => ['' => 'Any role'] + $roles])
        . field('team', 'Team', $team, ['type' => 'select', 'options' => ['' => 'Any team'] + $teams])
        . field('status', 'Status', $status, ['type' => 'select', 'options' => ['' => 'Any', 'active' => 'Active', 'off' => 'Access off', 'no2fa' => 'Without two-step sign-in']])
        . '<button class="btn small" type="submit">Filter</button></form>';
    $t = '<div class="table-wrap"><table class="sortable"><thead><tr>' . ($manage ? '<th><input type="checkbox" data-check-all="people" aria-label="Select all"></th>' : '')
        . '<th>Person</th><th>Role</th><th>Team</th><th>Reports to</th><th>Two-step</th><th>Status</th><th>Last sign-in</th></tr></thead><tbody>';
    foreach ($rows as $u) {
        $t .= '<tr>' . ($manage ? '<td><input type="checkbox" name="ids[]" value="' . (int)$u['id'] . '" form="bulk" data-group="people" aria-label="Select ' . e($u['name']) . '"></td>' : '')
            . '<td><a href="/admin/settings/people/' . (int)$u['id'] . '">' . e($u['name']) . '</a><br><span class="small">' . e($u['email']) . '</span></td>'
            . '<td>' . e($roles[$u['role_key']] ?? $u['role_key']) . '</td><td>' . e($teams[(string)$u['team_id']] ?? '') . '</td><td>' . e($names[$u['manager_id']] ?? '') . '</td>'
            . '<td>' . ((int)$u['totp_enabled'] ? chip('ok', 'On') : ($u['sso_subject'] ? chip('ok', 'SSO') : chip('warn', 'Off'))) . '</td>'
            . '<td>' . staff_status($u) . '</td><td data-v="' . e((string)$u['last_login']) . '">' . e(fmt_date($u['last_login'], 'j M Y H:i')) . '</td></tr>';
    }
    $t .= '</tbody></table></div>';
    if (!$rows) $t = empty_state('Nobody matches', 'Change the filters to see more people.');
    $bulk = '';
    $add = '';
    if ($manage) {
        $grant = grantable_roles($me);
        $bulk = '<form id="bulk" method="post" action="/admin/settings/people/bulk" class="bulk-bar" data-confirm="Apply this to everyone selected?">' . csrf_field()
            . field('action', 'With the selected people', '', ['type' => 'select', 'options' => ['' => 'Choose an action', 'role' => 'Set role to…', 'team' => 'Set team to…', 'invite' => 'Send a password link',
                'unlock' => 'Unlock', 'signout' => 'Sign out everywhere', 'on' => 'Turn access on', 'off' => 'Turn access off']])
            . field('role_key', 'Role', '', ['type' => 'select', 'options' => ['' => '(for Set role)'] + $grant])
            . field('team_id', 'Team', '', ['type' => 'select', 'options' => ['' => '(for Set team)'] + teams_options()])
            . '<button class="btn small" type="submit">Apply</button></form>';
        $add = '<form method="post" action="/admin/settings/people" class="card inline-form">' . csrf_field() . '<h3>Add a person</h3><div class="row">'
            . field('name', 'Name', '', ['required' => true, 'attrs' => ' maxlength="120"']) . field('email', 'Work email', '', ['type' => 'email', 'required' => true]) . '</div><div class="row">'
            . field('role_key', 'Role', 'viewer', ['type' => 'select', 'options' => $grant]) . field('team_id', 'Team', '', ['type' => 'select', 'options' => teams_options()]) . '</div>'
            . '<p class="small">They get an email with a one-time link to set their password. The email never contains a password.</p><button class="btn" type="submit">Add and send invitation</button></form>';
    }
    return settings_layout('people', 'People', 'Everyone who works in the admin panel. Client portal users are managed on each client\'s page.', $filters . $bulk . $t . $add . role_cards());
}

/** Plain-language summary of what each type of user can do. */
function role_cards(): string
{
    $labels = [];
    foreach (permission_catalog() as $perms) $labels += $perms;
    $counts = array_column(all("SELECT role_key, COUNT(*) AS n FROM users WHERE role = 'admin' AND active = 1 GROUP BY role_key"), 'n', 'role_key');
    $cards = '';
    foreach (roles_list() as $r) {
        $perms = role_permissions($r['rkey']);
        $can = $r['rkey'] === 'owner' ? ['Everything, including roles and the Owner role'] : array_map(fn($p) => $labels[$p], array_slice($perms, 0, 6));
        if ($r['rkey'] !== 'owner' && count($perms) > 6) $can[] = 'and ' . (count($perms) - 6) . ' more';
        $n = (int)($counts[$r['rkey']] ?? 0);
        $cards .= '<article class="card role-card"><div class="item-top"><h3>' . e($r['name']) . '</h3><span class="chip chip-ok">' . $n . ' ' . ($n === 1 ? 'person' : 'people') . '</span></div>'
            . '<p class="small">' . e((string)$r['description']) . '</p>' . ticks($can) . '</article>';
    }
    return '<h2>Who can do what</h2><p class="small">Pick the simplest role that lets someone do their job. Change what each role can do in <a href="/admin/settings/roles">Roles &amp; permissions</a>.</p><div class="grid role-grid">' . $cards . '</div>';
}

function st_people_add(): never
{
    $me = require_perm('people.manage');
    $email = strtolower(p('email', 191));
    $name = p('name', 120);
    $roleKey = p('role_key', 40);
    $team = (int)p('team_id', 12) ?: null;
    if (!valid_email($email) || $name === '') { flash('err', 'Enter a name and a valid email.'); redirect('/admin/settings/people'); }
    if (!array_key_exists($roleKey, grantable_roles($me))) refuse('people.add', 'Your role does not allow giving that role.', '/admin/settings/people');
    if (!staff_domain_allowed($email)) { flash('err', 'Staff emails must use an approved domain (Settings → Sign-in).'); redirect('/admin/settings/people'); }
    if (one('SELECT id FROM users WHERE email = ?', [$email])) { flash('err', 'Someone already uses that email.'); redirect('/admin/settings/people'); }
    if ($team && !one('SELECT id FROM teams WHERE id = ?', [$team])) $team = null;
    $id = insert('users', ['role' => 'admin', 'role_key' => $roleKey, 'team_id' => $team, 'client_id' => null, 'name' => $name, 'email' => $email, 'password_hash' => null, 'active' => 1, 'created_at' => now()]);
    audit('people.add', "$email as $roleKey");
    send_invite($id, true);
    redirect('/admin/settings/people/' . $id);
}

/** Guards shared by single and bulk actions. Returns an error or null. */
function person_guard(array $me, array $u, string $action, ?string $newRole = null): ?string
{
    if ($u['role'] !== 'admin') return 'That is not a staff account.';
    if ((int)$u['id'] === (int)$me['id'] && in_array($action, ['role', 'access', 'off', 'remove'], true)) return 'Nobody can change their own role or access.';
    $mine = role_permissions((string)$me['role_key']);
    if (array_diff(role_permissions((string)$u['role_key']), $mine)) return 'You cannot change someone who holds permissions you do not.';
    if ($newRole !== null && !array_key_exists($newRole, grantable_roles($me))) return 'Your role does not allow giving that role.';
    $losesOwner = $u['role_key'] === 'owner' && (int)$u['active'] && (in_array($action, ['off', 'remove', 'access'], true) || ($action === 'role' && $newRole !== 'owner'));
    if ($losesOwner && active_owner_count() <= 1) return 'There must always be at least one active Owner.';
    return null;
}

function st_people_bulk(): never
{
    $me = require_perm('people.manage');
    $ids = array_map('intval', (array)($_POST['ids'] ?? []));
    $action = p('action', 20);
    if (!$ids || $action === '') { flash('err', 'Select people and an action.'); redirect('/admin/settings/people'); }
    $done = 0;
    $refused = [];
    foreach (array_slice($ids, 0, 200) as $id) {
        $u = one("SELECT * FROM users WHERE id = ? AND role = 'admin'", [$id]);
        if (!$u) continue;
        $role = $action === 'role' ? p('role_key', 40) : null;
        if ($err = person_guard($me, $u, $action, $role)) { $refused[] = $u['name'] . ': ' . $err; continue; }
        switch ($action) {
            case 'role': q('UPDATE users SET role_key = ? WHERE id = ?', [$role, $id]); break;
            case 'team': $t = (int)p('team_id', 12); q('UPDATE users SET team_id = ? WHERE id = ?', [$t && one('SELECT id FROM teams WHERE id = ?', [$t]) ? $t : null, $id]); break;
            case 'invite': send_invite($id, false); break;
            case 'unlock': q('UPDATE users SET locked_until = NULL WHERE id = ?', [$id]); break;
            case 'signout': revoke_sessions($id); break;
            case 'on': q('UPDATE users SET active = 1 WHERE id = ?', [$id]); break;
            case 'off': q('UPDATE users SET active = 0 WHERE id = ?', [$id]); revoke_sessions($id); break;
            default: continue 2;
        }
        audit('people.' . $action, $u['email'] . ($role ? " → $role" : ''));
        $done++;
    }
    if ($refused) { audit('refused: people.' . $action, implode('; ', $refused)); flash('err', 'Not changed: ' . implode(' ', $refused)); }
    if ($done) flash('ok', "Done for $done " . ($done === 1 ? 'person' : 'people') . '.');
    redirect('/admin/settings/people');
}

function st_person(string $id): string
{
    $me = require_perm('people.view');
    $u = one("SELECT * FROM users WHERE id = ? AND role = 'admin'", [(int)$id]);
    if (!$u) abort();
    $manage = can('people.manage');
    $roles = array_column(roles_list(), 'name', 'rkey');
    $facts = '<dl class="facts"><dt>Email</dt><dd>' . e($u['email']) . '</dd><dt>Role</dt><dd>' . e($roles[$u['role_key']] ?? '') . '</dd><dt>Status</dt><dd>' . staff_status($u) . '</dd>'
        . '<dt>Two-step sign-in</dt><dd>' . ((int)$u['totp_enabled'] ? 'On, ' . count(json_decode((string)$u['recovery_codes'], true) ?: []) . ' recovery codes left' : ($u['sso_subject'] ? 'Uses single sign-on' : 'Off')) . '</dd>'
        . '<dt>Last sign-in</dt><dd>' . e(fmt_date($u['last_login'], 'j M Y H:i') ?: 'Never') . '</dd><dt>Password set</dt><dd>' . e(fmt_date($u['password_changed_at']) ?: 'Not yet') . '</dd>'
        . '<dt>Terms accepted</dt><dd>' . e(fmt_date($u['terms_at']) ?: 'Not yet') . '</dd><dt>Added</dt><dd>' . e(fmt_date($u['created_at'])) . '</dd></dl>';
    if (!$manage) return settings_layout('people', $u['name'], '', $facts);
    $grant = grantable_roles($me);
    if (!isset($grant[$u['role_key']])) $grant = [$u['role_key'] => $roles[$u['role_key']] ?? $u['role_key']] + $grant;
    $managers = ['' => '(nobody)'] + array_column(all("SELECT id, name FROM users WHERE role = 'admin' AND id <> ? ORDER BY name", [$u['id']]), 'name', 'id');
    $form = '<form method="post" action="/admin/settings/people/' . (int)$u['id'] . '" class="card edit">' . csrf_field() . '<div class="row">'
        . field('name', 'Name', $u['name'], ['required' => true, 'attrs' => ' maxlength="120"']) . field('email', 'Sign-in email', $u['email'], ['type' => 'email', 'required' => true, 'help' => 'Both the old and new address are told about a change.']) . '</div><div class="row">'
        . field('role_key', 'Role', (string)$u['role_key'], ['type' => 'select', 'options' => $grant]) . field('team_id', 'Team', (string)$u['team_id'], ['type' => 'select', 'options' => teams_options()]) . '</div>'
        . field('manager_id', 'Reports to', (string)$u['manager_id'], ['type' => 'select', 'options' => $managers])
        . '<button class="btn" type="submit">Save changes</button></form>';
    $id = (int)$u['id'];
    $actions = '<div class="actions">' . person_action_button($id, 'invite', 'Send a password link')
        . ($u['locked_until'] ? person_action_button($id, 'unlock', 'Unlock now') : '')
        . ((int)$u['totp_enabled'] ? person_action_button($id, 'reset2fa', 'Reset two-step sign-in', 'They will set it up again at their next sign-in.') : '')
        . person_action_button($id, 'signout', 'Sign out everywhere', 'End every session this person has open?')
        . person_action_button($id, 'access', (int)$u['active'] ? 'Turn access off' : 'Turn access on', (int)$u['active'] ? 'They are signed out at once and cannot sign in until access is turned back on.' : '')
        . person_action_button($id, 'remove', 'Remove', 'Remove this person for good? Their actions stay in the audit log.', 'danger') . '</div>';
    return settings_layout('people', $u['name'], '', $facts . $actions . $form);
}

function person_action_button(int $userId, string $action, string $label, string $confirm = '', string $cls = 'ghost'): string
{
    return '<form method="post" action="/admin/settings/people/' . $userId . '/' . e($action) . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>'
        . csrf_field() . '<button class="btn small ' . e($cls) . '" type="submit">' . e($label) . '</button></form>';
}

function st_person_save(string $id): never
{
    $me = require_perm('people.manage');
    $u = one("SELECT * FROM users WHERE id = ? AND role = 'admin'", [(int)$id]);
    if (!$u) abort();
    $back = '/admin/settings/people/' . (int)$u['id'];
    $role = p('role_key', 40);
    $email = strtolower(p('email', 191));
    $name = p('name', 120);
    if ($role !== $u['role_key'] && ($err = person_guard($me, $u, 'role', $role))) refuse('people.role', $err, $back);
    if ($err = person_guard($me, $u, 'edit')) refuse('people.edit', $err, $back);
    if (!valid_email($email) || $name === '') { flash('err', 'Enter a name and a valid email.'); redirect($back); }
    if ($email !== $u['email'] && one('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $u['id']])) { flash('err', 'Someone already uses that email.'); redirect($back); }
    $team = (int)p('team_id', 12);
    $mgr = (int)p('manager_id', 12);
    // Reporting lines cannot form a circle.
    for ($m = $mgr, $i = 0; $m && $i < 50; $i++) {
        if ($m === (int)$u['id']) { flash('err', 'Reporting lines cannot form a circle.'); redirect($back); }
        $m = (int)val('SELECT manager_id FROM users WHERE id = ?', [$m]);
    }
    update('users', (int)$u['id'], ['name' => $name, 'email' => $email, 'role_key' => $role, 'team_id' => $team && one('SELECT id FROM teams WHERE id = ?', [$team]) ? $team : null, 'manager_id' => $mgr ?: null]);
    if ($email !== $u['email']) {
        foreach ([$u['email'], $email] as $to) send_mail([$to], 'Your ' . brand_name() . ' sign-in email changed', "Hello {$name},\n\nThe sign-in email for your " . brand_name() . " staff account changed from {$u['email']} to $email.\n\nIf you did not expect this, tell an administrator at once.");
    }
    audit('people.edit', $email . ($role !== $u['role_key'] ? " role {$u['role_key']} → $role" : ''));
    flash('ok', 'Saved.');
    redirect($back);
}

function st_person_action(string $id, string $action): never
{
    $me = require_perm('people.manage');
    $u = one("SELECT * FROM users WHERE id = ? AND role = 'admin'", [(int)$id]);
    if (!$u) abort();
    $back = '/admin/settings/people/' . (int)$u['id'];
    $guardAction = $action === 'access' ? ((int)$u['active'] ? 'off' : 'on') : $action;
    if ($err = person_guard($me, $u, $guardAction)) refuse('people.' . $action, $err, $back);
    switch ($action) {
        case 'invite': send_invite((int)$u['id'], false); break;
        case 'unlock': q('UPDATE users SET locked_until = NULL WHERE id = ?', [$u['id']]); flash('ok', 'Unlocked.'); break;
        case 'reset2fa': q('UPDATE users SET totp_enabled = 0, totp_secret = NULL, recovery_codes = NULL WHERE id = ?', [$u['id']]); revoke_sessions((int)$u['id']); flash('ok', 'Two-step sign-in reset. They will set it up again at their next sign-in.'); break;
        case 'signout': revoke_sessions((int)$u['id']); flash('ok', 'Signed out everywhere.'); break;
        case 'access':
            q('UPDATE users SET active = ? WHERE id = ?', [(int)$u['active'] ? 0 : 1, $u['id']]);
            if ((int)$u['active']) revoke_sessions((int)$u['id']);
            flash('ok', (int)$u['active'] ? 'Access turned off.' : 'Access turned on.');
            break;
        case 'remove':
            q('UPDATE users SET manager_id = NULL WHERE manager_id = ?', [$u['id']]);
            q('UPDATE teams SET leader_id = NULL WHERE leader_id = ?', [$u['id']]);
            q('UPDATE enquiries SET assigned_to = NULL WHERE assigned_to = ?', [$u['id']]);
            q('DELETE FROM users WHERE id = ?', [$u['id']]);
            audit('people.remove', $u['email']);
            flash('ok', $u['name'] . ' was removed.');
            redirect('/admin/settings/people');
    }
    audit('people.' . $action, $u['email']);
    redirect($back);
}

function st_teams(): string
{
    require_perm('people.view');
    $teams = all('SELECT t.*, (SELECT COUNT(*) FROM users u WHERE u.team_id = t.id) AS members FROM teams t ORDER BY name');
    $by = [];
    foreach ($teams as $t) $by[(int)$t['parent_id']][] = $t;
    $names = array_column(all("SELECT id, name FROM users WHERE role = 'admin'"), 'name', 'id');
    $manage = can('people.manage');
    $html = $teams ? '<ul class="team-tree">' . team_tree_html($by, $names, $manage, 0, 0) . '</ul>' : empty_state('No teams yet', 'Teams group people, and leaders can be given enquiries for their team.');
    if ($manage) {
        $edit = g('edit') !== '' ? one('SELECT * FROM teams WHERE id = ?', [(int)g('edit')]) : null;
        $parents = ['' => '(top level)'];
        foreach ($teams as $t) if (!$edit || (int)$t['id'] !== (int)$edit['id']) $parents[(string)$t['id']] = $t['name'];
        $html .= '<form method="post" action="/admin/settings/teams" class="card inline-form">' . csrf_field() . '<h3>' . ($edit ? 'Edit ' . e($edit['name']) : 'Add a team') . '</h3>'
            . '<input type="hidden" name="id" value="' . (int)($edit['id'] ?? 0) . '"><div class="row">' . field('name', 'Name', $edit['name'] ?? '', ['required' => true, 'attrs' => ' maxlength="120"'])
            . field('parent_id', 'Sits below', (string)($edit['parent_id'] ?? ''), ['type' => 'select', 'options' => $parents]) . '</div>'
            . field('leader_id', 'Leader', (string)($edit['leader_id'] ?? ''), ['type' => 'select', 'options' => ['' => '(none)'] + $names])
            . field('description', 'Description', $edit['description'] ?? '', ['attrs' => ' maxlength="255"'])
            . '<div class="actions"><button class="btn" type="submit">' . ($edit ? 'Save team' : 'Add team') . '</button>' . ($edit ? '<a class="btn small ghost" href="/admin/settings/teams">Cancel</a>' : '') . '</div></form>'
            . ($edit ? '<form method="post" action="/admin/settings/teams/' . (int)$edit['id'] . '/delete" data-confirm="Remove this team? People in it keep their accounts and have no team.">' . csrf_field() . '<button class="btn small danger" type="submit">Remove team</button></form>' : '');
    }
    return settings_layout('teams', 'Teams', 'Teams form a tree. A leader sees and is notified about their team\'s enquiries.', $html);
}

/** One level of the team tree; calls itself for the teams below, at most 8 levels deep. */
function team_tree_html(array $byParent, array $names, bool $manage, int $parent, int $depth): string
{
    $o = '';
    foreach ($byParent[$parent] ?? [] as $t) {
        $o .= '<li><div class="team-row"><strong>' . e($t['name']) . '</strong> <span class="small">' . (int)$t['members'] . ' ' . ((int)$t['members'] === 1 ? 'person' : 'people')
            . ($t['leader_id'] ? ' &middot; led by ' . e($names[$t['leader_id']] ?? '?') : ' &middot; ' . chip('warn', 'No leader')) . '</span>'
            . ($t['description'] ? '<p class="small">' . e($t['description']) . '</p>' : '')
            . ($manage ? '<a class="small" href="?edit=' . (int)$t['id'] . '">Edit</a>' : '') . '</div>'
            . ($depth < 8 ? '<ul>' . team_tree_html($byParent, $names, $manage, (int)$t['id'], $depth + 1) . '</ul>' : '') . '</li>';
    }
    return $o;
}

function st_teams_save(): never
{
    require_perm('people.manage');
    $id = (int)p('id', 12);
    $name = p('name', 120);
    $parent = (int)p('parent_id', 12) ?: null;
    $leader = (int)p('leader_id', 12) ?: null;
    if ($name === '') { flash('err', 'Give the team a name.'); redirect('/admin/settings/teams'); }
    if ($parent && !one('SELECT id FROM teams WHERE id = ?', [$parent])) $parent = null;
    if ($leader && !one("SELECT id FROM users WHERE id = ? AND role = 'admin'", [$leader])) $leader = null;
    for ($p = $parent, $i = 0; $id && $p && $i < 50; $i++) {
        if ($p === $id) { flash('err', 'A team cannot sit below itself.'); redirect('/admin/settings/teams'); }
        $p = (int)val('SELECT parent_id FROM teams WHERE id = ?', [$p]);
    }
    $data = ['name' => $name, 'parent_id' => $parent, 'leader_id' => $leader, 'description' => p('description', 255)];
    if ($id && one('SELECT id FROM teams WHERE id = ?', [$id])) update('teams', $id, $data);
    else $id = insert('teams', $data + ['created_at' => now()]);
    audit('teams.save', $name);
    flash('ok', 'Saved.');
    redirect('/admin/settings/teams');
}

function st_team_delete(string $id): never
{
    require_perm('people.manage');
    $t = one('SELECT * FROM teams WHERE id = ?', [(int)$id]);
    if (!$t) abort();
    q('UPDATE teams SET parent_id = ? WHERE parent_id = ?', [$t['parent_id'], $t['id']]);
    q('UPDATE users SET team_id = NULL WHERE team_id = ?', [$t['id']]);
    q('UPDATE enquiries SET team_id = NULL WHERE team_id = ?', [$t['id']]);
    q('DELETE FROM teams WHERE id = ?', [$t['id']]);
    audit('teams.remove', $t['name']);
    flash('ok', 'Team removed.');
    redirect('/admin/settings/teams');
}

function st_roles(): string
{
    $me = require_perm('people.view');
    $roles = roles_list();
    $counts = array_column(all("SELECT role_key, COUNT(*) AS n FROM users WHERE role = 'admin' GROUP BY role_key"), 'n', 'role_key');
    $edit = can('roles.manage');
    $mine = role_permissions((string)$me['role_key']);
    $head = '<tr><th>Permission</th>';
    foreach ($roles as $r) $head .= '<th class="c">' . e($r['name']) . '<br><span class="small">' . (int)($counts[$r['rkey']] ?? 0) . ' ' . ((int)($counts[$r['rkey']] ?? 0) === 1 ? 'person' : 'people') . '</span></th>';
    $head .= '</tr>';
    $body = '';
    foreach (permission_catalog() as $group => $perms) {
        $body .= '<tr class="grp"><th colspan="' . (count($roles) + 1) . '">' . e($group) . '</th></tr>';
        foreach ($perms as $perm => $label) {
            $row = '<tr data-perm="' . e($perm . ' ' . strtolower($label)) . '"><td>' . e($label) . '<br><code class="small">' . e($perm) . '</code></td>';
            foreach ($roles as $r) {
                $has = in_array($perm, role_permissions($r['rkey']), true);
                $locked = !$edit || $r['rkey'] === 'owner' || !in_array($perm, $mine, true);
                $row .= '<td class="c"><input type="checkbox" name="perm[' . e($r['rkey']) . '][]" value="' . e($perm) . '"' . ($has ? ' checked' : '') . ($locked ? ' disabled' : '') . ' aria-label="' . e($r['name'] . ': ' . $label) . '"></td>';
            }
            $body .= $row . '</tr>';
        }
    }
    $names = '';
    if ($edit) {
        $names = '<tr><td>Role name</td>';
        foreach ($roles as $r) $names .= '<td class="c">' . ($r['rkey'] === 'owner' ? '' : '<input class="mini" name="name[' . e($r['rkey']) . ']" value="' . e($r['name']) . '" maxlength="80" aria-label="Name of ' . e($r['name']) . '">') . '</td>';
        $names .= '</tr>';
    }
    $tools = '<div class="filters"><div class="field"><label for="perm-find">Find a permission</label><input id="perm-find" type="search" data-filter-rows="#roles-matrix tbody tr[data-perm]"></div>'
        . '<label class="check"><input type="checkbox" data-only-diff="#roles-matrix"> <span>Show only the differences</span></label></div>';
    $matrix = '<form method="post" action="/admin/settings/roles">' . csrf_field() . '<div class="table-wrap"><table id="roles-matrix" class="matrix"><thead>' . $head . '</thead><tbody>' . $body . $names . '</tbody></table></div>'
        . ($edit ? '<div class="actions"><button class="btn" type="submit">Save role changes</button><a class="btn small ghost" href="/admin/settings/roles">Discard</a></div>' : '') . '</form>';
    $new = '';
    if ($edit) {
        $custom = array_filter($roles, fn($r) => !(int)$r['builtin']);
        $new = '<form method="post" action="/admin/settings/roles/new" class="card inline-form">' . csrf_field() . '<h3>New role</h3><div class="row">'
            . field('name', 'Name', '', ['required' => true, 'attrs' => ' maxlength="80"']) . field('from', 'Start from', 'viewer', ['type' => 'select', 'options' => array_column(array_filter($roles, fn($r) => $r['rkey'] !== 'owner'), 'name', 'rkey')]) . '</div>'
            . '<button class="btn small" type="submit">Create role</button></form>';
        foreach ($custom as $r) {
            if (!(int)($counts[$r['rkey']] ?? 0)) $new .= '<form method="post" action="/admin/settings/roles/' . e($r['rkey']) . '/delete" data-confirm="Remove the role ' . e($r['name']) . '?" class="inline">' . csrf_field() . '<button class="linkish" type="submit">Remove ' . e($r['name']) . '</button></form> ';
        }
    }
    $rules = '<p class="small">Owner always holds everything. Nobody gives a permission they do not hold, only an Owner gives the Owner role, and there is always an active Owner. Built-in roles and roles that people hold cannot be removed.</p>';
    return settings_layout('roles', 'Roles & permissions', 'Every role against every permission.', $tools . $matrix . $rules . $new);
}

function st_roles_save(): never
{
    $me = require_perm('roles.manage');
    $mine = role_permissions((string)$me['role_key']);
    $posted = (array)($_POST['perm'] ?? []);
    $changed = [];
    foreach (roles_list() as $r) {
        if ($r['rkey'] === 'owner') continue;
        $before = role_permissions($r['rkey']);
        $want = array_values(array_intersect((array)($posted[$r['rkey']] ?? []), all_permissions()));
        // Permissions the editor does not hold are left exactly as they were.
        $after = array_values(array_unique(array_merge(array_diff($before, $mine), array_intersect($want, $mine))));
        sort($after);
        sort($before);
        $posted_name = $_POST['name'][$r['rkey']] ?? $r['name'];
        $name = trim(is_string($posted_name) ? $posted_name : $r['name']);
        $name = mb_substr($name !== '' ? $name : $r['name'], 0, 80);
        if ($after !== $before || $name !== $r['name']) {
            q('UPDATE roles SET permissions = ?, name = ? WHERE rkey = ?', [json_encode($after), $name, $r['rkey']]);
            $changed[] = $r['rkey'] . ' (+' . implode(',', array_diff($after, $before)) . ' -' . implode(',', array_diff($before, $after)) . ')';
        }
    }
    audit('roles.save', $changed ? implode('; ', $changed) : 'no change');
    flash('ok', $changed ? 'Role changes saved.' : 'Nothing changed.');
    redirect('/admin/settings/roles');
}

function st_role_new(): never
{
    $me = require_perm('roles.manage');
    $name = p('name', 80);
    $from = p('from', 40);
    if ($name === '' || $from === 'owner' || !one('SELECT rkey FROM roles WHERE rkey = ?', [$from])) { flash('err', 'Give the role a name and choose a role to start from.'); redirect('/admin/settings/roles'); }
    $key = substr(slugify($name), 0, 30);
    while (one('SELECT rkey FROM roles WHERE rkey = ?', [$key])) $key = substr(slugify($name), 0, 26) . '-' . random_int(10, 99);
    $perms = array_values(array_intersect(role_permissions($from), role_permissions((string)$me['role_key'])));
    insert('roles', ['rkey' => $key, 'name' => $name, 'description' => 'Custom role', 'permissions' => json_encode($perms), 'builtin' => 0, 'sort' => 100]);
    audit('roles.new', "$key from $from");
    flash('ok', 'Role created. Adjust its permissions below.');
    redirect('/admin/settings/roles');
}

function st_role_delete(string $key): never
{
    require_perm('roles.manage');
    $r = one('SELECT * FROM roles WHERE rkey = ?', [$key]);
    if (!$r || (int)$r['builtin']) refuse('roles.remove', 'Built-in roles cannot be removed.', '/admin/settings/roles');
    if (val('SELECT COUNT(*) FROM users WHERE role_key = ?', [$key])) refuse('roles.remove', 'People still hold this role.', '/admin/settings/roles');
    q('DELETE FROM roles WHERE rkey = ?', [$key]);
    audit('roles.remove', $key);
    flash('ok', 'Role removed.');
    redirect('/admin/settings/roles');
}

// =====================================================================
// RECORDS
// =====================================================================

function audit_query(): array
{
    $where = [];
    $args = [];
    if (($t = g('q', 100)) !== '') { $where[] = "(a.action LIKE ? ESCAPE '!' OR a.detail LIKE ? ESCAPE '!')"; $l = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $t) . '%'; array_push($args, $l, $l); }
    if (($w = g('who')) !== '') { $where[] = 'a.user_id = ?'; $args[] = (int)$w; }
    if (($a = g('what')) !== '') { $where[] = "a.action LIKE ? ESCAPE '!'"; $args[] = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $a) . '%'; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', g('from'))) { $where[] = 'a.created_at >= ?'; $args[] = g('from') . ' 00:00:00'; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', g('to'))) { $where[] = 'a.created_at <= ?'; $args[] = g('to') . ' 23:59:59'; }
    if (g('outcome') === 'refused') $where[] = "a.action LIKE 'refused:%'";
    if (g('outcome') === 'done') $where[] = "a.action NOT LIKE 'refused:%'";
    return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $args];
}

function st_audit(): string
{
    require_perm('audit.view');
    [$w, $args] = audit_query();
    $page = max(0, (int)g('page'));
    $rows = all('SELECT a.*, u.name, u.email FROM audit a LEFT JOIN users u ON u.id = a.user_id' . $w . ' ORDER BY a.id DESC LIMIT 101 OFFSET ' . ($page * 100), $args);
    $more = count($rows) > 100;
    $rows = array_slice($rows, 0, 100);
    $people = ['' => 'Anyone'] + array_column(all("SELECT id, name FROM users WHERE role = 'admin' ORDER BY name"), 'name', 'id');
    $areas = ['' => 'Anything', 'signin' => 'Sign-in', 'people' => 'People', 'roles' => 'Roles', 'teams' => 'Teams', 'settings' => 'Settings', 'enquiry' => 'Enquiries', 'client' => 'Clients',
        'quote' => 'Quotes', 'document' => 'Documents', 'backup' => 'Backups', 'system' => 'System', 'account' => 'Accounts', 'terms' => 'Terms', 'refused' => 'Refused'];
    $f = '<form class="filters" method="get">' . field('q', 'Search', g('q', 100), ['type' => 'search']) . field('who', 'Who', g('who'), ['type' => 'select', 'options' => $people])
        . field('what', 'What', g('what'), ['type' => 'select', 'options' => $areas]) . field('from', 'From', g('from'), ['type' => 'date']) . field('to', 'To', g('to'), ['type' => 'date'])
        . field('outcome', 'Outcome', g('outcome'), ['type' => 'select', 'options' => ['' => 'Any', 'done' => 'Done', 'refused' => 'Refused']]) . '<button class="btn small" type="submit">Filter</button></form>';
    $qs = http_build_query(array_intersect_key($_GET, array_flip(['q', 'who', 'what', 'from', 'to', 'outcome'])));
    $tools = '<div class="actions"><a class="btn small ghost" href="/admin/settings/audit.csv?' . e($qs) . '">Export CSV</a><form method="post" action="/admin/settings/audit/verify">' . csrf_field() . '<button class="btn small ghost" type="submit">Check the log is intact</button></form></div>';
    $t = '<div class="table-wrap"><table><thead><tr><th>When (UTC)</th><th>Who</th><th>What</th><th>Details</th><th>Address</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $ref = str_starts_with($r['action'], 'refused:');
        $t .= '<tr' . ($ref ? ' class="refused"' : '') . '><td>' . e($r['created_at']) . '</td><td>' . e($r['name'] ?? 'System or visitor') . ($r['email'] ? '<br><span class="small">' . e($r['email']) . '</span>' : '') . '</td>'
            . '<td>' . e(audit_label($r['action'])) . '<br><code class="small">' . e($r['action']) . '</code></td><td>' . e($r['detail']) . '</td><td><code class="small" title="Keyed hash of the IP address">' . e(substr((string)$r['ip_hash'], 0, 10)) . '</code></td></tr>';
    }
    $t .= '</tbody></table></div>';
    if (!$rows) $t = empty_state('Nothing recorded', 'No entries match these filters.');
    $nav = '<div class="actions">' . ($page > 0 ? '<a class="btn small ghost" href="?' . e($qs . '&page=' . ($page - 1)) . '">Newer entries</a>' : '') . ($more ? '<a class="btn small ghost" href="?' . e($qs . '&page=' . ($page + 1)) . '">Older entries</a>' : '') . '</div>';
    audit('audit.viewed', 'page ' . $page);
    return settings_layout('audit', 'Audit log', 'Every change and every refusal, chained so that any edit or deletion is detectable. IP addresses are kept only as a keyed hash.', $f . $tools . $t . $nav);
}

function audit_label(string $a): string
{
    $map = ['signin' => 'Signed in', 'signin.failed' => 'Sign-in failed', 'signin.locked' => 'Account locked', 'signin.mfa_failed' => 'Wrong two-step code', 'logout' => 'Signed out',
        'people.add' => 'Added a person', 'people.edit' => 'Changed a person', 'people.remove' => 'Removed a person', 'roles.save' => 'Changed roles', 'settings' => 'Changed settings',
        'backup.created' => 'Backup taken', 'backup.restored' => 'Backup restored', 'terms.accepted' => 'Accepted the terms', 'account.two_step_on' => 'Turned on two-step sign-in', 'audit.viewed' => 'Viewed the audit log',
        'audit.exported' => 'Exported the audit log', 'audit.verified' => 'Checked the audit log', 'system.migrated' => 'Database updated', 'install' => 'Installed'];
    if (str_starts_with($a, 'refused:')) return 'Refused: ' . trim(substr($a, 8));
    if (isset($map[$a])) return $map[$a];
    $head = explode('.', $a)[0];
    return $map[$head] ?? ucfirst(str_replace(['.', '_'], ' ', $a));
}

function st_audit_csv(): never
{
    require_perm('audit.view');
    [$w, $args] = audit_query();
    audit('audit.exported', http_build_query($_GET));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit-' . gmdate('Ymd-His') . '.csv"');
    no_store();
    $out = fopen('php://output', 'w');
    fputcsv($out, ['When (UTC)', 'Who', 'Email', 'Action', 'Details', 'Address hash', 'Entry hash']);
    foreach (q('SELECT a.*, u.name, u.email FROM audit a LEFT JOIN users u ON u.id = a.user_id' . $w . ' ORDER BY a.id DESC LIMIT 50000', $args) as $r) {
        // Prefix cells that a spreadsheet would treat as a formula.
        $cells = array_map(fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : (string)$v, [$r['created_at'], $r['name'] ?? '', $r['email'] ?? '', $r['action'], $r['detail'], $r['ip_hash'], $r['hash']]);
        fputcsv($out, $cells);
    }
    fclose($out);
    exit;
}

function st_audit_verify(): never
{
    require_perm('audit.view');
    [$ok, $n, $bad] = audit_verify();
    audit('audit.verified', $ok ? "$n entries intact" : "broken at #$bad");
    flash($ok ? 'ok' : 'err', $ok ? "The log is intact: $n entries, each chained to the one before." : "The chain is broken at entry #$bad. An entry was changed or removed outside the app.");
    redirect('/admin/settings/audit');
}

function st_email_log(): string
{
    require_perm('audit.view');
    $since = gmdate('Y-m-d H:i:s', time() - 86400);
    $sent = (int)val("SELECT COUNT(*) FROM mail_log WHERE status = 'sent' AND created_at > ?", [$since]);
    $failed = (int)val("SELECT COUNT(*) FROM mail_log WHERE status = 'failed' AND created_at > ?", [$since]);
    $lastOk = (string)val("SELECT created_at FROM mail_log WHERE status = 'sent' ORDER BY id DESC LIMIT 1");
    $tiles = '<div class="stats"><div class="stat card"><span class="n">' . $sent . '</span><span>Sent in 24 hours</span></div><div class="stat card"><span class="n' . ($failed ? ' bad' : '') . '">' . $failed . '</span><span>Failed in 24 hours</span></div>'
        . '<div class="stat card"><span class="n small-n">' . e($lastOk ? fmt_date($lastOk, 'j M H:i') : 'Never') . '</span><span>Last delivered</span></div></div>';
    $status = in_array(g('status'), ['sent', 'failed'], true) ? g('status') : '';
    $rows = all('SELECT * FROM mail_log' . ($status ? ' WHERE status = ?' : '') . ' ORDER BY id DESC LIMIT 200', $status ? [$status] : []);
    $f = '<form class="filters" method="get">' . field('status', 'Status', $status, ['type' => 'select', 'options' => ['' => 'Any', 'sent' => 'Sent', 'failed' => 'Failed']]) . '<button class="btn small" type="submit">Filter</button></form>';
    $t = '<div class="table-wrap"><table><thead><tr><th>When (UTC)</th><th>To</th><th>Subject</th><th>Result</th></tr></thead><tbody>';
    foreach ($rows as $r) $t .= '<tr><td>' . e($r['created_at']) . '</td><td>' . e($r['recipients']) . '</td><td>' . e($r['subject']) . '</td><td>' . ($r['status'] === 'sent' ? chip('ok', 'Sent') : chip('fail', 'Failed') . '<br><span class="small">' . e($r['error']) . '</span>') . '</td></tr>';
    $t .= '</tbody></table></div>';
    if (!$rows) $t = empty_state('No emails yet', 'Every notification, invitation and password link the site sends is listed here, with the reason when one fails.');
    $why = $failed ? '<div class="note">Emails are failing. Open <a href="/admin/settings/email">Email (SMTP)</a> and run the test: it shows the server\'s answer. Enquiries are always saved in the admin panel, so nothing is lost.</div>' : '';
    return settings_layout('email-log', 'Email log', 'What the site sent and whether the mail server accepted it. Message bodies are not stored.', $tiles . $why . $f . $t);
}

function st_email_test(): never
{
    require_perm('settings.manage');
    $to = notify_recipients();
    $ok = send_mail($to, brand_name() . ' website: test email', "This is a test from " . brand_name() . " Settings → Email (SMTP).\n\nIf you can read this, enquiry notifications will reach you.");
    $err = $ok ? '' : (string)val("SELECT error FROM mail_log WHERE status = 'failed' ORDER BY id DESC LIMIT 1");
    set_setting('mail_last_test', ($ok ? 'ok ' : 'failed ') . gmdate('Y-m-d H:i') . ' UTC' . ($err ? " ($err)" : ''));
    audit('settings.email_test', $ok ? 'ok' : 'failed');
    flash($ok ? 'ok' : 'err', $ok ? 'Test email sent to ' . implode(', ', $to) . '.' : 'Sending failed: ' . ($err ?: 'see the email log') . '.');
    redirect('/admin/settings/email');
}

function st_sso_test(): never
{
    require_perm('settings.manage');
    try {
        $d = sso_discovery(true);
        $keys = https_json($d['jwks_uri'])['keys'] ?? [];
        $msg = 'The provider answered. Issuer ' . $d['issuer'] . ', ' . count($keys) . ' signing key(s).' . (secret_setting('sso_client_secret') === '' ? ' Add the client secret before turning it on.' : '');
        flash('ok', $msg);
        audit('settings.sso_test', 'ok');
    } catch (Throwable $ex) {
        // Every error is logged in full. Provider errors (RuntimeException) are safe to show;
        // anything else (a database or JSON error) is described in plain words only.
        log_line('app.log', 'sso check failed: ' . get_class($ex) . ': ' . $ex->getMessage());
        $known = $ex instanceof RuntimeException;
        flash('err', $known ? 'The check failed: ' . $ex->getMessage() : 'The check could not be completed. The details are in the application log.');
        audit('settings.sso_test', $known ? 'failed' : 'error');
    }
    redirect('/admin/settings/sso');
}

// =====================================================================
// SYSTEM: HTTPS, updates, backups & health, help, about
// =====================================================================

function tls_certificate(): ?array
{
    $host = parse_url(base_url(), PHP_URL_HOST);
    if (!$host || !str_starts_with(base_url(), 'https://')) return null;
    $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
    $fp = @stream_socket_client("ssl://$host:443", $no, $err, 5, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return ['error' => $err ?: 'Could not connect'];
    $cert = stream_context_get_params($fp)['options']['ssl']['peer_certificate'] ?? null;
    fclose($fp);
    $i = $cert ? openssl_x509_parse($cert) : null;
    if (!$i) return ['error' => 'No certificate'];
    return ['subject' => $i['subject']['CN'] ?? '', 'issuer' => ($i['issuer']['O'] ?? '') . ' ' . ($i['issuer']['CN'] ?? ''), 'from' => $i['validFrom_time_t'], 'to' => $i['validTo_time_t'],
        'names' => str_replace('DNS:', '', $i['extensions']['subjectAltName'] ?? '')];
}

function https_status_card(): string
{
    $c = tls_certificate();
    if ($c === null) $cert = '<p>' . chip('fail', 'Not HTTPS') . ' The site address in app/config.php does not start with https://.</p>';
    elseif (isset($c['error'])) $cert = '<p>' . chip('fail', 'Not trusted') . ' ' . e($c['error']) . '</p>';
    else {
        $days = (int)floor(($c['to'] - time()) / 86400);
        $cert = '<dl class="facts"><dt>Status</dt><dd>' . chip($days > 14 ? 'ok' : 'warn', $days > 14 ? 'Trusted by browsers' : "Expires in $days days") . '</dd><dt>Issued to</dt><dd>' . e($c['subject']) . '</dd><dt>Also covers</dt><dd>' . e($c['names']) . '</dd>'
            . '<dt>Issued by</dt><dd>' . e(trim($c['issuer'])) . '</dd><dt>Valid until</dt><dd>' . e(gmdate('j M Y', $c['to'])) . " ($days days)</dd></dl>";
    }
    $headers = ticks(['Every HTTP request is redirected to HTTPS (.htaccess)', 'www is redirected to the bare domain', 'Content-Security-Policy with no inline script or style',
        'X-Frame-Options DENY and frame-ancestors none', 'nosniff, strict Referrer-Policy, Permissions-Policy, Cross-Origin-Opener-Policy', 'Session cookie: __Host- prefix, Secure, HttpOnly, SameSite=Lax']);
    return '<div class="card inline-form"><h3>Certificate</h3>' . $cert . '<p class="small">Hostinger renews it automatically. To replace it, use hPanel → Security → SSL.</p></div><div class="card inline-form"><h3>Always on</h3>' . $headers . '</div>';
}

function version_label(string $v = APP_VERSION): string
{
    $p = array_map('intval', explode('.', $v) + [0, 0, 0]);
    return sprintf('C4C-%02d.%02d.%02d', $p[0], $p[1], $p[2]);
}

function build_commit(): string
{
    $gitDir = realpath(ROOT . '/.git');
    if ($gitDir === false || !is_file($gitDir . '/HEAD')) return 'unknown';
    $head = trim((string)file_get_contents($gitDir . '/HEAD'));
    if (preg_match('#^ref: (refs/heads/[A-Za-z0-9._/-]{1,200})$#', $head, $m) && !str_contains($m[1], '..')) {
        // Only a plain branch reference inside .git is followed, never a path from elsewhere.
        $refFile = realpath($gitDir . '/' . $m[1]);
        if ($refFile === false || !str_starts_with($refFile, $gitDir . DIRECTORY_SEPARATOR)) return 'unknown';
        $head = trim((string)file_get_contents($refFile));
    }
    return preg_match('/^[0-9a-f]{40}$/', $head) ? substr($head, 0, 12) : 'unknown';
}

/** Compares every shipped file with the SHA-256 list in SHA256SUMS made at release time. */
function integrity_check(): array
{
    $sums = [];
    foreach (lines_of((string)@file_get_contents(ROOT . '/SHA256SUMS')) as $line) {
        if (preg_match('#^([0-9a-f]{64})  ([A-Za-z0-9._/-]+)$#', $line, $m) && !str_contains($m[2], '..')) $sums[$m[2]] = $m[1];
    }
    if (!$sums) return ['state' => 'unknown', 'changed' => [], 'missing' => [], 'checked' => 0, 'version' => ''];
    $changed = $missing = [];
    foreach ($sums as $path => $sum) {
        $full = ROOT . '/' . $path;
        if (!is_file($full)) $missing[] = $path;
        elseif (!hash_equals($sum, hash_file('sha256', $full))) $changed[] = $path;
    }
    return ['state' => $changed || $missing ? 'changed' : 'genuine', 'changed' => $changed, 'missing' => $missing, 'checked' => count($sums), 'version' => APP_VERSION];
}

function st_updates(): string
{
    require_perm('system.updates');
    $i = integrity_check();
    $state = match ($i['state']) { 'genuine' => chip('ok', 'Genuine'), 'changed' => chip('fail', 'Changed'), default => chip('warn', 'No manifest') };
    $cards = '<div class="stats"><div class="stat card"><span class="small">Running</span><span class="n small-n">' . e(version_label()) . '</span><span class="small">' . e(APP_VERSION) . ' &middot; commit ' . e(build_commit()) . '</span></div>'
        . '<div class="stat card"><span class="small">Installation</span><span class="n small-n">' . e(ucfirst(setting('environment', 'production'))) . '</span><span class="small">Change it in General</span></div>'
        . '<div class="stat card"><span class="small">Software integrity</span><span class="n small-n">' . $state . '</span><span class="small">' . (int)$i['checked'] . ' files checked</span></div></div>';
    $diff = '';
    if ($i['changed'] || $i['missing']) $diff = '<div class="note">Files that differ from the released version ' . e($i['version']) . ': ' . e(implode(', ', array_slice(array_merge($i['changed'], $i['missing']), 0, 30))) . '. If nobody edited them on the server on purpose, treat this as a security incident and redeploy.</div>';
    $done = applied_migrations();
    $pending = pending_migrations();
    $rows = '';
    foreach (migrations() as $v => $m) {
        $rows .= '<tr><td><code>' . e($v) . '</code></td><td>' . e($m[0]) . '</td><td>' . (isset($done[$v]) ? chip('ok', 'Applied ' . fmt_date($done[$v])) : chip('warn', 'Pending')) . '</td></tr>';
    }
    $apply = $pending ? '<form method="post" action="/admin/settings/updates" data-confirm="Apply ' . count($pending) . ' database update(s)? A backup is taken first.">' . csrf_field() . '<button class="btn" type="submit">Back up and apply now</button></form>' : '';
    $how = '<div class="card inline-form"><h3>How updates arrive</h3>' . ticks([
        'A release is merged to the main branch on GitHub after its tests and the Checkmarx scan pass.',
        'Hostinger auto-deploy pulls it into the site within a minute.',
        'Database updates apply automatically on the first request, under a lock, and are listed here.',
        'SHA256SUMS, made at release time, lets this page prove every file is the released one.',
        'To roll back, restore the previous release on GitHub and, if its data changed, restore the backup taken before the update.',
    ]) . '</div>';
    return settings_layout('updates', 'Updates', 'What is running, whether it is genuine, and the database updates that came with it.', $cards . $diff . '<h2>Database updates</h2><div class="table-wrap"><table><thead><tr><th>Version</th><th>What it does</th><th>State</th></tr></thead><tbody>' . $rows . '</tbody></table></div>' . $apply . $how);
}

function st_updates_apply(): never
{
    require_perm('system.updates');
    $b = create_backup('pre-update');
    $applied = run_migrations();
    @file_put_contents(STORAGE . '/schema.version', latest_migration(), LOCK_EX);
    audit('system.migrated', ($applied ? implode(', ', $applied) : 'nothing pending') . "; backup $b");
    flash('ok', $applied ? 'Applied ' . implode(', ', $applied) . ". Backup $b was taken first." : 'Nothing was pending.');
    redirect('/admin/settings/updates');
}

function readiness_checks(): array
{
    $meant = json_decode(setting('readiness_as_meant'), true) ?: [];
    $h = [];
    foreach (health_checks() as $c) $h[$c['label']] = $c;
    $last = last_backup();
    $cert = tls_certificate();
    $certOk = $cert && !isset($cert['error']) && $cert['to'] - time() > 14 * 86400;
    $owners = active_owner_count();
    $staleInvites = (int)val("SELECT COUNT(*) FROM users WHERE password_hash IS NULL AND sso_subject IS NULL AND active = 1 AND created_at < ?", [gmdate('Y-m-d H:i:s', time() - 14 * 86400)]);
    $teamsNoLeader = (int)val('SELECT COUNT(*) FROM teams WHERE leader_id IS NULL');
    $sysHolders = 0;
    foreach (roles_list() as $r) if (!in_array($r['rkey'], ['owner', 'admin'], true) && array_intersect(role_permissions($r['rkey']), ['settings.manage', 'system.backups', 'system.updates', 'roles.manage'])) $sysHolders += (int)val('SELECT COUNT(*) FROM users WHERE role_key = ?', [$r['rkey']]);
    $errors = 0;
    $log = STORAGE . '/logs/php-error.log';
    if (is_file($log) && filemtime($log) > time() - 86400) $errors = substr_count((string)file_get_contents($log, false, null, max(0, filesize($log) - 200000)), 'PHP Fatal') + substr_count((string)file_get_contents($log, false, null, max(0, filesize($log) - 200000)), 'Error:');
    $checks = [
        ['backup-day', 'A backup in the last day', $last && time() - $last['time'] < 86400 ? 'ok' : 'warn', 'backups', ''],
        ['backup-sealed', 'Backups are sealed (encrypted)', setting('backup_encrypt', '1') === '1' ? 'ok' : 'warn', 'backups', ''],
        ['backup-offsite', 'Backups are also kept away from this server', setting('backup_offsite_note') !== '' ? 'ok' : 'check', 'backups', 'Download one each week to your company drive, then mark this as meant.'],
        ['restore-tried', 'A restore has been tried', setting('restore_tested_at') !== '' ? 'ok' : 'warn', 'backups', ''],
        ['audit', 'Audit log intact', ($h['Audit log integrity']['status'] ?? 'fail') === 'ok' ? 'ok' : 'warn', 'audit', ''],
        ['cert', 'A certificate browsers trust, valid 14+ days', $certOk ? 'ok' : 'warn', 'https', ''],
        ['env', 'Marked as Staging or Production', setting('environment') !== '' ? 'ok' : 'warn', 'general', ''],
        ['email', 'Email set up and tested', str_starts_with(setting('mail_last_test'), 'ok') ? 'ok' : 'warn', 'email', ''],
        ['disk', 'More than 1 GB disk free', ($h['Free disk space']['status'] ?? 'warn') === 'ok' ? 'ok' : 'warn', 'backups', ''],
        ['cron', 'Scheduled jobs are running', ($h['Scheduled jobs (cron)']['status'] ?? 'warn') === 'ok' ? 'ok' : 'warn', 'backups', ''],
        ['owners', 'At least two active Owners', $owners >= 2 ? 'ok' : 'warn', 'people', 'So nobody is locked out if one person leaves.'],
        ['twostep', 'Every staff account uses two-step sign-in or SSO', ($h['Two-step sign-in for staff']['status'] ?? 'warn') === 'ok' ? 'ok' : 'warn', 'people', ''],
        ['invites', 'No invitation unanswered after 14 days', $staleInvites ? 'warn' : 'ok', 'people', ''],
        ['leaders', 'Every team has a leader', $teamsNoLeader ? 'warn' : 'ok', 'teams', ''],
        ['system-perms', 'Only Owners and Administrators hold system permissions', $sysHolders ? 'check' : 'ok', 'roles', ''],
        ['install-key', 'Install key removed from the config file', ($h['Install key removed']['status'] ?? 'warn') === 'ok' ? 'ok' : 'warn', 'backups', ''],
        ['debug', 'Debug mode off', cfg('debug') ? 'warn' : 'ok', 'backups', ''],
        ['company', 'Company name and address shown in the footer', setting('company_legal_name') !== '' && setting('company_address') !== '' ? 'ok' : 'warn', 'general', ''],
        ['rates', 'A rate card is set', rate_card() ? 'ok' : 'warn', 'rates', ''],
        ['errors', 'No errors in the last day', $errors ? 'warn' : 'ok', 'backups', ''],
    ];
    foreach ($checks as &$c) if ($c[2] === 'check' && isset($meant[$c[0]])) $c[2] = 'meant';
    return $checks;
}

function st_backups(): string
{
    require_perm('system.backups');
    $list = list_backups();
    $b = '<div class="actions"><form method="post" action="/admin/settings/backups/run">' . csrf_field() . '<button class="btn" type="submit">Back up now</button></form>'
        . '<a class="btn small ghost" href="/admin/settings/backups/report">Download troubleshooting report</a></div>';
    $b .= '<p class="small">Nightly through cron, before every database update and before every restore, and by hand. The newest ' . (int)setting('backup_keep', '14') . ' are kept'
        . (setting('backup_encrypt', '1') === '1' ? ', each sealed with the app key (a backup cannot be read without app/config.php).' : '. <strong>Backups are not sealed.</strong>') . '</p>';
    if (setting('backup_encrypt', '1') !== '1') $b .= '<div class="note">Backups contain client data in readable form. Turn sealing back on below.</div>';
    $b .= '<div class="note">Backups sit on the same server as the data. Download one regularly and keep it somewhere else.</div>';
    if ($list) {
        $t = '<div class="table-wrap"><table><thead><tr><th>Backup</th><th>Taken (UTC)</th><th>Size</th><th></th></tr></thead><tbody>';
        foreach ($list as $x) {
            $t .= '<tr><td><code class="small">' . e($x['name']) . '</code></td><td>' . e(gmdate('Y-m-d H:i', $x['time'])) . '</td><td>' . number_format($x['size'] / 1024, 0) . ' KB</td><td class="row-actions">'
                . '<a href="/admin/settings/backups/download?name=' . e(rawurlencode($x['name'])) . '">Download</a>'
                . '<form method="post" action="/admin/settings/backups/restore" data-confirm="Replace ALL current data with this backup from ' . e(gmdate('j M Y H:i', $x['time'])) . ' UTC? A backup of the current data is taken first.">' . csrf_field()
                . '<input type="hidden" name="name" value="' . e($x['name']) . '"><input type="hidden" name="confirm" value="RESTORE"><button class="linkish" type="submit">Restore</button></form></td></tr>';
        }
        $b .= $t . '</tbody></table></div>';
    } else {
        $b .= empty_state('No backups yet', 'Press Back up now, and add the cron job below so one is taken every night.');
    }
    $b .= '<form method="post" action="/admin/settings/backups" class="card inline-form">' . csrf_field() . '<h3>Backup settings</h3>'
        . st_field('backup_keep', ['Keep the newest', 'int', ['min' => 3, 'max' => 90]], setting('backup_keep', '14'))
        . st_field('backup_encrypt', ['Seal backups with the app key', 'bool', []], setting('backup_encrypt', '1'))
        . st_field('backup_offsite_note', ['Where off-server copies are kept', 'text', ['help' => 'For example: downloaded every Monday to the company SharePoint.']], setting('backup_offsite_note'))
        . '<button class="btn small" type="submit">Save</button></form>';

    $checks = health_checks();
    $h = '<div class="table-wrap"><table><thead><tr><th>Area</th><th>Check</th><th>Result</th></tr></thead><tbody>';
    foreach ($checks as $c) $h .= '<tr><td>' . e($c['area']) . '</td><td>' . e($c['label']) . '</td><td>' . chip($c['status'], ['ok' => 'OK', 'warn' => 'Check', 'fail' => 'Problem'][$c['status']]) . ' <span class="small">' . e($c['detail']) . '</span></td></tr>';
    $h .= '</tbody></table></div>';
    $r = '<div class="table-wrap"><table><thead><tr><th>Ready for production?</th><th>State</th><th></th></tr></thead><tbody>';
    $readyN = 0;
    foreach (readiness_checks() as [$key, $label, $st, $page, $hint]) {
        if (in_array($st, ['ok', 'meant'], true)) $readyN++;
        $chipHtml = match ($st) { 'ok' => chip('ok', 'Ready'), 'meant' => chip('ok', 'As meant'), 'check' => chip('warn', 'Check'), default => chip('warn', 'To do') };
        $r .= '<tr><td>' . e($label) . ($hint ? '<br><span class="small">' . e($hint) . '</span>' : '') . '</td><td>' . $chipHtml . '</td><td class="row-actions"><a href="/admin/settings/' . e($page) . '">Open</a>'
            . ($st === 'check' ? '<form method="post" action="/admin/settings/backups/as-meant">' . csrf_field() . '<input type="hidden" name="key" value="' . e($key) . '"><button class="linkish" type="submit">As meant</button></form>' : '') . '</td></tr>';
    }
    $r .= '</tbody></table></div>';
    $total = count(readiness_checks());
    $cron = '<div class="card inline-form"><h3>Scheduled jobs</h3><p class="small">In hPanel → Advanced → Cron Jobs, add a custom job that runs every hour. Use the site\'s PHP 8.3 and load sodium, because the default command-line PHP does not have it:</p><pre class="msg">/opt/alt/php83/usr/bin/php -d extension=sodium.so ' . e(ROOT) . '/cron.php</pre>'
        . '<p class="small">It takes the nightly backup, applies retention, expires old quotes and tidies sessions and logs.</p></div>';
    return settings_layout('backups', 'Backups & health', '', '<h2>Ready for production? <span class="small">' . $readyN . ' of ' . $total . ' ready</span></h2>' . $r . '<h2>Backups</h2>' . $b . '<h2>Health</h2>' . chip(health_summary($checks), ['ok' => 'Healthy', 'warn' => 'Needs attention', 'fail' => 'Problem'][health_summary($checks)]) . $h . $cron);
}

function st_backups_settings_save(): never
{
    require_perm('system.backups');
    $keep = max(3, min(90, (int)p('backup_keep', 3)));
    set_setting('backup_keep', (string)$keep);
    set_setting('backup_encrypt', isset($_POST['backup_encrypt']) ? '1' : '0');
    set_setting('backup_offsite_note', p('backup_offsite_note', 200));
    audit('settings.backups', "keep $keep, sealed " . (isset($_POST['backup_encrypt']) ? 'yes' : 'no'));
    flash('ok', 'Saved.');
    redirect('/admin/settings/backups');
}

function st_backup_run(): never
{
    require_perm('system.backups');
    $n = create_backup('manual');
    audit('backup.created', $n);
    flash('ok', "Backup $n taken.");
    redirect('/admin/settings/backups');
}

function st_backup_download(): never
{
    require_perm('system.backups');
    $name = g('name', 120);
    try { $path = backup_path($name); } catch (Throwable) { abort(); }
    audit('backup.downloaded', $name);
    no_store();
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

function st_backup_restore(): never
{
    require_perm('system.backups');
    if (p('confirm', 10) !== 'RESTORE') refuse('backup.restore', 'Restore was not confirmed.', '/admin/settings/backups');
    $name = p('name', 120);
    try {
        $data = read_backup($name);
        $safety = restore_backup($name);
        set_setting('restore_tested_at', now());
        audit('backup.restored', "$name (schema {$data['schema']}); safety backup $safety");
        flash('ok', "Restored $name. The data from before the restore is in $safety.");
    } catch (Throwable $ex) {
        audit('refused: backup.restore', $ex->getMessage());
        flash('err', 'Restore failed and nothing was changed: ' . $ex->getMessage());
    }
    redirect('/admin/settings/backups');
}

function st_readiness_as_meant(): never
{
    require_perm('system.backups');
    $meant = json_decode(setting('readiness_as_meant'), true) ?: [];
    $k = preg_replace('/[^a-z-]/', '', p('key', 40));
    $meant[$k] = now();
    set_setting('readiness_as_meant', json_encode($meant));
    audit('readiness.as_meant', $k);
    redirect('/admin/settings/backups');
}

function st_troubleshooting(): never
{
    require_perm('system.backups');
    $safeSettings = [];
    foreach (all('SELECT k, v FROM settings') as $r) {
        if (preg_match('/pass|secret|key|cache|totp/', $r['k'])) continue;
        $safeSettings[$r['k']] = $r['v'];
    }
    $report = ['generated_at' => gmdate('c'), 'version' => APP_VERSION, 'label' => version_label(), 'commit' => build_commit(), 'php' => PHP_VERSION, 'sapi' => PHP_SAPI,
        'database' => is_sqlite() ? 'sqlite' : 'mysql', 'health' => health_checks(), 'integrity' => integrity_check(), 'migrations' => applied_migrations(),
        'settings' => $safeSettings, 'counts' => ['users' => (int)val('SELECT COUNT(*) FROM users'), 'enquiries' => (int)val('SELECT COUNT(*) FROM enquiries'), 'items' => (int)val('SELECT COUNT(*) FROM items')]];
    audit('system.report', 'troubleshooting report downloaded');
    no_store();
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="c4c-troubleshooting-' . gmdate('Ymd-His') . '.json"');
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function st_help(): string
{
    $u = require_role('admin');
    $mail = setting('help_support_email', contact_email());
    $body = '<div class="split"><div><h2>Ask for help</h2><form method="post" action="/admin/settings/help" class="card">' . csrf_field()
        . field('kind', 'What is it about?', '', ['type' => 'select', 'options' => ['problem' => 'Something is not working', 'idea' => 'I would like something added']])
        . field('subject', 'Subject', '', ['required' => true, 'attrs' => ' maxlength="150"']) . field('details', 'Details', '', ['type' => 'textarea', 'required' => true, 'attrs' => ' maxlength="5000"'])
        . field('urgency', 'How urgent?', 'normal', ['type' => 'select', 'options' => ['low' => 'Whenever possible', 'normal' => 'This week', 'high' => 'Today: work is blocked']])
        . '<button class="btn" type="submit">Send</button><p class="small">Goes to ' . e($mail) . ' with your name, version ' . e(version_label()) . ' and the page you came from.</p></form></div>'
        . '<div><h2>Guides</h2>' . ticks(['docs/ADMIN-GUIDE.md: one section per Settings page', 'docs/OPERATIONS.md: deploy, back up, restore, roll back', 'docs/SECURITY.md: controls and how to report a vulnerability'])
        . (setting('help_docs_url') ? '<p><a href="' . e(setting('help_docs_url')) . '" rel="noopener noreferrer">Open the online guide</a></p>' : '')
        . '<h3>Support hours</h3><p>' . e(setting('help_support_hours', 'Monday to Friday')) . '</p></div></div>';
    if (can('settings.manage')) {
        $body .= '<form method="post" action="/admin/settings/help-settings" class="card inline-form">' . csrf_field() . '<h3>Help settings</h3>'
            . st_field('help_support_email', ['Requests are emailed to', 'email', []], $mail) . st_field('help_support_hours', ['Support hours', 'text', []], setting('help_support_hours'))
            . st_field('help_docs_url', ['Online guide URL (optional)', 'url', []], setting('help_docs_url')) . '<button class="btn small" type="submit">Save</button></form>';
    }
    return settings_layout('help', 'Get help', '', $body);
}

function st_help_post(): never
{
    $u = require_role('admin');
    $subject = p('subject', 150);
    $details = p('details', 5000);
    if ($subject === '' || $details === '') { flash('err', 'Add a subject and details.'); redirect('/admin/settings/help'); }
    if (throttle('help', (string)$u['id'], 10, 3600)) { flash('err', 'You have sent several requests in the last hour.'); redirect('/admin/settings/help'); }
    $kind = p('kind', 10) === 'idea' ? 'ENH' : 'SUP';
    $n = (int)setting('help_seq', '0') + 1;
    set_setting('help_seq', (string)$n);
    $ref = "$kind-$n";
    $ok = send_mail([setting('help_support_email', contact_email())], "[$ref] $subject",
        "From: {$u['name']} <{$u['email']}>\nUrgency: " . p('urgency', 10) . "\nVersion: " . version_label() . ' (' . build_commit() . ")\n\n$details", $u['email']);
    audit('help.request', "$ref $subject");
    flash($ok ? 'ok' : 'err', $ok ? "Sent as $ref." : "Saved as $ref, but the email could not be sent. Check Email (SMTP).");
    redirect('/admin/settings/help');
}

function st_help_settings_save(): never
{
    require_perm('settings.manage');
    $mail = p('help_support_email', 191);
    $url = p('help_docs_url', 300);
    if (!valid_email($mail)) { flash('err', 'Enter a valid email for help requests.'); redirect('/admin/settings/help'); }
    if ($url !== '' && !preg_match('#^https://[^\s<>"]+$#', $url)) { flash('err', 'The guide URL must start with https://'); redirect('/admin/settings/help'); }
    set_setting('help_support_email', strtolower($mail));
    set_setting('help_support_hours', p('help_support_hours', 120));
    set_setting('help_docs_url', $url);
    audit('settings.help');
    flash('ok', 'Saved.');
    redirect('/admin/settings/help');
}

/** Indicative range for a service from its typical duration and the chosen day rate. */
function estimate_for(array $item): ?array
{
    if ($item['kind'] !== 'service') return null;
    $rate = null;
    foreach (rate_card() as $r) if ($r['name'] === setting('estimate_rate') && strtolower($r['unit']) === 'day') $rate = $r['amount'];
    if (!$rate) return null;
    if (!preg_match('/(\d+)\s*(?:to|-|–)\s*(\d+)\s*(day|week)s?/i', (string)$item['duration'], $m) && !preg_match('/(\d+)()\s*(day|week)s?/i', (string)$item['duration'], $m)) return null;
    $lo = (int)$m[1];
    $hi = $m[2] !== '' ? (int)$m[2] : $lo;
    $f = strtolower($m[3]) === 'week' ? 5 : 1;
    return [round($lo * $f * $rate, -2), round($hi * $f * $rate, -2), setting('default_currency', 'USD')];
}

function estimate_text(array $item): string
{
    if (setting('estimate_enabled') !== '1' || !($e = estimate_for($item))) return '';
    return 'Indicative ' . $e[2] . ' ' . number_format($e[0]) . ($e[1] > $e[0] ? ' to ' . number_format($e[1]) : '');
}

function estimate_preview(): string
{
    $rows = '';
    foreach (all("SELECT * FROM items WHERE kind = 'service' AND published = 1 ORDER BY sort") as $it) {
        $e = estimate_for($it);
        $rows .= '<tr><td>' . e($it['name']) . '</td><td>' . e($it['duration']) . '</td><td>' . ($e ? e($e[2] . ' ' . number_format($e[0]) . ' to ' . number_format($e[1])) : '<span class="small">No range: duration is not in days or weeks</span>') . '</td></tr>';
    }
    return '<div class="card inline-form"><h3>Preview</h3><p class="small">Duration in days or weeks × 5 working days a week × the chosen day rate, rounded to the nearest hundred.</p><div class="table-wrap"><table><thead><tr><th>Service</th><th>Typical duration</th><th>Indicative range</th></tr></thead><tbody>' . $rows . '</tbody></table></div></div>';
}

function st_about(): string
{
    $u = require_role('admin');
    $log = (string)@file_get_contents(ROOT . '/CHANGELOG.md');
    $sections = preg_split('/^(?=## )/m', $log) ?: [];
    $sections = array_values(array_filter($sections, fn($s) => str_starts_with($s, '## ')));
    $news = '';
    foreach ($sections as $i => $s) {
        [$head, $rest] = array_pad(explode("\n", $s, 2), 2, '');
        $news .= '<details' . ($i < 3 ? ' open' : '') . '><summary>' . e(trim(substr($head, 3))) . '</summary><div class="prose">' . md($rest) . '</div></details>';
    }
    $facts = '<dl class="facts"><dt>Version</dt><dd>' . e(version_label()) . ' <span class="small">(' . e(APP_VERSION) . ', commit ' . e(build_commit()) . ')</span></dd>'
        . '<dt>Organisation</dt><dd>' . e(setting('company_legal_name') ?: brand_name()) . '</dd>'
        . '<dt>Your terms acceptance</dt><dd>' . e($u['terms_at'] ? 'Accepted ' . fmt_date($u['terms_at'], 'j M Y H:i') . ' UTC' : 'Not yet') . ' &middot; <a href="/terms">Read the terms</a></dd>'
        . '<dt>Runs on</dt><dd>PHP ' . e(PHP_VERSION) . ', ' . (is_sqlite() ? 'SQLite' : 'MySQL') . '. No third-party PHP packages. No telemetry: nothing is sent anywhere except the emails you configure.</dd>'
        . '<dt>Third-party components</dt><dd>qrcode-generator 1.4.4 by Kazuhiko Arase (MIT), used to draw the two-step sign-in QR code. Text uses the system font of each device; nothing is loaded from other sites.</dd>'
        . '<dt>Report a vulnerability</dt><dd><a href="/.well-known/security.txt">security.txt</a></dd></dl>';
    return settings_layout('about', 'About', '', $facts . '<h2>What is new</h2>' . ($news ?: empty_state('No release notes', 'CHANGELOG.md was not found.')));
}
