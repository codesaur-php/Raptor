<?php

namespace Raptor\Content;

/**
 * Trait ReadNewsTrait
 *
 * Public вэбсайтын visitor-ийн "уншсан мэдээ" байдлыг cookie-р хянах.
 *
 * Cookie-д зөвхөн сүүлийн READ_NEWS_MAX (50) ID хадгалагдана (FIFO trim),
 * TTL READ_NEWS_TTL (90 хоног). Session write шаардлагагүй тул энгийн
 * `/news/{slug}` route (session-гүй) дээрээс ашиглаж болно.
 *
 * @package Raptor\Content
 */
trait ReadNewsTrait
{
    protected const READ_NEWS_COOKIE = 'raptor_read_news';
    protected const READ_NEWS_MAX = 50;
    protected const READ_NEWS_TTL = 90 * 86400;

    /**
     * Cookie-оос уншсан мэдээний ID-нуудыг O(1) lookup map хэлбэрээр авах.
     *
     * @return array<int,bool>
     */
    protected function getReadNewsMap(): array
    {
        $raw = $_COOKIE[self::READ_NEWS_COOKIE] ?? '';
        if ($raw === '') {
            return [];
        }
        $ids = \json_decode($raw, true);
        if (!\is_array($ids)) {
            return [];
        }
        $map = [];
        foreach ($ids as $id) {
            if (\is_int($id) || (\is_string($id) && \ctype_digit($id))) {
                $map[(int)$id] = true;
            }
        }
        return $map;
    }

    /**
     * Мэдээг уншсан гэж тэмдэглэх (cookie бичнэ).
     * render()-ээс ӨМНӨ дуудах ёстой, эс бөгөөс header-ийг оройтож илгээнэ.
     */
    protected function markNewsAsRead(int $id): void
    {
        $raw = $_COOKIE[self::READ_NEWS_COOKIE] ?? '';
        $ids = [];
        if ($raw !== '') {
            $decoded = \json_decode($raw, true);
            if (\is_array($decoded)) {
                foreach ($decoded as $x) {
                    if ((\is_int($x) || (\is_string($x) && \ctype_digit($x))) && (int)$x !== $id) {
                        $ids[] = (int)$x;
                    }
                }
            }
        }
        $ids[] = $id;
        if (\count($ids) > self::READ_NEWS_MAX) {
            $ids = \array_slice($ids, -self::READ_NEWS_MAX);
        }
        \setcookie(self::READ_NEWS_COOKIE, \json_encode($ids), [
            'expires'  => \time() + self::READ_NEWS_TTL,
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Мэдээний жагсаалтын элемент бүрт `is_read` flag тавих.
     */
    protected function decorateReadNews(array &$records): void
    {
        $map = $this->getReadNewsMap();
        if (empty($map)) {
            foreach ($records as &$r) {
                $r['is_read'] = false;
            }
            return;
        }
        foreach ($records as &$r) {
            $r['is_read'] = isset($map[(int)($r['id'] ?? 0)]);
        }
    }
}
