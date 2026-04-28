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
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT p.*, " .
            "CONCAT(c.first_name, ' ', c.last_name) as creator_name, " .
            "CONCAT(pb.first_name, ' ', pb.last_name) as publisher_name " .
            "FROM $table p " .
            "LEFT JOIN $users c ON p.created_by = c.id " .
            "LEFT JOIN $users pb ON p.published_by = pb.id " .
            "WHERE p.slug = :slug LIMIT 1"
        );
        $stmt->bindValue(':slug', $slug);
        $stmt->execute();
        $record = $stmt->fetch();
        if (empty($record)) {
            throw new \Exception('Хуудас олдсонгүй', 404);
        }

        $id = $record['id'];

        // Үг тоолох ба уншихад шаардлагатай хугацаа
        $plainText = \strip_tags($record['content'] ?? '');
        $record['word_count'] = \preg_match_all('/[\p{L}\p{N}]+/u', $plainText);
        $record['read_time'] = \max(1, (int) \ceil($record['word_count'] / 200));

        // Siblings (ижил parent_id-тэй хуудсууд) + parent title
        $parentId = (int) ($record['parent_id'] ?? 0);
        if ($parentId > 0) {
            $parentStmt = $this->prepare("SELECT title FROM $table WHERE id = :id LIMIT 1");
            $parentStmt->bindValue(':id', $parentId, \PDO::PARAM_INT);
            $parentStmt->execute();
            $parent = $parentStmt->fetch();
            $record['parent_title'] = $parent['title'] ?? '';

            $sibStmt = $this->prepare(
                "SELECT id, slug, title FROM $table " .
                "WHERE parent_id = :pid AND published = 1 AND code = :code " .
                "ORDER BY position ASC"
            );
            $sibStmt->bindValue(':pid', $parentId, \PDO::PARAM_INT);
            $sibStmt->bindValue(':code', $record['code']);
            $record['siblings'] = $sibStmt->execute() ? $sibStmt->fetchAll() : [];
        }

        // Файлуудыг татах
        $files = new FilesModel($this->pdo);
        $files->setTable($table);
        $record['files'] = $files->getRows([
            'WHERE' => "record_id=$id"
        ]);

        // Render page template
        $this->webTemplate(__DIR__ . '/page.html', $record)->render();

        // Read count нэмэгдүүлэх
        $this->exec("UPDATE $table SET read_count=read_count+1 WHERE id=$id");

        // Лог
        $this->log(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code} : /page/{slug}] {title} - хуудсыг уншиж байна',
            ['action' => 'page', 'record_id' => $id, 'slug' => $slug, 'title' => $record['title']]
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
        $stmt = $this->prepare("SELECT slug FROM $table WHERE id=:id");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if (empty($row)) {
            throw new \Exception('Хуудас олдсонгүй', 404);
        }
        return $this->page($row['slug']);
    }
}
