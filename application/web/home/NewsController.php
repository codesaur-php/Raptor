<?php

namespace Web\Home;

use Psr\Log\LogLevel;

use Web\Template\TemplateController;

use Raptor\Content\NewsModel;
use Raptor\Content\FilesModel;

class NewsController extends TemplateController
{
    public function newsById(int $id)
    {
        $model = new NewsModel($this->pdo);
        $table = $model->getName();
        $stmt = $this->prepare("SELECT slug FROM $table WHERE id=:id AND is_active=1");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if (empty($row)) {
            throw new \Error('Мэдээ олдсонгүй', 404);
        }
        return $this->news($row['slug']);
    }

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
            throw new \Error('Мэдээ олдсонгүй', 404);
        }

        $template = $this->template(__DIR__ . '/news-type.html', [
            'records' => $records,
            'type' => $type
        ]);
        $template->set('record_title', $type);
        $template->render();

        $this->log(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code} : /news/type/{type}] Мэдээнүүдийн жагсаалтыг нээж байна',
            ['action' => 'news-type', 'type' => $type]
        );
    }

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

        $template = $this->template(__DIR__ . '/archive.html', [
            'years' => $years,
            'selected_year' => $selectedYear,
            'months' => $months,
        ]);
        $template->set('record_title', $this->text('news-archive'));
        $template->render();
    }
}
