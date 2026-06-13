<?php

namespace Raptor\Migration;

use codesaur\DataObject\Constants;

/**
 * Class MigrationRunner
 *
 * Raptor Framework-ийн file-based SQL migration хөдөлгүүр.
 *
 * State tracking:
 *   DB хүснэгт ашиглахгүй. Файлын байршил нь state-ийг тодорхойлно.
 *
 *     database/migrations/
 *       └── {userId}-{username}/
 *             ├── pending_file.sql       <- pending
 *             └── ran/
 *                   └── applied_file.sql <- applied
 *
 * Файлын формат: ердийн SQL - бүх statement-уудыг дарааллаар нь ажиллуулна.
 * Аль нэг statement унавал бусад нь зогсож, файл pending хэвээр үлдэнэ
 * (Fix хийгээд дахин apply).
 *
 * Per-environment audit: хэн ямар файл upload хийсэн, аль нь амжилттай /
 * амжилтгүй ажилласан нь файлын систем дээр харагдаж байна.
 *
 * @package Raptor\Migration
 */
class MigrationRunner
{
    private \PDO $pdo;
    private string $migrationsPath;
    private MigrationSecurityScanner $scanner;

    public function __construct(\PDO $pdo, string $migrationsPath)
    {
        $this->pdo = $pdo;
        $this->migrationsPath = \rtrim($migrationsPath, "/\\");
        $this->scanner = new MigrationSecurityScanner();
    }

    /**
     * Бүх user folder-уудын дотроос pending ба ran файлуудыг
     * нэгтгэн status буцаана.
     *
     * @return array{folders: array<int, array{user_id:int, username:string, label:string, pending:array, ran:array}>}
     */
    public function status(): array
    {
        $folders = [];
        foreach ($this->getUserFolders() as $folder) {
            $folders[] = [
                'user_id'  => $folder['user_id'],
                'username' => $folder['username'],
                'label'    => $folder['label'],
                'pending'  => $this->describeFiles($folder['path'], false),
                'ran'      => $this->describeFiles($folder['path'] . '/ran', true),
            ];
        }
        return ['folders' => $folders];
    }

