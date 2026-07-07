# Session Lifetime (gc_maxlifetime) Configuration Guide

## Background

To keep an admin logged in for a long time, Raptor sets the session **cookie lifetime to 30 days** from `SessionMiddleware` (`session_set_cookie_params(...)`). This way an admin stays logged in even after closing and reopening the browser, and the JWT and CSRF token stored in the session are not lost early. This simple approach has run reliably for years on many Mongolian cPanel / VPS hosts with no extra configuration.

The same `session_set_cookie_params(...)` call (array form) also sets the cookie's security flags explicitly, rather than leaving them to php.ini: `httponly => true`, `samesite => 'Lax'`, and `secure` auto-detected from HTTPS (`HTTPS` / port 443 / `X-Forwarded-Proto`) so the cookie is Secure over HTTPS and still works over plain HTTP in local development.

Because these flags live in the **same** call as the lifetime, "hand session control entirely to PHP by removing the `session_set_cookie_params(...)` call" (described below) now delegates the **security flags too**, not just the lifetime - fully consistent with that promise, just wider in scope. So if you remove the call, set the flags in php.ini to keep the hardening: `session.cookie_httponly=1`, `session.cookie_samesite=Lax`, and `session.cookie_secure=1` (on HTTPS). To change **only** the lifetime while keeping the flags, edit the `lifetime` value in the array instead of removing the whole call. Everything below concerns the **lifetime** only.

This affects the **client-side cookie only**. Raptor does **not** manage session's **server-side** behaviour from code - file cleanup (`session.gc_maxlifetime`) and storage path (`session.save_path`) are left to the host's PHP configuration (php.ini). Forcing the server-side session lifetime from code is unreliable on many environments:

- **Runtime `ini_set()` does not reach the system cleaner.** On Debian/Ubuntu, PHP's own GC is disabled and session files are cleaned by the `sessionclean` cron in `/etc/cron.d/php`. That cron reads the value from **php.ini**, so it completely ignores a runtime `ini_set(gc_maxlifetime, ...)`.
- **Conflict in a shared `/tmp`.** When many sites share one session directory, another tenant's GC (with a shorter `gc_maxlifetime`) deletes your files too.
- **System cron purge.** cPanel/LiteSpeed and macOS purge `/tmp` independently of PHP's settings.

So the server-side session policy (lifetime, storage path, cleanup) belongs to the **developer**, tuned to the specific host. A developer can also hand session control **entirely** to PHP: **remove** the `session_set_cookie_params(...)` line from `SessionMiddleware` and configure the full session lifetime/path/cleanup - **and the cookie security flags** (`cookie_httponly` / `cookie_samesite` / `cookie_secure`, see the note above) - in php.ini / .user.ini (see below).

---

## What Raptor does now (default behaviour)

`SessionMiddleware`:

- **Sets the session cookie lifetime to 30 days** - `session_set_cookie_params(...)`. This is the CLIENT-side cookie only, and is the practical default that runs out-of-the-box on many cPanel/VPS hosts (admins stay logged in across browser restarts).
- Does **not** touch `session.save_path`, `session.gc_maxlifetime`, or `session.gc_probability` from code - those (especially the server-side file cleanup) come from the host php.ini.
- Runs `session_name('raptor')` + `session_start()`, and `session_write_close()` early on routes that do not write to the session (concurrency optimization).

> **To rely entirely on PHP config:** to let the host php.ini govern session lifetime end-to-end, **remove** the `session_set_cookie_params(...)` line from `SessionMiddleware` and configure php.ini instead (see below).

---

## How the developer configures it

For a long session (e.g. 30 days = `2592000` seconds), set **all three values together**:

| Setting | Purpose |
|---------|---------|
| `session.save_path` | store session files in a **private directory** (host cron / other tenants cannot reach it) |
| `session.gc_maxlifetime` | server side - how many seconds a file lives |
| `session.cookie_lifetime` | client side - how many seconds the browser keeps the cookie |

For consistent login UX, set these **close to `RAPTOR_JWT_LIFETIME`**.

### Two rules for every environment

1. Set it at the **php.ini / .user.ini level** - do not rely on runtime `ini_set()` (the system cron does not read it).
2. Set it for the **web SAPI** (apache2 / php-fpm), not just CLI. Restart the web server / FPM after changing it.

