# Raptor Framework - AI Guide

## Architecture

Raptor is a PHP framework following the MVC pattern with a modular (package-by-feature) structure - each module bundles its Controller, Model, and templates together in one folder rather than splitting them across separate layer directories (no top-level `Models/`, `Controllers/`, `Views/`). It has multi-tenant RBAC and is built on PSR-7/PSR-15 middleware. Templates are rendered by `codesaur/template` (see "Create Templates" below for syntax notes). Frontend is not locked to any specific library - the current codebase uses Bootstrap 5 but developers can use any CSS/JS framework.

### Directory Structure

```
application/
  raptor/          # Core framework (controllers, models, middleware, RBAC, localization)
  dashboard/       # Admin panel (badge, home, manual, protected, shop modules)
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

`public_html/index.php` bootstraps the app, loads `.env`, opens the PDO connection via `\Raptor\DatabaseConnection::connect()` (driver selected by `RAPTOR_DB_DRIVER`: `mysql` | `pgsql`), and passes it to the Application as the `pdo` request attribute. Web and Dashboard share the same connection. Then routes to `Web\Application` or `Dashboard\Application` based on URL path. Controllers pick up `$this->pdo` automatically inside `Raptor\Controller::__construct()`.

### Namespaces

- `Raptor\` - Core framework
- `Web\` - Public website (controllers, templates, feature modules all under this namespace)
- `Dashboard\` - Admin dashboard
- `Tests\` - Test classes

## General Rule

If something is not covered in this guide, read existing code in `application/` and follow the same patterns. Match the conventions of the nearest similar module.

Everything outside `vendor/` is developer-owned project code, not a locked library. After `composer create-project`, the whole tree (`application/`, `public_html/`, `database/`, `tests/`, `docs/`, config files) can be freely modified, replaced, or removed - including the `raptor/` core itself. The default codebase is only a baseline covering a developer's common needs - adapt the code directly to project-specific requirements. Only the `vendor/*` packages are Composer-managed dependencies (updated via `composer update`); leave those untouched.

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

**DashboardTrait method collision rule:** a controller that uses `Raptor\Template\DashboardTrait` MUST NOT define a method with the same name as any of the trait's public API (`dashboardTemplate`, `dashboardProhibited`, `modalProhibited`, `getUserMenu`, `getUserOrganizations`). In PHP a class method silently overrides the trait method, so the trait's internal calls (e.g. `dashboardTemplate()` calling `getUserOrganizations()` for the topbar org switcher) would dispatch to the controller's unrelated version and break the layout. If a controller needs a similar helper, pick a distinct name (e.g. `getMemberOrganizations()`).

**Custom dashboard layout (`overrideDashboardLayout`):** the three layout templates DashboardTrait renders internally (`dashboard.html`, `alert-no-permission.html`, `modal-no-permission.html`) can be replaced from the developer's own Application - an alternative to editing the raptor templates in place (both are valid; the override keeps the baseline files intact). Register in the Application constructor (same explicit-override philosophy as router `override()`):

```php
// application/{myapp}/Application.php
parent::__construct($response);
// Use a folder name distinct from raptor's own `template/` to avoid confusion.
$this->overrideDashboardLayout('dashboard.html', __DIR__ . '/myspecial/dashboard.html');
```

The map travels as the `dashboard_layouts` request attribute (injected in `Raptor\Application::handle()`) and is resolved by `DashboardTrait::layout()`. Registration fail-fasts with `InvalidArgumentException` if the custom file does not exist. When writing a custom `dashboard.html`, start from a copy of the core file - it must keep `{{ content }}`, the `csrf-token` and `waf-body-encoding` meta tags, and the `dashboard.js`/`dashboard.css` includes, otherwise CSRF, WAF encoding, badges and the org switcher break. The sidemenu loop is optional - the developer can build their own navigation any way they like (keep the loop only if you want the ready-made RBAC-filtered menu). Pages that have their own route (login etc.) are NOT part of this map - override their route instead.

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
- **CSRF**: every mutating dashboard route (POST/PUT/PATCH/DELETE and `GET_POST`/`GET_PUT` compounds) MUST chain `->middleware([CsrfMiddleware::class])` (with `use Raptor\CsrfMiddleware;`) - CSRF is per-route, not app-wide. Only login routes are exempt. See "CsrfMiddleware" below.
- **Dashboard home route**: the named `'home'` route lives at `/home`, and `/` (dashboard root) stays registered WITHOUT a name - both point to `HomeController::index` (see `HomeRouter`). Do not merge them: sidebar active-detection uses prefix matching (`href.startsWith(link)`), so naming `/` as home would keep the home link active on every page; removing `/` would 404 the public web's `{{ index }}/dashboard` CTA/footer links. The bare dashboard root (where login lands) does not prefix-match the `/home` link, so `dashboard.html`'s inline script adds `active` to the home link when `window.location.pathname` equals `{{ index }}/dashboard`.

### 5. Create Templates

- Use vanilla HTML comments (`<!-- -->`), not template engine comments (`{# #}`)
- Never use `{{ }}` or `{% %}` inside comments - template may evaluate them. Document variables by name only, e.g. `<!-- Variables: max_file_size, record, files -->`
- In inline `<script>` blocks use `/* ... */` comments, NEVER `//` line comments - HTML minification can collapse newlines, and a `//` would then comment out all following code on the merged line
- `|text` filter returns the keyword itself when not found, so do NOT add `|default` after it - `{{ 'keyword'|text }}` is always safe

