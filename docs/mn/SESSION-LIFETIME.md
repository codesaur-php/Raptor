# Session-ийн амьдрах хугацаа (gc_maxlifetime) тохируулах заавар

## Танилцуулга

Raptor нь нэвтэрсэн админы session-ийг удаан хадгалахын тулд `SessionMiddleware` дотроос session **cookie-ийн амьдрах хугацааг 30 хоног** болгож тохируулдаг (`session_set_cookie_params(2592000)`). Ингэснээр админ browser-ээ хаагаад дахин нээсэн ч нэвтэрсэн хэвээр үлдэж, session дотор хадгалагдсан JWT болон CSRF token эрт алдагдахгүй. Энэ энгийн арга нь олон жилийн турш Монголын олон cPanel хост / VPS серверүүд дээр нэмэлт тохиргоогүйгээр найдвартай ажиллаж ирсэн.

Энэ нь зөвхөн **client талын cookie**-д л үйлчилнэ. Raptor нь session-ийн **server талын** удирдлагыг - файлын цэвэрлэгээ (`session.gc_maxlifetime`), хадгалах зам (`session.save_path`) - кодоос **тулгадаггүй**, харин host-ийн PHP тохиргоонд (php.ini) даатгадаг. Учир нь server талын session хугацааг кодоос албадах нь зарим орчинд найдваргүй:

- **Runtime `ini_set()` нь системийн цэвэрлэгчид хүрдэггүй.** Debian/Ubuntu дээр PHP-ийн дотоод GC унтраалттай байж, session файлыг `/etc/cron.d/php` дахь `sessionclean` cron цэвэрлэдэг. Энэ cron нь **php.ini**-ийн утгыг уншдаг тул кодын `ini_set(gc_maxlifetime, ...)`-ийг бүрэн үл хэрэгсэнэ.
- **Хуваалцсан `/tmp` дахь зөрчил.** Олон сайт нэг session хавтас хуваалцах үед, өөр түрээслэгчийн GC (богино `gc_maxlifetime`-тай) танай файлыг ч устгана.
- **Системийн cron-ы purge.** cPanel/LiteSpeed, macOS зэрэг нь /tmp-г PHP-ийн тохиргооноос үл хамааран бие даан цэвэрлэдэг.

Тиймээс session-ийн server талын бодлого (хугацаа, хадгалах зам, цэвэрлэгээ) нь тухайн серверт тохирч **developer-ийн мэдэлд** үлддэг. Хүсвэл developer session-ийн удирдлагыг **бүхэлд нь** PHP тохиргоонд шилжүүлж болно: `SessionMiddleware` доторх `session_set_cookie_params(2592000)` мөрийг **устгаад**, бүх session-ийн хугацаа/зам/цэвэрлэгээг php.ini / .user.ini-д тохируулна (доорх заавраас үз).

---

## Raptor одоо юу хийдэг вэ (default зан төлөв)

`SessionMiddleware` нь:

- **Session cookie-ийн амьдрах хугацааг 30 хоног болгоно** - `session_set_cookie_params(2592000)`. Энэ нь ЗӨВХӨН client талын cookie бөгөөд олон cPanel/VPS host дээр нэмэлт тохиргоогүйгээр шууд ажиллах practical default юм (админ browser хаасан ч нэвтэрсэн хэвээр).
- `session.save_path`, `session.gc_maxlifetime`, `session.gc_probability`-д **кодоос гар хүрэхгүй** - эдгээр (ялангуяа server талын файлын цэвэрлэгээ) нь host php.ini-аар тодорхойлогдоно.
- `session_name('raptor')` + `session_start()`, мөн session-д бичдэггүй route дээр `session_write_close()`-оор эрт хаах (concurrency) оновчлол.

> **PHP тохиргоонд бүрэн даатгах:** session-ийн хугацааг бүхэлд нь host php.ini-д удирдахыг хүсвэл `SessionMiddleware` доторх `session_set_cookie_params(2592000)` мөрийг **устгаад** php.ini-д тохируул (доорх заавар).

---

## Developer хэрхэн тохируулах вэ

Урт session (жишээ: 30 хоног = `2592000` секунд) хүсвэл **гурван утгыг хамтад нь** тохируул:

