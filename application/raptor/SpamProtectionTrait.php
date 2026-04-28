<?php

namespace Raptor;

/**
 * Trait SpamProtectionTrait
 *
 * Spam хамгаалалтын нэгдсэн логик.
 * Contact form, comment form, order form зэрэг public form-уудад ашиглана.
 *
 * Боломжууд:
 *   - Honeypot field шалгалт
 *   - HMAC token + timestamp шалгалт
 *   - Session rate limit
 *   - Cloudflare Turnstile (RAPTOR_TURNSTILE_SECRET_KEY .env-д байвал идэвхждэг)
 *   - Link spam шүүлтүүр (хэт олон URL агуулсан текст хаах)
 *
 * @package Raptor
 */
trait SpamProtectionTrait
{
    /**
     * Turnstile site key авах (form render хийхэд).
     * .env-д байхгүй бол хоосон string - widget харагдахгүй.
     */
    protected function getTurnstileSiteKey(): string
    {
        return $_ENV['RAPTOR_TURNSTILE_SITE_KEY'] ?? '';
    }

    /**
     * Spam хамгаалалтын бүрэн шалгалт хийх.
     *
     * @param array  $parsed     POST body
     * @param string $formName   HMAC-д ашиглах form нэр (жишээ: "contact-form", "comment-5")
     * @param string $sessionKey Rate limit session key (жишээ: "_last_contact_at")
     * @param int    $rateLimit  Хоорондох хамгийн бага секунд (default: 10)
     * @param int    $minTime    Form бөглөх хамгийн бага секунд (default: 2)
     * @throws \Exception Spam илэрвэл
     */
    protected function validateSpamProtection(array $parsed, string $formName, string $sessionKey, int $rateLimit = 10, int $minTime = 2): void
    {
        // 1) Honeypot
        if (!empty($parsed['website'])) {
            throw new \Exception('Invalid request', 400);
        }

        // 2) HMAC token + timestamp
        $ts = (int)($parsed['_ts'] ?? 0);
        $token = $parsed['_token'] ?? '';
        $secret = $_ENV['RAPTOR_JWT_SECRET'] ?? '';
        if (empty($secret)) {
            throw new \RuntimeException('RAPTOR_JWT_SECRET environment variable is not set');
        }
        $expected = \hash_hmac('sha256', "$formName-$ts", $secret);
        if (!\hash_equals($expected, $token)) {
            throw new \Exception('Invalid request', 403);
        }

        // 3) Хугацааны шалгалт
        $elapsed = \time() - $ts;
        if ($elapsed < $minTime) {
            throw new \Exception('Invalid request', 429);
        }
        if ($elapsed > 3600) {
            throw new \Exception('Form expired. Please try again.', 400);
        }

        // 4) Session rate limit
        $now = \time();
        $last = $_SESSION[$sessionKey] ?? 0;
        if ($now - $last < $rateLimit) {
            throw new \Exception('Too many requests. Please wait.', 429);
        }

        // 5) Cloudflare Turnstile (байвал шалгана, байхгүй бол skip)
        $this->verifyTurnstile($parsed['cf-turnstile-response'] ?? '');
    }

    /**
     * Cloudflare Turnstile token шалгах.
     * Secret key .env-д байхгүй бол skip хийнэ.
     *
     * @param string $token Client-ээс ирсэн turnstile response token
     * @throws \Exception Turnstile шалгалт амжилтгүй бол
     */
    private function verifyTurnstile(string $token): void
    {
        $secret = $_ENV['RAPTOR_TURNSTILE_SECRET_KEY'] ?? '';
        if (empty($secret)) {
            return;
        }

        if (empty($token)) {
            throw new \Exception('Invalid request', 400);
        }

        $response = (new \codesaur\Http\Client\CurlClient())->send(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            'POST',
            \http_build_query(['secret' => $secret, 'response' => $token]),
            [\CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']]
        );

        if ($response->isError()) {
            throw new \Exception('Invalid request', 403);
        }

        $result = $response->json();
        if (empty($result['success'])) {
            throw new \Exception('Invalid request', 403);
        }
    }

    /**
     * Текст дотор хэт олон link байгаа эсэхийг шалгах.
     *
     * @param string $text    Шалгах текст
     * @param int    $maxLinks Зөвшөөрөгдөх дээд link тоо (default: 2)
     * @throws \Exception Хэт олон link байвал
     */
    protected function checkLinkSpam(string $text, int $maxLinks = 2): void
    {
        $count = \preg_match_all('/https?:\/\/|www\./i', $text);
        if ($count > $maxLinks) {
            throw new \Exception('Too many links', 400);
        }
    }
}
