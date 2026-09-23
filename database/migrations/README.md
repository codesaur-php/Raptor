# Raptor Migrations

This directory holds per-environment SQL migrations. The framework uses a
**fully file-based** migration system - there is no tracking table.

## Layout

```
database/migrations/
|-- .gitkeep                       <- placeholder, keeps folder in git
|-- README.md                      <- you are here
|-- {userId}-{username}/           <- per-user folder (created on first upload)
|   |-- 2026-05-13_add_index.sql   <- pending
|   |-- 2026-05-12_bad.sql         <- pending (apply failed; see dashboard log)
|   `-- ran/
|       `-- 2026-05-10_alter.sql   <- successfully applied
`-- ...
```

`{username}` is sanitized for cross-OS safety: only `A-Z a-z 0-9 . _ -` are
kept (anything else becomes `_`), at most 50 characters; a username with no
allowed characters becomes `user`.

State derivation:
- File at `{user_folder}/*.sql` -> **pending**
- File at `{user_folder}/ran/*.sql` -> **applied**

## Workflow

1. A `system_coder` user uploads a `.sql` file via `/dashboard/migrations`.
2. The file lands at `database/migrations/{userId}-{username}/{filename}.sql`.
3. The coder clicks Apply. The framework scans the SQL and warns on writes
   against sensitive tables (`users`, `rbac_*`, `organizations*`,
   `localization_language`, `raptor_menu`), on DCL (`GRANT`/`REVOKE`,
   `CREATE`/`DROP`/`ALTER USER`) and on any `CREATE TABLE` (tables belong in
   Model classes). If there is at least one warning, a typed `CONFIRM` is
   required.
4. On success, the file moves to `{userId}-{username}/ran/` and the framework
   clears the application cache - a migration may have changed cached data
   (`rbac_*` permissions, `raptor_menu`, `localization_*` translations, settings),
   so the cache is flushed to avoid serving stale rows until the TTL expires.
5. On failure, the file stays pending, the cache is left untouched, and the
   error is captured in the `dashboard_log` table with `action: migration-apply`.

## SQL file format

```sql
-- Optional first-line description (becomes the summary in the UI)
ALTER TABLE products ADD COLUMN category VARCHAR(100) DEFAULT NULL;
CREATE INDEX idx_products_category ON products (category);
```

(`ADD COLUMN IF NOT EXISTS` / `CREATE INDEX IF NOT EXISTS` are accepted by
PostgreSQL and MariaDB but rejected by MySQL - use them only when the target
database supports them.)

Statements run in order; the first failure stops the rest and leaves the
file pending. Fix the SQL (or delete the pending file via the UI), then
re-upload.

Write SQL that works on **both MySQL and PostgreSQL** - the framework supports
both drivers. The statement splitter is driver-aware: PostgreSQL `$$...$$`
dollar-quoting is parsed only on pgsql and MySQL `\'` backslash escapes only on
mysql, so a `;` inside a string literal or dollar-quoted block is never mistaken
for a statement separator on either driver.

## Why this directory is git-ignored

Migrations are per-environment: what staging needs is rarely what production
needs at the same moment. Each server keeps its own audit trail of who
uploaded what and which files ran successfully. Only `.gitkeep` and this
`README.md` are tracked by git.