| Тохиргоо | Үүрэг |
|----------|-------|
| `session.save_path` | session файлыг **хувийн хавтаст** хадгалах (host cron / бусад түрээслэгч хүрэхгүй) |
| `session.gc_maxlifetime` | server тал - файл хэдэн секунд амьдрах |
| `session.cookie_lifetime` | client тал - browser cookie хэдэн секунд хадгалагдах |

Нэвтрэлтийн UX тогтвортой байлгахын тулд эдгээрийг **`RAPTOR_JWT_LIFETIME`-тэй ойролцоо** тавихыг зөвлөнө.

### Бүх орчинд нийтлэг 2 дүрэм

1. **php.ini / .user.ini түвшинд** тохируул - runtime `ini_set()`-д бүү найд (системийн cron түүнийг уншдаггүй).
2. **Web SAPI-д** (apache2 / php-fpm) тохируул, зөвхөн CLI биш. Өөрчилсний дараа web server / FPM-ийг restart хий.

---

### 1. Ubuntu / Debian VPS

PHP-ийн SAPI-д тохирох php.ini-г засна:

- Apache mod_php: `/etc/php/8.x/apache2/php.ini`
- Nginx / PHP-FPM: `/etc/php/8.x/fpm/php.ini`

```ini
session.gc_maxlifetime = 2592000
session.cookie_lifetime = 2592000
```

```bash
sudo systemctl restart apache2     # эсвэл: sudo systemctl restart php8.x-fpm
```

**Онцлог:** Debian/Ubuntu дээр PHP-ийн дотоод GC унтраалттай (`session.gc_probability = 0`); оронд нь `/etc/cron.d/php` дахь `sessionclean` скрипт 30 минут тутам ажиллаж, **php.ini-ээс уншсан** `gc_maxlifetime` болон `save_path`-аар цэвэрлэдэг. Тиймээс дээрх php.ini засвар нь cron-д хүндэтгэгдэж 30 хоног **үнэхээр ажиллана**.

- Хэрэв **custom `save_path`** ашиглавал - түүнийгээ **php.ini-д** заа (`.htaccess` / runtime-д биш), эс бөгөөс `sessionclean` тэр хавтсыг олж цэвэрлэхгүй. Эсвэл өөрийн GC-г асаа (`session.gc_probability = 1`) / тусдаа cron нэм.

Debian-ы default save_path: `/var/lib/php/sessions` (/tmp биш).

---

### 2. cPanel shared hosting (root эрхгүй)

php.ini-д шууд хандах эрхгүй тул:

- **cPanel -> "MultiPHP INI Editor"** -> домэйнээ сонгоод `session.gc_maxlifetime`, `session.cookie_lifetime`-г тавь, **эсвэл**
- `public_html/.user.ini` файл (cPanel ихэвчлэн PHP-FPM / CGI ашигладаг):

```ini
session.save_path = "/home/USERNAME/sessions"
session.gc_maxlifetime = 2592000
session.cookie_lifetime = 2592000
session.gc_probability = 1
session.gc_divisor = 100
```

**Онцлог:** cPanel / CloudLinux нь /tmp-г идэвхтэй цэвэрлэдэг тул **хувийн `save_path`** (`/home/USERNAME/sessions`) заах нь 30 хоног ажиллах түлхүүр. Тэр хавтсыг урьдчилан үүсгэж, бичих эрхтэй болго. `.user.ini` нь ~300 секунд кэшлэгддэг тул өөрчлөлт шууд биш.

---

### 3. Windows + XAMPP / WAMP

php.ini-г засна:

- XAMPP: `C:\xampp\php\php.ini`
- WAMP: tray цэс -> PHP -> `php.ini`

```ini
session.save_path = "C:\xampp\tmp"
session.gc_maxlifetime = 2592000
session.cookie_lifetime = 2592000
session.gc_probability = 1
session.gc_divisor = 100
```

Дараа нь Apache-г restart (XAMPP / WAMP Control Panel).

**Онцлог:** Windows-д session цэвэрлэх **системийн cron байхгүй** - зөвхөн PHP-ийн дотоод GC цэвэрлэгч. XAMPP default нь `gc_divisor = 1000` (0.1%) тул GC ховор ажилладаг ба файл хуримтлагдаж болзошгүй. Шийдэл:

