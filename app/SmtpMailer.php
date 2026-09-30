<?php // Version: 260916.14
declare(strict_types=1);

/**
 * app/SmtpMailer.php — Minimal SMTP client, dependency-free.
 *
 * WHY NOT PHPMailer
 * PHPMailer is the better-maintained, more battle-tested choice — it handles
 * more server quirks and edge cases than this file does. It was left out
 * because this project has no Composer dependency anywhere: every other
 * class here is a single hand-written file, deployed by uploading it, same
 * as everything else in this codebase. Adding Composer would mean a
 * `vendor/` folder, a lockfile, and a different deploy process for this one
 * feature — a larger change than the feature justifies.
 *
 * If Composer/terminal access is available on the host later (Plesk does
 * support this), replacing this class with PHPMailer is a clean, contained
 * swap — every call site uses the same three methods (`to`, `subject`,
 * `send`), so nothing else in the app needs to change.
 *
 * WHY NOT PHP's mail()
 * mail() relies on a local MTA (sendmail/postfix) being correctly configured
 * on the host, which is inconsistent across shared hosting and frequently
 * results in mail landing in spam with no authentication at all. This talks
 * directly to your configured SMTP provider (Gmail, Outlook, SendGrid, etc.)
 * over TLS/STARTTLS with AUTH LOGIN, which is what actually gets delivered.
 *
 * SCOPE: outbound only, plain + HTML body, no attachments. That covers every
 * use in this app (lead notifications, compliance alerts) — attachments were
 * not built because nothing here needs to send one.
 */

if (!defined('BASE_PATH')) exit('No direct script access');

final class SmtpMailer {

    private string $host; private int $port; private string $enc; // 'tls' | 'ssl' | ''
    private string $user; private string $pass;
    private string $fromEmail; private string $fromName;

    private array $to = []; private string $subject = ''; private string $bodyHtml = ''; private string $bodyText = '';
    private array $errors = [];

    public function __construct(array $config) {
        $this->host      = trim((string)($config['host'] ?? ''));
        $this->port      = (int)($config['port'] ?? 587);
        $this->enc       = strtolower(trim((string)($config['encryption'] ?? 'tls')));
        $this->user      = trim((string)($config['username'] ?? ''));
        $this->pass      = (string)($config['password'] ?? '');
        $this->fromEmail = trim((string)($config['from_email'] ?? $this->user));
        $this->fromName  = trim((string)($config['from_name'] ?? ''));
    }

    public static function isConfigured(): bool {
        $c = self::config();
        return !empty($c['enabled']) && !empty($c['host']) && !empty($c['username']) && !empty($c['password']);
    }

    public static function config(): array {
        $f = DATA_PATH . '/smtp_config.php';
        if (!is_readable($f)) return [];
        $c = @include $f;
        return is_array($c) ? $c : [];
    }

    public function to(string $email, string $name = ''): self {
        $this->to[] = ['email' => $email, 'name' => $name];
        return $this;
    }
    public function subject(string $s): self { $this->subject = $s; return $this; }
    public function html(string $h): self { $this->bodyHtml = $h; return $this; }
    public function text(string $t): self { $this->bodyText = $t; return $this; }
    public function lastError(): string { return implode(' ', $this->errors); }

    /** Send. Returns true/false; never throws — a notification failure must
     *  never take down the request that triggered it. */
    public function send(): bool {
        if ($this->host === '' || empty($this->to) || $this->subject === '') {
            $this->errors[] = 'Mailer not fully configured or message incomplete.';
            return false;
        }

        $transport = ($this->enc === 'ssl') ? 'ssl://' . $this->host : $this->host;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $sock = @stream_socket_client($transport . ':' . $this->port, $errno, $errstr, 12, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) { $this->errors[] = "Connection failed: {$errstr} ({$errno})"; return false; }

        try {
            $this->expect($sock, '220');
            $this->cmd($sock, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '250');

            if ($this->enc === 'tls') {
                $this->cmd($sock, 'STARTTLS', '220');
                if (!@stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS negotiation failed.');
                }
                $this->cmd($sock, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '250');
            }

            $this->cmd($sock, 'AUTH LOGIN', '334');
            $this->cmd($sock, base64_encode($this->user), '334');
            $this->cmd($sock, base64_encode($this->pass), '235');

            $this->cmd($sock, 'MAIL FROM:<' . $this->fromEmail . '>', '250');
            foreach ($this->to as $t) {
                $this->cmd($sock, 'RCPT TO:<' . $t['email'] . '>', ['250', '251']);
            }
            $this->cmd($sock, 'DATA', '354');

            $boundary = 'b_' . bin2hex(random_bytes(8));
            $headers = [];
            $headers[] = 'From: ' . $this->encodeHeader($this->fromName) . ' <' . $this->fromEmail . '>';
            $headers[] = 'To: ' . implode(', ', array_map(
                fn($t) => ($t['name'] !== '' ? $this->encodeHeader($t['name']) . ' ' : '') . '<' . $t['email'] . '>',
                $this->to
            ));
            $headers[] = 'Subject: ' . $this->encodeHeader($this->subject);
            $headers[] = 'Date: ' . date('r');
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . preg_replace('/[^a-z0-9.-]/i', '', $this->host) . '>';

            $body = '';
            if ($this->bodyHtml !== '' && $this->bodyText !== '') {
                $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
                $body .= "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                       . chunk_split(base64_encode($this->bodyText)) . "\r\n";
                $body .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                       . chunk_split(base64_encode($this->bodyHtml)) . "\r\n--{$boundary}--\r\n";
            } elseif ($this->bodyHtml !== '') {
                $headers[] = 'Content-Type: text/html; charset=UTF-8';
                $headers[] = 'Content-Transfer-Encoding: base64';
                $body = chunk_split(base64_encode($this->bodyHtml));
            } else {
                $headers[] = 'Content-Type: text/plain; charset=UTF-8';
                $headers[] = 'Content-Transfer-Encoding: base64';
                $body = chunk_split(base64_encode($this->bodyText));
            }

            // Dot-stuff any line starting with '.', per RFC 5321 — otherwise a
            // stray leading dot in the body is misread as end-of-DATA.
            $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body;
            $payload = preg_replace('/^\./m', '..', $payload);

            fwrite($sock, $payload . "\r\n.\r\n");
            $this->expect($sock, '250');
            $this->cmd($sock, 'QUIT', '221', false);
            fclose($sock);
            return true;
        } catch (Throwable $e) {
            $this->errors[] = $e->getMessage();
            @fclose($sock);
            return false;
        }
    }

    private function cmd($sock, string $line, $expect, bool $mustSucceed = true): void {
        fwrite($sock, $line . "\r\n");
        $this->expect($sock, $expect, $mustSucceed);
    }

    private function expect($sock, $codes, bool $mustSucceed = true): void {
        $codes = (array)$codes;
        $resp = '';
        while (($line = fgets($sock, 512)) !== false) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;   // final line of a multi-line reply
        }
        $code = substr(trim($resp), 0, 3);
        if ($mustSucceed && !in_array($code, $codes, true)) {
            throw new RuntimeException("SMTP error: expected " . implode('/', $codes) . ", got: " . trim($resp));
        }
    }

    private function encodeHeader(string $s): string {
        // Encoded-word (RFC 2047) so non-ASCII names/subjects survive.
        return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }
}
