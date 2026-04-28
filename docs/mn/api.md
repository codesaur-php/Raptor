# Raptor API Reference (MN)

> Бүх модуль, класс, методуудын дэлгэрэнгүй тайлбар.

---

## Агуулга

1. [Raptor\Controller](#raptorcontroller)
2. [Raptor\Application](#raptorapplication)
3. [Authentication](#authentication)
4. [User](#user)
5. [Organization](#organization)
6. [RBAC](#rbac)
7. [Content - Files](#content--files)
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

## Raptor\Controller

**Файл:** `application/raptor/Controller.php`
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
Template үүсгэх. Автоматаар `user`, `index`, `localization`, `request` хувьсагчид нэмэгдэнэ. `text` болон `link` filter-ууд бүртгэгдэнэ.

#### `respondJSON(array $response, int|string $code = 0): void`
JSON хариулт хэвлэх. `Content-Type: application/json` header тохируулна.

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

#### `invalidateCache(string ...$keys): void`
Заасан cache key-үүдийг устгана. `{code}` placeholder ашиглавал бүх хэлээр давтана. Cache байхгүй бол алгасна.

```php
$this->invalidateCache('pages_nav.{code}', 'featured_pages.{code}');
$this->invalidateCache('texts.{code}');
$this->invalidateCache('languages');
```

#### `headerResponseCode(int|string $code): void`
HTTP response code тохируулах. Стандарт бус код бол алгасна.

#### `getScriptPath(): string`
Script path буцаах (subdirectory дэмжлэг).

#### `getDocumentRoot(): string`
Document root зам буцаах.

---

## Middleware-ийн аюулгүй байдлын дүрэм

### handle()-г try/catch дотор хэзээ ч дуудаж болохгүй

Middleware runner нь дотоод array pointer (`current()`/`next()`) ашиглан queue-г дамждаг. `$handler->handle()` дуудагдах бүрт pointer нэг алхам ахина - буцаах боломжгүй.

**Хэрвээ `handle()`-г `try` блок дотор дуудвал**, гүнд exception уусаад буцаж ирэхэд `catch` блок барьж авна - гэхдээ pointer аль хэдийн ахисан байна. Тэгээд `try`-ийн гадна дахин `handle()` дуудвал pointer хэтэрч, `current()` нь `false` буцааж програм унана.

```php
// БУРУУ - exception уусахад handle() давхар дуудагдана
public function process($request, $handler): ResponseInterface
{
    try {
        $data = $cache->get('key');
        if ($data !== null) {
            return $handler->handle($request->withAttribute('data', $data));
            //     ^^^^^^^^^^^^^^^ try дотор дуудагдсан - pointer ахина
            //     Гүнд exception уусвал catch барьж авна,
            //     тэгээд доорх handle() ДАХИАД дуудагдана
        }
        $data = $this->loadFromDb();
    } catch (\Throwable $e) {
        \error_log($e->getMessage());
        // Exception чимээгүй баригдсан - гүйцэтгэл үргэлжилнэ
    }
    return $handler->handle($request->withAttribute('data', $data ?? []));
    //     ^^^^^^^^^^^^^^^ ХОЁР ДАХЬ дуудалт - pointer хэтэрсэн -> crash
}
```

```php
// ЗӨВ - handle()-г зөвхөн нэг удаа, try-ийн гадна дуудна
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
    //     ^^^^^^^^^^^^^^^ зөвхөн НЭГ удаа, try-ийн гадна дуудагдана
}
```

**Дүрмүүд:**
- `$handler->handle()` нь middleware бүрт яг **НЭГ** удаа дуудагдах ёстой
- Тэр дуудалт нь `try/catch` блокийн **ГАДНА** байх ёстой
- `try/catch` нь зөвхөн data бэлтгэх логикийг (DB query, cache read, validation) хамрах ёстой
- `catch` блок нь алдааг зохицуулаад (лог, default утга), гүйцэтгэлийг ганц `handle()` дуудалт руу урсгах ёстой

---

## Raptor\Application

**Файл:** `application/raptor/Application.php`
**Extends:** `codesaur\Http\Application\Application`

Dashboard Application-ийн суурь. Middleware pipeline болон Router-уудыг бүртгэнэ.

### Constructor Pipeline

1. `ErrorHandler` - Алдаа барих
2. `MySQLConnectMiddleware` - DB холболт
3. `MigrationMiddleware` - Pending migration автоматаар ажиллуулах
4. `SessionMiddleware` - Session удирдлага
5. `JWTAuthMiddleware` - JWT баталгаажуулалт
6. `CsrfMiddleware` - POST/PUT/PATCH/DELETE хүсэлтүүдэд CSRF token шалгах
7. `ContainerMiddleware` - DI Container
8. `LocalizationMiddleware` - Олон хэл
9. `SettingsMiddleware` - Тохиргоо
10. `LoginRouter`, `UsersRouter`, `OrganizationRouter`, `RBACRouter`, `LocalizationRouter`, `ContentsRouter`, `LogsRouter`, `DevelopmentRouter`, `MigrationRouter`, `TemplateRouter`, `BadgeRouter`

`Dashboard\Application` нэмдэг: `HomeRouter`, `ShopRouter` (products + orders + reviews), `ManualRouter`

---

## Authentication

### JWTAuthMiddleware

**Файл:** `application/raptor/authentication/JWTAuthMiddleware.php`
**Implements:** `MiddlewareInterface`

#### `generate(array $data): string`
JWT токен үүсгэх. Payload дотор `iat`, `exp`, `seconds` + `$data` орно.

#### `validate(string $jwt): array`
JWT decode + validate хийх. Хугацаа дууссан бол `RuntimeException`. `user_id`, `organization_id` шаардлагатай.

#### `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`
1. `$_SESSION['RAPTOR_JWT']` уншина
2. JWT validate хийнэ
3. Хэрэглэгчийн profile DB-с татна
4. Байгууллагын хамаарлыг шалгана
5. RBAC эрхүүдийг ачаална
6. `User` объект үүсгэж request attribute-д нэмнэ
7. Алдаа гарвал `/dashboard/login` руу redirect хийнэ

### SessionMiddleware

**Файл:** `application/raptor/SessionMiddleware.php`
**Implements:** `MiddlewareInterface`

Dashboard болон Web app хоёуланд ашиглагдах session middleware.
Session эхлүүлж, read-only route дээр write-lock-ийг эрт суллана.

Constructor-аар `needsWrite` closure авна:
- Dashboard: `fn($path, $method) => str_contains($path, '/login')`
- Web: `fn($path, $method) => str_starts_with($path, '/language/') || ...`

Closure null бол бүх route дээр session_write_close() дуудна.

### LoginRouter

**Файл:** `application/raptor/authentication/LoginRouter.php`

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

**Файл:** `application/raptor/authentication/User.php`

| Property | Type | Тайлбар |
|----------|------|---------|
| `$profile` | `array` | Хэрэглэгчийн profile |
| `$organization` | `array` | Байгууллагын мэдээлэл |
| `$permissions` | `array` | RBAC эрхүүд |

| Метод | Тайлбар |
|-------|---------|
| `is(string $role): bool` | Role шалгах |
| `can(string $permission): bool` | Permission шалгах |

---

## User

### UsersModel

**Файл:** `application/raptor/user/UsersModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `users`

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `username` | varchar(50) | Нэвтрэх нэр |
| `email` | varchar(100) | И-мэйл хаяг |
| `password` | varchar(255) | Bcrypt hash |
| `phone` | varchar(50) | Утас |
| `first_name` | varchar(50) | Нэр |
| `last_name` | varchar(50) | Овог |
| `photo` | varchar(255) | Avatar зураг |
| `is_active` | tinyint | Идэвхтэй эсэх |
| `created_at` | datetime | Үүсгэсэн огноо |
| `updated_at` | datetime | Шинэчилсэн огноо |

---

## Organization

### OrganizationModel

**Файл:** `application/raptor/organization/OrganizationModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `organizations`

### OrganizationUserModel

**Файл:** `application/raptor/organization/OrganizationUserModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `organizations_users`

Хэрэглэгч-байгууллагын холбоос хүснэгт.

---

## RBAC

### RBAC

**Файл:** `application/raptor/rbac/RBAC.php`

Хэрэглэгчийн бүх role болон permission-г ачаалж `jsonSerialize()` хэлбэрээр буцаадаг.

### Roles

**Файл:** `application/raptor/rbac/Roles.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `rbac_roles`

### Permissions

**Файл:** `application/raptor/rbac/Permissions.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `rbac_permissions`

### RolePermissions

**Файл:** `application/raptor/rbac/RolePermissions.php`

Role-Permission хамаарал.

### UserRole

**Файл:** `application/raptor/rbac/UserRole.php`

User-Role хамаарал.

---

## Content - Files

### FilesModel

**Файл:** `application/raptor/content/file/FilesModel.php`
**Extends:** `codesaur\DataObject\Model`

Файлуудын мэдээлэл хадгалах. Хүснэгтийн нэр динамик (`setTable()`).

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `record_id` | bigint | Хамаарах бичлэгийн ID |
| `file` | varchar(255) | Анхны файлын нэр |
| `path` | varchar(255) | Хадгалагдсан зам |
| `size` | bigint | Файлын хэмжээ (bytes) |
| `type` | varchar(50) | Файлын төрөл (image, video, document...) |
| `mime_content_type` | varchar(100) | MIME type |
| `keyword` | varchar(255) | Түлхүүр үг |
| `description` | text | Тайлбар |
| `created_at` | datetime | Үүсгэсэн огноо |
| `created_by` | bigint | Үүсгэсэн хэрэглэгч |
| `updated_at` | datetime | Шинэчилсэн огноо |
| `updated_by` | bigint | Шинэчилсэн хэрэглэгч |

### FilesController

**Файл:** `application/raptor/content/file/FilesController.php`

| Метод | Тайлбар |
|-------|---------|
| `index()` | Файлын менежмент хуудас |
| `list(string $table)` | JSON файлын жагсаалт |
| `upload()` | Файл upload хийх (хадгалахгүй, зөвхөн зөөх) |
| `post(string $table)` | Upload + DB-д бүртгэх |
| `modal(string $table)` | Файл сонгох modal |
| `update(string $table, int $id)` | Файлын мэдээлэл шинэчлэх |
| `delete(string $table)` | Бүрмөсөн устгах (Хогийн савд нөөцлөнө) |

### ContentsRouter - Files маршрутууд

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/files` | GET | `files` |
| `/dashboard/files/list/{table}` | GET | `files-list` |
| `/dashboard/files/upload` | POST | `files-upload` |
| `/dashboard/files/post/{table}` | POST | `files-post` |
| `/dashboard/files/modal/{table}` | GET | `files-modal` |
| `/dashboard/files/{table}/{uint:id}` | PATCH | `files-update` |
| `/dashboard/files/{table}/delete` | DELETE | `files-delete` |
| `/dashboard/private/file` | GET | - |

---

## Content - News

### NewsModel

**Файл:** `application/raptor/content/news/NewsModel.php`
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
| `code` | varchar(2) | Хэлний код |
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
Сүүлийн нийтлэгдсэн мэдээнүүдийг буцаана. id, slug, title, description, photo, code, type, category, is_featured, comment, published_at, created_at, source талбаруудыг авна. `read_count` (dynamic) оруулаагүй тул cache-д тохиромжтой. HomeController-д `recent_news.{code}` cache key-ээр ашиглагдана.

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
| `/dashboard/news/view/{uint:id}` | GET | - |
| `/dashboard/news/delete` | DELETE | `news-delete` |
| `/dashboard/news/reset` | DELETE | `news-sample-reset` |

---

## Content - Comments

### CommentsModel

**Файл:** `application/raptor/content/news/CommentsModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `news_comments`

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `news_id` | bigint | Мэдээний холбоос (FK -> news) |
| `parent_id` | bigint | Эцэг сэтгэгдэл, 1 түвшний хариулт (FK -> news_comments, self) |
| `created_by` | bigint | Зохиогч хэрэглэгч (FK -> users, зочин бол null) |
| `name` | varchar(128) | Сэтгэгдэл бичигчийн нэр |
| `email` | varchar(128) | Сэтгэгдэл бичигчийн и-мэйл |
| `comment` | text | Сэтгэгдлийн текст |
| `created_at` | datetime | Үүсгэсэн огноо |

> **Тэмдэглэл:** `is_active` багана хасагдсан. Устгалтыг бүрмөсөн устгах + Хогийн сав аргаар хийнэ.

### CommentsController (Dashboard)

**Файл:** `application/raptor/content/news/CommentsController.php`
**Extends:** `Raptor\Controller`

| Метод | Тайлбар |
|-------|---------|
| `index()` | Сэтгэгдлийн удирдлагын хуудас |
| `list()` | JSON сэтгэгдлийн жагсаалт |
| `view(int $id)` | Сэтгэгдлийн дэлгэрэнгүй |
| `delete()` | Бүрмөсөн устгах (Хогийн савд нөөцлөнө) |

### ContentsRouter - Comments маршрутууд

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/news/comments` | GET | `comments` |
| `/dashboard/news/comments/list` | GET | `comments-list` |
| `/dashboard/news/comments/{uint:id}` | GET | `comments-view` |
| `/dashboard/news/comments/delete` | DELETE | `comments-delete` |
| `/dashboard/news/comment/{uint:id}/reply` | GET | - |

---

## Content - Messages

### MessagesModel

**Файл:** `application/raptor/content/messages/MessagesModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `messages`

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `name` | varchar(128) | Илгээгчийн нэр |
| `phone` | varchar(50) | Илгээгчийн утас |
| `email` | varchar(128) | Илгээгчийн и-мэйл |
| `message` | text | Мессежийн текст |
| `code` | varchar(2) | Хэлний код |
| `is_read` | tinyint | Уншсан эсэх (0=шинэ, 1=уншсан, 2=хариулсан) |
| `replied_note` | text | Админы хариултын тэмдэглэл |
| `created_at` | datetime | Үүсгэсэн огноо |

> **Тэмдэглэл:** `is_active` багана хасагдсан. Устгалтыг бүрмөсөн устгах + Хогийн сав аргаар хийнэ.

### MessagesController (Dashboard)

**Файл:** `application/raptor/content/messages/MessagesController.php`
**Extends:** `Raptor\Controller`

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

**Файл:** `application/raptor/content/page/PagesModel.php`
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
| `code` | varchar(2) | Хэлний код |
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
Нийтлэгдсэн `*-menu` төрлийн хуудсуудаас мод бүтэцтэй навигаци буцаана. position, id-р эрэмбэлнэ.

#### `buildTree(array $pages, int $parentId = 0): array`
Хуудсуудын жагсаалтаас parent -> children -> submenu рекурсив мод бүтэц үүсгэх.

#### `getFeaturedLeafPages(string $code): array`
Онцлох хуудсуудаас child-гүй (leaf) хуудсуудыг буцаана.

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
| `/dashboard/pages/view/{uint:id}` | GET | - |
| `/dashboard/pages/delete` | DELETE | `page-delete` |
| `/dashboard/pages/reset` | DELETE | `pages-sample-reset` |

---

## Content - References

### ReferencesModel

**Файл:** `application/raptor/content/reference/ReferencesModel.php`
**Extends:** `codesaur\DataObject\LocalizedModel`

Динамик хүснэгтийн нэртэй лавлагааны хүснэгт.

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

**Файл:** `application/raptor/content/settings/SettingsModel.php`
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
| `is_active` | tinyint | Идэвхтэй эсэх |

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
Идэвхтэй (`is_active=1`) тохиргоог авах. Хоосон бол `[]`.

### SettingsMiddleware

**Файл:** `application/raptor/content/settings/SettingsMiddleware.php`
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
| `RAPTOR_CONTACT_EMAIL_NOTIFY` | bool | true | Холбоо барих мессежийн имэйл мэдэгдэл toggle |
| `RAPTOR_CONTACT_EMAIL_TO` | email | - | Мессежийн мэдэгдэл хүлээн авах имэйл |
| `RAPTOR_ORDER_EMAIL_NOTIFY` | bool | true | Захиалгын имэйл мэдэгдэл toggle |
| `RAPTOR_ORDER_EMAIL_TO` | email | - | Захиалгын мэдэгдэл хүлээн авах имэйл |
| `RAPTOR_COMMENT_EMAIL_NOTIFY` | bool | false | Сэтгэгдлийн имэйл мэдэгдэл toggle |
| `RAPTOR_COMMENT_EMAIL_TO` | email | - | Сэтгэгдлийн мэдэгдэл хүлээн авах имэйл |
| `RAPTOR_REVIEW_EMAIL_NOTIFY` | bool | false | Үнэлгээний имэйл мэдэгдэл toggle |
| `RAPTOR_REVIEW_EMAIL_TO` | email | - | Үнэлгээний мэдэгдэл хүлээн авах имэйл |

**Төрлийн ажиллагаа:**
- `bool` - Одоогийн утгыг эсрэгээр солино (`value` талбар хэрэггүй). Хариуд `value: true|false`
- `email` - `filter_var()` ашиглан имэйл формат шалгана. Хоосон утга нь хаягийг арилгана
- `string` - Шалгалтгүй, шууд хадгална

Messages, orders, comments, reviews жагсаалтын хуудасны дээд хэсэгт `system_coder` эрхтэй хэрэглэгчдэд харагддаг тохиргоо.

---

## Localization

### LanguageModel

**Файл:** `application/raptor/localization/language/LanguageModel.php`
**Extends:** `codesaur\DataObject\Model`

Хэлний бүртгэлийн хүснэгт.

### TextModel

**Файл:** `application/raptor/localization/text/TextModel.php`
**Extends:** `codesaur\DataObject\Model`

Орчуулгын текстүүд (key -> value).

#### `retrieve(array $languageCodes): array`
Бүх орчуулгыг хэлний код -> key -> value бүтцээр буцаана.

### LocalizationMiddleware

**Файл:** `application/raptor/localization/LocalizationMiddleware.php`
**Implements:** `MiddlewareInterface`

Dashboard болон Web app хоёуланд ашиглагдана. Constructor-аар session key авна:
- Dashboard: `new LocalizationMiddleware()` - default `RAPTOR_LANGUAGE_CODE`
- Web: `new LocalizationMiddleware('WEB_LANGUAGE_CODE')`

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

**Файл:** `application/raptor/log/Logger.php`
**Extends:** `\Psr\Log\AbstractLogger`

PSR-3 стандартын лог систем. Өгөгдлийн санд хадгална.

#### `setTable(string $table): void`
Лог хүснэгтийн нэр тохируулах.

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

**Файл:** `application/raptor/mail/Mailer.php`

И-мэйл илгээх. `send()` нь `RAPTOR_MAIL_TRANSPORT` .env тохиргоогоор transport сонгоно: `brevo` (анхдагч), `smtp`, `mail`.

---

## Database Middleware

### MySQLConnectMiddleware

**Файл:** `application/raptor/MySQLConnectMiddleware.php`

1. ENV-аас DB тохиргоо уншина
2. MySQL серверт PDO холболт үүсгэнэ
3. Localhost дээр database автоматаар үүсгэнэ
4. charset/collation тохируулна
5. `pdo` нэрээр request attribute-д inject хийнэ

### PostgresConnectMiddleware

**Файл:** `application/raptor/PostgresConnectMiddleware.php`

PostgreSQL-д зориулсан. DSN: `pgsql:host=...;dbname=...`

### ContainerMiddleware

**Файл:** `application/raptor/ContainerMiddleware.php`

PSR-11 DI Container-г request-д inject хийнэ. PDO, User ID, `EventDispatcher` (`DiscordListener`-тэй), `DiscordNotifier` (legacy) сервисийг container-д бүртгэнэ.

---

## SpamProtectionTrait

**Файл:** `application/raptor/SpamProtectionTrait.php`

Cloudflare Turnstile болон линк-д суурилсан эвристик ашиглан спам хамгаалалтын методууд хангана.

### Methods

#### `getTurnstileSiteKey(): string`
ENV тохиргооноос Turnstile site key буцаана. Тохируулаагүй бол хоосон string.

#### `validateSpamProtection(): bool`
Request-аас Cloudflare Turnstile токен шалгана. Баталгаажуулалт амжилттай эсвэл Turnstile тохируулаагүй бол `true` буцаана.

#### `checkLinkSpam(string $text): bool`
Текстэд сэжигтэй линк загвар байгаа эсэх шалгана. Спам илэрвэл `true` буцаана.

### Хэрэглэдэг газрууд

- `Web\Service\ContactController` - Холбоо барих форм илгээх
- `Web\Content\NewsController` - Мэдээний сэтгэгдэл илгээх
- `Web\Shop\ShopController` - Захиалга илгээх
- `Raptor\Authentication\LoginController` - Бүртгүүлэх, нууц үг сэргээх

---

## CsrfMiddleware

**Файл:** `application/raptor/CsrfMiddleware.php`
**Implements:** `Psr\Http\Server\MiddlewareInterface`

Dashboard-ийн state-changing хүсэлтүүдэд session-тай холбоотой CSRF token шалгалт.

### Ажиллах зарчим

1. Token нь login үед үүсэж `$_SESSION['CSRF_TOKEN']` дотор хадгалагдана
2. Token байхгүй хуучин session-д session бичих эрхтэй үед автоматаар үүсгэнэ
3. Token-г `csrf_token` request attribute-аар controller-уудад дамжуулна
4. GET/HEAD/OPTIONS хүсэлтүүд шалгалтгүйгээр дамжина
5. `/login` замууд exempt (тэнд token үүсдэг)
6. Бусад бүх методууд (POST, PUT, PATCH, DELETE) `X-CSRF-TOKEN` header шаарддаг
7. Token таарахгүй эсвэл байхгүй бол 403 JSON хариу буцаана

### Frontend интеграци

- Token нь `dashboard.html` дахь `<meta name="csrf-token">` tag-аар frontend-д хүрнэ
- `dashboard.js` дахь `csrfFetch()` wrapper нь `X-CSRF-TOKEN` header-г автоматаар нэмнэ
- Dashboard модулиудын бүх POST/PUT/PATCH/DELETE хүсэлтэд `csrfFetch()` ашиглана
- Standalone хуудсууд (жнь: login) `dashboard.js` ачаалагдахгүй тул энгийн `fetch()` ашиглана

---

## HtmlValidationTrait

**Файл:** `application/raptor/content/HtmlValidationTrait.php`

Серверийн талын HTML контент шалгалт. Pages, News, Products controller-ууд insert/update хийхэд ашиглана.

#### `validateHtmlContent(string $html): void`
Хаагдаагүй HTML comment (`<!-- -->`), эвдэрсэн tag шалгана. DOMDocument ашиглан текстийн урт харьцуулна. 20%-аас их алдагдалтай бол `InvalidArgumentException` шидэнэ.

### Ашигладаг газрууд

- `Raptor\Content\NewsController` - Мэдээ нэмэх/засах
- `Raptor\Content\PagesController` - Хуудас нэмэх/засах
- `Dashboard\Shop\ProductsController` - Бүтээгдэхүүн нэмэх/засах

---

## DashboardTrait

**Файл:** `application/raptor/template/DashboardTrait.php`

Dashboard UI рендерлэлт, эрхийн мэдэгдэл, sidebar цэс үүсгэх, хэрэглэгчийн мэдээлэл авах.

#### `dashboardTemplate(string $template, array $vars = []): FileTemplate`
`dashboard.html` layout дотор контент рендерлэнэ. Sidebar цэс, тохиргоо ачаална. Цэсийг cache-с уншина (`menu.{code}` key).

#### `dashboardProhibited(?string $alert = null, int|string $code = 0): FileTemplate`
Эрхийн хориглолын мэдэгдэл dashboard layout дотор харуулна.

#### `modalProhibited(?string $alert = null, int|string $code = 0): FileTemplate`
Эрхийн хориглолын modal (standalone, layout-гүй).

#### `getUserMenu(): array`
Хэрэглэгчийн эрх, байгууллагын alias, харагдах байдал, идэвхжилтээр шүүсэн sidebar цэс үүсгэнэ.

#### `retrieveUsersDetail(?int ...$ids): array`
`[user_id => "username - First Last (email)"]` map буцаана. ID өгөөгүй бол бүх хэрэглэгчид.

---

## FileController

**Файл:** `application/raptor/content/file/FileController.php`
**Extends:** `Raptor\Controller`

Файл upload, шалгалт, хадгалалт, зураг optimize хийх суурь класс. FilesController, SettingsController, UsersController зэрэг файл upload хийдэг controller-ууд үүнийг extend хийнэ.

### Гол методууд

| Метод | Тайлбар |
|-------|---------|
| `setFolder(string $folder)` | Upload хавтас тохируулах (жнь: `/users/1`, `/pages/22`) |
| `getFilePublicPath(string $fileName)` | Файлын public URL зам буцаах |
| `allowExtensions(array $exts)` | Зөвшөөрөх файлын extension-ууд |
| `allowImageOnly()` | Зөвхөн зурагны extension зөвшөөрөх |
| `allowCommonTypes()` | Түгээмэл вэб файлын төрлүүд зөвшөөрөх (зураг, баримт, медиа, архив) |
| `setSizeLimit(int $size)` | Дээд хэмжээ bytes-ээр |
| `setOverwrite(bool $overwrite)` | Давхцах нэрийн файлыг дарж бичих эсэх |
| `moveUploaded($uploadedFile, bool $optimize)` | Үндсэн upload: шалгаж, хадгалж, файлын мэдээлэл буцаана |
| `optimizeImage(string $filePath)` | JPEG/PNG/GIF/WebP вэб-д зориулж хэмжээ/чанар optimize хийнэ |
| `getMaximumFileUploadSize()` | `MIN(post_max_size, upload_max_filesize)` bytes-ээр |
| `formatSizeUnits(?int $bytes)` | Хүний уншихад хялбар формат (жнь: `10.5mb`) |
| `unlinkByName(string $fileName)` | Upload хавтаснаас файл устгах |

---

## AIHelper

**Файл:** `application/raptor/content/AIHelper.php`
**Extends:** `Raptor\Controller`

moedit WYSIWYG editor-ийн OpenAI API интеграци. HTML контент сайжруулалт (Shine) болон зургаас текст таних (OCR/Vision).

#### `moeditAI(): void`
POST `/dashboard/content/moedit/ai` - Хоёр горимтой:

**HTML горим** (`mode: 'html'`): GPT-4o-mini ашиглан HTML контент сайжруулна. Body: `{mode, html, prompt}`.

**Vision горим** (`mode: 'vision'`): GPT-4o vision ашиглан зургаас текст таниулна. Body: `{mode, images[], prompt}`.

Хариу: `{status: 'success', html: '...'}` эсвэл `{status: 'error', message: '...'}`.

`.env`-д `RAPTOR_OPENAI_API_KEY` шаардлагатай. Нэвтэрсэн хэрэглэгч шаардлагатай.

---

## Badge систем

### BadgeController

**Файл:** `application/raptor/template/BadgeController.php`
**Extends:** `Raptor\Controller`

Dashboard sidebar-д модуль тус бүрийн уншаагүй үйлдлийн тоог badge-ээр харуулах систем. `*_log` хүснэгтүүдээс уншина.

#### `list(): void`
GET `/dashboard/badges` - Модуль бүрийн badge тоог JSON-оор буцаана. Өнгө: ногоон=create, цэнхэр=update, улаан=delete. Админы эрхээр шүүнэ. Өөрийн үйлдлийг тоолохгүй. Шинэ хэрэглэгчид 30 хоногийн lookback.

#### `seen(): void`
POST `/dashboard/badges/seen` - Модулийг уншсан гэж тэмдэглэнэ. `checked_at` timestamp шинэчилнэ.

### Тогтмолууд

- `BADGE_MAP` - `[log_table][action]` -> `[module_path, color]` зурагчлал
- `PERMISSION_MAP` - Модуль бүрд шаардагдах эрх (`null` = аль ч админ, `'system_x'` = эрх шалгах, `'role:system_coder'` = role шалгах)

### BadgeRouter

**Файл:** `application/raptor/template/BadgeRouter.php`

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/badges` | GET | `dashboard-badges` |
| `/dashboard/badges/seen` | POST | - |

### AdminBadgeSeenModel

**Файл:** `application/raptor/template/AdminBadgeSeenModel.php`
**Extends:** `codesaur\DataObject\Model`

Админ тус бүрийн модуль сүүлд хэзээ үзсэн мэдээллийг хадгална. Багана: `admin_id`, `module`, `checked_at`, `last_seen_count`. Unique index: `(admin_id, module)`.

---

## MenuModel

**Файл:** `application/raptor/template/MenuModel.php`
**Extends:** `codesaur\DataObject\LocalizedModel`

Dashboard sidebar цэсний олон хэлтэй, parent/child бүтэцтэй model.

### Баганууд

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `parent_id` | bigint | Эцэг цэсний ID (0 = root) |
| `icon` | varchar(64) | Bootstrap Icons класс |
| `href` | varchar(255) | Цэсний холбоос URL |
| `alias` | varchar(64) | Байгууллагын alias шүүлтүүр |
| `permission` | varchar(128) | Цэсийг харахад шаардагдах эрх |
| `position` | smallint | Дэс дараалал |
| `is_visible` | tinyint | Харагдах эсэх |
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
| `/dashboard` | GET | `dashboard` |
| `/dashboard/search` | GET | - |
| `/dashboard/stats` | GET | - |
| `/dashboard/log-stats` | GET | - |

### SearchController

**Файл:** `application/dashboard/home/SearchController.php`
**Extends:** `Raptor\Controller`

#### `search(): void`
Dashboard ерөнхий хайлт. Мэдээ, хуудас, бүтээгдэхүүн, захиалга, хэрэглэгч хүснэгтүүдээс LIKE query ашиглан хайна. RBAC эрхээр шүүнэ. JSON буцаана.

### WebLogStatsController

**Файл:** `application/dashboard/home/WebLogStatsController.php`
**Extends:** `Raptor\Controller`

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
| `/dashboard/manual/{file}` | GET | - |

### ManualController

**Файл:** `application/dashboard/manual/ManualController.php`
**Extends:** `Raptor\Controller`

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
ExceptionHandler -> MySQL -> Container -> Session -> Localization -> Settings -> WebRouter

### WebRouter

**Файл:** `application/web/WebRouter.php`

| Маршрут | Метод | Нэр | Тайлбар |
|---------|-------|-----|---------|
| `/` | GET | `home` | Нүүр хуудас |
| `/home` | GET | - | Нүүр alias |
| `/language/{code}` | GET | `language` | Хэл солих |
| `/page/{uint:id}` | GET | `page-by-id` | ID-р хуудас (slug руу redirect) |
| `/page/{slug}` | GET | `page` | Хуудас үзэх |
| `/contact` | GET | `contact` | Холбоо барих |
| `/news/{uint:id}` | GET | `news-by-id` | ID-р мэдээ (slug руу redirect) |
| `/news/{slug}` | GET | `news` | Мэдээ үзэх |
| `/news/type/{type}` | GET | `news-type` | Төрлөөр мэдээ |
| `/archive` | GET | `archive` | Мэдээний архив |
| `/products` | GET | `products` | Бүтээгдэхүүний жагсаалт |
| `/product/{uint:id}` | GET | `product-by-id` | ID-р бүтээгдэхүүн (slug руу redirect) |
| `/product/{slug}` | GET | `product` | Бүтээгдэхүүн үзэх |
| `/order` | GET | `order` | Захиалгын форм |
| `/order` | POST | `order-submit` | Захиалга илгээх |
| `/search` | GET | `search` | Хайлт |
| `/sitemap` | GET | `sitemap` | Sitemap хуудас |
| `/sitemap.xml` | GET | - | XML sitemap |
| `/rss` | GET | `rss` | RSS feed |
| `/session/contact-send` | POST | `contact-send` | Холбоо барих мессеж илгээх |
| `/session/order` | POST | - | Захиалга илгээх (session) |
| `/session/language/{code}` | GET | - | Хэл солих (session) |
| `/session/news/{uint:id}/comment` | POST | `news-comment` | Мэдээнд сэтгэгдэл бичих |

### HomeController

**Файл:** `application/web/HomeController.php`
**Extends:** `TemplateController`

| Метод | Тайлбар |
|-------|---------|
| `index()` | Нүүр хуудас (сүүлийн 20 нийтлэгдсэн мэдээ, хэлээр cache хийгдсэн) |
| `favicon()` | Favicon redirect эсвэл 204 No Content cache header-тэй |
| `language(string $code)` | Session хэл тохируулж нүүр хуудас руу redirect |

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

### SeoController

**Файл:** `application/web/service/SeoController.php`
**Extends:** `TemplateController`

| Метод | Тайлбар |
|-------|---------|
| `search()` | Хуудас, мэдээ, бүтээгдэхүүнээс хайлт (доод тал нь 2 тэмдэгт) |
| `sitemap()` | Хүнд ээлтэй sitemap хуудасны модтой |
| `sitemapXml()` | Хайлтын системүүдэд XML sitemap |
| `rss()` | RSS 2.0 feed (сүүлийн 20 мэдээ + 20 бүтээгдэхүүн) |

### TemplateController

**Файл:** `application/web/template/TemplateController.php`
**Extends:** `Raptor\Controller`

| Метод | Тайлбар |
|-------|---------|
| `webTemplate(string $template, array $vars): FileTemplate` | Web layout + content нэгтгэх. $vars доторх title, code, description, photo key-г index layout-ийн SEO meta-д автоматаар map хийнэ. Тохиргоо, навигаци, онцлох хуудсуудыг ачаална (cache). |

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

**Файл:** `application/raptor/content/ContentsRouter.php`

Контент модулийн бүх маршрутыг нэг дор бүртгэнэ. Files, News, Pages, References, Settings, Moedit AI.

---

## Shop

### ProductsModel

**Файл:** `application/dashboard/shop/ProductsModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `products`

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `slug` | varchar(255) | SEO-friendly URL slug |
| `title` | varchar(255) | Бүтээгдэхүүний нэр |
| `description` | text | Товч тайлбар |
| `content` | longtext | HTML контент |
| `price` | decimal(10,2) | Үнэ |
| `sale_price` | decimal(10,2) | Хямдралын үнэ |
| `sku` | varchar(50) | SKU код |
| `barcode` | varchar(50) | Баркод |
| `sizes` | varchar(255) | Хэмжээнүүд |
| `colors` | varchar(255) | Өнгөнүүд |
| `stock` | int | Нөөцийн тоо |
| `link` | varchar(255) | Гадаад холбоос |
| `photo` | varchar(255) | Нүүр зураг |
| `code` | varchar(6) | Хэлний код |
| `type` | varchar(50) | Бүтээгдэхүүний төрөл |
| `category` | varchar(50) | Ангилал |
| `is_featured` | tinyint | Онцлох бүтээгдэхүүн |
| `comment` | tinyint | Сэтгэгдэл идэвхтэй |
| `read_count` | int | Үзэлтийн тоо |
| `published` | tinyint | Нийтлэгдсэн эсэх |
| `published_at` | datetime | Нийтлэгдсэн огноо |
| `published_by` | bigint | Нийтлэсэн хэрэглэгч |
| `created_at` | datetime | Үүсгэсэн огноо |
| `created_by` | bigint | Үүсгэсэн хэрэглэгч |
| `updated_at` | datetime | Шинэчилсэн огноо |
| `updated_by` | bigint | Шинэчилсэн хэрэглэгч |

#### `generateSlug(string $title): string`
SEO-friendly slug үүсгэх. Монгол кирилл транслитераци дэмждэг.

#### `getExcerpt(string $content, int $length = 150): string`
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
| `/dashboard/products/view/{uint:id}` | GET | - |
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
| `/dashboard/orders/view/{uint:id}` | GET | - |
| `/dashboard/orders/{uint:id}/status` | PATCH | `order-status` |
| `/dashboard/orders/delete` | DELETE | `order-delete` |

---

## Notification

### DiscordListener

**Файл:** `application/raptor/notification/DiscordListener.php`

PSR-14 event listener - Discord webhook мэдэгдэл илгээнэ. Өмнөх `DiscordNotifier` шууд дуудлагын загварыг орлосон. `ListenerProvider`-ээр бүртгэгддэг.

#### `__construct(string $webhookUrl)`
`RAPTOR_DISCORD_WEBHOOK_URL` орчны хувьсагчаас Discord webhook URL авна.

#### `onContentEvent(ContentEvent $event): void`
Контентийн үйлдлүүдийг боловсруулна (нэмэх, засах, устгах, нийтлэх) - Мэдээ, Хуудас, Бүтээгдэхүүн гэх мэт.

#### `onUserEvent(UserEvent $event): void`
Хэрэглэгчтэй холбоотой event-үүдийг боловсруулна (бүртгүүлэх, зөвшөөрөх).

#### `onOrderEvent(OrderEvent $event): void`
Захиалгын event-үүдийг боловсруулна (шинэ захиалга, статус өөрчлөлт, үнэлгээ).

#### `onDevRequestEvent(DevRequestEvent $event): void`
Хөгжүүлэлтийн хүсэлтийн event-үүдийг боловсруулна (шинэ хүсэлт, шинэ хариулт).

#### Өнгөний тогтмолууд

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

**Файл:** `application/raptor/development/DevelopmentRouter.php`

| Маршрут | Метод | Нэр | Тайлбар |
|---------|-------|-----|---------|
| `/dashboard/dev-requests` | GET | `dev-requests` | Хүсэлтийн жагсаалт |
| `/dashboard/dev-requests/list` | GET | `dev-requests-list` | JSON жагсаалт |
| `/dashboard/dev-requests/create` | GET | `dev-requests-create` | Хүсэлт үүсгэх форм |
| `/dashboard/dev-requests/store` | POST | `dev-requests-store` | Хүсэлт илгээх |
| `/dashboard/dev-requests/view/{uint:id}` | GET | `dev-requests-view` | Хүсэлт харах |
| `/dashboard/dev-requests/respond` | POST | `dev-requests-respond` | Хариулт нэмэх |
| `/dashboard/dev-requests/delete` | DELETE | `dev-requests-delete` | Устгах (Хогийн савд нөөцлөнө) |

`development:development` RBAC эрхээр хамгаалагдсан.

---

## Migration

### MigrationRunner

**Файл:** `application/raptor/migration/MigrationRunner.php`

SQL файл суурьтай, зөвхөн урагшлах (forward-only) migration хөдөлгүүр.

| Метод | Тайлбар |
|-------|---------|
| `__construct(\PDO $pdo, string $migrationsPath)` | PDO + migrations хавтасны зам |
| `hasPending(): bool` | Хүлээгдэж буй migration байгаа эсэхийг шалгах |
| `migrate(): array` | Бүх pending SQL файлыг ажиллуулж, migrate хийсэн файлуудын жагсаалт буцаах |
| `status(): array` | `['ran' => [...], 'pending' => [...]]` буцаах |
| `parseFile(string $path): array` | SQL файлыг задлан `['up' => string, 'down' => string]` буцаах |

### MigrationMiddleware

**Файл:** `application/raptor/migration/MigrationMiddleware.php`
**Implements:** `MiddlewareInterface`

Хүсэлт бүрт pending migration-г автоматаар ажиллуулна. Advisory lock (`GET_LOCK`) ашиглан зэрэгцээ ажиллахаас хамгаална. Алдаа гарвал лог бичих боловч хүсэлтийг блоклохгүй.

### MigrationController

**Файл:** `application/raptor/migration/MigrationController.php`
**Extends:** `Raptor\Controller`

| Метод | Тайлбар |
|-------|---------|
| `index()` | Migration dashboard хуудас (зөвхөн system_coder) |
| `status()` | JSON: ажилласан + хүлээгдэж буй migration жагсаалт |
| `view()` | AJAX modal: SQL файлын агуулга харуулах |

### MigrationRouter

**Файл:** `application/raptor/migration/MigrationRouter.php`

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/migrations` | GET | `migrations` |
| `/dashboard/migrations/status` | GET | `migrations-status` |
| `/dashboard/migrations/view` | GET | `migrations-view` |

---

## Cache

### CacheService

**Файл:** `application/raptor/CacheService.php`
**Namespace:** `Raptor`

Custom file-based DB cache (PSR-16 SimpleCache). Гадаад dependency-гүй, зөвхөн `psr/simple-cache` interface ашиглана. `private/cache/` дотор хадгалагдана. `ContainerMiddleware`-д `cache` service бүртгэгдсэн. TTL: 12 цаг (нөөц хамгаалалт). Cache байхгүй бол систем DB-ээс шууд уншина.

| Method | Тайлбар |
|--------|---------|
| `__construct(string $cacheDir, int $defaultTtl = 3600)` | Cache directory болон TTL тохируулна |
| `get(string $key, mixed $default = null): mixed` | Cache-ээс утга авна, байхгүй бол default |
| `set(string $key, mixed $value, ?int $ttl = null): bool` | Утга хадгална |
| `delete(string $key): bool` | Cache устгана |
| `clear(): bool` | Бүх cache цэвэрлэнэ |

### Cache-лэгдсэн өгөгдөл

| Key | Хаана ачаалагддаг | Хэзээ устгагддаг |
|-----|-------------------|-----------------|
| `languages` | LocalizationMiddleware | LanguageController |
| `texts.{code}` | LocalizationMiddleware | TextController, LanguageController |
| `settings.{code}` | SettingsMiddleware | SettingsController |
| `menu.{code}` | DashboardTrait | TemplateController (цэс CRUD) |
| `rbac.{userId}` | JWTAuthMiddleware | RBACController (`clear()`) |
| `pages_nav.{code}` | Web TemplateController | PagesController |
| `featured_pages.{code}` | Web TemplateController | PagesController |
| `recent_news.{code}` | HomeController | NewsController |
| `reference.{code}` | (бэлтгэсэн) | ReferencesController |

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

**Файл:** `application/raptor/rbac/PermissionsSeed.php`

`system_` угтвартай 18+ системийн эрх: `logger`, `rbac`, `user_*` (5), `organization_*` (4), `content_*` (6), `product_*` (5), `localization_*` (4), `templates_index`, `development`.

### RolePermissionSeed

**Файл:** `application/raptor/rbac/RolePermissionSeed.php`

Анхдагч role-ууд үүсгэж эрх оноох:

| Role | Хамрах хүрээ |
|------|-------------|
| `coder` | Super admin - бүх шалгалтыг алгасна |
| `admin` | Бүх эрхтэй (development-с бусад) |
| `manager` | Хэрэглэгч, байгууллага, контент, бүтээгдэхүүн, хэл, хөгжүүлэлт |
| `editor` | Контент, бүтээгдэхүүн (index/insert/update/publish) |
| `viewer` | Контент, бүтээгдэхүүн (зөвхөн index) |

### MenuSeed

**Файл:** `application/raptor/template/MenuSeed.php`

Dashboard sidebar цэсний бүтэц 3 хэсэгтэй (MN/EN):
- **Contents** - Мессеж, Хуудас, Мэдээ, Файл, Хэл, Лавлагаа, Тохиргоо
- **Shop** - Бүтээгдэхүүн, Захиалга
- **System** - Хэрэглэгч, Байгууллага, Протокол, Хөгжүүлэлт, Гарын авлага, Migration, Цэс удирдлага

Цэс бүр `permission` хамгаалалт, `position` дэс дараалалтай.

### TextInitial

**Файл:** `application/raptor/localization/text/TextInitial.php`

100+ системийн орчуулгын keyword MN/EN хос хэлбэрээр (жнь: `accept`, `cancel`, `delete`, `dashboard`, `error`, `success`). Цагаан толгойн дарааллаар, `sys-defined` төрөлтэй.

### ReferenceInitial

**Файл:** `application/raptor/content/reference/ReferenceInitial.php`

`reference_templates` хүснэгтэд и-мэйл загвар, хууль эрхзүйн контент:
- И-мэйл загварууд: `forgotten-password-reset`, `request-new-user`, `approve-new-user`, `dev-request-new`, `dev-request-response`, `contact-message-notify`, `order-status-update`, `order-confirmation`, `order-notify`, `comment-notify`, `review-notify`
- Хууль: `tos` (Үйлчилгээний нөхцөл), `pp` (Нууцлалын бодлого)

### Жишиг дата классууд

Жишиг дата зөвхөн суурь модулиудад байдаг. Шинэ суулгалтад ажиллаж, dashboard-ийн "Reset" товчоор устгаж болно.

| Класс | Файл | Дата |
|-------|------|------|
| `NewsSamples` | `raptor/content/news/NewsSamples.php` | 6 мэдээ (3 MN + 3 EN), 3 төрөлтэй |
| `PagesSamples` | `raptor/content/page/PagesSamples.php` | 14+ шатлалтай хуудас (MN + EN) |
| `ProductsSamples` | `dashboard/shop/ProductsSamples.php` | 4 бүтээгдэхүүн (2 MN + 2 EN) |

---

## Trash (Хогийн сав)

### TrashModel

**Файл:** `application/raptor/trash/TrashModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `trash`

Бүрмөсөн устгасны дараа бичлэгүүдийг JSON хэлбэрээр хадгална. `deleteById()` амжилттай дууссан тохиолдолд л trash-д хадгална.

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `table_name` | varchar(128) | Эх хүснэгтийн нэр |
| `log_table` | varchar(64) | "Restored" мөр бичих log channel-ийн нэр (жнь: `products`, `news`, `content`) |
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

**Файл:** `application/raptor/trash/TrashController.php`
**Extends:** `Raptor\Controller`

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

#### Restore алгоритм

1. **UNIQUE pre-flight** - schema-аас (MySQL: `information_schema.STATISTICS`, PostgreSQL: `pg_index`) UNIQUE баганаудыг олж тус бүрд live хүснэгтэд давхцал байгаа эсэхийг шалгана. Давхцалтай бол алдаа буцаах (slug, keyword, code, sku гэх мэт талбарыг тодруулж).
2. **Original ID-аар оролдох** - FK холбоосыг (`comments.news_id` гэх мэт) хадгалахын тулд эхлээд анхны ID-аар insert хийнэ.
3. **Auto-increment fallback** - `SQLSTATE 23000` (PRIMARY KEY conflict - UNIQUE-ийг pre-flight-аар үнэлсэн тул зөвхөн ID conflict ирнэ) гарвал ID-г хасч, DB-д шинэ ID олгуулна. Хүүхэд бичлэгүүдийн (comments гэх мэт) FK-г гар аргаар шинэчлэх шаардлагатай гэдгийг хариунд анхааруулга бичнэ.
4. **LocalizedModel content** - snapshot-д `localized` массив байвал `{primary}_content` хүснэгтэд шинэ `parent_id`-аар тус хэлийн мөр бүрийг insert хийнэ.
5. **Хоёр давхар аудит лог** - `trash_log`-д (`action='trash-restore'`, `restored_by`, `restored_at`, `original_id`, `new_id`, `used_original_id`) БА `log_table` баганаас уншсан channel-д (`action='restore'`, `record_id=<new_id>`) бичнэ. Энэ нь сэргээгдсэн бичлэгийн харах/засах хуудсан дээр Logger Protocol-аар "restored" мөр харагдахын тулд.

### TrashRouter

**Файл:** `application/raptor/trash/TrashRouter.php`

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
| **Бүрмөсөн устгах + Хогийн сав** | News, Pages, Products, Orders, Reviews, Comments, Messages, Files, References, Settings, DevRequests, DevResponses, Menus, Texts, Languages | `deleteById()` дараа `TrashModel::store()` |
| **Soft delete** (is_active=0) | Users, Organizations | `deactivateById()` |

`deactivate()` -> `delete()` болж өөрчлөгдсөн Controller-ууд:
- `NewsController` (маршрут: `/dashboard/news/delete`)
- `PagesController` (маршрут: `/dashboard/pages/delete`)
- `ProductsController` (маршрут: `/dashboard/products/delete`)
- `OrdersController` (маршрут: `/dashboard/orders/delete`)
- `ReviewsController` (маршрут: `/dashboard/reviews/delete`)
- `CommentsController` (маршрут: `/dashboard/news/comments/delete`)
- `MessagesController` (маршрут: `/dashboard/messages/delete`)
- `FilesController` (маршрут: `/dashboard/files/{table}/delete`)
- `ReferencesController` (маршрут: `/dashboard/references/delete`)
- `DevRequestController` (маршрут: `/dashboard/dev-requests/delete`)

---

## Event систем

### EventDispatcher

**Файл:** `application/raptor/event/EventDispatcher.php`
**Implements:** `Psr\EventDispatcher\EventDispatcherInterface`

PSR-14 стандартын event dispatcher. `ListenerProvider`-аас listener-үүдийг авч event объектоор дуудна.

#### `__construct(ListenerProvider $provider)`
`ListenerProvider` instance авна.

#### `dispatch(object $event): object`
Event-г бүртгэгдсэн бүх listener-үүдэд дамжуулна.

### ListenerProvider

**Файл:** `application/raptor/event/ListenerProvider.php`
**Implements:** `Psr\EventDispatcher\ListenerProviderInterface`

Event төрөл бүрд listener бүртгэж хангана.

#### `addListener(string $eventClass, callable $listener): void`
Тодорхой event класст listener бүртгэнэ.

#### `getListenersForEvent(object $event): iterable`
Өгөгдсөн event-ийн классад бүртгэгдсэн бүх listener-үүдийг буцаана.

### ContentEvent

**Файл:** `application/raptor/event/ContentEvent.php`

Контентийн үйлдлүүдэд дамжуулагддаг event.

| Property | Төрөл | Тайлбар |
|----------|-------|---------|
| `$type` | string | Контентийн төрөл (`'news'`, `'page'`, `'product'` гэх мэт) |
| `$action` | string | Хийсэн үйлдэл (`'insert'`, `'update'`, `'delete'`, `'publish'`) |
| `$title` | string | Контентийн гарчиг |
| `$id` | int | Контент бичлэгийн ID |
| `$user` | string | Үйлдэл хийсэн хэрэглэгч |

### UserEvent

**Файл:** `application/raptor/event/UserEvent.php`

Хэрэглэгчтэй холбоотой event.

| Property | Төрөл | Тайлбар |
|----------|-------|---------|
| `$action` | string | Үйлдэл (`'signup_request'`, `'approved'`) |
| `$username` | string | Хэрэглэгчийн нэр |
| `$email` | string | И-мэйл хаяг |

### OrderEvent

**Файл:** `application/raptor/event/OrderEvent.php`

Захиалгатай холбоотой event.

| Property | Төрөл | Тайлбар |
|----------|-------|---------|
| `$action` | string | Үйлдэл (`'new'`, `'status_changed'`, `'review'`) |
| `$orderId` | ?int | Захиалгын ID |
| `$customer` | string | Захиалагчийн нэр |
| `$email` | ?string | Захиалагчийн и-мэйл |
| `$product` | ?string | Бүтээгдэхүүний нэр |
| `$quantity` | ?int | Тоо ширхэг |
| `$oldStatus` | ?string | Өмнөх статус (статус өөрчлөлтөд) |
| `$newStatus` | ?string | Шинэ статус (статус өөрчлөлтөд) |

### DevRequestEvent

**Файл:** `application/raptor/event/DevRequestEvent.php`

Хөгжүүлэлтийн хүсэлтийн event.

| Property | Төрөл | Тайлбар |
|----------|-------|---------|
| `$action` | string | Үйлдэл (`'new_request'`, `'new_response'`) |
| `$title` | string | Хүсэлтийн гарчиг |
| `$id` | int | Хүсэлтийн ID |

### Controller дотор хэрэглэх

```php
// Мэдээ нэмсний дараа content event дамжуулах
$this->dispatch(new ContentEvent(
    'insert', 'news', $record['title'], $id
));
```