**Template engine = `codesaur/template` (NOT Twig).** The syntax mimics Twig but is a custom parser. Twig features that are NOT supported (use the listed alternative):
- `..` range operator -> `range(a, b)` function. e.g. `{% for i in 1..5 %}` -> `{% for i in range(1, 5) %}`
- `**` power, `//` integer division
- `is divisible by`, `is same as` tests
- `loop.revindex`, `loop.revindex0`, `loop.parent`
- `{% verbatim %}`, `{% spaceless %}`, `{% apply %}`, `{% extends %}`, `{% block %}`, `{% include %}`
- Named filter arguments (`|date(format='Y-m-d')`) - only positional

Supported and works as in Twig: `if/elseif/else/endif`, `for/else/endfor`, `set`, `macro/endmacro`, `is defined`, `is empty`, `is null`, `is iterable`, `is even`, `is odd`, `loop.{first,last,index,index0,length}`, `?:`, `??`, `~` concat, `in`, `not in` (membership: array/string/Traversable), `starts with`, `ends with`, `matches` (regex), ternary, hash/array literals, dot/bracket access, filter chains, `range()`, `max()`, `min()`, `attribute()` functions, plus 30+ built-in filters (`e`, `date`, `length`, `keys`, `slice`, `json_encode`, `merge`, `trim`, `nl2br`, `number_format`, `format`, `replace`, `column`, `batch`, `wordwrap`, etc.). (`in`/`not in`, `ends with`, `matches`, `is even`/`is odd` require `codesaur/template` >= 4.1.0.)

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

Uniqueness is the composite `(alias, name)`, not `name` alone - the same `name` may be reused under different aliases (e.g. `system_content_update` and `common_content_update`). The runtime key is the separator-less concatenation `{alias}_{name}`, so `alias` MUST NOT contain an underscore (it is a clean grouping value tied to the sidebar menu: `system`, `common`, ...); `name` carries the underscores. `Permissions::insert()/updateById()` enforce both rules (reject underscore in alias, reject duplicate `(alias, name)`) before the DB constraint is hit. When matching permissions by `name` in seeds/queries, qualify with `alias` too (`AND p.alias = '...'`) - matching by `name` alone can now hit rows under other aliases.

Seed files only run on fresh installs. If the system is already deployed, also write a migration SQL to insert the new permissions and role assignments into the live database.

### 8. Add Menu Entry (dashboard modules)

Add the module's index link to `MenuSeed.php` so it appears in the dashboard sidebar. Set `permission` to control visibility by role. Place under an existing section (Contents, Shop, System; the Coder section is reserved for `system_coder`-only framework tools) if it fits, or create a new section if the module is a separate concern.

Seed files only run on fresh installs. If the system is already deployed, also write a migration SQL to insert the new menu entry into the live database. The migration must insert into the correct parent menu (use SELECT to find parent_id by title).

### 9. Register Badge (dashboard modules with sidebar link)