    /**
     * Тодорхой нэг pending файлыг ажиллуулна.
     *
     * Алхам:
     *   1. Advisory lock - зэрэгцээ apply-аас сэргийлэх
     *   2. SQL уншиж SHA-256 hash тооцоолох
     *   3. Statement-уудад хуваах (string/comment/dollar-quote aware)
     *   4. Тус бүрийг exec(). Алдаа гарвал early return - файл pending хэвээр
     *   5. Амжилттай бол `ran/` folder руу шилжүүлнэ
     *
     * @param string $folder   `{id}-{username}` хэлбэрийн folder нэр
     * @param string $filename `.sql` файлын нэр (basename only)
     *
     * @return array{
     *   ok: bool,
     *   sha256: string,
     *   statements: int,
     *   error?: string,
     *   moved_to?: string
     * }
     */
    public function apply(string $folder, string $filename): array
    {
        $path = $this->resolvePending($folder, $filename);
        if ($path === null) {
            return ['ok' => false, 'sha256' => '', 'statements' => 0, 'error' => 'File not found in pending folder'];
        }

        if (!$this->acquireLock()) {
            return ['ok' => false, 'sha256' => '', 'statements' => 0, 'error' => 'Another migration is in progress'];
        }

        try {
            $sql = \file_get_contents($path);
            if ($sql === false) {
                // Файл уншигдахгүй бол (эрх, mid-flight устгал) хоосон гэж андуурч
                // ran/-руу "амжилттай" зөөхөөс сэргийлж шууд алдаа буцаана.
                return ['ok' => false, 'sha256' => '', 'statements' => 0, 'error' => 'Could not read migration file'];
            }
            $sha256 = \hash('sha256', $sql);
            $statements = $this->splitStatements($sql);

            foreach ($statements as $stmt) {
                try {
                    $this->pdo->exec($stmt);
                } catch (\Throwable $e) {
                    $msg = $e->getMessage();
                    \error_log("Migration [$folder/$filename]: $msg");
                    return [
                        'ok'         => false,
                        'sha256'     => $sha256,
                        'statements' => \count($statements),
                        'error'      => $msg,
                    ];
                }
            }

            $movedTo = $this->moveToRan($folder, $filename);

            return [
                'ok'         => true,
                'sha256'     => $sha256,
                'statements' => \count($statements),
                'moved_to'   => $movedTo,
            ];
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Security scan: SQL агуулгыг static-аар шалгах.
     */
    public function scan(string $sql): array
    {
        return $this->scanner->scan($sql);
    }

    /**
     * SQL агуулгаас товч summary гаргах.
     *
     * Эхний 1-р мөр нь `--` -ээр эхэлсэн бол түүнийг тайлбараар авна.
     * Үгүй бол statement-уудаас уншиж "ALTER TABLE x: ADD COLUMN y" гэх мэт
     * товч мэдээллийг үүсгэнэ.
     *
     * @return string  ≤500 тэмдэгт
     */
    public function summarize(string $sql): string
    {
        $sql = \ltrim($sql);
        if (\str_starts_with($sql, '--')) {
            $firstLine = \strtok($sql, "\n");
            $comment = \trim(\ltrim($firstLine, '-'));
            if ($comment !== '') {
                return $this->truncate($comment, 500);
            }
        }

        $statements = $this->splitStatements($sql);
        $parts = [];
        foreach ($statements as $stmt) {
            $first = \trim(\strtok($stmt, "\n"));
            $parts[] = $this->truncate($first, 80);
            if (\count($parts) >= 5) {
                $parts[] = '...';
                break;
            }
        }
        return $this->truncate(\implode('; ', $parts), 500);
    }

    /**
     * Upload-той ижил байх ёстой validation: filename → user folder.
     *
     * Cross-OS folder name шалгуур:
     *   - Allow list: A-Z a-z 0-9 . _ -    (Windows-ийн forbidden chars автоматаар хасагдана)
     *   - Trailing `.`, `-`, `_`, space → хасна (Windows NTFS trailing dot stripping-ээс)
     *   - Leading `.` → хасна (Unix hidden file-аас сэргийлэх)
     *   - Result `.`, `..` эсвэл хоосон бол `user`-р орлуулна
     *   - Max 50 char-аар хязгаарлана (path length / Windows MAX_PATH-ийн нөөц)
     *
     * Numeric prefix `{userId}-` нь Windows reserved name-уудтай (`CON`, `PRN`,
     * `NUL`, `COM1` ...) огтхон ч таарахгүй (тэдгээр нь үсгээр эхэлдэг).
     *
     * @return string Folder-ийн бүтэн зам (үүсээгүй байж болно)
     */
    public function getUserFolderPath(int $userId, string $username): string
    {
        $safe = \preg_replace('/[^A-Za-z0-9._-]/', '_', $username);
        $safe = \trim($safe, ".-_ ");
        if ($safe === '' || $safe === '.' || $safe === '..') {
            $safe = 'user';
        }
        if (\strlen($safe) > 50) {
            $safe = \substr($safe, 0, 50);
            $safe = \rtrim($safe, ".-_ ");
            if ($safe === '') {
                $safe = 'user';
            }
        }
        return $this->migrationsPath . '/' . $userId . '-' . $safe;
    }

    /**
     * `{userId}-{username}` folder-уудыг scan.
     *
     * @return array<int, array{user_id:int, username:string, label:string, path:string}>
     */
    private function getUserFolders(): array
    {
        if (!\is_dir($this->migrationsPath)) {
            return [];
        }

        $entries = \scandir($this->migrationsPath);
        if ($entries === false) {
            return [];
        }

        $folders = [];
        foreach ($entries as $name) {
            if ($name === '.' || $name === '..' || $name === '.gitkeep' || $name === 'README.md') {
                continue;
            }
            $path = $this->migrationsPath . '/' . $name;
            if (!\is_dir($path)) {
                continue;
            }
            if (!\preg_match('/^(\d+)-(.+)$/', $name, $m)) {
                continue;
            }
            $folders[] = [
                'user_id'  => (int) $m[1],
                'username' => $m[2],
                'label'    => $name,
                'path'     => $path,
            ];
        }

        \usort($folders, fn($a, $b) => $a['user_id'] <=> $b['user_id']);
        return $folders;
    }

    /**
     * Folder доторх .sql файлуудыг бүх metadata-тай нь буцаана.
     *
     * @param bool $applied  true бол status='ran', false бол 'pending'
     */
    private function describeFiles(string $path, bool $applied): array
    {
        if (!\is_dir($path)) {
            return [];
        }

        $files = \glob($path . '/*.sql');
        if ($files === false) {
            return [];
        }
        \sort($files);

        $out = [];
        foreach ($files as $file) {
            $sql = (string) \file_get_contents($file);
            $out[] = [
                'file'     => \basename($file),
                'summary'  => $this->summarize($sql),
                'size'     => \filesize($file) ?: 0,
                'modified' => \date('Y-m-d H:i:s', \filemtime($file) ?: \time()),
                'status'   => $applied ? 'ran' : 'pending',
            ];
        }
        return $out;
    }

    /**
     * Pending file-ийн бүтэн замыг буцаана. `..` ба separator block.
     */
    private function resolvePending(string $folder, string $filename): ?string
    {
        if (!$this->isSafeName($folder) || !$this->isSafeName($filename) || !\str_ends_with($filename, '.sql')) {
            return null;
        }
        $path = $this->migrationsPath . '/' . $folder . '/' . $filename;
        return \is_file($path) ? $path : null;
    }

    /**
     * Файлыг `ran/` folder руу шилжүүлнэ.
     *
     * @return string  Шинэ замын basename + folder
     */
    private function moveToRan(string $folder, string $filename): string
    {
        $src = $this->migrationsPath . '/' . $folder . '/' . $filename;
        $ranDir = $this->migrationsPath . '/' . $folder . '/ran';
        if (!\is_dir($ranDir) && !\mkdir($ranDir, 0775, true) && !\is_dir($ranDir)) {
            throw new \RuntimeException("Could not create ran directory: $ranDir");
        }
        $dst = $ranDir . '/' . $filename;

        if (\file_exists($dst)) {
            // Ижил нэртэй өмнө нь applied файл байгаа бол timestamp нэмж rename
            $dst = $ranDir . '/' . \pathinfo($filename, PATHINFO_FILENAME)
                . '_' . \date('YmdHis')
                . '.sql';
        }

        \rename($src, $dst);
        return $folder . '/ran/' . \basename($dst);
    }

    /**
     * Folder/file name доторх dangerous character илрүүлэх.
     * Зөвхөн a-z A-Z 0-9 . _ - зөвшөөрөгдөнө.
     */
    private function isSafeName(string $name): bool
    {
        return $name !== '' && \preg_match('/^[A-Za-z0-9._-]+$/', $name) === 1;
    }

    private function truncate(string $s, int $max): string
    {
        if (\strlen($s) <= $max) {
            return $s;
        }
        return \substr($s, 0, $max - 3) . '...';
    }

    /**
     * SQL текстийг бие даасан statement-уудад хуваах.
     *
     * Цэгтэй таслал (;) -аар хуваана. String literal, SQL comment,
     * PostgreSQL dollar-quoted string бүгдийг зөв боловсруулна.
     */
    public function splitStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $inDollar = false;
        $dollarTag = '';
        $length = \strlen($sql);

        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        // Dollar-quoting ($$...$$) нь зөвхөн PostgreSQL-ийн онцлог. MySQL/SQLite дээр
        // идэвхжүүлбэл санамсаргүй $...$ хос нь statement-уудыг (тэдгээрийн хооронд
        // байх ; -ийг залгиснаар) буруу нэгтгэдэг тул зөвхөн pgsql дээр асаана.
        $pgDollar = $driver === Constants::DRIVER_PGSQL;
        // String literal escape: MySQL дээр \' нь quote-г escape хийдэг. PostgreSQL
        // (standard_conforming_strings) ба SQLite дээр backslash literal бөгөөд зөвхөн
        // '' (doubled quote) escape хийдэг тул backslash-escape-ийг зөвхөн MySQL дээр.
        $mysqlEscape = $driver === Constants::DRIVER_MYSQL;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($inDollar) {
                $current .= $char;
                if ($char === '$' && \substr($sql, $i, \strlen($dollarTag)) === $dollarTag) {
                    $current .= \substr($sql, $i + 1, \strlen($dollarTag) - 1);
                    $i += \strlen($dollarTag) - 1;
                    $inDollar = false;
                    $dollarTag = '';
                }
                continue;
            }

            if ($inString) {
                $current .= $char;
                // MySQL дээр quote-г backslash-аар escape хийдэг. Гэхдээ өмнөх
                // backslash-ууд хосоороо (\\) бол тэдгээр нь literal backslash тул
                // quote escape хийгдээгүй. Тиймээс өмнөх дараалсан backslash-уудын
                // тоог тоолж, СОНДГОЙ үед л quote escape хийгдсэн гэж үзнэ.
                $backslashEscaped = false;
                if ($mysqlEscape) {
                    $backslashes = 0;
                    for ($j = $i - 1; $j >= 0 && $sql[$j] === '\\'; $j--) {
                        $backslashes++;
                    }
                    $backslashEscaped = ($backslashes % 2) === 1;
                }
                if ($char === $stringChar && !$backslashEscaped) {
                    $inString = false;
                }
                continue;
            }

            if ($pgDollar && $char === '$') {
                $closing = \strpos($sql, '$', $i + 1);
                if ($closing !== false) {
                    $tagBody = \substr($sql, $i + 1, $closing - $i - 1);
                    if ($tagBody === '' || \preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $tagBody)) {
                        $dollarTag = '$' . $tagBody . '$';
                        $current .= $dollarTag;
                        $i = $closing;
                        $inDollar = true;
                        continue;
                    }
                }
            }

            if ($char === '\'' || $char === '"') {
                $inString = true;
                $stringChar = $char;
                $current .= $char;
                continue;
            }

            if ($char === '-' && $i + 1 < $length && $sql[$i + 1] === '-') {
                $end = \strpos($sql, "\n", $i);
                if ($end === false) {
                    break;
                }
                $i = $end;
                continue;
            }

            if ($char === ';') {
                $trimmed = \trim($current);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $trimmed = \trim($current);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }

    private function acquireLock(): bool
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === Constants::DRIVER_PGSQL) {
            $stmt = $this->pdo->query("SELECT pg_try_advisory_lock(hashtext('raptor_migration'))");
        } else {
            $stmt = $this->pdo->query("SELECT GET_LOCK('raptor_migration', 0)");
        }
        $result = (bool) $stmt->fetchColumn();
        $stmt->closeCursor();
        return $result;
    }

    private function releaseLock(): void
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === Constants::DRIVER_PGSQL) {
            $stmt = $this->pdo->query("SELECT pg_advisory_unlock(hashtext('raptor_migration'))");
        } else {
            $stmt = $this->pdo->query("SELECT RELEASE_LOCK('raptor_migration')");
        }
        $stmt->closeCursor();
    }
}
