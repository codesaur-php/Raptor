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
    /**
     * ProductsModel constructor.
     *
     * PDO instance-г оноож, бүтээгдэхүүний хүснэгтийн бүх багануудыг тодорхойлно.
     * Хүснэгтийн нэрийг 'products' гэж тохируулна.
     *
     * @param \PDO $pdo Database connection instance
     */
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
           (new Column('review', 'tinyint'))->default(1),
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

    /**
     * Анхны тохиргоо (initial setup).
     *
     * Хүснэгт анх үүсэх үед foreign key constraint-уудыг автоматаар үүсгэнэ.
     * Мөн жишиг (sample) бүтээгдэхүүнүүдийг MN/EN хэл дээр үүсгэнэ.
     *
     * @return void
     */
    protected function __initial()
    {
        $table = $this->getName();

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

        // Хайлт, шүүлтийн гүйцэтгэлийг сайжруулах индексүүд
        $this->exec("CREATE INDEX {$table}_idx_active_published ON $table (is_active, published)");
        $this->exec("CREATE INDEX {$table}_idx_code_active_published ON $table (code, is_active, published, published_at)");

        ProductsSamples::seed($this);
    }

    /**
     * Шинэ бүтээгдэхүүн үүсгэх.
     *
     * Бүтээгдэхүүний бичлэг үүсгэх үед created_at болон slug талбаруудыг
     * автоматаар бөглөнө (хэрэв өгөгдөөгүй бол).
     *
     * @param array $record Бүтээгдэхүүний мэдээлэл
     * @return array|false Амжилттай бол үүссэн бичлэгийн массив, бусад тохиолдолд false
     */
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

    /**
     * Гарчигаас SEO-friendly slug үүсгэх.
     *
     * Кирилл үсгийг латин руу хөрвүүлж, тусгай тэмдэгтүүдийг
     * хасаж, зөвхөн үсэг, тоо, зураас үлдээнэ.
     * Давхардсан slug байвал дугаар нэмнэ (жишээ: my-slug-2).
     *
     * @param string $title Бүтээгдэхүүний гарчиг
     * @return string SEO-friendly slug
     */
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

    /**
     * Slug-аар бүтээгдэхүүн хайх.
     *
     * @param string $slug Бүтээгдэхүүний slug
     * @return array|null Бүтээгдэхүүн эсвэл null
     */
    public function getBySlug(string $slug): array|null
    {
        return $this->getRowWhere(['slug' => $slug]);
    }

    /**
     * Content-оос товч тайлбар (excerpt) үүсгэх.
     *
     * Block tag (p, div, li, ...) хаалтын ард зай нэмж, текст наалдахаас сэргийлнэ.
     * HTML tag-уудыг хасаж, эхний $length тэмдэгтийг буцаана.
     *
     * @param string $content Бүтээгдэхүүний агуулга (HTML).
     * @param int $length Хамгийн их тэмдэгтийн урт (анхдагч: 200).
     * @return string Товчилсон текст. Хэтэрвэл `...` залгана.
     */
    public function getExcerpt(string $content, int $length = 200): string
    {
        // Block tag хаалтын өмнө зай нэмж, текст наалдахаас сэргийлэх
        $text = \preg_replace('/<\/(p|div|br|li|h[1-6]|blockquote|tr)>/i', '</$1> ', $content);
        $text = \strip_tags($text);
        $text = \preg_replace('/\s+/', ' ', $text);
        $text = \trim($text);

        if (\mb_strlen($text) <= $length) {
            return $text;
        }

        return \mb_substr($text, 0, $length) . '...';
    }
}