---

### 1. Ubuntu / Debian VPS

Edit the php.ini of the relevant SAPI:

- Apache mod_php: `/etc/php/8.x/apache2/php.ini`
- Nginx / PHP-FPM: `/etc/php/8.x/fpm/php.ini`

```ini
session.gc_maxlifetime = 2592000
session.cookie_lifetime = 2592000
```

```bash
sudo systemctl restart apache2     # or: sudo systemctl restart php8.x-fpm
```

**Note:** on Debian/Ubuntu PHP's own GC is disabled (`session.gc_probability = 0`); instead `/etc/cron.d/php` runs `sessionclean` every 30 minutes, cleaning by the `gc_maxlifetime` and `save_path` **read from php.ini**. So the php.ini change above is honoured by the cron and 30 days **actually works**.

- If you use a **custom `save_path`**, set it **in php.ini** (not `.htaccess` / runtime), otherwise `sessionclean` will not find and clean it. Or enable your own GC (`session.gc_probability = 1`) / add your own cron.

Debian default save_path: `/var/lib/php/sessions` (not /tmp).

---

### 2. cPanel shared hosting (no root)

No direct php.ini access, so:

- **cPanel -> "MultiPHP INI Editor"** -> select your domain, set `session.gc_maxlifetime` and `session.cookie_lifetime`, **or**
- `public_html/.user.ini` (cPanel usually uses PHP-FPM / CGI):

```ini
session.save_path = "/home/USERNAME/sessions"
session.gc_maxlifetime = 2592000
session.cookie_lifetime = 2592000
session.gc_probability = 1
session.gc_divisor = 100
```

**Note:** cPanel / CloudLinux actively purges /tmp, so a **private `save_path`** (`/home/USERNAME/sessions`) is the key to making 30 days work. Create that directory and make it writable. `.user.ini` is cached for ~300 seconds, so changes are not instant.

---

### 3. Windows + XAMPP / WAMP

Edit php.ini:

- XAMPP: `C:\xampp\php\php.ini`
- WAMP: tray menu -> PHP -> `php.ini`

```ini
session.save_path = "C:\xampp\tmp"
session.gc_maxlifetime = 2592000
session.cookie_lifetime = 2592000
session.gc_probability = 1
session.gc_divisor = 100
```

Then restart Apache (XAMPP / WAMP Control Panel).

**Note:** Windows has **no system cron** for session cleanup - only PHP's own GC. The XAMPP default is `gc_divisor = 1000` (0.1%), so GC runs rarely and files may accumulate. Solutions:

- Set `session.gc_probability = 1`, `session.gc_divisor = 100` (1%), **or**
- Delete old files via Windows Task Scheduler:

```
forfiles /p "C:\xampp\tmp" /m sess_* /d -30 /c "cmd /c del @path"
```

---

### 4. macOS (Homebrew PHP / MAMP)

macOS 12+ removed the built-in PHP, so most use Homebrew or MAMP.

**Homebrew PHP** (`brew install php`):

- Find php.ini: `php --ini` -> see the "Loaded Configuration File" line. Usually:
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

**MAMP:** edit `/Applications/MAMP/bin/php/php8.x.x/conf/php.ini` and restart the servers from the MAMP menu.

**Note:** macOS has **no** Debian-style `sessionclean` cron. launchd periodic tasks clean /tmp and `/var/folders/...` occasionally (usually files older than 3 days), so sessions in /tmp do not last long. Use a **private `save_path`**, set `session.gc_probability = 1`, or clean via launchd/cron:

```bash
find /Users/USERNAME/.php-sessions -name 'sess_*' -mtime +30 -delete
```

---

### 5. Other environments

| Environment | Where to set | Cleaner |
|-------------|--------------|---------|
| Nginx + PHP-FPM (any OS) | FPM `php.ini`, or pool `.conf`: `php_value[session.gc_maxlifetime] = 2592000` | Debian cron or PHP GC |
| Docker (`php:fpm` image) | `/usr/local/etc/php/conf.d/session.ini` (mount/COPY) | PHP GC (enable `gc_probability`) |
| Apache mod_php (any OS) | `php.ini`, or `.htaccess`: `php_value session.gc_maxlifetime 2592000` | PHP GC / system cron |

