# Серверийн PHP аюулгүй байдлын мэдэгдэл ба түр хамгаалалт

## Үндэслэл

Raptor олон өөр орчинд ажиллах боломжтой - VPS, shared hosting (cPanel, Plesk, DirectAdmin, ...) болон localhost (XAMPP, WAMP, MAMP, Homebrew, Docker). PHP-д ноцтой эмзэг байдал зарлагдахад сайт хариуцагч хоёр зүйлийг мэдэх хэрэгтэй:

1. **Вэб сайтад үйлчилж буй PHP засвартай юу?** Shared hosting дээр бүтэн хувилбар (`8.3.xx`) ихэвчлэн хаана ч харагддаггүй, VPS / localhost дээр CLI-ийн `php -v` нь вэб серверийн ашигладаг PHP-ээс өөр байж болно.
2. **PHP-г шууд шинэчлэх боломжгүй бол түр хамгаалалт үнэхээр үйлчилж байна уу?** Буруу файлд хийсэн (эсвэл restart хийгээгүй) ini өөрчлөлт чимээгүйгээр ямар ч нөлөөгүй үлддэг.

Dashboard-ийн нүүр хуудас энэ хоёрт бодит вэб хүсэлтийн дотроос хариулна.

---

## Raptor юу харуулдаг вэ

`Dashboard\Home\PhpSecurityStatus` (`application/dashboard/home/PhpSecurityStatus.php`)-г `HomeController::index()` **зөвхөн `system_coder`-т** дуудна (`isUser('system_coder')`; бусад хэрэглэгч, мөн Act As горимд байгаа coder `php_status = null` авна - хувилбар, ini утга, php.ini-ийн зам нь серверийн мэдээлэл). Зөвхөн dashboard-ийн нүүр хуудсанд харагдана, хуудас бүрт биш.

- **Засвартай** - юу ч харуулахгүй (аюулгүй үед мэдэгдэл шаардлагагүй).
- **Эмзэг** - улаан анхааруулга:
  - ажиллаж буй хувилбар ба түүний салбарын засвартай хамгийн бага хувилбар (`8.2.12` -> `8.2.35+`; жагсаалтаас хуучин салбар -> жагсаалтын хамгийн эхний засвар; дистрибьюторын backport-той багц -> засвартай багцын хувилбар, жишээ нь `8.3.6-0ubuntu0.24.04.13+`),
  - дистрибьюторын багцын хувилбар (илэрсэн бол),
  - ажиллах горим (SAPI: `apache2handler`, `fpm-fcgi`, `cgi-fcgi`, `litespeed`, ...), ачаалагдсан `php.ini`-ийн зам, хэрэглэгчийн ini файлын нэр (`.user.ini`),
  - түр хамгаалалтын тохиргоо бүрийн **бодит** утга, шаардлагатай утга, тэмдэг (зөв / буруу),
  - түр хамгаалалт үйлчилж байгаа эсэх, илэрсэн SAPI-д тохиргоог хаана хийх товч заавар.

Dashboard-ийн хэл `mn` үед монголоор, бусад бүх хэлэнд англиар харагдана.

Утгыг вэб хүсэлтийн дотор `ini_get()`-ээр уншдаг тул энэ нь командын мөрийн `php -i` биш, вэб сайтын бодит ажиллаж буй утга юм.

### Дистрибьюторын backport

Ubuntu засварыг upstream-ийн шинэ хувилбар руу шилжүүлэлгүй хуучин хувилбартаа нөхдөг: Ubuntu 24.04-ийн засвартай `php8.3` нь `8.3.6-0ubuntu0.24.04.13` бөгөөд PHP `8.3.6` хэвээр харагдана (upstream засвар `8.3.36`). Зөвхөн upstream хувилбараар харьцуулбал засвартай серверийг эмзэг гэж буруу дүгнэнэ, тиймээс засвартай эсэхийг багцын хувилбараар шийднэ:

1. `PHP_VERSION`-д багцын хувилбар залгагдсан бол (`8.1.2-1ubuntu2.28`) түүнийг ашиглана.
2. Залгаасгүй бол (`8.3.6`) одоогийн SAPI-ийн багцын (`php8.3-fpm`, `libapache2-mod-php8.3`, `php8.3-cgi`, `php8.3-cli`) суулгасан хувилбарыг `/var/lib/dpkg/status`-аас уншина. Shared hosting, Windows, `open_basedir` хязгаартай орчинд уншигдахгүй - энэ үед upstream харьцуулалт хэвээр.
3. Багцын upstream хэсэг ажиллаж буй хувилбартай таарах ёстой (`8.3.6-...` нь `8.3.6`-д), өөр PHP-ийн багцыг тооцохгүй.

`BACKPORTS` дахь суурьтай (SRU дугааргүй хэсэг) таарвал SRU дугаарыг тоогоор харьцуулна (`.13` >= `.13`; `+esm1` зэрэг залгаасыг үл тооно). Debian засвартай upstream хувилбар руу шилждэг (`8.2.35-1~deb12u1`, `8.4.27-1~deb13u1`) тул `PATCHED`-ээр шууд танигдана.

