<?php

namespace Web\Content;

use Psr\Log\LogLevel;

use Raptor\Content\NewsModel;
use Raptor\Content\FilesModel;
use Raptor\Content\CommentsModel;

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
        $record = $model->getRowWhere([
            'slug' => $slug,
            'is_active' => 1
        ]);
        if (empty($record)) {
            throw new \Exception('Мэдээ олдсонгүй', 404);
        }

        $id = $record['id'];

        // Файлууд
        $files = new FilesModel($this->pdo);
        $files->setTable($table);
        $record['files'] = $files->getRows([
            'WHERE' => "record_id=$id AND is_active=1"
        ]);

        // Сэтгэгдлүүдийг татах (comment=1 үед)
        if (!empty($record['comment'])) {
            $commentsModel = new CommentsModel($this->pdo);
            $commentsTable = $commentsModel->getName();
            $cstmt = $this->prepare(
                "SELECT id, parent_id, created_by, name, comment, created_at FROM $commentsTable
                 WHERE news_id=:nid AND is_active=1 ORDER BY created_at ASC"
            );
            $record['comments'] = $cstmt->execute([':nid' => $id]) ? $cstmt->fetchAll() : [];

            $ts = \time();
            $secret = $this->getJwtSecret();
            $record['spam_ts'] = $ts;
            $record['spam_token'] = \hash_hmac('sha256', "comment-$id-$ts", $secret);
            $record['turnstile_site_key'] = $this->getTurnstileSiteKey();
        }

        // Render template
        $this->twigWebLayout(__DIR__ . '/news.html', $record)->render();

        // Read count
        $this->exec("UPDATE $table SET read_count=read_count+1 WHERE id=$id");

        // Лог
        $this->log(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code} : /news/{slug}] {title} - мэдээг уншиж байна',
            ['action' => 'news', 'id' => $id, 'slug' => $slug, 'title' => $record['title']]
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
        $stmt = $this->prepare("SELECT slug FROM $table WHERE id=:id AND is_active=1");
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
        $stmt = $this->prepare(
            "SELECT id, slug, title, description, photo, read_count, published_at
             FROM $news_table
             WHERE is_active=1 AND published=1 AND type=:type AND code=:code
             ORDER BY published_at DESC"
        );
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':code', $code);
        $records = $stmt->execute() ? $stmt->fetchAll() : [];
        if (empty($records)) {
            throw new \Exception('Мэдээ олдсонгүй', 404);
        }

        $this->twigWebLayout(__DIR__ . '/news-type.html', [
            'records' => $records,
            'type' => $type,
            'title' => $type
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

        // Жилүүдийн жагсаалт
        $stmt = $this->prepare(
            "SELECT DISTINCT YEAR(published_at) AS y FROM $news_table
             WHERE is_active=1 AND published=1 AND code=:code
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
                "SELECT id, title, slug, published_at, MONTH(published_at) AS m
                 FROM $news_table
                 WHERE is_active=1 AND published=1 AND code=:code
                   AND YEAR(published_at) = :year
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

        $this->twigWebLayout(__DIR__ . '/archive.html', [
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
            $news = $newsModel->getRowWhere(['id' => $id, 'is_active' => 1]);
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
            $this->checkLinkSpam($comment);

            $_SESSION['_last_comment_at'] = \time();

            $parentId = !empty($parsed['parent_id']) ? (int)$parsed['parent_id'] : null;
            $commentsModel = new CommentsModel($this->pdo);

            // 1-level reply only: reply-д reply хийхийг хориглох
            if ($parentId) {
                $parentComment = $commentsModel->getRowWhere(['id' => $parentId, 'is_active' => 1]);
                if (empty($parentComment) || !empty($parentComment['parent_id'])) {
                    throw new \Exception('Invalid request', 400);
                }
            }

            $commentsModel->insert([
                'news_id' => $id,
                'parent_id' => $parentId,
                'name' => $name,
                'email' => $email,
                'comment' => $comment,
                'created_at' => \date('Y-m-d H:i:s')
            ]);

            // Discord мэдэгдэл
            $appUrl = \rtrim((string)$this->getRequest()->getUri()->withPath($this->getScriptPath()), '/');
            $this->getService('discord')?->contentAction('comment', 'insert', $news['title'], $id, $name, $appUrl);

            $this->respondJSON([
                'status' => 'success',
                'message' => $code === 'mn'
                    ? 'Таны сэтгэгдэл амжилттай нэмэгдлээ!'
                    : 'Your comment has been posted successfully!'
            ]);
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
}
