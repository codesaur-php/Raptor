# Server PHP Security Notice and Interim Mitigation

## Background

Raptor can run on many different environments - VPS, shared hosting (cPanel, Plesk, DirectAdmin, ...) and localhost (XAMPP, WAMP, MAMP, Homebrew, Docker). When a serious PHP vulnerability is announced, the person responsible for the site needs to know two things:

1. **Does the PHP that serves the website have the fix?** On shared hosting the full version (`8.3.xx`) is often not shown anywhere, and on a VPS / localhost the CLI `php -v` can be a different binary from the one the web server uses.
2. **If PHP cannot be upgraded right away, is the interim mitigation actually in effect?** An ini change made in the wrong file (or not picked up until a restart) silently does nothing.

The dashboard home page answers both from inside the real web request.

---

## What Raptor shows

`Dashboard\Home\PhpSecurityStatus` (`application/dashboard/home/PhpSecurityStatus.php`) is called by `HomeController::index()` **only for `system_coder`** (`isUser('system_coder')`; other users - and a coder in Act As mode - get `php_status = null`, since the version, ini values and the php.ini path are server details). It appears on the dashboard home page only, not on every page.

- **Patched** - nothing is shown (no notice is needed when the server is safe).
- **Vulnerable** - a red alert with:
  - the running version and the minimum patched version of its branch (`8.2.12` -> `8.2.35+`; a branch older than the list -> the oldest listed fix; a distribution package with a backported fix -> the patched package version, e.g. `8.3.6-0ubuntu0.24.04.13+`),
  - the distribution package version (when detected),
  - the SAPI (`apache2handler`, `fpm-fcgi`, `cgi-fcgi`, `litespeed`, ...), the loaded `php.ini` path and the user ini file name (`.user.ini`),
  - every mitigation setting with its **actual** value, the required value and a check / cross,
  - whether the mitigation is in effect, plus a short hint on where to set it for the detected SAPI.

The text is Mongolian when the dashboard language is `mn`, English for every other language.

The values are read with `ini_get()` inside the web request, so they are what the website really runs with - not what `php -i` on the command line says.

### Distribution backports

Ubuntu fixes its PHP packages without moving to the new upstream release: the patched `php8.3` on Ubuntu 24.04 is `8.3.6-0ubuntu0.24.04.13`, and PHP still reports `8.3.6` (the upstream fix is `8.3.36`). Comparing upstream versions alone would flag a patched server as vulnerable, so the package version decides:

1. When `PHP_VERSION` carries the package version (`8.1.2-1ubuntu2.28`), that is used.
2. Otherwise (`8.3.6`), the installed version of the current SAPI's package (`php8.3-fpm`, `libapache2-mod-php8.3`, `php8.3-cgi`, `php8.3-cli`) is read from `/var/lib/dpkg/status`. On shared hosting, Windows or under `open_basedir` it cannot be read - the upstream comparison stays in effect.
3. The upstream part of the package must equal the running version (`8.3.6-...` for `8.3.6`); a package of a different PHP is ignored.

When the package matches a base in `BACKPORTS` (the part without the SRU number), the SRU numbers are compared numerically (`.13` >= `.13`; suffixes such as `+esm1` are ignored). Debian moves to the patched upstream release (`8.2.35-1~deb12u1`, `8.4.27-1~deb13u1`), so `PATCHED` already recognizes it.

The dpkg database shows the **installed** package - until PHP-FPM / Apache is restarted after `apt upgrade`, the running process still has the old code.

### Updating for a new advisory

Edit the four constants of `PhpSecurityStatus`:

```php
public const ADVISORY = 'PHP2Shell (CVE-2026-109618, CVE-2026-109619)';
public const PATCHED = ['8.2' => '8.2.35', '8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12'];
public const MITIGATION = ['session.upload_progress.enabled' => 'Off', 'memory_limit' => '-1'];
public const BACKPORTS = ['8.3.6-0ubuntu0.24.04' => '8.3.6-0ubuntu0.24.04.13', ...];
```

- `PATCHED` - the first fixed release of every supported branch. A branch newer than the last key counts as patched, an older one as vulnerable.
- `BACKPORTS` - distribution package base => minimum patched package version, taken from the distribution's security notice (Ubuntu USN, e.g. USN-8948-1). Rewrite every entry for a new advisory.
- `MITIGATION` - ini setting => required value from the advisory's interim recommendation. On/Off values are compared regardless of spelling (`""`, `0`, `off` are all Off); other values are compared as exact strings. Leave it empty (`[]`) when the advisory has no interim mitigation.

`tests/Unit/Controller/PhpSecurityStatusTest.php` covers the version comparison - update its version lists together with `PATCHED` and `BACKPORTS`.

---

## The real fix: upgrade PHP

The interim mitigation only narrows the attack surface; **upgrading PHP to a patched release is the fix**.

