# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/) and this project adheres to [Semantic Versioning](https://semver.org/).

---

## [2.0.1] - 2026-03-18
[2.0.1]: https://github.com/codesaur-php/Raptor/compare/v2.0.0...v2.0.1

GitHub Actions Node.js 24 compatibility update.

### Changed
- **ci.yml** - `actions/checkout` v4 -> v6 (Node.js 24 support)
- **cpanel.deploy.yml** (example) - `actions/checkout` v4 -> v6, `SamKirkland/FTP-Deploy-Action` v4.3.5 -> v4.3.6

---

## [2.0.0] - 2026-03-18
[2.0.0]: https://github.com/codesaur-php/Raptor/compare/v1.9.1...v2.0.0

Dashboard sidebar badge system - log-based activity notifications for admin users.

### Added
- **Badge system** - Dashboard sidebar menu items display colored badge pills showing unseen activity counts. Green = create/insert, blue = update, red = delete/deactivate. Multiple badges per module shown in green-blue-red order
- **BadgeController** (`raptor/template/`) - BADGE_MAP constant maps log table + action pairs to sidebar modules with colors. PERMISSION_MAP controls per-module RBAC access. Queries `*_log` tables via JSON_EXTRACT to count actions since admin's last visit
- **AdminBadgeSeenModel** (`raptor/template/`) - Tracks `checked_at` datetime per admin per module. `last_seen_count` for file-count badges (manual, migrations)
- **BadgeRouter** (`raptor/template/`) - `GET /dashboard/badges` returns all badge counts as JSON, `POST /dashboard/badges/seen` marks a module as seen
- **initSidebarBadges()** (`dashboard.js`) - AJAX badge loader. Fetches badge data on page load, renders colored pills on matching sidebar links, POSTs seen on click
- **Sidebar badge CSS** (`dashboard.css`) - flex layout for nav-link with auto-margin badge positioning

### Changed
- **ContactController** - `contactSend()` log context now includes `auth_user` without `id` field so badge system distinguishes web visitors from admin users
- **Web NewsController** - `commentSubmit()` log context now includes `auth_user` without `id` field for the same reason
- **Dashboard Application** - Registered BadgeRouter
- **dashboard.html** - Added `initSidebarBadges()` call on DOMContentLoaded, bumped CSS/JS versions to v=2
- **CLAUDE.md** - Added "Dashboard Sidebar Badge System" section documenting architecture, BADGE_MAP, PERMISSION_MAP, and integration guide

---

## [1.9.1] - 2026-03-18
[1.9.1]: https://github.com/codesaur-php/Raptor/compare/v1.9.0...v1.9.1

Contact page layout redesign, sample data improvements.

### Changed
- **Contact page layout** - Photo and content moved to left column, contact info card and message form to right column. Photo renders with Fancybox lightbox like other page templates
- **Contact info card** - Removed "Холбогдох мэдээлэл" heading. Card hidden entirely when no contact info items exist
- **Settings seed data** - Added sample phone, email, address, open-hours, and social media links so contact info card is visible on fresh install
- **PagesSamples contact content** - Replaced email-centric text with generic message encouraging form use. Added Google Maps embed (Ulaanbaatar) as reference for developers. Added office-view.jpg as header image

---

## [1.9.0] - 2026-03-17
[1.9.0]: https://github.com/codesaur-php/Raptor/compare/v1.8.2...v1.9.0

Messages, Comments, Spam Protection, Discord notifications, template refactoring.

### Added
- **Messages module** - Contact form submissions saved to `messages` DB table with dashboard management (index, view, delete). Admin can mark messages as New/Read/Replied with note (phone/email contacted). Dashboard menu under Contents
- **Comments module** - News article comments with 1-level reply thread. `news_comments` table with `parent_id` (self FK) and `created_by` (admin/guest). Web visitors can post comments when `comment=1` on news. Admin can reply from dashboard
- **CommentsController (Dashboard)** - View all comments across news, reply to comments, delete with cascade. Accessible from Contents -> Comments menu and news-index publish column
- **Comments view news metadata** - Comments view page shows news article photo (if exists), publisher name, read count, published status, and full content so admin can read the article before replying to comments
- **ContactController** - Extracted from PageController to `Web\Service\ContactController` as standalone controller with own template
- **SpamProtectionTrait** - Unified spam protection: honeypot, HMAC token + timestamp, session rate limit, Cloudflare Turnstile (optional), link spam filter. Used by ContactController, NewsController, ShopController, LoginController
- **Cloudflare Turnstile** - Optional CAPTCHA via `RAPTOR_TURNSTILE_SITE_KEY` / `RAPTOR_TURNSTILE_SECRET_KEY` env vars. When keys are empty, Turnstile is skipped. Applied to contact form, comment form, order form, signup form
- **Link spam filter** - `checkLinkSpam()` blocks messages/comments with 3+ URLs
- **Comment reply validation** - Backend enforces 1-level reply limit: reply-to-reply requests rejected with 400 error. Both web (`NewsController::comment`) and dashboard (`NewsController::commentReply`) validate that `parent_id` points to a root comment
- **CI workflow** - `.github/workflows/ci.yml` default workflow runs on every push and PR: composer validate, PHP syntax check, merge conflict markers, debug statement detection, autoload verification
- **cPanel deploy workflow** - `docs/conf.example/cpanel.deploy.yml` restructured to use `workflow_run` trigger - waits for CI to succeed before deploying. CI failure skips deploy automatically
- **Discord notifications** - `settingsUpdated()` for settings changes (texts/files/options), `contentAction()` now shows changed fields on update. App URL shown in footer of all notifications
- **DiscordNotifier app URL** - All notification methods accept `$appUrl` parameter, displayed as embed footer
- **SettingsModel default config** - Initial settings include JSON structure for social media links and open hours
- **TextInitial keywords** - `messages`, `read`, `replied`, `reply`, `reply-method`, `contacted-by-phone`, `contacted-by-email`, `comments`, `write-comment`, `contact-info`, `send-message`, `working-hours`, `social-media`, `thank-you`, `server-error`, `confirm-delete`, `delete-with-replies`, `view`, `no-email`
- **Manual pages** - Comments manual (EN/MN) and Messages manual (EN/MN) added to `application/dashboard/manual/`. Covers overview, list view, actions (reply, delete), and required permissions for each module
- **Unit tests** - 14 SpamProtectionTrait tests (honeypot, HMAC, timestamp, rate limit, Turnstile skip/verify, link spam)

