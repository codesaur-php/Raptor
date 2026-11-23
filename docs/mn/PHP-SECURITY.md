# Серверийн PHP аюулгүй байдлын мэдэгдэл ба түр хамгаалалт

## Үндэслэл

Raptor олон өөр орчинд ажиллах боломжтой - VPS, shared hosting (cPanel, Plesk, DirectAdmin, ...) болон localhost (XAMPP, WAMP, MAMP, Homebrew, Docker). PHP-д ноцтой эмзэг байдал зарлагдахад сайт хариуцагч хоёр зүйлийг мэдэх хэрэгтэй:

1. **Вэб сайтад үйлчилж буй PHP засвартай юу?** Shared hosting дээр бүтэн хувилбар (`8.3.xx`) ихэвчлэн хаана ч харагддаггүй, VPS / localhost дээр CLI-ийн `php -v` нь вэб серверийн ашигладаг PHP-ээс өөр байж болно.
2. **PHP-г шууд шинэчлэх боломжгүй бол түр хамгаалалт үнэхээр үйлчилж байна уу?** Буруу файлд хийсэн (эсвэл restart хийгээгүй) ini өөрчлөлт чимээгүйгээр ямар ч нөлөөгүй үлддэг.

Dashboard-ийн нүүр хуудас энэ хоёрт бодит вэб хүсэлтийн дотроос хариулна.

---

## Одоогийн зөвлөмж

2026-11-19-ний PHP security release - **PHP 8.5.13, 8.4.28, 8.3.37, 8.2.36** - 18 эмзэг байдлыг засдаг, 6 нь Critical (CVSS 4.0 9.2). Түгээмэл тохиргоонд хүрдэг гурав:

| CVE | Хэзээ өртөх вэ |
|---|---|
| CVE-2026-129289 | PHP-FPM-ийн өмнөх вэб сервер FastCGI холболтыг дахин ашигладаг үед (nginx `fastcgi_keep_conn on`, Apache `mod_proxy_fcgi` `enablereuse=on`) - нэг хүсэлтээр алсаас код ажиллуулах. Хоёулаа анхдагчаар off. |
| CVE-2026-129474 | PHP 8.5 production php.ini-гүй ажиллах үед (php.ini ачаалагдаагүй - анхдагч утгууд). |
| CVE-2026-115560 | Session upload progress (`session.upload_progress.enabled`, анхдагчаар On) - нэг session store-ийг өөр `session.serialize_handler`-тай апп-ууд хуваалцах үед. Засвартай хувилбарууд upload progress-ийг анхдагчаар унтраадаг. |

Засвартай хувилбарууд өмнөх зөвлөмжийн (PHP2Shell, CVE-2026-109618 / CVE-2026-109619, 8.2.35 / 8.3.36 / 8.4.27 / 8.5.12-т засагдсан) засварыг ч агуулдаг тул `PATCHED` хоёуланг нь хамарна.

---

## Raptor юу харуулдаг вэ

`Dashboard\Home\PhpSecurityStatus` (`application/dashboard/home/PhpSecurityStatus.php`)-г `HomeController::index()` **зөвхөн `system_coder`-т** дуудна (`isUser('system_coder')`; бусад хэрэглэгч, мөн Act As горимд байгаа coder `php_status = null` авна - хувилбар, ini утга, php.ini-ийн зам нь серверийн мэдээлэл). Зөвхөн dashboard-ийн нүүр хуудсанд харагдана, хуудас бүрт биш.

