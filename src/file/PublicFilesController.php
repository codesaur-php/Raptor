<?php

namespace Raptor\File;

use Psr\Log\LogLevel;

use Indoraptor\File\FileModel;

class PublicFilesController extends FileController
{
    public string $table;
    
    public function setTable(string $name): PublicFilesController
    {
        $table = \preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        if (empty($table)) {
            throw new \Exception(__CLASS__ . ": Table name can't empty", 1103);
        }
        $this->table = $table;
        
        return $this;
    }
    
    public function post(string $input, string $table, int $id)
    {
        try {
            
            $this->setTable($table);
            if ($id == 0) {
                $record = [];
                $this->setFolder("/$table/files");
            } else {
                $record = ['record_id' => $id];
                $this->setFolder("/$table/$id");
            }
            $this->allowCommonTypes();
            $uploaded = $this->moveUploaded($input);            
            if (!$uploaded) {
                throw new \InvalidArgumentException(__CLASS__ . ': Invalid upload!', 400);
            }
            $content = [];
            foreach (\array_keys($this->getLanguages()) as $code) {
                $content[$code]['title'] = 'asdasd';
            }
            $record['file_id'] = $this->indopost('/record?model=' . FileModel::class, ['record' => $uploaded, 'content' => $content]);
            $this->indolog('file', LogLevel::INFO, 'Мэдээллийн indo_file хүснэгтэд шинэ файл байршууллаа', ['reason' => 'upload-file', 'record' => $uploaded]);

            $text = "Мэдээллийн $this->table хүснэгтийн $id-р бичлэгт зориулж {$record['file_id']} дугаартай файлыг холболоо";
            $record['id'] = $this->indo("/files/$this->table/insert", $record);
            $record += $uploaded;
            $this->indolog('file', LogLevel::INFO, $text, ['table' => $this->table, 'reason' => 'insert-file'] + $record);            
            $this->respondJSON($record);
        } catch (\Throwable $e) {
            $error = ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
            $this->respondJSON($error, $e->getCode());
            
            if ($uploaded ?? false) {
                $this->tryDeleteFile(\basename($uploaded['file']));
            }
        }
    }
    
    protected function submit(array|false $upload, int $record_id, array $table_record = [], array $file_record = []): array
    {
        if (empty($this->table)
            || !isset($upload['dir'])
            || empty($upload['name'])
        ) {
            throw new \InvalidArgumentException(__CLASS__ . ': Invalid upload data for submit!', 400);
        }
        
        $language_codes = \array_keys($this->getLanguages());
        if (isset($table_record['purpose'])) {
            $existing = $this->getLast($record_id, $table_record['purpose'], $table_record['codes'] ?? '');
            if ($existing) {
                try {
                    $this->indodelete("/files/$this->table", ['WHERE' => "id={$existing['files_id']}"]);
                    $text = "Мэдээллийн $this->table хүснэгтийн $record_id-р бичлэгийн {$existing['purpose']} зориулалттай ({$existing['codes']}') хэл дээрх файлыг бүртгэлээс хаслаа.";
                    $this->indolog('file', LogLevel::INFO, $text, ['reason' => 'strip-file', 'table' => $this->table, 'record' => $record_id, 'purpose' => $existing['purpose']]);
                } catch (\Throwable $e) {
                    $this->errorLog($e);
                }
            }
        }

        if (!isset($file_record['content'])) {
            foreach ($language_codes as $code) {
                $file_record['content'][$code] = ['title' => ''];
            }
        }
        
        $file_record['record']['file'] = $upload['dir'] . $upload['name'];
        $file_record['record']['path'] = $this->getPath($upload['name']);
        $file_id = $this->indopost('/record?model=' . FileModel::class, $file_record);
        $this->indolog('file', LogLevel::INFO, "Мэдээллийн file хүснэгтэд шинэ файл байршууллаа.", ['reason' => 'upload-file', 'record' => $existing]);

        $table_record['file'] = $file_id;
        $table_record['record'] = $record_id;
        $text = "Мэдээллийн $this->table хүснэгтийн $record_id-р бичлэгт зориулж $file_id дугаартай файлыг байршууллаа.";
        $files_id = $this->indo("/files/$this->table/insert", $table_record);
        $this->indolog('file', LogLevel::INFO, $text, ['table' => $this->table, 'reason' => 'insert-file', 'record' => $record_id, 'file' => $file_record['record']['file'], 'path' => $file_record['record']['path']]);
        return $this->getById($files_id);
    }
    
    public function getById(int $id): array|null
    {
        if (empty($this->table)) {
            throw new \Exception(__CLASS__ . ': File table name not set!');
        }

        return $this->getRecord($this->indo("/files/$this->table", ['id' => $id]));
    }
    
    public function getLast(int $record, string $purpose, string $codes = ''): array|null
    {
        try {
            if (empty($this->table)) {
                throw new \Exception('File table name not set');
            }
            
            $condition = [
                'WHERE' => "record=$record AND purpose='" . \addslashes($purpose) . "'",
                'ORDER BY' => 'id desc',
                'LIMIT' => '1'
            ];
            $clean_code = \preg_replace('/[^A-Za-z;-_]/', '', $codes);
            if (!empty($clean_code)) {
                $condition['WHERE'] .= " AND codes LIKE '%$clean_code%'";
            }

            $rows = $this->indo("/files/records/$this->table", $condition);
            return $this->getRecord(\current($rows));
        } catch (\Throwable $e) {
            $this->errorLog($e);
            
            return null;
        }
    }
    
    private function getRecord(array $record): array|null
    {
        if (empty($record['file_id'])) {
            throw new \Exception('Please provide valid file record info');
        }
        $condition = ['p.id' => $record['file_id']];

        $result = $this->indo('/record?model=' . FileModel::class, $condition);
        $result['id'] = $record['id'];
        $result['record_id'] = $record['record_id'];
        $result['codes'] = $record['codes'] ?? null;
        $result['purpose'] = $record['purpose'] ?? null;
        return $result;
    }
}
