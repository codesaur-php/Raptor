# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/) and this project adheres to [Semantic Versioning](https://semver.org/).

---

## [5.1.0] - 2026-07-22
[5.1.0]: https://github.com/codesaur-php/Raptor/compare/v5.0.0...v5.1.0

### Changed

- **Admin signup requests modal now lists every signup request, including email-unverified ones.** `UsersController::requestsModal('signup')` previously filtered `verified_at IS NOT NULL`, so requests whose confirmation email was never clicked were invisible to admins - they sat in the `signup` table (blocking their UNIQUE username/email) with no way to review or clean them up. The modal now loads all rows and `signup-index-modal.html` shows a fourth status badge: `unverified` (pending + `verified_at` empty) alongside `pending` / `approved` / `rejected`. Unverified requests get no Accept button (double opt-in stays mandatory; `signupApprove()` keeps its server-side 403 guard for unverified email) but can be rejected - and a rejected row can then be permanently deleted to Trash, freeing the name/email for a new request. The users manual (MN/EN) is updated to document the four states, replacing badge names (`waiting`/`deactivated`) that had drifted from the implementation.

### Fixed

- **Error toasts rendered as blue info instead of red - `'error'` is not a valid `Notify()` type.** `Notify()` in `dashboard.js` accepts only `success`/`danger`/`warning`/`primary`; any other value silently falls back to the cyan info style. Six template call sites (`news-view.html` x2, `comments-view.html`, `devrequest-create.html`, `devrequest-view.html`, `trash-index.html`) passed `'error'` directly, and `RBACController::setRolePermission()`'s catch block responded with `'type' => 'error'` which `rbac-alias.html` feeds straight into `Notify(response.type ?? 'warning', ...)` - all seven now use `'danger'`. The valid-type rule (including for server `'type' =>` values consumed by `Notify(response.type ...)`) is documented in the `Notify()` docblock and CLAUDE.md UI Conventions.
- **RSS feed generation spammed `code.log` with PHP 8.1+ deprecations and emitted 1970 pubDates when `published_at` was NULL.** Rows published before the `published_at` column existed (legacy data) carry NULL there; `SeoController::rss()` passed that straight into `strtotime()` in both the merge-sort comparator and the `<pubDate>` builder - every feed request logged a "Passing null to parameter" deprecation per NULL row, and `gmdate()` over the resulting `false` produced `Thu, 01 Jan 1970` publication dates in the feed. Both call sites now guard NULL (and unparseable values): the sort treats missing dates as 0 so those items order last, and `<pubDate>` falls back to the current time instead of 1970. The sitemap's `lastmod` lines were already NULL-safe via `?? 'now'` and are unchanged.
- **Web visit statistics cache died with 'Data too long' (1406) on real traffic - MySQL `web_log_cache` columns upgraded TEXT -> MEDIUMTEXT.** A single day's aggregated JSON (`news_data` etc.) exceeds 400KB on a busy site, overflowing the 64KB TEXT limit, so `refreshCache()` failed on insert and the stats cache stopped advancing (on hosts without strict mode the data was silently truncated to corrupt JSON instead). The MySQL `CREATE TABLE` in `WebLogStats::ensureCacheTable()` now uses MEDIUMTEXT (16MB) for all seven `*_data` columns, so fresh installs are correct from the start. Already-deployed MySQL/MariaDB databases need a one-time migration: `ALTER TABLE web_log_cache MODIFY actions_data MEDIUMTEXT DEFAULT NULL, MODIFY pages_data MEDIUMTEXT DEFAULT NULL, MODIFY news_data MEDIUMTEXT DEFAULT NULL, MODIFY products_data MEDIUMTEXT DEFAULT NULL, MODIFY orders_data MEDIUMTEXT DEFAULT NULL, MODIFY ips_data MEDIUMTEXT DEFAULT NULL, MODIFY ua_data MEDIUMTEXT DEFAULT NULL;`. PostgreSQL is unchanged - its TEXT type is unlimited, so no migration is needed there. The MySQL `CREATE TABLE` also now takes its charset/collation from `RAPTOR_DB_CHARSET`/`RAPTOR_DB_COLLATION` (same `utf8mb4`/`utf8mb4_unicode_ci` fallbacks as `DatabaseConnection`) instead of a hardcoded `CHARSET=utf8mb4` with no COLLATE - previously the table silently took the server's default collation for that charset, which could differ from every other table in the database.
- **motable showed "no data in the table" while an AJAX table was still loading.** The constructor unconditionally ran `setBody()` -> `setReady()`, which overwrote the initial "Loading table ..." label with the empty-table label whenever the tbody had zero rows - exactly the state every AJAX-loaded index table (news, users, products, orders, ...) is in between construction and the fetch response, misleading admins into thinking the table was empty. The constructor now computes the state immediately only when the table already has a `<tbody>` element; a table without `<tbody>` keeps the loading label until `setBody()`/`setReady()`/`error()` is called. This convention (AJAX-loaded tables omit `<tbody>` from markup, server-rendered tables always write the tag - even when the row loop produces nothing) already held across all shipped templates and is now documented in CLAUDE.md UI Conventions plus a bilingual coupling comment in `motable.js`. Asset reference bumped to `motable.js?v=1` in `dashboard.html`.
- **Signup form never sent its Turnstile token - every signup failed with 400 when `RAPTOR_TURNSTILE_SECRET_KEY` was configured.** The Turnstile widget lives inside the `#register` form, but the signup submit JS builds its JSON payload by hand (not via `FormData`), so the hidden `cf-turnstile-response` input never reached the server and `LoginController::spamCheck()` always verified an empty token. `login.html` now includes `'cf-turnstile-response'` in the signup payload, and `LoginController::signup()` unsets the field before `SignupModel->insert()` (the `signup` table has no such column).
- **Turnstile widgets were not reset after AJAX submits - retrying after a server error (or reusing the form after success) always failed siteverify.** Turnstile tokens are single-use and `form.reset()` does not reset the widget. All fetch-based forms carrying a `cf-turnstile` widget (contact form in `contact.html`, comment form in `news.html`, review form in `product.html`, signup form in `login.html`) now call `window.turnstile.reset()` in their `.finally()` blocks. `order.html` is a plain browser POST (full page reload re-renders the widget), so it needs no reset.
- **Every forgot password request displayed as "used" in the admin requests modal.** The status badge in `forgot-index-modal.html` checks `row['is_active'] == 0` first, but `ForgotModel` had no `is_active` column - so the expression was `null == 0` (true) for every row, including brand-new requests. The `is_active` column (`tinyint`, default 1) is restored on `ForgotModel`, and the reset flow in `LoginController::setPassword()` now consumes the token with `deactivateById()` instead of `deleteById()` - so all three states (used / expired / ready) display correctly. Token lookups (`forgotPassword()`, `setPassword()`) filter `is_active=1` so a used token stays invalid, and the resend cooldown counts only active requests (used tokens no longer block a new request, matching the old delete behavior). Already-deployed databases need a migration: `ALTER TABLE forgot ADD COLUMN is_active TINYINT NOT NULL DEFAULT 1;` (MySQL/MariaDB) / `ALTER TABLE forgot ADD COLUMN IF NOT EXISTS is_active SMALLINT NOT NULL DEFAULT 1;` (PostgreSQL); fresh installs create the column automatically.

---

## [5.0.0] - 2026-07-08
[5.0.0]: https://github.com/codesaur-php/Raptor/compare/v4.5.2...v5.0.0

### Changed

