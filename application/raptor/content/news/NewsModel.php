<?php

namespace Raptor\Content;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Class NewsModel
 *
 * Мэдээний (`news`) хүснэгттэй ажиллах өгөгдлийн загвар (Model) класс.
 * Энэ класс нь Raptor Framework-ийн DataObject\Model-ийг ашиглан
 * мэдээний хүснэгтийн бүтцийг тодорхойлох, CRUD болон өгөгдлийн агуулахтай
 * уялдаа холбоо үүсгэх үүрэгтэй.
 *
 * Үндсэн боломжууд:
 *  - Мэдээний хүснэгтийн багануудыг тодорхойлох
 *  - FK constraint-уудыг анхны тохиргоонд үүсгэх
 *  - Шинэ мэдээ үүсгэх үед created_at, slug талбаруудыг автоматаар бөглөх
 *  - Мэдээний төрөл (type), ангилал (category) зэрэг талбаруудыг удирдах
 *  - Хэлний код (code) ашиглан олон хэл дээрх мэдээний удирдлага
 *
 * Хүснэгтийн талбарууд:
 *  - id (bigint, primary) - Мэдээний өвөрмөц дугаар
 *  - slug (varchar 255, unique, nullable) - SEO-friendly URL (жишээ: mongol-uls-2025)
 *  - title (varchar 255) - Мэдээний гарчиг
 *  - content (mediumtext) - Мэдээний бүтэн агуулга
 *  - photo (varchar 255) - Мэдээний зургын URL path
 *  - code (varchar 2) - Хэлний код (mn, en, гэх мэт)
 *  - type (varchar 32, default: 'article') - Мэдээний төрөл
 *  - category (varchar 32, default: 'general') - Мэдээний ангилал
 *  - is_featured (tinyint, default: 0) - Онцлох мэдээ эсэх
 *  - comment (tinyint, default: 1) - Сэтгэгдэл идэвхтэй эсэх
 *  - read_count (bigint, default: 0) - Уншсан тоо
 *  - is_active (tinyint, default: 1) - Идэвхтэй эсэх
 *  - published (tinyint, default: 0) - Нийтлэгдсэн эсэх
 *  - published_at (datetime) - Нийтлэгдсэн огноо
 *  - published_by (bigint) - Нийтлэсэн хэрэглэгчийн ID
 *  - created_at (datetime) - Үүсгэсэн огноо
 *  - created_by (bigint) - Үүсгэсэн хэрэглэгчийн ID
 *  - updated_at (datetime) - Шинэчлэсэн огноо
 *  - updated_by (bigint) - Шинэчлэсэн хэрэглэгчийн ID
 *
 * Хүснэгт ашиглалт:
 *  - Мэдээ → Хэрэглэгч (published_by, created_by, updated_by)
 *
 * @package Raptor\Content
 */
