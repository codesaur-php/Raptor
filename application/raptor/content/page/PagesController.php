<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;

use codesaur\DataObject\Constants;

/**
 * Class PagesController
 *
 * Хуудас (Pages) модулийн Dashboard Controller.
 * Хуудас үүсгэх, засварлах, харах, устгах
 * зэрэг CRUD үйлдлийг гүйцэтгэнэ.
 *
 * Онцлог:
 *  - Хуудас бүр нэг хэлтэй (code), parent_id-р шатлал үүсгэнэ
 *  - type талбараар төрөл зааж өгнө (анхдагч: menu)
 *  - Header image (photo) + content media + attachment файлуудтай
 *  - Published/unpublished төлөвтэй, нийтлэхэд тусгай эрх шаардлагатай
 *  - Slug автоматаар үүсгэдэг (PagesModel)
 *
 * @package Raptor\Content
 */
class PagesController extends FileController
{
    use \Raptor\Template\DashboardTrait;
    use HtmlValidationTrait;

    /**
     * Хуудасны навигацийн мод бүтцийн Dashboard хуудас.
     *
     * - pages-list JSON endpoint-ийг дахин ашиглана (template-ээс fetch хийнэ)
     * - Хуудсуудын parent_id шатлалыг tree view хэлбэрээр харуулна
     *
     * Permission: system_content_index
     */
    public function nav()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        $dashboard = $this->dashboardTemplate(__DIR__ . '/pages-nav.html');
        $dashboard->set('title', $this->text('pages-navigation'));
        $dashboard->render();

