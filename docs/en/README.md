# Raptor Framework - Full Documentation

[![PHP Version](https://img.shields.io/badge/php-%5E8.2.1-777BB4.svg?logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)

> **codesaur/raptor** - A multi-layered, multi-tenant PHP CMS framework built on PSR standards.

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Installation](#2-installation)
3. [Configuration (.env)](#3-configuration)
4. [Architecture](#4-architecture)
5. [Middleware Pipeline](#5-middleware-pipeline)
6. [Modules](#6-modules) - one subsection per module
7. [Template System](#7-template-system)
8. [Routing](#8-routing)
9. [Controller](#9-controller)
10. [Model](#10-model)
11. [Testing](#11-testing)
12. [Usage Examples](#12-usage-examples)

---

## 1. Introduction

`codesaur/raptor` is a PHP framework built on PSR-7/PSR-15 middleware standards. It ships with two apps by default - **Web** (public site) and **Dashboard** (admin panel) - and a developer can add as many app layers as the project needs.

### Key Features

- **PSR-7/PSR-15** middleware-based architecture
- **JWT + Session** authentication
- **Multi-tenant** organizations with **RBAC** (Role-Based Access Control)
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
- Shared hosting / WAF compatibility (cPanel/LiteSpeed/mod_security): HTTP method override, body encoding
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

- In a new environment you MUST create the empty database yourself - Raptor only connects to an existing database, it never creates one (e.g. on MySQL: `CREATE DATABASE raptor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`)
- All tables inside it and the initial seed data (permissions, roles, translations, menu, sample content) are created automatically by Raptor's Model classes on first run
- Never create the tables by hand - the code will not work against a mismatched schema

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

### WAF Compatibility (mod_security)

```env
RAPTOR_WAF_BODY_ENCODING=true
```

- `RAPTOR_WAF_BODY_ENCODING` - When `true` (default), `csrfFetch()` base64-encodes form field values so a mod_security-style WAF cannot flag HTML/JS-like rich-text in the POST body; `BodyEncodingMiddleware` decodes them server-side. Set `false` on hosts without a body-inspecting WAF. See the "Shared Hosting / WAF Compatibility" section in `CLAUDE.md` for the full mechanism.

> **The session cookie lifetime** is set to **30 days** by Raptor in `SessionMiddleware` (`session_set_cookie_params(...)`) - a client-side cookie that, in practice, keeps an admin logged in even after closing the browser. The **server-side** session file cleanup (`session.gc_maxlifetime`), however, follows the host's php.ini rather than the framework; to store session files reliably and have them cleaned up on schedule, configure it per host - see [SESSION-LIFETIME.md](SESSION-LIFETIME.md).

#### "Why does my PUT/DELETE request show up as POST in the browser?"

This is expected, not a bug. When you open DevTools -> Network and submit a dashboard form declared `method="PUT"` (or PATCH/DELETE), you will see the request go out as **POST**. `csrfFetch()` deliberately rewrites these verbs to POST and carries the real verb in the **`X-HTTP-Method-Override`** request header, because many shared hosts (cPanel/LiteSpeed + mod_security) block PUT/PATCH/DELETE at the web-server level before PHP ever runs. On the server, `MethodOverrideMiddleware` reads that header and restores the real method before routing, so `GET_PUT('/news/{id}')` still dispatches to its PUT handler.

How to confirm what is happening in DevTools -> Network -> (the request) -> Headers:

- **Request method:** `POST` (the tunnel)
- **`X-HTTP-Method-Override: PUT`** - the real verb the route receives
- **`X-Body-Encoding: base64`** - present when body encoding is on; the form body shows as base64 instead of readable HTML (this is also intentional - see below)

The URL and the JSON response are identical to a native PUT, so nothing downstream changes.

#### Disabling body encoding

If your host has no body-inspecting WAF and you would rather see/inspect raw form bodies:

1. **Site-wide:** set `RAPTOR_WAF_BODY_ENCODING=false` in `.env`. `Controller::template()` then emits `<meta name="waf-body-encoding" content="0">` and `csrfFetch()` stops encoding. (The server-side decode is header-gated, so it simply never triggers - safe either way.)
2. **Custom layouts:** the `<meta name="waf-body-encoding">` tag is the only channel that carries this flag to the browser. If you do not use the shipped `dashboard.html`, you must include it, or the `=false` setting is silently ignored on the client.
3. **One-off raw request:** `csrfFetch()` has no per-call opt-out. To send a single request with the verb and body verbatim, call plain `fetch()` instead - but add the `X-CSRF-TOKEN` header yourself (`getCsrfToken()`) for CSRF-protected routes. This is mainly for calling non-dashboard/external endpoints; for dashboard routes prefer `csrfFetch()`.

Note: method override (the verb -> POST rewrite) has no off switch - it is always on but is a no-op unless the override header is present, so it never needs disabling. To hit an endpoint with a genuine PUT/DELETE verb, the host must allow those verbs and you must use a client other than `csrfFetch()` (e.g. plain `fetch()`).

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

> **CRITICAL: the web server document root MUST be `public_html/`, never the project root.**
> `public_html/` is the only directory meant to be web-served; everything else
> (`application/`, `protected/`, `database/`, `vendor/`, `.env`, `logs/`, ...)
> lives one level above it and must stay unreachable by URL. Pointing the docroot
> at the project root would expose source code, the `.env` secrets, uploaded
> files, and session data. This single rule is the primary, server-agnostic
> protection (Apache and Nginx alike) - the `.htaccess` / nginx `deny` rules are
> only a secondary fallback for exactly this misconfiguration. On shared hosting,
> set the domain's document root to `.../public_html`; the example configs below
> already do this.

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

**A / B / C** below are jobs of this workflow and cover virtually every environment - shared hosting, VPS, cloud VM, dedicated, Windows Server. **D** is outside the workflow: a fallback used only when none of A/B/C can reach the server.

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
| `FTP_SERVER_DIR` | Target directory on server | `/` |

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

**D) cPanel Git Deploy (fallback - only when none of A/B/C can reach the server)**

The three paths above are the standard ones. Being hosted on cPanel does NOT by
itself mean you need this path - if the host offers FTP (A) or SSH (B) access,
use those.

A few rare environments, however, are unreachable by any GitHub Actions job:
cPanel shared hosting with SSH/Terminal disabled and no externally reachable
FTP. A real-world example of such an environment is the shared hosting operated
by the National Data Center of Mongolia for government agency web portals. Only
in that case, use the scaffold built on cPanel's own Git + cron:

| File | Role |
|------|------|
| [`docs/conf.example/.cpanel.yml.example`](../conf.example/.cpanel.yml.example) | Copy to repo root as `.cpanel.yml` - cPanel Git deploy task list |
| [`docs/conf.example/auto-deploy.sh.example`](../conf.example/auto-deploy.sh.example) | Copy to `deploy/auto-deploy.sh`, run via cron |

Full guide: [`docs/mn/CPANEL.md`](../mn/CPANEL.md)

**Note:** The deploy workflow requires CI (`ci.yml`) to exist. If CI workflow is removed, deploy will not trigger.

#### Excluded from deployment

- **`.env`** - Create and configure manually on the server
- **Runtime folder contents** - `cache/`, `logs/`, `protected/`, `public_html/public/` and `database/migrations/` deploy as folders with their guard files (`.htaccess` etc.) and are created on the server, but their runtime contents (cache entries, logs, uploaded files, migration SQL) are never uploaded, overwritten or deleted by a deploy. Each deploy path enforces this with its own mechanism, so the three filter lists in `deploy.yml` intentionally differ - see the comments there before "harmonizing" them.
- **`docs/`, `tests/`** - Development only
- **`vendor/`** - Built during the workflow with `composer install/update --no-dev`

---

## 4. Architecture

### Two-Layer Structure

```
public_html/index.php (Entry point)
|
|-- /dashboard/* -> Dashboard\Application (Admin Panel)
|    |-- Middleware: ErrorHandler -> MethodOverride -> BodyEncoding -> Session -> JWT -> Container -> Localization -> Settings (CSRF is per-route)
|    |-- Routers: Login, Users, Organization, RBAC, Localization, Contents, Messages, Comments, Logs, Template, Shop, Development, Migration
|    \-- Controllers -> Templates -> HTML Response
|
\-- /* -> Web\Application (Public Website)
     |-- Middleware: ExceptionHandler -> Container -> Session -> Localization -> Settings
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

Raptor follows the MVC pattern but uses a **modular (package-by-feature)**
organization: each module bundles its Controller, Model, and templates together in
one folder, rather than splitting them across separate layer directories (there is
no top-level `Models/`, `Controllers/`, or `Views/`). Adding or removing a feature
is as simple as copying or deleting one folder.

`application/` holds two apps: `dashboard/` (admin panel application, which also
hosts the shared platform modules - base Controller, middlewares, models - used
by web) and `web/` (public website application). See the "Directory Structure"
section of the root `README.md` for the app-level layout. Each module's folder
location and classes are documented in its own subsection of section 6.

> **Everything outside `vendor/` is yours.** After `composer create-project`,
> `application/`, `public_html/`, `database/`, `tests/`, `docs/` and the config
> files all become part of your project - freely modify, delete, or rewrite them to
> fit your needs. There is no separate "framework core" layer: `application/` holds
> two apps, `dashboard/` and `web/`, and both are plain project code.
> The default codebase is a baseline covering a developer's common needs,
> so adapt the code directly to your own detailed requirements. Only the `vendor/*`
> packages are Composer-managed dependencies (updated via `composer update`); leave those untouched.

---

## 5. Middleware Pipeline

Middleware are PSR-15 standard layers that process request/response. Registration order matters!

The PDO connection is opened once in `public_html/index.php` via
`\Dashboard\DatabaseConnection::connect()` and reaches the Application as the
request's `pdo` attribute.

### Dashboard Middleware

| # | Middleware | Purpose |
|---|-----------|---------|
| 1 | `ErrorHandler` | Returns errors as JSON/HTML |
| 2 | `MethodOverrideMiddleware` | Restores PUT/PATCH/DELETE from `X-HTTP-Method-Override` (WAF verb-block workaround). Runs before Session/routing so the real verb is visible everywhere |
| 3 | `BodyEncodingMiddleware` | base64-decodes form fields sent with `X-Body-Encoding` (WAF body-inspection workaround) |
| 4 | `SessionMiddleware` | Starts and manages PHP session |
| 5 | `JWTAuthMiddleware` | Validates JWT and creates `User` object |
| 6 | `ContainerMiddleware` | Injects DI Container |
| 7 | `LocalizationMiddleware` | Determines language and translations |
| 8 | `SettingsMiddleware` | Injects system settings |

> `CsrfMiddleware` is NOT in the app-wide pipeline - it is attached per-route to each mutating route in the router (see 6.20).

### Web Middleware

| # | Middleware | Purpose |
|---|-----------|---------|
| 1 | `ExceptionHandler` | Renders error pages using templates |
| 2 | `ContainerMiddleware` | DI Container |
| 3 | `SessionMiddleware` | Session (stores language preference) |
| 4 | `LocalizationMiddleware` | Multi-language |
| 5 | `SettingsMiddleware` | Settings (logo, title, footer) |

### Database Driver Selection

The driver is chosen via the `RAPTOR_DB_DRIVER` variable in `.env`
(`mysql` or `pgsql`). `\Dashboard\DatabaseConnection::connect()` reads it
and returns the corresponding PDO instance:

```dotenv
# MySQL (default)
RAPTOR_DB_DRIVER=mysql

# PostgreSQL
RAPTOR_DB_DRIVER=pgsql
```

---

## 6. Modules

### 6.1 Authentication

**Classes:** `LoginRouter`, `LoginController`, `JWTAuthMiddleware`, `SessionMiddleware`, `User`

- JWT + Session combined authentication
- Login / Logout / Forgot password / Signup
- Password reset tokens are single-use: consumed via `deactivateById()` on successful reset (not deleted) - the admin requests modal shows them with used / expired / ready states
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
- Topbar organization switcher: users with more than one organization switch via a dropdown on the topbar brand area (search filter appears above 10 organizations)
- `system_coder` can switch into ANY active organization - access is derived from the role, no membership rows are created

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

### 6.5 Files

**Classes:** `FileRouter`, `FileController`, `FilesController`, `FilesModel`, `ProtectedFilesController`

- File upload (native JS, FormData)
- Image optimization (GD)
- Files organized by module/table
- MIME type detection
- Protected files (authenticated users only, authorizeRead() hook)

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
- Lives in `application/dashboard/development/` (`Dashboard\Development` namespace)
- Any authenticated user can create requests and can view, respond to and delete only their own or assigned ones
- Users with the `system_development` permission manage all requests (view, respond, delete any)

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

- **Per-route middleware** (NOT app-wide). Attached to each mutating route in the router via `->middleware([CsrfMiddleware::class])` (with `use Dashboard\CsrfMiddleware;`)
- The middleware ONLY validates: it compares `$_SESSION['CSRF_TOKEN']` against the `X-CSRF-TOKEN` header and returns 403 on mismatch
- GET/HEAD/OPTIONS requests pass through without validation (protects the GET side of `GET_POST`/`GET_PUT` compound routes)
- Token is generated at login and stored in `$_SESSION['CSRF_TOKEN']`. As a fallback for old sessions, `Controller::template()` generates it when an authorized user has none and the session is writable
- Token delivered to frontend via `<meta name="csrf-token">` in `dashboard.html` (`Controller::template()` reads it from session)
- Client JS sends token via `X-CSRF-TOKEN` header using `csrfFetch()` wrapper
- For new modules: every mutating route MUST add `->middleware([CsrfMiddleware::class])` (NOT automatic); only login routes are exempt. On the client, use `csrfFetch()` instead of `fetch()` for all state-changing requests

### 6.21 Database Migration

**Classes:** `MigrationRunner`, `MigrationController`, `MigrationRouter`, `MigrationSecurityScanner`

- File-based forward-only SQL migration system; state lives on disk (no tracking table)
- `database/migrations/` is git-ignored - per-environment upload
- Per-user folder: `{userId}-{username}/` holds pending files, `{userId}-{username}/ran/` holds applied files
- Coders upload `.sql` via the dashboard (max = min(10 MB, php.ini post_max_size, upload_max_filesize)); Apply runs the file and moves it to `ran/` on success
- `MigrationSecurityScanner` flags writes against sensitive tables (`users`, `rbac_*`, `organizations*`, `localization_language`, `raptor_menu`) and DCL (`GRANT/REVOKE/CREATE-DROP-ALTER USER`); a soft warning requires the coder to type `CONFIRM` before apply proceeds
- Advisory lock (`GET_LOCK` / `pg_try_advisory_lock`) prevents concurrent apply
- Every upload/apply/delete is logged to `dashboard_log` with SHA-256, statement count, and warning count
- Protected: only `system_coder` users can access the dashboard
- Parent `database/.htaccess` (deny from all) protection blocks direct browser access to SQL files

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

**Classes:** `Dashboard\Badge\BadgeController`, `Dashboard\Badge\BadgeRouter`, `Dashboard\Badge\AdminBadgeSeenModel` (`application/dashboard/badge/`)

- Colored badge pills on sidebar menu items showing unseen activity counts per admin
- Reads from existing `*_log` tables - no separate event table
- Multi-tenant: `orgScopedModules()` scopes listed modules' badges to the viewing admin's organization, filtering by the record's org (`record_organization_id` in log context) with the actor's org as fallback; `system_coder` and admins viewing from the system organization (`isSystemWideViewer()`) see all organizations
- Badge colors: green (create), blue (update), red (delete)
- Up to 3 badges per module, shown left to right in green-blue-red order
- Filters by admin permissions (PERMISSION_MAP) and excludes admin's own actions
- First-time users get 30-day lookback
- File-count badges for manual and migrations (non-log based)
- JS: `initSidebarBadges()` in `dashboard.js` fetches and renders badges on page load

### 6.25 Dashboard Home

**Classes:** `HomeRouter`, `SearchController`, `WebLogStatsController`, `WebLogStats`

- Dashboard home page with system overview
- Topbar quick icons (search | language | theme): search modal (Ctrl+K) across news, pages, products, orders, users, organizations, dev-requests, messages, comments, and reviews (RBAC-filtered, each source gated by its module's index permission or row-level filter); language dropdown (session-persisted); light/dark theme dropdown (instant, no reload)
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
- HTML mode: content enhancement with Bootstrap 5 components - model via `RAPTOR_OPENAI_MODEL` (.env, default `gpt-5-mini`)
- Vision mode: OCR/image text recognition - model via `RAPTOR_OPENAI_VISION_MODEL` (.env, default `gpt-5.1`)
- Endpoint: `POST /dashboard/content/moedit/ai`
- Requires `RAPTOR_OPENAI_API_KEY` in `.env`

### 6.28 Seed and Initial Data

**Classes:** `PermissionsSeed`, `RolePermissionSeed`, `MenuSeed`, `TextInitial`, `ReferenceInitial`, `NewsSamples`, `PagesSamples`, `ProductsSamples`

- Automatically populate database on fresh installs via Model `__initial()` methods
- Permissions: 18+ system permissions with `system_` prefix
- Roles: coder, admin, manager, editor, viewer with permission assignments
- Menu: 4-section dashboard sidebar (Contents, Shop, System, Coder - the last visible to system_coder only) with i18n titles
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
| `**`, `//` operators | use `*`, `/` |
| `is divisible by`, `is same as` | not available |
| `loop.revindex`, `loop.parent` | not available (use `loop.length - loop.index0`) |
| `{% verbatim %}`, `{% include %}`, `{% extends %}` | not available |
| `\|date(format='Y-m-d')` | `\|date('Y-m-d')` (positional only) |

> Since `codesaur/template` 4.1.0, `in` / `not in` membership, `ends with`, `matches` (regex), and `is even` / `is odd` ARE supported - e.g. `{% if type in ['image', 'video'] %}`.

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

### Base Controller (Dashboard\Controller)

All controllers extend `Dashboard\Controller`. Available methods:

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

class ProductsController extends \Dashboard\Controller
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
| `deactivateById($id, $record)` | Soft delete by ID (Users/Organizations soft delete, also consumes used Forgot tokens) |
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
|       \-- MigrationSecurityScannerTest.php  # Sensitive SQL pattern checks
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
        \-- MigrationRunnerIntegrationTest.php  # File-based migration runner tests
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

3. Register the Router inside `Dashboard\Application`'s constructor, next to the
   other `$this->use(...)` router registrations:

```php
// application/dashboard/Application.php  (inside __construct)
$this->use(new MyModule\MyModuleRouter());  // New router
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

Change the driver via `RAPTOR_DB_DRIVER` in `.env`:

```dotenv
# MySQL (default)
RAPTOR_DB_DRIVER=mysql

# Switch to PostgreSQL
RAPTOR_DB_DRIVER=pgsql
```

`\Dashboard\DatabaseConnection::connect()` reads this value and returns the matching PDO.

---

## Next Steps

- [API Reference](api.md) - Detailed API reference for all classes and methods
- [Discussions](https://github.com/orgs/codesaur-php/discussions) - Ask questions, share ideas, get help
- [codesaur ecosystem](https://github.com/codesaur-php) - Other packages
