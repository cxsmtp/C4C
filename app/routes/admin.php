<?php
declare(strict_types=1);

function admin_routes(): array
{
    return [
        ['GET', '/admin/login', 'admin_login_page'],
        ['POST', '/admin/login', 'admin_login_post'],
        ['POST', '/admin/logout', 'any_logout'],
        ['GET', '/admin', 'admin_dashboard'],
        ['GET', '/admin/enquiries', 'admin_enquiries'],
        ['GET', '/admin/enquiries/(\d+)', 'admin_enquiry'],
        ['POST', '/admin/enquiries/(\d+)', 'admin_enquiry_update'],
        ['POST', '/admin/enquiries/(\d+)/client', 'admin_enquiry_to_client'],
        ['POST', '/admin/enquiries/(\d+)/delete', 'admin_enquiry_delete'],
        ['GET', '/admin/c/([a-z_]+)', 'admin_list'],
        ['GET', '/admin/c/([a-z_]+)/(new|\d+)', 'admin_edit'],
        ['POST', '/admin/c/([a-z_]+)/save', 'admin_save'],
        ['POST', '/admin/c/([a-z_]+)/(\d+)/delete', 'admin_delete'],
        ['GET', '/admin/clients', 'admin_clients'],
        ['GET', '/admin/clients/(\d+)', 'admin_client'],
        ['POST', '/admin/clients/(\d+)/users', 'admin_client_add_user'],
        ['POST', '/admin/clients/(\d+)/documents', 'admin_upload_document'],
        ['POST', '/admin/users/(\d+)/invite', 'admin_user_invite'],
        ['POST', '/admin/users/(\d+)/toggle', 'admin_user_toggle'],
        ['GET', '/admin/documents/(\d+)', 'admin_download'],
        ['POST', '/admin/documents/(\d+)/delete', 'admin_document_delete'],
        ['GET', '/admin/quotes', 'admin_quotes'],
        ['GET', '/admin/quotes/(new|\d+)', 'admin_quote_edit'],
        ['POST', '/admin/quotes/save', 'admin_quote_save'],
        ['POST', '/admin/quotes/(\d+)/send', 'admin_quote_send'],
        ['POST', '/admin/quotes/(\d+)/delete', 'admin_quote_delete'],
        ['GET', '/admin/account', 'admin_account'],
        ['POST', '/admin/account', 'account_password_post'],
    ];
}

function admin_layout(string $title, string $body, string $section = ''): string
{
    $u = current_user();
    $new = can('enquiries.view') ? (int)val("SELECT COUNT(*) FROM enquiries WHERE status = 'new'") : 0;
    $groups = [
        '' => [['/admin', 'Dashboard', ''], ['/admin/enquiries', 'Enquiries' . ($new ? " ($new)" : ''), 'enquiries.view'], ['/admin/clients', 'Clients', 'clients.view'], ['/admin/quotes', 'Quotes', 'quotes.manage']],
        'Website' => [['/admin/c/items', 'Catalogue', 'content.manage'], ['/admin/c/categories', 'Categories', 'content.manage'], ['/admin/c/posts', 'Insights', 'content.manage'],
            ['/admin/c/case_studies', 'Case studies', 'content.manage'], ['/admin/c/jobs', 'Jobs', 'content.manage'], ['/admin/c/pages', 'Pages', 'content.manage']],
        'You' => [['/admin/settings', 'Settings', ''], ['/admin/account', 'My account', '']],
    ];
    $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $links = '';
    foreach ($groups as $g => $items) {
        $l = '';
        foreach ($items as [$h, $label, $perm]) {
            if ($perm !== '' && !can($perm)) continue;
            $cur = ($path === $h || ($h !== '/admin' && str_starts_with($path, $h))) ? ' aria-current="page"' : '';
            $l .= '<a href="' . $h . '"' . $cur . '>' . e($label) . '</a>';
        }
        if ($l) $links .= ($g ? '<p class="st-label">' . e($g) . '</p>' : '') . $l;
    }
    $env = setting('environment', 'production') === 'staging' ? '<span class="chip chip-warn">Staging</span>' : '';
    $body = '<div class="admin' . ($section === 'settings' ? ' is-settings' : '') . '"><aside class="admin-nav"><p class="small">Signed in as<br><strong>' . e($u['name'] ?? '') . '</strong> ' . $env . '</p>'
        . '<button class="menu-toggle" type="button" aria-expanded="false" aria-controls="admin-menu">Admin menu</button><div class="menu-body" id="admin-menu">' . $links
        . '<form method="post" action="/admin/logout">' . csrf_field() . '<button class="linkish" type="submit">Sign out</button></form><a href="/" class="small">View website</a></div></aside>'
        . '<div class="admin-main"><h1>' . e($title) . '</h1>' . flashes() . $body . '</div></div>';
    return render_layout($title . ' | Admin', '', $body, ['noindex' => true, 'admin' => true]);
}

function login_form(string $action, string $title, string $intro, string $err = '', string $email = ''): string
{
    $e = $err ? '<div class="flash err" role="alert">' . e($err) . '</div>' : '';
    return render_layout($title . ' | Call4Counsel', '', page_head('', $title, $intro)
        . '<section class="band"><div class="wrap narrow"><form method="post" action="' . e($action) . '" class="card">' . csrf_field() . $e
        . field('email', 'Email', $email, ['type' => 'email', 'required' => true, 'attrs' => ' autocomplete="username"'])
        . field('password', 'Password', '', ['type' => 'password', 'required' => true, 'attrs' => ' autocomplete="current-password"'])
        . '<button class="btn" type="submit">Sign in</button><p class="small"><a href="/portal/forgot">Forgotten your password?</a></p></form>'
        . ($action === '/admin/login' && sso_ready() ? '<div class="or"><span>or</span></div><a class="btn ghost wide" href="/signin/sso">Sign in with your company account</a>' : '')
        . '</div></section>', ['noindex' => true]);
}

function admin_login_page(): string
{
    no_store();
    $u = current_user();
    if ($u && $u['role'] === 'admin') redirect('/admin');
    return login_form('/admin/login', 'Staff sign in', 'For ' . brand_name() . ' staff.');
}

function admin_login_post(): string
{
    no_store();
    $r = attempt_login(p('email', 191), (string)($_POST['password'] ?? ''), 'admin');
    if ($r === 'mfa') redirect('/signin/verify');
    if (is_string($r)) return login_form('/admin/login', 'Staff sign in', 'For ' . brand_name() . ' staff.', $r, p('email', 191));
    after_signin($r);
}

function any_logout(): never
{
    $role = current_user()['role'] ?? '';
    if ($role) audit('logout');
    logout_user();
    redirect($role === 'admin' ? '/admin/login' : '/portal/login');
}

function badge(string $s): string
{
    return '<span class="badge b-' . e(preg_replace('/[^a-z]/', '', strtolower($s))) . '">' . e(ucfirst($s)) . '</span>';
}

