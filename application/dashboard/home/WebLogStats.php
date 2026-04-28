<?php

namespace Dashboard\Home;

use codesaur\DataObject\Constants;

/**
 * Class WebLogStats
 * ------------------------------------------------------------------
 * Вэб сайтын зочлолын статистик мэдээллийг тооцоолох,
 * web_log_cache хүснэгтийг удирдах зэрэг бүх stats логикийг агуулна.
 *
 * HomeController-оос салгаж авсан бүрэн бие даасан анги.
 *
 * @package Dashboard\Home
 */
class WebLogStats
{
    use \codesaur\DataObject\PDOTrait;

    /**
     * WebLogStats constructor.
     *
     * @param \PDO $pdo Database connection instance
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Вэб сайтын зочлолын статистик мэдээллийг массиваар буцаах.
     *
     * @return array Статистик өгөгдөл
     */
    public function getStats(): array
    {
        if (!$this->hasTable('web_log')) {
            return [
                'today' => 0, 'week' => 0, 'month' => 0, 'total' => 0,
                'chart_data' => [], 'actions' => [],
                'top_pages' => [], 'top_news' => [], 'top_ips' => []
            ];
        }

        $this->ensureCacheTable();
        $this->refreshCache();

        $today = \date('Y-m-d');
        $tomorrow = \date('Y-m-d', \strtotime('+1 day'));
        $weekAgo = \date('Y-m-d', \strtotime('-7 days'));
        $monthAgo = \date('Y-m-d', \strtotime('-30 days'));
        $data = [];

        // Өнөөдрийн live count
        $stmt = $this->prepare(
            "SELECT COUNT(*) AS cnt FROM web_log
             WHERE created_at >= :today AND created_at < :tomorrow"
        );
        $stmt->bindValue(':today', $today);
        $stmt->bindValue(':tomorrow', $tomorrow);
        $stmt->execute();
        $todayCount = (int)$stmt->fetch()['cnt'];

        // Stat cards - cache-ээс
        $data['today'] = $todayCount;

        $stmt = $this->prepare(
            "SELECT COALESCE(SUM(visit_count),0) AS cnt FROM web_log_cache
             WHERE cache_date >= :week_ago AND cache_date < :today"
        );
        $stmt->bindValue(':week_ago', $weekAgo);
        $stmt->bindValue(':today', $today);
        $stmt->execute();
        $data['week'] = (int)$stmt->fetch()['cnt'] + $todayCount;

        $stmt = $this->prepare(
            "SELECT COALESCE(SUM(visit_count),0) AS cnt FROM web_log_cache
             WHERE cache_date >= :month_ago AND cache_date < :today"
        );
        $stmt->bindValue(':month_ago', $monthAgo);
        $stmt->bindValue(':today', $today);
        $stmt->execute();
        $data['month'] = (int)$stmt->fetch()['cnt'] + $todayCount;

        $stmt = $this->prepare("SELECT COALESCE(SUM(visit_count),0) AS cnt FROM web_log_cache");
        $stmt->execute();
        $data['total'] = (int)$stmt->fetch()['cnt'] + $todayCount;

        // Лог хамрах хугацаа
        $stmt = $this->prepare("SELECT MIN(cache_date) AS first_at FROM web_log_cache");
        $stmt->execute();
        $data['log_from'] = $stmt->fetch()['first_at'];
        $data['log_to'] = $today;

        // 30 day chart - cache-ээс
        $stmt = $this->prepare(
            "SELECT cache_date AS date, visit_count AS count FROM web_log_cache
             WHERE cache_date >= :month_ago AND cache_date < :today
             ORDER BY cache_date ASC"
        );
        $stmt->bindValue(':month_ago', $monthAgo);
        $stmt->bindValue(':today', $today);
        $chartData = [];
        if ($stmt->execute()) {
            while ($row = $stmt->fetch()) {
                $chartData[] = ['date' => $row['date'], 'count' => (int)$row['count']];
            }
        }
        $chartData[] = ['date' => $today, 'count' => $todayCount];
        $data['chart_data'] = $chartData;

        // Өмнөх өдрүүдийн cache-лэгдсэн JSON-г нэгтгэх (30 хоног)
        $stmt = $this->prepare(
            "SELECT cache_date, actions_data, pages_data, news_data, products_data, orders_data, ips_data, ua_data FROM web_log_cache
             WHERE cache_date >= :month_ago AND cache_date < :today
               AND actions_data IS NOT NULL"
        );
        $stmt->bindValue(':month_ago', $monthAgo);
        $stmt->bindValue(':today', $today);
        $stmt->execute();

        $sevenDaysAgo = \date('Y-m-d', \strtotime('-7 days'));
        $actionsMerge = [];
        $pagesMerge = [];
        $newsMerge = [];
        $productsMerge = [];
        $pagesWeek = [];
        $newsWeek = [];
        $productsWeek = [];
        $ipsMerge = [];
        $uaMerge = [];
        while ($row = $stmt->fetch()) {
            $this->mergeJsonCounts($actionsMerge, $row['actions_data']);
            $this->mergeJsonCounts($pagesMerge, $row['pages_data']);
            $this->mergeJsonCounts($newsMerge, $row['news_data']);
            $this->mergeJsonCounts($productsMerge, $row['products_data'] ?? null);
            $this->mergeJsonCounts($ipsMerge, $row['ips_data']);
            $this->mergeJsonCounts($uaMerge, $row['ua_data']);
            if ($row['cache_date'] >= $sevenDaysAgo) {
                $this->mergeJsonCounts($pagesWeek, $row['pages_data']);
                $this->mergeJsonCounts($newsWeek, $row['news_data']);
                $this->mergeJsonCounts($productsWeek, $row['products_data'] ?? null);
            }
        }

        // Өнөөдрийн live мэдээллийг нэг query-гээр авч PHP дээр ангилна.
        // 6 тусдаа GROUP BY query-г нэгтгэснээр MySQL-ийн түр хүснэгт (tmp table) үүсгэхгүй.
        $pagesToday = [];
        $newsToday = [];
        $productsToday = [];
        $stmt = $this->prepare(
            "SELECT {$this->jsonVal('context', '$.action')} AS action,
                    COALESCE({$this->jsonVal('context', '$.server_request.code')}, '?') AS code,
                    COALESCE({$this->jsonVal('context', '$.record_id')}, '0') AS rid,
                    {$this->jsonVal('context', '$.title')} AS title,
                    {$this->jsonVal('context', '$.customer_name')} AS customer_name,
                    {$this->jsonVal('context', '$.server_request.remote_addr')} AS ip,
                    {$this->jsonVal('context', '$.server_request.user_agent')} AS ua
             FROM web_log
             WHERE created_at >= :today AND created_at < :tomorrow"
        );
        $stmt->bindValue(':today', $today);
        $stmt->bindValue(':tomorrow', $tomorrow);
        if ($stmt->execute()) {
            while ($row = $stmt->fetch()) {
                $action = $row['action'] ?? null;
                if ($action !== null) {
                    $actionsMerge[$action] = ($actionsMerge[$action] ?? 0) + 1;
                }
                if ($action === 'page' && $row['title'] !== null) {
                    $key = $row['code'] . '|' . $row['rid'] . '|' . $row['title'];
                    $pagesToday[$key] = ($pagesToday[$key] ?? 0) + 1;
                    $pagesMerge[$key] = ($pagesMerge[$key] ?? 0) + 1;
                    $pagesWeek[$key] = ($pagesWeek[$key] ?? 0) + 1;
                } elseif ($action === 'news' && $row['title'] !== null) {
                    $key = $row['code'] . '|' . $row['rid'] . '|' . $row['title'];
                    $newsToday[$key] = ($newsToday[$key] ?? 0) + 1;
                    $newsMerge[$key] = ($newsMerge[$key] ?? 0) + 1;
                    $newsWeek[$key] = ($newsWeek[$key] ?? 0) + 1;
                } elseif ($action === 'product' && $row['title'] !== null) {
                    $key = $row['code'] . '|' . $row['rid'] . '|' . $row['title'];
                    $productsToday[$key] = ($productsToday[$key] ?? 0) + 1;
                    $productsMerge[$key] = ($productsMerge[$key] ?? 0) + 1;
                    $productsWeek[$key] = ($productsWeek[$key] ?? 0) + 1;
                }
                if ($row['ip'] !== null) {
                    $ipsMerge[$row['ip']] = ($ipsMerge[$row['ip']] ?? 0) + 1;
                }
                if ($row['ua'] !== null) {
                    $uaMerge[$row['ua']] = ($uaMerge[$row['ua']] ?? 0) + 1;
                }
            }
        }

        // Нэгтгэсэн дүнг эрэмбэлж буцаах
        \arsort($actionsMerge);
        $data['actions'] = [];
        foreach ($actionsMerge as $action => $count) {
            $data['actions'][] = ['action' => $action, 'count' => $count];
        }

        $data['top_pages'] = $this->buildTop10($pagesMerge);
        $data['top_pages_week'] = $this->buildTop10($pagesWeek);
        $data['top_pages_today'] = $this->buildTop10($pagesToday);

        $data['top_news'] = $this->buildTop10($newsMerge);
        $data['top_news_week'] = $this->buildTop10($newsWeek);
        $data['top_news_today'] = $this->buildTop10($newsToday);

        $data['top_products'] = $this->buildTop10($productsMerge);
        $data['top_products_week'] = $this->buildTop10($productsWeek);
        $data['top_products_today'] = $this->buildTop10($productsToday);

        \arsort($ipsMerge);
        $data['top_ips'] = [];
        $i = 0;
        foreach ($ipsMerge as $ip => $count) {
            $data['top_ips'][] = ['ip' => $ip, 'count' => $count];
            if (++$i >= 10) break;
        }

        \arsort($uaMerge);
        $data['top_uas'] = [];
        $i = 0;
        foreach ($uaMerge as $ua => $count) {
            $data['top_uas'][] = ['ua' => $ua, 'count' => $count];
            if (++$i >= 10) break;
        }

        // Захиалгын статистик (orders хүснэгт)
        $data['orders_total'] = 0;
        $data['orders_new'] = 0;
        if ($this->hasTable('orders')) {
            $stmt = $this->prepare(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) AS new_count
                 FROM orders"
            );
            $stmt->execute();
            $r = $stmt->fetch();
            $data['orders_total'] = (int)$r['total'];
            $data['orders_new'] = (int)$r['new_count'];
        }