- **Засвартай** - юу ч харуулахгүй (аюулгүй үед мэдэгдэл шаардлагагүй).
- **Эмзэг** - улаан анхааруулга:
  - ажиллаж буй хувилбар ба түүний салбарын засвартай хамгийн бага хувилбар (`8.2.12` -> `8.2.36+`; жагсаалтаас хуучин салбар -> жагсаалтын хамгийн эхний засвар; дистрибьюторын backport-той багц -> засвартай багцын хувилбар, жишээ нь `8.3.6-0ubuntu0.24.04.14+`),
  - дистрибьюторын багцын хувилбар (илэрсэн бол),
  - ажиллах горим (SAPI: `apache2handler`, `fpm-fcgi`, `cgi-fcgi`, `litespeed`, ...), ачаалагдсан `php.ini`-ийн зам, хэрэглэгчийн ini файлын нэр (`.user.ini`); php.ini ачаалагдаагүй бол энэ тухай мөр (PHP 8.5 дээр CVE-2026-129474),
  - ажиллаж буй PHP-д байгаа түр хамгаалалтын тохиргоо бүрийн **бодит** утга, шаардлагатай утга, тэмдэг (зөв / буруу),
  - түр хамгаалалт үйлчилж байгаа эсэх, илэрсэн SAPI-д тохиргоог хаана хийх товч заавар (`fpm-fcgi` үед PHP-ээс шалгах боломжгүй вэб сервер / pool-ийн тохиргоог мөн сануулна).

Dashboard-ийн хэл `mn` үед монголоор, бусад бүх хэлэнд англиар харагдана.

Утгыг вэб хүсэлтийн дотор `ini_get()`-ээр уншдаг тул энэ нь командын мөрийн `php -i` биш, вэб сайтын бодит ажиллаж буй утга юм.

Ажиллаж буй PHP-д байхгүй `MITIGATION` тохиргоо (`ini_get()` нь `false`) жагсаалтад харагдахгүй, "үйлчилж байна" дүгнэлтэд нөлөөлөхгүй: `fatal_error_backtraces` нь зөвхөн PHP 8.5-д байдаг тул 8.2 - 8.4 дээр үлдсэн тохиргоонууд шийднэ. PHP-ээс гадуурх тохиргоог - `fastcgi_keep_conn` / `enablereuse` (вэб сервер), `catch_workers_output` (FPM pool) - `ini_get()` уншиж чадахгүй тул шалгахгүй; доорх зааврын дагуу тохируулна.

### Дистрибьюторын backport

Ubuntu засварыг upstream-ийн шинэ хувилбар руу шилжүүлэлгүй хуучин хувилбартаа нөхдөг: Ubuntu 24.04-ийн засвартай `php8.3` нь `8.3.6-0ubuntu0.24.04.14` бөгөөд PHP `8.3.6` хэвээр харагдана (upstream засвар `8.3.37`). Зөвхөн upstream хувилбараар харьцуулбал засвартай серверийг эмзэг гэж буруу дүгнэнэ, тиймээс засвартай эсэхийг багцын хувилбараар шийднэ:

1. `PHP_VERSION`-д багцын хувилбар залгагдсан бол (`8.3.6-0ubuntu0.24.04.14`) түүнийг ашиглана.
2. Залгаасгүй бол (`8.3.6`) одоогийн SAPI-ийн багцын (`php8.3-fpm`, `libapache2-mod-php8.3`, `php8.3-cgi`, `php8.3-cli`) суулгасан хувилбарыг `/var/lib/dpkg/status`-аас уншина. Shared hosting, Windows, `open_basedir` хязгаартай орчинд уншигдахгүй - энэ үед upstream харьцуулалт хэвээр.
3. Багцын upstream хэсэг ажиллаж буй хувилбартай таарах ёстой (`8.3.6-...` нь `8.3.6`-д), өөр PHP-ийн багцыг тооцохгүй.

`BACKPORTS` дахь суурьтай (SRU дугааргүй хэсэг) таарвал SRU дугаарыг тоогоор харьцуулна (`.14` >= `.14`; `+esm1` зэрэг залгаасыг үл тооно). Debian засвартай upstream хувилбар руу шилждэг (`8.x.yy-1~deb12uN`) тул `PATCHED`-ээр шууд танигдана.

Энэ зөвлөмжийн Ubuntu багцууд (2026-11-20-нд `-security` pocket-д гарсан; эх сурвалж: Ubuntu CVE tracker, Launchpad-ийн багцын changelog - бичих үед USN дугаар холбогдоогүй байсан):