function admin_dashboard(): string
{
    require_role('admin');
    $stats = [
        ['New enquiries', (int)val("SELECT COUNT(*) FROM enquiries WHERE status = 'new'"), '/admin/enquiries?status=new'],
        ['Assessments (30 days)', (int)val("SELECT COUNT(*) FROM enquiries WHERE type = 'assessment' AND created_at > ?", [gmdate('Y-m-d H:i:s', time() - 30 * 86400)]), '/admin/enquiries?type=assessment'],
        ['Quotes awaiting client', (int)val("SELECT COUNT(*) FROM quotes WHERE status = 'sent'"), '/admin/quotes'],
        ['Clients', (int)val('SELECT COUNT(*) FROM clients'), '/admin/clients'],
    ];
    $tiles = '';
    foreach ($stats as [$l, $n, $h]) $tiles .= '<a class="stat card" href="' . $h . '"><span class="n">' . $n . '</span><span>' . e($l) . '</span></a>';
    $recent = all('SELECT * FROM enquiries ORDER BY id DESC LIMIT 8');
    $warn = '';
    if (can('system.backups')) {
        $ready = readiness_checks();
        $todo = count(array_filter($ready, fn($c) => !in_array($c[2], ['ok', 'meant'], true)));
        if ($todo) $warn .= '<p class="note">' . $todo . ' item(s) to finish before this is production ready. <a href="/admin/settings/backups">See the checklist</a>.</p>';
    }
    return admin_layout('Dashboard', $warn . '<div class="stats">' . $tiles . '</div>' . (can('enquiries.view') ? '<h2>Latest enquiries</h2>' . enquiry_table($recent) : ''));
}

function enquiry_table(array $rows): string
{
    if (!$rows) return '<p class="note">Nothing here yet.</p>';
    $t = enquiry_types();
    $h = '<div class="table-wrap"><table><thead><tr><th>Received</th><th>Type</th><th>From</th><th>Subject</th><th>Status</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $h .= '<tr><td>' . e(fmt_date($r['created_at'], 'j M Y H:i')) . '</td><td>' . e($t[$r['type']] ?? $r['type']) . '</td><td>' . e($r['name']) . '<br><span class="small">' . e($r['company']) . '</span></td>'
            . '<td><a href="/admin/enquiries/' . (int)$r['id'] . '">' . e($r['subject']) . '</a></td><td>' . badge($r['status']) . '</td></tr>';
    }
    return $h . '</tbody></table></div>';
}

const ENQ_STATUSES = ['new', 'open', 'replied', 'won', 'closed', 'spam'];