If the module has a sidebar menu entry and uses `$this->log()` with an `action` context key, register it for badge tracking in `BadgeController`:

Module paths in both maps are **mount-naive** - use the bare route path (`/my-module`) WITHOUT the `/dashboard` prefix. The dashboard mount point is configured once via `->mount('/dashboard')` in `public_html/index.php`; `BadgeController` re-attaches it at runtime through `getMountPath()`. Never hardcode `/dashboard` in these maps - it would break if the mount path changes.

1. Add entries to `BADGE_MAP` mapping log table + action to module path + color:
```php
'my_table' => [
    'create'     => ['/my-module', 'green'],
    'update'     => ['/my-module', 'blue'],
    'delete'     => ['/my-module', 'red'],
],
```

2. Add the module to `PERMISSION_MAP` with the permission required to view it:
```php
'/my-module' => 'system_my_permission',
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

`Raptor\CsrfMiddleware` protects dashboard mutating requests against CSRF attacks. It is a **per-route** middleware (NOT app-wide) - import it (`use Raptor\CsrfMiddleware;`) and attach it to each mutating route in the router:

```php
$this->POST('/news/insert', [NewsController::class, 'insert'])
    ->name('news-insert')
    ->middleware([CsrfMiddleware::class]);
```

- The middleware ONLY validates: it compares `$_SESSION['CSRF_TOKEN']` against the `X-CSRF-TOKEN` header and returns 403 on mismatch.
- GET/HEAD/OPTIONS pass through without validation (so `GET_POST`/`GET_PUT` compound routes can still serve their GET side).
- Token is generated per-session at login and stored in `$_SESSION['CSRF_TOKEN']`. As a fallback for old sessions, `Controller::template()` generates it when an authorized dashboard user has none and the session is writable.
- Token is delivered to the frontend via `<meta name="csrf-token">` in `dashboard.html` (`Controller::template()` reads it from session).
- Client JS sends the token via `X-CSRF-TOKEN` header using `csrfFetch()` wrapper.

**For new dashboard modules**: every mutating route (POST/PUT/PATCH/DELETE and `GET_POST`/`GET_PUT` compounds) MUST add `->middleware([CsrfMiddleware::class])` (with `use Raptor\CsrfMiddleware;` in the router) - it is NOT automatic. Login routes are the only exempt mutating routes (do not attach it there; the token is created during login). On the client, use `csrfFetch()` instead of `fetch()` for all mutating requests; GET requests can use either. `csrfFetch()` is defined in `dashboard.js` and auto-adds the CSRF header.

**For standalone pages** (not using `dashboard.html` layout, e.g. login): use plain `fetch()` since `dashboard.js` is not loaded and login routes carry no CsrfMiddleware anyway.

### Shared Hosting / WAF Compatibility

Many shared hosts (cPanel/LiteSpeed with mod_security) interfere with normal dashboard saves in two ways. The framework works around both automatically - no per-module code needed. These are registered as app-wide middleware in both `Raptor\Application` and `Web\Application`, and wired into `csrfFetch()` on the client. (Raptor sets a 30-day session cookie by default in `SessionMiddleware`; the server-side `gc_maxlifetime`/`save_path` use PHP/host config, and that one line can be removed to rely entirely on php.ini - see `docs/en/SESSION-LIFETIME.md`.)

1. **Blocked verbs -> 403 on PUT/PATCH/DELETE.** WAFs often reject these verbs before PHP runs. `MethodOverrideMiddleware` reads `X-HTTP-Method-Override` and restores the real verb before routing; `csrfFetch()` sends mutating requests as POST + that header. POST-only, never overrides to GET, so CSRF still applies. Always on (zero downside).

2. **Blocked body content -> 403 on HTML-rich saves.** WAF XSS rules flag rich-text content (`<a>`, `<img>`, `<script>`, pasted markup) in the POST body. When `RAPTOR_WAF_BODY_ENCODING=true` (default), `csrfFetch()` base64-encodes FormData string field VALUES (files and field names untouched) so the raw body carries no HTML; `BodyEncodingMiddleware` decodes them back (header-gated by `X-Body-Encoding: base64`, strict base64, recursive). Works because no controller reads `$_POST` directly - all use `getParsedBody()`. The flag reaches the client via `<meta name="waf-body-encoding">` set by `Controller::template()`. Single encode/single decode, so already-base64 content (inline `data:` image URIs) round-trips byte-for-byte.

When adding new modules: keep using `method="PUT"` forms + `csrfFetch()`; tunneling and encoding are transparent. A dashboard save 403 on this kind of host is a WAF symptom, NOT a CSRF bug. Web (public) forms only get the server-side middleware - they have no `csrfFetch`, so add an equivalent client wrapper if a public form needs HTML body encoding.

**Defaults are fail-safe (always ON):** every layer defaults to encoding enabled - env unset (`Controller` `?? true`), template var unset (`?? '1'`), and meta tag missing (`csrfFetch` treats absent meta as enabled). So a misconfiguration can never silently re-introduce the WAF 403; worst case is harmless extra payload on a non-WAF host. The one consequence: the `<meta name="waf-body-encoding">` tag is the ONLY channel carrying the env value to the client. **If you build a custom dashboard layout (not the shipped `dashboard.html`), you MUST include this meta tag** - otherwise `RAPTOR_WAF_BODY_ENCODING=false` is silently ignored on the client (encoding stays on). The server side stays correct regardless (it is gated by the `X-Body-Encoding` header, not by env), so nothing breaks - the toggle just won't take effect.

**Sending a request the "plain" way (no method override, no body encoding):** there are two levels.

- **Site-wide:** set `RAPTOR_WAF_BODY_ENCODING=false` to turn off body encoding everywhere (hosts with no body-inspecting WAF). Method override has no site-wide switch - it is always registered but is a no-op unless the client sends `X-HTTP-Method-Override`, so it costs nothing and never needs disabling.
- **Per request:** `csrfFetch()` ALWAYS tunnels verbs and (when enabled) encodes the body - there is no per-call opt-out flag. To bypass both for a single request, call plain `fetch()` instead of `csrfFetch()`: it sends the verb and body verbatim with no transformation. The catch - `fetch()` does NOT add the `X-CSRF-TOKEN` header, so against a CsrfMiddleware-protected (mutating) route it will 403; add it yourself with `getCsrfToken()` (GET/HEAD/OPTIONS are exempt and need nothing). The legitimate use for plain `fetch()` is calling a NON-dashboard / external endpoint where you must not leak your CSRF token or send override/encoding headers; for dashboard routes always prefer `csrfFetch()` since the transformations are exactly what makes them work behind a WAF.

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

Fully file-based, forward-only SQL migration engine. State is derived entirely from the directory layout. Migrations are ONLY for modifying existing tables on deployed systems - NOT for creating new tables (Model handles that).

The `database/migrations/` folder is **git-ignored**. Each environment uploads its own SQL files through `/dashboard/migrations` (requires `system_coder`).

Directory layout:
```
database/migrations/
|-- .gitkeep
|-- README.md
`-- {userId}-{username}/         <- per-user folder, created on first upload
    |-- pending_file.sql         <- pending
    `-- ran/
        `-- applied_file.sql     <- successfully applied
```

