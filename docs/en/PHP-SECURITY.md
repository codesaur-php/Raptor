# Server PHP Security Notice and Interim Mitigation

## Background

Raptor can run on many different environments - VPS, shared hosting (cPanel, Plesk, DirectAdmin, ...) and localhost (XAMPP, WAMP, MAMP, Homebrew, Docker). When a serious PHP vulnerability is announced, the person responsible for the site needs to know two things:

1. **Does the PHP that serves the website have the fix?** On shared hosting the full version (`8.3.xx`) is often not shown anywhere, and on a VPS / localhost the CLI `php -v` can be a different binary from the one the web server uses.
2. **If PHP cannot be upgraded right away, is the interim mitigation actually in effect?** An ini change made in the wrong file (or not picked up until a restart) silently does nothing.

The dashboard home page answers both from inside the real web request.

---

## Current advisory

The PHP security release of 2026-11-19 - **PHP 8.5.13, 8.4.28, 8.3.37, 8.2.36** - fixes 18 vulnerabilities, 6 of them Critical (CVSS 4.0 9.2). Three reach common configurations:

| CVE | Exposed when |
|---|---|
| CVE-2026-129289 | PHP-FPM behind a web server that reuses FastCGI connections (nginx `fastcgi_keep_conn on`, Apache `mod_proxy_fcgi` `enablereuse=on`) - remote code execution with a single request. Both are off by default. |
| CVE-2026-129474 | PHP 8.5 running without a production php.ini (no php.ini loaded - built-in defaults). |
| CVE-2026-115560 | Session upload progress (`session.upload_progress.enabled`, On by default) when one session store is shared by applications with different `session.serialize_handler` values. The fixed releases turn upload progress off by default. |

The patched releases also contain the fixes of the previous advisory (PHP2Shell, CVE-2026-109618 / CVE-2026-109619, fixed in 8.2.35 / 8.3.36 / 8.4.27 / 8.5.12), so `PATCHED` covers both.

---

## What Raptor shows

`Dashboard\Home\PhpSecurityStatus` (`application/dashboard/home/PhpSecurityStatus.php`) is called by `HomeController::index()` **only for `system_coder`** (`isUser('system_coder')`; other users - and a coder in Act As mode - get `php_status = null`, since the version, ini values and the php.ini path are server details). It appears on the dashboard home page only, not on every page.

- **Patched** - nothing is shown (no notice is needed when the server is safe).
- **Vulnerable** - a red alert with:
  - the running version and the minimum patched version of its branch (`8.2.12` -> `8.2.36+`; a branch older than the list -> the oldest listed fix; a distribution package with a backported fix -> the patched package version, e.g. `8.3.6-0ubuntu0.24.04.14+`),
  - the distribution package version (when detected),
  - the SAPI (`apache2handler`, `fpm-fcgi`, `cgi-fcgi`, `litespeed`, ...), the loaded `php.ini` path and the user ini file name (`.user.ini`); when no php.ini is loaded, a line saying so (CVE-2026-129474 on PHP 8.5),
  - every mitigation setting that exists in the running PHP with its **actual** value, the required value and a check / cross,
  - whether the mitigation is in effect, plus a short hint on where to set it for the detected SAPI (on `fpm-fcgi` also a reminder of the web server / pool settings that cannot be checked from PHP).

The text is Mongolian when the dashboard language is `mn`, English for every other language.

The values are read with `ini_get()` inside the web request, so they are what the website really runs with - not what `php -i` on the command line says.

A `MITIGATION` setting that does not exist in the running PHP (`ini_get()` returns `false`) is not listed and does not affect "mitigated": `fatal_error_backtraces` exists only in PHP 8.5, so on 8.2 - 8.4 the remaining settings decide. Settings that live outside PHP - `fastcgi_keep_conn` / `enablereuse` (web server) and `catch_workers_output` (FPM pool) - cannot be read with `ini_get()` and are not checked; set them from the instructions below.

### Distribution backports

Ubuntu fixes its PHP packages without moving to the new upstream release: the patched `php8.3` on Ubuntu 24.04 is `8.3.6-0ubuntu0.24.04.14`, and PHP still reports `8.3.6` (the upstream fix is `8.3.37`). Comparing upstream versions alone would flag a patched server as vulnerable, so the package version decides:

