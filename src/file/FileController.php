<?php

namespace Raptor\File;

use Exception;
use Throwable;

use Psr\Log\LogLevel;

use Indoraptor\Record\FileModel;
use Indoraptor\Record\FilesModel;

use Raptor\Controller;

class FileController extends Controller
{
    public $file;
    public $table;
    public $allow;
    public $local;
    public $public;
    public $overwrite;
    public $size_limit;
    
    public function init(string $folder = 'files', int $allows = 0, $overwrite = false, $sizelimit = false)
    {
        $this->file = new File();
        
        $this->setFolder($folder);
        $this->allowExtensions($this->file->getAllowed($allows));
        
        $this->overwrite = $overwrite;
        $this->size_limit = $sizelimit;
    }

    public function setTable(string $name)
    {
        $this->table = preg_replace('/[^A-Za-z0-9_-]/', '', $name);
        
        return $this;
    }
    
    public function setFolder(string $folder, bool $relative = true)
    {
        $script_path = dirname($this->getRequest()->getServerParams()['SCRIPT_NAME']);
        if (in_array($script_path, array('.', '/',  '\\'))) {
            $script_path = '';
        }
        $public_folder = "$script_path/public{$folder}";

        $this->local = dirname($_SERVER['SCRIPT_FILENAME']) . '/public' . $folder;
        $this->public = $relative ? $public_folder : (string)$this->getRequest()->getUri()->withPath($public_folder);
    }
    
    public function getPath(string $fileName): string
    {
        return $this->local . "/$fileName";
    }
    
    public function getPathUrl(string $fileName): string
    {
        return $this->public . "/$fileName";
    }

    public function setSizeLimit(int $size)
    {
        $this->size_limit = $size;
    }

    public function allowExtensions(array $exts)
    {
        $this->allow = $exts;
    }    
        
    public function checkInput($input)
    {
        return $this->file->isUpload($input);
    }

    public function upload($input)
    {
        if ($this->checkInput($input)) {
            return $this->file->upload($input, "$this->local/", $this->allow, $this->overwrite, $this->size_limit);
        }
        
        return false;
    }
    
    public function post(string $input, $record_id, array $table_record = [], array $file_record = [])
    {
        return $this->submit($this->upload($input), $record_id, $table_record, $file_record);
    }
    
    public function post_multi(string $input, $record_id, array $table_record = [], array $file_record = [])
    {
        $language_codes = array_keys($this->getAttribute('localization')['language'] ?? array());
        $result = array();
        foreach ($language_codes as $code) {
            $table_record['code'] = $code;
            $result[] = $this->submit($this->upload(array($input => $code)), $record_id, $table_record, $file_record);
        }
        
        return $result;
    }
    
    public function submit($upload, $record_id, array $table_record = [], array $file_record = [])
    {
        $language_codes = array_keys($this->getAttribute('localization')['language'] ?? array());
        if (isset($upload['dir'])
                && isset($upload['name'])
                && !empty($this->table)) {
            if (isset($table_record['type'])) {
                $existing = $this->getLast($record_id, $table_record['type'], $table_record['code'] ?? '');                
                if ($existing) {
                    try {
                        $this->indodelete("/record?table=$this->table&model=" . FilesModel::class, array('WHERE' => "id={$existing['files_id']}"));
                        $text = "Мэдээллийн $this->table хүснэгтийн $record_id-р бичлэгийн {$existing['type']} төрлийн ({$existing['code']}') хэл дээрх файлыг бүртгэлээс хаслаа.";
                        $this->indolog('file', LogLevel::INFO, $text, array('reason' => 'strip-file', 'table' => $this->table, 'record' => $record_id, 'type' => $existing['type']));
                        if ($existing['protection'] == 1) {
                            $this->indodelete('/record?model=' . FileModel::class, array('WHERE' => "id={$existing['id']}"));
                            $text = "Мэдээллийн file хүснэгтийн {$existing['id']}-р бичлэг дээрх файлыг устгалаа.";
                            $this->indolog('file', LogLevel::INFO, $text, array('reason' => 'delete-file', 'record' => $existing));
                        }
                    } catch (Throwable $e) {
                        $this->errorLog($e);
                    }
                }
            }
            
            try {
                if (!isset($file_record['content'])) {
                    foreach ($language_codes as $code) {
                        $file_record['content'][$code] = array('title' => '');
                    }
                }                
                $file_record['record']['file'] = $upload['dir'] . $upload['name'];
                $file_record['record']['path'] = $this->getPathUrl($upload['name']);
                $file_id = $this->indopost('/record?model=' . FileModel::class, $file_record);
                
                $table_record['file'] = $file_id;
                $table_record['record'] = $record_id;
                $this->indopost("/record?table=$this->table&model=" . FilesModel::class, array('record' => $table_record));
                $text = "Мэдээллийн $this->table хүснэгтийн $record_id-р бичлэгт зориулж $file_id дугаартай файлыг байршууллаа.";
                $this->log($text, array('table' => $this->table, 'reason' => 'insert-file', 'record' => $record_id, 'file' => $file_record['record']['file'], 'path' => $file_record['record']['path']), 'file');
                return $file_record;
            } catch (Throwable $e) {
                $this->errorLog($e);
            }
        }
        
        return false;
    }
    
    public function getRecord($record, $code = null)
    {
        try {
            if (empty($record['file'])) {
                throw new Exception('Please provide valid file record info');
            }
            $result = $this->indo('/record?model=' . FileModel::class, array('p.id' => $record['file'], 'c.code' => $code));
            $result['files_id'] = $record['id'];
            $result['record'] = $record['record'];
            $result['type'] = $record['type'] ?? null;
            $result['code'] = $record['code'] ?? null;
            $result['rank'] = $record['rank'] ?? null;
            return $result;
        } catch (Throwable $e) {
            $this->errorLog($e);
            
            return null;
        }
    }
    
    public function getLast(int $record, int $type, string $code = '')
    {
        try {
            if ($this->isEmpty($this->table)) {
                throw new Exception('File table name not set');
            }
            
            $condition = array(
                'WHERE' => "record=$record AND type=$type",
                'ORDER BY' => 'id desc',
                'LIMIT' => '1'
            );
            if (!empty($code)) {
                $condition['WHERE'] .= " AND code='$code'";
            }

            $rows = $this->indo("/record/rows?table=$this->table&model=" . FilesModel::class, $condition);
            return $this->getRecord(current($rows));
        } catch (Throwable $e) {
            $this->errorLog($e);
            
            return null;
        }
    }
}
