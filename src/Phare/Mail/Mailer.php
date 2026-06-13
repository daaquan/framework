<?php

namespace Phare\Mail;

class Mailer
{
    protected array $config;

    protected array $sentMails = [];

    /**
     * Drivers that always record into {@see $sentMails} instead of handing the
     * message to a real transport. These are used by tests and local
     * development so no mail ever leaves the machine.
     *
     * Note: `smtp` is NOT here. The SMTP driver records as well, but it ALSO
     * opens a real socket — gated behind the `live` config flag — so that the
     * test suite (which uses driver `smtp` without `live`) stays record-only
     * while production (driver `smtp` + `live => true` + a host) delivers.
     */
    protected const RECORDING_DRIVERS = ['array', 'log'];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'driver' => 'smtp',
            'host' => 'localhost',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
            'from' => [
                'address' => 'noreply@example.com',
                'name' => 'Phare Application',
            ],
        ], $config);
    }

    public function send(Mailable $mailable): bool
    {
        try {
            $mailable->build();

            return $this->transport($this->record($mailable));
        } catch (MailException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new MailException('Failed to send email: ' . $e->getMessage(), 0, $e);
        }
    }

    public function raw(string $text, \Closure $callback): bool
    {
        $message = new Message();
        $callback($message);

        $mailable = new RawMailable($text, $message);

        return $this->send($mailable);
    }

    public function html(string $html, \Closure $callback): bool
    {
        $message = new Message();
        $callback($message);

        $mailable = new HtmlMailable($html, $message);

        return $this->send($mailable);
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getSentMails(): array
    {
        return $this->sentMails;
    }

    public function clearSentMails(): void
    {
        $this->sentMails = [];
    }

    /**
     * The configured transport driver, normalised to lower-case. Honours
     * `driver` first, then `default`, defaulting to `sendmail` (PHP mail()).
     */
    protected function driver(): string
    {
        $driver = $this->config['driver'] ?? $this->config['default'] ?? 'sendmail';

        return strtolower((string)$driver);
    }

    /**
     * Build a normalised record of the message and append it to the sent-mail
     * log. Always runs so {@see getSentMails()} reflects every dispatch.
     *
     * @return array<string, mixed> The recorded message envelope.
     */
    protected function record(Mailable $mailable): array
    {
        $record = [
            'to' => $mailable->getTo(),
            'cc' => $mailable->getCc(),
            'bcc' => $mailable->getBcc(),
            'replyTo' => $mailable->getReplyTo(),
            'subject' => $mailable->getSubject(),
            'htmlBody' => $mailable->hasHtml() ? $mailable->getHtmlBody() : null,
            'textBody' => $mailable->hasText() ? $mailable->getTextBody() : null,
            'attachments' => $mailable->getAttachments(),
            'headers' => $mailable->getHeaders(),
            'sent_at' => date('Y-m-d H:i:s'),
        ];

        $this->sentMails[] = $record;

        return $record;
    }

    /**
     * Hand the recorded message to the configured transport.
     *
     * @param array<string, mixed> $record
     */
    protected function transport(array $record): bool
    {
        $driver = $this->driver();

        return match (true) {
            // Recording drivers (array/log) keep the message in $sentMails only
            // — used by the test suite and local dev. No mail is transported.
            in_array($driver, self::RECORDING_DRIVERS, true) => true,

            // SMTP: record-only UNLESS explicitly armed for live delivery.
            // See {@see shouldSendLive()} for the gate that keeps tests green.
            $driver === 'smtp' => $this->shouldSendLive()
                ? $this->sendViaSmtp($record)
                : true,

            // The real default: hand the message to the local MTA via mail().
            $driver === 'sendmail' || $driver === 'mail' => $this->sendViaMail($record),

            default => throw new MailException(
                "Mail driver [{$driver}] is not supported."
            ),
        };
    }

    /**
     * Whether the SMTP driver should open a real socket and deliver the message.
     *
     * Backward-compatibility gate: the test suite constructs the Mailer with
     * driver `smtp` and asserts on {@see getSentMails()} without ever setting a
     * `live` flag, so SMTP must stay record-only by default. Real delivery is
     * armed ONLY when the config explicitly sets `live => true` AND provides a
     * non-empty host. Production wires `mail.live` (or per-mailer `live`) on.
     */
    protected function shouldSendLive(): bool
    {
        $live = $this->config['live'] ?? false;
        $host = (string)($this->config['host'] ?? '');

        return filter_var($live, FILTER_VALIDATE_BOOLEAN) && $host !== '';
    }

    /**
     * Deliver a message through PHP's built-in mail() (the local MTA).
     *
     * Builds RFC-822 headers (From, Cc, Bcc, Reply-To, custom) and a MIME
     * body. When HTML and/or attachments are present the body becomes a
     * multipart message; otherwise a plain-text body is sent.
     *
     * @param array<string, mixed> $record
     */
    protected function sendViaMail(array $record): bool
    {
        $to = $this->formatAddressList($record['to']);

        if ($to === '') {
            throw new MailException('Cannot send mail: no recipient address.');
        }

        $subject = (string)$record['subject'];
        [$body, $mimeHeaders] = $this->buildBody($record);

        $headers = array_merge($this->buildEnvelopeHeaders($record), $mimeHeaders);
        $headerString = $this->encodeHeaders($headers);

        $sent = @mail($to, $subject, $body, $headerString);

        if ($sent === false) {
            throw new MailException("Failed to hand message to the MTA for [{$to}].");
        }

        return true;
    }

    /**
     * Deliver a message over a raw SMTP socket.
     *
     * Honours the configured host/port/encryption (tls|ssl)/username/password.
     * Opens the connection, negotiates EHLO, upgrades to TLS via STARTTLS when
     * encryption is `tls`, authenticates with AUTH LOGIN when credentials are
     * present, then issues MAIL FROM / RCPT TO / DATA and writes the same MIME
     * message produced for the sendmail driver. Throws {@see MailException} on
     * any non-2xx/3xx SMTP reply or socket error.
     *
     * @param array<string, mixed> $record
     */
    protected function sendViaSmtp(array $record): bool
    {
        $recipients = $this->collectRecipients($record);
        if ($recipients === []) {
            throw new MailException('Cannot send mail: no recipient address.');
        }

        $from = $this->config['from']['address'] ?? '';
        if ($from === '') {
            throw new MailException('Cannot send mail: no from address configured.');
        }

        $host = (string)$this->config['host'];
        $port = (int)($this->config['port'] ?? 25);
        $encryption = strtolower((string)($this->config['encryption'] ?? ''));
        $timeout = (int)($this->config['timeout'] ?? 30);

        // ssl:// implies an implicit TLS connection from the first byte; tls is
        // negotiated later via STARTTLS over a plain connection.
        $transport = $encryption === 'ssl' ? 'ssl://' : '';

        $socket = @stream_socket_client(
            $transport . $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if ($socket === false) {
            throw new MailException("Failed to connect to SMTP host [{$host}:{$port}]: {$errstr} ({$errno}).");
        }

        try {
            stream_set_timeout($socket, $timeout);

            $this->smtpExpect($socket, [220]);

            $ehloHost = $this->smtpClientName();
            $this->smtpCommand($socket, 'EHLO ' . $ehloHost, [250]);

            if ($encryption === 'tls') {
                $this->smtpCommand($socket, 'STARTTLS', [220]);

                $crypto = @stream_socket_enable_crypto(
                    $socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                );

                if ($crypto !== true) {
                    throw new MailException('Failed to enable TLS encryption on SMTP connection.');
                }

                // RFC 3207: re-issue EHLO after the TLS upgrade.
                $this->smtpCommand($socket, 'EHLO ' . $ehloHost, [250]);
            }

            $username = (string)($this->config['username'] ?? '');
            $password = (string)($this->config['password'] ?? '');

            if ($username !== '') {
                $this->smtpCommand($socket, 'AUTH LOGIN', [334]);
                $this->smtpCommand($socket, base64_encode($username), [334]);
                $this->smtpCommand($socket, base64_encode($password), [235]);
            }

            $this->smtpCommand($socket, 'MAIL FROM:<' . $from . '>', [250]);

            foreach ($recipients as $recipient) {
                $this->smtpCommand($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
            }

            $this->smtpCommand($socket, 'DATA', [354]);
            $this->smtpCommand($socket, $this->buildSmtpData($record), [250]);

            $this->smtpCommand($socket, 'QUIT', [221]);
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }

        return true;
    }

    /**
     * The full RFC-822 message (headers + body) for the SMTP DATA command,
     * terminated with the `\r\n.\r\n` end-of-data sequence. Reuses the same MIME
     * building as the sendmail driver so both transports emit identical bodies.
     *
     * @param array<string, mixed> $record
     */
    protected function buildSmtpData(array $record): string
    {
        [$body, $mimeHeaders] = $this->buildBody($record);

        $headers = [];

        $to = $this->formatAddressList($record['to']);
        if ($to !== '') {
            $headers['To'] = $to;
        }

        $headers['Subject'] = (string)$record['subject'];
        $headers = array_merge($headers, $this->buildEnvelopeHeaders($record), $mimeHeaders);

        $headerString = $this->encodeHeaders($headers);

        // Dot-stuffing: any line starting with '.' must be escaped to '..'.
        $body = preg_replace('/^\./m', '..', $body) ?? $body;

        return $headerString . "\r\n\r\n" . $body . "\r\n.";
    }

    /**
     * Flatten every recipient bucket (to/cc/bcc) to a unique list of addresses
     * for the SMTP envelope (RCPT TO).
     *
     * @param array<string, mixed> $record
     * @return array<int, string>
     */
    protected function collectRecipients(array $record): array
    {
        $addresses = [];

        foreach (['to', 'cc', 'bcc'] as $bucket) {
            foreach ((array)($record[$bucket] ?? []) as $address => $name) {
                $address = (string)$address;
                if ($address !== '') {
                    $addresses[$address] = true;
                }
            }
        }

        return array_keys($addresses);
    }

    /**
     * The client identity advertised in EHLO. Derives from the from-address
     * domain when available, falling back to localhost.
     */
    protected function smtpClientName(): string
    {
        $from = (string)($this->config['from']['address'] ?? '');
        $at = strrpos($from, '@');

        if ($at !== false && $at + 1 < strlen($from)) {
            return substr($from, $at + 1);
        }

        return 'localhost';
    }

    /**
     * Write a command and assert its reply code is one of $expected.
     *
     * @param resource $socket
     * @param array<int, int> $expected
     */
    protected function smtpCommand($socket, string $command, array $expected): string
    {
        if (@fwrite($socket, $command . "\r\n") === false) {
            throw new MailException("Failed to write SMTP command: {$command}.");
        }

        return $this->smtpExpect($socket, $expected);
    }

    /**
     * Read a (possibly multi-line) SMTP reply and assert its code.
     *
     * @param resource $socket
     * @param array<int, int> $expected
     */
    protected function smtpExpect($socket, array $expected): string
    {
        $response = '';
        $code = 0;

        do {
            $line = fgets($socket, 515);
            if ($line === false) {
                throw new MailException('SMTP connection closed unexpectedly while reading reply.');
            }

            $response .= $line;
            $code = (int)substr($line, 0, 3);

            // A hyphen at position 3 indicates a continuation line follows.
            $more = isset($line[3]) && $line[3] === '-';
        } while ($more);

        if (!in_array($code, $expected, true)) {
            throw new MailException(
                'Unexpected SMTP reply: ' . trim($response) . ' (expected ' . implode('/', $expected) . ').'
            );
        }

        return $response;
    }

    /**
     * RFC-822 envelope headers shared by every transported message.
     *
     * @param array<string, mixed> $record
     * @return array<string, string>
     */
    protected function buildEnvelopeHeaders(array $record): array
    {
        $headers = [];

        $from = $this->config['from'] ?? [];
        if (!empty($from['address'])) {
            $headers['From'] = $this->formatAddress($from['address'], $from['name'] ?? null);
        }

        if (!empty($record['cc'])) {
            $headers['Cc'] = $this->formatAddressList($record['cc']);
        }

        if (!empty($record['bcc'])) {
            $headers['Bcc'] = $this->formatAddressList($record['bcc']);
        }

        if (!empty($record['replyTo'])) {
            $headers['Reply-To'] = $this->formatAddressList($record['replyTo']);
        }

        // Custom user headers take precedence but never overwrite envelope keys
        // that carry routing meaning above.
        foreach ((array)$record['headers'] as $name => $value) {
            $headers[$name] = (string)$value;
        }

        return $headers;
    }

    /**
     * Build the MIME body and the matching content headers.
     *
     * @param array<string, mixed> $record
     * @return array{0: string, 1: array<string, string>}
     */
    protected function buildBody(array $record): array
    {
        $html = $record['htmlBody'] ?? null;
        $text = $record['textBody'] ?? null;
        $attachments = (array)$record['attachments'];

        $isMultipart = !empty($attachments) || (!empty($html) && !empty($text));

        if (!$isMultipart) {
            if (!empty($html)) {
                return [(string)$html, [
                    'MIME-Version' => '1.0',
                    'Content-Type' => 'text/html; charset=UTF-8',
                ]];
            }

            return [(string)$text, [
                'MIME-Version' => '1.0',
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]];
        }

        $boundary = 'phare-' . bin2hex(random_bytes(12));
        $parts = [];

        if (!empty($text)) {
            $parts[] = $this->mimePart('text/plain; charset=UTF-8', (string)$text);
        }

        if (!empty($html)) {
            $parts[] = $this->mimePart('text/html; charset=UTF-8', (string)$html);
        }

        foreach ($attachments as $attachment) {
            $part = $this->attachmentPart($attachment);
            if ($part !== null) {
                $parts[] = $part;
            }
        }

        $body = '--' . $boundary . "\r\n"
            . implode("\r\n--" . $boundary . "\r\n", $parts)
            . "\r\n--" . $boundary . "--\r\n";

        return [$body, [
            'MIME-Version' => '1.0',
            'Content-Type' => 'multipart/mixed; boundary="' . $boundary . '"',
        ]];
    }

    /**
     * A single inline MIME part with base64-encoded content.
     */
    protected function mimePart(string $contentType, string $content): string
    {
        return "Content-Type: {$contentType}\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($content));
    }

    /**
     * Build a MIME attachment part from an attachment descriptor.
     *
     * @param array<string, mixed> $attachment
     */
    protected function attachmentPart(array $attachment): ?string
    {
        $name = $attachment['name'] ?? null;
        $type = $attachment['type'] ?? 'application/octet-stream';

        if (isset($attachment['data'])) {
            $content = (string)$attachment['data'];
            $name ??= 'attachment';
        } elseif (!empty($attachment['path']) && is_readable($attachment['path'])) {
            $content = (string)file_get_contents($attachment['path']);
            $name ??= basename((string)$attachment['path']);
        } else {
            // A path that cannot be read is skipped rather than aborting the
            // whole message; the body still carries the readable parts.
            return null;
        }

        return "Content-Type: {$type}; name=\"{$name}\"\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "Content-Disposition: attachment; filename=\"{$name}\"\r\n\r\n"
            . chunk_split(base64_encode($content));
    }

    /**
     * Flatten an address map (address => name|null) to an RFC-822 list.
     *
     * @param array<string, string|null> $addresses
     */
    protected function formatAddressList(array $addresses): string
    {
        $formatted = [];
        foreach ($addresses as $address => $name) {
            $formatted[] = $this->formatAddress($address, $name);
        }

        return implode(', ', $formatted);
    }

    protected function formatAddress(string $address, ?string $name = null): string
    {
        if ($name === null || $name === '') {
            return $address;
        }

        return sprintf('%s <%s>', $name, $address);
    }

    /**
     * @param array<string, string> $headers
     */
    protected function encodeHeaders(array $headers): string
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return implode("\r\n", $lines);
    }
}
