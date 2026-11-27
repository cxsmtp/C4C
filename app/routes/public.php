<?php
declare(strict_types=1);

function public_routes(): array
{
    return [
        ['GET', '/', 'page_home'],
        ['GET', '/services', 'page_services'],
        ['GET', '/services/([a-z0-9-]+)', 'page_service_category'],
        ['GET', '/ai-security', 'page_ai_security'],
        ['GET', '/catalogue', 'page_catalogue'],
        ['GET', '/catalogue/([a-z0-9-]+)', 'page_item'],
        ['POST', '/quote/add', 'quote_add'],
        ['POST', '/quote/remove', 'quote_remove'],
        ['GET', '/quote', 'page_quote'],
        ['POST', '/quote', 'quote_submit'],
        ['GET', '/assessment', 'page_assessment'],
        ['POST', '/assessment', 'assessment_submit'],
        ['GET', '/assessment/result/([a-f0-9]{48})', 'page_assessment_result'],
        ['GET', '/case-studies', 'page_cases'],
        ['GET', '/insights', 'page_insights'],
        ['GET', '/insights/([a-z0-9-]+)', 'page_post'],
        ['GET', '/careers', 'page_careers'],
        ['GET', '/careers/([a-z0-9-]+)', 'page_job'],
        ['POST', '/careers/([a-z0-9-]+)', 'job_apply'],
        ['GET', '/contact', 'page_contact'],
        ['POST', '/contact', 'contact_submit'],
        ['GET', '/thanks', 'page_thanks'],
        ['GET', '/(about|privacy|terms)', 'page_cms'],
        ['GET', '/sitemap.xml', 'sitemap'],
        // Old static URLs keep working.
        ['GET', '/(index|services|application-security|data-engineering|about|case-studies|insights|careers|contact|privacy)\.html', 'legacy_redirect'],
    ];
}

function legacy_redirect(string $name): never
{
    $map = ['index' => '/', 'application-security' => '/services/application-security', 'data-engineering' => '/services/data-engineering'];
    redirect($map[$name] ?? '/' . $name, 301);
}

function service_categories(string $kind = 'service'): array
{
    return all('SELECT * FROM categories WHERE kind = ? ORDER BY sort, name', [$kind]);
}

function items_in(int $catId): array
{
    return all('SELECT * FROM items WHERE category_id = ? AND published = 1 ORDER BY featured DESC, sort, name', [$catId]);
}

function page_home(): string
{
    $aiPoints = ticks([
        'Inventory every AI tool, model and agent, including shadow AI',
        'Red-team chatbots, copilots, RAG and agents for prompt injection and data leakage',
        'Least privilege and human approval for agent actions',
        'AI gateways, guardrails and monitoring, deployed and tuned',
        'Governance aligned with ISO/IEC 42001, NIST AI RMF and the EU AI Act',
    ]);
    $iPeople = icon('people');
    $iDoc = icon('doc');
    $iCheck = icon('check');
    $iBox = icon('box');
    $featured = all("SELECT i.*, c.name AS cat_name FROM items i LEFT JOIN categories c ON c.id = i.category_id WHERE i.published = 1 AND i.featured = 1 ORDER BY i.sort LIMIT 6");
    $posts = all('SELECT * FROM posts WHERE published = 1 ORDER BY published_at DESC LIMIT 3');
    $cats = service_categories();
    $catCards = '';
    foreach ($cats as $c) {
        $list = array_slice(array_column(items_in((int)$c['id']), 'name'), 0, 4);
        $href = $c['slug'] === 'ai-agentic-security' ? '/ai-security' : '/services/' . $c['slug'];
        $catCards .= '<article class="card">' . icon($c['icon']) . '<h3>' . e($c['name']) . '</h3><p>' . e($c['blurb']) . '</p>' . ticks($list)
            . '<a class="more" href="' . e($href) . '">Explore ' . e(strtolower($c['name'])) . ' &rarr;</a></article>';
    }
    $feat = implode('', array_map(fn($i) => item_card($i, true), $featured));
    $postCards = implode('', array_map('post_card', $posts));
    $map = hero_panel();
    $steps = steps(ROUTE_STEPS);
    $cta = cta();
    return render_layout('Call4Counsel | Application security, AI & agentic security and IT services',
        'Call4Counsel secures software, AI assistants and agents, and the IT they run on, and supplies the security and IT products to back it up.', <<<HTML
<section class="hero"><div class="hero-in">
  <div>
    <p class="eyebrow">Application security &middot; AI &amp; agentic security &middot; IT services</p>
    <h1>Adopt AI with confidence. Secure everything it touches.</h1>
    <p class="lead">Call4Counsel helps organisations understand how they really use AI and software today, then delivers the services and products that close the gaps: from red-teaming AI agents to hardening Microsoft 365, from code scanning licences to a helpdesk your people like.</p>
    <div class="actions"><a class="btn" href="/assessment">Take the free AI &amp; process assessment</a><a class="btn ghost" href="/catalogue">Browse the catalogue</a></div>
    <p class="small hero-note">About 5 minutes. You see your results straight away, and a senior consultant follows up with a tailored proposal.</p>
  </div>
  $map
</div></section>

<section class="band"><div class="wrap">
  <div class="section-head"><h2>One accountable partner for security, AI and IT</h2>
  <p class="lead">Most companies juggle a security consultancy, an IT provider and several software resellers, and nobody owns the whole picture. We bring advice, delivery and products under one roof, so recommendations turn into results.</p></div>
  <div class="grid">$catCards</div>
</div></section>

<section class="band sea"><div class="wrap split">
  <div><p class="eyebrow">New risks, new controls</p><h2>Your AI can now take actions. Who checks them?</h2>
  <p class="lead">Assistants read your documents. Agents call your APIs, send emails and change records. A single poisoned web page or support ticket can steer them. We assess, test and govern AI so it stays useful and stays inside safe limits.</p>
  <div class="actions"><a class="btn" href="/ai-security">AI &amp; agentic security</a></div></div>
  <div>$aiPoints</div>
</div></section>

<section class="band"><div class="wrap">
  <div class="section-head"><h2>Popular services and products</h2><p class="lead">Every item can be added to a quote list. We reply with a clear, fixed proposal.</p></div>
  <div class="grid">$feat</div>
  <div class="actions"><a class="btn ghost" href="/catalogue">See the full catalogue</a></div>
</div></section>

<section class="band sea"><div class="wrap">
  <div class="section-head"><h2>How we work</h2><p class="lead">Every engagement follows the same route. You always know where we are, what comes next, and what it costs.</p></div>
  $steps
</div></section>

<section class="band"><div class="wrap split">
  <div><h2>Why clients call us</h2>
  <p class="lead">The people who scope the work do the work. Findings come with fixes, and products come with people who know how to run them.</p>
  <a class="btn ghost" href="/about">About Call4Counsel</a></div>
  <div class="grid grid-tight">
    <article class="card">$iPeople<h3>Senior practitioners</h3><p>Hands-on consultants who have built and defended real systems.</p></article>
    <article class="card">$iDoc<h3>Plain-language advice</h3><p>Findings ranked by business risk, written for engineers and executives alike.</p></article>
    <article class="card">$iCheck<h3>Recognised frameworks</h3><p>OWASP, NIST, ISO and CIS, so results map to what customers and auditors expect.</p></article>
    <article class="card">$iBox<h3>Advice before products</h3><p>We recommend what fits, including tools you already own, and we are open about what we supply.</p></article>
  </div>
</div></section>

<section class="band sea"><div class="wrap">
  <div class="section-head"><h2>Insights</h2></div>
  <div class="grid">$postCards</div>
</div></section>
$cta
HTML, ['canonical' => '/']);
}
function hero_panel(): string
{
    $steps = [
        ['Assess', 'We map how you use AI, build software and run IT today.'],
        ['Secure', 'We test and fix the gaps in apps, APIs and AI agents.'],
        ['Equip', 'We supply and set up the tools that close them.'],
        ['Support', 'We hand over, train your team and stay on call.'],
    ];
    $li = '';
    foreach ($steps as $i => [$t, $d]) $li .= '<li><span class="num">' . ($i + 1) . '</span><div><strong>' . e($t) . '</strong><span class="small">' . e($d) . '</span></div></li>';
    return '<aside class="hero-panel" aria-label="How we work"><h2>How we work</h2><ol>' . $li . '</ol></aside>';
}