State derivation: file at `{folder}/*.sql` = **pending**; file at `{folder}/ran/*.sql` = **applied**.

Lifecycle:
1. `system_coder` uploads a `.sql` file via the dashboard
2. File stored at `database/migrations/{userId}-{username}/{filename}.sql`
3. Apply is requested -> `MigrationSecurityScanner` flags writes against sensitive tables (`users`, `rbac_*`, `organizations*`, `localization_language`, `raptor_menu`) and DCL (`GRANT/REVOKE`, `CREATE/DROP/ALTER USER`)
4. If warnings present, the dashboard requires a typed `CONFIRM` to proceed (soft guard, not hard block)
5. On success the file moves to `{folder}/ran/`; on failure it stays pending and the error is logged to `dashboard_log` (action: `migration-apply`)

File format:
```sql
-- Optional first-line description (used as summary in UI)
ALTER TABLE products ADD COLUMN category VARCHAR(100) DEFAULT NULL;
CREATE INDEX idx_products_category ON products (category);
```

Rules:
1. Never use CREATE TABLE - Model classes handle table creation
2. Each statement ends with `;`
3. Statements run in order; the first failure stops the rest and leaves the file pending
4. To revert a bad apply, write a new migration (forward-only)
5. Never edit a file in `ran/`
6. Use `IF NOT EXISTS` / `IF EXISTS` where possible
7. Audit trail is preserved on disk per-environment + in `dashboard_log` (`action: migration-upload` / `migration-apply` / `migration-delete`)
8. Migration SQL MUST work on both MySQL and PostgreSQL (see "MySQL / PostgreSQL Compatibility" above). The statement splitter is driver-aware: PostgreSQL dollar-quoting (`$$...$$`) is parsed only on pgsql, and MySQL backslash escapes (`\'`) only on mysql - so a `;` inside a string/dollar-block is not mistaken for a statement separator on either driver.