### Changed
- **`template()` renamed to `twigWebLayout()`** - Web TemplateController method renamed to match `twigDashboard()` pattern. Auto-maps `title`, `code`, `description`, `photo` from `$vars` to index layout SEO meta. All web controllers updated
- **Session routes use `/session/` prefix** - All web routes that write to `$_SESSION` now use `/session/` prefix (`/session/language/{code}`, `/session/contact-send`, `/session/order`, `/session/news/{id}/comment`). SessionMiddleware simplified to `str_starts_with($path, '/session/')`
- **Discord `contentAction()` title simplified** - Removed redundant `(Updated)` / `(Deleted)` label from title since description already states the action. ID moved from separate field to description as `#ID`
- **Contact form localization** - All hardcoded `lang == 'mn' ? ... : ...` text replaced with `|text` Twig filter using TextInitial keywords. JS text passed via Twig `{{ 'keyword'|text }}` directly
- **Contact form error display** - `alert()` replaced with Bootstrap modal for both success and error messages
- **Order form submit protection** - Submit button disabled with spinner while processing to prevent duplicate submissions
- **Comment form UX** - Send button disabled until both name and comment fields are filled. Placeholder shows `*` for required fields. Prevents confusing silent failures
- **Messages reply dialog** - "Contacted by email" checkbox disabled with "(No email)" label when message has no email address. Prevents admin from selecting an unavailable reply method
- **Dev request view** - Response thread order changed to `flex-column-reverse` (oldest first at bottom, newest at top)

### Removed
- **Pages `comment` field** - Removed from PagesModel, PagesController permission checks, page-insert/update/view templates. Comments are a News-only feature per best practice

---

## [1.8.2] - 2026-03-16
[1.8.2]: https://github.com/codesaur-php/Raptor/compare/v1.8.1...v1.8.2

Web layer action logging for public-facing controllers.

### Added
- **ShopController::order()** - Log when order form is opened, includes product_id and title when a product is pre-selected
- **SearchController::search()** - Log search queries with result count
- **SeoController::sitemap()** - Log HTML sitemap page views
- **NewsController::archive()** - Log news archive page views with selected year

---

## [1.8.1] - 2026-03-16
[1.8.1]: https://github.com/codesaur-php/Raptor/compare/v1.8.0...v1.8.1

Web layer naming cleanup and Mongolian text corrections.

### Changed
- **`Web\Seo` renamed to `Web\Service`** - `application/web/seo/` folder renamed to `application/web/service/`. Search, Sitemap, RSS are site-wide services, not SEO-specific
- **`SiteRouter` renamed to `WebRouter`** - Class and file renamed from `SiteRouter.php` to `WebRouter.php` for consistency with `Web\Application` naming
- **Mongolian text fix** - `'products'` keyword translation changed from "Бүтээгдэхүүнүүд" to "Бүтээгдэхүүн" in `TextInitial`, `DashboardMenus`, and `PagesSamples` seed data

### Documentation
- All `*.md` files updated to reflect renamed paths and namespaces (`CLAUDE.md`, `README.md`, `docs/mn/`, `docs/en/`)

---

## [1.8.0] - 2026-03-15
[1.8.0]: https://github.com/codesaur-php/Raptor/compare/v1.7.1...v1.8.0

Major code review, refactoring, shared middleware consolidation, seed data extraction, RBAC UI redesign, security hardening, and CLAUDE.md rewrite.

### Added
- **SearchController** (`Web\Seo`) - Dedicated web search controller with full-text search across Pages (title, slug, description, content, source, link), News (title, slug, description, content, source), Products (title, slug, description, content, link). Strips `<img>` tags from content before matching to avoid false positives from image URLs
- **PrivateFilesController security** - Blocks dangerous file extensions (php, phtml, phar, sh, bat, cmd, exe, ini, log, sql) and sensitive filenames (.env*, .htaccess, .htpasswd, .gitignore, composer.json, composer.lock) with 403 Forbidden
- **Owner access pattern** - Users without `_update`/`_delete` permission can edit/delete their own unpublished (`published=0`) records in News, Pages, Products controllers
- **Published view access** - `published=1` records viewable by all authenticated admins regardless of `_index` permission
- **Seed data classes** - Extracted seed data from Model files into dedicated classes to reduce runtime file size:
  - `NewsSamples`, `PagesSamples`, `ProductsSamples` - Content sample data
  - `DashboardMenus` - Dashboard sidebar menu structure
  - `PermissionsSeed` - RBAC permissions (parameterized queries)
  - `RolePermissionSeed` - Role-permission assignments + role creation (admin, manager, editor, viewer)
- **PrivateFilesBlockedTest** - 21 tests for blocked file extension/name security
- **RAPTOR_PASSWORD_RESET_MINUTES** - Renamed from `CODESAUR_PASSWORD_RESET_MINUTES`, added to `.env` and `.env.example`