function admin_enquiries(): string
{
    require_perm('enquiries.view');
    $status = in_array(g('status'), ENQ_STATUSES, true) ? g('status') : '';
    $type = array_key_exists(g('type'), enquiry_types()) ? g('type') : '';
    $where = [];
    $args = [];
    if ($status) { $where[] = 'status = ?'; $args[] = $status; }
    if ($type) { $where[] = 'type = ?'; $args[] = $type; }
    $rows = all('SELECT * FROM enquiries' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY id DESC LIMIT 300', $args);
    $f = '<form class="filters" method="get">' . field('status', 'Status', $status, ['type' => 'select', 'options' => ['' => 'Any'] + array_combine(ENQ_STATUSES, array_map('ucfirst', ENQ_STATUSES))])
        . field('type', 'Type', $type, ['type' => 'select', 'options' => ['' => 'Any'] + enquiry_types()]) . '<button class="btn small" type="submit">Filter</button></form>';
    return admin_layout('Enquiries', $f . enquiry_table($rows));
}

function admin_enquiry(string $id): string
{
    require_perm('enquiries.view');
    $q = one('SELECT * FROM enquiries WHERE id = ?', [(int)$id]);
    if (!$q) abort();
    if ($q['status'] === 'new') { q("UPDATE enquiries SET status = 'open', updated_at = ? WHERE id = ?", [now(), $q['id']]); $q['status'] = 'open'; }
    $client = $q['client_id'] ? one('SELECT * FROM clients WHERE id = ?', [$q['client_id']]) : null;
    $info = '<dl class="facts">';
    foreach (['Type' => enquiry_types()[$q['type']] ?? $q['type'], 'Received' => fmt_date($q['created_at'], 'j M Y H:i'), 'Name' => $q['name'], 'Email' => $q['email'], 'Company' => $q['company'], 'Phone' => $q['phone']] as $k => $v) {
        if ($v) $info .= '<dt>' . e($k) . '</dt><dd>' . ($k === 'Email' ? '<a href="mailto:' . e($v) . '">' . e($v) . '</a>' : e($v)) . '</dd>';
    }
    $info .= '<dt>Client</dt><dd>' . ($client ? '<a href="/admin/clients/' . (int)$client['id'] . '">' . e($client['company']) . '</a>' : 'Not linked') . '</dd></dl>';
    $assessment = '';
    if ($q['type'] === 'assessment') {
        $assessment = '<h2>Assessment results</h2><p><a href="/assessment/result/' . e($q['token']) . '">Open the results page the client saw</a></p>';
    }
    $form = '<form method="post" action="/admin/enquiries/' . (int)$q['id'] . '" class="card">' . csrf_field()
        . field('status', 'Status', $q['status'], ['type' => 'select', 'options' => array_combine(ENQ_STATUSES, array_map('ucfirst', ENQ_STATUSES))])
        . field('assigned_to', 'Assigned to', (string)$q['assigned_to'], ['type' => 'select', 'options' => ['' => '(nobody)'] + array_column(all("SELECT id, name FROM users WHERE role = 'admin' AND active = 1 ORDER BY name"), 'name', 'id')])
        . field('notes', 'Internal notes (never shown to the client)', (string)$q['notes'], ['type' => 'textarea'])
        . '<button class="btn small" type="submit">Save</button></form>';
    $actions = '<div class="actions">';
    if (!$client) {
        $actions .= '<form method="post" action="/admin/enquiries/' . (int)$q['id'] . '/client">' . csrf_field() . '<button class="btn small" type="submit">Create client and portal login</button></form>';
    } else {
        $actions .= '<a class="btn small" href="/admin/quotes/new?client=' . (int)$client['id'] . '&amp;enquiry=' . (int)$q['id'] . '">Create a quote</a>';
    }
    $actions .= '<a class="btn small ghost" href="mailto:' . e($q['email']) . '?subject=' . rawurlencode('Re: ' . $q['subject']) . '">Reply by email</a>'
        . '<form method="post" action="/admin/enquiries/' . (int)$q['id'] . '/delete" data-confirm="Delete this enquiry permanently?">' . csrf_field() . '<button class="btn small danger" type="submit">Delete</button></form></div>';
    return admin_layout($q['subject'], '<div class="split"><div>' . $info . '<h2>Message</h2><pre class="msg">' . e($q['message']) . '</pre>' . $assessment . '</div><div>' . $actions . $form . '</div></div>');
}

function admin_enquiry_update(string $id): never
{
    require_perm('enquiries.manage');
    $status = in_array(p('status'), ENQ_STATUSES, true) ? p('status') : 'open';
    $assignee = (int)p('assigned_to', 12);
    $assignee = $assignee && one("SELECT id FROM users WHERE id = ? AND role = 'admin' AND active = 1", [$assignee]) ? $assignee : null;
    $before = one('SELECT assigned_to, subject FROM enquiries WHERE id = ?', [(int)$id]);
    if (!$before) abort();
    q('UPDATE enquiries SET status = ?, notes = ?, assigned_to = ?, team_id = (SELECT team_id FROM users WHERE id = ?), updated_at = ? WHERE id = ?', [$status, p('notes', 20000), $assignee, $assignee, now(), (int)$id]);
    if ($assignee && (int)$before['assigned_to'] !== $assignee) {
        $to = one('SELECT name, email FROM users WHERE id = ?', [$assignee]);
        send_mail([$to['email']], 'Assigned to you: ' . $before['subject'], "Hello {$to['name']},\n\nThis enquiry is now yours:\n\n" . base_url() . '/admin/enquiries/' . (int)$id);
    }
    audit('enquiry.update', "#$id $status" . ($assignee ? " assigned #$assignee" : ''));
    flash('ok', 'Saved.');
    redirect('/admin/enquiries/' . (int)$id);
}

function admin_enquiry_delete(string $id): never
{
    require_perm('enquiries.manage');
    q('DELETE FROM enquiries WHERE id = ?', [(int)$id]);
    audit('enquiry.delete', "#$id");
    flash('ok', 'Enquiry deleted.');
    redirect('/admin/enquiries');
}

function admin_enquiry_to_client(string $id): never
{
    require_perm('clients.manage');
    $q = one('SELECT * FROM enquiries WHERE id = ?', [(int)$id]);
    if (!$q) abort();
    $existing = one("SELECT * FROM users WHERE email = ?", [strtolower((string)$q['email'])]);
    if ($existing && $existing['role'] !== 'client') {
        flash('err', 'That email belongs to an admin account, so it cannot be a client login.');
        redirect('/admin/enquiries/' . (int)$id);
    }
    if ($existing) {
        $clientId = (int)$existing['client_id'];
    } else {
        $clientId = insert('clients', ['company' => $q['company'] ?: $q['name'], 'contact_name' => $q['name'], 'email' => $q['email'], 'phone' => $q['phone'], 'notes' => '', 'created_at' => now()]);
        create_portal_user($clientId, (string)$q['name'], (string)$q['email']);
    }
    q('UPDATE enquiries SET client_id = ? WHERE email = ?', [$clientId, $q['email']]);
    audit('client.from_enquiry', "#$id");
    redirect('/admin/clients/' . $clientId);
}

function create_portal_user(int $clientId, string $name, string $email): void
{
    $uid = insert('users', ['role' => 'client', 'client_id' => $clientId, 'name' => $name, 'email' => strtolower($email), 'password_hash' => null, 'active' => 1, 'created_at' => now()]);
    send_invite($uid, true);
}

function send_invite(int $uid, bool $isNew): void
{
    $u = one('SELECT * FROM users WHERE id = ?', [$uid]);
    $token = issue_invite($uid);
    $link = base_url() . '/portal/set-password/' . $token;
    $area = $u['role'] === 'admin' ? 'the Call4Counsel admin panel' : 'your Call4Counsel client portal';
    $ok = send_mail([$u['email']], $isNew ? 'Your Call4Counsel account' : 'Set your Call4Counsel password',
        "Hello {$u['name']},\n\nUse this link to set your password for $area. It works once and expires in 7 days:\n\n$link\n\nIf you did not expect this email, you can ignore it.\n\nCall4Counsel");
    // The link is shown to the admin so it can be shared another way if email fails.
    flash($ok ? 'ok' : 'err', ($ok ? 'Invitation emailed to ' : 'Email could not be sent. Share this link with ') . $u['email'] . ($ok ? '. Backup link: ' : ': ') . $link);
    audit('user.invite', $u['email']);
}

// ---------- generic content editor ----------

function entities(): array
{
    $cats = [];
    foreach (all('SELECT id, kind, name FROM categories ORDER BY sort') as $c) $cats[(string)$c['id']] = ucfirst($c['kind']) . ': ' . $c['name'];
    $icons = array_combine(array_keys(ICONS), array_keys(ICONS));
    $md = 'Markdown: ## heading, - list item, **bold**, *italic*, [link](https://...).';
    return [
        'items' => ['Catalogue items', 'name', ['kind', 'category_id', 'published'], [
            'name' => ['Name', 'text', true], 'slug' => ['URL slug', 'slug'], 'kind' => ['Type', 'select', true, ['service' => 'Service', 'product' => 'Product']],
            'category_id' => ['Category', 'select', true, $cats], 'summary' => ['Summary', 'textarea', true], 'body' => ['Description', 'markdown', false, null, $md],
            'deliverables' => ['What you get (one per line)', 'textarea'], 'duration' => ['Typical duration or term', 'text'], 'price_note' => ['Pricing note', 'text'],
            'vendor' => ['Vendor (products only; leave empty unless you have a reseller agreement)', 'text'],
            'tags' => ['Matching tags (comma separated; used by the assessment)', 'text', false, null, 'Known tags: ' . implode(', ', known_tags())],
            'featured' => ['Featured on the home page', 'bool'], 'published' => ['Published', 'bool'], 'sort' => ['Sort order', 'int']]],
        'categories' => ['Categories', 'name', ['kind', 'sort'], [
            'name' => ['Name', 'text', true], 'slug' => ['URL slug', 'slug'], 'kind' => ['Type', 'select', true, ['service' => 'Services', 'product' => 'Products']],
            'blurb' => ['Short description', 'textarea'], 'icon' => ['Icon', 'select', false, $icons], 'sort' => ['Sort order', 'int']]],
        'posts' => ['Insights posts', 'title', ['topic', 'published', 'published_at'], [
            'title' => ['Title', 'text', true], 'slug' => ['URL slug', 'slug'], 'topic' => ['Topic', 'text'], 'excerpt' => ['Summary', 'textarea'],
            'body' => ['Article', 'markdown', true, null, $md], 'author' => ['Author', 'text'], 'published' => ['Published', 'bool'],
            'published_at' => ['Publish date (YYYY-MM-DD HH:MM:SS, UTC)', 'datetime']]],
        'case_studies' => ['Case studies', 'title', ['practice', 'is_sample', 'published'], [
            'title' => ['Title', 'text', true], 'slug' => ['URL anchor', 'slug'], 'sector' => ['Sector', 'text'], 'practice' => ['Practice', 'text'], 'engagement' => ['Engagement', 'text'],
            'challenge' => ['The challenge', 'textarea'], 'approach' => ['What we did', 'textarea'], 'outcome' => ['The outcome', 'textarea'],
            'is_sample' => ['Sample scenario (untick only for a real client story you have permission to publish)', 'bool'], 'published' => ['Published', 'bool'], 'sort' => ['Sort order', 'int']]],
        'jobs' => ['Jobs', 'title', ['team', 'location', 'published'], [
            'title' => ['Title', 'text', true], 'slug' => ['URL slug', 'slug'], 'team' => ['Team', 'text'], 'location' => ['Location', 'text'],
            'summary' => ['Summary', 'textarea'], 'body' => ['Description', 'markdown', false, null, $md], 'published' => ['Published', 'bool'], 'sort' => ['Sort order', 'int']]],
        'pages' => ['Pages', 'title', ['slug'], [
            'title' => ['Title', 'text', true], 'lead' => ['Introduction', 'textarea'], 'body' => ['Content', 'markdown', true, null, $md]], 'nocreate' => true],
        'clients' => ['Clients', 'company', ['contact_name', 'email'], [
            'company' => ['Company', 'text', true], 'contact_name' => ['Main contact', 'text'], 'email' => ['Email', 'email'], 'phone' => ['Phone', 'text'], 'notes' => ['Internal notes', 'textarea']]],
    ];
}

function known_tags(): array
{
    return ['ai-assessment', 'ai-governance', 'ai-spm', 'agent-security', 'ai-redteam', 'ai-guardrails', 'ai-gateway', 'ai-monitoring', 'rag-security', 'ml-security',
        'data-protection', 'dlp', 'vendor-risk', 'appsec', 'sast', 'sca', 'secrets', 'dast', 'pentest', 'threat-model', 'devsecops', 'aspm', 'iam', 'pam',
        'endpoint', 'managed-it', 'backup', 'cloud', 'cspm', 'm365', 'incident', 'compliance', 'licensing', 'data-platform'];
}

function entity(string $name, bool $write = false): array
{
    $all = entities();
    if (!isset($all[$name])) abort();
    require_perm($name === 'clients' ? ($write ? 'clients.manage' : 'clients.view') : 'content.manage');
    return $all[$name];
}

function admin_list(string $name): string
{
    $ent = entity($name);
    [$label, $titleCol, $cols, $fields] = $ent;
    $rows = all("SELECT * FROM $name ORDER BY " . (isset($fields['sort']) ? 'sort, ' : '') . 'id DESC');
    $catNames = $name === 'items' ? array_column(all('SELECT id, name FROM categories'), 'name', 'id') : [];
    $h = '<div class="actions">' . (empty($ent['nocreate']) ? '<a class="btn small" href="/admin/c/' . $name . '/new">Add new</a>' : '') . '</div>'
        . '<div class="table-wrap"><table><thead><tr><th>' . e($fields[$titleCol][0]) . '</th>';
    foreach ($cols as $c) $h .= '<th>' . e($fields[$c][0] ?? ucfirst($c)) . '</th>';
    $h .= '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $link = $name === 'clients' ? '/admin/clients/' . (int)$r['id'] : '/admin/c/' . $name . '/' . (int)$r['id'];
        $h .= '<tr><td><a href="' . $link . '">' . e($r[$titleCol]) . '</a></td>';
        foreach ($cols as $c) {
            $v = $r[$c];
            if (($fields[$c][1] ?? '') === 'bool') $v = $v ? 'Yes' : 'No';
            if ($c === 'category_id') $v = $catNames[$v] ?? '';
            $h .= '<td>' . e((string)$v) . '</td>';
        }
        $h .= '</tr>';
    }
    return admin_layout($label, $h . '</tbody></table></div>');
}

