<?php
declare(strict_types=1);

/**
 * The AI & process assessment. Each answer adds weight to "need" tags; catalogue
 * items carry the same tags, so the best-matching services and products surface.
 * Findings are rule-based and reference public frameworks (OWASP Top 10 for LLM
 * Applications 2025, NIST AI RMF, ISO/IEC 42001, MITRE ATLAS).
 */
function assessment_questions(): array
{
    return [
        'size' => ['Organisation size', 'radio', [
            'micro' => '1 to 10 people', 'small' => '11 to 50', 'mid' => '51 to 250', 'large' => '251 to 1,000', 'ent' => 'More than 1,000']],
        'sector' => ['Sector', 'radio', [
            'fin' => 'Financial services or fintech', 'health' => 'Healthcare or life sciences', 'saas' => 'Software or SaaS',
            'retail' => 'Retail or e-commerce', 'pro' => 'Professional services', 'public' => 'Public sector or education', 'other' => 'Other']],
        'ai_use' => ['Where does your organisation use AI today?', 'check', [
            'chat_public' => 'A chatbot or assistant that customers use',
            'copilot' => 'Staff use AI assistants (for example Microsoft Copilot, ChatGPT or Gemini)',
            'code_ai' => 'Developers use AI coding assistants',
            'rag' => 'AI that answers from our internal documents or data (RAG)',
            'agents' => 'AI agents that call tools, APIs or other systems',
            'custom_ml' => 'We train or fine-tune our own models',
            'planning' => 'Not yet, but we are planning to',
            'none' => 'We do not use AI and have no plans']],
        'autonomy' => ['How much can your AI act on its own?', 'radio', [
            'none' => 'It only answers questions', 'suggest' => 'It drafts or suggests; a person does the action',
            'approve' => 'It takes actions after a person approves', 'auto' => 'It takes some actions with no human approval',
            'unsure' => 'Not sure']],
        'ai_data' => ['What can your AI tools see or reach?', 'check', [
            'public' => 'Only public information', 'internal' => 'Internal documents and email', 'pii' => 'Customer personal data',
            'financial' => 'Financial or payment data', 'healthdata' => 'Health data', 'source' => 'Source code',
            'prod' => 'Production systems, credentials or admin APIs', 'unsure' => 'Not sure']],
        'ai_controls' => ['Which AI controls do you already have?', 'check', [
            'policy' => 'An AI acceptable-use policy staff know about', 'inventory' => 'A list of the AI tools, models and agents in use',
            'vendor' => 'Security and privacy reviews of AI vendors', 'injection' => 'Testing for prompt injection and jailbreaks',
            'logging' => 'Logging and monitoring of prompts, outputs and agent actions', 'hitl' => 'Human approval for high-impact agent actions',
            'least' => 'Least-privilege access for AI to data and tools', 'redteam' => 'An AI red-team exercise in the last 12 months']],
        'sdlc' => ['How do you build and test software?', 'check', [
            'no_dev' => 'We do not build software ourselves', 'sast' => 'Static code analysis (SAST)', 'sca' => 'Open-source dependency scanning (SCA)',
            'secrets' => 'Secrets scanning', 'dast' => 'Dynamic or API testing (DAST)', 'threat' => 'Threat modelling for new designs',
            'pentest' => 'A penetration test in the last 12 months', 'cicd' => 'Security checks that run in CI/CD']],
        'it' => ['Which IT foundations are in place?', 'check', [
            'mfa' => 'Single sign-on and MFA for all staff', 'endpoint' => 'Managed, protected laptops (EDR)', 'backup' => 'Backups that are tested by restoring',
            'cloud' => 'Cloud configuration reviewed against a benchmark', 'm365' => 'Microsoft 365 or Google Workspace hardened',
            'helpdesk' => 'A responsive IT helpdesk', 'pam' => 'Password manager and privileged access controls', 'ir' => 'An incident response plan']],
        'compliance' => ['Which standards or laws apply to you?', 'check', [
            'soc2' => 'SOC 2', 'iso27001' => 'ISO/IEC 27001', 'iso42001' => 'ISO/IEC 42001 (AI management)', 'euaiact' => 'EU AI Act',
            'privacy' => 'GDPR, India DPDP Act or similar privacy law', 'pci' => 'PCI DSS', 'hipaa' => 'HIPAA', 'none' => 'None that we know of']],
        'priority' => ['What matters most in the next six months?', 'radio', [
            'ai_launch' => 'Launching or scaling AI safely', 'audit' => 'Passing an audit or customer security review',
            'incident' => 'Reducing breach risk after a scare', 'cost' => 'Getting more from IT and tooling spend',
            'deal' => 'Closing enterprise deals or due diligence', 'unsure' => 'Not sure yet']],
    ];
}