function post_card(array $p): string
{
    return '<article class="card"><div class="post-meta">' . e($p['topic']) . ' &middot; ' . e(fmt_date($p['published_at'])) . '</div><h3><a href="/insights/' . e($p['slug']) . '">'
        . e($p['title']) . '</a></h3><p>' . e($p['excerpt']) . '</p><a class="more" href="/insights/' . e($p['slug']) . '">Read article &rarr;</a></article>';
}

function page_services(): string
{
    $out = '';
    foreach (service_categories() as $c) {
        $href = $c['slug'] === 'ai-agentic-security' ? '/ai-security' : '/services/' . $c['slug'];
        $out .= '<article class="card">' . icon($c['icon']) . '<h2>' . e($c['name']) . '</h2><p>' . e($c['blurb']) . '</p>'
            . ticks(array_column(items_in((int)$c['id']), 'name')) . '<a class="more" href="' . e($href) . '">See ' . e(strtolower($c['name'])) . ' in detail &rarr;</a></article>';
    }
    $prod = '';
    foreach (service_categories('product') as $c) {
        $prod .= '<article class="card">' . icon($c['icon']) . '<h3>' . e($c['name']) . '</h3><p>' . e($c['blurb']) . '</p><a class="more" href="/catalogue?cat=' . e($c['slug']) . '">View products &rarr;</a></article>';
    }
    $engage = cards([
        ['search', 'Assessment', 'Fixed scope and fixed fee, usually two to four weeks. You get findings, a risk-ranked roadmap and a readout for leadership.'],
        ['map', 'Project delivery', 'A defined outcome, such as a secured AI agent, a DevSecOps pipeline or a cloud migration, delivered with your team.'],
        ['headset', 'Managed and retained services', 'Ongoing IT support, a fractional security lead, or monitoring of the tools we deploy, for a predictable monthly fee.'],
        ['box', 'Products and licences', 'The security and IT products you need, sized, licensed, deployed and supported by the same team.'],
    ]);
    return render_layout('Services | Call4Counsel', 'AI and agentic security, application security, IT services and data engineering, plus the products to back them.',
        page_head('Services', 'Services and products', 'Four practices that work together, and the products to support them. Engage one, or all, in the way that suits your team.')
        . '<section class="band"><div class="wrap grid two">' . $out . '</div></section>'
        . '<section class="band sea"><div class="wrap"><div class="section-head"><h2>Products we supply</h2><p class="lead">Licensed, deployed and supported by the people who recommended them.</p></div><div class="grid">' . $prod . '</div></div></section>'
        . '<section class="band" id="engage"><div class="wrap"><div class="section-head"><h2>Ways to engage</h2><p class="lead">Every engagement has a written scope and a clear price before work starts.</p></div>' . $engage . '</div></section>'
        . faq_block() . cta());
}

function faq_block(): string
{
    $faq = [
        ['How quickly can you start?', 'Usually within two weeks of agreeing a scope. For urgent security concerns, say so on the contact form and we aim to respond the same business day.'],
        ['Do you work with small companies?', 'Yes. Many of our clients are start-ups and mid-sized firms that need senior expertise without hiring a full team.'],
        ['Will you sign an NDA?', 'Of course. We are happy to sign a mutual NDA before any detailed conversation about your systems.'],
        ['Do you sell software?', 'Yes. We supply security and IT products as part of our service. Our advice always starts from your needs, we will tell you when a tool you already own will do, and every quote shows exactly what you are buying.'],
        ['What happens after the AI assessment?', 'You see your results immediately. A senior consultant reviews your answers and replies within two business days with a tailored proposal of services and products. There is no obligation.'],
        ['Is it safe to test our AI or applications?', 'We test only systems you own or are authorised to have tested, under written rules of engagement agreed before any work starts.'],
    ];
    $d = '';
    foreach ($faq as [$q, $a]) $d .= '<details><summary>' . e($q) . '</summary><p>' . e($a) . '</p></details>';
    return '<section class="band sea" id="faq"><div class="wrap split"><div><h2>Questions we often hear</h2><p class="lead">Something else on your mind? <a href="/contact">Ask us directly.</a></p></div><div>' . $d . '</div></div></section>';
}

