<?php

namespace Raptor\User;


use Psr\Log\LogLevel;

use codesaur\DataObject\Constants;
use codesaur\Template\MemoryTemplate;

use Raptor\Authentication\ForgotModel;
use Raptor\Authentication\SignupModel;
use Raptor\Content\FileController;
use Raptor\Organization\OrganizationModel;
use Raptor\Organization\OrganizationUserModel;
use Raptor\RBAC\UserRole;
use Raptor\RBAC\Roles;
use Raptor\Log\Logger;

/**
 * Class UsersController
 *
 * Хэрэглэгчийн бүртгэл, мэдээлэл засварлалт, RBAC дүрийн удирдлага,
 * байгууллагын хамаарал тохируулах, нууц үг солих зэрэг
 * хэрэглэгчтэй холбоотой бүх server-side логикийг агуулсан
 * Raptor Dashboard-ийн үндсэн Controller юм.
 *
 * --------------------------------------------------------------
 * Архитектур - PDO автоматаар хэрхэн ирдэг вэ?
 * --------------------------------------------------------------
 *  Raptor\Controller нь:
 *
 *      use \codesaur\DataObject\PDOTrait;
 *
 *  гэдэг trait-ийг ашигладаг. PDOTrait нь `$pdo` шинж чанарыг
 *  controller-ийн объект дээр үүсгэж өгдөг.
 *
 *  Framework-ийн түвшинд DatabaseConnectMiddleware нь:
 *
 *      $request = $request->withAttribute('pdo', $pdo);
 *
 *  гэж PSR-7 ServerRequest дотор `pdo` attribute-ийг суулгадаг.
 *
 *  Controller нь BaseController::__construct() дотор:
 *
 *      $this->pdo = $request->getAttribute('pdo');
 *
 *  хэлбэрээр автоматаар авч `$this->pdo` болгон тохируулдаг.
 *
 * Энэ механизмаар бүх Model-классуудыг:
 *      new UsersModel($this->pdo)
 *      new Roles($this->pdo)
 *      new OrganizationModel($this->pdo)
 *  гэх мэтээр шууд хэрэглэнэ.
 *
 * --------------------------------------------------------------
 * Хамааралтай модулиуд
 * --------------------------------------------------------------
 *  * UsersModel - хэрэглэгчийн үндсэн өгөгдлийн хүснэгт
 *  * RBAC => Roles / UserRole - RBAC дүрийн систем
 *  * OrganizationModel / OrganizationUserModel - байгууллагын хамаарал
 *  * SignupModel / ForgotModel - бүртгүүлэх болон нууц үг сэргээх хүсэлт
 *  * FileController - зураг upload удирдлага (profile photo)
 *  * Logger - үйлдлийн протокол
 *  * DashboardTrait - Dashboard template integration
 *
 * --------------------------------------------------------------
 * Аюулгүй байдал ба Permission
 * --------------------------------------------------------------
 *  * `isUserCan()` функцээр бүх үйлдэл зөвшөөрөл шалгадаг
 *  * Root хэрэглэгчийг хамгаална (id = 1)
 *  * Хэрэглэгч өөрийгөө устгах боломжгүй
 *  * RBAC aliases (common, org1, org2 ...) дагуу role binding
 *
 * --------------------------------------------------------------
 * Response төрөл
 * --------------------------------------------------------------
 *  * Dashboard UI (template)
 *  * JSON (AJAX хүсэлтүүдэд)
 *  * Modal templates
 *
 * --------------------------------------------------------------
 * Logging
 * --------------------------------------------------------------
 *  Бүх томоохон үйлдэл log() руу дараах бүтэцтэйгээр бичигдэнэ:
 *
 *      $this->log(
 *          'users',
 *          LogLevel::NOTICE | ERROR | ALERT,
 *          'Мессеж',
 *          ['action' => '...', 'id' => ..., 'record' => ...]
 *      );
 *
 * --------------------------------------------------------------
 * File upload
 * --------------------------------------------------------------
 *  FileController-с удамшдаг тул дараах боломжтой:
 *      $this->setFolder("/users/{$id}");
 *      $this->allowImageOnly();
 *      $photo = $this->moveUploaded('photo');
 *
 * --------------------------------------------------------------
 * Энэ класст багтах үндсэн үйлдлүүд:
 * --------------------------------------------------------------
 *  * index()               - хэрэглэгчийн Dashboard view
 *  * list()                - хэрэглэгчдийн JSON жагсаалт
 *  * insert()              - шинэ хэрэглэгч үүсгэх
 *  * update($id)           - хэрэглэгчийн мэдээлэл засварлах
 *  * view($id)             - хэрэглэгчийн дэлгэрэнгүй харах
 *  * deactivate()          - хэрэглэгчийг идэвхгүй болгох
 *  * requestsModal()       - signup / forgot хүсэлтүүдийн жагсаалт харах
 *  * signupApprove()       - хэрэглэгч шинээр бүртгүүлэх хүсэлтийг зөвшөөрөх
 *  * signupDeactivate()    - хэрэглэгч шинээр бүртгүүлэх хүсэлтийг устгах
 *  * setPassword($id)      - хэрэглэгчийн нууц үг тохируулах
 *  * setOrganization($id)  - хэрэглэгчийн байгууллага тохируулах
 *  * setRole($id)          - хэрэглэгчийн RBAC дүр тохируулах
 *
 * @package Raptor\User
 */
class UsersController extends FileController
{
    use \Raptor\Template\DashboardTrait;
    
