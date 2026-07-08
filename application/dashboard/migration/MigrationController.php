<?php

namespace Dashboard\Migration;

use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LogLevel;

/**
 * Class MigrationController
 *
 * File-based Raptor Migration удирдлагын controller.
 *
 * Зөвхөн `system_coder` эрхтэй хэрэглэгч хандах боломжтой.
 *
 * Workflow:
 *   1. Coder Migration хуудаснаас .sql файл upload хийнэ
 *      -> `database/migrations/{userId}-{username}/{file}.sql` хадгалагдана
 *   2. Status хуудаснаа pending файл харагдана
 *   3. Apply товч дарвал security scan ажиллана -> warning байвал
 *      confirm modal-д "CONFIRM" гэж бичүүлж баталгаажуулна
 *   4. Apply амжилттай -> файл `{userId}-{username}/ran/{file}.sql` руу зөөгдөнө
 *      ба cache цэвэрлэгдэнэ (migration нь cached дата - эрх, цэс, орчуулга,
 *      тохиргоо - өөрчилсөн байж болзошгүй тул)
 *   5. Apply амжилтгүй -> файл pending хэвээр, cache хэвээр, error log-д бичигдэнэ
 *
 * @package Dashboard\Migration
 */
class MigrationController extends \Dashboard\Controller
{
    use \Dashboard\Template\DashboardTrait;

    /** Бид ийм том файлыг гар upload-аар хүлээж авахгүй. */
    private const SIZE_CEILING = 10 * 1024 * 1024; // 10 MB

    /**
     * Бодит max upload size: `min(10 MB, post_max_size, upload_max_filesize)`.
     * PHP-ийн өөрийн limit-ээс илүү хүлээж авах боломжгүй тул хамгийн бага утгыг
     * runtime-аар тооцно.
     */
    private function getMaxUploadSize(): int
    {
        $candidates = [self::SIZE_CEILING];
        foreach (['post_max_size', 'upload_max_filesize'] as $key) {
            $bytes = $this->parseIniSize((string) \ini_get($key));
            if ($bytes > 0) {
                $candidates[] = $bytes;
            }
        }
        return \min($candidates);
    }

    /**
     * "10M" / "8K" / "1G" гэх мэт ini-size string-ийг bytes болгоно.
     */
    private function parseIniSize(string $value): int
    {
        $value = \trim($value);
        if ($value === '') {
            return 0;
        }
        $unit = \strtolower($value[\strlen($value) - 1]);
        $number  = (int) $value;
        return match ($unit) {
            'g'     => $number * 1024 * 1024 * 1024,
            'm'     => $number * 1024 * 1024,
            'k'     => $number * 1024,
            default => $number,
        };
    }

    /**
     * Зөвхөн system_coder.
     */
    private function guardAccess(): void
    {
        if (!$this->isUser('system_coder')) {
            $this->respondJSON(['error' => 'Unauthorized'], 403);
            exit;
        }
    }

    private function getRunner(): MigrationRunner
    {
        return new MigrationRunner($this->pdo, $this->getMigrationsPath());
    }

    private function getMigrationsPath(): string
    {
        return \dirname(__DIR__, 3) . '/database/migrations';
    }

