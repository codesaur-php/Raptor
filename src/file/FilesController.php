<?php

namespace Raptor\File;

use Psr\Log\LogLevel;

use Indoraptor\Contents\FilesModel;

class FilesController extends FileController
{

    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        $tblNames = $this->indo('/statement', ['query' => "SHOW TABLES LIKE '%_files'"]);
        $tables = [];
        $total = ['tables' => 0, 'rows' => 0, 'sizes' => 0];
        foreach ($tblNames as $result) {
            $table = \current($result);
            $rows = $this->indo('/statement', ['query' => "SELECT COUNT(*) as count FROM $table WHERE is_active=1"]);
            $sizes = $this->indo('/statement', ['query' => "SELECT SUM(size) as size FROM $table WHERE is_active=1"]);
            $count = $rows[0]['count'];
            $size = $sizes[0]['size'];
            ++$total['tables'];
            $total['rows'] += $count;
            $total['sizes'] += $size;
            $tables[$table] = ['count' => $count, 'size' => $this->formatSizeUnits($size)];
        }
        
        if (isset($this->getQueryParams()['table'])) {
            $table = \preg_replace('/[^A-Za-z0-9_-]/', '',  $this->getQueryParams()['table']);
        } elseif (!empty($tables)) {
            $keys = \array_keys($tables);
            $table = \reset($keys);
        } else {
            $this->dashboardProhibited('No file tables found!', 404)->render();
            return;
        }
        
        $files = $this->indosafe("/files/records/$table");
        $total['sizes'] = $this->formatSizeUnits($total['sizes']);
        $dashboard = $this->twigDashboard(
            \dirname(__FILE__) . '/files-index.html',
            [
                'tables' => $tables,
                'total' => $total,
                'table' => $table,
                'files' => $files
            ]
        );
        $dashboard->set('title', $this->text('files'));
        $dashboard->render();

        $this->indolog('files', LogLevel::NOTICE, 'Файлын жагсаалтыг нээж үзэж байна', [
            'model' => FilesModel::class, 'tables' => $tables, 'total' => $total, 'table' => $table
        ]);
    }

    public function post(string $input, string $table, int $id, string $folder)
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception('Unauthorized', 401);
            }

            $_table = \preg_replace('/[^A-Za-z0-9_-]/', '', $table);
            if (empty($_table)) {
                throw new \InvalidArgumentException(__CLASS__ . ": Table name can't empty", 1103);
            }

            $this->setFolder("/$folder/" . ($id == 0 ? 'files' : $id));
            $this->allowCommonTypes();
            $uploaded = $this->moveUploaded($input);
            if (!$uploaded) {
                throw new \InvalidArgumentException(__CLASS__ . ': Invalid upload!', 400);
            }

            $record = $uploaded;
            if ($id > 0) {
                $record['record_id'] = $id;
            }
            $record['id'] = $this->indo("/files/$_table/insert", $record);
            $text = "Мэдээллийн $_table хүснэгтийн $id-р бичлэгт зориулж {$record['id']} дугаартай файлыг байршуулан холболоо";
            $this->indolog('files', LogLevel::INFO, $text, ['reason' => 'insert-upload-file', 'table' => $_table, 'record' => $record]);
            $this->respondJSON($record);
        } catch (\Throwable $e) {
            $error = ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
            $this->respondJSON($error, $e->getCode());

            if (!empty($uploaded['file'])) {
                $this->tryDeleteFile(\basename($uploaded['file']));
            }
        }
    }

    protected function submit(string $table, array|false $upload, array $record): array|null
    {
        if (!$this->isUserAuthorized()) {
            throw new \Exception('Unauthorized', 401);
        }

        $_table = \preg_replace('/[^A-Za-z0-9_-]/', '', $table);
        if (empty($_table) || !isset($upload['dir']) || empty($upload['name'])
        ) {
            throw new \InvalidArgumentException(__CLASS__ . ': Invalid upload data for submit!', 400);
        } elseif (!isset($record['record_id'])) {
            throw new \InvalidArgumentException(__CLASS__ . ': Invalid record data!', 400);
        }

        $record['file'] = $upload['dir'] . $upload['name'];
        $record['path'] = $this->getPath($upload['name']);
        $record['id'] = $this->indo("/files/$_table/insert", $record);
        $this->indolog(
            'files',
            LogLevel::INFO,
            "Мэдээллийн $_table хүснэгтийн {$record['record_id']}-р бичлэгт зориулж {$record['id']} дугаартай файлыг бүртгэлээ",
            ['reason' => 'insert-file', 'table' => $_table, 'record' => $record]
        );
        return $this->indo("/files/$_table", ['id' => $record['id']]);
    }

    public function moveToFolder(string $table, int $id, string $folder, int $mode = 0755)
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception('Unauthorized', 401);
            }

            $_table = \preg_replace('/[^A-Za-z0-9_-]/', '', $table);
            if (empty($_table)) {
                throw new \InvalidArgumentException(__CLASS__ . ": Table name can't empty", 1103);
            }

            $record = $this->indo("/files/$_table", ['id' => $id]);
            $this->setFolder($folder);
            $upload_path = "$this->local/";
            $file_name = \basename($record['file']);
            if (!\file_exists($upload_path) || !\is_dir($upload_path)) {
                \mkdir($upload_path, $mode, true);
            } else {
                $name = \pathinfo($file_name, \PATHINFO_FILENAME);
                $ext = \strtolower(\pathinfo($file_name, \PATHINFO_EXTENSION));
                $file_name = $this->uniqueName($upload_path, $name, $ext);
            }
            $newPath = $upload_path . $file_name;
            if (!\rename($record['file'], $newPath)) {
                throw new \Exception("Can't rename file [{$record['file']}] to [$newPath]");
            }
            $update = ['file' => $newPath, 'path' => $this->getPath($file_name)];
            $this->indo("/files/$_table/update", [
                'record' => $update, 'condition' => ['WHERE' => "id=$id"]
            ]);

            $text = "Мэдээллийн $_table хүснэгтийн {$record['record_id']}-р бичлэгт зориулcан $id дугаартай файлын байршил солигдлоо";
            $this->indolog('files', LogLevel::INFO, $text, [
                'reason' => 'rename-file-folder', 'table' => $_table, 'folder' => $folder, 'record' => $update + $record, 'mode' => $mode
            ]);
            return true;
        } catch (\Throwable $e) {
            $this->errorLog($e);
            return false;
        }
    }

    private function formatSizeUnits(int $bytes): string
    {
        if ($bytes >= 1099511627776) {
            return \number_format($bytes / 1099511627776, 2) . ' TB';
        } elseif ($bytes >= 1073741824) {
            return \number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return \number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return \number_format($bytes / 1024, 2) . ' KB';
        } else {
            return "$bytes bytes";
        }
    }
}