function page_service_category(string $slug): string
{
    if ($slug === 'ai-agentic-security') redirect('/ai-security', 301);
    $c = one("SELECT * FROM categories WHERE slug = ? AND kind = 'service'", [$slug]);
    if (!$c) abort();
    $items = items_in((int)$c['id']);
    $extra = match ($slug) {
        'application-security' => '<section class="band sea"><div class="wrap split"><div><h2>Standards we work to</h2><p>Results map directly to what customers and auditors expect.</p></div>'
            . pills(['OWASP Top 10', 'OWASP API Security Top 10', 'OWASP ASVS', 'OWASP SAMM', 'NIST SSDF', 'NIST CSF', 'CIS Benchmarks', 'SOC 2', 'ISO/IEC 27001', 'PCI DSS', 'SLSA']) . '</div></section>',
        'it-services' => '<section class="band sea"><div class="wrap split"><div><h2>Signs it is time to call</h2></div>'
            . ticks(['IT depends on one person who is always busy', 'Nobody is sure who still has access after people leave', 'You pay for licences nobody uses', 'Backups exist, but nobody has tried a restore', 'Staff use AI tools nobody approved']) . '</div></section>',
        'data-engineering' => '<section class="band sea"><div class="wrap split"><div><h2>Technology we work with</h2><p>We will help you choose, and we are just as happy to make the most of what you have.</p></div>'
            . pills(['Snowflake', 'Databricks', 'BigQuery', 'Amazon Redshift', 'PostgreSQL', 'dbt', 'Apache Airflow', 'Apache Spark', 'Apache Kafka', 'Terraform']) . '</div></section>',
        default => '',
    };
    $products = all("SELECT i.*, c.name AS cat_name FROM items i LEFT JOIN categories c ON c.id = i.category_id WHERE i.published = 1 AND i.kind = 'product' ORDER BY i.featured DESC, i.sort LIMIT 3");
    return render_layout($c['name'] . ' | Call4Counsel', (string)$c['blurb'],
        page_head('<a href="/services">Services</a> / ' . e($c['name']), $c['name'], (string)$c['blurb'])
        . '<section class="band"><div class="wrap"><div class="section-head"><h2>What we offer</h2></div><div class="grid">' . implode('', array_map('item_card', $items)) . '</div></div></section>'
        . $extra
        . '<section class="band"><div class="wrap"><div class="section-head"><h2>Products that pair well</h2></div><div class="grid">' . implode('', array_map(fn($i) => item_card($i, true), $products)) . '</div></div></section>'
        . '<section class="band sea"><div class="wrap"><div class="section-head"><h2>How it works</h2></div>' . steps(ROUTE_STEPS) . '</div></section>'
        . cta(), ['section' => '/services']);
}

function page_ai_security(): string
{
    $c = one("SELECT * FROM categories WHERE slug = 'ai-agentic-security'");
    $svc = $c ? items_in((int)$c['id']) : [];
    $prodCat = one("SELECT * FROM categories WHERE slug = 'ai-security-platforms'");
    $prod = $prodCat ? items_in((int)$prodCat['id']) : [];
    $risks = [
        ['LLM01', 'Prompt injection', 'Instructions hidden in user input, web pages, emails or documents take control of your AI.'],
        ['LLM02', 'Sensitive information disclosure', 'Confidential, personal or regulated data leaks through answers, logs or providers.'],
        ['LLM03', 'Supply chain', 'Models, plug-ins, MCP servers and datasets from third parties carry their own risks.'],
        ['LLM05', 'Improper output handling', 'AI output passed into browsers, code or databases becomes an injection attack.'],
        ['LLM06', 'Excessive agency', 'Agents hold more tools and permissions than their task needs, and act without checks.'],
        ['LLM08', 'Vector and embedding weaknesses', 'Retrieval returns documents a user should never see, or poisoned content.'],
    ];
    $riskHtml = '';
    foreach ($risks as [$id, $t, $d]) $riskHtml .= '<article class="card"><span class="pill">' . e($id) . '</span><h3>' . e($t) . '</h3><p>' . e($d) . '</p></article>';
    return render_layout('AI & agentic security | Call4Counsel',
        'Assess, red-team and govern AI assistants, RAG systems and autonomous agents. OWASP LLM Top 10, NIST AI RMF and ISO/IEC 42001 aligned.',
        page_head('AI &amp; agentic security', 'AI & agentic security', 'AI assistants and agents now read your data and act on your systems. We help you see every AI use, test it like an attacker would, and put the right guardrails and governance in place.',
            '<div class="actions"><a class="btn" href="/assessment">Start the free assessment</a><a class="btn ghost" href="/contact">Talk to a specialist</a></div>')
        . '<section class="band"><div class="wrap"><div class="section-head"><h2>The risks we test for</h2><p class="lead">Mapped to the OWASP Top 10 for LLM Applications (2025) and MITRE ATLAS.</p></div><div class="grid">' . $riskHtml . '</div></div></section>'
        . '<section class="band sea"><div class="wrap"><div class="section-head"><h2>Services</h2></div><div class="grid">' . implode('', array_map('item_card', $svc)) . '</div></div></section>'
        . '<section class="band"><div class="wrap"><div class="section-head"><h2>AI security products we supply</h2><p class="lead">Chosen to fit your environment, then licensed, deployed and tuned by our team.</p></div><div class="grid">' . implode('', array_map('item_card', $prod)) . '</div></div></section>'
        . '<section class="band sea"><div class="wrap split"><div><h2>Frameworks we align to</h2><p>So your AI programme stands up to customers, auditors and regulators.</p></div>'
        . pills(['OWASP Top 10 for LLM Applications', 'OWASP Agentic AI guidance', 'MITRE ATLAS', 'NIST AI RMF', 'NIST AI 600-1', 'ISO/IEC 42001', 'ISO/IEC 23894', 'EU AI Act']) . '</div></section>'
        . cta('How exposed is your AI today?', 'Answer a few questions about how you use AI and software. You get instant findings and recommendations, and a consultant follows up.'));
}

