<?php

namespace Web\Home;

use Psr\Log\LogLevel;

use Web\Template\TemplateController;

use Raptor\Content\PagesModel;
use Raptor\Content\FilesModel;

class PageController extends TemplateController
{
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

    public function pageById(int $id)
    {
        $model = new PagesModel($this->pdo);
        $table = $model->getName();
        $stmt = $this->prepare("SELECT slug FROM $table WHERE id=:id AND is_active=1");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if (empty($row)) {
            throw new \Error('Хуудас олдсонгүй', 404);
        }
        return $this->page($row['slug']);
    }

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
}
