<?php

namespace Raptor\Content;

use Psr\SimpleCache\CacheInterface;

/**
 * Class TemplateService
 *
 * LocalizedModel-ийн templates content хүснэгтээс keyword-аар орчуулга татах service.
 *
 * Cache strategy (2 mode):
 *
 *  1) Cache enabled (CacheInterface inject хийгдсэн):
 *     - Хэлний БҮХ template-уудыг нэг "reference.templates.{code}" entry болгож хадгална
 *     - getByKeyword/getByKeywords нь cached map-аас array lookup хийнэ
 *     - DB query: бүх келвордыг 1 удаа цуглуулах нэг query (anh load)
 *
 *  2) Cache disabled (cache = null):
 *     - getByKeyword:  WHERE keyword=? - зөвхөн уг 1 row-г татна
 *     - getByKeywords: WHERE keyword IN (...) - зөвхөн заасан мөрүүдийг татна
 *     - Бүх 300 template-ийг ачаалахаас зайлсхийнэ
 *
 * Энэ нь cache байхгүй deployment-д ч (cPanel write permission байхгүй гэх мэт)
 * DB-аас илүү хэмжээний өгөгдөл татахыг хориглоно.
 *
 * @package Raptor\Content
 */
class TemplateService
{
    /** @var \PDO Database connection instance */
    protected \PDO $pdo;

    /** @var CacheInterface|null Optional PSR-16 cache (null bol DB-ees shууд унш) */
    protected ?CacheInterface $cache;

    /**
     * @param \PDO $pdo Database connection
     * @param CacheInterface|null $cache Optional cache (ContainerMiddleware-ээс ирнэ)
     */
    public function __construct(\PDO $pdo, ?CacheInterface $cache = null)
    {
        $this->pdo = $pdo;
        $this->cache = $cache;
    }

    /**
     * Нэг keyword-аар template татах.
     *
     * @param string $keyword Template keyword (жишээ: 'tos', 'pp', 'request-new-user')
     * @param string $code Language code (жишээ: 'mn', 'en')
     * @return array|null Сонгосон хэлний контент эсвэл null
     */
    public function getByKeyword(string $keyword, string $code): ?array
    {
        // Cache enabled бол бүх map-аар lookup
        if ($this->cache !== null) {
            $all = $this->loadAllForCode($code);
            return $all[$keyword] ?? null;
        }

        // Cache disabled - зөвхөн уг 1 row-г DB-ээс татна
        $referenceModel = new ReferenceModel($this->pdo);
        $referenceModel->setTable('templates');

        $reference = $referenceModel->getRowWhere([
            'c.code'    => $code,
            'p.keyword' => $keyword
        ]);

        if (empty($reference) || empty($reference['localized'][$code])) {
            return null;
        }
        return $reference['localized'][$code];
    }

    /**
     * Олон keyword-аар template-ууд татах.
     *
     * @param array $keywords Template keyword-уудын массив (жишээ: ['tos', 'pp'])
     * @param string $code Language code (жишээ: 'mn', 'en')
     * @return array Keyword => Сонгосон хэлний контент бүтэцтэй массив
     */
    public function getByKeywords(array $keywords, string $code): array
    {
        if (empty($keywords)) {
            return [];
        }

        // Cache enabled бол бүх map-аар lookup
        if ($this->cache !== null) {
            $all = $this->loadAllForCode($code);
            $result = [];
            foreach ($keywords as $keyword) {
                if (isset($all[$keyword])) {
                    $result[$keyword] = $all[$keyword];
                }
            }
            return $result;
        }

        // Cache disabled - зөвхөн заасан keyword-уудыг IN(...) batch query
        $referenceModel = new ReferenceModel($this->pdo);
        $referenceModel->setTable('templates');

        $placeholders = [];
        $params = [':code' => $code];
        foreach ($keywords as $i => $keyword) {
            $placeholder = ":kw_$i";
            $placeholders[] = $placeholder;
            $params[$placeholder] = $keyword;
        }

        $rows = $referenceModel->getRows([
            'WHERE' => 'c.code=:code AND p.keyword IN (' . \implode(', ', $placeholders) . ')',
            'PARAM' => $params
        ]);

        $result = [];
        foreach ($rows as $row) {
            $keyword = $row['keyword'] ?? null;
            if ($keyword !== null && isset($row['localized'][$code])) {
                $result[$keyword] = $row['localized'][$code];
            }
        }
        return $result;
    }

    /**
     * Хэлний бүх template-уудыг нэг дор ачаалаад cache-д хадгална.
     *
     * Зөвхөн cache enabled үед дуудагдана. Cache miss үед DB-ээс read хийж
     * cache-д бичнэ; дараа дараагийн дуудлагууд cached map-аар хариулна.
     *
     * @param string $code Language code
     * @return array Keyword => template content map
     */
    private function loadAllForCode(string $code): array
    {
        $cacheKey = "reference.templates.$code";

        try {
            $cached = $this->cache?->get($cacheKey);
            if (\is_array($cached)) {
                return $cached;
            }
        } catch (\Throwable) {
            // Cache унших алдаа - DB fallback
        }

        $referenceModel = new ReferenceModel($this->pdo);
        $referenceModel->setTable('templates');

        $rows = $referenceModel->getRows([
            'WHERE' => 'c.code=:code',
            'PARAM' => [':code' => $code]
        ]);

        $map = [];
        foreach ($rows as $row) {
            $keyword = $row['keyword'] ?? null;
            if ($keyword !== null && isset($row['localized'][$code])) {
                $map[$keyword] = $row['localized'][$code];
            }
        }

        try {
            $this->cache?->set($cacheKey, $map);
        } catch (\Throwable) {
            // Cache бичих алдаа нь read flow-г таслах ёсгүй
        }

        return $map;
    }
}