| Хувилбар | Багц | Засвартай хувилбар |
|---|---|---|
| 24.04 LTS | php8.3 | `8.3.6-0ubuntu0.24.04.14` |
| 26.04 LTS | php8.5 | `8.5.4-0ubuntu1.6` |
| 26.10 | php8.5 | `8.5.9-0ubuntu2.2` |
| 22.04 LTS | php8.1 | жагсаалтад ороогүй - `8.1.2-1ubuntu2.29` нь CVE-2026-129289, CVE-2026-126714-ийг засдаг ч changelog-д нь CVE-2026-115560 байхгүй |

Тиймээс Ubuntu 22.04 эмзэг гэж харагдсаар байна. `8.1.2-1ubuntu2.29`-ийг ямар ч байсан суулгаж, Ubuntu CVE-2026-115560-ийн засвар гаргах хүртэл `session.upload_progress.enabled = Off`-ийг хадгална (гарсны дараа `BACKPORTS`-д нэмнэ).

dpkg-ийн бүртгэл нь **суулгасан** багцыг заана - `apt upgrade`-ийн дараа PHP-FPM / Apache-г restart хийгээгүй бол ажиллаж буй процесс хуучин кодоороо үлдэнэ.

### Шинэ эмзэг байдал зарлагдвал

`PhpSecurityStatus`-ийн дөрвөн тогтмолыг засна:

```php
public const ADVISORY = 'PHP security release 2026-11-19 (18 CVEs, incl. CVE-2026-129289, CVE-2026-129474, CVE-2026-115560)';
public const PATCHED = ['8.2' => '8.2.36', '8.3' => '8.3.37', '8.4' => '8.4.28', '8.5' => '8.5.13'];
public const MITIGATION = ['session.upload_progress.enabled' => 'Off', 'zend.exception_ignore_args' => 'On', 'fatal_error_backtraces' => 'Off'];
public const BACKPORTS = ['8.3.6-0ubuntu0.24.04' => '8.3.6-0ubuntu0.24.04.14', ...];
```

- `PATCHED` - дэмжигдэж буй салбар бүрийн анхны засвартай хувилбар. Сүүлийн түлхүүрээс шинэ салбарыг засвартай, хуучныг эмзэг гэж үзнэ.
- `BACKPORTS` - дистрибьюторын багцын суурь => засвартай хамгийн бага багцын хувилбар, дистрибьюторын аюулгүй байдлын мэдэгдэл / CVE tracker / багцын changelog-оос авна. Шинэ зөвлөмжид бүх мөрийг шинээр бичиж, зөвлөмжийн бүх CVE-г засдаг багцтай хувилбарыг л нэмнэ.
- `MITIGATION` - зөвлөмжийн түр хамгаалалтын ini тохиргоо => шаардлагатай утга. On/Off утгыг бичлэгээс үл хамааран харьцуулна (`""`, `0`, `off` бүгд Off; `1`, `on` нь On); бусад утгыг яг тэнцүү мөрөөр харьцуулна. Ажиллаж буй PHP-д байхгүй тохиргоог алгасна. Зөвхөн `ini_get()`-ээр уншигдах тохиргоог оруулна - вэб сервер, FPM pool-ийн тохиргоог энэ баримт бичигт бичнэ. Зөвлөмжид түр хамгаалалт байхгүй бол хоосон (`[]`) үлдээнэ.

`tests/Unit/Controller/PhpSecurityStatusTest.php` хувилбарын харьцуулалтыг шалгана - `PATCHED`, `BACKPORTS`-той хамт түүний хувилбарын жагсаалтыг шинэчилнэ.

---

## Жинхэнэ шийдэл: PHP шинэчлэх

Түр хамгаалалт зөвхөн халдлагын боломжийг нарийсгана; **засвартай хувилбар руу шинэчлэх нь шийдэл**.

