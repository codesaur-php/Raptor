<?php

namespace Raptor\RBAC;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * Permissions - RBAC эрхийн системийн үндсэн модель.
 *
 * Энэ хүснэгт нь системийн бүх боломжит үйлдэл (permission)-ийг
 * нэр, alias, module, тайлбар зэрэг мета мэдээллийн хамт хадгалдаг.
 *
 * RBAC архитектур дахь үүрэг:
 * ---------------------------------------------------------------
 *  - Permission: "юу хийх эрхтэй вэ?" (жишээ: user_insert, content_delete)
 *  - Role: Permission-үүдийн багц (жишээ: admin, editor, viewer)
 *  - UserRole: хэрэглэгч -> role холболт
 *
 * Permissions хүснэгт нь системд ажиллах бүх эрхийн
 * жагсаалтын authoritative source болно.
 *
 *
 * Баганууд:
 * ---------------------------------------------------------------
 * id            - bigint, primary key
 *
 * name          - string (128)  
 *                 Permission нэр (unique).  
 *                 Жишээ: "user_insert", "organization_delete"
 *
 * module        - string (128)  
 *                 Permission ямар модульд харьяалагдах.  
 *                 Жишээ: "user", "organization", "content"
 *
 * description   - string (255)  
 *                 Permission-ийн тайлбар (UI болон документацид хэрэглэгдэнэ)
 *
 * alias         - string (64), notNull  
 *                 Permission-ийн функционал ангилал.  
 *                 Жишээ: "system", "general"
 *
 * created_at    - datetime  
 * created_by    - FK -> users.id  
 *                 Permission-г үүсгэсэн хэрэглэгч.  
 *
 *
 * __initial(): анхны Permission seed үүсгэнэ
 * ---------------------------------------------------------------
 * Модель анх үүссэн үед (хүснэгт шинээр үүсэх үед) default permission-үүдийг
 * систем автоматаар бүртгэнэ.
 *
 * Эдгээр нь:
 *  - system logger permission
 *  - RBAC permissions
 *  - user management permissions
 *  - organization management permissions
 *  - content (page/news/file/settings) permissions
 *  - product (shop) permissions
 *  - localization permissions
 *
 * Энэ нь framework-ийг суурилуулахад шаардлагатай үндсэн эрхүүдийг
 * автоматаар бүртгэх зориулалттай.
 *
 *
 * Security онцлогууд:
 * ---------------------------------------------------------------
 *  - Permission name нь unique -> давхардлаас хамгаалсан
 *  - created_by -> users.id FK -> audit trail
 *  - Seed permission-үүдийг хэтрүүлэн өөрчлөхөөс model хамгаална
 *  - Permissions хүснэгт нь хэрэглэгчидтэй шууд холбогдохгүй,
 *    role дээр дамжуулж хэрэглэгдэнэ
 *
 */
class Permissions extends Model
{
    /**
     * Reserved permission key-үүд - permission болгон үүсгэхийг хориглоно.
     *
     * Эдгээр "{alias}_{name}" key нь RBAC role нэртэй давхцдаг. Жишээ нь
     * `system_coder` нь sidebar цэс болон бусад газар isUserCan('system_coder')
     * хэлбэрээр шүүгддэг ба зөвхөн coder ROLE-ийг илэрхийлэх ёстой (coder бол
     * бүх эрхийг давдаг тул тухайн шалгалт үнэн буцаадаг). Хэрэв `system_coder`
     * нэртэй бодит permission үүсээд дурын роль-д оноогдвол тэр роль-той
     * хэрэглэгч coder-only цэс/үйлдлийг харах/гүйцэтгэх эрсдэлтэй болно.
     *
     * @var string[]
     */
    public const RESERVED = ['system_coder'];

