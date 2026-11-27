#!/usr/bin/env python3
"""Generates the static Call4Counsel site into OUT."""
import math, sys, pathlib

OUT = pathlib.Path(sys.argv[1] if len(sys.argv) > 1 else "/mnt/project-files/call4counsel")
DOMAIN = "https://call4counsel.com"
EMAIL = "hello@call4counsel.com"
CAREERS_EMAIL = "careers@call4counsel.com"

# ---------- drawing helpers ----------
def blob(cx, cy, R, phase=0.0, sx=1.0, sy=0.8, n=72):
    pts = []
    for i in range(n):
        t = 2 * math.pi * i / n
        r = R * (1 + 0.11 * math.sin(3 * t + phase) + 0.06 * math.cos(5 * t + phase * 1.7))
        pts.append((cx + r * math.cos(t) * sx, cy + r * math.sin(t) * sy))
    d = "M" + " L".join(f"{x:.1f},{y:.1f}" for x, y in pts) + "Z"
    return d

def hill(cx, cy, rings, step, phase, sx=1.0, sy=0.8):
    out = []
    for k in range(rings, 0, -1):
        cls = "c major" if k % 3 == 0 else "c"
        out.append(f'<path class="{cls}" d="{blob(cx, cy, k * step, phase + k * 0.25, sx, sy)}"/>')
    return "".join(out)

LOGO = ('<svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="18" fill="#0f1c2b" stroke="#2a4460"/>'
        '<path d="M8 22c4-6 9-8 13-6s7 1 11-4" fill="none" stroke="#bfe3ee" stroke-width="1.6" opacity=".7"/>'
        '<path d="M7 28c5-4 10-5 14-3s8 0 12-3" fill="none" stroke="#bfe3ee" stroke-width="1.6" opacity=".45"/>'
        '<path d="M11 30 L18 19 L24 23 L30 11" fill="none" stroke="#ff4fa8" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>'
        '<circle cx="30" cy="11" r="3.4" fill="#ff4fa8" stroke="#fff" stroke-width="1.4"/></svg>')

def hero_map():
    W, H = 700, 500
    grid = "".join(f'<line class="grid" x1="{x}" y1="0" x2="{x}" y2="{H}"/>' for x in range(100, W, 100))
    grid += "".join(f'<line class="grid" x1="0" y1="{y}" x2="{W}" y2="{y}"/>' for y in range(100, H, 100))
    water = ('<path class="water" d="M0,390 C80,370 120,420 200,430 C290,442 330,480 360,500 L0,500Z"/>'
             '<path class="water" d="M560,0 C580,40 640,60 700,52 L700,0Z"/>')
    hills = hill(250, 205, 8, 19, 0.4, 1.15, 0.78) + hill(500, 300, 7, 18, 2.1, 1.0, 0.85) + hill(620, 150, 4, 16, 1.2, 0.9, 0.8)
    route = '<path class="route" d="M95,410 C150,360 170,300 250,205 C320,130 390,250 500,300 C570,330 560,180 625,150"/>'
    wps = [  # x, y, label, sub, tag-x, tag-y
        (95, 410, "Assess", "Know where you stand", 112, 418),
        (250, 205, "Secure", "Harden apps and APIs", 92, 92),
        (500, 300, "Engineer", "Build data you trust", 410, 352),
        (625, 150, "Operate", "Run with confidence", 512, 52),
    ]
    tags = ""
    for x, y, l, s, tx, ty in wps:
        tags += (f'<circle class="pt" cx="{x}" cy="{y}" r="9"/>'
                 f'<rect class="tag" x="{tx}" y="{ty}" width="168" height="50" rx="8"/>'
                 f'<text class="lbl" x="{tx+14}" y="{ty+22}">{l}</text>'
                 f'<text class="sub" x="{tx+14}" y="{ty+40}">{s}</text>')
    compass = ('<g transform="translate(60,60)"><circle r="26" fill="none" stroke="var(--ink)" stroke-width="1.2"/>'
               '<path d="M0,-22 L6,0 L0,22 L-6,0Z" fill="var(--route)" opacity=".9"/>'
               '<text class="sub" x="-4" y="-32" style="display:block">N</text></g>')
    return (f'<svg viewBox="0 0 {W} {H}" role="img" aria-label="A survey map: a route runs from Assess, over the Secure and Engineer hills, to Operate.">'
            f'<rect class="land" width="{W}" height="{H}"/>{grid}{water}{hills}{route}{compass}{tags}</svg>')

def topo():
    return ('<svg class="topo" viewBox="0 0 600 400" aria-hidden="true">' +
            "".join(f'<path d="{blob(380, 170, k*24, k*0.3, 1.2, 0.75)}"/>' for k in range(1, 10)) + '</svg>')

