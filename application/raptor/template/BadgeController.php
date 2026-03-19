<?php

namespace Raptor\Template;

/**
 * Class BadgeController
 * ---------------------------------------------------------------
 * Dashboard Sidebar Badge System Controller
 *
 * Dashboard-ийн sidebar цэс дээр module бүрт шинэ үйлдлийн тоог
 * badge хэлбэрээр харуулах системийн controller.
 *
 * Архитектурын зарчим:
 * ---------------------------------------------------------------
 * Log-д суурилсан тоолол - Тусдаа badge хүснэгт хөтлөхгүй, харин
 *   log хүснэгтүүдээс (messages_log, news_log, pages_log гэх мэт)
 *   шууд JSON_EXTRACT ашиглан action тоолно.
 * Admin бүрт тусдаа хяналт - admin_badge_seen хүснэгтэд admin бүрт
 *   module бүрийн checked_at цагийг хадгална. Admin өөрийн сүүлд
 *   үзсэн цагаас хойшхи, ЗӨВХӨН бусад admin-ий хийсэн action-уудыг
 *   badge-ээр харна (өөрийнхөө action-г тоолохгүй).
 * RBAC-д нийцсэн - admin бүрт зөвхөн тухайн хэрэглэгчийн эрхэд
 *   нийцэх module-уудын badge-г буцаана.
 * Файл тооны badge - Log-д бичигддэггүй зүйлсийг (manual, migrations)
 *   файлын тоогоор хянана. Сүүлд үзсэн үеийн файлын тооноос одоогийн
 *   файлын тоо их байвал шинэ badge харуулна.
 * Өнгөний код - green = шинэ бичлэг/create, blue = засвар/update,
 *   red = устгах/deactivate, info = сэтгэгдэл/үнэлгээ (comment/review).
 *
 * @package Raptor\Template
 */
class BadgeController extends \Raptor\Controller
{
    /**
     * Module бүрт хандахад шаардлагатай эрхийн зураглал.
     *
     * Гурван төрлийн утга авна:
     *   null               - аливаа нэвтэрсэн admin хандах боломжтой (isUserAuthorized)
     *   'role:system_coder' - тухайн role-той эсэхийг isUser() ашиглан шалгана
     *   'system_content_index' - RBAC permission-г isUserCan() ашиглан шалгана
     *
     * @var array<string, string|null>
     */
    public const PERMISSION_MAP = [
        '/dashboard/messages'      => 'system_content_index',
        '/dashboard/pages'         => 'system_content_index',
        '/dashboard/news'          => 'system_content_index',
        '/dashboard/files'         => 'system_content_index',
        '/dashboard/localization'  => 'system_localization_index',
        '/dashboard/settings'      => 'system_content_settings',
        '/dashboard/references'    => 'system_content_index',
        '/dashboard/products'      => 'system_product_index',
        '/dashboard/orders'        => 'system_product_index',
        '/dashboard/users'         => 'system_user_index',
        '/dashboard/organizations' => 'system_organization_index',
        '/dashboard/manage/menu'   => 'role:system_coder',
        '/dashboard/dev-requests'  => 'system_development',
        '/dashboard/manual'        => null,
        '/dashboard/migrations'    => 'role:system_coder',
    ];

