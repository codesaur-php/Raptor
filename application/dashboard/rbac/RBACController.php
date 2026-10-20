<?php

namespace Dashboard\RBAC;

use Psr\Log\LogLevel;

use codesaur\DataObject\Constants;

/**
 * RBACController - RBAC (Role-Based Access Control) модулийн
 * UI болон API-д зориулсан үндсэн контроллер.
 *
 * Үндсэн үүрэг:
 * ---------------------------------------------------------------
 *  - RBAC alias бүрийн роль, зөвшөөрлийн (permission) жагсаалт харуулах
 *  - Role үүсгэх (GET: form, POST: insert)
 *  - Permission үүсгэх (GET: form, POST: insert)
 *  - Role <-> Permission холболт хийх, устгах
 *  - Тухайн рольд хамаарах дэлгэрэнгүйг харах (modal)
 *
 * Security:
 * ---------------------------------------------------------------
 *  - Бүх RBAC удирдлагын үйлдэлд "system_rbac" permission заавал шалгана.
 *  - Permission-гүй хэрэглэгчдэд DashboardTrait ашиглан
 *    "no permission" alert/modal үзүүлнэ.
 *
 * UI Rendering Pipeline:
 * ---------------------------------------------------------------
 *  - DashboardTrait::dashboardTemplate() ашиглан sidebar + content бүхий layout
 *  - template() ашиглан modal/forms рендерлэх
 *
 * Logging (audit trail):
 * ---------------------------------------------------------------
 *  - log() ашиглан dashboard_log хүснэгтэд бүх үйлдлийг бүртгэнэ.
 *  - Амжилттай/алдаатай аль ч тохиолдолд finally дотор лог бичигдэнэ.
 *
 * RBACController нь:
 *    -> RBACRouter-оор дамжин бүх RBAC админ интерфэйс рүү ханддаг
 *    -> Roles, Permissions, RolePermission модельтэй шууд ажиллана
 *    -> RBAC UI-г бүрдүүлдэг хамгийн гол контроллер юм.
 */
class RBACController extends \Dashboard\Controller
{
    use \Dashboard\Template\DashboardTrait;

