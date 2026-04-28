# Raptor Framework - AI Guide

## Architecture

Raptor is a PHP MVC framework with multi-tenant RBAC, built on PSR-7/PSR-15 middleware. Templates are rendered by `codesaur/template` (see "Create Templates" below for syntax notes). Frontend is not locked to any specific library - the current codebase uses Bootstrap 5 but developers can use any CSS/JS framework.

### Directory Structure

```
application/
  raptor/          # Core framework (controllers, models, middleware, RBAC, localization)
  dashboard/       # Admin panel (home, shop, manual modules)
  web/             # Public website
    content/       # Pages, News controllers + templates
    shop/          # Products, Orders controllers + templates
    service/       # Search, Sitemap, RSS, Contact controllers + templates
    template/      # Web layout, exception handler
public_html/       # Document root (index.php entry point, assets/)
database/
  migrations/      # Pending SQL migration files
  migrations/ran/  # Completed migrations
tests/             # PHPUnit tests
```

### Entry Point

`public_html/index.php` bootstraps the app, loads `.env`, routes to `Web\Application` or `Dashboard\Application` based on URL path.

### Namespaces

- `Raptor\` - Core framework
- `Web\` - Public website (controllers, templates, feature modules all under this namespace)
- `Dashboard\` - Admin dashboard
- `Tests\` - Test classes

## General Rule

If something is not covered in this guide, read existing code in `application/` and follow the same patterns. Match the conventions of the nearest similar module.

## Adding a New Module

### 1. Create Controller

Extend `Raptor\Controller` (dashboard) or `Web\Template\TemplateController` (web). Dashboard modules go in `application/dashboard/{module}/`, core/shared modules in `application/raptor/{module}/`.

```php
$this->pdo                          // PDO database connection
$this->isUserAuthorized()           // Check if logged in (auth only, no permission)
$this->isUserCan('system_rbac')     // Check permission
$this->getUserId()                  // User ID (do NOT use for auth checks)
$this->text('keyword')              // Get localized text
$this->respondJSON($data, $code)    // JSON response
$this->template('file.html')        // Render a standalone template (no layout)
$this->generateRouteLink('route')   // Generate URL
$this->log('table', $level, $msg)   // PSR-3 logging
$this->prepare($sql)                // PDO prepare
$this->invalidateCache('key')       // Clear cached data after CRUD
$this->getService('cache')          // CacheService instance (or null)
$this->dispatch($event)             // Dispatch PSR-14 event (notifications, etc.)
```

**Notification dispatch** - use PSR-14 events instead of calling services directly. Admin name and dashboard URL are auto-injected by `DiscordNotifier` (set in `ContainerMiddleware`), so controllers only pass content-specific data:

```php
$this->dispatch(new \Raptor\Notification\ContentEvent(
    'delete', 'my-module', $title, $id
));
```

**Template rendering** has three levels:

1. `template('file.html', $vars)` - Renders a single standalone template without any layout wrapper. The template itself becomes the full output. Use for any response that does not need the standard layout: AJAX modal forms, error pages (e.g., page-404.html), custom standalone pages, partial HTML fragments, etc.

2. `dashboardTemplate('module.html', $vars)` - Dashboard full-page render: wraps content inside `dashboard.html` layout with sidebar, settings. From DashboardTrait.

3. `webTemplate('page.html', $vars)` - Web full-page render: wraps content inside `index.html` layout with navbar, footer, SEO meta. From TemplateController.

**Rule:** When you need the standard layout (navbar/sidebar, footer, settings), use `dashboardTemplate()` or `webTemplate()`. These call `template()` internally to build layout + content. When you need full control over the output without any layout, use `template()` directly.

```php
// AJAX modal - standalone, no layout
$this->template(__DIR__ . '/role-insert-modal.html', $vars)->render();

// Error page - standalone, own HTML structure
$this->template(__DIR__ . '/page-404.html')->render();
```

`webTemplate()` auto-maps SEO meta from `$vars` to the index layout: `title` -> `record_title`, `code` -> `record_code`, `description` -> `record_description`, `photo` -> `record_photo`. For content records (news, page, product) that already have these keys, no extra work is needed:

```php
// Record with title/code/description/photo - meta is auto-mapped
$this->webTemplate(__DIR__ . '/page.html', $record)->render();