function admin_edit(string $name, string $id, array $errors = [], ?array $row = null): string
{
    $ent = entity($name);
    [$label, $titleCol, , $fields] = $ent;
    if ($id === 'new' && !empty($ent['nocreate'])) abort();
    if ($row === null) {
        $row = $id === 'new' ? ['published' => 1, 'sort' => 0, 'is_sample' => 1, 'published_at' => now()] : one("SELECT * FROM $name WHERE id = ?", [(int)$id]);
        if (!$row) abort();
    }
    $f = '';
    foreach ($fields as $col => $def) {
        [$lab, $type] = $def;
        $req = !empty($def[2]);
        $help = $def[4] ?? null;
        $val = (string)($row[$col] ?? '');
        $f .= match ($type) {
            'bool' => '<label class="check"><input type="checkbox" name="' . $col . '" value="1"' . ($val ? ' checked' : '') . '> <span>' . e($lab) . '</span></label>',
            'select' => field($col, $lab, $val, ['type' => 'select', 'required' => $req, 'options' => ($req ? [] : ['' => '(none)']) + $def[3]]),
            'textarea', 'markdown' => field($col, $lab, $val, ['type' => 'textarea', 'required' => $req, 'help' => $help, 'attrs' => $type === 'markdown' ? ' class="tall"' : '']),
            'int' => field($col, $lab, $val, ['type' => 'number', 'attrs' => ' step="1"']),
            'email' => field($col, $lab, $val, ['type' => 'email']),
            'slug' => field($col, $lab, $val, ['help' => 'Lowercase letters, numbers and hyphens. Leave empty to create it from the title.', 'attrs' => ' pattern="[a-z0-9-]*" maxlength="120"']),
            default => field($col, $lab, $val, ['required' => $req, 'help' => $help, 'attrs' => ' maxlength="500"']),
        };
    }
    $err = $errors ? '<div class="flash err" role="alert">' . implode('<br>', array_map('e', $errors)) . '</div>' : '';
    $del = ($id !== 'new' && empty($ent['nocreate'])) ? '<form method="post" action="/admin/c/' . $name . '/' . (int)$id . '/delete" data-confirm="Delete this permanently?">' . csrf_field() . '<button class="btn small danger" type="submit">Delete</button></form>' : '';
    $view = '';
    $public = ['items' => '/catalogue/', 'posts' => '/insights/', 'jobs' => '/careers/'];
    if ($id !== 'new' && isset($public[$name])) $view = '<a class="btn small ghost" href="' . $public[$name] . e((string)$row['slug']) . '">View on site</a>';
    if ($name === 'pages' && $id !== 'new') $view = '<a class="btn small ghost" href="/' . e((string)$row['slug']) . '">View on site</a>';
    return admin_layout(($id === 'new' ? 'New: ' : 'Edit: ') . ($row[$titleCol] ?? $label),
        $err . '<form method="post" action="/admin/c/' . $name . '/save" class="card edit">' . csrf_field() . '<input type="hidden" name="id" value="' . e($id) . '">' . $f
        . '<div class="actions"><button class="btn" type="submit">Save</button>' . $view . '<a class="btn small ghost" href="/admin/c/' . $name . '">Cancel</a></div></form><div class="actions">' . $del . '</div>');
}

