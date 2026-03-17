<?php

namespace Web\Service;

use Psr\Log\LogLevel;

use Raptor\Content\PagesModel;
use Raptor\Content\MessagesModel;

use Web\Template\TemplateController;

/**
 * Class ContactController
 * ---------------------------------------------------------------
 * Холбоо барих хуудас болон мессеж илгээх контроллер.
 *
 * Энэ контроллер нь:
 *   - Холбоо барих хуудсыг харуулах (contact)
 *   - Холбоо барих формоор мессеж илгээх (contactSend)
 *   - Spam хамгаалалт: honeypot, HMAC token, timestamp, rate limit, Cloudflare Turnstile
 *
 * @package Web\Service
 */
class ContactController extends TemplateController
{
    use \Raptor\SpamProtectionTrait;
    /**
     * Холбоо барих хуудсыг харуулах.
     *
     * link талбарт '/contact' агуулсан нийтлэгдсэн хуудсыг хайж,
     * contact.html template-ээр рендерлэнэ. Мессеж илгээх формтой.
     *
     * @return void
     */
    public function contact()
    {
        $code = $this->getLanguageCode();
        $pages_table = (new PagesModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT id, title, content, photo, code
             FROM $pages_table
             WHERE is_active=1 AND published=1
               AND code=:code
               AND link LIKE '%/contact'
             ORDER BY published_at DESC
             LIMIT 1"
        );
        $record = $stmt->execute([':code' => $code]) ? $stmt->fetch() : [];

        // Spam хамгаалалтын бүрдэл: timestamp + HMAC token
        $ts = \time();
        $secret = $this->getJwtSecret();

        $this->twigWebLayout(__DIR__ . '/contact.html', [
            'record' => $record ?: [],
            'title' => $record['title'] ?? $this->text('contact'),
            'code' => $record['code'] ?? '',
            'description' => $record['description'] ?? '',
            'photo' => $record['photo'] ?? '',
            'spam_ts' => $ts,
            'spam_token' => \hash_hmac('sha256', "contact-form-$ts", $secret),
            'turnstile_site_key' => $this->getTurnstileSiteKey()
        ] + $this->getAttribute('settings', []))->render();

        $this->log(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code}] Холбоо барих хуудсыг уншиж байна',
            ['action' => 'contact']
        );
    }

    /**
     * Холбоо барих форм илгээх (AJAX).
     *
     * Spam хамгаалалтын механизм:
     *   1) Honeypot талбар - бот бөглөвөл хаяна
     *   2) HMAC token - хуурамч form илрүүлэх
     *   3) Хугацааны шалгалт - 3 секундээс хурдан бөглөвөл бот
     *   4) 1 цагаас хэтэрсэн form хүчингүй
     *   5) Session rate limit - 10 секундэд 1 мессеж
     *
     * @return void
     */
    public function contactSend()
    {
        try {
            $parsed = $this->getParsedBody();
            $code = $this->getLanguageCode();

            $this->validateSpamProtection($parsed, 'contact-form', '_last_contact_at', 10, 3);

            $name    = \trim($parsed['name'] ?? '');
            $phone   = \trim($parsed['phone'] ?? '');
            $email   = \trim($parsed['email'] ?? '');
            $message = \trim($parsed['message'] ?? '');

            if (empty($name)) {
                throw new \InvalidArgumentException($code === 'mn' ? 'Нэрээ оруулна уу' : 'Please enter your name');
            }
            if (empty($phone)) {
                throw new \InvalidArgumentException($code === 'mn' ? 'Утасны дугаараа оруулна уу' : 'Please enter your phone number');
            }
            if (empty($message)) {
                throw new \InvalidArgumentException($code === 'mn' ? 'Мессежээ бичнэ үү' : 'Please enter your message');
            }
            $this->checkLinkSpam($message);

            $_SESSION['_last_contact_at'] = \time();

            // Мессежийг DB-д хадгалах
            (new MessagesModel($this->pdo))->insert([
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'message' => $message,
                'code' => $code,
                'created_at' => \date('Y-m-d H:i:s')
            ]);

            // Discord мэдэгдэл
            $appUrl = \rtrim((string)$this->getRequest()->getUri()->withPath($this->getScriptPath()), '/');
            $this->getService('discord')?->newContactMessage($name, $phone, $email, $message, $appUrl);

            $this->log(
                'web',
                LogLevel::INFO,
                '[{server_request.code}] Холбоо барих мессеж: {name} ({phone})',
                [
                    'action' => 'contact-send',
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'message' => $message
                ]
            );

            $this->respondJSON([
                'status' => 'success',
                'message' => $code === 'mn'
                    ? 'Таны мессеж амжилттай илгээгдлээ! Бид тантай удахгүй холбогдох болно.'
                    : 'Your message has been sent successfully! We will contact you soon.'
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode() ?: 500);
        }
    }

    /**
     * JWT secret авах.
     *
     * @return string JWT secret
     * @throws \RuntimeException Environment variable тохируулаагүй бол
     */
    private function getJwtSecret(): string
    {
        $secret = $_ENV['RAPTOR_JWT_SECRET'] ?? '';
        if (empty($secret)) {
            throw new \RuntimeException('RAPTOR_JWT_SECRET environment variable is not set');
        }
        return $secret;
    }
}
