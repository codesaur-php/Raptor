<?php

namespace Web\Home;

use Psr\Log\LogLevel;

use Web\Template\TemplateController;

use Raptor\Content\NewsModel;
use Raptor\Content\PagesModel;
use Raptor\Content\FilesModel;

/**
 * Class HomeController
 * ========================================================================
 * Public Website Controller (Web Layer)
 * - Raptor Framework-ийн веб нүүр хуудасны үндсэн Controller.
 *
 * Энэ контроллерийн үүрэг:
 *   Нүүр хуудас (/) руу ирсэн хүсэлтийг боловсруулах
 *   Хуудасны мэдээлэл (PagesModel) үзүүлэх
 *   Мэдээ мэдээлэл (NewsModel) үзүүлэх
 *   Контакт хуудасны dynamic routing хийх
 *   Хэл солих route (`/language/{code}`)
 *   Хуудасны үзэлт (read_count) нэмэгдүүлэх
 *   Web-level action-уудыг лог бүртгэлд (log) хадгалах
 *
 * Анхаарах зүйлс:
 *   - TemplateController-г өргөтгөж template() ашиглан public UI руу рендерлэнэ.
 *   - Developer өөрийн вэб сайт дээр home, page, news гэх мэт хуудасуудыг
 *     өөриймшүүлэн сайжруулж өргөтгөх боломжтой.
 *
 * @package Web\Home
 */
