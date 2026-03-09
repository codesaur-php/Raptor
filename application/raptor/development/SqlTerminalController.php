<?php

namespace Raptor\Development;

use Psr\Log\LogLevel;

/**
 * Class SqlTerminalController
 * ------------------------------------------------------------------
 * MySQL Terminal - SQL query гүйцэтгэх контроллер.
 *
 * Зөвхөн system_coder (id=1) эрхтэй хэрэглэгчид л
 * энэ модулийг ашиглах боломжтой.
 *
 * @package Raptor\Development
 */
class SqlTerminalController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

    /**
     * SQL Terminal хуудсыг харуулах.
     *
     * @return void
     */
    public function index()
    {
        if (!$this->isUser('system_coder') || $this->getUserId() !== 1) {
            $this->dashboardProhibited(null, 403)->render();
            return;
        }

        $dashboard = $this->twigDashboard(__DIR__ . '/sql-terminal.html');
        $dashboard->set('title', 'MySQL Terminal');
        $dashboard->render();

        $this->log('sql_terminal', LogLevel::NOTICE, 'MySQL Terminal хуудсыг нээлээ');
    }

    /**
     * SQL query гүйцэтгэх.
     *
     * Зөвхөн SELECT, SHOW, DESCRIBE, EXPLAIN query зөвшөөрнө.
     * INSERT, UPDATE, DELETE, ALTER, DROP, CREATE, TRUNCATE зэргийг
     * тусад нь зөвшөөрөх/хориглох тохиргоотой.
     *
     * @return void JSON хариулт
     */
    public function execute()
    {
        try {
            if (!$this->isUser('system_coder') || $this->getUserId() !== 1) {
                throw new \Exception('Энэ үйлдлийг гүйцэтгэх эрхгүй байна', 403);
            }

            $payload = $this->getParsedBody();
            $sql = \trim($payload['query'] ?? '');
            if (empty($sql)) {
                throw new \InvalidArgumentException('SQL query хоосон байна', 400);
            }

            // SQL comment-уудыг алгасаж эхний бодит командыг олох
            $stripped = \preg_replace('/--[^\n]*/', '', $sql);        // -- comment
            $stripped = \preg_replace('/\/\*.*?\*\//s', '', $stripped); // /* comment */
            $stripped = \trim($stripped);
            if (empty($stripped)) {
                throw new \InvalidArgumentException('SQL query хоосон байна (зөвхөн comment)', 400);
            }
            $firstWord = \strtoupper(\strtok($stripped, " \t\n\r("));
            $readOnly = ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'];
            $writeAllowed = ['INSERT', 'UPDATE', 'DELETE', 'ALTER', 'DROP', 'CREATE', 'TRUNCATE'];
            $allowWrite = !empty($payload['allow_write']);

            if (!\in_array($firstWord, $readOnly) && !\in_array($firstWord, $writeAllowed)) {
                throw new \Exception("Зөвшөөрөгдөөгүй SQL комманд: $firstWord", 400);
            }

            if (\in_array($firstWord, $writeAllowed) && !$allowWrite) {
                throw new \Exception("Бичих эрхтэй query ($firstWord) ажиллуулахын тулд 'Бичих зөвшөөрөх' сонголтыг идэвхжүүлнэ үү", 400);
            }

            $startTime = \microtime(true);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $duration = \round((\microtime(true) - $startTime) * 1000, 2);

            $result = [
                'status' => 'success',
                'duration' => $duration,
                'query' => $sql
            ];

            if (\in_array($firstWord, $readOnly)) {
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $result['columns'] = !empty($rows) ? \array_keys($rows[0]) : [];
                $result['rows'] = $rows;
                $result['row_count'] = \count($rows);
            } else {
                $result['affected_rows'] = $stmt->rowCount();
                $result['message'] = "Амжилттай. {$stmt->rowCount()} мөр өөрчлөгдлөө.";
            }

            $this->respondJSON($result);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status' => 'error',
                'message' => $err->getMessage()
            ], $err->getCode() ?: 500);
        } finally {
            $context = ['action' => 'execute', 'query' => $sql ?? ''];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'SQL Terminal алдаа: ' . ($err->getMessage());
            } else {
                $level = LogLevel::INFO;
                $message = 'SQL query гүйцэтгэлээ';
                $context['duration_ms'] = $duration ?? 0;
            }
            $this->log('sql_terminal', $level, $message, $context);
        }
    }
}