    /**
     * Migration хуудас.
     */
    public function index()
    {
        if (!$this->isUser('system_coder')) {
            $this->redirectTo('login');
            return;
        }

        $max = $this->getMaxUploadSize();
        $this->dashboardTemplate(__DIR__ . '/migration-index.html', [
            'max_upload_size'       => $max,
            'max_upload_size_human' => $this->formatBytes($max),
        ])->render();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return \round($bytes / 1024 / 1024, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return \round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * GET /dashboard/migrations/status
     */
    public function status()
    {
        $this->guardAccess();

        try {
            $this->respondJSON($this->getRunner()->status());
        } catch (\Throwable $e) {
            $this->respondJSON(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /dashboard/migrations/view?folder=X&state=pending|ran&file=Y
     *
     * Modal-д SQL агуулга + summary + scan warnings гаргана.
     */
    public function view()
    {
        $this->guardAccess();

        $params  = $this->getRequest()->getQueryParams();
        $folder  = (string) ($params['folder'] ?? '');
        $state   = (string) ($params['state']  ?? '');
        $file    = (string) ($params['file']   ?? '');

        $path = $this->resolveFile($folder, $state, $file);
        if ($path === null) {
            $this->viewModal('Error', '<p class="text-danger">File not found or invalid path</p>');
            return;
        }

        $sql      = (string) \file_get_contents($path);
        $sha256   = \hash('sha256', $sql);
        $runner   = $this->getRunner();
        $summary  = $runner->summarize($sql);
        $warnings = $runner->scan($sql);

        $stateBadge = $state === 'ran'
            ? '<span class="badge bg-success ms-2">Ran</span>'
            : '<span class="badge bg-warning text-dark ms-2">Pending</span>';

        $title = '<i class="bi bi-file-earmark-code"></i> '
            . \htmlspecialchars($folder . '/' . ($state === 'ran' ? 'ran/' : '') . $file, ENT_QUOTES, 'UTF-8')
            . $stateBadge;

        $warningsHtml = '';
        if (!empty($warnings)) {
            $warningsHtml = '<div class="alert alert-warning mx-3 mt-3 mb-0"><strong><i class="bi bi-exclamation-triangle"></i> Security warnings:</strong><ul class="mb-0 mt-1">';
            foreach ($warnings as $w) {
                $warningsHtml .= '<li>' . \htmlspecialchars($w['reason'], ENT_QUOTES, 'UTF-8') . '</li>';
            }
            $warningsHtml .= '</ul></div>';
        }

        $body = '<div class="px-3 pt-3 small text-body-secondary">'
            . '<div><strong>Summary:</strong> ' . \htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') . '</div>'
            . '<div><strong>SHA-256:</strong> <code>' . $sha256 . '</code></div>'
            . '<div><strong>Size:</strong> ' . \filesize($path) . ' bytes</div>'
            . '</div>'
            . $warningsHtml
            . '<pre class="m-0 mt-3 p-3 bg-body-tertiary" style="max-height:400px; overflow:auto;"><code>'
            . \htmlspecialchars($sql, ENT_QUOTES, 'UTF-8')
            . '</code></pre>';

        $this->viewModal($title, $body, false);
    }

    /**
     * POST /dashboard/migrations/upload
     *
     * Multipart `file` талбараар .sql файл хүлээж аваад
     * `{userId}-{username}/` folder руу хадгална.
     */
    public function upload()
    {
        $this->guardAccess();

        try {
            $uploaded = $this->getRequest()->getUploadedFiles()['file'] ?? null;
            if (!$uploaded instanceof UploadedFileInterface) {
                $this->respondJSON(['error' => 'No file provided'], 400);
                return;
            }
            if ($uploaded->getError() !== \UPLOAD_ERR_OK) {
                $this->respondJSON(['error' => 'Upload error code ' . $uploaded->getError()], 400);
                return;
            }
            $maxBytes = $this->getMaxUploadSize();
            // PSR-7 getSize() null буцааж болно (хэмжээ тодорхойгүй) - энэ үед
            // null > $maxBytes нь false болж шалгалт алгасагдахаас сэргийлж шууд татгалзана.
            $size = $uploaded->getSize();
            if ($size === null || $size > $maxBytes) {
                $this->respondJSON([
                    'error' => 'File too large or size unknown (max ' . $this->formatBytes($maxBytes) . ')',
                ], 400);
                return;
            }

            $clientName = $uploaded->getClientFilename() ?? '';
            $name = \basename($clientName);
            if (\strtolower(\pathinfo($name, PATHINFO_EXTENSION)) !== 'sql') {
                $this->respondJSON(['error' => 'Only .sql files are accepted'], 400);
                return;
            }
            $safeName = \preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
            if ($safeName === '' || $safeName === '.' || $safeName === '..') {
                $this->respondJSON(['error' => 'Invalid filename'], 400);
                return;
            }

            $user = $this->getUser();
            if ($user === null) {
                $this->respondJSON(['error' => 'No authenticated user'], 401);
                return;
            }
            $userId   = (int) ($user->profile['id'] ?? 0);
            $username = (string) ($user->profile['username'] ?? '');
            if ($userId <= 0 || $username === '') {
                $this->respondJSON(['error' => 'Invalid user'], 401);
                return;
            }

            $folderPath = $this->getRunner()->getUserFolderPath($userId, $username);
            // mkdir амжилтгүй болоод дараа нь moveTo() ойлгомжгүй алдаа өгөхөөс
            // сэргийлж энд тодорхой шалгана (race-д is_dir дахин шалгана).
            if (!\is_dir($folderPath) && !\mkdir($folderPath, 0775, true) && !\is_dir($folderPath)) {
                $this->respondJSON(['error' => 'Could not create migration folder'], 500);
                return;
            }

            $destination = $folderPath . '/' . $safeName;
            if (\file_exists($destination)) {
                $this->respondJSON([
                    'error' => 'A pending file with the same name already exists. Delete it first or rename your upload.',
                ], 409);
                return;
            }

            $ranSibling = $folderPath . '/ran/' . $safeName;
            if (\file_exists($ranSibling)) {
                $this->respondJSON([
                    'error' => 'A file with this name has already been applied (exists in ran/). Rename your upload to avoid confusion.',
                ], 409);
                return;
            }

            // Stream upload to disk
            $uploaded->moveTo($destination);

            // Read back for summary + scan (after moveTo so it's final on disk)
            $sql      = (string) \file_get_contents($destination);
            $sha256   = \hash('sha256', $sql);
            $summary  = $this->getRunner()->summarize($sql);
            $warnings = $this->getRunner()->scan($sql);

            $this->log('dashboard', LogLevel::INFO, "Migration uploaded: " . \basename($folderPath) . "/$safeName", [
                'action'   => 'migration-upload',
                'folder'   => \basename($folderPath),
                'file'     => $safeName,
                'sha256'   => $sha256,
                'summary'  => $summary,
                'warnings' => \count($warnings),
            ]);

            $this->respondJSON([
                'ok'       => true,
                'folder'   => \basename($folderPath),
                'file'     => $safeName,
                'sha256'   => $sha256,
                'summary'  => $summary,
                'warnings' => $warnings,
            ]);
        } catch (\Throwable $e) {
            $this->respondJSON(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /dashboard/migrations/apply
     *
     * Body: {folder, file, confirm: "CONFIRM"}
     *
     * Warning байгаа бол `confirm === 'CONFIRM'` шаардана.
     * Амжилттай бол файл `ran/` руу зөөгдөж, cache цэвэрлэгдэнэ (migration нь
     * cached дата - эрх, цэс, орчуулга, тохиргоо - өөрчилсөн байж болзошгүй тул).
     */
    public function apply()
    {
        $this->guardAccess();

        try {
            $body    = (array) $this->getRequest()->getParsedBody();
            $folder  = (string) ($body['folder']  ?? '');
            $file    = (string) ($body['file']    ?? '');
            $confirm = (string) ($body['confirm'] ?? '');

            $path = $this->resolveFile($folder, 'pending', $file);
            if ($path === null) {
                $this->respondJSON(['error' => 'File not found in pending folder'], 404);
                return;
            }

            $sql      = (string) \file_get_contents($path);
            $runner   = $this->getRunner();
            $warnings = $runner->scan($sql);

            if (!empty($warnings) && $confirm !== 'CONFIRM') {
                $this->respondJSON([
                    'ok'              => false,
                    'needs_confirm'   => true,
                    'warnings'        => $warnings,
                    'error'           => 'Security warnings present - type CONFIRM to proceed',
                ], 409);
                return;
            }

            $result = $runner->apply($folder, $file);

            // Migration нь cached дата (эрх, цэс, орчуулга, тохиргоо) өөрчилсөн
            // байж болзошгүй тул амжилттай apply болсны дараа бүх cache-г цэвэрлэнэ.
            if (!empty($result['ok']) && $this->hasService('cache')) {
                $this->getService('cache')->clear();
            }

            $this->log(
                'dashboard',
                $result['ok'] ? LogLevel::INFO : LogLevel::ERROR,
                ($result['ok'] ? "Migration applied: " : "Migration failed: ") . "$folder/$file",
                [
                    'action'     => 'migration-apply',
                    'folder'     => $folder,
                    'file'       => $file,
                    'sha256'     => $result['sha256'] ?? '',
                    'statements' => $result['statements'] ?? 0,
                    'warnings'   => \count($warnings),
                    'moved_to'   => $result['moved_to'] ?? null,
                    'error'      => $result['error']   ?? null,
                ]
            );

            $this->respondJSON($result, $result['ok'] ? 200 : 500);
        } catch (\Throwable $e) {
            $this->respondJSON(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /dashboard/migrations/delete
     *
     * Body: {folder, file} - pending файлыг устгана.
     * Apply болсон файлуудыг (ran/ дотор) хөндөхгүй.
     */
    public function delete()
    {
        $this->guardAccess();

        try {
            $body    = (array) $this->getRequest()->getParsedBody();
            $folder  = (string) ($body['folder'] ?? '');
            $file    = (string) ($body['file']   ?? '');

            $path = $this->resolveFile($folder, 'pending', $file);
            if ($path === null) {
                $this->respondJSON(['error' => 'File not found in pending folder'], 404);
                return;
            }

            \unlink($path);

            $this->log('dashboard', LogLevel::WARNING, "Migration deleted: $folder/$file", [
                'action' => 'migration-delete',
                'folder' => $folder,
                'file'   => $file,
            ]);

            $this->respondJSON(['ok' => true]);
        } catch (\Throwable $e) {
            $this->respondJSON(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Folder + state + file нэрнээс бодит замыг буцаана.
     * Path traversal-аас хамгаалсан.
     */
    private function resolveFile(string $folder, string $state, string $file): ?string
    {
        if (!$this->isSafeName($folder) || !$this->isSafeName($file)) {
            return null;
        }
        if (\strtolower(\pathinfo($file, PATHINFO_EXTENSION)) !== 'sql') {
            return null;
        }

        $sub = $state === 'ran' ? '/ran' : '';
        $path = $this->getMigrationsPath() . '/' . $folder . $sub . '/' . $file;
        return \is_file($path) ? $path : null;
    }

    private function isSafeName(string $name): bool
    {
        return $name !== '' && \preg_match('/^[A-Za-z0-9._-]+$/', $name) === 1;
    }

    private function viewModal(string $title, string $body, bool $bodyPadding = true): void
    {
        $bodyClass = $bodyPadding ? 'modal-body' : 'modal-body p-0';

        echo '<div class="modal-lg modal-dialog modal-dialog-centered">'
            . '<div class="modal-content">'
            . '<div class="modal-header">'
            . '<h3 class="modal-title fs-6 text-uppercase text-primary">' . $title . '</h3>'
            . '<button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>'
            . '</div>'
            . '<div class="' . $bodyClass . '">' . $body . '</div>'
            . '<div class="modal-footer">'
            . '<button class="btn btn-primary" data-bs-dismiss="modal" type="button">Close</button>'
            . '</div>'
            . '</div>'
            . '</div>';
    }
}