- **BREAKING: `application/raptor/` merged into `application/dashboard/` - `application/` now holds exactly two apps, `dashboard/` and `web/`.** The separate "framework core" layer is gone: all former raptor modules (authentication, content, exception, localization, log, mail, migration, notification, organization, rbac, template, trash, user, plus the root Controller/middleware/DatabaseConnection files) now live in `application/dashboard/`, and the `Raptor\` PHP namespace is renamed to `Dashboard\` across the whole tree (application code, tests, `public_html/index.php`, composer PSR-4 maps). `Raptor\Application` and `Dashboard\Application` are merged into a single concrete `Dashboard\Application` that registers the full middleware pipeline and all sixteen routers. Nothing changes at runtime - routes, permissions, DB tables, env variables (`RAPTOR_*`) and the `codesaur/raptor` package name stay as they are - but any downstream project importing `Raptor\` classes must rename its imports. Rationale: after `composer create-project` the whole tree is the developer's project code, and the core-vs-app split kept suggesting otherwise - customization guidance across code comments and docs now says to edit files directly instead of subclassing controllers or overriding routes.
- **Critical warning comments are now bilingual (Mongolian + English).** Comments guarding critical invariants - hidden coupling between distant files (TemplateService cache key vs ReferencesController invalidation, LogsController OFFSET vs dashboard.js infinite-scroll stop, MenuSeed vs Permissions::RESERVED), ordering rules (middleware registration order, handle()-outside-try), and security rules (JWT secret with no default, log masking, username enumeration, /protected/* anonymous fall-through, root-user RBAC guards, badge mount-naive map keys) - now carry an English version below the Mongolian text so non-Mongolian-reading contributors cannot miss them. Ordinary comments stay single-language; the rule is documented in CLAUDE.md Documentation Style.
- **Bootstrap CDN upgraded 5.3.6 -> 5.3.8** (latest 5.3.x patch; SRI integrity hashes updated to the official values) across `dashboard.html`, `login.html`, `login-reset-password.html`, web `index.html` and `page-404.html`. Bootstrap Icons stays at 1.13.1 (still latest).
- **`RAPTOR_SIGNUP_VERIFY_HOURS` default raised from 48 to 72 hours (3 days).** The signup email verification link now stays valid for 3 days when the env variable is not set; `.env.example` updated to match. Explicitly configured environments are unaffected.
- **Exception handlers no longer log 404s in production.** `ErrorHandler`, `JsonExceptionHandler` and the web `ExceptionHandler` now skip `error_log()` for 404s unless `CODESAUR_DEVELOPMENT` is on - unknown-route hits are overwhelmingly bot scans and were bloating `logs/code.log` with noise. Bot traffic remains fully visible in the web server's access log (Apache/nginx `access.log`, hosting panel Raw Access Logs) with IP and User-Agent.
- **File management consolidated into one module: `application/dashboard/file/` (`Dashboard\File`).** The `content/file` module (`FileController`, `FilesController`, `FilesModel` + the file modal templates) moved up out of `content/` and merged with the `protected/` module (`ProtectedFilesController`, `ProtectedRouter` - the latter renamed to `FileRouter` as the file module now owns it) - general upload/attachment handling and protected file serving are one concern. Namespaces `Dashboard\Content\File*` and `Dashboard\Protected\*` became `Dashboard\File\*`; routes, route names, permissions and the `{table}_files` tables are unchanged. The /files management routes moved from `ContentsRouter` into `FileRouter` (paths and route names unchanged), so the module owns all its routes. `FilesModel`'s docs were generalized: it is a companion table for attaching files to any model's records (`{table}_files` via `setTable()`), not just the shipped content tables.
- **Badge org-scoping: record-level filtering and system-wide viewers.** For modules listed in `BadgeController::orgScopedModules()` the count query now filters by `COALESCE(record_organization_id, auth_user.organization_id)` in the log context - a tenant-scoped module's controller can log the record's owning organization as `record_organization_id` (same convention as `record_id`), so the badge reaches the record's organization even when the change was made by an admin logged into another organization (previously only the actor's org was consulted, so a system-org admin editing org B's record never badged org B's users). Entries with neither key (old logs, web frontend) still count for all admins. New `isSystemWideViewer()` hook (default: current organization is the system primary org, `id=1`) joins `system_coder` in bypassing scoping entirely - the same user sees all organizations' activity while switched into the system organization and only their own org's while switched into a common one.
- **`auto-deploy.sh` logs connectivity outages as one line down, one line up.** When GitHub was unreachable, every cron run appended a fresh fetch error and the log grew without bound. The scaffold now keeps an offline marker file (`/tmp/PROJECT-auto-deploy.offline`): the first failed fetch writes a single `git fetch амжилтгүй...` line, subsequent failures stay silent, and the first successful fetch writes a single `git fetch сэргэв...` line and clears the marker - the quiet gap between the two lines is the outage duration.

### Removed

- **`Application::overrideDashboardLayout()`, the `dashboard_layouts` request attribute and `DashboardTrait::layout()`.** The dashboard layout templates (`dashboard.html`, `alert-no-permission.html`, `modal-no-permission.html` in `application/dashboard/template/`) are project code - edit them in place; the indirection layer (and its `DashboardLayoutOverrideTest`) is gone. `DashboardTrait` now resolves the three layout files directly via `__DIR__`.
- **Subclass/route-override customization guidance removed from code comments and docs.** BadgeRouter/BadgeController, FileRouter/ProtectedFilesController, the exception handlers, `Web\Application` and the module guides (CLAUDE.md, READMEs, api.md) no longer present "subclass the controller and override the route" as a customization path - the documented path is editing the module in place.

---

## [4.5.2] - 2026-07-07
[4.5.2]: https://github.com/codesaur-php/Raptor/compare/v4.5.1...v4.5.2

### Fixed

- **SSH deploy silently ignored its entire `exclude:` list - every deploy would have wiped server-side runtime data.** The `burnett01/rsync-deployments` action has no `exclude` input, so the list the workflow passed was discarded (GitHub Actions only emits an "unexpected input" warning) and the job ran plain `rsync -avz --delete`. That both uploaded repo-only files (`.git`, `docs/`, `tests/`, `phpunit.xml`) to the server and, far worse, deleted every server-generated file the repo does not track: uploaded images under `public_html/public/`, protected files, `logs/`, `cache/` and the per-environment migration audit trail under `database/migrations/`. All filters now live in `switches` as native rsync rules, ordered include-before-exclude (rsync is first-match-wins): guard-file `--include`s first, then anchored content excludes in the `--exclude=/folder/*` form - which transfers the folder itself (so it is created on a fresh server) while leaving its contents out of both the transfer and `--delete` (rsync never deletes excluded files without `--delete-excluded`).
- **Name-based deploy excludes also excluded the `application/dashboard/protected/` module.** FTP's `**/protected/**` glob and robocopy's bare-name `/XD protected` match a directory called `protected` at any depth, so `ProtectedFilesController.php` / `ProtectedRouter.php` never reached the server - fatal on a fresh deploy, since `Dashboard\Application` registers `ProtectedRouter` unconditionally. The FTP job no longer excludes runtime folders at all (see Changed), and the robocopy `/XD` entries for runtime folders are now full source+destination path pairs (`"%GITHUB_WORKSPACE%\protected" "%DEPLOY_PATH%\protected"`) - the source side keeps the folder out of the transfer, the destination side keeps `/MIR` from deleting the server's copy, and nested module folders are untouched. Verified with a full robocopy simulation (runtime files survive `/MIR`, the module deploys, stale files still get mirrored away).
- **`logs/` was never created by the FTP/SSH/Windows deploy paths, silently losing the PHP error log.** The workflow header claimed "logs/ is auto-created on the server" but nothing creates it - `public_html/index.php` points `error_log` at `logs/code.log` and PHP does not create missing directories, so on a deployed server without the folder every logged error vanished without trace. All three paths now deploy the runtime folders themselves (with their `.htaccess` guard files) while leaving their contents alone; the false header note is replaced with the real semantics.
- **`cache/` was wiped on every SSH/Windows deploy.** It appeared in no exclude list, so `rsync --delete` / `robocopy /MIR` mirrored it back to the two guard files the repo tracks on every run - a needless cache cold start (and a behavior the FTP path did not share). It is now protected like the other runtime folders on all three paths.

### Changed

- **Runtime folders unified: one gitignore pattern, one deploy semantic.** `cache/`, `logs/` and `protected/` now each carry the same self-contained `.gitignore` (`*`, `*/`, `!.gitignore`, `!.htaccess`) plus a `deny from all` `.htaccess` - `protected/` gets its own `.gitignore` (its block moves out of the root `.gitignore`), `logs/` gains the `.htaccess` it was missing. The deploy semantic across all three workflow paths (plus `public_html/public/` and `database/migrations/`, which fall in the same class): the folder and its guard files deploy and are created on the server; the runtime contents (cache entries, logs, uploads, migration SQL) are never uploaded, overwritten or deleted. Each path implements this with its own mechanism, so the three filter lists intentionally differ: FTP relies on the action's state-file (it never deletes files it did not upload, so runtime folders need no exclude at all), rsync uses include-before-exclude `/folder/*` rules inside `switches`, robocopy uses `/XD` source+destination path pairs plus small non-`/MIR` guard-file copies. The workflow header documents this so the lists do not get "harmonized" back into a bug.

---

## [4.5.1] - 2026-07-07
[4.5.1]: https://github.com/codesaur-php/Raptor/compare/v4.5.0...v4.5.1

### Changed

- **Directory trees in the docs de-duplicated to a single app-level diagram.** The root `README.md` "Directory Structure" tree is now the only tree, trimmed to the app level (no per-module enumeration); `docs/mn/README.md` and `docs/en/README.md` replace their deep trees with a short pointer to it (module locations stay documented in each module's own section 6 subsection), and `CLAUDE.md` drops its module lists too. The router enumeration in the root README "Quick Architecture" block is generalized the same way (`Routers (one per feature module, registered in Application.php)` instead of naming each router), and the docs READMEs' table-of-contents entry for section 6 drops its module-name list and hardcoded subsection ranges (`6.1-6.13 Core | 6.14-6.29 Shop, ...`) - the numbering shifted on every module addition, making it the most drift-prone line of all. Deep trees duplicated in four files rotted silently on every module move (the 4.5.0 development-module relocation left the root README tree showing `development/` under `raptor/`) - one shallow tree has nothing left to drift.

---

## [4.5.0] - 2026-07-07
[4.5.0]: https://github.com/codesaur-php/Raptor/compare/v4.4.1...v4.5.0

### Added

- **Topbar organization switcher.** The old standalone `/organization/user/list` page (`OrganizationUserController` + `organization-user.html`) is replaced by a dropdown on the topbar brand area, shown when the user has more than one organization. `DashboardTrait::getUserOrganizations()` builds the list (all active organizations for `system_coder`, membership-only for everyone else; the currently selected organization is always included; organization `id=1` always sorts first when present, the rest by name). The dropdown gets a search filter, result counter and scrollable list when there are more than 10 organizations (`initOrgSwitcher()` in `dashboard.js`; the filter hides items with inline `display:none !important` because Bootstrap's `d-flex` on the items would otherwise win). Assets bumped to `dashboard.css?v=4` / `dashboard.js?v=6` (one `?v=` increment from v4.4.1 for the whole release).
- **`User::hasRoleAlias(string $alias)`.** Returns true when the user holds any role under the given alias (role keys are `{alias}_{name}`). Lets multi-tenant apps gate cross-organization visibility by role alias.
- **Hardcoded sidebar home link.** `dashboard.html` sidebar now starts with a permission/alias-independent "Dashboard" link (`'home'|link`, `bi-speedometer2`).
- **cPanel Git deploy scaffold.** Templates (`docs/conf.example/.cpanel.yml.example`, `docs/conf.example/auto-deploy.sh.example`) and developer guide (`docs/mn/CPANEL.md`) for cPanel Git + cron auto-deploy - a fallback for SSH-less shared hosting the `.github/workflows/deploy.yml` jobs cannot reach: `.cpanel.yml` task list, self-update-safe `auto-deploy.sh` (flock, CLI-SAPI php lookup, `composer install` on lock change, `composer dump-autoload -o` on autoload-only `composer.json` change), placeholder table and the two-phase sequencing rule for changing the deploy script itself. The READMEs present this scaffold as **deploy path D** - an explicit last-resort fallback listed after the A) FTP / B) SSH / C) Windows-runner workflow jobs, not under the server config examples table, so being hosted on cPanel is not read as "must use the CPANEL.md scaffold" (a real-world D case: the National Data Center of Mongolia shared hosting for government portals, where no workflow job can reach the server).
- **Global search now covers organizations, dev-requests, messages, comments and reviews** in addition to news, pages, products, orders and users. Every new source keeps the permission invariant (gated exactly like its module's index page): organizations -> `system_organization_index`, messages/comments -> `system_content_index`, reviews -> `system_product_index`, dev-requests -> visible to any authorized user but limited to own/assigned rows without `system_development` (mirrors `DevRequestController::list()`). Click targets follow each module's own view style: dev-requests open their view page, comments jump to the news page `#comments` anchor (via `news_id`), organizations and messages load their view modal inside `#static-modal` (new `modal: true` flag in `SOURCE_META`), reviews link to the reviews index (no per-record view).
- **Topbar redesigned: quick icons (search | language | theme), a flat profile link and a logout button with confirmation replace the expanding search input, the "Language & Options" modal and the user dropdown.** Search opens a centered modal (`#global-search`, also via Ctrl+K / Cmd+K) with the global record search - same `SearchController` endpoint, debounced, grouped with source badges, arrows + Enter keyboard navigation, Esc closes (`initGlobalSearch()` replaces `initTopbarSearch()` in `dashboard.js`). If an app has not registered the search route (`|link` resolves to `'#'`), `initGlobalSearch()` removes the search icon and exits; individual results whose view-route pattern resolves to `'#'` (module removed while search kept) are hidden instead of linking nowhere. Language is a dropdown listing the active languages with flag icons via flagcdn.com (`en` maps to the `us` flag, same convention the old modal used; current one checked; selection is session-persisted via the existing `language` route, then reload) - hidden when only one language is active. Theme is a light/dark dropdown applied instantly via `localStorage` + `data-bs-theme`, no reload (`initTopbarQuick()` in `dashboard.js`). The avatar + name link straight to the admin's own profile page (`user-update` route; tooltip carries the new `my-profile` keyword) - no dropdown in between. Logout is an icon-only button after a second `.topbar-sep` separator; because it is a plain GET link one accidental click away, it always asks for confirmation (`initLogoutConfirm()`): a Bootstrap modal (`#logout-confirm-modal` in `dashboard.html`) when Bootstrap JS is available, falling back to native `confirm()` when the CDN failed to load - logout stays functional even fully offline, and custom dashboard layouts that keep the shipped logout button must also keep the modal markup (or accept the fallback). Spacing in the actions area is flex-gap based with optical compensation on the separators (icon buttons carry ~.5rem of invisible hit-area), so both separators sit visually centered. The organization name truncates with an ellipsis (`.topbar-brand-name`, 45vw / 30vw below 576px) so long names never squeeze the quick icons or wrap the topbar on small screens; below 576px spacing tightens. All topbar `{{ ... }}` interpolations of admin-editable text (organization name, user name, menu titles, language title) are `|e`-escaped, and the theme toggle + search modal carry `aria-label`/`role`. New translation keywords `theme-dark` / `theme-light` / `theme` / `my-profile` / `confirm-logout` (deployed systems need a migration insert).
- **`Application::overrideDashboardLayout()` - swap the dashboard layout from your own Application.** The three layout templates `DashboardTrait` renders internally (`dashboard.html`, `alert-no-permission.html`, `modal-no-permission.html`) can now be replaced from the developer's own Application constructor: `$this->overrideDashboardLayout('dashboard.html', __DIR__ . '/template/dashboard.html')`. Same explicit-override philosophy as router `override()` - the override is visible in the bootstrap. The map is injected as the `dashboard_layouts` request attribute in `Raptor\Application::handle()` and resolved by the new `DashboardTrait::layout()` helper; registration fail-fasts with `InvalidArgumentException` when the custom file does not exist. Pages with their own route (login etc.) are intentionally out of scope - override their route instead. Covered by `tests/Unit/Template/DashboardLayoutOverrideTest.php`.

### Changed

- **Sidebar badge system moved into the dashboard app layer, and made multi-tenant aware.** `BadgeController`, `BadgeRouter` and `AdminBadgeSeenModel` moved from `application/raptor/template/` (`Raptor\Template`) to `application/dashboard/badge/` (`Dashboard\Badge`; new PSR-4 map; `BadgeRouter` registration moved from `Raptor\Application` to `Dashboard\Application`). Route names (`dashboard-badges`) and the JS/CSS are unchanged. This puts `BADGE_MAP` / `PERMISSION_MAP` / badge behavior next to the rest of the code a project is most likely to customize. New: `BadgeController::orgScopedModules()` (default `[]`) - modules it lists have their badges scoped to the viewing admin's current organization (so org A's activity no longer badges an org B admin), via a new `context.auth_user.organization_id` recorded in every log entry by `Controller::log()`. NULL-org log rows (old data / web-frontend) still count for all admins (backward-compatible), and `system_coder` bypasses scoping as a cross-tenant superuser. The shipped content modules are global (no `organization_id`) so the default list is empty; multi-tenant apps list theirs in `orgScopedModules()` (edit directly, or override in a subclass).
- **Framework file cache moved from `protected/cache/` to a dedicated top-level `cache/` directory** (outside the document root, sibling of `logs/`, kept in git via its own `.gitignore` with contents ignored). Runtime-generated cache is framework state, not user content, so it no longer shares the `/protected` folder that `ProtectedFilesController` serves. `ContainerMiddleware` now points the `cache` service at `dirname(SCRIPT_FILENAME, 2) . '/cache'`. As a consequence `ProtectedFilesController` lost its cache-specific `read()`/`setFolder()` guards (the cache is no longer under `/protected`, and hardcoding a blocked `cache` subfolder in a generic protected-files controller was a latent footgun for any project whose protected files legitimately include a folder named `cache`). Existing deployments regenerate cache automatically (12h TTL + explicit invalidation); the old `protected/cache/` can be deleted.
- **`system_coder` cross-tenant access is now derived from the role, not materialized as data.** `JWTAuthMiddleware` loads RBAC before the organization check and branches: coders only need the target organization to exist and be active; regular users still need an `organizations_users` membership row. `LoginController::selectOrganization()` no longer auto-inserts a membership row when a coder switches into a foreign organization - previously those rows accumulated as fake memberships and, worse, outlived the `system_coder` role itself (removing the role no longer revoked the access it had silently granted). Existing deployments should clean up previously auto-inserted rows with a one-off migration (`DELETE FROM organizations_users WHERE organization_id <> 1 AND user_id IN (SELECT ur.user_id FROM rbac_user_role ur INNER JOIN rbac_roles r ON ur.role_id = r.id WHERE r.alias = 'system' AND r.name = 'coder')`).
- **`ProtectedFilesController` moved to `application/dashboard/protected/` (`Dashboard\Protected`) with an overridable `authorizeRead()` hook.** The class serves files from the document-root-external `/protected` folder via `GET /dashboard/protected/file` (`ProtectedRouter`, registered in `Dashboard\Application`). No shipped module uses protected storage (every module writes to `/public` via `FileController::setFolder()`), so it was relocated from `application/raptor/content/file/` to `application/dashboard/protected/` (new `Dashboard\Protected\` PSR-4 map; route moved out of `ContentsRouter`). `read()` now calls `protected function authorizeRead(string $relativePath): bool` before serving; the default is permissive (any authenticated user may read; `system_coder` always). Projects serving sensitive files tighten `authorizeRead()` with their module's permission or tenant-ownership rule (e.g. the file's owning organization id - see the commented example in the method) - either by editing it in place or by subclassing and `$this->override()`-ing the route to their controller.
- **Development module moved to the dashboard app layer.** `DevelopmentRouter`, `DevRequestController`, `DevRequestModel` and `DevResponseModel` moved from `application/raptor/development/` (`Raptor\Development`) to `application/dashboard/development/` (`Dashboard\Development`; new PSR-4 map; router registration moved from `Raptor\Application` to `Dashboard\Application`). Routes, route names, permissions, the `dev_requests` log channel and DB tables are unchanged - deployed systems need no migration. Same rationale as the badge and protected-files relocations: the dev-request workflow is dashboard-app code a project is likely to customize, not shared framework infrastructure. Two accuracy fixes rode along: `getDevRecipients()` now qualifies its permission lookup with `p.alias = 'system'` (matching by `name` alone can hit a same-named permission under another alias), and the docs' misleading "protected by `development:development` RBAC permission" line was replaced with the real access rules (every route requires login; users without `system_development` see only their own/assigned requests; `system_development` manages all).
- **Dashboard home route split: named `'home'` moved to `/home`, `/` stays as an unnamed alias.** Sidebar active-detection is prefix-based (`href.startsWith(link)`), so a home link pointing at the dashboard root was active on every page. `/` remains registered (unnamed) because the public web layout links to `{{ index }}/dashboard` directly. Because the bare root does not prefix-match the `/home` link, `dashboard.html`'s inline script activates the home link when the current path equals the dashboard root (template-level check against `{{ index }}/dashboard`) - so the Dashboard item is highlighted right after login too.
- **Coder-only sidebar items moved out of System into their own "Coder" section.** Database Migrations, Trash and Manage Menu (all gated by `system_coder`) now live under a fourth top-level sidebar section (mn "Кодер" / en "Coder", position 990) instead of being appended to System. The section's parent row itself carries `permission => 'system_coder'` and `alias => 'system'`, so non-coders (and coders browsing a non-system organization) never see an empty section header. Deployed systems migrate with: `INSERT INTO raptor_menu (parent_id, position, alias, permission, is_visible, created_at) VALUES (0, 990, 'system', 'system_coder', 1, NOW());` then insert the mn/en titles into `raptor_menu_content` for the new id and `UPDATE raptor_menu SET parent_id = <new_id> WHERE permission = 'system_coder' AND href IS NOT NULL;` (menu cache invalidates on next menu CRUD or 12h TTL - or clear `cache/`).
- **moedit AI models are now .env-configurable and defaults moved off the retiring GPT-4o family.** `AIHelper` had `gpt-4o-mini` (HTML mode) and `gpt-4o` (vision/OCR) hardcoded; OpenAI retired the GPT-4o family from ChatGPT on 2026-02-13 and an API sunset is expected to follow. The model ids now come from `RAPTOR_OPENAI_MODEL` / `RAPTOR_OPENAI_VISION_MODEL` (.env, optional) with safe defaults in `AIHelper::DEFAULT_MODEL` (`gpt-5-mini`) and `DEFAULT_VISION_MODEL` (`gpt-5.1`), so future model deprecations are a one-line config change instead of a code edit. Existing API keys keep working - keys are not tied to model generations.
- **`Permissions::RESERVED` constant centralizes the reserved-permission guard.** The inline `system_coder` checks that 4.4.1 shipped in `Permissions::insert()`/`updateById()` moved into `assertValidIdentity()`, driven by a new public `Permissions::RESERVED = ['system_coder']` list - one place to extend when another role-name key must never become a permission. The error message now reads `"..." is a reserved permission (it is an RBAC role name)`.

### Removed

- **`OrganizationUserController`, `organization-user.html` and the `organization-user` route** - superseded by the topbar organization switcher. The usermenu dropdown entry pointing at the page is gone as well.
- **"Language & Options" modal (`user-option` route, `TemplateController::userOption()`, `user-option-modal.html`)** - superseded by the topbar quick icons (search / language / theme). The usermenu dropdown entry is gone as well.
- **Unused translation keywords `average-rating` and `confirm-deactivate`** dropped from `TextInitial.php` - neither is referenced anywhere in `application/`, `public_html/` or `tests/` (verified by scanning all 288 seeded keywords against every php/html/js file; these two never appear even as a bare substring, so dynamic construction is ruled out). Deployed systems can clean their live database with `DELETE FROM localization_text_content WHERE parent_id IN (SELECT id FROM localization_text WHERE keyword IN ('average-rating', 'confirm-deactivate')); DELETE FROM localization_text WHERE keyword IN ('average-rating', 'confirm-deactivate');`.

### Security

- **Session hardening: session ID is regenerated on privilege change, and the session cookie now sets HttpOnly / Secure / SameSite explicitly.** `LoginController::entry()` (login) and `selectOrganization()` (organization switch) now call `session_regenerate_id(true)` before writing the new JWT/CSRF token, so a session ID an attacker may have fixed before authentication is invalidated at login (the session cookie is long-lived, which made fixation more valuable). `SessionMiddleware` replaces the single-argument `session_set_cookie_params(2592000)` with the array form, adding `httponly => true` (blocks `document.cookie` theft via XSS), `secure => <https-detected>` (auto-detected from `HTTPS`, port 443, or `X-Forwarded-Proto` so it is on over HTTPS and off over plain-HTTP dev), and `samesite => 'Lax'` (CSRF mitigation). The 30-day lifetime is unchanged; removing the call still hands session config entirely to php.ini.
- **SQL injection in `FilesController::list()` on PostgreSQL.** The `GET /files/list/{table}` route segment is untyped, so the router matches it with `DEFAULT_REGEX` (which allows single quotes) and `rawurldecode`s the value. `list()` interpolated it straight into the table-existence query (`... AND tablename = '{$table}_files'`) without going through `Model::setTable()` sanitization like the sibling `post()`/`modal()`/`update()`/`delete()` actions do, so an authenticated `system_content_index` user could run a UNION injection through the `{table}` parameter on the PostgreSQL branch (the MySQL branch was safe via `quote()`). `list()` now sanitizes `{table}` with the same `preg_replace('/[^A-Za-z0-9_-]/', '', ...)` whitelist `index()` uses before any interpolation.
- **Stored XSS via public-visitor comments, reviews and contact messages.** The template engine has no autoescape and the `nl2br` filter does not escape, so anonymously-submitted content (news comments, product reviews, contact messages) was rendered raw with `{{ ...|nl2br }}` - a visitor posting `<img src=x onerror=...>` executed JavaScript in every reader's browser, and (via the dashboard views) in authenticated admin sessions. The six sinks now escape before line-breaking with `{{ ...|e|nl2br }}`: `web/content/news.html`, `web/shop/product.html`, `dashboard/shop/products-view.html`, `raptor/content/messages/messages-view-modal.html`, `raptor/content/news/comments-view.html`, `raptor/content/news/news-view.html`. (The email notifiers already did `htmlspecialchars()` before `nl2br` server-side; this brings the on-page views in line.)
- **Reflected XSS in the development-mode exception stack trace.** `ErrorHandler` and the web `ExceptionHandler` render `json_encode($throwable->getTrace(), JSON_PRETTY_PRINT)` inside a `<pre>` when `CODESAUR_DEVELOPMENT` is on; `json_encode` escapes `/` but leaves `<`/`>` intact, so an `<img src=x onerror=...>` payload appearing in a trace argument executed in the dev error page. Both now add `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`. Dev-mode only, but it is the same reflected-XSS class the upstream `codesaur/http-application` 7.0.1 `ExceptionHandler` just hardened.
- **Dashboard global search leaked shop data to non-shop users.** The PRODUCTS and ORDERS blocks in `SearchController::search()` were gated with `system_content_index`, while the Products/Orders index pages themselves require `system_product_index`. A user holding only the content permission could not open the shop pages but could still read product rows and - worse - order rows with customer PII (name, email, phone) through topbar search. Both blocks now require `system_product_index`, restoring the invariant that search results are a subset of what the user could see by browsing.
- **moedit AI endpoint had no permission gate and no cost limit.** `AIHelper::moeditAI()` (`POST /dashboard/content/moedit/ai`) only checked `isUserAuthorized()`, so any logged-in dashboard user - including a zero-permission `viewer` - could drive the server's OpenAI key: run the prompt as a free general-purpose LLM proxy (the `prompt` field is fully caller-controlled), and in vision mode make one `gpt-4o` call per image through an unbounded `foreach` loop with no throttle, letting a single request run up arbitrary API spend. Three fixes: (1) a `canUseAI()` gate requiring `system_content_insert/update` or `system_product_insert/update` (the permissions for the news/pages/products modules that actually embed moedit; `coder` bypasses as usual) - a viewer now gets 403; (2) vision mode caps images at `MAX_IMAGES_PER_REQUEST` (8) per request; (3) a per-user fixed-window rate limit (`RATE_LIMIT_MAX` 30 calls / `RATE_LIMIT_WINDOW` 60s, vision counting one call per image) stored in the file cache - session can't be used because dashboard `SessionMiddleware` closes it early on non-login routes; the limiter is fail-open if the cache service is unavailable, since the permission gate is the primary control. Also hardened the `catch` block to coerce a non-integer exception code (OpenAI returns string codes like `invalid_api_key`) to a valid HTTP status instead of risking a `TypeError`.

### Fixed

- **Hard-deleting a user or organization with a photo/logo reported failure and orphaned the file.** `UsersController` and `OrganizationController` hard-delete called `$this->getPublicPath()`, a method that exists nowhere on the controller chain. The DB row was already deleted and copied to trash, then the undefined-method `Error` fired and was swallowed by the surrounding `catch (\Throwable)`, so the admin got an error JSON response for an operation that actually succeeded, the attempt was logged as a failure, and the physical photo/logo was never unlinked (orphaned on disk). Replaced with a new `FileController::getStoredFilePhysicalPath()` helper that correctly maps a stored public URL (`{scriptPath}/public/...`, `rawurlencode`d filename) back to its physical path (strips the script-path prefix, `rawurldecode`s, prepends the document root).
- **Sidebar badge JS now degrades cleanly when the badge route is not registered.** The `|link` template filter returns `'#'` for an unknown route name (fail-safe), but `'#'` passed the truthy guard in `initSidebarBadges()`: the loader then `fetch('#')`-ed the current dashboard page itself on every load - doubling the server render work per page view, with the JSON parse failing silently. `initSidebarBadges()` now treats `'#'` as "route not registered in this app" and skips entirely, so an app that drops `BadgeRouter` gets a clean page with no wasted requests. (The new `initGlobalSearch()` ships with the same `'#'` guard - see the topbar entry under Added.)
- **Dashboard global search and order-list errors returned HTTP 200 instead of an error code.** `SearchController::search()` and two `OrdersController` handlers passed `$err->getCode()` (0 for a plain exception) to `respondJSON()`, which leaves a code-0 response at HTTP 200 - the global search modal then rendered a genuine query failure as an empty "no results". These three now pass `$err->getCode() ?: 500`, matching the convention already used in the badge/reviews/web controllers.
- **Global search silently swallowed real query errors.** Every per-module block in `SearchController::search()` used an empty `catch (\Throwable) {}` so that a not-yet-created module table (partial install) is tolerated - but this also hid genuine query regressions (bad column, driver mismatch), making a module vanish from search with no trace. The catches now log the failure via `error_log()` when `CODESAUR_DEVELOPMENT` is on, keeping production quiet while surfacing regressions in development.
- **Missing translation keywords `draft` and `not-published` rendered as raw slugs.** `comments-view.html` (published badge) and `products-view.html` / `news-view.html` / `page-view.html` (draft tooltip) used `'not-published'|text` / `'draft'|text`, but only `published` existed in `TextInitial.php` - the `|text` filter falls back to the literal slug, so admins saw "not-published" / "draft" instead of translated text. Both keywords added (deployed systems need a migration insert).
- **RBAC permission cache was dead code - every authenticated request reloaded all roles/permissions from the DB.** `JWTAuthMiddleware` read the `cache` service via `$request->getAttribute('container')?->get('cache')`, but `ContainerMiddleware` (which registers the container) runs AFTER `JWTAuthMiddleware` in the pipeline, so the `container` attribute did not exist yet - the `?->` short-circuited to `null`, the `rbac.{userId}` cache never populated, and a full `new RBAC(...)->jsonSerialize()` DB load ran on every request (with `RBACController`'s cache-clear having nothing to clear). `JWTAuthMiddleware` now builds the cache directly via the new `CacheService::fromDefaultPath()` factory (shared with `ContainerMiddleware`, same `cache/` directory, so `RBACController` invalidation still works). Regression test added in `CacheTest`.

### Dependencies

- **Upgraded the codesaur packages.** `container` v3.1.3 -> v3.2.0, `dataobject` v10.1.0 -> v10.1.1, `http-application` v7.0.0 -> v7.0.1, `http-client` v2.1.1 -> v2.1.2, `http-message` v3.0.3 -> v3.0.4, `router` v6.0.0 -> v6.0.1, `template` v4.1.0 -> v4.1.1. No application code changes were required; Raptor (mysql/pgsql only) benefits automatically from several upstream fixes: `http-message` now strips a duplicated `host:port` from generated absolute URLs (previously `base_url`, sitemap, RSS and redirect URLs came out as `http://host:8080:8080` on non-standard ports) and validates header names/values against CRLF injection; `router` escapes literal dots in static route segments (`/sitemap.xml` no longer also matches `/sitemapXxml`) and fixes partial-parameter `generate()`; `template` fixes the ternary/`??`/`and`/`or` parser leaving tokens unconsumed inside parentheses and function arguments; and `http-application`'s `ExceptionHandler` now escapes the error title/message/host. The full test suite (836 tests) passes on the new versions.

---

## [4.4.1] - 2026-06-27
[4.4.1]: https://github.com/codesaur-php/Raptor/compare/v4.4.0...v4.4.1

### Fixed

- **RBAC permission uniqueness contradicted the `{alias}_{name}` key design.** The runtime permission key is built as `{alias}_{name}` (see `Role::fetchPermissions()` and `RBAC`), so the same `name` is meant to be reusable under different aliases - e.g. `system_request_update` and `common_request_update`. But `Permissions` declared the `name` column `->unique()` on its own, so only one row per `name` could ever exist; the second `INSERT` failed with a raw `Duplicate entry '...' for key 'name'` and the two keys could never coexist. This was dormant in the shipped framework only because every seeded permission carries a globally-unique, module-prefixed `name` (`user_insert`, `content_index`, ...) all under `alias = 'system'`. The constraint is now composite `UNIQUE (alias, name)`, matching the key semantics.

### Changed

- **`rbac_permissions` unique constraint: `(name)` -> `(alias, name)`.** `Permissions` no longer marks `name` as `->unique()`; instead `__initial()` adds `ADD CONSTRAINT rbac_permissions_uq_alias_name UNIQUE (alias, name)` (raw `ALTER`, works on both MySQL and PostgreSQL).
- **`RolePermissionSeed` permission lookups narrowed to `alias = 'system'`.** Both the `admin` bulk assignment and `assignPermissions()` matched permissions by `name` alone (`p.name IN (...)`). With the composite constraint allowing same-named permissions under different aliases, those queries could match and assign more than the intended system permissions, so `AND p.alias = 'system'` is now part of both - this is exactly the seed's intent (default roles get the default system permissions).

### Added

- **`Permissions::assertValidIdentity()` insert/update validation.** Surfaces clear errors before the database constraint is hit, following the existing `system_coder` guard style (English `\RuntimeException`, surfaced to the UI via the controller's `getMessage()`): (1) rejects an `alias` containing `_`, because the separator-less `{alias}_{name}` concatenation would otherwise let `(system_request, update)` and `(system, request_update)` collide into the same key - aliases are clean grouping values (`system`, `common`, ...) tied to the sidebar menu and never need an underscore; (2) pre-checks `(alias, name)` for duplicates and throws `Permission "..." already exists.` instead of leaking the raw SQL `Duplicate entry` exception.

---

## [4.4.0] - 2026-06-24
[4.4.0]: https://github.com/codesaur-php/Raptor/compare/v4.3.1...v4.4.0

### Added

- **moedit: Video Poster feature.** After a video is inserted (uploaded file or YouTube embed), moedit now offers to capture the video's first frame and use it as the content's header image. For uploaded videos the frame is drawn on a canvas and auto-uploaded via the existing `opts.upload` endpoint; for YouTube, the thumbnail is fetched (`maxresdefault.jpg`, falling back to `hqdefault.jpg`). The offer appears as a modal dialog; accepting triggers upload and sets the header image inline without a page reload. New methods: `_confirmDialog` (generic confirm dialog helper), `_captureVideoPosterBlob` (canvas frame capture with 15 s timeout and cross-origin guard), `_youtubePosterBlob` (thumbnail fetch with quality fallback), `_applyHeaderImageFromBlob` (upload + preview + callback chain), `_offerVideoPoster` (orchestrates the prompt after insert).
- **moedit: `_extractYouTubeId` centralized.** YouTube ID extraction is now a single prototype method covering `youtube.com/watch?v=`, `youtube.com/embed/`, `youtube.com/v/`, `youtube.com/shorts/`, `youtu.be/`, and bare 11-character IDs. Previously duplicated between the YouTube dialog and embed path.
- **moedit: `_insertYouTubeEmbed`, `_doInsertImage`, `_doInsertMedia`, `_insertImageByUrl`.** Insertion helpers refactored or extracted from inline code to reusable prototype methods, shared between the direct-insert and video-poster code paths.
- **`tests/Unit/Content/ProtectedFilesTraversalTest.php`.** Unit tests for the `read()` directory-traversal fix, in two layers. (1) Source-code guards: asserts `read()` canonicalizes both the protected dir and the requested file with `realpath()`, anchors the prefix check with `DIRECTORY_SEPARATOR`, treats an unresolvable path as forbidden, and throws `403 Forbidden`. (2) Behavioral: replicates `read()`'s containment algorithm against a real temp filesystem - legit root/nested files are served, while `../` traversal payloads are blocked (parent escape `/../secret.txt`, nested-then-up `/sub/../../secret.txt`, the `/../../../somesecretfolder/dangerinfo.txt` example, and the `/../protected-evil/x.txt` prefix-collision sibling), plus empty and nonexistent names. Null-byte and absolute-path payloads are not covered.

### Fixed

- **`ProtectedFilesController::read()`: directory traversal (security).** `read()` serves protected files from the `?name=` query parameter. It built the path with `getDocumentPath('/../protected' . $name)` and then only checked whether the result fell inside the cache directory; the `protected/` root itself was never enforced. Because `getDocumentPath()` just concatenates the string (it does not resolve `../`), a crafted `name` such as `/../../../etc/passwd` could escape the protected folder entirely and read arbitrary server files. `read()` now resolves the canonical path with `realpath()`, returns `404` unless that path is an existing file (`is_file()`, which - unlike `file_exists()` - also rejects directories), and returns `403` if the canonical path does not begin with the protected root plus `DIRECTORY_SEPARATOR` (the trailing separator also prevents prefix-collision bypasses such as `protected-evil/`). All downstream operations - extension/basename blocklist, MIME sniff, headers, `readfile` - run on the canonical `$realFile`, not the raw concatenated path. The cache-directory check is layered on top of the containment check.
- **moedit `destroy()` header-image button listener leak.** The click listeners added to the Change / Remove buttons in `_initHeaderImage()` were anonymous closures not stored anywhere, so `destroy()` never removed them. Post-destroy clicks called `this._selectHeaderImage()` / `this._removeHeaderImage()` against a destroyed instance (`this.opts === null`), crashing with TypeError. Handlers are now stored as `_boundHandlers.headerImageChange` / `_boundHandlers.headerImageRemove` and explicitly removed in `destroy()` before refs are nulled.
- **moedit `_captureVideoPosterBlob`: `seeked` listener lingered after capture.** The `seeked` event listener for `captureFrame` was registered without `{ once: true }`, so it remained active after the frame was drawn. Added `{ once: true }`.
- **moedit `_cleanToVanillaHTML`: XSS filter incomplete.** The `href`/`src` attribute sanitizer blocked `javascript:`, `vbscript:`, and `data:text/html` but allowed other scriptable `data:` MIME types: `data:text/xml`, `data:text/javascript`, `data:application/xhtml+xml`, `data:application/xml`, `data:application/javascript`, and `data:application/ecmascript`. All are now blocked by the same regex.
- **moedit table / accordion dialog: zero silently became three.** `parseInt(rowsInput.value) || 3` (and the equivalent for cols and accordion count) treated a user-entered `0` as "use the default 3" with no feedback - the dialog closed and inserted a 3x3 table. Replaced with `parseInt(value, 10)` and a `!(n >= 1)` guard: on invalid input an error notification is shown and the dialog stays open.
- **moedit dialogs: debounce timer not cleared on close.** The URL-preview `setTimeout` inside the input handler of the Image-URL, YouTube, Facebook, Twitter, and Map dialogs was not cancelled when the dialog closed. If the user closed the dialog before the debounce delay elapsed, the timer fired and accessed detached DOM elements. `clearTimeout(debounceTimer)` is now called in every `closeDialog()`.
- **moedit `_selectHeaderImage`: `prevSrc` absolutized URL.** The upload-failure rollback read `this.headerImagePreview.src` (the DOM `src` property, which the browser absolutizes to a full URL) as the previous image to restore. On hosts with a path like `/uploads/img.jpg` this produced `http://host/uploads/img.jpg`, which could behave differently from the original relative path. Now reads `this._headerImagePath` (the internal server-relative path), matching the same fix already applied to `_applyHeaderImageFromBlob`.
- **moedit `_selectHeaderImage`: file input orphaned on cancel in old browsers.** The hidden `<input type="file">` was removed from the DOM on `cancel` (modern browsers) and on `change`, but browsers without `cancel` support (Firefox < 91, Safari < 16.4) left it dangling indefinitely. A `window.focus` fallback (500 ms delay, `fileInputDone` flag) now removes the input when the window regains focus and no file was selected.

### Changed

- **moedit assets cache-busted to `?v=4`.** `moedit.js` and `moedit.ui.js` version query strings updated from `v=3` to `v=4` in all six dashboard editor templates: news-insert, news-update, page-insert, page-update, products-insert, products-update.
- **moedit manuals updated.** `moedit-manual-en.html` and `moedit-manual-mn.html` document the new video/YouTube header-image capture prompt.

---

## [4.3.1] - 2026-06-22
[4.3.1]: https://github.com/codesaur-php/Raptor/compare/v4.3.0...v4.3.1

### Reverted

- **The server-side session management added in 4.3.0 is reverted - server-side session lifetime now follows host defaults.** Raptor no longer forces `session.save_path`, `session.gc_maxlifetime`, or `session.gc_probability` from code. Removed: the `protected/sessions` storage block and gc tweaks in `index.php`, the `protected/sessions/` block in `ProtectedFilesController`, and the `RAPTOR_SESSION_SAVE_PATH` / `RAPTOR_SESSION_LIFETIME` env keys. Reason: forcing a 30-day `gc_maxlifetime` from code was unreliable on shared hosting (runtime `ini_set` is ignored by the Debian `sessionclean` cron; shared `/tmp` and host crons purge files independently) and added a session-file accumulation / cleanup burden, while not being the actual fix for the reported 403s. Raptor does NOT configure the server-side session lifetime - that is left to the host's `php.ini` / `.user.ini`, and developers may optionally configure it - see `docs/en/SESSION-LIFETIME.md` and `docs/mn/SESSION-LIFETIME.md`.

### Added

- **`docs/en/SESSION-LIFETIME.md` and `docs/mn/SESSION-LIFETIME.md`** - a per-environment guide (Ubuntu/Debian VPS, cPanel shared hosting, Windows XAMPP/WAMP, macOS Homebrew/MAMP, Nginx-FPM/Docker/mod_php) for configuring `session.gc_maxlifetime` correctly. Linked from each language's README and `api.md`.

### Kept

- `SessionMiddleware` still sets the session cookie lifetime to 30 days via `session_set_cookie_params(...)` (client-side cookie only - this is what keeps an admin logged in across browser restarts).
- The WAF-compatibility feature from 4.3.0 (`MethodOverrideMiddleware`, `BodyEncodingMiddleware`, `csrfFetch` verb tunneling + body encoding, `RAPTOR_WAF_BODY_ENCODING`) is unchanged.

---

## [4.3.0] - 2026-06-19
[4.3.0]: https://github.com/codesaur-php/Raptor/compare/v4.2.3...v4.3.0

### Added

- **Shared hosting / WAF compatibility layer.** Many cPanel/LiteSpeed hosts with mod_security broke dashboard saves in three ways; all are now handled framework-wide with no per-module code. (1) `MethodOverrideMiddleware` (new) tunnels PUT/PATCH/DELETE through POST via the `X-HTTP-Method-Override` header for hosts that block those verbs before PHP - POST-only, never overrides to GET, so CsrfMiddleware still applies. (2) `BodyEncodingMiddleware` (new) base64-decodes form field values when the client sends `X-Body-Encoding: base64`, defeating WAF XSS rules that flag HTML/JS-like rich-text content in the POST body (strict base64, recursive, header-gated, files/field-names untouched; works because every controller reads `getParsedBody()`, none read `$_POST`). Both are registered in `Raptor\Application` and `Web\Application`. The client side lives in `csrfFetch()` (`dashboard.js`): it rewrites verbs and base64-encodes `FormData` string values (UTF-8-safe, files skipped), gated by the `<meta name="waf-body-encoding">` flag that `Controller::template()` emits. Single encode / single decode, so already-base64 content (inline `data:` image URIs) round-trips byte-for-byte. New env vars (see below); `dashboard.js` bumped to `?v=5`. Tests: `MethodOverrideMiddlewareTest`, `BodyEncodingMiddlewareTest`.

### Fixed

- **Intermittent CSRF 403 on shared hosting from lost sessions.** Sessions stored in the shared `/tmp` were purged by system cron / a short `session.gc_maxlifetime`, emptying `$_SESSION['CSRF_TOKEN']` so a later mutating request failed `CsrfMiddleware`. `public_html/index.php` now stores sessions in `protected/sessions` (outside `/tmp`, already web-blocked by `.htaccess`) and raises `gc_maxlifetime`. `SessionMiddleware` cookie params are corrected: `session_set_cookie_params()` was passing an absolute timestamp where a duration (seconds) is expected; it now passes a real lifetime plus `httponly`/`samesite=Lax` and `secure` only on HTTPS (honouring `X-Forwarded-Proto` behind a proxy). `ProtectedFilesController` now blocks `protected/sessions/` from the file-read API the same way `protected/cache/` is blocked.

### Configuration

- New `.env` keys under "Shared Hosting / WAF Compatibility": `RAPTOR_SESSION_SAVE_PATH` (empty = auto `protected/sessions`), `RAPTOR_SESSION_LIFETIME` (seconds, default `2592000` = 30 days; drives both the session cookie and server-side gc), and `RAPTOR_WAF_BODY_ENCODING` (default `true`; set `false` on hosts without a body-inspecting WAF to skip client-side base64 encoding).

### Changed

- **Started using `codesaur/template` ^4.1.0**, which adds the Twig-compatible `in` / `not in`, `ends with`, `matches`, and `is even` / `is odd` operators. The shop product template now uses `{% if file['type'] in ['image', 'video', 'audio'] %}` instead of the previous `or`-chain.

---

## [4.2.3] - 2026-06-17
[4.2.3]: https://github.com/codesaur-php/Raptor/compare/v4.2.1...v4.2.3

### Fixed

- **A stray `W` before the opening `<?php` tag in `application/raptor/migration/MigrationRouter.php` is removed.** The file's first line was `W<?php` - a leftover keystroke. Because the character sits outside the PHP tag, PHP treats it as raw output and echoes it the moment the file is autoloaded, printing a stray `W` at the top of the response body and triggering "headers already sent" warnings on any request that loaded the migration router. The line is now a clean `<?php`.

### Changed

- **Code-style cleanup only (no behavior change):** removed a redundant blank line in `public_html/assets/moedit/moedit.ui.js` (before `_insertImage`), continuing the formatting pass started in 4.2.1.

### Notes

- **Release-process correction for Packagist version immutability.** `v4.2.1` was first tagged at commit `1fc8c5d` and published to Packagist, then the tag was deleted, re-created on a later commit, and force-pushed. Packagist rejected the update because a published stable version's source/dist reference is immutable - once a tag is published it must keep pointing at the same commit forever. To realign, `v4.2.1` was restored to its originally published commit (`1fc8c5d`) and the two follow-up fixes above were shipped as this fresh version instead of by re-tagging. Going forward the rule is one version number = exactly one commit: published tags are never moved or recreated; any further change ships under a new, higher version (which is why this release is `4.2.3`).

---

## [4.2.1] - 2026-06-17
[4.2.1]: https://github.com/codesaur-php/Raptor/compare/v4.2.0...v4.2.1

### Fixed

- **Editing a menu item with no `href`/`icon` no longer shows the literal string `"null"` in the form, nor emits PHP warnings.** This was one bug with two surfaces, both in the dashboard menu manager (`application/raptor/template/`). On the client (`manage-menu.html`), the edit/view modal populated the inputs with `record.icon` / `record.href` directly; for a section-header menu (Contents, System, etc.) those columns are `NULL`, and assigning `null` to an `<input>`'s `value` IDL attribute coerces to the string `"null"` (the `value` setter has no `[LegacyNullToEmptyString]`), so the field showed `null` instead of being empty. Now guarded with `record.icon ?? ''` / `record.href ?? ''`. On the server (`TemplateController::manageMenuUpdate()`), the change-detection loop compared `$record[$index]` / `$record['localized'][$key][$index]` against the submitted value; for a header menu with no `href`/`icon` column those keys are undefined, raising `Undefined array key "href"`/`"icon"` warnings in `error.log` on every menu update. Both comparisons now use `?? null` (kept as `null`, not `''`, so the `!=` change check still distinguishes an unset column from a submitted empty value).

### Changed

- **Applying a migration now auto-clears the cache.** A migration can write to any table - including the ones whose rows are cached (`rbac_*` permissions, `raptor_menu`, `localization_*` translations, settings). Previously those caches kept serving stale data after a successful apply until their 12-hour TTL expired or an unrelated admin CRUD action happened to invalidate them. `MigrationController::apply()` now calls `cache->clear()` (guarded by `hasService('cache')`, fail-safe) right after a successful apply, matching the full-clear pattern already used for RBAC changes. Only runs on `$result['ok']`; a failed apply leaves the cache untouched. Documented in `database/migrations/README.md` (workflow step 4) and the controller PHPDoc.
- **`codesaur/dataobject` upgraded from `^10.0.1` to `^10.1.0`** (`composer.json` + `composer.lock`). The 10.1.0 release broadens cross-driver column type conversion in `TableTrait::getSyntax()`, so a column type declared for one database resolves to a valid native type on the others instead of reaching the engine verbatim and raising a runtime SQL error - e.g. `double`/`float` and the `*blob`/`binary`/`varbinary` family now map correctly to PostgreSQL (`double precision`/`real`/`bytea`), while `jsonb`/`uuid`/`inet`/`cidr`/`bytea`/`double precision` map back on MySQL and SQLite. It also stops emitting an invalid `(length)` suffix on length-less types (no more malformed DDL like `bytea(16)`). This directly strengthens the framework's "raw SQL/models must work on both MySQL and PostgreSQL" invariant; no application code changes were required (the public API is unchanged from 10.0.x).
- **Code-style cleanup only (no behavior change):** removed redundant blank lines (block-start/block-end and double blanks), fixed indentation, switched single-quoted template expressions containing inner quotes to backticks, and converted Unicode box-drawing tree diagrams to ASCII across PHP, HTML template, and Markdown files.

---

## [4.2.0] - 2026-06-16
[4.2.0]: https://github.com/codesaur-php/Raptor/compare/v4.0.0...v4.2.0

### Removed

- **PHPStan static analysis removed entirely.** The `phpstan/phpstan` dev dependency (added in 4.1.1) was dropped from `require-dev`, the `phpstan` and `phpstan:baseline` Composer scripts were removed, and the three config files (`phpstan.neon.dist`, `phpstan-bootstrap.php`, `phpstan-baseline.neon`) were deleted. `composer.lock` and `vendor/` were synced (`composer update phpstan/phpstan`), so the package is gone from the dependency tree. No runtime/request-path impact - PHPStan was dev-only and already excluded from production via `composer install --no-dev`.

---

## [4.1.1] - 2026-06-16

### Security

- **Log secret masking no longer silently misses `*_token` keys.** `Logger::encodeContext()` masked `PASSWORD` via substring match but checked `PIN`, `JWT`, `TOKEN` with an exact `in_array()`, so realistic keys like `_token`, `csrf_token`, `access_token` were written to `*_log.context` in plaintext - even though `Controller::log()` auto-injects the full parsed request body into `server_request.body`. The login form's spam `_token` field was leaking this way today. `TOKEN` is now matched as a substring (alongside `PASSWORD`) via a single precompiled regex, while `PIN` and `JWT` stay on exact `in_array()` matching on purpose: as short substrings they would over-mask legitimate keys (`PIN` is a substring of `mapping`, `opinion`, `shipping`; masking the wrong data is the same class of silent failure in reverse).

### Fixed

- **JSON error endpoints no longer return HTTP 200 on an exception with code 0.** `BadgeController::badges()`/`seen()` and `LogsController::retrieve()` passed `$err->getCode()` straight to `respondJSON()`; a plain exception thrown without an explicit HTTP code carries `0`, which falls outside the 100-599 range check and leaves the status at the default `200` - so a real server-side failure reached the client as `200 OK` and the `fetch().ok` check passed, swallowing the error. These now use `$err->getCode() ?: 500`, matching the fallback already used by `LogsController::errorLogRead()`.

### Added

- **PHPStan static analysis (dev-only).** `phpstan/phpstan` added to `require-dev` (excluded from production via `composer install --no-dev`; zero runtime/request-path impact). Config in `phpstan.neon.dist` runs at a conservative `level: 1` over `application/`, with `phpstan-bootstrap.php` defining the runtime-only `CODESAUR_DEVELOPMENT` constant for analysis. The 301 pre-existing findings are frozen in `phpstan-baseline.neon` so the check is green and only NEW code is analyzed; the level can be raised over time. Run with `composer phpstan`; regenerate the baseline with `composer phpstan:baseline`. This catches the class of typo that caused the 4.0.0 `ReasonPrhase` bug (undefined-class reference) automatically. PHPStan reads code structure/types only - Mongolian text in strings/comments/docblock descriptions is never flagged, and code formatting/naming is out of scope (that is PHP_CodeSniffer/CS-Fixer territory).

### Changed

- **HTTP status code validation switched from a `ReasonPhrase::STATUS_*` constant lookup to a numeric 100-599 range check (RFC 9110).** `Controller::headerResponseCode()`, `Controller::respondJSON()`, `Raptor\Exception\JsonExceptionHandler`, `Raptor\Exception\ErrorHandler`, and `Web\Template\ExceptionHandler` previously validated a code with `\defined(ReasonPhrase::class . "::STATUS_$code")`. That pattern is silently fragile: a wrong class reference (the `ReasonPrhase` typo fixed in 4.0.0) resolves to a literal string and `\defined()` just returns `false`, so validation cannot distinguish "unknown code" from "wrong class name". Validation is now `\is_numeric($code) && $code >= 100 && $code <= 599`, which has no class coupling and cannot be disabled by a symbol typo. `respondJSON()` now delegates status handling to `headerResponseCode()`, removing a duplicated check and an inconsistency (the old `respondJSON` used `\is_int()`, so it rejected numeric-string codes like `'403'` that `headerResponseCode` accepted). The now-unused `use codesaur\Http\Message\ReasonPhrase;` import was removed from all four files; the `ReasonPhrase` class itself is unchanged and still used for PSR-7 reason-phrase text. Docs (`docs/en/api.md`, `docs/mn/api.md`) updated to describe the 100-599 range.
- **`delete-language` route renamed to `language-delete`** (`LocalizationRouter`) to match the `{entity}-delete` naming convention used by every other delete route (`text-delete`, `news-delete`, `user-delete`, etc.). The `{{ 'delete-language'|link }}` reference in `localization-index.html` was updated; the unrelated `.delete-language` CSS class on the button is unchanged. No DB impact (route names are code-only).
- **Reference-table cache coupling documented.** Added comments in `TemplateService::loadAllForCode()` (the literal `reference.templates.$code` read/write key) and the three `ReferencesController` `invalidateCache("reference.$table.{code}")` calls, recording the invariant that only the `templates` reference table is cached, so invalidating any other table is a harmless no-op.

### Removed

- **`templates_index` permission removed** from `PermissionsSeed.php` and the `manager`/`editor` assignments in `RolePermissionSeed.php`. It was redundant: it only gated the "Reference Tables" sidebar menu entry while `ReferencesController` actually authorizes on `system_content_index`. The `MenuSeed` entry for `/references` now uses `system_content_index`, aligning menu visibility with the controller's real access check. Also dropped from the RBAC manual tables (`rbac-manual-{mn,en}.html`). **Deploy note:** for already-deployed databases, write a migration to delete the `templates_index` rows from `rbac_permissions`/`rbac_role_permission` and to update the `raptor_menu` row's permission to `system_content_index` (seed files only run on fresh installs).

### Security

- **Removed the hardcoded fallback secret for the login anti-spam HMAC token.** `LoginController` generated and verified the login form's spam token with `$_ENV['RAPTOR_JWT_SECRET'] ?? 'raptor-form-secret'`. The fallback is a public, source-visible string, so a deployment that forgot to set `RAPTOR_JWT_SECRET` would silently sign tokens with a known key, making the anti-spam HMAC forgeable while still appearing to work. Spam-token handling is now centralized in `SpamProtectionTrait`: a new `getSpamSecret()` (fail-loud - throws `RuntimeException` if `RAPTOR_JWT_SECRET` is unset, no default) and `generateSpamToken($formName, $ts)`. `LoginController` (generate + verify) and the web `ContactController`, `NewsController`, `ShopController` (which each previously duplicated a private `getJwtSecret()` and an inline `hash_hmac()` generation call) now route both generation and validation through `generateSpamToken()`, so the two sides provably share one formula and one fail-loud secret source. The four duplicate `getJwtSecret()` methods were deleted.

### Fixed

- **`ProtectedFilesController::read()` now returns a real `401` for unauthenticated requests instead of being short-circuited by the login redirect.** The route (`/dashboard/protected/file`) is in the dashboard app, where `JWTAuthMiddleware` redirected every unauthenticated non-login route to `/dashboard/login` *before* the controller ran - so the controller's own `if (!isUserAuthorized()) throw 401` never executed, and a protected-file URL embedded in an `<img>`/download for a logged-out user received `302` + login HTML instead of a clean `401`. `JWTAuthMiddleware` now exempts the `/dashboard/protected/*` path segment from the login redirect (alongside the existing `login` exemption): on auth failure it falls through anonymously so `read()` runs and returns its own `401`/`403`/`404`. Established as a convention: every route under `/dashboard/protected/*` (current and future) falls through anonymously and must enforce its own auth (return `401`/`403`) instead of relying on the redirect - so adding a new protected route needs no middleware change. (Pairs with the earlier `headerResponseCode` range-check fix, which is what now actually emits the `401`.) The route stays at `/dashboard/protected/file` so the mount-aware `generateRouteLink('protected-file-read')` links generated across the dashboard controllers keep resolving.
- **Protected file serving now sends `Content-Disposition` and `Content-Length`.** `ProtectedFilesController::read()` previously emitted only `Content-Type` + `readfile()`. Since the route URL carries no file extension (`/dashboard/protected/file`), a saved file defaulted to the name `file` with no extension. `read()` now adds `Content-Disposition: inline; filename="<basename>"` (a save/download gets the real name and extension; browsers that can render the type may show it inline) and `Content-Length` (enables download progress). Whether a given type renders inline or downloads remains the browser's/OS's decision - the server only supplies the correct headers.

---

## [4.0.0] - 2026-06-13
[4.0.0]: https://github.com/codesaur-php/Raptor/compare/v3.1.0...v4.0.0

This cycle consolidates database access behind a single `DatabaseConnection` factory, adds a fully file-based per-user SQL migration system, adopts the `codesaur/http-application` v7 application-wide mount feature (prefix-naive routers mounted at `/dashboard`), moves CSRF protection from an app-wide middleware to explicit per-route middleware, and renames the authenticated file-serving controller and its storage folder from `Private*`/`private/` to `Protected*`/`protected/`. A round of correctness fixes (middleware control flow, return types, cross-driver SQL handling, defensive I/O checks) is also included.

### Added

- `Raptor\DatabaseConnection` (`application/raptor/DatabaseConnection.php`) - single PDO factory used by `public_html/index.php` and tests. `connect()` reads `RAPTOR_DB_DRIVER` from `.env` (`mysql` | `pgsql`) and returns a ready PDO. Web and Dashboard share the same connection via `$request->withAttribute('pdo', $pdo)` set in the entry point.
- Fully file-based, per-user migration system under `database/migrations/{userId}-{username}/[ran/]` - no tracking table; state is derived from file location (`*.sql` = pending, `ran/*.sql` = applied). The folder is git-ignored so each environment uploads its own SQL via `/dashboard/migrations` (requires `system_coder`). SQL files are plain statement lists; an optional first-line `--` comment becomes the UI summary.
- `MigrationSecurityScanner` (`application/raptor/migration/MigrationSecurityScanner.php`) - static SQL scanner with case-insensitive, comment- and string-aware pattern matching against sensitive tables (`users`, `rbac_*`, `organizations*`, `localization_language`, `raptor_menu`) and DCL (`GRANT/REVOKE/CREATE-DROP-ALTER USER`); apply requires a typed `CONFIRM` when any pattern matches. Also flags `CREATE TABLE` to nudge coders toward defining a Model with `setTable()` (auto-creates the table on first use) instead of duplicating that in a migration.
- Upload UI on `/dashboard/migrations` with per-folder grouping, per-row view/apply/delete actions, and a CONFIRM modal for sensitive applies.
- SHA-256 hash + summary + statement count logged to `dashboard_log` for every upload, apply, and delete (`action: migration-upload | migration-apply | migration-delete`).
- Manual pages: `application/dashboard/manual/migrations-manual-{mn,en}.html`.
- Tests: `MigrationSecurityScannerTest` (pattern matches, case-insensitivity, comment/string false-positive avoidance) and `MigrationRunnerIntegrationTest` (apply/move-to-ran, failure-stays-pending, path-traversal rejection, cross-OS folder name sanitization).
- Delete action on the reviews moderation list (`application/dashboard/shop/reviews-index.html`), gated by `system_product_delete` and reusing the existing `products-reviews-delete` route - previously reviews could only be deleted from the product view page.

### Removed

- `application/raptor/MySQLConnectMiddleware.php` and `application/raptor/PostgresConnectMiddleware.php` - replaced by the single `DatabaseConnection` factory wired in `index.php`.

### Changed

- `codesaur/http-application` upgraded to v7.0.0 (multi-router + application-wide mount feature). Dashboard routers are now prefix-naive and the Dashboard app is mounted at `/dashboard` via `->mount('/dashboard')` in `index.php`.
- **CSRF protection moved from app-wide to per-route.** `Application` no longer registers `CsrfMiddleware` globally; instead every mutating route (POST/PUT/PATCH/DELETE and `GET_POST`/`GET_PUT` compounds) attaches `->middleware([CsrfMiddleware::class])` (69 routes across 12 routers; login routes are exempt). The middleware now only validates; token provisioning moved to login and `Controller::template()` (reads from session, regenerates as a fallback for old sessions). This makes each route's CSRF requirement explicit at the router.
- `PrivateFilesController` renamed to **`ProtectedFilesController`**, and the storage folder `private/` to `protected/` (cache moved to `protected/cache/`). Access is gated on authentication only (not per-owner), so "protected" is more accurate than "private". Route is `/dashboard/protected/file` (name `protected-file-read`); `.gitignore` updated. **Deploy note:** the folder is git-ignored, so each environment must rename `private/` to `protected/` manually.
- `database/migrations/*` is git-ignored (only `.gitkeep` and `README.md` tracked).
- `BadgeController` file-count badge for `/dashboard/migrations` globs `*/*.sql` to count pending files across user folders (excluding `ran/`).
- `getUserFolderPath()` sanitizes username for cross-OS safety: whitelist `[A-Za-z0-9._-]`, strip leading/trailing `. - _ ` (Windows NTFS trailing-dot stripping), fallback to `user` for empty / `.` / `..`, cap at 50 chars.
- Private property names no longer use a leading underscore (PSR-12 compliance). `User`: `$_rbac` -> `$rbac`. `FileController`: `$_overwrite` -> `$overwrite`, `$_size_limit` -> `$size_limit`, `$_allowed_exts` -> `$allowed_exts`, `$_upload_error` -> `$upload_error`. Internal only - all properties are `private`, so the public API is unchanged.

### Fixed

- **`ReasonPhrase` class typo** - the class name was misspelled `ReasonPrhase` across `Controller`, `ErrorHandler`, `JsonExceptionHandler`, and web `ExceptionHandler`. Since `::class` resolves to a literal string without requiring the class to exist, `\defined(ReasonPrhase::class . "::STATUS_$code")` and `\class_exists(ReasonPrhase::class)` always returned false. As a result `Controller::headerResponseCode()` returned early for every code and never called `http_response_code()` - so all error responses (401/403/404/500, e.g. `ProtectedFilesController::read()` rejecting an unauthenticated request or a `protected/cache/` access) silently fell back to `200 OK` with an empty body. Corrected to `ReasonPhrase` everywhere; HTTP error codes are now emitted as intended.
- **JWTAuthMiddleware** - `$handler->handle()` is now called exactly once outside try/catch. Previously it was called inside `try`, so a downstream controller exception was caught here and masked as a login redirect (or double-handled on login pages); exceptions now propagate to the ErrorHandler as intended.
- **OrganizationUserModel::retrieve()** - return type `: array` -> `: array|false`. `return false` on a no-row result threw a TypeError, which broke the `system_coder` organization-switch flow.
- **WebLogStats** - dashboard home order stats queried a non-existent `orders` table (always 0); now queries `products_orders`.
- **NewsController::update()** - now captures the `updateById()` result (`$updated`); the success log stores the actual record instead of `null`.
- **PagesController::view()** - avoids a malformed `id=` SQL condition for a top-level page (NULL `parent_id`).
- **SettingsController** - reads `*_removed` form fields with `?? 0` to avoid undefined-array-key warnings.
- **SearchController** (dashboard) - NEWS/PAGES/USERS branches are wrapped in inner try/catch, matching PRODUCTS/ORDERS (degrades gracefully on partial installs).
- **MigrationRunner::splitStatements()** - SQL splitting is now driver-aware: PostgreSQL dollar-quoting (`$$...$$`) is parsed only on pgsql, and MySQL `\'` backslash escapes only on mysql. Prevents stray `$...$` pairs (MySQL/SQLite) or a backslash-before-quote (PostgreSQL) from merging statements across `;`.
- **MigrationSecurityScanner** - DML patterns use `[^;]*` (single-statement scope) instead of `.*` with `/s`, so a sensitive table name in a different statement no longer produces a false warning. `stripCommentsAndStrings()` also keeps `'-- UPDATE users'` string literals and `-- UPDATE users` comments from triggering false warnings.
- **MigrationController** - rejects uploads when `getSize()` returns null (PSR-7) instead of silently bypassing the size check.
- **MigrationRunner::apply()** - returns an error when `file_get_contents()` fails, instead of treating an unreadable file as an empty (successful) migration and moving it to `ran/`.
- **MigrationController / MigrationRunner** - `mkdir()` failures are now checked (upload folder and `ran/` folder), surfacing a clear error instead of a confusing downstream failure.
- **DiscordNotifier** - skips the POST when `json_encode()` returns false (e.g. invalid UTF-8) instead of sending an empty body.
- **Deploy (Windows job)** - `deploy.yml` robocopy `/XD` excluded a nonexistent `private` directory and did not exclude `protected/`; the `/MIR` mirror would delete the server's live `protected/` (uploads + cache). Now excludes `protected/`, matching the FTP and SSH jobs.
- **TrashController::restore()** - the primary insert, localized `_content` insert, and trash-row removal are now wrapped in a single transaction; a failure in any step rolls back, preventing an orphan primary row on partial restore. `insertPrimary()` now pre-checks whether the original ID is free instead of catch-and-retry on a failed INSERT (PostgreSQL aborts the whole transaction on a failed statement, which would have broken the auto-increment fallback inside the transaction).
- **products-view.html** - replaced the unsupported `in` membership operator (`codesaur/template` has no `in`) with an explicit `==`/`or` chain, so the attachment preview-vs-download logic evaluates correctly.
- **ProductsController::update()** - clears `published_at`/`published_by` when a product is unpublished (1 -> 0); previously stale publish metadata persisted after un-publishing.
- **MigrationRunner::splitStatements()** - MySQL quote-escape detection now counts consecutive preceding backslashes (even count = literal `\\`, not an escape) instead of a single-character lookback, so a string literal ending in `\\` no longer swallows the following `;` and merges statements.
- **contact.html** - the public contact page title (admin-entered content) is now HTML-escaped (`|e`).
- **`Controller::respondJSON()` PHPdoc** clarified: a valid HTTP integer code is returned as the response code, while a string or unrecognized code is intentionally ignored (stays 200) with the error conveyed through the JSON `status: 'error'` envelope. Behavior unchanged - documentation only, for future maintainers and AI agents.
- **Documentation accuracy** - corrected the web middleware pipeline diagram (removed the deleted `MySQL` connection middleware) in `README` and `api.md` (mn/en); event classes (`EventDispatcher`, `ListenerProvider`, `ContentEvent`, `UserEvent`, `OrderEvent`, `DevRequestEvent`) now documented under `application/raptor/notification/` (not a nonexistent `event/`); `ContentEvent`/`OrderEvent`/`DevRequestEvent` property tables matched to their actual constructors; `ReviewsController` delete route corrected to `/dashboard/products/reviews/delete`; `FTP_SERVER_DIR` example aligned across docs; migration README example uses `IF NOT EXISTS`.