class HomeController extends TemplateController
{
    /**
     * ------------------------------------------------------------
     *  Нүүр хуудас (/)
     * ------------------------------------------------------------
     * Logic:
     *   1) Хэлний кодыг авах
     *   2) Сүүлийн мэдээнүүдээс 20-г татах (id, title, slug, photo, published_at)
     *   3) home.html template-ийг рендерлэнэ
     *   4) Web layer-т зориулсан лог үлдээх
     */
    public function index()
    {
        $code = $this->getLanguageCode();
        // news хүснэгтийн нэрийг NewsModel::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
        $news_table = (new NewsModel($this->pdo))->getName();
        $stmt_recent = $this->prepare(
            "SELECT id, title, slug, photo, published_at
             FROM $news_table
             WHERE is_active=1 AND published=1 AND code=:code
             ORDER BY published_at DESC
             LIMIT 20"
        );
        $recent = $stmt_recent->execute([':code' => $code])
            ? $stmt_recent->fetchAll()
            : [];
        $vars = ['recent' => $recent];
        
        // Public layout template
        $home = $this->template(__DIR__ . '/home.html', $vars);
        $home->render();

        // Log: вебийн нүүр хуудас уншигдсан
        $this->log(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code}] Нүүр хуудсыг уншиж байна',
            ['action' => 'home']
        );
    }

    /**
     * ------------------------------------------------------------
     *  Contact хуудас
     * ------------------------------------------------------------
     * PagesModel дотор хамгийн сүүлд нийтлэгдсэн төлөвтэй "/contact" гэсэн линктэй хуудасыг олж
     * page() функцээр үзүүлнэ.
     */
    public function contact()
    {
        $pages_table = (new PagesModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT slug
             FROM $pages_table
             WHERE is_active=1 AND published=1
               AND code=:code
               AND link LIKE '%/contact'
             ORDER BY published_at DESC
             LIMIT 1"
        );
        $contact = $stmt->execute([':code' => $this->getLanguageCode()])
            ? $stmt->fetch()
            : [];
        return $this->page($contact['slug'] ?? '');
    }

    /**
     * ------------------------------------------------------------
     *  Хуудас үзүүлэх (/page/{slug})
     * ------------------------------------------------------------
     * Процесс:
     *   1) PagesModel -> тухайн slug-тай хуудас татах
     *   2) Олдохгүй бол 404 Error
     *   3) FilesModel ашиглан хавсаргасан файлуудыг татах
     *   4) page.html template рүү дамжуулж рендерлэх
     *   5) OG meta (record_code, record_title, record_description, record_photo) дамжуулах
     *   6) read_count-ыг нэмэгдүүлэх
     *   7) Үйлдлийн лог үлдээх
     *
     * @param string $slug
     * @return void
     * @throws Error
     */
    public function page(string $slug)
    {
        $model = new PagesModel($this->pdo);
        $table = $model->getName();
        $record = $model->getRowWhere([
            'slug' => $slug,
            'is_active' => 1
        ]);
        if (empty($record)) {
            throw new \Error('Хуудас олдсонгүй', 404);
        }

        $id = $record['id'];

        // Файлуудыг татах
        $files = new FilesModel($this->pdo);
        $files->setTable($table);
        $record['files'] = $files->getRows([
            'WHERE' => "record_id=$id AND is_active=1"
        ]);

        // Render page template
        $template = $this->template(__DIR__ . '/page.html', $record);
        $template->set('record_code', $record['code'] ?? '');
        $template->set('record_title', $record['title'] ?? '');
        $template->set('record_description', $record['description'] ?? '');
        $template->set('record_photo', $record['photo'] ?? '');
        $template->render();

        // Read count нэмэгдүүлэх
        $read_count = ($record['read_count'] ?? 0) + 1;
        $this->exec("UPDATE $table SET read_count=$read_count WHERE id=$id");

        // Лог
        $this->log(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code} : /page/{slug}] {title} - хуудсыг уншиж байна',
            ['action' => 'page', 'id' => $id, 'slug' => $slug, 'title' => $record['title']]
        );
    }

    /**
     * ------------------------------------------------------------
     *  Мэдээ үзүүлэх (/news/{slug})
     * ------------------------------------------------------------
     * Процесс:
     *   1) NewsModel -> тухайн slug-тай мэдээ татах
     *   2) Мэдээ байхгүй бол 404 Error
     *   3) FilesModel ашиглан хавсаргасан файлуудыг татах
     *   4) news.html template рүү дамжуулж рендерлэх
     *   5) OG meta (record_code, record_title, record_description, record_photo) дамжуулах
     *   6) read_count-ыг нэмэгдүүлэх
     *   7) Үйлдлийн лог үлдээх
     *
     * @param string $slug
     * @return void
     * @throws Error
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
            throw new \Error('Мэдээ олдсонгүй', 404);
        }

        $id = $record['id'];

        // Файлууд
        $files = new FilesModel($this->pdo);
        $files->setTable($table);
        $record['files'] = $files->getRows([
            'WHERE' => "record_id=$id AND is_active=1"
        ]);

        // Render template
        $template = $this->template(__DIR__ . '/news.html', $record);
        $template->set('record_code', $record['code'] ?? '');
        $template->set('record_title', $record['title'] ?? '');
        $template->set('record_description', $record['description'] ?? '');
        $template->set('record_photo', $record['photo'] ?? '');
        $template->render();

        // Read count
        $read_count = ($record['read_count'] ?? 0) + 1;
        $this->exec("UPDATE $table SET read_count=$read_count WHERE id=$id");

        // Лог
        $this->log(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code} : /news/{slug}] {title} - мэдээг уншиж байна',
            ['action' => 'news', 'id' => $id, 'slug' => $slug, 'title' => $record['title']]
        );
    }

    /**
     * ------------------------------------------------------------
     *  Хэл солих (/language/{code})
     * ------------------------------------------------------------
     * SESSION['WEB_LANGUAGE_CODE'] утгыг шинэчлээд нүүр рүү буцаана.
     *
     * @param string $code
     * @return void
     */
    public function language(string $code)
    {
        $from = $this->getLanguageCode();
        $language = $this->getLanguages();
        if (isset($language[$code]) && $code !== $from) {
            $_SESSION['WEB_LANGUAGE_CODE'] = $code;
        }

        $script_path = $this->getScriptPath();
        $home = (string)$this->getRequest()->getUri()->withPath($script_path);
        \header("Location: $home", false, 302);
        exit;
    }
}