For touching the sensitive table list, see `MigrationSecurityScanner::SENSITIVE_TABLES` and the `PATTERNS` map in the same class.

### Delete Strategy

- **Users, Organizations, Signup** use soft delete (`deactivateById`, `is_active=0`) with optional hard delete for deactivated records
- **All other models** use hard delete (`deleteById`) directly. Deleted data is preserved in the `trash` table via `TrashModel::store()` before deletion

## Cache

Custom file-based cache (PSR-16 SimpleCache). Гадаад dependency-гүй, зөвхөн `psr/simple-cache` interface ашиглана. Stored in `cache/` (a dedicated top-level directory outside the document root, sibling of `logs/`; kept in git via its own `.gitignore`, contents ignored). Registered as `cache` container service via `ContainerMiddleware`. TTL: 12 hours (safety net - primary invalidation is explicit).

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

### ProtectedFilesController

`Dashboard\Protected\ProtectedFilesController` (`application/dashboard/protected/`) serves files from the document-root-external `/protected` folder through the `GET /dashboard/protected/file?name=...` route (`ProtectedRouter`, registered in `Dashboard\Application`). It lives in `application/dashboard/` because no shipped module uses protected storage - every shipped module stores files in `/public` via `FileController::setFolder()`. Protected storage is a per-project decision, so this is a reference implementation to customize.

Authorization is an overridable hook: `read()` calls `protected function authorizeRead(string $relativePath): bool` before serving. The default is permissive - any authenticated user may read (`system_coder` always). To restrict, either edit `authorizeRead()` in place or subclass `ProtectedFilesController`, override `authorizeRead()` and `$this->override(...)` the route to your controller - both ways apply your module's index/view permission or tenant-ownership rule (the method carries a commented org-id example). Because the default is permissive, a project that stores sensitive per-tenant files under `/protected` MUST tighten `authorizeRead()` - otherwise any logged-in user of any tenant can read every protected file.

`read()` also blocks directory traversal (realpath containment) and a denylist of executable/sensitive extensions and filenames (php, .env, .htaccess, etc.). (The framework cache lives in the top-level `cache/` directory, not under `/protected`, so the controller has no cache-specific logic.)

## Frontend

### Current Stack (replaceable)

The current codebase uses these libraries, but none are required by the framework:

- Bootstrap 5.3.6 (CDN), Bootstrap Icons 1.13.1
- `motable.js` - Data table, `moedit.js` - Rich text editor
- `dashboard.js` - AJAX modals, notifications, search modal (Ctrl+K), topbar language/theme dropdowns, sidebar badges, CSRF fetch wrapper, log protocol loader, dark mode
- SweetAlert2 - Confirmation dialogs

### Asset Versioning

When making significant changes to JS or CSS files, bump `?v=` in Templates (e.g. `dashboard.css?v=1` -> `dashboard.css?v=2`). Only local assets, not CDN.

`?v=` increments by exactly 1 per RELEASE, relative to the last git tag - not once per change. If the file already got its `+1` since the last tag, further edits in the same release keep that number (check with `git show <last-tag>:<template> | grep '?v='`). Only bump files whose content actually changed since the tag; an untouched file keeps its old `?v=`.

### UI Conventions

- Header: simple `d-flex` with title and manual `?` button, no shadow/rounded wrappers
- Filter row: "New" button left, filter button `ms-auto` right
- Tabs: use `<button>` with `data-bs-target`, not `<a href="#id">`
- Delete: SweetAlert2, not Bootstrap modals
- Language dropdown: hide with `localization.language|length > 1` when only one language