| Орчин | Хэрхэн |
|---|---|
| VPS (Debian/Ubuntu, ondrej/php) | `apt update && apt upgrade` (php8.x-* багцууд), дараа нь `systemctl restart php8.x-fpm` эсвэл `apache2`. Ubuntu-ийн өөрийн багц засварыг backport хийдэг тул PHP хувилбар нь өөрчлөгдөхгүй (`8.3.6`) - засвар авахын тулд ondrej PPA руу шилжих шаардлагагүй (дээрх "Дистрибьюторын backport"-ыг үз; энэ зөвлөмжид Ubuntu 22.04 үл хамаарна) |
| VPS (RHEL/Alma/Rocky, Remi) | `dnf upgrade 'php*'`, дараа нь php-fpm / httpd restart |
| Shared hosting | Хостингоор шинэчлүүлэх, эсвэл хяналтын самбараас засвартай хувилбар сонгох (cPanel MultiPHP Manager, CloudLinux PHP Selector, Plesk PHP settings). Тэнд сонгох нь салбар - тухайн салбарын засвартай build-ийг хостинг суулгасан байх ёстой. |
| Docker | Засвартай албан ёсны tag (`php:8.3.37-fpm` г.м.) дээр image-г дахин build хийж deploy хийнэ |
| Windows XAMPP / WAMP | Шинэ багц суулгах, эсвэл `php` хавтсыг windows.php.net-ийн засвартай build-ээр (ижил VC/TS хувилбар) солих, `php.ini`-гээ хадгална |
| macOS Homebrew | `brew upgrade php` (эсвэл `php@8.x`), дараа нь `brew services restart php` |

Шинэчилсний дараа `system_coder`-ээр dashboard-ийн нүүр хуудсыг нээнэ - улаан анхааруулга алга болсон байх ёстой. Энэ зөвлөмжийн түр хамгаалалтын тохиргоо Raptor-т сөрөг нөлөөгүй тул хэвээр үлдээж болно.

---

## Түр хамгаалалт: хаана тохируулах вэ

PHP шинэчлэх хүртэл бүгдийг тохируулна:

| Тохиргоо | Хаана | Утга |
|---|---|---|
| `session.upload_progress.enabled` | php.ini / FPM pool / Apache config | `Off` (`0`) |
| `zend.exception_ignore_args` | php.ini / FPM pool / Apache config | `On` (`1`) |
| `fatal_error_backtraces` | php.ini / FPM pool / Apache config - **зөвхөн PHP 8.5** | `Off` (`0`) |
| FastCGI холболтыг дахин ашиглах | вэб сервер | nginx `fastcgi_keep_conn off;`, Apache `enablereuse=off` |
| `catch_workers_output` | FPM pool | `no` |
| production php.ini | PHP 8.5 | php.ini ачаалагдсан байх ёстой (`php.ini-production`-ийг хуулна) |

Мэдэгдэл эхний гуравыг (ажиллаж буй PHP-д байгааг нь л) шалгаж, php.ini ачаалагдаагүй бол харуулна. Вэб сервер, pool-ийн тохиргоо PHP-ээс харагдахгүй - тохиргооны файлаас шалгана.

Аль файлыг PHP уншихыг мэдэгдэлд харагдах SAPI тодорхойлно. Хаана ч хоёр дүрэм хүчинтэй:

- **Raptor-ийн кодоос хийх боломжгүй.** `session.upload_progress.enabled` нь `PHP_INI_PERDIR` тохиргоо (`ini_set()` өөрчилж чадахгүй), хүсэлтийн upload-ийг задлах нь ямар ч PHP код ажиллахаас өмнө болдог; `ini_set()` эсвэл `.user.ini`-ийн утга энэ зөвлөмжийн хүсэлтийн түвшний замд хэтэрхий оройтож үйлчилнэ. Тиймээс Raptor утгыг зөвхөн *харуулдаг*, өөрөө тохируулдаггүй - php.ini эсвэл FPM pool-д тохируулна.
- **CLI биш, dashboard-оос шалгана.** CLI ихэвчлэн өөр php.ini ачаалдаг.

### 1. Серверийн php.ini (VPS, localhost) - хамгийн тохиромжтой

Мэдэгдлийн `php.ini:` замаас файлыг олно (жишээ нь `/etc/php/8.3/fpm/php.ini`, `/etc/php/8.3/apache2/php.ini`, `C:\xampp\php\php.ini`, `/opt/homebrew/etc/php/8.3/php.ini`) ба:

