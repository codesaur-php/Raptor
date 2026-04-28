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
6. [Modules](#6-modules) (6.1-6.13 Core | 6.14-6.29 Shop, Reviews, Event/Notification, Development, SEO, Spam, CSRF, Migration, Messages, Comments, Badges, Home, Manual, AI, Seed, Trash)
7. [Template System](#7-template-system)
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
- **codesaur/template** custom template engine (Twig-style syntax)
- **OpenAI** integration (moedit editor)
- Image optimization (GD)
- PSR-3 logging system
- Email delivery (**Brevo** API, SMTP, PHP mail)
- **PSR-14** Event Dispatcher system (Discord, email, logging via listeners)
- **Discord** webhook notifications (via event listener)
- SEO: Search, Sitemap, XML Sitemap, RSS feed
- Spam protection (honeypot, HMAC token, rate limiting, Cloudflare Turnstile)
- CSRF protection (CsrfMiddleware, csrfFetch)
- File-based DB cache (PSR-16 SimpleCache) with auto-invalidation
- Contact form with message management
- News article comments with 1-level reply
- Product reviews with star rating (1-5)
- Admin email notifications for contact messages, orders, comments, reviews (configurable per channel)
- **Trash** module for recovering deleted content records

### codesaur Ecosystem

Raptor works together with these codesaur packages:

| Package | Purpose |
|---------|---------|
| `codesaur/http-application` | PSR-15 Application, Router, Middleware base |
| `codesaur/dataobject` | PDO-based ORM (Model, LocalizedModel) |
| `codesaur/template` | Template engine wrapper |
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
#RAPTOR_MAIL_REPLY_TO=

# Transport: brevo (default), smtp, mail
#RAPTOR_MAIL_TRANSPORT=brevo
#RAPTOR_MAIL_BREVO_APIKEY=

# SMTP settings (when transport=smtp)
#RAPTOR_SMTP_HOST=smtp.gmail.com
#RAPTOR_SMTP_PORT=465
#RAPTOR_SMTP_USERNAME=
#RAPTOR_SMTP_PASSWORD=
#RAPTOR_SMTP_SECURE=ssl
```

- `send()` selects transport based on `RAPTOR_MAIL_TRANSPORT` env var (brevo/smtp/mail)

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

Unified deploy workflow with 3 jobs: **FTP**, **SSH**, and **Windows Server self-hosted runner**. Each job runs only when its required secrets/variables are configured. All configured jobs run in parallel.

**Execution flow:**

```
Push to main -> CI workflow runs -> Success -> Deploy workflow starts
                                 -> Failure -> Deploy is skipped
```

The deploy workflow uses a `workflow_run` trigger to wait for the CI workflow result. Deploy starts only when CI succeeds (`conclusion == 'success'`). If CI fails, deploy is `skipped` - broken code never reaches the server. If no deploy secrets/variables are configured (e.g. developer clone), all jobs are silently skipped.

**A) FTP Deploy**

For any server with FTP access (shared hosting, VPS, dedicated). Add the following secrets in **Settings -> Secrets and variables -> Actions -> Secrets**:

| Secret | Description | Example |
|--------|-------------|---------|
| `FTP_HOST` | FTP server address | `ftp.example.com` |
| `FTP_USERNAME` | FTP username | `user@example.com` |
| `FTP_PASSWORD` | FTP password | |
| `FTP_SERVER_DIR` | Target directory on server | `/public_html/` |

**B) SSH Deploy**

For Linux servers with SSH access (VPS, cloud VM, dedicated). Add the following secrets in **Settings -> Secrets and variables -> Actions -> Secrets**:

| Secret | Description | Example |
|--------|-------------|---------|
| `SSH_HOST` | Server address | `example.com` or `1.2.3.4` |
| `SSH_USERNAME` | SSH username | `deploy` |
| `SSH_KEY` | SSH private key (full content of id_rsa) | |
| `SSH_DEPLOY_DIR` | Target directory on server | `/var/www/myproject` |
| `SSH_PORT` | (optional) SSH port, default: 22 | `22` |

**C) Windows Self-hosted Runner Deploy**

1. Install a self-hosted runner on your Windows Server:
   - **Settings -> Actions -> Runners -> New self-hosted runner -> Windows**
   - Register the runner as a Windows service for auto-start

2. Add the following variable in **Settings -> Secrets and variables -> Actions -> Variables**:

| Variable | Description | Example |
|----------|-------------|---------|
| `DEPLOY_PATH` | Server project directory | `C:\xampp\htdocs\myproject` |

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
|    |-- Middleware: ErrorHandler -> MySQL -> Session -> JWT -> CSRF -> Container -> Localization -> Settings
|    |-- Routers: Login, Users, Organization, RBAC, Localization, Contents, Messages, Comments, Logs, Template, Shop, Development, Migration
|    \-- Controllers -> Templates -> HTML Response
|
\-- /* -> Web\Application (Public Website)
     |-- Middleware: ExceptionHandler -> MySQL -> Container -> Session -> Localization -> Settings
     |-- Router: WebRouter (/, /page, /news, /contact, /products, /order, /search, /sitemap, /rss, /session/language, /session/contact-send, /session/order, /session/news/{id}/comment, /session/product/{id}/review, ...)
     \-- Controllers -> Templates -> HTML Response
```

### Request Flow

```
Browser -> index.php -> .env -> ServerRequest
  -> Application selection (by URL path)
    -> Middleware chain (in order)
      -> Router match
        -> Controller::action()
          -> Model (DB)
          -> FileTemplate (codesaur/template) -> render()
            -> HTML Response -> Browser
```

### Directory Structure

```
raptor/
|-- application/
|   |-- raptor/                    # Core framework (Dashboard + shared)
|   |   |-- Application.php        # Dashboard Application base
|   |   |-- Controller.php         # Base Controller for all controllers
|   |   |-- CacheService.php       # File-based DB cache (PSR-16 SimpleCache)
|   |   |-- CsrfMiddleware.php     # CSRF token validation
|   |   |-- SpamProtectionTrait.php # Honeypot, HMAC, rate limit, Turnstile
|   |   |-- MySQLConnectMiddleware.php    # MySQL PDO connection (auto-creates DB on localhost)
|   |   |-- PostgresConnectMiddleware.php # PostgreSQL PDO connection (UTF8 client encoding)
|   |   |-- ContainerMiddleware.php       # PSR-11 DI container wiring (events, cache, mailer, Discord)
|   |   |-- SessionMiddleware.php         # Shared session lifecycle (write-close optimization)
|   |   |-- authentication/        # Login, JWT
|   |   |-- content/               # CMS modules
|   |   |   |-- AIHelper.php       # OpenAI integration (moedit)
|   |   |   |-- ContentsRouter.php # Central content router
|   |   |   |-- HtmlValidationTrait.php # Server-side HTML validation
|   |   |   |-- file/              # File management + upload base
|   |   |   |-- news/              # News
|   |   |   |-- page/              # Pages
|   |   |   |-- messages/          # Contact form messages
|   |   |   |-- reference/         # References + email templates
|   |   |   \-- settings/          # System settings
|   |   |-- localization/          # Languages & translations
|   |   |-- organization/          # Organization management
|   |   |-- rbac/                  # Access control + seed data
|   |   |-- user/                  # User management
|   |   |-- template/              # Dashboard UI, menu, badges
|   |   |-- log/                   # PSR-3 logging
|   |   |-- mail/                  # Email (Brevo API, SMTP, PHP mail)
|   |   |-- event/                 # PSR-14 Event Dispatcher system
|   |   |-- notification/          # Discord webhook listener
|   |   |-- trash/                 # Trash module (deleted record recovery)
|   |   |-- migration/             # Database migration system
|   |   |-- development/           # Dev request tracking
|   |   \-- exception/             # Error handling
|   |-- dashboard/                 # Dashboard Application
|   |   |-- Application.php
|   |   |-- home/                  # Dashboard Home, search, stats
|   |   |-- manual/                # Help documentation viewer
|   |   \-- shop/                  # Shop module (Products, Orders, Reviews)
|   \-- web/                       # Web Application
|       |-- Application.php
|       |-- WebRouter.php         # Web routes
|       |-- HomeController.php     # Home, language
|       |-- content/               # Pages, News
|       |-- shop/                  # Products, Orders, Reviews
|       |-- service/               # Search, Sitemap, RSS, Contact
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
|       \-- deploy.yml             # Auto deploy (FTP / SSH / Windows Server)
|-- logs/                          # Error log files
|-- private/                       # Protected files (uploads, cache)
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
| 6 | `CsrfMiddleware` | CSRF token validation for POST/PUT/PATCH/DELETE |
| 7 | `ContainerMiddleware` | Injects DI Container |
| 8 | `LocalizationMiddleware` | Determines language and translations |
| 9 | `SettingsMiddleware` | Injects system settings |

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

- User CRUD (Create, Read, Update, Deactivate/soft delete)
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

- News CRUD (hard delete with Trash backup)
- Cover image upload
- File attachments
- Publish date management
- View count (read_count)
- Content editing with moedit editor
- Sample data reset: clear seed data and restart with ID=1 (`reset()` method)

### 6.7 Content - Pages

**Classes:** `PagesController`, `PagesModel`

- Page CRUD (hard delete with Trash backup) with simplified single-form interface (no type wizard)
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
- Use in Templates: `{{ 'key'|text }}`

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

- `send()` selects transport via `RAPTOR_MAIL_TRANSPORT` env var (brevo/smtp/mail)
- HTML messages, CC/BCC, attachments

### 6.13 Template (Dashboard UI)

**Classes:** `TemplateRouter`, `TemplateController`, `DashboardTrait`, `MenuModel`, `FileController`

- Dashboard layout rendering via `DashboardTrait::dashboardTemplate()`
- Sidebar menu with i18n, permissions, parent/child hierarchy (`MenuModel`)
- Menu management CRUD (insert, update, deactivate)
- File upload, validation, image optimization via `FileController` base class
- SweetAlert2, motable, moedit JS components
- Responsive Bootstrap 5 design

### 6.14 Shop (E-Commerce)

**Classes:** `ProductsController`, `OrdersController`, `ReviewsController`, `ShopRouter` (unified router for products + orders + reviews), `ProductsModel`, `ProductOrdersModel`, `ReviewsModel`

- Product CRUD (hard delete with Trash backup) with slug generation, excerpt extraction
- Product fields: price, sale_price, SKU, barcode, sizes, colors, stock, category, featured, review toggle
- Order management (`products_orders` table) with customer info and status tracking
- Product reviews with star rating (1-5) and written comments
- Reviews displayed in product detail view (both web and dashboard)
- Media gallery on web product page (thumbnail strip + large preview for images/video/audio)
- Sample product data seeded on first run
- PSR-14 event-driven notifications for new orders, status changes, and reviews
- Admin email notification for new orders (configurable: toggle + recipient email, `system_coder` only)

### 6.15 Reviews (Product Reviews)

**Classes:** `ReviewsController`, `ReviewsModel` (dashboard, registered via `ShopRouter`), `ShopController::reviewSubmit()` (web)

- Public review form on product detail pages (when `review=1`)
- Star rating (1-5) with written review text
- Guest reviews with name and optional email
- Spam protection via `SpamProtectionTrait` (honeypot, HMAC, rate limiting, Turnstile)
- Average rating and review count displayed on product listing cards
- Dashboard: reviews shown in products-view with delete support
- Dashboard: reviews index accessible from products-index header link
- Badge: new reviews appear as `info` (cyan) badge on products sidebar item
- Admin email notification (configurable: toggle + recipient email, `system_coder` only, disabled by default)
- Web-side review submission via `/session/product/{id}/review`

### 6.16 Event System & Notification

**Classes:** `EventDispatcher`, `ListenerProvider`, `ContentEvent`, `UserEvent`, `OrderEvent`, `DevRequestEvent`, `DiscordListener`

- **PSR-14 Event Dispatcher** system replaces direct Discord calls
- Event classes: `ContentEvent`, `UserEvent`, `OrderEvent`, `DevRequestEvent`
- `ListenerProvider` registers listeners (currently `DiscordListener`)
- `DiscordListener` sends Discord webhook notifications for all event types
- Controllers dispatch events via `$this->dispatch(new ContentEvent(...))` helper
- `DiscordNotifier` stores admin name and dashboard URL (injected via `ContainerMiddleware`)
- Notification types: user signup, user approval, new order, order status change, content actions (insert, update, delete, publish)
- Color-coded Discord embed messages
- Configured via `RAPTOR_DISCORD_WEBHOOK_URL` env variable
- Gracefully skips if webhook URL is not set or listener is unavailable

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

### 6.20 CSRF Protection

**Classes:** `CsrfMiddleware`

- Per-session CSRF token validation for all dashboard POST/PUT/PATCH/DELETE requests
- Token generated at login, stored in `$_SESSION['CSRF_TOKEN']`
- Auto-generated for existing sessions missing a token (requires writable session)
- GET/HEAD/OPTIONS requests pass through without validation
- `/login` routes are exempt (token is created there)
- Client JS sends token via `X-CSRF-TOKEN` header using `csrfFetch()` wrapper
- Token delivered to frontend via `<meta name="csrf-token">` in `dashboard.html`
- For new modules: use `csrfFetch()` instead of `fetch()` for all state-changing requests

### 6.21 Database Migration

**Classes:** `MigrationRunner`, `MigrationMiddleware`, `MigrationController`, `MigrationRouter`

- SQL file-based forward-only migration system
- Migrations stored in `database/migrations/` directory
- Pending = files in `migrations/`, Ran = moved to `migrations/ran/`
- `MigrationMiddleware` auto-runs pending migrations on each request
- Advisory lock (`GET_LOCK`) prevents concurrent migration execution
- Dashboard UI for viewing migration status and SQL file contents
- Protected: only `system_coder` users can access the dashboard
- `.htaccess` protection blocks direct browser access to SQL files

### 6.22 Messages (Contact Form)

**Classes:** `MessagesController`, `MessagesModel` (dashboard), `ContactController` (web)

- Public contact form (`/contact`) with spam protection
- Contact form submissions stored in database
- Dashboard interface for viewing and managing messages
- View message details in modal dialog
- Hard delete with Trash backup (replaced soft delete/deactivate)
- Event-driven notification on new contact message (Discord via PSR-14 listener)
- Admin email notification (configurable: toggle + recipient email, `system_coder` only)
- Web-side `ContactController` handles form display and submission via `/session/contact-send`

### 6.23 Comments (News)

**Classes:** `CommentsController`, `CommentsModel` (dashboard), `NewsController::commentSubmit()` (web)

- Public comment form on news article pages
- 1-level reply support (parent_id for replies to top-level comments)
- Guest comments with name and email fields
- Authenticated users auto-fill name/email from profile
- Spam protection via `SpamProtectionTrait` (honeypot, HMAC, rate limiting, Turnstile)
- Dashboard: comments shown in news-view with reply and delete support
- Dashboard: comments index accessible from news-index header link
- Badge: new comments appear as `info` (cyan) badge on news sidebar item
- Hard delete with Trash backup (replaced soft delete/deactivate)
- Admin email notification (configurable: toggle + recipient email, `system_coder` only, disabled by default)
- Web-side comment submission via `/session/news/{id}/comment`

### 6.24 Badge System (Sidebar Badges)

**Classes:** `BadgeController`, `BadgeRouter`, `AdminBadgeSeenModel`

- Colored badge pills on sidebar menu items showing unseen activity counts per admin
- Reads from existing `*_log` tables - no separate event table
- Badge colors: green (create), blue (update), red (delete)
- Up to 3 badges per module, shown left to right in green-blue-red order
- Filters by admin permissions (PERMISSION_MAP) and excludes admin's own actions
- First-time users get 30-day lookback
- File-count badges for manual and migrations (non-log based)
- JS: `initSidebarBadges()` in `dashboard.js` fetches and renders badges on page load

### 6.25 Dashboard Home

**Classes:** `HomeRouter`, `SearchController`, `WebLogStatsController`, `WebLogStats`

- Dashboard home page with system overview
- Global search across news, pages, products, orders, and users (RBAC-filtered)
- Web visit statistics with chart data, top pages/news/products, IP addresses
- System log statistics per `*_log` table (today/week/total counts)
- `web_log_cache` table for performance optimization

### 6.26 Dashboard Manual

**Classes:** `ManualRouter`, `ManualController`

- Lists all help/manual HTML files grouped by module with language variants
- Displays specific manual with language fallback to English
- Manual files stored in `application/dashboard/manual/` as `{name}-manual-{lang}.html`

### 6.27 AI Helper (moedit)

**Classes:** `AIHelper`

- OpenAI API integration for moedit WYSIWYG editor
- HTML mode: content enhancement using GPT-4o-mini (Bootstrap 5 components)
- Vision mode: OCR/image text recognition using GPT-4o
- Endpoint: `POST /dashboard/content/moedit/ai`
- Requires `RAPTOR_OPENAI_API_KEY` in `.env`

### 6.28 Seed and Initial Data

**Classes:** `PermissionsSeed`, `RolePermissionSeed`, `MenuSeed`, `TextInitial`, `ReferenceInitial`, `NewsSamples`, `PagesSamples`, `ProductsSamples`

- Automatically populate database on fresh installs via Model `__initial()` methods
- Permissions: 18+ system permissions with `system_` prefix
- Roles: coder, admin, manager, editor, viewer with permission assignments
- Menu: 3-section dashboard sidebar (Contents, Shop, System) with i18n titles
- Translations: 100+ system UI keywords in MN/EN
- Reference templates: 11+ email templates (password reset, notifications, order confirmation) + ToS/PP
- Sample data: demo news (6), pages (14+), products (4) - removable via dashboard "Reset" button

### 6.29 Trash

**Classes:** `TrashRouter`, `TrashController`, `TrashModel`

- Stores deleted content records as JSON snapshots before hard deletion
- Replaces the old soft delete (`is_active=0`) pattern for content modules
- 15 models lost the `is_active` column; `deactivateById()` replaced with `deleteById()` for: News, Pages, Products, Orders, Reviews, Comments, Messages, Files, References, Settings, DevRequests, DevResponses, Menus, Texts, Languages
- Users and Organizations still use soft delete (`is_active` column retained)
- Dashboard interface for viewing, inspecting, and managing deleted records
- **Restore**: returns a record to its source table. Tries the original ID first to preserve FK references, falls back to auto-increment on PRIMARY KEY conflict; aborts with an admin-friendly message on UNIQUE collisions (slug, keyword, code, sku, etc.); LocalizedModel `_content` rows are restored alongside the primary row
- **Dual restore audit logging**: each restore writes to both `trash_log` (full audit) and the channel named by the trash record's `log_table` column, so the entry shows up in Logger Protocol on the record's view/update page. Controllers pass the log channel name directly to `TrashModel::store()` (e.g. `ReviewsController` -> `'products'`, `ReferencesController` -> `'content'`)
- Empty trash to permanently remove all records
- Restricted to the `system_coder` role

---

## 7. Template System

Raptor uses the `FileTemplate` class from the `codesaur/template` package - a lightweight custom engine with Twig-style syntax (NOT the actual `twig/twig` library).

### Base Variables

When calling `template()` from a controller, these variables are automatically added:

| Variable | Description |
|----------|-------------|
| `user` | Authenticated `User` object (may be null) |
| `index` | Script path (subdirectory support) |
| `localization` | Language and translation data |
| `request` | Current URL path |

### Custom Filters (registered by Controller)

| Filter | Usage | Description |
|--------|-------|-------------|
| `text` | `{{ 'key'\|text }}` | Get translation text |
| `link` | `{{ 'route'\|link({'id': 5}) }}` | Generate URL from route name |
| `basename` | `{{ path\|basename }}` | Extract filename (Web templates) |

### Twig features NOT supported

Use these alternatives in `codesaur/template`:

| Twig (unsupported) | Replacement |
|--------------------|-------------|
| `{% for i in 1..5 %}` | `{% for i in range(1, 5) %}` |
| `{% if x in list %}` | `{% if list[x] is defined %}` (use lookup map) or explicit `or` chain |
| `ends with`, `matches` | not available (only `starts with` works) |
| `**`, `//` operators | use `*`, `/` |
| `is odd/even/divisible/same` | not available |
| `loop.revindex`, `loop.parent` | not available (use `loop.length - loop.index0`) |
| `{% verbatim %}`, `{% include %}`, `{% extends %}` | not available |
| `\|date(format='Y-m-d')` | `\|date('Y-m-d')` (positional only) |

### Example

```html
<!-- Translation -->
<h1>{{ 'welcome'|text }}</h1>

<!-- Route link -->
<a href="{{ 'page'|link({'id': page.id}) }}">{{ page.title }}</a>

<!-- User check (object method calls supported) -->
{% if user is not null and user.can('system_content_index') %}
    <p>Hello, {{ user.profile.first_name }}!</p>
{% endif %}

<!-- Language switcher -->
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

        // PUT route (full resource update)
        $this->PUT('/path/{uint:id}', [Controller::class, 'method'])->name('route-name');

        // PATCH route (partial update - single field, status toggle)
        $this->PATCH('/path/{uint:id}/status', [Controller::class, 'method'])->name('route-name');

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
- `{{ 'route-name'|link }}` in Templates
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
| `template($file, $vars)` | Template object |
| `respondJSON($data, $code)` | JSON response |
| `redirectTo($route, $params)` | Redirect |
| `log($table, $level, $msg)` | Write log entry |
| `dispatch($event)` | Dispatch a PSR-14 event |
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
        $products = $model->getRows();

        // Render template
        $tpl = $this->template(__DIR__ . '/index.html', [
            'products' => $products
        ]);
        $tpl->render();
    }

    public function store()
    {
        $body = $this->getRequest()->getParsedBody();
        $model = new ProductsModel($this->pdo);
        $id = $model->insert($body);

        // Write log - use the standard `record_id` key so the entry shows up
        // in the record's Logger Protocol on its view/update page.
        $this->log('products', \Psr\Log\LogLevel::INFO, 'Product added', [
            'action'    => 'create',
            'record_id' => $id
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
| `deleteById($id)` | Hard delete by ID (used for content modules) |
| `deactivateById($id, $record)` | Soft delete by ID (used only for Users/Organizations) |
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
// application/dashboard/mymodule/MyModuleRouter.php
namespace Dashboard\MyModule;

class MyModuleRouter extends \codesaur\Router\Router
{
    public function __construct()
    {
        $this->GET('/dashboard/mymodule', [MyModuleController::class, 'index'])->name('mymodule');
        $this->GET_POST('/dashboard/mymodule/insert', [MyModuleController::class, 'insert'])->name('mymodule-insert');
    }
}
```

2. Register the namespace in `composer.json`:

```json
{
    "autoload": {
        "psr-4": {
            "Dashboard\\MyModule\\": "application/dashboard/mymodule/"
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
        $this->use(new MyModule\MyModuleRouter());  // New router
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
    $products = $model->getRows(['WHERE' => "published=1 AND code='$code'"]);
    $this->webTemplate(__DIR__ . '/products.html', ['products' => $products])->render();
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