function admin_save(string $name): string
{
    $ent = entity($name, true);
    [, $titleCol, , $fields] = $ent;
    $idRaw = p('id', 12);
    $id = ctype_digit($idRaw) ? (int)$idRaw : 0;
    if (!$id && !empty($ent['nocreate'])) abort();
    $data = [];
    $errors = [];
    foreach ($fields as $col => $def) {
        [$lab, $type] = $def;
        $v = p($col, in_array($type, ['markdown', 'textarea'], true) ? 100000 : 500);
        switch ($type) {
            case 'bool': $v = isset($_POST[$col]) ? 1 : 0; break;
            case 'int': $v = (int)$v; break;
            case 'select':
                if ($v !== '' && !array_key_exists($v, $def[3])) $errors[] = "Choose a valid value for $lab.";
                if ($col === 'category_id') $v = $v === '' ? null : (int)$v;
                break;
            case 'email': if ($v !== '' && !valid_email($v)) $errors[] = 'Enter a valid email address.'; break;
            case 'datetime':
                if ($v === '') $v = now();
                elseif (!preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $v)) $errors[] = "Use the format YYYY-MM-DD HH:MM:SS for $lab.";
                else $v = substr($v . ' 00:00:00', 0, 19);
                break;
            case 'slug': $v = $v === '' ? slugify(p($titleCol, 200)) : slugify($v); break;
        }
        if (!empty($def[2]) && ($v === '' || $v === null)) $errors[] = "$lab is required.";
        $data[$col] = $v;
    }
    if ($name === 'items') $data['tags'] = implode(',', array_filter(array_map(fn($t) => slugify($t), explode(',', (string)$data['tags'])), fn($t) => $t !== 'item'));
    if (isset($data['slug']) && val("SELECT COUNT(*) FROM $name WHERE slug = ? AND id <> ?", [$data['slug'], $id]) > 0) $errors[] = 'That URL slug is already used. Choose another.';
    if ($errors) return admin_edit($name, $id ? (string)$id : 'new', $errors, $data);
    if (in_array($name, ['items', 'posts', 'pages'], true)) $data['updated_at'] = now();
    if ($name === 'clients' && !$id) $data['created_at'] = now();
    if ($id) {
        if (!one("SELECT id FROM $name WHERE id = ?", [$id])) abort();
        update($name, $id, $data);
    } else {
        $id = insert($name, $data);
    }
    audit("$name.save", "#$id");
    flash('ok', 'Saved.');
    redirect($name === 'clients' ? '/admin/clients/' . $id : "/admin/c/$name/$id");
}

function admin_delete(string $name, string $id): never
{
    $ent = entity($name, true);
    if (!empty($ent['nocreate'])) abort(403);
    $id = (int)$id;
    if ($name === 'categories' && val('SELECT COUNT(*) FROM items WHERE category_id = ?', [$id]) > 0) {
        flash('err', 'Move or delete the items in this category first.');
        redirect("/admin/c/categories/$id");
    }
    if ($name === 'clients') delete_client($id);
    else q("DELETE FROM $name WHERE id = ?", [$id]);
    audit("$name.delete", "#$id");
    flash('ok', 'Deleted.');
    redirect($name === 'clients' ? '/admin/clients' : "/admin/c/$name");
}

function delete_client(int $id): void
{
    foreach (all('SELECT * FROM documents WHERE client_id = ?', [$id]) as $d) @unlink(upload_path($d['stored']));
    q('DELETE FROM documents WHERE client_id = ?', [$id]);
    foreach (all('SELECT id FROM quotes WHERE client_id = ?', [$id]) as $qt) q('DELETE FROM quote_lines WHERE quote_id = ?', [$qt['id']]);
    q('DELETE FROM quotes WHERE client_id = ?', [$id]);
    q("DELETE FROM users WHERE client_id = ? AND role = 'client'", [$id]);
    q('UPDATE enquiries SET client_id = NULL WHERE client_id = ?', [$id]);
    q('DELETE FROM clients WHERE id = ?', [$id]);
}

// ---------- clients ----------

function admin_clients(): string
{
    require_perm('clients.view');
    $rows = all('SELECT c.*, (SELECT COUNT(*) FROM users u WHERE u.client_id = c.id) AS users, (SELECT COUNT(*) FROM quotes qt WHERE qt.client_id = c.id) AS quotes FROM clients c ORDER BY c.company');
    $h = '<div class="actions"><a class="btn small" href="/admin/c/clients/new">Add client</a></div><div class="table-wrap"><table><thead><tr><th>Company</th><th>Contact</th><th>Portal users</th><th>Quotes</th></tr></thead><tbody>';
    foreach ($rows as $r) $h .= '<tr><td><a href="/admin/clients/' . (int)$r['id'] . '">' . e($r['company']) . '</a></td><td>' . e($r['contact_name']) . '<br><span class="small">' . e($r['email']) . '</span></td><td>' . (int)$r['users'] . '</td><td>' . (int)$r['quotes'] . '</td></tr>';
    return admin_layout('Clients', $h . '</tbody></table></div>');
}

function admin_client(string $id): string
{
    require_perm('clients.view');
    $c = one('SELECT * FROM clients WHERE id = ?', [(int)$id]);
    if (!$c) abort();
    $cid = (int)$c['id'];
    $users = all("SELECT * FROM users WHERE client_id = ? AND role = 'client' ORDER BY name", [$cid]);
    $uh = '<div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead><tbody>';
    foreach ($users as $u) {
        $status = !$u['active'] ? 'disabled' : ($u['password_hash'] ? 'active' : 'invited');
        $uh .= '<tr><td>' . e($u['name']) . '</td><td>' . e($u['email']) . '</td><td>' . badge($status) . '</td><td>' . e(fmt_date($u['last_login'], 'j M Y H:i')) . '</td><td class="row-actions">'
            . '<form method="post" action="/admin/users/' . (int)$u['id'] . '/invite">' . csrf_field() . '<button class="linkish" type="submit">Send password link</button></form>'
            . '<form method="post" action="/admin/users/' . (int)$u['id'] . '/toggle">' . csrf_field() . '<button class="linkish" type="submit">' . ($u['active'] ? 'Disable' : 'Enable') . '</button></form></td></tr>';
    }
    $uh .= '</tbody></table></div><form method="post" action="/admin/clients/' . $cid . '/users" class="card inline-form">' . csrf_field() . '<h3>Add portal user</h3><div class="row">'
        . field('name', 'Name', '', ['required' => true, 'attrs' => ' maxlength="120"']) . field('email', 'Email', '', ['type' => 'email', 'required' => true]) . '</div><button class="btn small" type="submit">Add and send invitation</button></form>';
    $quotes = all('SELECT * FROM quotes WHERE client_id = ? ORDER BY id DESC', [$cid]);
    $qh = '<div class="actions"><a class="btn small" href="/admin/quotes/new?client=' . $cid . '">New quote</a></div>' . quote_table($quotes);
    $docs = all('SELECT * FROM documents WHERE client_id = ? ORDER BY id DESC', [$cid]);
    $dh = '<div class="table-wrap"><table><thead><tr><th>Title</th><th>File</th><th>Added</th><th></th></tr></thead><tbody>';
    foreach ($docs as $d) {
        $dh .= '<tr><td>' . e($d['title']) . '</td><td><a href="/admin/documents/' . (int)$d['id'] . '">' . e($d['filename']) . '</a> <span class="small">(' . number_format($d['size'] / 1024, 0) . ' KB)</span></td><td>' . e(fmt_date($d['created_at'])) . '</td>'
            . '<td><form method="post" action="/admin/documents/' . (int)$d['id'] . '/delete" data-confirm="Delete this document?">' . csrf_field() . '<button class="linkish" type="submit">Delete</button></form></td></tr>';
    }
    $dh .= '</tbody></table></div><form method="post" action="/admin/clients/' . $cid . '/documents" enctype="multipart/form-data" class="card inline-form">' . csrf_field()
        . '<h3>Share a document</h3>' . field('title', 'Title', '', ['required' => true, 'attrs' => ' maxlength="200"'])
        . field('file', 'File (PDF, Word, Excel, PowerPoint, PNG, JPG, CSV or TXT; up to 20 MB)', '', ['type' => 'file', 'required' => true, 'attrs' => ' accept=".pdf,.docx,.xlsx,.pptx,.png,.jpg,.jpeg,.csv,.txt"'])
        . '<button class="btn small" type="submit">Upload</button></form>';
    $enq = all('SELECT * FROM enquiries WHERE client_id = ? ORDER BY id DESC LIMIT 50', [$cid]);
    $info = '<dl class="facts"><dt>Contact</dt><dd>' . e($c['contact_name']) . '</dd><dt>Email</dt><dd>' . e($c['email']) . '</dd><dt>Phone</dt><dd>' . e($c['phone']) . '</dd></dl>'
        . ($c['notes'] ? '<pre class="msg">' . e($c['notes']) . '</pre>' : '') . '<div class="actions"><a class="btn small ghost" href="/admin/c/clients/' . $cid . '">Edit client details</a></div>';
    return admin_layout($c['company'], $info . '<h2>Portal users</h2>' . $uh . '<h2>Quotes</h2>' . $qh . '<h2>Documents and reports</h2>' . $dh . '<h2>Enquiries and assessments</h2>' . enquiry_table($enq));
}

