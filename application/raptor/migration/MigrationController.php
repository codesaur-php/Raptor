<?php

namespace Raptor\Migration;

/**
 * Class MigrationController
 *
 * Raptor Dashboard дээрх Migration удирдлагын контроллер.
 *
 * Зөвхөн system_coder эрхтэй хэрэглэгч хандах боломжтой.
 *
 * @package Raptor\Migration
 */
class MigrationController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Хандалтын эрх шалгах.
     *
     * system_coder эрхгүй бол 403 Unauthorized буцаана.
     *
     * @return void
     */
    private function guardAccess(): void
    {
        if (!$this->isUser('system_coder')) {
            $this->respondJSON(['error' => 'Unauthorized'], 403);
            exit;
        }
    }

    /**
     * MigrationRunner instance үүсгэх.
     *
     * @return MigrationRunner
     */
    private function getRunner(): MigrationRunner
    {
        return new MigrationRunner($this->pdo, \dirname(__DIR__, 3) . '/database/migrations');
    }

    /**
     * Dashboard migration хуудсыг харуулах.
     *
     * Эрхгүй хэрэглэгчийг login руу чиглүүлнэ.
     *
     * @return void
     */
    public function index()
    {
        if (!$this->isUser('system_coder')) {
            $this->redirectTo('login');
            return;
        }

        $this->twigDashboard(__DIR__ . '/migration-index.html')->render();
    }

    /**
     * Migration-уудын төлөв байдлыг JSON-оор буцаах (AJAX).
     *
     * @return void Буцаах утга: {ran: [...], pending: [...]}
     */
    public function status()
    {
        $this->guardAccess();

        try {
            $runner = $this->getRunner();
            $this->respondJSON($runner->status());
        } catch (\Throwable $e) {
            $this->respondJSON(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Migration SQL файлын агуулгыг HTML-ээр буцаах (ajaxModal).
     *
     * Query parameter: ?file=filename.sql&type=pending|ran
     * Dashboard-ийн #static-modal дотор харагдана.
     *
     * @return void
     */
    public function view()
    {
        $this->guardAccess();

        $file = $this->getRequest()->getQueryParams()['file'] ?? '';
        $type = $this->getRequest()->getQueryParams()['type'] ?? 'pending';

        if ($file === '' || \preg_match('/[\/\\\\]/', $file)) {
            $this->viewModal('Error', '<p class="text-danger">Invalid file name</p>');
            return;
        }

        $basePath = \dirname(__DIR__, 3) . '/database/migrations';
        $path = $type === 'ran'
            ? $basePath . '/ran/' . $file
            : $basePath . '/' . $file;

        if (!\is_file($path)) {
            $this->viewModal('Error', '<p class="text-danger">File not found</p>');
            return;
        }

        $escapedFile = \htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
        $content = \htmlspecialchars(\file_get_contents($path), ENT_QUOTES, 'UTF-8');
        $badge = $type === 'ran'
            ? '<span class="badge bg-success ms-2">Ran</span>'
            : '<span class="badge bg-warning text-dark ms-2">Pending</span>';

        $title = '<i class="bi bi-file-earmark-code"></i> '  . $escapedFile . $badge;
        $body = '<pre class="m-0 p-3 bg-body-tertiary" style="max-height:500px; overflow:auto;"><code>' . $content . '</code></pre>';

        $this->viewModal($title, $body, false);
    }

    /**
     * ajaxModal-д зориулсан стандарт modal HTML бүтэц буцаах.
     *
     * @param string $title       Modal гарчиг (HTML)
     * @param string $body        Modal агуулга (HTML)
     * @param bool   $bodyPadding Body дотор padding хэрэглэх эсэх
     * @return void
     */
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
