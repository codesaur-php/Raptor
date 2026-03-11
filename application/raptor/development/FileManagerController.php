<?php

namespace Raptor\Development;

use Psr\Log\LogLevel;

/**
 * Class FileManagerController
 * ------------------------------------------------------------------
 * Project-ийн файл/фолдерыг Dashboard-аас удирдах контроллер.
 *
 * Зөвхөн system_coder (id=1) эрхтэй хэрэглэгчид хандах боломжтой.
 * Project-ийн root directory-г үндэс болгон ашиглана.
 *
 * @package Raptor\Development
 */
class FileManagerController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Project-ийн root directory-г буцаах.
     */
    private function getProjectRoot(): string
    {
        return \dirname($this->getDocumentRoot());
    }

    /**
     * Хэрэглэгчийн оруулсан зам аюулгүй эсэхийг шалгах.
     * Directory traversal (../../) халдлагаас хамгаалах.
     */
    private function resolveSecurePath(string $relativePath): string|false
    {
        $root = $this->getProjectRoot();
        $relativePath = \str_replace('\\', '/', $relativePath);
        $relativePath = \ltrim($relativePath, '/');

        // Path traversal зогсоох
        if (\str_contains($relativePath, '..')) {
            return false;
        }

        $fullPath = $root . '/' . $relativePath;
        $realPath = \realpath($fullPath);

        if ($realPath === false) {
            return false;
        }

        $realRoot = \realpath($root);
        if (!\str_starts_with($realPath, $realRoot)) {
            return false;
        }

        return $realPath;
    }

    /**
     * Нуугдах ёстой зам мөн эсэх.
     */
    private function isHiddenPath(string $name): bool
    {
        return false;
    }

    /**
     * Файлын хэмжээг хүний унших хэлбэрт хувиргах.
     */
    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return \round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return \round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return \round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * File Manager хуудсыг харуулах.
     *
     * @return void
     */
    public function index()
    {
        if (!$this->isUser('system_coder') || $this->getUserId() !== 1) {
            $this->dashboardProhibited(null, 403)->render();
            return;
        }

        $dashboard = $this->twigDashboard(__DIR__ . '/filemanager.html');
        $dashboard->set('title', 'File Manager');
        $dashboard->render();

        $this->log('development', LogLevel::NOTICE, 'File Manager page opened');
    }

    /**
     * Тодорхой directory-ийн агуулгыг JSON-оор буцаах.
     *
     * Query params:
     *   path - Project root-ээс харьцангуй зам (default: '')
     *
     * @return void
     */
    public function browse()
    {
        try {
            if (!$this->isUser('system_coder') || $this->getUserId() !== 1) {
                throw new \Exception('Access denied', 403);
            }

            $params = $this->getQueryParams();
            $relativePath = $params['path'] ?? '';

            if (empty($relativePath)) {
                $fullPath = $this->getProjectRoot();
            } else {
                $fullPath = $this->resolveSecurePath($relativePath);
                if ($fullPath === false) {
                    throw new \Exception('Path not found or access denied', 400);
                }
            }

            if (!\is_dir($fullPath)) {
                throw new \Exception('Not a directory', 400);
            }

            $items = [];
            $entries = @\scandir($fullPath);
            if ($entries === false) {
                throw new \Exception('Failed to read directory', 500);
            }

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                if ($this->isHiddenPath($entry)) {
                    continue;
                }

                $entryPath = $fullPath . \DIRECTORY_SEPARATOR . $entry;
                $isDir = \is_dir($entryPath);

                $root = $this->getProjectRoot();
                $entryRelative = \str_replace('\\', '/', \substr($entryPath, \strlen($root) + 1));

                $item = [
                    'name' => $entry,
                    'path' => $entryRelative,
                    'is_dir' => $isDir,
                    'size' => $isDir ? null : @\filesize($entryPath),
                    'size_formatted' => $isDir ? '-' : $this->formatSize(@\filesize($entryPath) ?: 0),
                    'modified' => @\date('Y-m-d H:i:s', \filemtime($entryPath) ?: 0),
                    'extension' => $isDir ? '' : \strtolower(\pathinfo($entry, \PATHINFO_EXTENSION)),
                    'permissions' => \substr(\sprintf('%o', @\fileperms($entryPath) ?: 0), -4)
                ];

                $items[] = $item;
            }

            // Directories эхлээд, дараа нь файлууд (нэрээр)
            \usort($items, function ($a, $b) {
                if ($a['is_dir'] !== $b['is_dir']) {
                    return $a['is_dir'] ? -1 : 1;
                }
                return \strcasecmp($a['name'], $b['name']);
            });

            // Breadcrumb үүсгэх
            $breadcrumbs = [['name' => 'Project', 'path' => '']];
            if (!empty($relativePath)) {
                $parts = \explode('/', \str_replace('\\', '/', $relativePath));
                $cumulative = '';
                foreach ($parts as $part) {
                    $cumulative .= ($cumulative ? '/' : '') . $part;
                    $breadcrumbs[] = ['name' => $part, 'path' => $cumulative];
                }
            }

            $this->respondJSON([
                'status' => 'success',
                'path' => $relativePath,
                'breadcrumbs' => $breadcrumbs,
                'items' => $items,
                'count' => \count($items)
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status' => 'error',
                'message' => $err->getMessage()
            ], $err->getCode() ?: 500);
        }
    }

    /**
     * Файлын агуулгыг уншиж JSON-оор буцаах.
     *
     * Query params:
     *   path - Файлын зам (project root-ээс харьцангуй)
     *
     * @return void
     */
    public function readFile()
    {
        try {
            if (!$this->isUser('system_coder') || $this->getUserId() !== 1) {
                throw new \Exception('Access denied', 403);
            }

            $params = $this->getQueryParams();
            $relativePath = $params['path'] ?? '';
            if (empty($relativePath)) {
                throw new \Exception('File path not specified', 400);
            }

            $fullPath = $this->resolveSecurePath($relativePath);
            if ($fullPath === false) {
                throw new \Exception('File not found or access denied', 400);
            }

            if (\is_dir($fullPath)) {
                throw new \Exception('Path is a directory, not a file', 400);
            }

            $size = @\filesize($fullPath) ?: 0;
            $maxReadSize = 2097152; // 2MB
            $extension = \strtolower(\pathinfo($fullPath, \PATHINFO_EXTENSION));

            // Binary файл уу?
            $binaryExtensions = [
                'jpg', 'jpeg', 'png', 'gif', 'bmp', 'ico', 'webp', 'svg',
                'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                'zip', 'rar', 'tar', 'gz', '7z',
                'mp3', 'mp4', 'avi', 'mov', 'wmv', 'flv',
                'exe', 'dll', 'so', 'bin', 'dat',
                'woff', 'woff2', 'ttf', 'eot', 'otf'
            ];

            $isBinary = \in_array($extension, $binaryExtensions);

            // Зураг файл бол
            $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'ico', 'webp', 'svg'];
            $isImage = \in_array($extension, $imageExtensions);

            $content = null;
            $truncated = false;

            if ($isBinary) {
                $content = '[Binary file - cannot display]';
            } elseif ($size > $maxReadSize) {
                $content = @\file_get_contents($fullPath, false, null, 0, $maxReadSize);
                $truncated = true;
            } else {
                $content = @\file_get_contents($fullPath);
            }

            if ($content === false) {
                throw new \Exception('Unable to read file', 500);
            }

            // Encoding шалгах
            if (!$isBinary && !\mb_check_encoding($content, 'UTF-8')) {
                $content = \mb_convert_encoding($content, 'UTF-8', 'auto');
            }

            $result = [
                'status' => 'success',
                'path' => $relativePath,
                'name' => \basename($fullPath),
                'extension' => $extension,
                'size' => $size,
                'size_formatted' => $this->formatSize($size),
                'modified' => @\date('Y-m-d H:i:s', \filemtime($fullPath) ?: 0),
                'is_binary' => $isBinary,
                'is_image' => $isImage,
                'truncated' => $truncated,
                'content' => $content,
                'line_count' => $isBinary ? 0 : \substr_count($content, "\n") + 1
            ];

            $this->respondJSON($result);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status' => 'error',
                'message' => $err->getMessage()
            ], $err->getCode() ?: 500);
        }
    }

    /**
     * Error Log хуудсыг харуулах.
     *
     * @return void
     */
    public function errorLogIndex()
    {
        if (!$this->isUser('system_coder') || $this->getUserId() !== 1) {
            $this->dashboardProhibited(null, 403)->render();
            return;
        }

        $logFile = \dirname($this->getDocumentRoot()) . '/logs/code.log';
        $totalLines = 0;
        if (\is_file($logFile)) {
            $totalLines = $this->countFileLines($logFile);
        }

        $dashboard = $this->twigDashboard(
            __DIR__ . '/error-log.html',
            ['total_lines' => $totalLines]
        );
        $dashboard->set('title', 'Error Log');
        $dashboard->render();
    }

    /**
     * Error log файлыг сүүлээс нь 100 мөрөөр уншиж JSON буцаах.
     *
     * Query params:
     *   page=1 -> хамгийн сүүлийн 100 мөр
     *   page=2 -> түүнээс өмнөх 100 мөр гэх мэт
     *
     * @return void
     */
    public function errorLogRead()
    {
        try {
            if (!$this->isUser('system_coder') || $this->getUserId() !== 1) {
                throw new \Exception('Access denied', 403);
            }

            $logFile = \dirname($this->getDocumentRoot()) . '/logs/code.log';
            if (!\is_file($logFile)) {
                throw new \Exception('Log file not found: logs/code.log', 404);
            }

            $params = $this->getQueryParams();
            $page = \max(1, (int)($params['page'] ?? 1));
            $perPage = 100;

            $totalLines = $this->countFileLines($logFile);
            $totalPages = \max(1, (int)\ceil($totalLines / $perPage));

            // Сүүлээс нь уншихдаа page=1 -> хамгийн сүүлийн мөрүүд
            $endLine = $totalLines - (($page - 1) * $perPage);
            $startLine = \max(1, $endLine - $perPage + 1);

            $lines = [];
            if ($endLine > 0) {
                $lines = $this->readFileLines($logFile, $startLine, $endLine);
                $lines = \array_reverse($lines); // Сүүлийнхийг эхэнд харуулах
            }

            $this->respondJSON([
                'status' => 'success',
                'lines' => $lines,
                'page' => $page,
                'total_pages' => $totalPages,
                'total_lines' => $totalLines,
                'from_line' => $startLine,
                'to_line' => $endLine
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status' => 'error',
                'message' => $err->getMessage()
            ], $err->getCode() ?: 500);
        }
    }

    /**
     * Файлын нийт мөрийн тоог тоолох.
     */
    private function countFileLines(string $filePath): int
    {
        $count = 0;
        $fh = \fopen($filePath, 'r');
        if ($fh) {
            while (!\feof($fh)) {
                $chunk = \fread($fh, 65536);
                $count += \substr_count($chunk, "\n");
            }
            \fclose($fh);
        }
        return $count;
    }

    /**
     * Файлаас тодорхой мөрүүдийг уншиж авах ($startLine-$endLine, 1-based).
     */
    private function readFileLines(string $filePath, int $startLine, int $endLine): array
    {
        $lines = [];
        $fh = \fopen($filePath, 'r');
        if ($fh) {
            $current = 0;
            while (($line = \fgets($fh)) !== false) {
                $current++;
                if ($current >= $startLine && $current <= $endLine) {
                    $lines[] = \rtrim($line);
                }
                if ($current > $endLine) {
                    break;
                }
            }
            \fclose($fh);
        }
        return $lines;
    }

    /**
     * Файлыг татаж авах (download).
     *
     * Query params:
     *   path - Файлын зам
     *
     * @return void
     */
    public function download()
    {
        try {
            if (!$this->isUser('system_coder') || $this->getUserId() !== 1) {
                throw new \Exception('Access denied', 403);
            }

            $params = $this->getQueryParams();
            $relativePath = $params['path'] ?? '';
            if (empty($relativePath)) {
                throw new \Exception('File path not specified', 400);
            }

            $fullPath = $this->resolveSecurePath($relativePath);
            if ($fullPath === false || !\is_file($fullPath)) {
                throw new \Exception('File not found', 404);
            }

            $fileName = \basename($fullPath);
            $fileSize = \filesize($fullPath);
            $mimeType = \mime_content_type($fullPath) ?: 'application/octet-stream';

            \header('Content-Type: ' . $mimeType);
            \header('Content-Disposition: attachment; filename="' . $fileName . '"');
            \header('Content-Length: ' . $fileSize);
            \header('Cache-Control: no-cache, no-store, must-revalidate');

            \readfile($fullPath);
            exit;
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status' => 'error',
                'message' => $err->getMessage()
            ], $err->getCode() ?: 500);
        }
    }
}