    /**
     * RBAC alias жагсаалт (role + permission matrix) үзүүлэх.
     *
     * URL жишээ:
     *   /dashboard/organizations/rbac/alias?alias=user&title=User+RBAC
     *
     * Workflow:
     * ---------------------------------------------------------------
     * 1) "system_rbac" permission шалгах
     * 2) Query параметрээс alias болон title авах
     * 3) Roles (coder-role-оос бусад)-ийг alias-аар шүүх
     * 4) Permissions-ийг alias-аар шүүх
     * 5) RolePermission mapping-ийг бүдүүвч байдлаар авах
     * 6) Dashboard layout руу rbac-alias.html template дамжуулах
     * 7) Алдаа гарвал dashboardProhibited() руу шилжих
     *
     * @return void
     */
    public function alias()
    {
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $queryParams = $this->getQueryParams();
            $alias = $queryParams['alias'] ?? null;
            if (empty($alias)) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            $title = $queryParams['title'] ?? null;

            // Roles жагсаалт
            $roles_table = (new Roles($this->pdo))->getName();
            $select_roles = $this->prepare(
                "SELECT id,name,description FROM $roles_table
                 WHERE alias=:alias AND NOT (name='coder' AND alias='system')"
            );
            $select_roles->bindParam(':alias', $alias);
            $select_roles->execute();
            $roles = $select_roles->fetchAll() ?: [];

            // Permissions жагсаалт
            $permissions_table = (new Permissions($this->pdo))->getName();
            $select_perms = $this->prepare(
                "SELECT id,name,description FROM $permissions_table
                 WHERE alias=:alias ORDER BY module"
            );
            $select_perms->bindParam(':alias', $alias);
            $select_perms->execute();
            $permissions = $select_perms->fetchAll() ?: [];
            // Coder биш хэрэглэгч зөвхөн өөрт байгаа эрхийг өөрчилнө (setRolePermission()-тэй ижил дүрэм)
            $locked_permissions = [];
            if (!$this->isUser('system_coder')) {
                foreach ($permissions as $permission) {
                    if (!$this->isUserCan("{$alias}_{$permission['name']}")) {
                        $locked_permissions[$permission['id']] = true;
                    }
                }
            }

            // RolePermission mapping
            $role_perm_table = (new RolePermission($this->pdo))->getName();
            $select_rp = $this->prepare(
                "SELECT role_id,permission_id FROM $role_perm_table WHERE alias=:alias"
            );
            $select_rp->bindParam(':alias', $alias);
            $role_permission = [];
            if ($select_rp->execute()) {
                foreach ($select_rp->fetchAll() as $row) {
                    $role_permission[$row['role_id']][$row['permission_id']] = true;
                }
            }

            // Dashboard руу рендерлэх
            $dashboard = $this->dashboardTemplate(
                __DIR__ . '/rbac-alias.html',
                [
                    'alias'           => $alias,
                    'title'           => $title,
                    'roles'           => $roles,
                    'permissions'     => $permissions,
                    'role_permission' => $role_permission,
                    'locked_permissions' => $locked_permissions,
                ]
            );
            $dashboard->set('title', "RBAC - $alias");
            $dashboard->render();
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            // Audit log
            $context = ['action' => 'rbac-alias'];
            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = 'RBAC жагсаалтыг нээх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'RBAC [{alias}] жагсаалтыг үзэж байна';
                $context += ['alias' => $alias];
            }
            $this->log('dashboard', $level, $message, $context);
        }
    }

    /**
     * Role үүсгэх (GET: form, POST: insert).
     *
     * URL:
     *   GET  /dashboard/organizations/rbac/{alias}/insert/role
     *   POST /dashboard/organizations/rbac/{alias}/insert/role
     *
     * POST Workflow:
     * ---------------------------------------------------------------
     * 1) Permission шалгах (system_rbac)
     * 2) Request payload баталгаажуулах
     * 3) New Role insert (alias + created_by)
     * 4) JSON success response буцаах
     *
     * GET Workflow:
     * ---------------------------------------------------------------
     * 1) Modal form (rbac-insert-role-modal.html) рендерлэх
     *
     * @param string $alias RBAC бүлгийн alias (system, user, content ...)
     * @return void
     */
    public function insertRole(string $alias)
    {
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $payload = $this->getParsedBody();
            $title   = $this->getQueryParams()['title'] ?? '';
            if ($this->getRequest()->getMethod() == 'POST') {
                // Role insert
                $record = (new Roles($this->pdo))->insert(
                    $payload + ['alias' => $alias, 'created_by' => $this->getUserId()]
                );
                if (empty($record)) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                if ($this->hasService('cache')) {
                    $this->getService('cache')->clear();
                }
                $this->respondJSON([
                    'status'  => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
            } else {
                // Modal form render
                $this->template(
                    __DIR__ . '/rbac-insert-role-modal.html',
                    ['alias' => $alias, 'title' => $title]
                )->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON([
                    'status'  => 'error',
                    'title'   => $this->text('error'),
                    'message' => $err->getMessage(),
                ], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            // Audit log
            $context = ['action' => 'rbac-create-role', 'alias' => $alias];
            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = 'RBAC дүр үүсгэх явцад алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'POST') {
                $level = LogLevel::INFO;
                $message = 'RBAC дүр [{record.name}] амжилттай үүсгэлээ';
                $context += ['record_id' => $record['id'], 'record' => $record];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'RBAC дүр үүсгэх үйлдлийг эхлүүллээ';
            }
            $this->log('dashboard', $level, $message, $context);
        }
    }

    /**
     * Дүрийн дэлгэрэнгүйг харах (modal).
     *
     * URL:
     *   /dashboard/organizations/rbac/role/view?role=system_coder
     *
     * @return void
     */
    public function viewRole()
    {
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $roleKey = $this->getQueryParams()['role'] ?? '';
            $values  = ['role' => $roleKey];

            // Role-г alias_name байдлаар lookup хийх
            $roles_table = (new Roles($this->pdo))->getName();
            $concat_expr = ($this->getDriverName() == Constants::DRIVER_PGSQL)
                ? "alias || '_' || name"
                : "CONCAT_WS('_',alias,name)";
            $select_role = $this->prepare(
                "SELECT * FROM $roles_table
                 WHERE $concat_expr=:role
                 ORDER BY id DESC LIMIT 1"
            );
            $select_role->bindParam(':role', $roleKey);
            $select_role->execute();
            $record = $select_role->fetch();
            if (empty($record)) {
                throw new \Exception('Record not found', 404);
            }
            $values += $record;

            // Тухайн role-д оноогдсон permission-уудыг авах
            $rp_table = (new RolePermission($this->pdo))->getName();
            $perms_table = (new Permissions($this->pdo))->getName();
            $stmt = $this->prepare(
                "SELECT p.name, p.module, p.description
                 FROM $rp_table rp
                 JOIN $perms_table p ON rp.permission_id = p.id
                 WHERE rp.role_id = :role_id AND rp.alias = :alias
                 ORDER BY p.module, p.name"
            );
            $stmt->bindValue(':role_id', $record['id'], \PDO::PARAM_INT);
            $stmt->bindValue(':alias', $record['alias']);
            $stmt->execute();
            $values['permissions'] = $stmt->fetchAll();

            $this->template(
                __DIR__ . '/rbac-view-role-modal.html',
                $values
            )->render();

        } catch (\Throwable $err) {
            $this->modalProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'rbac-view-role'];
            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = 'RBAC дүрийн мэдээлэл нээх үед алдаа гарлаа';
                $context += ['error' => ['code'=>$err->getCode(), 'message'=>$err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'RBAC дүр [{record.name}] дэлгэрэнгүйг үзэж байна';
                $context += [
                    'alias'     => $record['alias'],
                    'record_id' => $record['id'],
                    'record'    => $record
                ];
            }
            $this->log('dashboard', $level, $message, $context);
        }
    }

    /**
     * Permission үүсгэх (GET: form, POST: insert).
     *
     * GET:
     *   - Зөвхөн modal form рендерлэх
     *   - Тухайн alias-ийн permission module-уудын жагсаалт авч өгөх
     *
     * POST:
     *   - Permission-г insert хийнэ
     *   - created_by = current user
     *   - JSON амжилттай мессеж буцаах
     *
     * @param string $alias
     * @return void
     */
    public function insertPermission(string $alias)
    {
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $payload = $this->getParsedBody();
            $title   = $this->getQueryParams()['title'] ?? '';
            if ($this->getRequest()->getMethod() == 'POST') {
                $record = (new Permissions($this->pdo))->insert(
                    $payload + ['alias' => $alias, 'created_by' => $this->getUserId()]
                );
                if (empty($record)) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                if ($this->hasService('cache')) {
                    $this->getService('cache')->clear();
                }
                $this->respondJSON([
                    'status'  => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
            } else {
                // Module-уудын жагсаалт (UI dropdown)
                $permissions_table = (new Permissions($this->pdo))->getName();
                $select_modules = $this->prepare(
                    "SELECT DISTINCT module FROM $permissions_table
                     WHERE alias=:alias AND module<>''"
                );
                $select_modules->bindParam(':alias', $alias);
                $select_modules->execute();
                $modules = $select_modules->fetchAll() ?: [];

                $this->template(
                    __DIR__ . '/rbac-insert-permission-modal.html',
                    ['alias' => $alias, 'title' => $title, 'modules' => $modules]
                )->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'POST') {
                $this->respondJSON([
                    'status'  => 'error',
                    'title'   => $this->text('error'),
                    'message' => $err->getMessage()
                ], $err->getCode());
            } else {
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            // Audit log
            $context = ['action' => 'rbac-create-permission', 'alias' => $alias];
            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = 'RBAC зөвшөөрөл үүсгэх үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message'=>$err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'POST') {
                $level = LogLevel::INFO;
                $message = 'RBAC зөвшөөрөл [{record.name}] амжилттай үүсгэлээ';
                $context += ['record_id'=>$record['id'], 'record'=>$record];
            } else {
                $level = LogLevel::NOTICE;
                $message = 'RBAC зөвшөөрөл үүсгэх үйлдлийг эхлүүллээ';
            }
            $this->log('dashboard', $level, $message, $context);
        }
    }

    /**
     * Роль -> Зөвшөөрөл (permission) холболт хийх/устгах.
     *
     * POST   -> холбох  
     * DELETE -> салгах
     *
     * Payload шаардлага:
     *   - alias
     *   - role_id
     *   - permission_id
     *
     * @param string $alias RBAC бүлгийн alias
     * @return void
     */
    public function setRolePermission(string $alias)
    {
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $parsedBody = $this->getParsedBody();
            if (empty($alias)
                || empty($parsedBody['role_id'])
                || empty($parsedBody['permission_id'])
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }

            $payload = [
                'alias'         => $alias,
                'role_id'       => $parsedBody['role_id'],
                'permission_id' => $parsedBody['permission_id']
            ];
            $model = new RolePermission($this->pdo);
            $row   = $model->getRowWhere($payload);
            $method = $this->getRequest()->getMethod();

            // Coder биш хэрэглэгч зөвхөн өөрт байгаа эрхийг дүрд нэмж, хасна, мөн өөрийн
            // удирдлагын эрхийг (Permissions::MANAGEMENT) өөрийн дүрээс хасч өөрийгөө түгжихгүй
            $this->assertRolePermissionChangeAllowed(
                (int) $payload['role_id'],
                (int) $payload['permission_id'],
                $method === 'DELETE'
            );
            if ($method === 'POST') {
                // Assign
                if (empty($row) && $model->insert($payload)) {
                    if ($this->hasService('cache')) {
                        $this->getService('cache')->clear();
                    }
                    return $this->respondJSON([
                        'type'    => 'success',
                        'message' => $this->text('record-insert-success')
                    ]);
                }
            } elseif ($method === 'DELETE') {
                // Revoke
                if (isset($row['id']) && $model->deleteById($row['id'])) {
                    if ($this->hasService('cache')) {
                        $this->getService('cache')->clear();
                    }
                    return $this->respondJSON([
                        'type'    => 'primary',
                        'message' => $this->text('record-successfully-deleted')
                    ]);
                }
            }

            throw new \Exception($this->text('invalid-values'), 400);
        } catch (\Throwable $err) {
            // type нь клиент талд Notify()-д шууд дамждаг тул зөвхөн
            // success/danger/warning/primary утга байх ёстой
            $this->respondJSON([
                'type'    => 'danger',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            // Audit log
            $context = ['action' => 'rbac-set-role-permission', 'alias' => $alias];
            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = 'Role -> Permission тохируулах үед алдаа гарлаа';
                $context += ['error' => ['code'=>$err->getCode(),'message'=>$err->getMessage()]];
            } else {
                $level = LogLevel::INFO;
                $context += $payload;
                $message = 'Role -> Permission ' . ($method === 'DELETE'
                    ? 'устгагдлаа'
                    : 'заагдлаа');
            }
            $this->log('dashboard', $level, $message, $context);
        }
    }

    /**
     * Role <-> Permission өөрчлөлт нэвтэрсэн хэрэглэгчийн эрхийн хүрээнд байгаа эсэх.
     *
     * Coder биш хэрэглэгч:
     *   * зөвхөн өөрт байгаа эрхийг дүрд нэмж, хасна - эс бөгөөс system_rbac эзэмшигч
     *     өөрийн дүрд дурын эрх нэмж эрхээ хэтрүүлэх боломжтой болно
     *   * өөрийн эзэмшдэг дүрээс Permissions::MANAGEMENT эрхийг хасаад тэр эрх өөр
     *     ямар ч дүрээр нь үлдэхгүй бол хориглоно (өөрийгөө түгжихгүй)
     *
     * Security (English): a non-coder may only assign or revoke permissions
     * they hold themselves (no self-escalation through the role matrix), and
     * may not strip a management permission from themselves.
     *
     * @param int  $roleId
     * @param int  $permissionId
     * @param bool $revoke  DELETE (хасах) эсэх
     * @return void
     * @throws \Exception 403
     */
    private function assertRolePermissionChangeAllowed(int $roleId, int $permissionId, bool $revoke): void
    {
        if ($this->isUser('system_coder')) {
            return;
        }

        $permission = (new Permissions($this->pdo))->getRowWhere(['id' => $permissionId]);
        if (!isset($permission['id'])) {
            throw new \InvalidArgumentException($this->text('invalid-values'), 400);
        }
        $key = "{$permission['alias']}_{$permission['name']}";
        if (!$this->isUserCan($key)) {
            throw new \Exception($this->text('permission-not-own') . ": $key", 403);
        }

        if ($revoke && \in_array($key, Permissions::MANAGEMENT, true)) {
            // Энэ мөрийг хассаны дараа хэрэглэгчийн бусад дүрээр тэр эрх үлдэх эсэх
            $user_role = (new UserRole($this->pdo))->getName();
            $role_permission = (new RolePermission($this->pdo))->getName();
            $stmt = $this->prepare(
                "SELECT 1 FROM $user_role as ur INNER JOIN $role_permission as rp ON rp.role_id=ur.role_id " .
                'WHERE ur.user_id=:user AND rp.permission_id=:perm AND rp.role_id<>:role'
            );
            $stmt->bindValue(':user', $this->getUserId(), \PDO::PARAM_INT);
            $stmt->bindValue(':perm', $permissionId, \PDO::PARAM_INT);
            $stmt->bindValue(':role', $roleId, \PDO::PARAM_INT);
            $stmt->execute();
            $holds_role = !empty((new UserRole($this->pdo))->getRowWhere([
                'user_id' => $this->getUserId(),
                'role_id' => $roleId
            ]));
            if ($holds_role && !$stmt->fetch()) {
                throw new \Exception($this->text('cannot-remove-own-management-permission') . ": $key", 403);
            }
        }
    }
}
