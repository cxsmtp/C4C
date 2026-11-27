<?php
declare(strict_types=1);

/** Initial content. Everything here can be edited later in the admin panel. */
function seed_content(): void
{
    $now = now();
    $settings = [
        'notify_emails' => 'noreply@call4counsel.com',
        'contact_email' => 'hello@call4counsel.com',
        'contact_phone' => '',
        'company_legal_name' => '',
        'company_address' => '',
        'company_registration' => '',
        'quote_prefix' => 'C4C-Q',
        'default_currency' => 'USD',
    ];
    foreach ($settings as $k => $v) if (val('SELECT COUNT(*) FROM settings WHERE k = ?', [$k]) == 0) set_setting($k, $v);
    if ((int)val('SELECT COUNT(*) FROM categories') > 0) return;

    $cats = [
        ['service', 'ai-agentic-security', 'AI & agentic security', 'Assess, test and govern the AI assistants, RAG systems and autonomous agents your business now depends on.', 'bot', 1],
        ['service', 'application-security', 'Application security', 'Find and fix the weaknesses in your software, and build security into how your team ships.', 'shield', 2],
        ['service', 'it-services', 'IT services', 'Managed IT, cloud, identity and workplace services that give a growing company solid foundations.', 'server', 3],
        ['service', 'data-engineering', 'Data engineering', 'Data platforms and pipelines that are tested, observable, secure and sensibly priced to run.', 'data', 4],
        ['product', 'ai-security-platforms', 'AI security platforms', 'Tools that discover, protect and monitor AI models, assistants and agents.', 'spark', 5],
        ['product', 'application-security-tools', 'Application security tools', 'Scanners and platforms that catch vulnerabilities from the first commit to production.', 'code', 6],
        ['product', 'it-cloud-licensing', 'IT, cloud and security licensing', 'Workplace, endpoint, identity and backup licences, sourced, deployed and supported.', 'box', 7],
    ];
    $catId = [];
    foreach ($cats as [$kind, $slug, $name, $blurb, $icon, $sort]) {
        $catId[$slug] = insert('categories', compact('kind', 'slug', 'name', 'blurb', 'icon', 'sort'));
    }

    $svc = 'Fixed fee, agreed after a free scoping call';
    $lic = 'Licensed per user, asset or year. Quoted after we size your needs';
    $items = [
        // ---------- AI & agentic security ----------
        ['ai-agentic-security', 'service', 'AI & agentic process assessment', 'A structured review of how your organisation uses AI today, from staff assistants to autonomous agents, ending in a risk-ranked roadmap of the services and products that fit.',
            "We interview the people who own your AI use cases, review the tools, data flows and permissions involved, and test the riskiest paths by hand.\n\n## What we look at\n- Every AI tool, model, plug-in and agent in use, including unofficial \"shadow AI\"\n- What data each one can read and what actions it can take\n- Prompt injection, data leakage and excessive agency risks\n- Governance: policy, vendor terms, human oversight and logging\n- Fit with ISO/IEC 42001, the NIST AI Risk Management Framework and the EU AI Act where they apply\n\n## What you get\nA clear picture of where AI helps you and where it exposes you, with a short list of fixes in priority order. Where a product would close a gap faster than process alone, we say so and explain the options, including tools you already own.",
            "AI inventory and data-flow map\nRisk-ranked findings mapped to the OWASP Top 10 for LLM Applications\nGovernance gap analysis against ISO/IEC 42001 and NIST AI RMF\nRecommended services and products, with indicative effort and cost\nReadout for engineering and leadership", '2 to 3 weeks', $svc, 'ai-assessment,ai-governance,agent-security,data-protection', 1],
        ['ai-agentic-security', 'service', 'LLM and agent red teaming', 'Hands-on adversarial testing of your chatbots, copilots, RAG systems and agents, to find the ways they can be manipulated before attackers do.',
            "Our testers attack your AI the way a motivated adversary would: through direct prompts, poisoned documents, web pages and emails, tool responses and multi-step agent workflows.\n\n## Coverage\n- Direct and indirect prompt injection and jailbreaks\n- Sensitive information and system prompt disclosure\n- Excessive agency: unsafe tool use, privilege escalation and unintended actions\n- Insecure output handling that leads to XSS, SSRF or code execution\n- Retrieval weaknesses: cross-tenant leakage and poisoned embeddings\n- Cost and abuse limits\n\nEvery finding includes a reproducible example, its impact and a concrete fix. A re-test of fixed issues is included.",
            "Test plan agreed with your team\nFindings with evidence, severity and fixes\nMapping to OWASP LLM Top 10 and MITRE ATLAS techniques\nFree re-test of fixed issues", '1 to 3 weeks', $svc, 'ai-redteam,agent-security,ai-guardrails,rag-security,pentest', 1],
        ['ai-agentic-security', 'service', 'Agentic architecture and threat modelling', 'Design review for AI agents and multi-agent systems: what they can reach, who can steer them, and which guardrails belong where.',
            "Agents turn language into actions. We work with your architects to map each agent's tools, credentials, memory and inputs, then design the controls that keep it inside safe limits.\n\n## Typical outcomes\n- Least-privilege tool and credential design\n- Human approval points for high-impact actions\n- Isolation between untrusted content and instructions\n- Logging that lets you replay and explain any agent decision\n- A threat model your team keeps up to date as the system grows",
            "Threat model and trust-boundary diagram\nControl design for tools, credentials and approvals\nSecure patterns for MCP servers and tool integrations\nPrioritised backlog for your engineers", '1 to 2 weeks', $svc, 'agent-security,threat-model,iam,ai-guardrails', 0],
        ['ai-agentic-security', 'service', 'AI governance programme', 'Policies, inventories, risk assessments and oversight that let you scale AI with confidence and show customers and regulators you are in control.',
            "We build an AI management system sized to your organisation, not a binder nobody reads.\n\n## Included\n- AI acceptable-use policy and approved-tools list\n- AI system inventory and risk classification\n- AI vendor due-diligence questionnaire and review process\n- Impact assessments for higher-risk use cases\n- Roles, training and human-oversight procedures\n- Readiness for ISO/IEC 42001 certification and EU AI Act obligations where they apply",
            "AI policy set and approved-tools list\nAI inventory and risk register\nVendor assessment process\nISO/IEC 42001 gap analysis and roadmap", '4 to 8 weeks', $svc, 'ai-governance,vendor-risk,compliance', 0],
        ['ai-agentic-security', 'service', 'Secure RAG and data access review', 'Make sure your AI only retrieves what each user is allowed to see, and that sensitive data stays out of prompts, logs and providers.',
            "Retrieval-augmented generation connects AI to your documents and databases. We review ingestion, chunking, embedding stores, permission checks and output filtering, and fix the gaps.",
            "Data-flow and permission review\nTests for cross-user and cross-tenant leakage\nRedaction and data-loss prevention design\nRecommendations for vector store hardening", '1 to 2 weeks', $svc, 'rag-security,data-protection,dlp', 0],
        ['ai-agentic-security', 'service', 'AI guardrails implementation', 'We deploy and tune the guardrails, AI gateways and monitoring that your assessment called for, and train your team to run them.',
            "Product-agnostic implementation of input and output filtering, prompt-injection detection, PII redaction, rate limits and audit logging, integrated with your identity provider and SIEM.",
            "Guardrail and gateway deployment\nPolicies tuned to your use cases\nDashboards and alerting\nRunbook and team training", '2 to 6 weeks', $svc, 'ai-guardrails,ai-gateway,ai-monitoring,dlp', 0],

        // ---------- Application security ----------
        ['application-security', 'service', 'Web, mobile and API penetration testing', 'Manual, expert-led testing guided by the OWASP Top 10, API Security Top 10 and ASVS. Every finding comes with proof, impact and a clear fix.',
            "We combine careful manual testing with tuned tooling, and we explain what we find in terms your engineers and your board both understand.",
            "Executive summary in business terms\nDetailed findings with evidence and fixes\nPrioritised remediation roadmap\nFree re-test of fixed issues\nAttestation letter for customers and auditors", '1 to 3 weeks', $svc, 'pentest,appsec,dast', 1],
        ['application-security', 'service', 'Secure code review', 'Targeted review of authentication, authorisation, input handling and cryptography, combined with tuned static analysis, including code written with AI assistants.',
            "We focus on the code paths that matter most, cut false positives, and pair with your developers on the fixes.",
            "Findings with file and line references\nFix guidance and secure code examples\nTuned SAST rules for your codebase", '1 to 2 weeks', $svc, 'sast,appsec', 0],
        ['application-security', 'service', 'Threat modelling workshops', 'Workshops with your engineers to map how data flows, where trust changes hands and what could go wrong, before it is built.',
            "We use a lightweight, repeatable method your teams can keep running after we leave.",
            "Threat model and data-flow diagrams\nRisk-ranked threats with controls\nA method and template your teams can reuse", '2 to 5 days', $svc, 'threat-model,appsec', 0],
        ['application-security', 'service', 'DevSecOps pipeline build', 'Security checks inside CI/CD: SAST, dependency and secret scanning, container and infrastructure-as-code checks, signed builds and SBOMs.',
            "We choose sensible gates so security runs on every change without slowing releases, and we wire results into the tools your developers already use.",
            "Pipeline integration for scanning tools\nPolicies and break-the-build thresholds\nSBOM and signed build setup\nDeveloper onboarding guide", '2 to 6 weeks', $svc, 'devsecops,sast,sca,secrets,aspm', 0],
        ['application-security', 'service', 'Fractional application security lead', 'A senior AppSec lead on retainer: design reviews, triage, vendor choices, security champions and a programme that grows with you.',
            "Ideal for companies that need senior security leadership without a full-time hire.",
            "Monthly hours on demand\nQuarterly programme review and roadmap\nSecurity champions programme\nAnswers to customer security questionnaires", 'Monthly retainer', 'Monthly fee by agreed hours', 'appsec,aspm,compliance,ai-governance', 0],
        ['application-security', 'service', 'Compliance readiness: SOC 2, ISO 27001, PCI DSS', 'Practical preparation for audits: gap analysis, policies that match reality and controls that produce evidence automatically.',
            "We prepare you for the auditor, we do not audit you. That keeps our advice independent and practical.",
            "Gap analysis and remediation plan\nPolicy and procedure set\nEvidence automation\nAudit-day support", '6 to 12 weeks', $svc, 'compliance', 0],

        // ---------- IT services ----------
        ['it-services', 'service', 'Managed IT and helpdesk', 'Responsive support for your people and their devices, with proactive patching, monitoring and clear monthly reporting.',
            "A single, accountable team for day-to-day IT: user onboarding and offboarding, device management, patching and a helpdesk your staff actually like.",
            "Helpdesk with agreed response times\nDevice management and patching\nOnboarding and offboarding\nMonthly service report", 'Monthly service', 'Per user per month', 'managed-it,endpoint,licensing', 1],
        ['it-services', 'service', 'Cloud migration and management', 'Plan, migrate and run workloads on AWS, Azure or Google Cloud, with security and cost control built in from day one.',
            "We move you in safe, reversible stages, and leave you with infrastructure as code, monitoring and a sensible bill.",
            "Migration plan and landing zone\nInfrastructure as code\nCost and posture reviews\nOptional ongoing management", '4 to 12 weeks', $svc, 'cloud,cspm,managed-it', 0],
        ['it-services', 'service', 'Microsoft 365 and Google Workspace hardening', 'Set up or tighten your workplace platform: identity, MFA, email security, data-loss prevention and safe settings for AI assistants such as Copilot.',
            "Most workplace breaches start with weak defaults. We apply a recognised baseline and explain every change.",
            "Baseline configuration review\nMFA, conditional access and email security\nDLP and sharing controls\nCopilot and Gemini readiness checks", '1 to 3 weeks', $svc, 'm365,iam,dlp', 0],
        ['it-services', 'service', 'Identity and access management', 'Single sign-on, MFA, joiner-mover-leaver processes and privileged access, for people and for AI agents and service accounts.',
            "Identity is the new perimeter. We design and implement access that is easy for staff and hard for attackers.",
            "SSO and MFA rollout\nAccess reviews and role design\nPrivileged access controls\nNon-human and agent identity design", '2 to 6 weeks', $svc, 'iam,pam', 0],
        ['it-services', 'service', 'Backup and disaster recovery', 'Backups you can trust: immutable copies, tested restores and a recovery plan for ransomware and outages.',
            "We design the backup strategy, set up the tooling and run restore tests so you know your recovery time before you need it.",
            "Backup design and setup\nImmutable and off-site copies\nRestore tests and recovery runbook", '1 to 4 weeks', $svc, 'backup,incident', 0],
        ['it-services', 'service', 'Incident response readiness', 'A plan, contacts and rehearsals so your team knows exactly what to do in the first hour of a security incident.',
            "We write a practical plan and run a tabletop exercise based on scenarios that fit your business, including AI-related incidents.",
            "Incident response plan and playbooks\nTabletop exercise\nContact and escalation tree", '1 to 3 weeks', $svc, 'incident', 0],

        // ---------- Data engineering ----------
        ['data-engineering', 'service', 'Data platform architecture and build', 'A clear target architecture and a warehouse or lakehouse your analysts and AI projects can trust.',
            "Implementation on Snowflake, Databricks, BigQuery, Redshift or PostgreSQL, with tested, version-controlled pipelines using tools such as dbt, Airflow, Spark and Kafka.",
            "Target architecture\nWarehouse or lakehouse build\nTested pipelines with monitoring\nHandover and training", '4 to 12 weeks', $svc, 'data-platform,data-protection', 0],
        ['data-engineering', 'service', 'Data quality, governance and privacy', 'Automated data tests, lineage, access control, classification and masking that keep data accurate and safe for analytics and AI.',
            "Data that feeds AI must be correct and appropriately protected. We put the checks and controls in place.",
            "Data tests and alerting\nClassification and masking\nAccess model and audit logging", '2 to 6 weeks', $svc, 'data-protection,dlp,data-platform', 0],

        // ---------- Products: AI security ----------
        ['ai-security-platforms', 'product', 'AI gateway and LLM firewall', 'A control point between your users, apps and AI models that blocks prompt injection, redacts sensitive data and logs every interaction.',
            "We help you choose between leading commercial and open-source options, then license, deploy and tune it for your use cases.\n\nEvery product we supply can be bundled with deployment and support from our team.",
            "Product selection against your requirements\nLicensing at competitive rates\nDeployment and policy tuning\nOngoing support option", 'Annual subscription', $lic, 'ai-gateway,ai-guardrails,dlp,ai-monitoring', 1],
        ['ai-security-platforms', 'product', 'AI security posture management (AI-SPM)', 'Continuously discover the models, agents, AI services and shadow AI across your cloud and SaaS, and flag risky configurations.',
            "An AI inventory that keeps itself current is the foundation of AI governance. We size, license and deploy a platform that fits your environment.",
            "Discovery of AI assets and shadow AI\nRisk scoring and misconfiguration alerts\nIntegration with your cloud and SIEM", 'Annual subscription', $lic, 'ai-spm,ai-governance,ml-security', 0],
        ['ai-security-platforms', 'product', 'Automated AI red-teaming tools', 'Continuous automated testing of your AI applications for jailbreaks, prompt injection and data leakage, run on every release.',
            "Complements our manual red teaming with regression testing you can run in CI/CD.",
            "Tool licensing and setup\nTest suites tailored to your apps\nCI/CD integration", 'Annual subscription', $lic, 'ai-redteam,devsecops', 0],
        ['ai-security-platforms', 'product', 'AI data-loss prevention for staff', 'Browser and endpoint controls that stop sensitive data being pasted into public AI tools, while allowing approved ones.',
            "A practical way to enforce your AI acceptable-use policy without banning AI outright.",
            "Licensing and rollout\nPolicy aligned to your approved-tools list\nUsage reporting", 'Annual subscription', $lic, 'dlp,ai-governance,data-protection', 0],

        // ---------- Products: AppSec tools ----------
        ['application-security-tools', 'product', 'Static application security testing (SAST)', 'Scan source code, including AI-generated code, for vulnerabilities in the IDE, in pull requests and in CI/CD.',
            "We match the scanner to your languages and workflow, license it, and tune rules so developers trust the results.",
            "Product selection and licensing\nIDE and pipeline integration\nRule tuning and developer onboarding", 'Annual subscription', $lic, 'sast,devsecops,appsec', 1],
        ['application-security-tools', 'product', 'Software composition analysis and SBOM', 'Find vulnerable and risky open-source packages, including invented package names suggested by AI, and produce SBOMs for customers.',
            "Licence and dependency risk in one place, with automated fix pull requests.",
            "Licensing and setup\nPolicy for licences and vulnerabilities\nSBOM generation", 'Annual subscription', $lic, 'sca,devsecops', 0],
        ['application-security-tools', 'product', 'Secrets detection', 'Stop API keys, tokens and passwords reaching your repositories, pipelines and AI prompts, and find the ones already there.',
            "Pre-commit, repository and pipeline scanning with clear remediation workflows.",
            "Licensing and rollout\nHistorical scan and clean-up plan\nPre-commit and CI integration", 'Annual subscription', $lic, 'secrets,devsecops', 0],
        ['application-security-tools', 'product', 'DAST and API security testing', 'Automated testing of running web apps and APIs for vulnerabilities, scheduled or on every deployment.',
            "Authenticated scanning that understands modern single-page apps and APIs.",
            "Licensing and setup\nAuthenticated scan configuration\nPipeline scheduling", 'Annual subscription', $lic, 'dast,appsec', 0],
        ['application-security-tools', 'product', 'Application security posture management (ASPM)', 'One view of risk across all your scanners, with deduplication, prioritisation and ownership.',
            "Pulls findings from SAST, SCA, secrets, DAST and cloud tools into one prioritised queue.",
            "Licensing and integration\nPrioritisation policy\nDashboards for teams and leadership", 'Annual subscription', $lic, 'aspm,devsecops', 0],
        ['application-security-tools', 'product', 'Container and infrastructure-as-code scanning', 'Catch vulnerable images and risky cloud configuration in Terraform, Kubernetes and Dockerfiles before deployment.',
            "Shift cloud security left with checks in the pipeline.",
            "Licensing and integration\nBaseline policies\nDeveloper guidance", 'Annual subscription', $lic, 'cspm,devsecops,cloud', 0],

        // ---------- Products: IT licensing ----------
        ['it-cloud-licensing', 'product', 'Microsoft 365 and Copilot licences', 'The right Microsoft 365 plans and Copilot add-ons for each role, with secure configuration included.',
            "We review what you pay for today, remove waste and set up the security features already in your plan.",
            "Licence review and right-sizing\nProcurement and renewal management\nSecure baseline configuration", 'Monthly or annual', $lic, 'm365,licensing,managed-it', 0],
        ['it-cloud-licensing', 'product', 'Google Workspace licences', 'Google Workspace editions and Gemini add-ons, set up with sensible security and sharing defaults.',
            "Migration help available from other email and file platforms.",
            "Licensing and migration support\nSecurity baseline\nAdmin training", 'Monthly or annual', $lic, 'm365,licensing', 0],
        ['it-cloud-licensing', 'product', 'Endpoint detection and response (EDR)', 'Next-generation protection for laptops and servers, with optional 24x7 managed detection and response.',
            "We deploy, tune and, if you wish, monitor it for you.",
            "Licensing and rollout\nPolicy tuning\nOptional managed monitoring", 'Annual subscription', $lic, 'endpoint,incident', 0],
        ['it-cloud-licensing', 'product', 'Backup and recovery software', 'Immutable backup for Microsoft 365, Google Workspace, endpoints, servers and cloud workloads.',
            "Paired with restore testing so you know it works.",
            "Licensing and setup\nRetention policy design\nRestore testing", 'Annual subscription', $lic, 'backup', 0],
        ['it-cloud-licensing', 'product', 'Password manager and privileged access', 'Business password management and privileged access controls for admins, service accounts and AI agents.',
            "Reduce shared passwords and standing admin rights.",
            "Licensing and rollout\nVault and access design\nStaff onboarding", 'Annual subscription', $lic, 'pam,iam,secrets', 0],
        ['it-cloud-licensing', 'product', 'Cloud security posture management (CSPM/CNAPP)', 'Continuous checks of AWS, Azure and Google Cloud against security benchmarks, with prioritised fixes.',
            "Includes workload, identity and AI-service visibility on supported platforms.",
            "Licensing and onboarding\nBenchmark policies\nFix prioritisation", 'Annual subscription', $lic, 'cspm,cloud,ai-spm', 0],
        ['it-cloud-licensing', 'product', 'Identity provider and MFA', 'Single sign-on and phishing-resistant MFA for every app your people use.',
            "Includes conditional access design and rollout support.",
            "Licensing and rollout\nApp integrations\nConditional access policies", 'Annual subscription', $lic, 'iam', 0],
    ];
    $sort = 0;
    foreach ($items as [$cat, $kind, $name, $summary, $body, $deliv, $dur, $price, $tags, $feat]) {
        insert('items', [
            'category_id' => $catId[$cat], 'kind' => $kind, 'slug' => slugify($name), 'name' => $name, 'summary' => $summary,
            'body' => $body, 'deliverables' => $deliv, 'duration' => $dur, 'price_note' => $price, 'vendor' => '',
            'tags' => $tags, 'featured' => $feat, 'published' => 1, 'sort' => $sort++, 'updated_at' => $now,
        ]);
    }

    $cases = [
        ['agentic-support', 'Agentic AI: securing a customer-support agent before launch', 'Software and SaaS', 'AI & agentic security', 'Assessment, red teaming and guardrails',
            'A SaaS company had built an AI agent that could read support tickets, look up accounts and issue refunds. Launch was a month away and nobody had tested what a hostile customer could make it do.',
            'We mapped the agent\'s tools and credentials, then red-teamed it with direct and indirect prompt injection hidden in tickets and attachments. We narrowed its permissions, added human approval above a refund threshold, and put an AI gateway in front of the model to filter inputs and log every action.',
            'The agent launched on schedule with least-privilege access, an approval step for high-impact actions and a full audit trail. Automated red-team tests now run on every release.'],
        ['fintech-api', 'Fintech: API security review before a funding round', 'Financial services', 'Application security', 'Assessment and a two-week remediation sprint',
            'A growing payments start-up needed to show investors that its customer-facing APIs were secure, with due diligence weeks away.',
            'We ran a threat modelling workshop, then tested the public and partner APIs by hand against the OWASP API Security Top 10. We paired with the engineering team to fix the most serious issues, including authorisation gaps between customer accounts, and re-tested each fix.',
            'The team entered due diligence with a clear report, a closed list of critical findings and a roadmap for the rest. Security reviews became part of every new API design.'],
        ['copilot-rollout', 'Professional services: a safe Microsoft 365 Copilot rollout', 'Professional services', 'IT services and AI governance', 'Workplace hardening, licensing and policy',
            'A 200-person firm wanted Copilot for everyone, but years of over-shared files meant the assistant could surface salaries and client documents to the wrong people.',
            'We reviewed sharing and permissions, applied sensitivity labels and data-loss prevention, right-sized the Microsoft 365 licences, and wrote a short AI acceptable-use policy with training for staff.',
            'Copilot rolled out in phases with sensitive content protected, licence spend went down after the review, and the firm could answer client questions about how it uses AI.'],
        ['healthtech-devsecops', 'Health-tech: DevSecOps for SOC 2 readiness', 'Healthcare technology', 'Application security', 'Project delivery and tooling',
            'A health-tech SaaS company needed SOC 2 to close enterprise deals, but security checks were manual and evidence lived in screenshots.',
            'We selected and licensed SAST, dependency and secrets scanning, added them with container and infrastructure-as-code checks to the CI/CD pipeline, and automated evidence collection for change management and access reviews.',
            'Security checks run on every change without slowing releases, and the team approached the audit with evidence produced by its own tooling.'],
    ];
    foreach ($cases as $i => [$slug, $title, $sector, $practice, $engagement, $challenge, $approach, $outcome]) {
        insert('case_studies', compact('slug', 'title', 'sector', 'practice', 'engagement', 'challenge', 'approach', 'outcome') + ['is_sample' => 1, 'published' => 1, 'sort' => $i]);
    }

    $posts = [
        ['owasp-llm-top-10-for-leaders', 'The OWASP Top 10 for LLM applications, for busy leaders', 'AI security',
            'Ten risks, explained in plain language, with the one question to ask your team about each.',
            "The OWASP Top 10 for Large Language Model Applications is the most widely used list of AI application risks. Here is what each one means for your business.\n\n## The ten risks\n1. **Prompt injection.** Text from users, web pages or documents can override your instructions. *Ask: what can untrusted text make our AI do?*\n2. **Sensitive information disclosure.** AI can repeat data it should not. *Ask: what confidential data can our AI see?*\n3. **Supply chain.** Models, plug-ins and datasets come from third parties. *Ask: who reviewed our AI vendors?*\n4. **Data and model poisoning.** Tampered training or retrieval data skews answers. *Ask: who can change the data our AI learns from?*\n5. **Improper output handling.** AI output passed straight into code, browsers or databases becomes an attack. *Ask: do we treat AI output as untrusted input?*\n6. **Excessive agency.** Agents with too many permissions do too much. *Ask: what is the worst action our agent could take?*\n7. **System prompt leakage.** Secrets in prompts get exposed. *Ask: are there credentials in our prompts?*\n8. **Vector and embedding weaknesses.** Retrieval returns documents users should not see. *Ask: are permissions enforced at retrieval time?*\n9. **Misinformation.** Confident, wrong answers. *Ask: where does a person check the output?*\n10. **Unbounded consumption.** Abuse drives up costs or takes services down. *Ask: what are our usage limits?*\n\n## Where to start\nList every AI tool and agent you use, note what data and actions each can reach, and test the riskiest ones first. Our [free AI and process assessment](/assessment) walks you through exactly that."],
        ['securing-ai-agents', 'Five controls every AI agent needs before it touches production', 'Agentic AI',
            'Agents turn language into actions. These five controls keep those actions inside safe limits.',
            "AI agents can read email, call APIs, change records and spend money. That is their value, and their risk.\n\n## The five controls\n- **Least privilege.** Give each agent only the tools and data it needs, with its own identity and short-lived credentials.\n- **Human approval for high-impact actions.** Payments, deletions, permission changes and external messages should wait for a person.\n- **Separate instructions from content.** Treat everything the agent reads, including tool results, as untrusted data that cannot change its goals.\n- **Full audit trail.** Log prompts, tool calls, inputs and outputs so you can replay and explain any decision.\n- **Limits.** Cap spend, rate, scope and run time, and make it easy to switch the agent off.\n\n## Test it like an attacker would\nPut malicious instructions in the places your agent reads: tickets, documents, web pages and tool responses. If it follows them, you have found your first fix."],
        ['threat-modelling-in-an-afternoon', 'Threat modelling in an afternoon', 'Application security',
            'A useful first threat model takes a few hours and a whiteboard. Here is how.',
            "Threat modelling has a reputation for being slow and academic. It does not need to be.\n\n## Four questions\n- **What are we building?** Draw the main components, the data stores and the arrows between them.\n- **What can go wrong?** Walk each arrow and ask how it could be spoofed, tampered with, read or blocked.\n- **What are we going to do about it?** Pick a control for each serious threat, or accept the risk on purpose.\n- **Did we do a good job?** Check the model against what was actually built.\n\n## Keep it alive\nStore the diagram next to the code and update it in the same pull request as a design change. A small model that stays current beats a perfect one that is forgotten."],
    ];
    foreach ($posts as $i => [$slug, $title, $topic, $excerpt, $body]) {
        insert('posts', compact('slug', 'title', 'topic', 'excerpt', 'body') + ['author' => 'Call4Counsel', 'published' => 1, 'published_at' => gmdate('Y-m-d H:i:s', time() - $i * 86400 * 9), 'updated_at' => $now]);
    }

    $jobs = [
        ['AI security consultant', 'AI & agentic security', 'Remote', 'Red-team LLM applications and agents, run AI threat modelling and help clients build AI governance.'],
        ['Senior application security consultant', 'Application security', 'Remote', 'Lead assessments and penetration tests, run threat modelling workshops and help clients fix what you find.'],
        ['IT and cloud engineer', 'IT services', 'Remote or hybrid', 'Run Microsoft 365, identity, endpoint and cloud environments for growing companies.'],
    ];
    foreach ($jobs as $i => [$title, $team, $location, $summary]) {
        insert('jobs', ['slug' => slugify($title), 'title' => $title, 'team' => $team, 'location' => $location, 'summary' => $summary,
            'body' => "## What you will do\n$summary\n\n## What we look for\n- Hands-on experience in the field and the judgement that comes with it\n- Clear writing and calm communication with clients\n- Curiosity about how new technology, especially AI, changes risk", 'published' => 1, 'sort' => $i]);
    }

    $pages = [
        ['about', 'Counsel you can call on', 'Call4Counsel gives organisations senior, honest advice on application security, AI and IT, and the products and hands-on help to put it into practice.',
            "## Our story\nToo many engagements end with a thick report and a team that is no better equipped than before. We started Call4Counsel to work differently.\n\nWe focus on application security, the fast-moving field of AI and agentic security, and the IT foundations everything else depends on. Modern companies run on software, data and now AI, and all three need to be trustworthy.\n\n## Advice first, products second\nWe also supply the security and IT products our clients need, so you can get advice, licences, deployment and support from one accountable team. Our recommendations always start from your needs. We will tell you when a tool you already own will do, and we are open about any product we supply.\n\n## What we value\n- **Candour.** We tell you what we find, including when the answer is that you do not need us.\n- **Partnership.** We work with your team, not around it.\n- **Craft.** Careful, tested, documented work.\n- **Discretion.** Your systems and data are confidential, and we handle them with the care we recommend to others.\n\n## Who you will work with\nEvery engagement is led by a senior practitioner, with one named lead from scoping to handover. Consultants work under NDA, and we are happy to sign yours."],
        ['privacy', 'Privacy policy', 'How we collect, use and protect your personal information.',
            "*Last updated: November 2026. Please review this policy with a legal adviser for your jurisdiction before relying on it.*\n\n## Who we are\nCall4Counsel (\"we\", \"us\") provides application security, AI security and IT services and supplies related products. This policy explains how we handle personal information collected through call4counsel.com and in the course of our business.\n\n## What we collect\n- Details you send through our forms: name, company, email, phone and your message or assessment answers.\n- Information shared during an engagement, handled under our contract and any NDA.\n- Account details for client portal users.\n- A keyed hash of your IP address, used only to prevent abuse of our forms. We do not store raw IP addresses with enquiries.\n\n## How we use it\n- To reply to your enquiry, prepare quotes and provide our services and products.\n- To manage client relationships, contracts and invoicing.\n- To assess job applications.\n- To keep our website secure.\n\nWe do not sell personal information and do not use it for unrelated marketing without your consent.\n\n## Cookies\nWe use one essential cookie to keep your session and protect forms. We do not use advertising or tracking cookies.\n\n## Sharing\nWe share information only with providers who help us operate, such as our hosting and email providers, with vendors when you ask us to supply their product, or where the law requires it.\n\n## Retention\nWe keep enquiries for up to two years, client records for as long as the law requires, and job applications for up to twelve months.\n\n## Your rights\nDepending on where you live, you may have the right to access, correct, delete or export your information, and to object to or restrict its use. To make a request, email us at the address in the footer.\n\n## Changes\nWe will update the date above when this policy changes."],
        ['terms', 'Terms of business', 'The basis on which we provide services and supply products.',
            "*This is a summary. Each engagement is governed by a written proposal or order form, which takes precedence. Please review these terms with a legal adviser before relying on them.*\n\n## Proposals and quotes\nQuotes are valid for the period shown on them. Work starts once a proposal or order is accepted in writing or through the client portal.\n\n## Services\nWe perform services with reasonable skill and care, within the agreed scope. Security testing is carried out only on systems you own or are authorised to have tested, under written rules of engagement.\n\n## Products and licences\nThird-party products are supplied under the vendor's own licence terms, which we provide with the quote. Licence fees are generally non-refundable once ordered.\n\n## Confidentiality\nWe keep your information confidential and are happy to sign a mutual NDA.\n\n## Payment\nInvoices are payable within the period stated on them."],
    ];
    foreach ($pages as [$slug, $title, $lead, $body]) {
        insert('pages', compact('slug', 'title', 'lead', 'body') + ['updated_at' => $now]);
    }
}
