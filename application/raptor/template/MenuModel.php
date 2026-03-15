<?php

namespace Raptor\Template;

use codesaur\DataObject\Column;
use codesaur\DataObject\LocalizedModel;

/**
 * Class MenuModel
 *
 * Raptor Framework-ийн Dashboard хэсгийн үндсэн
 * менютэй холбоотой өгөгдлийг удирдах LocalizedModel.
 *
 * Онцлог:
 *  - Олон хэл дээрх title талбар LocalizedModel-аар автоматаар удирдагдана.
 *  - parent/child бүтэц бүхий модульчлагдсан цэс зохион байгуулалттай.
 *  - Permission-тэй уялдаж тухайн хэрэглэгчийн харж болох менюг
 *    динамикаар шүүн харуулдаг.
 *  - Хамгийн эхний удаа Dashboard Application суух/ачаалах үед (__initial) анхны системийн меню үүснэ.
 *
 * Хүснэгт: raptor_menu + raptor_menu_content (LocalizedModel)
 *
 * @package Raptor\Template
 */
class MenuModel extends LocalizedModel
{
    /**
     * MenuModel constructor.
     *
     * @param \PDO $pdo  PDO instance
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        // --- Үндсэн баганаууд ---
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('parent_id', 'bigint'))->default(0),             // Эцэг меню ID
            new Column('icon', 'varchar', 64),                          // Bootstrap Icons нэр
            new Column('href', 'varchar', 255),                         // Линк
            new Column('alias', 'varchar', 64),                         // Меню аль байгууллагынх вэ (organization alias)
            new Column('permission', 'varchar', 128),                   // RBAC permission код
           (new Column('position', 'smallint'))->default(100),          // Дараалал
           (new Column('is_visible', 'tinyint'))->default(1),           // UI дээр харагдах эсэх
           (new Column('is_active', 'tinyint'))->default(1),            // Идэвхтэй эсэх
            new Column('created_at', 'datetime'),
            new Column('created_by', 'bigint'),
            new Column('updated_at', 'datetime'),
            new Column('updated_by', 'bigint')
        ]);

        // --- Олон хэл дээрх контент талбар ---
        $this->setContentColumns([
            new Column('title', 'varchar', 128)  // Менюгийн харагдах нэр
        ]);

        $this->setTable('raptor_menu');
    }

    /**
     * Анхны тохиргоо (__initial)
     *
     * Энэ функц нь:
     *   - FK хамаарлуудыг зурж өгнө
     *   - Dashboard-д харагдах үндсэн меню, дэд менюг автоматаар seed хийнэ:
     *        Contents -> Pages / News / Files / Localization / Reference tables / Settings / Website
     *        System -> Users / Organizations / Logs
     *
     * @return void
     */
    protected function __initial()
    {
        $table = $this->getName();

        $this->setForeignKeyChecks(false);

        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        // created_by / updated_by -> Users FK
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_created_by
                     FOREIGN KEY (created_by) REFERENCES $users(id)
                     ON DELETE SET NULL ON UPDATE CASCADE");
        $this->exec("ALTER TABLE $table ADD CONSTRAINT {$table}_fk_updated_by
                     FOREIGN KEY (updated_by) REFERENCES $users(id)
                     ON DELETE SET NULL ON UPDATE CASCADE");

        $this->setForeignKeyChecks(true);

        // Цэсний мод бүтэц, эрэмбэлэлтийн гүйцэтгэлийг сайжруулах индекс
        $this->exec("CREATE INDEX {$table}_idx_parent_id ON $table (parent_id)");

        DashboardMenus::seed($this);
    }

    /**
     * Insert хийж буй үед created_at автоматаар бөглөгдөнө.
     *
     * @param array $record  Үндсэн хүснэгтийн өгөгдөл
     * @param array $content Олон хэлний контент
     * @return array|false Амжилттай бол бичлэгийн массив, бусад тохиолдолд false
     */
    public function insert(array $record, array $content): array|false
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record, $content);
    }

    /**
     * Update хийх үед updated_at автоматаар шинэчлэгдэнэ.
     *
     * @param int   $id      Бичлэгийн ID
     * @param array $record  Үндсэн хүснэгтийн өгөгдөл
     * @param array $content Олон хэлний контент
     * @return array|false Амжилттай бол шинэчилсэн бичлэг, бусад тохиолдолд false
     */
    public function updateById(int $id, array $record, array $content): array|false
    {
        $record['updated_at'] ??= \date('Y-m-d H:i:s');
        return parent::updateById($id, $record, $content);
    }
}
