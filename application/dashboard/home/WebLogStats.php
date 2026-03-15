<?php

namespace Dashboard\Home;

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

        // Өнөөдрийн live actions нэмэх
        $stmt = $this->prepare(
            "SELECT {$this->jsonVal('context', '$.action')} AS k, COUNT(*) AS v
             FROM web_log
             WHERE created_at >= :today AND created_at < :tomorrow
               AND {$this->jsonVal('context', '$.action')} IS NOT NULL
             GROUP BY k"
        );
        $stmt->bindValue(':today', $today);
        $stmt->bindValue(':tomorrow', $tomorrow);
        if ($stmt->execute()) {
            while ($row = $stmt->fetch()) {
                $actionsMerge[$row['k']] = ($actionsMerge[$row['k']] ?? 0) + (int)$row['v'];
            }
        }

        // Өнөөдрийн live pages (code|id|title)
        $pagesToday = [];
        $stmt = $this->prepare(
            "SELECT COALESCE({$this->jsonVal('context', '$.server_request.code')}, '?') AS c,
                    COALESCE({$this->jsonVal('context', '$.id')}, '0') AS rid,
                    {$this->jsonVal('context', '$.title')} AS k, COUNT(*) AS v
             FROM web_log
             WHERE created_at >= :today AND created_at < :tomorrow
               AND {$this->jsonVal('context', '$.action')} = 'page'
               AND {$this->jsonVal('context', '$.title')} IS NOT NULL
             GROUP BY c, rid, k"
        );
        $stmt->bindValue(':today', $today);
        $stmt->bindValue(':tomorrow', $tomorrow);
        if ($stmt->execute()) {
            while ($row = $stmt->fetch()) {
                $key = $row['c'] . '|' . $row['rid'] . '|' . $row['k'];
                $pagesToday[$key] = (int)$row['v'];
                $pagesMerge[$key] = ($pagesMerge[$key] ?? 0) + (int)$row['v'];
                $pagesWeek[$key] = ($pagesWeek[$key] ?? 0) + (int)$row['v'];
            }
        }

        // Өнөөдрийн live news (code|id|title)
        $newsToday = [];
        $stmt = $this->prepare(
            "SELECT COALESCE({$this->jsonVal('context', '$.server_request.code')}, '?') AS c,
                    COALESCE({$this->jsonVal('context', '$.id')}, '0') AS rid,
                    {$this->jsonVal('context', '$.title')} AS k, COUNT(*) AS v
             FROM web_log
             WHERE created_at >= :today AND created_at < :tomorrow
               AND {$this->jsonVal('context', '$.action')} = 'news'
               AND {$this->jsonVal('context', '$.title')} IS NOT NULL
             GROUP BY c, rid, k"
        );
        $stmt->bindValue(':today', $today);
        $stmt->bindValue(':tomorrow', $tomorrow);
        if ($stmt->execute()) {
            while ($row = $stmt->fetch()) {
                $key = $row['c'] . '|' . $row['rid'] . '|' . $row['k'];
                $newsToday[$key] = (int)$row['v'];
                $newsMerge[$key] = ($newsMerge[$key] ?? 0) + (int)$row['v'];
                $newsWeek[$key] = ($newsWeek[$key] ?? 0) + (int)$row['v'];
            }
        }

        // Өнөөдрийн live products (code|id|title)
        $productsToday = [];
        $stmt = $this->prepare(
            "SELECT COALESCE({$this->jsonVal('context', '$.server_request.code')}, '?') AS c,
                    COALESCE({$this->jsonVal('context', '$.id')}, '0') AS rid,
                    {$this->jsonVal('context', '$.title')} AS k, COUNT(*) AS v
             FROM web_log
             WHERE created_at >= :today AND created_at < :tomorrow
               AND {$this->jsonVal('context', '$.action')} = 'product'
               AND {$this->jsonVal('context', '$.title')} IS NOT NULL
             GROUP BY c, rid, k"
        );
        $stmt->bindValue(':today', $today);
        $stmt->bindValue(':tomorrow', $tomorrow);
        if ($stmt->execute()) {
            while ($row = $stmt->fetch()) {
                $key = $row['c'] . '|' . $row['rid'] . '|' . $row['k'];
                $productsToday[$key] = (int)$row['v'];
                $productsMerge[$key] = ($productsMerge[$key] ?? 0) + (int)$row['v'];
                $productsWeek[$key] = ($productsWeek[$key] ?? 0) + (int)$row['v'];
            }
        }

        // Өнөөдрийн live IPs нэмэх
        $stmt = $this->prepare(
            "SELECT {$this->jsonVal('context', '$.server_request.remote_addr')} AS k, COUNT(*) AS v
             FROM web_log
             WHERE created_at >= :today AND created_at < :tomorrow
               AND {$this->jsonVal('context', '$.server_request.remote_addr')} IS NOT NULL
             GROUP BY k"
        );
        $stmt->bindValue(':today', $today);
        $stmt->bindValue(':tomorrow', $tomorrow);
        if ($stmt->execute()) {
            while ($row = $stmt->fetch()) {
                $ipsMerge[$row['k']] = ($ipsMerge[$row['k']] ?? 0) + (int)$row['v'];
            }
        }

        // Өнөөдрийн live User Agents нэмэх
        $stmt = $this->prepare(
            "SELECT {$this->jsonVal('context', '$.server_request.user_agent')} AS k, COUNT(*) AS v
             FROM web_log
             WHERE created_at >= :today AND created_at < :tomorrow
               AND {$this->jsonVal('context', '$.server_request.user_agent')} IS NOT NULL
             GROUP BY k"
        );
        $stmt->bindValue(':today', $today);
        $stmt->bindValue(':tomorrow', $tomorrow);
        if ($stmt->execute()) {
            while ($row = $stmt->fetch()) {
                $uaMerge[$row['k']] = ($uaMerge[$row['k']] ?? 0) + (int)$row['v'];
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
                 FROM orders WHERE is_active = 1"
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

        if ($driver === 'pgsql') {
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
                $q = $driver === 'pgsql'
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
        if ($this->hasTable('web_log_cache')) {
            try {
                $this->exec("ALTER TABLE web_log_cache ADD COLUMN products_data TEXT DEFAULT NULL");
            } catch (\Throwable) {}
            try {
                $this->exec("ALTER TABLE web_log_cache ADD COLUMN orders_data TEXT DEFAULT NULL");
            } catch (\Throwable) {}
            return;
        }

        $driver = $this->getDriverName();
        if ($driver === 'pgsql') {
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

        foreach ($dates as $date) {
            $nextDate = \date('Y-m-d', \strtotime($date . ' +1 day'));

            // Visit count
            $s = $this->prepare(
                "SELECT COUNT(*) AS cnt FROM web_log
                 WHERE created_at >= :d AND created_at < :nd"
            );
            $s->bindValue(':d', $date);
            $s->bindValue(':nd', $nextDate);
            $s->execute();
            $visitCount = (int)$s->fetch()['cnt'];

            // Actions
            $s = $this->prepare(
                "SELECT {$this->jsonVal('context', '$.action')} AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= :d AND created_at < :nd
                   AND {$this->jsonVal('context', '$.action')} IS NOT NULL
                 GROUP BY k"
            );
            $s->bindValue(':d', $date);
            $s->bindValue(':nd', $nextDate);
            $s->execute();
            $actions = [];
            while ($r = $s->fetch()) {
                $actions[$r['k']] = (int)$r['v'];
            }

            // Pages
            $s = $this->prepare(
                "SELECT COALESCE({$this->jsonVal('context', '$.server_request.code')}, '?') AS c,
                        COALESCE({$this->jsonVal('context', '$.id')}, '0') AS rid,
                        {$this->jsonVal('context', '$.title')} AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= :d AND created_at < :nd
                   AND {$this->jsonVal('context', '$.action')} = 'page'
                   AND {$this->jsonVal('context', '$.title')} IS NOT NULL
                 GROUP BY c, rid, k"
            );
            $s->bindValue(':d', $date);
            $s->bindValue(':nd', $nextDate);
            $s->execute();
            $pages = [];
            while ($r = $s->fetch()) {
                $key = $r['c'] . '|' . $r['rid'] . '|' . $r['k'];
                $pages[$key] = (int)$r['v'];
            }

            // News
            $s = $this->prepare(
                "SELECT COALESCE({$this->jsonVal('context', '$.server_request.code')}, '?') AS c,
                        COALESCE({$this->jsonVal('context', '$.id')}, '0') AS rid,
                        {$this->jsonVal('context', '$.title')} AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= :d AND created_at < :nd
                   AND {$this->jsonVal('context', '$.action')} = 'news'
                   AND {$this->jsonVal('context', '$.title')} IS NOT NULL
                 GROUP BY c, rid, k"
            );
            $s->bindValue(':d', $date);
            $s->bindValue(':nd', $nextDate);
            $s->execute();
            $news = [];
            while ($r = $s->fetch()) {
                $key = $r['c'] . '|' . $r['rid'] . '|' . $r['k'];
                $news[$key] = (int)$r['v'];
            }

            // Products
            $s = $this->prepare(
                "SELECT COALESCE({$this->jsonVal('context', '$.server_request.code')}, '?') AS c,
                        COALESCE({$this->jsonVal('context', '$.id')}, '0') AS rid,
                        {$this->jsonVal('context', '$.title')} AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= :d AND created_at < :nd
                   AND {$this->jsonVal('context', '$.action')} = 'product'
                   AND {$this->jsonVal('context', '$.title')} IS NOT NULL
                 GROUP BY c, rid, k"
            );
            $s->bindValue(':d', $date);
            $s->bindValue(':nd', $nextDate);
            $s->execute();
            $products = [];
            while ($r = $s->fetch()) {
                $key = $r['c'] . '|' . $r['rid'] . '|' . $r['k'];
                $products[$key] = (int)$r['v'];
            }

            // Orders
            $s = $this->prepare(
                "SELECT COALESCE({$this->jsonVal('context', '$.server_request.code')}, '?') AS c,
                        COALESCE({$this->jsonVal('context', '$.id')}, '0') AS rid,
                        {$this->jsonVal('context', '$.customer_name')} AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= :d AND created_at < :nd
                   AND {$this->jsonVal('context', '$.action')} = 'order'
                   AND {$this->jsonVal('context', '$.customer_name')} IS NOT NULL
                 GROUP BY c, rid, k"
            );
            $s->bindValue(':d', $date);
            $s->bindValue(':nd', $nextDate);
            $s->execute();
            $orders = [];
            while ($r = $s->fetch()) {
                $key = $r['c'] . '|' . $r['rid'] . '|' . $r['k'];
                $orders[$key] = (int)$r['v'];
            }

            // IPs
            $s = $this->prepare(
                "SELECT {$this->jsonVal('context', '$.server_request.remote_addr')} AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= :d AND created_at < :nd
                   AND {$this->jsonVal('context', '$.server_request.remote_addr')} IS NOT NULL
                 GROUP BY k"
            );
            $s->bindValue(':d', $date);
            $s->bindValue(':nd', $nextDate);
            $s->execute();
            $ips = [];
            while ($r = $s->fetch()) {
                $ips[$r['k']] = (int)$r['v'];
            }

            // User Agents
            $s = $this->prepare(
                "SELECT {$this->jsonVal('context', '$.server_request.user_agent')} AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= :d AND created_at < :nd
                   AND {$this->jsonVal('context', '$.server_request.user_agent')} IS NOT NULL
                 GROUP BY k"
            );
            $s->bindValue(':d', $date);
            $s->bindValue(':nd', $nextDate);
            $s->execute();
            $uas = [];
            while ($r = $s->fetch()) {
                $uas[$r['k']] = (int)$r['v'];
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

        if ($driver === 'pgsql') {
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

        if ($driver === 'pgsql') {
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
