<?php

namespace Raptor\Localization;

use codesaur\DataObject\Column;
use codesaur\DataObject\LocalizedModel;

/**
 * Class TextModel
 *
 * Нутагшуулалтын текстүүдийг удирдах зориулалттай загвар (Model).
 * LocalizedModel-ийг өргөтгөн ашиглаж байгаа тул:
 *    - Үндсэн хүснэгт (parent table)
 *    - Олон хэл дээрх контентын хүснэгт (content table)
 * гэсэн хоёр түвшний өгөгдөлтэй ажиллана.
 *
 * Хүснэгт:
 *    localization_text         -> parent
 *    localization_text_content -> content
 *
 * Гол зориулалт:
 *    - Тухайн модульд хамаарах keyword-уудыг хадгалах
 *    - Текстийн орчуулгуудыг (text) хэл тус бүрийн content хүснэгтэд хадгалах
 *    - retrieve() - бүх текст эсвэл нэг хэлний текст татах
 *    - insert/update - parent + content хүснэгтүүд рүү зэрэг бичих
 */
class TextModel extends LocalizedModel
{
    /**
     * TextModel constructor.
     *
     * @param PDO $pdo  PDO instance - өгөгдлийн сантай холбогдох
     *
     * Parent хүснэгтийн баганууд:
     *    id           - анхдагч түлхүүр
     *    keyword      - текстийн түлхүүр нэр (жишээ: accept)
     *    type         - текстийн төрөл (sys-defined, user-defined ...)
     *    created_at / created_by
     *    updated_at / updated_by
     *
     * Content хүснэгтийн багана:
     *    text         - тухайн keyword-ийн тухайн хэл дээрх орчуулга
     */
    public function __construct(\PDO $pdo)
    {
        // Middleware-ээс ирсэн PDO instance авах
        $this->setInstance($pdo);

        // Parent table columns
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('keyword', 'varchar', 128))->unique(),
            new Column('type', 'varchar', 16),
            new Column('created_at', 'timestamp'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'timestamp'),
            new Column('updated_by', 'bigint')
        ]);

        // Content table columns
        $this->setContentColumns([
            new Column('text', 'varchar', 255)
        ]);

        // Хүснэгтийн нэрийг тогтоох
        parent::setTable('localization_text');
    }

    /**
     * retrieve()
     *
     * @param string|null $code Хэлний код (null бол бүх хэлээр авна)
     * @return array
     *
     * Бүх keyword -> хэл -> орчуулга бүтэцтэй массив буцаана:
     *
     *    [
     *       "homepage" => [
     *           "mn" => "Нүүр хуудас",
     *           "en" => "Home"
     *       ],
     *       ...
     *    ]
     *
     * Хэрэв code өгсөн бол зөвхөн тэр хэлний текст:
     *    code = 'mn' байсан гэж бодоход
     * 
     *    [
     *       "homepage" => "Нүүр хуудас",
     *       "welcome" => "Тавтай морил"
     *    ]
     */
    public function retrieve(?string $code = null): array
    {
        $text = [];
        if (empty($code)) {
            // Бүх хэлээр татах
            $stmt = $this->select(
                "p.keyword as keyword, c.code as code, c.text as text",
                ['ORDER BY' => 'p.keyword']
            );
            // keyword -> code -> text
            while ($row = $stmt->fetch()) {
                $text[$row['keyword']][$row['code']] = $row['text'];
            }
        } else {
            // Зөвхөн 1 хэлний текст татах
            $condition = [
                'WHERE' => "c.code=:code",
                'ORDER BY' => 'p.keyword',
                'PARAM' => [':code' => $code]
            ];
            $stmt = $this->select('p.keyword as keyword, c.text as text', $condition);
            while ($row = $stmt->fetch()) {
                $text[$row['keyword']] = $row['text'];
            }
        }
        return $text;
    }

    /**
     * __initial()
     *
     * Хүснэгтийг анх үүсгэх үед:
     *    - created_by / updated_by талбаруудад FK холбоос үүсгэнэ
     *    - хэрэв TextInitial class дээр уг хүснэгтэд зориулсан анхны өгөгдөл
     *      тодорхойлсон бол түүнийг ажиллуулна.
     */
    protected function __initial()
    {
        $table = $this->getName();

        $this->setForeignKeyChecks(false);

        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();

        // created_by FK
        $this->exec(
            "ALTER TABLE $table
             ADD CONSTRAINT {$table}_fk_created_by
             FOREIGN KEY (created_by) REFERENCES $users(id)
             ON DELETE SET NULL ON UPDATE CASCADE"
        );

        // updated_by FK
        $this->exec(
            "ALTER TABLE $table
             ADD CONSTRAINT {$table}_fk_updated_by
             FOREIGN KEY (updated_by) REFERENCES $users(id)
             ON DELETE SET NULL ON UPDATE CASCADE"
        );

        $this->setForeignKeyChecks(true);

        // TextInitial class дотор анхны өгөгдөл суулгах
        TextInitial::localization_text($this);
    }

    /**
     * insert()
     *
     * @param array $record   Parent хүснэгтэд орох мэдээлэл
     * @param array $content  Content хүснэгтэд орчуулгын мэдээлэл
     *
     * @return array    Оруулсан мөрийг нийлүүлсэн (parent+content) бүтэцтэй буцаана
     *
     * created_at талбарыг ирүүлээгүй бол автомат онооно.
     */
    public function insert(array $record, array $content): array
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record, $content);
    }

    /**
     * updateById()
     *
     * @param int $id         Бичлэгийн id дугаар
     * @param array $record   Parent хүснэгтийн шинэ мэдээлэл
     * @param array $content  Content хүснэгтийн шинэ текстүүд
     *
     * @return array    Шинэчлэгдсэн мөрийг нийлүүлсэн (parent+content) бүтэцтэй буцаана
     *
     * updated_at талбарыг ирүүлээгүй бол автомат онооно.
     */
    public function updateById(int $id, array $record, array $content): array
    {
        $record['updated_at'] ??= \date('Y-m-d H:i:s');
        return parent::updateById($id, $record, $content);
    }
}
