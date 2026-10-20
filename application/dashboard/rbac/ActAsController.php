<?php

namespace Dashboard\RBAC;

use Psr\Log\LogLevel;

use Dashboard\Authentication\JWTAuthMiddleware;

/**
 * ActAsController - сонгосон ролиор dashboard-ыг ашиглах ("Act As" /
 * role preview) горимыг эхлүүлэх, дуусгах.
 *
 * Зорилго:
 * ---------------------------------------------------------------
 *  Тухайн рольтой хэрэглэгчдэд ямар цэс, товч, үйлдэл харагдаж, ажиллаж
 *  байгааг (controller-ийн эрхийн шалгалтыг оролцуулан) өөрөө шалгах -
 *  жишээ нь дүр тохируулсны дараа, эсвэл доод дүрийн хүмүүст зааварчилгаа
 *  өгөхийн өмнө.
 *
 * Хэн, ямар ролиор (JWTAuthMiddleware::canUseActAs() / canActAs()):
 * ---------------------------------------------------------------
 *  - system_coder        -> coder-оос бусад дурын роль
 *  - system_rbac эрхтэй  -> бүх эрх нь өөрт байгаа дурын роль
 *  - бусад, 2+ ролтой    -> зөвхөн өөрийн эзэмшдэг ролиуд
 *  - ганц ролтой         -> боломжгүй
 *
 * Ажиллах зарчим:
 * ---------------------------------------------------------------
 *  - start() нь $_SESSION[RAPTOR_ACT_AS]-д role_id + эхэлсэн цагийг бичнэ.
 *  - JWTAuthMiddleware хүсэлт бүрд canActAs()-ийг жинхэнэ эрхээр дахин шалгаж,
 *    эрхийн матрицыг тэр ролийн permission-ээр солино (user ID, байгууллага хэвээр).
 *  - Үйлдэл хориглохгүй: эрх зөвхөн багасдаг тул эрх нэмэгдэх эрсдэлгүй.
 *    Хийсэн өөрчлөлт бодит бөгөөд хэрэглэгчийн өөрийн user ID-гаар бүртгэгдэж,
 *    лог бүрд auth_user.act_as тэмдэглэгдэнэ.
 *  - ACT_AS_LIFETIME (1 цаг)-ийн дараа автоматаар дуусна.
 *
 * Хязгаарлалт:
 * ---------------------------------------------------------------
 *  getUserId() нь хэрэглэгчийн өөрийн ID хэвээр тул "өөрийн нийтлэгдээгүй
 *  бичлэгийг засах" owner шалгалт өөрийн бичлэг дээр үйлчилнэ. Цэсийн
 *  organization alias шүүлт нь одоогийн байгууллагаар явна.
 *
 * Route-ууд нь /act-as/ угтвартай - Dashboard\Application-ийн SessionMiddleware
 * closure тэр угтвар дээр session-г бичигдэх боломжтой үлдээдэг.
 */
class ActAsController extends \Dashboard\Controller
{
    use \Dashboard\Template\DashboardTrait;

    /**
     * Роль сонгох modal (GET).
     *
     * @return void
     */
    public function roles()
    {
        try {
            $real = $this->getRealPermissions();
            if (!JWTAuthMiddleware::canUseActAs($real)) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $roles_table = (new Roles($this->pdo))->getName();
            $stmt = $this->prepare(
                "SELECT id, alias, name, description FROM $roles_table
                 WHERE NOT (name='coder' AND alias='system')
                 ORDER BY alias, name"
            );
            $stmt->execute();
            $roles = [];
            foreach ($stmt->fetchAll() as $row) {
                // Зөвхөн хэрэглэгчид зөвшөөрөгдөх ролиуд (JWTAuthMiddleware-тэй ижил дүрэм)
                $key = "{$row['alias']}_{$row['name']}";
                $permissions = (new Role())->fetchPermissions($this->pdo, (int) $row['id'])->permissions;
                if (JWTAuthMiddleware::canActAs($real, $key, $permissions)) {
                    $roles[$row['alias']][] = $row;
                }
            }

            $this->template(__DIR__ . '/act-as-modal.html', [
                'roles'    => $roles,
                'lifetime' => (int) (JWTAuthMiddleware::ACT_AS_LIFETIME / 60)
            ])->render();
        } catch (\Throwable $err) {
            $this->modalProhibited($err->getMessage(), $err->getCode())->render();
        }
    }