// List page - pass title explicitly in $vars
$this->webTemplate(__DIR__ . '/products.html', [
    'products' => $products,
    'title' => $this->text('products')
])->render();
```

### 2. Create Model

Extend `codesaur\DataObject\Model`. Define columns in constructor, set table name via `setTable()`. The framework automatically creates the table on model's first use - do NOT write CREATE TABLE in migration files. Use `__initial()` for FK constraints and indexes only. Do not create sample data (*Samples.php) for new modules - sample data only exists for the built-in modules (Pages, Reference, News, Products, Menu, Organization) that ship with the framework. Production seed data (permissions, translations, menu entries) is handled in steps 6-8 below.

Migration files are ONLY for changing existing tables (ALTER, new indexes, data inserts into live databases). Never use migrations to create tables for new modules.

### 3. Register Namespace

If the module introduces a new namespace, add it to `composer.json` under `autoload.psr-4` and run `composer dump-autoload`.

### 4. Create Router

Register routes in a Router class extending `codesaur\Router\Router`.

- Give a route `->name()` only when referenced from templates (`{{ 'name'|link }}`) or PHP (`generateRouteLink()`). Routes called dynamically from JS do not need a name.
- Register the router in the app's `Application.php`.

### 5. Create Templates

- Use vanilla HTML comments (`<!-- -->`), not template engine comments (`{# #}`)
- Never use `{{ }}` or `{% %}` inside comments - template may evaluate them. Document variables by name only, e.g. `<!-- Variables: max_file_size, record, files -->`
- `|text` filter returns the keyword itself when not found, so do NOT add `|default` after it - `{{ 'keyword'|text }}` is always safe

**Template engine = `codesaur/template` (NOT Twig).** The syntax mimics Twig but is a custom parser. Twig features that are NOT supported (use the listed alternative):
- `..` range operator -> `range(a, b)` function. e.g. `{% for i in 1..5 %}` -> `{% for i in range(1, 5) %}`
- `in` membership -> explicit `==`/`or` chain or `[key] is defined` lookup map
- `ends with`, `matches` operators (only `starts with` works)
- `**` power, `//` integer division
- `is odd`, `is even`, `is divisible by`, `is same as` tests
- `loop.revindex`, `loop.revindex0`, `loop.parent`
- `{% verbatim %}`, `{% spaceless %}`, `{% apply %}`, `{% extends %}`, `{% block %}`, `{% include %}`
- Named filter arguments (`|date(format='Y-m-d')`) - only positional

Supported and works as in Twig: `if/elseif/else/endif`, `for/else/endfor`, `set`, `macro/endmacro`, `is defined`, `is empty`, `is null`, `is iterable`, `loop.{first,last,index,index0,length}`, `?:`, `??`, `~` concat, `starts with`, ternary, hash/array literals, dot/bracket access, filter chains, `range()`, `max()`, `min()`, `attribute()` functions, plus 30+ built-in filters (`e`, `date`, `length`, `keys`, `slice`, `json_encode`, `merge`, `trim`, `nl2br`, `number_format`, `format`, `replace`, `column`, `batch`, `wordwrap`, etc.).

Object method calls work: `{{ user.can('perm') }}`, `{% if auth.is('role') %}` - the engine dispatches to public PHP methods on `is_object($val)`.

Steps 6-11 below must all be completed for a dashboard module to be fully integrated.

### 6. Add Translations

If the module uses text keywords not yet in `TextInitial.php`, add them alphabetically:

```php
$model->insert(
    ['keyword' => 'my-keyword', 'type' => 'sys-defined'],
    ['mn' => ['text' => 'Монгол текст'], 'en' => ['text' => 'English text']]
);
```

Prefer combining existing keywords in templates over creating new ones:

```html
{{ 'username'|text }} / {{ 'email'|text }}
```

Seed files only run on fresh installs. If the system is already deployed, also write a migration SQL to insert the new translations into the live database.

### 7. Add Permissions (dashboard modules)

Add new permissions to `PermissionsSeed.php` and assign to roles in `RolePermissionSeed.php`.

Permission format: `{alias}_{name}` - e.g. `system_content_index`, `system_product_delete`

Seed files only run on fresh installs. If the system is already deployed, also write a migration SQL to insert the new permissions and role assignments into the live database.

### 8. Add Menu Entry (dashboard modules)

Add the module's index link to `MenuSeed.php` so it appears in the dashboard sidebar. Set `permission` to control visibility by role. Place under an existing section (Contents, Shop, System) if it fits, or create a new section if the module is a separate concern.

Seed files only run on fresh installs. If the system is already deployed, also write a migration SQL to insert the new menu entry into the live database. The migration must insert into the correct parent menu (use SELECT to find parent_id by title).

### 9. Register Badge (dashboard modules with sidebar link)

If the module has a sidebar menu entry and uses `$this->log()` with an `action` context key, register it for badge tracking in `BadgeController`:

1. Add entries to `BADGE_MAP` mapping log table + action to module path + color:
```php
'my_table' => [
    'create'     => ['/dashboard/my-module', 'green'],
    'update'     => ['/dashboard/my-module', 'blue'],
    'delete'     => ['/dashboard/my-module', 'red'],
],
```

2. Add the module to `PERMISSION_MAP` with the permission required to view it:
```php
'/dashboard/my-module' => 'system_my_permission',
```

No controller changes needed - the badge system reads from the existing `*_log` tables that `$this->log()` already writes to. Just ensure log calls include `'action' => 'action-name'` in the context array.

### 9a. Implement Delete with Trash

Delete methods must call `deleteById()` first, then store the record to trash. This ensures trash only contains actually deleted records - if `deleteById()` fails (throws exception), the record is not stored in trash:

```php
$model->deleteById($id);
(new TrashModel($this->pdo))->store('my_log_channel', $tableName, $id, $record, $userId);
```

**First arg = log channel name.** Pass the same string you would pass to `$this->log()` as the table prefix. `restore()` writes the "restored" audit row directly to that channel, so the entry shows up in Logger Protocol on the recovered record's view/update page. Examples: ReviewsController passes `'products'` (because reviews log to `products_log`); ReferencesController passes `'content'`; TemplateController menu delete passes `'dashboard'`.

The Trash system already provides a `restore()` flow (UNIQUE pre-flight, original-ID-first / auto-increment fallback, LocalizedModel `_content` row recreation). For your new module to restore correctly, the `record_data` you pass to `store()` must be the full record snapshot - including `localized` for LocalizedModel entries.

### 10. Write Manual (dashboard modules)


Create `application/dashboard/manual/{module}-manual-{lang}.html` for both MN and EN. If the index page has a manual `?` button, the manual file MUST exist - do not link to non-existent files.

- Headers: `bg-secondary` with `text-white`
- Back button: `btn-light`
- Use ` - ` (dash with spaces) as separator, NOT unicode arrows
- Manual index cards use dark theme (`text-bg-dark`)
- Link the manual from the module's index page with the `?` button (rightmost position)

### 11. Write Tests (if needed)

Place tests in `tests/Unit/` or `tests/Integration/`. Extend `Tests\Support\RaptorTestCase` (unit) or `Tests\Support\IntegrationTestCase` (DB required). Test security rules, business logic, and code quality - not trivial getters/setters.

## RBAC and Authentication

- `isUserAuthorized()` - only checks login status, no permissions. Use when middleware does not cover the route.
- `isUserCan('permission')` - check permission in `raptor/` and `dashboard/` controllers. Also needed in `web/` if the site has membership features.
- `getUserId()` - returns user ID. Do NOT use `!$this->getUserId()` for auth checks - id=0 can bypass. Only use when you need the actual ID value.
- Controllers with `published` field (News, Pages, Products) allow users without `_update`/`_delete` permission to edit/delete their own unpublished records.
- Models WITHOUT `published` field are immediately live - no owner access bypass.

### Default Roles (system alias)

- `coder` - Super admin, bypasses all checks
- `admin` - Full management
- `manager` - Users, content, localization
- `editor` - Create, edit content
- `viewer` - Read only

## Shared Middleware

### Writing Middleware - Critical Rule

**NEVER call `$handler->handle()` inside a `try` block.** The middleware runner uses a shared internal pointer (via `current()`/`next()`) to iterate the middleware queue. Each `handle()` call advances this pointer. If `handle()` is called inside `try` and an exception propagates back from deeper in the chain, the `catch` block catches it, and execution continues to a SECOND `handle()` call - causing the pointer to advance past the end of the queue (`current()` returns `false`, crashing the application).

```php
// WRONG - causes double handle() call when exception occurs
public function process($request, $handler): ResponseInterface
{
    try {
        $data = $this->loadData($request);
        if ($data) {
            return $handler->handle($request->withAttribute('data', $data));
        }
        // ... fallback loading ...
    } catch (\Throwable $err) {
        // Exception from deep in the chain is caught here,
        // but handle() already advanced the pointer
    }
    return $handler->handle($request);  // SECOND call - pointer is stale!
}
```

```php
// CORRECT - prepare data in try, call handle() once outside
public function process($request, $handler): ResponseInterface
{
    $data = [];
    try {
        $data = $this->loadData($request);
        // No handle() call here - only data preparation
    } catch (\Throwable $err) {
        // Safe - no pointer was advanced
    }
    return $handler->handle($request->withAttribute('data', $data));
}
```

**Rule summary for middleware:**
- `$handler->handle()` must be called exactly ONCE per middleware
- That single call must be OUTSIDE any `try/catch` block
- `try/catch` should only wrap data preparation logic (DB queries, cache reads, etc.)
- The `catch` block should handle the error (log, set defaults), then let execution flow to the single `handle()` call

### SessionMiddleware

`Raptor\SessionMiddleware` is shared by both apps. Constructor accepts a `needsWrite` closure. All other routes call `session_write_close()` early for concurrency.

- **Dashboard**: checks for `/login` path or empty CSRF token (to allow first-time token generation)
- **Web**: checks for `/session/` prefix - all routes that write to `$_SESSION` use `/session/` prefix (e.g., `/session/language/{code}`, `/session/contact-send`, `/session/order`)

When adding a new Web route that writes to `$_SESSION`, register it with `/session/` prefix in `WebRouter.php`. No need to modify `Application.php`.

For Dashboard, update the closure in `Application.php`:

```php
new SessionMiddleware(fn($path, $method) =>
    str_contains($path, '/login') || empty($_SESSION['CSRF_TOKEN'])
);
```

### CsrfMiddleware

`Raptor\CsrfMiddleware` protects dashboard POST/PUT/PATCH/DELETE requests against CSRF attacks.

- Token is generated per-session at login and stored in `$_SESSION['CSRF_TOKEN']`
- If no token exists (e.g. old session), the middleware auto-generates one (requires session write access from SessionMiddleware)
- GET/HEAD/OPTIONS requests pass through without validation
- `/login` routes are exempt (token is created there)
- Client JS sends the token via `X-CSRF-TOKEN` header using `csrfFetch()` wrapper
- Token is delivered to the frontend via `<meta name="csrf-token">` in `dashboard.html`

**For new dashboard modules**: use `csrfFetch()` instead of `fetch()` for all POST/PUT/PATCH/DELETE requests. GET requests can use either. `csrfFetch()` is defined in `dashboard.js` and auto-adds the CSRF header.

**For standalone pages** (not using `dashboard.html` layout, e.g. login): use plain `fetch()` since `dashboard.js` is not loaded and login is CSRF-exempt anyway.

### LocalizationMiddleware

`Raptor\Localization\LocalizationMiddleware` is shared. Constructor accepts session key. Controllers read `$this->getAttribute('localization')['session_key']` to write language to session without hardcoding.

## Database

### Parameterized Queries

Use `prepare()` + `bindValue()` for user input. Router-validated values (e.g. `{uint:id}`) are safe to use directly in SQL since the router rejects non-matching requests with 404.

### MySQL / PostgreSQL Compatibility

The framework supports both MySQL and PostgreSQL. Raw SQL queries MUST work on both drivers. Use `$this->getDriverName()` together with `codesaur\DataObject\Constants::DRIVER_*` to branch when syntax differs:

```php
use codesaur\DataObject\Constants;

// JSON extraction
if ($this->getDriverName() === Constants::DRIVER_PGSQL) {
    $expr = "(context::jsonb)->>'action'";
} else {
    $expr = "JSON_UNQUOTE(JSON_EXTRACT(context, '$.action'))";
}

// String concatenation - CONCAT() works on both, but || is PostgreSQL-only.
// Use CONCAT() for cross-database compatibility.
$sql = "CONCAT(first_name, ' ', last_name)";

// Table/column discovery
if ($this->getDriverName() === Constants::DRIVER_PGSQL) {
    $sql = "SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public' AND tablename LIKE '%_log'";
} else {
    $sql = "SHOW TABLES LIKE '%_log'";
}

// Auto-increment reset
if ($this->getDriverName() === Constants::DRIVER_PGSQL) {
    $this->exec("SELECT setval(pg_get_serial_sequence('$table', 'id'), $nextId, false)");
} else {
    $this->exec("ALTER TABLE $table AUTO_INCREMENT = $nextId");
}
```

Always use `Constants::DRIVER_PGSQL` / `DRIVER_MYSQL` / `DRIVER_SQLITE` rather than the raw `'pgsql'` / `'mysql'` strings - the literals were replaced framework-wide when `codesaur/dataobject` v9.1.0 introduced the Constants class.

Common differences to watch: `JSON_EXTRACT` vs `::jsonb`, `SHOW TABLES/COLUMNS` vs `pg_catalog`/`information_schema`, `AUTO_INCREMENT` vs `setval()`, `ON DUPLICATE KEY UPDATE` vs `ON CONFLICT DO UPDATE`, `DATE_SUB(NOW(), INTERVAL 15 MINUTE)` vs `NOW() - INTERVAL '15 minutes'`, identifier quoting (backticks vs double quotes).

### Migration System

File-based, forward-only SQL migration engine. Runs automatically via `MigrationMiddleware`. Migrations are ONLY for modifying existing tables on deployed systems - NOT for creating new tables (Model handles that).

- **Pending**: `database/migrations/*.sql`
- **Completed**: `database/migrations/ran/*.sql`
- File naming: `YYYY-MM-DD_description.sql`
- Use `-- [UP]` and `-- [DOWN]` markers. DOWN auto-runs if UP partially fails.

Typical use cases:
- ALTER TABLE (add/modify/drop columns)
- CREATE INDEX on existing tables
- INSERT seed data (permissions, translations, menu entries) into live databases

```sql
-- [UP]
ALTER TABLE products ADD COLUMN category VARCHAR(100) DEFAULT NULL;

-- [DOWN]
ALTER TABLE products DROP COLUMN category;
```

Rules:
1. Never use CREATE TABLE - Model classes handle table creation
2. Always include both `-- [UP]` and `-- [DOWN]`
3. DOWN must reverse UP exactly
4. Each statement ends with `;`
5. One concern per file
6. Use `IF NOT EXISTS` / `IF EXISTS` where possible
7. Never edit a file in `ran/`

### Delete Strategy

- **Users, Organizations, Signup** use soft delete (`deactivateById`, `is_active=0`) with optional hard delete for deactivated records
- **All other models** use hard delete (`deleteById`) directly. Deleted data is preserved in the `trash` table via `TrashModel::store()` before deletion
- Hard delete for Users/Organizations uses `setForeignKeyChecks(false)` before `deleteById()`

## Cache

Custom file-based cache (PSR-16 SimpleCache). Гадаад dependency-гүй, зөвхөн `psr/simple-cache` interface ашиглана. Stored in `private/cache/`. Registered as `cache` container service. TTL: 12 hours (safety net - primary invalidation is explicit).

### Cached Data

| Cache key | Loaded by | Invalidated by |
|-----------|-----------|---------------|
| `languages` | LocalizationMiddleware | LanguageController |
| `texts.{code}` | LocalizationMiddleware | TextController, LanguageController |
| `settings.{code}` | SettingsMiddleware | SettingsController |
| `menu.{code}` | DashboardTrait | TemplateController (menu CRUD) |
| `rbac.{userId}` | JWTAuthMiddleware | RBACController (`clear()`) |
| `pages_nav.{code}` | Web TemplateController | PagesController |
| `featured_pages.{code}` | Web TemplateController | PagesController |
| `recent_news.{code}` | HomeController | NewsController |
| `reference.{table}.{code}` | TemplateService | ReferencesController |

### Cache Invalidation Rules

- Call `$this->invalidateCache('key')` in the `try` block, right after successful DB write and before `respondJSON()`
- Use `{code}` placeholder for language-specific keys - automatically iterates all languages
- RBAC changes use `$this->getService('cache')->clear()` since they affect all users
- Never place `invalidateCache` in `finally` blocks (runs on errors too)
- Cache is fail-safe: if unavailable, system works without it (direct DB queries)

### Adding Cache to New Modules

When a new module has data that is loaded on every request and only changes via admin CRUD:

1. Add cache read in the middleware/controller that loads the data:
```php
$cache = $this->hasService('cache') ? $this->getService('cache') : null;
$data = $cache?->get('my_key');
if ($data === null) {
    $data = $model->retrieve();
    $cache?->set('my_key', $data);
}
```

2. Add `$this->invalidateCache('my_key')` after successful CRUD operations in the controller

### PrivateFilesController

`private/cache/` is protected from `PrivateFilesController` - both `read()` and `setFolder()` block access to the cache directory.

## Frontend

### Current Stack (replaceable)

The current codebase uses these libraries, but none are required by the framework:

- Bootstrap 5.3.6 (CDN), Bootstrap Icons 1.13.1
- `motable.js` - Data table, `moedit.js` - Rich text editor
- `dashboard.js` - AJAX modals, notifications, search, sidebar badges, CSRF fetch wrapper, log protocol loader, dark mode
- SweetAlert2 - Confirmation dialogs

### Asset Versioning

When making significant changes to JS or CSS files, bump `?v=` in Templates (e.g. `dashboard.css?v=1` -> `dashboard.css?v=2`). Only local assets, not CDN.

### UI Conventions

- Header: simple `d-flex` with title and manual `?` button, no shadow/rounded wrappers
- Filter row: "New" button left, filter button `ms-auto` right
- Tabs: use `<button>` with `data-bs-target`, not `<a href="#id">`
- Delete: SweetAlert2, not Bootstrap modals
- Language dropdown: hide with `localization.language|length > 1` when only one language

## Dashboard Sidebar Badge System

Colored badge pills on sidebar menu items showing unseen activity counts per admin. Reads directly from existing `*_log` tables - no separate event table.

### Architecture

- `BadgeController` (`raptor/template/`) - BADGE_MAP, PERMISSION_MAP, badge counting + seen API
- `AdminBadgeSeenModel` (`raptor/template/`) - stores `checked_at` per admin per module
- `BadgeRouter` (`raptor/template/`) - GET `/dashboard/badges`, POST `/dashboard/badges/seen`. Registered in `Raptor\Application`
- `dashboard.js` - `initSidebarBadges()` AJAX fetch + DOM render
- `dashboard.css` - sidebar badge flex layout + pill styles

### Badge Colors

- Green (`bg-success`) - create, insert
- Blue (`bg-primary`) - update
- Red (`bg-danger`) - delete

Up to 3 badges per module, shown left to right in green-blue-red order.

### How It Works

1. On dashboard page load, JS calls `GET /dashboard/badges`
2. BadgeController builds a reverse map from BADGE_MAP: module -> [(log_table, action, color)]
3. For each module, checks admin permission via PERMISSION_MAP. Skips unauthorized modules
4. Queries `{table}_log` for entries after admin's `checked_at` using JSON_EXTRACT on the `context` column
5. Filters out admin's own actions via `auth_user.id != admin_id`
6. If no `admin_badge_seen` entry exists (new permission or first use), counts from last 30 days
7. When admin clicks a sidebar link, JS removes the badge and POSTs `seen` -> `checked_at = NOW()`

### BADGE_MAP

`BadgeController::BADGE_MAP` is structured as `[log_table][action] => [module_path, color]`. To add badges for a new module, add entries here.

### PERMISSION_MAP

`BadgeController::PERMISSION_MAP` maps each module to its required permission:

- `null` - any authenticated admin (e.g. `/dashboard/manual`)
- `'system_content_index'` - checked via `isUserCan()`
- `'role:system_coder'` - checked via `isUser()`

### Web Frontend Log Context

Web controllers (contact form, comment) add `auth_user` to log context without the `id` field. This makes `JSON_EXTRACT(context, '$.auth_user.id')` return NULL, so the action counts as a badge for all admins.

```php
$this->log('messages', LogLevel::INFO, 'message', [
    'action' => 'contact-send',
    'auth_user' => ['username' => $name, 'first_name' => $name, ...]
]);
```

### File-count Badge

For modules not tracked in logs (manual, migrations), badges are based on file count. The system compares current `glob()` count against `last_seen_count` stored in `admin_badge_seen`.

## Logger Protocol (View/Update Page Log Display)

`initLoggerProtocol()` in `dashboard.js` auto-loads log entries for view/update pages. No JS needed in templates - just add data attributes to the `<ul>` element:

```html
{% if user.can('system_logger') %}
<div class="mt-5">
    <hr>
    <label class="form-label fw-bolder">
        {{ 'log'|text }} <i class="bi bi-clock-history"></i>
    </label>
    <div class="spinner-grow mt-3" role="status" style="display:none">
        <span class="visually-hidden">Loading logs...</span>
    </div>
    <ul class="list-group logger-protocol" id="logger-{table}"
        data-retrieve="{{ 'logs-retrieve'|link }}"
        data-view="{{ 'logs-view'|link }}"
        data-context='{"record_id":"{{ record['id'] }}"}'
        style="max-height:240px; overflow-y:auto">
    </ul>
</div>
{% endif %}
```

- `id="logger-{table}"` - log table name (e.g. `logger-content`, `logger-localization`)
- `data-retrieve` / `data-view` - route links (required)
- `data-context` - JSON filter for log context (optional, omit for no filtering)

Common context patterns:
- Record-specific: `{"record_id":"{{ record['id'] }}"}`
- Action-specific: `{"action":"reference-*","record_id":"{{ record['id'] }}"}`
- No filter: omit `data-context` entirely

**Convention:** any log entry tied to a record must use `'record_id' => $id` in the context array - never `'id' => $id`. This single convention keeps Logger Protocol filters consistent across modules. The separate `auth_user.id` field has its own semantic (used by the Badge system to filter out the actor's own actions) and is unrelated.

## Code Style

### Use Statement Order

Group by namespace, separated by blank lines: external packages -> codesaur -> Raptor -> Dashboard -> Web

### Documentation Style

Write in Mongolian Cyrillic or English. Applies to `*.md`, PHPDoc, HTMLDoc, JSDoc, comments.

- No Unicode special characters - ASCII only (`->` not arrow, `-` not bullet, `--` not em-dash, `"` `'` not curly quotes, `>=` not `≥`, `>` not `»`, `(c)` or `&copy;` not `©`)
- When changing code, update related docs in the same commit

**Allowed Unicode exceptions** (do not "fix" these):
- `🦖` dinosaur emoji - Raptor brand identity. Allowed in `README.md` (title) and `application/raptor/migration/migration-index.html` (header).
- Emoji in `application/dashboard/manual/*-manual-*.html` - allowed for admin clarity in user-facing manual pages.
- Emoji in `application/raptor/notification/DiscordNotifier.php` - allowed because Discord notifications use emoji as their primary visual language for admin clarity.
- IPA pronunciation characters (e.g. `/vɪˈlɒsɪræptər/`) in `application/raptor/content/page/PagesSamples.php` and `README.md` "Did You Know?" trivia - educational content, not docs/comments.
- Superscript/subscript characters (`²`, `₂` etc.) in `application/dashboard/manual/moedit-manual-*.html` - chemistry/math notation examples in moedit manual.

### error_log Usage

`\error_log()` directly only for real errors (exceptions, migration failures, error handlers). Normal/debug logging: wrap with `if (CODESAUR_DEVELOPMENT)`.

### Username is Immutable

Cannot be changed after creation (readonly in edit form, `unset` in controller).