ICONS = {
    "shield": '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
    "data": '<ellipse cx="12" cy="5.5" rx="7.5" ry="2.5"/><path d="M4.5 5.5v6c0 1.4 3.4 2.5 7.5 2.5s7.5-1.1 7.5-2.5v-6"/><path d="M4.5 11.5v6c0 1.4 3.4 2.5 7.5 2.5s7.5-1.1 7.5-2.5v-6"/>',
    "code": '<path d="M8 8l-4 4 4 4M16 8l4 4-4 4M13.5 5l-3 14"/>',
    "cloud": '<path d="M7 18h10a4 4 0 0 0 .5-8 6 6 0 0 0-11.4 1.6A3.3 3.3 0 0 0 7 18z"/>',
    "flow": '<circle cx="5" cy="6" r="2"/><circle cx="19" cy="6" r="2"/><circle cx="12" cy="18" r="2"/><path d="M7 6h10M6 8l5 8M18 8l-5 8"/>',
    "check": '<path d="M4 12l5 5L20 6"/>',
    "search": '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>',
    "lock": '<rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
    "map": '<path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2z"/><path d="M9 4v14M15 6v14"/>',
    "people": '<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><circle cx="17" cy="9" r="2.4"/><path d="M15.5 14.6c3 .1 5.5 2 5.5 5.4"/>',
    "gauge": '<path d="M4 17a8 8 0 1 1 16 0"/><path d="M12 17l4-6"/>',
    "doc": '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h6"/>',
}
def icon(k): return f'<div class="icon"><svg viewBox="0 0 24 24" aria-hidden="true">{ICONS[k]}</svg></div>'

NAV = [("index.html", "Home"), ("services.html", "Services"), ("case-studies.html", "Case studies"),
       ("about.html", "About"), ("insights.html", "Insights"), ("careers.html", "Careers")]

def page(file, title, desc, body, section=None):
    section = section or file
    links = "".join(f'<a href="{h}"{" aria-current=page" if h == section else ""}>{t}</a>' for h, t in NAV)
    return f'''<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{title}</title>
<meta name="description" content="{desc}">
<link rel="canonical" href="{DOMAIN}/{'' if file == 'index.html' else file}">
<meta property="og:title" content="{title}">
<meta property="og:description" content="{desc}">
<meta property="og:type" content="website">
<meta name="theme-color" content="#08111c">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Overpass:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/site.css">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="site-head">
  <div class="head-in">
    <a class="brand" href="index.html" aria-label="Call4Counsel home">{LOGO}<span>Call<b>4</b>Counsel</span></a>
    <button class="nav-toggle" aria-expanded="false" aria-controls="nav">Menu</button>
    <nav class="nav" id="nav" aria-label="Main">{links}<a class="btn" href="contact.html">Book a call</a></nav>
  </div>
</header>
<main id="main">
{body}
</main>
<footer class="site-foot">
  <div class="foot-in">
    <div>
      <a class="brand" href="index.html">{LOGO}<span>Call<b>4</b>Counsel</span></a>
      <p>Independent consulting for application security and data engineering. Senior people, clear advice, work that lasts after we leave.</p>
      <p><a href="mailto:{EMAIL}">{EMAIL}</a></p>
    </div>
    <div><h3>Services</h3><ul>
      <li><a href="application-security.html">Application security</a></li>
      <li><a href="data-engineering.html">Data engineering</a></li>
      <li><a href="services.html#engage">Ways to engage</a></li>
      <li><a href="services.html#faq">FAQ</a></li></ul></div>
    <div><h3>Company</h3><ul>
      <li><a href="about.html">About</a></li>
      <li><a href="case-studies.html">Case studies</a></li>
      <li><a href="insights.html">Insights</a></li>
      <li><a href="careers.html">Careers</a></li></ul></div>
    <div><h3>Get in touch</h3><ul>
      <li><a href="contact.html">Contact us</a></li>
      <li><a href="contact.html">Book a free consultation</a></li>
      <li><a href="privacy.html">Privacy policy</a></li></ul></div>
  </div>
  <div class="foot-base"><span>&copy; <span data-year>2026</span> Call4Counsel. All rights reserved.</span><span><a href="privacy.html">Privacy</a></span></div>
</footer>
<script src="assets/site.js"></script>
</body>
</html>
'''

def head(crumb, h1, lead):
    c = f'<div class="crumb"><a href="index.html">Home</a> / {crumb}</div>' if crumb else ""
    return f'<section class="page-head">{topo()}<div class="wrap">{c}<h1>{h1}</h1><p class="lead">{lead}</p></div></section>'

def cta(h="Not sure where to start?", p="Book a free 30-minute call. We will listen, ask good questions, and tell you honestly whether we can help."):
    return f'''<section class="band ink"><div class="wrap cta"><div><h2>{h}</h2><p class="lead">{p}</p></div>
<a class="btn" href="contact.html">Book a free consultation</a></div></section>'''

def cards(items, cls="grid"):
    out = []
    for it in items:
        ic, t, p = it[0], it[1], it[2]
        more = f'<a class="more" href="{it[3]}">{it[4]} &rarr;</a>' if len(it) > 3 else ""
        out.append(f'<article class="card">{icon(ic)}<h3>{t}</h3><p>{p}</p>{more}</article>')
    return f'<div class="{cls}">' + "".join(out) + "</div>"

def ticks(items): return '<ul class="ticks">' + "".join(f"<li>{i}</li>" for i in items) + "</ul>"

STEPS = [("Assess", "A short, focused discovery: we read the code, the pipelines and the paperwork, and talk to the people who run them."),
         ("Chart", "A prioritised plan in plain language, with effort, risk and cost for each step, so you can decide with confidence."),
         ("Build", "We work alongside your team to fix, design and deliver, in small reviewed increments."),
         ("Hand over", "Runbooks, training and documentation, so your team owns the result when we step back.")]
def steps():
    return '<ol class="steps">' + "".join(f'<li><div class="wp"><i></i>{t}</div><p>{p}</p></li>' for t, p in STEPS) + "</ol>"

