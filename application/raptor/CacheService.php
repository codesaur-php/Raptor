<?php

namespace Raptor;

use Psr\SimpleCache\CacheInterface;

/**
 * Class CacheService
 *
 * PSR-16 (SimpleCache) стандартыг бүрэн хэрэгжүүлсэн файл суурьтай cache.
 * Гадаад dependency-гүй, зөвхөн psr/simple-cache interface ашиглана.
 * cPanel shared host, XAMPP, localhost зэрэг бүх орчинд ажиллана.
 *
 * Cache entry бүр [expiry_timestamp, value] хос болж serialize хийгдэн
 * SHA-1 hash нэртэй .cache файлд хадгалагдана. Хугацаа дууссан entry-г
 * get() дуудах үед автоматаар устгана.
 *
 * ContainerMiddleware-д 'cache' service-ээр бүртгэгдсэн бөгөөд controller-ууд
 * $this->getService('cache') ашиглан хандана. Write permission байхгүй бол
 * ContainerMiddleware null буцааж, систем cache-гүйгээр DB-ээс шууд уншина.
 *
 * @package Raptor
 *
 * @see \Raptor\ContainerMiddleware::registerServices() Cache service бүртгэл
 * @see \Raptor\Controller::invalidateCache() Cache invalidation helper
 */
class CacheService implements CacheInterface
{
    /** @var string Cache файлуудын хадгалагдах directory */
    private string $dir;

    /** @var int Default TTL (секундээр). 0 бол хугацаагүй */
    private int $ttl;

    /**
     * @param string $cacheDir Cache файлуудын directory зам
     * @param int $defaultTtl Default TTL секундээр (0 = хугацаагүй)
     */
    public function __construct(string $cacheDir, int $defaultTtl = 3600)
    {
        $this->dir = $cacheDir;
        $this->ttl = $defaultTtl;

        // Race condition: өөр process нэгэн зэрэг үүсгэж байж болзошгүй тул
        // warning суурийг дарж эцсийн төлвийг шалгана.
        if (!\is_dir($this->dir) && !@\mkdir($this->dir, 0755, true) && !\is_dir($this->dir)) {
            throw new \RuntimeException("CacheService: cannot create cache directory [$this->dir]");
        }
    }

    /**
     * Cache-ээс утга авах.
     *
     * Файл байхгүй, unserialize амжилтгүй, эсвэл хугацаа дууссан бол
     * $default буцаана. Хугацаа дууссан файлыг автоматаар устгана.
     *
     * @param string $key Cache түлхүүр
     * @param mixed $default Олдоогүй үед буцаах утга
     * @return mixed Cache-д хадгалсан утга эсвэл $default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->path($key);
        if (!\is_file($file)) {
            return $default;
        }

        // Файл унших алдааг (race, lock) suppress хийнэ - false буцвал unserialize-д баригдана
        $raw = @\file_get_contents($file);
        if ($raw === false) {
            return $default;
        }

        $data = @\unserialize($raw);
        if (!\is_array($data) || \count($data) !== 2 || ($data[0] !== 0 && $data[0] < \time())) {
            @\unlink($file);
            return $default;
        }

        return $data[1];
    }

    /**
     * Cache-д утга хадгалах.
     *
     * [expiry, value] хос serialize хийж файлд бичнэ. LOCK_EX ашиглан
     * concurrent write-аас хамгаална.
     *
     * @param string $key Cache түлхүүр
     * @param mixed $value Хадгалах утга (serialize хийгдэх боломжтой дурын утга)
     * @param \DateInterval|int|null $ttl Хугацаа секундээр. null бол defaultTtl ашиглана
     * @return bool Амжилттай бол true
     */
    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        if ($ttl instanceof \DateInterval) {
            $now = new \DateTimeImmutable();
            $seconds = $now->add($ttl)->getTimestamp() - $now->getTimestamp();
            $exp = $seconds > 0 ? \time() + $seconds : 0;
        } elseif (\is_int($ttl)) {
            $exp = $ttl > 0 ? \time() + $ttl : 0;
        } else {
            $exp = $this->ttl > 0 ? \time() + $this->ttl : 0;
        }

        // Windows lock / disk full / permission зэрэгт warning дарна, false буцаана
        return @\file_put_contents($this->path($key), \serialize([$exp, $value]), \LOCK_EX) !== false;
    }

    /**
     * Cache-ээс түлхүүрээр устгах.
     *
     * @param string $key Cache түлхүүр
     * @return bool Амжилттай бол true (файл байхгүй бол мөн true)
     */
    public function delete(string $key): bool
    {
        $file = $this->path($key);
        if (!\is_file($file)) {
            return true;
        }
        // Windows file lock эсвэл race condition үед warning дарж true/false буцаана
        return @\unlink($file) || !\file_exists($file);
    }

    /**
     * Бүх cache файлуудыг устгах.
     *
     * Best-effort cross-platform хэлбэрээр:
     *   - Windows file lock, race condition зэрэгт `@` ашиглаж warning дарна
     *   - Iterator-ийн permission/IO алдааг exception-аар барина
     *   - PSR-16 ёсоор аль нэг файл устгагдаагүй бол false буцаана
     *
     * @return bool Бүх файлыг амжилттай устгасан бол true
     */
    public function clear(): bool
    {
        if (!\is_dir($this->dir)) {
            return true;
        }

        $success = true;
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $f) {
                $path = $f->getPathname();
                $ok = $f->isDir() ? @\rmdir($path) : @\unlink($path);
                if (!$ok && \file_exists($path)) {
                    $success = false;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        return $success;
    }

    /**
     * Түлхүүр cache-д байгаа эсэхийг шалгах.
     *
     * Зөвхөн файлын байдлаар шалгана - null утгатай бичлэгийг "байгаа" гэж тоолно.
     * Хугацаа дууссан бол файлыг устгаж false буцаана.
     *
     * @param string $key Cache түлхүүр
     * @return bool Байвал true
     */
    public function has(string $key): bool
    {
        $file = $this->path($key);
        if (!\is_file($file)) {
            return false;
        }

        $raw = @\file_get_contents($file);
        if ($raw === false) {
            return false;
        }

        $data = @\unserialize($raw);
        if (!\is_array($data) || \count($data) !== 2 || ($data[0] !== 0 && $data[0] < \time())) {
            @\unlink($file);
            return false;
        }
        return true;
    }

    /**
     * Олон түлхүүрийн утгыг нэг дор авах.
     *
     * @param iterable<string> $keys Түлхүүрүүд
     * @param mixed $default Олдоогүй үед буцаах утга
     * @return iterable<string, mixed> Түлхүүр => утга хос
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $r = [];
        foreach ($keys as $k) {
            $r[$k] = $this->get($k, $default);
        }
        return $r;
    }

    /**
     * Олон утгыг нэг дор хадгалах.
     *
     * @param iterable<string, mixed> $values Түлхүүр => утга хос
     * @param \DateInterval|int|null $ttl Хугацаа секундээр
     * @return bool Бүгд амжилттай бол true
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $k => $v) {
            if (!$this->set($k, $v, $ttl)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Олон түлхүүрийг нэг дор устгах.
     *
     * @param iterable<string> $keys Түлхүүрүүд
     * @return bool Бүгд амжилттай бол true
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $k) {
            if (!$this->delete($k)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Cache түлхүүрийг файл зам руу хөрвүүлэх.
     *
     * @param string $key Cache түлхүүр
     * @return string Файлын бүтэн зам
     */
    private function path(string $key): string
    {
        return $this->dir . '/' . \sha1($key) . '.cache';
    }
}
