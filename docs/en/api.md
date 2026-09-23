# Raptor API Reference (EN)

> Detailed reference for all modules, classes, and methods.

---

## Table of Contents

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

## Dashboard\Controller

**File:** `application/dashboard/Controller.php`
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
Creates a FileTemplate with auto-injected variables: `user`, `index`, `localization`, `csrf_token`, `waf_body_encoding`. Registers the `text`, `link` and `pattern` filters (`pattern` returns the route pattern with its `{placeholders}` intact, for client-side JS rendering).

#### `respondJSON(array $response, int|string $code = 0): void`
Outputs a JSON response with `Content-Type: application/json` header. When `$code` is a valid HTTP status (100-599) it is applied via `headerResponseCode()`; otherwise the response stays `200`.

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

#### `hasService(string $id): bool`
Returns whether the container has the given service (`false` when there is no container).

#### `invalidateCache(string ...$keys): void`
Deletes specified cache keys. Use `{code}` placeholder for language-specific keys (auto-iterates all languages). Fail-safe: silently skips if cache is unavailable.

```php
$this->invalidateCache('pages_nav.{code}', 'featured_pages.{code}');
$this->invalidateCache('texts.{code}');
$this->invalidateCache('languages');
```

#### `headerResponseCode(int|string $code): void`
Sets the HTTP response code only when `$code` is numeric and within the standard HTTP range 100-599 (RFC 9110). Non-numeric, out-of-range, or `200` (default) values are ignored, so non-standard codes are never emitted.

#### `getScriptPath(): string`
Returns the script path (subdirectory support).

#### `getDocumentRoot(): string`
Returns the document root path.

#### `getMountPath(): string`
Returns the mount path of the running Application (e.g. `/dashboard`, or `''` when mounted at the root).

#### `setLanguageCode(string $code): void`
Writes the language code to the session key provided by `LocalizationMiddleware` (`localization['session_key']`); no-op when the key is `null` (Web).

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

## Dashboard\Application

**File:** `application/dashboard/Application.php`
**Extends:** `codesaur\Http\Application\Application`

The Dashboard Application. Registers the middleware pipeline and all routers.

The PDO connection is opened once in `public_html/index.php` via
`\Dashboard\DatabaseConnection::connect()` and reaches the Application as the
request's `pdo` attribute.

### Constructor Pipeline

1. `ErrorHandler` - Error handling
2. `MethodOverrideMiddleware` - Restores PUT/PATCH/DELETE from `X-HTTP-Method-Override` (WAF verb-block workaround); runs before Session/routing so the real verb is visible everywhere
3. `BodyEncodingMiddleware` - base64-decodes form fields sent with `X-Body-Encoding` (WAF body-inspection workaround)
4. `SessionMiddleware` - Session management
5. `JWTAuthMiddleware` - JWT authentication
6. `ContainerMiddleware` - DI Container
7. `LocalizationMiddleware` - Multi-language
8. `SettingsMiddleware` - System settings
9. `LoginRouter`, `UsersRouter`, `OrganizationRouter`, `RBACRouter`, `LocalizationRouter`, `ContentsRouter`, `LogsRouter`, `MigrationRouter`, `TrashRouter`, `TemplateRouter`, `HomeRouter`, `ShopRouter` (products + orders + reviews), `ManualRouter`, `Development\DevelopmentRouter` (dev requests live in `application/dashboard/development/`), `File\FileRouter`, `Badge\BadgeRouter` (the sidebar badge system lives in `application/dashboard/badge/`)

`CsrfMiddleware` is NOT in the app-wide pipeline - it is attached per-route to each mutating route in the router.

---

## Authentication

### JWTAuthMiddleware

**File:** `application/dashboard/authentication/JWTAuthMiddleware.php`
**Implements:** `MiddlewareInterface`

#### `generate(array $data): string`
Generates a JWT token. Payload includes `iat`, `exp`, `seconds` + `$data`.

#### `validate(string $jwt): array`
Decodes and validates JWT. Throws `RuntimeException` if expired. Requires `user_id` and `organization_id`.

#### `process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface`
1. Reads `$_SESSION['RAPTOR_JWT']`
2. Validates JWT
3. Fetches user profile from DB
4. Loads RBAC permissions (cached as `rbac.{userId}`) - BEFORE the organization check, so the coder role is known
5. Verifies organization access: regular users need an `organizations_users` membership row; `system_coder` is a cross-tenant superuser and only needs the target organization to be active (access is derived from the role - no membership row is required or created)
6. Creates `User` object and adds to request attributes
7. On failure, redirects to `/dashboard/login` - except for paths whose second segment is `login` or `protected` (`/dashboard/login/*`, `/dashboard/protected/*`), which fall through to their controller as an anonymous request (no `user` attribute) and must perform their own `isUserAuthorized()` check. For browser page loads (GET/HEAD with `Accept: text/html`, excluding the dashboard root/home) the original path + query is passed as `?redirect=...`, which `LoginController` sanitizes (same-origin path under the dashboard mount only) and `login.html` navigates to after a successful sign-in

### SessionMiddleware

**File:** `application/dashboard/SessionMiddleware.php`
**Implements:** `MiddlewareInterface`

Shared middleware for both Dashboard and Web apps.
Starts PHP session and releases write-lock early on read-only routes.

Raptor sets the session cookie lifetime to 30 days from code (`session_set_cookie_params(...)`); the server-side `gc_maxlifetime` / `save_path` use PHP / host config. To tune session longevity per host (or remove that line and rely on php.ini), see [SESSION-LIFETIME.md](SESSION-LIFETIME.md).

Constructor accepts a `needsWrite` closure to define which routes need session writes:
- Dashboard: `fn($path, $method) => str_contains($path, '/login') || empty($_SESSION['CSRF_TOKEN'])` (the second clause keeps the session writable so a missing CSRF token can be generated)
- Web: `fn($path, $method) => str_starts_with(preg_replace('#^/[a-z]{2}(?=/|$)#', '', $path), '/session/')` (strips a leading language prefix, then matches the `/session/` route prefix)

If closure is null, all routes are read-only (session_write_close on every request).

### LoginRouter

**File:** `application/dashboard/authentication/LoginRouter.php`

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

**File:** `application/dashboard/authentication/User.php`

| Property | Type | Description |
|----------|------|-------------|
| `$profile` | `array` | User profile data |
| `$organization` | `array` | Organization data |

The RBAC matrix (`RBAC::jsonSerialize()` output, keyed `{alias}_{role} => [permission => true]`) is held in a private readonly `$rbac` property and is only reachable through `is()` / `hasRoleAlias()` / `can()`.

| Method | Description |
|--------|-------------|
| `is(string $role): bool` | Check role |
| `hasRoleAlias(string $alias): bool` | Check whether the user holds ANY role under the given alias (role keys are `{alias}_{name}`) - used to gate multi-tenant visibility by role alias |
| `can(string $permission, ?string $role = null): bool` | Check permission; when `$role` is given, only inside that role. `system_coder` always `true` |

---

## User

### UsersModel

**File:** `application/dashboard/user/UsersModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `users`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `username` | varchar(128), unique | Login name |
| `password` | varchar(255), default `''` | Bcrypt hash |
| `first_name` | varchar(128) | First name |
| `last_name` | varchar(128) | Last name |
| `phone` | varchar(128) | Phone |
| `email` | varchar(128), unique | Email address |
| `photo` | varchar(255) | Avatar public URL |
| `photo_file` | varchar(255) | Avatar physical file path |
| `photo_size` | int | Avatar size (bytes) |
| `code` | varchar(2) | Preferred language code |
| `is_active` | tinyint, default 1 | Active status |
| `created_at` | datetime | Created date |
| `created_by` | bigint | Created by user |
| `updated_at` | datetime | Updated date |
| `updated_by` | bigint | Updated by user |

---

## Organization

### OrganizationModel

**File:** `application/dashboard/organization/OrganizationModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `organizations`