function admin_client_add_user(string $id): never
{
    require_perm('clients.manage');
    $cid = (int)$id;
    if (!one('SELECT id FROM clients WHERE id = ?', [$cid])) abort();
    $email = strtolower(p('email', 191));
    $name = p('name', 120);
    if (!valid_email($email) || $name === '') flash('err', 'Enter a name and a valid email.');
    elseif (one('SELECT id FROM users WHERE email = ?', [$email])) flash('err', 'An account with that email already exists.');
    else create_portal_user($cid, $name, $email);
    redirect("/admin/clients/$cid");
}

function admin_user_invite(string $id): never
{
    require_perm('clients.manage');
    $u = one("SELECT * FROM users WHERE id = ? AND role = 'client'", [(int)$id]);
    if (!$u) abort();
    send_invite((int)$u['id'], false);
    redirect($u['role'] === 'client' ? '/admin/clients/' . (int)$u['client_id'] : '/admin/admins');
}

function admin_user_toggle(string $id): never
{
    $me = require_perm('clients.manage');
    $u = one("SELECT * FROM users WHERE id = ? AND role = 'client'", [(int)$id]);
    if (!$u) abort();
    if ((int)$u['id'] === (int)$me['id']) {
        flash('err', 'You cannot disable your own account.');
    } elseif ($u['role'] === 'admin' && $u['active'] && (int)val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1") <= 1) {
        flash('err', 'At least one admin must stay active.');
    } else {
        q('UPDATE users SET active = ? WHERE id = ?', [$u['active'] ? 0 : 1, $u['id']]);
        if ($u['active']) revoke_sessions((int)$u['id']);
        audit('user.toggle', $u['email']);
        flash('ok', 'Updated.');
    }
    redirect($u['role'] === 'client' ? '/admin/clients/' . (int)$u['client_id'] : '/admin/admins');
}

// ---------- documents ----------