    /**
     * Log хүснэгт + action -> [module path, color] зураглал.
     *
     * Бүтэц: [log_table_prefix][action_name] => [module_path, color]
     *   log_table_prefix - log хүснэгтийн нэр (_log suffix-гүй), жишээ: 'news' -> news_log
     *   action_name - log context доторх 'action' талбарын утга
     *   module_path - dashboard sidebar-ийн module замнал
     *   color - badge-ийн өнгө: green (create/insert), blue (update), red (delete/deactivate)
     *
     * Нэг log хүснэгтээс олон module-руу badge үүсэх боломжтой.
     * Жишээ: news_log доторх 'create' action нь /dashboard/news-руу green badge,
     * 'comment-insert' action нь /dashboard/news-руу info badge үүсгэнэ.
     * products_orders_log доторх 'order' action нь /dashboard/orders-руу badge үүсгэнэ.
     *
     * @var array<string, array<string, array{0: string, 1: string}>>
     */
    public const BADGE_MAP = [
        'messages' => [
            'contact-send'   => ['/dashboard/messages', 'green'],
            'mark-replied'   => ['/dashboard/messages', 'blue'],
            'deactivate'     => ['/dashboard/messages', 'red'],
        ],
        'pages' => [
            'create'         => ['/dashboard/pages', 'green'],
            'update'         => ['/dashboard/pages', 'blue'],
            'deactivate'     => ['/dashboard/pages', 'red'],
        ],
        'news' => [
            'create'             => ['/dashboard/news', 'green'],
            'update'             => ['/dashboard/news', 'blue'],
            'deactivate'         => ['/dashboard/news', 'red'],
            'comment-insert'     => ['/dashboard/news', 'info'],
            'comment-reply'      => ['/dashboard/news', 'info'],
            'comment-deactivate' => ['/dashboard/news', 'red'],
        ],
        'files' => [
            'files-upload'     => ['/dashboard/files', 'green'],
            'files-update'     => ['/dashboard/files', 'blue'],
            'files-deactivate' => ['/dashboard/files', 'red'],
        ],
        'content' => [
            'localization-language-create'     => ['/dashboard/localization', 'green'],
            'localization-language-update'     => ['/dashboard/localization', 'blue'],
            'localization-language-delete'     => ['/dashboard/localization', 'red'],
            'localization-text-create'         => ['/dashboard/localization', 'green'],
            'localization-text-update'         => ['/dashboard/localization', 'blue'],
            'localization-text-deactivate'     => ['/dashboard/localization', 'red'],
            'settings-post'        => ['/dashboard/settings', 'blue'],
            'settings-files'       => ['/dashboard/settings', 'blue'],
            'reference-create'     => ['/dashboard/references', 'green'],
            'reference-update'     => ['/dashboard/references', 'blue'],
            'reference-deactivate' => ['/dashboard/references', 'red'],
        ],
        'products' => [
            'create'            => ['/dashboard/products', 'green'],
            'update'            => ['/dashboard/products', 'blue'],
            'deactivate'        => ['/dashboard/products', 'red'],
            'review-insert'     => ['/dashboard/products', 'info'],
            'review-deactivate' => ['/dashboard/products', 'red'],
        ],
        'products_orders' => [
            'order'         => ['/dashboard/orders', 'green'],
            'update-status' => ['/dashboard/orders', 'blue'],
            'deactivate'    => ['/dashboard/orders', 'red'],
        ],
        'users' => [
            'create'             => ['/dashboard/users', 'green'],
            'signup-approve'     => ['/dashboard/users', 'green'],
            'update'             => ['/dashboard/users', 'blue'],
            'set-password'       => ['/dashboard/users', 'blue'],
            'set-organization'   => ['/dashboard/users', 'blue'],
            'set-role'           => ['/dashboard/users', 'blue'],
            'deactivate'         => ['/dashboard/users', 'red'],
            'signup-deactivate'  => ['/dashboard/users', 'red'],
            'strip-organization' => ['/dashboard/users', 'red'],
            'strip-role'         => ['/dashboard/users', 'red'],
        ],
        'organizations' => [
            'create'     => ['/dashboard/organizations', 'green'],
            'update'     => ['/dashboard/organizations', 'blue'],
            'deactivate' => ['/dashboard/organizations', 'red'],
        ],
        'dashboard' => [
            'rbac-create-role'         => ['/dashboard/organizations', 'green'],
            'rbac-create-permission'   => ['/dashboard/organizations', 'green'],
            'rbac-set-role-permission' => ['/dashboard/organizations', 'blue'],
            'template-menu-create'     => ['/dashboard/manage/menu', 'green'],
            'template-menu-update'     => ['/dashboard/manage/menu', 'blue'],
            'template-menu-deactivate' => ['/dashboard/manage/menu', 'red'],
        ],
        'dev_requests' => [
            'store'      => ['/dashboard/dev-requests', 'green'],
            'respond'    => ['/dashboard/dev-requests', 'blue'],
            'deactivate' => ['/dashboard/dev-requests', 'red'],
        ],
    ];