    /**
     * Act As горимыг эхлүүлэх (POST, body: role_id).
     *
     * @return void
     */
    public function start()
    {
        try {
            $real = $this->getRealPermissions();
            if (!JWTAuthMiddleware::canUseActAs($real)) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $role_id = (int) ($this->getParsedBody()['role_id'] ?? 0);
            $record = $role_id > 0
                ? (new Roles($this->pdo))->getRowWhere(['id' => $role_id])
                : null;
            if (!isset($record['id'])) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            if ($record['alias'] === 'system' && $record['name'] === 'coder') {
                throw new \InvalidArgumentException($this->text('invalid-values'), 400);
            }
            $permissions = (new Role())->fetchPermissions($this->pdo, $role_id)->permissions;
            if (!JWTAuthMiddleware::canActAs($real, "{$record['alias']}_{$record['name']}", $permissions)) {
                throw new \Exception($this->text('role-exceeds-own-permissions'), 403);
            }

            $_SESSION[JWTAuthMiddleware::ACT_AS_SESSION_KEY] = [
                'role_id'    => (int) $record['id'],
                'started_at' => \time()
            ];

            $this->respondJSON([
                'status'  => 'success',
                'message' => "{$record['alias']}_{$record['name']}"
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status'  => 'error',
                'type'    => 'danger',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            $context = ['action' => 'act-as-start'];
            if (isset($err)) {
                $level = LogLevel::ERROR;
                $message = 'Act As горимыг эхлүүлэх үед алдаа гарлаа';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = '{auth_user.username} хэрэглэгч [{role}] ролиор Act As горимд орлоо';
                $context += ['role' => "{$record['alias']}_{$record['name']}", 'role_id' => $record['id']];
            }
            $this->log('dashboard', $level, $message, $context);
        }
    }

    /**
     * Хэрэглэгчийн жинхэнэ (Act As-аар солигдоогүй) эрхийн матриц.
     *
     * JWTAuthMiddleware 'rbac_real' attribute-д тавьдаг. Зөвхөн Act As-ийн
     * эрхийн шалгалтад ашиглана - энгийн эрх шалгалт isUserCan()-аар явна.
     *
     * @return array  [roleKey => [permission => true]]
     */
    private function getRealPermissions(): array
    {
        if (!$this->isUserAuthorized()) {
            return [];
        }
        $real = $this->getAttribute('rbac_real');
        return \is_array($real) ? $real : [];
    }

    /**
     * Act As горимоос гарах (POST).
     *
     * Act As идэвхтэй үед хэрэглэгч сонгосон ролийн эрхтэй мэт харагддаг тул
     * зөвхөн нэвтэрсэн эсэхийг шалгана - session-оос key устгах нь эрх
     * нэмэхгүй, зөвхөн жинхэнэ эрх рүү буцаана.
     *
     * @return void
     */
    public function stop()
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $act_as = $this->getAttribute('act_as');
            unset($_SESSION[JWTAuthMiddleware::ACT_AS_SESSION_KEY]);

            $this->respondJSON(['status' => 'success']);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status'  => 'error',
                'type'    => 'danger',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            if (!isset($err) && !empty($act_as)) {
                $this->log(
                    'dashboard',
                    LogLevel::NOTICE,
                    '{auth_user.username} хэрэглэгч [{role}] ролийн Act As горимоос гарлаа',
                    ['action' => 'act-as-stop', 'role' => $act_as['role'], 'role_id' => $act_as['role_id']]
                );
            }
        }
    }
}
