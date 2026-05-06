<?php

namespace Raptor\Content;

use codesaur\DataObject\Column;
use codesaur\DataObject\LocalizedModel;

/**
 * Class SettingsModel
 *
 * Raptor framework-ийн **Settings** (сайтын ерөнхий тохиргоо) хадгалах model.
 *
 * - `raptor_settings` хүснэгт дээр ажиллана
 * - LocalizedModel ашиглаж байгаа тул:
 *   - `setColumns()` -> үндсэн хүснэгтийн баганууд
 *   - `setContentColumns()` -> хэл тус бүрийн контент (title, description, address г.м)
 *
 * Гол хэрэглээ:
 * - Админы удирдлагын хэсэгт:
 *   - Сайтын гарчиг, лого, тайлбар
 *   - Холбоо барих мэдээлэл (утас, имэйл, хаяг)
 *   - Favicon, Apple Touch Icon
 *   - Нэмэлт config JSON / TEXT
 * - `retrieve()` функцээр хамгийн сүүлийн бичлэгийг авах
 *
 * Анхаарах зүйл:
 * - `created_by`, `updated_by` нь Raptor-ийн хэрэглэгчийн хүснэгт
 *   (`Raptor\User\UsersModel`) рүү FK холболттой.
 */
class SettingsModel extends LocalizedModel
{
    /**
     * SettingsModel constructor.
     *
     * @param \PDO $pdo
     *      Raptor / codesaur DataObject-той хамт ашиглах PDO instance.
     *      - DB холболтыг гаднаас Injection хэлбэрээр авна.
     *      - PDOTrait-ээр дамжин кеш, driver name, FK toggle г.м ашиглагдана.
     *
     * Хийж буй ажил:
     *  - `$this->setInstance($pdo)` -> LocalizedModel-д PDO-г суулгах
     *  - `$this->setColumns([...])` -> үндсэн хүснэгтийн бүтэц тодорхойлох
     *  - `$this->setContentColumns([...])` -> хэл тус бүрийн контент тодорхойлох
     *  - `$this->setTable('raptor_settings')` -> үндсэн хүснэгтийн нэр оноох
     */
    public function __construct(\PDO $pdo)
    {
        // Raptor DataObject-ийн PDO instance-г тохируулна
        $this->setInstance($pdo);

        // Үндсэн (primary / non-localized) хүснэгтийн баганууд
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),          // PK, авто өсөх ID
            new Column('email', 'varchar', 70),              // Ерөнхий контакт имэйл
            new Column('phone', 'varchar', 70),              // Ерөнхий холбоо барих утас
            new Column('favicon', 'varchar', 255),           // Favicon файлын харгалзах зам
            new Column('apple_touch_icon', 'varchar', 255),  // Apple touch icon зам
            new Column('config', 'text'),                    // Нэмэлт тохиргоо (ихэвчлэн JSON)
            new Column('created_at', 'datetime'),            // Бичлэг үүсгэсэн огноо
            new Column('created_by', 'bigint'),              // Үүсгэсэн хэрэглэгчийн ID (FK)
            new Column('updated_at', 'datetime'),            // Сүүлд шинэчилсэн огноо
            new Column('updated_by', 'bigint')               // Шинэчилсэн хэрэглэгчийн ID (FK)
        ]);

        // Хэл тус бүрийн контент хадгалах баганууд (Localized / content table)
        $this->setContentColumns([
            new Column('title', 'varchar', 70),              // Веб сайтын гарчиг (title)
            new Column('logo', 'varchar', 255),              // Лого зураг / зам
            new Column('description', 'varchar', 255),       // Товч тайлбар / SEO description
            new Column('urgent', 'text'),                    // Яаралтай мэдэгдэл / banner текст
            new Column('contact', 'text'),                   // Холбоо барих дэлгэрэнгүй мэдээлэл (HTML байж болно)
            new Column('address', 'text'),                   // Хаяг (олон мөрт текст)
            new Column('copyright', 'varchar', 255)          // Зохиогчийн эрхийн мөр (footer)
        ]);

        // Үндсэн хүснэгтийн нэр
        $this->setTable('raptor_settings');
    }

    /**
     * Хүснэгтийг анх удаа үүсгэх үед ажиллах hook.
     *
     * LocalizedModel / DataObject доторх автомат миграцийн үед:
     * - FK constraint-уудыг үүсгэх
     * - Зарим DB specific тохиргоонуудыг хийх зорилготой.
     *
     * Энд:
     *  - `created_by` -> users.id рүү FK
     *  - `updated_by` -> users.id рүү FK
     *
     * @return void
     */
    protected function __initial(): void
    {
        $table = $this->getName();
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        // created_by талбарын FK
        $this->exec(
            "ALTER TABLE $table
             ADD CONSTRAINT {$table}_fk_created_by
             FOREIGN KEY (created_by) REFERENCES $users(id)
             ON DELETE SET NULL
             ON UPDATE CASCADE"
        );
        // updated_by талбарын FK
        $this->exec(
            "ALTER TABLE $table
             ADD CONSTRAINT {$table}_fk_updated_by
             FOREIGN KEY (updated_by) REFERENCES $users(id)
             ON DELETE SET NULL
             ON UPDATE CASCADE"
        );

        $path = \dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        if ($path == '\\' || $path == '/' || $path == '.') {
            $path = '';
        }
        $logo = $path . '/assets/images/codesaur.png';

        $this->insert(
            [
                'phone' => '+976 0000-0000',
                'email' => 'info@example.com',
                'config' => \json_encode([
                    'facebook' => 'https://facebook.com',
                    'youtube' => 'https://youtube.com',
                    'instagram' => 'https://instagram.com',
                    'open-hours' => [
                        'mn' => 'Даваа - Баасан, 09:00 - 18:00',
                        'en' => 'Mon - Fri, 09:00 - 18:00'
                    ]
                ], \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT)
            ],
            [
                'mn' => [
                    'title' => 'Raptor',
                    'logo' => $logo,
                    'description' => 'Raptor Framework дээр суурилсан вэб сайт',
                    'address' => 'Улаанбаатар хот, Сүхбаатар дүүрэг',
                    'copyright' => '&copy; ' . \date('Y') . ' Raptor'
                ],
                'en' => [
                    'title' => 'Raptor',
                    'logo' => $logo,
                    'description' => 'Website powered by Raptor Framework',
                    'address' => 'Ulaanbaatar, Sukhbaatar District',
                    'copyright' => '&copy; ' . \date('Y') . ' Raptor'
                ]
            ]
        );
    }

    /**
     * Шинэ settings бичлэг оруулах.
     *
     * @param array $record
     *      Үндсэн хүснэгтийн өгөгдөл:
     *      - email, phone, favicon, apple_touch_icon, config, created_by г.м
     * @param array $content
     *      Хэл тус бүрийн контент:
     *      - title, logo, description, urgent, contact, address, copyright
     *      - LocalizedModel-ийн форматтай (жишээ нь: ['mn_MN' => [...], 'en_US' => [...]] )
     *
     * @return array
     *      Амжилттай байвал:
     *          [
     *              'record'  => [...], // үндсэн мөр
     *              'content' => [...]  // контент мөрүүд
     *          ]
     *
     * Тайлбар:
     *  - Хэрэв `$record['created_at']` ирээгүй бол автоматаар одоогийн цагийг онооно.
     *  - Дараа нь `parent::insert()` дуудагдана (LocalizedModel).
     */
    public function insert(array $record, array $content): array
    {
        // created_at ирээгүй бол автомат огноо онооно
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record, $content);
    }

    /**
     * ID-р settings бичлэг шинэчлэх.
     *
     * @param int   $id
     *      Шинэчлэх гэж буй үндсэн мөрийн ID (`raptor_settings.id`)
     * @param array $record
     *      Үндсэн хүснэгтийн шинэ утгууд:
     *      - phone, email, config, updated_by г.м
     * @param array $content
     *      Хэл тус бүрийн шинэ контент:
     *      - title, description, logo, address, contact г.м
     *
     * @return array
     *      Амжилттай бол шинэчлэгдсэн өгөгдөлтэй массив
     *
     * Тайлбар:
     *  - `$record['updated_at']` параметр ирээгүй бол автоматаар одоогийн цаг онооно.
     *  - LocalizedModel-ийн `updateById()`-ыг ашиглаж, үндсэн + контент хүснэгтийг зэрэг шинэчилнэ.
     */
    public function updateById(int $id, array $record, array $content): array
    {
        // updated_at ирээгүй тохиолдолд автоматаар одоогийн цаг онооно
        $record['updated_at'] ??= \date('Y-m-d H:i:s');        
        return parent::updateById($id, $record, $content);
    }

    /**
     * Идэвхтэй settings тохиргоог авах.
     *
     * @return array
     *      - Хамгийн сүүлийнх мөрийг буцаана
     *      - Хоосон байвал хоосон массив `[]`
     *
     * Тайлбар:
     *  - `getRows()` нь бүх мөрийг авна.
     *      - Хэрэв олон бичлэг байвал `end($record)` ашиглаж хамгийн
     *        сүүлд орсон/уншсан мөрийг буцаана.
     *  - UI талд: 
     *      - header, footer, contact page, SEO мета мэдээлэл г.м бүх газар
     *        яг энэ методыг ашиглаж ерөнхий тохиргоог унших боломжтой.
     */
    public function retrieve(): array
    {
        $record = $this->getRows() ?? [];
        // Хоосон байвал [] буцаана, байвал хамгийн сүүлийнх мөрийг буцаана
        return \end($record) ?: [];
    }
}