- `session.gc_probability = 1`, `session.gc_divisor = 100` (1%) болгох, **эсвэл**
- Windows Task Scheduler-ээр хуучин файл устгах:

```
forfiles /p "C:\xampp\tmp" /m sess_* /d -30 /c "cmd /c del @path"
```

---

### 4. macOS (Homebrew PHP / MAMP)

macOS 12+ дээр built-in PHP-г хассан тул ихэвчлэн Homebrew эсвэл MAMP ашиглана.

**Homebrew PHP** (`brew install php`):

- php.ini-г олох: `php --ini` -> "Loaded Configuration File" мөрийг үз. Ихэвчлэн:
  - Apple Silicon (M1/M2/M3): `/opt/homebrew/etc/php/8.x/php.ini`
  - Intel: `/usr/local/etc/php/8.x/php.ini`

```ini
session.save_path = "/Users/USERNAME/.php-sessions"
session.gc_maxlifetime = 2592000
session.cookie_lifetime = 2592000
session.gc_probability = 1
session.gc_divisor = 100
```

```bash
brew services restart php
```

**MAMP:** `/Applications/MAMP/bin/php/php8.x.x/conf/php.ini`-г засаад MAMP цэснээс server-үүдийг restart.

**Онцлог:** macOS-д Debian шиг `sessionclean` cron **байхгүй**. /tmp болон `/var/folders/...`-г launchd-ийн periodic task үе үе (ихэвчлэн 3 хоногоос хуучин файл) цэвэрлэдэг тул /tmp дахь session урт хадгалагдахгүй. Тиймээс **хувийн `save_path`** заа, мөн `session.gc_probability = 1` тавь эсвэл launchd/cron-оор цэвэрлэ:

```bash
find /Users/USERNAME/.php-sessions -name 'sess_*' -mtime +30 -delete
```

---

### 5. Бусад орчин

| Орчин | Хаана тохируулах | Цэвэрлэгч |
|-------|------------------|-----------|
| Nginx + PHP-FPM (ямар ч OS) | FPM-ийн `php.ini`, эсвэл pool `.conf`-д `php_value[session.gc_maxlifetime] = 2592000` | Debian cron эсвэл PHP GC |
| Docker (`php:fpm` image) | `/usr/local/etc/php/conf.d/session.ini` (mount/COPY) | PHP GC (`gc_probability` асаа) |
| Apache mod_php (ямар ч OS) | `php.ini`, эсвэл `.htaccess`-д `php_value session.gc_maxlifetime 2592000` | PHP GC / системийн cron |

> `.htaccess` дахь `php_value` нь зөвхөн **mod_php** дээр ажиллана. PHP-FPM дээр 500 алдаа өгдөг тул FPM дээр `.user.ini` ашигла.

---

## Developer юу ч тохируулаагүй бол

### Session хэр хугацаатай ажиллах вэ

PHP-ийн анхдагч утгуудаар:

- **`session.gc_maxlifetime`** (server тал) - анхдагч ихэвчлэн **1440 секунд (24 минут)**. Session файл сүүлд хандсанаас хойш энэ хугацаа өнгөрвөл GC/cron түүнийг устгах боломжтой болно. Өөрөөр хэлбэл админ **~24 минут идэвхгүй** байвал session устаж магадгүй (хост бүрд харилцан адилгүй - зарим хост үүнийг өндөр тавьсан байдаг).
- **`session.cookie_lifetime`** (client тал) - PHP-ийн анхдагч нь **0** (browser хаах хүртэл) боловч **Raptor үүнийг кодоос 30 хоног болгодог** (`SessionMiddleware`). Тиймээс default-аар cookie 30 хоног хадгалагдана; `session_set_cookie_params(2592000)` мөрийг устгасан үед л **0** (browser хаах хүртэл) болно.

### Raptor-д хэрхэн нөлөөлөх вэ

Raptor-ийн dashboard нэвтрэлт нь **JWT-г session дотор** (`$_SESSION['RAPTOR_JWT']`), CSRF token-г мөн session дотор (`$_SESSION['CSRF_TOKEN']`) хадгалдаг. Тиймээс:

- Session GC-д устах эсвэл cookie дуусахад **JWT, CSRF token хоюулаа алга болно**.
- Дараагийн хүсэлт дээр `JWTAuthMiddleware` нэвтрэлтгүй гэж үзэж **login руу шилжүүлнэ** (админ дахин нэвтрэх шаардлагатай болно). Энэ нь алдаа биш - өгөгдөл алдагдахгүй, зүгээр л дахин нэвтрэлт.
- CSRF token нь дараагийн нэвтрэлт дээр (эсвэл `Controller::template()`-ийн fallback-аар) дахин үүснэ.

**Чухал:** `RAPTOR_JWT_LIFETIME` нь 30 хоног (default) байсан ч session нь түүнээс эрт уствал нэвтрэлт session-ы хугацаагаар хязгаарлагдана. Өөрөөр хэлбэл бодит "нэвтэрсэн хэвээр байх" хугацаа = **min(session-ы хугацаа, JWT-ийн хугацаа)**. Хост дээр default `gc_maxlifetime` богино бол JWT 30 хоног хүчинтэй атал админ ~24 минутын дараа гарч магадгүй.

**Дүгнэлт:** Raptor default-аар session cookie-г 30 хоног болгодог тул админ browser-ээ хаагаад дахин нээсэн ч нэвтэрсэн хэвээр үлддэг - энэ нь энгийн бөгөөд практикт найдвартай. Гэхдээ **server талын** тохиргоо хийхгүй бол нэвтрэлт богино настай байж болзошгүй: хост дээр `gc_maxlifetime` богино бол админ удаан идэвхгүй байх үед session файл цэвэрлэгдэж, 30 хоног cookie хүчинтэй атал дахин нэвтрэх шаардлага гарч магадгүй (бодит хугацаа = **min(session файл, JWT, cookie)**). Session-ийн удирдлагыг бүрэн найдвартай, урт хугацаатай болгохыг хүсвэл дээрх server талын тохиргоог хийж болно.

---

## Тохиргоо ажилласан эсэхийг шалгах

**`phpinfo()`-оор** (анхаар: phpinfo() нь `.env` доторх нууцыг ил гаргадаг тул шалгаад заавал устга): түр `phpinfo.php` файл үүсгэ:

```php
<?php phpinfo();
```

Browser-аар нээж `session.gc_maxlifetime`-ийн **"Local Value"** = `2592000` эсэхийг шалга (CLI-ийн `php -i` биш - web SAPI-ийн утга чухал). Шалгаж дуусаад `phpinfo.php`-г **устгаарай**.

Идэвхтэй php.ini-г олох: `php --ini`.

---

## Гол зарчмууд (давтан)

1. **Raptor default-аар session cookie-ийн хугацааг 30 хоног болгодог** - нэвтэрсэн админы session-ийг удаан хадгалахын тулд `SessionMiddleware` дотроос `session_set_cookie_params(2592000)`-аар тохируулдаг. Энэ нь зөвхөн client талын cookie бөгөөд админ browser-ээ хаагаад дахин нээсэн ч нэвтэрсэн хэвээр үлдэнэ.
2. **php.ini / .user.ini түвшинд** тохируул - runtime `ini_set()` нь системийн cron-д хүрдэггүй тул найдваргүй.
3. **Хувийн `save_path` + `gc_maxlifetime` + `cookie_lifetime` гурвыг хамтад нь** - host purge-аас сэргийлж урт хугацаа үнэхээр ажиллана. Зөвхөн `gc_maxlifetime`-г хуваалцсан /tmp-д тавих нь найдваргүй.
4. **Web SAPI-д** тохируулж, web server / FPM-ийг restart хий.
5. Нэвтрэлтийн UX тогтвортой байлгахын тулд session-ы хугацааг **`RAPTOR_JWT_LIFETIME`-тэй ойролцоо** тавь.
6. Тохиргоо хийхгүй бол server талын session файл PHP-ийн default `gc_maxlifetime`-аар (ихэвчлэн богино) цэвэрлэгдэж болзошгүй ба админ дахин нэвтэрнэ - энэ нь хэвийн, аюулгүй зан төлөв. Client cookie нь дээрх #1-ийн дагуу 30 хоног хэвээр; **харин хэрэв developer `SessionMiddleware` дээрээс `session_set_cookie_params(2592000)` мөрийг устгасан бол** cookie ч PHP-ийн default буюу `0` (browser хаах хүртэл) болно.