class NewsModel extends Model
{
    /**
     * NewsModel constructor.
     *
     * PDO instance-г оноож, мэдээний хүснэгтийн бүх багануудыг тодорхойлно.
     * Хүснэгтийн нэрийг 'news' гэж тохируулна.
     *
     * ⚡ **PDO Injection тухай тэмдэглэл**
     * --------------------------------------------------------------
     * Raptor Framework нь PDO instance-ийг дараах дарааллаар inject хийдэг:
     *
     *   1) MySQLConnectMiddleware / PostgresConnectMiddleware
     *      → Application-ийн ServerRequestInterface дотор PDO instance үүсгэнэ
     *      → $request->withAttribute('pdo', $pdo) хэлбэрээр хадгална
     *
     *   2) Controller constructor
     *      → ServerRequest-аас PDO-г авч $this->pdo хувьсагчид онооно
     *      → $this->pdo = $request->getAttribute('pdo')
     *
     *   3) Model constructor (энэ функц)
     *      → Controller-ийн байгуулагчаар дамжуулан PDO instance ирнэ
     *      → new NewsModel($this->pdo) хэлбэрээр дуудагдана
     *
     * Иймээс энэхүү `$pdo` нь *middleware injection-ээр дамжсан баталгаатай
     * холболт* бөгөөд Model анги зөвхөн өгөгдөлтэй ажиллахад анхаарна.
     *
     * ✔ Framework-ийн request-scope injection механизм ашигладаг
     * ✔ Нэг request дотор нэг л PDO instance ажиллана
     * ✔ DatabaseMiddleware нь PDO-г автоматаар үүсгэж inject хийнэ
     *
     * @param \PDO $pdo Database connection instance.
     *                  Application-ийн ServerRequestInterface дотор inject хийгдсэн
     *                  PDO object (DatabaseMiddleware inject хийсэн) байгаа нь
     *                  Controller-ийн байгуулагчаар дамжуулан ирдэг.
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
            new Column('photo', 'varchar', 255),
            new Column('code', 'varchar', 2),
           (new Column('type', 'varchar', 32))->default('article'),
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
        
        $this->setTable('news');
    }
    
    /**
     * Анхны тохиргоо (initial setup).
     *
     * Хүснэгт анх үүсэх үед foreign key constraint-уудыг автоматаар үүсгэнэ.
     * Энэ функц нь:
     *  - published_by → users(id) foreign key
     *  - created_by → users(id) foreign key
     *  - updated_by → users(id) foreign key
     *
     * Бүх foreign key-ууд ON DELETE SET NULL, ON UPDATE CASCADE бүтэцтэй.
     *
     * @return void
     */
    protected function __initial()
    {
        $table = $this->getName();

        // SQLite нь ALTER TABLE ... ADD CONSTRAINT дэмжихгүй
        // MySQL/PostgreSQL дээр л FK constraint нэмнэ
        if ($this->getDriverName() != 'sqlite') {
            $this->setForeignKeyChecks(false);
            
            $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
            
            // Foreign key constraint-ууд үүсгэх
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
        $seed = [
            'is_active' => 1,
            'published' => 1,
            'created_at' => $now,
            'published_at' => $now,
            'category' => 'sample'
        ];

        // MN мэдээнүүд
        $this->insert($seed + [
            'code' => 'mn',
            'title' => 'Raptor Framework суулгагдлаа',
            'photo' => $path . '/assets/images/code.jpg',
            'content' => '<p><a href="https://github.com/codesaur-php/Raptor" target="_blank">Raptor Framework</a> амжилттай суулгагдлаа. '
                . 'Та одоо <a href="' . $path . '/dashboard">хянах самбар</a>аас контент удирдах боломжтой.</p>'
                . '<p>Ашиглагдаж буй codesaur package-ууд:</p>'
                . '<ul>'
                . '<li><a href="https://github.com/codesaur-php/http-application" target="_blank">codesaur/http-application</a> - PSR-7/15 HTTP Application, Middleware</li>'
                . '<li><a href="https://github.com/codesaur-php/router" target="_blank">codesaur/router</a> - URL Router</li>'
                . '<li><a href="https://github.com/codesaur-php/http-message" target="_blank">codesaur/http-message</a> - PSR-7 HTTP Message</li>'
                . '<li><a href="https://github.com/codesaur-php/dataobject" target="_blank">codesaur/dataobject</a> - PDO суурьтай ORM (Model, LocalizedModel)</li>'
                . '<li><a href="https://github.com/codesaur-php/template" target="_blank">codesaur/template</a> - Twig Template Engine Wrapper</li>'
                . '<li><a href="https://github.com/codesaur-php/http-client" target="_blank">codesaur/http-client</a> - HTTP Client (OpenAI API)</li>'
                . '<li><a href="https://github.com/codesaur-php/container" target="_blank">codesaur/container</a> - PSR-11 DI Container</li>'
                . '</ul>'
        ]);
        $this->insert($seed + [
            'code' => 'mn',
            'title' => 'Тавтай морилно уу',
            'photo' => $path . '/assets/images/welcome.jpg',
            'content' => '<p>Манай вэб сайтад тавтай морилно уу. '
                . 'Энэ бол <a href="https://github.com/codesaur-php/Raptor" target="_blank">Raptor</a> дээр суурилсан демо сайт юм.</p>'
                . '<p>Дэлгэрэнгүй мэдээллийг <a href="https://codesaur.net" target="_blank">codesaur.net</a> '
                . 'болон <a href="https://packagist.org/packages/codesaur/" target="_blank">Packagist</a> дээрээс авна уу.</p>'
        ]);
        $this->insert($seed + [
            'code' => 'mn',
            'title' => 'CMS систем ашиглах заавар',
            'content' => '<p><a href="' . $path . '/dashboard">Хянах самбар</a>т нэвтэрч мэдээ болон хуудсуудыг удирдаарай. '
                . 'Анхдагч нэвтрэх: admin / password</p>'
        ]);

        // EN мэдээнүүд
        $this->insert($seed + [
            'code' => 'en',
            'title' => 'Raptor Framework Installed',
            'photo' => $path . '/assets/images/code.jpg',
            'content' => '<p><a href="https://github.com/codesaur-php/Raptor" target="_blank">Raptor Framework</a> has been successfully installed. '
                . 'You can now manage content from the <a href="' . $path . '/dashboard">admin dashboard</a>.</p>'
                . '<p>codesaur packages in use:</p>'
                . '<ul>'
                . '<li><a href="https://github.com/codesaur-php/http-application" target="_blank">codesaur/http-application</a> - PSR-7/15 HTTP Application, Middleware</li>'
                . '<li><a href="https://github.com/codesaur-php/router" target="_blank">codesaur/router</a> - URL Router</li>'
                . '<li><a href="https://github.com/codesaur-php/http-message" target="_blank">codesaur/http-message</a> - PSR-7 HTTP Message</li>'
                . '<li><a href="https://github.com/codesaur-php/dataobject" target="_blank">codesaur/dataobject</a> - PDO-based ORM (Model, LocalizedModel)</li>'
                . '<li><a href="https://github.com/codesaur-php/template" target="_blank">codesaur/template</a> - Twig Template Engine Wrapper</li>'
                . '<li><a href="https://github.com/codesaur-php/http-client" target="_blank">codesaur/http-client</a> - HTTP Client (OpenAI API)</li>'
                . '<li><a href="https://github.com/codesaur-php/container" target="_blank">codesaur/container</a> - PSR-11 DI Container</li>'
                . '</ul>'
        ]);
        $this->insert($seed + [
            'code' => 'en',
            'title' => 'Welcome to Our Website',
            'photo' => $path . '/assets/images/welcome.jpg',
            'content' => '<p>Welcome to our website. '
                . 'This is a demo site built on <a href="https://github.com/codesaur-php/Raptor" target="_blank">Raptor</a>.</p>'
                . '<p>For more information visit <a href="https://codesaur.net" target="_blank">codesaur.net</a> '
                . 'and <a href="https://packagist.org/packages/codesaur/" target="_blank">Packagist</a>.</p>'
        ]);
        $this->insert($seed + [
            'code' => 'en',
            'title' => 'Getting Started with CMS',
            'content' => '<p>Log in to the <a href="' . $path . '/dashboard">admin dashboard</a> to manage news and pages. '
                . 'Default credentials: admin / password</p>'
        ]);
    }
    
    /**
     * Шинэ мэдээ үүсгэх.
     *
     * Мэдээний бичлэг үүсгэх үед created_at болон slug талбаруудыг
     * автоматаар бөглөнө (хэрэв өгөгдөөгүй бол).
     *
     * @param array $record Мэдээний мэдээлэл (title, content, гэх мэт)
     * @return array|false Амжилттай бол үүссэн бичлэгийн массив, бусад тохиолдолд false
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
     * @param string $title Мэдээний гарчиг
     * @return string SEO-friendly slug (жишээ: mongol-uls-2025-ond)
     */
    public function generateSlug(string $title): string
    {
        // Монгол кирилл -> латин (ICU transliterator Монгол дэмждэггүй)
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
        // Жижиг үсэг болгох
        $slug = \mb_strtolower($slug);
        // Зөвхөн үсэг, тоо, зураас үлдээх
        $slug = \preg_replace('/[^a-z0-9]+/', '-', $slug);
        // Эхний болон сүүлийн зураас хасах
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
     * Slug-аар мэдээ хайх.
     *
     * @param string $slug Мэдээний slug
     * @return array|null Мэдээ эсвэл null
     */
    public function getBySlug(string $slug): array|null
    {
        return $this->getRowWhere(['slug' => $slug]);
    }

    /**
     * Content-оос товч тайлбар (excerpt) үүсгэх.
     *
     * HTML tag-уудыг хасаж, эхний $length тэмдэгтийг буцаана.
     *
     * @param string $content Мэдээний агуулга (HTML)
     * @param int $length Тэмдэгтийн урт (default: 200)
     * @return string Товч тайлбар
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
