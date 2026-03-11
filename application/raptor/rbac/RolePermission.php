<?php

namespace Raptor\RBAC;

use codesaur\DataObject\Model;
use codesaur\DataObject\Column;

/**
 * RolePermission - RBAC системийн "роль <-> эрх (permission)" холболтын модель.
 *
 * RBAC архитектур дахь байр суурь:
 * ---------------------------------------------------------------
 *  - Permission  = Нэгж үйлдлийн эрх (жишээ: user_insert)
 *  - Role        = Permission багцууд (жишээ: admin)
 *  - UserRole    = Хэрэглэгч -> Role холболт
 *  - RolePermission = Role -> Permission холболт (энэ файл)
 *
 * RolePermission хүснэгт нь:
 *    -> Аль role ямар permission-тэй вэ?
 *    -> Нэг role-д хэдэн permission байх боломжтой?
 *    -> Permission-г role-оор дамжуулж хэрэглэгчид оноох
 *
 * Үүнийг тодорхойлдог гол хүснэгт юм.
 *
 *
 * Хүснэгтийн баганууд:
 * ---------------------------------------------------------------
 * id              - bigint, primary key
 *
 * role_id         - FK -> rbac_roles.id  
 *                    Role (эрхийн багц)
 *
 * permission_id   - FK -> rbac_permissions.id  
 *                    Permission (нэгж эрх)
 *
 * alias           - varchar(64), not null  
 *                    Role дотор энэ permission ямар ангилалд багтахыг илэрхийлнэ.
 *                    (Жишээ: "system", "general", "content", "user" гэх мэт)
 *
 * created_at      - datetime  
 * created_by      - FK -> users.id  
 *                    Permission-г role-д хэн нэмсэн бэ (audit trail)
 *
 *
 * FK хамаарал ба Cascade зан төлөв:
 * ---------------------------------------------------------------
 *  role_id -> roles.id
 *      ON DELETE CASCADE  
 *      -> Role уствал түүнд хамаарах бүх permission холбоос устгана.
 *
 *  permission_id -> permissions.id
 *      ON DELETE CASCADE  
 *      -> Permission уствал түүнд хамаарах бүх role mapping устгана.
 *
 *  created_by -> users.id
 *      ON DELETE SET NULL  
 *      -> Permission-г role-д хэн нэмсэнийг устгасан ч лог үлдэнэ.
 *
 *
 * __initial() hook:
 * ---------------------------------------------------------------
 * Модель анх бий болох үед FK constraint-үүдийг үүсгэнэ:
 *   - role_id FK
 *   - permission_id FK
 *   - created_by FK
 *
 * Анхны өгөгдөл (seed) үүсгэдэггүй - permissions болон roles
 * аль хэдийн үүссэн үед энэ хүснэгт ашиглагдаж эхэлдэг.
 *
 *
 * Security:
 * ---------------------------------------------------------------
 *  - RBAC зөв ажиллахын тулд энэ хүснэгт integrity маш өндөр байх ёстой
 *  - Cascade delete нь orphan mapping үүсэхээс хамгаална
 *  - created_by талбар нь audit trail үүрэгтэй
 *
 *
 * @package Raptor RBAC
 */
