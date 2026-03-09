<?php

namespace Dashboard\Home;

use Psr\Log\LogLevel;

/**
 * Class HomeController
 * ------------------------------------------------------------------
 * Dashboard-ийн нүүр хуудасны контроллер.
 *
 * Энэ контроллер нь:
 *  - Dashboard-ийн нүүр хуудсыг харуулах (index)
 *  - Вэб сайтын зочлолын статистик мэдээллийг JSON-оор буцаах (stats)
 *  - Системийн бусад *_log хүснэгтүүдийн статистикийг буцаах (logStats)
 *  - web_log_cache хүснэгтийг удирдах (ensureCacheTable, refreshCache)
 *
 * @package Dashboard\Home
 */
class HomeController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Dashboard-ийн нүүр хуудсыг харуулах.
     *
     * @return void
     */
    public function index()
    {
        $this->twigDashboard(__DIR__ . '/home.html')->render();

        $this->log('dashboard', LogLevel::NOTICE, 'Нүүр хуудсыг уншиж байна');
    }

    /**
     * Вэб сайтын зочлолын статистик мэдээллийг JSON-оор буцаах.
     *
     * Бүх хүнд JSON_EXTRACT query-нүүдийг өдөр бүрээр cache-лэж хадгалдаг.
     * Зөвхөн өнөөдрийн live тоог web_log-оос шууд авна.
     * Өмнөх өдрүүдийн actions, pages, news, ips-г cache-ээс нэгтгэнэ.
     *
     * @return void JSON хариулт
     */
    public function stats()
    {
        try {
            if (!$this->hasTable('web_log')) {
                $this->respondJSON([
                    'today' => 0, 'week' => 0, 'month' => 0, 'total' => 0,
                    'chart_data' => [], 'actions' => [],
                    'top_pages' => [], 'top_news' => [], 'top_ips' => []
                ]);
                return;
            }

            $this->ensureCacheTable();
            $this->refreshCache();

            $today = \date('Y-m-d');
            $data = [];

            // Өнөөдрийн live count - index-тэй range scan
            $stmt = $this->prepare(
                "SELECT COUNT(*) AS cnt FROM web_log
                 WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY"
            );
            $stmt->execute();
            $todayCount = (int)$stmt->fetch()['cnt'];

            // Stat cards - cache-ээс
            $data['today'] = $todayCount;

            $stmt = $this->prepare(
                "SELECT COALESCE(SUM(visit_count),0) AS cnt FROM web_log_cache
                 WHERE cache_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND cache_date < CURDATE()"
            );
            $stmt->execute();
            $data['week'] = (int)$stmt->fetch()['cnt'] + $todayCount;

            $stmt = $this->prepare(
                "SELECT COALESCE(SUM(visit_count),0) AS cnt FROM web_log_cache
                 WHERE cache_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND cache_date < CURDATE()"
            );
            $stmt->execute();
            $data['month'] = (int)$stmt->fetch()['cnt'] + $todayCount;

            $stmt = $this->prepare("SELECT COALESCE(SUM(visit_count),0) AS cnt FROM web_log_cache");
            $stmt->execute();
            $data['total'] = (int)$stmt->fetch()['cnt'] + $todayCount;

            // Лог хамрах хугацаа - cache-ээс авна (MIN нь хэзээ ч өөрчлөгдөхгүй)
            $stmt = $this->prepare("SELECT MIN(cache_date) AS first_at FROM web_log_cache");
            $stmt->execute();
            $data['log_from'] = $stmt->fetch()['first_at'];
            $data['log_to'] = $today;

            // 30 day chart - cache-ээс
            $stmt = $this->prepare(
                "SELECT cache_date AS date, visit_count AS count FROM web_log_cache
                 WHERE cache_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND cache_date < CURDATE()
                 ORDER BY cache_date ASC"
            );
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
                 WHERE cache_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND cache_date < CURDATE()
                   AND actions_data IS NOT NULL"
            );
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
                "SELECT JSON_UNQUOTE(JSON_EXTRACT(context, '$.action')) AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY
                   AND JSON_EXTRACT(context, '$.action') IS NOT NULL
                 GROUP BY k"
            );
            if ($stmt->execute()) {
                while ($row = $stmt->fetch()) {
                    $actionsMerge[$row['k']] = ($actionsMerge[$row['k']] ?? 0) + (int)$row['v'];
                }
            }

            // Өнөөдрийн live pages (code|id|title)
            $pagesToday = [];
            $stmt = $this->prepare(
                "SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(context, '$.server_request.code')), '?') AS c,
                        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(context, '$.id')), '0') AS rid,
                        JSON_UNQUOTE(JSON_EXTRACT(context, '$.title')) AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY
                   AND JSON_UNQUOTE(JSON_EXTRACT(context, '$.action')) = 'page'
                   AND JSON_EXTRACT(context, '$.title') IS NOT NULL
                 GROUP BY c, rid, k"
            );
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
                "SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(context, '$.server_request.code')), '?') AS c,
                        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(context, '$.id')), '0') AS rid,
                        JSON_UNQUOTE(JSON_EXTRACT(context, '$.title')) AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY
                   AND JSON_UNQUOTE(JSON_EXTRACT(context, '$.action')) = 'news'
                   AND JSON_EXTRACT(context, '$.title') IS NOT NULL
                 GROUP BY c, rid, k"
            );
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
                "SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(context, '$.server_request.code')), '?') AS c,
                        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(context, '$.id')), '0') AS rid,
                        JSON_UNQUOTE(JSON_EXTRACT(context, '$.title')) AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY
                   AND JSON_UNQUOTE(JSON_EXTRACT(context, '$.action')) = 'product'
                   AND JSON_EXTRACT(context, '$.title') IS NOT NULL
                 GROUP BY c, rid, k"
            );
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
                "SELECT JSON_UNQUOTE(JSON_EXTRACT(context, '$.server_request.remote_addr')) AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY
                   AND JSON_EXTRACT(context, '$.server_request.remote_addr') IS NOT NULL
                 GROUP BY k"
            );
            if ($stmt->execute()) {
                while ($row = $stmt->fetch()) {
                    $ipsMerge[$row['k']] = ($ipsMerge[$row['k']] ?? 0) + (int)$row['v'];
                }
            }

            // Өнөөдрийн live User Agents нэмэх
            $stmt = $this->prepare(
                "SELECT JSON_UNQUOTE(JSON_EXTRACT(context, '$.server_request.user_agent')) AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY
                   AND JSON_EXTRACT(context, '$.server_request.user_agent') IS NOT NULL
                 GROUP BY k"
            );
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

            $this->respondJSON($data);
        } catch (\Throwable $err) {
            $code = (int)$err->getCode();
            $this->respondJSON(['status' => 'error', 'message' => $err->getMessage()], ($code >= 400 && $code < 600) ? $code : 500);
        }
    }

    /**
     * Системийн бусад *_log хүснэгтүүдийн статистикийг JSON-оор буцаах.
     *
     * Table бүрт 4 query биш, нэг query-д нэгтгэж авна.
     *
     * @return void JSON хариулт
     */
    public function logStats()
    {
        try {
            $stmt = $this->prepare(
                "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME LIKE '%\_log'
                   AND TABLE_NAME != 'web_log'
                   AND TABLE_NAME != 'web_log_cache'
                 ORDER BY TABLE_NAME"
            );
            $stmt->execute();

            $logs = [];
            while ($row = $stmt->fetch()) {
                $tableName = $row['TABLE_NAME'];
                $label = \str_replace('_log', '', $tableName);

                try {
                    // 4 query-г 1 query-д нэгтгэсэн
                    $s = $this->prepare(
                        "SELECT
                            COUNT(*) AS total,
                            SUM(CASE WHEN created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY THEN 1 ELSE 0 END) AS today,
                            SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS week,
                            MAX(created_at) AS last_at
                         FROM `$tableName`"
                    );
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

            $this->respondJSON(['status' => 'success', 'logs' => $logs]);
        } catch (\Throwable $err) {
            $code = (int)$err->getCode();
            $this->respondJSON(['status' => 'error', 'message' => $err->getMessage()], ($code >= 400 && $code < 600) ? $code : 500);
        }
    }

    /**
     * web_log_cache хүснэгт байгаа эсэхийг шалгаж, байхгүй бол үүсгэх.
     *
     * @return void
     */
    private function ensureCacheTable(): void
    {
        $this->prepare(
            "CREATE TABLE IF NOT EXISTS web_log_cache (
                cache_date DATE PRIMARY KEY,
                visit_count INT NOT NULL DEFAULT 0,
                actions_data MEDIUMTEXT DEFAULT NULL,
                pages_data MEDIUMTEXT DEFAULT NULL,
                news_data MEDIUMTEXT DEFAULT NULL,
                products_data MEDIUMTEXT DEFAULT NULL,
                orders_data MEDIUMTEXT DEFAULT NULL,
                ips_data MEDIUMTEXT DEFAULT NULL,
                ua_data MEDIUMTEXT DEFAULT NULL
            )"
        )->execute();

        // Хуучин cache хүснэгтэд products_data, orders_data багана нэмэх
        try {
            $this->exec("ALTER TABLE web_log_cache ADD COLUMN products_data MEDIUMTEXT DEFAULT NULL AFTER news_data");
        } catch (\Throwable) {}
        try {
            $this->exec("ALTER TABLE web_log_cache ADD COLUMN orders_data MEDIUMTEXT DEFAULT NULL AFTER products_data");
        } catch (\Throwable) {}
    }

    /**
     * web_log_cache хүснэгтийн кэш өгөгдлийг шинэчлэх.
     *
     * Өдөр бүрийн visit_count + actions + pages + news + ips-г cache-лэнэ.
     *
     * @return void
     */
    private function refreshCache(): void
    {
        $stmt = $this->prepare("SELECT MAX(cache_date) AS last_date FROM web_log_cache WHERE actions_data IS NOT NULL");
        $stmt->execute();
        $lastDate = $stmt->fetch()['last_date'];
        $yesterday = \date('Y-m-d', \strtotime('-1 day'));

        // Cache шинэчлэх шаардлагагүй - бүгд бэлэн
        if ($lastDate !== null && $lastDate >= $yesterday) {
            return;
        }

        // Cache-гүй өдрүүдийг олох
        $fromDate = $lastDate ?? '2000-01-01';
        $stmt = $this->prepare(
            "SELECT DISTINCT DATE(created_at) AS log_date FROM web_log
             WHERE created_at > :from_date AND created_at < CURDATE()
             ORDER BY log_date"
        );
        $stmt->bindValue(':from_date', $fromDate);
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
                "SELECT JSON_UNQUOTE(JSON_EXTRACT(context, '$.action')) AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= :d AND created_at < :nd
                   AND JSON_EXTRACT(context, '$.action') IS NOT NULL
                 GROUP BY k"
            );
            $s->bindValue(':d', $date);
            $s->bindValue(':nd', $nextDate);
            $s->execute();
            $actions = [];
            while ($r = $s->fetch()) {
                $actions[$r['k']] = (int)$r['v'];
            }

            // Pages (code|id|title хэлбэрээр cache-лэнэ)
            $s = $this->prepare(
                "SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(context, '$.server_request.code')), '?') AS c,
                        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(context, '$.id')), '0') AS rid,
                        JSON_UNQUOTE(JSON_EXTRACT(context, '$.title')) AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= :d AND created_at < :nd
                   AND JSON_UNQUOTE(JSON_EXTRACT(context, '$.action')) = 'page'
                   AND JSON_EXTRACT(context, '$.title') IS NOT NULL
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

            // News (code|id|title хэлбэрээр cache-лэнэ)
            $s = $this->prepare(
                "SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(context, '$.server_request.code')), '?') AS c,
                        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(context, '$.id')), '0') AS rid,
                        JSON_UNQUOTE(JSON_EXTRACT(context, '$.title')) AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= :d AND created_at < :nd
                   AND JSON_UNQUOTE(JSON_EXTRACT(context, '$.action')) = 'news'
                   AND JSON_EXTRACT(context, '$.title') IS NOT NULL
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

            // Products (code|id|title хэлбэрээр cache-лэнэ)
            $s = $this->prepare(
                "SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(context, '$.server_request.code')), '?') AS c,
                        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(context, '$.id')), '0') AS rid,
                        JSON_UNQUOTE(JSON_EXTRACT(context, '$.title')) AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= :d AND created_at < :nd
                   AND JSON_UNQUOTE(JSON_EXTRACT(context, '$.action')) = 'product'
                   AND JSON_EXTRACT(context, '$.title') IS NOT NULL
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

            // Orders (code|id|customer_name хэлбэрээр cache-лэнэ)
            $s = $this->prepare(
                "SELECT COALESCE(JSON_UNQUOTE(JSON_EXTRACT(context, '$.server_request.code')), '?') AS c,
                        COALESCE(JSON_UNQUOTE(JSON_EXTRACT(context, '$.id')), '0') AS rid,
                        JSON_UNQUOTE(JSON_EXTRACT(context, '$.customer_name')) AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= :d AND created_at < :nd
                   AND JSON_UNQUOTE(JSON_EXTRACT(context, '$.action')) = 'order'
                   AND JSON_EXTRACT(context, '$.customer_name') IS NOT NULL
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
                "SELECT JSON_UNQUOTE(JSON_EXTRACT(context, '$.server_request.remote_addr')) AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= :d AND created_at < :nd
                   AND JSON_EXTRACT(context, '$.server_request.remote_addr') IS NOT NULL
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
                "SELECT JSON_UNQUOTE(JSON_EXTRACT(context, '$.server_request.user_agent')) AS k, COUNT(*) AS v
                 FROM web_log
                 WHERE created_at >= :d AND created_at < :nd
                   AND JSON_EXTRACT(context, '$.server_request.user_agent') IS NOT NULL
                 GROUP BY k"
            );
            $s->bindValue(':d', $date);
            $s->bindValue(':nd', $nextDate);
            $s->execute();
            $uas = [];
            while ($r = $s->fetch()) {
                $uas[$r['k']] = (int)$r['v'];
            }

            // Upsert
            $s = $this->prepare(
                "INSERT INTO web_log_cache (cache_date, visit_count, actions_data, pages_data, news_data, products_data, orders_data, ips_data, ua_data)
                 VALUES (:d, :vc, :ad, :pd, :nd2, :prd, :ord, :id, :ud)
                 ON DUPLICATE KEY UPDATE
                    visit_count = VALUES(visit_count),
                    actions_data = VALUES(actions_data),
                    pages_data = VALUES(pages_data),
                    news_data = VALUES(news_data),
                    products_data = VALUES(products_data),
                    orders_data = VALUES(orders_data),
                    ips_data = VALUES(ips_data),
                    ua_data = VALUES(ua_data)"
            );
            $s->bindValue(':d', $date);
            $s->bindValue(':vc', $visitCount);
            $s->bindValue(':ad', \json_encode($actions, JSON_UNESCAPED_UNICODE));
            $s->bindValue(':pd', \json_encode($pages, JSON_UNESCAPED_UNICODE));
            $s->bindValue(':nd2', \json_encode($news, JSON_UNESCAPED_UNICODE));
            $s->bindValue(':prd', \json_encode($products, JSON_UNESCAPED_UNICODE));
            $s->bindValue(':ord', \json_encode($orders, JSON_UNESCAPED_UNICODE));
            $s->bindValue(':id', \json_encode($ips, JSON_UNESCAPED_UNICODE));
            $s->bindValue(':ud', \json_encode($uas, JSON_UNESCAPED_UNICODE));
            $s->execute();
        }
    }

    /**
     * code|id|title форматтай merge массиваас top 10 массив үүсгэх.
     *
     * @param array $merge key => count массив
     * @return array
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
     *
     * @param array &$merged Нэгтгэсэн массив (key => count)
     * @param string|null $json JSON string
     * @return void
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
