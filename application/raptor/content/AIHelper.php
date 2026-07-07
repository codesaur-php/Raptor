<?php

namespace Raptor\Content;

use codesaur\Http\Client\JSONClient;

/**
 * AI туслах класс - OpenAI API ашиглан контент боловсруулах.
 *
 * moedit WYSIWYG editor-ийн AI функцуудыг (Shine, OCR) дэмжих backend endpoint-уудыг агуулна.
 * Ашиглах моделиудыг .env-ээс тохируулна - OpenAI шинэ модель гаргах эсвэл
 * хуучныг зогсоох үед код хөндөлгүйгээр солино (default: DEFAULT_MODEL,
 * DEFAULT_VISION_MODEL константууд).
 *
 * Тохиргоо (.env файлд):
 * -----------------------------------------------------------------------------
 *   RAPTOR_OPENAI_API_KEY=sk-proj-...    # OpenAI API түлхүүр (заавал)
 *   RAPTOR_OPENAI_MODEL=gpt-5-mini       # HTML сайжруулалтын модель (сонголт)
 *   RAPTOR_OPENAI_VISION_MODEL=gpt-5.1   # Vision/OCR модель (сонголт)
 *
 * @package    Raptor\Content
 * @author     Narankhuu
 * @see        https://platform.openai.com/docs/api-reference OpenAI API Documentation
 */
class AIHelper extends \Raptor\Controller
{
    /**
     * Моделиудын анхдагч утга - .env (RAPTOR_OPENAI_MODEL,
     * RAPTOR_OPENAI_VISION_MODEL)-ээр дарж болно. OpenAI модель зогсоох
     * бүрд код засахгүйн тулд нэрийг payload-д hardcode хийхгүй.
     */
    private const DEFAULT_MODEL = 'gpt-5-mini';
    private const DEFAULT_VISION_MODEL = 'gpt-5.1';

    /**
     * Vision (OCR) mode-д нэг хүсэлтээр боловсруулах зургийн дээд тоо.
     * Зураг бүр тусад нь vision моделийн дуудлага үүсгэдэг тул энэ нь
     * нэг хүсэлтийн зардлыг хязгаарлана.
     */
    private const MAX_IMAGES_PER_REQUEST = 8;

    /**
     * Rate limit: нэг хэрэглэгч RATE_LIMIT_WINDOW секундэд хийж болох
     * OpenAI дуудлагын дээд тоо. Vision mode-д зураг бүр нэг дуудлага
     * гэж тооцно. Cache service байхгүй бол throttle skip хийнэ (гол
     * хамгаалалт нь эрхийн шалгалт).
     */
    private const RATE_LIMIT_MAX = 30;
    private const RATE_LIMIT_WINDOW = 60;

