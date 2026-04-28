<?php

namespace Dashboard\Manual;

use Raptor\Template\DashboardTrait;

/**
 * Class ManualController
 *
 * Dashboard-ийн гарын авлага (manual) модулийн controller.
 * Manual хавтас доторх HTML файлуудыг жагсаах, харуулах үйлдлүүдийг хариуцна.
 *
 * @package Dashboard\Manual
 */
class ManualController extends \Raptor\Controller
{
    use DashboardTrait;

    /**
     * Гарын авлагын жагсаалт харуулах.
     *
     * manual/ хавтас доторх бүх *-{code}.html файлуудыг олж,
     * нэрийг нь задлан жагсаалт үүсгэнэ.
     */
    public function index()
    {
        if (!$this->isUserAuthorized()) {
            return $this->dashboardProhibited(null, 401)->render();
        }

        $this->dashboardTemplate(__DIR__ . '/manual-index.html', [
            'manuals' => $this->getManualList()
        ])->render();
    }

    /**
     * Тодорхой гарын авлага харуулах.
     *
     * Хүсэлтийн {file} параметрээр manual файлыг олно.
     */
    public function view(string $file)
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $dir = __DIR__ . '/';
            $safeName = \basename($file);
            $filePath = $dir . $safeName;

            // Файл олдохгүй бол code='en' хувилбарыг хайна
            if (!\file_exists($filePath)) {
                $fallback = \preg_replace('/-[a-z]{2}\.html$/i', '-en.html', $safeName);
                $fallbackPath = $dir . $fallback;
                if ($fallback !== $safeName && \file_exists($fallbackPath)) {
                    $filePath = $fallbackPath;
                } else {
                    throw new \Exception('Manual not found', 404);
                }
            }

            $this->dashboardTemplate($filePath)->render();
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
        }
    }

    /**
     * Manual хавтас доторх бүх гарын авлагыг жагсаалт болгох.
     *
     * Файлын нэрийг задалж: moedit-manual-mn.html -> name: moedit-manual, code: mn
     * Ижил нэртэй файлуудыг бүлэглэн, хэлний хувилбаруудыг нэгтгэнэ.
     */
    private function getManualList(): array
    {
        $dir = __DIR__ . '/';
        $files = \glob($dir . '*-*.html');
        $manuals = [];
        foreach ($files as $file) {
            $filename = \basename($file);

            // manual-index.html зэрэг template файлыг алгасах
            if ($filename === 'manual-index.html') {
                continue;
            }

            // {name}-{code}.html задлах
            if (\preg_match('/^(.+)-([a-z]{2})\.html$/i', $filename, $m)) {
                $name = $m[1];
                $code = \strtolower($m[2]);

                if (!isset($manuals[$name])) {
                    $manuals[$name] = [
                        'name' => $name,
                        'languages' => []
                    ];
                }
                $manuals[$name]['languages'][$code] = $filename;
            }
        }
        return \array_values($manuals);
    }
}