ENGAGE = [("search", "Assessment", "Fixed scope and fixed fee, usually two to four weeks. You get findings, a risk-ranked roadmap and a readout for leadership."),
          ("map", "Project delivery", "A defined outcome, such as a secure CI/CD pipeline or a new data platform, delivered with your team on an agreed plan."),
          ("people", "Retained advisory", "A senior advisor on call each month for design reviews, incidents, vendor choices and second opinions.")]

# ---------- pages ----------
pages = {}

pages["index.html"] = page("index.html", "Call4Counsel | Application security and data engineering consulting",
 "Independent consultants for application security and data engineering. Secure your software, build data you can trust.", f'''
<section class="hero"><div class="hero-in">
  <div>
    <h1>Secure software. Reliable data. Clear advice.</h1>
    <p class="lead">Call4Counsel is an independent consultancy for application security and data engineering. We help growing companies find the real risks, fix them, and build data platforms their teams can depend on.</p>
    <div class="actions"><a class="btn" href="contact.html">Book a free consultation</a><a class="btn ghost" href="services.html">Explore services</a></div>
  </div>
  <div class="map">{hero_map()}</div>
</div></section>

<section class="band"><div class="wrap">
  <div class="section-head"><h2>Two practices, one standard of care</h2>
  <p class="lead">Most firms treat security and data as separate worlds. Your attackers and your auditors do not. We bring both under one roof, so your data platform is secure by design and your security work is grounded in how your systems really run.</p></div>
  <div class="grid two">
    <article class="card">{icon("shield")}<h3>Application security</h3>
      <p>From threat modelling to secure code review and DevSecOps, we help you ship software that stands up to attackers and auditors.</p>
      {ticks(["Security assessments and penetration testing", "Secure SDLC and DevSecOps pipelines", "Cloud, API and identity security", "SOC 2 and ISO 27001 readiness"])}
      <a class="more" href="application-security.html">Application security services &rarr;</a></article>
    <article class="card">{icon("data")}<h3>Data engineering</h3>
      <p>We design and build data platforms and pipelines that are fast, tested and well governed, so your reports and models are right.</p>
      {ticks(["Modern data platform architecture", "Batch and streaming pipelines", "Data quality, testing and observability", "Governance, privacy and access control"])}
      <a class="more" href="data-engineering.html">Data engineering services &rarr;</a></article>
  </div>
</div></section>

<section class="band sea"><div class="wrap">
  <div class="section-head"><h2>How an engagement runs</h2>
  <p class="lead">Every engagement follows the same simple route. You always know where we are, what comes next, and what it costs.</p></div>
  {steps()}
</div></section>

<section class="band"><div class="wrap split">
  <div><h2>Why clients call us</h2>
  <p class="lead">You get the people who sold the work doing the work. No juniors learning on your budget, no 80-page reports nobody reads.</p>
  <a class="btn ghost" href="about.html">About our approach</a></div>
  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,240px),1fr))">
    <article class="card">{icon("people")}<h3>Senior practitioners</h3><p>Hands-on consultants who have built and defended real systems.</p></article>
    <article class="card">{icon("doc")}<h3>Plain-language advice</h3><p>Findings ranked by business risk, written for engineers and executives alike.</p></article>
    <article class="card">{icon("check")}<h3>Fixes, not just findings</h3><p>We stay to help your team remediate and build, not just to report.</p></article>
    <article class="card">{icon("lock")}<h3>Vendor neutral</h3><p>We do not resell tools, so our recommendations are about what fits you.</p></article>
  </div>
</div></section>

<section class="band sea"><div class="wrap">
  <div class="section-head"><h2>Ways to work with us</h2></div>
  {cards(ENGAGE)}
</div></section>

<section class="band"><div class="wrap">
  <div class="section-head"><h2>From the field</h2><p class="lead">Examples of the kinds of problems we solve.</p></div>
  <div class="grid">
    <article class="card"><span class="sample">Sample scenario</span><h3>Fintech API security review before a funding round</h3><p>Threat model, API testing and a remediation sprint ahead of investor due diligence.</p><a class="more" href="case-studies.html#fintech">Read the scenario &rarr;</a></article>
    <article class="card"><span class="sample">Sample scenario</span><h3>Retail data platform rebuilt for trusted reporting</h3><p>Legacy nightly scripts replaced with tested, observable pipelines on a cloud warehouse.</p><a class="more" href="case-studies.html#retail">Read the scenario &rarr;</a></article>
    <article class="card"><span class="sample">Sample scenario</span><h3>Health-tech DevSecOps for SOC 2 readiness</h3><p>Security checks built into CI/CD, with the evidence auditors ask for produced automatically.</p><a class="more" href="case-studies.html#healthtech">Read the scenario &rarr;</a></article>
  </div>
</div></section>
{cta()}''')

