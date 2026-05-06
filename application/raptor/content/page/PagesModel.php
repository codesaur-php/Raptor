<?php

namespace Raptor\Content;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;
use codesaur\DataObject\Constants;

/**
 * Class PagesModel
 *
 * Raptor CMS-ийн "Хуудас" (Pages) модулийн өгөгдлийн загвар.
 *
 * Хуудас нь мод бүтэцтэй (parent_id), олон хэлтэй бус (single-table),
 * SEO-friendly slug-тай контент юм. type талбарын анхдагч утга нь 'menu'.
 *
 * Бүх нийтлэгдсэн хуудас навигацид харагдана.
 *  - Ерөнхий мэдээлэл (category=general)
 *  - Нийтлэл (published/draft)
 *
 * codesaur\DataObject\Model-оос өвлөсөн тул:
 *  - CRUD (insert, getRow, updateById, deleteById)
 *  - getRowWhere(), getRows() зэрэг query method-ууд
 *  - __initial() хүснэгт анх үүсгэх үед FK constraint нэмэх
 *
 * Нэмэлт функцууд:
 *  - generateSlug() - Монгол/олон хэлний гарчгаас URL slug үүсгэх
 *  - getBySlug() - Slug-аар хуудас хайх
 *  - getExcerpt() - HTML контентоос товч текст гаргах
 *
 * @package Raptor\Content
 */
class PagesModel extends Model
{
    /**
     * Конструктор - PDO холболт тохируулж, баганууд болон хүснэгт нэрийг зарлах.
     *
     * @param \PDO $pdo Өгөгдлийн сангийн холболт.
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
       (new Column('id', 'bigint'))->primary(),
       (new Column('slug', 'varchar', 255))->unique(),
        new Column('parent_id', 'bigint'),
        new Column('title', 'varchar', 255),
        new Column('description', 'varchar', 255),
        new Column('content', 'mediumtext'),
        new Column('source', 'varchar', 255),
        new Column('photo', 'varchar', 255),
        new Column('code', 'varchar', Constants::DEFAULT_CODE_LENGTH),
       (new Column('type', 'varchar', 32))->default('menu'),
       (new Column('category', 'varchar', 32))->default('general'),
       (new Column('position', 'smallint'))->default(100),
        new Column('link', 'varchar', 255),
       (new Column('is_featured', 'tinyint'))->default(0),
       (new Column('read_count', 'bigint'))->default(0),
       (new Column('published', 'tinyint'))->default(0),
        new Column('published_at', 'datetime'),
        new Column('published_by', 'bigint'),
        new Column('created_at', 'datetime'),
        new Column('created_by', 'bigint'),
        new Column('updated_at', 'datetime'),
        new Column('updated_by', 'bigint')
        ]);

        $this->setTable('pages');
    }

    /**
     * Хүснэгт анх үүсэх үед FK constraint-уудыг нэмэх.
     *
     * published_by, created_by, updated_by баганууд нь
     * users хүснэгтийн id руу гадаад түлхүүрээр холбогдоно.
     */
    protected function __initial()
    {
        $table = $this->getName();
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

        // Хайлт, шүүлтийн гүйцэтгэлийг сайжруулах индексүүд
        $this->exec("CREATE INDEX {$table}_idx_parent_id ON $table (parent_id)");
        $this->exec("CREATE INDEX {$table}_idx_published ON $table (published)");
        $this->exec("CREATE INDEX {$table}_idx_code_published ON $table (code, published, position)");

        PagesSamples::seed($this);
    }