### OrganizationUserModel

**File:** `application/dashboard/organization/OrganizationUserModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `organizations_users`

User-organization relationship table.

---

## RBAC

### RBAC

**File:** `application/dashboard/rbac/RBAC.php`

Loads all roles and permissions for a user and returns them via `jsonSerialize()`.

### Role

**File:** `application/dashboard/rbac/Role.php`

Runtime value object (not a Model). `fetchPermissions(\PDO $pdo, int $role_id)` loads the role's permissions as `{alias}_{name} => true`; `hasPermission(string $permissionName): bool` is an O(1) lookup. `RBAC` holds one `Role` per `{alias}_{name}` role key.

### Roles

**File:** `application/dashboard/rbac/Roles.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `rbac_roles`

### Permissions

**File:** `application/dashboard/rbac/Permissions.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `rbac_permissions`

### RolePermission

**File:** `application/dashboard/rbac/RolePermission.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `rbac_role_permission`

Role-Permission relationships (`role_id`, `permission_id`, `alias`).

### UserRole

**File:** `application/dashboard/rbac/UserRole.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `rbac_user_role`

User-Role relationships.

---

## Files

### FilesModel

**File:** `application/dashboard/file/FilesModel.php`
**Extends:** `codesaur\DataObject\Model`

Stores file metadata. `setTable($name)` maps to the `{name}_files` table (e.g. `setTable('news')` -> `news_files`).

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `record_id` | bigint | Related record ID in the parent table (0 = unattached) |
| `file` | varchar(255) | Absolute local file path on the server |
| `path` | varchar(255), default `''` | Public URL of the file |
| `size` | int | File size (bytes) |
| `type` | varchar(24) | File type (image, audio, video, application...) |
| `mime_content_type` | varchar(127) | MIME type |
| `keyword` | varchar(32) | Keyword |
| `description` | varchar(255) | Description |
| `created_at` | datetime | Created date |
| `created_by` | bigint | Created by user |
| `updated_at` | datetime | Updated date |
| `updated_by` | bigint | Updated by user |

### FilesController

**File:** `application/dashboard/file/FilesController.php`

| Method | Description |
|--------|-------------|
| `index()` | File management page |
| `list(string $table)` | JSON file list |
| `upload()` | Upload file (move only, no DB record) |
| `post(string $table, int $record_id = 0)` | Upload + register in `{table}_files`; via the route only table `files` is accepted (`record_id` stays 0) |
| `modal(string $table)` | File selection modal |
| `update(string $table, int $id)` | Update file metadata |
| `delete(string $table)` | Deletes the DB record (only table `files`) and stores it in Trash; the physical file is left on disk. Requires `system_content_delete`, or ownership of an unattached file |

### FileRouter - File Routes

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/files` | GET | `files` |
| `/dashboard/files/list/{table}` | GET | `files-list` |
| `/dashboard/files/upload` | POST | `files-upload` |
| `/dashboard/files/post/{table}` | POST | `files-post` |
| `/dashboard/files/modal/{table}` | GET | `files-modal` |
| `/dashboard/files/{table}/{uint:id}` | PATCH | `files-update` |
| `/dashboard/files/{table}/delete` | DELETE | `files-delete` |

**Protected files** (same `FileRouter`):

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/protected/file` | GET | `protected-file-read` |

`Dashboard\File\ProtectedFilesController` (`application/dashboard/file/`) serves files from the document-root-external `/protected` folder. It is a reference implementation to customize (no shipped module uses protected storage). Authorization is the `authorizeRead(string $relativePath): bool` hook - default is permissive (any authenticated user; `system_coder` always). Tighten it with your module's index/view permission or tenant-ownership rule to restrict access to sensitive files - edit the method in place.

---

## Content - News

### NewsModel

**File:** `application/dashboard/content/news/NewsModel.php`
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
| `code` | varchar(2) | Language code, or `*` for a language-neutral record shown on every language |
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
Returns recently published news for the given language plus language-neutral (`code='*'`) records (`code IN (:code, '*')`). Selects id, slug, title, description, photo, code, type, category, is_featured, comment, published_at, created_at, source. Excludes `read_count` (dynamic) for cache compatibility. Used by HomeController with cache key `recent_news.{code}`.

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
| `/dashboard/news/view/{uint:id}` | GET | `news-view` |
| `/dashboard/news/delete` | DELETE | `news-delete` |
| `/dashboard/news/reset` | DELETE | `news-sample-reset` |

---

## Content - Comments

### CommentsModel

**File:** `application/dashboard/content/news/CommentsModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `news_comments`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `news_id` | bigint | News article reference (FK -> news) |
| `parent_id` | bigint | Parent comment for 1-level reply (FK -> news_comments, self) |
| `created_by` | bigint | Author user (FK -> users, null for guest) |
| `name` | varchar(255) | Commenter name |
| `email` | varchar(255) | Commenter email |
| `comment` | text | Comment text |
| `created_at` | datetime | Created date |

> **Note:** The `is_active` column was removed. Deletion is now handled via hard delete with Trash backup.

### CommentsController (Dashboard)

**File:** `application/dashboard/content/news/CommentsController.php`
**Extends:** `Dashboard\Controller`

| Method | Description |
|--------|-------------|
| `index()` | Comments management page |
| `list()` | JSON comment list (all comments joined with news title) |
| `view(int $id)` | Redirects to the news view page (`news-view`) `#comments` anchor; `$id` is the news ID |
| `comment(int $id)` | Admin writes a root comment on news `$id` (requires `system_content_index`) |
| `reply(int $id)` | Admin replies to root comment `$id` (1-level only, requires `system_content_update`) |
| `delete()` | Hard delete comment and its replies (stores in Trash) |

### ContentsRouter - Comment Routes

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/news/comments` | GET | `comments` |
| `/dashboard/news/comments/list` | GET | `comments-list` |
| `/dashboard/news/comments/{uint:id}` | GET | `comments-view` |
| `/dashboard/news/{uint:id}/comment` | POST | `news-comment` |
| `/dashboard/news/comment/{uint:id}/reply` | POST | `news-comment-reply` |
| `/dashboard/news/comments/delete` | DELETE | `comments-delete` |

---

## Content - Messages

### MessagesModel

**File:** `application/dashboard/content/messages/MessagesModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `messages`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `name` | varchar(255) | Sender name |
| `phone` | varchar(50) | Sender phone |
| `email` | varchar(255) | Sender email |
| `message` | text | Message text |
| `code` | varchar(2) | Language code |
| `is_read` | tinyint (default: 0) | Read status (0=new, 1=read, 2=replied) |
| `replied_note` | text | Admin reply note |
| `created_at` | datetime | Created date |

> **Note:** The `is_active` column was removed. Deletion is now handled via hard delete with Trash backup.

### MessagesController (Dashboard)

**File:** `application/dashboard/content/messages/MessagesController.php`
**Extends:** `Dashboard\Controller`

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