pages["services.html"] = page("services.html", "Services | Call4Counsel",
 "Application security and data engineering consulting: assessments, delivery projects and retained advisory.", f'''
{head("Services", "Consulting services", "Two specialist practices that work together: application security and data engineering. Engage one, or both, in the way that suits your team.")}
<section class="band"><div class="wrap grid two">
  <article class="card">{icon("shield")}<h2>Application security</h2>
    <p>Find and fix the weaknesses in your software before someone else does, and build security into how your team ships.</p>
    {ticks(["Application and API security assessments", "Penetration testing for web, mobile and APIs", "Threat modelling and secure architecture review", "Secure code review", "DevSecOps and supply chain security", "Cloud security posture (AWS, Azure, GCP)", "Compliance readiness: SOC 2, ISO 27001, PCI DSS", "Developer security training"])}
    <a class="more" href="application-security.html">See application security in detail &rarr;</a></article>
  <article class="card">{icon("data")}<h2>Data engineering</h2>
    <p>Turn scattered data into a platform your business can trust, with pipelines that are tested, observable and affordable to run.</p>
    {ticks(["Data strategy and platform architecture", "Warehouse and lakehouse implementation", "Batch and real-time streaming pipelines", "Data quality, testing and observability", "Legacy and on-premises migration to cloud", "Data governance, privacy and access control", "Cost optimisation for cloud data platforms", "Analytics and machine learning enablement"])}
    <a class="more" href="data-engineering.html">See data engineering in detail &rarr;</a></article>
</div></section>
<section class="band sea"><div class="wrap">
  <div class="section-head"><h2>Where the two practices meet</h2><p class="lead">Some of the most valuable work sits between security and data.</p></div>
  {cards([("lock", "Secure data platforms", "Encryption, fine-grained access, secrets handling and audit logging designed into your warehouse and pipelines from day one."),
          ("doc", "Privacy engineering", "Practical controls for personal data: classification, masking, retention and deletion that hold up to GDPR and similar laws."),
          ("gauge", "Security analytics", "Pipelines that bring logs and security events together, so your team can detect and investigate faster.")])}
</div></section>
<section class="band" id="engage"><div class="wrap">
  <div class="section-head"><h2>Ways to engage</h2><p class="lead">Start small with an assessment, or bring us in to deliver. Every engagement has a written scope and a clear price before work starts.</p></div>
  {cards(ENGAGE)}
</div></section>
<section class="band sea"><div class="wrap"><div class="section-head"><h2>Our route, every time</h2></div>{steps()}</div></section>
<section class="band" id="faq"><div class="wrap split">
  <div><h2>Questions we often hear</h2><p class="lead">Something else on your mind? <a href="contact.html">Ask us directly.</a></p></div>
  <div>
    <details><summary>How quickly can you start?</summary><p>Usually within two to three weeks of agreeing a scope. For urgent security concerns, tell us on the contact form and we will try to respond the same business day.</p></details>
    <details><summary>Do you work with small companies?</summary><p>Yes. Many of our engagements are with start-ups and mid-sized firms that need senior expertise without hiring a full-time team.</p></details>
    <details><summary>Will you sign an NDA?</summary><p>Of course. We are happy to sign a mutual NDA before any detailed conversation about your systems.</p></details>
    <details><summary>Do you work remotely?</summary><p>Most of our work is remote, which keeps costs down. We visit on site for workshops and kick-offs when it helps.</p></details>
    <details><summary>Do you sell or resell software?</summary><p>No. We are vendor neutral and recommend whatever fits your needs, including the tools you already own.</p></details>
  </div>
</div></section>
{cta()}''')

pages["application-security.html"] = page("application-security.html", "Application security consulting | Call4Counsel",
 "Application security assessments, penetration testing, threat modelling, DevSecOps and compliance readiness.", f'''
{head('<a href="services.html">Services</a> / Application security', "Application security consulting", "Ship software that stands up to attackers and auditors. We find the weaknesses that matter, help you fix them, and build security into the way your team works.")}
<section class="band"><div class="wrap">
  <div class="section-head"><h2>What we do</h2></div>
  {cards([("search", "Security assessments and penetration testing", "Manual, expert-led testing of web apps, mobile apps and APIs, guided by the OWASP Top 10 and ASVS. Every finding comes with proof, impact and a clear fix."),
          ("map", "Threat modelling and architecture review", "Workshops with your engineers to map how data flows, where trust changes hands, and what could go wrong, before it is built."),
          ("code", "Secure code review", "Targeted review of authentication, authorisation, input handling and cryptography, combined with tuned static analysis."),
          ("flow", "DevSecOps and supply chain", "Security checks inside CI/CD: dependency and secret scanning, container and infrastructure-as-code checks, signed builds and SBOMs."),
          ("cloud", "Cloud and API security", "Review and hardening of AWS, Azure and GCP configurations, identity and access, API gateways and network boundaries."),
          ("doc", "Compliance readiness", "Practical preparation for SOC 2, ISO 27001 and PCI DSS: gap analysis, policies that match reality, and controls that produce evidence.")])}
</div></section>
<section class="band sea"><div class="wrap split">
  <div><h2>What you receive</h2><p class="lead">Deliverables your engineers can act on and your board can understand.</p></div>
  <div>{ticks(["An executive summary that explains risk in business terms", "Detailed findings with severity, evidence and step-by-step fixes", "A prioritised remediation roadmap with effort estimates", "A free re-test of fixed issues within the agreed window", "A readout session with your engineering and leadership teams"])}</div>
</div></section>
<section class="band"><div class="wrap split">
  <div><h2>Standards we work to</h2><p>We align our work with widely recognised frameworks, so results map directly to what customers and auditors expect.</p></div>
  <div><span class="pill">OWASP Top 10</span><span class="pill">OWASP ASVS</span><span class="pill">OWASP SAMM</span><span class="pill">NIST SSDF</span><span class="pill">NIST CSF</span><span class="pill">CIS Benchmarks</span><span class="pill">SOC 2</span><span class="pill">ISO 27001</span><span class="pill">PCI DSS</span><span class="pill">SLSA</span></div>
</div></section>
<section class="band sea"><div class="wrap"><div class="section-head"><h2>How it works</h2></div>{steps()}</div></section>
{cta("Have a release, audit or deal coming up?", "Tell us your deadline. We will scope a focused review that fits it.")}''', "services.html")