| Environment | How |
|---|---|
| VPS (Debian/Ubuntu, ondrej/php) | `apt update && apt upgrade` (php8.x-* packages), then `systemctl restart php8.x-fpm` or `apache2`. Ubuntu's own packages backport the fix, so the PHP version does not change (`8.3.6`) - switching to the ondrej PPA is not needed to get the fix (see "Distribution backports" above) |
| VPS (RHEL/Alma/Rocky, Remi) | `dnf upgrade 'php*'`, then restart php-fpm / httpd |
| Shared hosting | Ask the provider to upgrade, or pick a patched version in the control panel (cPanel MultiPHP Manager, CloudLinux PHP Selector, Plesk PHP settings). The version you choose there is a branch - the provider still has to install the patched build of that branch. |
| Docker | Rebuild the image on the patched official tag (`php:8.3.36-fpm` etc.) and redeploy |
| Windows XAMPP / WAMP | Install a newer package, or replace the `php` folder with the patched windows.php.net build (same VC/TS variant), keeping your `php.ini` |
| macOS Homebrew | `brew upgrade php` (or `php@8.x`), then `brew services restart php` |

After upgrading, open the dashboard home page as `system_coder` - the red alert must be gone. Then **restore the mitigation settings** to their previous values (see the caution on `memory_limit` below).

---

## Interim mitigation: where to set it

Which file is honoured depends on the SAPI shown in the notice. Two rules apply everywhere:

- **It cannot be done from Raptor's code.** `session.upload_progress.enabled` is a `PHP_INI_PERDIR` setting (`ini_set()` cannot change it), and request upload parsing happens before any PHP code runs. That is why Raptor only *reports* the values and does not set them.
- **Verify in the dashboard, not on the CLI.** The CLI often loads a different php.ini.

### 1. Server php.ini (VPS, localhost) - preferred

Find the file from the `php.ini:` path in the notice (for example `/etc/php/8.3/fpm/php.ini`, `/etc/php/8.3/apache2/php.ini`, `C:\xampp\php\php.ini`, `/opt/homebrew/etc/php/8.3/php.ini`) and set:

```ini
session.upload_progress.enabled = Off
memory_limit = -1
```

Or put them in a separate drop-in so package upgrades do not overwrite them: `/etc/php/8.3/fpm/conf.d/99-mitigation.ini` (Debian/Ubuntu), `/etc/php.d/99-mitigation.ini` (RHEL), `/usr/local/etc/php/conf.d/zz-mitigation.ini` (Docker official image).

Restart the PHP process: `systemctl restart php8.3-fpm` / `systemctl restart apache2` / XAMPP Control Panel -> Apache Stop/Start / `brew services restart php`.

### 2. PHP-FPM pool (SAPI `fpm-fcgi`, VPS)

Per-site and cannot be overridden by the site itself - the most reliable place on FPM. In the pool file (e.g. `/etc/php/8.3/fpm/pool.d/www.conf`):

```ini
php_admin_flag[session.upload_progress.enabled] = off
php_admin_value[memory_limit] = -1
```

Then `systemctl reload php8.3-fpm`.

On FPM, a value set through `.user.ini` may show up in the notice but still be applied too late for the request's body parsing - prefer the pool or php.ini. On shared hosting with FPM, ask the provider to set it in the pool.

### 3. Apache module (SAPI `apache2handler`)

In the virtual host or `httpd.conf` (not overridable from `.htaccess`):

```apache
php_admin_flag session.upload_progress.enabled off
php_admin_value memory_limit -1
```

Or, when only `.htaccess` is available (needs `AllowOverride Options` or `All`):

```apache
<IfModule mod_php.c>
    php_flag session.upload_progress.enabled off
    php_value memory_limit -1
</IfModule>
```

(`mod_php7.c` for PHP 7.) A `php_value` line on a server without mod_php causes a 500 error - keep the `<IfModule>` guard.

### 4. Shared hosting (SAPI `cgi-fcgi`, `litespeed`, `fpm-fcgi`)

- **cPanel:** MultiPHP INI Editor -> Editor Mode -> choose the domain -> add the two lines -> Save. It writes `.user.ini` (and/or `php.ini`) in the domain's document root. CloudLinux PHP Selector -> Options can also set `memory_limit`.
- **Plesk / DirectAdmin:** the domain's PHP settings page ("Additional configuration directives" in Plesk).
- **Manually:** a `.user.ini` file next to `index.php` (Raptor: `public_html/.user.ini`) with the two lines. `.user.ini` is re-read only every `user_ini.cache_ttl` seconds (300 by default) - wait up to 5 minutes before checking.
- If the notice still shows the old values (or the SAPI is `fpm-fcgi`), ask the provider to apply them server-side or, better, to upgrade PHP.

### Caution: `memory_limit = -1`

It removes PHP's memory cap: a runaway script can use all of the server's RAM, and on shared hosting the provider may forbid it or kill such processes. Use it only as long as the advisory requires, and put the previous value back (`128M`, `256M`, ...) right after upgrading PHP. `session.upload_progress.enabled = Off` has no side effect for Raptor (it does not use upload progress).

---

## Checklist

1. Log in as `system_coder` and open the dashboard home page.
2. No alert -> nothing to do. Red alert -> plan the PHP upgrade (table above).
3. If the upgrade has to wait: set the mitigation where the SAPI hint points, restart / reload PHP (or wait up to 5 minutes for `.user.ini`), reload the home page and confirm every setting has a check.
4. After the upgrade: confirm the alert is gone, then restore `memory_limit`.
