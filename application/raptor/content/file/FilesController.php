<?php

namespace Raptor\Content;

use Twig\TwigFilter;
use Psr\Log\LogLevel;

/**
 * Class FilesController
 *
 * Raptor Framework-ийн Content module-ийн файлын удирдлагын
 * үндсэн Controller. Файл upload хийх, жагсаалт харах, мэдээлэл
 * засварлах, идэвхгүй болгох, modal сонголт харах зэрэг бүх
 * үйлдлийг нэг дороос гүйцэтгэнэ.
 *
 * Ашигласан:
 *  - FileController -> файлын үндсэн upload/move логик
 *  - DashboardTrait -> dashboard-ийн template rendering
 *
 * Онцлог:
 *  - Бүх файлуудыг `{table}_files` нэртэй динамик хүснэгтэд хадгална
 *  - PostgreSQL/MySQL/SQLite ажиллана
 *  - JSON response + Dashboard HTML response хосолсон
 *  - Access control (permission) бүрэн тусгагдсан
 *  - log() -> үйлдэл бүрийг лог файл руу бичдэг
 *
 * @package Raptor\Content
 */
class FilesController extends FileController
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Файлын модулийн Dashboard эхлэл хуудас.
     *
     * - Файлын бүх хүснэгтүүдийг илрүүлнэ
     * - Тухайн хүснэгт доторх нийт файлын тоо, хэмжээ зэргийг тооцоолно
     * - Хүснэгтийн нэрийг автоматаар сонгоно
     * - Хэрэв `index-{table}.html` template байвал тэрийг хэрэглэнэ
     *
     * Permission: system_content_index
     *
     * @return void
     */
    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        // PostgreSQL, MySQL, эсвэл SQLite мөр илрүүлэх
        if ($this->getDriverName() == 'pgsql') {
            $query =
                'SELECT tablename FROM pg_catalog.pg_tables ' .
                "WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema' AND tablename like '%_files'";
        } elseif ($this->getDriverName() == 'sqlite') {
            // SQLite хувилбар
            $query = "SELECT name as tablename FROM sqlite_master WHERE type='table' AND name LIKE '%_files'";
        } else {
            $query = 'SHOW TABLES LIKE ' . $this->quote('%_files');
        }
        $tblNames = $this->query($query)->fetchAll();
        $tables = [];
        $total = ['tables' => 0, 'rows' => 0, 'sizes' => 0];

        // Хүснэгт бүрийн файлын тоо, хэмжээ авах
        foreach ($tblNames as $result) {
            $table = \substr(\current($result), 0, -(\strlen('_files')));
            $rows = $this->query("SELECT COUNT(*) as count FROM {$table}_files WHERE is_active=1")->fetchAll();
            $sizes = $this->query("SELECT SUM(size) as size FROM {$table}_files WHERE is_active=1")->fetchAll();
            $count = $rows[0]['count'];
            $size  = $sizes[0]['size'];

            ++$total['tables'];
            $total['rows']  += $count;
            $total['sizes'] += $size;

            $tables[$table] = [
                'count' => $count,
                'bytes' => (int) $size,
                'size'  => $this->formatSizeUnits($size)
            ];
        }

        // "files" нэртэй үндсэн хүснэгт байгаагүй бол автоматаар нэмнэ
        if (empty($tables['files'])) {
            $tables = ['files' => ['count' => 0, 'size' => 0]] + $tables;
        }

        // Query параметрт table өгсөн эсэх
        if (isset($this->getQueryParams()['table'])) {
            $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $this->getQueryParams()['table']);
        } elseif (!empty($tables)) {
            $keys = \array_keys($tables);
            $table = \reset($keys);
        } else {
            $this->dashboardProhibited('No file tables found!', 404)->render();
            return;
        }
        
        $total['total_bytes'] = (int) $total['sizes'];
        $total['sizes'] = $this->formatSizeUnits($total['sizes']);

        // Тухайн хүснэгтэнд зориулсан тусгай template мөрдөх
        $template = \file_exists(__DIR__ . "/index-$table.html")
            ? __DIR__ . "/index-$table.html" : __DIR__ . '/index.html';

        // Dashboard HTML render
        $dashboard = $this->twigDashboard($template, [
            'total'         => $total,
            'table'         => $table,
            'tables'        => $tables,
            'max_file_size' => $this->getMaximumFileUploadSize()
        ]);
        $dashboard->set('title', $this->text('files'));
        $dashboard->render();

        // Лог бичих
        $this->log(
            $table,
            LogLevel::NOTICE,
            '[{table}] файлын жагсаалтыг үзэж байна',
            [
                'action' => 'files-index',
                'tables' => $tables,
                'total'  => $total,
                'table'  => $table
            ]
        );
    }

    /**
     * Файлын жагсаалтыг JSON хэлбэрээр буцаана.
     *
     * - Хүснэгт үнэхээр байгаа эсэхийг шалгана
     * - is_active=1 мөрүүдийг буцаана
     *
     * Permission: system_content_index
     *
     * @param string $table Файлын модульд хамаарах үндсэн хүснэгтийн нэр
     * @return void
     */
    public function list(string $table)
    {
        try {
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            // Хүснэгт байгаа эсэх баталгаажуулалт
            if ($this->getDriverName() == 'pgsql') {
                $query =
                    'SELECT tablename FROM pg_catalog.pg_tables ' .
                    "WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema' AND tablename like '{$table}_files'";
            } elseif ($this->getDriverName() == 'sqlite') {
                // SQLite хувилбар
                $query = "SELECT name as tablename FROM sqlite_master WHERE type='table' AND name LIKE '{$table}_files'";
            } else {
                $query = 'SHOW TABLES LIKE ' . $this->quote("{$table}_files");
            }
            $exists = $this->query($query)->fetchAll();
            if (empty($exists)) {
                $files = [];
            } else {
                // Хүснэгт байгаа тул доторх file мөр бичлэгүүдийг авна
                $select_files =
                    'SELECT id, record_id, file, path, size, type, mime_content_type, keyword, description, created_at ' .
                    "FROM {$table}_files WHERE is_active=1";
                $files = $this->query($select_files)->fetchAll();
            }
            $this->respondJSON(['status' => 'success', 'list' => $files]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        }
    }
    
    /**
     * Файл upload хийх.
     *
     * - Заасан folder руу файлыг байршуулна
     * - optimize=1 бол зургийн файлыг optimize хийнэ
     * - moveUploaded-тэй адил бүтэцтэй утга буцаана (path, file, size, type, mime_content_type)
     *
     * @return void
     */
    public function upload()
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception('Unauthorized', 401);
            }

            $payload = $this->getParsedBody();
            $folder = '/' . \trim(\preg_replace('/[^a-zA-Z0-9_\/-]/', '', $payload['folder'] ?? 'files'), '/');
            $this->setFolder($folder);
            $this->allowCommonTypes();
            $optimize = ($payload['optimize'] ?? 0) == 1;
            $uploaded = $this->moveUploaded('file', $optimize);
            if (!$uploaded) {
                throw new \InvalidArgumentException('Upload failed', 400);
            }
            $this->respondJSON($uploaded);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'error' => [
                    'code'    => $err->getCode(),
                    'message' => $err->getMessage()
                ]
            ], $err->getCode() ?: 500);
        } finally {
            $context = ['action' => 'files-upload', 'folder' => $folder ?? ''];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Файл байршуулах үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::INFO;
                $message = '<a target="__blank" href="{path}">{path}</a> файлыг байршууллаа';
                $context += $uploaded;
            }
            $this->log('files', $level, $message, $context);
        }
    }
    
    /**
     * Файл upload хийх болон `{table}_files` хүснэгтэд бүртгэх.
     *
     * Энэ функц нь контент модуль бүхэлдээ файлуудыг динамик байдлаар
     * олон төрлийн хүснэгттэй холбох боломжийг олгодог.
     *
     * -----------------------------------------
     * Үндсэн ажиллагаа:
     * -----------------------------------------
     *  1) Файлыг upload хийж сервер дээр хадгална
     *     - moveUploaded($input) -> upload хийгээд file/path/size/type... мэдээлэл үүсгэнэ
     *
     *  2) Хадгалах фолдерыг автоматаар тохируулна
     *       /{table}/{record_id}/{uploaded_file}
     *     Жишээ:
     *       /pages/10/header.jpg        -> pages хүснэгтийн 10-р бичлэгийн файл
     *       /files/brandbook.pdf        -> content-той холбогдоогүй ерөнхий файл
     *
     *  3) FilesModel ашиглан `{table}_files` хүснэгтэд DB-р бүртгэнэ
     *     Мөр бүр дараах бүтэцтэй:
     *       id, record_id, file, path, size, type, mime_content_type,
     *       category, keyword, description, created_by, created_at ...
     *
     *  4) Амжилттай бол insert хийгдсэн мөрийн бүх мэдээллийг JSON-оор буцаана
     *
     * -----------------------------------------
     * `$id` параметрын утга - record_id талбарын жинхэнэ утга
     * -----------------------------------------
     * `$id` нь файлыг аль контент мөртэй холбож байгааг заана.
     *
     * Жишээ 1:
     *   pages хүснэгтэд "About Us" нэртэй бичлэг байлаа гэж бодъё
     *
     *   pages:
     *     id = 10   -> "About Us" page
     *
     *   Энэ page дээр 3 файл upload хийвэл:
     *      pages_files хүснэгтэд:
     *        - record_id = 10
     *        - 3 өөр мөр нэмэгдэнэ
     *
     *   Үүний ачаар тухайн page-ийн бүх хавсаргасан файлуудыг
     *   дараах байдлаар олж болно:
     *      SELECT * FROM pages_files WHERE record_id = 10 AND is_active=1;
     *
     * Жишээ 2:
     *   `$record_id = 0` бол файл ямар ч контент мөртэй холбогдохгүй.
     *   Энэ нь "ерөнхий upload", эсвэл түр хадгалах файл гэсэн утгатай.
     *
     * -----------------------------------------
     * Файлын бүтэц динамик байдаг - хүснэгт бүрийн өөрийн files table
     * -----------------------------------------
     *   pages      -> pages_files
     *   news       -> news_files
     *   products   -> products_files
     *   files      -> files_files (ерөнхий файл)
     *
     * Энэ архитектур нь нэг content дээр олон файл хавсаргах боломжийг
     * бүрэн тайван шийддэг.
     *
     * -----------------------------------------
     * Permission
     * -----------------------------------------
     *  Хэрэглэгч заавал **authentication** хийгдсэн байх ёстой.
     *
     * @param string $table
     *     files бүртгэх үндсэн хүснэгтийн нэр.
     *     Жишээ: 'files', 'pages', 'news', 'products'
     *
     * @param int $record_id
     *     Хамаарах контент бичлэгийн ID дугаар.
     *     - 0 -> ерөнхий файл, контент мөртэй холбогдохгүй
     *     - >0 -> тухайн content-ийн attachments (record_id)
     *
     * @return void
     */
    public function post(string $table, int $record_id = 0)
    {
        try {
            // Хэрэглэгч нэвтэрсэн байх ёстой
            if (!$this->isUserAuthorized()) {
                throw new \Exception('Unauthorized', 401);
            }
            
            // Файл хадгалах фолдерийг тохируулах
            $folder = "/$table" . ($record_id == 0 ? '' : "/$record_id");
            $this->setFolder($folder);
            $this->allowCommonTypes();

            // Upload -> Move (optimize=1 бол зургийг автоматаар optimize хийнэ)
            $body = $this->getParsedBody();
            $optimize = ($body['optimize'] ?? '0') === '1';
            $uploaded = $this->moveUploaded('file', $optimize);
            if (!$uploaded) {
                throw new \InvalidArgumentException(__CLASS__ . ': Invalid upload!', 400);
            }

            if ($record_id > 0) {
                // Холбох content record id дугаар
                $uploaded['record_id'] = $record_id;
            }

            // Files бичлэг бүртгэл
            $model = new FilesModel($this->pdo);
            $model->setTable($table);
            $record = $model->insert($uploaded + ['created_by' => $this->getUserId()]);
            if (!isset($record['id'])) {
                throw new \Exception($this->text('record-insert-error'));
            }
            $this->respondJSON($record);
        } catch (\Throwable $err) {
            $error = [
                'error' => [
                    'code'    => $err->getCode(),
                    'message' => $err->getMessage()
                ]
            ];
            $this->respondJSON($error, $err->getCode());

            // Files (DB) бичлэг амжилтгүй тул upload файл байвал устгах хэрэгтэй
            if (!empty($uploaded['file'])) {
                \unlink($uploaded['file']);
            }
        } finally {
            // Лог бичих
            $context = ['action' => 'files-post', 'table' => $table];
            if (isset($record['id'])) {
                $context += $record;
                $level = LogLevel::INFO;
                $message = '<a target="__blank" href="{path}">{path}</a> файлыг ';
                $message .= empty($record['record_id'])
                    ? 'байршууллаа'
                    : 'байршуулан {record_id}-р бичлэгт зориулж холболоо';
            } else {
                $context += $error;
                $level = LogLevel::ERROR;
                $message = 'Файл байршуулах үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            }
            $this->log($table, $level, $message, $context);
        }
    }
    
    /**
     * Файл сонгоход зориулсан Modal HTML харуулна.
     *
     * - id дугаараар мөрийн мэдээлэл авна
     * - Modal template-ийг динамикаар ачаална (`{name}-modal.html`)
     * - basename filter нэмнэ
     *
     * @param string $table
     * @return void
     */
    public function modal(string $table)
    {
        try {
            // Хэрэглэгч нэвтэрсэн байх ёстой
            if (!$this->isUserAuthorized()) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $queryParams = $this->getQueryParams();
            $id = $queryParams['id'] ?? null;
            if (!isset($id) || !\is_numeric($id)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }

            // Record шалгах
            $model = new FilesModel($this->pdo);
            $model->setTable($table);
            $record = $model->getRowWhere([
                'id'        => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            // Host бүрдүүлэх (absolute url)
            $uri = $this->getRequest()->getUri();
            $scheme = $uri->getScheme();
            $authority = $uri->getAuthority();
            $host = '';
            if ($scheme != '') {
                $host .= "$scheme:";
            }
            if ($authority != '') {
                $host .= "//$authority";
            }

            $modal = \preg_replace('/[^A-Za-z0-9_-]/', '', $queryParams['modal'] ?? 'null');
            $template = $this->twigTemplate(
                __DIR__ . "/$modal-modal.html",
                ['table' => $table, 'record' => $record, 'host' => $host]
            );
            // basename filter (rawurldecode хийж уншигдахуйц нэр харуулна)
            $template->addFilter(new TwigFilter('basename', fn(string $path): string => \rawurldecode(\basename($path))));
            $template->render();
        } catch (\Throwable $err) {
            $this->headerResponseCode($err->getCode());

            // Алдааны модал
            echo '<div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-body">
                            <div class="alert alert-danger shadow-sm fade mt-3 show" role="alert">
                                <i class="bi bi-shield-fill-exclamation" style="margin-right:.3rem"></i>'
                            . $err->getMessage() .
                            '</div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary shadow-sm" data-bs-dismiss="modal" type="button">'
                            . $this->text('close') .
                            '</button>
                        </div>
                    </div>
                </div>';
        }
    }

    /**
     * Файлын мэдээллийг засварлах.
     *
     * - form submit -> parsed body -> бүх `file_` prefix-ийг цэвэрлэнэ
     * - updateById() ашиглан мөрийг шинэчилнэ
     * - JSON success response буцаана
     *
     * Permission: system_content_update
     *
     * @param string $table Хүснэгтийн нэр
     * @param int    $id    Файлын бичлэгийн id дугаар
     *
     * @return void
     */
    public function update(string $table, int $id)
    {
        try {
            // Нэвтэрсэн хэрэглэгч content засах эрхтэй байх ёстой
            if (!$this->isUserCan('system_content_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $parsedBody = $this->getParsedBody();
            if (empty($parsedBody)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }

            // Payload боловсруулах (file_ -> арилга)
            $payload = [];
            foreach ($parsedBody as $k => $v) {
                if (\str_starts_with($k, 'file_')) {
                    $k = \substr($k, 5);
                }
                $payload[$k] = $v;
            }

            $model = new FilesModel($this->pdo);
            $model->setTable($table);

            // Одоогийн record-ийг татаж байна, өөрчлөлт байгаа эсэхийг шалгахын тулд
            $record = $model->getRowWhere(['id' => $id]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            // Өөрчлөгдсөн талбаруудыг тодорхойлох
            $updates = [];
            foreach ($payload as $field => $value) {
                if (\array_key_exists($field, $record)
                    && $record[$field] != $value) {
                    $updates[] = $field;
                }
            }
            if (empty($updates)) {
                // Өөрчлөгдсөн талбарууд байхгүй үед зогсооно
                throw new \InvalidArgumentException('No update!');
            }

            // Update metadata
            $payload['updated_by'] = $this->getUserId();
            // Update row with payload
            $updated = $model->updateById($id, $payload);
            if (empty($updated)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $this->respondJSON([
                'type'    => 'primary',
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-update-success'),
                'record'  => $updated
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        } finally {
            // Лог бичих
            if (empty($updated)) {
                $level = LogLevel::ERROR;
                $message = '{id} дугаартай файлын бичлэгийг засах үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context = ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::INFO;
                $message = '{id} дугаартай [{path}] файлын бичлэгийг амжилттай засварлалаа';
                if (!empty($updated['record_id'])) {
                    $message = "{record_id}-р бичлэгт зориулсан $message";
                }
                $context = $updated;
            }
            $this->log($table, $level, $message, ['action' => 'files-update', 'id' => $id] + $context);
        }
    }

    /**
     * Файлын бичлэгийг идэвхгүй болгоно (SOFT DELETE).
     *
     * Бодит файл устахгүй.
     *
     * Үйл явц:
     *  - id шалгана
     *  - files_model -> deactivateById()
     *  - JSON success response
     *  - Лог бичнэ
     *
     * Permission: system_content_delete
     *
     * @param string $table Файл хадгалдаг хүснэгт
     * @return void
     */
    public function deactivate(string $table)
    {
        try {
            // Нэвтэрсэн хэрэглэгч content устгах эрхтэй байх ёстой
            if (!$this->isUserCan('system_content_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }

            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);

            $model = new FilesModel($this->pdo);
            $model->setTable($table);
            $record = $model->getRowWhere([
                'id'        => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            $deactivated = $model->deactivateById($id, [
                'updated_by' => $this->getUserId(),
                'updated_at' => \date('Y-m-d H:i:s')
            ]);
            if (!$deactivated) {
                throw new \Exception($this->text('no-record-selected'));
            }

            // Амжилттай хариу
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            // Лог бичих
            if ($deactivated ?? false) {
                $level = LogLevel::ALERT;
                $message = '{id} дугаартай [{path}] файлын бичлэгийг идэвхгүй болголоо. Бодит файл [{file}] устаагүй болно.';
                if (!empty($record['record_id'])) {
                    $message = "{record_id}-р бичлэгт зориулсан $message";
                }
                $context = $record;
            } else {
                $level = LogLevel::ERROR;
                $message = 'Файлын бичлэгийг идэвхгүй болгох үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context = [
                    'error' => [
                        'code'    => $err->getCode(),
                        'message' => $err->getMessage()
                    ]
                ];
            }
            $this->log($table, $level, $message, ['action' => 'files-deactivate'] + $context);
        }
    }
}
