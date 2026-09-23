# Raptor API Reference (MN)

> Бүх модуль, класс, методуудын дэлгэрэнгүй тайлбар.

---

## Агуулга

1. [Dashboard\Controller](#dashboardcontroller)
2. [Dashboard\Application](#dashboardapplication)
3. [Authentication](#authentication)
4. [User](#user)
5. [Organization](#organization)
6. [RBAC](#rbac)
7. [Files](#files)
8. [Content - News](#content--news)
9. [Content - Comments](#content--comments)
10. [Content - Messages](#content--messages)
11. [Content - Pages](#content--pages)
12. [Content - References](#content--references)
13. [Content - Settings](#content--settings)
14. [Localization](#localization)
15. [Log](#log)
16. [Mail](#mail)
17. [Database Middleware](#database-middleware)
18. [SpamProtectionTrait](#spamprotectiontrait)
19. [CsrfMiddleware](#csrfmiddleware)
20. [HtmlValidationTrait](#htmlvalidationtrait)
21. [DashboardTrait](#dashboardtrait)
22. [FileController](#filecontroller)
23. [AIHelper](#aihelper)
24. [Badge систем](#badge-систем)
25. [MenuModel](#menumodel)
26. [Dashboard Home](#dashboard-home)
27. [Dashboard Manual](#dashboard-manual)
28. [Web Layer](#web-layer)
29. [Shop](#shop)
30. [Notification](#notification)
31. [Development](#development)
32. [Migration](#migration)
33. [Cache](#cache)
34. [Seed болон анхдагч дата](#seed-болон-анхдагч-дата)
35. [Trash (Хогийн сав)](#trash-хогийн-сав)
36. [Event систем](#event-систем)

---

## Dashboard\Controller

**Файл:** `application/dashboard/Controller.php`
**Extends:** `codesaur\Http\Application\Controller`
**Uses:** `codesaur\DataObject\PDOTrait`

Бүх Controller-ийн суурь анги.

### Properties

| Property | Type | Тайлбар |
|----------|------|---------|
| `$pdo` | `\PDO` | Өгөгдлийн сангийн холболт (PDOTrait-аар) |

### Methods

#### `__construct(ServerRequestInterface $request)`
Request-аас PDO instance-г авч `$this->pdo`-д оноох.

#### `getUser(): ?User`
Нэвтэрсэн хэрэглэгчийн `User` объект. Нэвтрээгүй бол `null`.

#### `getUserId(): ?int`
Нэвтэрсэн хэрэглэгчийн ID. Нэвтрээгүй бол `null`.

#### `isUserAuthorized(): bool`
Хэрэглэгч нэвтэрсэн эсэх.

#### `isUser(string $role): bool`
Хэрэглэгч тодорхой RBAC role-тэй эсэх.

#### `isUserCan(string $permission): bool`
Хэрэглэгч тодорхой RBAC permission-тэй эсэх.

#### `getLanguageCode(): string`
Идэвхтэй хэлний код (`'mn'`, `'en'` гэх мэт). Олдохгүй бол `''`.

#### `getLanguages(): array`
Бүртгэлтэй бүх хэлний жагсаалт.

#### `text(string $key, mixed $default = null): string`
Орчуулгын текст авах. Олдохгүй бол `$default` эсвэл `{key}`.

#### `template(string $template, array $vars = []): FileTemplate`
FileTemplate үүсгэх. Автоматаар `user`, `index`, `localization`, `csrf_token`, `waf_body_encoding` хувьсагчид нэмэгдэнэ. `text`, `link`, `pattern` filter-ууд бүртгэгдэнэ (`pattern` нь route pattern-ийг `{placeholder}`-той нь хэвээр буцаана - client талын JS рендерт зориулсан).

#### `respondJSON(array $response, int|string $code = 0): void`
JSON хариулт хэвлэх. `Content-Type: application/json` header тохируулна. `$code` нь хүчинтэй HTTP статус (100-599) бол `headerResponseCode()`-оор онооно; эс бөгөөс хариу `200` хэвээр үлдэнэ.

#### `redirectTo(string $routeName, array $params = []): void`
Route нэрээр 302 redirect хийх. `exit` дуудна.

#### `log(string $table, string $level, string $message, array $context = []): void`
Мэдээллийн баазийн `{$table}_log` нэртэй хүснэгт рүү системийн лог бичих. Server request metadata болон хэрэглэгчийн мэдээлэл автоматаар нэмэгдэнэ.

#### `dispatch(object $event): void`
DI container-ийн `EventDispatcher` сервисээр PSR-14 event дамжуулах. Dispatcher байхгүй бол чимээгүй алгасна.

#### `generateRouteLink(string $routeName, array $params = [], bool $is_absolute = false, string $default = '#'): string`
Route нэрээр URL үүсгэх.

#### `getContainer(): ?ContainerInterface`
DI Container авах.

#### `getService(string $id): mixed`
Container-аас service авах.

#### `hasService(string $id): bool`
Container-д тухайн service бүртгэлтэй эсэхийг буцаана (container байхгүй бол `false`).

#### `invalidateCache(string ...$keys): void`
Заасан cache key-үүдийг устгана. `{code}` placeholder ашиглавал бүх хэлээр давтана. Cache байхгүй бол алгасна.

```php
$this->invalidateCache('pages_nav.{code}', 'featured_pages.{code}');
$this->invalidateCache('texts.{code}');
$this->invalidateCache('languages');
```

#### `headerResponseCode(int|string $code): void`
HTTP response code-ыг зөвхөн `$code` нь тоон утга бөгөөд стандарт HTTP мужид (RFC 9110: 100-599) багтах үед онооно. Тоон бус, мужаас гадуур, эсвэл `200` (default) бол алгасна - стандарт бус код хэзээ ч илгээгдэхгүй.

#### `getScriptPath(): string`
Script path буцаах (subdirectory дэмжлэг).

#### `getDocumentRoot(): string`
Document root зам буцаах.

#### `getMountPath(): string`
Ажиллаж буй Application-ий mount path буцаана (жш: `/dashboard`, root дээр mount хийсэн бол `''`).

#### `setLanguageCode(string $code): void`
`LocalizationMiddleware`-ийн өгсөн session key-д (`localization['session_key']`) хэлний код бичнэ; key нь `null` бол (Web) юу ч хийхгүй.

---

## Middleware-ийн аюулгүй байдлын дүрэм

### handle()-г try/catch дотор хэзээ ч дуудаж болохгүй

Middleware runner нь дотоод array pointer (`current()`/`next()`) ашиглан queue-г дамждаг. `$handler->handle()` дуудагдах бүрт pointer нэг алхам ахина - буцаах боломжгүй.

**Хэрвээ `handle()`-г `try` блок дотор дуудвал**, гүнд exception уусаад буцаж ирэхэд `catch` блок барьж авна - гэхдээ pointer аль хэдийн ахисан байна. Тэгээд `try`-ийн гадна дахин `handle()` дуудвал pointer хэтэрч, `current()` нь `false` буцааж програм унана.

```php
// Буруу - exception уусахад handle() давхар дуудагдана
public function process($request, $handler): ResponseInterface
{
    try {
        $data = $cache->get('key');
        if ($data !== null) {
            return $handler->handle($request->withAttribute('data', $data));
            //     ^^^^^^^^^^^^^^^ try дотор дуудагдсан - pointer ахина
            //     Гүнд exception уусвал catch барьж авна,
            //     тэгээд доорх handle() дахиад дуудагдана
        }
        $data = $this->loadFromDb();
    } catch (\Throwable $e) {
        \error_log($e->getMessage());
        // Exception чимээгүй баригдсан - гүйцэтгэл үргэлжилнэ
    }
    return $handler->handle($request->withAttribute('data', $data ?? []));
    //     ^^^^^^^^^^^^^^^ хоёр дахь дуудалт - pointer хэтэрсэн -> crash
}
```

```php
// Зөв - handle()-г зөвхөн нэг удаа, try-ийн гадна дуудна
public function process($request, $handler): ResponseInterface
{
    $data = [];
    try {
        $cached = $cache->get('key');
        if ($cached !== null) {
            $data = $cached;          // Зөвхөн data бэлтгэх, handle() дуудахгүй
        } else {
            $data = $this->loadFromDb();
        }
    } catch (\Throwable $e) {
        \error_log($e->getMessage());
    }
    return $handler->handle($request->withAttribute('data', $data));
    //     ^^^^^^^^^^^^^^^ зөвхөн нэг удаа, try-ийн гадна дуудагдана
}
```

**Дүрмүүд:**
- `$handler->handle()` нь middleware бүрт яг **нэг** удаа дуудагдах ёстой
- Тэр дуудалт нь `try/catch` блокийн **гадна** байх ёстой
- `try/catch` нь зөвхөн data бэлтгэх логикийг (DB query, cache read, validation) хамрах ёстой
- `catch` блок нь алдааг зохицуулаад (лог, default утга), гүйцэтгэлийг ганц `handle()` дуудалт руу урсгах ёстой

---

## Dashboard\Application

**Файл:** `application/dashboard/Application.php`
**Extends:** `codesaur\Http\Application\Application`

Dashboard Application. Middleware pipeline болон бүх Router-уудыг бүртгэнэ.

PDO холболт нь `public_html/index.php` дотор
`\Dashboard\DatabaseConnection::connect()`-ээр нэг л удаа үүсэн request-ийн
`pdo` attribute хэлбэрээр Application руу ирдэг.

### Constructor Pipeline

1. `ErrorHandler` - Алдаа барих
2. `MethodOverrideMiddleware` - `X-HTTP-Method-Override`-аас PUT/PATCH/DELETE-г сэргээнэ (WAF verb-block-ийн шийдэл); Session/routing-аас өмнө ажиллаж жинхэнэ verb-ийг бүх давхаргад харагдуулна
3. `BodyEncodingMiddleware` - `X-Body-Encoding`-той ирсэн form талбаруудыг base64-аас decode хийнэ (WAF body-inspection-ийн шийдэл)
4. `SessionMiddleware` - Session удирдлага
5. `JWTAuthMiddleware` - JWT баталгаажуулалт
6. `ContainerMiddleware` - DI Container
7. `LocalizationMiddleware` - Олон хэл
8. `SettingsMiddleware` - Тохиргоо
9. `LoginRouter`, `UsersRouter`, `OrganizationRouter`, `RBACRouter`, `LocalizationRouter`, `ContentsRouter`, `LogsRouter`, `MigrationRouter`, `TrashRouter`, `TemplateRouter`, `HomeRouter`, `ShopRouter` (products + orders + reviews), `ManualRouter`, `Development\DevelopmentRouter` (хөгжүүлэлтийн хүсэлт `application/dashboard/development/`-д байрлана), `File\FileRouter`, `Badge\BadgeRouter` (sidebar badge систем `application/dashboard/badge/`-д байрлана)

`CsrfMiddleware` нь app-wide pipeline-д биш - router дээр mutating route бүрд per-route наагдана.

---

## Authentication

### JWTAuthMiddleware

**Файл:** `application/dashboard/authentication/JWTAuthMiddleware.php`
**Implements:** `MiddlewareInterface`

#### `generate(array $data): string`
JWT токен үүсгэх. Payload дотор `iat`, `exp`, `seconds` + `$data` орно.

#### `validate(string $jwt): array`
JWT decode + validate хийх. Хугацаа дууссан бол `RuntimeException`. `user_id`, `organization_id` шаардлагатай.

#### `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`
1. `$_SESSION['RAPTOR_JWT']` уншина
2. JWT validate хийнэ
3. Хэрэглэгчийн profile DB-с татна
4. RBAC эрхүүдийг ачаална (`rbac.{userId}` cache) - байгууллагын шалгалтаас өмнө, coder эсэхийг мэдэхийн тулд
5. Байгууллагын хандалтыг шалгана: энгийн хэрэглэгчид `organizations_users` гишүүнчлэлийн мөр заавал; `system_coder` бол cross-tenant superuser тул зөвхөн байгууллага идэвхтэй байхад хангалттай (хандах эрх рольоос гарна - гишүүнчлэлийн мөр шаардахгүй, үүсгэхгүй)
6. `User` объект үүсгэж request attribute-д нэмнэ
7. Алдаа гарвал `/dashboard/login` руу redirect хийнэ - харин замын хоёр дахь сегмент нь `login` эсвэл `protected` (`/dashboard/login/*`, `/dashboard/protected/*`) бол redirect хийхгүй: хүсэлт `user` attribute-гүйгээр (anonymous) controller-т хүрэх тул тэдгээр controller өөрсдөө `isUserAuthorized()` шалгалт хийнэ. Хөтчийн хуудас нээлт (GET/HEAD + `Accept: text/html`, dashboard root/home-оос бусад) дээр анхны зам + query-г `?redirect=...` параметрээр дамжуулна - `LoginController` үүнийг шүүж (зөвхөн dashboard mount доорх same-origin зам), амжилттай нэвтэрсний дараа `login.html` тэр хуудас руу шилжүүлнэ

### SessionMiddleware

**Файл:** `application/dashboard/SessionMiddleware.php`
**Implements:** `MiddlewareInterface`

Dashboard болон Web app хоёуланд ашиглагдах session middleware.
Session эхлүүлж, read-only route дээр write-lock-ийг эрт суллана.

Raptor нь session cookie-ийн хугацааг кодоос 30 хоног болгодог (`session_set_cookie_params(...)`); server талын `gc_maxlifetime` / `save_path` нь PHP / host тохиргоогоор ажиллана. Host бүрд тааруулах (эсвэл тэр мөрийг устгаад php.ini-д даатгах) талаар [SESSION-LIFETIME.md](SESSION-LIFETIME.md)-аас үз.

Constructor-аар `needsWrite` closure авна:
- Dashboard: `fn($path, $method) => str_contains($path, '/login') || empty($_SESSION['CSRF_TOKEN'])` (хоёр дахь нөхцөл нь CSRF token байхгүй үед үүсгэж чадахаар session-ийг бичих боломжтой үлдээнэ)
- Web: `fn($path, $method) => str_starts_with(preg_replace('#^/[a-z]{2}(?=/|$)#', '', $path), '/session/')` (эхэнд байгаа хэлний prefix-ийг хасаад `/session/` route prefix-тэй тулгана)

Closure null бол бүх route дээр session_write_close() дуудна.

### LoginRouter

**Файл:** `application/dashboard/authentication/LoginRouter.php`

| Маршрут | Метод | Нэр | Тайлбар |
|---------|-------|-----|---------|
| `/dashboard/login` | GET | `login` | Нэвтрэх хуудас |
| `/dashboard/login/try` | POST | `entry` | Нэвтрэх оролдлого |
| `/dashboard/login/logout` | GET | `logout` | Гарах |
| `/dashboard/login/forgot` | POST | `login-forgot` | Нууц үг сэргээх |
| `/dashboard/login/signup` | POST | `signup` | Бүртгүүлэх |
| `/dashboard/login/language/{code}` | GET | `language` | Хэл солих |
| `/dashboard/login/set/password` | POST | `login-set-password` | Нууц үг тохируулах |
| `/dashboard/login/organization/{uint:id}` | GET | `login-select-organization` | Байгууллага сонгох |

### User (Value Object)

**Файл:** `application/dashboard/authentication/User.php`

| Property | Type | Тайлбар |
|----------|------|---------|
| `$profile` | `array` | Хэрэглэгчийн profile |
| `$organization` | `array` | Байгууллагын мэдээлэл |

RBAC матриц (`RBAC::jsonSerialize()`-ийн үр дүн, `{alias}_{role} => [permission => true]` бүтэцтэй) нь private readonly `$rbac` property-д хадгалагдах бөгөөд зөвхөн `is()` / `hasRoleAlias()` / `can()`-аар хандана.

| Метод | Тайлбар |
|-------|---------|
| `is(string $role): bool` | Role шалгах |
| `hasRoleAlias(string $alias): bool` | Тухайн alias-д хамаарах ямар ч роль эзэмшдэг эсэхийг шалгах (роль key нь `{alias}_{name}`) - multi-tenant visibility-г alias-аар хянахад ашиглана |
| `can(string $permission, ?string $role = null): bool` | Permission шалгах; `$role` өгсөн бол зөвхөн тэр рольд байгаа эсэхийг шалгана. `system_coder` үргэлж `true` |

---

## User

### UsersModel

**Файл:** `application/dashboard/user/UsersModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `users`

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `username` | varchar(128), unique | Нэвтрэх нэр |
| `password` | varchar(255), default `''` | Bcrypt hash |
| `first_name` | varchar(128) | Нэр |
| `last_name` | varchar(128) | Овог |
| `phone` | varchar(128) | Утас |
| `email` | varchar(128), unique | И-мэйл хаяг |
| `photo` | varchar(255) | Avatar-ын нийтийн URL |
| `photo_file` | varchar(255) | Avatar-ын бодит файлын зам |
| `photo_size` | int | Avatar-ын хэмжээ (bytes) |
| `code` | varchar(2) | Сонгосон хэлний код |
| `is_active` | tinyint, default 1 | Идэвхтэй эсэх |
| `created_at` | datetime | Үүсгэсэн огноо |
| `created_by` | bigint | Үүсгэсэн хэрэглэгч |
| `updated_at` | datetime | Шинэчилсэн огноо |
| `updated_by` | bigint | Шинэчилсэн хэрэглэгч |

---

## Organization

### OrganizationModel

**Файл:** `application/dashboard/organization/OrganizationModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `organizations`

### OrganizationUserModel

**Файл:** `application/dashboard/organization/OrganizationUserModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `organizations_users`

Хэрэглэгч-байгууллагын холбоос хүснэгт.

---

## RBAC

### RBAC

**Файл:** `application/dashboard/rbac/RBAC.php`

Хэрэглэгчийн бүх role болон permission-г ачаалж `jsonSerialize()` хэлбэрээр буцаадаг.

### Role

**Файл:** `application/dashboard/rbac/Role.php`

Ажиллагааны үеийн value object (Model биш). `fetchPermissions(\PDO $pdo, int $role_id)` нь рольд хамаарах permission-үүдийг `{alias}_{name} => true` хэлбэрээр ачаална; `hasPermission(string $permissionName): bool` нь O(1) хайлт. `RBAC` нь `{alias}_{name}` роль key бүрт нэг `Role` хадгална.

### Roles

**Файл:** `application/dashboard/rbac/Roles.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `rbac_roles`

### Permissions

**Файл:** `application/dashboard/rbac/Permissions.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `rbac_permissions`

### RolePermission

**Файл:** `application/dashboard/rbac/RolePermission.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `rbac_role_permission`

Role-Permission хамаарал (`role_id`, `permission_id`, `alias`).

### UserRole

**Файл:** `application/dashboard/rbac/UserRole.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `rbac_user_role`

User-Role хамаарал.

---

## Files

### FilesModel

**Файл:** `application/dashboard/file/FilesModel.php`
**Extends:** `codesaur\DataObject\Model`

Файлуудын мэдээлэл хадгалах. `setTable($name)` нь `{name}_files` хүснэгтэд харгалзана (жш: `setTable('news')` -> `news_files`).

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `record_id` | bigint | Эцэг хүснэгт дэх холбогдох бичлэгийн ID (0 = холбогдоогүй) |
| `file` | varchar(255) | Сервер дэх файлын бүтэн (absolute) зам |
| `path` | varchar(255), default `''` | Файлын нийтийн URL |
| `size` | int | Файлын хэмжээ (bytes) |
| `type` | varchar(24) | Файлын төрөл (image, audio, video, application...) |
| `mime_content_type` | varchar(127) | MIME type |
| `keyword` | varchar(32) | Түлхүүр үг |
| `description` | varchar(255) | Тайлбар |
| `created_at` | datetime | Үүсгэсэн огноо |
| `created_by` | bigint | Үүсгэсэн хэрэглэгч |
| `updated_at` | datetime | Шинэчилсэн огноо |
| `updated_by` | bigint | Шинэчилсэн хэрэглэгч |

### FilesController

**Файл:** `application/dashboard/file/FilesController.php`

| Метод | Тайлбар |
|-------|---------|
| `index()` | Файлын менежмент хуудас |
| `list(string $table)` | JSON файлын жагсаалт |
| `upload()` | Файл upload хийх (хадгалахгүй, зөвхөн зөөх) |
| `post(string $table, int $record_id = 0)` | Upload + `{table}_files`-д бүртгэх; route-оор дуудахад зөвхөн `files` хүснэгтийг зөвшөөрнө (`record_id` 0 хэвээр) |
| `modal(string $table)` | Файл сонгох modal |
| `update(string $table, int $id)` | Файлын мэдээлэл шинэчлэх |
| `delete(string $table)` | DB бичлэгийг устгаж (зөвхөн `files` хүснэгт) Хогийн савд нөөцлөнө; бодит файл диск дээр үлдэнэ. `system_content_delete` эрх, эсвэл холбогдоогүй файлын эзэн байх шаардлагатай |

### FileRouter - Files маршрутууд

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/files` | GET | `files` |
| `/dashboard/files/list/{table}` | GET | `files-list` |
| `/dashboard/files/upload` | POST | `files-upload` |
| `/dashboard/files/post/{table}` | POST | `files-post` |
| `/dashboard/files/modal/{table}` | GET | `files-modal` |
| `/dashboard/files/{table}/{uint:id}` | PATCH | `files-update` |
| `/dashboard/files/{table}/delete` | DELETE | `files-delete` |

**Protected файл** (мөн `FileRouter`-т):

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/protected/file` | GET | `protected-file-read` |

`Dashboard\File\ProtectedFilesController` (`application/dashboard/file/`) нь document root-оос гадуурх `/protected` хавтасны файлыг дамжуулна. Raptor-тай хамт ирдэг модулиуд protected storage ашигладаггүй тул энэ нь төсөлдөө тааруулж өөрчлөх reference implementation. Эрхийн шалгалт нь `authorizeRead(string $relativePath): bool` hook - default нь permissive (нэвтэрсэн хэрэглэгч бүр; `system_coder` үргэлж). Эмзэг файлын хандалтыг нарийсгах бол модулийнхаа index/view permission эсвэл tenant-ownership дүрмээр method-ийг шууд засварлана.

---

## Content - News

### NewsModel

**Файл:** `application/dashboard/content/news/NewsModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `news`

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `slug` | varchar(255, unique) | SEO-friendly URL slug |
| `title` | varchar(255) | Гарчиг |
| `description` | varchar(255) | Товч тайлбар (контентоос автоматаар үүснэ) |
| `content` | mediumtext | HTML контент |
| `source` | varchar(255) | Эх сурвалж |
| `photo` | varchar(255) | Нүүр зураг |
| `code` | varchar(2) | Хэлний код, эсвэл бүх хэл дээр харагдах хэлнээс үл хамаарах бичлэгт `*` |
| `type` | varchar(32, default: 'article') | Мэдээний төрөл |
| `category` | varchar(32, default: 'general') | Ангилал |
| `is_featured` | tinyint (default: 0) | Онцлох мэдээ |
| `comment` | tinyint (default: 1) | Сэтгэгдэл идэвхтэй |
| `read_count` | bigint (default: 0) | Үзэлтийн тоо |
| `published` | tinyint (default: 0) | Нийтлэгдсэн эсэх |
| `published_at` | datetime | Нийтлэгдсэн огноо |
| `published_by` | bigint | Нийтлэсэн хэрэглэгч (FK -> users) |
| `created_at` | datetime | Үүсгэсэн огноо |
| `created_by` | bigint | Үүсгэсэн хэрэглэгч (FK -> users) |
| `updated_at` | datetime | Шинэчилсэн огноо |
| `updated_by` | bigint | Шинэчилсэн хэрэглэгч (FK -> users) |

> **Тэмдэглэл:** `is_active` багана news хүснэгтээс хасагдсан. Устгалтыг бүрмөсөн устгах + Хогийн сав аргаар хийнэ.

#### `getRecentPublished(string $code, int $limit = 20): array`
Тухайн хэлний болон хэлнээс үл хамаарах (`code='*'`) сүүлийн нийтлэгдсэн мэдээнүүдийг буцаана (`code IN (:code, '*')`). id, slug, title, description, photo, code, type, category, is_featured, comment, published_at, created_at, source талбаруудыг авна. `read_count` (dynamic) оруулаагүй тул cache-д тохиромжтой. HomeController-д `recent_news.{code}` cache key-ээр ашиглагдана.

#### `generateSlug(string $title): string`
SEO-friendly slug үүсгэх. Монгол кирилл транслитераци дэмждэг. Давхардвал дугаар залгана.

#### `getBySlug(string $slug): array|null`
Slug-аар мэдээ хайх.

#### `getExcerpt(string $content, int $length = 200): string`
HTML контентоос товч хураангуй гаргах.

### ContentsRouter - News маршрутууд

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/news` | GET | `news` |
| `/dashboard/news/list` | GET | `news-list` |
| `/dashboard/news/insert` | GET+POST | `news-insert` |
| `/dashboard/news/{uint:id}` | GET+PUT | `news-update` |
| `/dashboard/news/view/{uint:id}` | GET | `news-view` |
| `/dashboard/news/delete` | DELETE | `news-delete` |
| `/dashboard/news/reset` | DELETE | `news-sample-reset` |

---

## Content - Comments

### CommentsModel

**Файл:** `application/dashboard/content/news/CommentsModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `news_comments`

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `news_id` | bigint | Мэдээний холбоос (FK -> news) |
| `parent_id` | bigint | Эцэг сэтгэгдэл, 1 түвшний хариулт (FK -> news_comments, self) |
| `created_by` | bigint | Зохиогч хэрэглэгч (FK -> users, зочин бол null) |
| `name` | varchar(255) | Сэтгэгдэл бичигчийн нэр |
| `email` | varchar(255) | Сэтгэгдэл бичигчийн и-мэйл |
| `comment` | text | Сэтгэгдлийн текст |
| `created_at` | datetime | Үүсгэсэн огноо |

> **Тэмдэглэл:** `is_active` багана хасагдсан. Устгалтыг бүрмөсөн устгах + Хогийн сав аргаар хийнэ.

### CommentsController (Dashboard)

**Файл:** `application/dashboard/content/news/CommentsController.php`
**Extends:** `Dashboard\Controller`

| Метод | Тайлбар |
|-------|---------|
| `index()` | Сэтгэгдлийн удирдлагын хуудас |
| `list()` | JSON сэтгэгдлийн жагсаалт (бүх сэтгэгдэл, мэдээний гарчигтай JOIN) |
| `view(int $id)` | Мэдээний харах хуудасны (`news-view`) `#comments` anchor руу redirect; `$id` нь мэдээний ID |
| `comment(int $id)` | Админ `$id` мэдээнд үндсэн сэтгэгдэл бичих (`system_content_index` шаардана) |
| `reply(int $id)` | Админ `$id` үндсэн сэтгэгдэлд хариулах (зөвхөн 1 түвшин, `system_content_update` шаардана) |
| `delete()` | Сэтгэгдэл болон хариултуудыг нь бүрмөсөн устгах (Хогийн савд нөөцлөнө) |

### ContentsRouter - Comments маршрутууд

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/news/comments` | GET | `comments` |
| `/dashboard/news/comments/list` | GET | `comments-list` |
| `/dashboard/news/comments/{uint:id}` | GET | `comments-view` |
| `/dashboard/news/{uint:id}/comment` | POST | `news-comment` |
| `/dashboard/news/comment/{uint:id}/reply` | POST | `news-comment-reply` |
| `/dashboard/news/comments/delete` | DELETE | `comments-delete` |

---

## Content - Messages

### MessagesModel

**Файл:** `application/dashboard/content/messages/MessagesModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `messages`

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `name` | varchar(255) | Илгээгчийн нэр |
| `phone` | varchar(50) | Илгээгчийн утас |
| `email` | varchar(255) | Илгээгчийн и-мэйл |
| `message` | text | Мессежийн текст |
| `code` | varchar(2) | Хэлний код |
| `is_read` | tinyint (default: 0) | Уншсан эсэх (0=шинэ, 1=уншсан, 2=хариулсан) |
| `replied_note` | text | Админы хариултын тэмдэглэл |
| `created_at` | datetime | Үүсгэсэн огноо |

> **Тэмдэглэл:** `is_active` багана хасагдсан. Устгалтыг бүрмөсөн устгах + Хогийн сав аргаар хийнэ.

### MessagesController (Dashboard)

**Файл:** `application/dashboard/content/messages/MessagesController.php`
**Extends:** `Dashboard\Controller`

| Метод | Тайлбар |
|-------|---------|
| `index()` | Мессежийн удирдлагын хуудас |
| `list()` | JSON мессежийн жагсаалт |
| `view(int $id)` | Мессеж харах (уншсан гэж тэмдэглэнэ) |
| `markReplied(int $id)` | Хариулсан гэж тэмдэглэх |
| `delete()` | Бүрмөсөн устгах (Хогийн савд нөөцлөнө) |

### ContentsRouter - Messages маршрутууд

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/messages` | GET | `messages` |
| `/dashboard/messages/list` | GET | `messages-list` |
| `/dashboard/messages/view/{uint:id}` | GET | `messages-view` |
| `/dashboard/messages/replied/{uint:id}` | PATCH | `messages-replied` |
| `/dashboard/messages/delete` | DELETE | `messages-delete` |

---

## Content - Pages

### PagesModel

**Файл:** `application/dashboard/content/page/PagesModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `pages`

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `slug` | varchar(255, unique) | SEO-friendly URL slug |
| `parent_id` | bigint | Эцэг хуудасны ID |
| `title` | varchar(255) | Гарчиг |
| `description` | varchar(255) | Товч тайлбар (контентоос автоматаар үүснэ) |
| `content` | mediumtext | HTML контент |
| `source` | varchar(255) | Эх сурвалж |
| `photo` | varchar(255) | Нүүр зураг |
| `code` | varchar(2) | Хэлний код, эсвэл бүх хэл дээр харагдах хэлнээс үл хамаарах бичлэгт `*` |
| `type` | varchar(32, default: 'menu') | Хуудасны төрөл |
| `category` | varchar(32, default: 'general') | Ангилал |
| `position` | smallint (default: 100) | Эрэмбэ |
| `link` | varchar(255) | Гадаад холбоос |
| `is_featured` | tinyint (default: 0) | Онцлох хуудас |
| `read_count` | bigint (default: 0) | Үзэлтийн тоо |
| `published` | tinyint (default: 0) | Нийтлэгдсэн эсэх |
| `published_at` | datetime | Нийтлэгдсэн огноо |
| `published_by` | bigint | Нийтлэсэн хэрэглэгч (FK -> users) |
| `created_at` | datetime | Үүсгэсэн огноо |
| `created_by` | bigint | Үүсгэсэн хэрэглэгч (FK -> users) |
| `updated_at` | datetime | Шинэчилсэн огноо |
| `updated_by` | bigint | Шинэчилсэн хэрэглэгч (FK -> users) |

> **Тэмдэглэл:** `is_active` багана pages хүснэгтээс хасагдсан. Устгалтыг бүрмөсөн устгах + Хогийн сав аргаар хийнэ.

#### `generateSlug(string $title): string`
SEO-friendly slug үүсгэх. Монгол кирилл транслитераци дэмждэг. Давхардвал дугаар залгана.

#### `getBySlug(string $slug): array|null`
Slug-аар хуудас хайх.

#### `getNavigation(string $code): array`
Тухайн хэлний болон `code='*'` нийтлэгдсэн хуудсуудаас `type` нь `menu` эсвэл `-menu`-ээр төгссөн хуудсуудын мод бүтэцтэй навигаци буцаана. position, id-р эрэмбэлнэ. Модыг (parent -> children -> `submenu`) private туслах `buildTree(array $pages, int $parentId = 0)` үүсгэнэ.

#### `getFeaturedLeafPages(string $code): array`
Тухайн хэлний болон `code='*'` онцлох (`is_featured=1`, нийтлэгдсэн) хуудсуудаас child-гүй (leaf) хуудсуудыг буцаана.

#### `getExcerpt(string $content, int $length = 200): string`
HTML контентоос товч хураангуй гаргах.

### ContentsRouter - Pages маршрутууд

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/pages` | GET | `pages` |
| `/dashboard/pages/table` | GET | `pages-table` |
| `/dashboard/pages/list` | GET | `pages-list` |
| `/dashboard/pages/insert` | GET+POST | `page-insert` |
| `/dashboard/pages/{uint:id}` | GET+PUT | `page-update` |
| `/dashboard/pages/view/{uint:id}` | GET | `page-view` |
| `/dashboard/pages/delete` | DELETE | `page-delete` |
| `/dashboard/pages/reset` | DELETE | `pages-sample-reset` |

---

## Content - References

### ReferenceModel

**Файл:** `application/dashboard/content/reference/ReferenceModel.php`
**Extends:** `codesaur\DataObject\LocalizedModel`

Динамик хүснэгтийн нэртэй лавлагааны хүснэгт: `setTable('questions')` -> `reference_questions` (+ `reference_questions_content`). Үндсэн баганууд: `id`, `keyword` (varchar 128, unique), `category` (varchar 32), `created_at`/`created_by`, `updated_at`/`updated_by`; контент баганууд: `title` (varchar 255), `content` (mediumtext).

### ContentsRouter - References маршрутууд

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/references` | GET | `references` |
| `/dashboard/references/{table}` | GET+POST | `reference-insert` |
| `/dashboard/references/{table}/{uint:id}` | GET+PUT | `reference-update` |
| `/dashboard/references/view/{table}/{uint:id}` | GET | `reference-view` |
| `/dashboard/references/delete` | DELETE | `reference-delete` |

---

## Content - Settings

### SettingsModel

**Файл:** `application/dashboard/content/settings/SettingsModel.php`
**Extends:** `codesaur\DataObject\LocalizedModel`

**Хүснэгт:** `raptor_settings`

#### Үндсэн баганууд

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `email` | varchar(70) | Контакт имэйл |
| `phone` | varchar(70) | Контакт утас |
| `favicon` | varchar(255) | Favicon зам |
| `apple_touch_icon` | varchar(255) | Apple icon зам |
| `config` | text | JSON тохиргоо |
| `created_at` | datetime | Үүсгэсэн огноо |
| `created_by` | bigint | Үүсгэсэн хэрэглэгч (FK -> users) |
| `updated_at` | datetime | Шинэчилсэн огноо |
| `updated_by` | bigint | Шинэчилсэн хэрэглэгч (FK -> users) |

#### Контент баганууд (хэл тус бүр)

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `title` | varchar(70) | Сайтын гарчиг |
| `logo` | varchar(255) | Лого |
| `description` | varchar(255) | SEO тайлбар |
| `urgent` | text | Яаралтай мэдэгдэл |
| `contact` | text | Холбоо барих |
| `address` | text | Хаяг |
| `copyright` | varchar(255) | Copyright |

#### `retrieve(): array`
`getRows()`-ийн сүүлийн тохиргооны бичлэгийг (`localized` контенттой нь) буцаана. Хоосон бол `[]`.

### SettingsMiddleware

**Файл:** `application/dashboard/content/settings/SettingsMiddleware.php`
**Implements:** `MiddlewareInterface`

Settings-г DB-с уншиж `settings` нэрийн request attribute-д inject хийнэ.

### ContentsRouter - Settings маршрутууд

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/settings` | GET | `settings` |
| `/dashboard/settings` | POST | - |
| `/dashboard/settings/files` | POST | `settings-files` |
| `/dashboard/settings/env` | PATCH | `settings-env` |

### SettingsController::updateEnv()

`.env` файлын утгыг шинэчлэх нэгдсэн endpoint. `system_coder` эрх шаардлагатай.

**Request body:** `{ "name": "ENV_VAR_NAME", "value": "...", "type": "bool|email|string" }`

**Зөвшөөрөгдсөн .env хувьсагчид:**

| Хувьсагч | Төрөл | Анхдагч | Тайлбар |
|----------|--------|---------|---------|
| `RAPTOR_CONTACT_EMAIL_TO` | email | - | Мессежийн мэдэгдэл хүлээн авах имэйл (хоосон = мэдэгдэл унтраалттай) |
| `RAPTOR_ORDER_EMAIL_TO` | email | - | Захиалгын мэдэгдэл хүлээн авах имэйл (хоосон = мэдэгдэл унтраалттай) |
| `RAPTOR_COMMENT_EMAIL_TO` | email | - | Сэтгэгдлийн мэдэгдэл хүлээн авах имэйл (хоосон = мэдэгдэл унтраалттай) |
| `RAPTOR_REVIEW_EMAIL_TO` | email | - | Үнэлгээний мэдэгдэл хүлээн авах имэйл (хоосон = мэдэгдэл унтраалттай) |

Өөр ямар ч `name` 403-оор татгалзагдана.

**Төрлийн ажиллагаа:**
- `bool` - Одоогийн утгыг эсрэгээр солино (`value` талбар хэрэггүй). Хариуд `value: true|false`. Хэрэгжсэн ч framework-ийн template-үүд ашигладаггүй
- `email` - `filter_var()` ашиглан имэйл формат шалгана. Хоосон утга нь хаягийг арилгана
- `string` - Шалгалтгүй, шууд хадгална

Messages, orders, comments, reviews жагсаалтын хуудасны дээд хэсэгт `system_coder` эрхтэй хэрэглэгчдэд харагддаг тохиргоо. Тэдгээр template-ийн асаах/унтраах товч `type: 'email'` + хоосон `value` илгээдэг; controller-ууд мэдэгдлийн төлөв идэвхтэй эсэхийг `RAPTOR_*_EMAIL_TO` утга хоосон эсэхээс гаргана.

---

## Localization

### LanguageModel

**Файл:** `application/dashboard/localization/language/LanguageModel.php`
**Extends:** `codesaur\DataObject\Model`

Хэлний бүртгэлийн хүснэгт.

### TextModel

**Файл:** `application/dashboard/localization/text/TextModel.php`
**Extends:** `codesaur\DataObject\LocalizedModel`

Орчуулгын текстүүд (`localization_text` / `localization_text_content` хүснэгтүүд; контент багана `text` varchar(255)).

#### `retrieve(?string $code = null): array`
`$code` өгвөл тухайн хэлний хавтгай `keyword -> text` map буцаана (`LocalizationMiddleware` ашиглана, `texts.{code}` cache). `null` бол бүх орчуулгыг `keyword -> хэлний код -> text` бүтцээр буцаана.

### LocalizationMiddleware

**Файл:** `application/dashboard/localization/LocalizationMiddleware.php`
**Implements:** `MiddlewareInterface`

Dashboard болон Web app хоёуланд ашиглагдана. Constructor-аар nullable session key авна:
- Dashboard: `new LocalizationMiddleware()` - default `RAPTOR_LANGUAGE_CODE`, хэл session-д хадгалагдана
- Web: `new LocalizationMiddleware(null)` - session ашиглахгүй, хэл URL prefix-ээс ирнэ

Сонгох дараалал: `language_prefix` request attribute (`public_html/index.php` `/xx/` URL prefix-ээс тавьдаг; идэвхгүй код бол 404) -> session утга (session key өгсөн үед л) -> default хэл (эхний идэвхтэй хэл). Вэбд default хэл prefix-гүй (`/news/x`), бусад хэл prefix-тэй (`/en/news/x`); prefix нь Web application-ий mount path тул `|link` / `generateRouteLink()` автоматаар нэмнэ.

Request attribute-д `localization` массив inject хийнэ:

```php
[
    'code'        => 'mn',                    // Идэвхтэй хэлний код
    'language'    => [...],                   // Бүх хэлний жагсаалт
    'text'        => ['key' => 'value', ...], // Орчуулгын текстүүд
    'session_key' => 'RAPTOR_LANGUAGE_CODE'   // Session-д хэл хадгалах key
]
```

---

## Log

### Logger

**Файл:** `application/dashboard/log/Logger.php`
**Extends:** `\Psr\Log\AbstractLogger`

PSR-3 стандартын лог систем. Өгөгдлийн санд хадгална.

#### `setTable(string $name)`
Лог сувгийг тохируулна; бодит хүснэгт нь `{$name}_log` (`setTable('dashboard')` -> `dashboard_log`) бөгөөд анх ашиглахад индексүүдтэйгээ хамт үүснэ.

#### `log(mixed $level, string|\Stringable $message, array $context = []): void`
Лог бичих.

### LogsRouter маршрутууд

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/logs` | GET | `logs` |
| `/dashboard/logs/view` | GET | `logs-view` |
| `/dashboard/logs/retrieve` | POST | `logs-retrieve` |
| `/dashboard/logs/error-log-read` | GET | `error-log-read` |

---

## Mail

### Mailer

**Файл:** `application/dashboard/mail/Mailer.php`

И-мэйл илгээх. `send()` нь `RAPTOR_MAIL_TRANSPORT` .env тохиргоогоор transport сонгоно: `brevo` (анхдагч), `smtp`, `mail`.

---

## Database

### DatabaseConnection

**Файл:** `application/dashboard/DatabaseConnection.php`

Бүх PDO холболтыг нэг газраас удирдах helper класс. HTTP entry point
(`public_html/index.php`) болон тестүүд бүгд
`\Dashboard\DatabaseConnection::connect()`-ийг дуудаж нэгэн ижил PDO авна.

- `driver(): string` - `.env`-ийн `RAPTOR_DB_DRIVER` уншина (`mysql` | `pgsql`).
  Зөвшөөрөгдсөн утгуудаас гадуур бол `Exception` шиднэ.
- `connect(): \PDO` - driver-ийн дагуу MySQL эсвэл PostgreSQL-д холбогдож
  PDO instance буцаана. Database нь аль хэдийн үүссэн байх ёстой (implicit
  auto-create байхгүй).

`public_html/index.php` дотор үүсгэсэн PDO нь
`$request->withAttribute('pdo', $pdo)` хэлбэрээр Application руу дамждаг.
Controller-ууд `Dashboard\Controller::__construct()` дотор `$this->pdo` болгон
автоматаар авна.

### ContainerMiddleware

**Файл:** `application/dashboard/ContainerMiddleware.php`

PSR-11 DI Container-г request-д inject хийнэ. `cache`, `mailer`,
`template_service`, `discord`, `events` зэрэг service factory-уудыг
бүртгэнэ. Эдгээр factory-ууд шаардлагатай бол PDO-г request-ийн `pdo`
attribute-аас (entry point дээр суулгасан) уншиж ашиглана.

---

## SpamProtectionTrait

**Файл:** `application/dashboard/SpamProtectionTrait.php`

Нийтийн формуудын нэгдсэн спам хамгаалалт: honeypot талбар, HMAC token + timestamp, session-д суурилсан rate limit, Cloudflare Turnstile (зөвхөн `RAPTOR_TURNSTILE_SECRET_KEY` тохируулсан үед идэвхтэй) болон линкний тооны шүүлтүүр.

### Methods

#### `getTurnstileSiteKey(): string`
ENV тохиргооноос Turnstile site key буцаана. Тохируулаагүй бол хоосон string.

#### `generateSpamToken(string $formName, int $ts): string`
`"$formName-$ts"`-ийн `RAPTOR_JWT_SECRET`-ээр түлхүүрлэсэн HMAC-SHA256; формд `_ts`-ийн хажууд `_token` нэрээр оруулна. Secret заавал байх ёстой - `RAPTOR_JWT_SECRET` байхгүй бол `getSpamSecret()` `RuntimeException` шидэнэ (зориуд default secret-гүй).

#### `validateSpamProtection(array $parsed, string $formName, string $sessionKey, int $rateLimit = 10, int $minTime = 2): void`
POST body-г шалгана: honeypot `website` талбар хоосон байх, `_token` нь `generateSpamToken($formName, $_ts)`-тэй таарах, `_ts`-ээс хойш дор хаяж `$minTime` секунд, дээд тал нь 3600 секунд өнгөрсөн байх, `$_SESSION[$sessionKey]` нь `$rateLimit` секундээс хуучин байх, дараа нь Turnstile тохируулсан бол token-ийг нь шалгана. Алдаа гарвал 400/403/429 кодтой `\Exception` шидэнэ.

#### `checkLinkSpam(string $text, int $maxLinks = 2): void`
Текстэд `$maxLinks`-ээс олон URL (`http(s)://` эсвэл `www.`) байвал `\Exception('Too many links', 400)` шидэнэ.

### Хэрэглэдэг газрууд

- `Web\Service\ContactController` - Холбоо барих форм илгээх
- `Web\Content\NewsController` - Мэдээний сэтгэгдэл илгээх
- `Web\Shop\ShopController` - Захиалга болон үнэлгээ илгээх
- `Dashboard\Authentication\LoginController` - нэвтрэх, бүртгүүлэх, нууц үг сэргээх формууд (өөрийн `spamCheck()` нь `generateSpamToken()` / `getTurnstileSiteKey()`-г дахин ашиглана; Turnstile зөвхөн бүртгүүлэхэд шалгагдана)

---

## CsrfMiddleware

**Файл:** `application/dashboard/CsrfMiddleware.php`
**Implements:** `Psr\Http\Server\MiddlewareInterface`

Dashboard-ийн mutating хүсэлтүүдэд CSRF token шалгах **per-route** middleware. Router дээр mutating route бүрд `->middleware([CsrfMiddleware::class])`-аар наагдана (`use Dashboard\CsrfMiddleware;`).

### Ажиллах зарчим

1. Middleware нь зөвхөн **шалгана** (token үүсгэх, attribute тавих зэрэг хийхгүй)
2. GET/HEAD/OPTIONS хүсэлтүүд шалгалтгүйгээр дамжина (`GET_POST`/`GET_PUT` compound route-ийн GET талыг хамгаална)
3. Бусад методууд (POST, PUT, PATCH, DELETE) `$_SESSION['CSRF_TOKEN']`-г `X-CSRF-TOKEN` header-тэй тулгана
4. Token таарахгүй эсвэл байхгүй бол 403 JSON хариу буцаана
5. Login routes exempt - тэнд middleware наахгүй (token login үед үүсдэг)

### Token provisioning

- Token нь login үед үүсэж `$_SESSION['CSRF_TOKEN']` дотор хадгалагдана
- Хуучин session-д fallback болгож `Controller::template()` (нэвтэрсэн хэрэглэгчид token байхгүй + session writable бол) үүсгэнэ

### Frontend интеграци

- Token нь `dashboard.html` дахь `<meta name="csrf-token">` tag-аар frontend-д хүрнэ
- `dashboard.js` дахь `csrfFetch()` wrapper нь `X-CSRF-TOKEN` header-г автоматаар нэмнэ
- Dashboard модулиудын бүх POST/PUT/PATCH/DELETE хүсэлтэд `csrfFetch()` ашиглана
- Standalone хуудсууд (жш: login) `dashboard.js` ачаалагдахгүй тул энгийн `fetch()` ашиглана

---

## HtmlValidationTrait

**Файл:** `application/dashboard/content/HtmlValidationTrait.php`

Серверийн талын HTML контент шалгалт. Pages, News, Products controller-ууд insert/update хийхэд ашиглана.

#### `validateHtmlContent(string $html): void`
Хаагдаагүй HTML comment (`<!-- -->`), эвдэрсэн tag шалгана. DOMDocument ашиглан текстийн урт харьцуулна. 20%-аас их алдагдалтай бол `InvalidArgumentException` шидэнэ.

### Ашигладаг газрууд

- `Dashboard\Content\NewsController` - Мэдээ нэмэх/засах
- `Dashboard\Content\PagesController` - Хуудас нэмэх/засах
- `Dashboard\Shop\ProductsController` - Бүтээгдэхүүн нэмэх/засах

---

## DashboardTrait

**Файл:** `application/dashboard/template/DashboardTrait.php`

Dashboard UI рендерлэлт, эрхийн мэдэгдэл, sidebar цэс үүсгэх, хэрэглэгчийн мэдээлэл авах.

Энэ trait-ийг ашигладаг controller нь trait-ийн public API-тай (`dashboardTemplate`, `dashboardProhibited`, `modalProhibited`, `getUserMenu`, `getUserOrganizations`) ижил нэртэй метод тодорхойлж болохгүй - class метод trait методыг чимээгүй дарж, trait-ийн дотоод дуудлагуудыг эвдэнэ.

#### `dashboardTemplate(string $template, array $vars = []): FileTemplate`
`dashboard.html` layout дотор контент рендерлэнэ. Sidebar цэс, topbar-ийн байгууллага солих жагсаалт (`user_organizations`), тохиргоо ачаална. Цэсийг cache-с уншина (`menu.{code}` key). Мөн `raptor_name`, `raptor_version`, `raptor_modified` (`composer.json`-ийн `name` / `extra.version` / `extra.modified`-оос уншиж sidebar-ийн хувилбарын мөрөнд харуулна; байхгүй бол null) болон `has_web` (`Web\Application` байвал `true` - "Веблүү очих" sidebar холбоосыг асаана) хувьсагчдыг тохируулна.

#### `dashboardProhibited(?string $alert = null, int|string $code = 0): FileTemplate`
Эрхийн хориглолын мэдэгдэл dashboard layout дотор харуулна.

#### `modalProhibited(?string $alert = null, int|string $code = 0): FileTemplate`
Эрхийн хориглолын modal (standalone, layout-гүй).

#### `getUserMenu(): array`
Харагдах байдал (`is_visible=1`), байгууллагын alias, хэрэглэгчийн эрхээр шүүсэн sidebar цэс үүсгэнэ; дэд цэс нь хоосон үлдсэн эцэг цэсийг хасна.

#### `getUserOrganizations(): array`
Topbar-ийн байгууллага солих dropdown-д зориулж идэвхтэй байгууллагуудын жагсаалтыг `[['id' => ..., 'name' => ..., 'logo' => ...], ...]` хэлбэрээр буцаана. `system_coder`-т бүх идэвхтэй байгууллага (cross-tenant роль), бусдад зөвхөн гишүүнчлэлийнх; одоо нэвтэрсэн байгууллага үргэлж багтана. id=1 (системийн үндсэн байгууллага) жагсаалтад байвал үргэлж хамгийн эхэнд, бусад нь нэрийн эрэмбээр. Жагсаалт 1-ээс олон бол dropdown харагдаж, 10-аас олон бол хайлтын шүүлтүүртэй болно.

#### `retrieveUsersDetail(?int ...$ids)`
Protected туслах метод. `[user_id => "username - First Last (email)"]` map буцаана (алдаа гарвал хоосон массив). ID өгөөгүй бол бүх хэрэглэгчид.

---

## FileController

**Файл:** `application/dashboard/file/FileController.php`
**Extends:** `Dashboard\Controller`

Файл upload, шалгалт, хадгалалт, зураг optimize хийх суурь класс. FilesController, SettingsController, UsersController зэрэг файл upload хийдэг controller-ууд үүнийг extend хийнэ.

### Гол методууд

| Метод | Тайлбар |
|-------|---------|
| `setFolder(string $folder)` | Upload хавтас тохируулах (жш: `/users/1`, `/pages/22`) |
| `getFilePublicPath(string $fileName)` | Файлын public URL зам буцаах |
| `allowExtensions(array $exts)` | Зөвшөөрөх файлын extension-ууд |
| `allowImageOnly()` | Зөвхөн зурагны extension зөвшөөрөх |
| `allowCommonTypes()` | Түгээмэл вэб файлын төрлүүд зөвшөөрөх (зураг, баримт, медиа, архив) |
| `allowAnything()` | Extension-ий whitelist-ийг цэвэрлэнэ (бүх extension зөвшөөрнө) |
| `setSizeLimit(int $size)` | Дээд хэмжээ bytes-ээр |
| `setOverwrite(bool $overwrite)` | Давхцах нэрийн файлыг дарж бичих эсэх |
| `moveUploaded(string\|UploadedFileInterface $uploadedFile, bool $optimize = false, int $mode = 0755): array\|false` | Үндсэн upload (string бол `getUploadedFiles()` дахь key): шалгаж, хадгалж `[path, file, size, type, mime_content_type]` буцаана; амжилтгүй бол `false` (`getLastUploadError()`-оос шалтгааныг нь харна) |
| `getLastUploadError(): int` | Сүүлийн амжилтгүй `moveUploaded()`-ийн `UPLOAD_ERR_*` код |
| `optimizeImage(string $filePath): bool` | JPEG/PNG/GIF/WebP-г хэмжээ/чанараар optimize хийнэ (дээд өргөн `RAPTOR_CONTENT_IMG_MAX_WIDTH`, default 1920; чанар `RAPTOR_CONTENT_IMG_QUALITY`, default 90), EXIF эргүүлэлт хэрэглэнэ; зөвхөн эргүүлсэн эсвэл 10%-иас илүү жижигэрсэн үед файлыг солино |
| `getMaximumFileUploadSize()` | `MIN(post_max_size, upload_max_filesize)` bytes-ээр |
| `formatSizeUnits(?int $bytes)` | Хүний уншихад хялбар формат (жш: `10.5mb`) |
| `unlinkByName(string $fileName)` | Upload хавтаснаас файл устгах |

---

## AIHelper

**Файл:** `application/dashboard/content/AIHelper.php`
**Extends:** `Dashboard\Controller`

moedit WYSIWYG editor-ийн OpenAI API интеграци. HTML контент сайжруулалт (Shine) болон зургаас текст таних (OCR/Vision).

#### `moeditAI(): void`
POST `/dashboard/content/moedit/ai` - Хоёр горимтой:

**HTML горим** (`mode: 'html'`): `RAPTOR_OPENAI_MODEL` (.env, default `gpt-5-mini`) ашиглан HTML контент сайжруулна. Body: `{mode, html, prompt}`.

**Vision горим** (`mode: 'vision'`): `RAPTOR_OPENAI_VISION_MODEL` (.env, default `gpt-5.1`) ашиглан зургаас текст таниулна. Body: `{mode, images[], prompt}`.

Хариу: `{status: 'success', html: '...'}` эсвэл `{status: 'error', message: '...'}`.

`.env`-д `RAPTOR_OPENAI_API_KEY` шаардлагатай. Дуудагч нэвтэрсэн байхаас гадна `system_content_insert`, `system_content_update`, `system_product_insert`, `system_product_update` эрхийн аль нэгийг эзэмшсэн байх ёстой (үгүй бол 403). Хэрэглэгч бүрд 60 секундэд 30 OpenAI дуудлагын хязгаартай - `ai_ratelimit.{userId}` cache key-ээр (vision: зураг бүр нэг дуудлага; хэтэрвэл 429; cache service байхгүй бол алгасна). Vision горим нэг хүсэлтэд дээд тал нь 8 зураг авна (үгүй бол 400). Route нэр `moedit-ai`, CSRF хамгаалалттай.

---

## Badge систем

### BadgeController

**Файл:** `application/dashboard/badge/BadgeController.php`
**Extends:** `Dashboard\Controller`

Dashboard sidebar-д модуль тус бүрийн уншаагүй үйлдлийн тоог badge-ээр харуулах систем. `*_log` хүснэгтүүдээс уншина.

#### `list(): void`
GET `/dashboard/badges` - Модуль бүрийн badge тоог JSON-оор буцаана. Өнгө: ногоон=create, цэнхэр=update, улаан=delete, info=шинэ сэтгэгдэл/үнэлгээ. Админы эрхээр (`PERMISSION_MAP`) шүүнэ. Өөрийн үйлдлийг тоолохгүй - `/trash`-аас бусад (өөрийн устгасныг тэнд харуулах зорилготой). `orgScopedModules()`-д (default хоосон) жагсаасан модулиудыг харж буй админы одоогийн байгууллагаар нэмж шүүнэ - `system_coder` эсвэл `isSystemWideViewer()` (одоогийн байгууллага `id=1`) бол шүүхгүй. `/manual`, `/migrations` нь лог биш файлын тоогоор badge авна. Шинэ хэрэглэгчид 30 хоногийн lookback.

#### `seen(): void`
POST `/dashboard/badges/seen` - Модулийг уншсан гэж тэмдэглэнэ. `checked_at` timestamp шинэчилнэ.

### Тогтмолууд

- `BADGE_MAP` - `[log_table][action]` -> `[module_path, color]` зурагчлал
- `PERMISSION_MAP` - Модуль бүрд шаардагдах эрх (`null` = аль ч админ, `'system_x'` = эрх шалгах, `'role:system_coder'` = role шалгах)

Хоёр map хоёулаа **mount-naive** зам (`/news`, `/dashboard/news` биш)-аар түлхүүрлэгдэнэ. `/dashboard` mount prefix-ийг runtime-д `getMountPath()`-аар нэмдэг тул `index.php` дахь mount цэгийг өөрчилсөн ч эдгээр map-д гар хүрэх шаардлагагүй.

### BadgeRouter

**Файл:** `application/dashboard/badge/BadgeRouter.php`

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/badges` | GET | `dashboard-badges` |
| `/dashboard/badges/seen` | POST | - |

### AdminBadgeSeenModel

**Файл:** `application/dashboard/badge/AdminBadgeSeenModel.php`
**Extends:** `codesaur\DataObject\Model`

Админ тус бүрийн модуль сүүлд хэзээ үзсэн мэдээллийг хадгална. Багана: `admin_id`, `module`, `checked_at`, `last_seen_count`. Unique index: `(admin_id, module)`.

---

## MenuModel

**Файл:** `application/dashboard/template/MenuModel.php`
**Extends:** `codesaur\DataObject\LocalizedModel`

**Хүснэгт:** `raptor_menu` (+ localized `title`-д `raptor_menu_content`)

Dashboard sidebar цэсний олон хэлтэй, parent/child бүтэцтэй model. `__initial()` нь хэрэглэгчийн хоёр FK, `parent_id` дээрх индексийг нэмж, `MenuSeed::seed()`-ээр default цэсийг үүсгэнэ.

### Баганууд

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `parent_id` | bigint (default: 0) | Эцэг цэсний ID (0 = root) |
| `icon` | varchar(64) | Bootstrap Icons класс |
| `href` | varchar(255) | Цэсний холбоос URL |
| `alias` | varchar(64) | Байгууллагын alias шүүлтүүр |
| `permission` | varchar(128) | Цэсийг харахад шаардагдах эрх |
| `position` | smallint (default: 100) | Дэс дараалал |
| `is_visible` | tinyint (default: 1) | Харагдах эсэх |
| `created_at` | datetime | Үүсгэсэн огноо |
| `created_by` | bigint | Үүсгэсэн хэрэглэгч (FK -> users) |
| `updated_at` | datetime | Шинэчилсэн огноо |
| `updated_by` | bigint | Шинэчилсэн хэрэглэгч (FK -> users) |
| `title` (localized) | varchar(128) | Хэл тус бүрийн цэсний нэр |

### Методууд

| Метод | Тайлбар |
|-------|---------|
| `insert(array $record, array $content)` | Цэс нэмэх (`created_at` автомат) |
| `updateById(int $id, array $record, array $content)` | Цэс засах (`updated_at` автомат) |

---

## Dashboard Home

### HomeRouter

**Файл:** `application/dashboard/home/HomeRouter.php`

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/home` | GET | `home` |
| `/dashboard` | GET | - |
| `/dashboard/search` | GET | `dashboard-search` |
| `/dashboard/stats` | GET | `dashboard-stats` |
| `/dashboard/log-stats` | GET | `dashboard-log-stats` |

Нэрлэгдсэн `home` route нь `/dashboard/home` дээр; `/dashboard` (root) нь нэргүй alias хэлбэрээр мөн `HomeController::index` рүү заасан хэвээр. Sidebar-ийн active-илрүүлэлт prefix-д суурилдаг тул root дээрх home link бүх хуудсанд active болно; харин public вэб layout `{{ index }}/dashboard` руу шууд линк хийдэг тул root өөрөө үлдэх ёстой.

### SearchController

**Файл:** `application/dashboard/home/SearchController.php`
**Extends:** `Dashboard\Controller`

#### `search(): void`
Dashboard ерөнхий хайлт - topbar хайлтын modal (Ctrl+K)-ийн endpoint. Мэдээ, хуудас, бүтээгдэхүүн, захиалга, хэрэглэгч, байгууллага, хөгжүүлэлтийн хүсэлт, мессеж, сэтгэгдэл, үнэлгээ хүснэгтүүдээс LIKE query ашиглан хайна. Блок бүр тухайн модулийн index хуудасны ижил permission (эсвэл мөрийн түвшний шүүлт)-ээр хамгаалагдана (news/pages/messages/comments -> `system_content_index`, products/orders/reviews -> `system_product_index`, users -> `system_user_index`, organizations -> `system_organization_index`, dev-requests -> `system_development`-гүй бол зөвхөн өөрийн/хуваарилагдсан). JSON буцаана.

### WebLogStatsController

**Файл:** `application/dashboard/home/WebLogStatsController.php`
**Extends:** `Dashboard\Controller`

#### `stats(): void`
Вэб зочилсон статистик JSON буцаана (өнөөдөр/долоо хоног/сар, график дата, шилдэг хуудас/мэдээ/бүтээгдэхүүн, IP хаяг).

#### `logStats(): void`
Системийн `*_log` хүснэгтүүдийн статистик JSON буцаана (өнөөдөр/долоо хоног/нийт тоо, сүүлийн шинэчлэлт).

### WebLogStats

**Файл:** `application/dashboard/home/WebLogStats.php`

Вэб зочилсон статистик тооцоолох utility класс. `web_log_cache` хүснэгт ашиглан гүйцэтгэлийг хурдасгана. MySQL (`JSON_EXTRACT`) болон PostgreSQL (`::jsonb`) аль алийг дэмжинэ.

---

## Dashboard Manual

### ManualRouter

**Файл:** `application/dashboard/manual/ManualRouter.php`

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/manual` | GET | `manual` |
| `/dashboard/manual/{file}` | GET | `manual-view` |

### ManualController

**Файл:** `application/dashboard/manual/ManualController.php`
**Extends:** `Dashboard\Controller`

#### `index(): void`
`application/dashboard/manual/` хавтасны бүх гарын авлагын HTML файлуудыг модулиар нь бүлэглэн жагсаана.

#### `view(string $file): void`
Тодорхой гарын авлагын файлыг харуулна. Хүссэн хэлний хувилбар байхгүй бол англи хэлний (`-en.html`) руу fallback хийнэ.

---

## Web Layer

### Web\Application

**Файл:** `application/web/Application.php`
**Extends:** `codesaur\Http\Application\Application`

Public вэб сайтын Application. Middleware pipeline:
ExceptionHandler -> MethodOverride -> BodyEncoding -> Container -> Session -> Localization (зөвхөн URL prefix, session key-гүй) -> Settings -> WebRouter

### WebRouter

**Файл:** `application/web/WebRouter.php`

| Маршрут | Метод | Нэр | Тайлбар |
|---------|-------|-----|---------|
| `/` | GET | `home` | Нүүр хуудас |
| `/page/{uint:id}` | GET | - | ID-р хуудас (slug руу redirect) |
| `/page/{slug}` | GET | `page` | Хуудас үзэх |
| `/contact` | GET | `contact` | Холбоо барих |
| `/news/{uint:id}` | GET | - | ID-р мэдээ (slug руу redirect) |
| `/news/{slug}` | GET | `news` | Мэдээ үзэх |
| `/news/type/{type}` | GET | `news-type` | Төрлөөр мэдээ |
| `/archive` | GET | `archive` | Мэдээний архив |
| `/products` | GET | - | Бүтээгдэхүүний жагсаалт |
| `/product/{uint:id}` | GET | - | ID-р бүтээгдэхүүн (slug руу redirect) |
| `/product/{slug}` | GET | `product` | Бүтээгдэхүүн үзэх |
| `/order` | GET | `order` | Захиалгын форм |
| `/search` | GET | `search` | Хайлт (`SearchController`) |
| `/sitemap` | GET | `sitemap` | Sitemap хуудас |
| `/sitemap.xml` | GET | - | XML sitemap |
| `/rss` | GET | `rss` | RSS feed |
| `/favicon.ico` | GET | - | Favicon redirect / 204 |
| `/session/language/{code}` | GET | `language` | Тухайн хэлний нүүр рүү redirect (`/` эсвэл `/{code}/`); layout-ын хэлний dropdown нь одоо байгаа хуудасны хэл бүрийн URL руу заана |
| `/session/contact-send` | POST | `contact-send` | Холбоо барих мессеж илгээх |
| `/session/order` | POST | `order-submit` | Захиалга илгээх |
| `/session/news/{uint:id}/comment` | POST | `news-comment` | Мэдээнд сэтгэгдэл бичих |
| `/session/product/{uint:id}/review` | POST | `product-review` | Бүтээгдэхүүний үнэлгээ илгээх |

Нэргүй маршрутуудыг template болон PHP-ээс дуудахгүй (`|link` тэдгээрт `#` буцаана).

### HomeController

**Файл:** `application/web/HomeController.php`
**Extends:** `TemplateController`

| Метод | Тайлбар |
|-------|---------|
| `index()` | Нүүр хуудас (сүүлийн 20 нийтлэгдсэн мэдээ, хэлээр cache хийгдсэн) |
| `favicon()` | Favicon redirect эсвэл 204 No Content cache header-тэй |
| `language(string $code)` | Тухайн хэлний нүүр рүү 302 redirect (default хэлд `/`, бусад хэлд `/{code}/`); идэвхгүй код бол default хэл рүү. Session-д юу ч бичихгүй - вэбийн хэл зөвхөн URL prefix-ээс ирнэ |

### PageController

**Файл:** `application/web/content/PageController.php`
**Extends:** `TemplateController`

| Метод | Тайлбар |
|-------|---------|
| `pageById(int $id)` | ID-р хуудас slug URL руу redirect |
| `page(string $slug)` | Хуудас үзүүлэх + файлууд + read_count + OG meta |

### ContactController

**Файл:** `application/web/service/ContactController.php`
**Extends:** `TemplateController`

| Метод | Тайлбар |
|-------|---------|
| `contact()` | Холбоо барих хуудас (link LIKE '%/contact') |
| `contactSend()` | Холбоо барих мессеж илгээх (AJAX, spam хамгаалалттай) |

### NewsController (Web)

**Файл:** `application/web/content/NewsController.php`
**Extends:** `TemplateController`

| Метод | Тайлбар |
|-------|---------|
| `newsById(int $id)` | ID-р мэдээ slug URL руу redirect |
| `news(string $slug)` | Мэдээ үзүүлэх + файлууд + read_count + word_count + read_time + OG meta |
| `newsType(string $type)` | Төрлөөр мэдээний жагсаалт (эсвэл `all`) + ангилалын sidebar |
| `archive()` | Мэдээний архив, жил/сар шүүлтүүртэй |
| `commentSubmit(int $id)` | Мэдээнд сэтгэгдэл бичих (AJAX, 5 давхаргат спам хамгаалалт, и-мэйл + Discord мэдэгдэл) |

### ShopController

**Файл:** `application/web/shop/ShopController.php`
**Extends:** `TemplateController`

| Метод | Тайлбар |
|-------|---------|
| `products()` | Нийтлэгдсэн бүтээгдэхүүнүүдийн жагсаалт |
| `productById(int $id)` | ID-р бүтээгдэхүүн slug URL руу redirect |
| `product(string $slug)` | Бүтээгдэхүүн үзүүлэх + файлууд + read_count + OG meta |
| `order()` | Захиалгын форм харуулах (product_id query param-аар бүтээгдэхүүний мэдээлэл бөглөнө) |
| `orderSubmit()` | Захиалга боловсруулах (спам шалгах, validate, DB, и-мэйл, Discord) |
| `reviewSubmit(int $id)` | Бүтээгдэхүүний үнэлгээ бичих (AJAX, спам хамгаалалт, и-мэйл + Discord мэдэгдэл) |

### SearchController

**Файл:** `application/web/service/SearchController.php`
**Extends:** `TemplateController`

| Метод | Тайлбар |
|-------|---------|
| `search()` | `?q=`-ээр хуудас, мэдээ, бүтээгдэхүүнээс хайлт (доод тал нь 2 тэмдэгт, title/slug/description/content/source/link дээр LIKE, эх сурвалж бүрээс 20 мөр, одоогийн хэл + `*` бичлэгүүд) |

### SeoController

**Файл:** `application/web/service/SeoController.php`
**Extends:** `TemplateController`

| Метод | Тайлбар |
|-------|---------|
| `sitemap()` | Хүнд ээлтэй sitemap хуудасны модтой |
| `sitemapXml()` | Хайлтын системүүдэд XML sitemap |
| `rss()` | RSS 2.0 feed (сүүлийн 20 мэдээ + 20 бүтээгдэхүүн) |

### TemplateController

**Файл:** `application/web/template/TemplateController.php`
**Extends:** `Dashboard\Controller`

| Метод | Тайлбар |
|-------|---------|
| `webTemplate(string $template, array $vars = []): FileTemplate` | Web layout + content нэгтгэх. $vars доторх title, code, description, photo key-г index layout-ийн SEO meta-д автоматаар map хийнэ (`code='*'`-ийг map хийхгүй, layout одоогийн хэл рүү буцна). `base_url`, `current_url`, `language_urls` (одоогийн хуудас хэл бүрээр), `hreflang_urls` (зөвхөн бүх хэл дээр байдаг хуудсанд), `canonical_url` тохируулна. Тохиргоо, навигаци, онцлох хуудсуудыг ачаална (cache). |

### ExceptionHandler

**Файл:** `application/web/template/ExceptionHandler.php`
**Implements:** `codesaur\Http\Application\ExceptionHandlerInterface`

Вэб frontend-д хэрэглэгчид ээлтэй алдааны хуудас рендерлэнэ. `page-404.html` template ашиглана. `CODESAUR_DEVELOPMENT` горимд JSON stack trace нэмэгдэнэ.

### Moedit AI

**Маршрут:** `POST /dashboard/content/moedit/ai`
**Нэр:** `moedit-ai`

moedit editor-ийн AI товчинд зориулсан OpenAI API proxy.

---

## ContentsRouter - Бүх маршрутууд

**Файл:** `application/dashboard/content/ContentsRouter.php`

Контент модулийн бүх маршрутыг нэг дор бүртгэнэ: News, Comments, Pages, References, Settings, Messages, Moedit AI. Файлын маршрутууд `Dashboard\File\FileRouter`-т (`application/dashboard/file/FileRouter.php`) байна.

---

## Shop

### ProductsModel

**Файл:** `application/dashboard/shop/ProductsModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `products`

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `slug` | varchar(255), unique | SEO-friendly URL slug |
| `title` | varchar(255) | Бүтээгдэхүүний нэр |
| `description` | varchar(255) | Товч тайлбар |
| `content` | mediumtext | HTML контент |
| `price` | decimal(12,2), default 0 | Үнэ |
| `sale_price` | decimal(12,2) | Хямдралын үнэ |
| `sku` | varchar(64) | SKU код |
| `barcode` | varchar(64) | Баркод |
| `sizes` | text | Хэмжээнүүд |
| `colors` | text | Өнгөнүүд |
| `stock` | int, default 0 | Нөөцийн тоо |
| `link` | varchar(255) | Гадаад холбоос |
| `photo` | varchar(255) | Нүүр зураг |
| `code` | varchar(2) | Хэлний код, эсвэл бүх хэл дээр харагдах хэлнээс үл хамаарах бичлэгт `*` |
| `type` | varchar(32), default 'product' | Бүтээгдэхүүний төрөл |
| `category` | varchar(32), default 'general' | Ангилал |
| `is_featured` | tinyint, default 0 | Онцлох бүтээгдэхүүн |
| `review` | tinyint, default 1 | Үнэлгээ идэвхтэй |
| `read_count` | bigint, default 0 | Үзэлтийн тоо |
| `published` | tinyint, default 0 | Нийтлэгдсэн эсэх |
| `published_at` | datetime | Нийтлэгдсэн огноо |
| `published_by` | bigint | Нийтлэсэн хэрэглэгч |
| `created_at` | datetime | Үүсгэсэн огноо |
| `created_by` | bigint | Үүсгэсэн хэрэглэгч |
| `updated_at` | datetime | Шинэчилсэн огноо |
| `updated_by` | bigint | Шинэчилсэн хэрэглэгч |

#### `generateSlug(string $title): string`
SEO-friendly slug үүсгэх. Монгол кирилл транслитераци дэмждэг.

#### `getExcerpt(string $content, int $length = 200): string`
HTML контентоос товч хураангуй гаргах.

### ProductOrdersModel

**Файл:** `application/dashboard/shop/ProductOrdersModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `products_orders`

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `product_id` | bigint | Бүтээгдэхүүний холбоос (FK -> products) |
| `product_title` | varchar(255) | Бүтээгдэхүүний нэр (хуулбар) |
| `customer_name` | varchar(128) | Захиалагчийн нэр |
| `customer_email` | varchar(128) | Захиалагчийн и-мэйл |
| `customer_phone` | varchar(32) | Захиалагчийн утас |
| `message` | text | Захиалагчийн мессеж |
| `quantity` | int (default: 1) | Тоо ширхэг |
| `code` | varchar(2) | Хэлний код |
| `status` | varchar(32, default: 'new') | Захиалгын статус |
| `created_at` | datetime | Үүсгэсэн огноо |
| `created_by` | bigint | Үүсгэсэн хэрэглэгч (FK -> users) |
| `updated_at` | datetime | Шинэчилсэн огноо |
| `updated_by` | bigint | Шинэчилсэн хэрэглэгч (FK -> users) |

### ShopRouter

**Файл:** `application/dashboard/shop/ShopRouter.php`

Дэлгүүр модулийн нэгдсэн dashboard router: бүтээгдэхүүн, үнэлгээ, захиалга.

**Бүтээгдэхүүн:**

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/products` | GET | `products` |
| `/dashboard/products/list` | GET | `products-list` |
| `/dashboard/products/insert` | GET, POST | `product-insert` |
| `/dashboard/products/{uint:id}` | GET, PUT | `product-update` |
| `/dashboard/products/view/{uint:id}` | GET | `product-view` |
| `/dashboard/products/delete` | DELETE | `product-delete` |
| `/dashboard/products/reset` | DELETE | `products-sample-reset` |

**Үнэлгээ:**

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/products/reviews` | GET, POST | `products-reviews` (GET = HTML, POST = JSON жагсаалт) |
| `/dashboard/products/reviews/delete` | DELETE | `products-reviews-delete` |

**Захиалга:**

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/orders` | GET | `orders` |
| `/dashboard/orders/list` | GET | `orders-list` |
| `/dashboard/orders/view/{uint:id}` | GET | `order-view` |
| `/dashboard/orders/{uint:id}/status` | PATCH | `order-status` |
| `/dashboard/orders/delete` | DELETE | `order-delete` |

---

## Notification

### DiscordListener

**Файл:** `application/dashboard/notification/DiscordListener.php`

PSR-14 event listener - Discord webhook мэдэгдэл илгээнэ. Өмнөх `DiscordNotifier` шууд дуудлагын загварыг орлосон. `ListenerProvider`-ээр бүртгэгддэг.

#### `__construct(DiscordNotifier $notifier)`
Container-ийн `discord` сервисийг хүлээн авна; webhook URL-ийг `DiscordNotifier` өөрөө `RAPTOR_DISCORD_WEBHOOK_URL` орчны хувьсагчаас уншиж, хоосон бол илгээхгүй.

#### `onContentEvent(ContentEvent $event): void`
Контентийн үйлдлүүдийг боловсруулна (нэмэх, засах, устгах, нийтлэх) - Мэдээ, Хуудас, Бүтээгдэхүүн гэх мэт. Тусгай чиглүүлэлт: `module='message'` + `action='new'` -> `newContactMessage()`, `module='comment'` + `action='insert'` -> `newComment()`, `module='review'` + `action='insert'` -> `newReview()`, `module='settings'` -> `settingsUpdated()` (event-ийн `title` нь хэсгийг агуулна: `texts`/`files`/`options`); бусад бүх тохиолдол -> `contentAction()`.

#### `onUserEvent(UserEvent $event): void`
Хэрэглэгчтэй холбоотой event-үүдийг боловсруулна (`'signup_request'` -> `userSignupRequest()`, `'approved'` -> `userApproved()`); өөр action-ийг үл тоомсорлоно.

#### `onOrderEvent(OrderEvent $event): void`
Захиалгын event-үүдийг боловсруулна (`'new'` -> `newOrder()`, `'status_changed'` -> `orderStatusChanged()`); өөр action-ийг үл тоомсорлоно. Бүтээгдэхүүний үнэлгээ `ContentEvent` (`module='review'`)-ээр дамжина.

#### `onDevRequestEvent(DevRequestEvent $event): void`
Хөгжүүлэлтийн хүсэлтийн event-үүдийг боловсруулна (`'new'` -> `newDevRequest()`, `'updated'` -> `devRequestUpdated()`); өөр action-ийг үл тоомсорлоно.

#### Өнгөний тогтмолууд

`DiscordNotifier` дээр `COLOR_*` нэрээр тодорхойлогдсон (`COLOR_SUCCESS`, `COLOR_INFO`, ...).

| Тогтмол | Утга | Хэрэглээ |
|---------|------|----------|
| `SUCCESS` | Ногоон | Зөвшөөрсөн, дууссан |
| `INFO` | Цэнхэр | Мэдээллийн |
| `WARNING` | Шар | Анхааруулга |
| `DANGER` | Улаан | Алдаа, устгалт |
| `PURPLE` | Ягаан | Тусгай үйлдэл |

---

## Development

### DevelopmentRouter

**Файл:** `application/dashboard/development/DevelopmentRouter.php`

| Маршрут | Метод | Нэр | Тайлбар |
|---------|-------|-----|---------|
| `/dashboard/dev-requests` | GET | `dev-requests` | Хүсэлтийн жагсаалт |
| `/dashboard/dev-requests/list` | GET | `dev-requests-list` | JSON жагсаалт |
| `/dashboard/dev-requests/create` | GET | `dev-requests-create` | Хүсэлт үүсгэх форм |
| `/dashboard/dev-requests/store` | POST | `dev-requests-store` | Хүсэлт илгээх |
| `/dashboard/dev-requests/view/{uint:id}` | GET | `dev-requests-view` | Хүсэлт харах |
| `/dashboard/dev-requests/respond` | POST | `dev-requests-respond` | Хариулт нэмэх |
| `/dashboard/dev-requests/delete` | DELETE | `dev-requests-delete` | Устгах (Хогийн савд нөөцлөнө) |

Хандалтын дүрэм: бүх маршрут нэвтэрсэн хэрэглэгчийг шаардана. Нэвтэрсэн хэн ч хүсэлт үүсгэж, зөвхөн өөрийн үүсгэсэн болон өөрт хуваарилагдсан (`created_by`/`assigned_to`) хүсэлтийг харах, хариулах, устгах боломжтой. `system_development` эрхтэй хэрэглэгч (permission бичлэг: alias=`system`, name=`development`) бүх хүсэлтийг харах, хариулах, устгах бүрэн эрхтэй.

---

## Migration

File-based, forward-only SQL migration систем. State нь disk дээрх directory layout-аар тодорхойлогддог (tracking хүснэгт байхгүй). Per-user folder: `database/migrations/{userId}-{username}/` дотор pending файл, `{userId}-{username}/ran/` дотор амжилттай ажилласан файл. `database/migrations/` нь git-ignored.

### MigrationRunner

**Файл:** `application/dashboard/migration/MigrationRunner.php`

| Метод | Тайлбар |
|-------|---------|
| `__construct(\PDO $pdo, string $migrationsPath)` | PDO + migrations хавтасны зам |
| `status(): array` | `['folders' => [...]]` - folder бүрд pending/ran жагсаалт |
| `apply(string $folder, string $filename): array` | Тодорхой pending файлыг ажиллуулж амжилттай бол `ran/` руу зөөнө. Result: `ok`, `sha256`, `statements`, `error?`, `moved_to?` |
| `scan(string $sql): array` | `MigrationSecurityScanner::scan()` дамжуулагч |
| `summarize(string $sql): string` | SQL-ийн товч summary (эхний `--` мөр эсвэл statement-уудаас үүсгэх) |
| `splitStatements(string $sql): array` | SQL-ийг бие даасан statement-уудад хуваах (string/comment aware; dollar-quote зөвхөн pgsql, `\'` backslash-escape зөвхөн mysql) |
| `getUserFolderPath(int $userId, string $username): string` | Cross-OS аюулгүй folder path буцаах |

### MigrationController

**Файл:** `application/dashboard/migration/MigrationController.php`
**Extends:** `Dashboard\Controller`

Зөвхөн `system_coder` role-той хэрэглэгч хандана. Бүх POST үйлдэл CSRF middleware-аар хамгаалагдсан.

| Метод | Тайлбар |
|-------|---------|
| `index()` | Migration dashboard хуудас |
| `status()` | JSON: folder бүрийн pending/ran жагсаалт |
| `view()` | AJAX modal: SQL агуулга + summary + SHA-256 + security warnings |
| `upload()` | POST: `.sql` файл хүлээж аваад `{userId}-{username}/` руу хадгална. Max = `min(10 MB, php.ini post_max_size, upload_max_filesize)` |
| `apply()` | POST `{folder, file, confirm?}`: pending файлыг ажиллуулна. Scanner-ийн ямар ч warning байвал `confirm: 'CONFIRM'` шаардана (үгүй бол 409 `needs_confirm`). Амжилттай бол файл `ran/` руу зөөгдөж, cache бүхэлдээ цэвэрлэгдэнэ (`cache->clear()`) - migration нь эрх, цэс, орчуулга, тохиргоог өөрчилсөн байж болно |
| `delete()` | POST: pending файлыг устгана |

### MigrationRouter

**Файл:** `application/dashboard/migration/MigrationRouter.php`

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/migrations` | GET | `migrations` |
| `/dashboard/migrations/status` | GET | `migrations-status` |
| `/dashboard/migrations/view` | GET | `migrations-view` |
| `/dashboard/migrations/upload` | POST | `migrations-upload` |
| `/dashboard/migrations/apply` | POST | `migrations-apply` |
| `/dashboard/migrations/delete` | POST | `migrations-delete` |

### MigrationSecurityScanner

**Файл:** `application/dashboard/migration/MigrationSecurityScanner.php`

Static SQL scanner - apply хийхээс өмнө sensitive хүснэгтэд хандсан pattern илрүүлнэ.

| Метод | Тайлбар |
|-------|---------|
| `scan(string $sql): array` | Warning жагсаалт буцаана. Хоосон бол safe |

Sensitive хүснэгтүүд (`SENSITIVE_TABLES` const): `users`, `rbac_roles`, `rbac_permissions`, `rbac_user_role`, `rbac_role_permission`, `organizations`, `organizations_users`, `localization_language`, `raptor_menu`. Нэмж DCL (`GRANT`/`REVOKE`, `CREATE`/`DROP`/`ALTER USER`) болон дурын `CREATE [TEMPORARY] TABLE`-ийг (хүснэгт Model классаас үүсэх ёстой) тэмдэглэнэ. Warning бүр `['level' => 'warning', 'reason' => '...']` бүтэцтэй. SQL comment, string literal-аас үүдсэн false-positive-ийг мэдэрч таслана.

---

## Cache

### CacheService

**Файл:** `application/dashboard/CacheService.php`
**Namespace:** `Dashboard`

Custom file-based DB cache (PSR-16 SimpleCache). Гадаад dependency-гүй, зөвхөн `psr/simple-cache` interface ашиглана. Document root-оос гадуурх, дээд түвшний `cache/` хавтаст (`logs/`-тэй ижил түвшинд) хадгалагдана. `ContainerMiddleware`-д `cache` service бүртгэгдсэн. TTL: 12 цаг (нөөц хамгаалалт). Cache хавтас ашиглах боломжгүй бол `fromDefaultPath()` factory `null` буцааж, систем DB-ээс шууд уншина.

| Method | Тайлбар |
|--------|---------|
| `__construct(string $cacheDir, int $defaultTtl = 3600)` | Cache directory + default TTL (0 = хугацаа дуусахгүй). Хавтас үүсгэж чадахгүй бол `RuntimeException` шидэнэ |
| `static fromDefaultPath(int $ttl = 43200): ?self` | Framework-ийн runtime cache-ийг дээд түвшний `cache/` хавтаст (`dirname(SCRIPT_FILENAME, 2) . '/cache'`, `logs/`-тэй зэрэгцээ) үүсгэх factory. Хавтас ашиглах боломжгүй бол `null` буцаана. `ContainerMiddleware` (`cache` service) болон container үүсэхээс өмнө ажилладаг `JWTAuthMiddleware` шууд ашиглана |
| `get(string $key, mixed $default = null): mixed` | Cache-ээс утга авна, байхгүй бол default; хугацаа дууссан бичлэгийг уншихдаа устгана |
| `set(string $key, mixed $value, \DateInterval\|int\|null $ttl = null): bool` | Утга хадгална (`LOCK_EX`); `null` = default TTL |
| `has(string $key): bool` | Key байгаа бөгөөд хугацаа нь дуусаагүй эсэх |
| `delete(string $key): bool` | Cache устгана |
| `clear(): bool` | Бүх cache файлыг устгана |
| `getMultiple()` / `setMultiple()` / `deleteMultiple()` | PSR-16 bulk хувилбарууд |

### Cache-лэгдсэн өгөгдөл

| Key | Хаана ачаалагддаг | Хэзээ устгагддаг |
|-----|-------------------|-----------------|
| `languages` | LocalizationMiddleware | LanguageController |
| `texts.{code}` | LocalizationMiddleware | TextController, LanguageController |
| `settings.{code}` | SettingsMiddleware | SettingsController |
| `menu.{code}` | DashboardTrait | TemplateController (цэс CRUD) |
| `rbac.{userId}` | JWTAuthMiddleware (`CacheService::fromDefaultPath()`-аар - ContainerMiddleware-ээс өмнө ажилладаг) | RBACController (`clear()`) |
| `pages_nav.{code}` | Web TemplateController | PagesController |
| `featured_pages.{code}` | Web TemplateController | PagesController |
| `recent_news.{code}` | HomeController | NewsController |
| `reference.{table}.{code}` (одоогоор `reference.templates.{code}`) | TemplateService (`template_service` container service) | ReferencesController |

### Middleware-д ашиглах

```php
// Cache унших (LocalizationMiddleware, SettingsMiddleware)
$cache = $request->getAttribute('container')?->get('cache');
$data = $cache?->get('my_key');
if ($data === null) {
    $data = $model->retrieve();
    $cache?->set('my_key', $data);
}
```

### Controller-д ашиглах

```php
// Cache унших
$cache = $this->hasService('cache') ? $this->getService('cache') : null;
$data = $cache?->get("pages_nav.$code");
if ($data === null) {
    $data = $model->getNavigation($code);
    $cache?->set("pages_nav.$code", $data);
}

// Cache устгах (амжилттай DB бичлэгийн дараа, respondJSON-ийн өмнө)
$this->invalidateCache('pages_nav.{code}', 'featured_pages.{code}');

// RBAC өөрчлөлт (бүх хэрэглэгчид нөлөөлнө)
if ($this->hasService('cache')) {
    $this->getService('cache')->clear();
}
```

---

## Seed болон анхдагч дата

Seed болон Initial классууд шинэ суулгалт хийхэд өгөгдлийн санг дүүргэнэ. Model-ийн `__initial()` методоос автоматаар дуудагдана.

### PermissionsSeed

**Файл:** `application/dashboard/rbac/PermissionsSeed.php`

`system` alias-тай 26 эрх (runtime key `system_{name}`), тус бүр `module` бүлэглэлийн утгатай: `logger`, `rbac`, `user_index/insert/update/delete/organization_set`, `organization_index/insert/update/delete`, `content_settings/index/insert/update/publish/delete`, `product_index/insert/update/publish/delete`, `localization_index/insert/update/delete`, `development`. `Permissions::__initial()`-аас дуудагдана.

### RolePermissionSeed

**Файл:** `application/dashboard/rbac/RolePermissionSeed.php`

Анхдагч role-ууд үүсгэж эрх оноох:

| Role | Хамрах хүрээ |
|------|-------------|
| `coder` | Super admin - бүх шалгалтыг алгасна (`Roles::__initial()`-д үүсдэг, энэ seed-ээр биш) |
| `admin` | `system`-ийн бүх эрх, `development`-ийг оролцуулаад |
| `manager` | `logger`; хэрэглэгч (index/insert/update/organization_set); байгууллага (index/update); бүх `content_*`; бүх `product_*`; хэл (index/insert/update); `development` |
| `editor` | Контент, бүтээгдэхүүн (index/insert/update/publish), `localization_index` |
| `viewer` | `content_index`, `product_index`, `localization_index` |

### MenuSeed

**Файл:** `application/dashboard/template/MenuSeed.php`

Dashboard sidebar цэсний бүтэц 4 хэсэгтэй (MN/EN):
- **Contents** (position 100) - Мессеж, Хуудас, Мэдээ, Файл, Хэл, Лавлагаа, Тохиргоо
- **Shop** (200) - Бүтээгдэхүүн, Захиалга
- **System** (900) - Хэрэглэгч, Байгууллага, Хандалтын протокол, Хөгжүүлэлтийн хүсэлт (дурын хэрэглэгч), Гарын авлага (дурын хэрэглэгч)
- **Coder** (990, `permission='system_coder'`, зөвхөн coder рольд харагдана) - Database Migration, Хогийн сав, Цэс удирдлага

Цэс бүр `position` дэс дараалалтай; хандалт хязгаартай бол `permission` хамгаалалттай (системийн байгууллагад л зориулсан бол мөн `alias='system'`).

### TextInitial

**Файл:** `application/dashboard/localization/text/TextInitial.php`

100+ системийн орчуулгын keyword MN/EN хос хэлбэрээр (жш: `accept`, `cancel`, `delete`, `dashboard`, `error`, `success`). Цагаан толгойн дарааллаар, `sys-defined` төрөлтэй.

### ReferenceInitial

**Файл:** `application/dashboard/content/reference/ReferenceInitial.php`

`reference_templates` хүснэгтэд и-мэйл загвар, хууль эрхзүйн контент:
- И-мэйл загварууд: `forgotten-password-reset`, `request-new-user`, `approve-new-user`, `dev-request-new`, `dev-request-response`, `contact-message-notify`, `order-status-update`, `order-confirmation`, `order-notify`, `comment-notify`, `review-notify`
- Хууль: `tos` (Үйлчилгээний нөхцөл), `pp` (Нууцлалын бодлого)

### Жишиг дата классууд

Жишиг дата зөвхөн суурь модулиудад байдаг. Шинэ суулгалтад ажиллаж, dashboard-ийн "Reset" товчоор устгаж болно.

| Класс | Файл | Дата |
|-------|------|------|
| `NewsSamples` | `dashboard/content/news/NewsSamples.php` | 6 мэдээ (3 MN + 3 EN), 3 төрөлтэй |
| `PagesSamples` | `dashboard/content/page/PagesSamples.php` | 14+ шатлалтай хуудас (MN + EN) |
| `ProductsSamples` | `dashboard/shop/ProductsSamples.php` | 4 бүтээгдэхүүн (2 MN + 2 EN) |

---

## Trash (Хогийн сав)

### TrashModel

**Файл:** `application/dashboard/trash/TrashModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `trash`

Бүрмөсөн устгасны дараа бичлэгүүдийг JSON хэлбэрээр хадгална. `deleteById()` амжилттай дууссан тохиолдолд л trash-д хадгална.

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `table_name` | varchar(128) | Эх хүснэгтийн нэр |
| `log_table` | varchar(64) | "Restored" мөр бичих log channel-ийн нэр (жш: `products`, `news`, `content`) |
| `original_id` | bigint | Эх хүснэгтэд байсан анхны ID |
| `record_data` | mediumtext | Бичлэгийн бүрэн JSON дата (UTF-8 unescaped) |
| `deleted_by` | bigint | Устгасан хэрэглэгч (FK -> users) |
| `deleted_at` | datetime | Устгасан огноо |

Index: `trash_idx_table` нь `table_name`-д, `trash_idx_deleted` нь `deleted_at DESC`-д.

#### `store(string $logTable, string $tableName, int $originalId, array $recordData, int $deletedBy): array`
Устгасан бичлэгийн хуулбарыг хадгална. Эхний параметр `$logTable` нь controller-ийн `$this->log()`-руу дамжуулдаг log channel-ийн нэр - `restore()` энэ утгыг ашиглаж аудит мөр бичнэ. Controller-ууд `deleteById()` амжилттай дууссаны дараа дуудна. Sidebar badge-д зориулан `trash_log`-д `action='store'` бүхий лог мөн бичигдэнэ.

#### `getById(int $id): array|null`
ID-р нэг trash бичлэг авна.

#### `deleteById(int $id): bool`
Trash бичлэгийг бүрмөсөн устгана.

### TrashController

**Файл:** `application/dashboard/trash/TrashController.php`
**Extends:** `Dashboard\Controller`

Зөвхөн `system_coder` дүртэй хэрэглэгчид (бүх үйлдэл). Mutating маршрутууд `CsrfMiddleware`-тэй.

| Метод | Тайлбар |
|-------|---------|
| `index()` | Хогийн савны удирдлагын хуудас |
| `list()` | JSON trash бичлэгийн жагсаалт (`table_name` шүүлтүүртэй) |
| `view(int $id)` | Устгасан бичлэгийн дэлгэрэнгүй (JSON дата) |
| `restore(int $id)` | Бичлэгийг үндсэн хүснэгт рүү буцаах |
| `delete()` | Нэг trash бичлэгийг бүрмөсөн устгах |
| `empty()` | Бүх trash бичлэгүүдийг хоослох |

**Log channel resolution** - Controller-ууд `TrashModel::store()`-д log channel-ийн нэрийг эхний параметр болгож дамжуулдаг; `restore()` нь `log_table` баганаас уншиж `$this->log()`-руу дамжуулна. Codebase даяар ашигладаг стандарт утга:

| Эх controller | Дамжуулах `log_table` утга |
|---------------|----------------------------|
| `OrdersController` | `products_orders` |
| `ProductsController` (record + хавсралт) | `products` |
| `ReviewsController` | `products` |
| `NewsController` (record + хавсралт) | `news` |
| `CommentsController` | `news` |
| `PagesController` (record + хавсралт) | `pages` |
| `ReferencesController` | `content` |
| `LanguageController` | `content` |
| `TextController` | `content` |
| `TemplateController` (menu delete) | `dashboard` |
| `FilesController` | `files` |
| `MessagesController` | `messages` |
| `DevRequestController` | `dev_requests` |
| `UsersController` (идэвхгүй болгосон хэрэглэгч + бүртгүүлэх хүсэлт) | `users` |
| `OrganizationController` (идэвхгүй болгосон байгууллага) | `organizations` |

#### Restore алгоритм

1. **UNIQUE pre-flight** - schema-аас (MySQL: `information_schema.STATISTICS`, PostgreSQL: `pg_index`) UNIQUE баганаудыг олж тус бүрд live хүснэгтэд давхцал байгаа эсэхийг шалгана. Давхцалтай бол алдаа буцаах (slug, keyword, code, sku гэх мэт талбарыг тодруулж).
2. **Original ID-аар insert** - анхны ID сул эсэхийг шалгана (`SELECT id ... WHERE id=:id`); сул бол FK холбоосыг (`comments.news_id` гэх мэт) хадгалахын тулд тэр ID-аар insert хийнэ.
3. **Auto-increment fallback** - анхны ID аль хэдийн эзлэгдсэн бол `id`-гүйгээр insert хийж, DB-д шинэ ID олгуулна. Хүүхэд бичлэгүүдийн (comments гэх мэт) FK-г гар аргаар шинэчлэх шаардлагатай гэдгийг хариунд анхааруулга бичнэ. (Exception барьж дахин оролдох арга ашигладаггүй: PostgreSQL transaction-ийг тасалдаг.)
4. **LocalizedModel content** - snapshot-д `localized` массив байвал `{primary}_content` хүснэгтэд шинэ `parent_id`-аар тус хэлийн мөр бүрийг insert хийнэ.

2-4-р алхам болон trash мөрийг устгах үйлдэл нэг transaction дотор явагдана; аль нэг нь бүтэлгүйтвэл бүгдийг rollback хийнэ.

5. **Хоёр давхар аудит лог** - `trash_log`-д (`action='trash-restore'`, `restored_by`, `restored_at`, `original_id`, `new_id`, `used_original_id`) ба `log_table` баганаас уншсан channel-д (`action='restore'`, `record_id=<new_id>`) бичнэ. Энэ нь сэргээгдсэн бичлэгийн харах/засах хуудсан дээр Logger Protocol-аар "restored" мөр харагдахын тулд.

### TrashRouter

**Файл:** `application/dashboard/trash/TrashRouter.php`

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/trash` | GET | `trash` |
| `/dashboard/trash/list` | GET | `trash-list` |
| `/dashboard/trash/view/{uint:id}` | GET | `trash-view` |
| `/dashboard/trash/restore/{uint:id}` | POST | `trash-restore` |
| `/dashboard/trash/delete` | DELETE | `trash-delete` |
| `/dashboard/trash/empty` | DELETE | `trash-empty` |

### Устгах стратеги

Контент модулиуд soft delete-ийн оронд **бүрмөсөн устгах + Хогийн сав** ашиглана:

| Стратеги | Хэрэглэх газар | Метод |
|----------|---------------|-------|
| **Бүрмөсөн устгах + Хогийн сав** | News, Pages, Products (+ хавсралт), Orders, Reviews, Comments, Messages, Files, References, DevRequests, Menus, Texts, Languages | Эхлээд `deleteById()`, дараа нь `TrashModel::store()` |
| **Soft delete, дараа нь сонголтоор бүрмөсөн устгах + Хогийн сав** | Users, Organizations | `deactivateById()` (`is_active=0`); идэвхгүй болгосон бичлэгийг дараа нь бүрмөсөн устгаж болно (`/users/delete`, `/organizations/delete`) - `deleteById()` + `TrashModel::store()`. Бүртгүүлэх хүсэлт: `/users/signup/delete` мөн Хогийн савд нөөцлөн устгана |
| **Токен идэвхгүй болгох** (is_active=0) | Forgot (нууц үг сэргээх токен - амжилттай ашиглагдмагц идэвхгүй болно, админы жагсаалтад "used" төлөвт үлдэнэ) | `deactivateById()` |

Хогийн савд нөөцөлдөг устгах маршрутууд:
- `NewsController` (маршрут: `/dashboard/news/delete`)
- `PagesController` (маршрут: `/dashboard/pages/delete`)
- `ProductsController` (маршрут: `/dashboard/products/delete`)
- `OrdersController` (маршрут: `/dashboard/orders/delete`)
- `ReviewsController` (маршрут: `/dashboard/products/reviews/delete`)
- `CommentsController` (маршрут: `/dashboard/news/comments/delete`)
- `MessagesController` (маршрут: `/dashboard/messages/delete`)
- `FilesController` (маршрут: `/dashboard/files/{table}/delete`)
- `ReferencesController` (маршрут: `/dashboard/references/delete`)
- `DevRequestController` (маршрут: `/dashboard/dev-requests/delete`)
- `LanguageController` (маршрут: `/dashboard/language/delete`)
- `TextController` (маршрут: `/dashboard/text/delete`)
- `TemplateController` (маршрут: `/dashboard/manage/menu/delete`)
- `UsersController` (маршрут: `/dashboard/users/delete`, `/dashboard/users/signup/delete`)
- `OrganizationController` (маршрут: `/dashboard/organizations/delete`)

---

## Event систем

### EventDispatcher

**Файл:** `application/dashboard/notification/EventDispatcher.php`
**Implements:** `Psr\EventDispatcher\EventDispatcherInterface`

PSR-14 стандартын event dispatcher. Listener provider-аас listener-үүдийг авч event объектоор дуудна.

#### `__construct(ListenerProviderInterface $listenerProvider)`
PSR-14 дурын listener provider авна (framework `ListenerProvider` дамжуулна).

#### `dispatch(object $event): object`
Listener бүрийг дарааллаар дуудаж, event `isPropagationStopped()` гэж мэдэгдвэл эрт зогсоно; event-ийг буцаана.

### ListenerProvider

**Файл:** `application/dashboard/notification/ListenerProvider.php`
**Implements:** `Psr\EventDispatcher\ListenerProviderInterface`

Event төрөл бүрд listener бүртгэж хангана.

#### `listen(string $eventClass, callable $listener): void`
Event класст listener бүртгэнэ.

#### `getListenersForEvent(object $event): iterable`
Event-ийн яг тэр класст бүртгэгдсэн listener-үүдийг, дараа нь эцэг класс бүрд бүртгэгдсэнийг yield хийнэ (тиймээс `Event::class` дээрх listener бүх event-ийг хүлээн авна).

### Event

**Файл:** `application/dashboard/notification/Event.php`

Бүх event-ийн суурь класс. `StoppableEventInterface`-ийг хэрэгжүүлнэ (`isPropagationStopped()`, `stopPropagation()`), `public readonly string $user` агуулна - үйлдэл хийгчийн нэр, өгөөгүй бол `''` (`DiscordListener` тэгвэл `DiscordNotifier`-ийн хадгалсан админы нэр рүү fallback хийнэ).

### ContentEvent

**Файл:** `application/dashboard/notification/ContentEvent.php`

Контентийн үйлдлүүдэд дамжуулагддаг event. Constructor: `(string $action, string $module, string $title, ?int $id = null, string $user = '', array $updates = [])`.

| Property | Төрөл | Тайлбар |
|----------|-------|---------|
| `$action` | string | Хийсэн үйлдэл (`'insert'`, `'update'`, `'delete'`, `'publish'`, мөн нийтийн холбоо барих мессежид `'message'` модультай `'new'`) |
| `$module` | string | Модуль / контентийн төрөл (`'news'`, `'page'`, `'product'`, `'review'`, `'comment'`, `'message'`, `'settings'`, `'reference'`, `'language'`, `'text'`) |
| `$title` | string | Контентийн гарчиг |
| `$id` | ?int | Контент бичлэгийн ID |
| `$user` | string | Үйлдэл хийсэн хэрэглэгч (`Event`-ээс удамшсан) |
| `$updates` | array | Өөрчлөгдсөн талбарууд (update үйлдэлд) |

### UserEvent

**Файл:** `application/dashboard/notification/UserEvent.php`

Хэрэглэгчтэй холбоотой event.

| Property | Төрөл | Тайлбар |
|----------|-------|---------|
| `$action` | string | Үйлдэл (`'signup_request'`, `'approved'`) - `DiscordListener::onUserEvent()` зөвхөн эдгээрийг боловсруулна |
| `$username` | string | Хэрэглэгчийн нэр |
| `$email` | string | И-мэйл хаяг |

### OrderEvent

**Файл:** `application/dashboard/notification/OrderEvent.php`

Захиалгатай холбоотой event.

| Property | Төрөл | Тайлбар |
|----------|-------|---------|
| `$action` | string | Үйлдэл (`'new'`, `'status_changed'`) - `DiscordListener::onOrderEvent()` зөвхөн эдгээрийг боловсруулна |
| `$orderId` | int | Захиалгын ID |
| `$customer` | string | Захиалагчийн нэр |
| `$email` | string | Захиалагчийн и-мэйл |
| `$phone` | string | Захиалагчийн утас |
| `$product` | string | Бүтээгдэхүүний нэр |
| `$quantity` | int | Тоо ширхэг |
| `$oldStatus` | string | Өмнөх статус (статус өөрчлөлтөд) |
| `$newStatus` | string | Шинэ статус (статус өөрчлөлтөд) |

### DevRequestEvent

**Файл:** `application/dashboard/notification/DevRequestEvent.php`

Хөгжүүлэлтийн хүсэлтийн event.

| Property | Төрөл | Тайлбар |
|----------|-------|---------|
| `$action` | string | Үйлдэл (`'new'`, `'updated'`) - `DiscordListener::onDevRequestEvent()` зөвхөн эдгээрийг боловсруулна |
| `$requestId` | int | Хүсэлтийн ID |
| `$title` | string | Хүсэлтийн гарчиг |
| `$assignedTo` | string | Хариуцсан хөгжүүлэгч |
| `$status` | string | Хүсэлтийн статус |

### Controller дотор хэрэглэх

```php
// Мэдээ нэмсний дараа content event дамжуулах
$this->dispatch(new ContentEvent(
    'insert', 'news', $record['title'], $id
));
```
