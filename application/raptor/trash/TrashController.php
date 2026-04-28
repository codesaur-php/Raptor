<?php

namespace Raptor\Trash;

use Psr\Log\LogLevel;

use codesaur\DataObject\Constants;

/**
 * Class TrashController
 *
 * Устгагдсан бичлэгүүдийн хогийн сав.
 * Бүх модулаас устгагдсан өгөгдлийг нэг дор харж,
 * шаардлагатай үед бүрэн устгах боломжтой.
 *
 * Зөвхөн system_coder эрхтэй хэрэглэгч хандана.
 *
 * @package Raptor\Trash
 */
class TrashController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Trash index хуудас.
     */
    public function index()
    {
        if (!$this->isUser('system_coder')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        $dashboard = $this->dashboardTemplate(
            __DIR__ . '/trash-index.html'
        );
        $dashboard->set('title', 'Trash');
        $dashboard->render();

        $this->log('trash', LogLevel::NOTICE, 'Trash хуудсыг нээж байна', ['action' => 'index']);
    }

    /**
     * Trash жагсаалтыг JSON-ээр буцаах.
     */
    public function list()
    {
        try {
            if (!$this->isUser('system_coder')) {
                throw new \Exception('No permission!', 401);
            }

            $model = new TrashModel($this->pdo);
            $table = $model->getName();

            $params = $this->getQueryParams();
            $conditions = [];
            $bind = [];
            $allowed = ['table_name'];
            foreach ($params as $name => $value) {
                if (\in_array($name, $allowed) && !empty($value)) {
                    $conditions[] = "$name=:$name";
                    $bind[":$name"] = $value;
                }
            }

            $where = !empty($conditions) ? \implode(' AND ', $conditions) : '1=1';
            $sql = "SELECT * FROM $table WHERE $where ORDER BY deleted_at DESC";
            $stmt = $this->prepare($sql);
            $stmt->execute($bind);
            $list = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // deleted_by -> хэрэглэгчийн нэр нэмэх
            $userDetails = $this->retrieveUsersDetail();
            foreach ($list as &$row) {
                $uid = (int)($row['deleted_by'] ?? 0);
                $row['deleted_by_name'] = $userDetails[$uid] ?? "#{$uid}";
            }

            $this->respondJSON([
                'status' => 'success',
                'list'   => $list
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status'  => 'error',
                'message' => $err->getMessage()
            ], $err->getCode());
        }
    }

    /**
     * Trash бичлэгийн дэлгэрэнгүй (JSON өгөгдөл) харах.
     */
    public function view(int $id)
    {
        try {
            if (!$this->isUser('system_coder')) {
                throw new \Exception('No permission!', 401);
            }

            $model = new TrashModel($this->pdo);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception('Record not found', 404);
            }

            $record['record_data'] = \json_decode($record['record_data'], true);

            $this->respondJSON([
                'status' => 'success',
                'record' => $record
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status'  => 'error',
                'message' => $err->getMessage()
            ], $err->getCode());
        }
    }

    /**
     * Trash бичлэгийг бүрэн устгах (permanent delete).
     */
    public function delete()
    {
        try {
            if (!$this->isUser('system_coder')) {
                throw new \Exception('No permission!', 401);
            }

            $payload = $this->getParsedBody();
            $id = (int)($payload['id'] ?? 0);
            if (empty($id)) {
                throw new \Exception('Invalid request', 400);
            }

            $model = new TrashModel($this->pdo);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception('Record not found', 404);
            }

            $model->deleteById($id);

            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => 'Бичлэгийг бүрэн устгалаа'
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            $context = ['action' => 'trash-delete'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Trash бичлэгийг бүрэн устгах явцад алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::CRITICAL;
                $message = 'Trash #{id} ({table_name}/#{original_id}) бичлэгийг бүрэн устгалаа';
                $context += [
                    'id'          => $id,
                    'table_name'  => $record['table_name'] ?? '',
                    'log_table'   => $record['log_table'] ?? '',
                    'original_id' => $record['original_id'] ?? ''
                ];
            }
            $this->log('trash', $level, $message, $context);
        }
    }

    /**
     * Trash бичлэгийг сэргээх (restore).
     *
     * Алгоритм:
     *   1) UNIQUE багана-уудыг schema-аас илрүүлж, тус бүрд DB-д давхцал байгаа эсэхийг шалгана
     *      Давхцалтай бол алдаа буцаах (ID-аас бусад UNIQUE field - slug, keyword, code г.м.)
     *   2) Эхлээд анхны (original) ID-аар insert хийх оролдлого хийнэ
     *   3) ID-н хувьд unique-violation алдаа буцвал auto-increment ID-аар дахин оролдоно
     *   4) Localized content (LocalizedModel) бол `_content` хүснэгтэд parent_id шинэ id-аар бичнэ
     *   5) Амжилттай үед trash бичлэгийг хасаж, log-д restored_by/restored_at оруулна
     *
     * @return void
     */
    public function restore(int $id)
    {
        $newId = null;
        $usedOriginalId = false;
        try {
            if (!$this->isUser('system_coder')) {
                throw new \Exception('No permission!', 401);
            }

            $trashModel = new TrashModel($this->pdo);
            $entry = $trashModel->getById($id);
            if (empty($entry)) {
                throw new \Exception('Trash record not found', 404);
            }

            $tableName = $entry['table_name'];
            $originalId = (int) $entry['original_id'];
            $logTable = $entry['log_table'];
            $data = \json_decode($entry['record_data'], true);
            if (!\is_array($data)) {
                throw new \Exception('Trash record data is corrupted (invalid JSON)', 500);
            }

            // Эх хүснэгт байгаа эсэхийг шалгах
            if (!$this->hasTable($tableName)) {
                throw new \Exception(
                    "Source table '$tableName' no longer exists. Manual restore needed.",
                    410
                );
            }

            // LocalizedModel эсэхийг тогтоох
            $localized = null;
            $primary = $data;
            if (isset($data['localized']) && \is_array($data['localized'])) {
                $localized = $data['localized'];
                unset($primary['localized']);
            }

            // 1. UNIQUE pre-flight check (ID-аас бусад)
            $conflicts = $this->findUniqueConflicts($tableName, $primary);
            if (!empty($conflicts)) {
                throw new \Exception(
                    'Cannot restore - the following UNIQUE field(s) already have a record with the same value: '
                    . \implode(', ', $conflicts) . '. '
                    . 'Either delete the conflicting record(s) first or rename their unique value(s), then try again.',
                    409
                );
            }

            // 2-3. Original ID-аар оролдох -> амжилтгүй бол auto-increment
            $newId = $this->insertPrimary($tableName, $primary, $originalId);
            $usedOriginalId = ($newId === $originalId);

            // 4. Localized content
            if ($localized !== null) {
                $this->insertLocalizedContent($tableName, $localized, $newId);
            }

            // 5. Trash-аас хасах
            $trashModel->deleteById($id);

            // 6. Сэргээгдсэн record-н log table-д "restored" мөр бичих - Logger Protocol-д
            // тухайн record-н үзэх/засах хуудас дээр харагдахын тулд. `$logTable` нь
            // store() үед log channel-ийн нэрээр шууд хадгалагдсан байна.
            $this->log(
                $logTable,
                LogLevel::ALERT,
                'Trash #{trash_id}-аас #{record_id} ({table_name}) бичлэгийг сэргээлээ',
                [
                    'action'           => 'restore',
                    'record_id'        => $newId,
                    'trash_id'         => $id,
                    'table_name'       => $tableName,
                    'original_id'      => $originalId,
                    'used_original_id' => $usedOriginalId
                ]
            );

            $userMessage = $usedOriginalId
                ? "Restored with original ID #$originalId"
                : "Original ID #$originalId is already in use. Restored with new ID #$newId. "
                . "WARNING: Foreign key references (e.g. comments referencing this record) "
                . "still point to ID #$originalId and need manual update.";

            $this->respondJSON([
                'status'           => 'success',
                'title'            => $this->text('success'),
                'message'          => $userMessage,
                'new_id'           => $newId,
                'used_original_id' => $usedOriginalId
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode() ?: 500);
        } finally {
            $context = ['action' => 'trash-restore', 'trash_id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Trash #{trash_id} бичлэгийг сэргээх явцад алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = 'Trash #{trash_id} ({table_name}) бичлэг #{original_id} -> #{new_id} сэргээгдлээ';
                $context += [
                    'table_name'       => $entry['table_name'] ?? '',
                    'log_table'        => $entry['log_table'] ?? '',
                    'original_id'      => $entry['original_id'] ?? '',
                    'new_id'           => $newId,
                    'used_original_id' => $usedOriginalId,
                    'restored_by'      => $this->getUserId(),
                    'restored_at'      => \date('Y-m-d H:i:s')
                ];
            }
            $this->log('trash', $level, $message, $context);
        }
    }

    /**
     * UNIQUE constraint-тай баганууд дээр давхцал байгаа эсэхийг шалгах.
     *
     * Schema-аас (information_schema эсвэл pg_index) UNIQUE баганаудыг олж,
     * тус бүрд $data доторх утгаар хайлт хийнэ.
     *
     * @param string $tableName Хүснэгтийн нэр
     * @param array $data Шалгах өгөгдөл (key => value)
     * @return string[] Давхцалтай "field='value'" мэдээлэл
     */
    private function findUniqueConflicts(string $tableName, array $data): array
    {
        $uniqueCols = $this->getUniqueColumns($tableName);
        $conflicts = [];
        foreach ($uniqueCols as $col) {
            // ID-г энд шалгахгүй (insert логик дотор тусгаар оролдоно)
            if ($col === 'id' || !\array_key_exists($col, $data)) {
                continue;
            }
            $val = $data[$col];
            if ($val === null || $val === '') {
                continue;
            }
            $stmt = $this->prepare("SELECT id FROM $tableName WHERE $col=:v LIMIT 1");
            $stmt->bindValue(':v', $val);
            $stmt->execute();
            if ($stmt->fetch()) {
                $conflicts[] = "$col='$val'";
            }
        }
        return $conflicts;
    }

    /**
     * Тухайн хүснэгтийн UNIQUE баганаудыг schema-аас илрүүлэх.
     *
     * MySQL ба PostgreSQL хоёрыг дэмжинэ. PRIMARY KEY-г include хийнэ
     * (caller-аас id-г шүүх ёстой).
     *
     * @param string $tableName Хүснэгтийн нэр
     * @return string[] UNIQUE багануудын нэрс
     */
    private function getUniqueColumns(string $tableName): array
    {
        if ($this->getDriverName() === Constants::DRIVER_PGSQL) {
            $stmt = $this->prepare("
                SELECT a.attname
                FROM pg_index i
                JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
                WHERE i.indrelid = :tbl::regclass
                  AND i.indisunique = true
                  AND i.indisprimary = false
            ");
            $stmt->bindValue(':tbl', $tableName);
        } else {
            $stmt = $this->prepare("
                SELECT COLUMN_NAME
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :tbl
                  AND NON_UNIQUE = 0
                  AND COLUMN_NAME != 'id'
            ");
            $stmt->bindValue(':tbl', $tableName);
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Primary хүснэгтэд insert хийх. Эхлээд анхны ID-аар, амжилтгүй бол auto.
     *
     * @return int Шинэ бичлэгийн ID (анхных эсвэл auto)
     */
    private function insertPrimary(string $tableName, array $row, int $originalId): int
    {
        // 1. Анхны ID-аар оролдох
        try {
            $row['id'] = $originalId;
            $this->execInsert($tableName, $row);
            return $originalId;
        } catch (\PDOException $e) {
            // 23000 = integrity constraint violation (PRIMARY KEY эсвэл UNIQUE)
            // UNIQUE-ийг pre-flight-аар үнэлсэн тул энд зөвхөн ID conflict тохиолдоно
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }

        // 2. ID-г хасч auto-increment-аар оролдох
        unset($row['id']);
        $this->execInsert($tableName, $row);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * LocalizedModel-ийн _content хүснэгтэд олон хэлний бичлэг нэмэх.
     *
     * @param string $primaryTable Primary хүснэгтийн нэр (e.g. "news")
     * @param array $localized ['mn' => [...], 'en' => [...]]
     * @param int $parentId Primary record-н ID
     */
    private function insertLocalizedContent(string $primaryTable, array $localized, int $parentId): void
    {
        $contentTable = $primaryTable . Constants::CONTENT_TABLE_SUFFIX;
        if (!$this->hasTable($contentTable)) {
            return; // Энгийн Model - content хүснэгт байхгүй
        }
        foreach ($localized as $code => $fields) {
            // Content row-н id, parent_id, code-ийг overwrite (анхны утгаас үл хамаарч)
            unset($fields['id']);
            $fields['parent_id'] = $parentId;
            $fields['code'] = $code;
            $this->execInsert($contentTable, $fields);
        }
    }

    /**
     * Generic INSERT helper. PDO ERRMODE_EXCEPTION-д execute() өөрөө throw хийнэ.
     * Тиймээс SQLSTATE (23000 - integrity constraint violation) PDOException-аар
     * caller руу шууд гарна.
     */
    private function execInsert(string $tableName, array $row): void
    {
        if (empty($row)) {
            throw new \Exception("Empty row for INSERT into $tableName", 500);
        }
        $columns = \implode(', ', \array_keys($row));
        $placeholders = \implode(', ', \array_map(fn($k) => ":$k", \array_keys($row)));
        $stmt = $this->prepare("INSERT INTO $tableName ($columns) VALUES ($placeholders)");
        foreach ($row as $col => $val) {
            $stmt->bindValue(":$col", $val);
        }
        $stmt->execute();
    }

    /**
     * Бүх trash бичлэгийг цэвэрлэх (empty trash).
     */
    public function empty()
    {
        try {
            if (!$this->isUser('system_coder')) {
                throw new \Exception('No permission!', 401);
            }

            $model = new TrashModel($this->pdo);
            $table = $model->getName();
            $count = $model->countRows();

            if ($count === 0) {
                throw new \Exception('Trash is already empty', 400);
            }

            $this->exec("DELETE FROM $table");

            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => "$count бичлэгийг бүрэн устгалаа"
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            $context = ['action' => 'trash-empty'];
            if (isset($err) && $err instanceof \Throwable) {
                $this->log('trash', LogLevel::ERROR, 'Trash цэвэрлэх явцад алдаа', $context + ['error' => $err->getMessage()]);
            } else {
                $this->log('trash', LogLevel::CRITICAL, "Trash-ын $count бичлэгийг бүрэн цэвэрлэлээ", $context);
            }
        }
    }
}