/** Returns sanitised answers from POST. */
function assessment_answers_from_post(): array
{
    $a = [];
    foreach (assessment_questions() as $k => [$label, $type, $opts]) {
        $keys = array_map('strval', array_keys($opts));
        if ($type === 'radio') {
            $v = p($k, 40);
            $a[$k] = in_array($v, $keys, true) ? $v : '';
        } else {
            $a[$k] = p_list($k, $keys);
        }
    }
    return $a;
}

function assessment_analyse(array $a): array
{
    $has = fn(string $q, string $v) => in_array($v, (array)($a[$q] ?? []), true);
    $is = fn(string $q, string $v) => ($a[$q] ?? '') === $v;
    $need = [];
    $add = function (array $tags, int $w) use (&$need) { foreach ($tags as $t) $need[$t] = ($need[$t] ?? 0) + $w; };
    $findings = [];
    $find = function (string $sev, string $domain, string $title, string $why, string $ref = '') use (&$findings) {
        $findings[] = compact('sev', 'domain', 'title', 'why', 'ref');
    };

    $usesAI = array_diff((array)$a['ai_use'], ['none', 'planning']) !== [];
    $agents = $has('ai_use', 'agents');
    $sensitive = array_intersect((array)$a['ai_data'], ['pii', 'financial', 'healthdata', 'source', 'prod']) !== [];
    $ctrl = (array)$a['ai_controls'];
    $builds = !$has('sdlc', 'no_dev');

    // ---- AI and agentic ----
    if ($usesAI || $has('ai_use', 'planning')) $add(['ai-assessment'], 3);
    if ($usesAI && !in_array('inventory', $ctrl, true)) {
        $find('high', 'AI governance', 'No inventory of AI tools, models and agents', 'You cannot secure or govern what you have not listed. Shadow AI use is the most common starting point for data leaks.', 'NIST AI RMF: Map');
        $add(['ai-governance', 'ai-spm'], 3);
    }
    if ($usesAI && !in_array('policy', $ctrl, true)) {
        $find('medium', 'AI governance', 'No AI acceptable-use policy', 'Staff need clear rules on which AI tools are approved and what data may be shared with them.', 'ISO/IEC 42001');
        $add(['ai-governance'], 2);
    }
    if ($agents && in_array($a['autonomy'], ['auto', 'unsure'], true)) {
        $find('critical', 'Agentic AI', 'Agents can act without human approval', 'Autonomous agents with tool access can be steered by malicious input into actions you did not intend.', 'OWASP LLM06: Excessive Agency');
        $add(['agent-security', 'ai-redteam', 'ai-guardrails'], 4);
    }
    if ($agents && !in_array('least', $ctrl, true)) {
        $find('high', 'Agentic AI', 'Agents do not run with least privilege', 'Every tool and credential an agent holds is reachable by anyone who can influence its input.', 'OWASP LLM06: Excessive Agency');
        $add(['agent-security', 'iam'], 3);
    }
    if ($agents && !in_array('hitl', $ctrl, true) && $a['autonomy'] !== 'none') {
        $add(['agent-security'], 2);
    }
    if ($has('ai_use', 'chat_public') || $has('ai_use', 'rag') || $agents) {
        if (!in_array('injection', $ctrl, true)) {
            $find('high', 'AI application security', 'No testing for prompt injection', 'Text in a web page, email or document can carry instructions that hijack your AI and leak data or trigger actions.', 'OWASP LLM01: Prompt Injection');
            $add(['ai-redteam', 'ai-guardrails', 'ai-gateway'], 3);
        }
        if (!in_array('redteam', $ctrl, true)) $add(['ai-redteam'], 2);
    }
    if ($has('ai_use', 'rag') && ($sensitive || $has('ai_data', 'internal'))) {
        $find('high', 'AI application security', 'AI answers from internal data without verified access rules', 'Retrieval can return documents a user is not allowed to see unless permissions are enforced at query time.', 'OWASP LLM08: Vector and Embedding Weaknesses');
        $add(['rag-security', 'data-protection'], 3);
    }
    if ($usesAI && $sensitive) {
        $find('high', 'Data protection', 'Sensitive data is reachable by AI', 'Personal, financial, health or production data in AI context can be disclosed through outputs, logs or the AI provider.', 'OWASP LLM02: Sensitive Information Disclosure');
        $add(['data-protection', 'ai-gateway', 'dlp'], 3);
    }
    if ($has('ai_data', 'prod')) {
        $find('critical', 'Agentic AI', 'AI can reach production systems or credentials', 'A single successful injection could become a production incident. Isolate credentials and gate every write action.', 'MITRE ATLAS');
        $add(['agent-security', 'pam', 'secrets'], 4);
    }
    if ($usesAI && !in_array('logging', $ctrl, true)) {
        $find('medium', 'AI operations', 'No logging of prompts, outputs or agent actions', 'Without an audit trail you cannot investigate an incident or show regulators how your AI behaves.', 'NIST AI RMF: Measure');
        $add(['ai-gateway', 'ai-monitoring'], 2);
    }
    if ($usesAI && !in_array('vendor', $ctrl, true)) {
        $find('medium', 'AI governance', 'AI vendors are not risk-assessed', 'Model providers and AI plug-ins process your data. Their retention, training and security terms need review.', 'OWASP LLM03: Supply Chain');
        $add(['ai-governance', 'vendor-risk'], 2);
    }
    if ($has('ai_use', 'code_ai')) {
        $add(['sast', 'sca', 'secrets'], 1);
        if ($builds && !$has('sdlc', 'sast')) $find('medium', 'Software security', 'AI-written code is not scanned', 'AI coding assistants speed up delivery and also reproduce insecure patterns and invented package names.', 'OWASP LLM09 and LLM03');
    }
    if ($has('ai_use', 'custom_ml')) $add(['ml-security', 'ai-spm'], 2);
    if ($has('ai_use', 'copilot')) $add(['m365', 'dlp'], 2);
    if ($has('compliance', 'iso42001') || $has('compliance', 'euaiact')) {
        $find('medium', 'AI governance', 'AI regulation or certification in scope', 'ISO/IEC 42001 and the EU AI Act expect a documented AI management system, risk assessments and human oversight.', 'ISO/IEC 42001, EU AI Act');
        $add(['ai-governance'], 3);
    }

    // ---- Software security ----
    if ($builds) {
        $add(['appsec'], 1);
        if (!$has('sdlc', 'sast')) $add(['sast'], 2);
        if (!$has('sdlc', 'sca')) { $add(['sca'], 2); $find('medium', 'Software security', 'Open-source dependencies are not scanned', 'Most application code is open source. Known-vulnerable packages are a leading cause of breaches.', 'OWASP Top 10: A06'); }
        if (!$has('sdlc', 'secrets')) { $add(['secrets'], 2); $find('high', 'Software security', 'No secrets scanning', 'Keys and passwords committed to code are found by attackers within minutes of exposure.', 'OWASP Top 10: A07'); }
        if (!$has('sdlc', 'dast')) $add(['dast'], 1);
        if (!$has('sdlc', 'pentest')) { $add(['pentest'], 3); $find('medium', 'Software security', 'No penetration test in the last year', 'Customers, insurers and auditors increasingly ask for a recent independent test.', 'OWASP ASVS'); }
        if (!$has('sdlc', 'threat')) $add(['threat-model'], 1);
        if (!$has('sdlc', 'cicd')) $add(['devsecops', 'aspm'], 2);
    }

    // ---- IT foundations ----
    $it = (array)$a['it'];
    if (!in_array('mfa', $it, true)) { $add(['iam'], 3); $find('critical', 'IT foundations', 'MFA is not enforced for everyone', 'Stolen passwords remain the most common way in. MFA and single sign-on block most of these attacks.', 'CIS Control 6'); }
    if (!in_array('endpoint', $it, true)) { $add(['endpoint', 'managed-it'], 2); $find('high', 'IT foundations', 'Laptops are not centrally managed and protected', 'Unmanaged devices are hard to patch, encrypt or investigate after an incident.', 'CIS Controls 1, 4 and 10'); }
    if (!in_array('backup', $it, true)) { $add(['backup'], 3); $find('high', 'IT foundations', 'Backups are not tested by restoring', 'A backup that has never been restored is a hope, not a plan, especially against ransomware.', 'CIS Control 11'); }
    if (!in_array('cloud', $it, true)) $add(['cloud', 'cspm'], 2);
    if (!in_array('m365', $it, true)) $add(['m365'], 2);
    if (!in_array('helpdesk', $it, true)) $add(['managed-it'], 2);
    if (!in_array('pam', $it, true)) $add(['pam'], 2);
    if (!in_array('ir', $it, true)) { $add(['incident'], 1); $find('medium', 'IT foundations', 'No incident response plan', 'Knowing who does what in the first hour of an incident limits damage and cost.', 'NIST CSF: Respond'); }

    // ---- Compliance and priority ----
    foreach (['soc2' => 'compliance', 'iso27001' => 'compliance', 'pci' => 'compliance', 'hipaa' => 'compliance', 'privacy' => 'data-protection'] as $c => $tag) {
        if ($has('compliance', $c)) $add([$tag], 2);
    }
    $add(match ($a['priority']) {
        'ai_launch' => ['ai-assessment', 'agent-security', 'ai-guardrails'],
        'audit' => ['compliance', 'pentest'],
        'incident' => ['incident', 'endpoint', 'iam'],
        'cost' => ['managed-it', 'licensing'],
        'deal' => ['pentest', 'compliance'],
        default => ['ai-assessment'],
    }, 2);

    // ---- Maturity scores per domain (share of expected controls in place) ----
    $score = fn(int $have, int $of) => $of ? (int)round(100 * $have / $of) : null;
    $aiCtrl = ['policy', 'inventory', 'vendor', 'injection', 'logging', 'hitl', 'least', 'redteam'];
    $domains = [
        'AI governance and security' => $usesAI ? $score(count(array_intersect($ctrl, $aiCtrl)), count($aiCtrl)) : null,
        'Software security' => $builds ? $score(count(array_intersect((array)$a['sdlc'], ['sast', 'sca', 'secrets', 'dast', 'threat', 'pentest', 'cicd'])), 7) : null,
        'IT foundations' => $score(count($it), 8),
    ];

    $order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
    usort($findings, fn($x, $y) => $order[$x['sev']] <=> $order[$y['sev']]);
    arsort($need);
    return ['need' => $need, 'findings' => $findings, 'domains' => $domains, 'uses_ai' => $usesAI, 'agents' => $agents];
}

