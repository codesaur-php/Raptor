<?php

namespace Web\Content;

use Psr\Log\LogLevel;

use codesaur\DataObject\Constants;
use codesaur\Template\MemoryTemplate;

use Raptor\Content\NewsModel;
use Raptor\Content\FilesModel;
use Raptor\Content\CommentsModel;
use Raptor\Content\ReadNewsTrait;

use Web\Template\TemplateController;

/**
 * Class NewsController
 * ---------------------------------------------------------------
 * Вэб сайтын мэдээний хуудсуудыг харуулах контроллер.
 *
 * Энэ контроллер нь:
 *   - Мэдээг slug эсвэл ID-аар харуулах
 *   - Мэдээний төрлөөр жагсаалт харуулах (newsType)
 *   - Мэдээний архив (жил, сараар бүлэглэсэн) харуулах
 *   - Уншсан тоолуурыг (read_count) нэмэгдүүлэх
 *   - Хавсаргасан файлуудыг хамт харуулах
 *
 * @package Web\Content
 */
class NewsController extends TemplateController
{
    use \Raptor\SpamProtectionTrait;
    use ReadNewsTrait;
    /**
     * Slug-аар мэдээг харуулах.
     *
     * Мэдээний бүрэн мэдээлэл, хавсаргасан файлуудыг авч,
     * news.html template-ээр рендерлэнэ. Уншсан тоог нэмэгдүүлнэ.
     *
     * @param string $slug Мэдээний slug
     * @return void
     * @throws \Error Мэдээ олдохгүй бол 404 алдаа шидэнэ
     */
    public function news(string $slug)
    {
        $model = new NewsModel($this->pdo);
        $table = $model->getName();
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT n.*, " .
            "CONCAT(c.first_name, ' ', c.last_name) as creator_name, " .
            "CONCAT(u.first_name, ' ', u.last_name) as updater_name " .
            "FROM $table n " .
            "LEFT JOIN $users c ON n.created_by = c.id " .
            "LEFT JOIN $users u ON n.updated_by = u.id " .
            "WHERE n.slug = :slug LIMIT 1"
        );
        $stmt->bindValue(':slug', $slug);
        $stmt->execute();
        $record = $stmt->fetch();
        if (empty($record)) {
            throw new \Exception('Мэдээ олдсонгүй', 404);
        }

        // Үг тоолох ба уншихад шаардлагатай хугацаа
        $plainText = \strip_tags($record['content'] ?? '');
        $record['word_count'] = \preg_match_all('/[\p{L}\p{N}]+/u', $plainText);
        $record['read_time'] = \max(1, (int) \ceil($record['word_count'] / 200));

        $id = $record['id'];

        // Файлууд
        $files = new FilesModel($this->pdo);
        $files->setTable($table);
        $record['files'] = $files->getRows([
            'WHERE' => "record_id=$id"
        ]);

        // Сэтгэгдлүүдийг татах (comment=1 үед)
        if (!empty($record['comment'])) {
            $commentsModel = new CommentsModel($this->pdo);
            $commentsTable = $commentsModel->getName();
            $cstmt = $this->prepare(
                "SELECT id, parent_id, created_by, name, comment, created_at FROM $commentsTable
                 WHERE news_id=:nid ORDER BY created_at ASC"
            );
            $record['comments'] = $cstmt->execute([':nid' => $id]) ? $cstmt->fetchAll() : [];

            $ts = \time();
            $secret = $this->getJwtSecret();
            $record['spam_ts'] = $ts;
            $record['spam_token'] = \hash_hmac('sha256', "comment-$id-$ts", $secret);
            $record['turnstile_site_key'] = $this->getTurnstileSiteKey();
        }

        // Cookie: мэдээг уншсан гэж тэмдэглэе
        $this->markNewsAsRead((int)$id);

        // Render template
        $this->webTemplate(__DIR__ . '/news.html', $record)->render();

        // Read count
        $this->exec("UPDATE $table SET read_count=read_count+1 WHERE id=$id");

