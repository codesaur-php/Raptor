# Raptor Framework - Бүрэн танилцуулга

[![PHP Version](https://img.shields.io/badge/php-%5E8.2.1-777BB4.svg?logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](../../LICENSE)

> **codesaur/raptor** - PSR стандартууд дээр суурилсан, олон давхаргат архитектуртай PHP CMS фреймворк.

---

## Агуулга

1. [Танилцуулга](#1-танилцуулга)
2. [Суулгах](#2-суулгах)
3. [Тохиргоо (.env)](#3-тохиргоо)
4. [Архитектур](#4-архитектур)
5. [Middleware pipeline](#5-middleware-pipeline)
6. [Модулиуд](#6-модулиуд) (6.1-6.13 Суурь | 6.14-6.29 Дэлгүүр, Үнэлгээ, Event/Мэдэгдэл, Хөгжүүлэлт, SEO, Спам, CSRF, Migration, Мессеж, Сэтгэгдэл, Badge, Home, Manual, AI, Seed, Хогийн сав)
7. [Template систем](#7-template-систем)
8. [Routing](#8-routing)
9. [Controller](#9-controller)
10. [Model](#10-model)
11. [Тестчилгээ](#11-тестчилгээ)
12. [Хэрэглээний жишээ](#12-хэрэглээний-жишээ)

---

## 1. Танилцуулга

`codesaur/raptor` нь **Web** (нийтийн вебсайт) болон **Dashboard** (админ панель) гэсэн хоёр давхаргат бүтэцтэй, PSR-7/PSR-15 middleware суурьтай PHP фреймворк юм.

> **Тэмдэглэл:** Энэ package нь `codesaur/indodaptor` (500+ install) package-ийн залгамжлагч бөгөөд Packagist-ээс устгагдсан. "Indoraptor" нэр нь Universal Pictures-ийн trademark тул `codesaur/raptor` нэрээр шинээр үүсгэж, кодыг бүрэн refactor хийсэн.

### Гол боломжууд

- **PSR-7/PSR-15** middleware суурьтай архитектур
- **JWT + Session** нэвтрэлт баталгаажуулалт
- **RBAC** (Role-Based Access Control) эрхийн удирдлага
- **Олон хэл** дэмжлэг (Localization)
- CMS модулиуд: Мэдээ, Хуудас, Файл, Лавлах, Тохиргоо
- **Дэлгүүр** модуль (Бүтээгдэхүүн, Захиалга, Үнэлгээ)
- MySQL, PostgreSQL алийг нь ч дэмжинэ
- SQL файл суурьтай **өгөгдлийн сангийн migration** систем
- **codesaur/template** өөрийн template engine (Twig-маягийн синтакс)
- **OpenAI** интеграци (moedit editor)
- Зураг optimize хийх (GD)
- PSR-3 лог систем
- И-мэйл илгээх (**Brevo** API, SMTP, PHP mail)
- **PSR-14** Event Dispatcher систем (Discord, и-мэйл, лог - listener-ээр)
- **Discord** webhook мэдэгдэл (event listener-ээр)
- SEO: Хайлт, Sitemap, XML Sitemap, RSS feed
- Спам хамгаалалт (honeypot, HMAC token, rate limiting, Cloudflare Turnstile)
- CSRF хамгаалалт (CsrfMiddleware, csrfFetch)
- File-based DB cache (PSR-16 SimpleCache) - автомат invalidation-тэй
- Холбоо барих форм, мессеж удирдлага
- Мэдээний сэтгэгдэл, 1 түвшний хариулт
- Бүтээгдэхүүний үнэлгээ, одтой үнэлгээ (1-5)
- Админ имэйл мэдэгдэл: мессеж, захиалга, сэтгэгдэл, үнэлгээ (суваг тус бүрд тохируулах боломжтой)
- **Хогийн сав** модуль - устгасан контент бичлэгүүдийг сэргээх боломжтой

### codesaur экосистем

Raptor нь дараах codesaur packages-тэй хамтран ажиллана:

| Package | Зориулалт |
|---------|-----------|
| `codesaur/http-application` | PSR-15 Application, Router, Middleware суурь |
| `codesaur/dataobject` | PDO суурьтай ORM (Model, LocalizedModel) |
| `codesaur/template` | Template engine wrapper |
| `codesaur/http-client` | HTTP client (OpenAI API дуудлага) |
| `codesaur/container` | PSR-11 Dependency Injection Container |

---

## 2. Суулгах

### Шаардлага

- PHP **8.2.1+**
- Composer
- MySQL or PostgreSQL
- PHP extensions: `ext-gd`, `ext-intl`

### Composer ашиглан суулгах

```bash
composer create-project codesaur/raptor my-project
```

Composer-ийн `post-root-package-install` скрипт нь:
1. `.env.example` файлыг `.env` руу автоматаар хуулна (байхгүй бол)
2. `RAPTOR_JWT_SECRET` нууц түлхүүрийг автоматаар үүсгэнэ

> Хэрэв `.env` файл үүсээгүй бол `cp docs/conf.example/.env.example .env` командаар гараар хуулж, `RAPTOR_JWT_SECRET` утгыг өөрөө тохируулна.

### Гараар суулгах

```bash
git clone https://github.com/codesaur-php/Raptor.git my-project
cd my-project
composer install
cp docs/conf.example/.env.example .env
```

---

## 3. Тохиргоо

`.env` файлын бүх тохиргоонуудын тайлбар:

### Орчин ба Апп

```env
# Орчны горим: development эсвэл production
CODESAUR_APP_ENV=development

# Аппликейшний нэр
CODESAUR_APP_NAME=raptor

# Цагийн бүс (заавал биш)
#CODESAUR_APP_TIME_ZONE=Asia/Ulaanbaatar
```

- `development` горимд алдааг дэлгэцэн дээр харуулахын зэрэгцээ `logs/code.log` файлд бичнэ
- `production` горимд зөвхөн `logs/code.log` файлд бичнэ

### Өгөгдлийн сан

```env
RAPTOR_DB_HOST=localhost
RAPTOR_DB_NAME=raptor
RAPTOR_DB_USERNAME=root
RAPTOR_DB_PASSWORD=
RAPTOR_DB_CHARSET=utf8mb4
RAPTOR_DB_COLLATION=utf8mb4_unicode_ci
RAPTOR_DB_PERSISTENT=false
```

- Localhost (127.0.0.1) дээр ажиллаж байвал database автоматаар үүсгэнэ
- `RAPTOR_DB_PERSISTENT=true` байвал PDO persistent холболт ашиглана

### JWT (JSON Web Token)

```env
RAPTOR_JWT_ALGORITHM=HS256
RAPTOR_JWT_LIFETIME=2592000
RAPTOR_JWT_SECRET=auto-generated
#RAPTOR_JWT_LEEWAY=10
```

- `RAPTOR_JWT_SECRET` - Composer-ийн скриптээр автоматаар 128 тэмдэгт (64 байт hex) үүсгэнэ
- `RAPTOR_JWT_LIFETIME` - Токений хүчинтэй хугацаа секундээр (2592000 = 30 хоног)
- `RAPTOR_JWT_LEEWAY` - Серверийн цагийн зөрөөг зөвшөөрөх хугацаа

### И-мэйл

```env
RAPTOR_MAIL_FROM=noreply@codesaur.domain
#RAPTOR_MAIL_FROM_NAME="Raptor Notification"
#RAPTOR_MAIL_REPLY_TO=

# Transport: brevo (анхдагч), smtp, mail
#RAPTOR_MAIL_TRANSPORT=brevo
#RAPTOR_MAIL_BREVO_APIKEY=

# SMTP тохиргоо (transport=smtp үед)
#RAPTOR_SMTP_HOST=smtp.gmail.com
#RAPTOR_SMTP_PORT=465
#RAPTOR_SMTP_USERNAME=
#RAPTOR_SMTP_PASSWORD=
#RAPTOR_SMTP_SECURE=ssl
```

- `send()` нь `RAPTOR_MAIL_TRANSPORT`-оос хамааран transport сонгоно (brevo/smtp/mail)

### OpenAI

```env
#RAPTOR_OPENAI_API_KEY=sk-your-api-key-here
```

- moedit editor-ийн AI товчинд ашиглагдана

### Зургийн optimize

```env
RAPTOR_CONTENT_IMG_MAX_WIDTH=1920
RAPTOR_CONTENT_IMG_QUALITY=90
```

- CMS-д зураг upload хийхэд GD extension ашиглан optimize хийнэ

### Cloudflare Turnstile

```env
#RAPTOR_TURNSTILE_SITE_KEY=
#RAPTOR_TURNSTILE_SECRET_KEY=
```

- Заавал биш: тохируулаагүй бол Turnstile widget харагдахгүй, сервер талын шалгалт алгасна
- `SpamProtectionTrait` ашиглан нийтийн формуудад CAPTCHA шалгалт хийнэ

### Discord мэдэгдэл

```env
#RAPTOR_DISCORD_WEBHOOK_URL=https://discord.com/api/webhooks/...
```

- Заавал биш: хоосон эсвэл тохируулаагүй бол мэдэгдэл илгээхгүй
- `DiscordNotifier` сервис системийн үйл явдлын мэдэгдэлд ашиглана

### Серверийн тохиргоо

Apache болон Nginx серверийн жишээ тохиргоонууд [`docs/conf.example/`](../conf.example/) хавтаст байна:

| Файл | Тайлбар |
|------|---------|
| `.env.example` | Орчны тохиргооны лавлагаа |
| `.htaccess.example` | Apache URL rewrite болон HTTPS redirect |
| `.nginx.conf.example` | Nginx серверийн блок (HTTP, HTTPS, PHP-FPM) |

### CI/CD

Framework нь 2 GitHub Actions workflow-тэй:

#### CI (`.github/workflows/ci.yml`)

Repo-д анхнаасаа орсон default workflow. Push болон pull request бүрт код чанарын шалгалт хийнэ:

- `composer validate --strict` - composer.json шалгах
- PHP syntax check - бүх `.php` файлын синтакс
- Merge conflict markers - `<<<<<<<`, `=======`, `>>>>>>>` илрүүлэх
- Debug statements - `var_dump`, `dd`, `print_r` анхааруулга
- `composer dump-autoload --strict-psr` - autoload шалгах

#### Deploy (`.github/workflows/deploy.yml`)

Нэгдсэн deploy workflow, 3 job-той: **FTP**, **SSH**, болон **Windows Server self-hosted runner**. Job бүр зөвхөн шаардлагатай secrets/variables тохируулсан үед ажиллана. Тохируулсан бүх job-ууд зэрэг (parallel) ажиллана.

**Ажиллах дараалал:**

```
Push to main -> CI workflow ажиллана -> Амжилттай бол -> Deploy workflow эхэлнэ
                                     -> Амжилтгүй бол -> Deploy хийгдэхгүй
```

Deploy workflow нь `workflow_run` trigger ашиглан CI workflow-н дүнг хүлээнэ. CI амжилттай дуусвал (`conclusion == 'success'`) deploy эхэлнэ. CI fail болвол deploy `skipped` болно - алдаатай код серверт очихгүй. Deploy-ийн secrets/variables тохируулаагүй бол (жишээ: developer clone) бүх job чимээгүй алгасагдана.

**A) FTP Deploy**

FTP хандалттай дурын серверт (shared hosting, VPS, dedicated). **Settings -> Secrets and variables -> Actions -> Secrets** хэсэгт дараах secret-үүдийг нэмнэ:

| Secret | Тайлбар | Жишээ |
|--------|---------|-------|
| `FTP_HOST` | Серверийн FTP хаяг | `ftp.example.com` |
| `FTP_USERNAME` | FTP хэрэглэгчийн нэр | `user@example.com` |
| `FTP_PASSWORD` | FTP нууц үг | |
| `FTP_SERVER_DIR` | Серверийн зорьсон хавтас | `/` |

**B) SSH Deploy**

SSH хандалттай Linux серверт (VPS, cloud VM, dedicated). **Settings -> Secrets and variables -> Actions -> Secrets** хэсэгт дараах secret-үүдийг нэмнэ:

| Secret | Тайлбар | Жишээ |
|--------|---------|-------|
| `SSH_HOST` | Серверийн хаяг | `example.com` эсвэл `1.2.3.4` |
| `SSH_USERNAME` | SSH хэрэглэгчийн нэр | `deploy` |
| `SSH_KEY` | SSH private key (id_rsa агуулга бүтнээр) | |
| `SSH_DEPLOY_DIR` | Серверийн зорьсон хавтас | `/var/www/myproject` |
| `SSH_PORT` | (заавал биш) SSH порт, анхдагч: 22 | `22` |

**C) Windows Self-hosted Runner Deploy**

1. Windows Server дээр self-hosted runner суулгах:
   - **Settings -> Actions -> Runners -> New self-hosted runner -> Windows**
   - Runner-г Windows service болгон бүртгэж, сервер restart хийхэд автомат асдаг болгоно

2. **Settings -> Secrets and variables -> Actions -> Variables** хэсэгт дараах variable нэмнэ:

| Variable | Тайлбар | Жишээ |
|----------|---------|-------|
| `DEPLOY_PATH` | Серверийн project хавтас | `C:\xampp\htdocs\myproject` |

3. PHP болон Composer серверийн system PATH-д байх ёстой.

**Анхаарах:** Deploy workflow нь CI (`ci.yml`) байхыг шаарддаг. CI workflow-г устгасан бол deploy trigger хийгдэхгүй.

#### Deploy хийгдэхгүй файлууд

- **`.env`** - Серверт гараар үүсгэж тохируулна
- **`logs/`** - Аппликейшн автоматаар үүсгэнэ
- **`private/`** - Нууцлалтай файлууд (upload)
- **`docs/`** - Зөвхөн баримтжуулалт
- **`vendor/`** - Workflow дотор `composer install/update --no-dev` ажиллуулж build хийнэ

---

## 4. Архитектур

### Хоёр давхаргат бүтэц

```
public_html/index.php (Entry point)
|
|-- /dashboard/* -> Dashboard\Application (Админ панель)
|    |-- Middleware: ErrorHandler -> MySQL -> Session -> JWT -> CSRF -> Container -> Localization -> Settings
|    |-- Routers: Login, Users, Organization, RBAC, Localization, Contents, Messages, Comments, Logs, Template, Shop, Development, Migration
|    \-- Controllers -> Templates -> HTML Response
|
\-- /* -> Web\Application (Нийтийн вэб сайт)
     |-- Middleware: ExceptionHandler -> MySQL -> Container -> Session -> Localization -> Settings
     |-- Router: WebRouter (/, /page, /news, /contact, /products, /order, /search, /sitemap, /rss, /session/language, /session/contact-send, /session/order, /session/news/{id}/comment, /session/product/{id}/review, ...)
     \-- Controllers -> Templates -> HTML Response
```

### Request-ийн дамжих урсгал

```
Browser -> index.php -> .env -> ServerRequest
  -> Application сонгох (URL path-аар)
    -> Middleware chain (дарааллаар)
      -> Router match
        -> Controller::action()
          -> Model (DB)
          -> FileTemplate (codesaur/template) -> render()
            -> HTML Response -> Browser
```

### Директорийн бүтэц

```
raptor/
|-- application/
|   |-- raptor/                    # Суурь framework (Dashboard + shared)
|   |   |-- Application.php        # Dashboard Application суурь
|   |   |-- Controller.php         # Бүх Controller-ийн суурь анги
|   |   |-- CacheService.php       # Файл суурьтай DB cache (PSR-16 SimpleCache)
|   |   |-- CsrfMiddleware.php     # CSRF token шалгалт
|   |   |-- SpamProtectionTrait.php # Honeypot, HMAC, rate limit, Turnstile
|   |   |-- MySQLConnectMiddleware.php    # MySQL PDO холболт (localhost дээр DB-г автоматаар үүсгэнэ)
|   |   |-- PostgresConnectMiddleware.php # PostgreSQL PDO холболт (UTF8 client encoding)
|   |   |-- ContainerMiddleware.php       # PSR-11 DI container залгах (events, cache, mailer, Discord)
|   |   |-- SessionMiddleware.php         # Session lifecycle (shared, write-close оптимизаци)
|   |   |-- authentication/        # Login, JWT
|   |   |-- content/               # CMS модулиуд
|   |   |   |-- AIHelper.php       # OpenAI интеграци (moedit)
|   |   |   |-- ContentsRouter.php # Контентын төв router
|   |   |   |-- HtmlValidationTrait.php # Серверийн HTML шалгалт
|   |   |   |-- file/              # Файлын менежмент + upload суурь
|   |   |   |-- news/              # Мэдээ
|   |   |   |-- page/              # Хуудас
|   |   |   |-- messages/          # Холбоо барих мессежүүд
|   |   |   |-- reference/         # Лавлагаа + и-мэйл загварууд
|   |   |   \-- settings/          # Системийн тохиргоо
|   |   |-- localization/          # Хэл, орчуулга
|   |   |-- organization/          # Байгууллага
|   |   |-- rbac/                  # Эрхийн удирдлага + seed дата
|   |   |-- user/                  # Хэрэглэгч
|   |   |-- template/              # Dashboard UI, цэс, badge
|   |   |-- log/                   # PSR-3 лог
|   |   |-- mail/                  # И-мэйл (Brevo API, SMTP, PHP mail)
|   |   |-- event/                 # PSR-14 Event Dispatcher систем
|   |   |-- notification/          # Discord webhook listener
|   |   |-- trash/                 # Хогийн сав модуль (устгасан бичлэг сэргээх)
|   |   |-- migration/             # Өгөгдлийн сангийн migration систем
|   |   |-- development/           # Хөгжүүлэлтийн хүсэлт хянах
|   |   \-- exception/             # Алдаа барих
|   |-- dashboard/                 # Dashboard Application
|   |   |-- Application.php
|   |   |-- home/                  # Dashboard Home, хайлт, статистик
|   |   |-- manual/                # Гарын авлага харагч
|   |   \-- shop/                  # Дэлгүүр модуль (Бүтээгдэхүүн, Захиалга, Үнэлгээ)
|   \-- web/                       # Web Application
|       |-- Application.php
|       |-- WebRouter.php         # Web маршрутууд
|       |-- HomeController.php     # Нүүр, хэл солих
|       |-- content/               # Хуудас, Мэдээ
|       |-- shop/                  # Бүтээгдэхүүн, Захиалга, Үнэлгээ
|       |-- service/               # Хайлт, Sitemap, RSS, Холбоо барих
|       \-- template/              # Web layout
|           |-- TemplateController.php
|           |-- ExceptionHandler.php
|           \-- index.html
|-- public_html/
|   |-- index.php                  # Entry point
|   |-- .htaccess                  # Apache URL rewrite
|   |-- robots.txt                 # Хайлтын системийн бот удирдлага
|   \-- assets/                    # CSS, JS (dashboard, moedit, motable)
|-- docs/
|   |-- conf.example/              # Серверийн тохиргооны жишээ
|   |   |-- .env.example           # Орчны тохиргоо
|   |   |-- .htaccess.example      # Apache rewrite дүрмүүд
|   |   \-- .nginx.conf.example    # Nginx серверийн тохиргоо
|   |-- en/                        # Англи баримтжуулалт
|   \-- mn/                        # Монгол баримтжуулалт
|-- tests/                         # PHPUnit тестүүд (unit, integration)
|-- database/
|   \-- migrations/                # SQL migration файлууд
|-- .github/
|   \-- workflows/
|       |-- ci.yml                 # CI код чанарын шалгалт (push, PR)
|       \-- deploy.yml             # Автомат deploy (FTP / SSH / Windows Server)
|-- logs/                          # Алдааны лог файлууд
|-- private/                       # Хамгаалагдсан файлууд (upload, cache)
|-- composer.json
|-- phpunit.xml                    # PHPUnit тохиргоо
\-- LICENSE
```

---

## 5. Middleware Pipeline

Middleware бол PSR-15 стандартын дагуу request/response-г боловсруулах давхаргууд юм. Бүртгэгдсэн дараалал чухал!

### Dashboard Middleware

| # | Middleware | Зориулалт |
|---|-----------|-----------|
| 1 | `ErrorHandler` | Алдааг JSON/HTML хэлбэрээр хариулна |
| 2 | `MySQLConnectMiddleware` | PDO холболт үүсгэж request-д inject хийнэ |
| 3 | `MigrationMiddleware` | Pending SQL migration-г автоматаар ажиллуулна |
| 4 | `SessionMiddleware` | PHP session эхлүүлж удирдна |
| 5 | `JWTAuthMiddleware` | JWT шалгаж `User` объект үүсгэнэ |
| 6 | `CsrfMiddleware` | POST/PUT/PATCH/DELETE хүсэлтүүдэд CSRF token шалгана |
| 7 | `ContainerMiddleware` | DI Container-г inject хийнэ |
| 8 | `LocalizationMiddleware` | Хэл, орчуулгыг тодорхойлно |
| 9 | `SettingsMiddleware` | Системийн тохиргоог inject хийнэ |

### Web Middleware

| # | Middleware | Зориулалт |
|---|-----------|-----------|
| 1 | `ExceptionHandler` | Template ашиглан алдааны хуудас рендерлэнэ |
| 2 | `MySQLConnectMiddleware` | PDO холболт |
| 3 | `ContainerMiddleware` | DI Container |
| 4 | `SessionMiddleware` | Session (хэл хадгалах) |
| 5 | `LocalizationMiddleware` | Олон хэл |
| 6 | `SettingsMiddleware` | Тохиргоо (logo, title, footer) |

### Database Middleware сонголт

Зөвхөн **нэг** database middleware ашиглана:

```php
// MySQL (default)
$this->use(new \Raptor\MySQLConnectMiddleware());

// PostgreSQL
$this->use(new \Raptor\PostgresConnectMiddleware());
```

---

## 6. Модулиуд

### 6.1 Authentication (Нэвтрэлт)

**Классууд:** `LoginRouter`, `LoginController`, `JWTAuthMiddleware`, `SessionMiddleware`, `User`

- JWT + Session хосолсон authentication
- Login / Logout / Forgot password / Signup
- Байгууллага сонгох (олон байгууллагатай хэрэглэгч)
- JWT нь `$_SESSION['RAPTOR_JWT']` дотор хадгалагдана
- `User` объект нь profile, organization, RBAC permissions агуулна

### 6.2 User (Хэрэглэгч)

**Классууд:** `UsersRouter`, `UsersController`, `UsersModel`

- Хэрэглэгчийн CRUD (Create, Read, Update, Deactivate/soft delete)
- Нууц үг bcrypt hash ашиглан хадгална
- Profile мэдээлэл: username, email, phone, first_name, last_name
- Avatar зураг upload

### 6.3 Organization (Байгууллага)

**Классууд:** `OrganizationRouter`, `OrganizationController`, `OrganizationModel`, `OrganizationUserModel`

- Байгууллагын CRUD
- Хэрэглэгч-байгууллагын холбоос удирдлага
- Нэг хэрэглэгч олон байгууллагад харьяалагдах боломжтой

### 6.4 RBAC (Эрхийн удирдлага)

**Классууд:** `RBACRouter`, `RBACController`, `RBAC`, `Roles`, `Permissions`, `RolePermissions`, `UserRole`

- Role (дүр) үүсгэх, удирдах
- Permission (эрх) үүсгэх, удирдах
- Role-Permission хамаарал
- User-Role оноох
- Controller дотроос эрх шалгах:

```php
// Хэрэглэгч system байгууллага дээр "admin" дүртэй эсэх
$this->isUser('system_admin');

// Хэрэглэгч "news_edit" эрхтэй эсэх
$this->isUserCan('news_edit');
```

### 6.5 Content - Files (Файл)

**Классууд:** `FilesController`, `FilesModel`, `PrivateFilesController`

- Файл upload (native JS, FormData)
- Зураг optimize хийх (GD)
- Файлыг модуль/хүснэгтээр ангилах
- MIME type тодорхойлох
- Private файл (зөвхөн нэвтэрсэн хэрэглэгчдэд)

### 6.6 Content - News (Мэдээ)

**Классууд:** `NewsController`, `NewsModel`

- Мэдээний CRUD (бүрмөсөн устгах, Хогийн савд нөөцлөх)
- Нүүр зураг upload
- Хавсралт файлууд
- Нийтлэх огноо удирдах
- Үзэлтийн тоо (read_count)
- moedit editor ашиглан контент засварлах
- Жишиг дата цэвэрлэх: seed дата устгаж ID=1-ээс эхлүүлэх (`reset()` метод)

### 6.7 Content - Pages (Хуудас)

**Классууд:** `PagesController`, `PagesModel`

- Хуудасны CRUD (бүрмөсөн устгах, Хогийн савд нөөцлөх), хялбаршуулсан нэг формтой интерфэйс (type wizard хасагдсан)
- Parent-child бүтэц (олон түвшний навигацийн меню)
- `position` талбараар эрэмбэлэх
- `type` талбар: `content` (анхдагч), `nav` (эцэг/навигац хуудас - "Эцэг хуудас" switch ашиглан үүсгэнэ)
- Эцэг хуудас (хүүхэдтэй хуудас) засах үед контент талбарууд (description, content, link, featured) нуугдана
- `is_featured` талбар: Footer-д онцлох холбоос (хуудас эцэг болоход автоматаар 0 болно)
- `link` талбар: URL эсвэл локал зам, frontend + backend шалгалттай (`isValidLink()`)
- `read()` хамгаалалт: нийтлэгдсэн эсэх, эцэг хуудас эсэх, link redirect
- SEO slug үүсгэх (`generateSlug`)
- Файл хавсаргах
- Жишиг дата цэвэрлэх: seed дата устгаж ID=1-ээс эхлүүлэх (`reset()` метод)

### 6.8 Content - References (Лавлагаа)

**Классууд:** `ReferencesController`, `ReferencesModel`

- Лавлагааны хүснэгтүүд (key-value хэлбэрийн)
- Олон хэлтэй (LocalizedModel)
- Динамик хүснэгтийн нэр

### 6.9 Content - Settings (Тохиргоо)

**Классууд:** `SettingsController`, `SettingsModel`, `SettingsMiddleware`

- Системийн ерөнхий тохиргоо (олон хэлтэй)
- Сайтын гарчиг, лого, тайлбар
- Favicon, Apple Touch Icon
- Холбоо барих мэдээлэл (утас, имэйл, хаяг)
- Footer мэдээлэл (copyright, социал холбоосууд)
- `SettingsMiddleware` нь тохиргоог request attributes-д inject хийнэ

### 6.10 Localization (Олон хэл)

**Классууд:** `LocalizationRouter`, `LocalizationController`, `LanguageModel`, `TextModel`, `LocalizationMiddleware`

- Хэл нэмэх / засах / устгах
- Орчуулгын текст удирдах (key -> value)
- Session дээр суурилсан хэл сонголт
- Template дотор `{{ 'key'|text }}` ашиглах

### 6.11 Log (Лог)

**Классууд:** `LogsRouter`, `LogsController`, `Logger`

- PSR-3 стандартын лог систем
- Өгөгдлийн санд лог хадгалах
- Лог түвшин: emergency, alert, critical, error, warning, notice, info, debug
- Server request metadata автоматаар бүртгэх
- Хэрэглэгчийн мэдээлэл автоматаар бүртгэх
- Error log таб (system_coder хэрэглэгчид) - PHP error.log файлыг Хандалтын протокол хуудаснаас шууд харах

### 6.12 Mail (И-мэйл)

**Классууд:** `Mailer`

- `send()` нь `.env`-ийн `RAPTOR_MAIL_TRANSPORT`-оос хамааран brevo/smtp/mail сонгоно
- HTML форматтай мессеж, CC/BCC, хавсралт дэмжинэ

### 6.13 Template (Dashboard UI)

**Классууд:** `TemplateRouter`, `TemplateController`, `DashboardTrait`, `MenuModel`, `FileController`

- Dashboard layout рендерлэлт `DashboardTrait::dashboardTemplate()` ашиглан
- Sidebar цэс олон хэл, эрх, parent/child бүтэцтэй (`MenuModel`)
- Цэс удирдлагын CRUD (нэмэх, засах, идэвхгүй болгох)
- Файл upload, шалгалт, зураг optimize `FileController` суурь классаар
- SweetAlert2, motable, moedit зэрэг JS компонентууд
- Responsive Bootstrap 5 дизайн

### 6.14 Shop (Дэлгүүр)

**Классууд:** `ProductsController`, `OrdersController`, `ReviewsController`, `ShopRouter` (products + orders + reviews нэгдсэн router), `ProductsModel`, `ProductOrdersModel`, `ReviewsModel`

- Бүтээгдэхүүний CRUD (бүрмөсөн устгах, Хогийн савд нөөцлөх), slug үүсгэх, хураангуй гаргах
- Бүтээгдэхүүний талбарууд: үнэ, хямдралын үнэ, SKU, barcode, хэмжээ, өнгө, нөөц, ангилал, онцлох, үнэлгээ зөвшөөрөх
- Захиалгын удирдлага (`products_orders` хүснэгт) - хэрэглэгчийн мэдээлэл, статус хянах
- Бүтээгдэхүүний үнэлгээ, одтой үнэлгээ (1-5), бичмэл сэтгэгдэл
- Үнэлгээ products-view дотор харагдана (web болон dashboard)
- Web бүтээгдэхүүний хуудсанд media gallery (thumbnail strip + том preview)
- Жишиг дата анхны ачааллаар автоматаар үүсгэгдэнэ
- Шинэ захиалга, статус өөрчлөлт, үнэлгээний PSR-14 event суурьтай мэдэгдэл
- Шинэ захиалгын админ имэйл мэдэгдэл (toggle + хаяг тохируулах, `system_coder` эрхтэй)

### 6.15 Reviews (Бүтээгдэхүүний үнэлгээ)

**Классууд:** `ReviewsController`, `ReviewsModel` (dashboard, `ShopRouter`-аар бүртгэгдсэн), `ShopController::reviewSubmit()` (web)

- Бүтээгдэхүүний хуудсан дээрх нийтийн үнэлгээний форм (`review=1` үед)
- Одтой үнэлгээ (1-5), бичмэл сэтгэгдэл
- Зочин хэрэглэгч нэр, имэйлээ бичнэ (имэйл заавал биш)
- `SpamProtectionTrait` ашиглан спам хамгаалалт (honeypot, HMAC, rate limiting, Turnstile)
- Дундаж үнэлгээ, тоо бүтээгдэхүүний жагсаалтын карт дээр харагдана
- Dashboard: products-view дотор үнэлгээний жагсаалт, устгах боломжтой
- Dashboard: products-index толгой хэсгээс үнэлгээний жагсаалт руу очих линк
- Badge: шинэ үнэлгээ `info` (усан цэнхэр) badge-ээр products sidebar дээр харагдана
- Админ имэйл мэдэгдэл (toggle + хаяг тохируулах, `system_coder` эрхтэй, анхдагч: идэвхгүй)
- Web талаас `/session/product/{id}/review`-ээр үнэлгээ илгээнэ

### 6.16 Event систем & Мэдэгдэл

**Классууд:** `EventDispatcher`, `ListenerProvider`, `ContentEvent`, `UserEvent`, `OrderEvent`, `DevRequestEvent`, `DiscordListener`

- **PSR-14 Event Dispatcher** систем - шууд Discord дуудлагыг орлосон
- Event классууд: `ContentEvent`, `UserEvent`, `OrderEvent`, `DevRequestEvent`
- `ListenerProvider` нь listener-үүдийг бүртгэнэ (одоогоор `DiscordListener`)
- `DiscordListener` нь бүх төрлийн event-д Discord webhook мэдэгдэл илгээнэ
- Controller-ууд `$this->dispatch(new ContentEvent(...))` helper-ээр event дамжуулна
- `DiscordNotifier` нь админы нэр, dashboard URL-г хадгална (`ContainerMiddleware`-д inject хийгдсэн)
- Мэдэгдлийн төрлүүд: хэрэглэгч бүртгүүлсэн, хэрэглэгч зөвшөөрсөн, шинэ захиалга, захиалгын статус өөрчлөлт, контентийн үйлдлүүд (нэмэх, засах, устгах, нийтлэх)
- Өнгөт Discord embed мессеж
- `RAPTOR_DISCORD_WEBHOOK_URL` орчны хувьсагчаар тохируулна
- Webhook URL тохируулаагүй эсвэл listener байхгүй бол чимээгүй алгасна

### 6.17 Development (Хөгжүүлэлтийн хэрэгсэл)

**Классууд:** `DevelopmentRouter`, `DevRequestController`, `DevRequestModel`, `DevResponseModel`

- Хөгжүүлэлтийн хүсэлт хянах систем (хүсэлт илгээх, хариулах, түүх харах)
- `development:development` RBAC эрхээр хамгаалагдсан

### 6.18 Site Service (Web)

**Классууд:** `SeoController`

- Хуудас, мэдээ, бүтээгдэхүүн дундаас бүтэн текст хайлт
- Хүнд ээлтэй sitemap хуудас, шатлалтай хуудасны бүтэцтэй
- XML sitemap (`/sitemap.xml`) хайлтын системүүдэд
- RSS 2.0 feed (`/rss`) сүүлийн мэдээ, бүтээгдэхүүнтэй
- `robots.txt` - Хайлтын системийн бот удирдлага `public_html/`-д агуулагдана

#### robots.txt

`public_html/robots.txt` файл нь хайлтын системийн ботуудыг удирдана:

- **Зөвшөөрөгдсөн:** Googlebot, Bingbot, YandexBot, Baiduspider
- **Хаагдсан:** SEO scraper-ууд (MJ12bot, SemrushBot, AhrefsBot, DotBot, Bytespider, PetalBot)
- **AI ботууд:** Анхдагчаар зөвшөөрөгдсөн (GPTBot, ClaudeBot гэх мэт). Хаахыг хүсвэл тухайн мөрийг uncomment хийнэ
- **Dashboard:** `/dashboard/` бүх ботоос хаагдсан
- **Sitemap:** `Sitemap:` мөрийг өөрийн домэйн хаягаар солино

```
Sitemap: https://example.com/sitemap.xml
```

### 6.19 Спам хамгаалалт

**Классууд:** `SpamProtectionTrait`

- Honeypot нууц талбарын илрүүлэлт
- HMAC токен цаг хугацааны хамт шалгах
- Үйлдэл тус бүрийн хурд хязгаарлалт (login 2s, signup 5s, forgot 10s)
- Формын хугацаа дуусах шалгалт (1 цагийн дотор)
- Бөглөх хурдны доод хязгаар (1 секунд)
- Cloudflare Turnstile CAPTCHA дэмжлэг (`.env` дотор `RAPTOR_TURNSTILE_SECRET_KEY` тохируулсан үед идэвхжинэ)
- Линк спам шүүлтүүр (хэт олон URL агуулсан текстийг хаана)
- Нэвтрэх, бүртгүүлэх, нууц үг сэргээх, холбоо барих, сэтгэгдэл, үнэлгээ, захиалгын формуудад ашиглагдана

### 6.20 CSRF хамгаалалт

**Классууд:** `CsrfMiddleware`

- Dashboard-ийн бүх POST/PUT/PATCH/DELETE хүсэлтүүдэд session-тай холбоотой CSRF token шалгана
- Token нь login үед үүсэж `$_SESSION['CSRF_TOKEN']` дотор хадгалагдана
- Token байхгүй хуучин session-д автоматаар үүсгэнэ (session бичих эрхтэй байх шаардлагатай)
- GET/HEAD/OPTIONS хүсэлтүүд шалгалтгүйгээр дамжина
- `/login` замууд exempt (тэнд token үүсдэг)
- Клиент тал (JS) нь `csrfFetch()` wrapper ашиглан `X-CSRF-TOKEN` header-аар дамжуулна
- Token нь `dashboard.html` доторх `<meta name="csrf-token">` tag-аар frontend-д хүрнэ
- Шинэ модуль нэмэхэд: бүх state-changing хүсэлтэд `fetch()` биш `csrfFetch()` ашиглана

### 6.21 Database Migration (Өгөгдлийн сангийн шилжүүлэг)

**Классууд:** `MigrationRunner`, `MigrationMiddleware`, `MigrationController`, `MigrationRouter`

- SQL файл дээр суурилсан, зөвхөн урагшлах (forward-only) migration систем
- Migration файлууд `database/migrations/` хавтаст хадгалагдана
- Хүлээгдэж буй = `migrations/` дотор, Ажилласан = `migrations/ran/` руу зөөгдсөн
- `MigrationMiddleware` хүсэлт бүрт pending migration-г автоматаар ажиллуулна
- Advisory lock (`GET_LOCK`) зэрэгцээ ажиллахаас хамгаална
- Dashboard UI-аар migration төлөв, SQL файлын агуулга харах
- Зөвхөн `system_coder` эрхтэй хэрэглэгчид dashboard руу хандах боломжтой
- `.htaccess` хамгаалалт SQL файлуудад шууд хандахыг хаана

### 6.22 Messages (Холбоо барих мессеж)

**Классууд:** `MessagesController`, `MessagesModel` (dashboard), `ContactController` (web)

- Нийтийн холбоо барих форм (`/contact`), спам хамгаалалттай
- Холбоо барих формын мессежүүдийг өгөгдлийн санд хадгална
- Dashboard интерфэйс: мессежүүдийг харах, удирдах
- Мессежийн дэлгэрэнгүйг modal цонхонд харуулна
- Бүрмөсөн устгах, Хогийн савд нөөцлөх (soft delete/идэвхгүй болгохыг орлосон)
- Шинэ мессежийн event суурьтай мэдэгдэл (PSR-14 listener-ээр Discord)
- Админ имэйл мэдэгдэл (toggle + хаяг тохируулах, `system_coder` эрхтэй)
- Web талын `ContactController` нь формыг харуулах болон `/session/contact-send`-ээр илгээхийг удирдана

### 6.23 Comments (Мэдээний сэтгэгдэл)

**Классууд:** `CommentsController`, `CommentsModel` (dashboard), `NewsController::commentSubmit()` (web)

- Мэдээний хуудсан дээрх нийтийн сэтгэгдлийн форм
- 1 түвшний хариулт (parent_id ашиглан дээд түвшний сэтгэгдэлд хариулах)
- Зочин хэрэглэгч нэр, имэйлээ бичнэ
- Нэвтэрсэн хэрэглэгчийн нэр/имэйл профайлаас автоматаар бөглөгдөнө
- `SpamProtectionTrait` ашиглан спам хамгаалалт (honeypot, HMAC, rate limiting, Turnstile)
- Dashboard: news-view дотор сэтгэгдлийн жагсаалт, хариулах, устгах боломжтой
- Dashboard: news-index толгой хэсгээс сэтгэгдлийн жагсаалт руу очих линк
- Badge: шинэ сэтгэгдэл `info` (усан цэнхэр) badge-ээр news sidebar дээр харагдана
- Бүрмөсөн устгах, Хогийн савд нөөцлөх (soft delete/идэвхгүй болгохыг орлосон)
- Админ имэйл мэдэгдэл (toggle + хаяг тохируулах, `system_coder` эрхтэй, анхдагч: идэвхгүй)
- Web талаас `/session/news/{id}/comment`-ээр сэтгэгдэл илгээнэ

### 6.24 Badge систем (Sidebar Badge)

**Классууд:** `BadgeController`, `BadgeRouter`, `AdminBadgeSeenModel`

- Sidebar цэсний зүйлс дээр модуль тус бүрийн уншаагүй үйлдлийн тоог өнгөт badge-ээр харуулна
- `*_log` хүснэгтүүдээс уншина - тусдаа event хүснэгт шаардахгүй
- Badge өнгө: ногоон (create), цэнхэр (update), улаан (delete)
- Модуль бүрт 3 хүртэл badge, зүүнээс баруун тийш ногоон-цэнхэр-улаан дарааллаар
- Админы эрхээр шүүж (PERMISSION_MAP), өөрийн үйлдлийг хасна
- Шинэ хэрэглэгчид 30 хоногийн lookback
- Manual, migration-д файлын тоон дээр суурилсан badge (лог бус)
- JS: `initSidebarBadges()` `dashboard.js` дотор хуудас ачаалахад badge татаж рендерлэнэ

### 6.25 Dashboard Home

**Классууд:** `HomeRouter`, `SearchController`, `WebLogStatsController`, `WebLogStats`

- Dashboard нүүр хуудас системийн ерөнхий мэдээлэлтэй
- Мэдээ, хуудас, бүтээгдэхүүн, захиалга, хэрэглэгчээс ерөнхий хайлт (RBAC шүүлтүүртэй)
- Вэб зочилсон статистик: график, шилдэг хуудас/мэдээ/бүтээгдэхүүн, IP хаяг
- Системийн `*_log` хүснэгтүүдийн статистик (өнөөдөр/долоо хоног/нийт)
- `web_log_cache` хүснэгт гүйцэтгэлийг хурдасгахад ашиглана

### 6.26 Dashboard Manual (Гарын авлага)

**Классууд:** `ManualRouter`, `ManualController`

- Бүх гарын авлагын HTML файлуудыг модулиар бүлэглэн жагсаана
- Тодорхой гарын авлагыг харуулна, хэлний fallback англи руу
- Файлууд: `application/dashboard/manual/` хавтаст `{name}-manual-{lang}.html` форматтай

### 6.27 AI Helper (moedit)

**Классууд:** `AIHelper`

- moedit WYSIWYG editor-ийн OpenAI API интеграци
- HTML горим: GPT-4o-mini ашиглан контент сайжруулалт (Bootstrap 5 компонент)
- Vision горим: GPT-4o ашиглан зургаас текст таних (OCR)
- Endpoint: `POST /dashboard/content/moedit/ai`
- `.env`-д `RAPTOR_OPENAI_API_KEY` шаардлагатай

### 6.28 Seed болон анхдагч дата

**Классууд:** `PermissionsSeed`, `RolePermissionSeed`, `MenuSeed`, `TextInitial`, `ReferenceInitial`, `NewsSamples`, `PagesSamples`, `ProductsSamples`

- Шинэ суулгалтад өгөгдлийн сангийг Model `__initial()` методоор автоматаар дүүргэнэ
- Эрхүүд: `system_` угтвартай 18+ системийн эрх
- Role-ууд: coder, admin, manager, editor, viewer - эрхийн оноолттой
- Цэс: 3 хэсэгтэй dashboard sidebar (Contents, Shop, System), олон хэлтэй
- Орчуулга: 100+ системийн UI keyword MN/EN хэлээр
- Лавлагаа загварууд: 11+ и-мэйл загвар (нууц үг сэргээх, мэдэгдлүүд, захиалга) + Нөхцөл/Нууцлал
- Жишиг дата: демо мэдээ (6), хуудас (14+), бүтээгдэхүүн (4) - dashboard-ийн "Reset" товчоор устгах боломжтой

### 6.29 Trash (Хогийн сав)

**Классууд:** `TrashRouter`, `TrashController`, `TrashModel`

- Бүрмөсөн устгахаас өмнө устгасан бичлэгүүдийг JSON хэлбэрээр хадгална
- Контент модулиудын хуучин soft delete (`is_active=0`) загварыг орлосон
- 15 model-оос `is_active` багана хасагдсан; `deactivateById()` нь `deleteById()` болж солигдсон: News, Pages, Products, Orders, Reviews, Comments, Messages, Files, References, Settings, DevRequests, DevResponses, Menus, Texts, Languages
- Users болон Organizations нь soft delete хэвээр хадгалагдсан (`is_active` багана хэвээр)
- Dashboard интерфэйсээс устгасан бичлэгүүдийг харах, шалгах, удирдах
- **Сэргээх (Restore)**: бичлэгийг үндсэн хүснэгт рүү буцаах. Эхлээд анхны ID-аар оролдох (FK холбоосыг хадгалахын тулд), амжилтгүй бол auto-increment ID; UNIQUE талбар (slug, keyword, code, sku) давхцалтай бол админд ойлгомжтой алдаа буцаах; LocalizedModel-ийн `_content` мөрүүд хамт сэргээгдэнэ
- **Хоёр давхар аудит лог**: сэргээх үйлдэл `trash_log` (бүрэн audit) ба trash бичлэгийн `log_table` баганаас уншсан channel-д аль алинд бичигдэнэ - энэ нь Logger Protocol-оор сэргээгдсэн record-ын view/update хуудсан дээр харагдана. Controller-ууд `TrashModel::store()`-руу log channel-ийн нэрийг шууд дамжуулна (жишээ: `ReviewsController` -> `'products'`, `ReferencesController` -> `'content'`)
- Хогийн савыг бүрэн хоослох боломжтой
- Зөвхөн `system_coder` дүртэй админ хандана

---

## 7. Template систем

Raptor нь `codesaur/template` package-ийн `FileTemplate` классыг ашиглана - Twig-ийн синтаксыг дуурайсан хөнгөн engine (бодитоор `twig/twig` library биш).

### Суурь хувьсагчид

Controller дотроос `template()` дуудахад доорх хувьсагчид автоматаар нэмэгднэ:

| Хувьсагч | Тайлбар |
|----------|---------|
| `user` | Нэвтэрсэн хэрэглэгчийн `User` объект (null байж болно) |
| `index` | Script path (subdirectory дэмжлэг) |
| `localization` | Хэл, орчуулгын мэдээлэл |
| `request` | Одоогийн URL path |

### Custom filter-ууд (Controller-ээс бүртгэгдсэн)

| Filter | Хэрэглээ | Тайлбар |
|--------|----------|---------|
| `text` | `{{ 'key'\|text }}` | Орчуулгын текст авах |
| `link` | `{{ 'news'\|link({'id': 5}) }}` | 5-р мэдээний URL үүсгэх |
| `basename` | `{{ path\|basename }}` | Файлын нэр гаргах (Web templates) |

### Twig-ийн дэмжигдэхгүй боломжууд

`codesaur/template` дотор дараах Twig pattern ажилладаггүй; жишигийн орлуулга ашиглана:

| Twig (дэмжигдэхгүй) | Орлуулга |
|---------------------|----------|
| `{% for i in 1..5 %}` | `{% for i in range(1, 5) %}` |
| `{% if x in list %}` | `{% if list[x] is defined %}` (lookup map) эсвэл `or` chain |
| `ends with`, `matches` | байхгүй (зөвхөн `starts with`) |
| `**`, `//` | байхгүй (`*`, `/` ашиглана) |
| `is odd/even/divisible/same` | байхгүй |
| `loop.revindex`, `loop.parent` | байхгүй |
| `{% verbatim %}`, `{% include %}`, `{% extends %}` | байхгүй |
| `\|date(format='Y-m-d')` | `\|date('Y-m-d')` (зөвхөн positional) |

### Жишээ

```html
<!-- Орчуулга -->
<h1>{{ 'welcome'|text }}</h1>

<!-- Route link -->
<a href="{{ 'page'|link({'id': page.id}) }}">{{ page.title }}</a>

<!-- Хэрэглэгч шалгах (object method дуудлага дэмжигдэнэ) -->
{% if user is not null and user.can('system_content_index') %}
    <p>Сайн байна уу, {{ user.profile.first_name }}!</p>
{% endif %}

<!-- Хэл солих -->
{% for code, language in localization.language %}
    <a href="{{ 'language'|link({'code': code}) }}">{{ language.title }}</a>
{% endfor %}
```

---

## 8. Routing

Raptor нь `codesaur/http-application` package-ийн Router классыг ашиглана.

### Route тодорхойлох

```php
class MyRouter extends \codesaur\Router\Router
{
    public function __construct()
    {
        // GET маршрут
        $this->GET('/path', [Controller::class, 'method'])->name('route-name');

        // POST маршрут
        $this->POST('/path', [Controller::class, 'method'])->name('route-name');

        // PUT маршрут (бүтэн ресурс шинэчлэх)
        $this->PUT('/path/{uint:id}', [Controller::class, 'method'])->name('route-name');

        // PATCH маршрут (хэсэгчлэн шинэчлэх - нэг талбар, статус toggle)
        $this->PATCH('/path/{uint:id}/status', [Controller::class, 'method'])->name('route-name');

        // DELETE маршрут
        $this->DELETE('/path', [Controller::class, 'method'])->name('route-name');

        // GET + POST (форм)
        $this->GET_POST('/path', [Controller::class, 'method'])->name('route-name');

        // GET + PUT (засах форм)
        $this->GET_PUT('/path/{uint:id}', [Controller::class, 'method'])->name('route-name');
    }
}
```

### Динамик параметрууд

| Pattern | Тайлбар | Жишээ |
|---------|---------|-------|
| `{name}` | String параметр | `/page/{slug}` |
| `{uint:id}` | Unsigned integer | `/page/{uint:id}` |
| `{code}` | String (хэлний код) | `/language/{code}` |

### Router бүртгэх

Application класс дотроос:

```php
$this->use(new MyRouter());
```

### Route нэрийн оновчлол

`->name('route-name')` зөвхөн route нэр бодитоор ашиглагдаж байгаа үед тавина:
- Template дотор `{{ 'route-name'|link }}` хэлбэрээр
- PHP controller дотор `$this->redirectTo('route-name')` хэлбэрээр

Нэрээр дуудагддаггүй route-д `->name()` шаардлагагүй бөгөөд илүүдэл ачааллыг бууруулна.

---

## 9. Controller

### Суурь Controller (Raptor\Controller)

Бүх Controller-ууд `Raptor\Controller` ангиас удамшина. Доорх боломжуудыг нийтлэг авна:

| Метод | Тайлбар |
|-------|---------|
| `$this->pdo` | PDO холболт |
| `getUser()` | Нэвтэрсэн хэрэглэгч (`User\|null`) |
| `getUserId()` | Хэрэглэгчийн ID |
| `isUserAuthorized()` | Нэвтэрсэн эсэх |
| `isUser($role)` | RBAC дүр шалгах |
| `isUserCan($permission)` | RBAC эрх шалгах |
| `getLanguageCode()` | Идэвхтэй хэлний код |
| `getLanguages()` | Бүх хэлний жагсаалт |
| `text($key)` | Орчуулгын текст |
| `template($file, $vars)` | Template объект |
| `respondJSON($data, $code)` | JSON хариулт |
| `redirectTo($route, $params)` | Redirect хийх |
| `log($table, $level, $msg)` | Лог бичих |
| `dispatch($event)` | PSR-14 event дамжуулах |
| `generateRouteLink($name, $params)` | URL үүсгэх |
| `getContainer()` | DI Container |
| `getService($id)` | Service авах |

### Жишээ: Шинэ Controller бичих

```php
namespace Dashboard\Products;

class ProductsController extends \Raptor\Controller
{
    public function index()
    {
        // Эрх шалгах
        if (!$this->isUserCan('product_read')) {
            throw new \Error('Эрх хүрэлцэхгүй', 403);
        }

        // Model ашиглах
        $model = new ProductsModel($this->pdo);
        $products = $model->getRows();

        // Template рендерлэх
        $twig = $this->template(__DIR__ . '/index.html', [
            'products' => $products
        ]);
        $twig->render();
    }

    public function store()
    {
        $body = $this->getRequest()->getParsedBody();
        $model = new ProductsModel($this->pdo);
        $id = $model->insert($body);

        // Лог бичих - стандарт `record_id` түлхүүр ашиглах
        // (бичлэгийн харах/засах хуудсан дээрх Logger Protocol-д харагдана).
        $this->log('products', \Psr\Log\LogLevel::INFO, 'Бүтээгдэхүүн нэмлээ', [
            'action'    => 'create',
            'record_id' => $id
        ]);

        // JSON хариулт
        $this->respondJSON(['status' => 'success', 'id' => $id]);
    }
}
```

---

## 10. Model

Raptor нь `codesaur/dataobject` package-ийн Model классуудыг ашиглана.

### Model (нэг хэлтэй)

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

### LocalizedModel (олон хэлтэй)

```php
use codesaur\DataObject\Column;
use codesaur\DataObject\LocalizedModel;

class CategoriesModel extends LocalizedModel
{
    public function __construct(\PDO $pdo)
    {
        $this->setInstance($pdo);

        // Үндсэн хүснэгт
        $this->setColumns([
           (new Column('id', 'bigint'))->primary(),
           (new Column('is_active', 'tinyint'))->default(1),
        ]);

        // Хэл тус бүрийн контент
        $this->setContentColumns([
            new Column('title', 'varchar', 255),
            new Column('description', 'text'),
        ]);

        $this->setTable('categories');
    }
}
```

### Гол методууд

| Метод | Тайлбар |
|-------|---------|
| `insert($record)` | Бичлэг нэмэх |
| `updateById($id, $record)` | ID-р шинэчлэх |
| `deleteById($id)` | ID-р бүрмөсөн устгах (контент модулиудад) |
| `deactivateById($id, $record)` | ID-р идэвхгүй болгох (зөвхөн Users/Organizations-д) |
| `getRowWhere($with_values)` | WHERE key=value хэлбэрийн нөхцөлөөр нэг мөр авах |
| `getRow($condition)` | SELECT нөхцөлөөр нэг мөр авах |
| `getRows($condition)` | SELECT нөхцөлөөр олон мөр авах |
| `getName()` | Хүснэгтийн нэр авах |

### LocalizedModel өгөгдлийн бүтэц

`LocalizedModel::getRows()` буцаах бүтэц:

```php
[
    1 => [
        'id' => 1,
        'is_active' => 1,
        'localized' => [
            'mn' => ['title' => 'Монгол гарчиг', 'description' => '...'],
            'en' => ['title' => 'English title', 'description' => '...'],
        ]
    ],
    // ...
]
```

---

## 11. Тестчилгээ

Raptor нь PHPUnit 11 суурьтай unit болон integration тестүүдтэй.

### Шаардлага

```bash
composer install   # phpunit dev dependency суулгах
```

### Тест ажиллуулах

```bash
# Бүх тест
composer test

# Зөвхөн unit тест
composer test:unit

# Зөвхөн integration тест
composer test:integration
```

### Тохиргоо

`.env.testing` файл нь тест орчны тохиргоог агуулна. Integration тест нь тусдаа test database ашиглана (жишээ: `raptor12_test`).

```env
RAPTOR_DB_NAME=raptor12_test
```

### Тестийн бүтэц

```
tests/
|-- bootstrap.php              # Тест орчин тохируулах
|-- Support/
|   |-- RaptorTestCase.php     # Unit тестийн суурь анги
|   \-- IntegrationTestCase.php # Integration тестийн суурь анги
|-- Unit/
|   |-- Authentication/
|   |   \-- UserTest.php       # User::is(), User::can() тест
|   |-- Controller/
|   |   \-- ControllerTextTest.php  # Controller::text() тест
|   \-- Migration/
|       \-- MigrationRunnerTest.php  # Migration parser/status тест
\-- Integration/
    |-- Model/
    |   |-- UsersModelTest.php          # Хэрэглэгчийн CRUD тест
    |   |-- OrganizationModelTest.php   # Байгууллагын тест
    |   \-- SignupModelTest.php         # Бүртгэлийн тест
    |-- RBAC/
    |   \-- RolesPermissionsTest.php    # RBAC seed шалгалт
    |-- Authentication/
    |   \-- JWTAuthTest.php             # JWT encode/decode тест
    \-- Migration/
        \-- MigrationRunnerIntegrationTest.php  # Migration engine тест
```

### Тестийн онцлогууд

- **Transaction isolation** - Integration тест бүр transaction дотор ажиллаж, дуусахад rollback хийнэ. Тест дата бодит database-д нөлөөлөхгүй
- **RaptorTestCase** - Mock request, mock user үүсгэх helper-ууд (`createAdmin()`, `createCoder()`, `createGuest()`)
- **IntegrationTestCase** - Static PDO холболт (тест анги дотор дахин холбогдохгүй), auto database create

### Шинэ тест бичих жишээ

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

## 12. Хэрэглээний жишээ

### Шинэ Router нэмэх

1. Router класс үүсгэх:

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

2. `composer.json` дотор namespace бүртгэх:

```json
{
    "autoload": {
        "psr-4": {
            "Dashboard\\MyModule\\": "application/dashboard/mymodule/"
        }
    }
}
```

Дараа нь autoloader-г шинэчлэх:

```bash
composer dump-autoload
```

3. Application дотор Router бүртгэх:

```php
// application/dashboard/Application.php
class Application extends \Raptor\Application
{
    public function __construct()
    {
        parent::__construct();
        $this->use(new Home\HomeRouter());
        $this->use(new MyModule\MyModuleRouter());  // Шинэ router
    }
}
```

### Web хуудас нэмэх

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

### Database сонгох

`Application.php` дотор database middleware-г солих:

```php
// MySQL (default)
$this->use(new \Raptor\MySQLConnectMiddleware());

// PostgreSQL руу шилжих
$this->use(new \Raptor\PostgresConnectMiddleware());
```

---

## Дараагийн алхмууд

- [API тайлбар](api.md) - Бүх класс, методуудын дэлгэрэнгүй API reference
- [Хэлэлцүүлэг](https://github.com/orgs/codesaur-php/discussions) - Асуулт асуух, санал хуваалцах, тусламж авах
- [codesaur ecosystem](https://github.com/codesaur-php) - Бусад packages