## Dashboard Sidebar Badge System

Colored badge pills on sidebar menu items showing unseen activity counts per admin. Reads directly from existing `*_log` tables - no separate event table. The whole feature lives in the dashboard app layer (namespace `Dashboard\Badge`, folder `application/dashboard/badge/`) where per-project customization (`BADGE_MAP` / `PERMISSION_MAP` / `orgScopedModules()`) is expected to happen - edit it directly.

### Architecture

- `BadgeController` (`Dashboard\Badge`, `application/dashboard/badge/`) - BADGE_MAP, PERMISSION_MAP, `orgScopedModules()`, badge counting + seen API
- `AdminBadgeSeenModel` (`Dashboard\Badge`) - stores `checked_at` per admin per module
- `BadgeRouter` (`Dashboard\Badge`) - GET `/dashboard/badges`, POST `/dashboard/badges/seen`. Registered in `Dashboard\Application`
- `dashboard.js` - `initSidebarBadges()` AJAX fetch + DOM render
- `dashboard.css` - sidebar badge flex layout + pill styles

### Multi-tenant org scoping

`BadgeController::orgScopedModules()` (default `[]`) returns mount-naive module paths whose badges must be scoped to the viewing admin's current organization. For those modules the count query adds `context.auth_user.organization_id == currentOrgId` (log entries with a NULL org - old/web-frontend - still count for all, backward-compatible; `system_coder` bypasses scoping entirely as a cross-tenant superuser). The shipped content modules (news/pages/products/orders/messages) are global (no `organization_id` column) so the default list is empty; a multi-tenant app lists its tenant-scoped modules in `orgScopedModules()` (e.g. `['/request']`) - edit the method body directly, or override it in a subclass. The actor's org is recorded in every log entry by `Controller::log()` (`auth_user.organization_id`).

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

`BadgeController::BADGE_MAP` is structured as `[log_table][action] => [module_path, color]`. To add badges for a new module, add entries here. `module_path` is mount-naive (`/news`, not `/dashboard/news`).

### PERMISSION_MAP

`BadgeController::PERMISSION_MAP` maps each module (mount-naive key) to its required permission:

- `null` - any authenticated admin (e.g. `/manual`)
- `'system_content_index'` - checked via `isUserCan()`
- `'role:system_coder'` - checked via `isUser()`

Both maps key on the bare route path; the `/dashboard` mount prefix is added at runtime via `getMountPath()`. The full key (`/dashboard/news`) appears only in the JSON the API returns and in the `seen` POST body (matched against sidebar menu hrefs).

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

## Dashboard Global Search + Topbar Quick Icons

The topbar right side is flat (no user dropdown): **search | language | theme | user | logout**.

- **Search** opens a centered modal (`#global-search`, also via Ctrl+K). `SearchController` (`application/dashboard/home/`) powers it: per-module LIKE queries returning grouped JSON results. `initGlobalSearch()` in `dashboard.js` handles open/close, debounced search and keyboard navigation (arrows + Enter). If the search route is not registered (`|link` returns `'#'`) the function removes the topbar search icon and exits; results whose view-route pattern resolves to `'#'` are hidden from the list.
- **Language** is a dropdown listing active languages (hidden when only one language is active, per UI convention). Selecting fetches the `language` route (session-persisted) and reloads - handled by `initTopbarQuick()` via `data-language-url` attributes.
- **Theme** is a light/dark dropdown applied instantly through `localStorage` + `data-bs-theme` (no reload) - handled by `initTopbarQuick()` via `data-theme` attributes. These dropdowns replaced the old "Language & Options" modal (`user-option` route, removed).
- **User** (avatar + name) links straight to the admin's own profile page (`user-update` route) - no dropdown in between.
- **Logout** is an icon-only button after a separator. Because logout is a plain GET link, it always asks for confirmation (`initLogoutConfirm()` in `dashboard.js`): a Bootstrap modal (`#logout-confirm-modal` in `dashboard.html`) when Bootstrap JS is available, falling back to native `confirm()` when the CDN failed to load - so logout works even fully offline. A custom dashboard layout that keeps the shipped logout button must also keep the `#logout-confirm-modal` markup (or accept the `confirm()` fallback).

