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
9. [Content - Pages](#content--pages)
10. [Content - References](#content--references)
11. [Content - Settings](#content--settings)
12. [Localization](#localization)
13. [Log](#log)
14. [Mail](#mail)
15. [Database Middleware](#database-middleware)
16. [Web Layer](#web-layer)
17. [Shop](#shop)
18. [Notification](#notification)
19. [Development](#development)

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

#### `twigTemplate(string $template, array $vars = []): TwigTemplate`
Twig template үүсгэх. Автоматаар `user`, `index`, `localization`, `request` хувьсагчид нэмэгдэнэ. `text` болон `link` filter-ууд бүртгэгдэнэ.

#### `respondJSON(array $response, int|string $code = 0): void`
JSON хариулт хэвлэх. `Content-Type: application/json` header тохируулна.

#### `redirectTo(string $routeName, array $params = []): void`
Route нэрээр 302 redirect хийх. `exit` дуудна.

#### `log(string $table, string $level, string $message, array $context = []): void`
Мэдээллийн баазийн `{$table}_log` нэртэй хүснэгт рүү системийн лог бичих. Server request metadata болон хэрэглэгчийн мэдээлэл автоматаар нэмэгдэнэ.

#### `generateRouteLink(string $routeName, array $params = [], bool $is_absolute = false, string $default = '#'): string`
Route нэрээр URL үүсгэх.

#### `getContainer(): ?ContainerInterface`
DI Container авах.

#### `getService(string $id): mixed`
Container-аас service авах.

#### `headerResponseCode(int|string $code): void`
HTTP response code тохируулах. Стандарт бус код бол алгасна.

#### `getScriptPath(): string`
Script path буцаах (subdirectory дэмжлэг).

#### `getDocumentRoot(): string`
Document root зам буцаах.

---

## Raptor\Application

**Файл:** `application/raptor/Application.php`
**Extends:** `codesaur\Http\Application\Application`

Dashboard Application-ийн суурь. Middleware pipeline болон Router-уудыг бүртгэнэ.

### Constructor Pipeline

1. `ErrorHandler` - Алдаа барих
2. `MySQLConnectMiddleware` - DB холболт
3. `SessionMiddleware` - Session удирдлага
4. `JWTAuthMiddleware` - JWT баталгаажуулалт
5. `ContainerMiddleware` - DI Container
6. `LocalizationMiddleware` - Олон хэл
7. `SettingsMiddleware` - Тохиргоо
8. `LoginRouter`, `UsersRouter`, `OrganizationRouter`, `RBACRouter`, `LocalizationRouter`, `ContentsRouter`, `LogsRouter`, `TemplateRouter`, `ProductsRouter`, `OrdersRouter`, `DevelopmentRouter`

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

### SessionMiddleware (Dashboard)

**Файл:** `application/raptor/authentication/SessionMiddleware.php`
**Implements:** `MiddlewareInterface`

PHP session эхлүүлэх (`session_start()`).

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
| `is_active` | tinyint | Идэвхтэй эсэх |
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
| `deactivate(string $table)` | Soft delete |

### ContentsRouter - Files маршрутууд

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/files` | GET | `files` |
| `/dashboard/files/list/{table}` | GET | `files-list` |
| `/dashboard/files/upload` | POST | `files-upload` |
| `/dashboard/files/post/{table}` | POST | `files-post` |
| `/dashboard/files/modal/{table}` | GET | `files-modal` |
| `/dashboard/files/{table}/{uint:id}` | PUT | `files-update` |
| `/dashboard/files/{table}/deactivate` | DELETE | `files-deactivate` |
| `/dashboard/private/file` | GET | `private-files-read` |

---

## Content - News

### NewsModel

**Файл:** `application/raptor/content/news/NewsModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `news`

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `code` | varchar(6) | Хэлний код |
| `title` | varchar(255) | Гарчиг |
| `content` | longtext | HTML контент |
| `photo` | varchar(255) | Нүүр зураг |
| `published` | tinyint | Нийтлэгдсэн эсэх |
| `published_at` | datetime | Нийтлэгдсэн огноо |
| `read_count` | int | Үзэлтийн тоо |
| `is_active` | tinyint | Идэвхтэй эсэх |
| `created_at` | datetime | Үүсгэсэн огноо |
| `created_by` | bigint | Үүсгэсэн хэрэглэгч |
| `updated_at` | datetime | Шинэчилсэн огноо |
| `updated_by` | bigint | Шинэчилсэн хэрэглэгч |

### ContentsRouter - News маршрутууд

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/news` | GET | `news` |
| `/dashboard/news/list` | GET | `news-list` |
| `/dashboard/news/insert` | GET+POST | `news-insert` |
| `/dashboard/news/{uint:id}` | GET+PUT | `news-update` |
| `/dashboard/news/read/{slug}` | GET | `news-read` |
| `/dashboard/news/view/{uint:id}` | GET | `news-view` |
| `/dashboard/news/deactivate` | DELETE | `news-deactivate` |

---

## Content - Pages

### PagesModel

**Файл:** `application/raptor/content/page/PagesModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `pages`

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `parent_id` | bigint | Эцэг хуудасны ID |
| `code` | varchar(6) | Хэлний код |
| `type` | varchar(50) | Хуудасны төрөл |
| `title` | varchar(255) | Гарчиг |
| `content` | longtext | HTML контент |
| `photo` | varchar(255) | Нүүр зураг |
| `link` | varchar(255) | Гадаад холбоос |
| `position` | int | Эрэмбэ |
| `published` | tinyint | Нийтлэгдсэн эсэх |
| `published_at` | datetime | Нийтлэгдсэн огноо |
| `is_featured` | tinyint | Онцлох хуудас |
| `read_count` | int | Үзэлтийн тоо |
| `is_active` | tinyint | Идэвхтэй эсэх |
| `created_at` | datetime | Үүсгэсэн огноо |
| `created_by` | bigint | Үүсгэсэн хэрэглэгч |
| `updated_at` | datetime | Шинэчилсэн огноо |
| `updated_by` | bigint | Шинэчилсэн хэрэглэгч |

#### `generateSlug(string $title): string`
Гарчгаас SEO-friendly slug үүсгэх.

#### `getBySlug(string $slug): array`
Slug-аар хуудас хайх.

#### `getExcerpt(string $content, int $length = 150): string`
HTML контентоос товч хураангуй гаргах.

### ContentsRouter - Pages маршрутууд

| Маршрут | Метод | Нэр |
|---------|-------|-----|
| `/dashboard/pages` | GET | `pages` |
| `/dashboard/pages/list` | GET | `pages-list` |
| `/dashboard/pages/insert` | GET+POST | `page-insert` |
| `/dashboard/pages/{uint:id}` | GET+PUT | `page-update` |
| `/dashboard/pages/read/{slug}` | GET | `page-read` |
| `/dashboard/pages/view/{uint:id}` | GET | `page-view` |
| `/dashboard/pages/deactivate` | DELETE | `page-deactivate` |

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
| `/dashboard/references/deactivate` | DELETE | `reference-deactivate` |

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

Request attribute-д `localization` массив inject хийнэ:

```php
[
    'code'     => 'mn',           // Идэвхтэй хэлний код
    'language' => [...],          // Бүх хэлний жагсаалт
    'text'     => ['key' => 'value', ...]  // Орчуулгын текстүүд
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
| `/dashboard/logs/{table}` | GET | `logs-list` |
| `/dashboard/logs/view/{table}/{uint:id}` | GET | `log-view` |

---

## Mail

### Mailer

**Файл:** `application/raptor/mail/Mailer.php`

И-мэйл илгээх. Brevo (SendInBlue) API ашиглана.

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

PSR-11 DI Container-г request-д inject хийнэ. PDO, User ID, `DiscordNotifier` сервисийг container-д бүртгэнэ.

---

## Web Layer

### Web\Application

**Файл:** `application/web/Application.php`
**Extends:** `codesaur\Http\Application\Application`

Public вэб сайтын Application. Middleware pipeline:
ExceptionHandler -> MySQL -> Container -> Session -> Localization -> Settings -> HomeRouter

### HomeRouter

**Файл:** `application/web/home/HomeRouter.php`

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
| `/sitemap.xml` | GET | `sitemap-xml` | XML sitemap |
| `/rss` | GET | `rss` | RSS feed |

### HomeController

**Файл:** `application/web/home/HomeController.php`
**Extends:** `TemplateController`

| Метод | Тайлбар |
|-------|---------|
| `index()` | Нүүр хуудас (сүүлийн мэдээнүүд) |
| `language(string $code)` | Хэл солих + redirect |

### PageController

**Файл:** `application/web/home/PageController.php`
**Extends:** `TemplateController`

| Метод | Тайлбар |
|-------|---------|
| `contact()` | Холбоо барих хуудас (link LIKE '%/contact') |
| `pageById(int $id)` | ID-р хуудас slug URL руу redirect |
| `page(string $slug)` | Хуудас үзүүлэх + файлууд + read_count + OG meta |

### NewsController (Web)

**Файл:** `application/web/home/NewsController.php`
**Extends:** `TemplateController`

| Метод | Тайлбар |
|-------|---------|
| `newsById(int $id)` | ID-р мэдээ slug URL руу redirect |
| `news(string $slug)` | Мэдээ үзүүлэх + файлууд + read_count + OG meta |
| `newsType(string $type)` | Төрлөөр мэдээний жагсаалт |
| `archive()` | Мэдээний архив, жил/сар шүүлтүүртэй |

### ShopController

**Файл:** `application/web/home/ShopController.php`
**Extends:** `TemplateController`

| Метод | Тайлбар |
|-------|---------|
| `products()` | Нийтлэгдсэн бүтээгдэхүүнүүдийн жагсаалт |
| `productById(int $id)` | ID-р бүтээгдэхүүн slug URL руу redirect |
| `product(string $slug)` | Бүтээгдэхүүн үзүүлэх + файлууд + read_count + OG meta |
| `order()` | Захиалгын форм харуулах |
| `orderSubmit()` | Захиалга боловсруулах (спам шалгах, validate, DB, и-мэйл, Discord) |

### SeoController

**Файл:** `application/web/home/SeoController.php`
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
| `template(string $template, array $vars): TwigTemplate` | Web layout + content нэгтгэх |
| `getMainMenu(string $code): array` | Олон түвшний main menu үүсгэх |
| `getFeaturedPages(string $code): array` | Онцлох хуудсуудын жагсаалт |

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
| `is_active` | tinyint | Идэвхтэй эсэх |
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

### OrdersModel

**Файл:** `application/dashboard/shop/OrdersModel.php`
**Extends:** `codesaur\DataObject\Model`

**Хүснэгт:** `orders`

| Багана | Төрөл | Тайлбар |
|--------|-------|---------|
| `id` | bigint (PK) | Auto-increment |
| `product_id` | bigint | Бүтээгдэхүүний холбоос |
| `product_title` | varchar(255) | Бүтээгдэхүүний нэр (хуулбар) |
| `customer_name` | varchar(255) | Захиалагчийн нэр |
| `customer_email` | varchar(100) | Захиалагчийн и-мэйл |
| `customer_phone` | varchar(50) | Захиалагчийн утас |
| `message` | text | Захиалагчийн мессеж |
| `quantity` | int | Тоо ширхэг |
| `code` | varchar(6) | Хэлний код |
| `status` | varchar(50) | Захиалгын статус |
| `is_active` | tinyint | Идэвхтэй эсэх |
| `created_at` | datetime | Үүсгэсэн огноо |
| `created_by` | bigint | Үүсгэсэн хэрэглэгч |
| `updated_at` | datetime | Шинэчилсэн огноо |
| `updated_by` | bigint | Шинэчилсэн хэрэглэгч |

### ProductsRouter

**Файл:** `application/dashboard/shop/ProductsRouter.php`

Dashboard бүтээгдэхүүний удирдлагын маршрутууд.

### OrdersRouter

**Файл:** `application/dashboard/shop/OrdersRouter.php`

Dashboard захиалгын удирдлагын маршрутууд.

---

## Notification

### DiscordNotifier

**Файл:** `application/raptor/notification/DiscordNotifier.php`

Discord webhook интеграцийн сервис. DI Container-д `discord` нэрээр бүртгэгддэг.

#### `send(string $title, string $description, int $color, array $fields = []): void`
Ерөнхий Discord embed мессеж илгээх.

#### `userSignupRequest(string $username, string $email): void`
Шинэ хэрэглэгч бүртгүүлсэн мэдэгдэл.

#### `userApproved(string $username, string $email, string $admin): void`
Админ хэрэглэгчийг зөвшөөрсөн мэдэгдэл.

#### `newOrder(int $orderId, string $customer, string $email, string $product, int $quantity): void`
Шинэ захиалгын мэдэгдэл.

#### `orderStatusChanged(int $orderId, string $customer, string $oldStatus, string $newStatus, string $admin): void`
Захиалгын статус өөрчлөгдсөн мэдэгдэл.

#### `contentAction(string $type, string $action, string $title, int $id, string $admin): void`
Контентийн үйлдлийн мэдэгдэл (insert, update, delete, publish).

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

| Маршрут | Метод | Тайлбар |
|---------|-------|---------|
| `/dashboard/dev-requests/*` | CRUD | Хөгжүүлэлтийн хүсэлт хянах |
| `/dashboard/sql-terminal/*` | GET+POST | SQL query интерфэйс |
| `/dashboard/error-log/*` | GET | Алдааны лог үзэх |
| `/dashboard/file-manager/*` | GET | Файл менежер |

Бүх маршрут `development:development` RBAC эрхээр хамгаалагдсан.
