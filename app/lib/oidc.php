<?php
declare(strict_types=1);

/**
 * OpenID Connect single sign-on for staff (Microsoft Entra ID, Google Workspace or any
 * standard provider). Authorization code flow with PKCE, state and nonce; the ID token's
 * RS256 signature is checked against the provider's published keys.
 */
function sso_provider_issuer(): string
{
    $issuer = rtrim(setting('sso_issuer'), '/');
    if ($issuer === '' && setting('sso_provider') === 'google') $issuer = 'https://accounts.google.com';
    return $issuer;
}

function sso_ready(): bool
{
    return setting('sso_enabled') === '1' && sso_provider_issuer() !== '' && setting('sso_client_id') !== '' && secret_setting('sso_client_secret') !== '';
}

/** Fetches JSON over HTTPS. Returns the decoded array, or an error message (string) safe to show. */
function https_json(string $url, ?array $post = null): array|string
{
    if (!preg_match('#^https://[a-z0-9.-]+(:\d+)?/#i', $url)) return 'Only https endpoints are allowed.';
    $opts = ['http' => ['timeout' => 10, 'ignore_errors' => true, 'header' => "Accept: application/json\r\nUser-Agent: c4c-site\r\n"],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]];
    if ($post !== null) {
        $opts['http']['method'] = 'POST';
        $opts['http']['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $opts['http']['content'] = http_build_query($post);
    }
    $body = @file_get_contents($url, false, stream_context_create($opts));
    if ($body === false || strlen($body) > 1_000_000) return 'The identity provider did not answer.';
    $data = json_decode($body, true);
    if (!is_array($data)) return 'The identity provider sent something unexpected.';
    return $data;
}

/** The provider's OpenID configuration, or an error message (string). */
function sso_discovery(bool $fresh = false): array|string
{
    $cached = json_decode(setting('sso_discovery_cache'), true);
    if (!$fresh && is_array($cached) && ($cached['_issuer'] ?? '') === sso_provider_issuer() && ($cached['_at'] ?? 0) > time() - 86400) return $cached;
    $d = https_json(sso_provider_issuer() . '/.well-known/openid-configuration');
    if (is_string($d)) return $d;
    foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri', 'issuer'] as $k) {
        if (empty($d[$k]) || !is_string($d[$k])) return "The provider's configuration has no $k.";
    }
    $keep = array_intersect_key($d, array_flip(['authorization_endpoint', 'token_endpoint', 'jwks_uri', 'issuer'])) + ['_issuer' => sso_provider_issuer(), '_at' => time()];
    set_setting('sso_discovery_cache', json_encode($keep));
    return $keep;
}

function b64url_decode(string $s): string
{
    return (string)base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
}

