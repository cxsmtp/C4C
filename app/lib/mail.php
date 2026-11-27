<?php
declare(strict_types=1);

function clean_header(string $s): string
{
    return trim(preg_replace('/[\r\n\t]+/', ' ', $s) ?? '');
}

function notify_recipients(): array
{
    $list = preg_split('/[\s,;]+/', setting('notify_emails', 'noreply@call4counsel.com')) ?: [];
    return array_values(array_unique(array_filter(array_map('strtolower', $list), 'valid_email')));
}

/**
 * Sends a plain-text email. Uses SMTP when configured (recommended on Hostinger:
 * smtp.hostinger.com, port 465, the mailbox's own login), otherwise PHP mail().
 */
/** SMTP settings come from Settings > Email (SMTP); app/config.php can override each one. */
function mail_cfg(string $k, string $default = ''): string
{
    $c = cfg($k);
    if (is_scalar($c) && (string)$c !== '' && $c !== 'CHANGE-ME') return (string)$c;
    if ($k === 'smtp_pass') return unseal(setting('smtp_pass'));
    $v = setting($k);
    return $v !== '' ? $v : $default;
}

function send_mail(array $to, string $subject, string $body, ?string $replyTo = null): bool
{
    $to = array_values(array_filter($to, 'valid_email'));
    if (!$to) return false;
    $from = mail_cfg('mail_from', 'noreply@call4counsel.com');
    if (!valid_email($from)) $from = 'noreply@call4counsel.com';
    $fromName = clean_header(mail_cfg('mail_from_name', 'Call4Counsel website'));
    $subject = clean_header($subject);
    $reply = ($replyTo && valid_email($replyTo)) ? $replyTo : null;

    $headers = [
        'Date' => date('r'),
        'From' => '"' . str_replace('"', '', $fromName) . '" <' . $from . '>',
        'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . (explode('@', $from)[1] ?? 'localhost') . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => '8bit',
        'Auto-Submitted' => 'auto-generated',
    ];
    if ($reply) $headers['Reply-To'] = $reply;
    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $body = str_replace(["\r\n", "\r"], "\n", $body);

    $error = '';
    try {
        if (mail_cfg('smtp_host') !== '') {
            $ok = smtp_send($to, $from, $encSubject, $body, $headers);
        } else {
            $h = '';
            foreach ($headers as $k => $v) $h .= "$k: $v\r\n";
            $ok = mail(implode(', ', $to), $encSubject, str_replace("\n", "\r\n", $body), rtrim($h), '-f' . $from);
        }
    } catch (Throwable $ex) {
        $ok = false;
        $error = mb_substr($ex->getMessage(), 0, 250);
    }
    if (!$ok && $error === '') $error = 'The mail function refused the message';
    log_line('mail.log', ($ok ? 'sent' : 'FAILED') . ' "' . $subject . '" to ' . count($to) . ' recipient(s)' . ($error ? ": $error" : ''));
    try {
        insert('mail_log', ['recipients' => mb_substr(implode(', ', $to), 0, 1000), 'subject' => mb_substr($subject, 0, 255), 'status' => $ok ? 'sent' : 'failed', 'error' => $error, 'created_at' => now()]);
    } catch (Throwable) {
    }
    return $ok;
}

function smtp_send(array $to, string $from, string $subject, string $body, array $headers): bool
{
    $host = mail_cfg('smtp_host');
    $port = (int)mail_cfg('smtp_port', '465');
    $secure = $port === 465 ? 'ssl' : 'tls';
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new RuntimeException("connect: $errstr");
    stream_set_timeout($fp, 15);
    $read = function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function (string $c, array $ok) use ($fp, $read): string {
        if ($c !== '') fwrite($fp, $c . "\r\n");
        $r = $read();
        if (!in_array((int)substr($r, 0, 3), $ok, true)) throw new RuntimeException('SMTP: ' . trim(substr($r, 0, 200)));
        return $r;
    };
    $ehlo = 'call4counsel.com';
    $cmd('', [220]);
    $cmd("EHLO $ehlo", [250]);
    if ($secure === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) throw new RuntimeException('STARTTLS failed');
        $cmd("EHLO $ehlo", [250]);
    }
    $cmd('AUTH LOGIN', [334]);
    $cmd(base64_encode(mail_cfg('smtp_user', $from)), [334]);
    $cmd(base64_encode(mail_cfg('smtp_pass')), [235]);
    $cmd("MAIL FROM:<$from>", [250]);
    foreach ($to as $r) $cmd("RCPT TO:<$r>", [250, 251]);
    $cmd('DATA', [354]);
    $msg = 'To: ' . implode(', ', $to) . "\r\nSubject: $subject\r\n";
    foreach ($headers as $k => $v) $msg .= "$k: $v\r\n";
    $lines = explode("\n", $body);
    foreach ($lines as &$l) if (str_starts_with($l, '.')) $l = '.' . $l; // dot-stuffing
    $msg .= "\r\n" . implode("\r\n", $lines) . "\r\n.";
    $cmd($msg, [250]);
    fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return true;
}

/** Emails a new enquiry to everyone on the notification list. Never emails the visitor. */
function notify_enquiry(int $id): void
{
    $q = one('SELECT * FROM enquiries WHERE id = ?', [$id]);
    if (!$q) return;
    $types = enquiry_types();
    $lines = [
        'New ' . strtolower($types[$q['type']] ?? $q['type']) . ' on ' . (parse_url(base_url(), PHP_URL_HOST) ?: 'the website'),
        '',
        'Name:    ' . $q['name'],
        'Email:   ' . $q['email'],
        'Company: ' . ($q['company'] ?: '-'),
        'Phone:   ' . ($q['phone'] ?: '-'),
        'Subject: ' . $q['subject'],
        '',
        (string)$q['message'],
        '',
        'Open it in the admin panel:',
        base_url() . '/admin/enquiries/' . $id,
    ];
    send_mail(notify_recipients(), '[' . ($types[$q['type']] ?? 'Enquiry') . '] ' . $q['subject'], implode("\n", $lines), $q['email']);
}

function enquiry_types(): array
{
    return ['contact' => 'Contact enquiry', 'quote' => 'Quote request', 'assessment' => 'AI & process assessment', 'job' => 'Job application', 'portal' => 'Client portal request'];
}