```ini
session.upload_progress.enabled = 0
zend.exception_ignore_args = 1
; зөвхөн PHP 8.5
fatal_error_backtraces = 0
```

Эсвэл багц шинэчлэхэд дарагдахгүйн тулд тусдаа файлд: `/etc/php/8.3/fpm/conf.d/99-mitigation.ini` (Debian/Ubuntu), `/etc/php.d/99-mitigation.ini` (RHEL), `/usr/local/etc/php/conf.d/zz-mitigation.ini` (Docker албан ёсны image). PHP 8.2 - 8.4 дээр `fatal_error_backtraces`-ийг оруулахгүй (тэнд энэ тохиргоо байхгүй).

Мэдэгдэлд `php.ini:` зам харагдахгүй бол PHP php.ini-гүй ажиллаж байна. PHP 8.5 дээр энэ нь CVE-2026-129474-ийн тохиргоо: PHP-тэй хамт ирдэг `php.ini-production`-ийг тохиргооны хавтсанд `php.ini` болгон хуулж (`php --ini` харуулна; Windows дээр `php.exe`-ийн хажууд), дээрх мөрүүдийг нэмнэ.

PHP процессыг restart хийнэ: `systemctl restart php8.3-fpm` / `systemctl restart apache2` / XAMPP Control Panel -> Apache Stop/Start / `brew services restart php`.

### 2. PHP-FPM pool (SAPI `fpm-fcgi`, VPS)

Сайт тус бүрээр, сайт өөрөө дарж чадахгүй - FPM дээр хамгийн найдвартай газар. Pool файлд (жишээ нь `/etc/php/8.3/fpm/pool.d/www.conf`):

```ini
php_admin_flag[session.upload_progress.enabled] = off
php_admin_flag[zend.exception_ignore_args] = on
; зөвхөн PHP 8.5
php_admin_flag[fatal_error_backtraces] = off
catch_workers_output = no
```

Дараа нь `systemctl reload php8.3-fpm`.

FPM дээр `.user.ini`-ээр тохируулсан утга мэдэгдэлд харагдсан ч хүсэлтийн body задлахаас өмнө хэрэгжээгүй байж болзошгүй - pool эсвэл php.ini-г сонгоно. FPM-тэй shared hosting дээр хостингоор pool-д тохируулуулна.

### 3. Вэб сервер: FastCGI холболтыг дахин ашиглахгүй (PHP-FPM)

CVE-2026-129289 нь вэб сервер PHP-FPM-тэй FastCGI холболтыг хүсэлтүүдийн хооронд нээлттэй байлгах үед л ажиллана. Хоёулаа анхдагчаар off - хэн нэгэн асаагаагүй эсэхийг шалгана:

```nginx
# nginx: PHP-FPM руу дамжуулдаг location-д ("fastcgi_keep_conn on;"-ийг устгах эсвэл off)
fastcgi_keep_conn off;
```

```apache
# Apache mod_proxy_fcgi: PHP-FPM руу чиглэсэн ProxyPass / <Proxy>-д enablereuse=on байх ёсгүй
ProxyPass "/" "fcgi://127.0.0.1:9000/var/www/public_html/" enablereuse=off
```

Дараа нь `nginx -t && systemctl reload nginx` / `apachectl configtest && systemctl reload apache2`. Include хийгдсэн бүх файлыг хайна (`grep -rn "fastcgi_keep_conn\|enablereuse" /etc/nginx /etc/apache2 /etc/httpd`). Shared hosting дээр энэ нь хостингийн тохиргоо - хостингоос асууна.

### 4. Apache module (SAPI `apache2handler`)

Virtual host эсвэл `httpd.conf`-д (`.htaccess`-ээс дарагдахгүй):

```apache
php_admin_flag session.upload_progress.enabled off
php_admin_flag zend.exception_ignore_args on
```

Эсвэл зөвхөн `.htaccess` боломжтой бол (`AllowOverride Options` эсвэл `All` шаардлагатай):