pages["data-engineering.html"] = page("data-engineering.html", "Data engineering consulting | Call4Counsel",
 "Data platform architecture, pipelines, data quality, governance and cloud migration consulting.", f'''
{head('<a href="services.html">Services</a> / Data engineering', "Data engineering consulting", "Build a data platform your business can trust. We design, build and tune pipelines that are tested, observable, secure and sensibly priced to run.")}
<section class="band"><div class="wrap">
  <div class="section-head"><h2>What we do</h2></div>
  {cards([("map", "Data strategy and architecture", "A clear target architecture for your data, based on your questions, team skills and budget, not the latest trend."),
          ("data", "Warehouse and lakehouse builds", "Implementation on Snowflake, Databricks, BigQuery, Redshift or Postgres, with modelling that analysts can understand."),
          ("flow", "Batch and streaming pipelines", "Reliable ingestion and transformation with tools such as Airflow, dbt, Spark and Kafka, built as tested, version-controlled code."),
          ("check", "Data quality and observability", "Automated tests, freshness checks, lineage and alerts, so problems are caught before they reach a dashboard."),
          ("cloud", "Migration and modernisation", "Move from legacy databases, scripts and on-premises systems to the cloud in safe, reversible stages."),
          ("lock", "Governance, privacy and cost", "Access control, data classification, retention and FinOps practices that keep data safe and bills predictable.")])}
</div></section>
<section class="band sea"><div class="wrap split">
  <div><h2>Signs it is time to call</h2><p class="lead">If several of these sound familiar, a short assessment will pay for itself.</p></div>
  <div>{ticks(["Two reports show different numbers for the same metric", "Pipelines break silently and someone finds out from a customer", "Your cloud data bill grows faster than your data", "Only one person understands how the data gets loaded", "You are unsure who can access personal or sensitive data", "Your data science team spends most of its time cleaning data"])}</div>
</div></section>
<section class="band"><div class="wrap split">
  <div><h2>Technology we work with</h2><p>We are vendor neutral. We will help you choose, and we are just as happy to make the most of the tools you already have.</p></div>
  <div><span class="pill">Snowflake</span><span class="pill">Databricks</span><span class="pill">BigQuery</span><span class="pill">Amazon Redshift</span><span class="pill">PostgreSQL</span><span class="pill">dbt</span><span class="pill">Apache Airflow</span><span class="pill">Apache Spark</span><span class="pill">Apache Kafka</span><span class="pill">Fivetran</span><span class="pill">Terraform</span><span class="pill">Python</span><span class="pill">SQL</span><span class="pill">AWS</span><span class="pill">Azure</span><span class="pill">Google Cloud</span></div>
</div></section>
<section class="band sea"><div class="wrap"><div class="section-head"><h2>How it works</h2></div>{steps()}</div></section>
{cta("Want a second opinion on your data platform?", "Start with a short assessment. You keep the roadmap, whether or not we build it.")}''', "services.html")

pages["about.html"] = page("about.html", "About | Call4Counsel",
 "Call4Counsel is an independent consultancy for application security and data engineering.", f'''
{head("About", "Counsel you can call on", "Call4Counsel was founded on a simple idea: companies deserve senior, honest technical advice, from people who stay to help put it into practice.")}
<section class="band"><div class="wrap split">
  <div><h2>Our story</h2>
  <p>Too many consulting engagements end with a thick report and a team that is no better equipped than before. We started Call4Counsel to work differently.</p>
  <p>We focus on two disciplines we know deeply, application security and data engineering, and on the growing ground between them. Modern companies run on software and data, and both need to be trustworthy.</p>
  <p>We work as an extension of your team: we explain our reasoning, share what we know, and leave behind people and systems that are stronger than when we arrived.</p></div>
  <div class="map" style="aspect-ratio:auto;padding:28px">
    <h3>Our mission</h3><p class="lead" style="color:var(--ink)">Help organisations build software and data they can trust, through clear advice and hands-on delivery.</p>
    <h3 style="margin-top:20px">Who we serve</h3><p>Start-ups, scale-ups and mid-sized companies in fintech, healthcare, retail, SaaS and professional services, plus larger enterprises that need specialist help.</p></div>
</div></section>
<section class="band sea"><div class="wrap">
  <div class="section-head"><h2>What we value</h2></div>
  {cards([("doc", "Candour", "We tell you what we find, including when the answer is that you do not need us."),
          ("people", "Partnership", "We work with your team, not around it, and we measure success by what you can do after we leave."),
          ("check", "Craft", "Careful, tested, documented work. We would rather do fewer things well."),
          ("lock", "Discretion", "Your systems and data are confidential. We handle them with the care we preach.")])}
</div></section>
<section class="band"><div class="wrap split">
  <div><h2>Who you will work with</h2><p class="lead">Every engagement is led by a senior consultant with hands-on experience in building and securing production systems.</p></div>
  <div>{ticks(["One named lead for your engagement, from scoping to handover", "Specialists added only when the work needs them", "Weekly check-ins and a shared tracker, so nothing is a surprise", "Background-checked consultants who work under NDA"])}</div>
</div></section>
{cta("Let us get to know your challenge", "A first conversation is free and carries no obligation.")}''')