    /**
     * Шинэ хуудас оруулах.
     *
     * - created_at талбарыг автоматаар одоогийн цагаар тохируулна.
     * - slug хоосон бол title-аас generateSlug() ашиглан автоматаар үүсгэнэ.
     *
     * @param array $record Хуудасны өгөгдөл (title, content, parent_id, ...).
     * @return array Амжилттай бол оруулсан бичлэгийн мэдээлэл.
     */
    public function insert(array $record): array
    {
        // Slug автоматаар үүсгэх (title-аас)
        if (empty($record['slug']) && !empty($record['title'])) {
            $record['slug'] = $this->generateSlug($record['title']);
        }

        // Description хоосон бол content-оос автоматаар үүсгэх
        $desc = \trim($record['description'] ?? '');
        if ($desc == '' && !empty($record['content'])) {
            $record['description'] = $this->getExcerpt($record['content']);
        } else {
            $record['description'] = $desc;
        }

        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record);
    }

    /**
     * Гарчигаас SEO-friendly slug үүсгэх.
     *
     * Дараах алхмуудыг гүйцэтгэнэ:
     *  1) Монгол кирилл үсгийг латин тэмдэгтэд хөрвүүлэх
     *  2) Бусад Unicode тэмдэгтийг ICU transliterator-оор латинжуулах
     *  3) Жижиг үсэгт шилжүүлж, тусгай тэмдэгтүүдийг `-` болгох
     *  4) Давхардал шалгаж, шаардлагатай бол дугаар залгах (title-1, title-2, ...)
     *
     * @param string $title Хуудасны гарчиг.
     * @return string Давхардалгүй, URL-д тохирсон slug.
     */
    public function generateSlug(string $title): string
    {
        // Монгол кирилл -> латин
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

        // Бусад хэлний тэмдэгт байвал ICU transliterator ашиглах
        if (\preg_match('/[^\x00-\x7F]/', $slug)
            && \function_exists('transliterator_transliterate')
        ) {
            $slug = \transliterator_transliterate('Any-Latin; Latin-ASCII', $slug);
        }
        $slug = \mb_strtolower($slug);
        $slug = \preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = \trim($slug, '-');

        // Давхардал шалгах
        $original = $slug;
        $count = 1;
        while ($this->getBySlug($slug)) {
            $slug = $original . '-' . $count++;
        }

        return $slug;
    }

    /**
     * Slug-аар хуудас хайх.
     *
     * @param string $slug Хайх slug утга.
     * @return array|null Олдвол хуудасны бичлэг, олдохгүй бол null.
     */
    public function getBySlug(string $slug): array|null
    {
        return $this->getRowWhere(['slug' => $slug]);
    }

    /**
     * Хуудсуудыг parent_id-р мод бүтэцтэй навигаци болгон буцаана.
     *
     * Зөвхөн type нь *-menu (menu, mega-menu г.м.) хуудсуудаас навигацийн бүтэц буцаана.
     * parent -> child -> submenu хэлбэрээр бүтэцлэнэ.
     *
     * @param string $code Хэлний код (mn, en...)
     * @return array Олон түвшний submenu бүтэцтэй навигацийн массив
     */
    public function getNavigation(string $code): array
    {
        $table = $this->getName();
        $stmt = $this->pdo->prepare(
            'SELECT id, parent_id, title, slug, type, link ' .
            "FROM $table " .
            "WHERE code=:code AND published=1 AND (type='menu' OR type LIKE '%-menu') " .
            'ORDER BY position, id'
        );
        $stmt->bindParam(':code', $code, \PDO::PARAM_STR);
        $pages = [];
        if ($stmt->execute()) {
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $pages[$row['id']] = $row;
            }
        }
        return $this->buildTree($pages);
    }

    /**
     * Хуудсуудын жагсаалтаас parent_id дагаж олон түвшний мод бүтэц үүсгэх.
     *
     * Рекурсив байдлаар parent -> children -> submenu бүтэцлэнэ.
     *
     * @param array $pages    id => row бүтэцтэй хуудсуудын массив
     * @param int   $parentId Эхлэх parent ID (0 = root)
     * @return array Submenu бүтэцтэй навигацийн массив
     */
    private function buildTree(array $pages, int $parentId = 0): array
    {
        $tree = [];
        foreach ($pages as $page) {
            if ($page['parent_id'] == $parentId) {
                $children = $this->buildTree($pages, $page['id']);
                if ($children) {
                    $page['submenu'] = $children;
                }
                $tree[$page['id']] = $page;
            }
        }
        return $tree;
    }

    /**
     * Онцлох контент хуудсуудыг авах (child-гүй, is_featured=1).
     *
     * is_featured=1, нийтлэгдсэн, идэвхтэй хуудсуудаас
     * өөртөө ямар нэгэн child агуулаагүй (leaf) хуудсуудыг буцаана.
     *
     * @param string $code Хэлний код (mn, en...)
     * @return array id => row бүтэцтэй массив
     */
    public function getFeaturedLeafPages(string $code): array
    {
        $table = $this->getName();
        $stmt = $this->pdo->prepare(
            'SELECT p.id, p.title, p.description, p.slug, p.photo, p.code, ' .
            'p.type, p.category, p.position, p.link, p.published_at, p.created_at ' .
            "FROM $table p " .
            "LEFT JOIN $table c ON c.parent_id = p.id " .
            'WHERE p.code = :code AND p.published = 1 AND p.is_featured = 1 ' .
            'AND c.id IS NULL ' .
            'ORDER BY p.position, p.id'
        );
        $stmt->bindParam(':code', $code, \PDO::PARAM_STR);

        $pages = [];
        if ($stmt->execute()) {
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $pages[$row['id']] = $row;
            }
        }
        return $pages;
    }

    /**
     * HTML контентоос товч тайлбар (excerpt) үүсгэх.
     *
     * Block tag (p, div, li, ...) хаалтын ард зай нэмж, текст наалдахаас сэргийлнэ.
     * HTML tag-уудыг хасаж, цэвэр текстийг заасан уртаар таслана.
     *
     * @param string $content HTML контент.
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
