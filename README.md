# 🦖 codesaur/raptor

[![PHP Version](https://img.shields.io/badge/php-%5E8.2.1-777BB4.svg?logo=php)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Цэвэр архитектуртай объект хандалттай веб хөгжүүлэлтийн фреймворк  
Clean architecture object-oriented web development framework

---

## Агуулга / Table of Contents

1. [Монгол](#1-монгол-тайлбар) | 2. [English](#2-english-description) | 3. [Getting Started](#3-getting-started)

---

## 1. Монгол тайлбар

`codesaur/raptor` нь PSR стандартууд (PSR-3, PSR-7, PSR-15) дээр суурилсан, **олон давхаргат архитектуртай**, **бүрэн CMS боломжтой** PHP веб фреймворк юм.

Фреймворк нь **Web** (нийтийн вебсайт) болон **Dashboard** (админ панель) гэсэн хоёр давхаргад хуваагдан ажилладаг бөгөөд codesaur экосистемийн бусад packages-тэй хамтран ажиллана.

### Гол боломжууд

- PSR-7/PSR-15 middleware суурьтай архитектур
- JWT + Session нэвтрэлт баталгаажуулалт
- RBAC (Role-Based Access Control) эрхийн удирдлага
- Олон хэл дэмжлэг (Localization)
- CMS модулиуд: Мэдээ, Хуудас, Файл, Лавлах, Тохиргоо
- Дэлгүүр модуль: Бүтээгдэхүүн, Захиалга (e-commerce)
- MySQL, PostgreSQL алийг нь ч дэмжинэ
- SQL файл суурьтай өгөгдлийн сангийн migration систем
- Twig template engine
- OpenAI интеграци (moedit editor)
- Зураг optimize хийх (GD)
- PSR-3 лог систем
- Brevo API и-мэйл, Discord webhook мэдэгдэл
- SEO: Хайлт, Sitemap, XML Sitemap, RSS feed
- Спам хамгаалалт (honeypot, HMAC token, rate limiting, Cloudflare Turnstile)

### Дэлгэрэнгүй мэдээлэл

- [Бүрэн танилцуулга](docs/mn/README.md) - Суулгах, тохируулах, архитектур, хэрэглээ
- [API тайлбар](docs/mn/api.md) - Бүх модуль, класс, методуудын дэлгэрэнгүй

---

## 2. English Description

`codesaur/raptor` is a **multi-layered**, **full-featured CMS** PHP web framework built on PSR standards (PSR-3, PSR-7, PSR-15).

The framework operates in two layers - **Web** (public website) and **Dashboard** (admin panel) - and works together with other packages in the codesaur ecosystem.

### Key Features

- PSR-7/PSR-15 middleware-based architecture
- JWT + Session authentication
- RBAC (Role-Based Access Control)
- Multi-language support (Localization)
- CMS modules: News, Pages, Files, References, Settings
- Shop module: Products, Orders (e-commerce)
- MySQL or PostgreSQL supported
- SQL file-based database migration system
- Twig template engine
- OpenAI integration (moedit editor)
- Image optimization (GD)
- PSR-3 logging system
- Brevo API email, Discord webhook notifications
- SEO: Search, Sitemap, XML Sitemap, RSS feed
- Spam protection (honeypot, HMAC token, rate limiting, Cloudflare Turnstile)

### Documentation

- [Full Documentation](docs/en/README.md) - Installation, configuration, architecture, usage
- [API Reference](docs/en/api.md) - All modules, classes, and methods

---

## 3. Getting Started

### Requirements

- PHP **8.2.1+**
- Composer
- MySQL or PostgreSQL
- PHP extensions: `ext-gd`, `ext-intl`

### Installation

```bash
composer create-project codesaur/raptor my-project
```

### Configuration

`composer create-project` ашигласан бол `.env` файл автоматаар үүсэх бөгөөд `RAPTOR_JWT_SECRET` мөн автоматаар generate хийгдэнэ. Хэрэв `.env` үүсээгүй бол гараар хуулна:

If you used `composer create-project`, the `.env` file is auto-created and `RAPTOR_JWT_SECRET` is auto-generated. If `.env` was not created, copy it manually:

```bash
cp docs/conf.example/.env.example .env
```

Server configuration examples / Серверийн тохиргооны жишээ: [`docs/conf.example/`](docs/conf.example/)

Гол тохиргоонууд / Key configuration:

```env
# Environment (development / production)
CODESAUR_APP_ENV=development

# Database
RAPTOR_DB_HOST=localhost
RAPTOR_DB_NAME=raptor
RAPTOR_DB_USERNAME=root
RAPTOR_DB_PASSWORD=

# JWT (secret is auto-generated)
RAPTOR_JWT_ALGORITHM=HS256
RAPTOR_JWT_LIFETIME=2592000
```

### Quick Architecture

```
public_html/index.php
 |-- /dashboard/* -> Dashboard\Application (Admin Panel)
 |    |-- Middleware stack (Session, JWT, RBAC, Localization, Settings)
 |    |-- Routers (Login, Users, Organization, RBAC, Content, Logs, Shop, Development, Migration)
 |    \-- Controllers -> Twig Templates
 |
 \-- /* -> Web\Application (Public Website)
      |-- Middleware stack (Session, Localization, Settings)
      |-- WebRouter (/, /page/{id}, /news/{id}, /contact, /language/{code})
      \-- TemplateController -> Twig Templates
```

**Request Flow:** index.php -> Application -> Middleware chain -> Router match -> Controller -> Response

### Directory Structure

```
raptor/
|-- application/
|   |-- raptor/              # Core framework (Controllers, Models, Middleware)
|   |   |-- authentication/  # Login, JWT, Session
|   |   |-- content/         # CMS (files, messages, news, pages, references, settings)
|   |   |-- localization/    # Languages & translations
|   |   |-- organization/    # Organization management
|   |   |-- rbac/            # Roles & permissions
|   |   |-- user/            # User management
|   |   |-- template/        # Dashboard UI
|   |   |-- exception/       # Exception handler
|   |   |-- log/             # Logging
|   |   |-- mail/            # Email
|   |   |-- notification/    # Discord webhook notifications
|   |   |-- development/     # Dev request tracking
|   |   \-- migration/       # Database migration system
|   |-- dashboard/           # Dashboard application
|   |   |-- home/            # Dashboard home
|   |   |-- shop/            # Shop module (Products, Orders)
|   |   \-- manual/          # Manual pages
|   \-- web/                 # Public website application
|       |-- WebRouter.php    # Web routes
|       |-- content/         # Pages, News
|       |-- shop/            # Products, Orders
|       |-- service/         # Search, Sitemap, RSS, Contact
|       \-- template/        # Web layout, exception handler
|-- public_html/             # Document root
|   |-- index.php            # Entry point
|   |-- .htaccess            # Apache URL rewrite
|   |-- robots.txt           # Search engine bot rules
|   \-- assets/              # CSS, JS (dashboard, moedit, motable etc)
|-- database/
|   \-- migrations/          # SQL migration files
|-- tests/                   # PHPUnit tests (unit, integration)
|-- docs/
|   |-- conf.example/        # Server config examples + cPanel deploy
|   |-- en/                  # English documentation
|   \-- mn/                  # Mongolian documentation
|-- .github/
|   \-- workflows/
|       \-- ci.yml           # CI code quality checks (push, PR)
|-- logs/                    # Error logs
|-- private/                 # Protected files
|-- .env.testing             # Test environment variables
|-- composer.json            # Dependencies
|-- phpunit.xml              # PHPUnit configuration
\-- LICENSE                  # MIT License
```

### Testing / Тестчилгээ

PHPUnit 11 суурьтай unit болон integration тестүүдтэй.
Includes PHPUnit 11 test suite with unit and integration tests.

```bash
composer test              # Бүх тест / All tests
composer test:unit         # Unit тест / Unit tests only
composer test:integration  # Integration тест / Integration tests only
```

Integration тест `.env.testing` файлын тохиргоог ашиглан тусдаа test database дээр ажиллана. Тест бүр transaction дотор ажиллаж, дуусахад rollback хийнэ.

Integration tests use `.env.testing` config with a separate test database. Each test runs in a transaction and rolls back on teardown.

---

## History

> **Note:** This package is the successor of `codesaur/indodaptor` (500+ installs), which has been removed from Packagist. A new package `codesaur/raptor` was created with a full code refactor, as the name "Indoraptor" is a trademark of Universal Pictures.

---

## Did You Know?

**Velociraptor** (/vɪˈlɒsɪræptər/ - Латинаар "swift seizer" буюу "хурдан баригч") нь Cretaceous галавын сүүл үе буюу ойролцоогоор 75-71 сая жилийн өмнө амьдарч байсан dromaeosaurid theropod үлэг гүрвэлийн төрөл юм. Одоогоор хоёр зүйлийг хүлээн зөвшөөрсөн бөгөөд *V. mongoliensis* энэ зүйлийн олдворуудыг **Монгол** улсаас олсон байдаг. Хоёр дахь зүйл *V. osmolskae*-г 2008 онд Өвөр Монголоос олдсон гавлын материалаар нэрлэсэн.

## Acknowledgements

Энэ фреймворкийг хөгжүүлэхэд [**Gerege Systems LLC**](https://gerege.com/) ивээн тэтгэж, компанийн үүсгэн байгуулагч **Ц.Эрдэнэбат** багш удирдан зааварлаж чиглүүлсэн билээ.

This framework was developed with the sponsorship of [**Gerege Systems LLC**](https://gerege.com/) and under the guidance of **Erdenebat Ts**, founder of Gerege Systems.

---

## Changelog

- [CHANGELOG.md](CHANGELOG.md) - Version history

## Community

- [Discussions](https://github.com/orgs/codesaur-php/discussions) - Ask questions, share ideas, get help

## Contributing & Security

- [Contributing Guide](.github/CONTRIBUTING.md)
- [Security Policy](.github/SECURITY.md)

## License

This project is licensed under the MIT License.

## Author

**Narankhuu**  
Email: codesaur@gmail.com  
Phone: [+976 99000287](https://wa.me/97699000287)  
Web: https://github.com/codesaur

**codesaur ecosystem:** https://codesaur.net
