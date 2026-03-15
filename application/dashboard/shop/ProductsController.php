<?php

namespace Dashboard\Shop;

use Psr\Log\LogLevel;

use Raptor\Content\FileController;
use Raptor\Content\FilesModel;

/**
 * Class ProductsController
 * ---------------------------------------------------------------
 * Бүтээгдэхүүн (Products) контент удирдах controller.
 *
 * Энэ controller нь:
 *   - Бүтээгдэхүүний жагсаалт харуулах (index, list)
 *   - Шинэ бүтээгдэхүүн үүсгэх (insert)
 *   - Бүтээгдэхүүн шинэчлэх (update)
 *   - Бүтээгдэхүүн унших (read)
 *   - Бүтээгдэхүүний дэлгэрэнгүй харуулах (view)
 *   - Бүтээгдэхүүнийг идэвхгүй болгох (deactivate)
 *   - Жишиг дата цэвэрлэх (reset)
 *   зэрэг үйлдлүүдийг гүйцэтгэнэ.
 *
 * @package Dashboard\Shop
 */
class ProductsController extends FileController
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Бүтээгдэхүүний жагсаалтын dashboard хуудсыг харуулах.
     *
     * Хэл, төрөл, ангилал, статус зэрэг шүүлтийн сонголтуудыг бэлтгэнэ.
     *
     * Permission: system_product_index
     *
     * @return void
     */
    public function index()
    {
        if (!$this->isUserCan('system_product_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        $filters = [];
        $table = (new ProductsModel($this->pdo))->getName();
        $codes_result = $this->query(
            "SELECT DISTINCT code FROM $table WHERE is_active=1"
        )->fetchAll();
        $languages = $this->getLanguages();
        $filters['code']['title'] = $this->text('language');
        foreach ($codes_result as $row) {
            $filters['code']['values'][$row['code']] = "{$languages[$row['code']]['title']} [{$row['code']}]";
        }
        $types_result = $this->query(
            "SELECT DISTINCT type FROM $table WHERE is_active=1"
         )->fetchAll();
        $filters['type']['title'] = $this->text('type');
        foreach ($types_result as $row) {
            $filters['type']['values'][$row['type']] = $row['type'];
        }
        $categories_result = $this->query(
            "SELECT DISTINCT category FROM $table WHERE is_active=1"
        )->fetchAll();
        $filters['category']['title'] = $this->text('category');
        foreach ($categories_result as $row) {
            $filters['category']['values'][$row['category']] = $row['category'];
        }
        $filters += [
            'published' => [
                'title' => $this->text('status'),
                'values' => [
                    0 => 'unpublished',
                    1 => 'published'
                ]
            ]
        ];
        $dashboard = $this->twigDashboard(__DIR__ . '/products-index.html', ['filters' => $filters]);
        $dashboard->set('title', $this->text('products'));
        $dashboard->render();

        $this->log('product', LogLevel::NOTICE, 'Бүтээгдэхүүний жагсаалтыг үзэж байна', ['action' => 'index']);
    }

    /**
     * Бүтээгдэхүүний жагсаалтыг JSON хэлбэрээр буцаах.
     *
     * Query parameter-уудаас шүүлтийн нөхцөлүүдийг авч,
     * жагсаалтыг бүртгэлийн огноогоор буурахаар эрэмбэлнэ.
     *
     * Permission: system_product_index
     *
     * @return void JSON response буцаана
     */
    public function list()
    {
        try {
            if (!$this->isUserCan('system_product_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $params = $this->getQueryParams();
            if (!isset($params['is_active'])) {
                $params['is_active'] = 1;
            }
            $conditions = [];
            $allowed = ['code', 'type', 'category', 'published', 'is_active'];
            foreach (\array_keys($params) as $name) {
                if (\in_array($name, $allowed)) {
                    $conditions[] = "$name=:$name";
                } else {
                    unset($params[$name]);
                }
            }
            $where = \implode(' AND ', $conditions);
            $table = (new ProductsModel($this->pdo))->getName();
            $select_pages =
                'SELECT id, photo, title, slug, code, type, category, price, published, published_at, date(created_at) as created_date ' .
                "FROM $table WHERE $where ORDER BY created_at desc";
            $products_stmt = $this->prepare($select_pages);
            foreach ($params as $name => $value) {
                $products_stmt->bindValue(":$name", $value);
            }
            $products = $products_stmt->execute() ? $products_stmt->fetchAll() : [];
            $files_counts = $this->getFilesCounts($table);
            $sampleCheck = $this->query(
                "SELECT COUNT(*) as total, " .
                "SUM(CASE WHEN created_by IS NULL AND created_at = published_at AND category='_raptor_sample_' THEN 1 ELSE 0 END) as sample " .
                "FROM $table WHERE is_active=1"
            )->fetch();
            $isSample = (int)$sampleCheck['sample'] > 0;

            $this->respondJSON([
                'status' => 'success',
                'list' => $products,
                'files_counts' => $files_counts,
                'is_sample' => $isSample
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        }
    }

    /**
     * Шинэ бүтээгдэхүүн үүсгэх.
     *
     * GET: Форм хуудсыг харуулна
     * POST: Шинэ бүтээгдэхүүнийг үүсгэнэ
     *
     * Permission: system_product_insert, system_product_publish
     *
     * @return void
     */
    public function insert()
    {
        try {
            if (!$this->isUserCan('system_product_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $model = new ProductsModel($this->pdo);
            $table = $model->getName();
            if ($this->getRequest()->getMethod() == 'POST') {
                $parsedBody = $this->getParsedBody();
                if (empty($parsedBody['title'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }

                $isPublished = ($parsedBody['published'] ?? 0) == 1;
                $needsPublishPermission =
                    $isPublished ||
                    ($parsedBody['is_featured'] ?? 0) == 1 ||
                    ($parsedBody['comment'] ?? 0) == 1;
                if ($needsPublishPermission
                    && !$this->isUserCan('system_product_publish')
                ) {
                    throw new \Exception($this->text('system-no-permission'), 401);
                }

                if ($isPublished) {
                    $parsedBody['published_at'] = \date('Y-m-d H:i:s');
                    $parsedBody['published_by'] = $this->getUserId();
                }

                $files = \json_decode($parsedBody['files'] ?? '{}', true) ?: [];
                unset($parsedBody['files']);

                $payload = $this->sanitizePayload($model, $parsedBody);
                $record = $model->insert(
                    $payload + ['created_by' => $this->getUserId()]
                );
                if (!isset($record['id'])) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $id = $record['id'];

                $fileChanges = $this->processFiles($record, $files, true);

                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);

                $action = !empty($payload['published']) ? 'publish' : 'insert';
                $adminName = \trim(($this->getUser()->profile['first_name'] ?? '') . ' ' . ($this->getUser()->profile['last_name'] ?? ''));
                $this->getService('discord')?->contentAction('product', $action, $payload['title'] ?? '', $id, $adminName);
            } else {
                $dashboard = $this->twigDashboard(
                    __DIR__ . '/products-insert.html',
                    [
                        'table' => $table,
                        'max_file_size' => $this->getMaximumFileUploadSize()
                    ]
                );
                $dashboard->set('title', $this->text('add-record') . ' | Products');
                $dashboard->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = ['action' => 'create'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Бүтээгдэхүүн үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'POST') {
                $level = LogLevel::INFO;
                $message = '{record.id} дугаартай [{record.title}] бүтээгдэхүүнийг амжилттай үүсгэлээ';
                $context += ['record_id' => $id, 'record' => $record];
                $context['file_changes'] = !empty($fileChanges) ? $fileChanges : 'files not changed';
            } else {
                $level = LogLevel::NOTICE;
                $message = 'Бүтээгдэхүүн үүсгэх үйлдлийг эхлүүллээ';
            }
            $this->log('product', $level, $message, $context);
        }
    }

    /**
     * Бүтээгдэхүүний бичлэгийг шинэчлэх.
     *
     * GET: Шинэчлэх форм хуудсыг харуулна
     * PUT: Бүтээгдэхүүнийг шинэчлэнэ
     *
     * Permission: system_product_update, system_product_publish
     * Эсвэл: өөрийн үүсгэсэн, нийтлэгдээгүй бичлэгийг засах боломжтой
     *
     * @param int $id Шинэчлэх бүтээгдэхүүний ID
     * @return void
     */
    public function update(int $id)
    {
        try {
            $model = new ProductsModel($this->pdo);
            $table = $model->getName();
            $filesModel = new FilesModel($this->pdo);
            $filesModel->setTable($table);
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            if (!$this->isUserCan('system_product_update')) {
                if ((int)$record['created_by'] !== $this->getUserId()
                    || (int)$record['published'] !== 0
                ) {
                    throw new \Exception($this->text('system-no-permission'), 401);
                }
            } elseif ($record['published'] == 1
                && !$this->isUserCan('system_product_publish')
            ) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            if ($this->getRequest()->getMethod() == 'PUT') {
                $parsedBody = $this->getParsedBody();
                if (empty($parsedBody['title'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }

                $isPublished = ($parsedBody['published'] ?? 0) == 1;
                $needsPublishPermission =
                    $isPublished ||
                    ($parsedBody['is_featured'] ?? 0) == 1 ||
                    ($parsedBody['comment'] ?? 0) == 1;
                if ($needsPublishPermission
                    && !$this->isUserCan('system_product_publish')
                ) {
                    throw new \Exception($this->text('system-no-permission'), 401);
                }

                if ($isPublished && $record['published'] != 1) {
                    $parsedBody['published_at'] = \date('Y-m-d H:i:s');
                    $parsedBody['published_by'] = $this->getUserId();
                }

                $files = \json_decode($parsedBody['files'] ?? '{}', true) ?: [];
                unset($parsedBody['files']);
                if (isset($parsedBody['id'])) {
                    unset($parsedBody['id']);
                }

                $payload = $this->sanitizePayload($model, $parsedBody);
                
                $fileChanges = $this->processFiles($record, $files);
                
                $updates = [];
                foreach ($payload as $field => $value) {
                    if (($record[$field] ?? null) != $value) {
                        $updates[] = $field;
                    }
                }
                if (!empty($fileChanges)) {
                    $updates[] = 'files';
                }
                if (empty($updates)) {
                    throw new \InvalidArgumentException('No update!');
                }

                $desc = \trim($payload['description'] ?? '');
                if ($desc === '' && !empty($payload['content'])) {
                    $payload['description'] = $model->getExcerpt($payload['content']);
                } else {
                    $payload['description'] = $desc;
                }

                $payload['updated_at'] = \date('Y-m-d H:i:s');
                $payload['updated_by'] = $this->getUserId();
                $updated = $model->updateById($id, $payload);
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }

                $this->respondJSON([
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success')
                ]);

                $wasPublished = !empty($record['published']);
                $nowPublished = !empty($payload['published']);
                $action = (!$wasPublished && $nowPublished) ? 'publish' : 'update';
                $adminName = \trim(($this->getUser()->profile['first_name'] ?? '') . ' ' . ($this->getUser()->profile['last_name'] ?? ''));
                $this->getService('discord')?->contentAction('product', $action, $payload['title'] ?? $record['title'] ?? '', $id, $adminName);
            } else {
                $files = $filesModel->getRows(['WHERE' => "record_id=$id AND is_active=1"]);
                $dashboard = $this->twigDashboard(
                    __DIR__ . '/products-update.html',
                    [
                        'table' => $table,
                        'record' => $record,
                        'files' => $files,
                        'max_file_size' => $this->getMaximumFileUploadSize()
                    ]
                );
                $dashboard->set('title', $this->text('edit-record') . ' | Products');
                $dashboard->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'PUT') {
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            $context = ['action' => 'update', 'record_id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай бүтээгдэхүүнийг шинэчлэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'PUT') {
                $level = LogLevel::INFO;
                $message = '{record.id} дугаартай [{record.title}] бүтээгдэхүүнийг амжилттай шинэчлэлээ';
                $context += ['updates' => $updates, 'record' => $updated];
                $context['file_changes'] = !empty($fileChanges) ? $fileChanges : 'files not changed';
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.id} дугаартай [{record.title}] бүтээгдэхүүнийг шинэчлэхээр нээж байна';
                $context += ['record' => $record, 'files' => $files];
            }
            $this->log('product', $level, $message, $context);
        }
    }

    /**
     * Бүтээгдэхүүний дэлгэрэнгүй мэдээллийг dashboard-д харуулах.
     *
     * Permission: system_product_index
     * Нийтлэгдсэн бичлэгийг нэвтэрсэн бүх admin харах боломжтой
     *
     * @param int $id Үзэх бүтээгдэхүүний ID
     * @return void
     */
    public function view(int $id)
    {
        try {
            $model = new ProductsModel($this->pdo);
            $table = $model->getName();
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            if (!$this->isUserCan('system_product_index')
                && (int)$record['published'] !== 1
            ) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $filesModel = new FilesModel($this->pdo);
            $filesModel->setTable($table);
            $files = $filesModel->getRows(['WHERE' => "record_id=$id AND is_active=1"]);
            $dashboard = $this->twigDashboard(
                __DIR__ . '/products-view.html',
                ['table' => $table, 'record' => $record, 'files' => $files]
            );
            $dashboard->set('title', $this->text('view-record') . ' | Products');
            $dashboard->render();
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'view', 'record_id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай бүтээгдэхүүнийг нээх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.id} дугаартай [{record.title}] бүтээгдэхүүнийг үзэж байна';
                $context += ['record' => $record, 'files' => $files];
            }
            $this->log('product', $level, $message, $context);
        }
    }

    /**
     * Файлуудыг нэгдсэн аргаар боловсруулах.
     *
     * Frontend-ээс ирсэн files JSON-г parse хийж header image,
     * content media, attachment файлуудыг хадгалах/устгах.
     *
     * @param array $record Бичлэг
     * @param array $files Frontend-ээс ирсэн файлуудын мэдээлэл
     * @param bool $fromTemp Insert үйлдэл эсэх (temp folder-оос зөөх)
     * @return array Файлуудын өөрчлөлтүүдийн жагсаалт
     */
    private function processFiles(array $record, array $files, bool $fromTemp = false): array
    {
        $changes = [];
        $userId = $this->getUserId();

        $model = new ProductsModel($this->pdo);
        $table = $model->getName();

        $filesModel = new FilesModel($this->pdo);
        $filesModel->setTable($table);

        if ($fromTemp) {
            $this->setFolder("/$table/temp/$userId");
            $tempPath = $this->public_path;
            $tempFolder = $this->local_folder;
        }

        $this->setFolder("/$table/{$record['id']}");
        $recordPath = $this->public_path;
        $recordFolder = $this->local_folder;

        if (($files['headerImageRemoved'] ?? false) && !empty($record['photo'])) {
            $photoFilename = \basename(\rawurldecode($record['photo']));
            $this->unlinkByName($photoFilename);
            $record = $model->updateById($record['id'], ['photo' => '']);
            $changes[] = "header image deleted: $photoFilename";
        }

        if (!empty($files['headerImage'])) {
            $headerData = $files['headerImage'];
            $filename = \basename($headerData['file']);

            if (!empty($record['photo'])) {
                $photoFilename = \basename(\rawurldecode($record['photo']));
                $this->unlinkByName($photoFilename);
            }

            if ($fromTemp) {
                $tempFile = "$tempFolder/$filename";
                if (\is_file($tempFile)) {
                    if (!\is_dir($recordFolder)) {
                        \mkdir($recordFolder, 0755, true);
                    }
                    \rename($tempFile, "$recordFolder/$filename");
                    $headerData['path'] = "$recordPath/" . \rawurlencode($filename);
                }
            }

            $model->updateById($record['id'], ['photo' => $headerData['path']]);
            $changes[] = "header image updated: $filename";
        }

        foreach ($files['contentMedia'] ?? [] as $media) {
            if ($fromTemp) {
                $filename = \basename($media['file']);
                $tempFile = "$tempFolder/$filename";
                if (\is_file($tempFile)) {
                    if (!\is_dir($recordFolder)) {
                        \mkdir($recordFolder, 0755, true);
                    }
                    \rename($tempFile, "$recordFolder/$filename");
                    $changes[] = "content media moved: $filename";
                }
            }
        }

        if ($fromTemp) {
            $html = $record['content'] ?? '';
            if (\strpos($html, $tempPath) !== false) {
                $html = \str_replace($tempPath, $recordPath, $html);
                $model->updateById($record['id'], ['content' => $html]);
            }
        }

        foreach ($files['attachments']['new'] ?? [] as $att) {
            $filename = \basename($att['file']);

            if ($fromTemp) {
                $tempFile = "$tempFolder/$filename";
                if (\is_file($tempFile)) {
                    if (!\is_dir($recordFolder)) {
                        \mkdir($recordFolder, 0755, true);
                    }
                    \rename($tempFile, "$recordFolder/$filename");
                    $att['file'] = "$recordFolder/$filename";
                    $att['path'] = "$recordPath/" . \rawurlencode($filename);
                }
            }

            $filesModel->insert([
                'record_id'         => $record['id'],
                'file'              => $att['file'],
                'path'              => $att['path'],
                'size'              => $att['size'],
                'type'              => $att['type'],
                'mime_content_type' => $att['mime_content_type'],
                'description'       => $att['description'] ?? '',
                'created_by'        => $userId
            ]);
            $changes[] = "attachment added: $filename";
        }

        $currentDescriptions = [];
        if (!empty($files['attachments']['existing'])) {
            $currentFiles = $filesModel->getRows(['WHERE' => "record_id={$record['id']} AND is_active=1"]);
            $currentDescriptions = \array_column($currentFiles, 'description', 'id');
        }
        foreach ($files['attachments']['existing'] ?? [] as $att) {
            $attId = (int)$att['id'];
            $newDesc = $att['description'] ?? '';
            if (($currentDescriptions[$attId] ?? '') !== $newDesc) {
                $filesModel->updateById($attId, [
                    'description' => $newDesc,
                    'updated_at'  => \date('Y-m-d H:i:s'),
                    'updated_by'  => $userId
                ]);
                $changes[] = "attachment description updated: #$attId";
            }
        }

        foreach ($files['attachments']['deleted'] ?? [] as $fileId) {
            $filesModel->deactivateById((int)$fileId, [
                'updated_at' => \date('Y-m-d H:i:s'),
                'updated_by' => $userId
            ]);
            $changes[] = "attachment deleted: #$fileId";
        }

        return $changes;
    }

    /**
     * Бүтээгдэхүүний бичлэгийг идэвхгүй болгох.
     *
     * Permission: system_product_delete
     * Эсвэл: өөрийн үүсгэсэн, нийтлэгдээгүй бичлэгийг устгах боломжтой
     *
     * @return void JSON response буцаана
     */
    public function deactivate()
    {
        try {
            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);

            $model = new ProductsModel($this->pdo);
            if (!$this->isUserCan('system_product_delete')) {
                $record = $model->getRowWhere(['id' => $id, 'is_active' => 1]);
                if (empty($record)
                    || (int)$record['created_by'] !== $this->getUserId()
                    || (int)$record['published'] !== 0
                ) {
                    throw new \Exception('No permission for an action [delete]!', 401);
                }
            }

            $deactivated = $model->deactivateById(
                $id,
                [
                    'updated_by' => $this->getUserId(),
                    'updated_at' => \date('Y-m-d H:i:s')
                ]
            );
            if (!$deactivated) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);

            $adminName = \trim(($this->getUser()->profile['first_name'] ?? '') . ' ' . ($this->getUser()->profile['last_name'] ?? ''));
            $this->getService('discord')?->contentAction('product', 'delete', $payload['title'] ?? "#{$id}", $id, $adminName);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            $context = ['action' => 'deactivate'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Бүтээгдэхүүнийг идэвхгүй болгох үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = '{record_id} дугаартай [{server_request.body.title}] бүтээгдэхүүнийг идэвхгүй болголоо';
                $context += ['record_id' => $id];
            }
            $this->log('product', $level, $message, $context);
        }
    }

    /**
     * Жишиг датаг цэвэрлэж production эхлүүлэх.
     *
     * Permission: system_product_index
     *
     * @return void
     */
    public function reset()
    {
        try {
            if (!$this->isUserCan('system_product_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $model = new ProductsModel($this->pdo);
            $table = $model->getName();

            $check = $this->query(
                "SELECT COUNT(*) as sample FROM $table " .
                "WHERE is_active=1 AND created_by IS NULL AND created_at = published_at AND category='_raptor_sample_'"
            )->fetch();
            if ((int)$check['sample'] === 0) {
                throw new \Exception(
                    $this->text('reset-only-sample-data'),
                    400
                );
            }

            // Жишиг датаны ID-уудыг олох
            $sampleIds = $this->query(
                "SELECT id FROM $table WHERE category='_raptor_sample_' AND created_by IS NULL AND created_at = published_at"
            )->fetchAll(\PDO::FETCH_COLUMN);

            if (!empty($sampleIds)) {
                $idList = \implode(',', \array_map('intval', $sampleIds));
                // Жишиг датаны файлуудыг устгах
                try { $this->exec("DELETE FROM {$table}_files WHERE record_id IN ($idList)"); } catch (\Throwable) {}
                // Жишиг датаг устгах
                $this->exec("DELETE FROM $table WHERE id IN ($idList)");
            }

            // Идэвхгүй (is_active=0) бичлэгүүдийг устгах
            $inactiveIds = $this->query(
                "SELECT id FROM $table WHERE is_active=0"
            )->fetchAll(\PDO::FETCH_COLUMN);
            if (!empty($inactiveIds)) {
                $idList = \implode(',', \array_map('intval', $inactiveIds));
                try { $this->exec("DELETE FROM {$table}_files WHERE record_id IN ($idList)"); } catch (\Throwable) {}
                $this->exec("DELETE FROM $table WHERE id IN ($idList)");
            }

            // Auto increment тохируулах
            $maxId = $this->query("SELECT MAX(id) as max_id FROM $table")->fetch();
            $nextId = ((int)($maxId['max_id'] ?? 0)) + 1;
            $this->exec("ALTER TABLE $table AUTO_INCREMENT = $nextId");

            $this->respondJSON([
                'status' => 'success',
                'message' => $this->text('sample-data-cleared')
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        } finally {
            $context = ['action' => 'reset'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Бүтээгдэхүүний хүснэгтийг reset хийх үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = 'Бүтээгдэхүүний хүснэгтийг жишиг датанаас цэвэрлэж production горимд шилжүүллээ';
            }
            $this->log('product', $level, $message, $context);
        }
    }

    /**
     * Бүтээгдэхүүн бүрийн хавсралт файлын тоог тоолох.
     *
     * @param string $table Хүснэгтийн нэр
     * @return array Бүтээгдэхүүний ID => {attach} бүтэцтэй массив
     */
    private function getFilesCounts(string $table): array
    {
        try {
            $sql =
                'SELECT record_id, COUNT(*) as cnt ' .
                "FROM {$table}_files " .
                'WHERE is_active=1 ' .
                'GROUP BY record_id';
            $result = $this->query($sql)->fetchAll();
            $counts = [];
            foreach ($result as $row) {
                $counts[$row['record_id']] = ['attach' => (int)$row['cnt']];
            }
            return $counts;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Payload доторх тоон талбаруудын хоосон string утгыг null болгох.
     */
    private function sanitizePayload(ProductsModel $model, array $payload): array
    {
        foreach ($payload as $key => $value) {
            if ($value === '' && $model->hasColumn($key) && $model->getColumn($key)->isNumeric()) {
                $payload[$key] = null;
            }
        }
        return $payload;
    }
}