/** Ranks published catalogue items by how well their tags match the need weights. */
function recommend_items(array $need, string $kind, int $limit): array
{
    $items = all('SELECT i.*, c.name AS cat_name FROM items i LEFT JOIN categories c ON c.id = i.category_id WHERE i.published = 1 AND i.kind = ?', [$kind]);
    foreach ($items as &$it) {
        $s = 0;
        $why = [];
        foreach (array_filter(array_map('trim', explode(',', (string)$it['tags']))) as $t) {
            if (isset($need[$t])) { $s += $need[$t]; $why[] = $t; }
        }
        $it['score'] = $s + ((int)$it['featured'] * 0.5);
        $it['matched'] = $why;
    }
    unset($it);
    $items = array_filter($items, fn($i) => $i['score'] >= 2);
    usort($items, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($items, 0, $limit);
}

function assessment_summary_text(array $answers, array $result): string
{
    $qs = assessment_questions();
    $lines = [];
    foreach ($qs as $k => [$label, $type, $opts]) {
        $v = $answers[$k] ?? '';
        $txt = is_array($v) ? implode('; ', array_map(fn($x) => $opts[$x] ?? $x, $v)) : ($opts[$v] ?? '');
        $lines[] = $label . ': ' . ($txt ?: '-');
    }
    $lines[] = '';
    $lines[] = 'Findings:';
    foreach ($result['findings'] as $f) $lines[] = '- [' . strtoupper($f['sev']) . '] ' . $f['title'];
    return implode("\n", $lines);
}
