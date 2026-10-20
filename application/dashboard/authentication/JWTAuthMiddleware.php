<?php

namespace Dashboard\Authentication;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

use Dashboard\CacheService;
use Dashboard\RBAC\RBAC;
use Dashboard\RBAC\Role;
use Dashboard\RBAC\Roles;
use Dashboard\User\UsersModel;
use Dashboard\Authentication\User;
use Dashboard\Organization\OrganizationModel;
use Dashboard\Organization\OrganizationUserModel;

/**
 * Class JWTAuthMiddleware
 *
 * Raptor Dashboard болон Web хэсэгт нэвтэрсэн хэрэглэгчийн
 * баталгаажуулалтыг (Authentication) гүйцэтгэх middleware.
 *
 * Энэ middleware нь:
 *   - Session дотор хадгалсан JWT токеныг шалгана
 *   - JWT-г decode хийж, хугацаа нь дууссан эсэхийг үзнэ
 *   - Хэрэглэгч ба байгууллагын мэдээллийг баталгаажуулна
 *   - RBAC эрхүүдийг ачаалж 'User' объект үүсгэнэ
 *   - Дараагийн middleware / Controller рүү дамжуулна
 *
 * JWT байхгүй, буруу, хугацаа дууссан, эсвэл хэрэглэгч/байгууллага
 * тохирохгүй үед хэрэглэгчийг /dashboard/login хуудас руу redirect хийнэ.
 *
 * @package Dashboard\Authentication
 */
class JWTAuthMiddleware implements MiddlewareInterface
{
    /**
     * Орчны хувьсагчаар тодорхойлоогүй үед ашиглагдах үндсэн JWT алгоритм.
     */
    private const DEFAULT_ALGORITHM = 'HS256';

    /**
     * Act As горимын мэдээлэл хадгалах session key.
     * Утга: ['role_id' => int, 'started_at' => unix time]
     */
    public const ACT_AS_SESSION_KEY = 'RAPTOR_ACT_AS';

    /**
     * Act As горимын амьдрах хугацаа (секунд). Хугацаа дуусвал
     * coder өөрийн жинхэнэ эрх рүүгээ автоматаар буцна.
     */
    public const ACT_AS_LIFETIME = 3600;

    /**
     * JWT нууц түлхүүрийг ENV-аас уншина.
     * Хэрвээ тохируулагдаагүй бол Authentication-ийг шууд зогсооно.
     *
     * firebase/php-jwt 7.x хувилбарт HS256 алгоритмын хувьд түлхүүр
     * дор хаяж 32 байт (256 бит) байх ёстой. Hex-кодлогдсон түлхүүр
     * (128 тэмдэгт) ашиглаж байгаа бол энэ шаардлага хангагдсан байна.
     *
     * Энэ нь production орчин дахь аюулгүй байдлыг хамгаалах
     * хамгийн чухал safeguard юм.
     *
     * @return string
     * @throws RuntimeException
     */
    private function getSecret(): string
    {
        $secret = $_ENV['RAPTOR_JWT_SECRET'] ?? null;
        if (empty($secret)) {
            throw new \RuntimeException(
                'RAPTOR_JWT_SECRET тохируулагдаагүй байна. JWT баталгаажуулалт үргэлжлэх боломжгүй.'
            );
        }

        // firebase/php-jwt 7.x-д HS256 алгоритмын хувьд түлхүүр дор хаяж 32 байт байх ёстой
        $algorithm = $this->getAlgorithm();
        if ($algorithm === 'HS256' || $algorithm === 'HS384' || $algorithm === 'HS512') {
            $minLength = $algorithm === 'HS256' ? 32 : ($algorithm === 'HS384' ? 48 : 64);
            if (\strlen($secret) < $minLength) {
                throw new \RuntimeException(
                    "RAPTOR_JWT_SECRET түлхүүр урт хангалтгүй байна. $algorithm алгоритмын хувьд дор хаяж $minLength тэмдэгт байх шаардлагатай. " .
                    "Composer-ын post-root-package-install script нь автоматаар зөв урттай түлхүүр үүсгэнэ."
                );
            }
        }

        return $secret;
    }

    /**
     * JWT кодлох/тайлахад ашиглагдах алгоритмыг ENV-аас унших.
     * Хэрвээ тохируулагдаагүй бол анхны HS256 алгоритм хэрэглэнэ.
     *
     * @return string
     */
    private function getAlgorithm(): string
    {
        return $_ENV['RAPTOR_JWT_ALGORITHM'] ?? self::DEFAULT_ALGORITHM;
    }

