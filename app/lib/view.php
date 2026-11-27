<?php
declare(strict_types=1);

function site_name(): string
{
    return 'Call4Counsel';
}

function contact_email(): string
{
    $e = setting('contact_email', 'hello@call4counsel.com');
    return valid_email($e) ? $e : 'hello@call4counsel.com';
}

const LOGO = '<svg viewBox="0 0 40 40" aria-hidden="true"><rect x="1" y="1" width="38" height="38" rx="9" fill="#0b2545"/>'
    . '<path d="M20 8l10 3.8v7.2c0 6.3-4.3 10.6-10 12.2-5.7-1.6-10-5.9-10-12.2v-7.2z" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linejoin="round"/>'
    . '<path d="M15.5 19.8l3.2 3.2 6-6.4" fill="none" stroke="#7aa2ff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';

const ICONS = [
    'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
    'data' => '<ellipse cx="12" cy="5.5" rx="7.5" ry="2.5"/><path d="M4.5 5.5v6c0 1.4 3.4 2.5 7.5 2.5s7.5-1.1 7.5-2.5v-6"/><path d="M4.5 11.5v6c0 1.4 3.4 2.5 7.5 2.5s7.5-1.1 7.5-2.5v-6"/>',
    'code' => '<path d="M8 8l-4 4 4 4M16 8l4 4-4 4M13.5 5l-3 14"/>',
    'cloud' => '<path d="M7 18h10a4 4 0 0 0 .5-8 6 6 0 0 0-11.4 1.6A3.3 3.3 0 0 0 7 18z"/>',
    'flow' => '<circle cx="5" cy="6" r="2"/><circle cx="19" cy="6" r="2"/><circle cx="12" cy="18" r="2"/><path d="M7 6h10M6 8l5 8M18 8l-5 8"/>',
    'check' => '<path d="M4 12l5 5L20 6"/>',
    'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>',
    'lock' => '<rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
    'map' => '<path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2z"/><path d="M9 4v14M15 6v14"/>',
    'people' => '<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><circle cx="17" cy="9" r="2.4"/><path d="M15.5 14.6c3 .1 5.5 2 5.5 5.4"/>',
    'gauge' => '<path d="M4 17a8 8 0 1 1 16 0"/><path d="M12 17l4-6"/>',
    'doc' => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h6"/>',
    'bot' => '<rect x="4" y="8" width="16" height="11" rx="3"/><path d="M12 4v4M9 13h.01M15 13h.01M9 16.5h6"/><circle cx="12" cy="3.5" r="1"/>',
    'spark' => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/><path d="M19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8z"/>',
    'box' => '<path d="M3 7.5L12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5l9 4.5 9-4.5M12 12v9"/>',
    'server' => '<rect x="4" y="4" width="16" height="7" rx="2"/><rect x="4" y="13" width="16" height="7" rx="2"/><path d="M8 7.5h.01M8 16.5h.01"/>',
    'key' => '<circle cx="8" cy="15" r="4"/><path d="M11 12l8-8M16 7l2 2M14 9l2 2"/>',
    'headset' => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="14" width="4" height="6" rx="1.5"/><rect x="17" y="14" width="4" height="6" rx="1.5"/><path d="M19 20c0 1-2 2-5 2"/>',
];

function icon(?string $k): string
{
    $svg = ICONS[$k ?? ''] ?? ICONS['shield'];
    return '<div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true">' . $svg . '</svg></div>';
}

function nav_items(): array
{
    return [
        '/services' => 'Services', '/ai-security' => 'AI security', '/catalogue' => 'Catalogue',
        '/case-studies' => 'Case studies', '/insights' => 'Insights', '/about' => 'About',
    ];
}

