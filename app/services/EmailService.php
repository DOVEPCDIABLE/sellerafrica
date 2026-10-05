<?php

declare(strict_types=1);

namespace App;

final class EmailService
{
    /**
     * @param string|array<int, string|array{email?: string, name?: string}> $to
     * @param array<string, mixed> $options
     */
    public static function send(string|array $to, string $subject, string $html, string $text = '', array $options = []): array
    {
        $config = self::config();
        $message = [
            'to' => self::recipients($to),
            'subject' => $subject,
            'html' => $html,
            'text' => $text !== '' ? $text : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html))),
            'from_email' => (string)($options['from_email'] ?? $config['from_email']),
            'from_name' => (string)($options['from_name'] ?? $config['from_name']),
            'reply_to_email' => (string)($options['reply_to_email'] ?? $config['reply_to_email']),
        ];

        if ($message['to'] === []) {
            throw new \InvalidArgumentException('Email recipient is required.');
        }

        return match ($config['mailer']) {
            'brevo' => self::sendBrevo($config, $message),
            'zeptomail' => self::sendZeptoMail($config, $message),
            'sendmail' => self::sendNativeMail($message),
            'log' => self::logMail($message),
            default => self::sendSmtp($config, $message),
        };
    }

    /**
     * Worker-friendly dispatcher.
     *
     * @param array<string, mixed> $data
     */
    public static function dispatch(string $action, array $data = []): array
    {
        if ($action !== 'send' && $action !== 'send_email') {
            throw new \InvalidArgumentException('Unsupported email action: ' . $action);
        }

        return self::send(
            $data['to'] ?? [],
            (string)($data['subject'] ?? ''),
            (string)($data['html'] ?? $data['html_body'] ?? ''),
            (string)($data['text'] ?? $data['text_body'] ?? ''),
            is_array($data['options'] ?? null) ? $data['options'] : []
        );
    }

    /**
     * @return array<string, string|int>
     */
    public static function config(): array
    {
        $config = [
            'mailer' => strtolower((string)\app_setting('mail', 'mailer', $_ENV['MAIL_MAILER'] ?? 'smtp')),
            'from_name' => (string)\app_setting('mail', 'from_name', $_ENV['MAIL_FROM_NAME'] ?? \app_branding()['name']),
            'from_email' => (string)\app_setting('mail', 'from_email', $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@example.com'),
            'reply_to_email' => (string)\app_setting('mail', 'reply_to_email', ''),
            'smtp_host' => (string)\app_setting('mail', 'smtp_host', $_ENV['MAIL_HOST'] ?? ''),
            'smtp_port' => (int)\app_setting('mail', 'smtp_port', $_ENV['MAIL_PORT'] ?? 587),
            'smtp_encryption' => strtolower((string)\app_setting('mail', 'smtp_encryption', $_ENV['MAIL_ENCRYPTION'] ?? 'tls')),
            'smtp_username' => (string)\app_setting('mail', 'smtp_username', $_ENV['MAIL_USERNAME'] ?? ''),
            'smtp_password' => (string)\app_setting('mail', 'smtp_password', $_ENV['MAIL_PASSWORD'] ?? ''),
            'brevo_api_key' => (string)\app_setting('mail', 'brevo_api_key', $_ENV['BREVO_API_KEY'] ?? ''),
            'brevo_endpoint' => (string)\app_setting('mail', 'brevo_endpoint', 'https://api.brevo.com/v3/smtp/email'),
            'zeptomail_api_key' => (string)\app_setting('mail', 'zeptomail_api_key', $_ENV['ZEPTOMAIL_API_KEY'] ?? ''),
            'zeptomail_endpoint' => (string)\app_setting('mail', 'zeptomail_endpoint', 'https://api.zeptomail.com/v1.1/email'),
        ];

        if ($config['mailer'] === 'smtp' && $config['smtp_host'] === '') {
            if ($config['brevo_api_key'] !== '') {
                $config['mailer'] = 'brevo';
            } elseif ($config['zeptomail_api_key'] !== '') {
                $config['mailer'] = 'zeptomail';
            }
        }

        return $config;
    }

    /**
     * @param string|array<int, string|array{email?: string, name?: string}> $to
     * @return array<int, array{email: string, name: string}>
     */
    private static function recipients(string|array $to): array
    {
        $items = is_array($to) ? $to : [$to];
        $recipients = [];

        foreach ($items as $item) {
            $email = is_array($item) ? (string)($item['email'] ?? '') : (string)$item;
            $name = is_array($item) ? (string)($item['name'] ?? '') : '';
            $email = trim($email);

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $recipients[] = ['email' => $email, 'name' => trim($name)];
            }
        }

        return $recipients;
    }

    /**
     * @param array<string, string|int> $config
     * @param array<string, mixed> $message
     */
    private static function sendBrevo(array $config, array $message): array
    {
        $apiKey = (string)$config['brevo_api_key'];
        if ($apiKey === '') {
            throw new \RuntimeException('Brevo API key is not configured.');
        }

        $payload = [
            'sender' => ['email' => $message['from_email'], 'name' => $message['from_name']],
            'to' => array_map(static fn (array $recipient): array => array_filter([
                'email' => $recipient['email'],
                'name' => $recipient['name'] !== '' ? $recipient['name'] : null,
            ]), $message['to']),
            'subject' => $message['subject'],
            'htmlContent' => $message['html'],
            'textContent' => $message['text'],
        ];

        if ($message['reply_to_email'] !== '') {
            $payload['replyTo'] = ['email' => $message['reply_to_email']];
        }

        return self::postJson((string)$config['brevo_endpoint'], [
            'api-key' => $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $payload);
    }

    /**
     * @param array<string, string|int> $config
     * @param array<string, mixed> $message
     */
    private static function sendZeptoMail(array $config, array $message): array
    {
        $apiKey = (string)$config['zeptomail_api_key'];
        if ($apiKey === '') {
            throw new \RuntimeException('ZeptoMail API key is not configured.');
        }

        $payload = [
            'from' => ['address' => $message['from_email'], 'name' => $message['from_name']],
            'to' => array_map(static fn (array $recipient): array => [
                'email_address' => [
                    'address' => $recipient['email'],
                    'name' => $recipient['name'] !== '' ? $recipient['name'] : $recipient['email'],
                ],
            ], $message['to']),
            'subject' => $message['subject'],
            'htmlbody' => $message['html'],
            'textbody' => $message['text'],
        ];

        if ($message['reply_to_email'] !== '') {
            $payload['reply_to'] = ['address' => $message['reply_to_email']];
        }

        return self::postJson((string)$config['zeptomail_endpoint'], [
            'Authorization' => 'Zoho-enczapikey ' . $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $payload);
    }

    /**
     * @param array<string, mixed> $message
     */
    private static function sendNativeMail(array $message): array
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . self::mailbox($message['from_email'], $message['from_name']),
        ];

        if ($message['reply_to_email'] !== '') {
            $headers[] = 'Reply-To: ' . $message['reply_to_email'];
        }

        $sent = true;
        foreach ($message['to'] as $recipient) {
            $sent = mail(self::mailbox($recipient['email'], $recipient['name']), $message['subject'], $message['html'], implode("\r\n", $headers)) && $sent;
        }

        if (!$sent) {
            throw new \RuntimeException('Native mail transport failed.');
        }

        return ['ok' => true, 'provider' => 'sendmail'];
    }

    /**
     * @param array<string, string|int> $config
     * @param array<string, mixed> $message
     */
    private static function sendSmtp(array $config, array $message): array
    {
        $host = (string)$config['smtp_host'];
        if ($host === '') {
            throw new \RuntimeException('SMTP host is not configured.');
        }

        $port = (int)$config['smtp_port'];
        $encryption = (string)$config['smtp_encryption'];
        $remote = $encryption === 'ssl' ? 'ssl://' . $host : $host;
        $socket = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);

        if (!$socket) {
            throw new \RuntimeException('SMTP connection failed: ' . $errstr);
        }

        stream_set_timeout($socket, 20);

        try {
            self::smtpExpect($socket, [220]);
            self::smtpCommand($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);

            if ($encryption === 'tls') {
                self::smtpCommand($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('SMTP STARTTLS negotiation failed.');
                }
                self::smtpCommand($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
            }

            if ((string)$config['smtp_username'] !== '') {
                self::smtpCommand($socket, 'AUTH LOGIN', [334]);
                self::smtpCommand($socket, base64_encode((string)$config['smtp_username']), [334]);
                self::smtpCommand($socket, base64_encode((string)$config['smtp_password']), [235]);
            }

            foreach ($message['to'] as $recipient) {
                self::smtpCommand($socket, 'MAIL FROM:<' . $message['from_email'] . '>', [250]);
                self::smtpCommand($socket, 'RCPT TO:<' . $recipient['email'] . '>', [250, 251]);
                self::smtpCommand($socket, 'DATA', [354]);
                fwrite($socket, self::smtpMessage($message, $recipient) . "\r\n.\r\n");
                self::smtpExpect($socket, [250]);
            }

            self::smtpCommand($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }

        return ['ok' => true, 'provider' => 'smtp'];
    }

    /**
     * @param array<string, mixed> $message
     */
    private static function logMail(array $message): array
    {
        $line = json_encode([
            'time' => \sql_now(),
            'to' => $message['to'],
            'subject' => $message['subject'],
            'provider' => 'log',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        file_put_contents(LOG_PATH . '/mail.log', $line . PHP_EOL, FILE_APPEND);

        return ['ok' => true, 'provider' => 'log'];
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $payload
     */
    private static function postJson(string $url, array $headers, array $payload): array
    {
        if (class_exists('\GuzzleHttp\Client')) {
            $client = new \GuzzleHttp\Client(['timeout' => 20]);
            $response = $client->post($url, ['headers' => $headers, 'json' => $payload]);
            $body = (string)$response->getBody();

            return ['ok' => $response->getStatusCode() >= 200 && $response->getStatusCode() < 300, 'status' => $response->getStatusCode(), 'body' => $body];
        }

        if (!function_exists('curl_init')) {
            throw new \RuntimeException('No HTTP client is available for API email delivery. Install Guzzle or enable cURL.');
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('Unable to initialize HTTP mail request.');
        }

        $flatHeaders = [];
        foreach ($headers as $name => $value) {
            $flatHeaders[] = $name . ': ' . $value;
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $flatHeaders,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);

        $body = (string)curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('Email API request failed: HTTP ' . $status . ($error !== '' ? ' - ' . $error : ''));
        }

        return ['ok' => true, 'status' => $status, 'body' => $body];
    }

    /**
     * @param resource $socket
     * @param array<int, int> $expected
     */
    private static function smtpCommand($socket, string $command, array $expected): string
    {
        fwrite($socket, $command . "\r\n");

        return self::smtpExpect($socket, $expected);
    }

    /**
     * @param resource $socket
     * @param array<int, int> $expected
     */
    private static function smtpExpect($socket, array $expected): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new \RuntimeException('SMTP error: ' . trim($response));
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $message
     * @param array{email: string, name: string} $recipient
     */
    private static function smtpMessage(array $message, array $recipient): string
    {
        $boundary = 'sa-' . bin2hex(random_bytes(12));
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . self::mailbox($message['from_email'], $message['from_name']),
            'To: ' . self::mailbox($recipient['email'], $recipient['name']),
            'Subject: ' . self::header($message['subject']),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        if ($message['reply_to_email'] !== '') {
            $headers[] = 'Reply-To: ' . $message['reply_to_email'];
        }

        return implode("\r\n", $headers)
            . "\r\n\r\n--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
            . $message['text']
            . "\r\n--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
            . $message['html']
            . "\r\n--{$boundary}--";
    }

    private static function mailbox(string $email, string $name = ''): string
    {
        return $name !== '' ? self::header($name) . ' <' . $email . '>' : $email;
    }

    private static function header(string $value): string
    {
        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