CASES = [
 ("fintech", "Fintech: API security review before a funding round", "Financial services", "Application security", "Assessment, then a two-week remediation sprint",
  "A growing payments start-up needed to show investors that its customer-facing APIs were secure, with diligence weeks away.",
  "We ran a threat modelling workshop, then tested the public and partner APIs by hand against the OWASP API Security Top 10. We paired with the engineering team to fix the most serious issues, including authorisation gaps between customer accounts, and re-tested each fix.",
  "The team entered due diligence with a clear report, a closed list of critical findings and a roadmap for the rest. Security reviews became part of every new API design."),
 ("retail", "Retail: a data platform rebuilt for trusted reporting", "Retail and e-commerce", "Data engineering", "Assessment and a phased delivery project",
  "A multi-channel retailer had nightly scripts feeding a reporting database. Finance and marketing reported different revenue figures, and failures were found by people, not alerts.",
  "We designed a cloud warehouse with a clear modelling layer, rebuilt ingestion as version-controlled pipelines with automated tests, and agreed one definition for each key metric with the business.",
  "Reports now agree across teams, failures raise alerts before the working day starts, and the internal team owns and extends the platform."),
 ("healthtech", "Health-tech: DevSecOps for SOC 2 readiness", "Healthcare technology", "Application security", "Project delivery",
  "A health-tech SaaS company needed SOC 2 to close enterprise deals, but security checks were manual and evidence lived in screenshots.",
  "We added dependency, secret, container and infrastructure-as-code scanning to the CI/CD pipeline, set sensible gates, and automated evidence collection for change management and access reviews.",
  "Security checks run on every change without slowing releases, and the team approached the audit with evidence produced by its own tooling."),
 ("saas", "SaaS: a secure, privacy-aware analytics platform", "B2B software", "Security and data, together", "Project delivery and retained advisory",
  "A SaaS provider wanted to offer customers in-product analytics while keeping each customer's data strictly separate and meeting privacy obligations.",
  "We designed tenant isolation into the warehouse with row-level security, masked personal data in non-production environments, and added access logging and retention rules to the pipelines.",
  "The product team launched analytics features with a design their security and legal teams signed off, and kept us on for monthly design reviews."),
]
case_html = "".join(f'''<article class="case" id="{i}"><dl>
<dt>Industry</dt><dd>{ind}</dd><dt>Practice</dt><dd>{pr}</dd><dt>Engagement</dt><dd>{en}</dd></dl>
<div><span class="sample">Sample scenario</span><h2 style="font-size:28px">{t}</h2>
<h3>The challenge</h3><p>{c}</p><h3>What we did</h3><p>{w}</p><h3>The outcome</h3><p>{o}</p></div></article>''' for i, t, ind, pr, en, c, w, o in CASES)

pages["case-studies.html"] = page("case-studies.html", "Case studies | Call4Counsel",
 "Examples of application security and data engineering engagements.", f'''
{head("Case studies", "Case studies", "How our engagements typically unfold, from the first conversation to the handover.")}
<section class="band"><div class="wrap">
  <p class="note">These are sample scenarios that show the kind of work we do and how we report it. They are not accounts of named clients. Real client stories will be published here with each client's permission.</p>
  {case_html}
</div></section>
{cta("Facing something similar?", "Tell us about it. We will share how we would approach it, free of charge.")}''')

POSTS = [
 ("threat-modelling", "Threat modelling in an afternoon", "Application security", "6 min read",
  "<p>Threat modelling has a reputation for being slow and academic. It does not need to be. A useful first model takes a few hours and a whiteboard.</p>"
  "<h2>Four questions</h2><ul><li><strong>What are we building?</strong> Draw the main components, the data stores and the arrows between them.</li><li><strong>What can go wrong?</strong> Walk each arrow and ask how it could be spoofed, tampered with, read or blocked.</li><li><strong>What are we going to do about it?</strong> Pick a control for each serious threat, or accept the risk on purpose.</li><li><strong>Did we do a good job?</strong> Check the model again when the design changes.</li></ul>"
  "<h2>Keep it alive</h2><p>Store the diagram next to the code and update it in the same pull request as a design change. A small model that stays current beats a perfect one that is forgotten.</p>"),
 ("data-tests", "Five data tests every pipeline should have", "Data engineering", "5 min read",
  "<p>Most data incidents are caught by a person looking at a dashboard and thinking that number looks wrong. Simple automated tests catch the same problems hours earlier.</p>"
  "<h2>Start with these five</h2><ul><li><strong>Freshness:</strong> did new data arrive when expected?</li><li><strong>Volume:</strong> is today's row count within a sensible range of recent days?</li><li><strong>Uniqueness:</strong> are primary keys actually unique?</li><li><strong>Not null:</strong> are required fields filled in?</li><li><strong>Referential integrity:</strong> does every order point to a real customer?</li></ul>"
  "<h2>Make failures loud</h2><p>A test is only useful if a failure reaches someone who can act on it. Route alerts to the team that owns the pipeline, and stop downstream jobs from publishing bad data.</p>"),
 ("secure-warehouse", "Securing your data warehouse: a starter checklist", "Security and data", "7 min read",
  "<p>Data warehouses gather an organisation's most sensitive information in one place, which makes them valuable to analysts and attackers alike.</p>"
  "<h2>The checklist</h2><ul><li>Use single sign-on and remove shared logins.</li><li>Grant access through roles, never to individuals directly.</li><li>Classify sensitive columns and mask them by default.</li><li>Keep service credentials in a secrets manager and rotate them.</li><li>Turn on query and access logging, and review it.</li><li>Set retention rules and delete what you no longer need.</li></ul>"
  "<h2>Review regularly</h2><p>Access tends to grow over time. A quarterly review of who can see what is one of the cheapest controls you can run.</p>"),
]
post_cards = "".join(f'<article class="card"><div class="post-meta">{tag} &middot; {rt}</div><h3>{t}</h3><a class="more" href="#{i}">Read article &rarr;</a></article>' for i, t, tag, rt, _ in POSTS)
post_full = "".join(f'<article class="band{" sea" if n % 2 == 0 else ""}" id="{i}"><div class="wrap prose"><div class="post-meta">{tag} &middot; {rt}</div><h2 style="margin-top:0;font-size:clamp(28px,3.4vw,38px)">{t}</h2>{b}<p><a href="#top-insights">Back to all articles</a></p></div></article>' for n, (i, t, tag, rt, b) in enumerate(POSTS))
pages["insights.html"] = page("insights.html", "Insights | Call4Counsel",
 "Practical articles on application security and data engineering.", f'''
<div id="top-insights"></div>
{head("Insights", "Insights", "Practical guidance on application security and data engineering, written for the people who build and run systems.")}
<section class="band"><div class="wrap grid">{post_cards}</div></section>
{post_full}
{cta("Want advice on your situation?", "Our articles are general. A free call is specific to you.")}''')

