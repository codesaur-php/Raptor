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
     * Permission модель үүсгэх - хүснэгт ба багануудыг тодорхойлох.
     *
     * @param \PDO $pdo  PDO instance
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        
        $this->setColumns([
           (new Column('id',          'bigint'))->primary(),
           (new Column('name',        'varchar', 128))->unique()->notNull(),
           (new Column('module',      'varchar', 128))->default('general'),
            new Column('description', 'varchar', 255),
           (new Column('alias',       'varchar', 64))->notNull(),
            new Column('created_at',  'datetime'),
            new Column('created_by',  'bigint')
        ]);

        $this->setTable('rbac_permissions');
    }

    /**
     * __initial() - Permission хүснэгт шинээр үүсэх үед FK болон анхны өгөгдөл үүсгэх hook.
     *
     * FK:
     *   rbac_permissions.created_by -> users.id
     *       ON DELETE SET NULL
     *       ON UPDATE CASCADE
     *
     * Анхны seed өгөгдөл:
     *   - logger
     *   - rbac
     *   - user_* permissions
     *   - organization_* permissions
     *   - content_* permissions
     *   - localization_* permissions
     *
     * Анхны эрхүүд нь системийн үндсэн модулиудыг ажиллуулахад зайлшгүй
     * шаардлагатай тул автоматоор үүсгэнэ.
     *
     * @return void
     */
    protected function __initial()
    {
        $table = $this->getName();

        $this->setForeignKeyChecks(false);
        $users = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $this->exec("
            ALTER TABLE $table
            ADD CONSTRAINT {$table}_fk_created_by
            FOREIGN KEY (created_by)
            REFERENCES $users(id)
            ON DELETE SET NULL
            ON UPDATE CASCADE
        ");
        $this->setForeignKeyChecks(true);

        // Seed үндсэн permission-үүд
        $nowdate = \date('Y-m-d H:i:s');
        $query =
            "INSERT INTO $table(created_at,alias,module,name,description)
            VALUES
            ('$nowdate','system','log','logger','View system access logs and activity history'),
            ('$nowdate','system','user','rbac','Manage roles and assign permissions to users'),
            ('$nowdate','system','user','user_index','View the list of registered users'),
            ('$nowdate','system','user','user_insert','Create new user accounts'),
            ('$nowdate','system','user','user_update','Edit existing user profiles and settings'),
            ('$nowdate','system','user','user_delete','Delete user accounts from the system'),
            ('$nowdate','system','user','user_organization_set','Assign or change a user organization'),

            ('$nowdate','system','organization','organization_index','View the list of organizations'),
            ('$nowdate','system','organization','organization_insert','Create new organizations'),
            ('$nowdate','system','organization','organization_update','Edit existing organization details'),
            ('$nowdate','system','organization','organization_delete','Delete organizations from the system'),

            ('$nowdate','system','content','content_settings','Manage site content settings and configuration'),
            ('$nowdate','system','content','content_index','View the list of content pages news and files'),
            ('$nowdate','system','content','content_insert','Create new content entries'),
            ('$nowdate','system','content','content_update','Edit existing content entries'),
            ('$nowdate','system','content','content_publish','Publish or unpublish content'),
            ('$nowdate','system','content','content_delete','Delete content entries'),

            ('$nowdate','system','product','product_index','View the list of products and orders'),
            ('$nowdate','system','product','product_insert','Create new product entries'),
            ('$nowdate','system','product','product_update','Edit existing products and update order status'),
            ('$nowdate','system','product','product_publish','Publish or unpublish products'),
            ('$nowdate','system','product','product_delete','Delete products and orders'),

            ('$nowdate','system','localization','localization_index','View localization and translation entries'),
            ('$nowdate','system','localization','localization_insert','Add new translation entries'),
            ('$nowdate','system','localization','localization_update','Edit existing translation entries'),
            ('$nowdate','system','localization','localization_delete','Delete translation entries'),

            ('$nowdate','system','template','templates_index','View and manage reference tables'),

            ('$nowdate','system','development','development','Manage all development requests and respond to others')
        ";
        $this->exec($query);
    }

    /**
     * insert() - Permission бүртгэх үед created_at автоматаар тохируулах.
     *
     * Хамгаалалт: `system` alias дээр `coder` нэртэй permission үүсгэхийг хориглоно.
     * `system_coder` нь RBAC role бөгөөд permission биш. Хэрэв ийм permission
     * үүсвэл sidebar-ийн `system_coder` permission filter буруу ажиллах эрсдэлтэй.
     *
     * @param array $record
     * @return array|false
     * @throws \RuntimeException system_coder permission үүсгэх оролдлого хийвэл
     */
    public function insert(array $record): array|false
    {
        if (($record['alias'] ?? '') === 'system' && ($record['name'] ?? '') === 'coder') {
            throw new \RuntimeException(
                'Cannot create "system_coder" permission: it is a reserved RBAC role name, not a permission.'
            );
        }
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record);
    }

    /**
     * updateById() - Permission шинэчлэх үед system_coder хамгаалалт.
     *
     * @param int   $id
     * @param array $record
     * @return array|false
     * @throws \RuntimeException system_coder permission болгох оролдлого хийвэл
     */
    public function updateById(int $id, array $record): array|false
    {
        if (($record['alias'] ?? '') === 'system' && ($record['name'] ?? '') === 'coder') {
            throw new \RuntimeException(
                'Cannot rename permission to "system_coder": it is a reserved RBAC role name, not a permission.'
            );
        }
        return parent::updateById($id, $record);
    }
}
