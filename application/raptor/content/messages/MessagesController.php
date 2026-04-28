<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;

/**
 * Class MessagesController
 *
 * Холбоо барих хуудаснаас ирсэн мессежүүдийг удирдах dashboard контроллер.
 *
 * @package Raptor\Content
 */
class MessagesController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Мессежүүдийн жагсаалт хуудас.
     *
     * @return void
     */
    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        $filters = [];
        $filters['is_read'] = [
            'title' => $this->text('status'),
            'values' => [0 => $this->text('new'), 1 => $this->text('read'), 2 => $this->text('replied')]
        ];
        $settings = $this->getAttribute('settings', []);
        $dashboard = $this->dashboardTemplate(__DIR__ . '/messages-index.html', [
            'filters' => $filters,
            'contact_email_notify' => !empty($_ENV['RAPTOR_CONTACT_EMAIL_TO'] ?? ''),
            'notify_email' => $_ENV['RAPTOR_CONTACT_EMAIL_TO'] ?? '',
            'settings_email' => $settings['email'] ?? ''
        ]);
        $dashboard->set('title', $this->text('messages'));
        $dashboard->render();

        $this->log('messages', LogLevel::NOTICE, 'Мессежүүдийн жагсаалтыг үзэж байна', ['action' => 'index']);
    }

    /**
     * Мессежүүдийн жагсаалтыг JSON хэлбэрээр буцаах.
     *
     * @return void
     */
    public function list()
    {
        try {
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $params = $this->getQueryParams();
            $conditions = [];
            $allowed = ['is_read'];
            foreach (\array_keys($params) as $name) {
                if (\in_array($name, $allowed)) {
                    $conditions[] = "$name=:$name";
                } else {
                    unset($params[$name]);
                }
            }
            $where = !empty($conditions) ? \implode(' AND ', $conditions) : '1=1';
            $table = (new MessagesModel($this->pdo))->getName();
            $stmt = $this->prepare(
                "SELECT id, name, phone, email, message, code, is_read, created_at
                 FROM $table WHERE $where ORDER BY created_at DESC"
            );
            foreach ($params as $name => $value) {
                $stmt->bindValue(":$name", $value);
            }
            $rows = $stmt->execute() ? $stmt->fetchAll() : [];

            $this->respondJSON(['status' => 'success', 'list' => $rows]);
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
        }
    }

    /**
     * Мессежийн дэлгэрэнгүйг modal-ээр харуулах.
     *
     * Уншаагүй мессежийг автоматаар уншсан гэж тэмдэглэнэ.
     *
     * @return void
     */
    public function view(int $id)
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        $model = new MessagesModel($this->pdo);
        $table = $model->getName();
        $record = $model->getById($id);
        if (empty($record)) {
            throw new \Exception($this->text('no-record-selected'), 404);
        }

        // Уншаагүй бол уншсан болгох
        if (empty($record['is_read'])) {
            $this->exec("UPDATE $table SET is_read=1 WHERE id=$id");
            $record['is_read'] = 1;
        }

        $this->template(__DIR__ . '/messages-view-modal.html', ['record' => $record])->render();

        $this->log('messages', LogLevel::NOTICE, '#{record_id} ({name}) мессежийг нээж үзэж байна', ['action' => 'view', 'record_id' => $id, 'name' => $record['name']]);
    }

    /**
     * Мессежийг хариулсан гэж тэмдэглэх (partial update).
     *
     * PATCH /dashboard/messages/replied/{id}
     * Body: { "note": "..." }
     *
     * Зөвхөн is_read болон replied_note талбаруудыг шинэчилнэ.
     *
     * Permission: system_content_index
     *
     * @param int $id Мессежийн ID
     * @return void
     */
    public function markReplied(int $id)
    {
        try {
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $model = new MessagesModel($this->pdo);
            $table = $model->getName();
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'), 404);
            }

            $payload = $this->getParsedBody();
            $note = \trim($payload['note'] ?? '');
            $stmt = $this->prepare("UPDATE $table SET is_read=2, replied_note=:note WHERE id=:id");
            $stmt->bindValue(':note', $note);
            $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
            $stmt->execute();

            $this->respondJSON([
                'status' => 'success',
                'title' => $this->text('success'),
                'message' => $this->text('replied')
            ]);

            $this->log('messages', LogLevel::INFO, '#{record_id} ({name}) мессежид хариулсан гэж тэмдэглэлээ', ['action' => 'mark-replied', 'record_id' => $id, 'name' => $record['name']]);

            $this->dispatch(new \Raptor\Notification\ContentEvent(
                'update', 'message', $record['name'] ?? "#{$id}", $id
            ));
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status' => 'error',
                'title' => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        }
    }

    /**
     * Мессежийг устгах.
     *
     * @return void
     */
    public function delete()
    {
        try {
            if (!$this->isUserCan('system_content_delete')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $payload = $this->getParsedBody();
            $id = (int)($payload['id'] ?? 0);
            if (empty($id)) {
                throw new \Exception($this->text('no-record-selected'), 400);
            }

            $model = new MessagesModel($this->pdo);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'), 404);
            }

            $model->deleteById($id);
            (new \Raptor\Trash\TrashModel($this->pdo))->store(
                'messages', $model->getName(), $id, $record, $this->getUserId()
            );

            $this->respondJSON([
                'status' => 'success',
                'title' => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);

            $this->log('messages', LogLevel::WARNING, '#{record_id} ({name}) мессежийг устгалаа', ['action' => 'delete', 'record_id' => $id, 'name' => $record['name']]);

            $this->dispatch(new \Raptor\Notification\ContentEvent(
                'delete', 'message', $record['name'] ?? "#{$id}", $id
            ));
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status' => 'error',
                'title' => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        }
    }
}