function render_layout(string $title, string $desc, string $body, array $opt = []): string
{
    $path = $opt['section'] ?? (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $links = '';
    foreach (nav_items() as $href => $label) {
        $cur = ($path === $href || str_starts_with($path, $href . '/')) ? ' aria-current="page"' : '';
        $links .= '<a href="' . $href . '"' . $cur . '>' . e($label) . '</a>';
    }
    $cart = count($_SESSION['quote'] ?? []);
    $cartLink = $cart ? '<a class="quote-link" href="/quote">Quote list <span class="count">' . $cart . '</span></a>' : '';
    $canonical = base_url() . ($opt['canonical'] ?? $path);
    $robots = !empty($opt['noindex']) ? '<meta name="robots" content="noindex, nofollow">' : '';
    $email = e(contact_email());
    $legal = setting('company_legal_name');
    $addr = setting('company_address');
    $reg = setting('company_registration');
    $phone = setting('contact_phone');
    $company = '';
    if ($legal) $company .= '<p>' . e($legal) . ($reg ? '<br><span class="small">' . e($reg) . '</span>' : '') . '</p>';
    if ($addr) $company .= '<p class="small">' . nl2br(e($addr)) . '</p>';
    $phoneHtml = $phone ? '<p><a href="tel:' . e(preg_replace('/[^0-9+]/', '', $phone)) . '">' . e($phone) . '</a></p>' : '';
    $year = date('Y');
    $u = current_user();
    $acct = $u ? ($u['role'] === 'admin' ? '<li><a href="/admin">Admin panel</a></li>' : '<li><a href="/portal">My client portal</a></li>')
        : '<li><a href="/portal/login">Client portal login</a></li>';
    $desc = e($desc);
    $t = e($title);
    $ver = e(APP_VERSION);
    $brandVer = substr(md5(brand_accent()), 0, 8);
    $logo = LOGO;
    $brand = e(brand_name());
    $footer = e(setting('brand_footer', 'Application security, AI and agentic security, and IT services, with the products to back them. Senior people, clear advice, work that lasts.'));
    $flash = flashes();
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>$t</title>
<meta name="description" content="$desc">
$robots
<link rel="canonical" href="{$canonical}">
<meta property="og:title" content="$t">
<meta property="og:description" content="$desc">
<meta property="og:type" content="website">
<meta property="og:site_name" content="$brand">
<meta name="theme-color" content="#ffffff">
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/site.css?v={$ver}">
<link rel="stylesheet" href="/brand.css?v={$brandVer}">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="site-head">
  <div class="head-in">
    <a class="brand" href="/" aria-label="Call4Counsel home">{$logo}<span>Call<b>4</b>Counsel</span></a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav">Menu</button>
    <nav class="nav" id="nav" aria-label="Main">$links$cartLink<a class="btn" href="/assessment">Free AI assessment</a></nav>
  </div>
</header>
<main id="main">
$flash
$body
</main>
<footer class="site-foot">
  <div class="foot-in">
    <div>
      <a class="brand" href="/">{$logo}<span>Call<b>4</b>Counsel</span></a>
      <p>$footer</p>
      <p><a href="mailto:$email">$email</a></p>
      $phoneHtml
      $company
    </div>
    <div><h3>Services</h3><ul>
      <li><a href="/ai-security">AI &amp; agentic security</a></li>
      <li><a href="/services/application-security">Application security</a></li>
      <li><a href="/services/it-services">IT services</a></li>
      <li><a href="/catalogue?kind=product">Products we supply</a></li>
      <li><a href="/assessment">Free AI &amp; process assessment</a></li></ul></div>
    <div><h3>Company</h3><ul>
      <li><a href="/about">About</a></li>
      <li><a href="/case-studies">Case studies</a></li>
      <li><a href="/insights">Insights</a></li>
      <li><a href="/careers">Careers</a></li></ul></div>
    <div><h3>Get in touch</h3><ul>
      <li><a href="/contact">Contact us</a></li>
      <li><a href="/quote">Request a quote</a></li>
      $acct
      <li><a href="/privacy">Privacy policy</a></li>
      <li><a href="/terms">Terms of business</a></li></ul></div>
  </div>
  <div class="foot-base"><span>&copy; $year {$brand}. All rights reserved.</span><span><a href="/privacy">Privacy</a> &middot; <a href="/terms">Terms</a></span></div>
</footer>
<script src="/assets/site.js?v={$ver}"></script>
</body>
</html>
HTML;
}

function page_head(string $crumb, string $h1, string $lead, string $extra = ''): string
{
    $c = $crumb ? '<div class="crumb"><a href="/">Home</a> / ' . $crumb . '</div>' : '';
    return '<section class="page-head"><div class="wrap">' . $c . '<h1>' . e($h1) . '</h1><p class="lead">' . e($lead) . '</p>' . $extra . '</div></section>';
}

function cta(string $h = 'Not sure where to start?', string $p = 'Take the free AI & process assessment, or book a 30-minute call. We will tell you honestly whether we can help.'): string
{
    return '<section class="band ink"><div class="wrap cta"><div><h2>' . e($h) . '</h2><p class="lead">' . e($p) . '</p></div>'
        . '<div class="actions"><a class="btn" href="/assessment">Start the assessment</a><a class="btn ghost" href="/contact">Book a call</a></div></div></section>';
}

function cards(array $items, string $cls = 'grid'): string
{
    $out = '';
    foreach ($items as $it) {
        $more = isset($it[3]) ? '<a class="more" href="' . e($it[3]) . '">' . e($it[4]) . ' &rarr;</a>' : '';
        $out .= '<article class="card">' . icon($it[0]) . '<h3>' . e($it[1]) . '</h3><p>' . e($it[2]) . '</p>' . $more . '</article>';
    }
    return '<div class="' . $cls . '">' . $out . '</div>';
}

function ticks(array $items): string
{
    return '<ul class="ticks">' . implode('', array_map(fn($i) => '<li>' . e($i) . '</li>', $items)) . '</ul>';
}

function pills(array $items): string
{
    return '<div class="pills">' . implode('', array_map(fn($i) => '<span class="pill">' . e($i) . '</span>', $items)) . '</div>';
}

function steps(array $steps): string
{
    $o = '<ol class="steps">';
    foreach ($steps as [$t, $p]) $o .= '<li><div class="wp"><i></i>' . e($t) . '</div><p>' . e($p) . '</p></li>';
    return $o . '</ol>';
}

const ROUTE_STEPS = [
    ['Assess', 'We map how your teams, systems and AI tools really work today: the code, the pipelines, the agents and the paperwork.'],
    ['Chart', 'A prioritised plan in plain language, with the services and products that fit, and the effort, risk and cost of each.'],
    ['Deliver', 'We fix, build, deploy and license alongside your team, in small reviewed increments.'],
    ['Hand over', 'Runbooks, training and support, so your team owns the result when we step back.'],
];

function item_card(array $it, bool $showCat = false): string
{
    $kind = $it['kind'] === 'product' ? '<span class="kind product">Product</span>' : '<span class="kind service">Service</span>';
    $cat = $showCat && !empty($it['cat_name']) ? '<span class="post-meta">' . e($it['cat_name']) . '</span>' : '';
    $meta = [];
    if (!empty($it['duration'])) $meta[] = e($it['duration']);
    $est = function_exists('estimate_text') ? estimate_text($it) : '';
    if ($est !== '') $meta[] = '<strong>' . e($est) . '</strong>';
    elseif (!empty($it['price_note'])) $meta[] = e($it['price_note']);
    $metaHtml = $meta ? '<p class="small">' . implode(' &middot; ', $meta) . '</p>' : '';
    return '<article class="card item">' . '<div class="item-top">' . $kind . $cat . '</div>'
        . '<h3><a href="/catalogue/' . e($it['slug']) . '">' . e($it['name']) . '</a></h3><p>' . e($it['summary']) . '</p>' . $metaHtml
        . '<div class="item-actions">' . add_to_quote_form((int)$it['id'], true) . '<a class="more" href="/catalogue/' . e($it['slug']) . '">Details &rarr;</a></div></article>';
}

function add_to_quote_form(int $id, bool $compact = false): string
{
    $in = in_array($id, $_SESSION['quote'] ?? [], true);
    if ($in) return '<a class="btn small ghost" href="/quote">In your quote list</a>';
    // No session token here, so browsing the catalogue sets no cookie; /quote/add is
    // protected by the browser's Fetch Metadata and Origin headers instead (see csrf_check).
    return '<form method="post" action="/quote/add" class="inline"><input type="hidden" name="id" value="' . $id . '">'
        . '<button class="btn small' . ($compact ? ' ghost' : '') . '" type="submit">Add to quote</button></form>';
}

function field(string $name, string $label, string $value = '', array $o = []): string
{
    $id = 'f-' . preg_replace('/[^a-z0-9]+/i', '-', $name);
    $req = !empty($o['required']) ? ' required' : '';
    $type = $o['type'] ?? 'text';
    $help = isset($o['help']) ? '<span class="help">' . e($o['help']) . '</span>' : '';
    $attrs = ($o['attrs'] ?? '') . $req;
    if ($type === 'textarea') {
        $input = '<textarea id="' . $id . '" name="' . e($name) . '"' . $attrs . '>' . e($value) . '</textarea>';
    } elseif ($type === 'select') {
        $input = '<select id="' . $id . '" name="' . e($name) . '"' . $attrs . '>';
        $isList = array_is_list($o['options']);
        foreach ($o['options'] as $k => $v) {
            if ($isList) $k = $v;
            $input .= '<option value="' . e((string)$k) . '"' . ((string)$k === $value ? ' selected' : '') . '>' . e($v) . '</option>';
        }
        $input .= '</select>';
    } else {
        $input = '<input id="' . $id . '" type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . $attrs . '>';
    }
    return '<div class="field"><label for="' . $id . '">' . e($label) . '</label>' . $help . $input . '</div>';
}