---

## [3.1.0] - 2026-05-06
[3.1.0]: https://github.com/codesaur-php/Raptor/compare/v3.0.0...v3.1.0

`codesaur/dataobject` upgraded to v10.0.0 (which dropped `setForeignKeyChecks()`); 24 redundant FK-toggle wrappers around `ALTER TABLE ADD CONSTRAINT FOREIGN KEY` removed framework-wide; PostgreSQL deployments no longer require the application DB user to hold `SUPERUSER` privilege. Plus PostgreSQL compatibility fixes in news archive and language copy-content flows, and safer file lifecycle around hard-delete and profile/logo update.

### Breaking Changes

- **`codesaur/dataobject` upgraded to v10.0.0** which drops `setForeignKeyChecks(bool $enable)` from `PDOTrait`. Anyone with custom Models under `application/` that wrapped `ALTER TABLE ADD CONSTRAINT FOREIGN KEY` in `$this->setForeignKeyChecks(false)` ... `(true)` brackets must remove those calls (they were redundant on freshly-created empty tables anyway). If real toggling is genuinely needed, write the raw SQL directly:
  - MySQL: `$this->exec('SET foreign_key_checks=0')` / `=1`
  - PostgreSQL: `$this->exec("SET session_replication_role='replica'")` / `'origin'` (still requires SUPERUSER)
  - SQLite: `$this->exec('PRAGMA foreign_keys=OFF')` / `=ON`

