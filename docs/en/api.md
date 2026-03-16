# Raptor API Reference (EN)

> Detailed reference for all modules, classes, and methods.

---

## Table of Contents

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
20. [Migration](#migration)

---

## Raptor\Controller

**File:** `application/raptor/Controller.php`
**Extends:** `codesaur\Http\Application\Controller`
**Uses:** `codesaur\DataObject\PDOTrait`

Base class for all controllers.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `$pdo` | `\PDO` | Database connection (via PDOTrait) |

### Methods

#### `__construct(ServerRequestInterface $request)`
Extracts PDO instance from request and assigns to `$this->pdo`.

#### `getUser(): ?User`
Returns the authenticated `User` object, or `null` if not logged in.

#### `getUserId(): ?int`
Returns the authenticated user's ID, or `null` if not logged in.

#### `isUserAuthorized(): bool`
Returns whether a user is authenticated.

#### `isUser(string $role): bool`
Checks if the user has a specific RBAC role.

#### `isUserCan(string $permission): bool`
Checks if the user has a specific RBAC permission.

#### `getLanguageCode(): string`
Returns the active language code (`'mn'`, `'en'`, etc.). Returns `''` if not set.

#### `getLanguages(): array`
Returns all registered languages.

#### `text(string $key, mixed $default = null): string`
Returns translation text. Returns `$default` or `{key}` if not found.

#### `twigTemplate(string $template, array $vars = []): TwigTemplate`
Creates a Twig template with auto-injected variables: `user`, `index`, `localization`, `request`. Registers `text` and `link` filters.

#### `respondJSON(array $response, int|string $code = 0): void`
Outputs a JSON response with `Content-Type: application/json` header.

#### `redirectTo(string $routeName, array $params = []): void`
Redirects to a named route (302). Calls `exit`.

#### `log(string $table, string $level, string $message, array $context = []): void`
Writes a system log entry to the `{$table}_log` database table. Server request metadata and user info are auto-appended.

#### `generateRouteLink(string $routeName, array $params = [], bool $is_absolute = false, string $default = '#'): string`
Generates a URL from a route name.

#### `getContainer(): ?ContainerInterface`
Returns the DI Container.

#### `getService(string $id): mixed`
Gets a service from the container.

#### `headerResponseCode(int|string $code): void`
Sets HTTP response code. Ignores non-standard codes.

#### `getScriptPath(): string`
Returns the script path (subdirectory support).

#### `getDocumentRoot(): string`
Returns the document root path.

---

## Raptor\Application

**File:** `application/raptor/Application.php`
**Extends:** `codesaur\Http\Application\Application`

Base for the Dashboard Application. Registers the middleware pipeline and routers.

### Constructor Pipeline

1. `ErrorHandler` - Error handling
2. `MySQLConnectMiddleware` - DB connection
3. `MigrationMiddleware` - Auto-run pending migrations
4. `SessionMiddleware` - Session management
5. `JWTAuthMiddleware` - JWT authentication
6. `ContainerMiddleware` - DI Container
7. `LocalizationMiddleware` - Multi-language
8. `SettingsMiddleware` - System settings
9. `LoginRouter`, `UsersRouter`, `OrganizationRouter`, `RBACRouter`, `LocalizationRouter`, `ContentsRouter`, `LogsRouter`, `TemplateRouter`, `ProductsRouter`, `OrdersRouter`, `DevelopmentRouter`, `MigrationRouter`

---

## Authentication

### JWTAuthMiddleware

**File:** `application/raptor/authentication/JWTAuthMiddleware.php`
**Implements:** `MiddlewareInterface`

#### `generate(array $data): string`
Generates a JWT token. Payload includes `iat`, `exp`, `seconds` + `$data`.

#### `validate(string $jwt): array`
Decodes and validates JWT. Throws `RuntimeException` if expired. Requires `user_id` and `organization_id`.

#### `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`
1. Reads `$_SESSION['RAPTOR_JWT']`
2. Validates JWT
3. Fetches user profile from DB
4. Verifies organization membership
5. Loads RBAC permissions
6. Creates `User` object and adds to request attributes
7. On failure, redirects to `/dashboard/login`

### SessionMiddleware

**File:** `application/raptor/SessionMiddleware.php`
**Implements:** `MiddlewareInterface`

Shared middleware for both Dashboard and Web apps.
Starts PHP session and releases write-lock early on read-only routes.

Constructor accepts a `needsWrite` closure to define which routes need session writes:
- Dashboard: `fn($path, $method) => str_contains($path, '/login')`
- Web: `fn($path, $method) => str_starts_with($path, '/language/') || ...`

If closure is null, all routes are read-only (session_write_close on every request).

### LoginRouter

**File:** `application/raptor/authentication/LoginRouter.php`

| Route | Method | Name | Description |
|-------|--------|------|-------------|
| `/dashboard/login` | GET | `login` | Login page |
| `/dashboard/login/try` | POST | `entry` | Login attempt |
| `/dashboard/login/logout` | GET | `logout` | Logout |
| `/dashboard/login/forgot` | POST | `login-forgot` | Forgot password |
| `/dashboard/login/signup` | POST | `signup` | Sign up |
| `/dashboard/login/language/{code}` | GET | `language` | Switch language |
| `/dashboard/login/set/password` | POST | `login-set-password` | Set new password |
| `/dashboard/login/organization/{uint:id}` | GET | `login-select-organization` | Select organization |

### User (Value Object)

**File:** `application/raptor/authentication/User.php`

| Property | Type | Description |
|----------|------|-------------|
| `$profile` | `array` | User profile data |
| `$organization` | `array` | Organization data |
| `$permissions` | `array` | RBAC permissions |

| Method | Description |
|--------|-------------|
| `is(string $role): bool` | Check role |
| `can(string $permission): bool` | Check permission |

---

## User

### UsersModel

**File:** `application/raptor/user/UsersModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `users`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `username` | varchar(50) | Login name |
| `email` | varchar(100) | Email address |
| `password` | varchar(255) | Bcrypt hash |
| `phone` | varchar(50) | Phone |
| `first_name` | varchar(50) | First name |
| `last_name` | varchar(50) | Last name |
| `photo` | varchar(255) | Avatar image |
| `is_active` | tinyint | Active status |
| `created_at` | datetime | Created date |
| `updated_at` | datetime | Updated date |

---

## Organization

### OrganizationModel

**File:** `application/raptor/organization/OrganizationModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `organizations`

### OrganizationUserModel

**File:** `application/raptor/organization/OrganizationUserModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `organizations_users`

User-organization relationship table.

---

## RBAC

### RBAC

**File:** `application/raptor/rbac/RBAC.php`

Loads all roles and permissions for a user and returns them via `jsonSerialize()`.

### Roles

**File:** `application/raptor/rbac/Roles.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `rbac_roles`

### Permissions

**File:** `application/raptor/rbac/Permissions.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `rbac_permissions`

### RolePermissions

**File:** `application/raptor/rbac/RolePermissions.php`

Role-Permission relationships.

### UserRole

**File:** `application/raptor/rbac/UserRole.php`

User-Role relationships.

---

## Content - Files

### FilesModel

**File:** `application/raptor/content/file/FilesModel.php`
**Extends:** `codesaur\DataObject\Model`

Stores file metadata. Table name is dynamic (`setTable()`).

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `record_id` | bigint | Related record ID |
| `file` | varchar(255) | Original filename |
| `path` | varchar(255) | Stored path |
| `size` | bigint | File size (bytes) |
| `type` | varchar(50) | File type (image, video, document...) |
| `mime_content_type` | varchar(100) | MIME type |
| `keyword` | varchar(255) | Keyword |
| `description` | text | Description |
| `is_active` | tinyint | Active status |
| `created_at` | datetime | Created date |
| `created_by` | bigint | Created by user |
| `updated_at` | datetime | Updated date |
| `updated_by` | bigint | Updated by user |

### FilesController

**File:** `application/raptor/content/file/FilesController.php`

| Method | Description |
|--------|-------------|
| `index()` | File management page |
| `list(string $table)` | JSON file list |
| `upload()` | Upload file (move only, no DB record) |
| `post(string $table)` | Upload + register in DB |
| `modal(string $table)` | File selection modal |
| `update(string $table, int $id)` | Update file metadata |
| `deactivate(string $table)` | Soft delete |

### ContentsRouter - File Routes

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/files` | GET | `files` |
| `/dashboard/files/list/{table}` | GET | `files-list` |
| `/dashboard/files/upload` | POST | `files-upload` |
| `/dashboard/files/post/{table}` | POST | `files-post` |
| `/dashboard/files/modal/{table}` | GET | `files-modal` |
| `/dashboard/files/{table}/{uint:id}` | PUT | `files-update` |
| `/dashboard/files/{table}/deactivate` | DELETE | `files-deactivate` |
| `/dashboard/private/file` | GET | - |

---

## Content - News

### NewsModel

**File:** `application/raptor/content/news/NewsModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `news`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `slug` | varchar(255, unique) | SEO-friendly URL slug |
| `title` | varchar(255) | Title |
| `description` | varchar(255) | Short description (auto-generated from content) |
| `content` | mediumtext | HTML content |
| `source` | varchar(255) | Source attribution |
| `photo` | varchar(255) | Cover image |
| `code` | varchar(2) | Language code |
| `type` | varchar(32, default: 'article') | News type |
| `category` | varchar(32, default: 'general') | Category |
| `is_featured` | tinyint (default: 0) | Featured news |
| `comment` | tinyint (default: 1) | Comments enabled |
| `read_count` | bigint (default: 0) | View count |
| `is_active` | tinyint (default: 1) | Active status |
| `published` | tinyint (default: 0) | Published status |
| `published_at` | datetime | Published date |
| `published_by` | bigint | Published by user (FK -> users) |
| `created_at` | datetime | Created date |
| `created_by` | bigint | Created by user (FK -> users) |
| `updated_at` | datetime | Updated date |
| `updated_by` | bigint | Updated by user (FK -> users) |

#### `generateSlug(string $title): string`
Generates an SEO-friendly slug. Supports Mongolian Cyrillic transliteration. Auto-appends number on duplicate.

#### `getBySlug(string $slug): array|null`
Finds a news article by its slug.

#### `getExcerpt(string $content, int $length = 200): string`
Extracts a plain-text excerpt from HTML content.

### ContentsRouter - News Routes

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/news` | GET | `news` |
| `/dashboard/news/list` | GET | `news-list` |
| `/dashboard/news/insert` | GET+POST | `news-insert` |
| `/dashboard/news/{uint:id}` | GET+PUT | `news-update` |
| `/dashboard/news/view/{uint:id}` | GET | - |
| `/dashboard/news/deactivate` | DELETE | `news-deactivate` |
| `/dashboard/news/reset` | DELETE | `news-sample-reset` |

---

## Content - Pages

### PagesModel

**File:** `application/raptor/content/page/PagesModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `pages`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `slug` | varchar(255, unique) | SEO-friendly URL slug |
| `parent_id` | bigint | Parent page ID |
| `title` | varchar(255) | Title |
| `description` | varchar(255) | Short description (auto-generated from content) |
| `content` | mediumtext | HTML content |
| `source` | varchar(255) | Source attribution |
| `photo` | varchar(255) | Cover image |
| `code` | varchar(2) | Language code |
| `type` | varchar(32, default: 'menu') | Page type |
| `category` | varchar(32, default: 'general') | Category |
| `position` | smallint (default: 100) | Sort order |
| `link` | varchar(255) | External link |
| `is_featured` | tinyint (default: 0) | Featured page |
| `comment` | tinyint (default: 0) | Comments enabled |
| `read_count` | bigint (default: 0) | View count |
| `is_active` | tinyint (default: 1) | Active status |
| `published` | tinyint (default: 0) | Published status |
| `published_at` | datetime | Published date |
| `published_by` | bigint | Published by user (FK -> users) |
| `created_at` | datetime | Created date |
| `created_by` | bigint | Created by user (FK -> users) |
| `updated_at` | datetime | Updated date |
| `updated_by` | bigint | Updated by user (FK -> users) |

#### `generateSlug(string $title): string`
Generates an SEO-friendly slug. Supports Mongolian Cyrillic transliteration. Auto-appends number on duplicate.

#### `getBySlug(string $slug): array|null`
Finds a page by its slug.

#### `getNavigation(string $code): array`
Returns tree-structured navigation for published pages where type matches `*-menu` pattern. Ordered by position, id.

#### `buildTree(array $pages, int $parentId = 0): array`
Recursively builds parent -> children -> submenu tree from flat page list.

#### `getFeaturedLeafPages(string $code): array`
Returns featured pages that have no children (leaf nodes only).

#### `getExcerpt(string $content, int $length = 200): string`
Extracts a plain-text excerpt from HTML content.

### ContentsRouter - Page Routes

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/pages` | GET | `pages` |
| `/dashboard/pages/table` | GET | `pages-table` |
| `/dashboard/pages/list` | GET | `pages-list` |
| `/dashboard/pages/insert` | GET+POST | `page-insert` |
| `/dashboard/pages/{uint:id}` | GET+PUT | `page-update` |
| `/dashboard/pages/view/{uint:id}` | GET | - |
| `/dashboard/pages/deactivate` | DELETE | `page-deactivate` |
| `/dashboard/pages/reset` | DELETE | `pages-sample-reset` |

---

## Content - References

### ReferencesModel

**File:** `application/raptor/content/reference/ReferencesModel.php`
**Extends:** `codesaur\DataObject\LocalizedModel`

Reference table with dynamic table name.

### ContentsRouter - Reference Routes

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/references` | GET | `references` |
| `/dashboard/references/{table}` | GET+POST | `reference-insert` |
| `/dashboard/references/{table}/{uint:id}` | GET+PUT | `reference-update` |
| `/dashboard/references/view/{table}/{uint:id}` | GET | `reference-view` |
| `/dashboard/references/deactivate` | DELETE | `reference-deactivate` |

---

## Content - Settings

### SettingsModel

**File:** `application/raptor/content/settings/SettingsModel.php`
**Extends:** `codesaur\DataObject\LocalizedModel`

**Table:** `raptor_settings`

#### Primary Columns

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `email` | varchar(70) | Contact email |
| `phone` | varchar(70) | Contact phone |
| `favicon` | varchar(255) | Favicon path |
| `apple_touch_icon` | varchar(255) | Apple icon path |
| `config` | text | JSON config |
| `is_active` | tinyint | Active status |

#### Content Columns (per language)

| Column | Type | Description |
|--------|------|-------------|
| `title` | varchar(70) | Site title |
| `logo` | varchar(255) | Logo |
| `description` | varchar(255) | SEO description |
| `urgent` | text | Urgent message |
| `contact` | text | Contact info |
| `address` | text | Address |
| `copyright` | varchar(255) | Copyright |

#### `retrieve(): array`
Gets the active (`is_active=1`) settings record. Returns `[]` if empty.

### SettingsMiddleware

**File:** `application/raptor/content/settings/SettingsMiddleware.php`
**Implements:** `MiddlewareInterface`

Reads settings from DB and injects into request attributes as `settings`.

### ContentsRouter - Settings Routes

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/settings` | GET | `settings` |
| `/dashboard/settings` | POST | - |
| `/dashboard/settings/files` | POST | `settings-files` |

---

## Localization

### LanguageModel

**File:** `application/raptor/localization/language/LanguageModel.php`
**Extends:** `codesaur\DataObject\Model`

Language registration table.

### TextModel

**File:** `application/raptor/localization/text/TextModel.php`
**Extends:** `codesaur\DataObject\Model`

Translation texts (key -> value).

#### `retrieve(array $languageCodes): array`
Returns all translations structured as language code -> key -> value.

### LocalizationMiddleware

**File:** `application/raptor/localization/LocalizationMiddleware.php`
**Implements:** `MiddlewareInterface`

Shared middleware for both Dashboard and Web apps. Constructor accepts session key:
- Dashboard: `new LocalizationMiddleware()` - defaults to `RAPTOR_LANGUAGE_CODE`
- Web: `new LocalizationMiddleware('WEB_LANGUAGE_CODE')`

Injects `localization` array into request attributes:

```php
[
    'code'        => 'mn',                    // Active language code
    'language'    => [...],                   // All languages list
    'text'        => ['key' => 'value', ...], // Translation texts
    'session_key' => 'RAPTOR_LANGUAGE_CODE'   // Session key for language storage
]
```

---

## Log

### Logger

**File:** `application/raptor/log/Logger.php`
**Extends:** `\Psr\Log\AbstractLogger`

PSR-3 standard logging system. Stores logs in database.

#### `setTable(string $table): void`
Sets the log table name.

#### `log(mixed $level, string|\Stringable $message, array $context = []): void`
Writes a log entry.

### LogsRouter Routes

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/logs` | GET | `logs` |
| `/dashboard/logs/view` | GET | `logs-view` |
| `/dashboard/logs/retrieve` | POST | `logs-retrieve` |
| `/dashboard/logs/error-log-read` | GET | `error-log-read` |

---

## Mail

### Mailer

**File:** `application/raptor/mail/Mailer.php`

Sends email via Brevo (SendInBlue) API.

---

## Database Middleware

### MySQLConnectMiddleware

**File:** `application/raptor/MySQLConnectMiddleware.php`

1. Reads DB config from ENV
2. Creates PDO connection to MySQL
3. Auto-creates database on localhost
4. Sets charset/collation
5. Injects `pdo` into request attributes

### PostgresConnectMiddleware

**File:** `application/raptor/PostgresConnectMiddleware.php`

PostgreSQL variant. DSN: `pgsql:host=...;dbname=...`

### ContainerMiddleware

**File:** `application/raptor/ContainerMiddleware.php`

Injects PSR-11 DI Container into request. Registers PDO, User ID, and `DiscordNotifier` service in the container.

---

## Web Layer

### Web\Application

**File:** `application/web/Application.php`
**Extends:** `codesaur\Http\Application\Application`

Public website Application. Middleware pipeline:
ExceptionHandler -> MySQL -> Container -> Session -> Localization -> Settings -> WebRouter

### WebRouter

**File:** `application/web/WebRouter.php`

| Route | Method | Name | Description |
|-------|--------|------|-------------|
| `/` | GET | `home` | Home page |
| `/home` | GET | - | Home alias |
| `/language/{code}` | GET | `language` | Switch language |
| `/page/{uint:id}` | GET | `page-by-id` | Page by ID (redirect to slug) |
| `/page/{slug}` | GET | `page` | View page |
| `/contact` | GET | `contact` | Contact page |
| `/news/{uint:id}` | GET | `news-by-id` | News by ID (redirect to slug) |
| `/news/{slug}` | GET | `news` | View news |
| `/news/type/{type}` | GET | `news-type` | News by type/category |
| `/archive` | GET | `archive` | News archive |
| `/products` | GET | `products` | Product listing |
| `/product/{uint:id}` | GET | `product-by-id` | Product by ID (redirect to slug) |
| `/product/{slug}` | GET | `product` | View product |
| `/order` | GET | `order` | Order form |
| `/order` | POST | `order-submit` | Submit order |
| `/search` | GET | `search` | Search |
| `/sitemap` | GET | `sitemap` | Sitemap page |
| `/sitemap.xml` | GET | - | XML sitemap |
| `/rss` | GET | `rss` | RSS feed |

### HomeController

**File:** `application/web/HomeController.php`
**Extends:** `TemplateController`

| Method | Description |
|--------|-------------|
| `index()` | Home page (latest news) |
| `language(string $code)` | Switch language + redirect |

### PageController

**File:** `application/web/content/PageController.php`
**Extends:** `TemplateController`

| Method | Description |
|--------|-------------|
| `contact()` | Contact page (link LIKE '%/contact') |
| `pageById(int $id)` | Redirect page by ID to slug URL |
| `page(string $slug)` | Display page + files + read_count + OG meta |

### NewsController (Web)

**File:** `application/web/content/NewsController.php`
**Extends:** `TemplateController`

| Method | Description |
|--------|-------------|
| `newsById(int $id)` | Redirect news by ID to slug URL |
| `news(string $slug)` | Display news + files + read_count + OG meta |
| `newsType(string $type)` | List news by type/category |
| `archive()` | News archive with year/month filtering |

### ShopController

**File:** `application/web/shop/ShopController.php`
**Extends:** `TemplateController`

| Method | Description |
|--------|-------------|
| `products()` | List all published products |
| `productById(int $id)` | Redirect product by ID to slug URL |
| `product(string $slug)` | Display product + files + read_count + OG meta |
| `order()` | Display order form |
| `orderSubmit()` | Process order (spam check, validate, DB insert, email, Discord) |

### SeoController

**File:** `application/web/service/SeoController.php`
**Extends:** `TemplateController`

| Method | Description |
|--------|-------------|
| `search()` | Search across pages, news, products (min 2 chars) |
| `sitemap()` | Human-readable sitemap with page tree |
| `sitemapXml()` | XML sitemap for search engines |
| `rss()` | RSS 2.0 feed (latest 20 news + 20 products) |

### TemplateController

**File:** `application/web/template/TemplateController.php`
**Extends:** `Raptor\Controller`

| Method | Description |
|--------|-------------|
| `template(string $template, array $vars): TwigTemplate` | Merges web layout + content |

### Moedit AI

**Route:** `POST /dashboard/content/moedit/ai`
**Name:** `moedit-ai`

OpenAI API proxy for the moedit editor's AI button.

---

## ContentsRouter - All Routes

**File:** `application/raptor/content/ContentsRouter.php`

Central router that registers all content module routes: Files, News, Pages, References, Settings, Moedit AI.

---

## Shop

### ProductsModel

**File:** `application/dashboard/shop/ProductsModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `products`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `slug` | varchar(255) | SEO-friendly URL slug |
| `title` | varchar(255) | Product title |
| `description` | text | Short description |
| `content` | longtext | HTML content |
| `price` | decimal(10,2) | Price |
| `sale_price` | decimal(10,2) | Sale price |
| `sku` | varchar(50) | SKU code |
| `barcode` | varchar(50) | Barcode |
| `sizes` | varchar(255) | Available sizes |
| `colors` | varchar(255) | Available colors |
| `stock` | int | Stock quantity |
| `link` | varchar(255) | External link |
| `photo` | varchar(255) | Cover image |
| `code` | varchar(6) | Language code |
| `type` | varchar(50) | Product type |
| `category` | varchar(50) | Category |
| `is_featured` | tinyint | Featured product |
| `comment` | tinyint | Comments enabled |
| `read_count` | int | View count |
| `is_active` | tinyint | Active status |
| `published` | tinyint | Published status |
| `published_at` | datetime | Published date |
| `published_by` | bigint | Published by user |
| `created_at` | datetime | Created date |
| `created_by` | bigint | Created by user |
| `updated_at` | datetime | Updated date |
| `updated_by` | bigint | Updated by user |

#### `generateSlug(string $title): string`
Generates an SEO-friendly slug. Supports Mongolian Cyrillic transliteration.

#### `getExcerpt(string $content, int $length = 150): string`
Extracts a plain-text excerpt from HTML content.

### ProductOrdersModel

**File:** `application/dashboard/shop/ProductOrdersModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `products_orders`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `product_id` | bigint | Product reference (FK -> products) |
| `product_title` | varchar(255) | Product title snapshot |
| `customer_name` | varchar(128) | Customer name |
| `customer_email` | varchar(128) | Customer email |
| `customer_phone` | varchar(32) | Customer phone |
| `message` | text | Customer message |
| `quantity` | int (default: 1) | Quantity |
| `code` | varchar(2) | Language code |
| `status` | varchar(32, default: 'new') | Order status |
| `is_active` | tinyint (default: 1) | Active status |
| `created_at` | datetime | Created date |
| `created_by` | bigint | Created by user (FK -> users) |
| `updated_at` | datetime | Updated date |
| `updated_by` | bigint | Updated by user (FK -> users) |

### ProductsRouter

**File:** `application/dashboard/shop/ProductsRouter.php`

Dashboard product management routes.

### OrdersRouter

**File:** `application/dashboard/shop/OrdersRouter.php`

Dashboard order management routes.

---

## Notification

### DiscordNotifier

**File:** `application/raptor/notification/DiscordNotifier.php`

Discord webhook integration service. Registered in DI Container as `discord`.

#### `send(string $title, string $description, int $color, array $fields = []): void`
Sends a generic Discord embed message.

#### `userSignupRequest(string $username, string $email): void`
Notification for new user signup request.

#### `userApproved(string $username, string $email, string $admin): void`
Notification when admin approves a user.

#### `newOrder(int $orderId, string $customer, string $email, string $product, int $quantity): void`
Notification for new order submission.

#### `orderStatusChanged(int $orderId, string $customer, string $oldStatus, string $newStatus, string $admin): void`
Notification when order status changes.

#### `contentAction(string $type, string $action, string $title, int $id, string $admin): void`
Notification for content management actions (insert, update, delete, publish).

#### Color Constants

| Constant | Value | Usage |
|----------|-------|-------|
| `SUCCESS` | Green | Approval, completion |
| `INFO` | Blue | Informational |
| `WARNING` | Yellow | Warnings |
| `DANGER` | Red | Errors, deletions |
| `PURPLE` | Purple | Special actions |

---

## Development

### DevelopmentRouter

**File:** `application/raptor/development/DevelopmentRouter.php`

| Route | Method | Name | Description |
|-------|--------|------|-------------|
| `/dashboard/dev-requests` | GET | `dev-requests` | Request list |
| `/dashboard/dev-requests/list` | GET | `dev-requests-list` | JSON list |
| `/dashboard/dev-requests/create` | GET | `dev-requests-create` | Create form |
| `/dashboard/dev-requests/store` | POST | `dev-requests-store` | Submit request |
| `/dashboard/dev-requests/view/{uint:id}` | GET | `dev-requests-view` | View request |
| `/dashboard/dev-requests/respond` | POST | `dev-requests-respond` | Add response |
| `/dashboard/dev-requests/deactivate` | DELETE | `dev-requests-deactivate` | Deactivate |

Protected by `development:development` RBAC permission.

---

## Migration

### MigrationRunner

**File:** `application/raptor/migration/MigrationRunner.php`

SQL file-based forward-only migration engine.

| Method | Description |
|--------|-------------|
| `__construct(\PDO $pdo, string $migrationsPath)` | PDO + path to migrations directory |
| `hasPending(): bool` | Check if any pending migrations exist |
| `migrate(): array` | Run all pending SQL files, return list of migrated filenames |
| `status(): array` | Return `['ran' => [...], 'pending' => [...]]` |
| `parseFile(string $path): array` | Parse SQL file returning `['up' => string, 'down' => string]` |

### MigrationMiddleware

**File:** `application/raptor/migration/MigrationMiddleware.php`
**Implements:** `MiddlewareInterface`

Auto-runs pending migrations on each request. Uses advisory lock (`GET_LOCK`) to prevent concurrent execution. Silent failure: logs errors but does not block the request.

### MigrationController

**File:** `application/raptor/migration/MigrationController.php`
**Extends:** `Raptor\Controller`

| Method | Description |
|--------|-------------|
| `index()` | Dashboard page showing migration status (system_coder only) |
| `status()` | JSON: return ran + pending migration lists |
| `view()` | AJAX modal: display SQL file contents |

### MigrationRouter

**File:** `application/raptor/migration/MigrationRouter.php`

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/migrations` | GET | `migrations` |
| `/dashboard/migrations/status` | GET | `migrations-status` |
| `/dashboard/migrations/view` | GET | `migrations-view` |