        return $data;
    }

    /**
     * Системийн бусад *_log хүснэгтүүдийн статистикийг массиваар буцаах.
     *
     * @return array
     */
    public function getLogStats(): array
    {
        $driver = $this->getDriverName();
        $today = \date('Y-m-d');
        $tomorrow = \date('Y-m-d', \strtotime('+1 day'));
        $weekAgo = \date('Y-m-d', \strtotime('-7 days'));

        if ($driver === Constants::DRIVER_PGSQL) {
            $stmt = $this->prepare(
                "SELECT tablename AS table_name FROM pg_tables
                 WHERE schemaname = 'public'
                   AND tablename LIKE '%_log'
                   AND tablename != 'web_log'
                   AND tablename != 'web_log_cache'
                 ORDER BY tablename"
            );
        } else {
            $stmt = $this->prepare(
                "SELECT TABLE_NAME AS table_name FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME LIKE '%\_log'
                   AND TABLE_NAME != 'web_log'
                   AND TABLE_NAME != 'web_log_cache'
                 ORDER BY TABLE_NAME"
            );
        }
        $stmt->execute();

        $logs = [];
        while ($row = $stmt->fetch()) {
            $tableName = $row['table_name'];
            $label = \str_replace('_log', '', $tableName);

            try {
                $q = $driver === Constants::DRIVER_PGSQL
                    ? "SELECT
                        COUNT(*) AS total,
                        SUM(CASE WHEN created_at >= :today AND created_at < :tomorrow THEN 1 ELSE 0 END) AS today,
                        SUM(CASE WHEN created_at >= :week_ago THEN 1 ELSE 0 END) AS week,
                        MAX(created_at) AS last_at
                       FROM \"$tableName\""
                    : "SELECT
                        COUNT(*) AS total,
                        SUM(CASE WHEN created_at >= :today AND created_at < :tomorrow THEN 1 ELSE 0 END) AS today,
                        SUM(CASE WHEN created_at >= :week_ago THEN 1 ELSE 0 END) AS week,
                        MAX(created_at) AS last_at
                       FROM `$tableName`";

                $s = $this->prepare($q);
                $s->bindValue(':today', $today);
                $s->bindValue(':tomorrow', $tomorrow);
                $s->bindValue(':week_ago', $weekAgo);
                $s->execute();
                $r = $s->fetch();

                $logs[] = [
                    'table' => $tableName,
                    'label' => $label,
                    'total' => (int)$r['total'],
                    'today' => (int)$r['today'],
                    'week' => (int)$r['week'],
                    'last_at' => $r['last_at']
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        return $logs;
    }

    /**
     * web_log_cache хүснэгт байгаа эсэхийг шалгаж, байхгүй бол үүсгэх.
     */
    private function ensureCacheTable(): void
    {
        $driver = $this->getDriverName();
        if ($driver === Constants::DRIVER_PGSQL) {
            $this->prepare(
                "CREATE TABLE IF NOT EXISTS web_log_cache (
                    cache_date DATE PRIMARY KEY,
                    visit_count INT NOT NULL DEFAULT 0,
                    actions_data TEXT DEFAULT NULL,
                    pages_data TEXT DEFAULT NULL,
                    news_data TEXT DEFAULT NULL,
                    products_data TEXT DEFAULT NULL,
                    orders_data TEXT DEFAULT NULL,
                    ips_data TEXT DEFAULT NULL,
                    ua_data TEXT DEFAULT NULL
                )"
            )->execute();
        } else {
            $this->prepare(
                "CREATE TABLE IF NOT EXISTS web_log_cache (
                    cache_date DATE PRIMARY KEY,
                    visit_count INT NOT NULL DEFAULT 0,
                    actions_data TEXT DEFAULT NULL,
                    pages_data TEXT DEFAULT NULL,
                    news_data TEXT DEFAULT NULL,
                    products_data TEXT DEFAULT NULL,
                    orders_data TEXT DEFAULT NULL,
                    ips_data TEXT DEFAULT NULL,
                    ua_data TEXT DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            )->execute();
        }
    }

    /**
     * web_log_cache хүснэгтийн кэш өгөгдлийг шинэчлэх.
     */
    private function refreshCache(): void
    {
        $today = \date('Y-m-d');

        $stmt = $this->prepare("SELECT MAX(cache_date) AS last_date FROM web_log_cache WHERE actions_data IS NOT NULL");
        $stmt->execute();
        $lastDate = $stmt->fetch()['last_date'];
        $yesterday = \date('Y-m-d', \strtotime('-1 day'));

        if ($lastDate !== null && $lastDate >= $yesterday) {
            return;
        }

        $fromDate = $lastDate ?? '2000-01-01';
        $stmt = $this->prepare(
            "SELECT DISTINCT CAST(created_at AS DATE) AS log_date FROM web_log
             WHERE created_at > :from_date AND created_at < :today
             ORDER BY log_date"
        );
        $stmt->bindValue(':from_date', $fromDate);
        $stmt->bindValue(':today', $today);
        $stmt->execute();

        $dates = [];
        while ($row = $stmt->fetch()) {
            $dates[] = $row['log_date'];
        }

        // Shared hosting дээр disk дүүрэхээс сэргийлж нэг удаад хамгийн ихдээ 5 өдрийг боловсруулна.
        // Дараагийн хүсэлтээр үлдсэн өдрүүдийг үргэлжлүүлнэ.
        $dates = \array_slice($dates, 0, 5);

        foreach ($dates as $date) {
            $nextDate = \date('Y-m-d', \strtotime($date . ' +1 day'));

            // Нэг өдрийн бүх мэдээллийг нэг query-гээр авч PHP дээр ангилна.
            // 8 тусдаа GROUP BY query-г нэгтгэснээр MySQL tmp table үүсгэхгүй.
            $s = $this->prepare(
                "SELECT {$this->jsonVal('context', '$.action')} AS action,
                        COALESCE({$this->jsonVal('context', '$.server_request.code')}, '?') AS code,
                        COALESCE({$this->jsonVal('context', '$.record_id')}, '0') AS rid,
                        {$this->jsonVal('context', '$.title')} AS title,
                        {$this->jsonVal('context', '$.customer_name')} AS customer_name,
                        {$this->jsonVal('context', '$.server_request.remote_addr')} AS ip,
                        {$this->jsonVal('context', '$.server_request.user_agent')} AS ua
                 FROM web_log
                 WHERE created_at >= :d AND created_at < :nd"
            );
            $s->bindValue(':d', $date);
            $s->bindValue(':nd', $nextDate);
            $s->execute();

            $visitCount = 0;
            $actions = [];
            $pages = [];
            $news = [];
            $products = [];
            $orders = [];
            $ips = [];
            $uas = [];
            while ($r = $s->fetch()) {
                $visitCount++;
                $act = $r['action'] ?? null;
                if ($act !== null) {
                    $actions[$act] = ($actions[$act] ?? 0) + 1;
                }
                if ($act === 'page' && $r['title'] !== null) {
                    $key = $r['code'] . '|' . $r['rid'] . '|' . $r['title'];
                    $pages[$key] = ($pages[$key] ?? 0) + 1;
                } elseif ($act === 'news' && $r['title'] !== null) {
                    $key = $r['code'] . '|' . $r['rid'] . '|' . $r['title'];
                    $news[$key] = ($news[$key] ?? 0) + 1;
                } elseif ($act === 'product' && $r['title'] !== null) {
                    $key = $r['code'] . '|' . $r['rid'] . '|' . $r['title'];
                    $products[$key] = ($products[$key] ?? 0) + 1;
                } elseif ($act === 'order' && $r['customer_name'] !== null) {
                    $key = $r['code'] . '|' . $r['rid'] . '|' . $r['customer_name'];
                    $orders[$key] = ($orders[$key] ?? 0) + 1;
                }
                if ($r['ip'] !== null) {
                    $ips[$r['ip']] = ($ips[$r['ip']] ?? 0) + 1;
                }
                if ($r['ua'] !== null) {
                    $uas[$r['ua']] = ($uas[$r['ua']] ?? 0) + 1;
                }
            }

            $this->upsertCache($date, $visitCount, $actions, $pages, $news, $products, $orders, $ips, $uas);
        }
    }

    /**
     * JSON баганаас утга авах SQL fragment буцаах (MySQL vs PostgreSQL).
     */
    private function jsonVal(string $column, string $path): string
    {
        $driver = $this->getDriverName();

        if ($driver === Constants::DRIVER_PGSQL) {
            $parts = \explode('.', \ltrim($path, '$.'));
            if (\count($parts) === 1) {
                return "{$column}::jsonb->>'{$parts[0]}'";
            }
            $last = \array_pop($parts);
            $chain = $column . '::jsonb';
            foreach ($parts as $p) {
                $chain .= "->'{$p}'";
            }
            return "{$chain}->>'{$last}'";
        }

        return "JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}'))";
    }

    /**
     * web_log_cache хүснэгтэд upsert хийх (MySQL vs PostgreSQL).
     */
    private function upsertCache(
        string $date, int $visitCount,
        array $actions, array $pages, array $news,
        array $products, array $orders, array $ips, array $uas
    ): void {
        $driver = $this->getDriverName();
        $actionsJson = \json_encode($actions, JSON_UNESCAPED_UNICODE);
        $pagesJson = \json_encode($pages, JSON_UNESCAPED_UNICODE);
        $newsJson = \json_encode($news, JSON_UNESCAPED_UNICODE);
        $productsJson = \json_encode($products, JSON_UNESCAPED_UNICODE);
        $ordersJson = \json_encode($orders, JSON_UNESCAPED_UNICODE);
        $ipsJson = \json_encode($ips, JSON_UNESCAPED_UNICODE);
        $uasJson = \json_encode($uas, JSON_UNESCAPED_UNICODE);

        if ($driver === Constants::DRIVER_PGSQL) {
            $sql = "INSERT INTO web_log_cache (cache_date, visit_count, actions_data, pages_data, news_data, products_data, orders_data, ips_data, ua_data)
                    VALUES (:d, :vc, :ad, :pd, :nd2, :prd, :ord, :id, :ud)
                    ON CONFLICT (cache_date) DO UPDATE SET
                        visit_count = EXCLUDED.visit_count,
                        actions_data = EXCLUDED.actions_data,
                        pages_data = EXCLUDED.pages_data,
                        news_data = EXCLUDED.news_data,
                        products_data = EXCLUDED.products_data,
                        orders_data = EXCLUDED.orders_data,
                        ips_data = EXCLUDED.ips_data,
                        ua_data = EXCLUDED.ua_data";
        } else {
            $sql = "INSERT INTO web_log_cache (cache_date, visit_count, actions_data, pages_data, news_data, products_data, orders_data, ips_data, ua_data)
                    VALUES (:d, :vc, :ad, :pd, :nd2, :prd, :ord, :id, :ud)
                    ON DUPLICATE KEY UPDATE
                        visit_count = VALUES(visit_count),
                        actions_data = VALUES(actions_data),
                        pages_data = VALUES(pages_data),
                        news_data = VALUES(news_data),
                        products_data = VALUES(products_data),
                        orders_data = VALUES(orders_data),
                        ips_data = VALUES(ips_data),
                        ua_data = VALUES(ua_data)";
        }

        $s = $this->prepare($sql);
        $s->bindValue(':d', $date);
        $s->bindValue(':vc', $visitCount);
        $s->bindValue(':ad', $actionsJson);
        $s->bindValue(':pd', $pagesJson);
        $s->bindValue(':nd2', $newsJson);
        $s->bindValue(':prd', $productsJson);
        $s->bindValue(':ord', $ordersJson);
        $s->bindValue(':id', $ipsJson);
        $s->bindValue(':ud', $uasJson);
        $s->execute();
    }

    /**
     * code|id|title форматтай merge массиваас top 10 массив үүсгэх.
     */
    private function buildTop10(array $merge): array
    {
        \arsort($merge);
        $result = [];
        $i = 0;
        foreach ($merge as $key => $count) {
            $parts = \explode('|', $key, 3);
            if (\count($parts) === 3) {
                $result[] = ['code' => $parts[0], 'id' => $parts[1], 'title' => $parts[2], 'count' => $count];
            } else {
                $result[] = ['code' => $parts[0], 'id' => '0', 'title' => $parts[1] ?? $key, 'count' => $count];
            }
            if (++$i >= 10) break;
        }
        return $result;
    }

    /**
     * Cache-ийн JSON өгөгдлийг нэгтгэх.
     */
    private function mergeJsonCounts(array &$merged, ?string $json): void
    {
        if ($json === null || $json === '') {
            return;
        }
        $decoded = \json_decode($json, true);
        if (!\is_array($decoded)) {
            return;
        }
        foreach ($decoded as $key => $count) {
            $merged[$key] = ($merged[$key] ?? 0) + (int)$count;
        }
    }
}
