<?php

namespace Raptor\Migration;

/**
 * Class MigrationRunner
 *
 * Raptor Framework-ийн файлд суурилсан, зөвхөн урагшлах (forward-only)
 * SQL migration хөдөлгүүр.
 *
 * Онцлог:
 *  - SQL файлуудыг `database/migrations/` хавтсаас уншиж автоматаар ажиллуулна.
 *  - Амжилттай ажилласан файлуудыг `ran/` дэд хавтас руу зөөнө.
 *    Зөөх боломжгүй бол (permission) файлыг устгана.
 *  - `-- [UP]` / `-- [DOWN]` маркераар SQL файлыг задлан боловсруулна.
 *  - Хэсэгчилсэн алдаа (partial failure) гарвал `-- [DOWN]` хэсгийг
 *    автоматаар ажиллуулж цэвэрлэнэ, файл pending хэвээр үлдэнэ.
 *  - MySQL болон PostgreSQL advisory lock-оор зэрэгцээ хүсэлтүүдийн
 *    давхардлаас хамгаална.
 *  - DB хүснэгт ашиглахгүй, зөвхөн файлын системд тулгуурлана.
 *
 * @package Raptor\Migration
 */
class MigrationRunner
{
    /** @var \PDO Өгөгдлийн сангийн холболт */
    private \PDO $pdo;

    /** @var string Pending migration файлуудын зам (database/migrations/) */
    private string $migrationsPath;

    /** @var string Амжилттай ажилласан файлуудын зам (database/migrations/ran/) */
    private string $ranPath;

    /**
     * MigrationRunner constructor.
     *
     * @param \PDO   $pdo             PDO холболт
     * @param string $migrationsPath  Migration SQL файлуудын хавтасны зам
     */
    public function __construct(\PDO $pdo, string $migrationsPath)
    {
        $this->pdo = $pdo;
        $this->migrationsPath = $migrationsPath;
        $this->ranPath = $migrationsPath . '/ran';
    }

    /**
     * Ажиллуулах шаардлагатай (pending) migration файл байгаа эсэхийг шалгах.
     *
     * glob() ашиглан файлын системээс шалгадаг тул DB query шаардахгүй.
     *
     * @return bool Pending файл байвал true
     */
    public function hasPending(): bool
    {
        $files = \glob($this->migrationsPath . '/*.sql');
        return !empty($files);
    }