dpkg-ийн бүртгэл нь **суулгасан** багцыг заана - `apt upgrade`-ийн дараа PHP-FPM / Apache-г restart хийгээгүй бол ажиллаж буй процесс хуучин кодоороо үлдэнэ.

### Шинэ эмзэг байдал зарлагдвал

`PhpSecurityStatus`-ийн дөрвөн тогтмолыг засна:

```php
public const ADVISORY = 'PHP2Shell (CVE-2026-109618, CVE-2026-109619)';
public const PATCHED = ['8.2' => '8.2.35', '8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12'];
public const MITIGATION = ['session.upload_progress.enabled' => 'Off', 'memory_limit' => '-1'];
public const BACKPORTS = ['8.3.6-0ubuntu0.24.04' => '8.3.6-0ubuntu0.24.04.13', ...];
```

- `PATCHED` - дэмжигдэж буй салбар бүрийн анхны засвартай хувилбар. Сүүлийн түлхүүрээс шинэ салбарыг засвартай, хуучныг эмзэг гэж үзнэ.
- `BACKPORTS` - дистрибьюторын багцын суурь => засвартай хамгийн бага багцын хувилбар, дистрибьюторын аюулгүй байдлын мэдэгдлээс (Ubuntu USN, жишээ нь USN-8948-1) авна. Шинэ зөвлөмжид бүх мөрийг шинээр бичнэ.
- `MITIGATION` - зөвлөмжийн түр хамгаалалтын ini тохиргоо => шаардлагатай утга. On/Off утгыг бичлэгээс үл хамааран харьцуулна (`""`, `0`, `off` бүгд Off); бусад утгыг яг тэнцүү мөрөөр харьцуулна. Зөвлөмжид түр хамгаалалт байхгүй бол хоосон (`[]`) үлдээнэ.

`tests/Unit/Controller/PhpSecurityStatusTest.php` хувилбарын харьцуулалтыг шалгана - `PATCHED`, `BACKPORTS`-той хамт түүний хувилбарын жагсаалтыг шинэчилнэ.

---

## Жинхэнэ шийдэл: PHP шинэчлэх

Түр хамгаалалт зөвхөн халдлагын боломжийг нарийсгана; **засвартай хувилбар руу шинэчлэх нь шийдэл**.

| Орчин | Хэрхэн |
|---|---|
| VPS (Debian/Ubuntu, ondrej/php) | `apt update && apt upgrade` (php8.x-* багцууд), дараа нь `systemctl restart php8.x-fpm` эсвэл `apache2`. Ubuntu-ийн өөрийн багц засварыг backport хийдэг тул PHP хувилбар нь өөрчлөгдөхгүй (`8.3.6`) - засвар авахын тулд ondrej PPA руу шилжих шаардлагагүй (дээрх "Дистрибьюторын backport"-ыг үз) |
| VPS (RHEL/Alma/Rocky, Remi) | `dnf upgrade 'php*'`, дараа нь php-fpm / httpd restart |
| Shared hosting | Хостингоор шинэчлүүлэх, эсвэл хяналтын самбараас засвартай хувилбар сонгох (cPanel MultiPHP Manager, CloudLinux PHP Selector, Plesk PHP settings). Тэнд сонгох нь салбар - тухайн салбарын засвартай build-ийг хостинг суулгасан байх ёстой. |
| Docker | Засвартай албан ёсны tag (`php:8.3.36-fpm` г.м.) дээр image-г дахин build хийж deploy хийнэ |
| Windows XAMPP / WAMP | Шинэ багц суулгах, эсвэл `php` хавтсыг windows.php.net-ийн засвартай build-ээр (ижил VC/TS хувилбар) солих, `php.ini`-гээ хадгална |
| macOS Homebrew | `brew upgrade php` (эсвэл `php@8.x`), дараа нь `brew services restart php` |

Шинэчилсний дараа `system_coder`-ээр dashboard-ийн нүүр хуудсыг нээнэ - улаан анхааруулга алга болсон байх ёстой. Дараа нь **түр хамгаалалтын тохиргоог өмнөх утгад нь буцаана** (доорх `memory_limit`-ийн анхааруулгыг үз).

---

## Түр хамгаалалт: хаана тохируулах вэ

Аль файлыг PHP уншихыг мэдэгдэлд харагдах SAPI тодорхойлно. Хаана ч хоёр дүрэм хүчинтэй:

- **Raptor-ийн кодоос хийх боломжгүй.** `session.upload_progress.enabled` нь `PHP_INI_PERDIR` тохиргоо (`ini_set()` өөрчилж чадахгүй), хүсэлтийн upload-ийг задлах нь ямар ч PHP код ажиллахаас өмнө болдог. Тиймээс Raptor утгыг зөвхөн *харуулдаг*, өөрөө тохируулдаггүй.
- **CLI биш, dashboard-оос шалгана.** CLI ихэвчлэн өөр php.ini ачаалдаг.

### 1. Серверийн php.ini (VPS, localhost) - хамгийн тохиромжтой

