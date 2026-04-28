<?php

namespace Raptor\Mail;

use Psr\Log\LogLevel;

use codesaur\Http\Client\JSONClient;

use Raptor\Log\Logger;

/**
 * Raptor Framework-ийн и-мэйл илгээх сервис.
 *
 * RAPTOR_MAIL_TRANSPORT .env тохиргоогоор гурван аргаас сонгоно:
 *   - send()                   - .env-ээс transport сонгон илгээнэ (brevo/smtp/mail)
 *   - sendBrevoTransactional() - Brevo Transactional Email API (JSONClient)
 *   - sendSMTP()               - stream_socket_client ашиглан SMTP серверээр илгээнэ
 *   - sendMail()               - PHP mail() функц, cPanel/VPS серверийн sendmail/postfix
 *                                (Mail эцэг класст тодорхойлогдсон)
 *
 * Онцлогууд:
 *   - .env файлаас илгээгч (From), хариу хүлээн авагч (Reply-To) тохиргоог автомат уншина
 *   - HTML форматтай мессеж, CC / BCC / Attachment дэмжинэ
 *   - send() дуудагдах бүрд илгээлтийг `mailer` хүснэгтэд бүртгэнэ (амжилттай ба алдаатай аль аль нь)
 *
 * Attachment ялгаа:
 *   - Brevo API:  URL болон base64 content дэмждэг, локал файл дэмжигдэхгүй
 *   - SMTP:       Локал файл болон base64 content дэмждэг, URL дэмжигдэхгүй
 *   - sendMail(): Бүх төрөл дэмждэг (path, URL, content) - Mail эцэг класс хариуцна
 *
 * .env хувьсагчууд:
 *   RAPTOR_MAIL_FROM           - Илгээгч и-мэйл хаяг (заавал)
 *   RAPTOR_MAIL_FROM_NAME      - Илгээгчийн нэр
 *   RAPTOR_MAIL_REPLY_TO       - Хариу авах и-мэйл
 *   RAPTOR_MAIL_REPLY_TO_NAME  - Хариу авах нэр
 *   RAPTOR_MAIL_BREVO_APIKEY   - Brevo API түлхүүр (send() ашиглахад заавал)
 *   RAPTOR_MAIL_TRANSPORT      - Илгээх арга: 'brevo' (анхдагч), 'smtp', 'mail'
 *   RAPTOR_SMTP_HOST           - SMTP серверийн хаяг (transport=smtp үед заавал)
 *   RAPTOR_SMTP_PORT           - SMTP порт (анхдагч: 465)
 *   RAPTOR_SMTP_USERNAME       - SMTP нэвтрэх нэр
 *   RAPTOR_SMTP_PASSWORD       - SMTP нууц үг
 *   RAPTOR_SMTP_SECURE         - Шифрлэлт: 'ssl' (анхдагч) эсвэл 'tls'
 *
 * @package Raptor\Mail
 */
class Mailer extends \codesaur\Http\Client\Mail
{
    use \codesaur\DataObject\PDOTrait;

    /**
     * Mailer constructor.
     *
     * @param \PDO      $pdo           Database connection - илгээх протокол лог бичихэд ашиглагдана.
     * @param string|null $from        Илгээгчийн и-мэйл (хоосон бол .env -> RAPTOR_MAIL_FROM)
     * @param string|null $fromName    Илгээгчийн нэр (.env -> RAPTOR_MAIL_FROM_NAME)
     * @param string|null $replyTo     Хариу хүлээж авах хаяг (.env -> RAPTOR_MAIL_REPLY_TO)
     * @param string|null $replyToName Хариу авах нэр (.env -> RAPTOR_MAIL_REPLY_TO_NAME)
     *
     * @throws Exception Илгээгчийн хаяг тодорхойгүй бол.
     */
    public function __construct(
        \PDO $pdo,
        ?string $from = null, ?string $fromName = null,
        ?string $replyTo = null, ?string $replyToName = null
    ) {
        $this->setInstance($pdo);

        // Илгээгчийг тохируулах
        $this->setFrom(
            $from ?? $_ENV['RAPTOR_MAIL_FROM'] ?? '',
            $fromName ?? $_ENV['RAPTOR_MAIL_FROM_NAME'] ?? ''
        );

        // Reply-To (хэрэв өгөгдсөн бол)
        if (!empty($replyTo ?? $_ENV['RAPTOR_MAIL_REPLY_TO'] ?? '')) {
            $this->setReplyTo(
                $replyTo ?? $_ENV['RAPTOR_MAIL_REPLY_TO'],
                $replyToName ?? $_ENV['RAPTOR_MAIL_REPLY_TO_NAME'] ?? ''
            );
        }
    }