    /**
     * JWT decode хийхэд шаардлагатай Key объект үүсгэдэг.
     *
     * @return Key
     */
    private function getKey(): Key
    {
        return new Key($this->getSecret(), $this->getAlgorithm());
    }

    /**
     * JWT токен үүсгэгч функц.
     * Нэвтэрсэн хэрэглэгчийн мэдээллийг payload дотор хадгална.
     *
     * Payload:
     *   - iat     : issued at
     *   - exp     : хугацаа дуусах огноо
     *   - seconds : токений амьдрах хугацаа
     *   - хэрэглэгч ба байгууллагын мэдээлэл
     *
     * firebase/php-jwt 7.x хувилбарт symmetric алгоритмуудын (HS256, HS384, HS512)
     * хувьд string түлхүүрийг шууд ашиглах боломжтой, гэхдээ түлхүүр урт хангалттай
     * байх ёстой (HS256-ийн хувьд дор хаяж 32 байт).
     *
     * @param array $data  Payload дотор орох мэдээлэл
     * @return string      Кодолсон JWT токен
     */
    public function generate(array $data): string
    {
        $issuedAt = \time();
        $lifeSeconds = (int) ($_ENV['RAPTOR_JWT_LIFETIME'] ?? 2592000); // 30 хоног - SessionMiddleware-ийн cookie lifetime-тай ижил
        $payload = [
            'iat' => $issuedAt,
            'exp' => $issuedAt + $lifeSeconds,
            'seconds' => $lifeSeconds,
        ] + $data;

        // getSecret() нь түлхүүрийн уртыг шалгана (HS256-ийн хувьд >= 32 байт)
        // firebase/php-jwt 7.x-д encode() нь string түлхүүрийг хүлээн авна
        return JWT::encode($payload, $this->getSecret(), $this->getAlgorithm());
    }

    /**
     * JWT токеныг decode хийж, хугацаа дууссан эсэх,
     * payload бүтэц бүрэн эсэхийг шалгана.
     *
     * @param string $jwt
     * @return array
     *
     * @throws RuntimeException JWT буруу, хугацаа дууссан,
     *                          эсвэл шаардлагатай талбар дутуу үед
     */
    public function validate(string $jwt): array
    {
        // Decode үед буруу бол Exception шиднэ
        $decoded = JWT::decode($jwt, $this->getKey());
        $result = (array) $decoded;
        if (($result['exp'] ?? 0) < \time()) {
            throw new \RuntimeException('JWT хугацаа дууссан байна.');
        }
        if (!isset($result['user_id']) || !isset($result['organization_id'])) {
            throw new \RuntimeException('JWT мэдээлэл дутуу байна.', 401);
        }
        return $result;
    }

    /**
     * Redirect хийх универсал PSR-7 арга.
     * ResponseFactory -> Response -> header() гэсэн 3 шаттай fallback.
     *
     * @param ServerRequestInterface $request
     * @param string $location
     * @param int $status
     * @return ResponseInterface
     */
    private function redirectResponse(
        ServerRequestInterface $request,
        string $location,
        int $status = 302
    ): ResponseInterface {
        // 1) ResponseFactory байгаа эсэх
        $factory = $request->getAttribute('responseFactory');
        if ($factory instanceof ResponseFactoryInterface) {
            return $factory->createResponse($status)->withHeader('Location', $location);
        }

        // 2) Request дотор Response өгөгдсөн эсэх
        $response = $request->getAttribute('response');
        if ($response instanceof ResponseInterface) {
            return $response->withStatus($status)->withHeader('Location', $location);
        }

        // 3) Сүүлийн арга - Browser redirect
        header("Location: $location", false, $status);
        exit;
    }