function page_catalogue(): string
{
    $kind = in_array(g('kind'), ['service', 'product'], true) ? g('kind') : '';
    $catSlug = g('cat');
    $term = g('q', 80);
    $where = ['i.published = 1'];
    $args = [];
    if ($kind) { $where[] = 'i.kind = ?'; $args[] = $kind; }
    if ($catSlug !== '') { $where[] = 'c.slug = ?'; $args[] = $catSlug; }
    if ($term !== '') {
        $where[] = "(i.name LIKE ? ESCAPE '!' OR i.summary LIKE ? ESCAPE '!' OR i.tags LIKE ? ESCAPE '!')";
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';
        array_push($args, $like, $like, $like);
    }
    $items = all('SELECT i.*, c.name AS cat_name, c.slug AS cat_slug FROM items i LEFT JOIN categories c ON c.id = i.category_id WHERE '
        . implode(' AND ', $where) . ' ORDER BY c.sort, i.featured DESC, i.sort', $args);
    $cats = all('SELECT * FROM categories ORDER BY sort');
    $opts = ['' => 'All categories'];
    foreach ($cats as $c) $opts[$c['slug']] = ($c['kind'] === 'product' ? 'Products: ' : 'Services: ') . $c['name'];
    $filters = '<form class="filters" method="get" action="/catalogue">'
        . field('q', 'Search', $term, ['type' => 'search', 'attrs' => ' placeholder="For example: agents, SAST, backup"'])
        . field('kind', 'Type', $kind, ['type' => 'select', 'options' => ['' => 'Services and products', 'service' => 'Services', 'product' => 'Products']])
        . field('cat', 'Category', $catSlug, ['type' => 'select', 'options' => $opts])
        . '<button class="btn" type="submit">Filter</button></form>';
    $grouped = [];
    foreach ($items as $i) $grouped[$i['cat_name'] ?? 'Other'][] = $i;
    $body = '';
    foreach ($grouped as $name => $list) {
        $body .= '<h2 class="group-title">' . e($name) . '</h2><div class="grid">' . implode('', array_map('item_card', $list)) . '</div>';
    }
    if (!$items) $body = '<p class="note">Nothing matches that search. <a href="/catalogue">Show everything</a> or <a href="/contact">ask us</a>; we supply more than we list.</p>';
    return render_layout('Catalogue: security, AI and IT services and products | Call4Counsel',
        'Browse Call4Counsel services and the security and IT products we supply. Add items to a quote list and get a clear proposal.',
        page_head('Catalogue', 'Services and products catalogue', 'Everything we deliver and supply. Add what interests you to a quote list, or take the assessment and let us recommend the right mix.')
        . '<section class="band"><div class="wrap">' . $filters . $body
        . '<p class="note">Not sure what you need? <a href="/assessment">Take the free AI &amp; process assessment</a> and we will recommend the right services and products for your situation.</p></div></section>' . cta());
}

function page_item(string $slug): string
{
    $it = one('SELECT i.*, c.name AS cat_name, c.slug AS cat_slug FROM items i LEFT JOIN categories c ON c.id = i.category_id WHERE i.slug = ? AND i.published = 1', [$slug]);
    if (!$it) abort();
    $deliv = lines_of($it['deliverables']);
    $facts = '';
    foreach ([['Type', $it['kind'] === 'product' ? 'Product' : 'Service'], ['Category', $it['cat_name']], ['Typical duration', $it['duration']], ['Pricing', $it['price_note']], ['Estimate', estimate_text($it)], ['Vendor', $it['vendor']]] as [$k, $v]) {
        if ($v) $facts .= '<dt>' . e($k) . '</dt><dd>' . e($v) . '</dd>';
    }
    $related = all('SELECT i.*, c.name AS cat_name FROM items i LEFT JOIN categories c ON c.id = i.category_id WHERE i.published = 1 AND i.category_id = ? AND i.id <> ? ORDER BY i.featured DESC, i.sort LIMIT 3', [$it['category_id'], $it['id']]);
    return render_layout($it['name'] . ' | Call4Counsel', (string)$it['summary'],
        page_head('<a href="/catalogue">Catalogue</a> / <a href="/catalogue?cat=' . e($it['cat_slug']) . '">' . e($it['cat_name']) . '</a>', $it['name'], (string)$it['summary'])
        . '<section class="band"><div class="wrap split wide-left"><div class="prose">' . md($it['body'])
        . ($deliv ? '<h2>What you get</h2>' . ticks($deliv) : '') . '</div>'
        . '<aside class="card sticky"><dl class="facts">' . $facts . '</dl>' . add_to_quote_form((int)$it['id'])
        . '<p class="small">Adding to your quote list costs nothing. We reply with a clear proposal and fixed price.</p><a class="more" href="/contact">Or ask a question &rarr;</a></aside></div></section>'
        . ($related ? '<section class="band sea"><div class="wrap"><div class="section-head"><h2>Related</h2></div><div class="grid">' . implode('', array_map('item_card', $related)) . '</div></div></section>' : '')
        . cta(), ['section' => '/catalogue']);
}

function quote_add(): never
{
    $id = (int)p('id', 12);
    if ($id && one('SELECT id FROM items WHERE id = ? AND published = 1', [$id])) {
        $_SESSION['quote'] = array_values(array_unique([...($_SESSION['quote'] ?? []), $id]));
        $_SESSION['quote'] = array_slice($_SESSION['quote'], 0, 40);
        flash('ok', 'Added to your quote list.');
    }
    redirect('/quote');
}

function quote_remove(): never
{
    $id = (int)p('id', 12);
    $_SESSION['quote'] = array_values(array_filter($_SESSION['quote'] ?? [], fn($x) => $x !== $id));
    redirect('/quote');
}

function quote_items(): array
{
    $ids = array_map('intval', $_SESSION['quote'] ?? []);
    if (!$ids) return [];
    return all('SELECT i.*, c.name AS cat_name FROM items i LEFT JOIN categories c ON c.id = i.category_id WHERE i.published = 1 AND i.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY i.kind DESC, i.name', $ids);
}