### Changed
- **SessionMiddleware consolidated** - `Web\SessionMiddleware` and `Raptor\Authentication\SessionMiddleware` merged into single `Raptor\SessionMiddleware` with `needsWrite` closure constructor
- **LocalizationMiddleware consolidated** - `Web\LocalizationMiddleware` merged into `Raptor\Localization\LocalizationMiddleware` with `sessionKey` constructor parameter. Controllers read session key from `localization` attribute instead of hardcoding
- **Web namespace refactored** - `Web\Home\` renamed to `Web\Site\`, `HomeRouter` renamed to `SiteRouter`
- **RBAC UI redesigned** - `rbac-alias.html` changed from wide table matrix to responsive card-based layout (2 cards per row) with per-role permission checkboxes, select all/deselect all buttons, and badge counters. Roles and permissions display as `alias_name` format
- **ReferenceInitial refactored** - Single-line insert statements broken into readable multi-line format with numbered sections
- **error.html optimized** - particles.js moved from inline (~15KB) to CDN, CSS reformatted to multi-line
- **reset() permission lowered** - News, Pages, Products reset changed from `_delete` to `_index` permission so editors can clear sample data
- **reset() enhanced** - All reset methods now also delete `is_active=0` records alongside sample data
- **FilesController owner access** - `update()` allows own file edit, `deactivate()` allows own unattached (`record_id=0`) file delete without permission
- **UsersController email normalization** - Gmail `normalizeEmail()` added to `insert()` and `update()` methods
- **DevRequestController auth fix** - Replaced `!$this->getUserId()` with `!$this->isUserAuthorized()` in all 7 methods to prevent id=0 bypass
- **PagesModel::buildTree()** - Changed from `public` to `private` (only used internally)
- **Twig comments removed** - All `{# #}` comments in 31 HTML files converted to vanilla `<!-- -->` for template engine independence
- **SessionMiddlewareTest updated** - Tests both Web and Dashboard closure logic (21 tests)
- **Language dropdown** - Hidden when only one language is active (web navbar, dashboard login)

### Removed
- **products-read.html** - Unused template, route, and `ProductsController::read()` method removed
- **Web\SessionMiddleware** - Replaced by shared `Raptor\SessionMiddleware`
- **Web\LocalizationMiddleware** - Replaced by shared `Raptor\Localization\LocalizationMiddleware`

### Documentation
- **CLAUDE.md rewritten** - Restructured as forward-looking AI guide with "Adding a New Module" 11-step checklist, shared middleware configuration, owner access pattern, migration rules, and general conventions
- **CONTRIBUTING.md** - Removed `## Project Structure` section (duplicated in CLAUDE.md)
- **docs/en/api.md, docs/mn/api.md** - Updated SessionMiddleware and LocalizationMiddleware docs with shared middleware details
- **docs/en/README.md, docs/mn/README.md** - Updated directory tree (`web/site/`, `SessionMiddleware.php` location), removed deleted files

---

## [1.7.1] - 2026-03-13
[1.7.1]: https://github.com/codesaur-php/Raptor/compare/v1.7.0...v1.7.1

Shop router registration moved from shared base application to Dashboard application.

### Changed
- **Dashboard\Application** - Shop module routers (`ProductsRouter`, `OrdersRouter`) now registered here instead of in `Raptor\Application`, since shop routes are dashboard-specific
- **Raptor\Application** - Removed `Dashboard\Shop\ProductsRouter` and `Dashboard\Shop\OrdersRouter` registration from shared base class

---

## [1.7.0] - 2026-03-13
[1.7.0]: https://github.com/codesaur-php/Raptor/compare/v1.6.0...v1.7.0

Anti-spam hardening: username gibberish detection, Gmail email normalization, scoring-based validation system.

### Added
- **Username Gibberish Detection** - Score-based system to reject bot-generated random usernames (`LoginController::isGibberishUsername()`)
  - Shannon entropy check: high randomness in character distribution (+2/+3 points)
  - Vowel ratio check: rejects consonant-heavy nonsense (+1/+3 points)
  - Consecutive consonant check: flags long unpronounceable clusters (+1/+2 points)
  - Case change ratio check: detects random casing patterns like `CdIrBVTolz` (+1/+3 points)
  - Score threshold of 3+ required to reject (no single check causes false rejection)
  - Designed for Mongolian transliteration compatibility (`munkhtseteg`, `tserenbold` pass cleanly)
- **Username Format Validation** - Regex: 3-63 characters, must start with a letter, allows letters/numbers/underscores/dots
- **Gmail Email Normalization** - `LoginController::normalizeEmail()` strips Gmail alias tricks before uniqueness checks
  - Removes dots from Gmail/Googlemail local parts (`y.i.r@gmail.com` -> `yir@gmail.com`)
  - Strips `+` sub-addressing (`user+spam@gmail.com` -> `user@gmail.com`)
  - Normalizes `googlemail.com` -> `gmail.com`
  - Normalized email stored in database, preventing future bypass with dot variations
- **Anti-Spam Tests** - `LoginSpamProtectionTest` with 43 test cases
  - 22 valid username tests (Mongolian, Slavic, German names, common patterns)
  - 8 gibberish username tests (random chars, keyboard smash, bot patterns)
  - 8 Gmail normalization tests (dots, plus, googlemail, uppercase)
  - 4 non-Gmail preservation tests (Yahoo, Outlook, custom domains)
  - Real spam signup integration test (`CdIrBVTolzIvAxjqdF` + `yir.o.h.obo.j.u.k.1.0@gmail.com`)

---

## [1.6.0] - 2026-03-13
[1.6.0]: https://github.com/codesaur-php/Raptor/compare/v1.5.0...v1.6.0

Database migration system, security hardening (removed dangerous dev tools), error log integration, route optimization, documentation update.

### Added
- **Database Migration** - SQL file-based forward-only migration system (`Raptor\Migration`)
  - `MigrationRunner` - Core engine: parse SQL files, execute pending migrations, track status
  - `MigrationMiddleware` - PSR-15 middleware that auto-runs pending migrations on each request
  - `MigrationController` - Dashboard UI for viewing migration status and SQL file contents (system_coder only)
  - `MigrationRouter` - Routes: `/dashboard/migrations`, `/dashboard/migrations/status`, `/dashboard/migrations/view`
  - Pending migrations in `database/migrations/`, ran migrations moved to `database/migrations/ran/`
  - Advisory lock (`GET_LOCK`) prevents concurrent migration execution
  - Rename with unlink fallback for servers with permission issues
  - `.htaccess` protection (`deny from all`) blocks direct browser access to SQL files
  - Nginx `.sql` file deny rule added to `.nginx.conf.example`
  - `database/migrations/example_never_runs.sql` - Example pending migration for testing
- **Error Log Tab** - PHP error.log viewer integrated as the last tab in Access Logs page (`/dashboard/logs`)
  - Lazy-loaded on tab activation via AJAX (`error-log-read` endpoint)
  - Pagination support (newest entries first)
  - Syntax-highlighted log output
  - Visible to all `system_coder` users (no user ID restriction)
- **Migration Tests** - 35 tests covering the migration system
  - `tests/Unit/Migration/MigrationRunnerTest.php` - 22 unit tests (parseFile, splitStatements, hasPending, status)
  - `tests/Integration/Migration/MigrationRunnerIntegrationTest.php` - 13 integration tests (migrate, partial failure, lock, file naming)
- **Middleware Pipeline** - `MigrationMiddleware` registered after `MySQLConnectMiddleware` in both Dashboard and Web applications
- **Autoload** - `Raptor\Migration\` PSR-4 namespace added to `composer.json`

### Changed
- **Error Log** - Moved from standalone Development Tools page to Access Logs tab; `errorLogRead()` method moved from `FileManagerController` to `LogsController`
- **Route Name Optimization** - Removed `->name()` from 11 routes across 7 routers whose names were never referenced via `|link` or `redirectTo()`:
  - `organization-view` (OrganizationRouter)
  - `manage-menu` (TemplateRouter)
  - `user-view` (UsersRouter, removed from `update` route renamed path)
  - `product-read`, `product-view` (ProductsRouter)
  - `order-view` (OrdersRouter)
  - `private-files-read`, `news-view`, `page-view` (ContentsRouter)
  - `products-page`, `sitemap-xml` (HomeRouter)
- **Spam Protection** - Login rate limit reduced from 3s to 2s; minimum form fill speed reduced from 2s to 1s
- **Dashboard Sidebar** - Removed entire "Developer Tools" section (file-manager, sql-terminal, error-log links)
- **DevelopmentRouter** - Only DevRequest routes remain; SQL Terminal, Error Log, File Manager routes removed
- **Documentation** - All 5 markdown files updated (`README.md`, `docs/en/README.md`, `docs/mn/README.md`, `docs/en/api.md`, `docs/mn/api.md`)

### Removed
- **SqlTerminalController** - Production security risk: allowed arbitrary SQL execution. Completely removed (`SqlTerminalController.php`, `sql-terminal.html`)
- **FileManagerController** - Production security risk: allowed arbitrary file system browsing. Completely removed (`FileManagerController.php`, `filemanager.html`)
- **Error Log Page** - Standalone error log page removed (`error-log.html`); functionality preserved in Access Logs tab
- **Localization** - Removed 7 unused text keywords: `developer`, `error-log`, `file-manager`, `file-too-large-preview`, `sql-query-placeholder`, `sql-terminal`, `execute`

---

## [1.5.0] - 2026-03-12
[1.5.0]: https://github.com/codesaur-php/Raptor/compare/v1.4.0...v1.5.0

Pages module simplification: flexible type system, removed read mode, improved navigation filtering, getExcerpt fix, Discord notification improvement.

### Added
- **Pages** - User-editable `type` input with dropdown suggestions (`menu`, `mega-menu`, `special`) on insert and update forms; type field is free-text with max 32 characters
- **Pages** - `has_children` alert (alert-primary) on update and view forms warning that parent page content/links may not be directly used
- **Pages** - `link` alert (alert-info) on update and view forms explaining that linked pages redirect from menu
- **Localization** - New text keywords: `parent-page-content-warning` (mn/en), `link-page-content-warning` (mn/en)

### Changed
- **Pages** - Type system simplified: replaced rigid `nav`/`content`/`link` enum with flexible user-editable field; default changed from `content` to `menu`
- **Pages** - All form fields (description, content, link, featured) always visible regardless of type or children; removed all conditional field hiding
- **Pages** - `type` field is now editable on update (previously locked after creation)
- **Pages** - Navigation tree (`pages-nav.html`): folder icon based on `hasChildren` instead of `type=nav`; type and category shown as badges
- **Pages** - Seed data updated to use `type='menu'` (relies on column default)
- **PagesModel** - `getNavigation()` now filters by `type='menu' OR type LIKE '%-menu'`; only menu-type pages appear in website navigation
- **getExcerpt()** - Fixed text sticking together when stripping HTML block tags (`<p>text1.</p><p>text2.</p>` now produces `text1. text2.` instead of `text1.text2.`); applied to `PagesModel`, `NewsModel`, `ProductsModel`
- **DiscordNotifier** - `contentAction()` now includes admin name in description (e.g. "**Narankhuu N** updated a page.") and removed redundant Type/Action fields that duplicated the title

### Removed
- **Pages** - `read()` method and `page-read.html` template removed; blog-style page reading no longer supported
- **News** - `read()` method and `news-read.html` template removed; blog-style news reading no longer supported
- **Routes** - `page-read` and `news-read` routes removed from `ContentsRouter`
- **Pages** - Read button removed from `pages-index.html` action buttons
- **News** - Read button removed from `news-index.html` action buttons
- **Localization** - Removed unused `parent-page` and `parent-page-hint` text keywords

---

## [1.4.0] - 2026-03-11
[1.4.0]: https://github.com/codesaur-php/Raptor/compare/v1.3.1...v1.4.0

Log system consolidation, model rename, Discord notification improvement, automated testing, product RBAC permissions. Removed SQLite support from Raptor due to lack of practical use case.

### Added
- **RBAC** - New `system_product_*` permissions for Shop module: `product_index`, `product_insert`, `product_update`, `product_publish`, `product_delete`; assigned to admin (all), manager (all), editor (index/insert/update/publish), viewer (index)
- **Automated Testing** - PHPUnit 11 test suite with unit and integration tests (47 tests, 83 assertions)
  - Unit tests: `User::is()`, `User::can()`, `Controller::text()`, `Controller::getLanguageCode()`
  - Integration tests: `UsersModel`, `OrganizationModel`, `SignupModel` CRUD, RBAC seed data verification, JWT encode/decode
  - Transaction-based test isolation (auto-rollback)
  - `.env.testing` for test database configuration
- **DiscordNotifier** - `newOrder()` method now includes customer phone number in Discord embed notification
- **DiscordNotifier** - `newDevRequest()` method for notifying when a new development request is created (shows author and assigned user)
- **DiscordNotifier** - `devRequestUpdated()` method for notifying when a development request receives a response (shows responder and new status)
- **DevRequestController** - Discord notifications on `store()` and `respond()` actions

### Changed
- **Shop RBAC** - Shop module (`ProductsController`, `OrdersController`, templates) now uses dedicated `system_product_*` permissions instead of shared `system_content_*`
- **OrdersModel → ProductOrdersModel** - Renamed class and file; table name changed from `orders` to `products_orders`
- **Log consolidation** - All shop-related logs (orders CRUD, products CRUD, orderSubmit) now write to a single `'product'` log channel instead of dynamic `$table` names
- **Log consolidation** - All development module logs (DevRequest, FileManager, SqlTerminal) now write to a single `'development'` log channel instead of separate `'dev_requests'`, `'file_manager'`, `'sql_terminal'` channels
- **Error logging** - All non-critical `error_log()` calls across the application now only execute when `CODESAUR_DEVELOPMENT` is `true`; critical exception/error handlers remain unconditional
- **FileManagerController** - All UI-facing text (exception messages, log messages, response strings) changed from Mongolian to English; PHPDoc and code comments remain in Mongolian
- **SqlTerminalController** - All UI-facing text (exception messages, log messages, response strings) changed from Mongolian to English; PHPDoc and code comments remain in Mongolian

### Removed
- **SQLite support** - Removed `SQLiteConnectMiddleware` and all SQLite-specific code branches (sqlite_master queries, PRAGMA table_info, sqlite_sequence, json_extract without JSON_UNQUOTE). Application now supports MySQL and PostgreSQL only
- **Controller::errorLog()** - Removed `protected final function errorLog(\Throwable $e)` method; all call sites replaced with inline `if (CODESAUR_DEVELOPMENT) { \error_log(...); }` pattern

---

## [1.3.1] - 2026-03-10
[1.3.1]: https://github.com/codesaur-php/Raptor/compare/v1.3.0...v1.3.1

Honeypot spam field cleanup and numeric payload sanitization for Products.

### Fixed
- **LoginController** - Signup form honeypot fields (`website`, `_ts`, `_token`) were not removed from payload before DB insert, causing "column not found" error on the `signup` table
- **ProductsController** - Empty string values from frontend for numeric columns (`price`, `sale_price`, `stock`, etc.) caused database type errors on insert and update; added `sanitizePayload()` to convert empty strings to `null` for numeric fields

---

## [1.3.0] - 2026-03-10
[1.3.0]: https://github.com/codesaur-php/Raptor/compare/v1.2.1...v1.3.0

Dev Request user assignment and CI/CD quality checks.

### Added
- **Dev Request assignment** - Assign requests to a specific user when creating
  - `assigned_to` column added to `dev_requests` table (FK to `users.id`)
  - User selector dropdown on create form with two groups: Developers (coder role / development permission) shown first, then all other users
  - Only the assigned user receives the new-request email notification (instead of all dev users)
  - Assigned user can view, respond to, and deactivate the request
  - Assigned user name displayed in request list and detail view
- **CI/CD quality checks** - `validate` job added to `cpanel.deploy.yml` (runs before deploy)
  - `composer validate --strict` - Validates composer.json structure
  - PHP syntax check (`php -l`) on all application and public PHP files
  - Merge conflict marker detection in PHP, HTML, JS, CSS, JSON, YML files
  - Debug statement detection (`var_dump`, `dd`, `print_r`) as warning
  - PSR-4 autoload verification (`composer dump-autoload --strict-psr`)
- **Localization** - New dashboard text entries: `assign-to`, `assigned-to`, `developers`, `fill-required-fields`, `other-users`

### Changed
- **Dev Request notifications** - `notifyNewRequest()` now sends email only to the assigned user instead of broadcasting to all users with coder role or development permission
- **Dev Request permissions** - `list()`, `view()`, `respond()`, `deactivate()` now grant access to the assigned user in addition to the creator and users with `system_development` permission
- **Dev Request list query** - Users without `system_development` permission now see both their own requests and requests assigned to them

---

## [1.2.1] - 2026-03-09
[1.2.1]: https://github.com/codesaur-php/Raptor/compare/v1.2.0...v1.2.1

Comprehensive PHPDoc documentation and HTML comment security cleanup.

### Added
- **PHPDoc** - Full Mongolian PHPDoc comments for all Web layer PHP files
  - `HomeRouter`, `HomeController`, `PageController`, `NewsController`, `ShopController`, `SeoController`
  - Class-level docs with route listings, method-level docs with `@param`, `@return`, `@throws`
- **PHPDoc** - Full Mongolian PHPDoc comments for all Dashboard Shop PHP files
  - `ProductsModel`, `ProductsController`, `OrdersModel`, `OrdersController`
- **PHPDoc** - Expanded PHPDoc for Raptor module files
  - `DiscordNotifier` - All 6 notification methods documented
  - `DevResponseModel` - All 3 methods documented
- **Spam protection docs** - Detailed Mongolian comments in `ShopController::order()` explaining honeypot field, HMAC token, and timestamp mechanism

### Changed
- **HTML comment security** - Removed all `<!-- -->` doc comments from public Web templates to prevent information disclosure
  - Affected: `index.html`, `home.html`, `page.html`, `news.html`, `news-type.html`, `archive.html`, `search.html`, `sitemap.html`, `products.html`, `product.html`, `order.html`, `order-success.html`, `page-404.html`
- **Honeypot comment removal** - Removed honeypot-identifying comments from HTML templates (`order.html`, `login.html`) to prevent bot detection bypass; moved explanations to PHP controller code
- **Twig to HTML comments** - Converted all remaining Twig `{# #}` inline comments to vanilla `<!-- -->` in `sitemap.html` and `archive.html` section markers
- **Dashboard HTML** - Removed internal architecture doc comments from Dashboard Shop templates (`products-index`, `products-insert`, `products-update`, `products-view`, `products-read`, `orders-index`, `orders-view`)

---

## [1.2.0] - 2026-03-09
[1.2.0]: https://github.com/codesaur-php/Raptor/compare/v1.1.0...v1.2.0

E-commerce shop module, spam protection, Discord notifications, SEO features, development tools, dashboard statistics, and Web layer refactor.

### Added
- **Shop Module** - Full e-commerce with products and orders
  - `ProductsModel` - Product catalog with slug generation, excerpt, price/sale_price, SKU, barcode, sizes, colors, stock, photo, category, featured
  - `OrdersModel` - Customer orders with product reference, customer info, quantity, status tracking
  - `ProductsController`, `ProductsRouter` - Dashboard CRUD for products
  - `OrdersController`, `OrdersRouter` - Dashboard CRUD for orders
  - Sample product data seeded on first run
- **Notification Module** - Discord webhook integration (`Raptor\Notification\DiscordNotifier`)
  - Notification types: user signup request, user approval, new order, order status change, content actions (insert/update/delete/publish)
  - Color-coded embeds (SUCCESS, INFO, WARNING, DANGER, PURPLE)
  - Graceful skip when webhook URL is not configured
  - `RAPTOR_DISCORD_WEBHOOK_URL` env variable added to `.env.example`
- **Development Module** - Admin development tools (`Raptor\Development\DevelopmentRouter`)
  - Dev Requests - Development request tracking system (`DevRequestController`, `DevRequestModel`, `DevResponseModel`)
  - SQL Terminal - Database query interface (`SqlTerminalController`)
  - Error Log viewer
  - File Manager (`FileManagerController`)
  - New RBAC permission: `development:development`
- **SEO & Content Discovery** (Web layer)
  - `SeoController` - Search across pages, news, and products (min 2 chars)
  - Sitemap page (`/sitemap`) - Hierarchical page tree with news/product counts
  - XML sitemap (`/sitemap.xml`) - For search engines with lastmod, changefreq, priority
  - RSS 2.0 feed (`/rss`) - Latest 20 news and 20 products
  - Search form added to web navbar
  - Footer links: Archive, Sitemap, RSS
  - RSS `<link>` tag in web layout `<head>`
- **News Archive** - `/archive` route with year/month filtering and grouping
- **News by Type** - `/news/type/{type}` route for category-based news listing
- **Spam Protection** - Comprehensive anti-spam on login, signup, forgot, and order forms
  - Honeypot hidden field (`website` input)
  - HMAC token validation with timestamp
  - Rate limiting (login 3s, signup 5s, forgot 10s)
  - Form expiration check (1 hour max)
  - Minimum fill speed check (2 seconds)
- **Web Controllers** - Separated `HomeController` logic into dedicated controllers
  - `PageController` - Page and contact display (`pageById`, `page`, `contact`)
  - `NewsController` - News display (`newsById`, `news`, `newsType`, `archive`)
  - `ShopController` - Product listing, product detail, order form/submit, order confirmation email
  - `SeoController` - Search, sitemap, XML sitemap, RSS feed
- **Web Routes** - New public routes
  - `/page/{uint:id}` and `/news/{uint:id}` - Access by ID (redirect to slug)
  - `/products`, `/product/{uint:id}`, `/product/{slug}` - Product pages
  - `/order` (GET+POST) - Order form and submission
  - `/search`, `/sitemap`, `/sitemap.xml`, `/rss` - SEO routes
- **Localization** - 100+ new i18n text entries (MN/EN) for shop, archive, search, newsletter, and UI labels
- **Database Indexes** - Performance optimization
  - `files` table: `idx_record_id (record_id, is_active)`
  - `logger` tables: `idx_created_at (created_at)`
  - `products` table: `idx_active_published (is_active, published)`
  - `orders` table: `idx_active_status (is_active, status)`
- **Dashboard Statistics** - `HomeController::stats()`, `logStats()`, `refreshCache()` JSON endpoints for web traffic and log statistics
- **RBAC** - Detailed descriptions added to all system permissions; new permissions for template and development modules
- **cPanel Deploy** - GitHub Actions workflow example (`docs/conf.example/cpanel.deploy.yml`) for automated FTP deployment to cPanel on push to `main`

### Changed
- **Web HomeController** - Refactored: page, news, contact methods moved to `PageController`, `NewsController`; simplified to basic news listing
- **Web HomeRouter** - Completely restructured with all new routes for shop, SEO, archive, news type
- **Web templates** - All hardcoded MN/EN text replaced with i18n `|text` filter (`'read-more'|text`, `'attachments'|text`, `'file'|text`, `'size'|text`, `'type'|text`, `'source'|text`, `'deactivated'|text`)
- **Web index.html** - Menu titles rendered with `|raw` filter (allows HTML); search form in navbar; RSS feed link in head
- **Controller** - Now logs `HTTP_USER_AGENT` alongside `REMOTE_ADDR` in action logs
- **ContainerMiddleware** - Registers `DiscordNotifier` service in DI Container
- **Application** - Registers `ProductsRouter`, `OrdersRouter`, `DevelopmentRouter`
- **Login templates** - Terms & conditions text now uses i18n keys instead of hardcoded bilingual text
- **UsersController** - Sends Discord notification when admin approves a new user
- **composer.json** - New autoloader namespaces: `Dashboard\Shop\`, `Raptor\Notification\`, `Raptor\Development\`

---

## [1.1.0] - 2026-03-06
[1.1.0]: https://github.com/codesaur-php/Raptor/compare/v1.0.0...v1.1.0

Codebase-wide cleanup enforcing old-school coding style. Codesaur dependency patch versions bumped.

### Changed
- **Old-school coding style** - Removed all emoji and decorative Unicode characters from every project file (MD, PHP, HTML, CSS, JS, conf)
  - Unicode arrows (->), box-drawing characters (|, \, -), smart quotes, ellipsis, bullets, NBSP, ZWJ, BOM all replaced with plain ASCII equivalents
  - Only Mongolian Cyrillic text and ASCII characters remain throughout the project
- **codesaur/http-application** ^6.0.0 -> ^6.0.1
- **codesaur/dataobject** ^9.0.0 -> ^9.0.2
- **codesaur/http-client** ^2.0.0 -> ^2.0.4
- **codesaur/template** ^3.0.0 -> ^3.0.1
- **codesaur/container** ^3.1.0 -> ^3.1.3

### Fixed
- **moedit** - Internal copy/paste not working: pasting content copied from within the editor produced no result. Root cause was the image detection check intercepting paste events when clipboard contained both image and HTML data. Restructured `_handlePaste` to check HTML content before image detection. Added `_insertHtmlAtCursor()` helper with proper `_emitChange()` sync.
- **composer.json** - Removed stray emoji from post-install script output

---

## [1.0.0] - 2026-02-25

**`codesaur/raptor` v1.0.0 - Stable Release.** Package renamed from `codesaur/indoraptor` to `codesaur/raptor`.

### Changed
- **Package renamed** `codesaur/indoraptor` -> `codesaur/raptor`
- **Environment variables** `INDO_*` prefix -> `RAPTOR_*` prefix
- **Session name** `indoraptor` -> `raptor`
- **Default database name** `indoraptor` -> `raptor`
- `indolog()` method renamed to `log()`

---

## [0.8.0] - 2026-02-24

### Added
- **Pages** - "Parent page (navigation)" switch in page-insert form to create `type=nav` pages directly
- **Pages** - `link` field with frontend (JS) and backend (`isValidLink()`) validation; supports URL, local path (`/path`), `mailto:`, `tel:`
- **Pages** - `PagesController::read()` guards: published check (403), parent check (403), link redirect
- **Pages** - Auto-reset `is_featured=0` on parent page when a child is inserted or `parent_id` changes
- **Sample Data Reset** - Reset button in pages-index, pages-nav, news-index when sample data exists
  - `PagesController::reset()` and `NewsController::reset()` methods
  - Routes: `pages-sample-reset`, `news-sample-reset`
  - Detection: `created_by IS NULL AND created_at = published_at AND category='sample'`
  - Truncates main table and files table, resets auto-increment to ID=1

### Changed
- **Pages** - Removed page type wizard from page-insert; all fields (title, description, content, link) visible in a single form
- **Pages** - Merged Content and Link types into one form; `link` input always visible below content editor
- **Pages** - Parent pages (pages with children) hide description, content, link, featured fields in page-update
- **Pages** - page-view shows description/content/link conditionally (only when non-empty), link with `border-info`
- **Pages / News** - Action buttons in index/nav: link button if `link` is set and not parent, read button if no link and not parent, neither if parent or unpublished
- **Pages / News** - Seed data uses `category='sample'` instead of `'general'`, `created_by` and `published_by` are `NULL`
- **News** - news-index layout: "New" button moved from header to filter row (matches pages-index layout)

### Fixed
- **Pages** - `type="url"` input rejecting local paths like `/contact`; changed to `type="text"`

---

## [0.7.2] - 2026-02-12

### Added
- **moedit** - `attachFiles` option to enable/disable the Attach File button (`true` by default, `false` disables the button)

### Fixed
- **moedit** - Header image toolbar group responsive CSS selectors now require `.is-visible` class, preventing hidden elements from displaying incorrectly

---

## [0.7.1] - 2026-02-10

### Fixed
- **SettingsController** - Settings config save crash when no record exists (empty table)
  - `LocalizedModel::insert()` requires non-empty content, but Config tab only sends non-localized fields
  - Now populates empty localized content for each language before insert
- **SettingsController** - Localized field change detection used swapped indices (`[$field][$code]` instead of `[$code][$field]`)
- **TextController** - Same swapped localized indices bug in `update()` method

---

## [0.7.0] - 2026-02-09

### Added
- **Pages Navigation** (`/dashboard/pages/nav`) - Tree-structured page navigation management
  - Display cards grouped by language
  - Published/unpublished distinction: blue title + green check icon / eye-slash icon
  - Display page photo thumbnails
- **Removed the "System page" concept** - All published pages are now visible in navigation
- Insert form: automatic position calculation (parent position + 10 for first child, max sibling + 10 for subsequent, max top-level + 100 for same-language)
- Page view: position field (language, category, position displayed in a single row)
- **OG meta tags** for page and news: `og:title`, `og:description`, `og:image`, `og:type`, `og:site_name`, logo fallback
- `<title>` tag: "Record Title | Site Title" format for content pages

### Changed
- `getNavigation()`: removed `(type='nav' OR parent_id>0)` filter, added `slug` field
- `getFeaturedPages()`: added `slug` field
- `getInfos()`: added `position` and `code` fields
- Web page/news links use `slug` instead of `id` (`/page/{slug}`, `/news/{slug}`)

---

## [0.6.0] - 2026-02-06

Full CMS framework major release. Multi-DB support, DI Container, OpenAI integration, full documentation.

### Added
- `SQLiteConnectMiddleware` - SQLite database support (on top of MySQL, PostgreSQL)
- `ContainerMiddleware` - PSR-11 Dependency Injection Container (`codesaur/container`)
- OpenAI integration - moedit editor AI button (`INDO_OPENAI_API_KEY`)
- Image optimization via GD extension - `INDO_CONTENT_IMG_MAX_WIDTH`, `INDO_CONTENT_IMG_QUALITY`
- `ext-gd`, `ext-intl` PHP extension requirements
- `is_featured` field in Pages module (featured pages for footer)
- File attachments in Web templates (page.html, news.html)
- `basename` Twig filter for Web templates
- Native JS file upload (replaced Plupload external library)
- Native JS image preview (replaced Fancybox external library)
- `INDO_JWT_SECRET` auto-generation via Composer post-install script
- Full MN/EN documentation (`docs/mn/`, `docs/en/`)
- API Reference (`docs/mn/api.md`, `docs/en/api.md`)
- `CHANGELOG.md` version history

### Changed
- `codesaur/http-application` ^5.7 -> **^6.0.0** (breaking)
- `codesaur/dataobject` ^7.1 -> **^9.0.0** (breaking, `localized` access pattern changed)
- `codesaur/template` ^1.6 -> **^3.0.0** (breaking)
- `firebase/php-jwt` >=6.7 -> **^7.0.2** (HS256 key length requirement added)
- `getImportantMenu()` -> `getFeaturedPages()` refactor
- `ForgotModel`, `SignupModel` removed - authentication flow simplified
- `JsonExceptionHandler` removed
- Full PHPDoc added to all PHP files

### Fixed
- `localized` access pattern bugs in dashboard templates (DataObject ^9.0 refactor)
- Web news.html file list incorrect column names
- Web page.html stray character

---

## [0.5.0] - 2025-09-22

Content modules reorganized into subdirectories. Web layer template system improved. Multi-DB support started.

### Added
- `Web\Template\` namespace - Web ExceptionHandler, TemplateController
- `Web\SessionMiddleware`, `Web\LocalizationMiddleware` (separate from Dashboard)
- `PostgresConnectMiddleware` - PostgreSQL support
- `SignupModel` (user signup request model)

### Changed
- `PDOConnectMiddleware` -> `MySQLConnectMiddleware` renamed
- Content modules moved to subdirectories: `file/`, `news/`, `page/`, `reference/`, `settings/`
- Localization module separated: `language/`, `text/` subdirectories
- `UserRequestModel` -> `SignupModel` renamed
- `codesaur/dataobject` ^5.2 -> ^7.1

---

## [0.4.0] - 2024-09-28

**Full architectural overhaul.** Migrated from library to project/application structure. Two-layer (Dashboard + Web) architecture introduced.

### Added
- `application/` new directory structure (`raptor/`, `dashboard/`, `web/`)
- `Raptor\`, `Dashboard\`, `Web\` new namespaces (migrated from `Raptor\`)
- `public_html/index.php` entry point - automatic Dashboard/Web routing
- `.env` configuration (`vlucas/phpdotenv`) - all settings in environment file
- Twig template engine support (`codesaur/template`)
- `Raptor\Application` - Dashboard middleware pipeline base
- `Web\Application` - Public website application
- `Dashboard\Application` - Dashboard application
- Brevo (SendInBlue) email API (`getbrevo/brevo-php`)
- `SettingsMiddleware` - system settings middleware
- `DashboardTrait` - Dashboard UI common functions
- `TemplateRouter`, `TemplateController` - Dashboard template system
- `ErrorHandler` - Dashboard template-based error handling
- PSR-3 `Logger` class (built-in)
- `Mailer` class (Brevo + PHPMailer)
- `User` value object (profile, organization, RBAC permissions)
- `psr/log` ^3.0 direct dependency

### Changed
- **`Raptor\` -> `Raptor\`** full namespace change
- **`src/` -> `application/raptor/`** directory structure updated
- `IndoApplication` -> `Raptor\Application`
- `IndoController` -> `Raptor\Controller`
- `codesaur/rbac` -> `Raptor\RBAC\` built-in (separated from external package)
- `codesaur/logger` -> `Raptor\Log\Logger` built-in
- `phpmailer/phpmailer` re-added (^6.8)
- `codesaur/dataobject` ^5.2 re-added (was removed in v5-8)

### Removed
- `InternalRequest`, `InternalController` classes removed
- `JsonResponseMiddleware` removed
- `RecordController`, `StatementController` removed
- `codesaur/rbac` external dependency removed (built-in)
- `codesaur/logger` external dependency removed (built-in)

---

## [0.3.0] - 2024-07-30

Simplified to minimal base library. All CMS modules removed, only core classes remain.

### Changed
- Framework simplified to minimal base (9 PHP files)
- `codesaur/rbac` ^2.3 -> ^2.5
- `codesaur/logger` ^1.5 -> ^2.0

### Removed
- Auth, Localization, Contents, File, Mailer modules fully removed
- `CountriesController`, `CountriesModel`
- `LanguageController`, `LanguageModel`
- `TextController`, `TextModel`, `TextInitial`
- `FilesController`, `FilesModel`, `FileModel`, `FilesRouter`
- `NewsModel`, `PagesController`, `PagesModel`
- `ReferenceController`, `ReferenceModel`, `ReferenceInitial`
- `SettingsModel`
- `MailerController`, `MailerModel`, `MailerRouter`
- `LoggerController`, `LoggerModel`, `LoggerRouter`

---

## [0.2.0] - 2023-07-19

CMS modules added. PHP 8.2.1 requirement. File management, news, pages, references, and settings modules.

### Added
- `Contents` module: `NewsModel`, `PagesController`, `PagesModel`, `ReferenceController`, `ReferenceModel`, `SettingsModel`
- `File` module: `FileModel`, `FilesController`, `FilesModel`, `FilesRouter`
- `Mailer` module: `MailerController`, `MailerModel`, `MailerRouter`
- `Record` module: `RecordController`, `RecordRouter`
- `Statement` module: `StatementController`, `StatementRouter`
- `PDOConnectMiddleware` - DB connection middleware
- `JsonResponseMiddleware` - JSON response middleware
- `JsonExceptionHandler` - JSON exception handler
- `InternalRequest` - Internal API request
- `ContentsRouter` - CMS routes
- `codesaur/http-client` dependency added

### Changed
- PHP >=7.2 -> **PHP 8.2.1** requirement
- `Account\` -> `Auth\` namespace changed
- `TranslationController` -> `TextController` renamed
- `firebase/php-jwt` >=5.2 -> >=6.7
- `codesaur/http-application` >=1.2 -> >=5.5.2
- `codesaur/rbac` >=1.4 -> >=2.3.7

### Removed
- `phpmailer/phpmailer` direct dependency removed
- `codesaur/dataobject` direct dependency removed
- `codesaur/localization` direct dependency removed
- `AccountErrorCode` class removed

---

## [0.1.0] - 2021-04-18

Initial release. REST API-based server framework.

### Features
- PHP >=7.2 support
- `IndoApplication` - PSR-15 Application base
- `IndoController` - Base Controller
- `IndoExceptionHandler` - Exception handler
- JWT authentication (`firebase/php-jwt`)
- Account module: `AuthController`, `AccountController`, `AccountRouter`
- `OrganizationModel`, `OrganizationUserModel`
- `ForgotModel` - Password recovery
- Localization module: `LanguageController`, `CountriesController`, `TranslationController`
- Logger module: `LoggerController`, `LoggerRouter`
- RBAC access control (`codesaur/rbac`)
- PSR-3 logging system (`codesaur/logger`)
- Email sending (`phpmailer/phpmailer`)
- `codesaur/http-application` PSR-7/PSR-15 base
- `codesaur/dataobject` PDO ORM
- MIT License

[1.0.0]: https://github.com/codesaur-php/Raptor/releases/tag/v1.0.0
