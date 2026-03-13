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
6. [Модулиуд](#6-модулиуд) (6.1-6.13 Суурь | 6.14-6.19 Шинэ: Дэлгүүр, Мэдэгдэл, Хөгжүүлэлт, SEO, Спам хамгаалалт, Migration)
7. [Twig Template систем](#7-twig-template-систем)
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
- **Дэлгүүр** модуль (Бүтээгдэхүүн, Захиалга)
- MySQL, PostgreSQL алийг нь ч дэмжинэ
- SQL файл суурьтай **өгөгдлийн сангийн migration** систем
- **Twig** template engine
- **OpenAI** интеграци (moedit editor)
- Зураг optimize хийх (GD)
- PSR-3 лог систем
- **Brevo** API и-мэйл илгээх
- **Discord** webhook мэдэгдэл
- SEO: Хайлт, Sitemap, XML Sitemap, RSS feed
- Спам хамгаалалт (honeypot, HMAC token, rate limiting)

### codesaur экосистем

Raptor нь дараах codesaur packages-тэй хамтран ажиллана:

| Package | Зориулалт |
|---------|-----------|
| `codesaur/http-application` | PSR-15 Application, Router, Middleware суурь |
| `codesaur/dataobject` | PDO суурьтай ORM (Model, LocalizedModel) |
| `codesaur/template` | Twig template engine wrapper |
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
#RAPTOR_MAIL_BREVO_APIKEY=""
#RAPTOR_MAIL_REPLY_TO=
```

- Brevo (SendInBlue) API ашиглан и-мэйл илгээнэ

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
| `cpanel.deploy.yml` | GitHub Actions cPanel FTP deploy workflow |

### cPanel руу deploy хийх

`deploy.yml` файл нь GitHub Actions workflow бөгөөд `main` branch руу push хийхэд cPanel сервер рүү FTP-ээр автоматаар deploy хийнэ.

#### Тохируулах

1. Workflow файлыг хуулах:

```bash
mkdir -p .github/workflows
cp docs/conf.example/cpanel.deploy.yml .github/workflows/deploy.yml
```

2. GitHub репозиторийн **Settings -> Secrets and variables -> Actions** хэсэгт дараах secret-үүдийг нэмнэ:

| Secret | Тайлбар | Жишээ |
|--------|---------|-------|
| `FTP_HOST` | cPanel серверийн FTP хаяг | `ftp.example.com` |
| `FTP_USERNAME` | cPanel FTP хэрэглэгчийн нэр | `user@example.com` |
| `FTP_PASSWORD` | cPanel FTP нууц үг | |
| `FTP_SERVER_DIR` | Серверийн зорьсон хавтас | `/public_html/` |

3. `main` branch руу push хийхэд deploy автоматаар ажиллана.

#### Анхаарах зүйлс

- **`.env`** - Серверт гараар үүсгэж тохируулна (deploy хийгдэхгүй)
- **`logs/`** - Аппликейшн автоматаар үүсгэнэ, deploy хийх шаардлагагүй
- **`private/`** - Нууцлалтай файлууд (upload), deploy хийгдэхгүй
- **`docs/`** - Зөвхөн баримтжуулалт, deploy хийгдэхгүй
- **`vendor/`** - Workflow дотор `composer install --no-dev` ажиллуулж build хийнэ

---

## 4. Архитектур

### Хоёр давхаргат бүтэц

```
public_html/index.php (Entry point)
|
|-- /dashboard/* -> Dashboard\Application (Админ панель)
|    |-- Middleware: ErrorHandler -> MySQL -> Session -> JWT -> Container -> Localization -> Settings
|    |-- Routers: Login, Users, Organization, RBAC, Localization, Contents, Logs, Template, Shop, Development, Migration
|    \-- Controllers -> Twig Templates -> HTML Response
|
\-- /* -> Web\Application (Нийтийн вэб сайт)
     |-- Middleware: ExceptionHandler -> MySQL -> Container -> Session -> Localization -> Settings
     |-- Router: HomeRouter (/, /page, /news, /contact, /products, /order, /search, /sitemap, /rss, ...)
     \-- Controllers -> Twig Templates -> HTML Response
```

### Request-ийн дамжих урсгал

```
Browser -> index.php -> .env -> ServerRequest
  -> Application сонгох (URL path-аар)
    -> Middleware chain (дарааллаар)
      -> Router match
        -> Controller::action()
          -> Model (DB)
          -> TwigTemplate -> render()
            -> HTML Response -> Browser
```

### Директорийн бүтэц

```
raptor/
|-- application/
|   |-- raptor/                    # Суурь framework (Dashboard + shared)
|   |   |-- Application.php        # Dashboard Application суурь
|   |   |-- Controller.php         # Бүх Controller-ийн суурь анги
|   |   |-- MySQLConnectMiddleware.php
|   |   |-- PostgresConnectMiddleware.php
|   |   |-- ContainerMiddleware.php
|   |   |-- authentication/        # Login, JWT, Session
|   |   |-- content/               # CMS модулиуд
|   |   |   |-- file/              # Файлын менежмент
|   |   |   |-- news/              # Мэдээ
|   |   |   |-- page/              # Хуудас
|   |   |   |-- reference/         # Лавлагаа
|   |   |   \-- settings/          # Системийн тохиргоо
|   |   |-- localization/          # Хэл, орчуулга
|   |   |-- organization/          # Байгууллага
|   |   |-- rbac/                  # Эрхийн удирдлага
|   |   |-- user/                  # Хэрэглэгч
|   |   |-- template/              # Dashboard UI template
|   |   |-- log/                   # PSR-3 лог
|   |   |-- mail/                  # И-мэйл
|   |   |-- notification/          # Discord webhook мэдэгдэл
|   |   |-- migration/             # Өгөгдлийн сангийн migration систем
|   |   |-- development/           # Хөгжүүлэлтийн хүсэлт хянах
|   |   \-- exception/             # Алдаа барих
|   |-- dashboard/                 # Dashboard Application
|   |   |-- Application.php
|   |   |-- home/                  # Dashboard Home Router
|   |   \-- shop/                  # Дэлгүүр модуль (Бүтээгдэхүүн, Захиалга)
|   \-- web/                       # Web Application
|       |-- Application.php
|       |-- SessionMiddleware.php
|       |-- LocalizationMiddleware.php
|       |-- home/                  # Public page controllers + templates
|       |   |-- HomeRouter.php
|       |   |-- HomeController.php
|       |   |-- PageController.php
|       |   |-- NewsController.php
|       |   |-- ShopController.php
|       |   |-- SeoController.php
|       |   |-- home.html, page.html, news.html
|       |   |-- products.html, product.html
|       |   |-- order.html, order-success.html
|       |   |-- search.html, sitemap.html
|       |   \-- archive.html, news-type.html
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
|   |   |-- .nginx.conf.example    # Nginx серверийн тохиргоо
|   |   \-- cpanel.deploy.yml      # GitHub Actions cPanel FTP deploy
|   |-- en/                        # Англи баримтжуулалт
|   \-- mn/                        # Монгол баримтжуулалт
|-- tests/                         # PHPUnit тестүүд (unit, integration)
|-- database/
|   \-- migrations/              # SQL migration файлууд
|-- logs/                          # Алдааны лог файлууд
|-- private/                       # Хамгаалагдсан файлууд
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
| 2.5 | `MigrationMiddleware` | Pending SQL migration-г автоматаар ажиллуулна |
| 3 | `SessionMiddleware` | PHP session эхлүүлж удирдна |
| 4 | `JWTAuthMiddleware` | JWT шалгаж `User` объект үүсгэнэ |
| 5 | `ContainerMiddleware` | DI Container-г inject хийнэ |
| 6 | `LocalizationMiddleware` | Хэл, орчуулгыг тодорхойлно |
| 7 | `SettingsMiddleware` | Системийн тохиргоог inject хийнэ |

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

- Хэрэглэгчийн CRUD (Create, Read, Update, Deactivate)
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

- Мэдээний CRUD
- Нүүр зураг upload
- Хавсралт файлууд
- Нийтлэх огноо удирдах
- Үзэлтийн тоо (read_count)
- moedit editor ашиглан контент засварлах
- Жишиг дата цэвэрлэх: seed дата устгаж ID=1-ээс эхлүүлэх (`reset()` метод)

### 6.7 Content - Pages (Хуудас)

**Классууд:** `PagesController`, `PagesModel`

- Хуудасны CRUD, хялбаршуулсан нэг формтой интерфэйс (type wizard хасагдсан)
- Parent-child бүтэц (олон түвшний навигацийн меню)
- `position` талбараар эрэмбэлэх
- `type` талбар: `content` (анхдагч), `nav` (эцэг/навигац хуудас - "Эцэг хуудас" switch ашиглан үүсгэнэ)
- Эцэг хуудас (хүүхэдтэй хуудас) засах үед контент талбарууд (description, content, link, featured, comment) нуугдана
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
- Twig template дотор `{{ 'key'|text }}` ашиглах

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

- Brevo (SendInBlue) API ашиглан и-мэйл илгээх
- Template-based и-мэйл илгээх

### 6.13 Template (Dashboard UI)

**Классууд:** `TemplateRouter`, `TemplateController`

- Dashboard-ийн layout (sidebar, header, content area)
- SweetAlert2, motable, moedit зэрэг JS компонентууд
- Responsive Bootstrap 5 дизайн

### 6.14 Shop (Дэлгүүр)

**Классууд:** `ProductsController`, `ProductsRouter`, `ProductsModel`, `OrdersController`, `OrdersRouter`, `ProductOrdersModel`

- Бүтээгдэхүүний CRUD, slug үүсгэх, хураангуй гаргах
- Бүтээгдэхүүний талбарууд: үнэ, хямдралын үнэ, SKU, barcode, хэмжээ, өнгө, нөөц, ангилал, онцлох
- Захиалгын удирдлага (`products_orders` хүснэгт) - хэрэглэгчийн мэдээлэл, статус хянах
- Жишиг дата анхны ачааллаар автоматаар үүсгэгдэнэ
- Шинэ захиалга, статус өөрчлөлтийн Discord мэдэгдэл

### 6.15 Notification (Мэдэгдэл)

**Классууд:** `DiscordNotifier`

- Discord webhook интеграци
- Мэдэгдлийн төрлүүд: хэрэглэгч бүртгүүлсэн, хэрэглэгч зөвшөөрсөн, шинэ захиалга, захиалгын статус өөрчлөлт, контентийн үйлдлүүд гэх мэт
- Өнгөт embed мессеж
- `RAPTOR_DISCORD_WEBHOOK_URL` орчны хувьсагчаар тохируулна
- Webhook URL тохируулаагүй бол чимээгүй алгасна

### 6.16 Development (Хөгжүүлэлтийн хэрэгсэл)

**Классууд:** `DevelopmentRouter`, `DevRequestController`, `DevRequestModel`, `DevResponseModel`

- Хөгжүүлэлтийн хүсэлт хянах систем (хүсэлт илгээх, хариулах, түүх харах)
- `development:development` RBAC эрхээр хамгаалагдсан

### 6.17 SEO & Контент олох (Web)

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

### 6.18 Спам хамгаалалт

- Honeypot нууц талбарын илрүүлэлт
- HMAC токен цаг хугацааны хамт шалгах
- Үйлдэл тус бүрийн хурд хязгаарлалт (login 2s, signup 5s, forgot 10s)
- Формын хугацаа дуусах шалгалт (1 цагийн дотор)
- Бөглөх хурдны доод хязгаар (1 секунд)
- Нэвтрэх, бүртгүүлэх, нууц үг сэргээх, захиалгын формуудад ашиглагдана

### 6.19 Database Migration (Өгөгдлийн сангийн шилжүүлэг)

**Классууд:** `MigrationRunner`, `MigrationMiddleware`, `MigrationController`, `MigrationRouter`

- SQL файл дээр суурилсан, зөвхөн урагшлах (forward-only) migration систем
- Migration файлууд `database/migrations/` хавтаст хадгалагдана
- Хүлээгдэж буй = `migrations/` дотор, Ажилласан = `migrations/ran/` руу зөөгдсөн
- `MigrationMiddleware` хүсэлт бүрт pending migration-г автоматаар ажиллуулна
- Advisory lock (`GET_LOCK`) зэрэгцээ ажиллахаас хамгаална
- Dashboard UI-аар migration төлөв, SQL файлын агуулга харах
- Зөвхөн `system_coder` эрхтэй хэрэглэгчид dashboard руу хандах боломжтой
- `.htaccess` хамгаалалт SQL файлуудад шууд хандахыг хаана

---

## 7. Twig Template систем

Raptor нь `codesaur/template` package-ийн `TwigTemplate` классыг ашиглана.

### Суурь хувьсагчид

Controller дотроос `twigTemplate()` дуудахад доорх хувьсагчид автоматаар нэмэгднэ:

| Хувьсагч | Тайлбар |
|----------|---------|
| `user` | Нэвтэрсэн хэрэглэгчийн `User` объект (null байж болно) |
| `index` | Script path (subdirectory дэмжлэг) |
| `localization` | Хэл, орчуулгын мэдээлэл |
| `request` | Одоогийн URL path |

### Twig filter-ууд

| Filter | Хэрэглээ | Тайлбар |
|--------|----------|---------|
| `text` | `{{ 'key'\|text }}` | Орчуулгын текст авах |
| `link` | `{{ 'news'\|link({'id': 5}) }}` | 5-р мэдээний URL үүсгэх |
| `basename` | `{{ path\|basename }}` | Файлын нэр гаргах (Web templates) |

### Жишээ

```twig
{# Орчуулга #}
<h1>{{ 'welcome'|text }}</h1>

{# Route link #}
<a href="{{ 'page'|link({'id': page.id}) }}">{{ page.title }}</a>

{# Хэрэглэгч шалгах #}
{% if user is not null %}
    <p>Сайн байна уу, {{ user.profile.first_name }}!</p>
{% endif %}

{# Хэл солих #}
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

        // PUT маршрут
        $this->PUT('/path/{uint:id}', [Controller::class, 'method'])->name('route-name');

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
- Twig template дотор `{{ 'route-name'|link }}` хэлбэрээр
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
| `twigTemplate($file, $vars)` | Twig template объект |
| `respondJSON($data, $code)` | JSON хариулт |
| `redirectTo($route, $params)` | Redirect хийх |
| `log($table, $level, $msg)` | Лог бичих |
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
        $products = $model->getRows(['WHERE' => 'is_active=1']);

        // Template рендерлэх
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

        // Лог бичих
        $this->log('products', \Psr\Log\LogLevel::INFO, 'Бүтээгдэхүүн нэмлээ', [
            'product_id' => $id
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
| `deleteById($id)` | ID-р устгах |
| `deactivateById($id, $record)` | ID-р бичлэгийг идэвхгүй болгох (soft delete) |
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

2. `composer.json` дотор namespace бүртгэх:

```json
{
    "autoload": {
        "psr-4": {
            "Dashboard\\Products\\": "application/dashboard/products/"
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
        $this->use(new Products\ProductsRouter());  // Шинэ router
    }
}
```

### Web хуудас нэмэх

```php
// application/web/home/HomeRouter.php
$this->GET('/products', [HomeController::class, 'products'])->name('products');
```

```php
// application/web/home/HomeController.php
public function products()
{
    $model = new ProductsModel($this->pdo);
    $products = $model->getRows(['WHERE' => 'is_active=1']);
    $this->template(__DIR__ . '/products.html', ['products' => $products])->render();
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