    /**
     * Бүх module-ийн badge тоог тооцоолж JSON-аар буцаана.
     *
     * Ажиллах алгоритм:
     * 1) admin_badge_seen хүснэгтээс тухайн admin-ий module бүрийн
     *    checked_at (сүүлд үзсэн цаг) болон last_seen_count утгуудыг авна
     * 2) BADGE_MAP-г урвуу (reverse) хөрвүүлж module -> [(log_table, action, color)]
     *    зураглал үүсгэнэ. Ингэснээр module бүрт ямар log хүснэгтээс ямар
     *    action тоолохыг тодорхойлно
     * 3) Module бүрт PERMISSION_MAP-аар эрх шалгана - эрхгүй module-г алгасна
     * 4) Module бүрт холбогдох log хүснэгтүүдэд SQL query хийнэ:
     *    - checked_at-аас хойшхи бичлэгийг тоолно (бичлэг байхгүй бол 30 хоног)
     *    - JSON_EXTRACT ашиглан context доторх action талбараар шүүнэ
     *    - Тухайн admin-ий өөрийнх нь хийсэн action-г хасна (auth_user.id != adminId)
     *    - Зөвхөн info, alert, warning түвшинг тоолно
     * 5) Өнгө тус бүрээр нэгтгэж module-д badge массив нэмнэ
     * 6) File-count badge: manual болон migrations хавтсын файлын тоог
     *    addFileCountBadge() ашиглан тооцоолно
     *
     * Буцаах JSON бүтэц:
     *   { status: "success", badges: { "/dashboard/news": [{ color: "green", count: 3 }] } }
     *
     * GET /dashboard/badges
     *
     * @return void
     */
    public function list()
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception('Unauthorized', 401);
            }

            $adminId = $this->getUserId();
            $seenModel = new AdminBadgeSeenModel($this->pdo);
            $seenTable = $seenModel->getName();

            // Admin-ий checked_at мэдээллийг авах
            $seenRows = $this->query(
                "SELECT module, checked_at, last_seen_count FROM $seenTable WHERE admin_id=$adminId"
            )->fetchAll();
            $checkedMap = [];
            foreach ($seenRows as $row) {
                $checkedMap[$row['module']] = $row;
            }

            $badges = [];

            // Module -> [(log_table, action, color)] reverse map
            $moduleMap = [];
            foreach (self::BADGE_MAP as $logTable => $actions) {
                foreach ($actions as $action => [$module, $color]) {
                    $moduleMap[$module][] = [$logTable, $action, $color];
                }
            }

            // Module бүрт log хүснэгтүүдээс тоолох
            foreach ($moduleMap as $module => $entries) {
                // Permission шалгах
                if (!$this->hasModuleAccess($module)) {
                    continue;
                }

                // Бичлэг байхгүй бол сүүлийн 30 хоногоос тоолно
                $checkedAt = $checkedMap[$module]['checked_at']
                    ?? \date('Y-m-d H:i:s', \strtotime('-30 days'));

                // Log table-аар бүлэглэх
                $byTable = [];
                foreach ($entries as [$logTable, $action, $color]) {
                    $byTable[$logTable][] = ['action' => $action, 'color' => $color];
                }

                $moduleCounts = [];
                foreach ($byTable as $logTable => $actionList) {
                    $tableName = "{$logTable}_log";

                    // Хүснэгт байгаа эсэхийг шалгах
                    try {
                        $this->query("SELECT 1 FROM $tableName LIMIT 0");
                    } catch (\Throwable) {
                        continue;
                    }

                    $actionNames = \array_column($actionList, 'action');
                    $colorByAction = \array_column($actionList, 'color', 'action');
                    $placeholders = \implode(',', \array_fill(0, \count($actionNames), '?'));

                    if ($this->getDriverName() === 'pgsql') {
                        $sql =
                            "SELECT (context::jsonb)->>'action' as act, COUNT(*) as cnt " .
                            "FROM $tableName " .
                            "WHERE created_at > ? " .
                            "AND level IN ('info', 'alert', 'warning') " .
                            "AND (context::jsonb)->>'action' IN ($placeholders) " .
                            "AND ((context::jsonb)->'auth_user'->>'id' IS NULL " .
                            "     OR ((context::jsonb)->'auth_user'->>'id')::int != ?) " .
                            "GROUP BY act";
                    } else {
                        $sql =
                            "SELECT JSON_UNQUOTE(JSON_EXTRACT(context, '$.action')) as act, COUNT(*) as cnt " .
                            "FROM $tableName " .
                            "WHERE created_at > ? " .
                            "AND level IN ('info', 'alert', 'warning') " .
                            "AND JSON_UNQUOTE(JSON_EXTRACT(context, '$.action')) IN ($placeholders) " .
                            "AND (JSON_EXTRACT(context, '$.auth_user.id') IS NULL " .
                            "     OR JSON_EXTRACT(context, '$.auth_user.id') != ?) " .
                            "GROUP BY act";
                    }

                    $stmt = $this->prepare($sql);
                    $i = 1;
                    $stmt->bindValue($i++, $checkedAt);
                    foreach ($actionNames as $act) {
                        $stmt->bindValue($i++, $act);
                    }
                    $stmt->bindValue($i, $adminId, \PDO::PARAM_INT);

                    if ($stmt->execute()) {
                        while ($row = $stmt->fetch()) {
                            $color = $colorByAction[$row['act']] ?? 'blue';
                            $moduleCounts[$color] = ($moduleCounts[$color] ?? 0) + (int)$row['cnt'];
                        }
                    }
                }

                foreach ($moduleCounts as $color => $count) {
                    if ($count > 0) {
                        $badges[$module][] = ['color' => $color, 'count' => $count];
                    }
                }
            }

            // File-count badge: manual (бүх нэвтэрсэн admin)
            $this->addFileCountBadge(
                $badges, $checkedMap,
                '/dashboard/manual',
                \dirname(__DIR__, 2) . '/dashboard/manual/',
                '*-*.html',
                ['manual-index.html']
            );

            // File-count badge: migrations (coder only)
            if ($this->hasModuleAccess('/dashboard/migrations')) {
                $this->addFileCountBadge(
                    $badges, $checkedMap,
                    '/dashboard/migrations',
                    \dirname(__DIR__, 3) . '/database/migrations/',
                    '*.sql'
                );
            }

            $this->respondJSON(['status' => 'success', 'badges' => $badges]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        }
    }

    /**
     * Admin тухайн module-д хандах эрхтэй эсэхийг шалгана.
     *
     * PERMISSION_MAP-аас module-ийн эрхийн тохиргоог авч гурван тохиолдлоор шалгана:
     *   null         - нэвтэрсэн эсэхийг isUserAuthorized() ашиглан шалгана
     *   'role:xxx'   - тухайн role-той эсэхийг isUser(alias) ашиглан шалгана
     *   бусад string - RBAC permission-г isUserCan(permission) ашиглан шалгана
     *
     * PERMISSION_MAP-д бүртгэгдээгүй module нь null-тэй адил ажиллана.
     *
     * @param string $module Module-ийн path (жишээ: '/dashboard/news')
     * @return bool Admin энэ module-ийн badge-г харах эрхтэй эсэх
     */
    private function hasModuleAccess(string $module): bool
    {
        $permission = self::PERMISSION_MAP[$module] ?? null;
        if ($permission === null) {
            return $this->isUserAuthorized();
        }
        if (\str_starts_with($permission, 'role:')) {
            return $this->isUser(\substr($permission, 5));
        }
        return $this->isUserCan($permission);
    }

    /**
     * Файлын тоонд суурилсан badge нэмэх helper.
     *
     * Log хүснэгтэд бичигддэггүй module-уудад (manual, migrations) зориулсан.
     * Тухайн хавтас дахь файлуудын тоог glob() ашиглан тоолж, admin-ий сүүлд
     * үзсэн үеийн файлын тоо (last_seen_count)-тэй харьцуулна.
     *
     * Одоогийн файлын тоо > last_seen_count бол зөрүүгээр green badge нэмнэ.
     * Хэрвээ файл устгагдсан буюу тоо буурсан бол badge харуулахгүй.
     * last_seen_count = 0 (анх удаа) бол бүх файлыг шинэ гэж тоолно.
     *
     * @param array  &$badges     Badge массив (reference-аар дамжуулна)
     * @param array  $checkedMap  Admin-ий module бүрийн checked_at, last_seen_count map
     * @param string $module      Module-ийн path (жишээ: '/dashboard/manual')
     * @param string $dir         Файл хайх хавтасны зам
     * @param string $pattern     Glob pattern (жишээ: '*.sql', '*-*.html')
     * @param array  $exclude     Алгасах файлуудын нэрсийн жагсаалт (basename)
     * @return void
     */
    private function addFileCountBadge(
        array &$badges,
        array $checkedMap,
        string $module,
        string $dir,
        string $pattern,
        array $exclude = []
    ): void {
        $files = \glob($dir . $pattern) ?: [];
        if (!empty($exclude)) {
            $files = \array_filter($files, fn($f) => !\in_array(\basename($f), $exclude));
        }
        $fileCount = \count($files);

        $lastSeen = (int)($checkedMap[$module]['last_seen_count'] ?? 0);
        if ($fileCount > $lastSeen) {
            $badges[$module][] = [
                'color' => 'green',
                'count' => $fileCount - $lastSeen
            ];
        }
    }

    /**
     * Тухайн module-г уншсан (seen) гэж тэмдэглэнэ.
     *
     * Admin sidebar дээр module-ийн линк дарахад JS-ээс дуудагдана.
     * admin_badge_seen хүснэгтэд тухайн admin + module хосын бичлэгийг
     * upsert хийнэ:
     *   - Бичлэг байвал: checked_at болон last_seen_count-г шинэчилнэ
     *   - Бичлэг байхгүй бол: шинээр insert хийнэ
     *
     * File-count module-уудад (manual, migrations) тухайн үеийн файлын
     * тоог last_seen_count-д хадгална. Ингэснээр дараагийн list() дуудалт
     * дээр зөвхөн шинээр нэмэгдсэн файлуудын тоог badge-ээр харуулна.
     * Log-д суурилсан module-уудад last_seen_count = 0 байна.
     *
     * POST /dashboard/badges/seen
     * Body: { module: "/dashboard/news" }
     *
     * @return void
     */
    public function seen()
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception('Unauthorized', 401);
            }

            $payload = $this->getParsedBody();
            $module = \trim($payload['module'] ?? '');
            if (empty($module)) {
                throw new \InvalidArgumentException('Module is required', 400);
            }

            $adminId = $this->getUserId();
            $seenModel = new AdminBadgeSeenModel($this->pdo);
            $seenTable = $seenModel->getName();
            $now = \date('Y-m-d H:i:s');

            // File-count: manual, migrations
            $fileCount = 0;
            if ($module === '/dashboard/manual') {
                $manualDir = \dirname(__DIR__, 2) . '/dashboard/manual/';
                $manualFiles = \glob($manualDir . '*-*.html') ?: [];
                $manualFiles = \array_filter($manualFiles, fn($f) => \basename($f) !== 'manual-index.html');
                $fileCount = \count($manualFiles);
            } elseif ($module === '/dashboard/migrations') {
                $migrationsDir = \dirname(__DIR__, 3) . '/database/migrations/';
                $migrationFiles = \glob($migrationsDir . '*.sql') ?: [];
                $fileCount = \count($migrationFiles);
            }

            // Upsert
            $existing = $this->prepare(
                "SELECT id FROM $seenTable WHERE admin_id=:aid AND module=:module"
            );
            $existing->bindValue(':aid', $adminId, \PDO::PARAM_INT);
            $existing->bindValue(':module', $module);
            $existing->execute();

            if ($existing->fetch()) {
                $upd = $this->prepare(
                    "UPDATE $seenTable SET checked_at=:now, last_seen_count=:fcount " .
                    "WHERE admin_id=:aid AND module=:module"
                );
                $upd->bindValue(':now', $now);
                $upd->bindValue(':fcount', $fileCount, \PDO::PARAM_INT);
                $upd->bindValue(':aid', $adminId, \PDO::PARAM_INT);
                $upd->bindValue(':module', $module);
                $upd->execute();
            } else {
                $seenModel->insert([
                    'admin_id' => $adminId,
                    'module' => $module,
                    'checked_at' => $now,
                    'last_seen_count' => $fileCount
                ]);
            }

            $this->respondJSON(['status' => 'success']);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        }
    }
}