### Removed

- **24 redundant `setForeignKeyChecks()` calls** stripped from framework Models and Controllers:
  - 22 model `__initial()` hooks - ProductsModel, ReviewsModel, ProductOrdersModel, DevRequestModel, DevResponseModel, LanguageModel, TextModel, SignupModel, ForgotModel, SettingsModel, OrganizationModel, OrganizationUserModel, Roles, Permissions, RolePermission, UserRole, FilesModel, NewsModel, CommentsModel, PagesModel, ReferenceModel, MenuModel
  - 2 controller hard-delete blocks - UsersController, OrganizationController
  - All wrapped only `ALTER TABLE ADD CONSTRAINT FOREIGN KEY` on JUST-CREATED (empty) tables, where FK validation cannot fail. The toggling was over-defensive copy-paste from earlier MySQL-only days.
  - **Side effect:** PostgreSQL deployments no longer need `SUPERUSER`. After upgrade run `ALTER USER <app_db_user> NOSUPERUSER;` to revoke the previously-required privilege.

### Changed

- **Hard-delete file cleanup ordering** - `UsersController::delete()` and `OrganizationController::delete()` now `unlink()` the profile photo / logo file **after** `$model->deleteById($id)` succeeds. Previously the file was deleted first; if the DB delete failed (e.g. FK violation, permission denied) the record stayed pointing to a missing file - "хагас орхигдсон линк".
- **Profile/Org update file lifecycle** - `UsersController::update()` and `OrganizationController::update()` defer old-file `unlink()` until after `updateById()` returns successfully, and the `catch` block rolls back any newly uploaded file if the UPDATE failed. Previously the old file was unlinked before UPDATE, and a failed UPDATE left a freshly uploaded file orphaned on disk while the record still referenced the deleted old path.
- Stale docblock fragments referencing the removed FK-toggle pattern cleaned up in SignupModel, OrganizationUserModel, OrganizationModel, SettingsModel. Stale "// FK constraint түр унтрааж устгана" inline comments removed from UsersController and OrganizationController delete blocks.