function b64url(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

/** Builds a PEM public key from an RSA JWK (modulus and exponent). */
function jwk_to_pem(array $jwk): string
{
    $der = fn(int $tag, string $v) => chr($tag) . der_len(strlen($v)) . $v;
    $int = function (string $b) use ($der) { if (ord($b[0]) > 0x7f) $b = "\0" . $b; return $der(0x02, $b); };
    $rsa = $der(0x30, $int(b64url_decode($jwk['n'])) . $int(b64url_decode($jwk['e'])));
    $algo = $der(0x30, $der(0x06, "\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01") . "\x05\x00");
    $spki = $der(0x30, $algo . $der(0x03, "\0" . $rsa));
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function der_len(int $n): string
{
    if ($n < 0x80) return chr($n);
    $b = ltrim(pack('N', $n), "\0");
    return chr(0x80 | strlen($b)) . $b;
}

function sso_start(): never
{
    if (!sso_ready()) abort();
    $d = sso_discovery();
    if (is_string($d)) {
        log_line('app.log', 'single sign-on start failed: ' . $d);
        flash('err', 'Single sign-on is not available right now: ' . $d);
        redirect('/admin/login');
    }
    start_session();
    $state = b64url(random_bytes(24));
    $nonce = b64url(random_bytes(24));
    $verifier = b64url(random_bytes(48));
    $_SESSION['sso_flow'] = ['state' => $state, 'nonce' => $nonce, 'verifier' => $verifier, 'at' => time()];
    $q = http_build_query([
        'response_type' => 'code', 'client_id' => setting('sso_client_id'), 'redirect_uri' => base_url() . '/signin/sso/callback',
        'scope' => 'openid email profile', 'state' => $state, 'nonce' => $nonce,
        'code_challenge' => b64url(hash('sha256', $verifier, true)), 'code_challenge_method' => 'S256', 'prompt' => 'select_account',
    ]);
    header('Location: ' . $d['authorization_endpoint'] . (str_contains($d['authorization_endpoint'], '?') ? '&' : '?') . $q, true, 302);
    exit;
}

/** Returns the verified claims, or an error message (string) safe to show. */
function sso_finish(string $code, string $state): array|string
{
    $flow = $_SESSION['sso_flow'] ?? null;
    unset($_SESSION['sso_flow']);
    if (!$flow || !hash_equals($flow['state'], $state) || time() - $flow['at'] > 600) return 'The sign-in request expired. Try again.';
    $d = sso_discovery();
    if (is_string($d)) return $d;
    $tok = https_json($d['token_endpoint'], [
        'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => base_url() . '/signin/sso/callback',
        'client_id' => setting('sso_client_id'), 'client_secret' => secret_setting('sso_client_secret'), 'code_verifier' => $flow['verifier'],
    ]);
    if (is_string($tok)) return $tok;
    $jwt = (string)($tok['id_token'] ?? '');
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return 'The provider did not return an ID token.';
    $header = json_decode(b64url_decode($parts[0]), true);
    $claims = json_decode(b64url_decode($parts[1]), true);
    if (!is_array($header) || !is_array($claims) || ($header['alg'] ?? '') !== 'RS256') return 'Unsupported token.';
    $jwks = https_json($d['jwks_uri']);
    if (is_string($jwks)) return $jwks;
    $keys = is_array($jwks['keys'] ?? null) ? $jwks['keys'] : [];
    $pem = null;
    foreach ($keys as $k) {
        if (($k['kty'] ?? '') === 'RSA' && ($k['kid'] ?? null) === ($header['kid'] ?? null) && isset($k['n'], $k['e'])) { $pem = jwk_to_pem($k); break; }
    }
    if (!$pem || openssl_verify($parts[0] . '.' . $parts[1], b64url_decode($parts[2]), $pem, OPENSSL_ALGO_SHA256) !== 1) return 'The ID token signature is not valid.';
    $aud = (array)($claims['aud'] ?? []);
    $now = time();
    $issuerOk = ($claims['iss'] ?? '') === $d['issuer']
        // Microsoft "common"/"organizations" issuers contain {tenantid}.
        || (str_contains($d['issuer'], '{tenantid}') && isset($claims['tid']) && ($claims['iss'] ?? '') === str_replace('{tenantid}', (string)$claims['tid'], $d['issuer']));
    if (!$issuerOk || !in_array(setting('sso_client_id'), $aud, true) || ($claims['exp'] ?? 0) < $now - 60 || ($claims['iat'] ?? 0) > $now + 300
        || !hash_equals($flow['nonce'], (string)($claims['nonce'] ?? ''))) {
        return 'The ID token was not issued for this site.';
    }
    $email = strtolower((string)($claims['email'] ?? $claims['preferred_username'] ?? ''));
    if (!valid_email($email)) return 'Your company account has no email address.';
    if (array_key_exists('email_verified', $claims) && $claims['email_verified'] === false) return 'Your email address is not verified with the provider.';
    $domains = array_filter(array_map('trim', explode(',', strtolower(setting('sso_allowed_domains')))));
    if ($domains && !in_array(substr(strrchr($email, '@'), 1), $domains, true)) return 'Your email domain is not allowed to sign in here.';
    return ['sub' => (string)$claims['sub'], 'email' => $email, 'name' => (string)($claims['name'] ?? $email)];
}
