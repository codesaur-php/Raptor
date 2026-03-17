<?php

namespace Web\Content;

use Psr\Log\LogLevel;

use Raptor\Content\PagesModel;
use Raptor\Content\FilesModel;

use Web\Template\TemplateController;

/**
 * Class PageController
 * ---------------------------------------------------------------
 * Вэб сайтын динамик хуудсуудыг харуулах контроллер.
 *
 * Энэ контроллер нь:
 *   - Хуудсыг slug-аар харуулах (page)
 *   - Хуудсыг ID-аар хайж slug руу чиглүүлэх (pageById)
 *   - Уншсан тоолуурыг (read_count) нэмэгдүүлэх
 *   - Хавсаргасан файлуудыг хамт харуулах
 *
 * @package Web\Content
 */
class PageController extends TemplateController
{
    /**
     * Slug-аар хуудсыг харуулах.
     *
     * Хуудсын бүрэн мэдээлэл, хавсаргасан файлуудыг авч,
     * page.html template-ээр рендерлэнэ. Уншсан тоог нэмэгдүүлнэ.
     *
     * @param string $slug Хуудасны slug
     * @return void
     * @throws \Error Хуудас олдохгүй бол 404 алдаа шидэнэ
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
            throw new \Exception('Хуудас олдсонгүй', 404);
        }

        $id = $record['id'];

        // Файлуудыг татах
        $files = new FilesModel($this->pdo);
        $files->setTable($table);
        $record['files'] = $files->getRows([
            'WHERE' => "record_id=$id AND is_active=1"
        ]);

        // Render page template
        $this->twigWebLayout(__DIR__ . '/page.html', $record)->render();

        // Read count нэмэгдүүлэх
        $this->exec("UPDATE $table SET read_count=read_count+1 WHERE id=$id");

        // Лог
        $this->log(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code} : /page/{slug}] {title} - хуудсыг уншиж байна',
            ['action' => 'page', 'id' => $id, 'slug' => $slug, 'title' => $record['title']]
        );
    }
    
    /**
     * ID-аар хуудас хайж slug-аар чиглүүлэх.
     *
     * @param int $id Хуудасны ID дугаар
     * @return void
     * @throws \Error Хуудас олдохгүй бол 404 алдаа шидэнэ
     */
    public function pageById(int $id)
    {
        $model = new PagesModel($this->pdo);
        $table = $model->getName();
        $stmt = $this->prepare("SELECT slug FROM $table WHERE id=:id AND is_active=1");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if (empty($row)) {
            throw new \Exception('Хуудас олдсонгүй', 404);
        }
        return $this->page($row['slug']);
    }
}