> `php_value` in `.htaccess` only works under **mod_php**. Under PHP-FPM it raises a 500 error, so use `.user.ini` on FPM.

---

## If the developer configures nothing

### How long sessions last

With PHP's default values:

- **`session.gc_maxlifetime`** (server side) - default is commonly **1440 seconds (24 minutes)**. Once a session file has not been touched for this long, GC/cron may delete it. In other words, an admin idle for **~24 minutes** may lose the session (host-dependent - some hosts set this higher).
- **`session.cookie_lifetime`** (client side) - PHP's default is **0** (until the browser closes), but **Raptor sets it to 30 days from code** (`SessionMiddleware`). So by default the cookie persists 30 days. If you remove the `session_set_cookie_params(...)` line, the cookie falls back to php.ini's `session.cookie_lifetime` - `0` unless you configure it. That is exactly why `cookie_lifetime` is one of the **three values** in the recipe above: going the php.ini route, it must be set (e.g. `2592000`) alongside `gc_maxlifetime`/`save_path`, otherwise the cookie dies on browser close.

### How it affects Raptor

Raptor's dashboard login stores the **JWT in the session** (`$_SESSION['RAPTOR_JWT']`) and the CSRF token in the session (`$_SESSION['CSRF_TOKEN']`). Therefore:

- If the session is GC'd or the cookie expires, **both the JWT and CSRF token are lost**.
- On the next request `JWTAuthMiddleware` sees no auth and **redirects to login** (the admin must log in again). This is not an error - no data is lost, just a re-login.
- The CSRF token is regenerated at the next login (or via the `Controller::template()` fallback).

**Important:** even though `RAPTOR_JWT_LIFETIME` is 30 days (default), if the session expires earlier the login is capped by the session. Effective "stay logged in" time = **min(session lifetime, JWT lifetime)**. On a host with a short default `gc_maxlifetime`, the admin may be logged out after ~24 minutes even though the JWT is valid for 30 days.

**Bottom line:** by default Raptor sets a 30-day session cookie, so an admin stays logged in even after closing and reopening the browser - simple and reliable in practice. Without **server-side** configuration, however, a login can still be short-lived: if the host's `gc_maxlifetime` is short, the session file may be cleaned up after the admin is idle, forcing a re-login even though the 30-day cookie is still valid (effective time = **min(session file, JWT, cookie)**). For fully reliable, long-lived session management you can apply the server-side configuration above.

---

## Verify the configuration took effect

**Via `phpinfo()`** (note: phpinfo() exposes your `.env` secrets, so deleting it afterwards is essential): create a temporary `phpinfo.php`:

```php
<?php phpinfo();
```

Open it in a browser and check that the **"Local Value"** of `session.gc_maxlifetime` is `2592000` (not the CLI `php -i` - the web SAPI value is what matters). **Delete** `phpinfo.php` when done.

Find the active php.ini: `php --ini`.

---

## Key principles (recap)

1. **Raptor sets the session cookie lifetime to 30 days by default** - to keep a logged-in admin around, `SessionMiddleware` calls `session_set_cookie_params(...)`. This is a client-side cookie only, so an admin stays logged in even after closing and reopening the browser.
2. Set it at the **php.ini / .user.ini level** - runtime `ini_set()` is unreliable because the system cron does not read it.
3. Set **private `save_path` + `gc_maxlifetime` + `cookie_lifetime` together** - a long lifetime only truly works this way; `gc_maxlifetime` alone in a shared /tmp is unreliable.
4. Set it for the **web SAPI** and restart the web server / FPM.
5. For consistent login UX, set the session lifetime **close to `RAPTOR_JWT_LIFETIME`**.
6. With no configuration the server-side session file follows PHP's default `gc_maxlifetime` (usually short) and may be cleaned up, so admins simply re-login - normal, safe behaviour. The client cookie still stays 30 days per #1; **but if the developer removes `session_set_cookie_params(...)` from `SessionMiddleware` without setting `session.cookie_lifetime` in php.ini**, the cookie reverts to PHP's default `0` (until browser close) - removing that line is only safe together with the three-value php.ini recipe above.