    /**
     * moedit AI endpoint - HTML контент сайжруулах эсвэл зургаас текст таних (OCR).
     *
     * Энэ endpoint нь 2 горимоор ажиллана:
     *   1. HTML mode (mode='html') - Контентыг Bootstrap 5 компонентуудаар сайжруулах
     *   2. Vision mode (mode='vision') - Зураг дээрх текстийг таниж HTML болгох (OCR)
     *
     * Хүсэлт (HTML mode):
     * -----------------------------------------------------------------------------
     *   POST /dashboard/moedit/ai
     *   Content-Type: application/json
     *   Body: {
     *     "mode": "html",
     *     "html": "<p>Контент...</p>",
     *     "prompt": "Bootstrap card болгон хувирга"
     *   }
     *
     * Хүсэлт (Vision/OCR mode):
     * -----------------------------------------------------------------------------
     *   POST /dashboard/moedit/ai
     *   Content-Type: application/json
     *   Body: {
     *     "mode": "vision",
     *     "images": ["data:image/png;base64,...", "data:image/jpeg;base64,..."],
     *     "prompt": "Зураг дээрх текстийг HTML хүснэгт болго"
     *   }
     *
     * Хариу:
     * -----------------------------------------------------------------------------
     *   Амжилттай: { "status": "success", "html": "<div class='card'>..." }
     *   Алдаа:     { "status": "error", "message": "Алдааны тайлбар" }
     *
     * HTTP статус кодууд:
     *   200 - Амжилттай
     *   400 - Буруу хүсэлт (хоосон контент, prompt гэх мэт)
     *   401 - Нэвтрээгүй хэрэглэгч
     *   500 - Серверийн алдаа (API key байхгүй, OpenAI алдаа гэх мэт)
     *
     * @return void JSON хариу буцаана
     *
     * @throws \Exception Нэвтрээгүй бол (401)
     * @throws \Exception Контент үүсгэх/засах эрхгүй бол (403)
     * @throws \Exception Rate limit хэтэрсэн бол (429)
     * @throws \Exception API key тохируулаагүй бол (500)
     * @throws \InvalidArgumentException Контент эсвэл prompt хоосон бол (400)
     * @throws \InvalidArgumentException Vision mode-д зураг байхгүй / хэт олон бол (400)
     */
    public function moeditAI(): void
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception('Unauthorized', 401);
            }

            // Зөвхөн контент үүсгэх/засах эрхтэй хэрэглэгч AI-г ашиглана.
            // moedit editor нь news/pages (content) болон products модулиудад
            // ашиглагддаг тул тэдгээрийн insert/update эрхээр gate хийнэ.
            // Ингэснээр эрхгүй (viewer г.м.) хэрэглэгч OpenAI key-г урвуулан
            // ашиглах, төсөв шатаах боломжийг хаана. (coder бүх шалгалтыг давна.)
            if (!$this->canUseAI()) {
                throw new \Exception('Forbidden', 403);
            }

            // API key шалгах ($_ENV эсвэл getenv)
            $apiKey = $_ENV['RAPTOR_OPENAI_API_KEY'] ?? \getenv('RAPTOR_OPENAI_API_KEY');
            if (empty($apiKey)) {
                throw new \Exception(
                    'OpenAI API key тохируулаагүй байна. ' .
                    '.env файлд RAPTOR_OPENAI_API_KEY нэмнэ үү.',
                    500
                );
            }

            // PSR-7 request body унших
            $body = $this->getParsedBody();
            $html = $body['html'] ?? '';
            $mode = $body['mode'] ?? 'html'; // 'html', 'vision', 'clean'
            $customPrompt = $body['prompt'] ?? ''; // Frontend-ээс ирсэн custom prompt

            // Vision mode-д html шалгахгүй (images массив ашиглана)
            if ($mode !== 'vision' && empty(\trim($html))) {
                throw new \InvalidArgumentException(
                    'Контент хоосон байна.',
                    400
                );
            }

            // Заавал нэмэгдэх систем заавар (frontend-д харагдахгүй)
            $systemInstruction = "STRICT RULES:\n"
                . "1. Return ONLY raw HTML content. Never wrap in ```html or markdown.\n"
                . "2. NEVER include HTML comments (<!-- -->). This is critical.\n"
                . "3. NEVER include <!DOCTYPE>, <html>, <head>, <body>, <script>, <style> tags.\n"
                . "4. Return clean, semantic HTML only.\n\n";

            // Frontend-ээс prompt ирээгүй бол алдаа
            if (empty(\trim($customPrompt))) {
                throw new \InvalidArgumentException('Prompt хоосон байна.', 400);
            }

            // Mode-оос хамааран API дуудлага ялгаатай
            if ($mode === 'vision') {
                // OCR mode: Base64 зургуудыг хүлээн авах
                $base64Images = $body['images'] ?? [];

                if (!\is_array($base64Images) || empty($base64Images)) {
                    throw new \InvalidArgumentException(
                        'Зураг олдсонгүй. OCR ашиглахын тулд зураг оруулна уу.',
                        400
                    );
                }

                // Зургийн тоог хязгаарлах - зураг бүр тусдаа vision дуудлага
                // үүсгэдэг тул хязгааргүй бол нэг хүсэлтээр төсөв шатааж болно.
                if (\count($base64Images) > self::MAX_IMAGES_PER_REQUEST) {
                    throw new \InvalidArgumentException(
                        'Хэт олон зураг. Нэг удаад дээд тал нь ' . self::MAX_IMAGES_PER_REQUEST . ' зураг.',
                        400
                    );
                }

                // Rate limit: зураг бүр нэг дуудлага гэж тооцно
                $this->enforceRateLimit(\count($base64Images));

                // Frontend prompt + system instruction
                $prompt = $systemInstruction . $customPrompt;

                // Зураг тус бүрийг тусад нь боловсруулж, үр дүнг нэгтгэх
                $results = [];
                foreach ($base64Images as $base64Image) {
                    $singleImageResult = $this->callOpenAIVision($apiKey, $prompt, $base64Image);
                    if (!empty(\trim($singleImageResult))) {
                        $results[] = $singleImageResult;
                    }
                }

                $response = \implode("\n\n", $results);
            } else {
                // Rate limit: HTML mode нэг дуудлага
                $this->enforceRateLimit(1);

                // HTML mode: Frontend prompt + system instruction + контент
                $prompt = $systemInstruction . $customPrompt . "\n\n---КОНТЕНТ ЭХЛЭЛ---\n{$html}\n---КОНТЕНТ ТӨГСГӨЛ---";
                $response = $this->callOpenAI($apiKey, $prompt);
            }
            $this->respondJSON([
                'status' => 'success',
                'html'   => $response
            ]);
        } catch (\Throwable $e) {
            // Exception code нь заримдаа тоон бус string байж болно (OpenAI-ийн
            // алдааны код, жишээ 'invalid_api_key') тул HTTP статуст int болгоно.
            $code = (int) $e->getCode();
            $this->respondJSON([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], ($code >= 400 && $code <= 599) ? $code : 500);
        }
    }

    /**
     * Хэрэглэгч AI endpoint-ийг ашиглах эрхтэй эсэх.
     *
     * moedit editor нь news/pages (content) болон products модулиудад
     * ашиглагддаг тул тэдгээрийн insert/update эрхийн аль нэг байвал л
     * зөвшөөрнө. Зөвхөн нэвтэрсэн (эрхгүй viewer) хангалтгүй - эс бөгөөс
     * контентын ямар ч эрхгүй хэрэглэгч OpenAI key-г урвуулан ашиглана.
     * (isUserCan нь coder-т үргэлж true буцаадаг тул coder давна.)
     *
     * @return bool
     */
    private function canUseAI(): bool
    {
        return $this->isUserCan('system_content_insert')
            || $this->isUserCan('system_content_update')
            || $this->isUserCan('system_product_insert')
            || $this->isUserCan('system_product_update');
    }

    /**
     * Хэрэглэгч бүрийн OpenAI дуудлагын давтамжийг хязгаарлана.
     *
     * RATE_LIMIT_WINDOW секундын fixed window дотор RATE_LIMIT_MAX
     * дуудлага зөвшөөрнө. State-ийг cache-д хадгална (dashboard-д session
     * эрт хаагддаг тул $_SESSION-д найдаж болохгүй). Cache байхгүй бол
     * skip - throttle нь нэмэлт давхарга, гол хамгаалалт нь canUseAI().
     *
     * @param int $cost Энэ хүсэлтийн үүсгэх дуудлагын тоо (vision = зургийн тоо)
     * @throws \Exception Хязгаар хэтэрсэн бол (429)
     */
    private function enforceRateLimit(int $cost): void
    {
        if (!$this->hasService('cache')) {
            return;
        }
        $cache = $this->getService('cache');
        $key = 'ai_ratelimit.' . $this->getUserId();
        $now = \time();

        $entry = $cache->get($key);
        if (!\is_array($entry) || ($entry['reset'] ?? 0) <= $now) {
            $entry = ['count' => 0, 'reset' => $now + self::RATE_LIMIT_WINDOW];
        }

        if ($entry['count'] + $cost > self::RATE_LIMIT_MAX) {
            throw new \Exception('Хэт олон хүсэлт. Түр хүлээгээд дахин оролдоно уу.', 429);
        }

        $entry['count'] += $cost;
        // TTL нь reset хүртэлх үлдсэн хугацаа (window-той тэнцэх дээд хэмжээтэй)
        $cache->set($key, $entry, \max(1, $entry['reset'] - $now));
    }

    /**
     * OpenAI Chat Completions API дуудах (текст боловсруулалт).
     *
     * Хямд ангиллын моделиэр HTML контентыг Bootstrap 5 компонентуудаар
     * сайжруулах хүсэлт илгээнэ. Markdown code block хариуг автоматаар цэвэрлэнэ.
     *
     * API тохиргоо:
     *   - Model: RAPTOR_OPENAI_MODEL (.env, default: DEFAULT_MODEL - хурдан, хямд)
     *   - Temperature: 0.3 (тогтвортой үр дүн)
     *   - Max tokens: 4096
     *   - HTTP/1.1 протокол (HTTP/2 алдаанаас зайлсхийх)
     *
     * @param string $apiKey OpenAI API түлхүүр (sk-proj-... эсвэл sk-...)
     * @param string $prompt Системийн заавар болон хэрэглэгчийн контент агуулсан prompt
     *
     * @return string Цэвэрлэгдсэн HTML хариу (```html wrapper-гүй)
     *
     * @throws \Exception OpenAI API алдаа буцаавал (rate limit, invalid key гэх мэт)
     *
     * @see https://platform.openai.com/docs/api-reference/chat/create
     */
    private function callOpenAI(string $apiKey, string $prompt): string
    {
        $payload = [
            'model'       => $_ENV['RAPTOR_OPENAI_MODEL'] ?? self::DEFAULT_MODEL,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'You are an HTML content enhancer using Bootstrap 5. '
                        . 'Return ONLY clean HTML. NEVER include HTML comments (<!-- -->), '
                        . 'markdown fences, or document-level tags (DOCTYPE, html, head, body, script, style).'
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.3,
            'max_tokens'  => 4096
        ];
        $data = (new JSONClient())->post(
            'https://api.openai.com/v1/chat/completions',
            $payload,
            ['Authorization' => 'Bearer ' . $apiKey],
            [\CURLOPT_HTTP_VERSION => \CURL_HTTP_VERSION_1_1]
        );
        if (isset($data['error'])) {
            throw new \Exception('OpenAI API алдаа: ' . ($data['error']['message'] ?? 'Unknown error'), $data['error']['code'] ?? 500);
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        // Markdown code block байвал арилгах
        $content = \preg_replace('/^```html?\s*/i', '', $content);
        $content = \preg_replace('/\s*```$/', '', $content);
        return \trim($content);
    }

    /**
     * OpenAI Vision API дуудах (зураг таних / OCR).
     *
     * Vision чадвартай моделиэр зураг дээрх текстийг таниж HTML болгон
     * хувиргана. Нэг удаад нэг зураг боловсруулна.
     *
     * API тохиргоо:
     *   - Model: RAPTOR_OPENAI_VISION_MODEL (.env, default: DEFAULT_VISION_MODEL)
     *   - Temperature: 0.3 (тогтвортой үр дүн)
     *   - Max tokens: 4096
     *   - Image detail: high (өндөр нарийвчлал)
     *   - HTTP/1.1 протокол (HTTP/2 алдаанаас зайлсхийх)
     *
     * Дэмжигдэх зургийн формат:
     *   - Base64 data URL: data:image/png;base64,... эсвэл data:image/jpeg;base64,...
     *   - HTTPS URL: https://example.com/image.png
     *
     * @param string $apiKey   OpenAI API түлхүүр (sk-proj-... эсвэл sk-...)
     * @param string $prompt   Зургийг хэрхэн боловсруулах заавар
     * @param string $imageUrl Зургийн base64 data URL эсвэл HTTPS URL
     *
     * @return string Цэвэрлэгдсэн HTML хариу (```html wrapper-гүй)
     *
     * @throws \Exception OpenAI API алдаа буцаавал (rate limit, invalid key, image error гэх мэт)
     *
     * @see https://platform.openai.com/docs/guides/vision
     */
    private function callOpenAIVision(string $apiKey, string $prompt, string $imageUrl): string
    {
        $payload = [
            'model'       => $_ENV['RAPTOR_OPENAI_VISION_MODEL'] ?? self::DEFAULT_VISION_MODEL,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'You are an OCR specialist that extracts text from images and converts it to clean HTML. '
                        . 'Return ONLY clean HTML. NEVER include HTML comments (<!-- -->), '
                        . 'markdown fences, or document-level tags (DOCTYPE, html, head, body, script, style). '
                        . 'Preserve the original document structure: headings, paragraphs, tables, lists.'
                ],
                [
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        [
                            'type' => 'image_url',
                            'image_url' => ['url' => $imageUrl, 'detail' => 'high']
                        ]
                    ]
                ]
            ],
            'temperature' => 0.3,
            'max_tokens'  => 4096
        ];
        $data = (new JSONClient())->post(
            'https://api.openai.com/v1/chat/completions',
            $payload,
            ['Authorization' => 'Bearer ' . $apiKey],
            [\CURLOPT_HTTP_VERSION => \CURL_HTTP_VERSION_1_1]
        );
        if (isset($data['error'])) {
            throw new \Exception('OpenAI API алдаа: ' . ($data['error']['message'] ?? 'Unknown error'), $data['error']['code'] ?? 500);
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        // Markdown code block байвал арилгах
        $content = \preg_replace('/^```html?\s*/i', '', $content);
        $content = \preg_replace('/\s*```$/', '', $content);
        return \trim($content);
    }
}
