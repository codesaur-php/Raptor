<?php

namespace Dashboard\Shop;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Class ProductsModel
 *
 * Бүтээгдэхүүний (`products`) хүснэгттэй ажиллах өгөгдлийн загвар (Model) класс.
 *
 * @package Dashboard\Shop
 */
class ProductsModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('slug', 'varchar', 255))->unique(),
            new Column('title', 'varchar', 255),
            new Column('description', 'varchar', 255),
            new Column('content', 'mediumtext'),
           (new Column('price', 'decimal', '12,2'))->default(0),
            new Column('sale_price', 'decimal', '12,2'),
            new Column('sku', 'varchar', 64),
            new Column('barcode', 'varchar', 64),
            new Column('sizes', 'text'),
            new Column('colors', 'text'),
           (new Column('stock', 'int'))->default(0),
            new Column('link', 'varchar', 255),
            new Column('photo', 'varchar', 255),
            new Column('code', 'varchar', 2),
           (new Column('type', 'varchar', 32))->default('product'),
           (new Column('category', 'varchar', 32))->default('general'),
           (new Column('is_featured', 'tinyint'))->default(0),
           (new Column('comment', 'tinyint'))->default(1),
           (new Column('read_count', 'bigint'))->default(0),
           (new Column('is_active', 'tinyint'))->default(1),
           (new Column('published', 'tinyint'))->default(0),
            new Column('published_at', 'datetime'),
            new Column('published_by', 'bigint'),
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);

        $this->setTable('products');
    }

    protected function __initial()
    {
        $table = $this->getName();

        if ($this->getDriverName() != 'sqlite') {
            $this->setForeignKeyChecks(false);

            $users = (new \Raptor\User\UsersModel($this->pdo))->getName();

            $constraints = [
                'published_by' => "{$table}_fk_published_by",
                'created_by'   => "{$table}_fk_created_by",
                'updated_by'   => "{$table}_fk_updated_by"
            ];

            foreach ($constraints as $column => $constraint) {
                $this->exec(
                    "ALTER TABLE $table " .
                    "ADD CONSTRAINT $constraint " .
                    "FOREIGN KEY ($column) " .
                    "REFERENCES $users(id) " .
                    "ON DELETE SET NULL " .
                    "ON UPDATE CASCADE"
                );
            }

            $this->setForeignKeyChecks(true);
        }

        $this->exec("CREATE INDEX {$table}_idx_active_published ON $table (is_active, published)");

        $now = \date('Y-m-d H:i:s');
        $path = \dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        if ($path == '\\' || $path == '/' || $path == '.') {
            $path = '';
        }
        $seed = [
            'is_active' => 1,
            'published' => 1,
            'created_at' => $now,
            'published_at' => $now,
            'category' => 'sample'
        ];

        $this->insert($seed + [
            'code' => 'mn',
            'title' => 'Raptor Framework',
            'photo' => $path . '/assets/images/codesaur_repo.jpg',
            'price' => 0,
            'sku' => 'RPT-FW-001',
            'content' => '<p><a href="https://github.com/codesaur-php/Raptor" target="_blank">Raptor Framework</a> нь '
                . 'PHP дээр суурилсан орчин үеийн вэб хөгжүүлэлтийн framework юм.</p>'
                . '<p>PSR стандартуудыг бүрэн дэмждэг, MVC архитектуртай, '
                . 'олон хэлний дэмжлэгтэй контент удирдлагын систем.</p>'
        ]);
        $this->insert($seed + [
            'code' => 'mn',
            'title' => 'Вэб сайт хөгжүүлэлт',
            'price' => 1500000,
            'sku' => 'WEB-DEV-001',
            'content' => '<p>Мэргэжлийн вэб сайт хөгжүүлэлтийн үйлчилгээ. '
                . 'Таны бизнест тохирсон вэб сайтыг захиалгаар хөгжүүлж өгнө.</p>'
                . '<p>Responsive дизайн, SEO оновчлол, контент удирдлагын систем зэрэг '
                . 'бүх шаардлагатай боломжуудыг багтаасан.</p>'
        ]);

        $this->insert($seed + [
            'code' => 'en',
            'title' => 'Raptor Framework',
            'photo' => $path . '/assets/images/codesaur_repo.jpg',
            'price' => 0,
            'sku' => 'RPT-FW-001',
            'content' => '<p><a href="https://github.com/codesaur-php/Raptor" target="_blank">Raptor Framework</a> is '
                . 'a modern web development framework built on PHP.</p>'
                . '<p>Fully PSR-compliant, MVC architecture, '
                . 'with multilingual content management system.</p>'
        ]);
        $this->insert($seed + [
            'code' => 'en',
            'title' => 'Web Development',
            'price' => 1500000,
            'sku' => 'WEB-DEV-001',
            'content' => '<p>Professional web development service. '
                . 'We build custom websites tailored to your business needs.</p>'
                . '<p>Responsive design, SEO optimization, content management system '
                . 'and all essential features included.</p>'
        ]);
    }

    public function insert(array $record): array|false
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');

        if (empty($record['slug']) && !empty($record['title'])) {
            $record['slug'] = $this->generateSlug($record['title']);
        }

        $desc = \trim($record['description'] ?? '');
        if ($desc === '' && !empty($record['content'])) {
            $record['description'] = $this->getExcerpt($record['content']);
        } else {
            $record['description'] = $desc;
        }

        return parent::insert($record);
    }

    public function generateSlug(string $title): string
    {
        $mongolian = [
            'а'=>'a', 'б'=>'b', 'в'=>'v', 'г'=>'g', 'д'=>'d', 'е'=>'e', 'ё'=>'yo',
            'ж'=>'j', 'з'=>'z', 'и'=>'i', 'й'=>'i', 'к'=>'k', 'л'=>'l', 'м'=>'m',
            'н'=>'n', 'о'=>'o', 'ө'=>'u', 'п'=>'p', 'р'=>'r', 'с'=>'s', 'т'=>'t',
            'у'=>'u', 'ү'=>'u', 'ф'=>'f', 'х'=>'kh', 'ц'=>'ts', 'ч'=>'ch', 'ш'=>'sh',
            'щ'=>'sh', 'ъ'=>'i', 'ы'=>'y', 'ь'=>'i', 'э'=>'e', 'ю'=>'yu', 'я'=>'ya',
            'А'=>'A', 'Б'=>'B', 'В'=>'V', 'Г'=>'G', 'Д'=>'D', 'Е'=>'E', 'Ё'=>'Yo',
            'Ж'=>'J', 'З'=>'Z', 'И'=>'I', 'Й'=>'I', 'К'=>'K', 'Л'=>'L', 'М'=>'M',
            'Н'=>'N', 'О'=>'O', 'Ө'=>'U', 'П'=>'P', 'Р'=>'R', 'С'=>'S', 'Т'=>'T',
            'У'=>'U', 'Ү'=>'U', 'Ф'=>'F', 'Х'=>'Kh', 'Ц'=>'Ts', 'Ч'=>'Ch', 'Ш'=>'Sh',
            'Щ'=>'Sh', 'Ъ'=>'I', 'Ы'=>'Y', 'Ь'=>'I', 'Э'=>'E', 'Ю'=>'Yu', 'Я'=>'Ya'
        ];
        $slug = \strtr($title, $mongolian);

        if (\preg_match('/[^\x00-\x7F]/', $slug)
            && \function_exists('transliterator_transliterate')
        ) {
            $slug = \transliterator_transliterate('Any-Latin; Latin-ASCII', $slug);
        }
        $slug = \mb_strtolower($slug);
        $slug = \preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = \trim($slug, '-');

        $original = $slug;
        $count = 1;
        while ($this->getBySlug($slug)) {
            $slug = $original . '-' . $count++;
        }

        return $slug;
    }

    public function getBySlug(string $slug): array|null
    {
        return $this->getRowWhere(['slug' => $slug]);
    }

    public function getExcerpt(string $content, int $length = 200): string
    {
        $text = \strip_tags($content);
        $text = \trim($text);

        if (\mb_strlen($text) <= $length) {
            return $text;
        }

        return \mb_substr($text, 0, $length) . '...';
    }
}
