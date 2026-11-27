<?php
declare(strict_types=1);

/**
 * Secrets stored in the database (SMTP password, SSO client secret, TOTP seeds) are
 * sealed with libsodium's secretbox. The key is derived from app_key in app/config.php,
 * so a database copy alone does not reveal them.
 */
function seal_key(): string
{
    $k = (string)cfg('app_key', '');
    if (strlen($k) < 32) throw new RuntimeException('app_key must be set to a long random value in app/config.php');
    return sodium_crypto_generichash('c4c-seal-v1|' . $k, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
}

function seal(string $plain): string
{
    if ($plain === '') return '';
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return 'v1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, seal_key()));
}

function unseal(?string $sealed): string
{
    if (!$sealed || !str_starts_with($sealed, 'v1:')) return '';
    $raw = base64_decode(substr($sealed, 3), true);
    if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return '';
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), seal_key());
    return $plain === false ? '' : $plain;
}

/** A secret setting: sealed in the database; app/config.php can override it. */
function secret_setting(string $k, string $cfgKey = ''): string
{
    if ($cfgKey !== '' && (string)cfg($cfgKey, '') !== '' && cfg($cfgKey) !== 'CHANGE-ME') return (string)cfg($cfgKey);
    return unseal(setting($k));
}

// ---------- TOTP (RFC 6238): 30-second steps, 6 digits, SHA-1, as authenticator apps expect ----------

function base32_encode(string $bin): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $chunk) $out .= $alpha[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}

function base32_decode(string $s): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s) ?? '');
    $bits = '';
    foreach (str_split($s) as $c) $bits .= str_pad(decbin(strpos($alpha, $c)), 5, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}

function totp_code(string $secret, int $step): string
{
    $h = hash_hmac('sha1', pack('J', $step), base32_decode($secret), true);
    $o = ord($h[19]) & 0xf;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Returns the matching time step (one step of clock drift allowed either way), or null. */
function totp_verify(string $secret, string $code, int $lastStep = 0): ?int
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== 6) return null;
    $now = intdiv(time(), 30);
    foreach ([0, -1, 1] as $d) {
        $step = $now + $d;
        if ($step > $lastStep && hash_equals(totp_code($secret, $step), $code)) return $step;
    }
    return null;
}

function totp_uri(string $secret, string $account): string
{
    $issuer = brand_name();
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&period=30&digits=6&algorithm=SHA1';
}

/** Ten one-time codes, XXXXX-XXXXX, from an alphabet without look-alike characters. */
function new_recovery_codes(): array
{
    $alpha = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $codes = [];
    for ($i = 0; $i < 10; $i++) {
        $c = '';
        for ($j = 0; $j < 10; $j++) $c .= $alpha[random_int(0, strlen($alpha) - 1)];
        $codes[] = substr($c, 0, 5) . '-' . substr($c, 5);
    }
    return $codes;
}

/** Recovery codes are stored only as a keyed hash bound to the user. */
function recovery_hash(int $userId, string $code): string
{
    $norm = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    return hash_hmac('sha256', $userId . '|' . $norm, (string)cfg('app_key', 'dev'));
}