    /**
     * Email ачаалах тохиргоо (subject, message, recipients, attachments).
     *
     * @param string      $to          Хүлээн авагчийн и-мэйл
     * @param string|null $toName      Хүлээн авагчийн нэр
     * @param string      $subject     Гарчиг
     * @param string      $message     HTML форматтай мессеж
     * @param array|null  $attachments Attachment жагсаалт
     *
     * @return Mailer
     */
    public function mail(
        string $to,
        ?string $toName,
        string $subject,
        string $message,
        ?array $attachments = null
    ): Mailer {
        $this->setSubject($subject);
        $this->setMessage($message);
        $this->targetTo($to, $toName ?? '');

        if (\is_array($attachments)) {
            foreach ($attachments as $attachment) {
                $this->addAttachment($attachment);
            }
        }

        return $this;
    }

    /**
     * И-мэйл илгээх үндсэн функц.
     *
     * RAPTOR_MAIL_TRANSPORT .env тохиргоогоор илгээх аргыг сонгоно:
     *   - 'brevo' (анхдагч) - Brevo Transactional Email API
     *   - 'smtp'  - SMTP сервер (RAPTOR_SMTP_* тохиргоо шаардана)
     *   - 'mail'  - PHP mail() функц (серверийн sendmail/postfix)
     *
     * @return bool Илгээсэн эсэх
     */
    public function send(): bool
    {
        try {
            $transport = \strtolower($_ENV['RAPTOR_MAIL_TRANSPORT'] ?? 'brevo');
            switch ($transport) {
                case 'smtp':
                    $this->sendSMTP(
                        $_ENV['RAPTOR_SMTP_HOST'] ?? throw new \Exception('RAPTOR_SMTP_HOST is not set!'),
                        (int) ($_ENV['RAPTOR_SMTP_PORT'] ?? 465),
                        $_ENV['RAPTOR_SMTP_USERNAME'] ?? '',
                        $_ENV['RAPTOR_SMTP_PASSWORD'] ?? '',
                        $_ENV['RAPTOR_SMTP_SECURE'] ?? 'ssl'
                    );
                    break;
                case 'mail':
                    $this->sendMail();
                    break;
                default: // brevo
                    if (empty($_ENV['RAPTOR_MAIL_BREVO_APIKEY'] ?? '')) {
                        throw new \Exception('RAPTOR_MAIL_BREVO_APIKEY is not set!');
                    }
                    $result = $this->sendBrevoTransactional($_ENV['RAPTOR_MAIL_BREVO_APIKEY']);
                    if (empty($result) || isset($result['error'])) {
                        throw new \RuntimeException($result['error']['message'] ?? 'Email sending failed!');
                    }
                    break;
            }
            return true;
        } catch (\Throwable $err) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log($err->getMessage());
            }
            return false;
        } finally {
            try {
                $context = ['action' => 'mail-send'];
                $context['To'] = $this->getRecipients('To');
                $cc = $this->getRecipients('Cc');
                if (!empty($cc)) {
                    $context['Cc'] = $cc;
                }
                $bcc = $this->getRecipients('Bcc');
                if (!empty($bcc)) {
                    $context['Bcc'] = $bcc;
                }
                if (isset($err) && $err instanceof \Throwable) {
                    $level = LogLevel::ERROR;
                    $context['status'] = 'error';
                    $context['code'] = $err->getCode();
                    $context['message'] = $err->getMessage();
                } else {
                    $level = LogLevel::NOTICE;
                    $context['status'] = 'success';
                    $context['message'] = 'Email successfully sent to destination';
                }
                $context['remote_addr'] = $_SERVER['REMOTE_ADDR'] ?? '';
                $logger = new Logger($this->pdo);
                $logger->setTable('mailer');
                $toEmail = \reset($context['To'])['email'] ?? '';
                $mailSubject = $this->subject ?? '';
                $logger->log($level, "[$toEmail] - $mailSubject", $context);
            } catch (\Throwable $logErr) {
                if (CODESAUR_DEVELOPMENT) {
                    \error_log('Mailer log failed: ' . $logErr->getMessage());
                }
            }
        }
    }

    /**
     * SMTP серверээр и-мэйл илгээх.
     *
     * stream_socket_client ашиглан SMTP серверт шууд холбогдон илгээнэ.
     *
     * @param string $host         SMTP серверийн хаяг (жнь: smtp.gmail.com)
     * @param int    $port         SMTP порт (25, 465, 587)
     * @param string $username     SMTP нэвтрэх нэр
     * @param string $password     SMTP нууц үг
     * @param string $smtp_secure  Шифрлэлтийн төрөл: 'ssl' эсвэл 'tls'
     *
     * @return bool Амжилттай илгээсэн эсэх
     * @throws \RuntimeException SMTP холболт эсвэл илгээх явцад алдаа гарвал
     */
    protected function sendSMTP(
        string $host,
        int $port,
        string $username,
        string $password,
        string $smtp_secure = 'ssl'
    ): bool {
        $this->assertValues();

        // SSL/TLS холболт
        $context = \stream_context_create(['ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]]);
        $prefix = $smtp_secure === 'ssl' ? 'ssl://' : 'tcp://';
        $socket = @\stream_socket_client(
            $prefix . $host . ':' . $port,
            $errno, $errstr, 30,
            \STREAM_CLIENT_CONNECT, $context
        );
        if (!$socket) {
            throw new \RuntimeException("SMTP connection failed: $errstr ($errno)");
        }

        try {
            $this->smtpRead($socket);
            $this->smtpCommand($socket, "EHLO " . \gethostname());

            // STARTTLS (port 587)
            if ($smtp_secure === 'tls') {
                $this->smtpCommand($socket, "STARTTLS", 220);
                \stream_socket_enable_crypto($socket, true, \STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
                $this->smtpCommand($socket, "EHLO " . \gethostname());
            }

            // AUTH LOGIN
            $this->smtpCommand($socket, "AUTH LOGIN", 334);
            $this->smtpCommand($socket, \base64_encode($username), 334);
            $this->smtpCommand($socket, \base64_encode($password), 235);

            // Envelope
            $this->smtpCommand($socket, "MAIL FROM:<{$this->from}>");
            foreach (['To', 'Cc', 'Bcc'] as $type) {
                foreach ($this->getRecipients($type) as $r) {
                    $this->smtpCommand($socket, "RCPT TO:<{$r['email']}>");
                }
            }

            // DATA - MIME message
            $this->smtpCommand($socket, "DATA", 354);
            $mime = $this->buildMimeMessage();
            // SMTP: мөр бүрийн эхний цэгийг давхар цэгээр солино (dot-stuffing)
            $mime = \str_replace("\r\n.", "\r\n..", $mime);
            \fwrite($socket, $mime . "\r\n.\r\n");
            $this->smtpExpect($socket, 250);

            $this->smtpCommand($socket, "QUIT", 221);
            return true;
        } finally {
            \fclose($socket);
        }
    }

    /**
     * Brevo Transactional Email API ашиглаж илгээх.
     *
     * @param string $apiKey Brevo API key (.env -> RAPTOR_MAIL_BREVO_APIKEY)
     *
     * @return array API response
     * @throws \Exception Brevo local file attachment дэмжигдэхгүй
     */
    protected function sendBrevoTransactional(string $apiKey): array
    {
        $this->assertValues();

        $payload = [
            'subject' => $this->subject,
            'htmlContent' => $this->message,
            'to' => $this->getRecipients('To')
        ];

        $cc = $this->getRecipients('Cc');
        if (!empty($cc)) {
            $payload['cc'] = $cc;
        }
        $bcc = $this->getRecipients('Bcc');
        if (!empty($bcc)) {
            $payload['bcc'] = $bcc;
        }

        $payload['sender'] = !empty($this->fromName)
            ? ['name' => $this->fromName, 'email' => $this->from]
            : ['email' => $this->from];

        if (!empty($this->replyTo)) {
            $payload['replyTo'] = !empty($this->replyToName)
                ? ['name' => $this->replyToName, 'email' => $this->replyTo]
                : ['email' => $this->replyTo];
        }

        $attachments = [];
        foreach ($this->getAttachments() as $attachment) {
            if (isset($attachment['path'])) {
                throw new \Exception("Brevo API does not support local file attachments!");
            } elseif (isset($attachment['url'])) {
                $attachments[] = ['url' => $attachment['url'], 'name' => $attachment['name']];
            } elseif (isset($attachment['content'])) {
                $attachments[] = ['content' => \base64_encode($attachment['content']), 'name' => $attachment['name']];
            }
        }
        if (!empty($attachments)) {
            $payload['attachment'] = $attachments;
        }

        return (new JSONClient())->post(
            'https://api.brevo.com/v3/smtp/email',
            $payload,
            ['api-key' => $apiKey]
        );
    }

    // ==================== SMTP HELPERS ====================

    /**
     * SMTP серверээс хариу унших.
     */
    private function smtpRead($socket): string
    {
        $response = '';
        while ($line = \fgets($socket, 512)) {
            $response .= $line;
            // 4-р тэмдэгт нь зай бол сүүлийн мөр (RFC 5321)
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }

    /**
     * SMTP команд илгээж, хариу шалгах.
     */
    private function smtpCommand($socket, string $command, int $expect = 250): string
    {
        \fwrite($socket, $command . "\r\n");
        return $this->smtpExpect($socket, $expect);
    }

    /**
     * SMTP хариуны код шалгах.
     */
    private function smtpExpect($socket, int $expect): string
    {
        $response = $this->smtpRead($socket);
        $code = (int) \substr($response, 0, 3);
        if ($code !== $expect) {
            throw new \RuntimeException("SMTP error (expected $expect, got $code): $response");
        }
        return $response;
    }

    /**
     * MIME форматтай имэйл мессеж бүтээх (SMTP DATA-д ашиглана).
     */
    private function buildMimeMessage(): string
    {
        $boundary = 'Boundary_' . \md5(\uniqid());
        $headers = [];
        $headers[] = "From: " . $this->formatAddress($this->from, $this->fromName);
        $headers[] = "Subject: =?UTF-8?B?" . \base64_encode($this->subject) . "?=";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Date: " . \date('r');

        // To
        $toList = [];
        foreach ($this->getRecipients('To') as $r) {
            $toList[] = $this->formatAddress($r['email'], $r['name'] ?? '');
        }
        $headers[] = "To: " . \implode(', ', $toList);

        // Cc
        $ccList = [];
        foreach ($this->getRecipients('Cc') as $r) {
            $ccList[] = $this->formatAddress($r['email'], $r['name'] ?? '');
        }
        if (!empty($ccList)) {
            $headers[] = "Cc: " . \implode(', ', $ccList);
        }

        // Reply-To
        if (!empty($this->replyTo)) {
            $headers[] = "Reply-To: " . $this->formatAddress($this->replyTo, $this->replyToName);
        }

        $attachments = $this->getAttachments();
        if (empty($attachments)) {
            $headers[] = "Content-Type: text/html; charset=UTF-8";
            $headers[] = "Content-Transfer-Encoding: base64";
            return \implode("\r\n", $headers) . "\r\n\r\n" . \chunk_split(\base64_encode($this->message));
        }

        $headers[] = "Content-Type: multipart/mixed; boundary=\"$boundary\"";
        $body = \implode("\r\n", $headers) . "\r\n\r\n";

        // HTML body
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= \chunk_split(\base64_encode($this->message)) . "\r\n";

        // Attachments
        foreach ($attachments as $att) {
            if (isset($att['url'])) {
                throw new \RuntimeException('SMTP does not support URL attachments');
            }
            if (isset($att['path'])) {
                $data = \file_get_contents($att['path']);
                $mime = \mime_content_type($att['path']);
            } elseif (isset($att['content'])) {
                $data = $att['content'];
                $finfo = new \finfo(\FILEINFO_MIME_TYPE);
                $mime = $finfo->buffer($data);
            } else {
                continue;
            }
            $name = "=?UTF-8?B?" . \base64_encode($att['name']) . "?=";
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: $mime; name=\"$name\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= "Content-Disposition: attachment; filename=\"$name\"\r\n\r\n";
            $body .= \chunk_split(\base64_encode($data)) . "\r\n";
        }

        $body .= "--$boundary--";
        return $body;
    }

    /**
     * Имэйл хаягийг MIME форматаар бичих.
     */
    private function formatAddress(string $email, string $name = ''): string
    {
        if (empty($name)) {
            return $email;
        }
        $encoded = "=?UTF-8?B?" . \base64_encode($name) . "?=";
        return "$encoded <$email>";
    }
}