    /**
     * Хэрэглэгчдийн жагсаалтын Dashboard хуудсыг нээх
     *
     * --------------------------------------------------------------
     * Үндсэн үүрэг
     * --------------------------------------------------------------
     *  - system_user_index эрхтэй эсэхийг шалгана
     *  - Dashboard layout ашиглан user-index.html темплейтийг
     *    render хийнэ
     *  - Хэрэв алдаа гарвал dashboardProhibited() ашиглан
     *    хэрэглэгчдэд ойлгомжтой error UI үзүүлнэ
     *
     * --------------------------------------------------------------
     * Permission logic
     * --------------------------------------------------------------
     *  Энэ хуудсыг зөвхөн `system_user_index` эрхтэй хэрэглэгч 
     *  нээх боломжтой. Хэрэв эрхгүй бол:
     *
     *      throw new \Exception($this->text('system-no-permission'), 401);
     *
     * --------------------------------------------------------------
     * Алдаа барих ба лог бичилт
     * --------------------------------------------------------------
     *  try/catch/finally блок:
     *
     *  try - UI-г хэвийн нээнэ  
     *  catch - алдаа гарвал Dashboard UI дээр error box харуулна  
     *  finally - log() руу протокол тэмдэглэнэ:
     *      - Амжилттай нээсэн -> LogLevel::NOTICE  
     *      - Алдаатай -> LogLevel::ERROR  
     *
     *  Логт дараах context орно:
     *      ['action' => 'index', ...]  
     *
     * --------------------------------------------------------------
     * Response
     * --------------------------------------------------------------
     *  - UI response (Dashboard)
     *
     * --------------------------------------------------------------
     * Ашиглагдах template:
     * --------------------------------------------------------------
     *  /application/raptor/user/user-index.html
     *
     * @return void
     */
    public function index()
    {
        try {
            // RBAC эрх шалгана - хэрэглэгчид хэрэглэгчийн жагсаалт үзэх эрх байх ёстой
            if (!$this->isUserCan('system_user_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            // Dashboard зориулалтын template-ээ ачаална
            $dashboard = $this->dashboardTemplate(__DIR__ . '/user-index.html');            
             // Гарчгийг локальчилж, template-руу дамжуулна
            $dashboard->set('title', $this->text('users'));
            $dashboard->render();
        } catch (\Throwable $err) {
            // Ямар нэгэн алдаа гарвал алдааны dashboard-г үзүүлнэ
            $this->dashboardProhibited(
                "Хэрэглэгчдийн жагсаалтыг нээх үед алдаа гарлаа.<br/><br/>{$err->getMessage()}",
                $err->getCode()
            )->render();
        } finally {
            // Энэ action-ийн лог протокол - амжилттай эсэхээс үл хамааран бичнэ
            $context = ['action' => 'index'];
            if (isset($err) && $err instanceof \Throwable) {
                // Алдаатай төгссөн тохиолдолд ERROR level
                $level = LogLevel::ERROR;
                $message = 'Хэрэглэгчдийн жагсаалтыг нээх үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                // Амжилттай үзсэн бол NOTICE level
                $level = LogLevel::NOTICE;
                $message = 'Хэрэглэгчдийн жагсаалтыг үзэж байна';
            }
            // users logger-д бичих
            $this->log('users', $level, $message, $context);
        }
    }
    
    /**
     * Хэрэглэгчдийн жагсаалтыг JSON хэлбэрээр буцаах API.
     *
     * Гол үүрэг:
     *  - RBAC эрх (`system_user_index`) шалгана
     *  - users хүснэгтээс үндсэн мэдээлэл татна
     *  - UserRole / Roles хүснэгтээр дамжуулж хэрэглэгч бүрийн ролуудыг нэгтгэнэ
     *  - OrganizationUser / Organization хүснэгтээр дамжуулж байгууллагын мэдээлэл нэмж нэгтгэнэ
     *  - Эцэст нь нэг массив болгон нэгтгээд JSON-р буцаана:
     *      [
     *          {
     *              id, username, email, is_active, roles[], organizations[]
     *          },
     *          ...
     *      ]
     *
     * Ашиглагдах үндсэн газар:
     *  - Admin Dashboard-ийн Users list UI (AJAX-аар хүсэлт явуулж table-г populate хийхэд)
     *
     * @return void
     */
    public function list()
    {
        try {
            // RBAC эрх шалгах - хэрэглэгчийн жагсаалт авах эрхтэй эсэх
            if (!$this->isUserCan('system_user_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            // users хүснэгтийн нэрийг UsersModel::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
            $table = (new UsersModel($this->pdo))->getName();
            $users_infos = $this->query(
                "SELECT id,photo,photo_size,last_name,first_name,username,phone,email,is_active FROM $table ORDER BY id"
            )->fetchAll();
            
            $users = [];
            foreach ($users_infos as $user) {
                $users[$user['id']] = $user;
            }
            
            // Хэрэглэгч бүрийн role-уудыг (alias_name) хэлбэрээр нэгтгэх
            // Хүснэгтийн нэрийг UserRole болон Roles-ийн getName() метод ашиглан динамикаар авна.
            // Ирээдүйд хүснэгтийн нэр өөрчлөгдвөл Model класс дахь setTable() засах хангалттай.
            $user_role_table = (new UserRole($this->pdo))->getName();
            $roles_table = (new Roles($this->pdo))->getName();
            $select_user_role =
                'SELECT t1.role_id, t1.user_id, t2.name, t2.alias ' . 
                "FROM $user_role_table as t1 INNER JOIN $roles_table as t2 ON t1.role_id=t2.id";
            $user_role = $this->query($select_user_role)->fetchAll();
            // user_id-гаар нь users[$id]['roles'][] массив руу цуглуулна
            \array_walk($user_role, function($value) use (&$users) {
                if (isset($users[$value['user_id']])) {
                    if (!isset($users[$value['user_id']]['roles'])) {
                        $users[$value['user_id']]['roles'] = [];
                    }
                    $users[$value['user_id']]['roles'][] = "{$value['alias']}_{$value['name']}";
                }
            });
            
            // Байгууллагын мэдээллийг хэрэглэгч бүр дээр нэгтгэх
            // Хүснэгтийн нэрийг OrganizationModel болон OrganizationUserModel-ийн getName() метод ашиглан динамикаар авна.
            // Ирээдүйд хүснэгтийн нэр өөрчлөгдвөл Model класс дахь setTable() засах хангалттай.
            $org_table = (new OrganizationModel($this->pdo))->getName();
            $org_user_table = (new OrganizationUserModel($this->pdo))->getName();
            $select_orgs_users =
                'SELECT t1.user_id, t1.organization_id as id, t2.name, t2.alias ' .
                "FROM $org_user_table as t1 INNER JOIN $org_table as t2 ON t1.organization_id=t2.id " .
                'WHERE t2.is_active=1';
            $org_users = $this->query($select_orgs_users)->fetchAll();
            // user_id-гаар нь users[$id]['organizations'][] массив руу байгууллагын мэдээллийг нэмнэ
            \array_walk($org_users, function($value) use (&$users) {
                $user_id = $value['user_id'];
                unset($value['user_id']);
                if (isset($users[$user_id])) {
                    if (!isset($users[$user_id]['organizations'])) {
                        $users[$user_id]['organizations'] = [];
                    }
                    $users[$user_id]['organizations'][] = $value;
                }
            });
            
            // Амжилттай status=success, list = хэрэглэгчдийн массив (0-based index-ээр) хэвлэнэ
            $this->respondJSON([
                'status' => 'success',
                'list' => \array_values($users)
            ]);
        } catch (\Throwable $e) {
            // Алдаа гарвал зөвхөн мессеж, HTTP кодыг JSON-оор буцаана
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
        }
    }
    
    /**
     * Шинэ хэрэглэгч үүсгэх action.
     *
     * Хоёр янзаар ажиллана:
     *
     *  1) GET /users/insert
     *     - Хэрэглэгч үүсгэх form-тай Dashboard хуудсыг рендерлэнэ
     *     - `user-insert.html` template-д идэвхтэй байгууллагуудыг (organizations.is_active=1) дамжуулна
     *
     *  2) POST /users/insert
     *     - Request body-оос хэрэглэгчийн мэдээлэл уншина
     *     - username, email, password-г шалгана
     *       * password хоосон байвал санамсаргүй 10-byte (20 hex тэмдэгт) нууц үг үүсгэнэ
     *     - UsersModel ашиглан users хүснэгтэд insert хийнэ
     *     - Хэрэв organization_id ирсэн бол OrganizationUserModel-д харьяалал үүсгэнэ
     *     - FileController::moveUploaded() ашиглан photo upload хийж, users.photo_* талбаруудыг шинэчилнэ
     *     - Амжилттай POST бол JSON {status: success, message: ...} хэвлэнэ
     *
     * Лог:
     *  - finally хэсэгт `log('users', ...)` ашиглан create үйлдлийн протокол үлдээнэ
     *
     * @return void
     */
    public function insert()
    {
        try {
            if (!$this->isUserCan('system_user_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            // Ажиллах Model-ууд
            $model = new UsersModel($this->pdo);
            $orgModel = new OrganizationModel($this->pdo);
            // HTTP method шалгаад POST үед л DB insert хийнэ, бусад үед form харуулна
            if ($this->getRequest()->getMethod() == 'POST') {
                // -----------------------------
                // POST - хэрэглэгч үүсгэх
                // -----------------------------
                $payload = $this->getParsedBody();
                
                // Заавал байх ёстой талбаруудыг шалгах (username / email)
                if (empty($payload['username']) || empty($payload['email'])
                    || \filter_var($payload['email'], \FILTER_VALIDATE_EMAIL) === false
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                $payload['email'] = $this->normalizeEmail($payload['email']);

                // Нууц үг хоосон байвал санамсаргүй үүсгэнэ, байвал шууд ашиглана
                if (empty($payload['password'])) {
                    $bytes = \random_bytes(10);
                    $password = \bin2hex($bytes);
                } else {
                    $password = $payload['password'];
                }
                // Нууц үгийг bcrypt-аар hash хийж DB-д хадгалах бэлэн болно
                $payload['password'] = \password_hash($password, \PASSWORD_BCRYPT);
                
                // POST дээр ирсэн organization (optional) - дараа нь OrganizationUserModel-д ашиглана
                $post_organization = $payload['organization'] ?? null;
                unset($payload['organization']);
                
                // created_by-г одоогийн хэрэглэгчийн ID-аар тавьж insert хийнэ
                $record = $model->insert($payload + ['created_by' => $this->getUserId()]);
                if (empty($record)) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                // Client-д зориулан JSON хэвлэнэ - амжилттай үүссэн тухай
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
                
                // Хэрэв organization сонгосон бол хэрэглэгчийг тухайн байгууллагад холбох
                if (!empty($post_organization)) {
                    $organization = \filter_var($post_organization, \FILTER_VALIDATE_INT);
                    if ($organization !== false
                        && !empty($orgModel->getRowWhere(['id' => $organization, 'is_active' => 1]))
                    ) {
                        (new OrganizationUserModel($this->pdo))->insert([
                            'user_id'        => $record['id'],
                            'organization_id'=> $organization,
                            'created_by'     => $this->getUserId(),
                        ]);
                    }
                }
                
                // Хэрэглэгчийн зураг upload хийх боломжийг нээх
                // /users/{id} гэсэн хавтас руу байрлуулна -> {id} insert хийсэн шинэ бичлэгийн дугаар
                $this->setFolder("/{$model->getName()}/{$record['id']}");
                $this->allowImageOnly(); // зөвхөн зурган файл зөвшөөрнө
                $photo = $this->moveUploaded('photo');
                if ($photo) {
                    // Хэрэв зураг амжилттай upload болсон бол тухайн хэрэглэгчийн photo_* талбаруудыг шинэчилнэ
                    $record = $model->updateById(
                        $record['id'],
                        [
                            'photo' => $photo['path'],
                            'photo_file' => $photo['file'],
                            'photo_size' => $photo['size']
                        ]
                    );
                }
            } else {
                // -----------------------------
                // GET - хэрэглэгч үүсгэх form-тай Dashboard хуудсыг харуулах
                // -----------------------------
                $dashboard = $this->dashboardTemplate(
                    __DIR__ . '/user-insert.html',
                    [
                        // Зөвхөн идэвхтэй байгууллагуудыг сонгож form-д өгнө
                        'organizations' => $orgModel->getRows(['WHERE' => 'is_active=1'])
                    ]
                );
                $dashboard->set('title', $this->text('create-new-user'));
                $dashboard->render();
            }
        } catch (\Throwable $err) {
            // Алдаа гарсан үед:
            if ($this->getRequest()->getMethod() == 'POST') {
                // Хэрэв POST хүсэлт байсан бол JSON алдаа хэвлэх хэрэгтэй
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                // Харин form нээх явцад алдаа гарвал dashboard алдааны дэлгэц харуулна
                $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            // Энэ action-ийн лог протокол
            $context = ['action' => 'create'];
            if (isset($err) && $err instanceof \Throwable) {
                // Алдаатай дууссан тохиолдолд ERROR level
                $level = LogLevel::ERROR;
                $message = 'Хэрэглэгч үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'POST') {
                // Амжилттай шинэ хэрэглэгч үүсгэсэн үед INFO level
                $level = LogLevel::INFO;
                $message = 'Хэрэглэгч [{record.username}] {record.id} дугаартай амжилттай үүслээ';
                // POST амжилттай тул $record-г лог дээр нь хадгална
                $context += ['record_id' => $record['id'], 'record' => $record];
            } else {
                // Зөвхөн create form-ийг нээсэн үед NOTICE level
                $level = LogLevel::NOTICE;
                $message = 'Хэрэглэгч үүсгэх үйлдлийг эхлүүллээ';
            }
            // users logger-д бичих
            $this->log('users', $level, $message, $context);
        }
    }

    /**
     * Хэрэглэгчийн мэдээллийг засварлах (Edit User).
     *
     * Энэ method дараах 2 горимоор ажиллана:
     *
     *  1) GET /users/update/{id}
     *      - Хэрэглэгчийн мэдээллийг формд populate хийж dashboard руу харуулна.
     *      - Байгууллагууд, RBAC дүрүүд, тухайн хэрэглэгчийн харьяалагдаж буй
     *        байгууллагууд болон ролуудыг ачаална.
     *
     *  2) PUT /users/update/{id}
     *      - Backend логик: хэрэглэгчийн өгөгдлийг update хийх
     *      - username, email давхардал шалгах
     *      - password хоосон бол орхих, шинэ орж ирвэл hash хийх
     *      - RBAC roles + Organizations-г configure хийх
     *      - Зураг upload хийх
     *      - Ямар талбар өөрчлөгдсөнийг "updates" массивт бүртгэх
     *
     * RBAC:
     *  - Админ хэрэглэгч (`system_user_update`) бусдыг засах эрхтэй
     *  - Хэрэглэгч өөрийнхөө профайлыг засаж болно
     *
     * Root user (id=1):
     *  - Root-ийг зөвхөн root өөрөө засах боломжтой
     *
     * @param int $id  Засах гэж буй хэрэглэгчийн дугаар
     * @return void JSON эсвэл HTML form рендерлэнэ
     */
    public function update(int $id)
    {
        try {
            if (!$this->isUserAuthorized()
                || (!$this->isUserCan('system_user_update')
                    && $this->getUserId() != $id)
            ) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            // Root хэрэглэгчийг зөвхөн root өөрөө засна
            if ($id == 1 && $this->getUserId() != $id) {
                throw new \Exception('No one but root can edit this account!', 403);
            }
            
            // Model-оос тухайн хэрэглэгчийн мэдээллийг авах
            $model = new UsersModel($this->pdo);
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            if ($this->getRequest()->getMethod() == 'PUT') {
                // PUT - Формаас ирсэн өгөгдлийг хадгална
                $payload = $this->getParsedBody();
                if (empty($payload['username']) || empty($payload['email'])
                    || \filter_var($payload['email'], \FILTER_VALIDATE_EMAIL) === false
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                $payload['email'] = $this->normalizeEmail($payload['email']);

                // Нууц үг ирсэн бол hash хийнэ
                if (!empty($payload['password'])) {
                    $payload['password'] = \password_hash($payload['password'], \PASSWORD_BCRYPT);
                }
                
                // Organizations ирүүлсэн массивыг validate хийн хадгалах
                $post_organizations = \filter_var(
                    $payload['organizations'] ?? [],
                    \FILTER_VALIDATE_INT,
                    \FILTER_REQUIRE_ARRAY
                ) ?: [];
                unset($payload['organizations']);
                
                // Roles ирүүлсэн массивыг validate хийн хадгалах
                $post_roles = \filter_var(
                    $payload['roles'] ?? [],
                    \FILTER_VALIDATE_INT,
                    \FILTER_REQUIRE_ARRAY
                ) ?: [];
                unset($payload['roles']);

                // Username өөрчлөхийг хориглох
                unset($payload['username']);

                // Email давхардал шалгах
                $existing_email = $model->getRowWhere(['email' => $payload['email']]);
                if (!empty($existing_email) && $existing_email['id'] != $id) {
                    throw new \Exception(
                        $this->text('user-exists') . " email => [{$payload['email']}], id => {$existing_email['id']}",
                        403
                    );
                }
                
                // Хэрэв хэрэглэгч зураг устгахыг сонгосон бол
                if ($payload['photo_removed'] == 1) {
                    if (\file_exists($record['photo_file'])) {
                        \unlink($record['photo_file']);
                        $record['photo_file'] = '';
                    }
                    $payload['photo'] = '';
                    $payload['photo_file'] = '';
                    $payload['photo_size'] = 0;
                }
                unset($payload['photo_removed']);
                
                // Зураг upload хийх
                $this->setFolder("/{$model->getName()}/$id");
                $this->allowImageOnly();
                $photo = $this->moveUploaded('photo');
                if ($photo) {
                    if (!empty($record['photo_file'])
                        && \file_exists($record['photo_file'])
                    ) {
                        // Хуучин зураг байвал устгана
                        \unlink($record['photo_file']);
                    }
                    $payload['photo'] = $photo['path'];
                    $payload['photo_file'] = $photo['file'];
                    $payload['photo_size'] = $photo['size'];
                }
                
                // Аль талбар өөрчлөгдсөн бэ? - updates[] массив
                $updates = [];
                foreach ($payload as $field => $value) {
                    if ($record[$field] != $value) {
                        $updates[] = $field;
                    }
                }
                
                // Organizations ба Roles тохируулья
                if ($this->configureOrgs($id, $post_organizations)) {
                    $updates[] = 'organizations-configure';
                }
                if ($this->configureRoles($id, $post_roles)) {
                    $updates[] = 'roles-configure';
                }
                
                if (empty($updates)) {
                    throw new \InvalidArgumentException('No update!');
                }
                
                //  Database-д update хийх
                $payload['updated_at'] = \date('Y-m-d H:i:s');
                $payload['updated_by'] = $this->getUserId();
                $updated = $model->updateById($id, $payload);
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }

                // Client-рүү амжилттай JSON хариу хэвлэх                
                $this->respondJSON([ 
                    'type' => 'primary',
                    'status' => 'success',
                    'message' => $this->text('record-update-success')
                ]);
            } else { // GET - форм руу мэдээлэл бэлдэх                                                
                // Байгууллагууд
                $orgModel = new OrganizationModel($this->pdo);
                $orgUserModel = new OrganizationUserModel($this->pdo);
                $organizations = $orgModel->getRows(['WHERE' => 'is_active=1']);
                
                // Тухайн хэрэглэгчийн одоогийн байгууллагууд
                $select_org_ids =
                    'SELECT ou.organization_id as id ' .
                    "FROM {$orgUserModel->getName()} as ou INNER JOIN {$orgModel->getName()} as o ON ou.organization_id=o.id " .
                    "WHERE ou.user_id=$id AND o.is_active=1";
                $org_ids = $this->query($select_org_ids)->fetchAll();
                // [id => true] бүтэцтэй - template-д O(1) шалгалт хийхэд зориулсан
                $current_organizations = [];
                foreach ($org_ids as $org) {
                    $current_organizations[$org['id']] = true;
                }

                // RBAC бүтэц бэлдэх
                $rbacs = ['common' => 'Common'];
                $alias_names = $this->query(
                    "SELECT alias,name FROM {$orgModel->getName()} WHERE alias!='common' AND is_active=1 ORDER BY id desc"
                )->fetchAll();
                foreach ($alias_names as $row) {
                    if (isset($rbacs[$row['alias']])) {
                        $rbacs[$row['alias']] .= ", {$row['name']}";
                    } else {
                        $rbacs[$row['alias']] = $row['name'];
                    }
                }

                // Roles жагсаалт
                $rolesModel = new Roles($this->pdo);
                $roles = \array_map(function() { return []; }, \array_flip(\array_keys($rbacs)));
                $rbac_roles = $this->query(
                    "SELECT id,alias,name,description FROM {$rolesModel->getName()}"
                )->fetchAll();
                \array_walk($rbac_roles, function($value) use (&$roles) {
                    if (!isset($roles[$value['alias']])) {
                        $roles[$value['alias']] = [];
                    }
                    $roles[$value['alias']][$value['id']] = [$value['name']];

                    if (!empty($value['description'])) {
                        $roles[$value['alias']][$value['id']][] = $value['description'];
                    }
                });

                // Тухайн хэрэглэгчийн одоогийн ролууд
                $userRoleModel = new UserRole($this->pdo);
                $select_user_roles =
                    "SELECT rur.role_id FROM {$userRoleModel->getName()} as rur INNER JOIN {$rolesModel->getName()} as rr ON rur.role_id=rr.id " .
                    "WHERE rur.user_id=$id";
                $current_roles_rows = $this->query($select_user_roles)->fetchAll();
                // [role_id => true] бүтэцтэй - template-д O(1) шалгалт хийхэд зориулсан
                $current_role = [];
                foreach ($current_roles_rows as $row) {
                    $current_role[$row['role_id']] = true;
                }
                
                // Dashboard template рендерлэх
                $dashboard = $this->dashboardTemplate(
                    __DIR__ . '/user-update.html',
                    [
                        'record'                => $record,
                        'organizations'         => $organizations,
                        'current_organizations' => $current_organizations,
                        'rbacs'                 => $rbacs,
                        'roles'                 => $roles,
                        'current_roles'         => $current_role
                    ]
                );
                $dashboard->set('title', $this->text('edit-user'));
                $dashboard->render();
            }
        } catch (\Throwable $err) {
            if ($this->getRequest()->getMethod() == 'PUT') {
                // PUT үед JSON рендерлэнэ
                $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
            } else {
                // Form нээх үед dashboard error рендерлэнэ
                $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            // ЛОГ ПРОТОКОЛ - бүх үйлдлийг бүртгэнэ
            $context = ['action' => 'update', 'record_id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                // Алдаатай бол
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай хэрэглэгчийн мэдээллийг шинэчлэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } elseif ($this->getRequest()->getMethod() == 'PUT') {
                // Амжилттай update хийсэн үед
                $level = LogLevel::INFO;
                $message = '[{record.username}] {record.id} дугаартай хэрэглэгчийн мэдээллийг амжилттай шинэчлэлээ';
                $context += ['updates' => $updates, 'record' => $updated];
            } else {
                // Form нээсэн үед
                $level = LogLevel::NOTICE;
                $message = '[{record.username}] {record.id} дугаартай хэрэглэгчийн мэдээллийг шинэчлэх үйлдлийг эхлүүллээ';
                $context += ['record' => $record, 'current_roles' => $current_role, 'current_organizations' => $current_organizations];
            }
            $this->log('users', $level, $message, $context);
        }
    }

    /**
     * Хэрэглэгчийн дэлгэрэнгүй мэдээллийг харах
     * -------------------------------------------------------------
     * Энэ функц нь нэг хэрэглэгчийн (profile) бүх мэдээллийг цуглуулж
     * Dashboard-ийн readonly page дээр харуулах зориулалттай.
     *
     * Үйл ажиллагааны дараалал:
     *   1) Эрхийн шалгалт - зөвхөн:
     *        * system_user_index эрхтэй хэрэглэгч
     *        * эсвэл өөрийн профайлыг үзэж буй хэрэглэгч
     *   2) UsersModel-оос үндсэн profile мэдээллийг авах
     *   3) created_by / updated_by талбаруудын мэдээллийг 
     *      retrieveUsersDetail() ашиглан дэлгэрэнгүй болгох
     *   4) Байгууллага (Organizations) холбоотой мэдээллийг цуглуулах
     *   5) RBAC roles жагсаалтыг авах
     *   6) Dashboard template-д дамжуулж үзүүлэх
     *
     * @param int $id - Дэлгэрэнгүй харах хэрэглэгчийн дугаар
     * @throws Exception Хэрэв:
     *          * Хэрэглэгч эрхгүй бол
     *          * Бүртгэл олдохгүй бол
     * @return void
     */
    public function view(int $id)
    {
        try {
            // ---------------------------------------------------------
            // RBAC - эрхийн шалгалт
            // ---------------------------------------------------------
            // Зөвхөн дараах хүмүүс нэвтэрч болно:
            //   * system_user_index эрхтэй хэрэглэгч
            //   * эсвэл өөрийн профайлаа үзэж буй хэрэглэгч
            if (!$this->isUserAuthorized()
                || (!$this->isUserCan('system_user_index')
                && $this->getUserId() != $id)
            ) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            // Үндсэн PROFILE мэдээллийг авах
            $model = new UsersModel($this->pdo);
            $record = $model->getRowWhere([
                'id' => $id
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            // created_by / updated_by ID -> нэр, имэйл, утас гэх мэт
            $record['rbac_users'] =
                $this->retrieveUsersDetail(
                    $record['created_by'],
                    $record['updated_by']
                );
            
            // Байгууллагын харьяалал авах
            // Хүснэгтийн нэрийг OrganizationModel болон OrganizationUserModel-ийн getName() метод ашиглан динамикаар авна.
            // Ирээдүйд хүснэгтийн нэр өөрчлөгдвөл Model класс дахь setTable() засах хангалттай.
            $org_table = (new OrganizationModel($this->pdo))->getName();
            $org_user_table = (new OrganizationUserModel($this->pdo))->getName();
            $select_user_orgs =
                'SELECT t2.name, t2.alias, t2.id ' .
                "FROM $org_user_table as t1 INNER JOIN $org_table as t2 ON t1.organization_id=t2.id " .
                "WHERE t2.is_active=1 AND t1.user_id=$id";
            $organizations = $this->query($select_user_orgs)->fetchAll();

            // RBAC Roles жагсаалт авах
            // Хүснэгтийн нэрийг Roles болон UserRole-ийн getName() метод ашиглан динамикаар авна.
            // Ирээдүйд хүснэгтийн нэр өөрчлөгдвөл Model класс дахь setTable() засах хангалттай.
            $roles_table = (new Roles($this->pdo))->getName();
            $user_role_table = (new UserRole($this->pdo))->getName();
            $concat = $this->getDriverName() == Constants::DRIVER_PGSQL
                ? "t2.alias || '_' || t2.name"
                : "CONCAT(t2.alias, '_', t2.name)";
            $select_user_roles =
                "SELECT $concat as name
                 FROM $user_role_table as t1
                 INNER JOIN $roles_table as t2 ON t1.role_id=t2.id
                 WHERE t1.user_id=$id";
            $roles = $this->query($select_user_roles)->fetchAll();
            
            // Dashboard-ийн template рүү дамжуулж render хийх
            $dashboard = $this->dashboardTemplate(
                __DIR__ . '/user-view.html',
                [
                    'record' => $record,
                    'roles' => $roles,
                    'organizations' => $organizations
                ]
            );
            $dashboard->set('title', $this->text('user'));
            $dashboard->render();
        } catch (\Throwable $err) {
            // Алдаа гарвал permission forbidden template рендерлэнэ
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            // Үйлдлийн протокол
            $context = ['action' => 'view', 'record_id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай хэрэглэгчийн мэдээллийг нээх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.username} хэрэглэгчийн мэдээллийг үзэж байна';
                $context += ['record' => $record, 'roles' => $roles, 'organizations' => $organizations];
            }
            $this->log('users', $level, $message, $context);
        }
    }
    
    /**
     * Хэрэглэгчийг идэвхгүй болгох (Soft Delete)
     * -------------------------------------------------------------
     * Энэ функц нь хэрэглэгчийн бүртгэлийг бүр мөсөн устгахгүй,
     * зөвхөн is_active=0 болгож идэвхгүй төлөвт шилжүүлдэг.
     *
     * Гол зорилго:
     *   * Хэрэглэгчийг систем ашиглах боломжгүй болгох
     *   * Лог түүх хадгалагдана
     *   * Физик устгал хийгдэхгүй (soft delete)
     *
     * Аюулгүй ажиллагааны шалгалтууд:
     *   1) Зөвхөн system_user_delete эрхтэй хэрэглэгч ажиллуулна
     *   2) Хэрэглэгч өөрийгөө устгаж болохгүй
     *   3) Root account (#1) устгалтыг хориглоно
     *
     * @return void JSON рендерлэнэ
     * @throws Throwable
     */
    public function deactivate()
    {
        try {
            // RBAC - Устгах эрхтэй эсэхийг шалгана
            if (!$this->isUserCan('system_user_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            // Request body (JSON) -> payload авах
            $payload = $this->getParsedBody();
            
            // id (дугаар) заавал int байх ёстой
            if (!isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }            
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            
            // Хэрэглэгч өөрийгөө устгахыг хориглох
            if ($this->getUserId() == $id) {
                throw new \Exception('Cannot suicide myself :(', 403);
            } elseif ($id == 1) {
                // Root (#1) account-ыг идэвхгүй болгох хориотой
                throw new \Exception('Cannot remove first acccount!', 403);
            }
            
            // -------------------------------------------------------------
            // Soft delete - хэрэглэгчийн бичлэгийг is_active = 0 болгож идэвхгүй болгоно.
            //
            // Анхаарах зүйл:
            //   * Энэ горимд хэрэглэгчийн profile photo файлыг серверээс устгахгүй.
            //   * Учир нь тухайн хэрэглэгчийг ирээдүйд дахин идэвхжүүлэх (reactivate)
            //     боломж нээлттэй тул зураг болон мэдээллүүдийг хадгалж үлдээх шаардлагатай.
            //   * Хэрэв бүрэн устгах (hard delete) үйлдэл бол photo файлыг бас устгах хэрэгтэй.
            //
            $model = new UsersModel($this->pdo);
            $model->deactivateById(
                $id,
                [
                    'updated_by' => $this->getUserId(),
                    'updated_at' => \date('Y-m-d H:i:s')
                ]
            );
            
            // Амжилттай хариу -> JSON рендерлэнэ
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
        } catch (\Throwable $err) {
            // Специфик алдаа -> JSON рендерлэнэ
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            // Протокол бичих
            $context = ['action' => 'deactivate'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Хэрэглэгчийг идэвхгүй болгох үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = 
                    '[{server_request.body.id}] дугаартай [{server_request.body.name}] хэрэглэгчийг '
                    . '[{server_request.body.reason}] шалтгаанаар идэвхгүй болголоо';
            }
            $this->log('users', $level, $message, $context);
        }
    }

    /**
     * Идэвхгүй болсон хэрэглэгчийг бүрэн устгах (HARD DELETE).
     *
     * Зөвхөн is_active=0 болсон хэрэглэгчийг устгана.
     * FK constraint-уудыг түр унтрааж устгана.
     *
     * Permission: system_user_delete
     *
     * @return void JSON response
     */
    public function delete()
    {
        try {
            if (!$this->isUserCan('system_user_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }

            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);

            if ($this->getUserId() == $id) {
                throw new \Exception('Cannot delete myself!', 403);
            } elseif ($id == 1) {
                throw new \Exception('Cannot remove first account!', 403);
            }

            $model = new UsersModel($this->pdo);
            $user = $model->getById($id);
            if (empty($user)) {
                throw new \Exception($this->text('no-record-selected'), 404);
            }
            if ((int)($user['is_active'] ?? 1) !== 0) {
                throw new \Exception('Only deactivated users can be permanently deleted!', 403);
            }

            // Profile photo файлыг устгах
            if (!empty($user['photo'])) {
                $photoPath = $this->getPublicPath() . $user['photo'];
                if (\file_exists($photoPath)) {
                    \unlink($photoPath);
                }
            }

            // FK constraint түр унтрааж устгана
            $model->setForeignKeyChecks(false);
            $model->deleteById($id);
            $model->setForeignKeyChecks(true);

            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            $context = ['action' => 'delete'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Хэрэглэгчийг бүрэн устгах явцад алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::CRITICAL;
                $message =
                    '[{server_request.body.id}] дугаартай [{server_request.body.name}] хэрэглэгчийг '
                    . '[{server_request.body.reason}] шалтгаанаар бүрэн устгалаа';
            }
            $this->log('users', $level, $message, $context);
        }
    }

    /**
     * Бүртгүүлэх (signup) эсвэл Нууц үг мартсан (forgot) хүсэлтүүдийн модал
     * ----------------------------------------------------------------------
     * Энэхүү функц нь AJAX-аар дуудагддаг бөгөөд:
     *
     *   * forgot / signup хүсэлтүүдийн жагсаалтыг татаж modal-д харуулна
     *   * Хүсэлтүүд нь тусдаа хүснэгтүүд (forgot, signup) дээр хадгалагддаг
     *   * is_active хамаарахгүй бүхий л бичлэгүүдийг унших
     *
     * URL:
     *   GET /users/requests-modal/{table}
     *
     * table утга нь:
     *   - "forgot"
     *   - "signup"
     * өөр утга ирвэл алдаа шиднэ.
     *
     * Modal template:
     *   /application/raptor/user/forgot-index-modal.html
     *   /application/raptor/user/signup-index-modal.html
     *
     * @param string $table  "forgot" эсвэл "signup"
     * @return void
     */
    public function requestsModal(string $table)
    {
        try {
            // Эрхийн шалгалт: хэрэглэгч мэдээлэл харах эрхтэй эсэх
            if (!$this->isUserCan('system_user_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            // Modal template дуудах
            $template = $this->template(__DIR__ . "/$table-index-modal.html");
            
           // table параметрийн зөв эсэхийг шалгах
            switch ($table) {
                case 'forgot':
                    {
                        $model = new ForgotModel($this->pdo);
                        // isExpired() туслах функцийг темплейтэд нэмэх
                        // Хүсэлт амьд байх хугацаандаа байгаа эсэх шалгалтад хэрэглэнэ
                        $template->addFunction(
                            'isExpired',
                            function (string $created_at): bool {
                                $now = new \DateTime();
                                $then = new \DateTime($created_at);
                                $diff = $then->diff($now);
                                return
                                    $diff->y > 0 ||
                                    $diff->m > 0 ||
                                    $diff->d > 0 ||
                                    $diff->h > 0 ||
                                    $diff->i > RAPTOR_PASSWORD_RESET_MINUTES;
                            }
                        );
                    }
                    break;

                case 'signup':
                    $model = new SignupModel($this->pdo);
                    break;

                default:
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            
            // Хүснэгтээс хүсэлтүүдийг хамгийн сүүлд орсноор нь sort хийж авах
            //  * is_active хамаарахгүй бүхий л бичлэгүүдийг унших
            $rows = $model->getRows(['ORDER BY' => 'created_at Desc']);
            
            // Dashboard рендерлэнэ
            $template->set('rows',$rows);            
            $template->render();
        } catch (\Throwable $err) {
            // Error modal рендерлэнэ
            $this->modalProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            // LOGGER - modal хүсэлт нээгдсэн эсвэл алдаатай эсэх
            $context = ['action' => 'requests-modal', 'table' => $table];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Хэрэглэгчдийн мэдээллийн [{table}] хүснэгтийг нээж үзэх хүсэлт алдаатай байна';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '[{table}] хүсэлтүүдийн жагсаалтыг үзэж байна';
                $context += ['count-rows' => \count($rows)];
            }
            $this->log('users', $level, $message, $context);
        }
    }
    
    /**
     * Бүртгүүлэх хүсэлтийг (signup request) зөвшөөрч,
     * системийн users хүснэгтэд бодит хэрэглэгч болгон үүсгэх controller метод.
     *
     * Ажиллах дараалал:
     *  1. Хэрэглэгч эрхтэй эсэхийг шалгана (system_user_insert).
     *  2. signup хүсэлтийн ID-г шалгана, хүчинтэй integer эсэхийг баталгаажуулна.
     *  3. SignupModel-оос тухайн хүсэлтийг татна (id, is_active=1).
     *  4. Username / email давхардсан эсэхийг UsersModel дээр шалгана.
     *  5. Хэрэв OK бол:
     *        - UsersModel.insert() ашиглан жинхэнэ хэрэглэгч үүсгэнэ
     *        - signup хүсэлтийг is_active=2 болгож хаана
     *        - organization_id өгөгдвөл тухайн байгууллагад хэрэглэгчийг холбож нэмнэ
     *  6. И-мэйл баталгаажуулах шаблон (templates) байвал хэрэглэгч рүү илгээнэ.
     *  7. JSON хэлбэрээр хариу хэвлэнэ.
     *
     * Алдаа гарсан нөхцөл:
     *  - Эрхгүй үед 401
     *  - Хүсэлт буруу үед 400
     *  - Давхардсан username/email үед 403
     *  - Өгөгдөл бичихэд алдаа гарвал 500
     *
     * @return void JSON хариу render хийнэ
     * @throws Throwable бүх төрлийн алдааг finally хэсэг лог хийнэ
     */
    public function signupApprove()
    {
        try {
            // Зөвшөөрөх эрх байгаа эсэхийг шалгах
            if (!$this->isUserCan('system_user_insert')) {
                throw new \Exception('No permission for an action [approval]!', 401);
            }
            
            // Request body -> id авах, integer эсэхийг шалгах
            $parsedBody = $this->getParsedBody();
            if (empty($parsedBody['id'])
                || !\filter_var($parsedBody['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = \filter_var($parsedBody['id'], \FILTER_VALIDATE_INT);
            
            // SignupModel - Тухайн signup хүсэлтийг авах
            $signupModel = new SignupModel($this->pdo);
            $signup = $signupModel->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($signup)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            // UsersModel-д яг ижил username / email давхардсан эсэхийг шалгах
            $model = new UsersModel($this->pdo);
            $existing = $this->prepare("SELECT id FROM {$model->getName()} WHERE username=:username OR email=:email");            
            $existing->bindParam(':email', $signup['email'], \PDO::PARAM_STR, $model->getColumn('email')->getLength());
            $existing->bindParam(':username', $signup['username'], \PDO::PARAM_STR, $model->getColumn('username')->getLength());
            if ($existing->execute() && !empty($existing->fetch())) {
                
                throw new \Exception(
                    $this->text('user-exists')
                    . ": username/email => {$signup['username']}/{$signup['email']}",
                    403
                );
            }
            
            // UsersModel.insert() -> Жинхэнэ хэрэглэгч үүсгэх
            $record = $model->insert([
                'username' => $signup['username'],
                'password' => $signup['password'],
                'email' => $signup['email'],
                'code' => $signup['code'],
                'created_by' => $this->getUserId()
            ]);
            if (empty($record)) {
                throw new \Exception('Failed to create user');
            }
            
            // Signup хүсэлтийг хааж is_active=2 болгох
            $signupModel->updateById(
                $id,
                [
                    'user_id' => $record['id'],
                    'is_active' => 2,
                    'updated_by' => $this->getUserId()
                ]
            );
            
            // Хэрэв organization_id өгөгдвөл тухайн байгууллагад шууд холбох
            $organization_id = \filter_var($parsedBody['organization_id'] ?? 0, \FILTER_VALIDATE_INT);
            if (empty($organization_id)) {
                $organization_id = 1; // system organization fallback
            }            
            $orgModel = new OrganizationModel($this->pdo);
            $organization = $orgModel->getRowWhere([
                'id' => $organization_id,
                'is_active' => 1
            ]);
            
            if (!empty($organization)) {
                // Байгууллагын холбоосыг үүсгэх
                $user_org = (new OrganizationUserModel($this->pdo))->insert([
                    'user_id' => $record['id'],
                    'organization_id' => $organization_id,
                    'created_by' => $this->getUserId()
                ]);
                if (!empty($user_org)) {
                    $record['organizations'] = [$organization];
                }
            }
            
            // Амжилттай JSON хариу рендерлэнэ
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-insert-success'),
                'record' => $record
            ]);

            $this->dispatch(new \Raptor\Notification\UserEvent(
                'approve', $signup['username'], $signup['email']
            ));

            // Баталгаажуулалтын и-мэйл загвар авах (templates хүснэгтээс)
            $templateService = $this->getService('template_service');
            // approve-new-user template-ийг дуудна
            $template = $templateService->getByKeyword('approve-new-user', $signup['code']);
            if (!empty($template) && !empty($template['content'])) {
                // MemoryTemplate -> placeholder орлуулах
                $memtemplate = new MemoryTemplate();
                $memtemplate->source($template['content']);
                $memtemplate->set('email', $signup['email']);
                $memtemplate->set('login', $this->generateRouteLink('login', [], true));
                $memtemplate->set('username', $signup['username']);
                
                // И-мэйл илгээж тухайн хүсэлт өгсөн хэрэглэгчдээ мэдээлэх
                $this->getService('mailer')
                    ->mail(
                        $signup['email'],
                        null,
                        $template['title'],
                        $memtemplate->output()
                    )->send();
            }
        } catch (\Throwable $err) {
            // Алдаа -> JSON рендерлэнэ
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            // Хэрэглэгчийн signup approval үйлдлийг системийн протоколд үлдээх
            $context = ['action' => 'signup-approve'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Хэрэглэгчээр бүртгүүлэх хүсэлтийг зөвшөөрч системд нэмэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = 'Шинэ бүртгүүлсэн {signup.username} нэртэй {signup.email} хаягтай хүсэлтийг зөвшөөрч системд хэрэглэгчээр нэмлээ';
                $context += ['signup' => $signup, 'record' => $record];
            }
            $this->log('users', $level, $message, $context);
        }
    }

    /**
     * signupDeactivate()
     * -------------------
     * Хэрэглэгчээр бүртгүүлэх (signup) хүсэлтийг идэвхгүй болгох (soft delete).
     *
     * Ашиглалт:
     *  - Админ хэрэглэгч signup хүсэлтийг "устгах" үед дуудагдана.
     *  - SignupModel дээрх is_active талбарыг өөрчилж, хүсэлтийг идэвхгүй төлөвт шилжүүлнэ.
     *
     * Алгоритм:
     *  1. 'system_user_delete' эрхтэй эсэхийг шалгана.
     *  2. Request body дундах id-г шалгаж, хүчинтэй integer эсэхийг баталгаажуулна.
     *  3. SignupModel::deactivateById() ашиглан тухайн мөрийг идэвхгүй болгоно.
     *  4. Амжилттай бол success, алдаа гарвал error JSON хэвлэнэ.
     *  5. finally хэсэгт энэ үйлдлийг users лог дээр протокол болгон үлдээнэ.
     *
     * @return void JSON рендерлэнэ
     */
    public function signupDeactivate()
    {
        try {
            // Энэ үйлдлийг хийх эрх байгаа эсэхийг шалгах
            if (!$this->isUserCan('system_user_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            // Request body -> payload авч, id-г шалгах
            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                // Буруу эсвэл байхгүй id
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            // Хүчинтэй integer болгох
            $id = (int) $payload['id'];
            
            // SignupModel -> тухайн бичлэгийг deactivateById() ашиглан идэвхгүй болгох
            (new SignupModel($this->pdo))->deactivateById(
                $id,
                [
                    'updated_by' => $this->getUserId(),
                    'updated_at' => \date('Y-m-d H:i:s')
                ]
            );
            
            // Амжилттай JSON success хариу рендерлэнэ
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
        } catch (\Throwable $err) {
            // Алдаа гарсан тул JSON error хариу рендерлэнэ
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            // Энэ үйл явцыг лог (users лог) дээр үлдээх
            $context = ['action' => 'signup-deactivate'];
            if (isset($err) && $err instanceof \Throwable) {
                // Алдаатай дууссан тохиолдолд
                $level = LogLevel::ERROR;
                $message = 'Хэрэглэгчээр бүртгүүлэх хүсэлтийг идэвхгүй болгох явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                // Амжилттай идэвхгүй болгосон
                $level = LogLevel::ALERT;
                $message = '[{server_request.body.name}] хэрэглэгчээр бүртгүүлэх хүсэлтийг идэвхгүй болгов';
            }
            $this->log('users', $level, $message, $context);
        }
    }

    /**
     * Идэвхгүй болсон signup хүсэлтийг бүрэн устгах (HARD DELETE).
     *
     * Permission: system_user_delete
     *
     * @return void JSON response
     */
    public function signupDelete()
    {
        try {
            if (!$this->isUserCan('system_user_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }

            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = (int) $payload['id'];

            $model = new SignupModel($this->pdo);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'), 404);
            }
            if ((int)($record['is_active'] ?? 1) !== 0) {
                throw new \Exception('Only deactivated signup requests can be permanently deleted!', 403);
            }

            $model->deleteById($id);

            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            $context = ['action' => 'signup-delete'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Signup хүсэлтийг бүрэн устгах явцад алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::CRITICAL;
                $message = '[{server_request.body.name}] signup хүсэлтийг бүрэн устгалаа';
            }
            $this->log('users', $level, $message, $context);
        }
    }

    /**
     * setPassword(int $id)
     * ---------------------
     * Тухайн хэрэглэгчийн нууц үгийг солих функц.
     *
     * Хэн ашиглах вэ?
     *   Хэрэглэгч өөрийн нууц үгийг солих
     *   system_coder эрхтэй хэрэглэгч бусдын нууц үгийг солих
     *      - Гэхдээ system_coder байсан ч:
     *         if ($id == 1 && $this->getUserId() != 1)
     *         -> хориглоно (root password-г зөвхөн root өөрөө солино)
     *
     * Алгоритм:
     *   1) Хэрэглэгч эрхтэй эсэхийг шалгах
     *   2) Хэрэв POST бол:
     *       - password + password_retype ижил эсэхийг баталгаажуулах
     *       - password_hash -> updateById
     *       - JSON success рендерлэх
     *   3) Хэрэв GET бол modal HTML-г зурах
     *   4) finally -> users лог дээр үйлдлийг бүртгэх
     *
     * @param int $id  Нууц үг өөрчлөх хэрэглэгчийн дугаар
     * @return void JSON хэвлэх эсвэл rendered modal буцаана
     */
    public function setPassword(int $id)
    {
        try {
            // Эрх шалгах
            if (!$this->isUser('system_coder')
                && $this->getUser()->profile['id'] != $id
            ) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            // ROOT USER (id=1) тохиолдолд тусгай нөхцөл
            //    Зөвхөн root өөрийн нууц үгийг солих эрхтэй
            //    system_coder ч гэсэн root user-ийн password-ийг солих хориотой!
            // ---------------------------------------------------------
            if ($id == 1 && $this->getUserId() != 1) {
                throw new \Exception(
                    'Root хэрэглэгчийн нууц үгийг зөвхөн root өөрөө сольж чадна!',
                    403
                );
            }
            
            // Хэрэглэгчийн бүртгэл шалгах
            $model = new UsersModel($this->pdo);
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            if ($this->getRequest()->getMethod() == 'POST') {
                // ---------------------------------------------------------
                // POST -> нууц үг солих
                // ---------------------------------------------------------
                $parsedBody = $this->getParsedBody();
                $password = $parsedBody['password'] ?? null;            
                $password_retype = $parsedBody['password_retype'] ?? null;
                if (empty($password) || $password != $password_retype) {
                    throw new \Exception($this->text('password-must-match'), 400);
                }
                // Солилт
                $updated = $model->updateById(
                    $id,
                    [
                        'updated_by' => $this->getUserId(),
                        'updated_at' => \date('Y-m-d H:i:s'),
                        'password' => \password_hash($password, \PASSWORD_BCRYPT)
                    ]
                );
                if (empty($updated)) {
                    throw new \Exception("Can't reset user [{$record['username']}] password", 500);
                }
                $this->respondJSON([
                    'status'  => 'success',
                    'title'   => $this->text('success'),
                    'message' => $this->text('set-new-password-success')
                ]);
            } else {
                // ---------------------------------------------------------
                // GET -> modal form рендерлэнэ
                // ---------------------------------------------------------
                $this->template(
                    __DIR__ . '/user-set-password-modal.html',
                    ['profile' => $record]
                )->render();
            }
        } catch (\Throwable $err) {
            // Алдааны хэсэг            
            if ($this->getRequest()->getMethod() == 'POST') {
                // POST үед JSON рендерлэнэ
                $this->respondJSON([
                    'status'  => 'error',
                    'title'   => $this->text('error'),
                    'message' => $err->getMessage()
                ], $err->getCode());
            } else {
                // GET үед алдааны модал рендерлэнэ
                $this->modalProhibited($err->getMessage(), $err->getCode())->render();
            }
        } finally {
            // Лог бичих (success болон error аль алинд)
            $context = ['action' => 'set-password', 'record_id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай хэрэглэгчийн нууц үг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $context += ['record' => $record];
                $message = '{record_id} дугаартай [{record.username}] хэрэглэгчийн нууц ';
                if ($this->getRequest()->getMethod() == 'POST') {
                    $level = LogLevel::INFO;
                    $message .= 'үгийг амжилттай шинэчлэв';
                } else {
                    $level = LogLevel::NOTICE;
                    $message .= 'үг өөрчлөх үйлдлийг эхлүүллээ';
                }
            }
            $this->log('users', $level, $message, $context);
        }
    }

    /**
     * Хэрэглэгчийн байгууллагын харьяаллыг тохируулах (OrganizationUser)
     * ----------------------------------------------------------------------
     * Энэ method нь хэрэглэгчийг ямар байгууллагад харьяалагдахыг сонгох,
     * өөрчлөх, нэмэх, хасах боломжийг олгоно.
     *
     * Хэн ашиглах вэ?
     *   system_user_organization_set эрхтэй админ хэрэглэгч
     *
     * Ажиллах зарчим:
     *   1) Хэрэглэгчийг шалгах (id таарч байна уу, is_active=1 уу)
     *   2) GET -> popup modal харуулах (user-set-organization-modal.html)
     *   3) POST -> шинэчилсэн байгууллагуудыг configureOrgs() ашиглан update хийх
     *   4) Root user (id=1) -> үргэлж organization_id=1 дотор байх ёстой!
     *   5) Амжилттай POST бол JSON рендерлэнэ
     *
     * @param int $id  Байгууллагыг тохируулах гэж буй хэрэглэгчийн id
     * @return void
     */
    public function setOrganization(int $id)
    {
        try {
            // Энэ үйлдлийг хийх эрхтэй эсэхийг шалгах
            if (!$this->isUserCan('system_user_organization_set')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            // Хэрэглэгчийн үндсэн мэдээллийг Model-оос авах
            $model = new UsersModel($this->pdo);
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            // Хэрэв POST хүсэлт -> Update хийх
            if ($this->getRequest()->getMethod() == 'POST') {
                 // Ирсэн байгууллагуудын массивыг integer filter-тэйгээр цэвэрлэх
                $post_organizations =
                    \filter_var($this->getParsedBody()['organizations'] ?? [],
                        \FILTER_VALIDATE_INT,
                        \FILTER_REQUIRE_ARRAY
                    ) ?: [];                
                if ($id == 1
                    && (empty($post_organizations) || !\in_array(1, $post_organizations))
                ) {
                    // Root user бол үргэлж organization_id=1 -т харьяалагдсан байх ёстой
                    throw new \Exception('Root user must belong to a system organization', 503);
                }
                // configureOrgs() -> нэмэх/хасах үйлдлүүдийг автоматаар гүйцэтгээд амжилттай бол true
                if (!$this->configureOrgs($id, $post_organizations)) {
                    throw new \Exception('No updates');
                }
                return $this->respondJSON([
                    'status'  => 'success',
                    'title'   => $this->text('success'),
                    'message' => $this->text('record-update-success')
                ]);
            } else {
                // Одоогийн байгууллагуудыг user_id-аар татах
                $orgModel = new OrganizationModel($this->pdo);
                $orgUserModel = new OrganizationUserModel($this->pdo);
                $response = $this->query(
                    'SELECT ou.organization_id as id ' .
                    "FROM {$orgUserModel->getName()} as ou INNER JOIN {$orgModel->getName()} as o ON ou.organization_id=o.id " .
                    "WHERE ou.user_id=$id AND o.is_active=1"
                );
                // [id => true] бүтэцтэй - template-д O(1) шалгалт хийхэд зориулсан
                $current_organizations = [];
                foreach ($response as $org) {
                    $current_organizations[$org['id']] = true;
                }

                // GET хүсэлт -> popup modal HTML-ийг render хийе
                $this->template(
                    __DIR__ . '/user-set-organization-modal.html',
                    [
                        'profile' => $record,
                        'current_organizations' => $current_organizations,
                        'organizations' => $orgModel->getRows(['WHERE' => 'is_active=1'])
                    ]
                )->render();
            }
        } catch (\Throwable $err) {
            // Алдаа гарсан үед -> POST=JSON / GET=Modal хэлбэрээр хариулна
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
            // Лог бүртгэх
            $context = ['action' => 'set-organization', 'record_id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай хэрэглэгчийн байгууллага тохируулах үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $context += ['record' => $record];
                $message = '{record_id} дугаартай [{record.username}] хэрэглэгчийн байгууллага ';
                if ($this->getRequest()->getMethod() == 'POST') {
                    $level = LogLevel::INFO;
                    $message .= 'амжилттай тохируулав';
                } else {
                    $level = LogLevel::NOTICE;
                    $message .= 'тохируулах үйлдлийг эхлүүллээ';
                    $context += ['current_organizations' => $current_organizations];
                }
            }
            $this->log('users', $level, $message, $context);
        }
    }

    /**
     * Хэрэглэгчийн байгууллагын харьяаллыг (OrganizationUser) тооцоолж шинэчлэх үндсэн логик
     * ------------------------------------------------------------------------------------------
     * Энэ private function нь setOrganization() дотроос дуудагддаг бөгөөд:
     *
     *   * Одоогийн байгууллагуудыг DB-с уншина
     *   * POST ирсэн байгууллагуудтай харьцуулна
     *   * Шинээр нэмэгдэх байгууллагуудыг insert хийнэ
     *   * Хасагдах байгууллагуудыг delete хийнэ
     *   * Үйлдэл бүрийг users table-ийн logger руу бичнэ
     *
     * Анхаарах зүйлс:
     *   root user (id=1) -> organization_id = 1-ийг хэзээ ч устгаж болохгүй  
     *   root user түүнээс organization_id=1-ийг хасахыг оролдвол пропуск хийнэ  
     *   logger -> LogLevel::ALERT түвшинд бүртгэнэ  
     *   Мэдээлэл өгөгдсөнөөс хамааран "+" эсвэл "-" өөрчлөлт тодорхойлогдоно
     *
     * @param int   $id        Харьяалал өөрчлөх хэрэглэгчийн ID
     * @param array $orgSets   POST-оор ирсэн organization_id массив
     *
     * @return bool  Тохируулалт амжилттай хийгдсэн эсэх (нэг ч өөрчлөлт байхгүй -> false)
     */
    private function configureOrgs(int $id, array $orgSets): bool
    {
        $configured = false;
        try {
            // Эрх шалгах - зөвхөн system_user_organization_set арга хийж чадна
            if (!$this->isUserCan('system_user_organization_set')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            // Лог бичигчийн мэдээлэл бэлтгэх
            $logger = new Logger($this->pdo);
            $logger->setTable('users');
            $auth_user = [
                'id' => $this->getUser()->profile['id'],
                'username' => $this->getUser()->profile['username'],
                'first_name' => $this->getUser()->profile['first_name'],
                'last_name' => $this->getUser()->profile['last_name'],
                'phone' => $this->getUser()->profile['phone'],
                'email' => $this->getUser()->profile['email']
            ];
            
            // Одоогийн байгууллагуудын мэдээллийг татах
            $model = new UsersModel($this->pdo);
            $orgModel = new OrganizationModel($this->pdo);
            $orgUserModel = new OrganizationUserModel($this->pdo);
            $sql =
                'SELECT t1.id, t1.user_id, t1.organization_id, t2.name as organization_name, t3.username ' .
                "FROM {$orgUserModel->getName()} t1 INNER JOIN {$orgModel->getName()} t2 ON t1.organization_id=t2.id LEFT JOIN {$model->getName()} t3 ON t1.user_id=t3.id " .
                "WHERE t1.user_id=$id AND t2.is_active=1 AND t3.is_active=1";
            $userOrgs = $this->query($sql)->fetchAll();
            
            // POST ирсэн шинэ байгууллагуудыг key->value болгон map хийх
            //   Жишээ: [3,5,7] -> ['3'=>true,'5'=>true,'7'=>true]
            //          Энэ нь алгоритмд хурдтай ажиллана
            $organizationIds = \array_flip($orgSets);
            foreach ($userOrgs as $row) {
                if (isset($organizationIds[$row['organization_id']])) {
                    // Хэрэв одоо байгаа байгууллага POST-д бас байвал -> хасахгүй/нэмэхгүй/өөрчлөлт хийхгүй
                    unset($organizationIds[$row['organization_id']]);
                } elseif ($row['organization_id'] == 1 && $id == 1) {
                    // ROOT USER -> organization_id = 1-ийг хэзээ ч хасахгүй!
                    // can't strip root user from system organization!
                } elseif ($orgUserModel->deleteById($row['id'])) { 
                    // Байгууллагаас хаслаа
                    $configured = true;
                    // strip лог бичих 
                    $logger->log(
                        LogLevel::ALERT,
                        '[{organization_name}:{organization_id}] байгууллагаас [{username}:{user_id}] хэрэглэгчийг хаслаа',
                        ['action' => 'strip-organization'] + $row + ['auth_user' => $auth_user]
                    );
                }
            }
            
            // Шинээр нэмэгдэх байгууллагуудыг insert хийх
            foreach (\array_keys($organizationIds) as $org_id) {
                if (!empty($orgUserModel->insert(
                    ['user_id' => $id, 'organization_id' => $org_id, 'created_by' => $this->getUserId()]))
                ) {
                    $configured = true;
                    // set лог бичих 
                    $logger->log(
                        LogLevel::ALERT,
                        '{organization_id}-р байгууллагад {user_id}-р хэрэглэгчийг нэмлээ',
                        ['action' => 'set-organization', 'user_id' => $id, 'organization_id' => $org_id, 'auth_user' => $auth_user]
                    );
                }
            }
        } catch (\Throwable) {
            // ямар нэгэн exception гарвал зүгээр л false буцаана
            // setOrganization() тал дээр алдааг барьдаг - энэ function silent mode
        }
        return $configured;
    }
    
    /**
     * Хэрэглэгчийн RBAC дүрийг тохируулах action.
     * -----------------------------------------------------------
     * Энэхүү method нь:
     *   Хэрэглэгч дээр шинэ дүр нэмэх
     *   Хэрэглэгчээс дүр хасах
     *   Super-admin (coder) дүртэй холбоотой тусгай хамгаалалтын логик
     *   Дүр өөрчлөлтийн log протокол бүртгэх
     *   GET -> Modal form ачаалж харуулах
     *   POST -> Дүр солих update-г баталгаажуулах
     *
     * Хэн ашиглах вэ?
     *   system_rbac эрхтэй хэрэглэгч
     *
     * Онцгой нөхцөлүүд (system_coder дүрийн):
     *   * id=1 хэрэглэгч (root) -> coder дүрийг хасах/нэмэх эрх зөвхөн root coder-т байдаг
     *   * Root хэрэглэгчээс coder дүрийг хасахыг хэзээ ч зөвшөөрөхгүй
     *   * Root биш хэрэглэгч coder дүр нэмэхийг хориглоно
     *
     * @param int $id  RBAC дүр солих гэж буй хэрэглэгчийн primary key
     * @return void
     */
    public function setRole(int $id)
    {
        try {
            if (!$this->isUserCan('system_rbac')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            // Засварлах гэж буй хэрэглэгчийг ачаалах
            $model = new UsersModel($this->pdo);
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            // -----------------------------------------------------------
            // POST -> Дүр солих UPDATE логик
            // -----------------------------------------------------------
            if ($this->getRequest()->getMethod() == 'POST') {                
                // UI-аас ирсэн role_id массивыг integer array болгон normalize хийнэ
                 $post_roles = \filter_var(
                    $this->getParsedBody()['roles'] ?? [],
                    \FILTER_VALIDATE_INT,
                    \FILTER_REQUIRE_ARRAY
                );
                 
                // ROOT хэрэглэгч -> заавал system coder дүртэй байх ёстой
                if (($id == 1) &&
                    (empty($post_roles) || !\in_array(1, $post_roles))
                ) {
                    throw new \Exception(
                        'Root user must have a system role',
                        403
                    );
                }
                
                // configureRoles() - Дүрүүдийг нэмэх/хасах үндсэн логик
                if (!$this->configureRoles($id, $post_roles)) {
                    throw new \Exception('No updates');
                }
                
                $this->respondJSON([
                    'status'  => 'success',
                    'title'   => $this->text('success'),
                    'message' => $this->text('record-update-success')
                ]);
            } else {
                // -----------------------------------------------------------
                // GET -> Modal render (Дүр өөрчлөх UI)
                // -----------------------------------------------------------                
                $vars = ['profile' => $record];
                
                // RBAC-уудын жагсаалтыг байгууллагын alias-аар бүлэглэж харуулна
                $rbacs = ['common' => 'Common'];
                // organization хүснэгтийн нэрийг OrganizationModel::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
                $org_table = (new OrganizationModel($this->pdo))->getName();
                $organizations_result = $this->query(
                    "SELECT alias,name FROM $org_table WHERE alias!='common' AND is_active=1 ORDER BY id desc"
                )->fetchAll();
                foreach ($organizations_result as $row) {
                    if (isset($rbacs[$row['alias']])) {
                        $rbacs[$row['alias']] .= ", {$row['name']}";
                    } else {
                        $rbacs[$row['alias']] = $row['name'];
                    }
                }
                $vars['rbacs'] = $rbacs;

                // Тухайн RBAC alias бүр дээр харьяалагдах дүрүүдийг татах
                // roles хүснэгтийн нэрийг Roles::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
                $roles_table = (new Roles($this->pdo))->getName();
                $roles = \array_map(function() { return []; }, \array_flip(\array_keys($rbacs)));
                $roles_result = $this->query("SELECT id,alias,name,description FROM $roles_table")->fetchAll();
                \array_walk($roles_result, function($value) use (&$roles) {
                    if (!isset($roles[$value['alias']])) {
                        $roles[$value['alias']] = [];
                    }
                    $roles[$value['alias']][$value['id']] = [$value['name']];

                    if (!empty($value['description'])) {
                        $roles[$value['alias']][$value['id']][] = $value['description'];
                    }
                });
                $vars['roles'] = $roles;

                // Хэрэглэгчийн эзэмшиж буй дүрүүдийг татах
                $userRoleModel = new UserRole($this->pdo);
                // [role_id => true] бүтэцтэй - template-д O(1) шалгалт хийхэд зориулсан
                $current_role = [];
                $select_current_roles =
                    "SELECT rur.role_id FROM {$userRoleModel->getName()} as rur INNER JOIN $roles_table as rr ON rur.role_id=rr.id " .
                    "WHERE rur.user_id=$id";
                $current_roles = $this->query($select_current_roles)->fetchAll();
                foreach ($current_roles as $row) {
                    $current_role[$row['role_id']] = true;
                }
                $vars['current_role'] = $current_role;
                
                // Modal форм рүү дамжуулж render хийе
                $this->template(__DIR__ . '/user-set-role-modal.html', $vars)->render();
            }
        } catch (\Throwable $err) {
            // Error handling - GET/POST ялгаж JSON эсвэл modal error руу шилжүүлнэ
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
            // LOGGING - Rollback/Success бүх тохиолдолд RBAC log үлдээдэг
            $context = ['action' => 'set-role', 'record_id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай хэрэглэгчийн дүрийг тохируулах үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $context += ['record' => $record];
                $message = '{record_id} дугаартай [{record.username}] хэрэглэгчийн дүрийг ';
                if ($this->getRequest()->getMethod() == 'POST') {
                    $level = LogLevel::INFO;
                    $message .= 'амжилттай тохируулав';
                } else {
                    $level = LogLevel::NOTICE;
                    $message .= 'тохируулах үйлдлийг эхлүүллээ';
                    $context += ['current_role' => $current_role];
                }
            }
            $this->log('users', $level, $message, $context);
        }
    }

    /**
     * RBAC дүрүүдийг update хийх (add/remove) үндсэн backend функц.
     * --------------------------------------------------------------
     * Энэхүү method нь хэрэглэгчийн хуучин дүрүүд болон UI-аас ирсэн
     * шинэ roles array-г харьцуулж:
     *
     *   Шинээр нэмэгдэх дүрүүдийг олох
     *   Хэрэглэгчээс хасагдах дүрүүдийг олох
     *   Root болон Coder дүртэй холбоотой тусгай хамгаалалтуудыг баримтлах
     *   Дүрийн өөрчлөлт бүрт системийн log үлдээх
     *
     * Аюулгүй байдлын гол зарчмууд:
     *   * id = 1 хэрэглэгч -> coder дүрийг хэзээ ч хасахгүй
     *   * coder роль (role_id = 1) -> зөвхөн root coder өөрчилнө
     *   * Жирийн хэрэглэгч coder role хасах/нэмэх боломжгүй
     *
     * @param int   $id        Дүр солигдож буй хэрэглэгчийн ID
     * @param array $roleSets  UI-аас ирсэн сонгосон role_id[] жагсаалт
     *
     * @return bool  Дүрийн өөрчлөлт хийгдсэн эсэх (true = өөрчлөгдсөн)
     */
    private function configureRoles(int $id, array $roleSets): bool
    {
        $configured = false;
        try {            
            if (!$this->isUserCan('system_user_organization_set')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            // Log бичихэд ашиглах logger instance бэлтгэх
            $logger = new Logger($this->pdo);
            $logger->setTable('users');
            // Log протоколд ашиглагдах authenticated user info
            $auth_user = [
                'id' => $this->getUser()->profile['id'],
                'username' => $this->getUser()->profile['username'],
                'first_name' => $this->getUser()->profile['first_name'],
                'last_name' => $this->getUser()->profile['last_name'],
                'phone' => $this->getUser()->profile['phone'],
                'email' => $this->getUser()->profile['email']
            ];
            
            // UI-аас ирсэн дүрүүдийг flip хийж dictionary болгох
            //    Формат: [role_id => true]
            $roles = \array_flip($roleSets);
            
            // Одоогийн хэрэглэгчийн дүрүүдийг databse-ээс авах
            $userRoleModel = new UserRole($this->pdo);
            $user_role = $userRoleModel->fetchAllRolesByUser($id);
            
            // Одоогийн дүрүүдийг шинээр ирсэнтэй харьцуулж "хасах" жагсаалт гаргах
            foreach ($user_role as $row) {
                // roleSets-д байвал -> keep, remove candidates-оос хасна
                if (isset($roles[$row['role_id']])) {
                    unset($roles[$row['role_id']]);
                    continue;
                }
                
                // Root хэрэглэгчийн coder role хэзээ ч хасагдахгүй
                if ($row['role_id'] == 1 && $id == 1) {
                    // can't delete root user's coder role!
                    continue;
                }
                
                // coder role-ийг зөвхөн system_coder л хасч чадна
                if ($row['role_id'] == 1 && !$this->isUser('system_coder')) {
                    // only coder can strip another coder role
                    continue;
                }
                
                // Дүр хасах
                if ($userRoleModel->deleteById($row['id'])) {
                    $configured = true;
                    
                    // strip log хийж үлдээх
                    $logger->log(
                        LogLevel::ALERT,
                        '{user_id}-р хэрэглэгчээс {role_id} дугаар бүхий дүрийг хаслаа',
                        ['action' => 'strip-role', 'user_id' => $id, 'role_id' => $row['role_id'], 'auth_user' => $auth_user]
                    );
                }
            }
            
            // Шинэ ирсэн roles array-д үлдсэн key-үүд -> нэмэх шаардлагатай дүрүүд
            foreach (\array_keys($roles) as $role_id) {
                if ($role_id == 1 && (
                    !$this->isUser('system_coder') || $this->getUserId() != 1)
                ) {
                    // system_coder role-г зөвхөн root coder нэмж чадна
                    // only root coder can add another coder role
                    continue;
                }
                
                // Role-г шинээр нэмэх
                if (!empty($userRoleModel->insert(['user_id' => $id, 'role_id' => $role_id]))) {
                    $configured = true;
                    
                    // set log хийж үлдээх
                    $logger->log(
                        LogLevel::ALERT,
                        '{user_id}-р хэрэглэгч дээр {role_id} дугаар бүхий дүр нэмлээ',
                        ['action' => 'set-role', 'user_id' => $id, 'role_id' => $role_id, 'auth_user' => $auth_user]
                    );
                }
            }
        } catch (\Throwable) {
            // Алдааг залгия (UI дээр crash биш)
        }
        return $configured;
    }

    /**
     * Email normalize хийх.
     *
     * Gmail/Googlemail: sub-addressing (+), dots арилгана,
     * googlemail.com -> gmail.com болгоно.
     *
     * @param string $email
     * @return string Normalized email
     */
    private function normalizeEmail(string $email): string
    {
        $email = \strtolower(\trim($email));
        if (\strpos($email, '@') === false) {
            return $email;
        }
        [$local, $domain] = \explode('@', $email, 2);

        $gmailDomains = ['gmail.com', 'googlemail.com'];
        if (\in_array($domain, $gmailDomains, true)) {
            $plusPos = \strpos($local, '+');
            if ($plusPos !== false) {
                $local = \substr($local, 0, $plusPos);
            }
            $local = \str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return "$local@$domain";
    }
}
