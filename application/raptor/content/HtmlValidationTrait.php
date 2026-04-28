<?php

namespace Raptor\Content;

/**
 * Trait HtmlValidationTrait
 *
 * HTML контентын tag бүтэц эвдэрсэн эсэхийг шалгах.
 * Дутуу comment болон эвдэрсэн tag-аас болж контент алга болсон
 * эсэхийг илрүүлнэ.
 *
 * Pages, News, Products зэрэг content модулиудад ашиглана.
 *
 * @package Raptor
 */
trait HtmlValidationTrait
{
    /**
     * HTML контентын tag бүтэц эвдэрсэн эсэхийг шалгана.
     *
     * Дутуу comment болон эвдэрсэн tag-аас болж контент алга болсон
     * эсэхийг илрүүлж InvalidArgumentException шидэнэ.
     *
     * @param string $html  Шалгах HTML контент
     * @throws \InvalidArgumentException  Tag бүтэц эвдэрсэн тохиолдолд
     */
    protected function validateHtmlContent(string $html): void
    {
        if (\trim($html) === '') {
            return;
        }

        /* Дутуу хаалттай HTML comment шалгах */
        $opens = \substr_count($html, '<!--');
        $closes = \substr_count($html, '-->');
        if ($opens > $closes) {
            throw new \InvalidArgumentException(
                $this->text('html-tag-broken'),
                400
            );
        }

        /* DOMDocument-ээр parse хийж контент алга болсон эсэхийг шалгах.
           Эвдэрсэн tag/comment browser-д parse хийхэд контентыг "залгидаг"
           тул эх текстийн уртыг parse хийсэн текстийн урттай харьцуулна. */
        $originalText = \preg_replace('/<!--[\s\S]*?-->/', '', $html);
        $originalText = \strip_tags($originalText);
        $originalText = \html_entity_decode($originalText, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $originalText = \preg_replace('/\s+/', ' ', \trim($originalText));

        $prev = \libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML(
            '<!DOCTYPE html><html><body>' . $html . '</body></html>',
            \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD | \LIBXML_NOERROR
        );
        \libxml_clear_errors();
        \libxml_use_internal_errors($prev);

        $parsedText = \preg_replace('/\s+/', ' ', \trim($doc->textContent ?? ''));
        $originalLen = \mb_strlen($originalText);

        /* Эх текстийн 20%-аас дээш хэсэг алга болсон бол контент эвдэрсэн */
        if ($originalLen > 20) {
            $parsedLen = \mb_strlen($parsedText);
            $lostRatio = 1 - ($parsedLen / $originalLen);
            if ($lostRatio > 0.2) {
                throw new \InvalidArgumentException(
                    $this->text('html-tag-broken'),
                    400
                );
            }
        }
    }
}
