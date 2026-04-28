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
24. [Badge System](#badge-system)
25. [MenuModel](#menumodel)
26. [Dashboard Home](#dashboard-home)
27. [Dashboard Manual](#dashboard-manual)
28. [Web Layer](#web-layer)
29. [Shop](#shop)
30. [Notification](#notification)
31. [Development](#development)
32. [Migration](#migration)
33. [Cache](#cache)
34. [Seed and Initial Data](#seed-and-initial-data)
35. [Trash](#trash)
36. [Event System](#event-system)

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

#### `template(string $template, array $vars = []): FileTemplate`
Creates a Template with auto-injected variables: `user`, `index`, `localization`, `request`. Registers `text` and `link` filters.

#### `respondJSON(array $response, int|string $code = 0): void`
Outputs a JSON response with `Content-Type: application/json` header.

#### `redirectTo(string $routeName, array $params = []): void`
Redirects to a named route (302). Calls `exit`.

#### `log(string $table, string $level, string $message, array $context = []): void`
Writes a system log entry to the `{$table}_log` database table. Server request metadata and user info are auto-appended.

#### `dispatch(object $event): void`
Dispatches a PSR-14 event via the `EventDispatcher` service from the DI container. Silently skips if the dispatcher is not available.

#### `generateRouteLink(string $routeName, array $params = [], bool $is_absolute = false, string $default = '#'): string`
Generates a URL from a route name.

#### `getContainer(): ?ContainerInterface`
Returns the DI Container.

#### `getService(string $id): mixed`
Gets a service from the container.

#### `invalidateCache(string ...$keys): void`
Deletes specified cache keys. Use `{code}` placeholder for language-specific keys (auto-iterates all languages). Fail-safe: silently skips if cache is unavailable.

```php
$this->invalidateCache('pages_nav.{code}', 'featured_pages.{code}');
$this->invalidateCache('texts.{code}');
$this->invalidateCache('languages');
```

#### `headerResponseCode(int|string $code): void`
Sets HTTP response code. Ignores non-standard codes.

#### `getScriptPath(): string`
Returns the script path (subdirectory support).

#### `getDocumentRoot(): string`
Returns the document root path.

---

## Middleware Safety Rules

### Never call handle() inside try/catch

The middleware runner uses an internal array pointer (`current()`/`next()`) to iterate the queue. Each `$handler->handle()` call advances this pointer irreversibly.

**If `handle()` is called inside a `try` block**, and an exception propagates back from deeper in the chain, the `catch` block catches it - but the pointer has already advanced. If execution then reaches a second `handle()` call outside the `try`, the pointer is past the end of the queue, `current()` returns `false`, and the application crashes.

```php
// WRONG - double handle() call when exception occurs
public function process($request, $handler): ResponseInterface
{
    try {
        $data = $cache->get('key');
        if ($data !== null) {
            return $handler->handle($request->withAttribute('data', $data));
            //     ^^^^^^^^^^^^^^^ called inside try - pointer advances
            //     If exception occurs deeper in the chain, catch catches it,
            //     then the handle() below is called AGAIN
        }
        $data = $this->loadFromDb();
    } catch (\Throwable $e) {
        \error_log($e->getMessage());
        // Exception silently caught - execution continues below
    }
    return $handler->handle($request->withAttribute('data', $data ?? []));
    //     ^^^^^^^^^^^^^^^ SECOND call - pointer already past end -> crash
}
```

```php
// CORRECT - single handle() call, always outside try
public function process($request, $handler): ResponseInterface
{
    $data = [];
    try {
        $cached = $cache->get('key');
        if ($cached !== null) {
            $data = $cached;          // Only prepare data, no handle() call
        } else {
            $data = $this->loadFromDb();
        }
    } catch (\Throwable $e) {
        \error_log($e->getMessage());
    }
    return $handler->handle($request->withAttribute('data', $data));
    //     ^^^^^^^^^^^^^^^ called exactly ONCE, outside try
}
```

**Rules:**
- `$handler->handle()` must be called exactly **once** per middleware execution
- That single call must be **outside** any `try/catch` block
- `try/catch` should only wrap data preparation (DB queries, cache reads, validation)
- The `catch` block handles errors (log, set defaults), then lets execution flow to the single `handle()` call

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
6. `CsrfMiddleware` - CSRF token validation for POST/PUT/PATCH/DELETE
7. `ContainerMiddleware` - DI Container
8. `LocalizationMiddleware` - Multi-language
9. `SettingsMiddleware` - System settings
10. `LoginRouter`, `UsersRouter`, `OrganizationRouter`, `RBACRouter`, `LocalizationRouter`, `ContentsRouter`, `LogsRouter`, `DevelopmentRouter`, `MigrationRouter`, `TemplateRouter`, `BadgeRouter`

`Dashboard\Application` adds: `HomeRouter`, `ShopRouter` (products + orders + reviews), `ManualRouter`

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
| `delete(string $table)` | Hard delete file (stores in Trash) |

### ContentsRouter - File Routes

| Route | Method | Name |
|-------|--------|------|
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
| `published` | tinyint (default: 0) | Published status |
| `published_at` | datetime | Published date |
| `published_by` | bigint | Published by user (FK -> users) |
| `created_at` | datetime | Created date |
| `created_by` | bigint | Created by user (FK -> users) |
| `updated_at` | datetime | Updated date |
| `updated_by` | bigint | Updated by user (FK -> users) |

> **Note:** The `is_active` column was removed from the news table. Deletion is now handled via hard delete with Trash backup.

#### `getRecentPublished(string $code, int $limit = 20): array`
Returns recently published news for the given language. Selects id, slug, title, description, photo, code, type, category, is_featured, comment, published_at, created_at, source. Excludes `read_count` (dynamic) for cache compatibility. Used by HomeController with cache key `recent_news.{code}`.

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
| `/dashboard/news/delete` | DELETE | `news-delete` |
| `/dashboard/news/reset` | DELETE | `news-sample-reset` |

---

## Content - Comments

### CommentsModel

**File:** `application/raptor/content/news/CommentsModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `news_comments`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `news_id` | bigint | News article reference (FK -> news) |
| `parent_id` | bigint | Parent comment for 1-level reply (FK -> news_comments, self) |
| `created_by` | bigint | Author user (FK -> users, null for guest) |
| `name` | varchar(128) | Commenter name |
| `email` | varchar(128) | Commenter email |
| `comment` | text | Comment text |
| `created_at` | datetime | Created date |

> **Note:** The `is_active` column was removed. Deletion is now handled via hard delete with Trash backup.

### CommentsController (Dashboard)

**File:** `application/raptor/content/news/CommentsController.php`
**Extends:** `Raptor\Controller`

| Method | Description |
|--------|-------------|
| `index()` | Comments management page |
| `list()` | JSON comment list |
| `view(int $id)` | View comment detail |
| `delete()` | Hard delete comment (stores in Trash) |

### ContentsRouter - Comment Routes

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/news/comments` | GET | `comments` |
| `/dashboard/news/comments/list` | GET | `comments-list` |
| `/dashboard/news/comments/{uint:id}` | GET | `comments-view` |
| `/dashboard/news/comments/delete` | DELETE | `comments-delete` |
| `/dashboard/news/comment/{uint:id}/reply` | GET | - |

---

## Content - Messages

### MessagesModel

**File:** `application/raptor/content/messages/MessagesModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `messages`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `name` | varchar(128) | Sender name |
| `phone` | varchar(50) | Sender phone |
| `email` | varchar(128) | Sender email |
| `message` | text | Message text |
| `code` | varchar(2) | Language code |
| `is_read` | tinyint | Read status (0=new, 1=read, 2=replied) |
| `replied_note` | text | Admin reply note |
| `created_at` | datetime | Created date |

> **Note:** The `is_active` column was removed. Deletion is now handled via hard delete with Trash backup.

### MessagesController (Dashboard)

**File:** `application/raptor/content/messages/MessagesController.php`
**Extends:** `Raptor\Controller`

| Method | Description |
|--------|-------------|
| `index()` | Messages management page |
| `list()` | JSON message list |
| `view(int $id)` | View message detail (marks as read) |
| `markReplied(int $id)` | Mark message as replied with note |
| `delete()` | Hard delete message (stores in Trash) |

### ContentsRouter - Message Routes

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/messages` | GET | `messages` |
| `/dashboard/messages/list` | GET | `messages-list` |
| `/dashboard/messages/view/{uint:id}` | GET | `messages-view` |
| `/dashboard/messages/replied/{uint:id}` | PATCH | `messages-replied` |
| `/dashboard/messages/delete` | DELETE | `messages-delete` |

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
| `read_count` | bigint (default: 0) | View count |
| `published` | tinyint (default: 0) | Published status |
| `published_at` | datetime | Published date |
| `published_by` | bigint | Published by user (FK -> users) |
| `created_at` | datetime | Created date |
| `created_by` | bigint | Created by user (FK -> users) |
| `updated_at` | datetime | Updated date |
| `updated_by` | bigint | Updated by user (FK -> users) |

> **Note:** The `is_active` column was removed from the pages table. Deletion is now handled via hard delete with Trash backup.

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
| `/dashboard/pages/delete` | DELETE | `page-delete` |
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
| `/dashboard/references/delete` | DELETE | `reference-delete` |

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
| `/dashboard/settings/env` | PATCH | `settings-env` |

### SettingsController::updateEnv()

Unified `.env` value update endpoint. Requires `system_coder` role.

**Request body:** `{ "name": "ENV_VAR_NAME", "value": "...", "type": "bool|email|string" }`

**Allowed .env variables:**

| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| `RAPTOR_CONTACT_EMAIL_NOTIFY` | bool | true | Toggle contact message email notification |
| `RAPTOR_CONTACT_EMAIL_TO` | email | - | Recipient email for contact messages |
| `RAPTOR_ORDER_EMAIL_NOTIFY` | bool | true | Toggle order email notification |
| `RAPTOR_ORDER_EMAIL_TO` | email | - | Recipient email for orders |
| `RAPTOR_COMMENT_EMAIL_NOTIFY` | bool | false | Toggle comment email notification |
| `RAPTOR_COMMENT_EMAIL_TO` | email | - | Recipient email for comments |
| `RAPTOR_REVIEW_EMAIL_NOTIFY` | bool | false | Toggle review email notification |
| `RAPTOR_REVIEW_EMAIL_TO` | email | - | Recipient email for product reviews |

**Type behavior:**
- `bool` - Toggles current value (ignores `value` field). Response includes `value: true|false`
- `email` - Validates email format via `filter_var()`. Empty string clears the value
- `string` - No validation, stores as-is

Used by messages-index, orders-index, comments-index, reviews-index templates for admin email notification settings (visible to `system_coder` only).

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

Sends email. `send()` selects transport via `RAPTOR_MAIL_TRANSPORT` env var: `brevo` (default), `smtp`, `mail`.

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

Injects PSR-11 DI Container into request. Registers PDO, User ID, `EventDispatcher` (with `DiscordListener`), and `DiscordNotifier` (legacy) in the container.

---

## SpamProtectionTrait

**File:** `application/raptor/SpamProtectionTrait.php`

Provides spam protection methods using Cloudflare Turnstile and link-based heuristics.

### Methods

#### `getTurnstileSiteKey(): string`
Returns the Turnstile site key from ENV configuration. Returns empty string if not configured.

#### `validateSpamProtection(): bool`
Validates the Cloudflare Turnstile token from the request. Returns `true` if verification passes or if Turnstile is not configured.

#### `checkLinkSpam(string $text): bool`
Checks if text contains suspicious link patterns. Returns `true` if spam is detected.

### Used By

- `Web\Service\ContactController` - Contact form submission
- `Web\Content\NewsController` - News comment submission
- `Web\Shop\ShopController` - Order submission
- `Raptor\Authentication\LoginController` - Signup and forgot password

---

## CsrfMiddleware

**File:** `application/raptor/CsrfMiddleware.php`
**Implements:** `Psr\Http\Server\MiddlewareInterface`

Per-session CSRF token validation for dashboard state-changing requests.

### How It Works

1. Token is generated at login and stored in `$_SESSION['CSRF_TOKEN']`
2. If no token exists (e.g. old session), auto-generates one when session is writable
3. Token is set as `csrf_token` request attribute for controllers
4. GET/HEAD/OPTIONS requests pass through without validation
5. `/login` routes are exempt (token is created there)
6. All other methods (POST, PUT, PATCH, DELETE) require valid `X-CSRF-TOKEN` header
7. Mismatched or missing token returns 403 JSON response

### Frontend Integration

- Token delivered via `<meta name="csrf-token">` in `dashboard.html`
- `csrfFetch()` wrapper in `dashboard.js` auto-attaches `X-CSRF-TOKEN` header
- Use `csrfFetch()` for all POST/PUT/PATCH/DELETE requests in dashboard modules
- Standalone pages (e.g. login) use plain `fetch()` since `dashboard.js` is not loaded

---

## HtmlValidationTrait

**File:** `application/raptor/content/HtmlValidationTrait.php`

Server-side HTML content validation. Used by Pages, News, Products controllers on insert/update.

#### `validateHtmlContent(string $html): void`
Checks for unclosed HTML comments (`<!-- -->`), parses with DOMDocument, compares text length before/after parsing. Throws `InvalidArgumentException` if content loss exceeds 20% (indicating broken tags or unclosed comments).

### Used By

- `Raptor\Content\NewsController` - News insert/update
- `Raptor\Content\PagesController` - Page insert/update
- `Dashboard\Shop\ProductsController` - Product insert/update

---

## DashboardTrait

**File:** `application/raptor/template/DashboardTrait.php`

Provides dashboard UI rendering, permission alerts, sidebar menu generation, and user detail retrieval.

#### `dashboardTemplate(string $template, array $vars = []): FileTemplate`
Renders content within `dashboard.html` layout with sidebar menu and system settings. Loads menu from cache (`menu.{code}` key).

#### `dashboardProhibited(?string $alert = null, int|string $code = 0): FileTemplate`
Shows permission denial alert within dashboard layout.

#### `modalProhibited(?string $alert = null, int|string $code = 0): FileTemplate`
Shows permission denial modal (standalone, no layout wrapper).

#### `getUserMenu(): array`
Builds sidebar menu array filtered by user permissions, organization alias, visibility, and activity status.

#### `retrieveUsersDetail(?int ...$ids): array`
Returns `[user_id => "username - First Last (email)"]` map for audit/log display. Returns all users if no IDs provided.

---

## FileController

**File:** `application/raptor/content/file/FileController.php`
**Extends:** `Raptor\Controller`

Base class for file upload, validation, storage, and image optimization. Extended by controllers that handle file uploads (FilesController, SettingsController, UsersController, etc.).

### Key Methods

| Method | Description |
|--------|-------------|
| `setFolder(string $folder)` | Sets upload directory (e.g. `/users/1`, `/pages/22`) |
| `getFilePublicPath(string $fileName)` | Returns public URL path for a file |
| `allowExtensions(array $exts)` | Whitelist specific file extensions |
| `allowImageOnly()` | Restrict to image extensions only |
| `allowCommonTypes()` | Allow common web file types (images, docs, media, archives) |
| `setSizeLimit(int $size)` | Set max file size in bytes |
| `setOverwrite(bool $overwrite)` | Enable/disable overwrite on duplicate names |
| `moveUploaded($uploadedFile, bool $optimize)` | Main upload handler: validates, stores, returns file info array |
| `optimizeImage(string $filePath)` | Resizes/compresses JPEG/PNG/GIF/WebP for web (max width from `.env`, quality 90) |
| `getMaximumFileUploadSize()` | Returns `MIN(post_max_size, upload_max_filesize)` in bytes |
| `formatSizeUnits(?int $bytes)` | Formats bytes as human-readable string (e.g. `10.5mb`) |
| `unlinkByName(string $fileName)` | Deletes file by name from upload folder |

---

## AIHelper

**File:** `application/raptor/content/AIHelper.php`
**Extends:** `Raptor\Controller`

OpenAI API integration for moedit WYSIWYG editor. Provides HTML content enhancement (Shine) and image text recognition (OCR/Vision).

#### `moeditAI(): void`
POST `/dashboard/content/moedit/ai` - Main endpoint with two modes:

**HTML mode** (`mode: 'html'`): Enhances HTML content using GPT-4o-mini. Request body: `{mode, html, prompt}`.

**Vision mode** (`mode: 'vision'`): Extracts text from images using GPT-4o vision. Request body: `{mode, images[], prompt}`.

Response: `{status: 'success', html: '...'}` or `{status: 'error', message: '...'}`.

Requires `RAPTOR_OPENAI_API_KEY` in `.env`. Auth required (any logged-in user).

---

## Badge System

### BadgeController

**File:** `application/raptor/template/BadgeController.php`
**Extends:** `Raptor\Controller`

Dashboard sidebar badge system showing unseen activity counts per module. Reads from existing `*_log` tables.

#### `list(): void`
GET `/dashboard/badges` - Returns JSON with badge counts per module. Groups badges by color (green=create, blue=update, red=delete). Filters by admin permissions. Excludes admin's own actions. First-time users get 30-day lookback.

#### `seen(): void`
POST `/dashboard/badges/seen` - Marks a module as seen. Updates `checked_at` timestamp for the current admin.

### Constants

- `BADGE_MAP` - Maps `[log_table][action]` to `[module_path, color]`
- `PERMISSION_MAP` - Maps module path to required permission (`null` = any admin, `'system_x'` = permission check, `'role:system_coder'` = role check)

### BadgeRouter

**File:** `application/raptor/template/BadgeRouter.php`

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/badges` | GET | `dashboard-badges` |
| `/dashboard/badges/seen` | POST | - |

### AdminBadgeSeenModel

**File:** `application/raptor/template/AdminBadgeSeenModel.php`
**Extends:** `codesaur\DataObject\Model`

Tracks when each admin last viewed each module. Columns: `admin_id`, `module`, `checked_at`, `last_seen_count`. Unique index on `(admin_id, module)`.

---

## MenuModel

**File:** `application/raptor/template/MenuModel.php`
**Extends:** `codesaur\DataObject\LocalizedModel`

Dashboard sidebar menu items with multilingual titles and parent/child hierarchy.

### Columns

| Column | Type | Description |
|--------|------|-------------|
| `parent_id` | bigint | Parent menu ID (0 = root) |
| `icon` | varchar(64) | Bootstrap Icons class |
| `href` | varchar(255) | Menu link URL |
| `alias` | varchar(64) | Organization alias filter |
| `permission` | varchar(128) | Required permission to see menu item |
| `position` | smallint | Sort order |
| `is_visible` | tinyint | Visibility toggle |
| `title` (localized) | varchar(128) | Menu label per language |

### Methods

| Method | Description |
|--------|-------------|
| `insert(array $record, array $content)` | Creates menu item (auto-sets `created_at`) |
| `updateById(int $id, array $record, array $content)` | Updates menu item (auto-sets `updated_at`) |

---

## Dashboard Home

### HomeRouter

**File:** `application/dashboard/home/HomeRouter.php`

| Route | Method | Name |
|-------|--------|------|
| `/dashboard` | GET | `dashboard` |
| `/dashboard/search` | GET | - |
| `/dashboard/stats` | GET | - |
| `/dashboard/log-stats` | GET | - |

### SearchController

**File:** `application/dashboard/home/SearchController.php`
**Extends:** `Raptor\Controller`

#### `search(): void`
Dashboard global search. Searches across news, pages, products, orders, and users tables using LIKE queries. Results filtered by RBAC permissions. Returns JSON.

### WebLogStatsController

**File:** `application/dashboard/home/WebLogStatsController.php`
**Extends:** `Raptor\Controller`

#### `stats(): void`
Returns web visit statistics JSON (today/week/month totals, chart data, top pages/news/products, IP addresses, user agents).

#### `logStats(): void`
Returns system `*_log` table statistics JSON (today/week/total counts, last update time per table).

### WebLogStats

**File:** `application/dashboard/home/WebLogStats.php`

Standalone utility that calculates web visit statistics. Maintains a `web_log_cache` table for performance. Supports both MySQL (`JSON_EXTRACT`) and PostgreSQL (`::jsonb`).

---

## Dashboard Manual

### ManualRouter

**File:** `application/dashboard/manual/ManualRouter.php`

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/manual` | GET | `manual` |
| `/dashboard/manual/{file}` | GET | - |

### ManualController

**File:** `application/dashboard/manual/ManualController.php`
**Extends:** `Raptor\Controller`

#### `index(): void`
Lists all manual HTML files from `application/dashboard/manual/` directory, grouped by module with language variants.

#### `view(string $file): void`
Displays a specific manual file. Falls back to English (`-en.html`) if the requested language variant is not found.

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
| `/session/contact-send` | POST | `contact-send` | Send contact message |
| `/session/order` | POST | - | Submit order (session) |
| `/session/language/{code}` | GET | - | Switch language (session) |
| `/session/news/{uint:id}/comment` | POST | `news-comment` | Submit news comment |

### HomeController

**File:** `application/web/HomeController.php`
**Extends:** `TemplateController`

| Method | Description |
|--------|-------------|
| `index()` | Home page (latest 20 published news, cached by language) |
| `favicon()` | Returns favicon redirect or 204 No Content with cache headers |
| `language(string $code)` | Sets session language and redirects to homepage |

### PageController

**File:** `application/web/content/PageController.php`
**Extends:** `TemplateController`

| Method | Description |
|--------|-------------|
| `pageById(int $id)` | Redirect page by ID to slug URL |
| `page(string $slug)` | Display page + files + read_count + OG meta |

### ContactController

**File:** `application/web/service/ContactController.php`
**Extends:** `TemplateController`

| Method | Description |
|--------|-------------|
| `contact()` | Contact page (link LIKE '%/contact') |
| `contactSend()` | Send contact message (AJAX, spam-protected) |

### NewsController (Web)

**File:** `application/web/content/NewsController.php`
**Extends:** `TemplateController`

| Method | Description |
|--------|-------------|
| `newsById(int $id)` | Redirect news by ID to slug URL |
| `news(string $slug)` | Display news + files + read_count + word_count + read_time + OG meta |
| `newsType(string $type)` | List news by type/category (or `all`) with category sidebar |
| `archive()` | News archive with year/month filtering |
| `commentSubmit(int $id)` | Submit news comment (AJAX, 5-layer spam protection, email + Discord notify) |

### ShopController

**File:** `application/web/shop/ShopController.php`
**Extends:** `TemplateController`

| Method | Description |
|--------|-------------|
| `products()` | List all published products |
| `productById(int $id)` | Redirect product by ID to slug URL |
| `product(string $slug)` | Display product + files + read_count + OG meta |
| `order()` | Display order form (pre-fills product info if product_id query param) |
| `orderSubmit()` | Process order (spam check, validate, DB insert, email, Discord) |
| `reviewSubmit(int $id)` | Submit product review (AJAX, spam protection, email + Discord notify) |

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
| `webTemplate(string $template, array $vars): FileTemplate` | Merges web layout + content. Auto-maps title, code, description, photo from $vars to index layout SEO meta. Loads settings, navigation, featured pages (cached). |

### ExceptionHandler

**File:** `application/web/template/ExceptionHandler.php`
**Implements:** `codesaur\Http\Application\ExceptionHandlerInterface`

Renders user-friendly error pages for web frontend. Shows `page-404.html` template with error info. Appends JSON stack trace in `CODESAUR_DEVELOPMENT` mode only.

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
| `created_at` | datetime | Created date |
| `created_by` | bigint | Created by user (FK -> users) |
| `updated_at` | datetime | Updated date |
| `updated_by` | bigint | Updated by user (FK -> users) |

### ShopRouter

**File:** `application/dashboard/shop/ShopRouter.php`

Unified dashboard router for the shop module: products, reviews, and orders.

**Products:**

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/products` | GET | `products` |
| `/dashboard/products/list` | GET | `products-list` |
| `/dashboard/products/insert` | GET, POST | `product-insert` |
| `/dashboard/products/{uint:id}` | GET, PUT | `product-update` |
| `/dashboard/products/view/{uint:id}` | GET | - |
| `/dashboard/products/delete` | DELETE | `product-delete` |
| `/dashboard/products/reset` | DELETE | `products-sample-reset` |

**Reviews:**

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/products/reviews` | GET, POST | `products-reviews` (GET = HTML, POST = JSON list) |
| `/dashboard/products/reviews/delete` | DELETE | `products-reviews-delete` |

**Orders:**

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/orders` | GET | `orders` |
| `/dashboard/orders/list` | GET | `orders-list` |
| `/dashboard/orders/view/{uint:id}` | GET | - |
| `/dashboard/orders/{uint:id}/status` | PATCH | `order-status` |
| `/dashboard/orders/delete` | DELETE | `order-delete` |

---

## Notification

### DiscordListener

**File:** `application/raptor/notification/DiscordListener.php`

PSR-14 event listener that sends Discord webhook notifications. Replaces the previous `DiscordNotifier` direct-call pattern. Registered via `ListenerProvider`.

#### `__construct(string $webhookUrl)`
Takes the Discord webhook URL from `RAPTOR_DISCORD_WEBHOOK_URL` env variable.

#### `onContentEvent(ContentEvent $event): void`
Handles content actions (insert, update, delete, publish) for News, Pages, Products, etc.

#### `onUserEvent(UserEvent $event): void`
Handles user-related events (signup request, approval).

#### `onOrderEvent(OrderEvent $event): void`
Handles order events (new order, status change, review).

#### `onDevRequestEvent(DevRequestEvent $event): void`
Handles development request events (new request, new response).

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
| `/dashboard/dev-requests/delete` | DELETE | `dev-requests-delete` | Delete (stores in Trash) |

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

---

## Cache

### CacheService

**File:** `application/raptor/CacheService.php`
**Namespace:** `Raptor`

Custom file-based DB cache (PSR-16 SimpleCache). No external dependency beyond `psr/simple-cache`. Stored in `private/cache/`. Registered as `cache` container service via `ContainerMiddleware`. TTL: 12 hours (safety net). Fail-safe: returns `null` if unavailable.

| Method | Description |
|--------|-------------|
| `__construct(string $cacheDir, int $defaultTtl = 3600)` | Initialize with cache directory and default TTL |
| `get(string $key, mixed $default = null): mixed` | Get cached value or default |
| `set(string $key, mixed $value, ?int $ttl = null): bool` | Store value in cache |
| `delete(string $key): bool` | Remove cached value |
| `clear(): bool` | Remove all cached values |

### Cached Data

| Key | Loaded by | Invalidated by |
|-----|-----------|---------------|
| `languages` | LocalizationMiddleware | LanguageController |
| `texts.{code}` | LocalizationMiddleware | TextController, LanguageController |
| `settings.{code}` | SettingsMiddleware | SettingsController |
| `menu.{code}` | DashboardTrait | TemplateController (menu CRUD) |
| `rbac.{userId}` | JWTAuthMiddleware | RBACController (`clear()`) |
| `pages_nav.{code}` | Web TemplateController | PagesController |
| `featured_pages.{code}` | Web TemplateController | PagesController |
| `recent_news.{code}` | HomeController | NewsController |
| `reference.{code}` | (prepared) | ReferencesController |

### Usage in Middleware

```php
// Cache read pattern (LocalizationMiddleware, SettingsMiddleware)
$cache = $request->getAttribute('container')?->get('cache');
$data = $cache?->get('my_key');
if ($data === null) {
    $data = $model->retrieve();
    $cache?->set('my_key', $data);
}
```

### Usage in Controllers

```php
// Cache read
$cache = $this->hasService('cache') ? $this->getService('cache') : null;
$data = $cache?->get("pages_nav.$code");
if ($data === null) {
    $data = $model->getNavigation($code);
    $cache?->set("pages_nav.$code", $data);
}

// Cache invalidation (after successful DB write, before respondJSON)
$this->invalidateCache('pages_nav.{code}', 'featured_pages.{code}');

// RBAC changes (affects all users)
if ($this->hasService('cache')) {
    $this->getService('cache')->clear();
}
```

---

## Seed and Initial Data

Seed and Initial classes populate the database on fresh installs. They run automatically from Model `__initial()` methods when tables are first created.

### PermissionsSeed

**File:** `application/raptor/rbac/PermissionsSeed.php`

Seeds 18+ system permissions with `system_` prefix: `logger`, `rbac`, `user_*` (5), `organization_*` (4), `content_*` (6), `product_*` (5), `localization_*` (4), `templates_index`, `development`.

### RolePermissionSeed

**File:** `application/raptor/rbac/RolePermissionSeed.php`

Creates default roles and assigns permissions:

| Role | Scope |
|------|-------|
| `coder` | Super admin - bypasses all checks |
| `admin` | All permissions (except development) |
| `manager` | Users, organizations, content, products, localization, development |
| `editor` | Content and products (index/insert/update/publish) |
| `viewer` | Content and products (index only) |

### MenuSeed

**File:** `application/raptor/template/MenuSeed.php`

Creates dashboard sidebar menu structure with 3 sections (MN/EN):
- **Contents** - Messages, Pages, News, Files, Localization, References, Settings
- **Shop** - Products, Orders
- **System** - Users, Organizations, Logs, Dev Requests, Manual, Migrations, Menu Management

Each item has `permission` guard and `position` for ordering.

### TextInitial

**File:** `application/raptor/localization/text/TextInitial.php`

Seeds 100+ system localization keywords in MN/EN pairs (e.g. `accept`, `cancel`, `delete`, `dashboard`, `error`, `success`). Keywords are alphabetically ordered with type `sys-defined`.

### ReferenceInitial

**File:** `application/raptor/content/reference/ReferenceInitial.php`

Seeds `reference_templates` table with email templates and legal content:
- Email templates: `forgotten-password-reset`, `request-new-user`, `approve-new-user`, `dev-request-new`, `dev-request-response`, `contact-message-notify`, `order-status-update`, `order-confirmation`, `order-notify`, `comment-notify`, `review-notify`
- Legal: `tos` (Terms of Service), `pp` (Privacy Policy)

### Sample Data Classes

Sample data only exists for built-in modules. Runs on fresh install, removable via dashboard "Reset" button.

| Class | File | Data |
|-------|------|------|
| `NewsSamples` | `raptor/content/news/NewsSamples.php` | 6 news articles (3 MN + 3 EN) with 3 types |
| `PagesSamples` | `raptor/content/page/PagesSamples.php` | 14+ hierarchical pages (MN + EN) with parent/child structure |
| `ProductsSamples` | `dashboard/shop/ProductsSamples.php` | 4 products (2 MN + 2 EN) |

---

## Trash

### TrashModel

**File:** `application/raptor/trash/TrashModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `trash`

Stores deleted records as JSON snapshots before hard deletion.

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `table_name` | varchar(128) | Source table name |
| `log_table` | varchar(64) | Log channel name to write the "restored" row to (e.g. `products`, `news`, `content`) |
| `original_id` | bigint | Original record ID in source table |
| `record_data` | mediumtext | Full record data as JSON (UTF-8 unescaped) |
| `deleted_by` | bigint | User who deleted (FK -> users) |
| `deleted_at` | datetime | Deletion timestamp |

Indexes: `trash_idx_table` on `table_name`, `trash_idx_deleted` on `deleted_at DESC`.

#### `store(string $logTable, string $tableName, int $originalId, array $recordData, int $deletedBy): array`
Stores a deleted record snapshot. The `$logTable` argument is the **log channel name** the controller would pass to `$this->log()` - `restore()` writes the audit row to this exact channel without any translation. Called by controllers after `deleteById()` succeeds, so only actually deleted records end up in trash. Also writes a `trash_log` entry with `action='store'` for sidebar badge tracking.

#### `getById(int $id): array|null`
Returns a single trash record by ID.

#### `deleteById(int $id): bool`
Permanently removes a trash record.

### TrashController

**File:** `application/raptor/trash/TrashController.php`
**Extends:** `Raptor\Controller`

| Method | Description |
|--------|-------------|
| `index()` | Trash management page |
| `list()` | JSON trash record list (supports `table_name` filter) |
| `view(int $id)` | View deleted record detail (JSON data) |
| `restore(int $id)` | Restore a record to its source table |
| `delete()` | Permanently delete a single trash record |
| `empty()` | Empty all trash records |

**Log channel resolution** - Controllers pass the log channel name directly to `TrashModel::store()` as the first argument; `restore()` reads it back from the `log_table` column and uses it when calling `$this->log()`. Standard values used across the codebase:

| Source controller | `log_table` value passed |
|-------------------|--------------------------|
| `OrdersController` | `products_orders` |
| `ProductsController` (record + attachments) | `products` |
| `ReviewsController` | `products` |
| `NewsController` (record + attachments) | `news` |
| `CommentsController` | `news` |
| `PagesController` (record + attachments) | `pages` |
| `ReferencesController` | `content` |
| `LanguageController` | `content` |
| `TextController` | `content` |
| `TemplateController` (menu delete) | `dashboard` |
| `FilesController` | `files` |
| `MessagesController` | `messages` |
| `DevRequestController` | `dev_requests` |

#### Restore flow

1. **UNIQUE pre-flight** - reads UNIQUE columns from schema (`information_schema.STATISTICS` for MySQL, `pg_index` for PostgreSQL) and aborts if any value already exists in the live table. Returns a clear admin message naming the conflicting field/value.
2. **Original ID insert** - attempts to restore with the original ID to preserve foreign key references (`comments.news_id`, etc.).
3. **Auto-increment fallback** - on `SQLSTATE 23000` (PRIMARY KEY conflict only - UNIQUE already handled by pre-flight), retries the insert without the ID and lets the DB assign a new one. The response carries a warning that child FKs need manual update.
4. **LocalizedModel content** - if the snapshot includes a `localized` array, inserts each language row into `{primary}_content` with the new `parent_id`.
5. **Dual audit log** - writes both to `trash_log` (`action='trash-restore'`, `restored_by`, `restored_at`, `original_id`, `new_id`, `used_original_id`) AND to the channel named in `log_table` (`action='restore'`, `record_id=<new_id>`) so the restore appears in the record's Logger Protocol.

### TrashRouter

**File:** `application/raptor/trash/TrashRouter.php`

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/trash` | GET | `trash` |
| `/dashboard/trash/list` | GET | `trash-list` |
| `/dashboard/trash/view/{uint:id}` | GET | `trash-view` |
| `/dashboard/trash/restore/{uint:id}` | POST | `trash-restore` |
| `/dashboard/trash/delete` | DELETE | `trash-delete` |
| `/dashboard/trash/empty` | DELETE | `trash-empty` |

### Delete Strategy

Content modules now use **hard delete** with Trash backup instead of soft delete:

| Strategy | Applies to | Method |
|----------|-----------|--------|
| **Hard delete + Trash** | News, Pages, Products, Orders, Reviews, Comments, Messages, Files, References, Settings, DevRequests, DevResponses, Menus, Texts, Languages | `deleteById()` after `TrashModel::store()` |
| **Soft delete** (is_active=0) | Users, Organizations | `deactivateById()` |

Controllers that changed from `deactivate()` to `delete()`:
- `NewsController` (route: `/dashboard/news/delete`)
- `PagesController` (route: `/dashboard/pages/delete`)
- `ProductsController` (route: `/dashboard/products/delete`)
- `OrdersController` (route: `/dashboard/orders/delete`)
- `ReviewsController` (route: `/dashboard/reviews/delete`)
- `CommentsController` (route: `/dashboard/news/comments/delete`)
- `MessagesController` (route: `/dashboard/messages/delete`)
- `FilesController` (route: `/dashboard/files/{table}/delete`)
- `ReferencesController` (route: `/dashboard/references/delete`)
- `DevRequestController` (route: `/dashboard/dev-requests/delete`)

---

## Event System

### EventDispatcher

**File:** `application/raptor/event/EventDispatcher.php`
**Implements:** `Psr\EventDispatcher\EventDispatcherInterface`

PSR-14 compliant event dispatcher. Iterates through listeners from `ListenerProvider` and calls each one with the event object.

#### `__construct(ListenerProvider $provider)`
Takes a `ListenerProvider` instance.

#### `dispatch(object $event): object`
Dispatches an event to all registered listeners.

### ListenerProvider

**File:** `application/raptor/event/ListenerProvider.php`
**Implements:** `Psr\EventDispatcher\ListenerProviderInterface`

Registers and provides listeners for event types.

#### `addListener(string $eventClass, callable $listener): void`
Registers a listener for a specific event class.

#### `getListenersForEvent(object $event): iterable`
Returns all listeners registered for the given event's class.

### ContentEvent

**File:** `application/raptor/event/ContentEvent.php`

Event dispatched for content management actions.

| Property | Type | Description |
|----------|------|-------------|
| `$type` | string | Content type (`'news'`, `'page'`, `'product'`, etc.) |
| `$action` | string | Action performed (`'insert'`, `'update'`, `'delete'`, `'publish'`) |
| `$title` | string | Content title |
| `$id` | int | Content record ID |
| `$user` | string | User who performed the action |

### UserEvent

**File:** `application/raptor/event/UserEvent.php`

Event dispatched for user-related actions.

| Property | Type | Description |
|----------|------|-------------|
| `$action` | string | Action (`'signup_request'`, `'approved'`) |
| `$username` | string | Username |
| `$email` | string | Email address |

### OrderEvent

**File:** `application/raptor/event/OrderEvent.php`

Event dispatched for order-related actions.

| Property | Type | Description |
|----------|------|-------------|
| `$action` | string | Action (`'new'`, `'status_changed'`, `'review'`) |
| `$orderId` | ?int | Order ID |
| `$customer` | string | Customer name |
| `$email` | ?string | Customer email |
| `$product` | ?string | Product title |
| `$quantity` | ?int | Order quantity |
| `$oldStatus` | ?string | Previous status (for status change) |
| `$newStatus` | ?string | New status (for status change) |

### DevRequestEvent

**File:** `application/raptor/event/DevRequestEvent.php`

Event dispatched for development request actions.

| Property | Type | Description |
|----------|------|-------------|
| `$action` | string | Action (`'new_request'`, `'new_response'`) |
| `$title` | string | Request title |
| `$id` | int | Request ID |

### Usage in Controllers

```php
// Dispatch a content event after creating news
$this->dispatch(new ContentEvent(
    'insert', 'news', $record['title'], $id
));
```
