# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/) and this project adheres to [Semantic Versioning](https://semver.org/).

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
- **Pages** - Auto-reset `is_featured=0`, `comment=0` on parent page when a child is inserted or `parent_id` changes
- **Sample Data Reset** - Reset button in pages-index, pages-nav, news-index when sample data exists
  - `PagesController::reset()` and `NewsController::reset()` methods
  - Routes: `pages-sample-reset`, `news-sample-reset`
  - Detection: `created_by IS NULL AND created_at = published_at AND category='sample'`
  - Truncates main table and files table, resets auto-increment to ID=1

### Changed
- **Pages** - Removed page type wizard from page-insert; all fields (title, description, content, link) visible in a single form
- **Pages** - Merged Content and Link types into one form; `link` input always visible below content editor
- **Pages** - Parent pages (pages with children) hide description, content, link, featured, comment fields in page-update
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