1. When `PHP_VERSION` carries the package version (`8.3.6-0ubuntu0.24.04.14`), that is used.
2. Otherwise (`8.3.6`), the installed version of the current SAPI's package (`php8.3-fpm`, `libapache2-mod-php8.3`, `php8.3-cgi`, `php8.3-cli`) is read from `/var/lib/dpkg/status`. On shared hosting, Windows or under `open_basedir` it cannot be read - the upstream comparison stays in effect.
3. The upstream part of the package must equal the running version (`8.3.6-...` for `8.3.6`); a package of a different PHP is ignored.

When the package matches a base in `BACKPORTS` (the part without the SRU number), the SRU numbers are compared numerically (`.14` >= `.14`; suffixes such as `+esm1` are ignored). Debian moves to the patched upstream release (`8.x.yy-1~deb12uN`), so `PATCHED` already recognizes it.

Ubuntu packages for this advisory (published to the `-security` pockets on 2026-11-20; source: the Ubuntu CVE tracker and the Launchpad package changelogs - no USN number was linked at the time of writing):

| Release | Package | Patched version |
|---|---|---|
| 24.04 LTS | php8.3 | `8.3.6-0ubuntu0.24.04.14` |
| 26.04 LTS | php8.5 | `8.5.4-0ubuntu1.6` |
| 26.10 | php8.5 | `8.5.9-0ubuntu2.2` |
| 22.04 LTS | php8.1 | not listed - `8.1.2-1ubuntu2.29` fixes CVE-2026-129289 and CVE-2026-126714, but its changelog does not include CVE-2026-115560 |

Ubuntu 22.04 therefore stays flagged as vulnerable. Install `8.1.2-1ubuntu2.29` anyway and keep `session.upload_progress.enabled = Off` until Ubuntu ships the CVE-2026-115560 fix (then add the entry to `BACKPORTS`).

The dpkg database shows the **installed** package - until PHP-FPM / Apache is restarted after `apt upgrade`, the running process still has the old code.

### Updating for a new advisory

Edit the four constants of `PhpSecurityStatus`:

```php
public const ADVISORY = 'PHP security release 2026-11-19 (18 CVEs, incl. CVE-2026-129289, CVE-2026-129474, CVE-2026-115560)';
public const PATCHED = ['8.2' => '8.2.36', '8.3' => '8.3.37', '8.4' => '8.4.28', '8.5' => '8.5.13'];
public const MITIGATION = ['session.upload_progress.enabled' => 'Off', 'zend.exception_ignore_args' => 'On', 'fatal_error_backtraces' => 'Off'];
public const BACKPORTS = ['8.3.6-0ubuntu0.24.04' => '8.3.6-0ubuntu0.24.04.14', ...];
```

- `PATCHED` - the first fixed release of every supported branch. A branch newer than the last key counts as patched, an older one as vulnerable.
- `BACKPORTS` - distribution package base => minimum patched package version, taken from the distribution's security notice / CVE tracker / package changelog. Rewrite every entry for a new advisory, and add a release only when its package fixes every CVE the advisory is about.
- `MITIGATION` - ini setting => required value from the advisory's interim recommendation. On/Off values are compared regardless of spelling (`""`, `0`, `off` are all Off; `1`, `on` are On); other values are compared as exact strings. A setting missing from the running PHP is skipped. Only list settings `ini_get()` can read - web server and FPM pool directives go into this document. Leave it empty (`[]`) when the advisory has no interim mitigation.

`tests/Unit/Controller/PhpSecurityStatusTest.php` covers the version comparison - update its version lists together with `PATCHED` and `BACKPORTS`.

---

## The real fix: upgrade PHP

The interim mitigation only narrows the attack surface; **upgrading PHP to a patched release is the fix**.