    /**
     * Permission модель үүсгэх - хүснэгт ба багануудыг тодорхойлох.
     *
     * @param \PDO $pdo  PDO instance
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id',          'bigint'))->primary(),
           (new Column('name',        'varchar', 128))->notNull(),
           (new Column('module',      'varchar', 128))->default('general'),
            new Column('description', 'varchar', 255),
           (new Column('alias',       'varchar', 64))->notNull(),
            new Column('created_at',  'datetime'),
            new Column('created_by',  'bigint')
        ]);

        $this->setTable('rbac_permissions');
    }

    /**
     * Permission хүснэгт шинээр үүсэх үед FK үүсгэж,
     * PermissionsSeed-ээр анхны permission-уудыг seed хийнэ.
     *
     * @return void
     *
     * @see PermissionsSeed::seed()
     */
    protected function __initial()
    {
        $table = $this->getName();
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $this->exec("
            ALTER TABLE $table
            ADD CONSTRAINT {$table}_fk_created_by
            FOREIGN KEY (created_by)
            REFERENCES $users(id)
            ON DELETE SET NULL
            ON UPDATE CASCADE
        ");
        
        // Permission key нь "{alias}_{name}" форматтай тул нэг name өөр өөр
        // alias дор давтагдаж болно (жишээ: system_request_update ба
        // common_request_update). Тиймээс unique-г (name) биш (alias, name)
        // хосоор тавина - эс бөгөөс ижил name-тэй хоёр permission зэрэг
        // оршиж чадахгүй.
        $this->exec("
            ALTER TABLE $table
            ADD CONSTRAINT {$table}_uq_alias_name
            UNIQUE (alias, name)
        ");

        PermissionsSeed::seed($table, $this->pdo);
    }

    /**
     * insert() - Permission бүртгэх үед created_at автоматаар тохируулах.
     *
     * @param array $record
     * @return array
     * @throws \RuntimeException reserved permission, буруу alias, эсвэл давхардсан бол
     */
    public function insert(array $record): array
    {
        $this->assertValidIdentity($record);
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record);
    }

    /**
     * updateById() - Permission шинэчлэх.
     *
     * @param int   $id
     * @param array $record
     * @return array
     * @throws \RuntimeException reserved permission, буруу alias, эсвэл давхардсан бол
     */
    public function updateById(int $id, array $record): array
    {
        $this->assertValidIdentity($record, $id);
        return parent::updateById($id, $record);
    }

    /**
     * Permission identity (alias, name)-ийн бүрэн бүтэн байдлыг шалгах.
     *
     * Гурван зүйл баталгаажуулна:
     *  1) "{alias}_{name}" key нь reserved биш (RESERVED константыг үз). Reserved
     *     key нь RBAC role нэртэй давхцдаг тул permission болгон үүсгэвэл эрхийн
     *     шалгалт буруу ажиллах эрсдэлтэй.
     *  2) alias нь underscore агуулахгүй. Permission key нь тусгаарлагчгүй
     *     "{alias}_{name}" хэлбэрээр угсардаг тул alias дотор "_" байвал
     *     (system_request, update) ба (system, request_update) гэсэн өөр өөр
     *     хоёр мөр ижил key руу мөргөлдөх эрсдэлтэй. alias нь sidebar menu-тэй
     *     уялдсан цэвэр grouping утга (system, common ...) тул "_" хэрэггүй.
     *  3) (alias, name) хос давхардаагүй. UNIQUE(alias, name) constraint руу
     *     хүрэхээс өмнө ойлгомжтой алдаа буцааж, raw SQL exception-ийг
     *     хэрэглэгчид харуулахгүй.
     *
     * @param array    $record
     * @param int|null $excludeId  updateById үед өөрийн мөрийг алгасах ID
     * @return void
     * @throws \RuntimeException reserved permission, alias буруу, эсвэл (alias, name) давхардсан бол
     */
    private function assertValidIdentity(array $record, ?int $excludeId = null): void
    {
        $alias = $record['alias'] ?? '';
        $name  = $record['name'] ?? '';
        if ($alias === '' || $name === '') {
            return;
        }
        if (\in_array("{$alias}_{$name}", self::RESERVED, true)) {
            throw new \RuntimeException(
                "\"{$alias}_{$name}\" is a reserved permission (it is an RBAC role name) and cannot be created."
            );
        }
        if (\str_contains($alias, '_')) {
            throw new \RuntimeException('Permission alias cannot contain an underscore.');
        }

        $table  = $this->getName();
        $sql    = "SELECT id FROM $table WHERE alias = :alias AND name = :name";
        $params = [':alias' => $alias, ':name' => $name];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            throw new \RuntimeException("Permission \"{$alias}_{$name}\" already exists.");
        }
    }
}