    /**
     * Бүх pending migration файлуудыг дарааллаар нь ажиллуулах.
     *
     * Ажиллах дараалал:
     *  1. Advisory lock авах (авч чадахгүй бол хоосон буцаана)
     *  2. Pending SQL файлуудыг нэрээр нь эрэмбэлж унших
     *  3. Файл бүрийн `-- [UP]` хэсгийн SQL statement-уудыг ажиллуулах
     *  4. Амжилттай бол файлыг `ran/` руу зөөх (зөөж чадахгүй бол устгах)
     *  5. Алдаа гарвал `-- [DOWN]` хэсгийг ажиллуулж цэвэрлэх, файл pending хэвээр үлдэнэ
     *  6. Lock чөлөөлөх
     *
     * @return array Амжилттай ажилласан файлуудын нэрсийн жагсаалт
     */
    public function migrate(): array
    {
        if (!$this->acquireLock()) {
            return [];
        }

        try {
            $files = $this->getPendingFiles();
            $migrated = [];

            if (!empty($files) && !\is_dir($this->ranPath)) {
                \mkdir($this->ranPath, 0755, true);
            }

            foreach ($files as $file) {
                $name = \basename($file);
                $parsed = $this->parseFile($file);
                $statements = $this->splitStatements($parsed['up']);

                $failed = false;
                foreach ($statements as $sql) {
                    $sql = \trim($sql);
                    if ($sql === '') {
                        continue;
                    }
                    try {
                        $this->pdo->exec($sql);
                    } catch (\Throwable $e) {
                        \error_log("Migration [$name]: {$e->getMessage()}");
                        $failed = true;
                        break;
                    }
                }

                if ($failed) {
                    // Хэсэгчилсэн алдаа: DOWN хэсгийг ажиллуулж цэвэрлэх
                    $downStatements = $this->splitStatements($parsed['down']);
                    foreach ($downStatements as $downSql) {
                        $downSql = \trim($downSql);
                        if ($downSql === '') {
                            continue;
                        }
                        try {
                            $this->pdo->exec($downSql);
                        } catch (\Throwable $ignore) {
                        }
                    }
                } else {
                    // Амжилттай: файлыг ran/ руу зөөх, зөөж чадахгүй бол устгах
                    $destination = $this->resolveDestination($name);
                    $moved = @\rename($file, $destination);
                    if (!$moved) {
                        @\unlink($file);
                    }
                    $migrated[] = $name;
                }
            }

            return $migrated;
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Migration-уудын одоогийн төлөв байдлыг авах.
     *
     * @return array{ran: array, pending: array} ran = ажилласан файлуудын мэдээлэл,
     *                                           pending = хүлээгдэж буй файлуудын нэрс
     */
    public function status(): array
    {
        $pending = [];
        $pendingFiles = $this->getPendingFiles();
        foreach ($pendingFiles as $file) {
            $pending[] = \basename($file);
        }

        $ran = [];
        $ranFiles = \glob($this->ranPath . '/*.sql');
        if (!empty($ranFiles)) {
            // Ажилласан хугацаагаар нь эрэмбэлэх (filemtime)
            \usort($ranFiles, fn($a, $b) => \filemtime($a) - \filemtime($b));
            foreach ($ranFiles as $file) {
                $ran[] = [
                    'file' => \basename($file),
                    'executed_at' => \date('Y-m-d H:i:s', \filemtime($file))
                ];
            }
        }

        return ['ran' => $ran, 'pending' => $pending];
    }

    /**
     * SQL migration файлыг задлан UP/DOWN хэсгүүдэд хуваах.
     *
     * Файлд `-- [UP]` болон `-- [DOWN]` маркер байвал тэдгээрээр хуваана.
     * Маркер огт байхгүй бол файлын бүх агуулгыг UP гэж тооцно.
     * Маркер хайлт case-insensitive (-- [up], -- [UP] аль ч болно).
     *
     * @param string $path SQL файлын бүтэн зам
     * @return array{up: string, down: string} UP болон DOWN SQL агуулга
     */
    public function parseFile(string $path): array
    {
        $content = \file_get_contents($path);

        $upPos = \stripos($content, '-- [UP]');
        $downPos = \stripos($content, '-- [DOWN]');

        if ($upPos === false && $downPos === false) {
            return ['up' => $content, 'down' => ''];
        }

        $up = '';
        $down = '';

        if ($upPos !== false && $downPos !== false) {
            $upStart = $upPos + \strlen('-- [UP]');
            $up = \substr($content, $upStart, $downPos - $upStart);
            $down = \substr($content, $downPos + \strlen('-- [DOWN]'));
        } elseif ($upPos !== false) {
            $up = \substr($content, $upPos + \strlen('-- [UP]'));
        } else {
            $down = \substr($content, $downPos + \strlen('-- [DOWN]'));
        }

        return ['up' => \trim($up), 'down' => \trim($down)];
    }

    /**
     * SQL тексийг бие даасан statement-уудад хуваах.
     *
     * Цэгтэй таслал (;) -аар хуваана. Дараах тохиолдлуудыг зөв боловсруулна:
     *  - String literal доторх цэгтэй таслал (';' болон ";") → хуваахгүй
     *  - SQL comment (-- ...) → алгасна
     *  - Escape хийсэн quote (\') → string дотор гэж тооцно
     *  - Сүүлийн statement-д цэгтэй таслал байхгүй ч хүлээн авна
     *
     * @param string $sql Олон statement агуулсан SQL текст
     * @return array Бие даасан SQL statement-уудын массив
     */
    public function splitStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $length = \strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($inString) {
                $current .= $char;
                if ($char === $stringChar && ($i === 0 || $sql[$i - 1] !== '\\')) {
                    $inString = false;
                }
                continue;
            }

            if ($char === '\'' || $char === '"') {
                $inString = true;
                $stringChar = $char;
                $current .= $char;
                continue;
            }

            // SQL comment (-- ...) алгасах
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

    /**
     * ran/ хавтас дотор ижил нэртэй файл байвал давхардахгүй нэр олох.
     *
     * Жишээ: `name.sql` аль хэдийн байвал `name(1).sql`, дахин байвал `name(2).sql` гэх мэт.
     *
     * @param string $name Файлын нэр
     * @return string Давхардахгүй бүтэн зам
     */
    private function resolveDestination(string $name): string
    {
        $destination = $this->ranPath . '/' . $name;
        if (!\file_exists($destination)) {
            return $destination;
        }

        $extension = \pathinfo($name, PATHINFO_EXTENSION);
        $basename = $extension !== ''
            ? \substr($name, 0, -\strlen($extension) - 1)
            : $name;

        $counter = 1;
        do {
            $newName = $basename . '(' . $counter . ')' . ($extension !== '' ? '.' . $extension : '');
            $destination = $this->ranPath . '/' . $newName;
            $counter++;
        } while (\file_exists($destination));

        return $destination;
    }

    /**
     * Pending (хүлээгдэж буй) SQL файлуудыг нэрээр эрэмбэлж авах.
     *
     * @return array Файлуудын бүтэн замуудын массив
     */
    private function getPendingFiles(): array
    {
        $files = \glob($this->migrationsPath . '/*.sql');
        if ($files === false) {
            return [];
        }
        \sort($files);
        return $files;
    }

    /**
     * Advisory lock авах (зэрэгцээ migration давхардлаас хамгаалах).
     *
     * MySQL: GET_LOCK('raptor_migration', 0) — блоклохгүй, шууд буцаана.
     * PostgreSQL: pg_try_advisory_lock(hashtext('raptor_migration')).
     *
     * @return bool Lock амжилттай авсан бол true
     */
    private function acquireLock(): bool
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            $stmt = $this->pdo->query("SELECT pg_try_advisory_lock(hashtext('raptor_migration'))");
        } else {
            $stmt = $this->pdo->query("SELECT GET_LOCK('raptor_migration', 0)");
        }
        $result = (bool) $stmt->fetchColumn();
        $stmt->closeCursor();
        return $result;
    }

    /**
     * Advisory lock чөлөөлөх.
     *
     * MySQL: RELEASE_LOCK('raptor_migration').
     * PostgreSQL: pg_advisory_unlock(hashtext('raptor_migration')).
     *
     * @return void
     */
    private function releaseLock(): void
    {
        $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            $stmt = $this->pdo->query("SELECT pg_advisory_unlock(hashtext('raptor_migration'))");
        } else {
            $stmt = $this->pdo->query("SELECT RELEASE_LOCK('raptor_migration')");
        }
        $stmt->closeCursor();
    }
}
