<?php

namespace Dashboard\Log;

use codesaur\DataObject\Constants;
use codesaur\Template\FileTemplate;

use Dashboard\Organization\OrganizationModel;
use Dashboard\Organization\OrganizationUserModel;
use Dashboard\Trash\TrashModel;
use Dashboard\User\UsersModel;

/**
 * Class LogsController
 * 
 * Raptor Framework-ийн Log module-ийн үндсэн Controller.
 * 
 * Логтой холбоотой дараах 3 үндсэн үйлдлийг хариуцна:
 * ---------------------------------------------------------------
 * 1) index()   
 *      -> Логийн бүх _log хүснэгтийн жагсаалтыг харуулах
 * 
 * 2) view()    
 *      -> Нэг логийн дэлгэрэнгүйг modal-аар үзүүлэх
 * 
 * 3) retrieve()
 *      -> Логийн өгөгдлийг AJAX-р шүүх, хайх, ORDER BY, LIMIT хийх  
 *         (UI-ийн JS fetch() -> JSON response)
 * 
 * Аюулгүй байдлын нөхцөл:
 *      -> Хэрэглэгч 'system_logger' эрхтэй байх ёстой.
 * 
 * @package Dashboard\Log
 */
class LogsController extends \Dashboard\Controller
{
    use \Dashboard\Template\DashboardTrait;

    /** Хэрэглэгчийн үйл ажиллагааны хуудасны tab-ууд (userActivityRetrieve() view). */
    public const AUDIT_VIEWS = ['actions', 'received', 'logins', 'emails', 'deleted'];

    /** Аудитын хуудасны нэг удаагийн мөрийн тоо. */
    public const AUDIT_PAGE = 50;

    /** CSV export-ын дээд мөрийн тоо (санах ой, хугацааны хязгаар). */
    public const AUDIT_EXPORT_LIMIT = 10000;

    /** Байгууллагаар салгах боломжгүй view - системийн бус байгууллагын аудиторт хаалттай. */
    public const UNSCOPABLE_VIEWS = ['emails', 'deleted'];

    /**
     * "Хийсэн үйлдэл" tab-ын үйлдлийн төрлийн шүүлт: бүлэг -> context.action-ийн
     * яг утгууд (exact) ба LIKE загварууд (like). Модулиуд ижил үйлдлийг өөр
     * нэрээр бичдэг (insert / create / store ...) тул бүлэглэнэ. Шинэ module
     * шинэ action нэр нэмбэл энд тохирох бүлэгт нэмнэ.
     *
     * Coupling (English): modules name the same kind of action differently, so
     * a new module's action names must be added here to be found by the filter
     * (they still always show under "All").
     */
    public const ACTION_GROUPS = [
        'create' => [
            'exact' => ['create', 'insert', 'store', 'files-upload', 'files-post', 'migration-upload', 'comment-reply', 'respond'],
            'like'  => ['%-create', '%-insert', 'rbac-create-%']
        ],
        'update' => [
            'exact' => [
                'update', 'set-role', 'set-password', 'set-organization', 'strip-role', 'strip-organization',
                'sort', 'reset', 'update-status', 'update-env', 'settings-post', 'mark-replied',
                'signup-approve', 'signup-reject', 'migration-apply', 'restore', 'trash-restore', 'files-update'
            ],
            'like'  => ['%-update', '%-sort', 'rbac-set-%']
        ],
        'delete' => [
            'exact' => ['delete', 'deactivate', 'trash-delete', 'trash-empty', 'migration-delete', 'signup-delete'],
            'like'  => ['%-delete']
        ],
        'view' => [
            'exact' => ['index', 'view', 'search', 'requests-modal', 'template-menu-manage', 'settings-files'],
            'like'  => ['%-index', '%-view']
        ],
        'session' => [
            'exact' => ['login', 'logout', 'login-to-organization', 'act-as-start', 'act-as-stop', 'change-language'],
            'like'  => []
        ]
    ];