**File:** `application/dashboard/content/page/PagesModel.php`
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
| `code` | varchar(2) | Language code, or `*` for a language-neutral record shown on every language |
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
Returns tree-structured navigation for published pages of the given language plus `code='*'` pages, where `type` is `menu` or ends with `-menu`. Ordered by position, id. The tree (parent -> children -> `submenu`) is built by the private helper `buildTree(array $pages, int $parentId = 0)`.

#### `getFeaturedLeafPages(string $code): array`
Returns featured (`is_featured=1`, published) pages of the given language plus `code='*'` pages that have no children (leaf nodes only).

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
| `/dashboard/pages/view/{uint:id}` | GET | `page-view` |
| `/dashboard/pages/delete` | DELETE | `page-delete` |
| `/dashboard/pages/reset` | DELETE | `pages-sample-reset` |

---

## Content - References

### ReferenceModel

**File:** `application/dashboard/content/reference/ReferenceModel.php`
**Extends:** `codesaur\DataObject\LocalizedModel`

Reference table with dynamic table name: `setTable('questions')` -> `reference_questions` (+ `reference_questions_content`). Primary columns: `id`, `keyword` (varchar 128, unique), `category` (varchar 32), `created_at`/`created_by`, `updated_at`/`updated_by`; content columns: `title` (varchar 255), `content` (mediumtext).

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

**File:** `application/dashboard/content/settings/SettingsModel.php`
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
| `created_at` | datetime | Created date |
| `created_by` | bigint | Created by user (FK -> users) |
| `updated_at` | datetime | Updated date |
| `updated_by` | bigint | Updated by user (FK -> users) |

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
Returns the last settings record from `getRows()` (with `localized` content). Returns `[]` if empty.

### SettingsMiddleware

**File:** `application/dashboard/content/settings/SettingsMiddleware.php`
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
| `RAPTOR_CONTACT_EMAIL_TO` | email | - | Recipient email for contact messages (empty = notification off) |
| `RAPTOR_ORDER_EMAIL_TO` | email | - | Recipient email for orders (empty = notification off) |
| `RAPTOR_COMMENT_EMAIL_TO` | email | - | Recipient email for comments (empty = notification off) |
| `RAPTOR_REVIEW_EMAIL_TO` | email | - | Recipient email for product reviews (empty = notification off) |

Any other `name` is rejected with 403.

**Type behavior:**
- `bool` - Toggles current value (ignores `value` field). Response includes `value: true|false`. Implemented but not used by the shipped templates
- `email` - Validates email format via `filter_var()`. Empty string clears the value
- `string` - No validation, stores as-is

Used by messages-index, orders-index, comments-index, reviews-index templates for admin email notification settings (visible to `system_coder` only). The on/off switch in those templates sends `type: 'email'` with an empty `value`; the controllers derive the notification flag from whether the `RAPTOR_*_EMAIL_TO` value is empty.

---

## Localization

### LanguageModel

**File:** `application/dashboard/localization/language/LanguageModel.php`
**Extends:** `codesaur\DataObject\Model`

Language registration table.

### TextModel

**File:** `application/dashboard/localization/text/TextModel.php`
**Extends:** `codesaur\DataObject\LocalizedModel`

Translation texts (tables `localization_text` / `localization_text_content`; content column `text` varchar(255)).

#### `retrieve(?string $code = null): array`
With `$code` returns a flat `keyword -> text` map for that language (used by `LocalizationMiddleware`, cached as `texts.{code}`). With `null` returns all translations as `keyword -> language code -> text`.

### LocalizationMiddleware

**File:** `application/dashboard/localization/LocalizationMiddleware.php`
**Implements:** `MiddlewareInterface`

Shared middleware for both Dashboard and Web apps. Constructor accepts a nullable session key:
- Dashboard: `new LocalizationMiddleware()` - defaults to `RAPTOR_LANGUAGE_CODE`, the language is stored in the session
- Web: `new LocalizationMiddleware(null)` - no session; the language comes from the URL prefix

Resolution order: the `language_prefix` request attribute (set by `public_html/index.php` from a `/xx/` URL prefix; an inactive code throws a 404) -> the session value (only when a session key was given) -> the default language (first active language). On the public web the default language has no prefix (`/news/x`), every other language is prefixed (`/en/news/x`); the prefix is the Web application's mount path, so `|link` / `generateRouteLink()` prepend it automatically.

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

**File:** `application/dashboard/log/Logger.php`
**Extends:** `\Psr\Log\AbstractLogger`

PSR-3 standard logging system. Stores logs in database.

#### `setTable(string $name)`
Sets the log channel; the physical table is `{$name}_log` (`setTable('dashboard')` -> `dashboard_log`) and is created with its indexes on first use.

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

**File:** `application/dashboard/mail/Mailer.php`

Sends email. `send()` selects transport via `RAPTOR_MAIL_TRANSPORT` env var: `brevo` (default), `smtp`, `mail`.

---

## Database

### DatabaseConnection

**File:** `application/dashboard/DatabaseConnection.php`

Single helper that owns every PDO handshake. The HTTP entry point
(`public_html/index.php`) and tests all call
`\Dashboard\DatabaseConnection::connect()` and receive an identical PDO.

- `driver(): string` - reads `RAPTOR_DB_DRIVER` from `.env` (`mysql` |
  `pgsql`). Throws an `Exception` for any other value.
- `connect(): \PDO` - connects to MySQL or PostgreSQL based on the driver
  and returns the PDO instance. The database must already exist - there is
  no implicit auto-create.

The PDO instance created in `public_html/index.php` is passed to the
Application as `$request->withAttribute('pdo', $pdo)`. Controllers pick it
up automatically inside `Dashboard\Controller::__construct()` as `$this->pdo`.

### ContainerMiddleware

**File:** `application/dashboard/ContainerMiddleware.php`

Injects a PSR-11 DI Container into the request. It registers the `cache`,
`mailer`, `template_service`, `discord`, and `events` service factories.
Factories that need PDO read it from the request's `pdo` attribute (set
by the entry point).

---

## SpamProtectionTrait

**File:** `application/dashboard/SpamProtectionTrait.php`

Unified spam protection for public forms: honeypot field, HMAC token + timestamp, session rate limit, Cloudflare Turnstile (active only when `RAPTOR_TURNSTILE_SECRET_KEY` is set) and a link-count filter.

### Methods

#### `getTurnstileSiteKey(): string`
Returns the Turnstile site key from ENV configuration. Returns empty string if not configured.

#### `generateSpamToken(string $formName, int $ts): string`
HMAC-SHA256 of `"$formName-$ts"` keyed with `RAPTOR_JWT_SECRET`; embed it as `_token` next to `_ts` in the form. The secret is mandatory - `getSpamSecret()` throws `RuntimeException` when `RAPTOR_JWT_SECRET` is missing (there is no default secret on purpose).

#### `validateSpamProtection(array $parsed, string $formName, string $sessionKey, int $rateLimit = 10, int $minTime = 2): void`
Validates the POST body: the honeypot `website` field must be empty, `_token` must match `generateSpamToken($formName, $_ts)`, at least `$minTime` seconds and at most 3600 seconds must have passed since `_ts`, `$_SESSION[$sessionKey]` must be older than `$rateLimit` seconds, then the Turnstile token is verified when configured. Throws `\Exception` with code 400/403/429 on failure.

#### `checkLinkSpam(string $text, int $maxLinks = 2): void`
Throws `\Exception('Too many links', 400)` when the text contains more than `$maxLinks` URLs (`http(s)://` or `www.`).

### Used By

