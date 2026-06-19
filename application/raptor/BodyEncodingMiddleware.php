<?php

namespace Raptor;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class BodyEncodingMiddleware
 *
 * Request body-гийн талбаруудыг base64-аас decode хийх middleware.
 *
 * Зарим shared hosting (cPanel/LiteSpeed)-ийн mod_security WAF нь POST body
 * дотор HTML/JS-төстэй агуулга (жишээ: news/page-ийн rich-text content,
 * <a>, <img>, <script>, inline style) илрэхэд XSS халдлага гэж андуурч
 * 403 Forbidden буцаадаг. Үүний улмаас Facebook-оос хуулсан зэрэг агуулга
 * бүхий мэдээ хадгалах боломжгүй болдог.
 *
 * Шийдэл: клиент тал (csrfFetch) mutating хүсэлтийн form талбаруудын УТГЫГ
 * base64-аар кодлож, raw body-г WAF-д ил харагдахгүй болгоно. Энэ middleware
 * нь X-Body-Encoding: base64 header байгаа үед parsedBody-гийн scalar string
 * утгуудыг буцааж decode хийнэ. Талбарын нэрс кодлогдохгүй (нэр нь XSS
 * trigger биш) тул routing/handler өөрчлөгдөхгүй.
 *
 * Клиент талын кодлолтыг RAPTOR_WAF_BODY_ENCODING env-ээр асаах/унтраана
 * (Controller::template() <meta name="waf-body-encoding"> гаргаж, csrfFetch
 * түүнийг уншина). Энэ middleware нь header-gated тул env-ээс үл хамааран
 * аюулгүй - header байхгүй бол юу ч хийхгүй.
 *
 * Host-аас үл хамаарна. Файл (uploadedFiles) огт хөндөгдөхгүй.
 *
 * Аюулгүй байдал: зөвхөн header байгаа үед, зөвхөн strict base64 (valid)
 * утгыг decode хийнэ. Decode амжилтгүй бол анхны утгыг хэвээр үлдээнэ.
 */
class BodyEncodingMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (\strtolower(\trim($request->getHeaderLine('X-Body-Encoding'))) === 'base64') {
            // getParsedBody() нь PSR-7-ийн дагуу null|array|object буцааж болно.
            // Зөвхөн array (хоосон биш) тохиолдлыг decode хийнэ: манай client
            // (csrfFetch) зөвхөн FormData талбаруудыг кодолдог тул encode хийсэн
            // body үргэлж array хэлбэрээр ирнэ. object (жишээ нь JSON-оос
            // deserialize хийсэн) болон null-ийг зориуд хөндөхгүй - object-ийг
            // задлахын тулд төрлийг нь өөрчлөх шаардлагатай бөгөөд энэ нь object
            // хүлээж буй downstream кодыг эвдэж болзошгүй.
            $body = $request->getParsedBody();
            if (\is_array($body) && $body !== []) {
                $request = $request->withParsedBody($this->decode($body));
            }
            // Directive-ийг хэрэглэсэн тул арилгана (consume-after-use). Body
            // аль хэдийн decode хийгдсэн тул header үлдвэл "энэ body base64"
            // гэсэн худал зарлал болж, дотоод re-dispatch үед давхар decode
            // хийж эвдэх эрсдэлтэй. Header-гүй болгосноор үйлдэл idempotent.
            $request = $request->withoutHeader('X-Body-Encoding');
        }

        return $handler->handle($request);
    }

    /**
     * parsedBody-гийн scalar string утгуудыг рекурсивээр base64-аас decode хийх.
     * Талбарын нэрс хэвээр үлдэнэ. Decode амжилтгүй (invalid base64) бол анхны
     * утгыг хэвээр буцаана.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function decode(array $data): array
    {
        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $data[$key] = $this->decode($value);
            } elseif (\is_string($value) && $value !== '') {
                $decoded = \base64_decode($value, true);
                if ($decoded !== false) {
                    $data[$key] = $decoded;
                }
            }
        }

        return $data;
    }
}
