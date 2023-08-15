<?php

namespace Raptor\File;

use Psr\Log\LogLevel;

class FilesController extends FileController
{
    public string $table;
    
    public function setTable(string $name): FilesController
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
            $this->setFolder("/$table/" . ($id == 0 ? 'files' : $id));
            $this->allowCommonTypes();
            $uploaded = $this->moveUploaded($input);
            if (!$uploaded) {
                throw new \InvalidArgumentException(__CLASS__ . ': Invalid upload!', 400);
            }
            
            $record = $uploaded;
            $record['id'] = $this->indo("/files/$this->table/insert", $record);
            $text = "Мэдээллийн $this->table хүснэгтийн $id-р бичлэгт зориулж {$record['id']} дугаартай файлыг байршуулан холболоо";
            $this->indolog('file', LogLevel::INFO, $text, ['reason' => 'insert-upload-file', 'table' => $this->table, 'record' => $record]);            
            $this->respondJSON($record);
        } catch (\Throwable $e) {
            $error = ['error' => ['code' => $e->getCode(), 'message' => $e->getMessage()]];
            $this->respondJSON($error, $e->getCode());
            
            if ($uploaded ?? false) {
                $this->tryDeleteFile(\basename($uploaded['file']));
            }
        }
    }
    
    protected function submit(array|false $upload, array $record): array|null
    {
        if (empty($this->table)
            || !isset($upload['dir'])
            || empty($upload['name'])
        ) {
            throw new \InvalidArgumentException(__CLASS__ . ': Invalid upload data for submit!', 400);
        } elseif (!isset($record['record_id'])) {
            throw new \InvalidArgumentException(__CLASS__ . ': Invalid record data!', 400);
        }
        
        $record['file'] = $upload['dir'] . $upload['name'];
        $record['path'] = $this->getPath($upload['name']);
        $record['id'] = $this->indo("/files/$this->table/insert", $record);
        $this->indolog(
            'file',
            LogLevel::INFO,
            "Мэдээллийн $this->table хүснэгтийн {$record['record_id']}-р бичлэгт зориулж {$record['id']} дугаартай файлыг бүртгэлээ",
            ['reason' => 'insert-file', 'table' => $this->table, 'record' => $record]
        );
        return $this->indo("/files/$this->table", ['id' => $record['id']]);
    }
}