- `Web\Service\ContactController` - Contact form submission
- `Web\Content\NewsController` - News comment submission
- `Web\Shop\ShopController` - Order and review submission
- `Dashboard\Authentication\LoginController` - login, signup and forgot-password forms (its own `spamCheck()` reuses `generateSpamToken()` / `getTurnstileSiteKey()`; Turnstile is verified on signup only)

---

## CsrfMiddleware

**File:** `application/dashboard/CsrfMiddleware.php`
**Implements:** `Psr\Http\Server\MiddlewareInterface`

CSRF token validation for dashboard mutating requests. **Per-route** middleware - attached to each mutating route in the router via `->middleware([CsrfMiddleware::class])` (with `use Dashboard\CsrfMiddleware;`).

### How It Works

1. The middleware ONLY validates (it does not generate tokens or set request attributes)
2. GET/HEAD/OPTIONS requests pass through without validation (protects the GET side of `GET_POST`/`GET_PUT` compound routes)
3. Other methods (POST, PUT, PATCH, DELETE) compare `$_SESSION['CSRF_TOKEN']` against the `X-CSRF-TOKEN` header
4. Mismatched or missing token returns a 403 JSON response
5. Login routes are exempt - the middleware is simply not attached there (token is created during login)

### Token Provisioning

- Token is generated at login and stored in `$_SESSION['CSRF_TOKEN']`
- As a fallback for old sessions, `Controller::template()` generates it when an authorized user has none and the session is writable

### Frontend Integration

- Token delivered via `<meta name="csrf-token">` in `dashboard.html`
- `csrfFetch()` wrapper in `dashboard.js` auto-attaches `X-CSRF-TOKEN` header
- Use `csrfFetch()` for all POST/PUT/PATCH/DELETE requests in dashboard modules
- Standalone pages (e.g. login) use plain `fetch()` since `dashboard.js` is not loaded

---

## HtmlValidationTrait

**File:** `application/dashboard/content/HtmlValidationTrait.php`

Server-side HTML content validation. Used by Pages, News, Products controllers on insert/update.

#### `validateHtmlContent(string $html): void`
Checks for unclosed HTML comments (`<!-- -->`), parses with DOMDocument, compares text length before/after parsing. Throws `InvalidArgumentException` if content loss exceeds 20% (indicating broken tags or unclosed comments).

### Used By

- `Dashboard\Content\NewsController` - News insert/update
- `Dashboard\Content\PagesController` - Page insert/update
- `Dashboard\Shop\ProductsController` - Product insert/update

---

## DashboardTrait

**File:** `application/dashboard/template/DashboardTrait.php`

Provides dashboard UI rendering, permission alerts, sidebar menu generation, and user detail retrieval.

Controllers using this trait MUST NOT define methods with the same names as the trait's public API (`dashboardTemplate`, `dashboardProhibited`, `modalProhibited`, `getUserMenu`, `getUserOrganizations`) - a class method silently overrides the trait method and breaks the trait's internal calls.

#### `dashboardTemplate(string $template, array $vars = []): FileTemplate`
Renders content within the `dashboard.html` layout with sidebar menu, the topbar organization switcher list (`user_organizations`) and system settings. Loads menu from cache (`menu.{code}` key). Also sets `raptor_name`, `raptor_version`, `raptor_modified` (read from `composer.json` `name` / `extra.version` / `extra.modified`, shown in the sidebar version line; null when absent) and `has_web` (`true` when `Web\Application` exists - toggles the "Visit Website" sidebar link).

#### `dashboardProhibited(?string $alert = null, int|string $code = 0): FileTemplate`
Shows permission denial alert within dashboard layout.

#### `modalProhibited(?string $alert = null, int|string $code = 0): FileTemplate`
Shows permission denial modal (standalone, no layout wrapper).

#### `getUserMenu(): array`
Builds the sidebar menu array filtered by visibility (`is_visible=1`), organization alias and user permission; parents whose submenu ends up empty are removed.