ROLES = [("Senior application security consultant", "Application security", "Remote", "Lead assessments and penetration tests, run threat modelling workshops and help clients fix what you find."),
         ("Senior data engineer", "Data engineering", "Remote", "Design and build cloud data platforms and pipelines, and mentor client teams in modern practice."),
         ("DevSecOps engineer", "Application security", "Remote", "Build security into CI/CD pipelines and cloud infrastructure for clients of all sizes.")]
role_html = "".join(f'<article class="card"><div class="post-meta">{p} &middot; {l}</div><h3>{t}</h3><p>{d}</p><a class="more" href="mailto:{CAREERS_EMAIL}?subject={t.replace(" ", "%20")}">Apply by email &rarr;</a></article>' for t, p, l, d in ROLES)
pages["careers.html"] = page("careers.html", "Careers | Call4Counsel",
 "Join Call4Counsel as an application security or data engineering consultant.", f'''
{head("Careers", "Work with us", "Do your best technical work with interesting clients, and with colleagues who care about craft and candour.")}
<section class="band"><div class="wrap">
  <div class="section-head"><h2>Why Call4Counsel</h2></div>
  {cards([("people", "Real ownership", "You lead your engagements and talk directly with clients, from scoping to handover."),
          ("map", "Variety", "Work across industries and technology stacks, and in both security and data."),
          ("check", "Room to learn", "Time and budget for training, certifications, conferences and research."),
          ("cloud", "Flexible and remote", "Remote-first working with sensible hours and very little travel.")])}
</div></section>
<section class="band sea"><div class="wrap">
  <div class="section-head"><h2>Roles we hire for</h2><p class="lead">We hire throughout the year. If you do not see a perfect match, send us a note anyway.</p></div>
  <div class="grid">{role_html}</div>
  <p class="small" style="margin-top:16px">Send your CV and a few lines about yourself to {CAREERS_EMAIL}.</p>
</div></section>
<section class="band"><div class="wrap"><div class="section-head"><h2>Our hiring process</h2></div>
<ol class="steps"><li><div class="wp"><i></i>Intro call</div><p>A relaxed 30-minute conversation about you and about us.</p></li><li><div class="wp"><i></i>Technical conversation</div><p>A discussion of real problems, not trick puzzles.</p></li><li><div class="wp"><i></i>Practical exercise</div><p>A short, paid exercise close to real client work.</p></li><li><div class="wp"><i></i>Offer</div><p>A decision quickly, with clear terms.</p></li></ol>
</div></section>''')