| Environment | How |
|---|---|
| VPS (Debian/Ubuntu, ondrej/php) | `apt update && apt upgrade` (php8.x-* packages), then `systemctl restart php8.x-fpm` or `apache2`. Ubuntu's own packages backport the fix, so the PHP version does not change (`8.3.6`) - switching to the ondrej PPA is not needed to get the fix (see "Distribution backports" above; Ubuntu 22.04 is the exception for this advisory) |
| VPS (RHEL/Alma/Rocky, Remi) | `dnf upgrade 'php*'`, then restart php-fpm / httpd |
| Shared hosting | Ask the provider to upgrade, or pick a patched version in the control panel (cPanel MultiPHP Manager, CloudLinux PHP Selector, Plesk PHP settings). The version you choose there is a branch - the provider still has to install the patched build of that branch. |
| Docker | Rebuild the image on the patched official tag (`php:8.3.37-fpm` etc.) and redeploy |
| Windows XAMPP / WAMP | Install a newer package, or replace the `php` folder with the patched windows.php.net build (same VC/TS variant), keeping your `php.ini` |
| macOS Homebrew | `brew upgrade php` (or `php@8.x`), then `brew services restart php` |

After upgrading, open the dashboard home page as `system_coder` - the red alert must be gone. The mitigation settings of this advisory have no side effect for Raptor and can stay in place.

---

## Interim mitigation: where to set it

Set all of these until PHP is upgraded:

| Setting | Where | Value |
|---|---|---|
| `session.upload_progress.enabled` | php.ini / FPM pool / Apache config | `Off` (`0`) |
| `zend.exception_ignore_args` | php.ini / FPM pool / Apache config | `On` (`1`) |
| `fatal_error_backtraces` | php.ini / FPM pool / Apache config - **PHP 8.5 only** | `Off` (`0`) |
| FastCGI connection reuse | web server | nginx `fastcgi_keep_conn off;`, Apache `enablereuse=off` |
| `catch_workers_output` | FPM pool | `no` |
| production php.ini | PHP 8.5 | a php.ini must be loaded (copy `php.ini-production`) |

The notice checks the first three (only those that exist in the running PHP) and shows when no php.ini is loaded. The web server and pool directives are not visible from PHP - verify them in the configuration files.

Which file is honoured depends on the SAPI shown in the notice. Two rules apply everywhere:

- **It cannot be done from Raptor's code.** `session.upload_progress.enabled` is a `PHP_INI_PERDIR` setting (`ini_set()` cannot change it), and request upload parsing happens before any PHP code runs; an `ini_set()` or `.user.ini` value also applies too late for the request-level paths of this advisory. That is why Raptor only *reports* the values and does not set them - set them in php.ini or the FPM pool.
- **Verify in the dashboard, not on the CLI.** The CLI often loads a different php.ini.

### 1. Server php.ini (VPS, localhost) - preferred

Find the file from the `php.ini:` path in the notice (for example `/etc/php/8.3/fpm/php.ini`, `/etc/php/8.3/apache2/php.ini`, `C:\xampp\php\php.ini`, `/opt/homebrew/etc/php/8.3/php.ini`) and set:

```ini
session.upload_progress.enabled = 0
zend.exception_ignore_args = 1
; PHP 8.5 only
fatal_error_backtraces = 0
```

Or put them in a separate drop-in so package upgrades do not overwrite them: `/etc/php/8.3/fpm/conf.d/99-mitigation.ini` (Debian/Ubuntu), `/etc/php.d/99-mitigation.ini` (RHEL), `/usr/local/etc/php/conf.d/zz-mitigation.ini` (Docker official image). On PHP 8.2 - 8.4 leave out `fatal_error_backtraces` (the setting does not exist there).

When the notice shows no `php.ini:` path, PHP runs without a php.ini. On PHP 8.5 that is the CVE-2026-129474 configuration: copy `php.ini-production` (shipped with PHP) to `php.ini` in the configuration directory (`php --ini` shows it; on Windows next to `php.exe`) and add the lines above.

Restart the PHP process: `systemctl restart php8.3-fpm` / `systemctl restart apache2` / XAMPP Control Panel -> Apache Stop/Start / `brew services restart php`.

### 2. PHP-FPM pool (SAPI `fpm-fcgi`, VPS)

Per-site and cannot be overridden by the site itself - the most reliable place on FPM. In the pool file (e.g. `/etc/php/8.3/fpm/pool.d/www.conf`):

```ini
php_admin_flag[session.upload_progress.enabled] = off
php_admin_flag[zend.exception_ignore_args] = on
; PHP 8.5 only
php_admin_flag[fatal_error_backtraces] = off
catch_workers_output = no
```

