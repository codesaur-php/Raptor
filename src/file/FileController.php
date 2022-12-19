<?php

namespace Raptor\File;

use Exception;
use Throwable;

use Psr\Log\LogLevel;
use Psr\Http\Message\UploadedFileInterface;

use Indoraptor\File\FileModel;

use Raptor\Controller;

class FileController extends Controller
{
    public $table;
    public $allow;
    public $local;
    public $public;
    public $overwrite;
    public $size_limit;
    
    private $_error = UPLOAD_ERR_OK;
    
    public function init(string $folder = 'files', int $allows = 0, $overwrite = false, $sizelimit = false)
    {
        $this->setFolder($folder);
        $this->allowType($allows);
        
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
        $script_path = $this->getScriptPath();
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

    public function allowType(int $type)
    {
        $this->allow = $this->getAllowedExtensions($type);
    }

    public function allowExtensions(array $exts)
    {
        $this->allow = $exts;
    }
    
    public function getAllowedExtensions(int $type = 0): array
    {
        switch ($type) {
            case 1: return ['xls', 'xlsx', 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'pps', 'ppsx', 'odt'];
            case 2: return ['mp3', 'm4a', 'ogg', 'wav', 'mp4', 'm4v', 'mov', 'wmv', 'swf'];
            case 3: return ['jpg', 'jpeg', 'jpe', 'png', 'gif'];
            case 4: return ['ico', 'bmp', 'txt', 'xml', 'json'];
            case 5: return ['zip', 'rar'];
            default:
                return array_merge(
                    $this->getAllowedExtensions(1),
                    $this->getAllowedExtensions(2),
                    $this->getAllowedExtensions(3),
                    $this->getAllowedExtensions(4),
                    $this->getAllowedExtensions(5)
                );
        }
    }
    
    private function uniqueFileName(string $uploadpath, string $name, string $ext)
    {
        $filename = $name . '.' . $ext;
        if (file_exists($uploadpath . $filename)) {
            $number = 1;
            while (true) {
                if (file_exists($uploadpath . $name . " ($number)." . $ext)) {
                    $number++;
                } else {
                    break;
                }
            }
            $filename = $name . " ($number)." . $ext;
        }
        
        return $filename;
    }

    public function moveUploaded($input, $mode = 0755)
    {
        try {
            $uploadedFile = $this->getRequest()->getUploadedFiles()[$input] ?? null;
            if (!$uploadedFile instanceof UploadedFileInterface) {
                throw new Exception('No file upload provided', -1);
            }
            if ($uploadedFile->getError() != UPLOAD_ERR_OK) {
                throw new Exception('File upload error', $uploadedFile->getError());
            }

            $file_size = $uploadedFile->getSize();        
            if ($this->sizelimit && $file_size > $this->sizelimit) {
                throw new Exception('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form', UPLOAD_ERR_FORM_SIZE);
            }

            $upload_path = "$this->local/";
            $file_path = basename($uploadedFile->getClientFilename());
            $file_name = pathinfo($file_path, PATHINFO_FILENAME);
            $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            if (!$this->overwrite) {
                $file_path = $this->uniqueFileName($upload_path, $file_name, $file_ext);
            }

            if (!in_array($file_ext, $this->allow)) {
                throw new Exception('The uploaded file type is not allowed', 9);
            }

            if (!file_exists($upload_path) || !is_dir($upload_path)) {
                mkdir($upload_path, $mode, true);
            }
            
            $uploadedFile->moveTo($upload_path . $file_path);            
            $this->_error = UPLOAD_ERR_OK;
            return array(
                'dir' => $upload_path,
                'name' => $file_path,
                'ext' => $file_ext,
                'size' => $file_size
            );
        } catch (Throwable $e) {
            $this->errorLog($e);
            
            $this->_error = $e->getCode();
            
            // failed to move uploaded file!
            return false;
        }
    }
    
    public function post(string $input, $record_id, array $table_record = [], array $file_record = [])
    {
        return $this->submit($this->moveUploaded($input), $record_id, $table_record, $file_record);
    }
    
    public function post_multi(string $input, $record_id, array $table_record = [], array $file_record = [])
    {
        $language_codes = array_keys($this->getAttribute('localization')['language'] ?? array());
        $result = array();
        foreach ($language_codes as $code) {
            $table_record['code'] = $code;
            $result[] = $this->submit($this->moveUploaded(array($input => $code)), $record_id, $table_record, $file_record);
        }
        
        return $result;
    }
    
    public function submit($upload, $record_id, array $table_record = [], array $file_record = [])
    {
        $language_codes = array_keys($this->getAttribute('localization')['language'] ?? array());
        if (isset($upload['dir']) && isset($upload['name']) && !empty($this->table)) {
            if (isset($table_record['type'])) {
                $existing = $this->getLast($record_id, $table_record['type'], $table_record['code'] ?? '');                
                if ($existing) {
                    try {
                        $this->indodelete("/files/$this->table", array('WHERE' => "id={$existing['files_id']}"));
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
                $this->indopost("/files/$this->table", $table_record);
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

            $rows = $this->indo("/files/records/$this->table", $condition);
            return $this->getRecord(current($rows));
        } catch (Throwable $e) {
            $this->errorLog($e);
            
            return null;
        }
    }
    
    public function getLastError(): int
    {
        return $this->_error;
    }
}