function contact_fields(array $v = [], bool $company = true): string
{
    return '<div class="row">' . field('name', 'Your name', $v['name'] ?? '', ['required' => true, 'attrs' => ' autocomplete="name" maxlength="120"'])
        . ($company ? field('company', 'Company', $v['company'] ?? '', ['attrs' => ' autocomplete="organization" maxlength="200"']) : '') . '</div>'
        . '<div class="row">' . field('email', 'Work email', $v['email'] ?? '', ['type' => 'email', 'required' => true, 'attrs' => ' autocomplete="email" maxlength="191"'])
        . field('phone', 'Phone (optional)', $v['phone'] ?? '', ['type' => 'tel', 'attrs' => ' autocomplete="tel" maxlength="60"']) . '</div>';
}

function consent_box(): string
{
    return '<label class="check"><input type="checkbox" name="consent" value="yes" required> <span>I agree that Call4Counsel may use these details to reply to me, as described in the <a href="/privacy">privacy policy</a>.</span></label>';
}

function page_quote(array $err = [], array $v = []): string
{
    $items = quote_items();
    $rows = '';
    foreach ($items as $i) {
        $rows .= '<li class="quote-row"><div><span class="kind ' . e($i['kind']) . '">' . e(ucfirst($i['kind'])) . '</span> <a href="/catalogue/' . e($i['slug']) . '">' . e($i['name']) . '</a><div class="small">' . e($i['price_note']) . '</div></div>'
            . '<form method="post" action="/quote/remove" class="inline">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$i['id'] . '"><button class="linkish" type="submit">Remove</button></form></li>';
    }
    $list = $items ? '<ul class="quote-list">' . $rows . '</ul>' : '<p class="note">Your quote list is empty. <a href="/catalogue">Browse the catalogue</a> and add services or products, or just describe what you need below.</p>';
    $errHtml = $err ? '<div class="flash err" role="alert">' . implode('<br>', array_map('e', $err)) . '</div>' : '';
    $form = '<form method="post" action="/quote" class="card">' . csrf_field() . bot_fields() . $errHtml . contact_fields($v)
        . '<div class="row">' . field('users', 'Number of users or seats (if relevant)', $v['users'] ?? '', ['attrs' => ' maxlength="40" inputmode="numeric"'])
        . field('timeline', 'Timeline', $v['timeline'] ?? '', ['type' => 'select', 'options' => ['' => 'Choose one', 'Urgent (this week)' => 'Urgent (this week)', 'Within a month' => 'Within a month', 'This quarter' => 'This quarter', 'Just exploring' => 'Just exploring']]) . '</div>'
        . field('message', 'Anything we should know?', $v['message'] ?? '', ['type' => 'textarea', 'attrs' => ' maxlength="5000"', 'help' => 'Current tools, environment, deadlines or budget.'])
        . consent_box() . '<button class="btn" type="submit">Request my quote</button></form>';
    return render_layout('Request a quote | Call4Counsel', 'Request a quote for Call4Counsel services and products.',
        page_head('Quote', 'Request a quote', 'Tell us a little about your situation. We reply within one business day with questions or a clear proposal.')
        . '<section class="band"><div class="wrap split"><div><h2>Your quote list</h2>' . $list . '</div><div>' . $form . '</div></div></section>', ['noindex' => true]);
}

/** Validates the shared contact fields. Returns [values, errors]. */
function contact_input(): array
{
    $v = ['name' => p('name', 120), 'company' => p('company', 200), 'email' => strtolower(p('email', 191)), 'phone' => p('phone', 60), 'message' => p('message', 5000)];
    $err = [];
    if ($v['name'] === '') $err[] = 'Please enter your name.';
    if (!valid_email($v['email'])) $err[] = 'Please enter a valid email address.';
    if ($v['phone'] !== '' && !preg_match('/^[0-9 +().\-]{5,60}$/', $v['phone'])) $err[] = 'Please check the phone number.';
    if (p('consent') !== 'yes') $err[] = 'Please agree to the privacy notice so we can reply.';
    return [$v, $err];
}

function form_abuse_guard(string $bucket): ?string
{
    if (looks_like_bot()) return 'Something about that submission looked automated. Please wait a few seconds and try again.';
    if (throttle('form-' . $bucket, ip_hash(), 5, 3600)) return 'You have sent several requests recently. Please email us instead.';
    return null;
}

