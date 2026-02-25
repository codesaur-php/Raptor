<?php

namespace Raptor\Content;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Class PagesModel
 *
 * Raptor CMS-ийн "Хуудас" (Pages) модулийн өгөгдлийн загвар.
 *
 * Хуудас нь мод бүтэцтэй (parent_id), олон хэлтэй бус (single-table),
 * SEO-friendly slug-тай контент юм. Тухайлбал:
 *  - Цэсний навигац (type=nav) - дотроо хүүхэд хуудсуудыг агуулах цэсний зүйл
 *  - Агуулга (type=content) - контент бүхий хуудас
 *  - Холбоос (type=link) - URL руу чиглүүлэх
 *
 * Бүх нийтлэгдсэн хуудас навигацид харагдана (type ялгаагүй).
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
        new Column('photo', 'varchar', 255),
        new Column('code', 'varchar', 2),
       (new Column('type', 'varchar', 32))->default('content'),
       (new Column('category', 'varchar', 32))->default('general'),
       (new Column('position', 'smallint'))->default(100),
        new Column('link', 'varchar', 255),
       (new Column('is_featured', 'tinyint'))->default(0),
       (new Column('comment', 'tinyint'))->default(0),
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

        $this->setTable('pages');
    }

    /**
     * Хүснэгт анх үүсэх үед FK constraint-уудыг нэмэх.
     *
     * published_by, created_by, updated_by баганууд нь
     * users хүснэгтийн id руу гадаад түлхүүрээр холбогдоно.
     * SQLite дээр ALTER TABLE ADD CONSTRAINT дэмжигдэхгүй тул алгасна.
     */
    protected function __initial()
    {
        $table = $this->getName();

        // SQLite нь ALTER TABLE ... ADD CONSTRAINT дэмжихгүй
        // MySQL/PostgreSQL дээр л FK constraint нэмнэ
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

        $now = \date('Y-m-d H:i:s');
        $path = \dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        if ($path == '\\' || $path == '/' || $path == '.') {
            $path = '';
        }
        $assets = $path . '/assets/images';
        $seed = [
            'is_active' => 1,
            'published' => 1,
            'created_at' => $now,
            'published_at' => $now,
            'category' => 'sample'
        ];

        // ============ MN хуудсууд ============

        // Танилцуулга (root content)
        $this->insert($seed + [
            'code' => 'mn',
            'title' => 'Танилцуулга',
            'type' => 'content',
            'position' => 10,
            'content' => '<p>Энэ бол <a href="https://github.com/codesaur-php/Raptor" target="_blank">Raptor Framework</a> дээр суурилсан демо вэб сайт юм.</p>'
                . '<p>Та энэ хуудсыг <a href="' . $path . '/dashboard">хянах самбар</a>аас засварлах боломжтой.</p>'
                . '<p>Холбоосууд:</p>'
                . '<ul>'
                . '<li><a href="https://codesaur.net" target="_blank">codesaur.net</a> - Албан ёсны вэб сайт</li>'
                . '<li><a href="https://github.com/codesaur-php" target="_blank">GitHub</a> - Эх код, бүх package-ууд</li>'
                . '<li><a href="https://packagist.org/packages/codesaur/" target="_blank">Packagist</a> - Composer package-ууд</li>'
                . '</ul>'
        ]);

        // Бидний тухай (nav → dropdown menu жишээ)
        $mnAbout = $this->insert($seed + [
            'code' => 'mn',
            'title' => 'Бидний тухай',
            'type' => 'nav',
            'position' => 20
        ]);
        $this->insert($seed + [
            'code' => 'mn',
            'parent_id' => $mnAbout['id'],
            'title' => 'Байгууллага',
            'type' => 'content',
            'position' => 21,
            'photo' => $assets . '/organization.jpg',
            'content' => '<p>Байгууллагын танилцуулга энд байрлана.</p>'
                . '<p>Энэ хуудсыг <a href="' . $path . '/dashboard">хянах самбар</a>аас засварлах боломжтой.</p>'
        ]);
        $this->insert($seed + [
            'code' => 'mn',
            'parent_id' => $mnAbout['id'],
            'title' => 'Баг',
            'type' => 'content',
            'position' => 22,
            'content' => '<p>Манай багийн гишүүдийн танилцуулга.</p>'
                . '<p><img src="' . $assets . '/team.jpg" alt="Баг" class="img-fluid rounded shadow-sm"></p>'
        ]);

        // Динозаврууд (nav → dropdown menu жишээ)
        $mnDino = $this->insert($seed + [
            'code' => 'mn',
            'title' => 'Динозаврууд',
            'type' => 'nav',
            'position' => 30
        ]);
        $this->insert($seed + [
            'code' => 'mn',
            'parent_id' => $mnDino['id'],
            'title' => 'Велоцираптор',
            'type' => 'content',
            'position' => 31,
            'is_featured' => 1,
            'photo' => $assets . '/velociraptor.jpg',
            'content' => '<p><strong>Velociraptor</strong> (/vɪˈlɒsɪræptər/) - Латинаар "хурдан баригч" гэсэн утгатай.</p>'
                . '<p>Cretaceous галавын сүүл үе буюу ойролцоогоор 75-71 сая жилийн өмнө амьдарч байсан dromaeosaurid theropod үлэг гүрвэл юм. '
                . '<em>V. mongoliensis</em> зүйлийн олдворуудыг <strong>Монгол</strong> улсаас олсон байдаг.</p>'
        ]);
        $this->insert($seed + [
            'code' => 'mn',
            'parent_id' => $mnDino['id'],
            'title' => 'Тарбозавр',
            'type' => 'content',
            'position' => 32,
            'is_featured' => 1,
            'photo' => $assets . '/tarbosaurus.jpg',
            'content' => '<p><strong>Tarbosaurus</strong> - Монголоос олдсон хамгийн алдартай махан идэшт динозавр.</p>'
                . '<p>Tyrannosaurus Rex-ийн хамгийн ойрын төрөл бөгөөд ойролцоогоор 70 сая жилийн өмнө Азид амьдарч байжээ. '
                . 'Монгол палеонтологийн нэн чухал олдвор юм.</p>'
        ]);

        // Холбоо барих
        $this->insert($seed + [
            'code' => 'mn',
            'title' => 'Холбоо барих',
            'type' => 'content',
            'position' => 40,
            'link' => $path . '/contact',
            'content' => '<p>Бидэнтэй холбогдохыг хүсвэл доорх мэдээллийг ашиглана уу.</p>'
                . '<p>Имэйл: info@example.com</p>'
        ]);

        // ============ EN хуудсууд ============

        // Introduction (root content)
        $this->insert($seed + [
            'code' => 'en',
            'title' => 'Introduction',
            'type' => 'content',
            'position' => 50,
            'content' => '<p>This is a demo website built on the <a href="https://github.com/codesaur-php/Raptor" target="_blank">Raptor Framework</a>.</p>'
                . '<p>You can edit this page from the <a href="' . $path . '/dashboard">admin dashboard</a>.</p>'
                . '<p>Links:</p>'
                . '<ul>'
                . '<li><a href="https://codesaur.net" target="_blank">codesaur.net</a> - Official website</li>'
                . '<li><a href="https://github.com/codesaur-php" target="_blank">GitHub</a> - Source code &amp; packages</li>'
                . '<li><a href="https://packagist.org/packages/codesaur/" target="_blank">Packagist</a> - Composer packages</li>'
                . '</ul>'
        ]);

        // About Us (nav → dropdown menu example)
        $enAbout = $this->insert($seed + [
            'code' => 'en',
            'title' => 'About Us',
            'type' => 'nav',
            'position' => 60
        ]);
        $this->insert($seed + [
            'code' => 'en',
            'parent_id' => $enAbout['id'],
            'title' => 'Organization',
            'type' => 'content',
            'position' => 61,
            'photo' => $assets . '/organization.jpg',
            'content' => '<p>Organization introduction goes here.</p>'
                . '<p>You can edit this page from the <a href="' . $path . '/dashboard">admin dashboard</a>.</p>'
        ]);
        $this->insert($seed + [
            'code' => 'en',
            'parent_id' => $enAbout['id'],
            'title' => 'Team',
            'type' => 'content',
            'position' => 62,
            'content' => '<p>Meet our team members.</p>'
                . '<p><img src="' . $assets . '/team.jpg" alt="Team" class="img-fluid rounded shadow-sm"></p>'
        ]);

        // Dinosaurs (nav → dropdown menu example)
        $enDino = $this->insert($seed + [
            'code' => 'en',
            'title' => 'Dinosaurs',
            'type' => 'nav',
            'position' => 70
        ]);
        $this->insert($seed + [
            'code' => 'en',
            'parent_id' => $enDino['id'],
            'title' => 'Velociraptor',
            'type' => 'content',
            'position' => 71,
            'is_featured' => 1,
            'photo' => $assets . '/velociraptor.jpg',
            'content' => '<p><strong>Velociraptor</strong> (/vɪˈlɒsɪræptər/) - Latin for "swift seizer".</p>'
                . '<p>A dromaeosaurid theropod dinosaur that lived approximately 75 to 71 million years ago during the Late Cretaceous period. '
                . 'Fossils of <em>V. mongoliensis</em> were discovered in <strong>Mongolia</strong>.</p>'
        ]);
        $this->insert($seed + [
            'code' => 'en',
            'parent_id' => $enDino['id'],
            'title' => 'Tarbosaurus',
            'type' => 'content',
            'position' => 72,
            'is_featured' => 1,
            'photo' => $assets . '/tarbosaurus.jpg',
            'content' => '<p><strong>Tarbosaurus</strong> - The most famous carnivorous dinosaur discovered in Mongolia.</p>'
                . '<p>The closest relative of Tyrannosaurus Rex, it lived approximately 70 million years ago in Asia. '
                . 'One of the most important finds in Mongolian paleontology.</p>'
        ]);

        // Contact
        $this->insert($seed + [
            'code' => 'en',
            'title' => 'Contact',
            'type' => 'content',
            'position' => 80,
            'link' => $path . '/contact',
            'content' => '<p>Feel free to reach out to us using the information below.</p>'
                . '<p>Email: info@example.com</p>'
        ]);
    }

    /**
     * Шинэ хуудас оруулах.
     *
     * - created_at талбарыг автоматаар одоогийн цагаар тохируулна.
     * - slug хоосон бол title-аас generateSlug() ашиглан автоматаар үүсгэнэ.
     *
     * @param array $record Хуудасны өгөгдөл (title, content, parent_id, ...).
     * @return array|false Амжилттай бол оруулсан бичлэгийн мэдээлэл, алдаатай бол false.
     */
    public function insert(array $record): array|false
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');

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
     * Бүх нийтлэгдсэн, идэвхтэй хуудсуудаас навигацийн бүтэц буцаана.
     * parent → child → submenu хэлбэрээр бүтэцлэнэ (type ялгаагүй).
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
            "WHERE code=:code AND is_active=1 AND published=1 " .
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
     * Рекурсив байдлаар parent → children → submenu бүтэцлэнэ.
     *
     * @param array $pages    id => row бүтэцтэй хуудсуудын массив
     * @param int   $parentId Эхлэх parent ID (0 = root)
     * @return array Submenu бүтэцтэй навигацийн массив
     */
    public function buildTree(array $pages, int $parentId = 0): array
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
     * HTML контентоос товч тайлбар (excerpt) үүсгэх.
     *
     * HTML tag-уудыг хасаж, цэвэр текстийг заасан уртаар таслана.
     *
     * @param string $content HTML контент.
     * @param int $length Хамгийн их тэмдэгтийн урт (анхдагч: 200).
     * @return string Товчилсон текст. Хэтэрвэл `...` залгана.
     */
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