**Permission invariant:** every source block in `search()` MUST be gated with the SAME permission (or row-level filter) the module's own index page requires: news/pages/messages/comments -> `system_content_index`, products/orders/reviews -> `system_product_index`, users -> `system_user_index`, organizations -> `system_organization_index`, dev-requests -> any authorized user but WITHOUT `system_development` only own/assigned rows (`created_by`/`assigned_to`), mirroring `DevRequestController::list()`. Search results must be a subset of what the user could see by browsing - if the module's index page would render `dashboardProhibited`, its records must never appear in search (orders/messages carry customer PII, so a wrong gate is a data leak, not just a UX bug). When adding a module to search, copy the exact `isUserCan()` check from that module's index action.

**Result click targets:** modules with a full view page link directly (news, pages, products, orders, users, dev-requests; comments link via `news_id` to the news page `#comments` anchor); modules whose view is a modal fragment (organizations, messages) are marked `modal: true` in `SOURCE_META` (`dashboard.js`) and load inside `#static-modal`; reviews have no per-record view so they link to the reviews index.

Global search is optional: a project that does not need it can delete `SearchController`, its route in `HomeRouter`, plus the search icon and `#global-search` modal markup in `dashboard.html`. The language/theme dropdowns are independent and unaffected.

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

## Deployment

All standard deploy paths live in `.github/workflows/deploy.yml` (runs after CI succeeds; target auto-selected by configured secrets/variables): **A) FTP**, **B) SSH**, **C) Windows self-hosted runner**. Setup instructions are in that file's header comments.

As a fallback (**deploy path D** in the READMEs) for the rare environments none of those jobs can reach - cPanel shared hosting with SSH/Terminal disabled and no reachable FTP (real-world example: the National Data Center of Mongolia shared hosting for government agency portals); being on cPanel alone does NOT imply this path, use A/B when the host offers FTP/SSH - a cPanel Git scaffold ships in `docs/conf.example/`: `.cpanel.yml.example` (copy to repo root as `.cpanel.yml`) and `auto-deploy.sh.example` (copy to `deploy/auto-deploy.sh`, run via cron). The scaffold handles `vendor/`-less repos by running `composer install` when `composer.lock` changes and `composer dump-autoload -o` when only `composer.json` changes (new PSR-4 module maps). Read `docs/mn/CPANEL.md` BEFORE touching that scaffold's behavior - it documents the PascalCase-vs-lowercase module folder naming rule, the CLI-SAPI php lookup gotcha, and the two-phase sequencing rule for changing the deploy script itself (a deploy that updates `auto-deploy.sh` still runs the OLD logic that cycle - land script changes one deploy BEFORE the changes that depend on them).

## Code Style

### Use Statement Order

Group by namespace, separated by blank lines: external packages -> codesaur -> Raptor -> Dashboard -> Web

### Documentation Style

Write in Mongolian Cyrillic or English. Applies to `*.md`, PHPDoc, HTMLDoc, JSDoc, comments.

- No Unicode special characters - ASCII only (`->` not arrow, `-` not bullet, `--` not em-dash, `"` `'` not curly quotes, `>=` not `≥`, `>` not `»`, `(c)` or `&copy;` not `©`)
- When changing code, update related docs in the same commit

**Allowed Unicode exceptions** (do not "fix" these):
- `🦖` dinosaur emoji - Raptor brand identity. Allowed in `README.md` (title).
- Emoji in `application/dashboard/manual/*-manual-*.html` - allowed for admin clarity in user-facing manual pages.
- Emoji in `application/raptor/notification/DiscordNotifier.php` - allowed because Discord notifications use emoji as their primary visual language for admin clarity.
- Superscript/subscript characters (`²`, `₂` etc.) in `application/dashboard/manual/moedit-manual-*.html` - chemistry/math notation examples in moedit manual.
- Non-ASCII characters in test DATA (string literals exercising UTF-8 handling, e.g. `BodyEncodingMiddlewareTest`) - functional payload, not documentation.

### error_log Usage

`\error_log()` directly only for real errors (exceptions, migration failures, error handlers). Normal/debug logging: wrap with `if (CODESAUR_DEVELOPMENT)`.

### Username is Immutable

Cannot be changed after creation (readonly in edit form, `unset` in controller).
