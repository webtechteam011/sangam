<?php
declare(strict_types=1);

/**
 * Minimal SMTP sender (implicit SSL on 465 or STARTTLS on 587). Returns false on any failure.
 * Config comes from .env (SMTP_*), see .env.example.
 */
function smtp_send(string $to, string $subject, string $body, ?string $replyTo = null): bool
{
    $host = SMTP_HOST;
    $user = SMTP_USER;
    $pass = SMTP_PASS;
    if ($host === '' || $user === '' || $pass === '') {
        return false;
    }
    $secure = SMTP_SECURE;
    $port   = SMTP_PORT;
    $clean  = static fn(string $s): string => str_replace(["\r", "\n"], ' ', $s);

    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $en, $es, 10);
    if (!$fp) {
        error_log("SMTP connect failed: $es");
        return false;
    }
    stream_set_timeout($fp, 10);

    $read = static function () use ($fp): string {
        $out = '';
        while (($line = fgets($fp, 515)) !== false) {
            $out .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $out;
    };
    $cmd = static function (string $c, string $ok) use ($fp, $read): bool {
        fwrite($fp, $c . "\r\n");
        return str_starts_with($read(), $ok);
    };

    try {
        if (!str_starts_with($read(), '220')) {
            throw new RuntimeException('bad greeting');
        }
        $ehlo = 'EHLO ' . (preg_replace('/[^a-z0-9.-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost');
        if (!$cmd($ehlo, '250')) {
            throw new RuntimeException('EHLO failed');
        }
        if ($secure === 'tls') {
            if (!$cmd('STARTTLS', '220') || !stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS failed');
            }
            $cmd($ehlo, '250');
        }
        if (!$cmd('AUTH LOGIN', '334') || !$cmd(base64_encode($user), '334') || !$cmd(base64_encode($pass), '235')) {
            throw new RuntimeException('auth failed');
        }
        $from = SMTP_FROM !== '' ? SMTP_FROM : $user;
        if (!$cmd("MAIL FROM:<$from>", '250') || !$cmd('RCPT TO:<' . $clean($to) . '>', '250') || !$cmd('DATA', '354')) {
            throw new RuntimeException('envelope rejected');
        }
        $h  = 'From: =?UTF-8?B?' . base64_encode(SMTP_FROM_NAME) . "?= <$from>\r\n";
        $h .= 'To: ' . $clean($to) . "\r\n";
        if ($replyTo) {
            $h .= 'Reply-To: ' . $clean($replyTo) . "\r\n";
        }
        $h .= 'Subject: =?UTF-8?B?' . base64_encode($clean($subject)) . "?=\r\n";
        $h .= 'Date: ' . date('r') . "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n";
        $data = $h . "\r\n" . chunk_split(base64_encode($body));
        fwrite($fp, $data . "\r\n.\r\n");
        if (!str_starts_with($read(), '250')) {
            throw new RuntimeException('message rejected');
        }
        $cmd('QUIT', '221');
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        error_log('SMTP send failed: ' . $e->getMessage());
        @fclose($fp);
        return false;
    }
}