const UPLOAD_TYPES = [
    'pdf' => ['application/pdf'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
    'png' => ['image/png'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
    'csv' => ['text/csv', 'text/plain', 'application/csv'], 'txt' => ['text/plain'],
];

function upload_path(string $stored): string
{
    if (!preg_match('/^[a-f0-9]{40}$/', $stored)) throw new RuntimeException('bad file reference');
    return STORAGE . '/uploads/' . $stored;
}

function admin_upload_document(string $id): never
{
    require_perm('documents.manage');
    $cid = (int)$id;
    if (!one('SELECT id FROM clients WHERE id = ?', [$cid])) abort();
    $f = $_FILES['file'] ?? null;
    $title = p('title', 200);
    $err = null;
    if (!$f || !is_array($f) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name'])) $err = 'The upload failed. Try again.';
    elseif ($f['size'] > 20 * 1024 * 1024) $err = 'Files can be up to 20 MB.';
    elseif ($title === '') $err = 'Give the document a title.';
    if (!$err) {
        $orig = basename(str_replace('\\', '/', (string)$f['name']));
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
        if (!isset(UPLOAD_TYPES[$ext]) || !in_array($mime, UPLOAD_TYPES[$ext], true)) $err = 'That file type is not allowed.';
    }
    if ($err) {
        flash('err', $err);
        redirect("/admin/clients/$cid");
    }
    $stored = bin2hex(random_bytes(20));
    if (!move_uploaded_file($f['tmp_name'], upload_path($stored))) {
        flash('err', 'Could not save the file on the server.');
        redirect("/admin/clients/$cid");
    }
    @chmod(upload_path($stored), 0640);
    $safeName = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $orig) ?: 'document.' . $ext;
    insert('documents', ['client_id' => $cid, 'title' => $title, 'filename' => mb_substr($safeName, 0, 200), 'stored' => $stored, 'size' => (int)$f['size'], 'created_at' => now()]);
    audit('document.upload', "client #$cid $safeName");
    flash('ok', 'Document shared with the client.');
    redirect("/admin/clients/$cid");
}

function send_document(array $d): never
{
    $path = upload_path($d['stored']);
    if (!is_file($path)) abort();
    no_store();
    $name = preg_replace('/[^A-Za-z0-9._ -]+/', '_', (string)$d['filename']);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($path));
    header("Content-Security-Policy: default-src 'none'; sandbox");
    readfile($path);
    exit;
}

function admin_download(string $id): never
{
    require_perm('clients.view');
    $d = one('SELECT * FROM documents WHERE id = ?', [(int)$id]);
    if (!$d) abort();
    send_document($d);
}

function admin_document_delete(string $id): never
{
    require_perm('documents.manage');
    $d = one('SELECT * FROM documents WHERE id = ?', [(int)$id]);
    if (!$d) abort();
    @unlink(upload_path($d['stored']));
    q('DELETE FROM documents WHERE id = ?', [$d['id']]);
    audit('document.delete', '#' . $d['id']);
    flash('ok', 'Document deleted.');
    redirect('/admin/clients/' . (int)$d['client_id']);
}

// ---------- quotes ----------

const QUOTE_STATUSES = ['draft', 'sent', 'accepted', 'declined', 'expired'];

function quote_total(int $qid, float $taxRate = 0): array
{
    $sub = (float)val('SELECT COALESCE(SUM(qty * unit_price), 0) FROM quote_lines WHERE quote_id = ?', [$qid]);
    $tax = round($sub * $taxRate / 100, 2);
    return [$sub, $tax, $sub + $tax];
}

function quote_table(array $quotes): string
{
    if (!$quotes) return '<p class="note">No quotes yet.</p>';
    $clients = array_column(all('SELECT id, company FROM clients'), 'company', 'id');
    $h = '<div class="table-wrap"><table><thead><tr><th>Number</th><th>Title</th><th>Client</th><th>Total</th><th>Status</th></tr></thead><tbody>';
    foreach ($quotes as $qt) {
        [, , $total] = quote_total((int)$qt['id'], (float)$qt['tax_rate']);
        $h .= '<tr><td><a href="/admin/quotes/' . (int)$qt['id'] . '">' . e($qt['number']) . '</a></td><td>' . e($qt['title']) . '</td><td>' . e($clients[$qt['client_id']] ?? '') . '</td><td>' . money($total, $qt['currency']) . '</td><td>' . badge($qt['status']) . '</td></tr>';
    }
    return $h . '</tbody></table></div>';
}

function admin_quotes(): string
{
    require_perm('quotes.manage');
    return admin_layout('Quotes', '<p class="small">Create a quote from a client\'s page or from an enquiry.</p>' . quote_table(all('SELECT * FROM quotes ORDER BY id DESC LIMIT 300')));
}

function admin_quote_edit(string $id): string
{
    require_perm('quotes.manage');
    $clients = array_column(all('SELECT id, company FROM clients ORDER BY company'), 'company', 'id');
    if (!$clients) {
        flash('err', 'Add a client first.');
        redirect('/admin/clients');
    }
    if ($id === 'new') {
        $qt = ['id' => '', 'client_id' => (int)g('client'), 'enquiry_id' => (int)g('enquiry') ?: '', 'title' => '', 'status' => 'draft', 'currency' => setting('default_currency', 'USD'), 'tax_rate' => 0,
            'notes' => "Prices exclude any taxes unless shown.\nThird-party products are supplied under the vendor's licence terms.", 'valid_until' => gmdate('Y-m-d', time() + 30 * 86400), 'number' => ''];
        $lines = [];
        if ($qt['enquiry_id']) {
            $enq = one('SELECT * FROM enquiries WHERE id = ?', [$qt['enquiry_id']]);
            $data = $enq ? (json_decode((string)$enq['data'], true) ?: []) : [];
            $ids = array_map('intval', $data['item_ids'] ?? []);
            if ($ids) $lines = all('SELECT id AS item_id, name AS description, 1 AS qty, 0 AS unit_price FROM items WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
            if ($enq) $qt['title'] = mb_substr((string)$enq['subject'], 0, 200);
        }
    } else {
        $qt = one('SELECT * FROM quotes WHERE id = ?', [(int)$id]);
        if (!$qt) abort();
        $lines = all('SELECT * FROM quote_lines WHERE quote_id = ? ORDER BY sort, id', [$qt['id']]);
    }
    $itemOpts = ['' => '(free text)'] + array_column(all('SELECT id, name FROM items ORDER BY kind, name'), 'name', 'id');
    $lines[] = ['item_id' => '', 'description' => '', 'qty' => 1, 'unit_price' => 0];
    $lines[] = ['item_id' => '', 'description' => '', 'qty' => 1, 'unit_price' => 0];
    $lh = '<div class="table-wrap"><table class="lines"><thead><tr><th>Catalogue item</th><th>Description</th><th>Qty</th><th>Unit price</th></tr></thead><tbody>';
    foreach ($lines as $i => $l) {
        $sel = '<select name="line_item[]" aria-label="Catalogue item">';
        foreach ($itemOpts as $k => $v) $sel .= '<option value="' . e((string)$k) . '"' . ((string)$k === (string)$l['item_id'] ? ' selected' : '') . '>' . e($v) . '</option>';
        $sel .= '</select>';
        $lh .= '<tr><td>' . $sel . '</td><td><input name="line_desc[]" value="' . e((string)$l['description']) . '" maxlength="255" aria-label="Description"></td>'
            . '<td><input name="line_qty[]" type="number" step="0.01" min="0" value="' . e((string)(float)$l['qty']) . '" aria-label="Quantity"></td>'
            . '<td><input name="line_price[]" type="number" step="0.01" min="0" value="' . e((string)(float)$l['unit_price']) . '" aria-label="Unit price"></td></tr>';
    }
    $lh .= '</tbody></table></div><p class="small">Leave a description empty to remove that line. Save to get more empty lines.</p>';
    $totals = '';
    if ($qt['id']) {
        [$sub, $tax, $total] = quote_total((int)$qt['id'], (float)$qt['tax_rate']);
        $totals = '<p class="totals">Subtotal ' . money($sub, $qt['currency']) . ' &middot; Tax ' . money($tax, $qt['currency']) . ' &middot; <strong>Total ' . money($total, $qt['currency']) . '</strong></p>';
    }
    $form = '<form method="post" action="/admin/quotes/save" class="card edit">' . csrf_field() . '<input type="hidden" name="id" value="' . e((string)$qt['id']) . '"><input type="hidden" name="enquiry_id" value="' . e((string)$qt['enquiry_id']) . '">'
        . '<div class="row">' . field('client_id', 'Client', (string)$qt['client_id'], ['type' => 'select', 'required' => true, 'options' => array_combine(array_map('strval', array_keys($clients)), $clients)])
        . field('title', 'Title', (string)$qt['title'], ['required' => true, 'attrs' => ' maxlength="200"']) . '</div>'
        . '<div class="row">' . field('currency', 'Currency (3 letters)', (string)$qt['currency'], ['required' => true, 'attrs' => ' pattern="[A-Za-z]{3}" maxlength="3"'])
        . field('tax_rate', 'Tax rate %', (string)(float)$qt['tax_rate'], ['type' => 'number', 'attrs' => ' step="0.01" min="0" max="100"']) . '</div>'
        . '<div class="row">' . field('valid_until', 'Valid until', (string)$qt['valid_until'], ['type' => 'date'])
        . field('status', 'Status', (string)$qt['status'], ['type' => 'select', 'options' => array_combine(QUOTE_STATUSES, array_map('ucfirst', QUOTE_STATUSES))]) . '</div>'
        . '<h2>Lines</h2>' . $lh . $totals . field('notes', 'Notes and terms shown to the client', (string)$qt['notes'], ['type' => 'textarea'])
        . '<div class="actions"><button class="btn" type="submit">Save quote</button></div></form>';
    $side = '';
    if ($qt['id']) {
        $side = '<div class="actions">'
            . '<form method="post" action="/admin/quotes/' . (int)$qt['id'] . '/send">' . csrf_field() . '<button class="btn small" type="submit">' . ($qt['status'] === 'draft' ? 'Send to client portal' : 'Notify client again') . '</button></form>'
            . '<a class="btn small ghost" href="/admin/clients/' . (int)$qt['client_id'] . '">Client page</a>'
            . '<form method="post" action="/admin/quotes/' . (int)$qt['id'] . '/delete" data-confirm="Delete this quote?">' . csrf_field() . '<button class="btn small danger" type="submit">Delete</button></form></div>';
        if ($qt['client_comment']) $side .= '<h3>Client comment</h3><pre class="msg">' . e($qt['client_comment']) . '</pre>';
    }
    return admin_layout($qt['id'] ? 'Quote ' . $qt['number'] : 'New quote', $side . $form);
}

function admin_quote_save(): string
{
    require_perm('quotes.manage');
    $id = (int)p('id', 12);
    $clientId = (int)p('client_id', 12);
    if (!one('SELECT id FROM clients WHERE id = ?', [$clientId])) { flash('err', 'Choose a client.'); redirect('/admin/quotes'); }
    $status = in_array(p('status'), QUOTE_STATUSES, true) ? p('status') : 'draft';
    $vu = p('valid_until', 10);
    $data = [
        'client_id' => $clientId, 'title' => p('title', 200) ?: 'Quote', 'status' => $status,
        'currency' => strtoupper(preg_replace('/[^A-Za-z]/', '', p('currency', 3)) ?: 'USD'),
        'tax_rate' => max(0, min(100, (float)p('tax_rate', 10))), 'notes' => p('notes', 10000),
        'valid_until' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $vu) ? $vu : null, 'updated_at' => now(),
    ];
    if ($id) {
        if (!one('SELECT id FROM quotes WHERE id = ?', [$id])) abort();
        update('quotes', $id, $data);
    } else {
        $eid = (int)p('enquiry_id', 12);
        $data += ['enquiry_id' => $eid ?: null, 'created_at' => now(), 'number' => ''];
        $id = insert('quotes', $data);
        update('quotes', $id, ['number' => setting('quote_prefix', 'C4C-Q') . '-' . gmdate('Y') . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT)]);
    }
    q('DELETE FROM quote_lines WHERE quote_id = ?', [$id]);
    $desc = (array)($_POST['line_desc'] ?? []);
    $validItems = array_map('intval', array_column(all('SELECT id FROM items'), 'id'));
    foreach (array_slice($desc, 0, 100, true) as $i => $d) {
        $d = is_string($d) ? mb_substr(trim($d), 0, 255) : '';
        if ($d === '') continue;
        $itemId = (int)($_POST['line_item'][$i] ?? 0);
        insert('quote_lines', [
            'quote_id' => $id, 'item_id' => in_array($itemId, $validItems, true) ? $itemId : null, 'description' => $d,
            'qty' => max(0, min(1e7, (float)($_POST['line_qty'][$i] ?? 1))), 'unit_price' => max(0, min(1e9, (float)($_POST['line_price'][$i] ?? 0))), 'sort' => (int)$i,
        ]);
    }
    audit('quote.save', "#$id");
    flash('ok', 'Quote saved.');
    redirect("/admin/quotes/$id");
}