Then `systemctl reload php8.3-fpm`.

On FPM, a value set through `.user.ini` may show up in the notice but still be applied too late for the request's body parsing - prefer the pool or php.ini. On shared hosting with FPM, ask the provider to set it in the pool.

### 3. Web server: no FastCGI connection reuse (PHP-FPM)

CVE-2026-129289 needs the web server to keep the FastCGI connection to PHP-FPM open between requests. Both are off by default - check that nobody turned them on:

```nginx
# nginx: in the location that passes to PHP-FPM (remove "fastcgi_keep_conn on;" or set off)
fastcgi_keep_conn off;
```

```apache
# Apache mod_proxy_fcgi: no enablereuse=on on the ProxyPass / <Proxy> to PHP-FPM
ProxyPass "/" "fcgi://127.0.0.1:9000/var/www/public_html/" enablereuse=off
```

Then `nginx -t && systemctl reload nginx` / `apachectl configtest && systemctl reload apache2`. Search all included files (`grep -rn "fastcgi_keep_conn\|enablereuse" /etc/nginx /etc/apache2 /etc/httpd`). On shared hosting this is the provider's configuration - ask them.

### 4. Apache module (SAPI `apache2handler`)

In the virtual host or `httpd.conf` (not overridable from `.htaccess`):

```apache
php_admin_flag session.upload_progress.enabled off
php_admin_flag zend.exception_ignore_args on
```

Or, when only `.htaccess` is available (needs `AllowOverride Options` or `All`):

```apache
<IfModule mod_php.c>
    php_flag session.upload_progress.enabled off
    php_flag zend.exception_ignore_args on
</IfModule>
```

(`mod_php7.c` for PHP 7. Add `fatal_error_backtraces off` the same way on PHP 8.5.) A `php_flag` line on a server without mod_php causes a 500 error - keep the `<IfModule>` guard.

### 5. Shared hosting (SAPI `cgi-fcgi`, `litespeed`, `fpm-fcgi`)

- **cPanel:** MultiPHP INI Editor -> Editor Mode -> choose the domain -> add the lines -> Save. It writes `.user.ini` (and/or `php.ini`) in the domain's document root.
- **Plesk / DirectAdmin:** the domain's PHP settings page ("Additional configuration directives" in Plesk).
- **Manually:** a `.user.ini` file next to `index.php` (Raptor: `public_html/.user.ini`) with the lines. `.user.ini` is re-read only every `user_ini.cache_ttl` seconds (300 by default) - wait up to 5 minutes before checking.
- `.user.ini` applies too late for request parsing, and the web server / pool directives are not in your hands: ask the provider to apply the mitigation server-side or, better, to upgrade PHP.

### Side effects

- `session.upload_progress.enabled = Off` - none for Raptor (it does not use upload progress); the fixed releases turn it off by default too.
- `zend.exception_ignore_args = On` - the php.ini-production default; stack traces in logs no longer show argument values (also hides secrets from logs).
- `fatal_error_backtraces = Off` - fatal errors are logged without a backtrace.
- `fastcgi_keep_conn off` / `enablereuse=off` - the default; one new FastCGI connection per request.

### `memory_limit = -1` (previous advisory)

The interim mitigation of the previous advisory (PHP2Shell) was `memory_limit = -1`. It is no longer part of `MITIGATION`: the new patched releases include the PHP2Shell fix, and an unlimited memory cap lets a runaway script use all of the server's RAM. If you set it for PHP2Shell, put the previous value back (`128M`, `256M`, ...) once PHP is on a release from the table above. A server still below the PHP2Shell fix is exposed to both advisories - upgrade it.

---

## Checklist

1. Log in as `system_coder` and open the dashboard home page.
2. No alert -> nothing to do. Red alert -> plan the PHP upgrade (table above).
3. If the upgrade has to wait: set the mitigation where the SAPI hint points, check the web server / FPM pool directives (section 3, 2), restart / reload PHP and the web server (or wait up to 5 minutes for `.user.ini`), reload the home page and confirm every setting has a check.
4. After the upgrade: confirm the alert is gone. Restore `memory_limit` if it was set to `-1` for the previous advisory.