pages["contact.html"] = page("contact.html", "Contact | Call4Counsel",
 "Book a free consultation with Call4Counsel about application security or data engineering.", f'''
{head("Contact", "Let us talk", "Tell us a little about what you need. A senior consultant will reply within one business day.")}
<section class="band"><div class="wrap split">
  <div class="card">
    <form id="contact-form" novalidate data-to="{EMAIL}" data-endpoint="">
      <div class="row">
        <div class="field"><label for="f-name">Your name</label><input id="f-name" name="name" autocomplete="name" required></div>
        <div class="field"><label for="f-company">Company</label><input id="f-company" name="company" autocomplete="organization"></div>
      </div>
      <div class="field"><label for="f-email">Work email</label><input id="f-email" name="email" type="email" autocomplete="email" required></div>
      <div class="row">
        <div class="field"><label for="f-topic">What can we help with?</label><select id="f-topic" name="topic" required><option value="">Choose one</option><option>Application security</option><option>Data engineering</option><option>Both security and data</option><option>Not sure yet</option></select></div>
        <div class="field"><label for="f-time">Timeline</label><select id="f-time" name="timeline"><option value="">Choose one</option><option>Urgent (this week)</option><option>Within a month</option><option>In the next quarter</option><option>Just exploring</option></select></div>
      </div>
      <div class="field"><label for="f-msg">Tell us about your project</label><textarea id="f-msg" name="message" required></textarea></div>
      <label class="check"><input type="checkbox" name="consent" value="yes"> <span>I agree that Call4Counsel may use these details to reply to me, as described in the <a href="privacy.html">privacy policy</a>.</span></label>
      <p class="err" id="form-err" role="alert"></p>
      <button class="btn" type="submit">Send message</button>
    </form>
    <div class="form-done" id="form-done" tabindex="-1">
      <div id="done-sent" hidden><h3>Thank you, your message is on its way.</h3><p>A senior consultant will reply within one business day.</p></div>
      <div id="done-mail"><h3>Almost done</h3><p>Your message is ready. Press the button to send it from your email app, or write to us at <strong>{EMAIL}</strong>.</p><a class="btn" id="mail-link" href="mailto:{EMAIL}">Open in my email app</a></div>
    </div>
  </div>
  <div>
    <h2>What happens next</h2>
    <ol class="steps" style="grid-template-columns:minmax(0,1fr)">
      <li><div class="wp"><i></i>We reply within one business day</div><p>With a few questions and times for a call.</p></li>
      <li><div class="wp"><i></i>A free 30-minute call</div><p>We listen, ask questions and share first thoughts. An NDA is fine.</p></li>
      <li><div class="wp"><i></i>A written proposal</div><p>Clear scope, timeline and fixed price, usually within a few days.</p></li>
    </ol>
    <h3 style="margin-top:28px">Prefer email?</h3>
    <p><a href="mailto:{EMAIL}">{EMAIL}</a></p>
    <p class="small">For an active security incident, write "URGENT" in the subject line.</p>
  </div>
</div></section>''')

pages["privacy.html"] = page("privacy.html", "Privacy policy | Call4Counsel",
 "How Call4Counsel collects, uses and protects personal information.", f'''
{head("Privacy policy", "Privacy policy", "How we collect, use and protect your personal information.")}
<section class="band"><div class="wrap prose">
  <p class="note">This policy is a starting template. Please review it with a legal adviser and adjust it to your jurisdiction and actual practices before launch.</p>
  <p class="small">Last updated: November 2026</p>
  <h2>Who we are</h2><p>Call4Counsel ("we", "us") provides application security and data engineering consulting. This policy explains how we handle personal information collected through call4counsel.com and in the course of our business.</p>
  <h2>What we collect</h2><ul><li>Details you send us, such as your name, company, email address and message.</li><li>Information you share during an engagement, handled under our contract and any NDA.</li><li>Basic technical data, such as browser type and pages visited, if analytics are enabled.</li><li>CVs and application details if you apply for a role.</li></ul>
  <h2>How we use it</h2><ul><li>To reply to your enquiry and provide our services.</li><li>To manage client relationships, contracts and invoicing.</li><li>To assess job applications.</li><li>To improve our website and meet legal obligations.</li></ul><p>We do not sell personal information, and we do not use it for unrelated marketing without your consent.</p>
  <h2>Legal basis</h2><p>Where data protection law requires one, we rely on your consent, our legitimate interest in responding to enquiries and running our business, the performance of a contract, or compliance with legal obligations.</p>
  <h2>Sharing</h2><p>We share information only with service providers who help us operate, such as email and hosting providers, under appropriate agreements, or where the law requires it.</p>
  <h2>Retention</h2><p>We keep enquiry details for up to two years and client records for as long as the law requires. Job application details are kept for up to twelve months unless you ask us to delete them sooner.</p>
  <h2>Security</h2><p>We protect information with access controls, encryption in transit and at rest where supported, and careful handling of client data. Security is our business, and we hold ourselves to the standards we recommend.</p>
  <h2>Your rights</h2><p>Depending on where you live, you may have the right to access, correct, delete or export your information, and to object to or restrict its use. To make a request, email <a href="mailto:{EMAIL}">{EMAIL}</a>.</p>
  <h2>Cookies</h2><p>This website does not set advertising cookies. If we add analytics, we will update this policy and ask for consent where required.</p>
  <h2>Changes</h2><p>We may update this policy from time to time and will change the date above when we do.</p>
  <h2>Contact</h2><p>Questions about privacy? Email <a href="mailto:{EMAIL}">{EMAIL}</a>.</p>
</div></section>''', "privacy.html")

pages["404.html"] = page("404.html", "Page not found | Call4Counsel", "Page not found.", f'''
{head("", "Off the map", "We could not find that page. It may have moved or never existed.")}
<section class="band"><div class="wrap actions"><a class="btn" href="index.html">Back to home</a><a class="btn ghost" href="contact.html">Contact us</a></div></section>''')

for name, html in pages.items():
    (OUT / name).write_text(html, encoding="utf-8")

(OUT / "assets" / "favicon.svg").write_text(f'<svg xmlns="http://www.w3.org/2000/svg" {LOGO[5:]}', encoding="utf-8")
(OUT / "robots.txt").write_text(f"User-agent: *\nAllow: /\nSitemap: {DOMAIN}/sitemap.xml\n")
urls = "".join(f"<url><loc>{DOMAIN}/{'' if n == 'index.html' else n}</loc></url>" for n in pages if n != "404.html")
(OUT / "sitemap.xml").write_text(f'<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">{urls}</urlset>\n')
print("wrote", len(pages), "pages")