```apache
<IfModule mod_php.c>
    php_flag session.upload_progress.enabled off
    php_flag zend.exception_ignore_args on
</IfModule>
```

(PHP 7-д `mod_php7.c`. PHP 8.5 дээр `fatal_error_backtraces off`-ийг мөн адил нэмнэ.) mod_php-гүй сервер дээр `php_flag` мөр 500 алдаа өгдөг - `<IfModule>` хамгаалалтыг үлдээнэ.

### 5. Shared hosting (SAPI `cgi-fcgi`, `litespeed`, `fpm-fcgi`)

- **cPanel:** MultiPHP INI Editor -> Editor Mode -> домэйн сонгох -> мөрүүдийг нэмэх -> Save. Энэ нь домэйны document root-д `.user.ini` (ба/эсвэл `php.ini`) бичнэ.
- **Plesk / DirectAdmin:** домэйны PHP тохиргооны хуудас (Plesk-д "Additional configuration directives").
- **Гараар:** `index.php`-ийн хажууд (Raptor: `public_html/.user.ini`) мөрүүдтэй `.user.ini` файл. `.user.ini`-г `user_ini.cache_ttl` секунд тутам (анхдагч 300) дахин уншдаг - шалгахаас өмнө 5 минут хүртэл хүлээнэ.
- `.user.ini` хүсэлт задлахад хэтэрхий оройтож үйлчилдэг, вэб сервер / pool-ийн тохиргоо таны гарт байдаггүй: хостингоор серверийн талд тохируулуулах, эсвэл бүр PHP-г шинэчлүүлнэ.

### Сөрөг нөлөө

- `session.upload_progress.enabled = Off` - Raptor-т нөлөөгүй (upload progress ашигладаггүй); засвартай хувилбарууд ч анхдагчаар унтраадаг.
- `zend.exception_ignore_args = On` - php.ini-production-ийн анхдагч утга; лог дахь stack trace аргументын утгыг харуулахаа болино (нууц мэдээллийг логоос нуудаг давуу талтай).
- `fatal_error_backtraces = Off` - fatal алдаа backtrace-гүй бичигдэнэ.
- `fastcgi_keep_conn off` / `enablereuse=off` - анхдагч утга; хүсэлт бүрт шинэ FastCGI холболт.

### `memory_limit = -1` (өмнөх зөвлөмж)

Өмнөх зөвлөмжийн (PHP2Shell) түр хамгаалалт нь `memory_limit = -1` байсан. Энэ нь `MITIGATION`-д байхаа больсон: шинэ засвартай хувилбарууд PHP2Shell-ийн засварыг агуулдаг, хязгааргүй санах ой нь алдаатай скриптэд серверийн бүх RAM-ыг идэх боломж олгодог. PHP2Shell-ийн улмаас тохируулсан бол PHP дээрх хүснэгтийн хувилбарт шилжмэгц өмнөх утгад (`128M`, `256M`, ...) буцаана. PHP2Shell-ийн засвараас ч доош хувилбартай сервер хоёр зөвлөмжид хоёуланд нь өртөнө - шинэчилнэ.

---

## Шалгах дараалал

1. `system_coder`-ээр нэвтэрч dashboard-ийн нүүр хуудсыг нээнэ.
2. Анхааруулга байхгүй -> юу ч хийх шаардлагагүй. Улаан анхааруулга -> PHP шинэчлэлтийг төлөвлөнө (дээрх хүснэгт).
3. Шинэчлэлт хүлээгдэх бол: SAPI-ийн зааврын дагуу түр хамгаалалтыг тохируулж, вэб сервер / FPM pool-ийн тохиргоог шалгаад (3, 2-р хэсэг), PHP болон вэб серверийг restart / reload хийнэ (`.user.ini` бол 5 минут хүртэл хүлээнэ), нүүр хуудсыг дахин ачаалж тохиргоо бүр зөв тэмдэгтэй болсныг шалгана.
4. Шинэчилсний дараа: анхааруулга алга болсныг шалгана. Өмнөх зөвлөмжийн улмаас `memory_limit`-ийг `-1` болгосон бол буцаана.