function save_enquiry(string $type, array $v, string $subject, string $message, array $data = [], ?string $token = null): int
{
    $client = $v['email'] ? val('SELECT client_id FROM users WHERE email = ? AND role = ?', [$v['email'], 'client']) : null;
    $id = insert('enquiries', [
        'type' => $type, 'status' => 'new', 'name' => $v['name'], 'email' => $v['email'], 'company' => $v['company'] ?? '', 'phone' => $v['phone'] ?? '',
        'subject' => mb_substr($subject, 0, 255), 'message' => $message, 'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'token' => $token, 'client_id' => $client ?: null, 'notes' => '', 'ip_hash' => ip_hash(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    notify_enquiry($id);
    return $id;
}

function quote_submit(): string
{
    [$v, $err] = contact_input();
    $v['users'] = p('users', 40);
    $v['timeline'] = p('timeline', 40);
    if ($g = form_abuse_guard('quote')) $err[] = $g;
    $items = quote_items();
    if (!$items && $v['message'] === '') $err[] = 'Add at least one item from the catalogue, or describe what you need.';
    if ($err) return page_quote($err, $v);
    $names = array_column($items, 'name');
    $msg = ($names ? "Items requested:\n- " . implode("\n- ", $names) . "\n\n" : '') . 'Users/seats: ' . ($v['users'] ?: '-') . "\nTimeline: " . ($v['timeline'] ?: '-') . "\n\n" . $v['message'];
    save_enquiry('quote', $v, 'Quote request: ' . ($names ? implode(', ', array_slice($names, 0, 3)) . (count($names) > 3 ? ' and more' : '') : 'custom'), $msg,
        ['item_ids' => array_map('intval', array_column($items, 'id')), 'users' => $v['users'], 'timeline' => $v['timeline']]);
    $_SESSION['quote'] = [];
    $_SESSION['thanks'] = 'quote';
    redirect('/thanks');
}

function page_thanks(): string
{
    $kind = $_SESSION['thanks'] ?? 'contact';
    $msg = match ($kind) {
        'quote' => ['Thank you, your quote request is in.', 'A consultant will review it and reply within one business day with questions or a proposal.'],
        'job' => ['Thank you for applying.', 'We read every application and will reply within two weeks.'],
        default => ['Thank you, your message is on its way.', 'A senior consultant will reply within one business day.'],
    };
    return render_layout('Thank you | Call4Counsel', '', page_head('', $msg[0], $msg[1])
        . '<section class="band"><div class="wrap actions"><a class="btn" href="/">Back to home</a><a class="btn ghost" href="/insights">Read our insights</a></div></section>', ['noindex' => true]);
}

function page_assessment(array $err = [], array $a = [], array $v = []): string
{
    $qs = assessment_questions();
    $n = 0;
    $html = '';
    foreach ($qs as $k => [$label, $type, $opts]) {
        $n++;
        $cur = $a[$k] ?? ($type === 'check' ? [] : '');
        $o = '';
        foreach ($opts as $val => $text) {
            $checked = $type === 'check' ? in_array((string)$val, (array)$cur, true) : ((string)$val === $cur);
            $o .= '<label class="opt"><input type="' . ($type === 'check' ? 'checkbox' : 'radio') . '" name="' . e($k) . ($type === 'check' ? '[]' : '') . '" value="' . e((string)$val) . '"'
                . ($checked ? ' checked' : '') . ($type === 'radio' ? ' required' : '') . '> <span>' . e($text) . '</span></label>';
        }
        $hint = $type === 'check' ? '<span class="help">Choose all that apply.</span>' : '';
        $html .= '<fieldset class="q card"><legend><span class="qn">' . $n . '</span>' . e($label) . '</legend>' . $hint . '<div class="opts">' . $o . '</div></fieldset>';
    }
    $errHtml = $err ? '<div class="flash err" role="alert">' . implode('<br>', array_map('e', $err)) . '</div>' : '';
    return render_layout('Free AI & process security assessment | Call4Counsel',
        'Answer a few questions about how you use AI, agents, software and IT. Get instant findings mapped to OWASP and NIST, and tailored service and product recommendations.',
        page_head('Assessment', 'Free AI & process security assessment', 'About five minutes. Tell us how your organisation uses AI, builds software and runs IT. You get instant findings and recommendations, and a senior consultant follows up with a tailored proposal.')
        . '<section class="band"><div class="wrap narrow"><form method="post" action="/assessment" class="assessment">' . csrf_field() . bot_fields() . $errHtml . $html
        . '<fieldset class="q card"><legend><span class="qn">' . ($n + 1) . '</span>Where should we send your results?</legend>' . contact_fields($v)
        . field('message', 'Anything else we should know? (optional)', $v['message'] ?? '', ['type' => 'textarea', 'attrs' => ' maxlength="3000"']) . consent_box() . '</fieldset>'
        . '<button class="btn" type="submit">See my results</button><p class="small">Your answers are stored securely and seen only by our consultants. We never share them.</p></form></div></section>');
}

function assessment_submit(): string
{
    $a = assessment_answers_from_post();
    [$v, $err] = contact_input();
    if ($g = form_abuse_guard('assessment')) $err[] = $g;
    foreach (assessment_questions() as $k => [$label, $type]) {
        if ($type === 'radio' && $a[$k] === '') $err[] = 'Please answer: ' . $label;
    }
    if ($err) return page_assessment($err, $a, $v);
    $res = assessment_analyse($a);
    $token = bin2hex(random_bytes(24));
    $crit = count(array_filter($res['findings'], fn($f) => in_array($f['sev'], ['critical', 'high'], true)));
    save_enquiry('assessment', $v, 'AI & process assessment: ' . ($v['company'] ?: $v['name']) . " ($crit critical/high findings)",
        assessment_summary_text($a, $res) . ($v['message'] ? "\n\nNotes from the client:\n" . $v['message'] : ''), ['answers' => $a], $token);
    $_SESSION['assessments'][] = $token;
    redirect('/assessment/result/' . $token);
}

function render_assessment_result(array $enq, bool $forAdmin = false): string
{
    $data = json_decode((string)$enq['data'], true) ?: [];
    $res = assessment_analyse($data['answers'] ?? []);
    $sevLabel = ['critical' => 'Critical', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];
    $f = '';
    foreach ($res['findings'] as $x) {
        $f .= '<li class="finding sev-' . e($x['sev']) . '"><span class="sev">' . e($sevLabel[$x['sev']]) . '</span><div><strong>' . e($x['title']) . '</strong><span class="post-meta"> &middot; ' . e($x['domain'])
            . ($x['ref'] ? ' &middot; ' . e($x['ref']) : '') . '</span><p>' . e($x['why']) . '</p></div></li>';
    }
    if (!$f) $f = '<li class="finding"><div><strong>No major gaps from your answers.</strong><p>A short review will confirm it. Controls are only as good as how they are run.</p></div></li>';
    $meters = '';
    foreach ($res['domains'] as $name => $pct) {
        if ($pct === null) continue;
        $level = $pct >= 75 ? 'Strong' : ($pct >= 45 ? 'Developing' : 'Early');
        $meters .= '<div class="meter"><div class="meter-top"><span>' . e($name) . '</span><strong>' . $level . ' &middot; ' . $pct . '%</strong></div><div class="bar"><i class="w' . (int)(round($pct / 5) * 5) . '"></i></div></div>';
    }
    $svc = recommend_items($res['need'], 'service', 4);
    $prod = recommend_items($res['need'], 'product', 4);
    $cards = fn($list) => $list ? '<div class="grid">' . implode('', array_map(fn($i) => item_card($i, true), $list)) . '</div>' : '<p class="small">Nothing specific from your answers.</p>';
    return '<section class="band"><div class="wrap split"><div><h2>Your security posture</h2><p class="lead">Based on the controls you told us are in place.</p>' . $meters
        . '<p class="small">This is an automated first view, not an audit. A consultant will review your answers and refine it.</p></div>'
        . '<div><h2>What we found</h2><ul class="findings">' . $f . '</ul></div></div></section>'
        . '<section class="band sea"><div class="wrap"><div class="section-head"><h2>Recommended services</h2><p class="lead">The engagements that close your most important gaps first.</p></div>' . $cards($svc) . '</div></section>'
        . '<section class="band"><div class="wrap"><div class="section-head"><h2>Recommended products</h2><p class="lead">Where a tool closes a gap faster than process alone. We will check what you already own first.</p></div>' . $cards($prod) . '</div></section>';
}

function page_assessment_result(string $token): string
{
    $enq = one("SELECT * FROM enquiries WHERE token = ? AND type = 'assessment'", [$token]);
    if (!$enq) abort();
    $u = current_user();
    $mine = in_array($token, $_SESSION['assessments'] ?? [], true)
        || ($u && ($u['role'] === 'admin' || ($u['role'] === 'client' && (int)$u['client_id'] === (int)$enq['client_id'] && $enq['client_id'])));
    if (!$mine) abort();
    no_store();
    return render_layout('Your assessment results | Call4Counsel', '',
        page_head('Assessment', 'Your AI & process assessment results', 'Thank you, ' . $enq['name'] . '. Here is our first view. A senior consultant will review your answers and reply within two business days with a tailored proposal.',
            '<div class="actions"><a class="btn" href="/quote">Request a quote for these</a><a class="btn ghost" href="/contact">Book a call</a></div>')
        . render_assessment_result($enq) . cta('Want to go deeper?', 'Our full AI & agentic process assessment tests the riskiest paths by hand and gives you a costed roadmap.'), ['noindex' => true]);
}

function page_cases(): string
{
    $cases = all('SELECT * FROM case_studies WHERE published = 1 ORDER BY sort, id');
    $html = '';
    $anySample = false;
    foreach ($cases as $c) {
        $anySample = $anySample || $c['is_sample'];
        $html .= '<article class="case" id="' . e($c['slug']) . '"><dl><dt>Sector</dt><dd>' . e($c['sector']) . '</dd><dt>Practice</dt><dd>' . e($c['practice']) . '</dd><dt>Engagement</dt><dd>' . e($c['engagement']) . '</dd></dl><div>'
            . ($c['is_sample'] ? '<span class="sample">Sample scenario</span>' : '') . '<h2 class="case-title">' . e($c['title']) . '</h2>'
            . '<h3>The challenge</h3><p>' . e($c['challenge']) . '</p><h3>What we did</h3><p>' . e($c['approach']) . '</p><h3>The outcome</h3><p>' . e($c['outcome']) . '</p></div></article>';
    }
    $note = $anySample ? '<p class="note">Entries marked "Sample scenario" show the kind of work we do and how we report it. They are not accounts of named clients. Client stories are published only with each client\'s permission.</p>' : '';
    return render_layout('Case studies | Call4Counsel', 'Examples of AI security, application security and IT engagements.',
        page_head('Case studies', 'Case studies', 'How our engagements typically unfold, from the first conversation to the handover.')
        . '<section class="band"><div class="wrap">' . $note . $html . '</div></section>' . cta('Facing something similar?', 'Tell us about it. We will share how we would approach it, free of charge.'));
}

function page_insights(): string
{
    $posts = all('SELECT * FROM posts WHERE published = 1 ORDER BY published_at DESC');
    return render_layout('Insights | Call4Counsel', 'Practical articles on AI and agentic security, application security and IT.',
        page_head('Insights', 'Insights', 'Practical guidance on AI security, application security and IT, written for the people who build and run systems.')
        . '<section class="band"><div class="wrap grid">' . implode('', array_map('post_card', $posts)) . '</div></section>' . cta());
}

function page_post(string $slug): string
{
    $p = one('SELECT * FROM posts WHERE slug = ? AND published = 1', [$slug]);
    if (!$p) abort();
    return render_layout($p['title'] . ' | Call4Counsel', (string)$p['excerpt'],
        page_head('<a href="/insights">Insights</a>', $p['title'], (string)$p['excerpt'])
        . '<article class="band"><div class="wrap prose"><div class="post-meta">' . e($p['topic']) . ' &middot; ' . e(fmt_date($p['published_at'])) . ($p['author'] ? ' &middot; ' . e($p['author']) : '') . '</div>'
        . md($p['body']) . '<p><a href="/insights">&larr; All articles</a></p></div></article>' . cta(), ['section' => '/insights']);
}

function page_careers(): string
{
    $jobs = all('SELECT * FROM jobs WHERE published = 1 ORDER BY sort, id');
    $cards = '';
    foreach ($jobs as $j) {
        $cards .= '<article class="card"><div class="post-meta">' . e($j['team']) . ' &middot; ' . e($j['location']) . '</div><h3>' . e($j['title']) . '</h3><p>' . e($j['summary'])
            . '</p><a class="more" href="/careers/' . e($j['slug']) . '">View role and apply &rarr;</a></article>';
    }
    if (!$cards) $cards = '<p class="note">No open roles right now. We still like hearing from great people: <a href="/contact">get in touch</a>.</p>';
    return render_layout('Careers | Call4Counsel', 'Join Call4Counsel in AI security, application security or IT services.',
        page_head('Careers', 'Work with us', 'Do your best technical work with interesting clients, and with colleagues who care about craft and candour.')
        . '<section class="band"><div class="wrap">' . cards([
            ['people', 'Real ownership', 'You lead your engagements and talk directly with clients.'],
            ['spark', 'Work at the frontier', 'AI and agentic systems are changing security. You will help define how to secure them.'],
            ['check', 'Room to learn', 'Time and budget for training, certifications, conferences and research.'],
            ['cloud', 'Flexible and remote', 'Remote-first working with sensible hours.'],
        ]) . '</div></section><section class="band sea"><div class="wrap"><div class="section-head"><h2>Open roles</h2></div><div class="grid">' . $cards . '</div></div></section>');
}

function page_job(string $slug, array $err = [], array $v = []): string
{
    $j = one('SELECT * FROM jobs WHERE slug = ? AND published = 1', [$slug]);
    if (!$j) abort();
    $errHtml = $err ? '<div class="flash err" role="alert">' . implode('<br>', array_map('e', $err)) . '</div>' : '';
    $form = '<form method="post" action="/careers/' . e($j['slug']) . '" class="card">' . csrf_field() . bot_fields() . $errHtml . '<h2>Apply</h2>' . contact_fields($v, false)
        . field('profile', 'LinkedIn, GitHub or portfolio link', $v['profile'] ?? '', ['type' => 'url', 'attrs' => ' maxlength="300" placeholder="https://"'])
        . field('message', 'Tell us about yourself and your experience', $v['message'] ?? '', ['type' => 'textarea', 'required' => true, 'attrs' => ' maxlength="5000"'])
        . consent_box() . '<button class="btn" type="submit">Send application</button><p class="small">We will ask for your CV by email if we move forward. Please do not include sensitive personal data.</p></form>';
    return render_layout($j['title'] . ' | Careers | Call4Counsel', (string)$j['summary'],
        page_head('<a href="/careers">Careers</a>', $j['title'], $j['team'] . ' · ' . $j['location'])
        . '<section class="band"><div class="wrap split"><div class="prose">' . md($j['body']) . '</div><div>' . $form . '</div></div></section>', ['section' => '/careers']);
}

function job_apply(string $slug): string
{
    $j = one('SELECT * FROM jobs WHERE slug = ? AND published = 1', [$slug]);
    if (!$j) abort();
    [$v, $err] = contact_input();
    $v['profile'] = p('profile', 300);
    if ($v['profile'] !== '' && !preg_match('#^https://[^\s<>"]+$#i', $v['profile'])) $err[] = 'The profile link must start with https://';
    if ($v['message'] === '') $err[] = 'Please tell us a little about yourself.';
    if ($g = form_abuse_guard('job')) $err[] = $g;
    if ($err) return page_job($slug, $err, $v);
    save_enquiry('job', $v, 'Application: ' . $j['title'], 'Profile: ' . ($v['profile'] ?: '-') . "\n\n" . $v['message'], ['job_id' => (int)$j['id'], 'profile' => $v['profile']]);
    $_SESSION['thanks'] = 'job';
    redirect('/thanks');
}

function page_contact(array $err = [], array $v = []): string
{
    $email = e(contact_email());
    $errHtml = $err ? '<div class="flash err" role="alert">' . implode('<br>', array_map('e', $err)) . '</div>' : '';
    $topics = ['' => 'Choose one', 'AI & agentic security' => 'AI & agentic security', 'Application security' => 'Application security', 'IT services' => 'IT services',
        'Products and licensing' => 'Products and licensing', 'Data engineering' => 'Data engineering', 'Something else' => 'Something else'];
    $form = '<form method="post" action="/contact" class="card">' . csrf_field() . bot_fields() . $errHtml . contact_fields($v)
        . '<div class="row">' . field('topic', 'What can we help with?', $v['topic'] ?? '', ['type' => 'select', 'required' => true, 'options' => $topics])
        . field('timeline', 'Timeline', $v['timeline'] ?? '', ['type' => 'select', 'options' => ['' => 'Choose one', 'Urgent (this week)' => 'Urgent (this week)', 'Within a month' => 'Within a month', 'This quarter' => 'This quarter', 'Just exploring' => 'Just exploring']]) . '</div>'
        . field('message', 'Tell us about your project', $v['message'] ?? '', ['type' => 'textarea', 'required' => true, 'attrs' => ' maxlength="5000"'])
        . consent_box() . '<button class="btn" type="submit">Send message</button></form>';
    return render_layout('Contact | Call4Counsel', 'Talk to Call4Counsel about AI security, application security, IT services or products.',
        page_head('Contact', 'Let us talk', 'Tell us a little about what you need. A senior consultant will reply within one business day.')
        . '<section class="band"><div class="wrap split"><div>' . $form . '</div><div><h2>What happens next</h2>'
        . steps([['We reply within one business day', 'With a few questions and times for a call.'], ['A free 30-minute call', 'We listen, ask questions and share first thoughts. An NDA is fine.'], ['A written proposal', 'Clear scope, timeline and price, usually within a few days.']])
        . '<h3 class="gap">Prefer email?</h3><p><a href="mailto:' . $email . '">' . $email . '</a></p><p class="small">For an active security incident, put "URGENT" in the subject line.</p></div></div></section>');
}

function contact_submit(): string
{
    [$v, $err] = contact_input();
    $v['topic'] = p('topic', 60);
    $v['timeline'] = p('timeline', 40);
    if ($v['topic'] === '') $err[] = 'Please choose a topic.';
    if ($v['message'] === '') $err[] = 'Please add a short message.';
    if ($g = form_abuse_guard('contact')) $err[] = $g;
    if ($err) return page_contact($err, $v);
    save_enquiry('contact', $v, 'Contact: ' . $v['topic'], 'Timeline: ' . ($v['timeline'] ?: '-') . "\n\n" . $v['message'], ['topic' => $v['topic'], 'timeline' => $v['timeline']]);
    $_SESSION['thanks'] = 'contact';
    redirect('/thanks');
}

function page_cms(string $slug): string
{
    $p = one('SELECT * FROM pages WHERE slug = ?', [$slug]);
    if (!$p) abort();
    $body = '<section class="band"><div class="wrap prose">' . md($p['body']) . '</div></section>';
    return render_layout($p['title'] . ' | Call4Counsel', (string)$p['lead'], page_head(e($p['title']), $p['title'], (string)$p['lead']) . $body . ($slug === 'about' ? cta('Let us get to know your challenge', 'A first conversation is free and carries no obligation.') : ''));
}

function sitemap(): string
{
    header('Content-Type: application/xml; charset=utf-8');
    $b = base_url();
    $urls = ['/', '/services', '/ai-security', '/catalogue', '/assessment', '/case-studies', '/insights', '/careers', '/contact', '/about', '/privacy', '/terms'];
    foreach (service_categories() as $c) if ($c['slug'] !== 'ai-agentic-security') $urls[] = '/services/' . $c['slug'];
    foreach (all('SELECT slug FROM items WHERE published = 1') as $r) $urls[] = '/catalogue/' . $r['slug'];
    foreach (all('SELECT slug FROM posts WHERE published = 1') as $r) $urls[] = '/insights/' . $r['slug'];
    foreach (all('SELECT slug FROM jobs WHERE published = 1') as $r) $urls[] = '/careers/' . $r['slug'];
    $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($urls as $u) $x .= '<url><loc>' . e($b . $u) . '</loc></url>';
    return $x . "</urlset>\n";
}