Мэдэгдлийн `php.ini:` замаас файлыг олно (жишээ нь `/etc/php/8.3/fpm/php.ini`, `/etc/php/8.3/apache2/php.ini`, `C:\xampp\php\php.ini`, `/opt/homebrew/etc/php/8.3/php.ini`) ба:

```ini
session.upload_progress.enabled = Off
memory_limit = -1
```

Эсвэл багц шинэчлэхэд дарагдахгүйн тулд тусдаа файлд: `/etc/php/8.3/fpm/conf.d/99-mitigation.ini` (Debian/Ubuntu), `/etc/php.d/99-mitigation.ini` (RHEL), `/usr/local/etc/php/conf.d/zz-mitigation.ini` (Docker албан ёсны image).

PHP процессыг restart хийнэ: `systemctl restart php8.3-fpm` / `systemctl restart apache2` / XAMPP Control Panel -> Apache Stop/Start / `brew services restart php`.

### 2. PHP-FPM pool (SAPI `fpm-fcgi`, VPS)

Сайт тус бүрээр, сайт өөрөө дарж чадахгүй - FPM дээр хамгийн найдвартай газар. Pool файлд (жишээ нь `/etc/php/8.3/fpm/pool.d/www.conf`):

```ini
php_admin_flag[session.upload_progress.enabled] = off
php_admin_value[memory_limit] = -1
```

Дараа нь `systemctl reload php8.3-fpm`.

FPM дээр `.user.ini`-ээр тохируулсан утга мэдэгдэлд харагдсан ч хүсэлтийн body задлахаас өмнө хэрэгжээгүй байж болзошгүй - pool эсвэл php.ini-г сонгоно. FPM-тэй shared hosting дээр хостингоор pool-д тохируулуулна.

### 3. Apache module (SAPI `apache2handler`)

Virtual host эсвэл `httpd.conf`-д (`.htaccess`-ээс дарагдахгүй):

```apache
php_admin_flag session.upload_progress.enabled off
php_admin_value memory_limit -1
```

Эсвэл зөвхөн `.htaccess` боломжтой бол (`AllowOverride Options` эсвэл `All` шаардлагатай):

```apache
<IfModule mod_php.c>
    php_flag session.upload_progress.enabled off
    php_value memory_limit -1
</IfModule>
```

(PHP 7-д `mod_php7.c`.) mod_php-гүй сервер дээр `php_value` мөр 500 алдаа өгдөг - `<IfModule>` хамгаалалтыг үлдээнэ.

### 4. Shared hosting (SAPI `cgi-fcgi`, `litespeed`, `fpm-fcgi`)

- **cPanel:** MultiPHP INI Editor -> Editor Mode -> домэйн сонгох -> хоёр мөрийг нэмэх -> Save. Энэ нь домэйны document root-д `.user.ini` (ба/эсвэл `php.ini`) бичнэ. CloudLinux PHP Selector -> Options-оос мөн `memory_limit` тохируулж болно.
- **Plesk / DirectAdmin:** домэйны PHP тохиргооны хуудас (Plesk-д "Additional configuration directives").
- **Гараар:** `index.php`-ийн хажууд (Raptor: `public_html/.user.ini`) хоёр мөртэй `.user.ini` файл. `.user.ini`-г `user_ini.cache_ttl` секунд тутам (анхдагч 300) дахин уншдаг - шалгахаас өмнө 5 минут хүртэл хүлээнэ.
- Мэдэгдэлд хуучин утга хэвээр (эсвэл SAPI нь `fpm-fcgi`) байвал хостингоор серверийн талд тохируулуулах, эсвэл бүр PHP-г шинэчлүүлнэ.

### Анхааруулга: `memory_limit = -1`

Энэ нь PHP-ийн санах ойн хязгаарыг арилгана: алдаатай скрипт серверийн бүх RAM-ыг идэж болох ба shared hosting дээр хостинг үүнийг хориглох эсвэл ийм процессыг зогсоож болно. Зөвлөмж шаардсан хугацаанд л ашиглаж, PHP шинэчилсний дараа өмнөх утгад (`128M`, `256M`, ...) нэн даруй буцаана. `session.upload_progress.enabled = Off` нь Raptor-т ямар ч нөлөөгүй (upload progress ашигладаггүй).

---

## Шалгах дараалал

1. `system_coder`-ээр нэвтэрч dashboard-ийн нүүр хуудсыг нээнэ.
2. Анхааруулга байхгүй -> юу ч хийх шаардлагагүй. Улаан анхааруулга -> PHP шинэчлэлтийг төлөвлөнө (дээрх хүснэгт).
3. Шинэчлэлт хүлээгдэх бол: SAPI-ийн зааврын дагуу түр хамгаалалтыг тохируулж, PHP-г restart / reload хийнэ (`.user.ini` бол 5 минут хүртэл хүлээнэ), нүүр хуудсыг дахин ачаалж тохиргоо бүр зөв тэмдэгтэй болсныг шалгана.
4. Шинэчилсний дараа: анхааруулга алга болсныг шалгаад `memory_limit`-ийг буцаана.