    /**
     * Логийн бүх хүснэгтийн жагсаалтыг харуулах Dashboard хуудас.
     *
     * Процесс:
     * ---------------------------------------------------------------
     * 1) Хэрэглэгч log харах эрхтэй эсэхийг шалгана.
     * 2) MySQL / PostgreSQL аль ч тохиолдолд *_log нэртэй хүснэгтүүдийг олно.
     * 3) Тэдгээрийг dashboard template-д дамжуулж харуулна.
     *
     * @return void
     */
    public function index()
    {
        try {
            if (!$this->isUserCan('system_logger')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $log_tables = $this->logTables();

            // Error log файлын мэдээлэл (system_coder эрхтэй хэрэглэгчид)
            $errorLogLines = 0;
            $showErrorLog = $this->isUser('system_coder');
            if ($showErrorLog) {
                $logFile = \ini_get('error_log');
                if (\is_file($logFile)) {
                    $fh = \fopen($logFile, 'r');
                    if ($fh) {
                        while (!\feof($fh)) {
                            $errorLogLines += \substr_count(\fread($fh, 65536), "\n");
                        }
                        \fclose($fh);
                    }
                }
            }

            $dashboard = $this->dashboardTemplate(
                __DIR__ . '/index-list-logs.html',
                [
                    'log_tables' => $log_tables,
                    'show_error_log' => $showErrorLog,
                    'error_log_lines' => $errorLogLines
                ]
            );
            $dashboard->set('title', $this->text('log'));
            $dashboard->render();
            // Бүртгэлийг хэн харсныг бүртгэнэ (Нууцлалын бодлогод амласан)
            $this->log('dashboard', \Psr\Log\LogLevel::NOTICE, 'Хандалтын протоколыг нээлээ', ['action' => 'logs-index']);
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        }
    }

    /**
     * Нэг логийн бичлэгийг modal-аар харуулах.
     *
     * Query params:
     * ----------------------------------------
     * ?id=123
     * ?table=dashboard
     *
     * Процесс:
     * 1) Параметр шалгах (id тоон байх, хүснэгт зөв байх)
     * 2) Logger model -> setTable()
     * 3) getLogById(id) -> log data
     * 4) retrieve-log-modal.html template-д дамжуулж render хийх
     *
     * @return void
     */
    public function view()
    {
        try {
            if (!$this->isUserCan('system_logger')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $params = $this->getQueryParams();
            $param_id = $params['id'] ?? null;
            $table_name = $params['table'] ?? null;

            // Аюулгүй байдлын үүднээс хүснэгтийн нэрийг цэвэрлэнэ
            $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $table_name);

            if ($param_id === null || !\is_numeric($param_id)
                || empty($table) || !$this->hasTable("{$table}_log")) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = (int) $param_id;

            $logger = new Logger($this->pdo);
            $logger->setTable($table);
            $log = $logger->getLogById($id);

            (new FileTemplate(
                __DIR__ . '/retrieve-log-modal.html',
                [
                    'id' => (int) $id,
                    'table' => $table,
                    'logdata' => $log,
                    'close' => $this->text('close'),
                    'log_caption' => $this->text('log')
                ]
            ))->render();
            $this->log(
                'dashboard',
                \Psr\Log\LogLevel::NOTICE,
                // record_id биш log_id: dashboard_log-ийн record_id-аар шүүдэг бусад
                // бичлэгийн Logger Protocol-д буруу гарахгүйн тулд
                '{table}_log хүснэгтийн #{log_id} бүртгэлийг харлаа',
                ['action' => 'logs-view', 'log_id' => $id, 'table' => $table]
            );

        } catch (\Throwable $err) {
            $this->modalProhibited($err->getMessage(), $err->getCode())->render();
        }
    }

    /**
     * Error log файлыг сүүлээс нь 100 мөрөөр уншиж JSON буцаах.
     *
     * Зөвхөн system_coder эрхтэй хэрэглэгчид хандах боломжтой.
     *
     * Query params:
     *   page=1 -> хамгийн сүүлийн 100 мөр
     *   page=2 -> түүнээс өмнөх 100 мөр гэх мэт
     *
     * @return void
     */
    public function errorLogRead()
    {
        try {
            if (!$this->isUser('system_coder')) {
                throw new \Exception('Access denied', 403);
            }

            $logFile = \ini_get('error_log');
            if (!\is_file($logFile)) {
                throw new \Exception('Error log file not found', 404);
            }

            $params = $this->getQueryParams();
            $page = \max(1, (int)($params['page'] ?? 1));
            $perPage = 100;

            // Нийт мөрийн тоо тоолох
            $totalLines = 0;
            $fh = \fopen($logFile, 'r');
            if ($fh) {
                while (!\feof($fh)) {
                    $totalLines += \substr_count(\fread($fh, 65536), "\n");
                }
                \fclose($fh);
            }

            $totalPages = \max(1, (int)\ceil($totalLines / $perPage));

            // Сүүлээс нь уншихдаа page=1 -> хамгийн сүүлийн мөрүүд
            $endLine = $totalLines - (($page - 1) * $perPage);
            $startLine = \max(1, $endLine - $perPage + 1);

            $lines = [];
            if ($endLine > 0) {
                $fh = \fopen($logFile, 'r');
                if ($fh) {
                    $current = 0;
                    while (($line = \fgets($fh)) !== false) {
                        $current++;
                        if ($current >= $startLine && $current <= $endLine) {
                            $lines[] = \rtrim($line);
                        }
                        if ($current > $endLine) {
                            break;
                        }
                    }
                    \fclose($fh);
                }
                $lines = \array_reverse($lines);
            }

            $this->respondJSON([
                'status' => 'success',
                'lines' => $lines,
                'page' => $page,
                'total_pages' => $totalPages,
                'total_lines' => $totalLines,
                'from_line' => $startLine,
                'to_line' => $endLine
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status' => 'error',
                'message' => $err->getMessage()
            ], $err->getCode() ?: 500);
        }
    }

    /**
     * Лог бичлэгүүдийг хүснэгтээс хайж JSON буцаах.
     *
     * Query params-аар хүснэгтийн нэр, шүүлтүүр, хуудаслалт зэргийг хүлээн авна.
     *
     * @return void
     */
    public function retrieve()
    {
        try {
            if (!$this->isUserCan('system_logger')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $params = $this->getQueryParams();
            $table_name = $params['table'] ?? null;
            $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $table_name);
            if (empty($table) || !$this->hasTable("{$table}_log")) {
                throw new \InvalidArgumentException($this->text('invalid-request'));
            }

            $logger = new Logger($this->pdo);
            $logger->setTable($table);

            // Filter болон Query нөхцөл
            $condition = $this->getParsedBody();
            $context = $condition['CONTEXT'] ?? null;

            // Client-ээс ирсэн ORDER BY, LIMIT, OFFSET-ийг sanitize хийх.
            // ORDER BY: "column ASC|DESC" хэлбэрийг задалж Logger::orderBy() whitelist-ээр
            // дамжуулна - багана нь log хүснэгтэд зарлагдсан байх ёстой, чиглэл зөвхөн
            // ASC/DESC, identifier нь driver-т тохирсон хашилттай гарна. Зарлагдаагүй
            // багана эсвэл буруу хэлбэр бол анхдагч "id DESC" (шинэ лог эхэнд).
            $safeCondition = ['ORDER BY' => $logger->orderBy(Constants::COL_ID, 'DESC')];
            if (!empty($condition['ORDER BY'])
                && \is_string($condition['ORDER BY'])
                && \preg_match('/^([a-zA-Z_]+)\s+(ASC|DESC)$/i', $condition['ORDER BY'], $orderMatch)
                && $logger->hasColumn($orderMatch[1])
            ) {
                $safeCondition['ORDER BY'] = $logger->orderBy($orderMatch[1], $orderMatch[2]);
            }
            // LIMIT-ийг хязгаарлах: client ямар ч утга илгээсэн дээд тал нь 200.
            $clientLimit = (int) ($condition['LIMIT'] ?? 0);
            $safeCondition['LIMIT'] = $clientLimit > 0 ? \min($clientLimit, 200) : 100;
            // OFFSET - pagination-д шаардлагатай. Үгүй бол infinite scroll
            // ижил мөрүүдийг буцааж, JS-ийн "items.length < limit" stop нөхцөлд хэзээ ч хүрэхгүй.
            // Coupling (English): the infinite scroll in dashboard.js stops on
            // "items.length < limit" - without OFFSET support here it would keep
            // receiving the same rows forever and never terminate.
            $clientOffset = (int) ($condition['OFFSET'] ?? 0);
            if ($clientOffset > 0) {
                $safeCondition['OFFSET'] = $clientOffset;
            }
            $condition = $safeCondition;

            // JSON талбарын хайлтыг MySQL / PostgreSQL-д тааруулан хийх
            $wheres = [];
            foreach (\is_array($context) ? $context : [] as $field => $value) {
                if (!\is_string($value) || !\is_string($field)) {
                    continue;
                }

                // Field нэрийг sanitize (зөвхөн үсэг, тоо, доогуур зураас, цэг)
                if (!\preg_match('/^[a-zA-Z0-9_.]+$/', $field)) {
                    continue;
                }

                $isLike = \strpos($value, '*') !== false;
                if ($isLike) {
                    $value = \str_replace('*', '%', $value);
                }
                $quotedValue = $this->quote($value);

                $keys = \explode('.', $field);

                if ($this->getDriverName() == Constants::DRIVER_PGSQL) {
                    // PostgreSQL JSONB -> a->'b'->>'c'
                    $expr = '(context::jsonb)';
                    $lastKey = \array_pop($keys);
                    foreach ($keys as $k) {
                        $expr .= "->'$k'";
                    }
                    $expr .= "->>'$lastKey'";
                } else {
                    // MySQL JSON_EXTRACT
                    $jsonPath = '$';
                    foreach ($keys as $k) {
                        $jsonPath .= ".$k";
                    }
                    $expr = "JSON_UNQUOTE(JSON_EXTRACT(context, '$jsonPath'))";
                }

                $wheres[] = $isLike ? "$expr LIKE $quotedValue" : "$expr=$quotedValue";
            }
            $clause = \implode(' AND ', $wheres);
            if (!empty($clause)) {
                $condition['WHERE'] = empty($condition['WHERE'])
                    ? $clause
                    : $condition['WHERE'] . ' AND ' . $clause;
            }

            $this->respondJSON($logger->getLogs($condition));
        } catch (\Throwable $err) {
            $this->respondJSON(['error' => $err->getMessage()], $err->getCode() ?: 500);
        }
    }

    /**
     * Хэрэглэгчийн үйл ажиллагаа (audit trail) - сонгосон хэрэглэгчийн бүх
     * *_log хүснэгтэд үлдээсэн үйлдлийг нэг timeline-д харуулах хуудас.
     *
     * Route: GET /dashboard/user-activity
     *
     * Эрх: system_audit (admin дүрд анхдагчаар, system_coder үргэлж). Шинэ лог
     * цуглуулахгүй - байгаа логийг auth_user.id-аар шүүж харуулна. Зөвхөн уншина.
     *
     * Security (English): requires system_audit. Outside the system organization
     * only the members of the current organization can be selected (never a
     * system_coder), only entries made while signed in to that organization are
     * returned (auditScope()), and the emails / deleted views are closed because
     * that data carries no organization. The page is read-only - no log is ever
     * edited or deleted.
     *
     * @return void
     */
    public function userActivity()
    {
        try {
            if (!$this->isUserCan('system_audit')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $orgId = $this->auditScope();
            $users = (new UsersModel($this->pdo))->getName();
            $scope = '';
            if ($orgId !== null) {
                $members = (new OrganizationUserModel($this->pdo))->getName();
                $scope = " WHERE u.id IN (SELECT ou.user_id FROM $members ou WHERE ou.organization_id = :org)";
            }
            // Идэвхгүй болсон хэрэглэгч ч audit-д хэрэгтэй тул is_active-аар шүүхгүй
            $stmt = $this->prepare(
                "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.is_active
                   FROM $users u$scope ORDER BY u.first_name, u.last_name, u.username"
            );
            if ($orgId !== null) {
                $stmt->bindValue(':org', $orgId, \PDO::PARAM_INT);
            }
            $rows = $stmt->execute() ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
            if ($orgId !== null) {
                // Хүрээтэй аудитор system_coder хэрэглэгчийг харахгүй (retrieve ч 403)
                $coders = $this->coderIds();
                $rows = \array_values(\array_filter($rows, fn($u) => !\in_array((int) $u['id'], $coders, true)));
            }

            // Байгууллагаар горимын сонголт: системийн байгууллагаар нэвтэрсэн бол бүх
            // байгууллага, бусад байгууллагын аудиторт зөвхөн өөрийн идэвхтэй байгууллага
            $organizations = [];
            if ($orgId === null) {
                $orgs = (new OrganizationModel($this->pdo))->getName();
                $orgStmt = $this->prepare("SELECT id, name, is_active FROM $orgs ORDER BY name");
                foreach ($orgStmt->execute() ? $orgStmt->fetchAll(\PDO::FETCH_ASSOC) : [] as $o) {
                    $organizations[] = ['id' => (int) $o['id'], 'name' => (string) $o['name'], 'active' => (int) $o['is_active'] === 1];
                }
            } else {
                $organizations[] = ['id' => $orgId, 'name' => (string) ($this->getUser()->organization['name'] ?? ''), 'active' => true];
            }

            $dashboard = $this->dashboardTemplate(__DIR__ . '/user-activity.html', [
                'organizations' => $organizations,
                'export_limit' => self::AUDIT_EXPORT_LIMIT,
                'hidden_views' => $orgId === null ? [] : self::UNSCOPABLE_VIEWS,
                'users' => \array_map(fn($u) => [
                    'id' => (int) $u['id'],
                    'name' => \trim("{$u['first_name']} {$u['last_name']}") ?: (string) $u['username'],
                    'username' => (string) $u['username'],
                    'email' => (string) $u['email'],
                    'active' => (int) $u['is_active'] === 1
                ], $rows),
                'log_tables' => $this->logTables(),
                'scoped_organization' => $orgId === null ? null : ($this->getUser()->organization['name'] ?? '')
            ]);
            $dashboard->set('title', $this->getLanguageCode() === 'mn' ? 'Хэрэглэгчийн үйл ажиллагаа' : 'User activity');
            $dashboard->render();
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        }
    }

    /**
     * Аудитын өгөгдлийг огнооны дарааллаар (шинэ нь эхэнд) JSON-оор буцаана.
     *
     * Route: GET /dashboard/user-activity/retrieve
     * Query: auditQuery()-г үз + offset. Хуудас бүр AUDIT_PAGE мөр.
     *
     * Анхны хуудсыг (offset=0) уншихад хэн, юуг харсныг dashboard_log-д бичнэ
     * (audit-ыг хянах).
     *
     * @return void
     */
    public function userActivityRetrieve()
    {
        try {
            if (!$this->isUserCan('system_audit')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $params = $this->getQueryParams();
            $offset = \max(0, (int) ($params['offset'] ?? 0));
            $q = $this->auditQuery($params);
            $rows = $this->auditRows($q, self::AUDIT_PAGE, $offset);
            $this->respondJSON(['status' => 'success', 'rows' => $rows, 'limit' => self::AUDIT_PAGE]);
        } catch (\Throwable $err) {
            $code = (int) $err->getCode();
            $this->respondJSON(['message' => $err->getMessage()], $code >= 400 && $code < 600 ? $code : 500);
        } finally {
            if (!isset($err) && ($offset ?? 0) === 0) {
                $this->log(
                    'dashboard',
                    \Psr\Log\LogLevel::NOTICE,
                    ($q['mode'] ?? 'user') === 'org'
                        ? 'Байгууллагын үйл ажиллагааг харлаа (#{target_organization_id})'
                        : 'Хэрэглэгчийн үйл ажиллагааг харлаа (#{target_user_id})',
                    ['action' => 'user-activity-view'] + $this->auditLogContext($q ?? [])
                );
            }
        }
    }

    /**
     * Шүүсэн аудитын өгөгдлийг CSV файлаар татуулна (AUDIT_EXPORT_LIMIT мөр хүртэл).
     *
     * Route: GET /dashboard/user-activity/export
     * Query: auditQuery()-тэй ижил.
     *
     * Excel-д зөв нээгдэхийн тулд UTF-8 BOM-тэй. Нүд "=", "+", "-", "@", tab,
     * CR-ээр эхэлбэл урд нь "'" нэмнэ (CSV / formula injection, OWASP).
     * Export бүрийг dashboard_log-д (user-activity-export) мөрийн тоотой бичнэ.
     *
     * Security (English): same permission and scope checks as retrieve
     * (auditQuery()); cells are neutralized against spreadsheet formula
     * injection, because log values come from users and public forms.
     *
     * @return void
     */
    public function userActivityExport()
    {
        try {
            if (!$this->isUserCan('system_audit')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $q = $this->auditQuery($this->getQueryParams());
            $rows = $this->auditRows($q, self::AUDIT_EXPORT_LIMIT, 0);

            $cell = function ($v): string {
                $v = \is_scalar($v) || $v === null ? (string) $v : \json_encode($v, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
                return \preg_match('/^[=+\-@\t\r]/', $v) ? "'$v" : $v;
            };
            $name = ($q['mode'] === 'org' ? "organization-{$q['org']}" : "user-{$q['user_id']}")
                . '-' . $q['view'] . '-' . \date('Ymd-His') . '.csv';
            \header('Content-Type: text/csv; charset=utf-8');
            \header("Content-Disposition: attachment; filename=\"$name\"");
            \header('Cache-Control: no-store');
            $out = \fopen('php://output', 'w');
            \fwrite($out, "\xEF\xBB\xBF");
            \fputcsv($out, [
                'created_at', 'table', 'level', 'action', 'message', 'record_id', 'record_organization_id',
                'user_id', 'username', 'organization_id', 'act_as', 'ip', 'user_agent', 'context'
            ], ',', '"', '');
            foreach ($rows as $r) {
                $c = $r['context'] ?? [];
                $message = \html_entity_decode(\strip_tags((string) ($r['message'] ?? '')), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
                \fputcsv($out, \array_map($cell, [
                    $r['created_at'] ?? '', $r['table'] ?? '', $r['level'] ?? '', $c['action'] ?? '', $message,
                    $c['record_id'] ?? $c['original_id'] ?? '', $c['record_organization_id'] ?? '',
                    $c['auth_user']['id'] ?? '', $c['auth_user']['username'] ?? '', $c['auth_user']['organization_id'] ?? '',
                    $c['auth_user']['act_as'] ?? '', $c['server_request']['remote_addr'] ?? $c['remote_addr'] ?? '',
                    $c['server_request']['user_agent'] ?? '', $c
                ]), ',', '"', '');
            }
            \fclose($out);
        } catch (\Throwable $err) {
            $code = (int) $err->getCode();
            $this->respondJSON(['message' => $err->getMessage()], $code >= 400 && $code < 600 ? $code : 500);
        } finally {
            if (!isset($err)) {
                $this->log(
                    'dashboard',
                    \Psr\Log\LogLevel::NOTICE,
                    'Аудитын бүртгэлийг CSV-ээр татлаа ({rows} мөр)',
                    ['action' => 'user-activity-export', 'rows' => \count($rows ?? [])] + $this->auditLogContext($q ?? [])
                );
            }
        }
    }

    /**
     * Аудитын хүсэлтийн параметрийг шалгаж, эрх ба байгууллагын хязгаарыг хэрэгжүүлнэ.
     *
     * Query:
     *  - mode     - user (анхдагч) | org
     *  - user_id  - mode=user: заавал (аудит хийх хэрэглэгч); mode=org: заавал биш (үйлдэл хийсэн хүн)
     *  - org_id   - mode=org: системийн байгууллагаар нэвтэрсэн бол заавал; бусад байгууллагын аудиторт
     *               зөвхөн өөрийн идэвхтэй байгууллага (хоосон бол түүнийг авна)
     *  - view     - AUDIT_VIEWS (mode=org: зөвхөн actions)
     *  - group    - ACTION_GROUPS түлхүүр (actions-д)
     *  - table, from, to (YYYY-MM-DD)
     *
     * Security (English): every scope rule lives here so retrieve and export can
     * never diverge - membership, no system_coder targets, UNSCOPABLE_VIEWS and
     * the organization lock for a scoped auditor.
     *
     * @param array $params
     * @return array Шалгасан параметрүүд
     */
    private function auditQuery(array $params): array
    {
        $scope = $this->auditScope();
        $mode = ($params['mode'] ?? 'user') === 'org' ? 'org' : 'user';
        $userId = (int) ($params['user_id'] ?? 0);
        $view = (string) ($params['view'] ?? 'actions');
        $group = (string) ($params['group'] ?? '');
        $only = (string) ($params['table'] ?? '');
        $date = fn($v) => \is_string($v) && \preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && \strtotime($v) !== false ? $v : null;

        if (!\in_array($view, self::AUDIT_VIEWS, true)
            || ($mode === 'org' && $view !== 'actions')
            || ($group !== '' && !isset(self::ACTION_GROUPS[$group]))
            || ($mode === 'user' && $userId <= 0)
            || $userId < 0
        ) {
            throw new \InvalidArgumentException($this->text('invalid-request'), 400);
        }
        $tables = $this->logTables();
        if ($only !== '') {
            if (!\in_array($only, $tables, true)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $tables = [$only];
        }

        $org = null;
        if ($mode === 'org') {
            $org = (int) ($params['org_id'] ?? 0);
            if ($scope !== null) {
                // Хүрээтэй аудитор зөвхөн өөрийн идэвхтэй байгууллагыг харна
                if ($org === 0) {
                    $org = $scope;
                } elseif ($org !== $scope) {
                    throw new \Exception($this->text('system-no-permission'), 403);
                }
            } elseif ($org <= 0 || empty((new OrganizationModel($this->pdo))->getRowWhere(['id' => $org]))) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
        }

        $target = null;
        if ($userId > 0) {
            $target = (new UsersModel($this->pdo))->getRowWhere(['id' => $userId]);
            if (empty($target)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 404);
            }
        }
        if ($scope !== null) {
            // Хүрээтэй аудитор: хэрэглэгч тэр байгууллагын гишүүн, system_coder биш байх;
            // имэйл, хогийн сав байгууллагын id хадгалдаггүй тул хаалттай.
            //
            // Security (English): mailer_log and trash carry no organization, so
            // they cannot be limited to the auditor's tenant - a scoped auditor
            // never gets them. Coders are never auditable by a tenant.
            if (\in_array($view, self::UNSCOPABLE_VIEWS, true)) {
                throw new \Exception($this->text('system-no-permission'), 403);
            }
            if ($userId > 0) {
                $members = (new OrganizationUserModel($this->pdo))->getName();
                $check = $this->prepare("SELECT 1 FROM $members WHERE user_id = :u AND organization_id = :org");
                $check->bindValue(':u', $userId, \PDO::PARAM_INT);
                $check->bindValue(':org', $scope, \PDO::PARAM_INT);
                if (!$check->execute() || !$check->fetchColumn()
                    || \in_array($userId, $this->coderIds(), true)
                ) {
                    throw new \Exception($this->text('system-no-permission'), 403);
                }
            }
        }

        return [
            'mode' => $mode,
            'scope' => $scope,
            'org' => $org,
            'user_id' => $userId,
            'target' => $target,
            'view' => $view,
            'group' => $group,
            'only' => $only,
            'tables' => $tables,
            'from' => $date($params['from'] ?? null),
            'to' => $date($params['to'] ?? null)
        ];
    }

    /**
     * auditQuery()-ийн үр дүнгээр аудитын мөрүүдийг уншина.
     *
     * mode=user - view-ээс хамаарна (actions / received / logins / emails / deleted).
     * mode=org  - байгууллагын бүх үйлдэл: COALESCE(record_organization_id,
     *             auth_user.organization_id) = org (BadgeController-ийн байгууллагын
     *             дүрэмтэй адил - системийн ажилтан tenant-ийн бичлэг дээр ажилласан ч
     *             тэр tenant-д тооцогдоно), user_id өгвөл тэр хүний үйлдлээр шүүнэ.
     *             Хүрээтэй аудиторт system_coder-ийн үйлдэл орохгүй.
     *
     * @param array $q      auditQuery() үр дүн
     * @param int   $limit
     * @param int   $offset
     * @return array
     */
    private function auditRows(array $q, int $limit, int $offset): array
    {
        $userId = $q['user_id'];
        $orgId = $q['scope'];
        $view = $q['view'];
        $group = $q['group'];
        $from = $q['from'];
        $to = $q['to'];

        if ($view === 'deleted') {
            return $this->deletedByUser($userId, $from, $to, $limit, $offset);
        }

        $json = fn(string $path) => $this->jsonExpr($path);
        $existing = $this->logTables();
        $groupSql = null;
        if ($view === 'actions' && $group !== '') {
            // Утгууд нь ACTION_GROUPS тогтмолоос (client-ийн утга биш) - quote() хийж шууд оруулна
            $g = self::ACTION_GROUPS[$group];
            $or = [];
            if (!empty($g['exact'])) {
                $or[] = $json('action') . ' IN (' . \implode(', ', \array_map(fn($a) => $this->quote($a), $g['exact'])) . ')';
            }
            foreach ($g['like'] as $pattern) {
                $or[] = $json('action') . ' LIKE ' . $this->quote($pattern);
            }
            $groupSql = '(' . \implode(' OR ', $or) . ')';
        }

        // view бүрийн хүснэгт ба нөхцөл. Нөхцөл бүр :p (ба :q) параметртэй,
        // дугаар залгаж (:p0, :p1 ...) давхар нэрлэхгүй
        $spec = [];
        if ($q['mode'] === 'org') {
            $coders = $orgId !== null ? $this->coderIds() : [];
            foreach ($q['tables'] as $t) {
                $spec[$t] = [['COALESCE(' . $json('record_organization_id') . ', ' . $json('auth_user.organization_id') . ') = :p', (string) $q['org']]];
                if ($userId > 0) {
                    $spec[$t][] = [$json('auth_user.id') . ' = :p', (string) $userId];
                }
                if (!empty($coders)) {
                    // ID-ууд нь intval хийсэн бүхэл тоо (coderIds())
                    $spec[$t][] = ['(' . $json('auth_user.id') . ' IS NULL OR ' . $json('auth_user.id') . " NOT IN ('" . \implode("', '", $coders) . "'))", null];
                }
                if ($groupSql !== null) {
                    $spec[$t][] = [$groupSql, null];
                }
            }
        } else {
            switch ($view) {
                case 'actions':
                    // Хэрэглэгчийн өөрөө хийсэн үйлдэл - бүх *_log, сонгосон бол үйлдлийн төрлөөр
                    foreach ($q['tables'] as $t) {
                        $spec[$t] = [[$json('auth_user.id') . ' = :p', (string) $userId]];
                        if ($orgId !== null) {
                            $spec[$t][] = [$json('auth_user.organization_id') . ' = :p', (string) $orgId];
                        }
                        if ($groupSql !== null) {
                            $spec[$t][] = [$groupSql, null];
                        }
                    }
                    break;
                case 'received':
                    // Тухайн хэрэглэгчийн бүртгэл дээр хийгдсэн үйлдэл (профайл, дүр,
                    // байгууллага, идэвхжүүлэлт г.м.) - users_log-ийн record_id
                    if (\in_array('users', $existing, true)) {
                        $spec['users'] = [[$json('record_id') . ' = :p', (string) $userId]];
                        if ($orgId !== null) {
                            $spec['users'][] = [$json('auth_user.organization_id') . ' = :p', (string) $orgId];
                        }
                    }
                    break;
                case 'logins':
                    // Нэвтрэлт, гарах, байгууллага солих + нэвтрэх нэрээр нь амжилтгүй оролдлого
                    if (\in_array('dashboard', $existing, true)) {
                        $spec['dashboard'] = [
                            [$json('action') . " IN ('login', 'logout', 'login-to-organization')", null],
                            ['(' . $json('auth_user.id') . ' = :p OR ' . $json('server_request.body.username') . ' = :q)', (string) $userId, (string) $q['target']['username']]
                        ];
                        if ($orgId !== null) {
                            // Хүрээтэй аудитор: зөвхөн тэр байгууллага руу орсон (context.id),
                            // тэр байгууллагаас гарсан, мөн амжилтгүй оролдлого (байгууллагагүй,
                            // нэвтрэх нэрээр). Амжилттай login-д байгууллага бичигддэггүй тул орохгүй.
                            //
                            // Security (English): successful logins carry no organization, so a
                            // scoped auditor only gets switches INTO their organization, logouts
                            // from it and failed attempts - never the user's other organizations.
                            $spec['dashboard'][] = [
                                '((' . $json('action') . " = 'login-to-organization' AND " . $json('id') . ' = :p)'
                                . ' OR (' . $json('action') . " = 'logout' AND " . $json('auth_user.organization_id') . ' = :p)'
                                . ' OR (' . $json('action') . " = 'login' AND " . $json('auth_user.id') . ' IS NULL))',
                                (string) $orgId
                            ];
                        }
                    }
                    break;
                case 'emails':
                    // Хэрэглэгчийн имэйл рүү илгээсэн бүх имэйл (To / Cc / Bcc)
                    if (\in_array('mailer', $existing, true) && !empty($q['target']['email'])) {
                        $email = \strtolower(\trim((string) $q['target']['email']));
                        $like = '%' . \addcslashes(\json_encode($email, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES), '%_\\') . '%';
                        $spec['mailer'] = [['LOWER(context) LIKE :p', $like]];
                    }
                    break;
            }
            if ($q['only'] !== '') {
                $spec = \array_intersect_key($spec, [$q['only'] => true]);
            }
        }

        $parts = [];
        $binds = [];
        $n = 0;
        foreach ($spec as $table => $conditions) {
            // Хүснэгтийн нэр logTables()-аас (DB-ийн *_log жагсаалт) - client-ийн утга шууд орохгүй
            $where = [];
            foreach ($conditions as $c) {
                $sql = $c[0];
                foreach (['p' => 1, 'q' => 2] as $ph => $idx) {
                    if (\array_key_exists($idx, $c) && $c[$idx] !== null) {
                        $name = ':' . $ph . $n++;
                        $sql = \str_replace(':' . $ph, $name, $sql);
                        $binds[$name] = $c[$idx];
                    }
                }
                $where[] = $sql;
            }
            if ($from !== null) {
                $where[] = 'created_at >= :d' . $n;
                $binds[':d' . $n++] = "$from 00:00:00";
            }
            if ($to !== null) {
                $where[] = 'created_at <= :d' . $n;
                $binds[':d' . $n++] = "$to 23:59:59";
            }
            $quoted = $this->quote($table);
            $parts[] = "SELECT $quoted AS log_table, id, level, message, context, created_at FROM {$table}_log WHERE " . \implode(' AND ', $where);
        }
        $rows = [];
        if (!empty($parts)) {
            $stmt = $this->prepare(
                'SELECT * FROM (' . \implode(' UNION ALL ', $parts) . ') activity'
                . " ORDER BY created_at DESC, id DESC LIMIT $limit OFFSET $offset"
            );
            foreach ($binds as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            if ($stmt->execute()) {
                $logger = new Logger($this->pdo);
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $table = $row['log_table'];
                    unset($row['log_table']);
                    $row = $logger->normalizeLogRecord($row);
                    $row['table'] = $table;
                    $rows[] = $row;
                }
            }
        }
        return $rows;
    }

    /**
     * Аудитыг харсан / татсан лог бичлэгийн context (record_id биш - dashboard_log-ийн
     * record_id өөр бичлэгүүдийг заадаг тул Logger Protocol-д буруу гарахгүй).
     *
     * @param array $q auditQuery() үр дүн (алдаатай бол хоосон)
     * @return array
     */
    private function auditLogContext(array $q): array
    {
        return [
            'target_user_id' => ($q['user_id'] ?? 0) ?: null,
            'target_organization_id' => $q['org'] ?? null,
            'filter' => [
                'mode' => $q['mode'] ?? null,
                'view' => $q['view'] ?? null,
                'group' => $q['group'] ?? '',
                'table' => $q['only'] ?? '',
                'from' => $q['from'] ?? null,
                'to' => $q['to'] ?? null
            ]
        ];
    }

    /**
     * Хэрэглэгчийн устгасан бичлэгүүд (trash, deleted_by) - log мөртэй ижил хэлбэрээр.
     *
     * @return array
     */
    private function deletedByUser(int $userId, ?string $from, ?string $to, int $limit, int $offset): array
    {
        $trash = (new TrashModel($this->pdo))->getName();
        $where = ['deleted_by = :u'];
        $binds = [':u' => $userId];
        if ($from !== null) {
            $where[] = 'deleted_at >= :f';
            $binds[':f'] = "$from 00:00:00";
        }
        if ($to !== null) {
            $where[] = 'deleted_at <= :t';
            $binds[':t'] = "$to 23:59:59";
        }
        $stmt = $this->prepare(
            "SELECT id, table_name, log_table, original_id, record_data, deleted_at FROM $trash
              WHERE " . \implode(' AND ', $where) . " ORDER BY deleted_at DESC, id DESC LIMIT $limit OFFSET $offset"
        );
        foreach ($binds as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $rows = [];
        if ($stmt->execute()) {
            while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $data = \json_decode((string) $r['record_data'], true) ?: [];
                $label = $data['title'] ?? $data['name'] ?? $data['username'] ?? $data['keyword']
                    ?? (\is_array($data['localized'] ?? null) ? (\reset($data['localized'])['title'] ?? '') : '');
                $rows[] = [
                    'id' => (int) $r['id'],
                    'table' => 'trash',
                    'level' => 'warning',
                    'message' => "{$r['table_name']} #{$r['original_id']}" . ($label !== '' ? " - $label" : ''),
                    'created_at' => $r['deleted_at'],
                    'context' => [
                        'action' => 'delete',
                        'table_name' => $r['table_name'],
                        'log_table' => $r['log_table'],
                        'original_id' => $r['original_id'],
                        'record' => $data
                    ]
                ];
            }
        }
        return $rows;
    }

    /**
     * system_coder дүртэй хэрэглэгчдийн id.
     *
     * @return int[]
     */
    private function coderIds(): array
    {
        $roles = (new \Dashboard\RBAC\Roles($this->pdo))->getName();
        $userRoles = (new \Dashboard\RBAC\UserRole($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT ur.user_id FROM $userRoles ur INNER JOIN $roles r ON r.id = ur.role_id
              WHERE r.alias = 'system' AND r.name = 'coder'"
        );
        return $stmt->execute() ? \array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN)) : [];
    }

    /**
     * context JSON баганын замын SQL илэрхийлэл (MySQL / PostgreSQL).
     *
     * @param string $path Цэгээр тусгаарласан зам (жишээ: auth_user.id) - зөвхөн дотоод тогтмол
     * @return string
     */
    private function jsonExpr(string $path): string
    {
        $keys = \explode('.', $path);
        if ($this->getDriverName() == Constants::DRIVER_PGSQL) {
            $last = \array_pop($keys);
            $expr = '(context::jsonb)';
            foreach ($keys as $k) {
                $expr .= "->'$k'";
            }
            return "($expr->>'$last')";
        }
        return "JSON_UNQUOTE(JSON_EXTRACT(context, '$." . \implode('.', $keys) . "'))";
    }

    /**
     * Аудитор аль байгууллага дотроо харах вэ: system_coder эсвэл системийн байгууллагаар
     * (id=1) нэвтэрсэн бол null (бүх хэрэглэгч), бусад тохиолдолд одоогийн
     * байгууллагын id (MailController::organizationScope()-тэй адил дүрэм).
     *
     * Security (English): a non-system organization only audits its own
     * members, and only their entries made in that organization.
     *
     * @return int|null
     */
    private function auditScope(): ?int
    {
        $orgId = (int) ($this->getUser()?->organization['id'] ?? 0);
        if ($this->isUser('system_coder') || $orgId === 1) {
            return null;
        }
        return $orgId;
    }

    /**
     * Өгөгдлийн сангийн бүх *_log хүснэгтийн нэр ("_log"-гүй, жишээ: dashboard).
     *
     * @return string[]
     */
    private function logTables(): array
    {
        if ($this->getDriverName() == Constants::DRIVER_PGSQL) {
            $query =
                'SELECT tablename FROM pg_catalog.pg_tables ' .
                "WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema' AND tablename like '%_log'";
        } else {
            $query = 'SHOW TABLES LIKE ' . $this->quote('%_log');
        }
        $tables = [];
        $pdostmt = $this->prepare($query);
        if ($pdostmt->execute()) {
            // Жишээ: dashboard_log -> dashboard
            while ($row = $pdostmt->fetch()) {
                $name = \substr(\current($row), 0, -\strlen('_log'));
                // Identifier-т шууд орох тул зөвхөн аюулгүй тэмдэгттэй нэр
                if (\preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                    $tables[] = $name;
                }
            }
        }
        return $tables;
    }
}