#### `getUserOrganizations(): array`
Returns the list of active organizations for the topbar organization switcher as `[['id' => ..., 'name' => ..., 'logo' => ...], ...]`. All active organizations for `system_coder` (cross-tenant role), membership-only for everyone else; the currently selected organization is always included. Organization `id=1` (the system's primary organization) always comes first when present; the rest are sorted by name. The dropdown is shown when the list has more than one entry, and gains a search filter above 10 entries.

#### `retrieveUsersDetail(?int ...$ids)`
Protected helper. Returns `[user_id => "username - First Last (email)"]` map for audit/log display (empty array on error). Returns all users if no IDs provided.

---

## FileController

**File:** `application/dashboard/file/FileController.php`
**Extends:** `Dashboard\Controller`

Base class for file upload, validation, storage, and image optimization. Extended by controllers that handle file uploads (FilesController, SettingsController, UsersController, etc.).

### Key Methods

| Method | Description |
|--------|-------------|
| `setFolder(string $folder)` | Sets upload directory (e.g. `/users/1`, `/pages/22`) |
| `getFilePublicPath(string $fileName)` | Returns public URL path for a file |
| `allowExtensions(array $exts)` | Whitelist specific file extensions |
| `allowImageOnly()` | Restrict to image extensions only |
| `allowCommonTypes()` | Allow common web file types (images, docs, media, archives) |
| `allowAnything()` | Clears the extension whitelist (all extensions allowed) |
| `setSizeLimit(int $size)` | Set max file size in bytes |
| `setOverwrite(bool $overwrite)` | Enable/disable overwrite on duplicate names |
| `moveUploaded(string\|UploadedFileInterface $uploadedFile, bool $optimize = false, int $mode = 0755): array\|false` | Main upload handler (a string is the key in `getUploadedFiles()`): validates, stores, returns `[path, file, size, type, mime_content_type]`; `false` on failure (see `getLastUploadError()`) |
| `getLastUploadError(): int` | `UPLOAD_ERR_*` code of the last failed `moveUploaded()` |
| `optimizeImage(string $filePath): bool` | Resizes/compresses JPEG/PNG/GIF/WebP (max width `RAPTOR_CONTENT_IMG_MAX_WIDTH`, default 1920; quality `RAPTOR_CONTENT_IMG_QUALITY`, default 90), applies EXIF orientation; replaces the file only when it was rotated or became more than 10% smaller |
| `getMaximumFileUploadSize()` | Returns `MIN(post_max_size, upload_max_filesize)` in bytes |
| `formatSizeUnits(?int $bytes)` | Formats bytes as human-readable string (e.g. `10.5mb`) |
| `unlinkByName(string $fileName)` | Deletes file by name from upload folder |

---

## AIHelper

**File:** `application/dashboard/content/AIHelper.php`
**Extends:** `Dashboard\Controller`

OpenAI API integration for moedit WYSIWYG editor. Provides HTML content enhancement (Shine) and image text recognition (OCR/Vision).

#### `moeditAI(): void`
POST `/dashboard/content/moedit/ai` - Main endpoint with two modes:

**HTML mode** (`mode: 'html'`): Enhances HTML content using `RAPTOR_OPENAI_MODEL` (.env, default `gpt-5-mini`). Request body: `{mode, html, prompt}`.

**Vision mode** (`mode: 'vision'`): Extracts text from images using `RAPTOR_OPENAI_VISION_MODEL` (.env, default `gpt-5.1`). Request body: `{mode, images[], prompt}`.

Response: `{status: 'success', html: '...'}` or `{status: 'error', message: '...'}`.

Requires `RAPTOR_OPENAI_API_KEY` in `.env`. The caller must be logged in AND hold one of `system_content_insert`, `system_content_update`, `system_product_insert`, `system_product_update` (403 otherwise). Rate-limited to 30 OpenAI calls per user per 60 seconds via the cache key `ai_ratelimit.{userId}` (vision: one call per image; 429 when exceeded; skipped when no cache service is available). Vision mode accepts at most 8 images per request (400 otherwise). Route name `moedit-ai`, CSRF-protected.

---

## Badge System

### BadgeController

**File:** `application/dashboard/badge/BadgeController.php`
**Extends:** `Dashboard\Controller`

Dashboard sidebar badge system showing unseen activity counts per module. Reads from existing `*_log` tables.

#### `list(): void`
GET `/dashboard/badges` - Returns JSON with badge counts per module. Groups badges by color (green=create, blue=update, red=delete, info=new comment/review). Filters by admin permissions (`PERMISSION_MAP`). Excludes the admin's own actions except for `/trash` (own deletions are meant to be seen there). Modules listed in `orgScopedModules()` (default empty) are further filtered to the viewing admin's current organization unless the viewer is `system_coder` or `isSystemWideViewer()` (current organization `id=1`). `/manual` and `/migrations` get file-count badges instead of log counts. First-time users get 30-day lookback.

#### `seen(): void`
POST `/dashboard/badges/seen` - Marks a module as seen. Updates `checked_at` timestamp for the current admin.

### Constants

- `BADGE_MAP` - Maps `[log_table][action]` to `[module_path, color]`
- `PERMISSION_MAP` - Maps module path to required permission (`null` = any admin, `'system_x'` = permission check, `'role:system_coder'` = role check)

Both maps key on the **mount-naive** path (`/news`, not `/dashboard/news`). The `/dashboard` mount prefix is added at runtime via `getMountPath()`, so changing the mount point in `index.php` does not require touching these maps.

### BadgeRouter

**File:** `application/dashboard/badge/BadgeRouter.php`

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/badges` | GET | `dashboard-badges` |
| `/dashboard/badges/seen` | POST | - |

### AdminBadgeSeenModel

**File:** `application/dashboard/badge/AdminBadgeSeenModel.php`
**Extends:** `codesaur\DataObject\Model`

Tracks when each admin last viewed each module. Columns: `admin_id`, `module`, `checked_at`, `last_seen_count`. Unique index on `(admin_id, module)`.

---

## MenuModel

**File:** `application/dashboard/template/MenuModel.php`
**Extends:** `codesaur\DataObject\LocalizedModel`

**Table:** `raptor_menu` (+ `raptor_menu_content` for the localized `title`)

Dashboard sidebar menu items with multilingual titles and parent/child hierarchy. `__initial()` adds the two user FKs, an index on `parent_id`, and seeds the default menu via `MenuSeed::seed()`.

### Columns

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `parent_id` | bigint (default: 0) | Parent menu ID (0 = root) |
| `icon` | varchar(64) | Bootstrap Icons class |
| `href` | varchar(255) | Menu link URL |
| `alias` | varchar(64) | Organization alias filter |
| `permission` | varchar(128) | Required permission to see menu item |
| `position` | smallint (default: 100) | Sort order |
| `is_visible` | tinyint (default: 1) | Visibility toggle |
| `created_at` | datetime | Created date |
| `created_by` | bigint | Created by user (FK -> users) |
| `updated_at` | datetime | Updated date |
| `updated_by` | bigint | Updated by user (FK -> users) |
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
| `/dashboard/home` | GET | `home` |
| `/dashboard` | GET | - |
| `/dashboard/search` | GET | `dashboard-search` |
| `/dashboard/stats` | GET | `dashboard-stats` |
| `/dashboard/log-stats` | GET | `dashboard-log-stats` |

The named `home` route lives at `/dashboard/home`; `/dashboard` (root) stays registered WITHOUT a name as an alias to the same `HomeController::index`. Sidebar active-detection is prefix-based, so a home link at the root would be active on every page; the root itself is kept because the public web layout links to `{{ index }}/dashboard` directly.

### SearchController

**File:** `application/dashboard/home/SearchController.php`
**Extends:** `Dashboard\Controller`

#### `search(): void`
Dashboard global search - powers the topbar search modal (Ctrl+K). Searches across news, pages, products, orders, users, organizations, dev-requests, messages, comments and reviews using LIKE queries. Each source block is gated with the SAME permission (or row-level filter) its module's index page requires (news/pages/messages/comments -> `system_content_index`, products/orders/reviews -> `system_product_index`, users -> `system_user_index`, organizations -> `system_organization_index`, dev-requests -> own/assigned rows unless `system_development`). Returns JSON.

### WebLogStatsController

**File:** `application/dashboard/home/WebLogStatsController.php`
**Extends:** `Dashboard\Controller`

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
| `/dashboard/manual/{file}` | GET | `manual-view` |

### ManualController

**File:** `application/dashboard/manual/ManualController.php`
**Extends:** `Dashboard\Controller`

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
ExceptionHandler -> MethodOverride -> BodyEncoding -> Container -> Session -> Localization (URL prefix only, no session key) -> Settings -> WebRouter

### WebRouter

**File:** `application/web/WebRouter.php`

| Route | Method | Name | Description |
|-------|--------|------|-------------|
| `/` | GET | `home` | Home page |
| `/page/{uint:id}` | GET | - | Page by ID (redirect to slug) |
| `/page/{slug}` | GET | `page` | View page |
| `/contact` | GET | `contact` | Contact page |
| `/news/{uint:id}` | GET | - | News by ID (redirect to slug) |
| `/news/{slug}` | GET | `news` | View news |
| `/news/type/{type}` | GET | `news-type` | News by type/category |
| `/archive` | GET | `archive` | News archive |
| `/products` | GET | - | Product listing |
| `/product/{uint:id}` | GET | - | Product by ID (redirect to slug) |
| `/product/{slug}` | GET | `product` | View product |
| `/order` | GET | `order` | Order form |
| `/search` | GET | `search` | Search (`SearchController`) |
| `/sitemap` | GET | `sitemap` | Sitemap page |
| `/sitemap.xml` | GET | - | XML sitemap |
| `/rss` | GET | `rss` | RSS feed |
| `/favicon.ico` | GET | - | Favicon redirect / 204 |
| `/session/language/{code}` | GET | `language` | Redirect to that language's home (`/` or `/{code}/`); the layout's language dropdown links to the current page's per-language URL instead |
| `/session/contact-send` | POST | `contact-send` | Send contact message |
| `/session/order` | POST | `order-submit` | Submit order |
| `/session/news/{uint:id}/comment` | POST | `news-comment` | Submit news comment |
| `/session/product/{uint:id}/review` | POST | `product-review` | Submit product review |

Routes without a name are not referenced from templates or PHP (`|link` on them returns `#`).

### HomeController

**File:** `application/web/HomeController.php`
**Extends:** `TemplateController`

| Method | Description |
|--------|-------------|
| `index()` | Home page (latest 20 published news, cached by language) |
| `favicon()` | Returns favicon redirect or 204 No Content with cache headers |
| `language(string $code)` | Redirects (302) to that language's home (`/` for the default language, `/{code}/` otherwise); an inactive code falls back to the default. Writes nothing to the session - the web language comes from the URL prefix only |

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

### SearchController

**File:** `application/web/service/SearchController.php`
**Extends:** `TemplateController`

| Method | Description |
|--------|-------------|
| `search()` | Search across pages, news, products via `?q=` (min 2 chars, LIKE on title/slug/description/content/source/link, 20 rows per source, current language + `*` records) |

### SeoController

**File:** `application/web/service/SeoController.php`
**Extends:** `TemplateController`

| Method | Description |
|--------|-------------|
| `sitemap()` | Human-readable sitemap with page tree |
| `sitemapXml()` | XML sitemap for search engines |
| `rss()` | RSS 2.0 feed (latest 20 news + 20 products) |

### TemplateController

**File:** `application/web/template/TemplateController.php`
**Extends:** `Dashboard\Controller`

| Method | Description |
|--------|-------------|
| `webTemplate(string $template, array $vars = []): FileTemplate` | Merges web layout + content. Auto-maps title, code, description, photo from $vars to index layout SEO meta (`code='*'` is not mapped, the layout falls back to the current language). Sets `base_url`, `current_url`, `language_urls` (current page per language), `hreflang_urls` (only for pages existing in every language) and `canonical_url`. Loads settings, navigation, featured pages (cached). |

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

**File:** `application/dashboard/content/ContentsRouter.php`

Central router that registers all content module routes: News, Comments, Pages, References, Settings, Messages, Moedit AI. File routes live in `Dashboard\File\FileRouter` (`application/dashboard/file/FileRouter.php`).

---

## Shop

### ProductsModel

**File:** `application/dashboard/shop/ProductsModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `products`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto-increment |
| `slug` | varchar(255), unique | SEO-friendly URL slug |
| `title` | varchar(255) | Product title |
| `description` | varchar(255) | Short description |
| `content` | mediumtext | HTML content |
| `price` | decimal(12,2), default 0 | Price |
| `sale_price` | decimal(12,2) | Sale price |
| `sku` | varchar(64) | SKU code |
| `barcode` | varchar(64) | Barcode |
| `sizes` | text | Available sizes |
| `colors` | text | Available colors |
| `stock` | int, default 0 | Stock quantity |
| `link` | varchar(255) | External link |
| `photo` | varchar(255) | Cover image |
| `code` | varchar(2) | Language code, or `*` for a language-neutral record shown on every language |
| `type` | varchar(32), default 'product' | Product type |
| `category` | varchar(32), default 'general' | Category |
| `is_featured` | tinyint, default 0 | Featured product |
| `review` | tinyint, default 1 | Reviews enabled |
| `read_count` | bigint, default 0 | View count |
| `published` | tinyint, default 0 | Published status |
| `published_at` | datetime | Published date |
| `published_by` | bigint | Published by user |
| `created_at` | datetime | Created date |
| `created_by` | bigint | Created by user |
| `updated_at` | datetime | Updated date |
| `updated_by` | bigint | Updated by user |

#### `generateSlug(string $title): string`
Generates an SEO-friendly slug. Supports Mongolian Cyrillic transliteration.

#### `getExcerpt(string $content, int $length = 200): string`
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
| `/dashboard/products/view/{uint:id}` | GET | `product-view` |
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
| `/dashboard/orders/view/{uint:id}` | GET | `order-view` |
| `/dashboard/orders/{uint:id}/status` | PATCH | `order-status` |
| `/dashboard/orders/delete` | DELETE | `order-delete` |

---

## Notification

### DiscordListener

**File:** `application/dashboard/notification/DiscordListener.php`

PSR-14 event listener that sends Discord webhook notifications. Replaces the previous `DiscordNotifier` direct-call pattern. Registered via `ListenerProvider`.

#### `__construct(DiscordNotifier $notifier)`
Receives the `discord` container service; `DiscordNotifier` itself reads the webhook URL from the `RAPTOR_DISCORD_WEBHOOK_URL` env variable and skips sending when it is empty.

#### `onContentEvent(ContentEvent $event): void`
Handles content actions (insert, update, delete, publish) for News, Pages, Products, etc. Dedicated routes: `module='message'` + `action='new'` -> `newContactMessage()`, `module='comment'` + `action='insert'` -> `newComment()`, `module='review'` + `action='insert'` -> `newReview()`, `module='settings'` -> `settingsUpdated()` (the event `title` carries the section: `texts`/`files`/`options`); everything else -> `contentAction()`.

#### `onUserEvent(UserEvent $event): void`
Handles user-related events (`'signup_request'` -> `userSignupRequest()`, `'approved'` -> `userApproved()`); any other action is ignored.

#### `onOrderEvent(OrderEvent $event): void`
Handles order events (`'new'` -> `newOrder()`, `'status_changed'` -> `orderStatusChanged()`); any other action is ignored. Product reviews travel as `ContentEvent` (`module='review'`).

#### `onDevRequestEvent(DevRequestEvent $event): void`
Handles development request events (`'new'` -> `newDevRequest()`, `'updated'` -> `devRequestUpdated()`); any other action is ignored.

#### Color Constants

Defined on `DiscordNotifier` as `COLOR_*` (`COLOR_SUCCESS`, `COLOR_INFO`, ...).

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

**File:** `application/dashboard/development/DevelopmentRouter.php`

| Route | Method | Name | Description |
|-------|--------|------|-------------|
| `/dashboard/dev-requests` | GET | `dev-requests` | Request list |
| `/dashboard/dev-requests/list` | GET | `dev-requests-list` | JSON list |
| `/dashboard/dev-requests/create` | GET | `dev-requests-create` | Create form |
| `/dashboard/dev-requests/store` | POST | `dev-requests-store` | Submit request |
| `/dashboard/dev-requests/view/{uint:id}` | GET | `dev-requests-view` | View request |
| `/dashboard/dev-requests/respond` | POST | `dev-requests-respond` | Add response |
| `/dashboard/dev-requests/delete` | DELETE | `dev-requests-delete` | Delete (stores in Trash) |

Access rules: every route requires an authenticated user. Any logged-in user can create requests and can view, respond to and delete only their own or assigned ones (`created_by`/`assigned_to`). Users holding the `system_development` permission (permission record: alias=`system`, name=`development`) can view, respond to and delete any request.

---

## Migration

File-based, forward-only SQL migration system. State is derived entirely from the directory layout (no tracking table). Per-user folder: `database/migrations/{userId}-{username}/` holds pending files, `{userId}-{username}/ran/` holds applied files. `database/migrations/` is git-ignored.

### MigrationRunner

**File:** `application/dashboard/migration/MigrationRunner.php`

| Method | Description |
|--------|-------------|
| `__construct(\PDO $pdo, string $migrationsPath)` | PDO + path to migrations directory |
| `status(): array` | Returns `['folders' => [...]]` - pending/ran lists per user folder |
| `apply(string $folder, string $filename): array` | Run a pending file and move it to `ran/` on success. Returns `ok`, `sha256`, `statements`, `error?`, `moved_to?` |
| `scan(string $sql): array` | Forwards to `MigrationSecurityScanner::scan()` |
| `summarize(string $sql): string` | Short summary (first `--` line or first statements) |
| `splitStatements(string $sql): array` | Split SQL into individual statements (string/comment aware; dollar-quoting on pgsql only, `\'` backslash-escape on mysql only) |
| `getUserFolderPath(int $userId, string $username): string` | Cross-OS safe folder path |

### MigrationController

**File:** `application/dashboard/migration/MigrationController.php`
**Extends:** `Dashboard\Controller`

Restricted to users with the `system_coder` role. All POST routes are protected by the CSRF middleware.

| Method | Description |
|--------|-------------|
| `index()` | Migration dashboard page |
| `status()` | JSON: pending/ran lists per user folder |
| `view()` | AJAX modal: SQL contents + summary + SHA-256 + security warnings |
| `upload()` | POST: accept a `.sql` file and store it under `{userId}-{username}/`. Max = `min(10 MB, php.ini post_max_size, upload_max_filesize)` |
| `apply()` | POST `{folder, file, confirm?}`: run a pending file. Any scanner warning requires `confirm: 'CONFIRM'` (409 `needs_confirm` otherwise). On success the file moves to `ran/` and the whole cache is cleared (`cache->clear()`) - a migration may have changed permissions, menu, translations or settings |
| `delete()` | POST: remove a pending file |

### MigrationRouter

**File:** `application/dashboard/migration/MigrationRouter.php`

| Route | Method | Name |
|-------|--------|------|
| `/dashboard/migrations` | GET | `migrations` |
| `/dashboard/migrations/status` | GET | `migrations-status` |
| `/dashboard/migrations/view` | GET | `migrations-view` |
| `/dashboard/migrations/upload` | POST | `migrations-upload` |
| `/dashboard/migrations/apply` | POST | `migrations-apply` |
| `/dashboard/migrations/delete` | POST | `migrations-delete` |

### MigrationSecurityScanner

**File:** `application/dashboard/migration/MigrationSecurityScanner.php`

Static SQL scanner - checks uploaded SQL for writes against sensitive tables before apply.

| Method | Description |
|--------|-------------|
| `scan(string $sql): array` | Returns a list of warnings; empty array means safe |

Sensitive tables (`SENSITIVE_TABLES` const): `users`, `rbac_roles`, `rbac_permissions`, `rbac_user_role`, `rbac_role_permission`, `organizations`, `organizations_users`, `localization_language`, `raptor_menu`. Also flags DCL (`GRANT`/`REVOKE`, `CREATE`/`DROP`/`ALTER USER`) and any `CREATE [TEMPORARY] TABLE` (tables must come from Model classes). Each warning is `['level' => 'warning', 'reason' => '...']`. Comments and string literals are stripped before matching to avoid false positives.

---

## Cache

### CacheService

**File:** `application/dashboard/CacheService.php`
**Namespace:** `Dashboard`

Custom file-based DB cache (PSR-16 SimpleCache). No external dependency beyond `psr/simple-cache`. Stored in the top-level `cache/` directory (outside the document root, sibling of `logs/`). Registered as `cache` container service via `ContainerMiddleware`. TTL: 12 hours (safety net). Fail-safe: the `fromDefaultPath()` factory returns `null` when the cache directory is unusable, and the system then runs without cache.

| Method | Description |
|--------|-------------|
| `__construct(string $cacheDir, int $defaultTtl = 3600)` | Cache directory + default TTL (0 = no expiry). Throws `RuntimeException` if the directory cannot be created |
| `static fromDefaultPath(int $ttl = 43200): ?self` | Factory for the framework runtime cache in the top-level `cache/` directory (`dirname(SCRIPT_FILENAME, 2) . '/cache'`, sibling of `logs/`). Returns `null` when the directory is unusable. Used by `ContainerMiddleware` (`cache` service) and directly by `JWTAuthMiddleware`, which runs before the container exists |
| `get(string $key, mixed $default = null): mixed` | Get cached value or default; expired entries are deleted on read |
| `set(string $key, mixed $value, \DateInterval\|int\|null $ttl = null): bool` | Store value (`LOCK_EX`); `null` = default TTL |
| `has(string $key): bool` | Key exists and is not expired |
| `delete(string $key): bool` | Remove cached value |
| `clear(): bool` | Remove all cache files |
| `getMultiple()` / `setMultiple()` / `deleteMultiple()` | PSR-16 bulk variants |

### Cached Data

| Key | Loaded by | Invalidated by |
|-----|-----------|---------------|
| `languages` | LocalizationMiddleware | LanguageController |
| `texts.{code}` | LocalizationMiddleware | TextController, LanguageController |
| `settings.{code}` | SettingsMiddleware | SettingsController |
| `menu.{code}` | DashboardTrait | TemplateController (menu CRUD) |
| `rbac.{userId}` | JWTAuthMiddleware (via `CacheService::fromDefaultPath()` - it runs before ContainerMiddleware) | RBACController (`clear()`) |
| `pages_nav.{code}` | Web TemplateController | PagesController |
| `featured_pages.{code}` | Web TemplateController | PagesController |
| `recent_news.{code}` | HomeController | NewsController |
| `reference.{table}.{code}` (currently `reference.templates.{code}`) | TemplateService (`template_service` container service) | ReferencesController |

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

**File:** `application/dashboard/rbac/PermissionsSeed.php`

Seeds 26 permissions under alias `system` (runtime key `system_{name}`), each with a `module` grouping value: `logger`, `rbac`, `user_index/insert/update/delete/organization_set`, `organization_index/insert/update/delete`, `content_settings/index/insert/update/publish/delete`, `product_index/insert/update/publish/delete`, `localization_index/insert/update/delete`, `development`. Called from `Permissions::__initial()`.

### RolePermissionSeed

**File:** `application/dashboard/rbac/RolePermissionSeed.php`

Creates default roles and assigns permissions:

| Role | Scope |
|------|-------|
| `coder` | Super admin - bypasses all checks (created in `Roles::__initial()`, not by this seed) |
| `admin` | Every `system` permission, including `development` |
| `manager` | `logger`; users (index/insert/update/organization_set); organizations (index/update); all `content_*`; all `product_*`; localization (index/insert/update); `development` |
| `editor` | Content and products (index/insert/update/publish), `localization_index` |
| `viewer` | `content_index`, `product_index`, `localization_index` |

### MenuSeed

**File:** `application/dashboard/template/MenuSeed.php`

Creates dashboard sidebar menu structure with 4 sections (MN/EN):
- **Contents** (position 100) - Messages, Pages, News, Files, Localization, Reference Tables, Settings
- **Shop** (200) - Products, Orders
- **System** (900) - Users, Organizations, Access logs, Dev Requests (any user), Manual (any user)
- **Coder** (990, `permission='system_coder'`, visible only to the coder role) - Database Migrations, Trash, Manage Menu

Items carry `position` for ordering and, where access is restricted, a `permission` guard (plus `alias='system'` where the item is system-organization only).

### TextInitial

**File:** `application/dashboard/localization/text/TextInitial.php`

Seeds 100+ system localization keywords in MN/EN pairs (e.g. `accept`, `cancel`, `delete`, `dashboard`, `error`, `success`). Keywords are alphabetically ordered with type `sys-defined`.

### ReferenceInitial

**File:** `application/dashboard/content/reference/ReferenceInitial.php`

Seeds `reference_templates` table with email templates and legal content:
- Email templates: `forgotten-password-reset`, `request-new-user`, `approve-new-user`, `dev-request-new`, `dev-request-response`, `contact-message-notify`, `order-status-update`, `order-confirmation`, `order-notify`, `comment-notify`, `review-notify`
- Legal: `tos` (Terms of Service), `pp` (Privacy Policy)

### Sample Data Classes

Sample data only exists for built-in modules. Runs on fresh install, removable via dashboard "Reset" button.

| Class | File | Data |
|-------|------|------|
| `NewsSamples` | `dashboard/content/news/NewsSamples.php` | 6 news articles (3 MN + 3 EN) with 3 types |
| `PagesSamples` | `dashboard/content/page/PagesSamples.php` | 14+ hierarchical pages (MN + EN) with parent/child structure |
| `ProductsSamples` | `dashboard/shop/ProductsSamples.php` | 4 products (2 MN + 2 EN) |

---

## Trash

### TrashModel

**File:** `application/dashboard/trash/TrashModel.php`
**Extends:** `codesaur\DataObject\Model`

**Table:** `trash`

Stores JSON snapshots of records that have just been hard-deleted (called after `deleteById()` succeeded).

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

**File:** `application/dashboard/trash/TrashController.php`
**Extends:** `Dashboard\Controller`

Restricted to users with the `system_coder` role (all actions). Mutating routes carry `CsrfMiddleware`.

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
| `UsersController` (deactivated user + signup request) | `users` |
| `OrganizationController` (deactivated organization) | `organizations` |

#### Restore flow

1. **UNIQUE pre-flight** - reads UNIQUE columns from schema (`information_schema.STATISTICS` for MySQL, `pg_index` for PostgreSQL) and aborts if any value already exists in the live table. Returns a clear admin message naming the conflicting field/value.
2. **Original ID insert** - checks whether the original ID is free (`SELECT id ... WHERE id=:id`); if so, inserts with it to preserve foreign key references (`comments.news_id`, etc.).
3. **Auto-increment fallback** - if the original ID is already taken, inserts without `id` and lets the DB assign a new one. The response carries a warning that child FKs need manual update. (No exception-driven retry: PostgreSQL would abort the transaction.)
4. **LocalizedModel content** - if the snapshot includes a `localized` array, inserts each language row into `{primary}_content` with the new `parent_id`.

Steps 2-4 and the removal of the trash row run in a single transaction; any failure rolls everything back.

5. **Dual audit log** - writes both to `trash_log` (`action='trash-restore'`, `restored_by`, `restored_at`, `original_id`, `new_id`, `used_original_id`) AND to the channel named in `log_table` (`action='restore'`, `record_id=<new_id>`) so the restore appears in the record's Logger Protocol.

### TrashRouter

**File:** `application/dashboard/trash/TrashRouter.php`

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
| **Hard delete + Trash** | News, Pages, Products (+ attachments), Orders, Reviews, Comments, Messages, Files, References, DevRequests, Menus, Texts, Languages | `deleteById()` first, then `TrashModel::store()` |
| **Soft delete, then optional hard delete + Trash** | Users, Organizations | `deactivateById()` (`is_active=0`); a deactivated record can then be hard-deleted (`/users/delete`, `/organizations/delete`) - `deleteById()` + `TrashModel::store()`. Signup requests: `/users/signup/delete` also hard-deletes into Trash |
| **Token consumption** (is_active=0) | Forgot (password reset tokens - deactivated on successful use, kept as "used" in the admin requests modal) | `deactivateById()` |

Trash-backed delete routes:
- `NewsController` (route: `/dashboard/news/delete`)
- `PagesController` (route: `/dashboard/pages/delete`)
- `ProductsController` (route: `/dashboard/products/delete`)
- `OrdersController` (route: `/dashboard/orders/delete`)
- `ReviewsController` (route: `/dashboard/products/reviews/delete`)
- `CommentsController` (route: `/dashboard/news/comments/delete`)
- `MessagesController` (route: `/dashboard/messages/delete`)
- `FilesController` (route: `/dashboard/files/{table}/delete`)
- `ReferencesController` (route: `/dashboard/references/delete`)
- `DevRequestController` (route: `/dashboard/dev-requests/delete`)
- `LanguageController` (route: `/dashboard/language/delete`)
- `TextController` (route: `/dashboard/text/delete`)
- `TemplateController` (route: `/dashboard/manage/menu/delete`)
- `UsersController` (routes: `/dashboard/users/delete`, `/dashboard/users/signup/delete`)
- `OrganizationController` (route: `/dashboard/organizations/delete`)

---

## Event System

### EventDispatcher

**File:** `application/dashboard/notification/EventDispatcher.php`
**Implements:** `Psr\EventDispatcher\EventDispatcherInterface`

PSR-14 compliant event dispatcher. Iterates through listeners from the listener provider and calls each one with the event object.

#### `__construct(ListenerProviderInterface $listenerProvider)`
Takes any PSR-14 listener provider (the framework passes `ListenerProvider`).

#### `dispatch(object $event): object`
Calls each listener in turn and stops early when the event reports `isPropagationStopped()`; returns the event.

### ListenerProvider

**File:** `application/dashboard/notification/ListenerProvider.php`
**Implements:** `Psr\EventDispatcher\ListenerProviderInterface`

Registers and provides listeners for event types.

#### `listen(string $eventClass, callable $listener): void`
Registers a listener for an event class.

#### `getListenersForEvent(object $event): iterable`
Yields the listeners registered for the event's exact class, then those registered for each parent class (so a listener on `Event::class` receives every event).

### Event

**File:** `application/dashboard/notification/Event.php`

Base class of all events. Implements `StoppableEventInterface` (`isPropagationStopped()`, `stopPropagation()`) and carries `public readonly string $user` - the actor name, `''` when not given (`DiscordListener` then falls back to the admin name held by `DiscordNotifier`).

### ContentEvent

**File:** `application/dashboard/notification/ContentEvent.php`

Event dispatched for content management actions. Constructor: `(string $action, string $module, string $title, ?int $id = null, string $user = '', array $updates = [])`.

| Property | Type | Description |
|----------|------|-------------|
| `$action` | string | Action performed (`'insert'`, `'update'`, `'delete'`, `'publish'`, plus `'new'` for public contact messages with module `'message'`) |
| `$module` | string | Module / content type (`'news'`, `'page'`, `'product'`, `'review'`, `'comment'`, `'message'`, `'settings'`, `'reference'`, `'language'`, `'text'`) |
| `$title` | string | Content title |
| `$id` | ?int | Content record ID |
| `$user` | string | User who performed the action (inherited from `Event`) |
| `$updates` | array | Changed fields (for update actions) |

### UserEvent

**File:** `application/dashboard/notification/UserEvent.php`

Event dispatched for user-related actions.

| Property | Type | Description |
|----------|------|-------------|
| `$action` | string | Action (`'signup_request'`, `'approved'`) - the only values `DiscordListener::onUserEvent()` handles |
| `$username` | string | Username |
| `$email` | string | Email address |

### OrderEvent

**File:** `application/dashboard/notification/OrderEvent.php`

Event dispatched for order-related actions.

| Property | Type | Description |
|----------|------|-------------|
| `$action` | string | Action (`'new'`, `'status_changed'`) - the only values `DiscordListener::onOrderEvent()` handles |
| `$orderId` | int | Order ID |
| `$customer` | string | Customer name |
| `$email` | string | Customer email |
| `$phone` | string | Customer phone |
| `$product` | string | Product title |
| `$quantity` | int | Order quantity |
| `$oldStatus` | string | Previous status (for status change) |
| `$newStatus` | string | New status (for status change) |

### DevRequestEvent

**File:** `application/dashboard/notification/DevRequestEvent.php`

Event dispatched for development request actions.

| Property | Type | Description |
|----------|------|-------------|
| `$action` | string | Action (`'new'`, `'updated'`) - the only values `DiscordListener::onDevRequestEvent()` handles |
| `$requestId` | int | Request ID |
| `$title` | string | Request title |
| `$assignedTo` | string | Assigned developer |
| `$status` | string | Request status |

### Usage in Controllers

```php
// Dispatch a content event after creating news
$this->dispatch(new ContentEvent(
    'insert', 'news', $record['title'], $id
));
```