function admin_quote_send(string $id): never
{
    require_perm('quotes.manage');
    $qt = one('SELECT * FROM quotes WHERE id = ?', [(int)$id]);
    if (!$qt) abort();
    if ($qt['status'] === 'draft') q("UPDATE quotes SET status = 'sent', updated_at = ? WHERE id = ?", [now(), $qt['id']]);
    $emails = array_column(all("SELECT email FROM users WHERE client_id = ? AND role = 'client' AND active = 1", [$qt['client_id']]), 'email');
    if (!$emails) {
        flash('err', 'Quote is visible in the portal, but this client has no active portal users to notify. Add one on the client page.');
    } else {
        $ok = send_mail($emails, 'New quote from Call4Counsel: ' . $qt['number'],
            "Hello,\n\nA new quote, {$qt['number']} \"{$qt['title']}\", is ready in your client portal:\n\n" . base_url() . '/portal/quotes/' . (int)$qt['id'] . "\n\nYou can review it and accept or decline it there.\n\nCall4Counsel");
        flash($ok ? 'ok' : 'err', $ok ? 'Client notified by email.' : 'The quote is in the portal, but the email could not be sent.');
    }
    audit('quote.send', '#' . $qt['id']);
    redirect('/admin/quotes/' . (int)$qt['id']);
}

function admin_quote_delete(string $id): never
{
    require_perm('quotes.manage');
    q('DELETE FROM quote_lines WHERE quote_id = ?', [(int)$id]);
    q('DELETE FROM quotes WHERE id = ?', [(int)$id]);
    audit('quote.delete', "#$id");
    flash('ok', 'Quote deleted.');
    redirect('/admin/quotes');
}

// ---------- settings, admins, account ----------

function password_form(string $action, string $title, array $err = []): string
{
    $e = $err ? '<div class="flash err" role="alert">' . implode('<br>', array_map('e', $err)) . '</div>' : '';
    return '<form method="post" action="' . e($action) . '" class="card narrow-card">' . csrf_field() . $e
        . field('current', 'Current password', '', ['type' => 'password', 'required' => true, 'attrs' => ' autocomplete="current-password"'])
        . field('password', 'New password (at least ' . min_password() . ' characters)', '', ['type' => 'password', 'required' => true, 'attrs' => ' autocomplete="new-password" minlength="' . min_password() . '"'])
        . field('confirm', 'Confirm new password', '', ['type' => 'password', 'required' => true, 'attrs' => ' autocomplete="new-password"'])
        . '<button class="btn" type="submit">' . e($title) . '</button></form>';
}

function admin_account(): string
{
    $u = require_role('admin');
    $roles = array_column(roles_list(), 'name', 'rkey');
    $info = '<dl class="facts"><dt>Name</dt><dd>' . e($u['name']) . '</dd><dt>Email</dt><dd>' . e($u['email']) . '</dd><dt>Role</dt><dd>' . e($roles[$u['role_key']] ?? '') . '</dd>'
        . '<dt>Two-step sign-in</dt><dd>' . ((int)$u['totp_enabled'] ? 'On' : 'Off') . ' &middot; <a href="/account/two-step">Manage</a></dd></dl>';
    return admin_layout('My account', $info . '<h2>Change password</h2>' . password_form('/admin/account', 'Change password'));
}

/** Shared by the admin and portal account pages. */
function account_password_post(): string
{
    $u = current_user();
    if (!$u) redirect('/portal/login');
    no_store();
    $action = $u['role'] === 'admin' ? '/admin/account' : '/portal/account';
    $err = [];
    if (!password_verify((string)($_POST['current'] ?? ''), (string)$u['password_hash'])) $err[] = 'Your current password is not right.';
    if ($pe = password_problem((string)($_POST['password'] ?? ''), (string)($_POST['confirm'] ?? ''), $u['email'])) $err[] = $pe;
    if ($err) {
        $form = password_form($action, 'Change password', $err);
        return $u['role'] === 'admin' ? admin_layout('My account', $form) : portal_layout('My password', $form);
    }
    set_password((int)$u['id'], (string)$_POST['password']);
    login_user(one('SELECT * FROM users WHERE id = ?', [$u['id']]));
    audit('password.change');
    flash('ok', 'Password changed.');
    redirect($action);
}

