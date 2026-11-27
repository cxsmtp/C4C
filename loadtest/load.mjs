// Load test. No dependencies: Node 18+.
//   node loadtest/load.mjs [base-url] [users] [seconds] [think-ms]
// Simulates visitors browsing the site and, for 1 in 20, completing the assessment.
// Reports throughput, error count and p50/p95/p99 latency per action.
const [base = 'http://127.0.0.1:8099', users = '50', seconds = '60', think = '500'] = process.argv.slice(2);
const U = +users, END = Date.now() + +seconds * 1000, THINK = +think;
const lat = {}, errors = {};
let total = 0;
const rec = (name, ms, ok) => { (lat[name] ||= []).push(ms); total++; if (!ok) errors[name] = (errors[name] || 0) + 1; };
const pages = ['/', '/services', '/ai-security', '/catalogue', '/catalogue?q=agent', '/catalogue/llm-and-agent-red-teaming', '/case-studies', '/insights', '/insights/securing-ai-agents', '/about', '/contact', '/assessment'];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function hit(name, url, opts = {}) {
  const t = performance.now();
  try {
    const r = await fetch(base + url, { redirect: 'manual', ...opts });
    const body = await r.text();
    rec(name, performance.now() - t, r.status < 400);
    return { r, body };
  } catch {
    rec(name, performance.now() - t, false);
    return null;
  }
}

async function visitor(i) {
  while (Date.now() < END) {
    const p = pages[Math.floor(Math.random() * pages.length)];
    await hit('GET ' + p.split('?')[0].replace(/\/[^/]+$/, (m) => (p.split('/').length > 2 ? '/:slug' : m)), p);
    if (Math.random() < 0.05) {
      const res = await hit('GET /assessment (form)', '/assessment');
      if (res) {
        const cookie = (res.r.headers.get('set-cookie') || '').split(';')[0];
        const csrf = (res.body.match(/name="_csrf" value="([a-f0-9]+)"/) || [])[1] || '';
        await sleep(3100); // forms filled faster than 3 s are treated as bots
        const f = new URLSearchParams({ _csrf: csrf, size: 'mid', sector: 'saas', autonomy: 'approve', priority: 'ai_launch', name: `Load ${i}`, email: `load${i}@example.com`, consent: 'yes' });
        for (const [k, v] of [['ai_use[]', 'agents'], ['ai_use[]', 'rag'], ['ai_data[]', 'pii'], ['it[]', 'mfa']]) f.append(k, v);
        await hit('POST /assessment', '/assessment', { method: 'POST', body: f, headers: { cookie, 'content-type': 'application/x-www-form-urlencoded' } });
      }
    }
    if (THINK) await sleep(THINK * (0.5 + Math.random()));
  }
}

const t0 = Date.now();
await Promise.all(Array.from({ length: U }, (_, i) => visitor(i)));
const secs = (Date.now() - t0) / 1000;
const pct = (a, p) => a[Math.min(a.length - 1, Math.floor((a.length * p) / 100))];
console.log(`${U} users, ${secs.toFixed(0)} s, think ${THINK} ms: ${total} requests, ${(total / secs).toFixed(1)} req/s, errors ${Object.values(errors).reduce((a, b) => a + b, 0)}`);
console.log('action'.padEnd(34), 'count'.padStart(6), 'p50'.padStart(7), 'p95'.padStart(7), 'p99'.padStart(7), 'errors'.padStart(7));
for (const [k, a] of Object.entries(lat).sort()) {
  a.sort((x, y) => x - y);
  console.log(k.padEnd(34), String(a.length).padStart(6), pct(a, 50).toFixed(0).padStart(6) + 'ms', pct(a, 95).toFixed(0).padStart(6) + 'ms', pct(a, 99).toFixed(0).padStart(6) + 'ms', String(errors[k] || 0).padStart(7));
}
process.exit(Object.keys(errors).length ? 1 : 0);
