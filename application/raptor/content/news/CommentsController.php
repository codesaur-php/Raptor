<?php

namespace Raptor\Content;

use Psr\Log\LogLevel;

/**
 * Class CommentsController
 *
 * Сайт дахь бүх мэдээний сэтгэгдлүүдийг удирдах dashboard контроллер.
 *
 * @package Raptor\Content
 */
class CommentsController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Сэтгэгдлүүдийн жагсаалт хуудас.
     *
     * @return void
     */
    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        $settings = $this->getAttribute('settings', []);
        $dashboard = $this->dashboardTemplate(__DIR__ . '/comments-index.html', [
            'comment_email_notify' => !empty($_ENV['RAPTOR_COMMENT_EMAIL_TO'] ?? ''),
            'notify_email' => $_ENV['RAPTOR_COMMENT_EMAIL_TO'] ?? '',
            'settings_email' => $settings['email'] ?? ''
        ]);
        $dashboard->set('title', $this->text('comments'));
        $dashboard->render();

        $this->log('news', LogLevel::NOTICE, 'Бүх мэдээний сэтгэгдлүүдийн жагсаалтыг үзэж байна', ['action' => 'comment-index']);
    }

    /**
     * Сэтгэгдлүүдийн жагсаалтыг JSON хэлбэрээр буцаах.
     *
     * Root comment-уудыг (parent_id IS NULL) reply тоотой хамт буцаана.
     *
     * @return void
     */
    public function list()
    {
        try {
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $newsTable = (new NewsModel($this->pdo))->getName();
            $commentsTable = (new CommentsModel($this->pdo))->getName();
            $stmt = $this->query(
                "SELECT c.id, c.news_id, c.parent_id, c.created_by, c.name, c.comment, c.created_at,
                        n.title as news_title
                 FROM $commentsTable c
                 LEFT JOIN $newsTable n ON n.id=c.news_id
                 ORDER BY c.created_at DESC"
            );
            $rows = $stmt ? $stmt->fetchAll() : [];
            $this->respondJSON(['status' => 'success', 'list' => $rows]);
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
        }
    }

    /**
     * News ID-аар тухайн мэдээний view руу comments-д focus хийж чиглүүлэх.
     *
     * @param int $id News ID
     * @return void
     */
    public function view(int $id)
    {
        $path = $this->getScriptPath();
        \header("Location: $path/dashboard/news/view/$id#comments");
        exit;
    }

    /**
     * Мэдээнд админ анхны сэтгэгдэл бичих (root comment).
     *
     * @param int $id Мэдээний ID
     * @return void
     */
    public function comment(int $id)
    {
        try {
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $newsModel = new NewsModel($this->pdo);
            $news = $newsModel->getById($id);
            if (empty($news) || empty($news['comment'])) {
                throw new \Exception($this->text('no-record-selected'), 404);
            }

            $payload = $this->getParsedBody();
            $comment = \trim($payload['comment'] ?? '');
            if (empty($comment)) {
                throw new \Exception('Comment is required', 400);
            }

            $user = $this->getUser();
            $adminName = \trim(
                ($user?->profile['first_name'] ?? '') . ' '
                . ($user?->profile['last_name'] ?? '')
            );

            $commentsModel = new CommentsModel($this->pdo);
            $commentsModel->insert([
                'news_id' => $id,
                'created_by' => $this->getUserId(),
                'name' => $adminName ?: $user->profile['username'] ?? 'Admin',
                'comment' => $comment
            ]);

            $this->respondJSON([
                'status' => 'success',
                'title' => $this->text('success'),
                'message' => $this->text('record-insert-success')
            ]);

            $this->dispatch(new \Raptor\Notification\ContentEvent(
                'insert', 'comment', $comment, $id,
                $adminName, ['news_title' => $news['title'] ?? '']
            ));

            $this->log('news', LogLevel::INFO, '{record_id} мэдээнд сэтгэгдэл бичлээ ({name})', ['action' => 'comment-insert', 'record_id' => $id, 'name' => $adminName]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status' => 'error',
                'title' => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        }
    }

    /**
     * Мэдээний сэтгэгдэлд админ хариулт бичих.
     *
     * @param int $id Эцэг comment-ийн ID
     * @return void
     */
    public function reply(int $id)
    {
        try {
            if (!$this->isUserCan('system_content_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $commentsModel = new CommentsModel($this->pdo);
            $parent = $commentsModel->getById($id);
            if (empty($parent)) {
                throw new \Exception($this->text('no-record-selected'), 404);
            }

            // 1-level reply only: reply-д reply хийхийг хориглох
            if (!empty($parent['parent_id'])) {
                throw new \Exception('Invalid request', 400);
            }

            $payload = $this->getParsedBody();
            $comment = \trim($payload['comment'] ?? '');
            if (empty($comment)) {
                throw new \Exception('Comment is required', 400);
            }

            $user = $this->getUser();
            $adminName = \trim(
                ($user?->profile['first_name'] ?? '') . ' '
                . ($user?->profile['last_name'] ?? '')
            );

            $commentsModel->insert([
                'news_id' => $parent['news_id'],
                'parent_id' => $id,
                'created_by' => $this->getUserId(),
                'name' => $adminName ?: $user->profile['username'] ?? 'Admin',
                'comment' => $comment
            ]);

            $this->respondJSON([
                'status' => 'success',
                'title' => $this->text('success'),
                'message' => $this->text('record-insert-success')
            ]);

            $this->dispatch(new \Raptor\Notification\ContentEvent(
                'insert', 'comment', "@{$parent['name']}: $comment", (int)$parent['news_id'],
                $adminName
            ));

            $this->log('news', LogLevel::INFO, '{record_id} мэдээний #{parent_id} ({name}) сэтгэгдэлд хариулт бичлээ', ['action' => 'comment-reply', 'record_id' => $parent['news_id'], 'parent_id' => $id, 'name' => $parent['name']]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status' => 'error',
                'title' => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        }
    }

    /**
     * Сэтгэгдлийг устгах.
     *
     * Reply-уудыг мөн устгана.
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

            $model = new CommentsModel($this->pdo);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'), 404);
            }

            // Reply-уудыг мөн устгах
            $table = $model->getName();
            $this->exec("DELETE FROM $table WHERE parent_id=$id");

            $model->deleteById($id);
            (new \Raptor\Trash\TrashModel($this->pdo))->store(
                'news', $model->getName(), $id, $record, $this->getUserId()
            );

            $this->respondJSON([
                'status' => 'success',
                'title' => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);

            $this->log('news', LogLevel::WARNING, '{record_id} мэдээний #{id} ({name}) сэтгэгдлийг устгалаа', ['action' => 'comment-delete', 'record_id' => $record['news_id'], 'id' => $id, 'name' => $record['name']]);

            $this->dispatch(new \Raptor\Notification\ContentEvent(
                'delete', 'comment', "#{$id} by {$record['name']}", (int)($record['news_id'] ?? 0)
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