### Fixed

- **PostgreSQL: news archive crashed on first request** with `SQLSTATE[42883]: function year(timestamp without time zone) does not exist`. `Web\Content\NewsController::archive()` now branches on `getDriverName()`: `EXTRACT(YEAR FROM published_at)::int` / `EXTRACT(MONTH FROM published_at)::int` on PostgreSQL, `YEAR()` / `MONTH()` on MySQL.
- **PostgreSQL: language copy-content broke on second metadata lookup** - the first column-metadata query in `Raptor\Localization\LanguageController` was already driver-aware (`information_schema.columns` vs `SHOW COLUMNS`) but a sibling lookup a few lines below ran an unconditional `SHOW COLUMNS FROM $table` and accessed `$column['Field']`. Both branches now match: `information_schema.columns` + `column_name` on PostgreSQL, `SHOW COLUMNS` + `Field` on MySQL.

---

## [3.0.0] - 2026-04-28
[3.0.0]: https://github.com/codesaur-php/Raptor/compare/v2.2.0...v3.0.0

Trash system, PSR-14 event dispatcher, soft-delete removal across 15 models, hard-delete for users/organizations, file-based DB cache, admin notification emails, HTML validation, HTTP PATCH for partial updates, query optimizations, Mailer rewrite (PHPMailer/Brevo SDK/Guzzle removed), codesaur dependency upgrades (template v4.0.1, dataobject v9.1.0, http-client v2.1.0), SSH deploy, comprehensive documentation expansion.

### Breaking Changes

- **Soft delete (deactivateById) removed** from most models - only UsersModel, OrganizationModel, SignupModel retain `is_active` column
- **`is_active` column removed** from 15 models: NewsModel, PagesModel, ProductsModel, ProductOrdersModel, ReviewsModel, CommentsModel, FilesModel, MenuModel, TextModel, ReferenceModel, SettingsModel, LanguageModel, ForgotModel, DevRequestModel, MessagesModel
- All content controllers `deactivate()` method renamed to `delete()` with hard `deleteById()`
- Route paths changed from `/deactivate` to `/delete` across all content routers
- `getRowWhere(['id' => $id, 'is_active' => 1])` patterns replaced with `getById($id)`

### Added

- **Trash system** (`Raptor\Trash`) - Deleted records are stored in `trash` table before deletion
  - `TrashModel` columns: `id`, `table_name`, `log_table`, `original_id`, `record_data` (JSON mediumtext), `deleted_by`, `deleted_at`. The `log_table` column holds the **log channel name** that `restore()` writes the "restored" row to (e.g. `'products'`, `'news'`, `'content'`); controllers pass it directly to `TrashModel::store()`
  - `TrashController` - index, list (filter by `table_name`), view (JSON detail), restore (recover record), delete (permanent), empty (clear all)
  - `TrashRouter` - 6 routes under `/dashboard/trash/` (`trash`, `trash-list`, `trash-view`, `trash-restore`, `trash-delete`, `trash-empty`)
  - Dashboard UI with table-name filter, detail modal, restore button, SweetAlert confirmations
  - system_coder only access
  - Menu entry added to MenuSeed (position 365, before Manage Menu)
  - **Restore feature** - schema-aware UNIQUE pre-flight check (information_schema for MySQL, pg_index for PostgreSQL); attempts original ID first to preserve FK references, falls back to auto-increment on PRIMARY KEY conflict (SQLSTATE 23000); restores LocalizedModel `_content` rows with new parent_id; aborts with admin-friendly message on UNIQUE collision (slug, keyword, code, sku, etc.)
  - **Dual restore audit logging** - every restore writes to `trash_log` (full audit) AND the restored record's `log_table` channel (so the entry appears in Logger Protocol on the record's view/update page). The `log_table` value is set at delete time by the controller calling `TrashModel::store()` with its log channel name (e.g. ReviewsController passes `'products'`, ReferencesController passes `'content'`, TemplateController menu delete passes `'dashboard'`)
- **`record_id` log context convention** - Standardized record-identifier key across all CRUD log calls. Previously some modules used `'id' => $id`, others `'record_id' => $id`, breaking Logger Protocol filters. Refactored 10 controllers (Messages, Language, Text, Organization, RBAC, Users, References, web News/Page/Shop) and updated `WebLogStats` JSON_EXTRACT path from `$.id` to `$.record_id`. Reference view/update templates updated to filter by `record_id`. New rule: any log entry tied to a record must use `'record_id' => $id` in context (`auth_user.id` retains its own semantic for Badge system)
- **PSR-14 Event Dispatcher** (`Raptor\Notification`) - Decoupled notification system
  - `EventDispatcher` implementing `Psr\EventDispatcher\EventDispatcherInterface`
  - `ListenerProvider` implementing `Psr\EventDispatcher\ListenerProviderInterface`
  - `Event` base class implementing `StoppableEventInterface`
  - `ContentEvent` - content CRUD events (news, pages, text, references, files, etc.)
  - `UserEvent` - user signup/approve events
  - `OrderEvent` - order new/status_changed events
  - `DevRequestEvent` - dev request new/updated events
  - `DiscordListener` - subscribes to all events, delegates to `DiscordNotifier`
  - Registered as `events` service in Container
- **Hard delete for Users/Organizations** - `delete()` method with `setForeignKeyChecks(false)` for permanently deleting deactivated records
  - Fire icon button in UI for is_active=0 records
  - Trash icon button retained for is_active=1 records (deactivate)
  - Routes: `user-delete`, `user-signup-delete`, `organization-delete`
- **Controller helpers** added to base `Raptor\Controller`:
  - `dispatch(object $event)` - PSR-14 event dispatch
  - `getAdminName()` - current admin full name
  - `getDashboardUrl()` - dashboard base URL
- **CacheService** - Custom file-based DB cache (PSR-16 SimpleCache). Гадаад dependency-гүй. Stored in `private/cache/`. Registered as container service. Caches: languages, translations, settings, dashboard menu, RBAC permissions, pages navigation, featured pages, recent news, reference data. Auto-invalidated on CRUD operations via `Controller::invalidateCache()` helper. 12-hour TTL safety net. Fail-safe: system works without cache if unavailable
- **TemplateService caching** - Email/notification templates now load through 2-mode strategy: cache enabled = full table cached as `reference.templates.{code}` map; cache disabled = per-keyword DB query (single keyword) or batch IN(...) query (multiple keywords)
- **Admin email notifications** - Configurable email notifications for 4 channels: contact messages (`RAPTOR_CONTACT_EMAIL_NOTIFY`), orders (`RAPTOR_ORDER_EMAIL_NOTIFY`), comments (`RAPTOR_COMMENT_EMAIL_NOTIFY`), product reviews (`RAPTOR_REVIEW_EMAIL_NOTIFY`). Each channel has independent toggle and recipient email in `.env`. Contact and order enabled by default, comment and review disabled by default
- **Email notification UI** - Toggle switch and recipient email settings at top of messages, orders, comments, reviews index pages. Visible to `system_coder` role only. Directly modifies `.env` file via `MessagesController` toggle/update methods
- **Email templates** - `contact-message-notify`, `order-status-update`, `comment-notify`, `review-notify` templates in `reference_templates` for admin notification emails
- **HtmlValidationTrait** - Server-side HTML content validation for Pages, News, Products. Detects unclosed HTML comments and broken tags causing >20% content loss. Rejects save with error message
- **NewsModel::getRecentPublished()** - Reusable method for recent published news with cache-friendly field selection (excludes dynamic `read_count`)
- **moedit built-in notify** - `_notify(type, msg)` method with built-in popup notification (no external dependency). Displays centered toast with icon, colored background, auto-dismiss after 2.5s. Supports `success`, `warning`, `danger`, `info` types
- **moedit HTML validation** - Client-side validation when switching Source to Visual mode. Detects unclosed comments and broken tags. Stays in Source mode on failure with warning notification
- **SSH Deploy** - New deploy job using `rsync` + `ssh-action` for Linux servers with SSH access (VPS, cloud VM, dedicated). Post-deploy `composer dump-autoload` via SSH

### Changed

