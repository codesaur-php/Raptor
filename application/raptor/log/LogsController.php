<?php

namespace Raptor\Log;

use codesaur\DataObject\Constants;
use codesaur\Template\FileTemplate;

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
 * @package Raptor\Log
 */
class LogsController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

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

            if ($this->getDriverName() == Constants::DRIVER_PGSQL) {
                $query =
                    'SELECT tablename FROM pg_catalog.pg_tables ' .
                    "WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema' AND tablename like '%_log'";
            } else {
                $query = 'SHOW TABLES LIKE ' . $this->quote('%_log');
            }

            $log_tables = [];
            $pdostmt = $this->prepare($query);
            if ($pdostmt->execute()) {
                // Жишээ: dashboard_log -> dashboard
                while ($row = $pdostmt->fetch()) {
                    $log_tables[] = \substr(\current($row), 0, -\strlen('_log'));
                }
            }

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

        } catch (\Throwable $err) {
            $this->modalProhibited($err->getMessage(), $err->getCode())->render();
        }
    }

    /**
     * Логийн ашиглалтын өгөгдлийг AJAX-р авах API.
     *
     * Энэ API нь UI дээрх:
     *   - хайлт
     *   - шүүлтүүр (context.action, context.alias ...)
     *   - ORDER BY
     *   - LIMIT
     * бүгдийг хариуцдаг.
     *
     * Request format:
     * ---------------------------------------------
     * POST /dashboard/logs/retrieve?table=dashboard
     * Body (JSON):
     * {
     *      "ORDER BY": "id DESC",
     *      "LIMIT": 100,
     *      "CONTEXT": {
     *          "action": "rbac-*",
     *          "alias": "system"
     *      }
     * }
     *
     * @return void
     */
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

            // Filter болон Query нөхцөл
            $condition = $this->getParsedBody();
            $context = $condition['CONTEXT'] ?? null;

            // Client-ээс ирсэн ORDER BY, LIMIT, OFFSET-ийг sanitize хийх
            $safeCondition = [];
            if (!empty($condition['ORDER BY']) && \preg_match('/^[a-zA-Z_]+\s+(ASC|DESC|asc|desc)$/i', $condition['ORDER BY'])) {
                $safeCondition['ORDER BY'] = $condition['ORDER BY'];
            }
            // LIMIT-ийг хязгаарлах: client ямар ч утга илгээсэн дээд тал нь 200.
            $clientLimit = (int) ($condition['LIMIT'] ?? 0);
            $safeCondition['LIMIT'] = $clientLimit > 0 ? \min($clientLimit, 200) : 100;
            // OFFSET - pagination-д шаардлагатай. Үгүй бол infinite scroll
            // ижил мөрүүдийг буцааж, JS-ийн "items.length < limit" stop нөхцөлд хэзээ ч хүрэхгүй.
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

            $logger = new Logger($this->pdo);
            $logger->setTable($table);
            $this->respondJSON($logger->getLogs($condition));
        } catch (\Throwable $err) {
            $this->respondJSON(['error' => $err->getMessage()], $err->getCode());
        }
    }
}
