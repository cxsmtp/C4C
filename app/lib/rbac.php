<?php
declare(strict_types=1);

/** Every permission a staff role can hold, grouped for the Roles & permissions page. */
function permission_catalog(): array
{
    return [
        'Enquiries' => [
            'enquiries.view' => 'See enquiries, quote requests and assessments',
            'enquiries.manage' => 'Change status, assign, add notes and delete enquiries',
        ],
        'Clients and quotes' => [
            'clients.view' => 'See clients, their portal users and documents',
            'clients.manage' => 'Add and edit clients and invite portal users',
            'quotes.manage' => 'Create, edit and send quotes',
            'documents.manage' => 'Share and delete client documents',
        ],
        'Website content' => [
            'content.manage' => 'Edit catalogue, categories, insights, case studies, jobs and pages',
        ],
        'Pricing' => [
            'pricing.manage' => 'Change rates, currencies, tax and the estimate rules',
        ],
        'People' => [
            'people.view' => 'See people and teams',
            'people.manage' => 'Add people, change their role and team, reset sign-in, turn access off',
            'roles.manage' => 'Create and change roles',
        ],
        'Records' => [
            'audit.view' => 'See and export the audit log and the email log',
        ],
        'System' => [
            'settings.manage' => 'Change general, branding, email, sign-in, single sign-on and HTTPS settings',
            'system.backups' => 'Run, download and restore backups; see health',
            'system.updates' => 'Apply database updates',
        ],
    ];
}

function all_permissions(): array
{
    return array_merge(...array_values(array_map('array_keys', permission_catalog())));
}

/** Built-in roles. Owner always holds every permission and cannot be edited. */
function builtin_roles(): array
{
    $all = all_permissions();
    return [
        'owner' => ['Owner', 'Everything, including roles and giving the Owner role.', $all],
        'admin' => ['Administrator', 'Runs the site and the team. Cannot change roles.', array_values(array_diff($all, ['roles.manage']))],
        'sales' => ['Sales', 'Works enquiries into clients and quotes.', ['enquiries.view', 'enquiries.manage', 'clients.view', 'clients.manage', 'quotes.manage', 'people.view']],
        'consultant' => ['Consultant', 'Delivers work and shares reports with clients.', ['enquiries.view', 'enquiries.manage', 'clients.view', 'documents.manage', 'people.view']],
        'editor' => ['Content editor', 'Keeps the website content up to date.', ['content.manage', 'enquiries.view']],
        'viewer' => ['Viewer', 'Read-only access to enquiries, clients and records.', ['enquiries.view', 'clients.view', 'people.view', 'audit.view']],
    ];
}

function role_permissions(string $roleKey): array
{
    static $cache = [];
    if (isset($cache[$roleKey])) return $cache[$roleKey];
    if ($roleKey === 'owner') return $cache[$roleKey] = all_permissions();
    $r = one('SELECT permissions FROM roles WHERE rkey = ?', [$roleKey]);
    $perms = $r ? (json_decode((string)$r['permissions'], true) ?: []) : [];
    return $cache[$roleKey] = array_values(array_intersect($perms, all_permissions()));
}

function can(string $perm, ?array $user = null): bool
{
    $user ??= current_user();
    if (!$user || $user['role'] !== 'admin') return false;
    return in_array($perm, role_permissions((string)$user['role_key']), true);
}

/** Staff guard: signed in as staff (with two-step sign-in done) and holding the permission. */
function require_perm(string $perm): array
{
    $u = require_role('admin');
    if (!can($perm, $u)) abort(403);
    return $u;
}

function roles_list(): array
{
    return all('SELECT * FROM roles ORDER BY builtin DESC, sort, name');
}

/** Roles this user may hand out: only ones whose permissions they fully hold; Owner only by an Owner. */
function grantable_roles(array $by): array
{
    $mine = role_permissions((string)$by['role_key']);
    $out = [];
    foreach (roles_list() as $r) {
        if ($r['rkey'] === 'owner' && $by['role_key'] !== 'owner') continue;
        if (!array_diff(role_permissions($r['rkey']), $mine)) $out[$r['rkey']] = $r['name'];
    }
    return $out;
}

function active_owner_count(): int
{
    return (int)val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND role_key = 'owner' AND active = 1");
}