- All direct `$this->getService('discord')?->` calls (35 occurrences across 16 controllers) replaced with `$this->dispatch(new Event(...))` pattern
- Removed duplicated `$adminName`/`$appUrl` variable assignments from controllers (now helper methods)
- **`codesaur/dataobject` upgraded to v9.1.0** - significant ORM upgrade with breaking error-handling changes and new helper methods that Raptor adopts framework-wide:
  - **New `Constants` class** centralizes all magic values previously scattered through the ORM. Raptor now imports it everywhere a literal would have been used:
    - `Constants::DRIVER_PGSQL` / `DRIVER_MYSQL` / `DRIVER_SQLITE` replaces hardcoded `'pgsql'` string comparisons in `getDriverName()` checks (25 occurrences across 14 files: WebLogStats, TrashController, Application init, multiple migration helpers, etc.)
    - `Constants::DEFAULT_CODE_LENGTH` replaces hardcoded `2` in `new Column('code', 'varchar', 2)` (9 models with localization code columns)
    - `Constants::CONTENT_TABLE_SUFFIX` (`'_content'`) used by `TrashController::insertLocalizedContent()` to derive the LocalizedModel content-table name without hardcoding the suffix
    - Other constants available for future use: `COL_ID`, `COL_IS_ACTIVE`, `COL_PARENT_ID`, `COL_CODE`, `LOCALIZED_KEY`, `CONTENT_KEY_COLUMNS`, `PRIMARY_ALIAS_PREFIX`, `CONTENT_ALIAS_PREFIX`, `TABLE_NAME_PATTERN`, error codes
  - **Breaking: `insert()` and `updateById()` now throw on failure** instead of returning `false`. Return type narrowed from `array|false` to `array`. All Raptor callers updated; pattern `=== false` checks removed in favour of try/catch around the ORM call
  - **Breaking: `deactivateById()` throws when row is already inactive** (previously returned `false`). Also no longer mutates UNIQUE column values on deactivation (Raptor's `UsersModel`/`SignupModel`/`OrganizationModel` no longer rely on the old "negate numeric / prefix `[uniqid]`" hack - the username/email column lengths could be reduced from 143 to 128 chars accordingly)
  - **New shortcut methods leveraged across Raptor**: `getById(int $id)` adopted across 18 controllers (replacing the older `getRowWhere(['id' => $id])` pattern); `countRows(array $condition)` used by `TrashController::empty()` for the pre-clear summary count; `existsById(int $id)` available for lightweight presence checks (`SELECT 1 ... LIMIT 1`)
  - PDO error extraction centralized via `throwPdoError()` helper inside the package (no Raptor change needed - exceptions now carry richer context automatically)
- `codesaur/template` updated to v4.0.1 - adds `{% for %}{% else %}{% endfor %}` empty-iterable branch and object method calls in expressions (`{{ user.can('edit') }}`, `{% if auth.is('admin') %}`). Both were silently failing before; permission-gated UI is now visible to authorized users, and templates with `{% else %}` no longer truncate everything after `{% endfor %}`.
- `composer.json`: `psr/simple-cache` moved from dev to stable version, `minimum-stability: dev` removed, `psr/event-dispatcher` added
- Example `deactivateById()` calls wrapped in try/catch (adapts to v9.1.0's throw-on-already-inactive behaviour)
- **Shop module router merge** - `OrdersRouter`, `ProductsRouter`, `ReviewsRouter` consolidated into single `ShopRouter`. Reviews routes renamed: `reviews` -> `products-reviews` (GET=HTML, POST=JSON), `reviews-delete` -> `products-reviews-delete`; `reviews-list` and `reviews-view` removed (handler logic merged into index/products view)
- **`twigDashboard()` renamed to `dashboardTemplate()`** - Method in `DashboardTrait` renamed across 23 controllers and 4 docs/CLAUDE files. Old name was a leftover from when codesaur/template embedded Twig
- **CacheService cross-platform robustness** - All filesystem ops (`unlink`, `file_put_contents`, `file_get_contents`, `mkdir`, `rmdir`) wrapped with `@` to suppress warnings on Windows file locks/race conditions. `clear()` now returns `false` on partial failure (PSR-16 compliance). `set()` now properly handles `\DateInterval` TTL. `has()` correctly returns true for explicitly-stored null values
- **Mailer rewritten with native PHP / codesaur primitives** - All third-party email dependencies (PHPMailer, Brevo SDK, Guzzle) removed:
  - **Brevo transport** (`sendBrevoTransactional`) - rewritten using `codesaur/http-client`'s `JSONClient` (no more Brevo SDK + Guzzle dependency tree)
  - **SMTP transport** (`sendSMTP`) - rewritten using native `stream_socket_client` (no more PHPMailer). Visibility changed from `public` to `protected`. New helpers: `smtpRead`, `smtpCommand`, `smtpExpect`, `buildMimeMessage`, `formatAddress`
  - **Mail transport** (`sendMail`) - new method using PHP `mail()` for cPanel/VPS sendmail/postfix
  - **Transport selection** via new `RAPTOR_MAIL_TRANSPORT` env var: `brevo` (default), `smtp`, `mail`. `send()` dispatches to the appropriate handler based on this setting
  - **`.env` keys added**: `RAPTOR_MAIL_TRANSPORT`, `RAPTOR_SMTP_HOST`, `RAPTOR_SMTP_PORT`, `RAPTOR_SMTP_USERNAME`, `RAPTOR_SMTP_PASSWORD`, `RAPTOR_SMTP_SECURE`
  - **Attachments** - URL/base64 (Brevo), local file/base64 (SMTP), all types via parent class (Mail)
- **WebLogStats query optimization** - Consolidated 14 separate `GROUP BY` + `JSON_EXTRACT` queries into 2 single-scan queries (1 for today's live data, 1 per cached date). Eliminates MySQL temporary table creation that caused "Disk full" errors on shared hosting. `refreshCache()` batch limited to 5 dates per request to prevent overloading `/tmp` partition
- **moedit.js** - Removed `opts.notify` option. `_notify` definition moved entirely to `moedit.ui.js` as built-in. Version bumped to v3
- **moedit.ui.js** - `_notify` rewritten as built-in 2-parameter method `(type, msg)`. All 3-parameter calls consolidated to 2 parameters. Removed `Notify` global fallback and `alert()` fallback. Version bumped to v3
- **dashboard.js** - `copyContent()` moved to Files module `index.html` (only used by file tag/location modals). `initLoggerProtocol()` LIMIT capped at 100 entries with idempotent guard against duplicate fetches. `initInvalidTabFocus()` MutationObserver auto-switches Bootstrap tab when invalid input detected on hidden tab. Version bumped to v4
- **News list query** - Correlated subquery for comments count replaced with single LEFT JOIN. 4000 news = 4000 subqueries reduced to 1 grouped JOIN
- **Database indexes** - Added `idx_news_content_active_created`, `idx_news_content_code`, `idx_news_comments_newsid_active` for news list performance
- **DashboardMenus renamed to MenuSeed** - Class and file renamed for consistency with other seed classes (PermissionsSeed, RolePermissionSeed). CLAUDE.md and CHANGELOG.md updated
- **PagesModel::getFeaturedLeafPages()** - SELECT fields expanded for developer flexibility (added description, photo, code, type, category, position, published_at, created_at)
- **PrivateFilesController** - `read()` and `setFolder()` now block access to `private/cache/` directory
- **Cache invalidation** - Moved from `finally` blocks to `try` blocks (after successful DB write only). Exception-safe with error logging in development mode
- **Partial update routes: PUT -> PATCH** - 4 routes that perform partial resource updates now use HTTP PATCH instead of PUT, following RESTful conventions:
  - `PATCH /dashboard/orders/{id}/status` - Order status update (single field)
  - `PATCH /dashboard/files/{table}/{id}` - File metadata update (changed fields only)
  - `PATCH /dashboard/settings/env` - Single .env key-value toggle/update
  - `PATCH /dashboard/messages/replied/{id}` - Mark message as replied (is_read + replied_note)
- **Frontend templates updated** - All `csrfFetch()` calls for the 4 PATCH routes changed from `method: 'PUT'` to `method: 'PATCH'` across 6 HTML files (orders-view, orders-index, reviews-index, messages-index, comments-index, files-update-modal)
- **CsrfMiddleware** - PHPDoc updated to document PATCH as a protected method (POST/PUT/PATCH/DELETE). No code change needed - middleware already validates all non-safe methods
- **LogsController::retrieve()** - Now sanitizes and applies `OFFSET` parameter (was silently ignored, causing infinite-scroll endless loop on `index-list-logs.html`). `LIMIT` capped at 200 max with default 100
- **Empty `WHERE` SQL guard** - 3 list controllers (NewsController, PagesController, DevRequestController) now build optional `WHERE` clause to avoid `WHERE  ORDER BY` syntax error when no filter params are provided
- **codesaur/http-client upgraded to v2.1.0** - New features: `Response` object (`statusCode`, `headers`, `body`, `isOk()`, `isError()`, `json()`), `CurlClient::send()` returning Response, `CurlClient::sendWithRetry()` with exponential backoff, `CurlClient::upload()`, `JSONClient` base URL support, auto SSL verify based on `CODESAUR_APP_ENV`
- **DiscordNotifier** - `CurlClient::request()` replaced with `sendWithRetry()` for automatic retry on transient failures (2 retries with exponential backoff). Response status logged in development mode. Added `newReview()` method for product reviews showing star rating + comment
- **BadgeController** - `trash` log table now bypasses `auth_user.id != adminId` self-filter (admin's own trash entries should appear in their own badge). `store` action color changed from green to red (semantically a destructive operation)
- **deploy.yml** - Renamed from cPanel-specific to generic naming: "cPanel FTP Deploy" -> "FTP Deploy", job `cpanel` -> `ftp`. Pre-check job `check-ftp` -> `check-targets` detects both FTP and SSH. "XAMPP htdocs" -> "Server project directory". All 3 jobs (FTP, SSH, Windows) run in parallel when configured
- **SpamProtectionTrait** - Turnstile verification uses `CurlClient::send()` with `Response` object. HTTP status check (`isError()`) added before JSON parsing. `json_decode()` replaced with `$response->json()`

### Removed

- `is_active` column and all related SQL queries, indexes, filter logic from 15+ models and their controllers
- Direct Discord notification calls from all controllers (replaced by PSR-14 events)
- Codecov integration from CI workflow (coverage reports remain local)
- `OrdersRouter`, `ProductsRouter`, `ReviewsRouter` files (merged into `ShopRouter`)
- `ReviewsController::list()` and `ReviewsController::view()` methods (functionality merged into `index()` with method-aware response)
- `is_active` orphan check in `references-index.html` (Reference module never had `is_active` column)
- **`phpmailer/phpmailer ^7.0.1`** dependency (replaced by native `stream_socket_client` SMTP)
- **`getbrevo/brevo-php ^2.0.14`** dependency (replaced by `codesaur/http-client`'s `JSONClient`)
- **`guzzlehttp/guzzle`** transitive dependency (was pulled in by Brevo SDK; no longer needed)

### Tests

- **PatchRoutesTest** - 28 tests (40 assertions) covering PATCH route matching, PUT rejection, parameter extraction, URL generation, CsrfMiddleware PATCH validation, source code verification
- **CacheTest** expanded - 46 tests covering PSR-16 compliance: TTL expiry, `\DateInterval` TTL, `has()` semantics for null/false/zero, `getMultiple/setMultiple/deleteMultiple`, edge cases (long keys, unicode, special chars, corrupted files, nonexistent dir, invalid path)

### Documentation

- **moedit manual** (MN/EN) - Added HTML validation section covering editor-side and server-side validation
- **messages manual** (MN/EN) - Added admin email notification settings section
- **comments manual** (MN/EN) - Added admin email notification settings section
- **orders manual** (MN/EN) - Added admin email notification section, separated customer and admin email sections
- **reviews manual** (MN/EN) - Added admin email notification settings section
- **pages manual** (MN/EN) - Added HTML content validation warning in create/edit sections
- **news manual** (MN/EN) - Added HTML content validation warning in create/edit sections
- **products manual** (MN/EN) - Added HTML content validation warning in create/edit sections
- **docs/en/api.md, docs/mn/api.md** - Added 10 new sections: HtmlValidationTrait, DashboardTrait, FileController, AIHelper, Badge System, MenuModel, Dashboard Home, Dashboard Manual, ExceptionHandler, Seed/Initial Data. Route tables updated for PATCH. ShopRouter unified route table added (replaces ProductsRouter/OrdersRouter/ReviewsRouter). CsrfMiddleware added to pipeline. Web controller methods expanded (favicon, commentSubmit, reviewSubmit). Return types updated from `TwigTemplate` (removed) to `FileTemplate`
- **docs/en/README.md, docs/mn/README.md** - Added 5 new module sections (6.24-6.28): Badge System, Dashboard Home, Dashboard Manual, AI Helper, Seed/Initial Data. Template module expanded with DashboardTrait, MenuModel, FileController. CSRF section added (6.20). Middleware pipeline table updated. Architecture flow updated. Features list expanded. Router example code expanded with PATCH. Template engine section rewritten - codesaur/template (NOT Twig) limitations table added (range operator, `in` membership, `ends with`, etc.)
- **CLAUDE.md** - Template engine clarification (codesaur/template, not Twig). Twig features NOT supported listed with replacements. `dashboardTemplate()` method (renamed from `twigDashboard()`). CsrfMiddleware updated to PATCH-aware
- **README.md** - Added CSRF protection and sidebar badge system to features list (MN/EN). Architecture diagram updated with CSRF middleware
- **PHPDoc** - Updated in OrdersController, ContentsRouter, MessagesController, SettingsController, FilesController, CsrfMiddleware, ShopRouter, Application.php (web), CacheService

---

## [2.2.0] - 2026-03-19
[2.2.0]: https://github.com/codesaur-php/Raptor/compare/v2.1.0...v2.2.0

Security hardening (CSRF, login rate limiting, SQL injection protection), Discord notifications expansion, test coverage 3x increase, web content metadata and social sharing, news category listing, page sidebar, files module consolidation, and database compatibility improvements.

### Added
- **CsrfMiddleware** - Per-session CSRF token validation for all dashboard POST/PUT/PATCH/DELETE requests. Token generated at login and auto-generated for existing sessions. Delivered to frontend via `<meta name="csrf-token">` tag
- **csrfFetch() / getCsrfToken()** - JS wrapper in `dashboard.js` that auto-attaches `X-CSRF-TOKEN` header to fetch requests
- **Login rate limiting** - `checkLoginAttempts()` queries `dashboard_log` for failed attempts by IP or username. 10+ failures within 15 minutes triggers 429 lockout
- **Forgot password cooldown** - `checkForgotCooldown()` queries `forgot` table to prevent repeat requests within `RAPTOR_PASSWORD_RESET_MINUTES`
- **initLoggerProtocol()** - Centralized log display function in `dashboard.js`. Templates only need `data-retrieve`, `data-view`, `data-context` attributes on `<ul>` element
- **initInvalidTabFocus()** - Auto-focuses invalid form inputs in inactive Bootstrap tabs via MutationObserver
- **SQL injection protection** - `LogsController::retrieve()` sanitizes CONTEXT field names (allowlist regex), ORDER BY (column + direction only), LIMIT (integer only). Client-supplied WHERE/HAVING/JOIN keys are stripped
- **Discord notifications expanded** - `contentAction()` added to: ReferencesController (insert/update/delete), TextController (insert/update/delete), LanguageController (insert/update/delete), MessagesController (reply/delete), CommentsController (delete), ReviewsController (delete)
- **News type listing page** - `NewsController::newsType()` supports `type=all` (all news) and specific type filtering. Two-column layout with news cards (left) and category sidebar (right). Route: `GET /news/type/{type}`
- **PagesSamples news link** - "Мэдээлэл" / "News" navigation page entry with `link=/news/type/all` added for MN and EN
- **NewsSamples categories** - Sample news now have distinct types: `technology`, `announcement`, `guide` for category sidebar demo
- **Web content metadata** - News, Page, Product detail pages now display: published_at, creator, publisher, read_count, word_count, read_time, is_featured, category/type. Controllers JOIN users table for creator/publisher names
- **Social share + PDF** - Facebook, Twitter/X share buttons and Print/PDF button added to news, page, and product detail pages
- **OG meta improvements** - `og:url` added, `og:image` uses absolute URL via `base_url`. `TemplateController` now passes `base_url` and `current_url` to all web layouts
- **Page sidebar** - Two-column layout with siblings navigation (parent title as header), metadata card, and share/PDF card
- **Translations** - `too-many-login-attempts`, `password-reset-cooldown`, `optimize-images`, `all`, `share`, `words`, `min` keywords added to TextInitial
- **Unit tests** - 372 new tests (189 -> 561 total, 1419 assertions) covering: CsrfMiddleware, Logger masking/interpolation, BadgeController structure, Controller permissions, LocalizationMiddleware, SettingsMiddleware, SessionMiddleware CSRF, ErrorHandler, LoginController helpers (normalizeEmail, isGibberishUsername), FileController validation, FilesController access control, published/draft access pattern, DiscordNotifier, SpamProtection edge cases, LogsRetrieve SQL injection sanitization

### Changed
- **dashboard.js** - `eval()` replaced with `createElement('script')` for AJAX modal scripts. Badge seen POST uses `csrfFetch()`. Version bumped to v3
- **moedit.ui.js** - Upload uses `csrfFetch` when available, falls back to `fetch`. Version bumped to v2
- **All dashboard/raptor templates** (53 files) - `fetch()` replaced with `csrfFetch()` for state-changing requests
- **login.html** - Uses plain `fetch()` (login is CSRF-exempt, `dashboard.js` not loaded)
- **web-log-stats.html** - Uses plain `fetch()` (GET-only, runs before `dashboard.js` defer)
- **Files module consolidated** - `index-files.html` merged into `index.html`. Upload/delete/update only available on `files` table; attachments tables are list-only
- **FilesController** - `post()` rejects non-files table with `record_id=0` (403). `deactivate()` rejects non-files table (403). Default table selection fixed to `files` instead of first alphabetical key
- **Language insert modal** - Script wrapped in IIFE to prevent `const` redeclaration on reopen. Copy language flag display removed to avoid UX confusion
- **Localization log actions** - Renamed from `text-*`/`language-*` to `localization-text-*`/`localization-language-*` prefix
- **BadgeController** - `BADGE_MAP` localization actions updated with new prefix. JSON query now supports both MySQL (`JSON_EXTRACT`) and PostgreSQL (`::jsonb`) with driver detection
- **Auto-increment reset** - `clearSamples()` in ProductsController, NewsController, PagesController now uses `setval()` for PostgreSQL, `AUTO_INCREMENT` for MySQL
- **Dev requests naming** - Tables renamed: `development` -> `dev_requests`, `dev_request_responses` -> `dev_requests_responses`. Log table, files tables, and BadgeController updated accordingly
- **Dev requests attachments** - Index list now shows combined attachment count (request + response files) via subquery
- **SessionMiddleware** - Dashboard closure extended: session stays writable when `CSRF_TOKEN` is empty (for first-time token generation)
- **Controller.php** - CSRF token passed to Twig via request attribute (`csrf_token`). Removed unused `request` Twig variable (was path-only, never used in templates)
- **Application.php** - Middleware pipeline renumbered 1-9, CsrfMiddleware registered as step 6 after JWTAuth
- **Files manual** (MN/EN) - Added upload, edit/delete sections with files vs attachments distinction and permission table
- **SECURITY.md** - Added CSRF, login rate limiting, password reset cooldown, SQL injection documentation
- **CLAUDE.md** - Added CsrfMiddleware, Logger Protocol sections. Updated SessionMiddleware and dashboard.js documentation
- **Language delete** - Changed from soft delete (deactivate) to hard delete. Unique constraints on `code`, `locale`, `title` columns prevent soft delete. Route renamed: `/dashboard/language/deactivate` -> `/dashboard/language/delete`, method `deactivate()` -> `delete()`, log action `localization-language-delete`
- **codesaur/http-message** upgraded to v3.0.2 - Fixed `initFromGlobal()` to populate `$this->headers` from `getallheaders()`, enabling `getHeaderLine()` for all HTTP headers

### Removed
- **index-files.html** - Merged into index.html (renamed to .bak for verification)
- **Logger protocol duplication** - ~50-80 lines of duplicated JS log retrieval code removed from 13 template files

### Fixed
- **Language insert modal** - `Identifier already declared` error when reopening modal (IIFE fix)
- **Language insert modal** - `aria-hidden` warning on focused element
- **Files index default table** - URL without `?table=` parameter now correctly defaults to `files` instead of first alphabetical table
- **Log protocol undefined** - `log.context` null check added to prevent `Cannot read properties of undefined` errors

### Security
- **CSRF protection** - All dashboard state-changing requests now require valid CSRF token via `X-CSRF-TOKEN` header
- **Login brute force** - Rate limited via `dashboard_log` analysis (10 attempts / 15 min per IP or username)
- **Forgot password abuse** - Cooldown enforced per email via `forgot` table timestamp check
- **Log retrieve injection** - Context field names sanitized with `/^[a-zA-Z0-9_.]+$/`, ORDER BY validated as `column ASC|DESC`, LIMIT validated as integer, WHERE/HAVING/JOIN stripped from client input
- **File upload restriction** - Attachment tables reject direct upload/delete from files index page (backend enforcement)

---

## [2.1.0] - 2026-03-18
[2.1.0]: https://github.com/codesaur-php/Raptor/compare/v2.0.1...v2.1.0

Product review/rating system, comments consolidation, and dashboard UX improvements.

### Added
- **Product Reviews** - Star rating (1-5) with written review on product detail pages. New model (`ReviewsModel`, table: `products_reviews`), controller (`ReviewsController`), and router (`ReviewsRouter`)
- **Web review form** - Spam-protected review submission via `POST /session/product/{id}/review` with honeypot, HMAC, rate limiting, Turnstile support
- **Web product media gallery** - Thumbnail strip + large preview replacing the old attachment table. Images open in Fancybox, video/audio play inline, documents download or open in new tab (PDF)
- **Web products list ratings** - Average star rating and review count displayed on product cards
- **Dashboard review management** - Reviews list accessible from products-index header, product reviews shown in products-view with delete (SweetAlert2 confirmation)
- **Badge info color** - New `info` (`bg-info`, cyan) badge color for comment/review activity, distinct from `blue` (`bg-primary`) used for updates
- **Translations** - `average-rating`, `can-review`, `rating`, `reviews`, `write-review` keywords added to TextInitial
- **Manual** - `reviews-manual-mn.html`, `reviews-manual-en.html` for the reviews module

### Changed
- **ProductsModel** - `comment` column renamed to `review` (tinyint toggle for enabling reviews)
- **ProductsController** - `list()` includes `review_count` and `avg_rating` via subquery; `view()` passes full reviews list to template
- **OrdersController** - Log table changed from `'product'` to `'products_orders'` for dedicated order logging
- **ShopController (web)** - `orderSubmit()` logs to `'products_orders'`; `product()` fetches reviews + spam tokens; `products()` LEFT JOINs review stats
- **Comments consolidated into news-view** - `CommentsController::view()` now redirects to `/dashboard/news/view/{id}#comments` instead of rendering separate `comments-view.html`. Comments sidebar menu removed, link moved to news-index header
- **Reviews consolidated into products-view** - No separate reviews-view page. Reviews sidebar menu removed, link moved to products-index header
- **BadgeController BADGE_MAP** - Comment actions (`comment-insert`, `comment-reply`) now badge `/dashboard/news` with `info` color. Review actions (`review-insert`) badge `/dashboard/products` with `info` color. Order actions moved to `products_orders` log table key
- **BadgeController PERMISSION_MAP** - Removed `/dashboard/comments` and `/dashboard/reviews` entries (no longer sidebar modules)
- **DashboardMenus** - Removed Comments and Reviews sidebar menu entries
- **dashboard.js** - `COLOR_MAP` and badge render order updated: `green -> info -> blue -> red`
- **products-insert.html / products-update.html** - `comment` field renamed to `review`, label changed to `reviews` with `can-review` text, icon changed to `bi-star-half`
- **products-view.html** - Comment badge replaced with review badge, full reviews section with scrollable list and delete buttons

### Removed
- **reviews-view.html** - Consolidated into products-view.html
- **TextInitial unused keywords** - Removed 14 keywords not used via `|text` or `$this->text()`: `allow-write`, `clear`, `download`, `empty-directory`, `empty-result`, `items`, `lines`, `log-file-empty`, `network-error`, `newer`, `older`, `refresh`, `rows-found`, `running`

---

## [2.0.1] - 2026-03-18
[2.0.1]: https://github.com/codesaur-php/Raptor/compare/v2.0.0...v2.0.1

GitHub Actions Node.js 24 compatibility update.

### Changed
- **ci.yml** - `actions/checkout` v4 -> v6 (Node.js 24 support)
- **cpanel.deploy.yml** (example) - `actions/checkout` v4 -> v6, `SamKirkland/FTP-Deploy-Action` v4.3.5 -> v4.3.6

---

## [2.0.0] - 2026-03-18
[2.0.0]: https://github.com/codesaur-php/Raptor/compare/v1.9.1...v2.0.0

Dashboard sidebar badge system - log-based activity notifications for admin users.

### Added
- **Badge system** - Dashboard sidebar menu items display colored badge pills showing unseen activity counts. Green = create/insert, blue = update, red = delete/deactivate. Multiple badges per module shown in green-blue-red order
- **BadgeController** (`raptor/template/`) - BADGE_MAP constant maps log table + action pairs to sidebar modules with colors. PERMISSION_MAP controls per-module RBAC access. Queries `*_log` tables via JSON_EXTRACT to count actions since admin's last visit
- **AdminBadgeSeenModel** (`raptor/template/`) - Tracks `checked_at` datetime per admin per module. `last_seen_count` for file-count badges (manual, migrations)
- **BadgeRouter** (`raptor/template/`) - `GET /dashboard/badges` returns all badge counts as JSON, `POST /dashboard/badges/seen` marks a module as seen
- **initSidebarBadges()** (`dashboard.js`) - AJAX badge loader. Fetches badge data on page load, renders colored pills on matching sidebar links, POSTs seen on click
- **Sidebar badge CSS** (`dashboard.css`) - flex layout for nav-link with auto-margin badge positioning

### Changed
- **ContactController** - `contactSend()` log context now includes `auth_user` without `id` field so badge system distinguishes web visitors from admin users
- **Web NewsController** - `commentSubmit()` log context now includes `auth_user` without `id` field for the same reason
- **Dashboard Application** - Registered BadgeRouter
- **dashboard.html** - Added `initSidebarBadges()` call on DOMContentLoaded, bumped CSS/JS versions to v=2
- **CLAUDE.md** - Added "Dashboard Sidebar Badge System" section documenting architecture, BADGE_MAP, PERMISSION_MAP, and integration guide

---

## [1.9.1] - 2026-03-18
[1.9.1]: https://github.com/codesaur-php/Raptor/compare/v1.9.0...v1.9.1

Contact page layout redesign, sample data improvements.

### Changed
- **Contact page layout** - Photo and content moved to left column, contact info card and message form to right column. Photo renders with Fancybox lightbox like other page templates
- **Contact info card** - Removed "Холбогдох мэдээлэл" heading. Card hidden entirely when no contact info items exist
- **Settings seed data** - Added sample phone, email, address, open-hours, and social media links so contact info card is visible on fresh install
- **PagesSamples contact content** - Replaced email-centric text with generic message encouraging form use. Added Google Maps embed (Ulaanbaatar) as reference for developers. Added office-view.jpg as header image

---

## [1.9.0] - 2026-03-17
[1.9.0]: https://github.com/codesaur-php/Raptor/compare/v1.8.2...v1.9.0

Messages, Comments, Spam Protection, Discord notifications, template refactoring.

### Added
- **Messages module** - Contact form submissions saved to `messages` DB table with dashboard management (index, view, delete). Admin can mark messages as New/Read/Replied with note (phone/email contacted). Dashboard menu under Contents
- **Comments module** - News article comments with 1-level reply thread. `news_comments` table with `parent_id` (self FK) and `created_by` (admin/guest). Web visitors can post comments when `comment=1` on news. Admin can reply from dashboard
- **CommentsController (Dashboard)** - View all comments across news, reply to comments, delete with cascade. Accessible from Contents -> Comments menu and news-index publish column
- **Comments view news metadata** - Comments view page shows news article photo (if exists), publisher name, read count, published status, and full content so admin can read the article before replying to comments
- **ContactController** - Extracted from PageController to `Web\Service\ContactController` as standalone controller with own template
- **SpamProtectionTrait** - Unified spam protection: honeypot, HMAC token + timestamp, session rate limit, Cloudflare Turnstile (optional), link spam filter. Used by ContactController, NewsController, ShopController, LoginController
- **Cloudflare Turnstile** - Optional CAPTCHA via `RAPTOR_TURNSTILE_SITE_KEY` / `RAPTOR_TURNSTILE_SECRET_KEY` env vars. When keys are empty, Turnstile is skipped. Applied to contact form, comment form, order form, signup form
- **Link spam filter** - `checkLinkSpam()` blocks messages/comments with 3+ URLs
- **Comment reply validation** - Backend enforces 1-level reply limit: reply-to-reply requests rejected with 400 error. Both web (`NewsController::comment`) and dashboard (`NewsController::commentReply`) validate that `parent_id` points to a root comment
- **CI workflow** - `.github/workflows/ci.yml` default workflow runs on every push and PR: composer validate, PHP syntax check, merge conflict markers, debug statement detection, autoload verification
- **cPanel deploy workflow** - `docs/conf.example/cpanel.deploy.yml` restructured to use `workflow_run` trigger - waits for CI to succeed before deploying. CI failure skips deploy automatically
- **Discord notifications** - `settingsUpdated()` for settings changes (texts/files/options), `contentAction()` now shows changed fields on update. App URL shown in footer of all notifications
- **DiscordNotifier app URL** - All notification methods accept `$appUrl` parameter, displayed as embed footer
- **SettingsModel default config** - Initial settings include JSON structure for social media links and open hours
- **TextInitial keywords** - `messages`, `read`, `replied`, `reply`, `reply-method`, `contacted-by-phone`, `contacted-by-email`, `comments`, `write-comment`, `contact-info`, `send-message`, `working-hours`, `social-media`, `thank-you`, `server-error`, `confirm-delete`, `delete-with-replies`, `view`, `no-email`
- **Manual pages** - Comments manual (EN/MN) and Messages manual (EN/MN) added to `application/dashboard/manual/`. Covers overview, list view, actions (reply, delete), and required permissions for each module
- **Unit tests** - 14 SpamProtectionTrait tests (honeypot, HMAC, timestamp, rate limit, Turnstile skip/verify, link spam)

### Changed
- **`template()` renamed to `twigWebLayout()`** - Web TemplateController method renamed to match `twigDashboard()` pattern. Auto-maps `title`, `code`, `description`, `photo` from `$vars` to index layout SEO meta. All web controllers updated
- **Session routes use `/session/` prefix** - All web routes that write to `$_SESSION` now use `/session/` prefix (`/session/language/{code}`, `/session/contact-send`, `/session/order`, `/session/news/{id}/comment`). SessionMiddleware simplified to `str_starts_with($path, '/session/')`
- **Discord `contentAction()` title simplified** - Removed redundant `(Updated)` / `(Deleted)` label from title since description already states the action. ID moved from separate field to description as `#ID`
- **Contact form localization** - All hardcoded `lang == 'mn' ? ... : ...` text replaced with `|text` Twig filter using TextInitial keywords. JS text passed via Twig `{{ 'keyword'|text }}` directly
- **Contact form error display** - `alert()` replaced with Bootstrap modal for both success and error messages
- **Order form submit protection** - Submit button disabled with spinner while processing to prevent duplicate submissions
- **Comment form UX** - Send button disabled until both name and comment fields are filled. Placeholder shows `*` for required fields. Prevents confusing silent failures
- **Messages reply dialog** - "Contacted by email" checkbox disabled with "(No email)" label when message has no email address. Prevents admin from selecting an unavailable reply method
- **Dev request view** - Response thread order changed to `flex-column-reverse` (oldest first at bottom, newest at top)

### Removed
- **Pages `comment` field** - Removed from PagesModel, PagesController permission checks, page-insert/update/view templates. Comments are a News-only feature per best practice

---

## [1.8.2] - 2026-03-16
[1.8.2]: https://github.com/codesaur-php/Raptor/compare/v1.8.1...v1.8.2

Web layer action logging for public-facing controllers.

### Added
- **ShopController::order()** - Log when order form is opened, includes product_id and title when a product is pre-selected
- **SearchController::search()** - Log search queries with result count
- **SeoController::sitemap()** - Log HTML sitemap page views
- **NewsController::archive()** - Log news archive page views with selected year

---

## [1.8.1] - 2026-03-16
[1.8.1]: https://github.com/codesaur-php/Raptor/compare/v1.8.0...v1.8.1

Web layer naming cleanup and Mongolian text corrections.

### Changed
- **`Web\Seo` renamed to `Web\Service`** - `application/web/seo/` folder renamed to `application/web/service/`. Search, Sitemap, RSS are site-wide services, not SEO-specific
- **`SiteRouter` renamed to `WebRouter`** - Class and file renamed from `SiteRouter.php` to `WebRouter.php` for consistency with `Web\Application` naming
- **Mongolian text fix** - `'products'` keyword translation changed from "Бүтээгдэхүүнүүд" to "Бүтээгдэхүүн" in `TextInitial`, `DashboardMenus`, and `PagesSamples` seed data

### Documentation
- All `*.md` files updated to reflect renamed paths and namespaces (`CLAUDE.md`, `README.md`, `docs/mn/`, `docs/en/`)

---

## [1.8.0] - 2026-03-15
[1.8.0]: https://github.com/codesaur-php/Raptor/compare/v1.7.1...v1.8.0

Major code review, refactoring, shared middleware consolidation, seed data extraction, RBAC UI redesign, security hardening, and CLAUDE.md rewrite.

### Added
- **SearchController** (`Web\Seo`) - Dedicated web search controller with full-text search across Pages (title, slug, description, content, source, link), News (title, slug, description, content, source), Products (title, slug, description, content, link). Strips `<img>` tags from content before matching to avoid false positives from image URLs
- **PrivateFilesController security** - Blocks dangerous file extensions (php, phtml, phar, sh, bat, cmd, exe, ini, log, sql) and sensitive filenames (.env*, .htaccess, .htpasswd, .gitignore, composer.json, composer.lock) with 403 Forbidden
- **Owner access pattern** - Users without `_update`/`_delete` permission can edit/delete their own unpublished (`published=0`) records in News, Pages, Products controllers
- **Published view access** - `published=1` records viewable by all authenticated admins regardless of `_index` permission
- **Seed data classes** - Extracted seed data from Model files into dedicated classes to reduce runtime file size:
  - `NewsSamples`, `PagesSamples`, `ProductsSamples` - Content sample data
  - `DashboardMenus` - Dashboard sidebar menu structure
  - `PermissionsSeed` - RBAC permissions (parameterized queries)
  - `RolePermissionSeed` - Role-permission assignments + role creation (admin, manager, editor, viewer)
- **PrivateFilesBlockedTest** - 21 tests for blocked file extension/name security
- **RAPTOR_PASSWORD_RESET_MINUTES** - Renamed from `CODESAUR_PASSWORD_RESET_MINUTES`, added to `.env` and `.env.example`

### Changed
- **SessionMiddleware consolidated** - `Web\SessionMiddleware` and `Raptor\Authentication\SessionMiddleware` merged into single `Raptor\SessionMiddleware` with `needsWrite` closure constructor
- **LocalizationMiddleware consolidated** - `Web\LocalizationMiddleware` merged into `Raptor\Localization\LocalizationMiddleware` with `sessionKey` constructor parameter. Controllers read session key from `localization` attribute instead of hardcoding
- **Web namespace refactored** - `Web\Home\` renamed to `Web\Site\`, `HomeRouter` renamed to `SiteRouter`
- **RBAC UI redesigned** - `rbac-alias.html` changed from wide table matrix to responsive card-based layout (2 cards per row) with per-role permission checkboxes, select all/deselect all buttons, and badge counters. Roles and permissions display as `alias_name` format
- **ReferenceInitial refactored** - Single-line insert statements broken into readable multi-line format with numbered sections
- **error.html optimized** - particles.js moved from inline (~15KB) to CDN, CSS reformatted to multi-line
- **reset() permission lowered** - News, Pages, Products reset changed from `_delete` to `_index` permission so editors can clear sample data
- **reset() enhanced** - All reset methods now also delete `is_active=0` records alongside sample data
- **FilesController owner access** - `update()` allows own file edit, `deactivate()` allows own unattached (`record_id=0`) file delete without permission
- **UsersController email normalization** - Gmail `normalizeEmail()` added to `insert()` and `update()` methods
- **DevRequestController auth fix** - Replaced `!$this->getUserId()` with `!$this->isUserAuthorized()` in all 7 methods to prevent id=0 bypass
- **PagesModel::buildTree()** - Changed from `public` to `private` (only used internally)
- **Twig comments removed** - All `{# #}` comments in 31 HTML files converted to vanilla `<!-- -->` for template engine independence
- **SessionMiddlewareTest updated** - Tests both Web and Dashboard closure logic (21 tests)
- **Language dropdown** - Hidden when only one language is active (web navbar, dashboard login)

### Removed
- **products-read.html** - Unused template, route, and `ProductsController::read()` method removed
- **Web\SessionMiddleware** - Replaced by shared `Raptor\SessionMiddleware`
- **Web\LocalizationMiddleware** - Replaced by shared `Raptor\Localization\LocalizationMiddleware`

### Documentation
- **CLAUDE.md rewritten** - Restructured as forward-looking AI guide with "Adding a New Module" 11-step checklist, shared middleware configuration, owner access pattern, migration rules, and general conventions
- **CONTRIBUTING.md** - Removed `## Project Structure` section (duplicated in CLAUDE.md)
- **docs/en/api.md, docs/mn/api.md** - Updated SessionMiddleware and LocalizationMiddleware docs with shared middleware details
- **docs/en/README.md, docs/mn/README.md** - Updated directory tree (`web/site/`, `SessionMiddleware.php` location), removed deleted files

---

## [1.7.1] - 2026-03-13
[1.7.1]: https://github.com/codesaur-php/Raptor/compare/v1.7.0...v1.7.1

Shop router registration moved from shared base application to Dashboard application.

### Changed
- **Dashboard\Application** - Shop module routers (`ProductsRouter`, `OrdersRouter`) now registered here instead of in `Raptor\Application`, since shop routes are dashboard-specific
- **Raptor\Application** - Removed `Dashboard\Shop\ProductsRouter` and `Dashboard\Shop\OrdersRouter` registration from shared base class

---

## [1.7.0] - 2026-03-13
[1.7.0]: https://github.com/codesaur-php/Raptor/compare/v1.6.0...v1.7.0

Anti-spam hardening: username gibberish detection, Gmail email normalization, scoring-based validation system.

### Added
- **Username Gibberish Detection** - Score-based system to reject bot-generated random usernames (`LoginController::isGibberishUsername()`)
  - Shannon entropy check: high randomness in character distribution (+2/+3 points)
  - Vowel ratio check: rejects consonant-heavy nonsense (+1/+3 points)
  - Consecutive consonant check: flags long unpronounceable clusters (+1/+2 points)
  - Case change ratio check: detects random casing patterns like `CdIrBVTolz` (+1/+3 points)
  - Score threshold of 3+ required to reject (no single check causes false rejection)
  - Designed for Mongolian transliteration compatibility (`munkhtseteg`, `tserenbold` pass cleanly)
- **Username Format Validation** - Regex: 3-63 characters, must start with a letter, allows letters/numbers/underscores/dots
- **Gmail Email Normalization** - `LoginController::normalizeEmail()` strips Gmail alias tricks before uniqueness checks
  - Removes dots from Gmail/Googlemail local parts (`y.i.r@gmail.com` -> `yir@gmail.com`)
  - Strips `+` sub-addressing (`user+spam@gmail.com` -> `user@gmail.com`)
  - Normalizes `googlemail.com` -> `gmail.com`
  - Normalized email stored in database, preventing future bypass with dot variations
- **Anti-Spam Tests** - `LoginSpamProtectionTest` with 43 test cases
  - 22 valid username tests (Mongolian, Slavic, German names, common patterns)
  - 8 gibberish username tests (random chars, keyboard smash, bot patterns)
  - 8 Gmail normalization tests (dots, plus, googlemail, uppercase)
  - 4 non-Gmail preservation tests (Yahoo, Outlook, custom domains)
  - Real spam signup integration test (`CdIrBVTolzIvAxjqdF` + `yir.o.h.obo.j.u.k.1.0@gmail.com`)

---

## [1.6.0] - 2026-03-13
[1.6.0]: https://github.com/codesaur-php/Raptor/compare/v1.5.0...v1.6.0

Database migration system, security hardening (removed dangerous dev tools), error log integration, route optimization, documentation update.

### Added
- **Database Migration** - SQL file-based forward-only migration system (`Raptor\Migration`)
  - `MigrationRunner` - Core engine: parse SQL files, execute pending migrations, track status
  - `MigrationMiddleware` - PSR-15 middleware that auto-runs pending migrations on each request
  - `MigrationController` - Dashboard UI for viewing migration status and SQL file contents (system_coder only)
  - `MigrationRouter` - Routes: `/dashboard/migrations`, `/dashboard/migrations/status`, `/dashboard/migrations/view`
  - Pending migrations in `database/migrations/`, ran migrations moved to `database/migrations/ran/`
  - Advisory lock (`GET_LOCK`) prevents concurrent migration execution
  - Rename with unlink fallback for servers with permission issues
  - `.htaccess` protection (`deny from all`) blocks direct browser access to SQL files
  - Nginx `.sql` file deny rule added to `.nginx.conf.example`
  - `database/migrations/example_never_runs.sql` - Example pending migration for testing
- **Error Log Tab** - PHP error.log viewer integrated as the last tab in Access Logs page (`/dashboard/logs`)
  - Lazy-loaded on tab activation via AJAX (`error-log-read` endpoint)
  - Pagination support (newest entries first)
  - Syntax-highlighted log output
  - Visible to all `system_coder` users (no user ID restriction)
- **Migration Tests** - 35 tests covering the migration system
  - `tests/Unit/Migration/MigrationRunnerTest.php` - 22 unit tests (parseFile, splitStatements, hasPending, status)
  - `tests/Integration/Migration/MigrationRunnerIntegrationTest.php` - 13 integration tests (migrate, partial failure, lock, file naming)
- **Middleware Pipeline** - `MigrationMiddleware` registered after `MySQLConnectMiddleware` in both Dashboard and Web applications
- **Autoload** - `Raptor\Migration\` PSR-4 namespace added to `composer.json`

### Changed
- **Error Log** - Moved from standalone Development Tools page to Access Logs tab; `errorLogRead()` method moved from `FileManagerController` to `LogsController`
- **Route Name Optimization** - Removed `->name()` from 11 routes across 7 routers whose names were never referenced via `|link` or `redirectTo()`:
  - `organization-view` (OrganizationRouter)
  - `manage-menu` (TemplateRouter)
  - `user-view` (UsersRouter, removed from `update` route renamed path)
  - `product-read`, `product-view` (ProductsRouter)
  - `order-view` (OrdersRouter)
  - `private-files-read`, `news-view`, `page-view` (ContentsRouter)
  - `products-page`, `sitemap-xml` (HomeRouter)
- **Spam Protection** - Login rate limit reduced from 3s to 2s; minimum form fill speed reduced from 2s to 1s
- **Dashboard Sidebar** - Removed entire "Developer Tools" section (file-manager, sql-terminal, error-log links)
- **DevelopmentRouter** - Only DevRequest routes remain; SQL Terminal, Error Log, File Manager routes removed
- **Documentation** - All 5 markdown files updated (`README.md`, `docs/en/README.md`, `docs/mn/README.md`, `docs/en/api.md`, `docs/mn/api.md`)

### Removed
- **SqlTerminalController** - Production security risk: allowed arbitrary SQL execution. Completely removed (`SqlTerminalController.php`, `sql-terminal.html`)
- **FileManagerController** - Production security risk: allowed arbitrary file system browsing. Completely removed (`FileManagerController.php`, `filemanager.html`)
- **Error Log Page** - Standalone error log page removed (`error-log.html`); functionality preserved in Access Logs tab
- **Localization** - Removed 7 unused text keywords: `developer`, `error-log`, `file-manager`, `file-too-large-preview`, `sql-query-placeholder`, `sql-terminal`, `execute`

---

## [1.5.0] - 2026-03-12
[1.5.0]: https://github.com/codesaur-php/Raptor/compare/v1.4.0...v1.5.0

Pages module simplification: flexible type system, removed read mode, improved navigation filtering, getExcerpt fix, Discord notification improvement.

### Added
- **Pages** - User-editable `type` input with dropdown suggestions (`menu`, `mega-menu`, `special`) on insert and update forms; type field is free-text with max 32 characters
- **Pages** - `has_children` alert (alert-primary) on update and view forms warning that parent page content/links may not be directly used
- **Pages** - `link` alert (alert-info) on update and view forms explaining that linked pages redirect from menu
- **Localization** - New text keywords: `parent-page-content-warning` (mn/en), `link-page-content-warning` (mn/en)

### Changed
- **Pages** - Type system simplified: replaced rigid `nav`/`content`/`link` enum with flexible user-editable field; default changed from `content` to `menu`
- **Pages** - All form fields (description, content, link, featured) always visible regardless of type or children; removed all conditional field hiding
- **Pages** - `type` field is now editable on update (previously locked after creation)
- **Pages** - Navigation tree (`pages-nav.html`): folder icon based on `hasChildren` instead of `type=nav`; type and category shown as badges
- **Pages** - Seed data updated to use `type='menu'` (relies on column default)
- **PagesModel** - `getNavigation()` now filters by `type='menu' OR type LIKE '%-menu'`; only menu-type pages appear in website navigation
- **getExcerpt()** - Fixed text sticking together when stripping HTML block tags (`<p>text1.</p><p>text2.</p>` now produces `text1. text2.` instead of `text1.text2.`); applied to `PagesModel`, `NewsModel`, `ProductsModel`
- **DiscordNotifier** - `contentAction()` now includes admin name in description (e.g. "**Narankhuu N** updated a page.") and removed redundant Type/Action fields that duplicated the title

### Removed
- **Pages** - `read()` method and `page-read.html` template removed; blog-style page reading no longer supported
- **News** - `read()` method and `news-read.html` template removed; blog-style news reading no longer supported
- **Routes** - `page-read` and `news-read` routes removed from `ContentsRouter`
- **Pages** - Read button removed from `pages-index.html` action buttons
- **News** - Read button removed from `news-index.html` action buttons
- **Localization** - Removed unused `parent-page` and `parent-page-hint` text keywords

---

## [1.4.0] - 2026-03-11
[1.4.0]: https://github.com/codesaur-php/Raptor/compare/v1.3.1...v1.4.0

Log system consolidation, model rename, Discord notification improvement, automated testing, product RBAC permissions. Removed SQLite support from Raptor due to lack of practical use case.

### Added
- **RBAC** - New `system_product_*` permissions for Shop module: `product_index`, `product_insert`, `product_update`, `product_publish`, `product_delete`; assigned to admin (all), manager (all), editor (index/insert/update/publish), viewer (index)
- **Automated Testing** - PHPUnit 11 test suite with unit and integration tests (47 tests, 83 assertions)
  - Unit tests: `User::is()`, `User::can()`, `Controller::text()`, `Controller::getLanguageCode()`
  - Integration tests: `UsersModel`, `OrganizationModel`, `SignupModel` CRUD, RBAC seed data verification, JWT encode/decode
  - Transaction-based test isolation (auto-rollback)
  - `.env.testing` for test database configuration
- **DiscordNotifier** - `newOrder()` method now includes customer phone number in Discord embed notification
- **DiscordNotifier** - `newDevRequest()` method for notifying when a new development request is created (shows author and assigned user)
- **DiscordNotifier** - `devRequestUpdated()` method for notifying when a development request receives a response (shows responder and new status)
- **DevRequestController** - Discord notifications on `store()` and `respond()` actions

### Changed
- **Shop RBAC** - Shop module (`ProductsController`, `OrdersController`, templates) now uses dedicated `system_product_*` permissions instead of shared `system_content_*`
- **OrdersModel -> ProductOrdersModel** - Renamed class and file; table name changed from `orders` to `products_orders`
- **Log consolidation** - All shop-related logs (orders CRUD, products CRUD, orderSubmit) now write to a single `'product'` log channel instead of dynamic `$table` names
- **Log consolidation** - All development module logs (DevRequest, FileManager, SqlTerminal) now write to a single `'development'` log channel instead of separate `'dev_requests'`, `'file_manager'`, `'sql_terminal'` channels
- **Error logging** - All non-critical `error_log()` calls across the application now only execute when `CODESAUR_DEVELOPMENT` is `true`; critical exception/error handlers remain unconditional
- **FileManagerController** - All UI-facing text (exception messages, log messages, response strings) changed from Mongolian to English; PHPDoc and code comments remain in Mongolian
- **SqlTerminalController** - All UI-facing text (exception messages, log messages, response strings) changed from Mongolian to English; PHPDoc and code comments remain in Mongolian

### Removed
- **SQLite support** - Removed `SQLiteConnectMiddleware` and all SQLite-specific code branches (sqlite_master queries, PRAGMA table_info, sqlite_sequence, json_extract without JSON_UNQUOTE). Application now supports MySQL and PostgreSQL only
- **Controller::errorLog()** - Removed `protected final function errorLog(\Throwable $e)` method; all call sites replaced with inline `if (CODESAUR_DEVELOPMENT) { \error_log(...); }` pattern

---

## [1.3.1] - 2026-03-10
[1.3.1]: https://github.com/codesaur-php/Raptor/compare/v1.3.0...v1.3.1

Honeypot spam field cleanup and numeric payload sanitization for Products.

### Fixed
- **LoginController** - Signup form honeypot fields (`website`, `_ts`, `_token`) were not removed from payload before DB insert, causing "column not found" error on the `signup` table
- **ProductsController** - Empty string values from frontend for numeric columns (`price`, `sale_price`, `stock`, etc.) caused database type errors on insert and update; added `sanitizePayload()` to convert empty strings to `null` for numeric fields

---

## [1.3.0] - 2026-03-10
[1.3.0]: https://github.com/codesaur-php/Raptor/compare/v1.2.1...v1.3.0

Dev Request user assignment and CI/CD quality checks.

### Added
- **Dev Request assignment** - Assign requests to a specific user when creating
  - `assigned_to` column added to `dev_requests` table (FK to `users.id`)
  - User selector dropdown on create form with two groups: Developers (coder role / development permission) shown first, then all other users
  - Only the assigned user receives the new-request email notification (instead of all dev users)
  - Assigned user can view, respond to, and deactivate the request
  - Assigned user name displayed in request list and detail view
- **CI/CD quality checks** - `validate` job added to `cpanel.deploy.yml` (runs before deploy)
  - `composer validate --strict` - Validates composer.json structure
  - PHP syntax check (`php -l`) on all application and public PHP files
  - Merge conflict marker detection in PHP, HTML, JS, CSS, JSON, YML files
  - Debug statement detection (`var_dump`, `dd`, `print_r`) as warning
  - PSR-4 autoload verification (`composer dump-autoload --strict-psr`)
- **Localization** - New dashboard text entries: `assign-to`, `assigned-to`, `developers`, `fill-required-fields`, `other-users`

### Changed
- **Dev Request notifications** - `notifyNewRequest()` now sends email only to the assigned user instead of broadcasting to all users with coder role or development permission
- **Dev Request permissions** - `list()`, `view()`, `respond()`, `deactivate()` now grant access to the assigned user in addition to the creator and users with `system_development` permission
- **Dev Request list query** - Users without `system_development` permission now see both their own requests and requests assigned to them

---

## [1.2.1] - 2026-03-09
[1.2.1]: https://github.com/codesaur-php/Raptor/compare/v1.2.0...v1.2.1

Comprehensive PHPDoc documentation and HTML comment security cleanup.

### Added
- **PHPDoc** - Full Mongolian PHPDoc comments for all Web layer PHP files
  - `HomeRouter`, `HomeController`, `PageController`, `NewsController`, `ShopController`, `SeoController`
  - Class-level docs with route listings, method-level docs with `@param`, `@return`, `@throws`
- **PHPDoc** - Full Mongolian PHPDoc comments for all Dashboard Shop PHP files
  - `ProductsModel`, `ProductsController`, `OrdersModel`, `OrdersController`
- **PHPDoc** - Expanded PHPDoc for Raptor module files
  - `DiscordNotifier` - All 6 notification methods documented
  - `DevResponseModel` - All 3 methods documented
- **Spam protection docs** - Detailed Mongolian comments in `ShopController::order()` explaining honeypot field, HMAC token, and timestamp mechanism

### Changed
- **HTML comment security** - Removed all `<!-- -->` doc comments from public Web templates to prevent information disclosure
  - Affected: `index.html`, `home.html`, `page.html`, `news.html`, `news-type.html`, `archive.html`, `search.html`, `sitemap.html`, `products.html`, `product.html`, `order.html`, `order-success.html`, `page-404.html`
- **Honeypot comment removal** - Removed honeypot-identifying comments from HTML templates (`order.html`, `login.html`) to prevent bot detection bypass; moved explanations to PHP controller code
- **Twig to HTML comments** - Converted all remaining Twig `{# #}` inline comments to vanilla `<!-- -->` in `sitemap.html` and `archive.html` section markers
- **Dashboard HTML** - Removed internal architecture doc comments from Dashboard Shop templates (`products-index`, `products-insert`, `products-update`, `products-view`, `products-read`, `orders-index`, `orders-view`)

---

## [1.2.0] - 2026-03-09
[1.2.0]: https://github.com/codesaur-php/Raptor/compare/v1.1.0...v1.2.0

E-commerce shop module, spam protection, Discord notifications, SEO features, development tools, dashboard statistics, and Web layer refactor.

### Added
- **Shop Module** - Full e-commerce with products and orders
  - `ProductsModel` - Product catalog with slug generation, excerpt, price/sale_price, SKU, barcode, sizes, colors, stock, photo, category, featured
  - `OrdersModel` - Customer orders with product reference, customer info, quantity, status tracking
  - `ProductsController`, `ProductsRouter` - Dashboard CRUD for products
  - `OrdersController`, `OrdersRouter` - Dashboard CRUD for orders
  - Sample product data seeded on first run
- **Notification Module** - Discord webhook integration (`Raptor\Notification\DiscordNotifier`)
  - Notification types: user signup request, user approval, new order, order status change, content actions (insert/update/delete/publish)
  - Color-coded embeds (SUCCESS, INFO, WARNING, DANGER, PURPLE)
  - Graceful skip when webhook URL is not configured
  - `RAPTOR_DISCORD_WEBHOOK_URL` env variable added to `.env.example`
- **Development Module** - Admin development tools (`Raptor\Development\DevelopmentRouter`)
  - Dev Requests - Development request tracking system (`DevRequestController`, `DevRequestModel`, `DevResponseModel`)
  - SQL Terminal - Database query interface (`SqlTerminalController`)
  - Error Log viewer
  - File Manager (`FileManagerController`)
  - New RBAC permission: `development:development`
- **SEO & Content Discovery** (Web layer)
  - `SeoController` - Search across pages, news, and products (min 2 chars)
  - Sitemap page (`/sitemap`) - Hierarchical page tree with news/product counts
  - XML sitemap (`/sitemap.xml`) - For search engines with lastmod, changefreq, priority
  - RSS 2.0 feed (`/rss`) - Latest 20 news and 20 products
  - Search form added to web navbar
  - Footer links: Archive, Sitemap, RSS
  - RSS `<link>` tag in web layout `<head>`
- **News Archive** - `/archive` route with year/month filtering and grouping
- **News by Type** - `/news/type/{type}` route for category-based news listing
- **Spam Protection** - Comprehensive anti-spam on login, signup, forgot, and order forms
  - Honeypot hidden field (`website` input)
  - HMAC token validation with timestamp
  - Rate limiting (login 3s, signup 5s, forgot 10s)
  - Form expiration check (1 hour max)
  - Minimum fill speed check (2 seconds)
- **Web Controllers** - Separated `HomeController` logic into dedicated controllers
  - `PageController` - Page and contact display (`pageById`, `page`, `contact`)
  - `NewsController` - News display (`newsById`, `news`, `newsType`, `archive`)
  - `ShopController` - Product listing, product detail, order form/submit, order confirmation email
  - `SeoController` - Search, sitemap, XML sitemap, RSS feed
- **Web Routes** - New public routes
  - `/page/{uint:id}` and `/news/{uint:id}` - Access by ID (redirect to slug)
  - `/products`, `/product/{uint:id}`, `/product/{slug}` - Product pages
  - `/order` (GET+POST) - Order form and submission
  - `/search`, `/sitemap`, `/sitemap.xml`, `/rss` - SEO routes
- **Localization** - 100+ new i18n text entries (MN/EN) for shop, archive, search, newsletter, and UI labels
- **Database Indexes** - Performance optimization
  - `files` table: `idx_record_id (record_id, is_active)`
  - `logger` tables: `idx_created_at (created_at)`
  - `products` table: `idx_active_published (is_active, published)`
  - `orders` table: `idx_active_status (is_active, status)`
- **Dashboard Statistics** - `HomeController::stats()`, `logStats()`, `refreshCache()` JSON endpoints for web traffic and log statistics
- **RBAC** - Detailed descriptions added to all system permissions; new permissions for template and development modules
- **cPanel Deploy** - GitHub Actions workflow example (`docs/conf.example/cpanel.deploy.yml`) for automated FTP deployment to cPanel on push to `main`

### Changed
- **Web HomeController** - Refactored: page, news, contact methods moved to `PageController`, `NewsController`; simplified to basic news listing
- **Web HomeRouter** - Completely restructured with all new routes for shop, SEO, archive, news type
- **Web templates** - All hardcoded MN/EN text replaced with i18n `|text` filter (`'read-more'|text`, `'attachments'|text`, `'file'|text`, `'size'|text`, `'type'|text`, `'source'|text`, `'deactivated'|text`)
- **Web index.html** - Menu titles rendered with `|raw` filter (allows HTML); search form in navbar; RSS feed link in head
- **Controller** - Now logs `HTTP_USER_AGENT` alongside `REMOTE_ADDR` in action logs
- **ContainerMiddleware** - Registers `DiscordNotifier` service in DI Container
- **Application** - Registers `ProductsRouter`, `OrdersRouter`, `DevelopmentRouter`
- **Login templates** - Terms & conditions text now uses i18n keys instead of hardcoded bilingual text
- **UsersController** - Sends Discord notification when admin approves a new user
- **composer.json** - New autoloader namespaces: `Dashboard\Shop\`, `Raptor\Notification\`, `Raptor\Development\`

---

## [1.1.0] - 2026-03-06
[1.1.0]: https://github.com/codesaur-php/Raptor/compare/v1.0.0...v1.1.0

Codebase-wide cleanup enforcing old-school coding style. Codesaur dependency patch versions bumped.

### Changed
- **Old-school coding style** - Removed all emoji and decorative Unicode characters from every project file (MD, PHP, HTML, CSS, JS, conf)
  - Unicode arrows (->), box-drawing characters (|, \, -), smart quotes, ellipsis, bullets, NBSP, ZWJ, BOM all replaced with plain ASCII equivalents
  - Only Mongolian Cyrillic text and ASCII characters remain throughout the project
- **codesaur/http-application** ^6.0.0 -> ^6.0.1
- **codesaur/dataobject** ^9.0.0 -> ^9.0.2
- **codesaur/http-client** ^2.0.0 -> ^2.0.4
- **codesaur/template** ^3.0.0 -> ^3.0.1
- **codesaur/container** ^3.1.0 -> ^3.1.3

### Fixed
- **moedit** - Internal copy/paste not working: pasting content copied from within the editor produced no result. Root cause was the image detection check intercepting paste events when clipboard contained both image and HTML data. Restructured `_handlePaste` to check HTML content before image detection. Added `_insertHtmlAtCursor()` helper with proper `_emitChange()` sync.
- **composer.json** - Removed stray emoji from post-install script output

---

## [1.0.0] - 2026-02-25

**`codesaur/raptor` v1.0.0 - Stable Release.** First stable release of the framework under the `codesaur/raptor` name.

### Changed
- **Environment variables** standardized to the `RAPTOR_*` prefix
- **Session name** set to `raptor`
- **Default database name** set to `raptor`
- Audit logging method exposed as `log()`

---

## [0.8.0] - 2026-02-24

### Added
- **Pages** - "Parent page (navigation)" switch in page-insert form to create `type=nav` pages directly
- **Pages** - `link` field with frontend (JS) and backend (`isValidLink()`) validation; supports URL, local path (`/path`), `mailto:`, `tel:`
- **Pages** - `PagesController::read()` guards: published check (403), parent check (403), link redirect
- **Pages** - Auto-reset `is_featured=0` on parent page when a child is inserted or `parent_id` changes
- **Sample Data Reset** - Reset button in pages-index, pages-nav, news-index when sample data exists
  - `PagesController::reset()` and `NewsController::reset()` methods
  - Routes: `pages-sample-reset`, `news-sample-reset`
  - Detection: `created_by IS NULL AND created_at = published_at AND category='sample'`
  - Truncates main table and files table, resets auto-increment to ID=1

### Changed
- **Pages** - Removed page type wizard from page-insert; all fields (title, description, content, link) visible in a single form
- **Pages** - Merged Content and Link types into one form; `link` input always visible below content editor
- **Pages** - Parent pages (pages with children) hide description, content, link, featured fields in page-update
- **Pages** - page-view shows description/content/link conditionally (only when non-empty), link with `border-info`
- **Pages / News** - Action buttons in index/nav: link button if `link` is set and not parent, read button if no link and not parent, neither if parent or unpublished
- **Pages / News** - Seed data uses `category='sample'` instead of `'general'`, `created_by` and `published_by` are `NULL`
- **News** - news-index layout: "New" button moved from header to filter row (matches pages-index layout)

### Fixed
- **Pages** - `type="url"` input rejecting local paths like `/contact`; changed to `type="text"`

---

## [0.7.2] - 2026-02-12

### Added
- **moedit** - `attachFiles` option to enable/disable the Attach File button (`true` by default, `false` disables the button)

### Fixed
- **moedit** - Header image toolbar group responsive CSS selectors now require `.is-visible` class, preventing hidden elements from displaying incorrectly

---

## [0.7.1] - 2026-02-10

### Fixed
- **SettingsController** - Settings config save crash when no record exists (empty table)
  - `LocalizedModel::insert()` requires non-empty content, but Config tab only sends non-localized fields
  - Now populates empty localized content for each language before insert
- **SettingsController** - Localized field change detection used swapped indices (`[$field][$code]` instead of `[$code][$field]`)
- **TextController** - Same swapped localized indices bug in `update()` method

---

## [0.7.0] - 2026-02-09

### Added
- **Pages Navigation** (`/dashboard/pages/nav`) - Tree-structured page navigation management
  - Display cards grouped by language
  - Published/unpublished distinction: blue title + green check icon / eye-slash icon
  - Display page photo thumbnails
- **Removed the "System page" concept** - All published pages are now visible in navigation
- Insert form: automatic position calculation (parent position + 10 for first child, max sibling + 10 for subsequent, max top-level + 100 for same-language)
- Page view: position field (language, category, position displayed in a single row)
- **OG meta tags** for page and news: `og:title`, `og:description`, `og:image`, `og:type`, `og:site_name`, logo fallback
- `<title>` tag: "Record Title | Site Title" format for content pages

### Changed
- `getNavigation()`: removed `(type='nav' OR parent_id>0)` filter, added `slug` field
- `getFeaturedPages()`: added `slug` field
- `getInfos()`: added `position` and `code` fields
- Web page/news links use `slug` instead of `id` (`/page/{slug}`, `/news/{slug}`)

---

## [0.6.0] - 2026-02-06

Full CMS framework major release. Multi-DB support, DI Container, OpenAI integration, full documentation.

### Added
- `SQLiteConnectMiddleware` - SQLite database support (on top of MySQL, PostgreSQL)
- `ContainerMiddleware` - PSR-11 Dependency Injection Container (`codesaur/container`)
- OpenAI integration - moedit editor AI button (`INDO_OPENAI_API_KEY`)
- Image optimization via GD extension - `INDO_CONTENT_IMG_MAX_WIDTH`, `INDO_CONTENT_IMG_QUALITY`
- `ext-gd`, `ext-intl` PHP extension requirements
- `is_featured` field in Pages module (featured pages for footer)
- File attachments in Web templates (page.html, news.html)
- `basename` Twig filter for Web templates
- Native JS file upload (replaced Plupload external library)
- Native JS image preview (replaced Fancybox external library)
- `INDO_JWT_SECRET` auto-generation via Composer post-install script
- Full MN/EN documentation (`docs/mn/`, `docs/en/`)
- API Reference (`docs/mn/api.md`, `docs/en/api.md`)
- `CHANGELOG.md` version history

### Changed
- `codesaur/http-application` ^5.7 -> **^6.0.0** (breaking)
- `codesaur/dataobject` ^7.1 -> **^9.0.0** (breaking, `localized` access pattern changed)
- `codesaur/template` ^1.6 -> **^3.0.0** (breaking)
- `firebase/php-jwt` >=6.7 -> **^7.0.2** (HS256 key length requirement added)
- `getImportantMenu()` -> `getFeaturedPages()` refactor
- `ForgotModel`, `SignupModel` removed - authentication flow simplified
- `JsonExceptionHandler` removed
- Full PHPDoc added to all PHP files

### Fixed
- `localized` access pattern bugs in dashboard templates (DataObject ^9.0 refactor)
- Web news.html file list incorrect column names
- Web page.html stray character

---

## [0.5.0] - 2025-09-22

Content modules reorganized into subdirectories. Web layer template system improved. Multi-DB support started.

### Added
- `Web\Template\` namespace - Web ExceptionHandler, TemplateController
- `Web\SessionMiddleware`, `Web\LocalizationMiddleware` (separate from Dashboard)
- `PostgresConnectMiddleware` - PostgreSQL support
- `SignupModel` (user signup request model)

### Changed
- `PDOConnectMiddleware` -> `MySQLConnectMiddleware` renamed
- Content modules moved to subdirectories: `file/`, `news/`, `page/`, `reference/`, `settings/`
- Localization module separated: `language/`, `text/` subdirectories
- `UserRequestModel` -> `SignupModel` renamed
- `codesaur/dataobject` ^5.2 -> ^7.1

---

## [0.4.0] - 2024-09-28

**Full architectural overhaul.** Migrated from library to project/application structure. Two-layer (Dashboard + Web) architecture introduced.

### Added
- `application/` new directory structure (`raptor/`, `dashboard/`, `web/`)
- `Raptor\`, `Dashboard\`, `Web\` new namespaces (migrated from `Raptor\`)
- `public_html/index.php` entry point - automatic Dashboard/Web routing
- `.env` configuration (`vlucas/phpdotenv`) - all settings in environment file
- Twig template engine support (`codesaur/template`)
- `Raptor\Application` - Dashboard middleware pipeline base
- `Web\Application` - Public website application
- `Dashboard\Application` - Dashboard application
- Brevo (SendInBlue) email API (`getbrevo/brevo-php`)
- `SettingsMiddleware` - system settings middleware
- `DashboardTrait` - Dashboard UI common functions
- `TemplateRouter`, `TemplateController` - Dashboard template system
- `ErrorHandler` - Dashboard template-based error handling
- PSR-3 `Logger` class (built-in)
- `Mailer` class (Brevo + PHPMailer)
- `User` value object (profile, organization, RBAC permissions)
- `psr/log` ^3.0 direct dependency

### Changed
- **`Raptor\` -> `Raptor\`** full namespace change
- **`src/` -> `application/raptor/`** directory structure updated
- `IndoApplication` -> `Raptor\Application`
- `IndoController` -> `Raptor\Controller`
- `codesaur/rbac` -> `Raptor\RBAC\` built-in (separated from external package)
- `codesaur/logger` -> `Raptor\Log\Logger` built-in
- `phpmailer/phpmailer` re-added (^6.8)
- `codesaur/dataobject` ^5.2 re-added (was removed in v5-8)

### Removed
- `InternalRequest`, `InternalController` classes removed
- `JsonResponseMiddleware` removed
- `RecordController`, `StatementController` removed
- `codesaur/rbac` external dependency removed (built-in)
- `codesaur/logger` external dependency removed (built-in)

---

## [0.3.0] - 2024-07-30

Simplified to minimal base library. All CMS modules removed, only core classes remain.

### Changed
- Framework simplified to minimal base (9 PHP files)
- `codesaur/rbac` ^2.3 -> ^2.5
- `codesaur/logger` ^1.5 -> ^2.0

### Removed
- Auth, Localization, Contents, File, Mailer modules fully removed
- `CountriesController`, `CountriesModel`
- `LanguageController`, `LanguageModel`
- `TextController`, `TextModel`, `TextInitial`
- `FilesController`, `FilesModel`, `FileModel`, `FilesRouter`
- `NewsModel`, `PagesController`, `PagesModel`
- `ReferenceController`, `ReferenceModel`, `ReferenceInitial`
- `SettingsModel`
- `MailerController`, `MailerModel`, `MailerRouter`
- `LoggerController`, `LoggerModel`, `LoggerRouter`

---

## [0.2.0] - 2023-07-19

CMS modules added. PHP 8.2.1 requirement. File management, news, pages, references, and settings modules.

### Added
- `Contents` module: `NewsModel`, `PagesController`, `PagesModel`, `ReferenceController`, `ReferenceModel`, `SettingsModel`
- `File` module: `FileModel`, `FilesController`, `FilesModel`, `FilesRouter`
- `Mailer` module: `MailerController`, `MailerModel`, `MailerRouter`
- `Record` module: `RecordController`, `RecordRouter`
- `Statement` module: `StatementController`, `StatementRouter`
- `PDOConnectMiddleware` - DB connection middleware
- `JsonResponseMiddleware` - JSON response middleware
- `JsonExceptionHandler` - JSON exception handler
- `InternalRequest` - Internal API request
- `ContentsRouter` - CMS routes
- `codesaur/http-client` dependency added

### Changed
- PHP >=7.2 -> **PHP 8.2.1** requirement
- `Account\` -> `Auth\` namespace changed
- `TranslationController` -> `TextController` renamed
- `firebase/php-jwt` >=5.2 -> >=6.7
- `codesaur/http-application` >=1.2 -> >=5.5.2
- `codesaur/rbac` >=1.4 -> >=2.3.7

### Removed
- `phpmailer/phpmailer` direct dependency removed
- `codesaur/dataobject` direct dependency removed
- `codesaur/localization` direct dependency removed
- `AccountErrorCode` class removed

---

## [0.1.0] - 2021-04-18

Initial release. REST API-based server framework.

### Features
- PHP >=7.2 support
- `IndoApplication` - PSR-15 Application base
- `IndoController` - Base Controller
- `IndoExceptionHandler` - Exception handler
- JWT authentication (`firebase/php-jwt`)
- Account module: `AuthController`, `AccountController`, `AccountRouter`
- `OrganizationModel`, `OrganizationUserModel`
- `ForgotModel` - Password recovery
- Localization module: `LanguageController`, `CountriesController`, `TranslationController`
- Logger module: `LoggerController`, `LoggerRouter`
- RBAC access control (`codesaur/rbac`)
- PSR-3 logging system (`codesaur/logger`)
- Email sending (`phpmailer/phpmailer`)
- `codesaur/http-application` PSR-7/PSR-15 base
- `codesaur/dataobject` PDO ORM
- MIT License

[1.0.0]: https://github.com/codesaur-php/Raptor/releases/tag/v1.0.0