    /**
     * Middleware процесс.
     *
     * 1) Session-д хадгалсан JWT-г уншина
     * 2) JWT-г шалгана (decode + exp)
     * 3) Хэрэглэгчийн profile-г DB-с татаж шалгана
     * 4) Хэрэглэгч тухайн байгууллагад харьяалагдах эсэхийг хянана
     * 5) RBAC эрхүүдийг ачаалж 'User' объект үүсгэнэ
     * 6) Request attributes дотор user-г хадгалаад дараагийн middleware рүү дамжина
     *
     * Хэрвээ JWT асуудалтай бол -> login руу redirect хийнэ.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            // -------------------------------------------------------------
            // 1. JWT Session-д байхгүй бол нэвтрээгүй гэж үзнэ
            // -------------------------------------------------------------
            if (empty($_SESSION['RAPTOR_JWT'])) {
                throw new \RuntimeException('Session дотор JWT байхгүй байна.');
            }

            // -------------------------------------------------------------
            // 2. JWT decode + validate
            // -------------------------------------------------------------
            $result = $this->validate($_SESSION['RAPTOR_JWT']);

            // -------------------------------------------------------------
            // 3. Хэрэглэгчийн profile баталгаажуулах
            // -------------------------------------------------------------
            $pdo = $request->getAttribute('pdo');
            $users = new UsersModel($pdo);
            $profile = $users->getRowWhere([
                'id'        => $result['user_id'],
                'is_active' => 1,
            ]);
            if (!isset($profile['id'])) {
                throw new \RuntimeException('Хэрэглэгч олдсонгүй.', 404);
            }
            unset($profile['password']);

            // -------------------------------------------------------------
            // 4. RBAC эрхүүдийг ачаалах (cache-тэй)
            //    Байгууллагын шалгалтаас өмнө ачаална - хэрэглэгч system_coder
            //    эсэхийг мэдэж, cross-tenant хандалтыг ролиор шийдэхийн тулд.
            //
            //    Cache-ийг container-аас биш, шууд CacheService::fromDefaultPath()-
            //    аас авна: ContainerMiddleware энэ middleware-ээс хойно ажилладаг
            //    тул 'container' attribute энд хараахан байхгүй (өмнө нь энэ нь
            //    үргэлж null болж rbac cache огт ажилладаггүй байсан). Нэг хавтас
            //    ашигладаг тул RBACController-ийн cache invalidation хэвээр ажиллана.
            // -------------------------------------------------------------
            $cache = CacheService::fromDefaultPath();
            $rbacKey = "rbac.{$profile['id']}";
            $permissions = $cache?->get($rbacKey);
            if ($permissions === null) {
                $permissions = (new RBAC($pdo, $profile['id']))->jsonSerialize();
                $cache?->set($rbacKey, $permissions);
            }

            // -------------------------------------------------------------
            // 5. Хэрэглэгч тухайн байгууллагад хандах эрхтэй эсэхийг шалгах
            // -------------------------------------------------------------
            // Хүснэгтийн нэрийг Model-ийн getName() метод ашиглан динамикаар авна.
            // Ирээдүйд хүснэгтийн нэр өөрчлөгдвөл Model класс дахь setTable() засах хангалттай.
            $orgModel = new OrganizationModel($pdo);
            if (isset($permissions['system_coder'])) {
                // system_coder бол cross-tenant superuser: гишүүнчлэл шаардахгүй,
                // зөвхөн байгууллага идэвхтэй мөн эсэхийг шалгаад шууд оруулна.
                // organizations_users-т хуурамч мөр нэмэхгүй - хандах эрхийг
                // рольоос гаргаж авна, өгөгдлөөс биш. Ингэснээр role хасагдвал
                // хандалт нь өөрөө хаагдана (эрхийн үлдэгдэл үүсэхгүй).
                $stmt = $orgModel->prepare(
                    "SELECT * FROM {$orgModel->getName()} WHERE id=:org AND is_active=1 LIMIT 1"
                );
                $stmt->bindParam(':org', $result['organization_id'], \PDO::PARAM_INT);
                if (!$stmt->execute() || $stmt->rowCount() !== 1) {
                    throw new \RuntimeException('Байгууллага олдсонгүй эсвэл идэвхгүй байна.', 406);
                }
                $organization = $stmt->fetch();
            } else {
                // Энгийн хэрэглэгч: тухайн байгууллагад заавал харьяалагдах ёстой.
                $orgUserModel = new OrganizationUserModel($pdo);
                $stmt = $orgUserModel->prepare(
                    'SELECT t2.* ' .
                    "FROM {$orgUserModel->getName()} t1 " .
                    "INNER JOIN {$orgModel->getName()} t2 ON t1.organization_id=t2.id " .
                    'WHERE t1.user_id=:user AND t1.organization_id=:org AND t2.is_active=1 LIMIT 1'
                );
                $stmt->bindParam(':user', $result['user_id'], \PDO::PARAM_INT);
                $stmt->bindParam(':org',  $result['organization_id'], \PDO::PARAM_INT);
                if (!$stmt->execute() || $stmt->rowCount() !== 1) {
                    throw new \RuntimeException('Хэрэглэгч тухайн байгууллагад харьяалагдахгүй байна.', 406);
                }
                $organization = $stmt->fetch();
            }

            // -------------------------------------------------------------
            // 5a. Act As - сонгосон ролиор ажиллах (role preview)
            //
            //     Байгууллагын шалгалтын дараа солино: хэрэглэгчийн хандалт
            //     (одоогийн байгууллага) хэвээр үлдэж, зөвхөн эрхийн матриц
            //     сонгосон ролийн permission-ээр солигдоно. Ингэснээр sidebar,
            //     isUserCan(), search, badge бүгд тэр ролийн харагдацаар ажиллана.
            //     Солих логик нь rbac.{id} cache-аас хойно байх ёстой.
            //
            //     Хэн, ямар ролиор: canActAs() - хүсэлт бүрд ЖИНХЭНЭ эрхээр дахин
            //     шалгана (эхэлсний дараа эрх нь өөрчлөгдвөл горим дуусна). Эрх
            //     зөвхөн багасна: coder -> coder-оос бусад дурын роль,
            //     system_rbac -> бүх эрх нь өөрт байгаа роль, бусад -> өөрийн роль.
            //
            //     Critical (English): the swap runs AFTER the organization check
            //     and after the rbac.{id} cache read, and only when canActAs()
            //     accepts the role against the REAL permissions on this request.
            //     The replaced matrix must never contain system_coder and must
            //     never exceed the real permissions - Act As may only reduce rights.
            // -------------------------------------------------------------
            $realPermissions = $permissions;
            $actAs = null;
            if (!empty($_SESSION[self::ACT_AS_SESSION_KEY])) {
                $actAs = $this->resolveActAs($pdo, $cache, $_SESSION[self::ACT_AS_SESSION_KEY]);
                if ($actAs !== null
                    && !self::canActAs($realPermissions, $actAs['role'], $actAs['permissions'])
                ) {
                    $actAs = null;
                }
                if ($actAs === null) {
                    // Хугацаа дууссан эсвэл роль устгагдсан - session бичигдэх
                    // боломжтой бол цэвэрлэнэ, үгүй бол зүгээр үл тооцно.
                    if (\session_status() === \PHP_SESSION_ACTIVE) {
                        unset($_SESSION[self::ACT_AS_SESSION_KEY]);
                    }
                } else {
                    // Үйлдэл хориглохгүй: эрх зөвхөн багасна (canActAs()), user ID
                    // нь хэрэглэгчийнх тул лог, created_by зэрэгт жинхэнэ хийсэн
                    // хүн бичигдэнэ.
                    $permissions = [$actAs['role'] => $actAs['permissions']];
                    unset($actAs['permissions']);
                }
            }

            // -------------------------------------------------------------
            // 6. User объект үүсгэх
            // -------------------------------------------------------------
            $userObject = new User($profile, $organization, $permissions);

            // -------------------------------------------------------------
            // 7. Request-д user attribute нэмнэ (handle()-г try-ийн гадна нэг л удаа дуудна)
            // -------------------------------------------------------------
            $request = $request->withAttribute('user', $userObject);
            // Жинхэнэ (Act As-аар солигдоогүй) эрхийн матриц - ЗӨВХӨН Act As-ийн
            // эрхийн шалгалтад (ActAsController) ашиглана, бусад эрх шалгалтад биш
            //
            // Security (English): rbac_real is the unswapped matrix - use it only
            // for Act As eligibility, never for regular authorization checks.
            $request = $request->withAttribute('rbac_real', $realPermissions);
            if ($actAs !== null) {
                $request = $request->withAttribute('act_as', $actAs);
            }
        }

        // ==============================================================
        // JWT алдаа гарсан тохиолдол
        // ==============================================================
        catch (\Throwable $err) {
            // JWT-г дахин ашиглуулахгүйн тулд устгана
            if (isset($_SESSION['RAPTOR_JWT'])
                && \session_status() === \PHP_SESSION_ACTIVE
            ) {
                unset($_SESSION['RAPTOR_JWT']);
            }

            // Хөгжүүлэлтийн горимд байх үед лог хадгалах
            if (defined('CODESAUR_DEVELOPMENT')
                && CODESAUR_DEVELOPMENT
            ) {
                \error_log($err->getMessage());
            }

            // ---------------------------------------------------------
            // Redirect замыг зөв тооцоолох
            // ---------------------------------------------------------
            $path = \rawurldecode($request->getUri()->getPath());
            $scriptPath = \dirname($request->getServerParams()['SCRIPT_NAME']);
            if (($len = \strlen($scriptPath)) > 1) {
                $path = '/' . \ltrim(\substr($path, $len), '/');
            } else {
                $scriptPath = '';
            }

            // Login хуудас, эсвэл /protected/* замууд дээр login-redirect хийхгүй:
            //  - login     : anonymous-аар login form-оо render хийх ёстой.
            //  - protected : файл/API замууд (зураг, татац гэх мэт) тул HTML login
            //                руу 302 биш, цэвэр 401/403 буцаах ёстой.
            //
            // Конвенц: /protected/* segment доорх бүх route нь (одоогийн ба ирээдүйн)
            // anonymous-аар controller руу унаж, өөрсдөө auth шалгана - login-redirect-д
            // найдахгүй. Шинэ /protected/* route нэмэхдээ controller дотроо
            // isUserAuthorized() шалгаж 401/403 буцаахаа мартаж болохгүй.
            //
            // Convention (English): every route under the /protected/* segment -
            // current and future - falls through to its controller anonymously
            // and must do its own auth check. When adding a new /protected/*
            // route, do not forget to check isUserAuthorized() in the controller
            // and return 401/403 - there is no login-redirect safety net here.
            $segment = \explode('/', $path)[2] ?? '';
            if ($segment !== 'login' && $segment !== 'protected') {
                // Login хуудасны URL-д зөвхөн ?redirect=... query үлдээнэ -
                // хэрэглэгчийн орох гэсэн хуудсыг нэвтэрсний дараа нээхэд
                // LoginController ашиглана (open redirect-ээс тэнд шүүнэ).
                $loginUri = $request->getUri()
                    ->withPath("$scriptPath/dashboard/login")
                    ->withQuery('')
                    ->withFragment('');
                $target = $this->loginRedirectTarget($request, $path);
                if ($target !== null) {
                    $loginUri = $loginUri->withQuery('redirect=' . \rawurlencode($target));
                }
                return $this->redirectResponse($request, (string) $loginUri, 302);
            }

            // Login хуудас дээр байвал доорх ганц handle() рүү anonymous request-ээр fall-through
        }

        // Ганц handle() - try/catch-ийн гадна (CLAUDE.md-ийн middleware дүрэм):
        //   - auth амжилттай бол $request нь 'user' attribute-тай
        //   - login дээр auth алдсан бол original (anonymous) request
        // Downstream controller-ийн exception энд баригдахгүй, гадна талын ErrorHandler руу дамжина.
        return $handler->handle($request);
    }

    /**
     * Хэрэглэгч (жинхэнэ эрхээрээ) Act As цэсийг ашиглах боломжтой эсэх.
     *
     *   * system_coder -> тийм
     *   * system_rbac эрхтэй -> тийм (дүр тохируулдаг хүн дүрээ шалгана)
     *   * 2+ ролтой -> тийм (өөрийн ролиудаас нэгээр нь ажиллана)
     *   * ганц ролтой -> үгүй (өөрийн ганц ролиор "тоглох" утгагүй)
     *
     * @param array $realPermissions  RBAC::jsonSerialize() - [roleKey => [permission => true]]
     * @return bool
     */
    public static function canUseActAs(array $realPermissions): bool
    {
        return isset($realPermissions['system_coder'])
            || self::hasPermission($realPermissions, 'system_rbac')
            || \count($realPermissions) >= 2;
    }

