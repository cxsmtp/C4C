#!/usr/bin/env bash
# Installs into an empty database through /install, then requests every public page.
# Usage: tests/smoke.sh http://127.0.0.1:8099
set -euo pipefail
B=${1:-http://127.0.0.1:8099}
INSTALL_KEY=${INSTALL_KEY:-ci-install}
# A fresh random password for each run: no credential is kept in the repository.
PW="smoke-$(od -An -N12 -tx1 /dev/urandom | tr -d ' \n')"
J=$(mktemp)
tok() { curl -s -c "$J" -b "$J" "$B$1" | grep -o 'name="_csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
T=$(tok /install)
code=$(curl -s -c "$J" -b "$J" -o /dev/null -w '%{http_code}' -H "Origin: $B" --data-urlencode "_csrf=$T" --data-urlencode "install_key=$INSTALL_KEY" \
  --data-urlencode name=CI --data-urlencode email=ci@example.com --data-urlencode "password=$PW" --data-urlencode "confirm=$PW" "$B/install")
[ "$code" = 303 ] || { echo "install failed: $code"; exit 1; }
fail=0
for u in / /services /services/application-security /services/it-services /services/data-engineering /ai-security /catalogue "/catalogue?kind=product" \
  "/catalogue?q=agent" /catalogue/llm-and-agent-red-teaming /quote /assessment /case-studies /insights /insights/securing-ai-agents /careers \
  /careers/ai-security-consultant /contact /about /privacy /terms /sitemap.xml /health /.well-known/security.txt /portal/login /admin/login; do
  c=$(curl -s -o /dev/null -w '%{http_code}' "$B$u")
  [ "$c" = 200 ] || { echo "FAIL $c $u"; fail=1; }
done
for u in /nope /install; do
  c=$(curl -s -o /dev/null -w '%{http_code}' "$B$u"); [ "$c" = 404 ] || { echo "FAIL expected 404, got $c $u"; fail=1; }
done
c=$(curl -s -o /dev/null -w '%{http_code}' "$B/admin"); [ "$c" = 303 ] || { echo "FAIL /admin should redirect to sign-in, got $c"; fail=1; }
c=$(curl -s -o /dev/null -w '%{http_code}' -X POST -d x=1 "$B/contact"); [ "$c" = 400 ] || { echo "FAIL post without token should be 400, got $c"; fail=1; }
h=$(curl -s -D - -o /dev/null "$B/")
for hdr in content-security-policy x-frame-options x-content-type-options referrer-policy permissions-policy; do
  grep -qi "^$hdr:" <<<"$h" || { echo "FAIL missing header $hdr"; fail=1; }
done
rm -f "$J"
[ $fail = 0 ] && echo "smoke: all pages OK"
exit $fail
