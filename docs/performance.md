# Performance

How fast the site is, measured with `loadtest/load.mjs`, and how to repeat the measurement.

## Results for C4C-01.00.00 (27 November 2026)

**Setup:** PHP 8.3 with OPcache, the built-in server with 8 workers, SQLite, on 4 vCPUs, with the starter catalogue (36 items, 3 articles).

**Normal load:** 100 simultaneous visitors for 60 seconds. Each waits 0.25 to 0.75 s between pages, and 1 in 20 completes the assessment.

| Action | Requests | p50 | p95 | p99 | Errors |
|---|---:|---:|---:|---:|---:|
| All pages | 10,151 | 2 ms | 5 ms | 56–133 ms | 0 |
| Submit the assessment (analysis, save, email) | 455 | 6 ms | 10 ms | 29 ms | 0 |

Throughput was 160 requests a second.

**Stress:** 50 visitors with no pause for 30 seconds gave 325 requests a second with 0 errors.

The p99 outliers come from the built-in server's single accept queue, not from the application. Hostinger's LiteSpeed with MySQL serves several times the throughput of PHP's test server.

## Why it is fast

- No framework and no third-party packages. A page is one PHP file chain, a handful of indexed queries and one string render.
- Plain page views set no cookie and start no session. A session starts only for a form, a sign-in or a quote list.
- Settings are read once per request, from one query.
- Static assets are versioned (`?v=<version>`), so `.htaccess` lets browsers keep them for a year, and responses are compressed.
- Indexes cover every list the site shows: published items by kind and category, posts by date, enquiries by status and email, the audit log by action, and the email log by date.

## Repeat the measurement

```bash
node loadtest/load.mjs https://staging.example.com 100 60 500
```

The arguments are the base URL, the number of visitors, the duration in seconds and the think time in milliseconds. Set the think time to `0` for a stress run.

Run it against staging, never production. Each assessment it submits is a real enquiry that gets emailed.
