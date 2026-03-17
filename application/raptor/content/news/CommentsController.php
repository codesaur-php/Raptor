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

        $dashboard = $this->twigDashboard(__DIR__ . '/comments-index.html');
        $dashboard->set('title', $this->text('comments'));
        $dashboard->render();

        $this->log('news_comments', LogLevel::NOTICE, 'Сэтгэгдлүүдийн жагсаалтыг үзэж байна', ['action' => 'index']);
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

            $commentsTable = (new CommentsModel($this->pdo))->getName();
            $newsTable = (new NewsModel($this->pdo))->getName();

            $stmt = $this->query(
                "SELECT c.id, c.news_id, c.parent_id, c.created_by, c.name, c.comment, c.created_at,
                        n.title as news_title
                 FROM $commentsTable c
                 LEFT JOIN $newsTable n ON n.id=c.news_id
                 WHERE c.is_active=1
                 ORDER BY c.created_at DESC"
            );
            $rows = $stmt ? $stmt->fetchAll() : [];

            $this->respondJSON(['status' => 'success', 'list' => $rows]);
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
        }
    }

    /**
     * News ID-аар тухайн мэдээний сэтгэгдлүүдийг харуулах.
     * ?comment_id= query param байвал тухайн comment focus-д байна.
     *
     * @param int $id News ID
     * @return void
     */
    public function view(int $id)
    {
        try {
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $focusId = (int)($this->getQueryParams()['comment_id'] ?? 0) ?: null;
            $this->renderCommentsView($id, $focusId);
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        }
    }

    /**
     * Мэдээний сэтгэгдлүүдийг comments-view template-ээр render хийх.
     *
     * @param int      $newsId         News ID
     * @param int|null $focusCommentId Focus хийх comment ID
     * @return void
     */
    private function renderCommentsView(int $newsId, ?int $focusCommentId = null): void
    {
        $newsModel = new NewsModel($this->pdo);
        $newsTable = $newsModel->getName();
        $record = $newsModel->getRowWhere(['id' => $newsId, 'is_active' => 1]);
        if (empty($record)) {
            throw new \Exception($this->text('no-record-selected'), 404);
        }

        // Нийтлэсэн хэрэглэгчийн нэрийг авах
        if (!empty($record['published_by'])) {
            $usersTable = (new \Raptor\User\UsersModel($this->pdo))->getName();
            $ustmt = $this->prepare("SELECT username FROM $usersTable WHERE id=:uid");
            if ($ustmt->execute([':uid' => $record['published_by']])) {
                $record['publisher_name'] = $ustmt->fetchColumn() ?: null;
            }
        }

        $commentsModel = new CommentsModel($this->pdo);
        $commentsTable = $commentsModel->getName();
        $cstmt = $this->prepare(
            "SELECT id, parent_id, created_by, name, email, comment, created_at FROM $commentsTable
             WHERE news_id=:nid AND is_active=1 ORDER BY created_at ASC"
        );
        $comments = $cstmt->execute([':nid' => $newsId]) ? $cstmt->fetchAll() : [];

        $dashboard = $this->twigDashboard(
            __DIR__ . '/comments-view.html',
            [
                'table' => $newsTable,
                'record' => $record,
                'comments' => $comments,
                'focus_comment_id' => $focusCommentId
            ]
        );
        $dashboard->set('title', $this->text('comments') . ' | ' . $record['title']);
        $dashboard->render();
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
            $commentsTable = $commentsModel->getName();
            $parent = $commentsModel->getRowWhere(['id' => $id, 'is_active' => 1]);
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
            $adminName = \trim(($user->profile['first_name'] ?? '') . ' ' . ($user->profile['last_name'] ?? ''));

            $commentsModel->insert([
                'news_id' => $parent['news_id'],
                'parent_id' => $id,
                'created_by' => $this->getUserId(),
                'name' => $adminName ?: $user->profile['username'] ?? 'Admin',
                'comment' => $comment,
                'created_at' => \date('Y-m-d H:i:s')
            ]);

            $this->respondJSON([
                'status' => 'success',
                'title' => $this->text('success'),
                'message' => $this->text('record-insert-success')
            ]);

            // Discord мэдэгдэл
            $appUrl = \rtrim((string)$this->getRequest()->getUri()->withPath($this->getScriptPath()), '/') . '/dashboard';
            $this->getService('discord')?->contentAction('comment', 'insert', "@{$parent['name']}: $comment", (int)$parent['news_id'], $adminName, $appUrl);

            $this->log($commentsTable, LogLevel::INFO, "#{$id} сэтгэгдэлд хариулт бичлээ", ['action' => 'comment-reply', 'parent_id' => $id]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status' => 'error',
                'title' => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        }
    }

    /**
     * Сэтгэгдлийг идэвхгүй болгох (soft delete).
     *
     * @return void
     */
    public function deactivate()
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
            $table = $model->getName();
            $record = $model->getRowWhere(['id' => $id]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'), 404);
            }

            $this->exec("UPDATE $table SET is_active=0 WHERE id=$id");
            // Reply-уудыг мөн идэвхгүй болгох
            $this->exec("UPDATE $table SET is_active=0 WHERE parent_id=$id");

            $this->respondJSON([
                'status' => 'success',
                'title' => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);

            $this->log($table, LogLevel::WARNING, "#{$id} сэтгэгдлийг устгалаа", ['action' => 'deactivate', 'id' => $id]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status' => 'error',
                'title' => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        }
    }
}