        // Лог
        $this->log(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code} : /news/{slug}] {title} - мэдээг уншиж байна',
            ['action' => 'news', 'record_id' => $id, 'slug' => $slug, 'title' => $record['title']]
        );
    }    
    
    /**
     * ID-аар мэдээ хайж slug-аар чиглүүлэх.
     *
     * @param int $id Мэдээний ID дугаар
     * @return void
     * @throws \Error Мэдээ олдохгүй бол 404 алдаа шидэнэ
     */
    public function newsById(int $id)
    {
        $model = new NewsModel($this->pdo);
        $table = $model->getName();
        $stmt = $this->prepare("SELECT slug FROM $table WHERE id=:id");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if (empty($row)) {
            throw new \Exception('Мэдээ олдсонгүй', 404);
        }
        return $this->news($row['slug']);
    }

    /**
     * Мэдээний төрлөөр жагсаалт харуулах.
     *
     * Тухайн төрлийн нийтлэгдсэн бүх мэдээг огноогоор буурахаар
     * эрэмбэлж харуулна.
     *
     * @param string $type Мэдээний төрөл
     * @return void
     * @throws \Error Мэдээ олдохгүй бол 404 алдаа шидэнэ
     */
    public function newsType(string $type)
    {
        $code = $this->getLanguageCode();
        $news_table = (new NewsModel($this->pdo))->getName();

        // Ангилалуудын жагсаалт (sidebar-д ашиглана)
        $typeStmt = $this->prepare(
            "SELECT DISTINCT type FROM $news_table
             WHERE published=1 AND code=:code AND type != ''
             ORDER BY type ASC"
        );
        $typeStmt->bindValue(':code', $code);
        $categories = $typeStmt->execute() ? $typeStmt->fetchAll(\PDO::FETCH_COLUMN) : [];

        // Мэдээний жагсаалт - бүгд эсвэл ангилалаар
        if ($type === 'all') {
            $stmt = $this->prepare(
                "SELECT id, slug, title, description, photo, type, read_count, published_at
                 FROM $news_table
                 WHERE published=1 AND code=:code
                 ORDER BY published_at DESC"
            );
            $stmt->bindValue(':code', $code);
        } else {
            $stmt = $this->prepare(
                "SELECT id, slug, title, description, photo, type, read_count, published_at
                 FROM $news_table
                 WHERE published=1 AND type=:type AND code=:code
                 ORDER BY published_at DESC"
            );
            $stmt->bindValue(':type', $type);
            $stmt->bindValue(':code', $code);
        }
        $records = $stmt->execute() ? $stmt->fetchAll() : [];

        if (empty($records) && $type !== 'all') {
            throw new \Exception($this->text('no-news-found'), 404);
        }

        $this->decorateReadNews($records);

        $this->webTemplate(__DIR__ . '/news-type.html', [
            'records'    => $records,
            'type'       => $type,
            'categories' => $categories,
            'title'      => $this->text('news')
        ])->render();

        $this->log(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code} : /news/type/{type}] Мэдээнүүдийн жагсаалтыг нээж байна',
            ['action' => 'news-type', 'type' => $type]
        );
    }

    /**
     * Мэдээний архив хуудсыг харуулах.
     *
     * Жилүүдийн жагсаалтыг харуулж, сонгосон жилийн мэдээнүүдийг
     * сараар бүлэглэж харуулна.
     *
     * @return void
     */
    public function archive()
    {
        $code = $this->getLanguageCode();
        $news_table = (new NewsModel($this->pdo))->getName();
        $selectedYear = $this->getQueryParams()['year'] ?? null;

        $isPg = $this->getDriverName() === Constants::DRIVER_PGSQL;
        $yearExpr  = $isPg ? "EXTRACT(YEAR FROM published_at)::int"  : "YEAR(published_at)";
        $monthExpr = $isPg ? "EXTRACT(MONTH FROM published_at)::int" : "MONTH(published_at)";

        // Жилүүдийн жагсаалт
        $stmt = $this->prepare(
            "SELECT DISTINCT $yearExpr AS y FROM $news_table
             WHERE published=1 AND code=:code
             ORDER BY y DESC"
        );
        $years = $stmt->execute([':code' => $code]) ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];

        // Сонгосон жилийн мэдээнүүд (сараар бүлэглэсэн)
        if (empty($selectedYear) && !empty($years)) {
            $selectedYear = $years[0];
        }

        $months = [];
        if ($selectedYear) {
            $stmt = $this->prepare(
                "SELECT id, title, slug, published_at, $monthExpr AS m
                 FROM $news_table
                 WHERE published=1 AND code=:code
                   AND $yearExpr = :year
                 ORDER BY published_at DESC"
            );
            $stmt->bindValue(':code', $code);
            $stmt->bindValue(':year', (int)$selectedYear, \PDO::PARAM_INT);
            if ($stmt->execute()) {
                while ($row = $stmt->fetch()) {
                    $months[(int)$row['m']][] = $row;
                }
            }
        }

        $this->webTemplate(__DIR__ . '/archive.html', [
            'years' => $years,
            'selected_year' => $selectedYear,
            'months' => $months,
            'title' => $this->text('news-archive')
        ])->render();

        $this->log(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code}] Мэдээний архив уншиж байна - {year} он',
            ['action' => 'news-archive', 'year' => $selectedYear ?? '-']
        );
    }

    /**
     * Мэдээнд сэтгэгдэл бичих (AJAX).
     *
     * Spam хамгаалалт: honeypot, HMAC token, timestamp, rate limit, Cloudflare Turnstile.
     * auth_user-г id-гүйгээр log context-д нэмнэ (badge системд
     * web frontend хэрэглэгчийг admin-аас ялгах зорилготой).
     *
     * @param int $id Мэдээний ID
     * @return void
     */
    public function commentSubmit(int $id)
    {
        try {
            $parsed = $this->getParsedBody();
            $code = $this->getLanguageCode();

            // Мэдээ байгаа эсэх, comment идэвхтэй эсэх шалгах
            $newsModel = new NewsModel($this->pdo);
            $news = $newsModel->getById($id);
            if (empty($news) || empty($news['comment'])) {
                throw new \Exception('Invalid request', 400);
            }

            $this->validateSpamProtection($parsed, "comment-$id", '_last_comment_at', 5, 2);

            $name = \trim($parsed['name'] ?? '');
            $email = \trim($parsed['email'] ?? '');
            $comment = \trim($parsed['comment'] ?? '');

            if (empty($name)) {
                throw new \InvalidArgumentException($code === 'mn' ? 'Нэрээ оруулна уу' : 'Please enter your name');
            }
            if (empty($comment)) {
                throw new \InvalidArgumentException($code === 'mn' ? 'Сэтгэгдлээ бичнэ үү' : 'Please enter your comment');
            }
            if (!empty($email) && !\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException($code === 'mn' ? 'Зөв имэйл хаяг оруулна уу' : 'Please enter a valid email address');
            }
            $this->checkLinkSpam($comment);

            $_SESSION['_last_comment_at'] = \time();

            $parentId = !empty($parsed['parent_id']) ? (int)$parsed['parent_id'] : null;
            $commentsModel = new CommentsModel($this->pdo);

            // 1-level reply only: reply-д reply хийхийг хориглох
            if ($parentId) {
                $parentComment = $commentsModel->getById($parentId);
                if (empty($parentComment) || !empty($parentComment['parent_id'])) {
                    throw new \Exception('Invalid request', 400);
                }
            }

            $commentsModel->insert([
                'news_id' => $id,
                'parent_id' => $parentId,
                'name' => $name,
                'email' => $email,
                'comment' => $comment
            ]);

            $this->respondJSON([
                'status' => 'success',
                'message' => $code === 'mn'
                    ? 'Таны сэтгэгдэл амжилттай нэмэгдлээ!'
                    : 'Your comment has been posted successfully!'
            ]);

            $this->log('news', LogLevel::INFO, '{record_id} мэдээнд сэтгэгдэл бичлээ', [
                'action' => 'comment-insert',
                'record_id' => $id,
                'auth_user' => [
                    'username' => $name,
                    'first_name' => $name,
                    'last_name' => '',
                    'phone' => '',
                    'email' => $email
                ]
            ]);

            $this->dispatch(new \Raptor\Notification\ContentEvent(
                'insert', 'comment', $comment, $id,
                $name, ['news_title' => $news['title'] ?? '']
            ));
            
            // Админд email мэдэгдэл
            $this->sendCommentNotifyEmail($name, $email, $comment, $news['title']);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode() ?: 500);
        }
    }

    /**
     * JWT secret авах.
     *
     * @return string JWT secret
     * @throws \RuntimeException Environment variable тохируулаагүй бол
     */
    private function getJwtSecret(): string
    {
        $secret = $_ENV['RAPTOR_JWT_SECRET'] ?? '';
        if (empty($secret)) {
            throw new \RuntimeException('RAPTOR_JWT_SECRET environment variable is not set');
        }
        return $secret;
    }

    /**
     * Шинэ сэтгэгдэл ирсэн тухай админд email мэдэгдэл.
     */
    private function sendCommentNotifyEmail(
        string $name,
        string $email,
        string $comment,
        string $newsTitle
    ) {
        try {
            $notifyEmail = $_ENV['RAPTOR_COMMENT_EMAIL_TO'] ?? '';
            if (empty($notifyEmail)) {
                return;
            }

            $mailer = $this->getService('mailer');
            if (empty($mailer)) {
                return;
            }

            $code = $this->getLanguageCode() ?: 'en';
            $templateService = $this->getService('template_service');
            $template = $templateService?->getByKeyword('comment-notify', $code);
            if (empty($template)) {
                return;
            }

            $appUrl = \rtrim((string)$this->getRequest()->getUri()->withPath($this->getScriptPath()), '/');
            $commentsLink = $appUrl . '/dashboard/news/comments';

            $subjectTemplate = new MemoryTemplate();
            $subjectTemplate->source($template['title']);
            $subjectTemplate->set('news_title', $newsTitle);
            $subject = $subjectTemplate->output();

            $bodyTemplate = new MemoryTemplate();
            $bodyTemplate->source($template['content']);
            $bodyTemplate->set('name', \htmlspecialchars($name));
            $bodyTemplate->set('email', \htmlspecialchars($email));
            $bodyTemplate->set('comment', \nl2br(\htmlspecialchars($comment)));
            $bodyTemplate->set('news_title', \htmlspecialchars($newsTitle));
            $bodyTemplate->set('comments_link', $commentsLink);
            $body = $bodyTemplate->output();

            $mailer->mail($notifyEmail, null, $subject, $body);
            if (!empty($email)) {
                $mailer->setReplyTo($email, $name);
            }
            $mailer->send();
        } catch (\Throwable $e) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log("CommentNotifyEmail: {$e->getMessage()}");
            }
        }
    }
}
