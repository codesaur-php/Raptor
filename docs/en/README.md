# Raptor Framework - Full Documentation

[![PHP Version](https://img.shields.io/badge/php-%5E8.2.1-777BB4.svg?logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)

> **codesaur/raptor** - A multi-layered PHP CMS framework built on PSR standards.

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Installation](#2-installation)
3. [Configuration (.env)](#3-configuration)
4. [Architecture](#4-architecture)
5. [Middleware Pipeline](#5-middleware-pipeline)
6. [Modules](#6-modules) (6.1-6.13 Core | 6.14-6.22 New: Shop, Reviews, Notification, Development, SEO, Spam Protection, Migration, Messages, Comments)
7. [Twig Template System](#7-twig-template-system)
8. [Routing](#8-routing)
9. [Controller](#9-controller)
10. [Model](#10-model)
11. [Testing](#11-testing)
12. [Usage Examples](#12-usage-examples)

---

## 1. Introduction

`codesaur/raptor` is a PHP framework with a two-layer architecture: **Web** (public site) and **Dashboard** (admin panel), built on PSR-7/PSR-15 middleware standards.

> **Note:** This package is the successor of `codesaur/indodaptor` (500+ installs), which has been removed from Packagist. A new package `codesaur/raptor` was created with a full code refactor, as the name "Indoraptor" is a trademark of Universal Pictures.

### Key Features

- **PSR-7/PSR-15** middleware-based architecture
- **JWT + Session** authentication
- **RBAC** (Role-Based Access Control)
- **Multi-language** support (Localization)
- CMS modules: News, Pages, Files, References, Settings
- **Shop** module (Products, Orders, Reviews)
- MySQL or PostgreSQL supported
- SQL file-based **database migration** system
- **Twig** template engine
- **OpenAI** integration (moedit editor)
- Image optimization (GD)
- PSR-3 logging system
- **Brevo** API email delivery
- **Discord** webhook notifications
- SEO: Search, Sitemap, XML Sitemap, RSS feed
- Spam protection (honeypot, HMAC token, rate limiting, Cloudflare Turnstile)
- Contact form with message management
- News article comments with 1-level reply
- Product reviews with star rating (1-5)

### codesaur Ecosystem

Raptor works together with these codesaur packages:

| Package | Purpose |
|---------|---------|
| `codesaur/http-application` | PSR-15 Application, Router, Middleware base |
| `codesaur/dataobject` | PDO-based ORM (Model, LocalizedModel) |
| `codesaur/template` | Twig template engine wrapper |
| `codesaur/http-client` | HTTP client (OpenAI API calls) |
| `codesaur/container` | PSR-11 Dependency Injection Container |

---

## 2. Installation

### Requirements

- PHP **8.2.1+**
- Composer
- MySQL or PostgreSQL
- PHP extensions: `ext-gd`, `ext-intl`

### Install via Composer

```bash
composer create-project codesaur/raptor my-project
```

The Composer `post-root-package-install` script will:
1. Auto-copy `.env.example` to `.env` (if not already present)
2. Auto-generate the `RAPTOR_JWT_SECRET` key

> If `.env` was not created, copy it manually with `cp docs/conf.example/.env.example .env` and set `RAPTOR_JWT_SECRET` yourself.

### Manual Installation

```bash
git clone https://github.com/codesaur-php/Raptor.git my-project
cd my-project
composer install
cp docs/conf.example/.env.example .env
```

---

## 3. Configuration

All `.env` configuration options explained:

### Environment & App

```env
# Application name
CODESAUR_APP_NAME=raptor

# Environment mode: development or production
CODESAUR_APP_ENV=development

# Timezone (optional)
#CODESAUR_APP_TIME_ZONE=Asia/Ulaanbaatar
```

- In `development` mode, errors are displayed on screen and written to `logs/code.log`
- In `production` mode, errors are only written to `logs/code.log`

### Database

```env
RAPTOR_DB_HOST=localhost
RAPTOR_DB_NAME=raptor
RAPTOR_DB_USERNAME=root
RAPTOR_DB_PASSWORD=
RAPTOR_DB_CHARSET=utf8mb4
RAPTOR_DB_COLLATION=utf8mb4_unicode_ci
RAPTOR_DB_PERSISTENT=false
```

- On localhost (127.0.0.1), the database is auto-created if it doesn't exist
- Set `RAPTOR_DB_PERSISTENT=true` for persistent PDO connections

### JWT (JSON Web Token)

```env
RAPTOR_JWT_ALGORITHM=HS256
RAPTOR_JWT_LIFETIME=2592000
RAPTOR_JWT_SECRET=auto-generated
#RAPTOR_JWT_LEEWAY=10
```

- `RAPTOR_JWT_SECRET` - Auto-generated 128-character (64-byte hex) key by Composer script
- `RAPTOR_JWT_LIFETIME` - Token validity in seconds (2592000 = 30 days)
- `RAPTOR_JWT_LEEWAY` - Clock skew tolerance in seconds

### Email

```env
RAPTOR_MAIL_FROM=noreply@codesaur.domain
#RAPTOR_MAIL_FROM_NAME="Raptor Notification"
#RAPTOR_MAIL_BREVO_APIKEY=""
#RAPTOR_MAIL_REPLY_TO=
```

- Sends email via Brevo (SendInBlue) API

### OpenAI

```env
#RAPTOR_OPENAI_API_KEY=sk-your-api-key-here
```

- Used by the moedit editor's AI button

### Image Optimization

```env
RAPTOR_CONTENT_IMG_MAX_WIDTH=1920
RAPTOR_CONTENT_IMG_QUALITY=90
```

- CMS image uploads are optimized using the GD extension

### Cloudflare Turnstile

```env
#RAPTOR_TURNSTILE_SITE_KEY=
#RAPTOR_TURNSTILE_SECRET_KEY=
```

- Optional: If not set, Turnstile widget is not shown and server-side check is skipped
- Used by `SpamProtectionTrait` for CAPTCHA verification on public forms

### Discord Notifications

```env
#RAPTOR_DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/...
```

- Optional: If empty or not set, no notifications are sent
- Used by `DiscordNotifier` service for system event notifications

### Server Configuration

Example configuration files for Apache and Nginx are available in [`docs/conf.example/`](../conf.example/):

| File | Description |
|------|-------------|
| `.env.example` | Environment variables reference |
| `.htaccess.example` | Apache URL rewrite and HTTPS redirect |
| `.nginx.conf.example` | Nginx server block (HTTP, HTTPS, PHP-FPM) |

### CI/CD

The framework includes 2 GitHub Actions workflows:

#### CI (`.github/workflows/ci.yml`)

Default workflow included in the repository. Runs code quality checks on every push and pull request:

- `composer validate --strict` - validate composer.json
- PHP syntax check - all `.php` files
- Merge conflict markers - detect `<<<<<<<`, `=======`, `>>>>>>>`
- Debug statements - `var_dump`, `dd`, `print_r` warnings
- `composer dump-autoload --strict-psr` - autoload verification

#### Deploy (`.github/workflows/deploy.yml`)

Unified deploy workflow with 2 jobs: **cPanel FTP** and **Windows Server self-hosted runner**. Each job runs only when its required secrets/variables are configured. Both can run in parallel if both are configured.

**Execution flow:**

```
Push to main -> CI workflow runs -> Success -> Deploy workflow starts
                                 -> Failure -> Deploy is skipped
```

The deploy workflow uses a `workflow_run` trigger to wait for the CI workflow result. Deploy starts only when CI succeeds (`conclusion == 'success'`). If CI fails, deploy is `skipped` - broken code never reaches the server. If no deploy secrets/variables are configured (e.g. developer clone), both jobs are silently skipped.

**A) cPanel FTP Deploy**

Add the following secrets in **Settings -> Secrets and variables -> Actions -> Secrets**:

| Secret | Description | Example |
|--------|-------------|---------|
| `FTP_HOST` | cPanel FTP server address | `ftp.example.com` |
| `FTP_USERNAME` | cPanel FTP username | `user@example.com` |
| `FTP_PASSWORD` | cPanel FTP password | |
| `FTP_SERVER_DIR` | Target directory on server | `/public_html/` |

**B) Windows Server Self-hosted Runner Deploy**

1. Install a self-hosted runner on your Windows Server:
   - **Settings -> Actions -> Runners -> New self-hosted runner -> Windows**
   - Register the runner as a Windows service for auto-start

2. Add the following variable in **Settings -> Secrets and variables -> Actions -> Variables**:

| Variable | Description | Example |
|----------|-------------|---------|
| `DEPLOY_PATH` | XAMPP htdocs project directory | `C:\xampp\htdocs\myproject` |

3. Ensure PHP and Composer are in the system PATH on the server.

**Note:** The deploy workflow requires CI (`ci.yml`) to exist. If CI workflow is removed, deploy will not trigger.

#### Excluded from deployment

- **`.env`** - Create and configure manually on the server
- **`logs/`** - Created automatically by the application
- **`private/`** - Sensitive files (uploads)
- **`docs/`** - Documentation only
- **`vendor/`** - Built during the workflow with `composer install/update --no-dev`

---

## 4. Architecture

### Two-Layer Structure

```
public_html/index.php (Entry point)
|
|-- /dashboard/* -> Dashboard\Application (Admin Panel)
|    |-- Middleware: ErrorHandler -> MySQL -> Session -> JWT -> Container -> Localization -> Settings
|    |-- Routers: Login, Users, Organization, RBAC, Localization, Contents, Messages, Comments, Logs, Template, Shop, Development, Migration
|    \-- Controllers -> Twig Templates -> HTML Response
|
\-- /* -> Web\Application (Public Website)
     |-- Middleware: ExceptionHandler -> MySQL -> Container -> Session -> Localization -> Settings
     |-- Router: WebRouter (/, /page, /news, /contact, /products, /order, /search, /sitemap, /rss, /session/language, /session/contact-send, /session/order, /session/news/{id}/comment, /session/product/{id}/review, ...)
     \-- Controllers -> Twig Templates -> HTML Response
```

### Request Flow

```
Browser -> index.php -> .env -> ServerRequest
  -> Application selection (by URL path)
    -> Middleware chain (in order)
      -> Router match
        -> Controller::action()
          -> Model (DB)
          -> TwigTemplate -> render()
            -> HTML Response -> Browser
```

### Directory Structure

```
raptor/
|-- application/
|   |-- raptor/                    # Core framework (Dashboard + shared)
|   |   |-- Application.php        # Dashboard Application base
|   |   |-- Controller.php         # Base Controller for all controllers
|   |   |-- MySQLConnectMiddleware.php
|   |   |-- PostgresConnectMiddleware.php
|   |   |-- ContainerMiddleware.php
|   |   |-- SessionMiddleware.php  # Shared session management
|   |   |-- authentication/        # Login, JWT
|   |   |-- content/               # CMS modules
|   |   |   |-- file/              # File management
|   |   |   |-- news/              # News
|   |   |   |-- page/              # Pages
|   |   |   |-- messages/           # Contact form messages
|   |   |   |-- reference/         # References
|   |   |   \-- settings/          # System settings
|   |   |-- localization/          # Languages & translations
|   |   |-- organization/          # Organization management
|   |   |-- rbac/                  # Access control
|   |   |-- user/                  # User management
|   |   |-- template/              # Dashboard UI template
|   |   |-- log/                   # PSR-3 logging
|   |   |-- mail/                  # Email
|   |   |-- notification/          # Discord webhook notifications
|   |   |-- migration/             # Database migration system
|   |   |-- development/           # Dev request tracking
|   |   \-- exception/             # Error handling
|   |-- dashboard/                 # Dashboard Application
|   |   |-- Application.php
|   |   |-- home/                  # Dashboard Home Router
|   |   \-- shop/                  # Shop module (Products, Orders, Reviews)
|   \-- web/                       # Web Application
|       |-- Application.php
|       |-- WebRouter.php         # Web routes
|       |-- HomeController.php     # Home, language
|       |-- content/               # Pages, News
|       |-- shop/                  # Products, Orders, Reviews
|       |-- service/               # Search, Sitemap, RSS, Contact
|       |-- *.html                 # Twig templates
|       \-- template/              # Web layout
|           |-- TemplateController.php
|           |-- ExceptionHandler.php
|           \-- index.html
|-- public_html/
|   |-- index.php                  # Entry point
|   |-- .htaccess                  # Apache URL rewrite
|   |-- robots.txt                 # Search engine bot rules
|   \-- assets/                    # CSS, JS (dashboard, moedit, motable)
|-- docs/
|   |-- conf.example/              # Server configuration examples
|   |   |-- .env.example           # Environment variables
|   |   |-- .htaccess.example      # Apache rewrite rules
|   |   \-- .nginx.conf.example    # Nginx server config
|   |-- en/                        # English documentation
|   \-- mn/                        # Mongolian documentation
|-- tests/                         # PHPUnit tests (unit, integration)
|-- database/
|   \-- migrations/                # SQL migration files
|-- .github/
|   \-- workflows/
|       |-- ci.yml                 # CI code quality checks (push, PR)
|       \-- deploy.yml             # Auto deploy (cPanel FTP / Windows Server)
|-- logs/                          # Error log files
|-- private/                       # Protected files
|-- composer.json
|-- phpunit.xml                    # PHPUnit configuration
\-- LICENSE
```

---

## 5. Middleware Pipeline

Middleware are PSR-15 standard layers that process request/response. Registration order matters!

### Dashboard Middleware

| # | Middleware | Purpose |
|---|-----------|---------|
| 1 | `ErrorHandler` | Returns errors as JSON/HTML |
| 2 | `MySQLConnectMiddleware` | Creates PDO and injects into request |
| 3 | `MigrationMiddleware` | Auto-runs pending SQL migrations |
| 4 | `SessionMiddleware` | Starts and manages PHP session |
| 5 | `JWTAuthMiddleware` | Validates JWT and creates `User` object |
| 6 | `ContainerMiddleware` | Injects DI Container |
| 7 | `LocalizationMiddleware` | Determines language and translations |
| 8 | `SettingsMiddleware` | Injects system settings |

### Web Middleware

| # | Middleware | Purpose |
|---|-----------|---------|
| 1 | `ExceptionHandler` | Renders error pages using templates |
| 2 | `MySQLConnectMiddleware` | PDO connection |
| 3 | `ContainerMiddleware` | DI Container |
| 4 | `SessionMiddleware` | Session (stores language preference) |
| 5 | `LocalizationMiddleware` | Multi-language |
| 6 | `SettingsMiddleware` | Settings (logo, title, footer) |

### Database Middleware Options

Use only **one** database middleware:

```php
// MySQL (default)
$this->use(new \Raptor\MySQLConnectMiddleware());

// PostgreSQL
$this->use(new \Raptor\PostgresConnectMiddleware());
```

---

## 6. Modules

### 6.1 Authentication

**Classes:** `LoginRouter`, `LoginController`, `JWTAuthMiddleware`, `SessionMiddleware`, `User`

- JWT + Session combined authentication
- Login / Logout / Forgot password / Signup
- Organization selection (multi-org users)
- JWT stored in `$_SESSION['RAPTOR_JWT']`
- `User` object contains profile, organization, RBAC permissions

### 6.2 User Management

**Classes:** `UsersRouter`, `UsersController`, `UsersModel`

- User CRUD (Create, Read, Update, Deactivate)
- Passwords stored using bcrypt hash
- Profile fields: username, email, phone, first_name, last_name
- Avatar image upload

### 6.3 Organization

**Classes:** `OrganizationRouter`, `OrganizationController`, `OrganizationModel`, `OrganizationUserModel`

- Organization CRUD
- User-organization relationship management
- One user can belong to multiple organizations

### 6.4 RBAC (Access Control)

**Classes:** `RBACRouter`, `RBACController`, `RBAC`, `Roles`, `Permissions`, `RolePermissions`, `UserRole`

- Create and manage roles
- Create and manage permissions
- Role-Permission assignments
- User-Role assignments
- Check permissions in controllers:

```php
// Check if user has "admin" role in system organization
$this->isUser('system_admin');

// Check if user has "news_edit" permission
$this->isUserCan('news_edit');
```

### 6.5 Content - Files

**Classes:** `FilesController`, `FilesModel`, `PrivateFilesController`

- File upload (native JS, FormData)
- Image optimization (GD)
- Files organized by module/table
- MIME type detection
- Private files (authenticated users only)

### 6.6 Content - News

**Classes:** `NewsController`, `NewsModel`

- News CRUD
- Cover image upload
- File attachments
- Publish date management
- View count (read_count)
- Content editing with moedit editor
- Sample data reset: clear seed data and restart with ID=1 (`reset()` method)

### 6.7 Content - Pages

**Classes:** `PagesController`, `PagesModel`

- Page CRUD with simplified single-form interface (no type wizard)
- Parent-child structure (multi-level navigation menu)
- `position` field for ordering
- `type` field: `content` (default), `nav` (parent/navigation page created via "Parent page" switch)
- Parent pages (pages with children) automatically hide content fields (description, content, link, featured) in edit form
- `is_featured` field: featured pages in footer (auto-reset to 0 when page becomes a parent)
- `link` field: URL or local path with frontend + backend validation (`isValidLink()`)
- `read()` guards: published check, parent check, link redirect
- SEO slug generation (`generateSlug`)
- File attachments
- Sample data reset: clear seed data and restart with ID=1 (`reset()` method)

### 6.8 Content - References

**Classes:** `ReferencesController`, `ReferencesModel`

- Reference tables (key-value style)
- Multi-language (LocalizedModel)
- Dynamic table names

### 6.9 Content - Settings

**Classes:** `SettingsController`, `SettingsModel`, `SettingsMiddleware`

- System-wide settings (multi-language)
- Site title, logo, description
- Favicon, Apple Touch Icon
- Contact information (phone, email, address)
- Footer information (copyright, social links)
- `SettingsMiddleware` injects settings into request attributes

### 6.10 Localization

**Classes:** `LocalizationRouter`, `LocalizationController`, `LanguageModel`, `TextModel`, `LocalizationMiddleware`

- Add / edit / remove languages
- Translation text management (key -> value)
- Session-based language selection
- Use in Twig templates: `{{ 'key'|text }}`

### 6.11 Logging

**Classes:** `LogsRouter`, `LogsController`, `Logger`

- PSR-3 standard logging system
- Logs stored in database
- Log levels: emergency, alert, critical, error, warning, notice, info, debug
- Auto-captures server request metadata
- Auto-captures authenticated user info
- Error log viewer tab (system_coder users) - view PHP error.log directly in the Access Logs page

### 6.12 Mail

**Classes:** `Mailer`

- Brevo (SendInBlue) API email sending
- Template-based email sending

### 6.13 Template (Dashboard UI)

**Classes:** `TemplateRouter`, `TemplateController`

- Dashboard layout (sidebar, header, content area)
- SweetAlert2, motable, moedit JS components
- Responsive Bootstrap 5 design

### 6.14 Shop (E-Commerce)

**Classes:** `ProductsController`, `ProductsRouter`, `ProductsModel`, `OrdersController`, `OrdersRouter`, `ProductOrdersModel`, `ReviewsController`, `ReviewsRouter`, `ReviewsModel`

- Product CRUD with slug generation, excerpt extraction
- Product fields: price, sale_price, SKU, barcode, sizes, colors, stock, category, featured, review toggle
- Order management (`products_orders` table) with customer info and status tracking
- Product reviews with star rating (1-5) and written comments
- Reviews displayed in product detail view (both web and dashboard)
- Media gallery on web product page (thumbnail strip + large preview for images/video/audio)
- Sample product data seeded on first run
- Discord notifications for new orders, status changes, and reviews

### 6.15 Reviews (Product Reviews)

**Classes:** `ReviewsController`, `ReviewsRouter`, `ReviewsModel` (dashboard), `ShopController::reviewSubmit()` (web)

- Public review form on product detail pages (when `review=1`)
- Star rating (1-5) with written review text
- Guest reviews with name and optional email
- Spam protection via `SpamProtectionTrait` (honeypot, HMAC, rate limiting, Turnstile)
- Average rating and review count displayed on product listing cards
- Dashboard: reviews shown in products-view with delete support
- Dashboard: reviews index accessible from products-index header link
- Badge: new reviews appear as `info` (cyan) badge on products sidebar item
- Web-side review submission via `/session/product/{id}/review`

### 6.16 Notification

**Classes:** `DiscordNotifier`

- Discord webhook integration for system events
- Notification types: user signup, user approval, new order, order status change, content actions
- Color-coded embed messages
- Configured via `RAPTOR_DISCORD_WEBHOOK_URL` env variable
- Gracefully skips if webhook URL is not set

### 6.17 Development Tools

**Classes:** `DevelopmentRouter`, `DevRequestController`, `DevRequestModel`, `DevResponseModel`

- Development request tracking system (submit requests, respond, view history)
- Protected by `development:development` RBAC permission

### 6.18 Site Service (Web)

**Classes:** `SeoController`

- Full-text search across pages, news, and products
- Human-readable sitemap with hierarchical page tree
- XML sitemap (`/sitemap.xml`) for search engines
- RSS 2.0 feed (`/rss`) with latest news and products
- `robots.txt` - Search engine bot instructions included in `public_html/`

#### robots.txt

A pre-configured `robots.txt` is included at `public_html/robots.txt`. It controls which bots can access your site:

- **Allowed:** Googlebot, Bingbot, YandexBot, Baiduspider
- **Blocked:** SEO scrapers (MJ12bot, SemrushBot, AhrefsBot, DotBot, Bytespider, PetalBot)
- **AI bots:** Allowed by default (GPTBot, ClaudeBot, etc.). Uncomment lines to block
- **Dashboard:** `/dashboard/` is disallowed for all bots
- **Sitemap:** Update the `Sitemap:` line with your actual domain

```
Sitemap: https://example.com/sitemap.xml
```

### 6.19 Spam Protection

**Classes:** `SpamProtectionTrait`

- Honeypot hidden field detection
- HMAC token validation with timestamp
- Rate limiting per action (login 2s, signup 5s, forgot 10s)
- Form expiration (1 hour max)
- Minimum fill speed check (1 second)
- Cloudflare Turnstile CAPTCHA support (enabled when `RAPTOR_TURNSTILE_SECRET_KEY` is set in `.env`)
- Link spam filter (blocks text with excessive URLs)
- Applied to login, signup, forgot password, contact, comment, review, and order forms

### 6.20 Database Migration

**Classes:** `MigrationRunner`, `MigrationMiddleware`, `MigrationController`, `MigrationRouter`

- SQL file-based forward-only migration system
- Migrations stored in `database/migrations/` directory
- Pending = files in `migrations/`, Ran = moved to `migrations/ran/`
- `MigrationMiddleware` auto-runs pending migrations on each request
- Advisory lock (`GET_LOCK`) prevents concurrent migration execution
- Dashboard UI for viewing migration status and SQL file contents
- Protected: only `system_coder` users can access the dashboard
- `.htaccess` protection blocks direct browser access to SQL files

### 6.21 Messages (Contact Form)

**Classes:** `MessagesController`, `MessagesModel` (dashboard), `ContactController` (web)

- Public contact form (`/contact`) with spam protection
- Contact form submissions stored in database
- Dashboard interface for viewing and managing messages
- View message details in modal dialog
- Soft delete (deactivate) messages
- Discord notification on new contact message
- Web-side `ContactController` handles form display and submission via `/session/contact-send`

### 6.22 Comments (News)

**Classes:** `CommentsController`, `CommentsModel` (dashboard), `NewsController::commentSubmit()` (web)

- Public comment form on news article pages
- 1-level reply support (parent_id for replies to top-level comments)
- Guest comments with name and email fields
- Authenticated users auto-fill name/email from profile
- Spam protection via `SpamProtectionTrait` (honeypot, HMAC, rate limiting, Turnstile)
- Dashboard: comments shown in news-view with reply and delete support
- Dashboard: comments index accessible from news-index header link
- Badge: new comments appear as `info` (cyan) badge on news sidebar item
- Soft delete (deactivate) comments
- Web-side comment submission via `/session/news/{id}/comment`

---

## 7. Twig Template System

Raptor uses the `TwigTemplate` class from the `codesaur/template` package.

### Base Variables

When calling `twigTemplate()` from a controller, these variables are automatically added:

| Variable | Description |
|----------|-------------|
| `user` | Authenticated `User` object (may be null) |
| `index` | Script path (subdirectory support) |
| `localization` | Language and translation data |
| `request` | Current URL path |

### Twig Filters

| Filter | Usage | Description |
|--------|-------|-------------|
| `text` | `{{ 'key'\|text }}` | Get translation text |
| `link` | `{{ 'route'\|link({'id': 5}) }}` | Generate URL from route name |
| `basename` | `{{ path\|basename }}` | Extract filename (Web templates) |

### Example

```twig
{# Translation #}
<h1>{{ 'welcome'|text }}</h1>

{# Route link #}
<a href="{{ 'page'|link({'id': page.id}) }}">{{ page.title }}</a>

{# User check #}
{% if user is not null %}
    <p>Hello, {{ user.profile.first_name }}!</p>
{% endif %}

{# Language switcher #}
{% for code, language in localization.language %}
    <a href="{{ 'language'|link({'code': code}) }}">{{ language.title }}</a>
{% endfor %}
```

---

## 8. Routing

Raptor uses the Router class from the `codesaur/http-application` package.

### Defining Routes

```php
class MyRouter extends \codesaur\Router\Router
{
    public function __construct()
    {
        // GET route
        $this->GET('/path', [Controller::class, 'method'])->name('route-name');

        // POST route
        $this->POST('/path', [Controller::class, 'method'])->name('route-name');

        // PUT route
        $this->PUT('/path/{uint:id}', [Controller::class, 'method'])->name('route-name');

        // DELETE route
        $this->DELETE('/path', [Controller::class, 'method'])->name('route-name');

        // GET + POST (form display + submit)
        $this->GET_POST('/path', [Controller::class, 'method'])->name('route-name');

        // GET + PUT (edit form)
        $this->GET_PUT('/path/{uint:id}', [Controller::class, 'method'])->name('route-name');
    }
}
```

### Dynamic Parameters

| Pattern | Description | Example |
|---------|-------------|---------|
| `{name}` | String parameter | `/page/{slug}` |
| `{uint:id}` | Unsigned integer | `/page/{uint:id}` |
| `{code}` | String (language code) | `/language/{code}` |

### Registering Routers

In your Application class:

```php
$this->use(new MyRouter());
```

### Route Name Optimization

Only use `->name('route-name')` when the route name is actually referenced via:
- `{{ 'route-name'|link }}` in Twig templates
- `$this->redirectTo('route-name')` in PHP controllers

Routes that are never referenced by name do not need `->name()`, reducing unnecessary overhead.

---

## 9. Controller

### Base Controller (Raptor\Controller)

All controllers extend `Raptor\Controller`. Available methods:

| Method | Description |
|--------|-------------|
| `$this->pdo` | PDO connection |
| `getUser()` | Authenticated user (`User\|null`) |
| `getUserId()` | User ID |
| `isUserAuthorized()` | Is authenticated |
| `isUser($role)` | Check RBAC role |
| `isUserCan($permission)` | Check RBAC permission |
| `getLanguageCode()` | Active language code |
| `getLanguages()` | All languages list |
| `text($key)` | Translation text |
| `twigTemplate($file, $vars)` | Twig template object |
| `respondJSON($data, $code)` | JSON response |
| `redirectTo($route, $params)` | Redirect |
| `log($table, $level, $msg)` | Write log entry |
| `generateRouteLink($name, $params)` | Generate URL |
| `getContainer()` | DI Container |
| `getService($id)` | Get service |

### Example: Writing a New Controller

```php
namespace Dashboard\Products;

class ProductsController extends \Raptor\Controller
{
    public function index()
    {
        // Check permission
        if (!$this->isUserCan('product_read')) {
            throw new \Error('Access denied', 403);
        }

        // Use model
        $model = new ProductsModel($this->pdo);
        $products = $model->getRows(['WHERE' => 'is_active=1']);

        // Render template
        $twig = $this->twigTemplate(__DIR__ . '/index.html', [
            'products' => $products
        ]);
        $twig->render();
    }

    public function store()
    {
        $body = $this->getRequest()->getParsedBody();
        $model = new ProductsModel($this->pdo);
        $id = $model->insert($body);

        // Write log
        $this->log('products', \Psr\Log\LogLevel::INFO, 'Product added', [
            'product_id' => $id
        ]);

        // JSON response
        $this->respondJSON(['status' => 'success', 'id' => $id]);
    }
}
```

---

## 10. Model

Raptor uses the Model classes from the `codesaur/dataobject` package.

### Model (single language)

```php
use codesaur\DataObject\Column;
use codesaur\DataObject\Model;

class ProductsModel extends Model
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
            new Column('name', 'varchar', 255),
            new Column('price', 'decimal', '10,2'),
           (new Column('is_active', 'tinyint'))->default(1),
            new Column('created_at', 'datetime'),
        ]);
        $this->setTable('products');
    }
}
```

### LocalizedModel (multi-language)

```php
use codesaur\DataObject\Column;
use codesaur\DataObject\LocalizedModel;

class CategoriesModel extends LocalizedModel
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        // Primary table columns
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('is_active', 'tinyint'))->default(1),
        ]);

        // Per-language content columns
        $this->setContentColumns([
            new Column('title', 'varchar', 255),
            new Column('description', 'text'),
        ]);

        $this->setTable('categories');
    }
}
```

### Key Methods

| Method | Description |
|--------|-------------|
| `insert($record)` | Insert a record |
| `updateById($id, $record)` | Update by ID |
| `deleteById($id)` | Delete by ID |
| `deactivateById($id, $record)` | Deactivate a record by ID (soft delete) |
| `getRowWhere($with_values)` | Get single row by WHERE key=value conditions |
| `getRow($condition)` | Get single row with SELECT condition |
| `getRows($condition)` | Get multiple rows with SELECT condition |
| `getName()` | Get table name |

### LocalizedModel Data Structure

`LocalizedModel::getRows()` returns:

```php
[
    1 => [
        'id' => 1,
        'is_active' => 1,
        'localized' => [
            'mn' => ['title' => 'Mongolian title', 'description' => '...'],
            'en' => ['title' => 'English title', 'description' => '...'],
        ]
    ],
    // ...
]
```

---

## 11. Testing

Raptor includes a PHPUnit 11 test suite with unit and integration tests.

### Requirements

```bash
composer install   # installs phpunit dev dependency
```

### Running Tests

```bash
# All tests
composer test

# Unit tests only
composer test:unit

# Integration tests only
composer test:integration
```

### Configuration

The `.env.testing` file contains test environment settings. Integration tests use a separate test database (e.g., `raptor12_test`).

```env
RAPTOR_DB_NAME=raptor12_test
```

### Test Structure

```
tests/
|-- bootstrap.php              # Test environment setup
|-- Support/
|   |-- RaptorTestCase.php     # Base class for unit tests
|   \-- IntegrationTestCase.php # Base class for integration tests
|-- Unit/
|   |-- Authentication/
|   |   \-- UserTest.php       # User::is(), User::can() tests
|   |-- Controller/
|   |   \-- ControllerTextTest.php  # Controller::text() tests
|   \-- Migration/
|       \-- MigrationRunnerTest.php  # Migration parser/status tests
\-- Integration/
    |-- Model/
    |   |-- UsersModelTest.php          # User CRUD tests
    |   |-- OrganizationModelTest.php   # Organization tests
    |   \-- SignupModelTest.php         # Signup tests
    |-- RBAC/
    |   \-- RolesPermissionsTest.php    # RBAC seed data verification
    |-- Authentication/
    |   \-- JWTAuthTest.php             # JWT encode/decode tests
    \-- Migration/
        \-- MigrationRunnerIntegrationTest.php  # Migration engine tests
```

### Key Features

- **Transaction isolation** - Each integration test runs inside a transaction that is rolled back on teardown. Test data never affects the real database
- **RaptorTestCase** - Provides mock request and user factory helpers (`createAdmin()`, `createCoder()`, `createGuest()`)
- **IntegrationTestCase** - Static PDO connection shared across test class, auto-creates test database if not exists

### Writing a New Test

```php
namespace Tests\Unit;

use Tests\Support\RaptorTestCase;

class MyTest extends RaptorTestCase
{
    public function test_example(): void
    {
        $user = $this->createAdmin();
        $this->assertTrue($user->can('system_user_index'));
    }
}
```

---

## 12. Usage Examples

### Adding a New Router

1. Create the Router class:

```php
// application/dashboard/products/ProductsRouter.php
namespace Dashboard\Products;

class ProductsRouter extends \codesaur\Router\Router
{
    public function __construct()
    {
        $this->GET('/dashboard/products', [ProductsController::class, 'index'])->name('products');
        $this->GET_POST('/dashboard/products/insert', [ProductsController::class, 'insert'])->name('product-insert');
    }
}
```

2. Register the namespace in `composer.json`:

```json
{
    "autoload": {
        "psr-4": {
            "Dashboard\\Products\\": "application/dashboard/products/"
        }
    }
}
```

Then regenerate the autoloader:

```bash
composer dump-autoload
```

3. Register the Router in your Application:

```php
// application/dashboard/Application.php
class Application extends \Raptor\Application
{
    public function __construct()
    {
        parent::__construct();
        $this->use(new Home\HomeRouter());
        $this->use(new Products\ProductsRouter());  // New router
    }
}
```

### Adding a Public Web Page

```php
// application/web/WebRouter.php
$this->GET('/products', [HomeController::class, 'products'])->name('products');
```

```php
// application/web/HomeController.php
public function products()
{
    $model = new ProductsModel($this->pdo);
    $products = $model->getRows(['WHERE' => 'is_active=1']);
    $this->twigWebLayout(__DIR__ . '/products.html', ['products' => $products])->render();
}
```

### Switching Database

Change the database middleware in `Application.php`:

```php
// MySQL (default)
$this->use(new \Raptor\MySQLConnectMiddleware());

// Switch to PostgreSQL
$this->use(new \Raptor\PostgresConnectMiddleware());
```

---

## Next Steps

- [API Reference](api.md) - Detailed API reference for all classes and methods
- [Discussions](https://github.com/orgs/codesaur-php/discussions) - Ask questions, share ideas, get help
- [codesaur ecosystem](https://github.com/codesaur-php) - Other packages