class RolePermission extends Model
{
    /**
     * RolePermission модель - хүснэгтийн бүтцийг анх тодорхойлох.
     *
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        $this->setColumns([
           (new Column('id',             'bigint'))->primary(),
           (new Column('role_id',        'bigint'))->notNull(),
           (new Column('permission_id',  'bigint'))->notNull(),
           (new Column('alias',          'varchar', 64))->notNull(),
            new Column('created_at',     'datetime'),
            new Column('created_by',     'bigint')
        ]);

        $this->setTable('rbac_role_permission');
    }

    /**
     * __initial() - Хүснэгт шинээр үүсэх үед FK constraint-уудыг нэмэх hook.
     *
     * FK:
     *   rbac_role_permission.role_id
     *       -> rbac_roles.id
     *       ON DELETE CASCADE
     *       ON UPDATE CASCADE
     *
     *   rbac_role_permission.permission_id
     *       -> rbac_permissions.id
     *       ON DELETE CASCADE
     *       ON UPDATE CASCADE
     *
     *   rbac_role_permission.created_by
     *       -> users.id
     *       ON DELETE SET NULL
     *       ON UPDATE CASCADE
     *
     * @return void
     */
    protected function __initial()
    {
        $table = $this->getName();

        $this->setForeignKeyChecks(false);

        $roles       = (new Roles($this->pdo))->getName();
        $permissions = (new Permissions($this->pdo))->getName();
        $users       = (new \Raptor\User\UsersModel($this->pdo))->getName();
        $this->exec("
            ALTER TABLE $table
            ADD CONSTRAINT {$table}_fk_role_id
            FOREIGN KEY (role_id) REFERENCES $roles(id)
            ON DELETE CASCADE ON UPDATE CASCADE
        ");
        $this->exec("
            ALTER TABLE $table
            ADD CONSTRAINT {$table}_fk_permission_id
            FOREIGN KEY (permission_id) REFERENCES $permissions(id)
            ON DELETE CASCADE ON UPDATE CASCADE
        ");
        $this->exec("
            ALTER TABLE $table
            ADD CONSTRAINT {$table}_fk_created_by
            FOREIGN KEY (created_by) REFERENCES $users(id)
            ON DELETE SET NULL ON UPDATE CASCADE
        ");

        $this->setForeignKeyChecks(true);

        // Роль бүрт permission оноох seed
        $nowdate = \date('Y-m-d H:i:s');

        // coder: permission шаардахгүй - бүх газар шууд нэвтрэх эрхтэй

        // admin: development-аас бусад бүх permission
        $this->exec("
            INSERT INTO $table(created_at, role_id, permission_id, alias)
            SELECT '$nowdate', r.id, p.id, p.alias
            FROM $roles r, $permissions p
            WHERE r.name = 'admin'
        ");

        // manager: хэрэглэгч, байгууллага, контент, орчуулга удирдах
        $this->exec("
            INSERT INTO $table(created_at, role_id, permission_id, alias)
            SELECT '$nowdate', r.id, p.id, p.alias
            FROM $roles r, $permissions p
            WHERE r.name = 'manager' AND p.name IN (
                'logger',
                'user_index','user_insert','user_update','user_organization_set',
                'organization_index','organization_update',
                'content_settings','content_index','content_insert',
                'content_update','content_publish','content_delete',
                'product_index','product_insert','product_update',
                'product_publish','product_delete',
                'localization_index','localization_insert','localization_update',
                'templates_index',
                'development'
            )
        ");

        // editor: контент үүсгэх, засах, нийтлэх
        $this->exec("
            INSERT INTO $table(created_at, role_id, permission_id, alias)
            SELECT '$nowdate', r.id, p.id, p.alias
            FROM $roles r, $permissions p
            WHERE r.name = 'editor' AND p.name IN (
                'content_index','content_insert','content_update','content_publish',
                'product_index','product_insert','product_update','product_publish',
                'localization_index',
                'templates_index'
            )
        ");

        // viewer: зөвхөн харах эрх
        $this->exec("
            INSERT INTO $table(created_at, role_id, permission_id, alias)
            SELECT '$nowdate', r.id, p.id, p.alias
            FROM $roles r, $permissions p
            WHERE r.name = 'viewer' AND p.name IN (
                'content_index',
                'product_index',
                'localization_index'
            )
        ");
    }

    /**
     * insert() - Permission-г Role-д холбох үед created_at автоматаар тохируулах.
     *
     * @param array $record
     * @return array|false
     */
    public function insert(array $record): array|false
    {
        $record['created_at'] ??= \date('Y-m-d H:i:s');
        return parent::insert($record);
    }
}