        $this->log('pages', LogLevel::NOTICE, 'Хуудасны навигацийн модыг үзэж байна', ['action' => 'nav']);
    }

    /**
     * Хуудасны жагсаалтын хүснэгт Dashboard хуудас.
     *
     * - Шүүлтүүр: хэл (code), төрөл (type), ангилал (category), нийтлэгдсэн эсэх (published)
     * - pages-table.html template-д filter утгуудыг дамжуулна
     *
     * Permission: system_content_index
     */
    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        $filters = [];
        // pages хүснэгтийн нэрийг PagesModel::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
        $table = (new PagesModel($this->pdo))->getName();
        $codes_result = $this->query(
            "SELECT DISTINCT code FROM $table"
        )->fetchAll();
        $languages = $this->getLanguages();
        $filters['code']['title'] = $this->text('language');
        foreach ($codes_result as $row) {
            $filters['code']['values'][$row['code']] = "{$languages[$row['code']]['title']} [{$row['code']}]";
        }
        $types_result = $this->query(
            "SELECT DISTINCT type FROM $table"
        )->fetchAll();
        $filters['type']['title'] = $this->text('type');
        foreach ($types_result as $row) {
            $filters['type']['values'][$row['type']] = $row['type'];
        }
        $categories_result = $this->query(
            "SELECT DISTINCT category FROM $table"
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
        $dashboard = $this->dashboardTemplate(__DIR__ . '/pages-table.html', ['filters' => $filters]);
        $dashboard->set('title', $this->text('pages'));
        $dashboard->render();

        $this->log('pages', LogLevel::NOTICE, 'Хуудас жагсаалтыг үзэж байна', ['action' => 'index']);
    }
    
    /**
     * Хуудасны жагсаалтыг JSON хэлбэрээр буцаана.
     *
     * Query параметрүүдээр шүүлтүүр хийх боломжтой:
     * code, type, category, published
     *
     * Permission: system_content_index
     */
    public function list()
    {
        try {
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $params = $this->getQueryParams();
            $conditions = [];
            $allowed = ['code', 'type', 'category', 'published'];
            foreach (\array_keys($params) as $name) {
                if (\in_array($name, $allowed)) {
                    $conditions[] = "$name=:$name";
                } else {
                    unset($params[$name]);
                }
            }
            $whereClause = empty($conditions) ? '' : ' WHERE ' . \implode(' AND ', $conditions);
            // pages хүснэгтийн нэрийг PagesModel::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
            $table = (new PagesModel($this->pdo))->getName();
            $select_pages =
                'SELECT id, photo, title, slug, code, type, category, position, link, published, published_at ' .
                "FROM $table$whereClause ORDER BY position, id";
            $pages_stmt = $this->prepare($select_pages);
            foreach ($params as $name => $value) {
                $pages_stmt->bindValue(":$name", $value);
            }
            $pages = $pages_stmt->execute() ? $pages_stmt->fetchAll() : [];
            $infos = $this->getInfos($table);
            $files_counts = $this->getFilesCounts($table);
            // Жишиг дата байгаа эсэхийг шалгах
            $sampleCheck = $this->query(
                "SELECT COUNT(*) as total, " .
                "SUM(CASE WHEN created_by IS NULL AND created_at = published_at AND category='_raptor_sample_' THEN 1 ELSE 0 END) as sample " .
                "FROM $table"
            )->fetch();
            $isSample = (int)$sampleCheck['sample'] > 0;

            $this->respondJSON([
                'status' => 'success',
                'list' => $pages,
                'infos' => $infos,
                'files_counts' => $files_counts,
                'is_sample' => $isSample
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        }
    }

    /**
     * Жишиг датаг цэвэрлэж production эхлүүлэх.
     *
     * Хүснэгтийн бүх өгөгдлийг устгаж, auto-increment-г 1 рүү буцаана.
     * Зөвхөн жишиг дата байгаа үед л ажиллана.
     *
     * Permission: system_content_index
     */
    public function reset()
    {
        try {
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $model = new PagesModel($this->pdo);
            $table = $model->getName();

            // Жишиг дата байгаа эсэхийг давхар шалгах
            $check = $this->query(
                "SELECT COUNT(*) as sample FROM $table " .
                "WHERE created_by IS NULL AND created_at = published_at AND category='_raptor_sample_'"
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

            // Auto increment тохируулах
            $maxId = $this->query("SELECT MAX(id) as max_id FROM $table")->fetch();
            $nextId = ((int)($maxId['max_id'] ?? 0)) + 1;
            if ($this->getDriverName() === Constants::DRIVER_PGSQL) {
                $this->exec("SELECT setval(pg_get_serial_sequence('$table', 'id'), $nextId, false)");
            } else {
                $this->exec("ALTER TABLE $table AUTO_INCREMENT = $nextId");
            }

            $this->invalidateCache('pages_nav.{code}', 'featured_pages.{code}');
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
                $message = 'Хуудасны хүснэгтийг reset хийх үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = 'Хуудасны хүснэгтийг жишиг датанаас цэвэрлэж production горимд шилжүүллээ';
            }
            $this->log('pages', $level, $message, $context);
        }
    }

    /**
     * Шинэ хуудас үүсгэх.
     *
     * - GET: Хуудас үүсгэх форм харуулна
     * - POST: Хуудас үүсгэж DB-д хадгална
     *   - Header image, content media, attachment файлууд temp folder-оос зөөгдөнө
     *   - published=1 бол system_content_publish эрх шаардлагатай
     *
     * Permission: system_content_insert
     */
    public function insert()
    {
        try {
            if (!$this->isUserCan('system_content_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $model = new PagesModel($this->pdo);
            $table = $model->getName();
            if ($this->getRequest()->getMethod() == 'POST') {
                $payload = $this->getParsedBody();
                if (empty($payload['title'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }

                // HTML контент tag бүтэц шалгах
                if (!empty($payload['content'])) {
                    $this->validateHtmlContent($payload['content']);
                }

                // Нийтлэх эрх шаардлагатай талбарууд
                $isPublished = ($payload['published'] ?? 0) == 1;
                $needsPublishPermission =
                    $isPublished ||
                    ($payload['is_featured'] ?? 0) == 1;
                if ($needsPublishPermission
                    && !$this->isUserCan('system_content_publish')
                ) {
                    throw new \Exception($this->text('system-no-permission'), 401);
                }

                if ($isPublished) {
                    $payload['published_at'] = \date('Y-m-d H:i:s');
                    $payload['published_by'] = $this->getUserId();
                }

                // Model-д байхгүй талбаруудыг payload-оос салгах
                if (\array_key_exists('files', $payload)) {
                    $files = \json_decode($payload['files'], true) ?: [];
                    unset($payload['files']);
                } else {
                    $files = [];
                }

                // Parent хэлний шалгалт
                $parentId = (int)($payload['parent_id'] ?? 0);
                if ($parentId > 0 && !empty($payload['code'])) {
                    $parentRow = $model->getById($parentId);
                    if (empty($parentRow) || $parentRow['code'] !== $payload['code']) {
                        throw new \InvalidArgumentException(
                            $this->text('invalid-request'),
                            400
                        );
                    }
                }

                // Link шалгах
                $link = \trim($payload['link'] ?? '');
                if (!$this->isValidLink($link)) {
                    throw new \InvalidArgumentException(
                        $this->text('link-must-be-url'),
                        400
                    );
                }

                $record = $model->insert(
                    $payload + ['created_by' => $this->getUserId()]
                );
                if (!isset($record['id'])) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                $id = $record['id'];

                // Файлуудыг нэгдсэн аргаар боловсруулах
                $fileChanges = $this->processFiles($record, $files, true);

                $this->invalidateCache('pages_nav.{code}', 'featured_pages.{code}');
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);

                $action = !empty($payload['published']) ? 'publish' : 'insert';
                $this->dispatch(new \Raptor\Notification\ContentEvent(
                    $action, 'page', $payload['title'] ?? '', $id
                ));
            } else {
                $allInfos = $this->getInfos($table);
                $codeParam = $this->getQueryParams()['code'] ?? '';
                $dashboard = $this->dashboardTemplate(
                    __DIR__ . '/page-insert.html',
                    [
                        'table' => $table,
                        'all_infos' => $allInfos,
                        'infos' => $codeParam !== '' ? \array_filter($allInfos, fn($i) => $i['code'] === $codeParam) : $allInfos,
                        'max_file_size' => $this->getMaximumFileUploadSize()
                    ]
                );
                $dashboard->set('title', $this->text('add-record') . ' | Pages');
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
                $message = 'Хуудас үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'POST') {
                $level = LogLevel::INFO;
                $message = '{record.id} дугаартай [{record.title}] хуудсыг амжилттай үүсгэлээ';
                $context += ['record_id' => $id, 'record' => $record];
                $context['file_changes'] = !empty($fileChanges) ? $fileChanges : 'files not changed';
            } else {
                $level = LogLevel::NOTICE;
                $message = 'Хуудас үүсгэх үйлдлийг эхлүүллээ';
            }
            $this->log('pages', $level, $message, $context);
        }
    }

    /**
     * Хуудасны дэлгэрэнгүй мэдээлэл харах (Dashboard view).
     *
     * - Бичлэг + хавсралт файлууд + эцэг хуудасны мэдээлэл
     * - Дэд хуудас агуулсан бол has_children анхааруулга харуулна
     *
     * Permission: system_content_index
     * Нийтлэгдсэн бичлэгийг нэвтэрсэн бүх admin харах боломжтой
     *
     * @param int $id Хуудасны ID
     */
    public function view(int $id)
    {
        try {
            $model = new PagesModel($this->pdo);
            $table = $model->getName();
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            if (!$this->isUserCan('system_content_index')
                && (int)$record['published'] !== 1
            ) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $filesModel = new FilesModel($this->pdo);
            $filesModel->setTable($table);
            $files = $filesModel->getRows(['WHERE' => "record_id=$id"]);
            $infos = $this->getInfos($table, "(id=$id OR id={$record['parent_id']})");
            $childCount = $this->query(
                "SELECT COUNT(*) as cnt FROM $table WHERE parent_id=$id"
            )->fetch();
            $dashboard = $this->dashboardTemplate(
                __DIR__ . '/page-view.html',
                [
                    'table' => $table,
                    'record' => $record,
                    'files' => $files,
                    'infos' => $infos,
                    'has_children' => (int)($childCount['cnt'] ?? 0) > 0
                ]
            );
            $dashboard->set('title', $this->text('view-record') . ' | Pages');
            $dashboard->render();
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'view', 'record_id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай хуудасны мэдээллийг нээх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.id} дугаартай [{record.title}] хуудасны мэдээллийг үзэж байна';
                $context += ['record' => $record, 'files' => $files];
            }
            $this->log('pages', $level, $message, $context);
        }
    }

    /**
     * Хуудасны бичлэгийг шинэчлэх.
     *
     * - GET: Засварлах форм харуулна (дэд хуудас агуулсан бол has_children анхааруулга)
     * - PUT: Бичлэгийг шинэчлэнэ
     *   - type, code зэрэг бүх талбарыг өөрчлөх боломжтой
     *   - Гол зураг (photo) шинэчлэх/устгах боломжтой
     *   - Хавсаргасан файлууд нэмэх/засах/устгах боломжтой
     *   - published төлөв өөрчлөхөд system_content_publish эрх шаардлагатай
     *
     * Permission: system_content_update
     * Эсвэл: өөрийн үүсгэсэн, нийтлэгдээгүй бичлэгийг засах боломжтой
     *
     * @param int $id Шинэчлэх хуудасны ID
     */
    public function update(int $id)
    {
        try {
            $model = new PagesModel($this->pdo);
            $table = $model->getName();
            $filesModel = new FilesModel($this->pdo);
            $filesModel->setTable($table);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            if (!$this->isUserCan('system_content_update')) {
                if ((int)$record['created_by'] !== $this->getUserId()
                    || (int)$record['published'] !== 0
                ) {
                    throw new \Exception($this->text('system-no-permission'), 401);
                }
            } elseif ($record['published'] == 1
                && !$this->isUserCan('system_content_publish')
            ) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            if ($this->getRequest()->getMethod() == 'PUT') {
                $payload = $this->getParsedBody();
                if (empty($payload['title'])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }

                // HTML контент tag бүтэц шалгах
                if (!empty($payload['content'])) {
                    $this->validateHtmlContent($payload['content']);
                }

                // Нийтлэх эрх шаардлагатай талбарууд
                $isPublished = ($payload['published'] ?? 0) == 1;
                $needsPublishPermission =
                    $isPublished ||
                    ($payload['is_featured'] ?? 0) == 1;
                if ($needsPublishPermission
                    && !$this->isUserCan('system_content_publish')
                ) {
                    throw new \Exception($this->text('system-no-permission'), 401);
                }

                // Нийтлэх төлөв өөрчлөгдсөн бол
                if ($isPublished && $record['published'] != 1) {
                    $payload['published_at'] = \date('Y-m-d H:i:s');
                    $payload['published_by'] = $this->getUserId();
                }

                // Parent circular reference + хэлний шалгалт
                $parentId = (int)($payload['parent_id'] ?? 0);
                if ($parentId > 0) {
                    $parentRow = $model->getById($parentId);
                    if (empty($parentRow) || $parentRow['code'] !== $record['code']) {
                        throw new \InvalidArgumentException(
                            $this->text('invalid-request'),
                            400
                        );
                    }
                    $descendantIds = $this->getDescendantIds($id, $table);
                    if ($parentId === $id || \in_array($parentId, $descendantIds)) {
                        throw new \InvalidArgumentException(
                            $this->text('cannot-set-descendant-as-parent'),
                            400
                        );
                    }
                }

                // Link шалгах
                $link = \trim($payload['link'] ?? '');
                if (!$this->isValidLink($link)) {
                    throw new \InvalidArgumentException(
                        $this->text('link-must-be-url'),
                        400
                    );
                }

                // Model-д байхгүй болон аюултай талбаруудыг payload-оос салгах
                if (\array_key_exists('files', $payload)) {
                    $files = \json_decode($payload['files'], true) ?: [];
                    unset($payload['files']);
                } else {
                    $files = [];
                }
                if (\array_key_exists('id', $payload)) {
                    unset($payload['id']);
                }

                // Файлуудыг эхлээд боловсруулах
                $fileChanges = $this->processFiles($record, $files);

                // Өөрчлөлт байгаа эсэхийг шалгах
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

                // Description хоосон бол content-оос автоматаар үүсгэх
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

                $this->invalidateCache('pages_nav.{code}', 'featured_pages.{code}');
                $this->respondJSON([
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success')
                ]);

                $wasPublished = !empty($record['published']);
                $nowPublished = !empty($payload['published']);
                $action = (!$wasPublished && $nowPublished) ? 'publish' : 'update';
                $this->dispatch(new \Raptor\Notification\ContentEvent(
                    $action, 'page', $payload['title'] ?? $record['title'] ?? '', $id,
                    updates: $updates
                ));
            } else {
                $excludeIds = $this->getDescendantIds($id, $table);
                $excludeIds[] = $id;
                $excludeList = \implode(',', $excludeIds);
                $codeQuoted = $this->quote($record['code']);
                $infos = $this->getInfos($table, "id NOT IN ($excludeList) AND code=$codeQuoted");
                $files = $filesModel->getRows(['WHERE' => "record_id=$id"]);
                $childCount = $this->query(
                    "SELECT COUNT(*) as cnt FROM $table WHERE parent_id=$id"
                )->fetch();
                $dashboard = $this->dashboardTemplate(
                    __DIR__ . '/page-update.html',
                    [
                        'table' => $table,
                        'record' => $record,
                        'infos' => $infos,
                        'files' => $files,
                        'has_children' => (int)($childCount['cnt'] ?? 0) > 0,
                        'max_file_size' => $this->getMaximumFileUploadSize()
                    ]
                );
                $dashboard->set('title', $this->text('edit-record') . ' | Pages');
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
                $message = '{record_id} дугаартай хуудасны мэдээллийг шинэчлэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'PUT') {
                $level = LogLevel::INFO;
                $message = '{record.id} дугаартай [{record.title}] хуудасны мэдээллийг амжилттай шинэчлэлээ';
                $context += ['updates' => $updates, 'record' => $updated];
                $context['file_changes'] = !empty($fileChanges) ? $fileChanges : 'files not changed';
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.id} дугаартай [{record.title}] хуудасны мэдээллийг шинэчлэхээр нээж байна';
                $context += ['record' => $record, 'files' => $files];
            }
            $this->log('pages', $level, $message, $context);
        }
    }

    /**
     * Хуудсын бичлэгийг устгах.
     *
     * Permission: system_content_delete
     * Эсвэл: өөрийн үүсгэсэн, нийтлэгдээгүй бичлэгийг устгах боломжтой
     */
    public function delete()
    {
        try {
            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);

            $model = new PagesModel($this->pdo);
            if (!$this->isUserCan('system_content_delete')) {
                $record = $model->getById($id);
                if (empty($record)
                    || (int)$record['created_by'] !== $this->getUserId()
                    || (int)$record['published'] !== 0
                ) {
                    throw new \Exception('No permission for an action [delete]!', 401);
                }
            }

            if (!isset($record)) {
                $record = $model->getById($id);
            }
            $model->deleteById($id);
            if (!empty($record)) {
                (new \Raptor\Trash\TrashModel($this->pdo))->store(
                    'pages', $model->getName(), $id, $record, $this->getUserId()
                );
            }
            $this->invalidateCache('pages_nav.{code}', 'featured_pages.{code}');
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);

            $this->dispatch(new \Raptor\Notification\ContentEvent(
                'delete', 'page', $payload['title'] ?? "#{$id}", $id
            ));
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            $context = ['action' => 'delete'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Хуудсыг устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = '{record_id} дугаартай [{server_request.body.title}] хуудсыг устгалаа';
                $context += ['record_id' => $id];
            }
            $this->log('pages', $level, $message, $context);
        }
    }

    /**
     * Тухайн хуудасны бүх удам (children, grandchildren, ...) ID-г олох.
     *
     * @param int    $id    Хуудасны ID
     * @param string $table Хүснэгтийн нэр
     * @return array Бүх удмын ID-ийн массив
     */
    private function getDescendantIds(int $id, string $table): array
    {
        $allPages = $this->query(
            "SELECT id, parent_id FROM $table"
        )->fetchAll();

        $childrenMap = [];
        foreach ($allPages as $row) {
            $pid = (int)$row['parent_id'];
            $childrenMap[$pid][] = (int)$row['id'];
        }

        $descendants = [];
        $queue = $childrenMap[$id] ?? [];
        while (!empty($queue)) {
            $current = \array_shift($queue);
            $descendants[] = $current;
            foreach ($childrenMap[$current] ?? [] as $child) {
                $queue[] = $child;
            }
        }

        return $descendants;
    }

    /**
     * Хуудасны шатлалтай мэдээллийг авах.
     *
     * Хуудас бүрийн parent_id-г дагаж parent_titles
     * (жишээ: "Нүүр > Бидний тухай > ") бүрдүүлнэ.
     *
     * @param string $table  Хүснэгтийн нэр
     * @param string $condition  Нэмэлт WHERE нөхцөл (хоосон бол бүгдийг авна)
     * @return array  id => [id, parent_id, title, position, code, parent_titles] бүтэцтэй массив
     */
    private function getInfos(string $table, string $condition = ''): array
    {
        $pages = [];
        try {
            $select_pages =
                'SELECT id, parent_id, title, position, code ' .
                "FROM $table WHERE 1=1";
            $result = $this->query("$select_pages ORDER BY position, id")->fetchAll();
            foreach ($result as $record) {
                $pages[$record['id']] = $record;
            }
        } catch (\Throwable) {}

        if (!empty($condition)) {
            $pages_specified = [];
            try {
                $select_pages .= " AND $condition";
                $result_specified = $this->query("$select_pages ORDER BY position, id")->fetchAll();
                foreach ($result_specified as $row) {
                    $pages_specified[$row['id']] = $row;
                }
            } catch (\Throwable $e) {
            }
        }
        foreach ($pages as $page) {
            $id = $page['id'];
            $ancestry = $this->findAncestry($id, $pages);
            if (\array_key_exists($id, $ancestry)) {
                unset($ancestry[$id]);
                if (CODESAUR_DEVELOPMENT) {
                    \error_log(__CLASS__ . ": Page $id misconfigured with parenting path!");
                }
            }
            if (empty($ancestry)) {
                continue;
            }

            $path = '';
            $ancestry_keys = \array_flip($ancestry);
            for ($i = \count($ancestry_keys); $i > 0; $i--) {
                $path .= "{$pages[$ancestry_keys[$i]]['title']} > ";
            }
            $pages[$id]['parent_titles'] = $path;
            if (isset($pages_specified[$id])) {
                $pages_specified[$id]['parent_titles'] = $path;
            }
        }

        return $pages_specified ?? $pages;
    }

    /**
     * Хуудасны өвөг эцгийн шатлалыг рекурсивээр олох.
     *
     * @param int   $id       Хуудасны ID
     * @param array $pages    Бүх хуудасны массив (id => row)
     * @param array $ancestry Өвөг эцгийн жагсаалт (reference)
     * @return array parent_id => depth бүтэцтэй массив
     */
    private function findAncestry(int $id, array $pages, array &$ancestry = []): array
    {
        $parent = $pages[$id]['parent_id'];
        if (empty($parent)
            || !isset($pages[$parent])
            || \array_key_exists($parent, $ancestry)
        ) {
            return $ancestry;
        }

        $ancestry[$parent] = \count($ancestry) + 1;
        return $this->findAncestry($parent, $pages, $ancestry);
    }

    /**
     * Link талбарын утгыг шалгах.
     *
     * Хоосон утга зөвшөөрнө. Хоосон биш бол URL эсвэл локал зам байх ёстой.
     *
     * @param string $link Шалгах утга
     * @return bool Зөв эсэх
     */
    private function isValidLink(string $link): bool
    {
        if ($link === '') {
            return true;
        }

        // Локал зам: / -ээр эхлэх
        if ($link[0] === '/') {
            return true;
        }

        // URL: http://, https://, //, mailto:, tel:
        if (\preg_match('#^(https?://|//|mailto:|tel:)#i', $link)) {
            return true;
        }

        return false;
    }

    /**
     * Хуудас бүрийн хавсралт файлын тоог тоолох.
     *
     * @param string $table Хүснэгтийн нэр
     * @return array record_id => ['attach' => count] бүтэцтэй массив
     */
    private function getFilesCounts(string $table): array
    {
        try {
            $sql =
                'SELECT record_id, COUNT(*) as cnt ' .
                "FROM {$table}_files " .
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
     * Файлуудыг нэгдсэн аргаар боловсруулах.
     *
     * Frontend-ээс ирсэн files JSON-г parse хийж:
     * - Header image хадгалах/устгах
     * - Content media файлууд хадгалах
     * - Attachment файлууд нэмэх/шинэчлэх/устгах
     *
     * @param array $record  Бичлэг
     * @param array $files   Frontend-ээс ирсэн файлуудын мэдээлэл
     * @param bool $fromTemp Insert үйлдэл эсэх (temp folder-оос зөөх)
     * @return array Файлуудын өөрчлөлтүүдийн жагсаалт (хоосон бол өөрчлөлт байхгүй)
     */
    private function processFiles(array $record, array $files, bool $fromTemp = false): array
    {
        $changes = [];
        $userId = $this->getUserId();

        $model = new PagesModel($this->pdo);
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

        // Header image устгагдсан эсэх
        if (($files['headerImageRemoved'] ?? false) && !empty($record['photo'])) {
            $photoFilename = \basename(\rawurldecode($record['photo']));
            $this->unlinkByName($photoFilename);
            $record = $model->updateById($record['id'], ['photo' => '']);
            $changes[] = "header image deleted: $photoFilename";
        }

        // 1. Header Image - зөвхөн pages.photo талбарт хадгална
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

        // 2. Content Media - DB-д бүртгэхгүй, зөвхөн файл зөөх
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

        // Content HTML дахь temp path-уудыг record path болгох
        if ($fromTemp) {
            $html = $record['content'] ?? '';
            if (\strpos($html, $tempPath) !== false) {
                $html = \str_replace($tempPath, $recordPath, $html);
                $model->updateById($record['id'], ['content' => $html]);
            }
        }

        // 3. Attachments - New
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

        // 4. Attachments - Update existing (description only)
        $currentDescriptions = [];
        if (!empty($files['attachments']['existing'])) {
            $currentFiles = $filesModel->getRows(['WHERE' => "record_id={$record['id']}"]);
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

        // 5. Attachments - Delete
        foreach ($files['attachments']['deleted'] ?? [] as $fileId) {
            $fileRecord = $filesModel->getById((int)$fileId);
            $filesModel->deleteById((int)$fileId);
            if ($fileRecord) {
                (new \Raptor\Trash\TrashModel($this->pdo))->store(
                    'pages', $filesModel->getName(), (int)$fileId, $fileRecord, $userId
                );
            }
            $changes[] = "attachment deleted: #$fileId";
        }

        return $changes;
    }
}
