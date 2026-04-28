<?php

namespace Raptor\Development;

use Psr\Log\LogLevel;

use codesaur\Template\MemoryTemplate;

use Raptor\Content\FileController;
use Raptor\Content\FilesModel;

/**
 * Class DevRequestController
 * ------------------------------------------------------------------
 * Хөгжүүлэлтийн хүсэлтийн удирдлагын контроллер.
 *
 * Энэ контроллер нь:
 *  - Хүсэлтүүдийн жагсаалт харуулах (index, list)
 *  - Шинэ хүсэлт үүсгэх (create, store)
 *  - Хүсэлтийн дэлгэрэнгүй харах (view)
 *  - Хүсэлтэд хариулт бичих (respond)
 *  - Хүсэлтийг устгах (delete)
 *
 * Нэвтэрсэн бүх хэрэглэгч хүсэлт үүсгэж, өөрийнхөө хүсэлтийг харах боломжтой.
 * `system_development` эрхтэй хэрэглэгч бүх хүсэлтийг харах, хариулах, устгах эрхтэй.
 * Файл хавсаргах боломжтой (FileController-ийг өргөтгөсөн).
 *
 * @package Raptor\Development
 */
class DevRequestController extends FileController
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Хөгжүүлэлтийн хүсэлтүүдийн жагсаалтын хуудсыг харуулах.
     *
     * Нэвтэрсэн бүх хэрэглэгчид хандах боломжтой.
     *
     * @return void
     */
    public function index()
    {
        if (!$this->isUserAuthorized()) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        $dashboard = $this->dashboardTemplate(
            __DIR__ . '/devrequest-index.html',
            ['can_manage' => $this->isUserCan('system_development')]
        );
        $dashboard->set('title', $this->text('dev-requests'));
        $dashboard->render();

        $this->log('dev_requests', LogLevel::NOTICE, 'Хөгжүүлэлтийн хүсэлтүүдийн жагсаалтыг үзэж байна', ['action' => 'index']);
    }

    /**
     * Хөгжүүлэлтийн хүсэлтүүдийн JSON жагсаалтыг буцаах (AJAX).
     *
     * system_development эрхтэй бол бүх хүсэлтийг,
     * бусад тохиолдолд зөвхөн өөрийн үүсгэсэн хүсэлтийг буцаана.
     *
     * @return void JSON хариулт: { status, list[] }
     */
    public function list()
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $userId = $this->getUserId();

            $params = $this->getQueryParams();
            $conditions = [];
            $allowed = ['status'];
            foreach (\array_keys($params) as $name) {
                if (\in_array($name, $allowed)) {
                    $conditions[] = "t.$name=:$name";
                } else {
                    unset($params[$name]);
                }
            }

            // system_development эрхгүй бол зөвхөн өөрийн болон өөрт хуваарилагдсан хүсэлтүүд
            if (!$this->isUserCan('system_development')) {
                $conditions[] = "(t.created_by=:created_by OR t.assigned_to=:assigned_to)";
                $params['created_by'] = $userId;
                $params['assigned_to'] = $userId;
            }

            $whereClause = empty($conditions) ? '' : ' WHERE ' . \implode(' AND ', $conditions);

            $model = new DevRequestModel($this->pdo);
            $table = $model->getName();
            $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
            $reqFilesModel = new FilesModel($this->pdo);
            $reqFilesModel->setTable('dev_requests');
            $reqFilesTable = $reqFilesModel->getName();
            $respModel = new DevResponseModel($this->pdo);
            $respTable = $respModel->getName();
            $respFilesModel = new FilesModel($this->pdo);
            $respFilesModel->setTable('dev_requests_responses');
            $respFilesTable = $respFilesModel->getName();
            $sql =
                "SELECT t.id, t.title, t.status, t.created_at, t.created_by, t.assigned_to, " .
                "CONCAT(u.first_name, ' ', u.last_name) as created_user, " .
                "CONCAT(a.first_name, ' ', a.last_name) as assigned_user, " .
                "(" .
                    "(SELECT COUNT(*) FROM $reqFilesTable f WHERE f.record_id = t.id) + " .
                    "(SELECT COUNT(*) FROM $respFilesTable rf " .
                        "INNER JOIN $respTable r ON rf.record_id = r.id " .
                        "WHERE r.request_id = t.id)" .
                ") as attachment_count " .
                "FROM $table t LEFT JOIN $users u ON t.created_by = u.id " .
                "LEFT JOIN $users a ON t.assigned_to = a.id" .
                "$whereClause ORDER BY t.created_at DESC";
            $stmt = $this->prepare($sql);
            foreach ($params as $name => $value) {
                $stmt->bindValue(":$name", $value);
            }
            $list = $stmt->execute() ? $stmt->fetchAll() : [];

            $this->respondJSON([
                'status' => 'success',
                'list' => $list
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        }
    }

    /**
     * Шинэ хүсэлт үүсгэх формын хуудсыг харуулах.
     *
     * Нэвтэрсэн бүх хэрэглэгчид хандах боломжтой.
     *
     * @return void
     */
    public function create()
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $devRecipientIds = \array_column($this->getDevRecipients(), 'id');
            $usersModel = new \Raptor\User\UsersModel($this->pdo);
            $usersTable = $usersModel->getName();
            $allStmt = $this->prepare(
                "SELECT id, email, first_name, last_name FROM $usersTable WHERE is_active = 1 ORDER BY first_name ASC"
            );
            $allStmt->execute();
            $allUsers = $allStmt->fetchAll();

            $devUsers = [];
            $otherUsers = [];
            foreach ($allUsers as $u) {
                if (\in_array((int)$u['id'], $devRecipientIds)) {
                    $devUsers[] = $u;
                } else {
                    $otherUsers[] = $u;
                }
            }

            $dashboard = $this->dashboardTemplate(
                __DIR__ . '/devrequest-create.html',
                [
                    'max_file_size' => $this->getMaximumFileUploadSize(),
                    'dev_users' => $devUsers,
                    'other_users' => $otherUsers
                ]
            );
            $dashboard->set('title', $this->text('dev-requests'));
            $dashboard->render();
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        }
    }

    /**
     * Шинэ хөгжүүлэлтийн хүсэлтийг хадгалах.
     *
     * Нэвтэрсэн бүх хэрэглэгчид хандах боломжтой.
     *
     * @return void JSON хариулт: { status, message }
     */
    public function store()
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $payload = $this->getParsedBody();
            if (empty($payload['title']) || empty($payload['content'])) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }

            $assignedTo = !empty($payload['assigned_to']) ? \filter_var($payload['assigned_to'], \FILTER_VALIDATE_INT) : null;

            $insertData = [
                'title' => $payload['title'],
                'content' => $payload['content'],
                'created_by' => $this->getUserId()
            ];
            if ($assignedTo) {
                $insertData['assigned_to'] = $assignedTo;
            }

            $model = new DevRequestModel($this->pdo);
            $record = $model->insert($insertData);
            if (!isset($record['id'])) {
                throw new \Exception($this->text('record-insert-error'));
            }
            $id = $record['id'];

            $files = $this->getRequest()->getUploadedFiles()['attachments'] ?? [];
            if (!empty($files)) {
                $this->allowCommonTypes();
                $this->setFolder("/dev-requests/$id");
                $this->setSizeLimit(10485760); // 10MB
                $filesModel = new FilesModel($this->pdo);
                $filesModel->setTable('dev_requests');
                foreach ($files as $file) {
                    $uploaded = $this->moveUploaded($file);
                    if ($uploaded) {
                        $filesModel->insert($uploaded + [
                            'record_id' => $id,
                            'created_by' => $this->getUserId()
                        ]);
                    }
                }
            }

            // Email мэдэгдэл: зөвхөн сонгогдсон хэрэглэгчид
            $this->notifyNewRequest($id, $payload['title'], $assignedTo ?: null);

            $assignedName = '';
            if ($assignedTo) {
                $assignedUser = (new \Raptor\User\UsersModel($this->pdo))->getById($assignedTo);
                $assignedName = $assignedUser ? \trim(($assignedUser['first_name'] ?? '') . ' ' . ($assignedUser['last_name'] ?? '')) : '';
            }
            $this->dispatch(new \Raptor\Notification\DevRequestEvent(
                'new', $id, $payload['title'], $assignedName
            ));

            $this->respondJSON([
                'status' => 'success',
                'message' => $this->text('record-insert-success')
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        } finally {
            $context = ['action' => 'store'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Хөгжүүлэлтийн хүсэлт бүртгэх үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::INFO;
                $message = '{record_id} ({title}) дугаартай хөгжүүлэлтийн хүсэлт амжилттай бүртгэгдлээ';
                $context += ['record_id' => $id ?? null, 'title' => $payload['title'] ?? ''];
            }
            $this->log('dev_requests', $level, $message, $context);
        }
    }

    /**
     * Хөгжүүлэлтийн хүсэлтийн дэлгэрэнгүй хуудсыг харуулах.
     *
     * system_development эрхтэй бол дурын хүсэлтийг,
     * бусад тохиолдолд зөвхөн өөрийн үүсгэсэн хүсэлтийг харна.
     *
     * @param int $id Хүсэлтийн бичлэгийн ID
     * @return void
     */
    public function view(int $id)
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $userId = $this->getUserId();

            $canManage = $this->isUserCan('system_development');

            $model = new DevRequestModel($this->pdo);
            $table = $model->getName();
            $users = (new \Raptor\User\UsersModel($this->pdo))->getName();

            $sql =
                "SELECT t.*, CONCAT(u.first_name, ' ', u.last_name) as created_user, " .
                "CONCAT(a.first_name, ' ', a.last_name) as assigned_user " .
                "FROM $table t LEFT JOIN $users u ON t.created_by = u.id " .
                "LEFT JOIN $users a ON t.assigned_to = a.id " .
                "WHERE t.id = :id";
            $stmt = $this->prepare($sql);
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            $stmt->execute();
            $record = $stmt->fetch();

            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            // Өөрийн хүсэлт биш, хуваарилагдаагүй, system_development эрхгүй бол хориглох
            if (!$canManage && (int)$record['created_by'] !== $userId && (int)($record['assigned_to'] ?? 0) !== $userId) {
                throw new \Exception($this->text('system-no-permission'), 403);
            }

            // Хүсэлтийн хавсралтуудыг FilesModel-ээс татах
            $filesModel = new FilesModel($this->pdo);
            $filesModel->setTable('dev_requests');
            $filesTable = $filesModel->getName();
            $attachStmt = $this->prepare(
                "SELECT id, path, size, type, mime_content_type FROM $filesTable " .
                "WHERE record_id = :rid"
            );
            $attachStmt->bindValue(':rid', $id, \PDO::PARAM_INT);
            $attachStmt->execute();
            $attachments = $attachStmt->fetchAll();

            // Хариултуудын thread татах
            $responseModel = new DevResponseModel($this->pdo);
            $respTable = $responseModel->getName();
            $respSql =
                "SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) as author_name " .
                "FROM $respTable r LEFT JOIN $users u ON r.created_by = u.id " .
                "WHERE r.request_id = :request_id ORDER BY r.created_at ASC";
            $respStmt = $this->prepare($respSql);
            $respStmt->bindValue(':request_id', $id, \PDO::PARAM_INT);
            $respStmt->execute();
            $responses = $respStmt->fetchAll();

            // Хариулт бүрийн хавсралтуудыг FilesModel-ээс татах
            $respFilesModel = new FilesModel($this->pdo);
            $respFilesModel->setTable('dev_requests_responses');
            $respFilesTable = $respFilesModel->getName();
            foreach ($responses as &$resp) {
                $rfStmt = $this->prepare(
                    "SELECT id, path, size, type, mime_content_type FROM $respFilesTable " .
                    "WHERE record_id = :rid"
                );
                $rfStmt->bindValue(':rid', (int)$resp['id'], \PDO::PARAM_INT);
                $rfStmt->execute();
                $resp['_attachments'] = $rfStmt->fetchAll();
            }
            unset($resp);

            $dashboard = $this->dashboardTemplate(
                __DIR__ . '/devrequest-view.html',
                [
                    'record' => $record,
                    'attachments' => $attachments,
                    'responses' => $responses,
                    'can_manage' => $canManage
                ]
            );
            $dashboard->set('title', $this->text('dev-requests') . ' #' . $id);
            $dashboard->render();
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'view', 'record_id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай хүсэлтийг нээх үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record_id} ({title}) дугаартай хөгжүүлэлтийн хүсэлтийг үзэж байна';
                $context += ['title' => $record['title'], 'record' => $record];
            }
            $this->log('dev_requests', $level, $message, $context);
        }
    }

    /**
     * Хөгжүүлэлтийн хүсэлтэд хариулт бичих, төлөв шинэчлэх.
     *
     * Хариулт нь thread хэлбэрээр хадгалагдана (дарж бичихгүй).
     * Хариулт бичигдэх бүрт нөгөө талд email мэдэгдэл илгээнэ.
     *
     * @return void JSON хариулт: { status, title, message }
     */
    public function respond()
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $userId = $this->getUserId();

            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);

            $model = new DevRequestModel($this->pdo);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            // Өөрийн хүсэлт биш, хуваарилагдаагүй, system_development эрхгүй бол хориглох
            if (!$this->isUserCan('system_development') && (int)$record['created_by'] !== $userId && (int)($record['assigned_to'] ?? 0) !== $userId) {
                throw new \Exception($this->text('system-no-permission'), 403);
            }

            if (empty($payload['response'])) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }

            // Хариултыг thread-д нэмэх
            $responseModel = new DevResponseModel($this->pdo);
            $newStatus = '';
            if (!empty($payload['status']) && \in_array($payload['status'], ['pending', 'in_progress', 'resolved', 'closed'])) {
                $newStatus = $payload['status'];
            }
            $inserted = $responseModel->insert([
                'request_id' => $id,
                'content' => $payload['response'],
                'status' => $newStatus,
                'created_by' => $userId
            ]);
            if (!isset($inserted['id'])) {
                throw new \Exception($this->text('record-insert-error'));
            }
            $responseId = $inserted['id'];

            // Хариултын файл хавсаргах (FilesModel ашиглан)
            $files = $this->getRequest()->getUploadedFiles()['attachments'] ?? [];
            if (!empty($files)) {
                $this->allowCommonTypes();
                $this->setFolder("/dev-requests/$id/responses/$responseId");
                $this->setSizeLimit(10485760); // 10MB
                $filesModel = new FilesModel($this->pdo);
                $filesModel->setTable('dev_requests_responses');
                foreach ($files as $file) {
                    $uploaded = $this->moveUploaded($file);
                    if ($uploaded) {
                        $filesModel->insert($uploaded + [
                            'record_id' => $responseId,
                            'created_by' => $userId
                        ]);
                    }
                }
            }

            // Хүсэлтийн статус шинэчлэх
            $update = ['updated_by' => $userId];
            if (!empty($newStatus)) {
                $update['status'] = $newStatus;
            }
            $model->updateById($id, $update);

            // Email мэдэгдэл илгээх
            $this->notifyResponse($record, $userId, $payload['response']);

            $this->dispatch(new \Raptor\Notification\DevRequestEvent(
                'updated', $id, $record['title'], '', $newStatus
            ));

            $this->respondJSON([
                'status' => 'success',
                'title' => $this->text('success'),
                'message' => $this->text('record-update-success')
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status' => 'error',
                'title' => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            $context = ['action' => 'respond'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Хүсэлтэд хариулт бичих үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::INFO;
                $message = '{record_id} ({title}) дугаартай хүсэлтэд хариулт бичлээ';
                $context += ['record_id' => $id, 'title' => $record['title'] ?? ''];
            }
            $this->log('dev_requests', $level, $message, $context);
        }
    }

    /**
     * Хөгжүүлэлтийн хүсэлтийг устгах.
     *
     * system_development эрхтэй бол дурын хүсэлтийг,
     * бусад тохиолдолд зөвхөн өөрийн хүсэлтийг устгана.
     *
     * @return void JSON хариулт: { status, title, message }
     */
    public function delete()
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $userId = $this->getUserId();

            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);

            $model = new DevRequestModel($this->pdo);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            // Өөрийн хүсэлт биш, хуваарилагдаагүй, system_development эрхгүй бол хориглох
            if (!$this->isUserCan('system_development') && (int)$record['created_by'] !== $userId && (int)($record['assigned_to'] ?? 0) !== $userId) {
                throw new \Exception($this->text('system-no-permission'), 403);
            }

            $model->deleteById($id);
            (new \Raptor\Trash\TrashModel($this->pdo))->store(
                'dev_requests', $model->getName(), $id, $record, $this->getUserId()
            );

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
            $context = ['action' => 'delete'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Хөгжүүлэлтийн хүсэлтийг устгах үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = '{record_id} ({title}) дугаартай хөгжүүлэлтийн хүсэлтийг устгалаа';
                $context += ['record_id' => $id, 'title' => $record['title'] ?? ''];
            }
            $this->log('dev_requests', $level, $message, $context);
        }
    }

    /**
     * Шинэ хүсэлт үүссэн тухай email мэдэгдэл.
     *
     * Зөвхөн сонгогдсон (assigned_to) хэрэглэгчид email илгээнэ.
     * Email template: dev-request-new (Reference Templates-аас)
     *
     * @param int      $requestId  Хүсэлтийн ID
     * @param string   $title      Хүсэлтийн гарчиг
     * @param int|null $assignedTo Хүсэлтийг хүлээн авах хэрэглэгчийн ID
     */
    private function notifyNewRequest(int $requestId, string $title, ?int $assignedTo)
    {
        try {
            if (empty($assignedTo)) {
                return;
            }

            $mailer = $this->getService('mailer');
            if (empty($mailer)) {
                return;
            }

            // Өөртөө илгээхгүй
            if ($assignedTo === $this->getUserId()) {
                return;
            }

            $code = $this->getLanguageCode();
            $templateService = $this->getService('template_service');
            $template = $templateService->getByKeyword('dev-request-new', $code);
            if (empty($template)) {
                return;
            }

            $usersModel = new \Raptor\User\UsersModel($this->pdo);
            $recipient = $usersModel->getById($assignedTo);
            if (empty($recipient) || empty($recipient['email'])) {
                return;
            }

            $user = $this->getUser();
            $authorName = ($user->profile['first_name'] ?? '') . ' ' . ($user->profile['last_name'] ?? '');
            $link = $this->generateRouteLink('dev-requests-view', ['id' => $requestId], true);

            $memtemplate = new MemoryTemplate();
            $memtemplate->set('request_id', $requestId);
            $memtemplate->set('author', $authorName);
            $memtemplate->set('title', \htmlspecialchars($title));
            $memtemplate->set('link', $link);

            $subjectTemplate = new MemoryTemplate();
            $subjectTemplate->set('request_id', $requestId);
            $subjectTemplate->set('title', $title);
            $subjectTemplate->source($template['title']);
            $subject = $subjectTemplate->output();

            $memtemplate->source($template['content']);
            $body = $memtemplate->output();

            $mailer->mail($recipient['email'], $recipient['first_name'], $subject, $body)->send();
        } catch (\Throwable $e) {
            \error_log('DevRequest notifyNewRequest error: ' . $e->getMessage());
        }
    }

    /**
     * Хариулт бичигдсэн тухай email мэдэгдэл.
     *
     * Хариулт бичсэн хүнээс нөгөө талд email илгээнэ:
     *   - Хүсэлт үүсгэсэн хүн хариулбал -> сүүлийн response бичсэн хүнд
     *   - Бусад хүн хариулбал -> хүсэлт үүсгэсэн хүнд
     *
     * Email template: dev-request-response (Reference Templates-аас)
     *
     * @param array  $request      Хүсэлтийн бичлэг
     * @param int    $responderId  Хариулт бичсэн хэрэглэгчийн ID
     * @param string $responseText Хариултын текст
     */
    private function notifyResponse(array $request, int $responderId, string $responseText)
    {
        try {
            $mailer = $this->getService('mailer');
            if (empty($mailer)) {
                return;
            }

            $code = $this->getLanguageCode();
            $templateService = $this->getService('template_service');
            $template = $templateService->getByKeyword('dev-request-response', $code);
            if (empty($template)) {
                return;
            }

            $requestId = (int)$request['id'];
            $requestOwnerId = (int)$request['created_by'];

            // Хэнд email илгээх вэ?
            if ($responderId === $requestOwnerId) {
                // Хүсэлт үүсгэсэн хүн өөрөө хариулж байна
                // -> Сүүлийн response бичсэн (өөр) хүнд email очно
                $responseModel = new DevResponseModel($this->pdo);
                $respTable = $responseModel->getName();
                $stmt = $this->prepare(
                    "SELECT created_by FROM $respTable " .
                    "WHERE request_id = :rid AND created_by != :uid " .
                    "ORDER BY created_at DESC LIMIT 1"
                );
                $stmt->bindValue(':rid', $requestId, \PDO::PARAM_INT);
                $stmt->bindValue(':uid', $responderId, \PDO::PARAM_INT);
                $stmt->execute();
                $lastResponder = $stmt->fetch();
                $notifyUserId = $lastResponder ? (int)$lastResponder['created_by'] : null;
            } else {
                // Өөр хүн хариулж байна -> хүсэлт үүсгэсэн хүнд email очно
                $notifyUserId = $requestOwnerId;
            }

            if (empty($notifyUserId)) {
                return;
            }

            $usersModel = new \Raptor\User\UsersModel($this->pdo);
            $recipient = $usersModel->getById($notifyUserId);
            if (empty($recipient) || empty($recipient['email'])) {
                return;
            }

            $user = $this->getUser();
            $authorName = ($user->profile['first_name'] ?? '') . ' ' . ($user->profile['last_name'] ?? '');
            $link = $this->generateRouteLink('dev-requests-view', ['id' => $requestId], true);

            $memtemplate = new MemoryTemplate();
            $memtemplate->set('request_id', $requestId);
            $memtemplate->set('author', $authorName);
            $memtemplate->set('title', \htmlspecialchars($request['title']));
            $memtemplate->set('response', \nl2br(\htmlspecialchars($responseText)));
            $memtemplate->set('link', $link);

            $subjectTemplate = new MemoryTemplate();
            $subjectTemplate->set('request_id', $requestId);
            $subjectTemplate->set('title', $request['title']);
            $subjectTemplate->source($template['title']);
            $subject = $subjectTemplate->output();

            $memtemplate->source($template['content']);
            $body = $memtemplate->output();

            $mailer->mail($recipient['email'], $recipient['first_name'], $subject, $body)->send();
        } catch (\Throwable $e) {
            \error_log('DevRequest notifyResponse error: ' . $e->getMessage());
        }
    }

    /**
     * system_coder болон system_development эрхтэй хэрэглэгчдийн email жагсаалт.
     *
     * @return array [['id','email','first_name'], ...]
     */
    private function getDevRecipients(): array
    {
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $rolePerms = (new \Raptor\RBAC\RolePermission($this->pdo))->getName();
        $roles = (new \Raptor\RBAC\Roles($this->pdo))->getName();
        $permissions = (new \Raptor\RBAC\Permissions($this->pdo))->getName();
        $userRoles = 'rbac_user_role';

        $sql =
            "SELECT DISTINCT u.id, u.email, u.first_name FROM $users u " .
            "INNER JOIN $userRoles ur ON u.id = ur.user_id " .
            "INNER JOIN $roles r ON ur.role_id = r.id " .
            "WHERE r.name = 'coder' " .
            "OR ur.role_id IN (" .
            "  SELECT rp.role_id FROM $rolePerms rp " .
            "  INNER JOIN $permissions p ON rp.permission_id = p.id " .
            "  WHERE p.name = 'development'" .
            ")";

        $stmt = $this->prepare($sql);
        return $stmt->execute() ? $stmt->fetchAll() : [];
    }
}
