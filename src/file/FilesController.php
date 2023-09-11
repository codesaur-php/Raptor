<?php

namespace Raptor\File;

use Psr\Log\LogLevel;

class FilesController extends FileController
{
    public function post(string $input, string $table, int $id, string $folder)
    {
        try {
            $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $table);
            if (empty($table)) {
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
            $record['id'] = $this->indo("/files/$table/insert", $record);
            $text = "Мэдээллийн $table хүснэгтийн $id-р бичлэгт зориулж {$record['id']} дугаартай файлыг байршуулан холболоо";
            $this->indolog('files', LogLevel::INFO, $text, ['reason' => 'insert-upload-file', 'table' => $table, 'record' => $record]);            
            $this->respondJSON($record);
        } catch (\Throwable $e) {
            $error = ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
            $this->respondJSON($error, $e->getCode());
            
            if ($uploaded ?? false) {
                $this->tryDeleteFile(\basename($uploaded['file']));
            }
        }
    }
    
    protected function submit(string $table, array|false $upload, array $record): array|null
    {
        $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $table);
        if (empty($table)
            || !isset($upload['dir'])
            || empty($upload['name'])
        ) {
            throw new \InvalidArgumentException(__CLASS__ . ': Invalid upload data for submit!', 400);
        } elseif (!isset($record['record_id'])) {
            throw new \InvalidArgumentException(__CLASS__ . ': Invalid record data!', 400);
        }
        
        $record['file'] = $upload['dir'] . $upload['name'];
        $record['path'] = $this->getPath($upload['name']);
        $record['id'] = $this->indo("/files/$table/insert", $record);
        $this->indolog(
            'files',
            LogLevel::INFO,
            "Мэдээллийн $table хүснэгтийн {$record['record_id']}-р бичлэгт зориулж {$record['id']} дугаартай файлыг бүртгэлээ",
            ['reason' => 'insert-file', 'table' => $table, 'record' => $record]
        );
        return $this->indo("/files/$table", ['id' => $record['id']]);
    }
    
    public function moveToFolder(string $table, int $id, string $folder, int $mode = 0755)
    {
        try {
            $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $table);
            if (empty($table)) {
                throw new \InvalidArgumentException(__CLASS__ . ": Table name can't empty", 1103);
            }
            
            $record = $this->indo("/files/$table", ['id' => $id]);
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
            $this->indo("/files/$table/update", [
                'record' => $update, 'condition' => ['WHERE' => "id=$id"]
            ]);
            
            $text = "Мэдээллийн $table хүснэгтийн {$record['record_id']}-р бичлэгт зориулcан $id дугаартай файлын байршил солигдлоо";
            $this->indolog('files', LogLevel::INFO, $text, [
                'reason' => 'rename-file-folder', 'table' => $table, 'folder' => $folder, 'record' =>  $update + $record, 'mode' => $mode
            ]);
            return true;
        } catch (\Throwable $e) {
            $this->errorLog($e);
            return false;
        }
    }
}