    /**
     * Хэрэглэгч (жинхэнэ эрхээрээ) тухайн ролиор Act As хийж болох эсэх.
     *
     * Эрх хэзээ ч нэмэгдэхгүй байх нөхцөлүүд:
     *   * system_coder ролиор хэзээ ч болохгүй (бүх шалгалтыг TRUE болгоно)
     *   * system_coder -> бусад дурын роль
     *   * өөрийн эзэмшдэг роль -> болно (түүний эрх нийлбэр эрхэд багтана)
     *   * system_rbac эрхтэй -> ролийн бүх эрх өөрт байвал болно (subset)
     *   * бусад тохиолдолд болохгүй
     *
     * Security (English): Act As must only ever reduce rights. Keep this the
     * single eligibility rule for JWTAuthMiddleware (every request) and
     * ActAsController (role list + start).
     *
     * @param array  $realPermissions  RBAC::jsonSerialize() - [roleKey => [permission => true]]
     * @param string $roleKey          "{alias}_{name}"
     * @param array  $rolePermissions  [permission => true]
     * @return bool
     */
    public static function canActAs(array $realPermissions, string $roleKey, array $rolePermissions): bool
    {
        if ($roleKey === 'system_coder') {
            return false;
        }
        if (isset($realPermissions['system_coder']) || isset($realPermissions[$roleKey])) {
            return true;
        }
        if (!self::hasPermission($realPermissions, 'system_rbac')) {
            return false;
        }
        foreach ($rolePermissions as $permission => $granted) {
            if ($granted === true && !self::hasPermission($realPermissions, $permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Эрхийн матрицын аль нэг ролиор permission олгогдсон эсэх.
     *
     * @param array  $permissions  [roleKey => [permission => true]]
     * @param string $permission
     * @return bool
     */
    private static function hasPermission(array $permissions, string $permission): bool
    {
        foreach ($permissions as $rolePermissions) {
            if (($rolePermissions[$permission] ?? false) === true) {
                return true;
            }
        }
        return false;
    }

    /**
     * Session дахь Act As мэдээллээс сонгосон ролийн эрхийг ачаална.
     *
     * Ролийн permission-ийг rbac.act-as.{roleId} key-д cache хийнэ. RBAC
     * өөрчлөлт бүр (RBACController) cache-ийг бүхэлд нь clear() хийдэг тул
     * тусдаа invalidation хэрэггүй.
     *
     * @param \PDO       $pdo
     * @param mixed      $cache   CacheService эсвэл null
     * @param mixed      $session $_SESSION[ACT_AS_SESSION_KEY] утга
     * @return array|null ['role', 'role_id', 'description', 'started_at', 'expires_at', 'permissions']
     *                    эсвэл хугацаа дууссан / роль олдоогүй / system_coder бол null
     */
    private function resolveActAs(\PDO $pdo, $cache, mixed $session): ?array
    {
        $roleId = (int) ($session['role_id'] ?? 0);
        $startedAt = (int) ($session['started_at'] ?? 0);
        $expiresAt = $startedAt + self::ACT_AS_LIFETIME;
        if ($roleId < 1 || $expiresAt < \time()) {
            return null;
        }

        $cacheKey = "rbac.act-as.$roleId";
        $data = $cache?->get($cacheKey);
        if ($data === null) {
            $role = (new Roles($pdo))->getRowWhere(['id' => $roleId]);
            if (!isset($role['id'])) {
                return null;
            }
            $data = [
                'role'        => "{$role['alias']}_{$role['name']}",
                'description' => $role['description'] ?? '',
                'permissions' => (new Role())->fetchPermissions($pdo, $roleId)->permissions
            ];
            $cache?->set($cacheKey, $data);
        }

        // Coder ролиор "Act As" хийх нь утгагүй бөгөөд бүх шалгалтыг TRUE болгоно
        if ($data['role'] === 'system_coder') {
            return null;
        }

        return $data + [
            'role_id'    => $roleId,
            'started_at' => $startedAt,
            'expires_at' => $expiresAt
        ];
    }

    /**
     * Нэвтрээгүй хэрэглэгчийг login руу илгээхдээ буцаж очих замыг тодорхойлно.
     *
     * Зөвхөн хөтчийн шууд хуудас нээлт (GET/HEAD + Accept: text/html) дээр
     * утга буцаана - fetch()-ээр дуудагдсан JSON/fragment зам (жишээ нь
     * /dashboard/news/list) session дууссан үед login руу унавал түүнийг
     * буцах зам болгож болохгүй, нэвтэрсний дараа хэрэглэгч хоосон JSON
     * хуудас руу очно. Dashboard root болон home-д мөн null - тэдгээр нь
     * нэвтэрсний дараах default хуудас тул URL бохирдуулах хэрэггүй.
     *
     * @param ServerRequestInterface $request        Одоогийн хүсэлт
     * @param string                 $mountRelative  Script path хассан зам (/dashboard/news)
     * @return string|null Script path-тай бүтэн зам + query, эсвэл null
     */
    private function loginRedirectTarget(ServerRequestInterface $request, string $mountRelative): ?string
    {
        if (!\in_array($request->getMethod(), ['GET', 'HEAD'], true)
            || !\str_contains($request->getHeaderLine('Accept'), 'text/html')
        ) {
            return null;
        }

        $trimmed = \rtrim($mountRelative, '/');
        if ($trimmed === '' || $trimmed === '/dashboard' || $trimmed === '/dashboard/home') {
            return null;
        }

        $uri = $request->getUri();
        $target = $uri->getPath();
        if ($uri->getQuery() !== '') {
            $target .= '?' . $uri->getQuery();
        }
        return $target;
    }
}
