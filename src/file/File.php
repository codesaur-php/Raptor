<?php

namespace Raptor\File;

use finfo;

class File
{
    private $_error = 0;
    public $handle = null;
    
    function __destruct()
    {
        if ($this->handle) {
            $this->close();
        }
    }
    
    public function close()
    {
        $result = fclose($this->handle);
        if ($result) {
            $this->handle = null;
        }
    }

    public function duplicate($source)
    {
        $in = $source->handle;
        while ($buff = fread($in, 4096)) {
            $this->write($buff);
        }
    }

    public function exists(string $pathname): bool
    {
        return file_exists($pathname);
    }

    public function getExt(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }
    
    public function getSize(string $path): int
    {
        return $this->exists($path) ? filesize($path) : 0;
    }

    public function getMimeType(string $filename): string
    {
        $file_info = new finfo(FILEINFO_MIME);
        $mime_type = $file_info->buffer(file_get_contents($filename));
        
        return $mime_type;
    }

    public function getNaked(string $path)
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    public function getName(string $path): string
    {
        return basename($path);
    }
    
    public function getAllowed(int $type = 0): array
    {
        switch ($type) {
            case 1: return ['xls', 'xlsx', 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'pps', 'ppsx', 'odt'];
            case 2: return ['mp3', 'm4a', 'ogg', 'wav', 'mp4', 'm4v', 'mov', 'wmv', 'swf'];
            case 3: return ['jpg', 'jpeg', 'jpe', 'png', 'gif'];
            case 4: return ['ico', 'bmp', 'txt', 'xml', 'json'];
            case 5: return ['zip', 'rar'];
            default:
                return array_merge(
                        $this->getAllowed(1),
                        $this->getAllowed(2),
                        $this->getAllowed(3),
                        $this->getAllowed(4),
                        $this->getAllowed(5));
        }
    }
    
    public function generateName(string $uploadpath, string $filename)
    {
        if ($this->exists($uploadpath . $filename)) {
            $number = 1;
            $ext = $this->getExt($filename);
            $fname = $this->getNaked($filename);
            while (true) {
                if ($this->exists($uploadpath . $fname . " ($number)." . $ext)) {
                    $number++;
                } else {
                    break;
                }
            }
            $filename = $fname . " ($number)." . $ext;
        }
        
        return $filename;
    }
    
    public function formatBytes($bytes, $precision = 2, array $suffixes = ['', 'KB', 'MB', 'GB', 'TB'])
    { 
        $base = log($bytes, 1024);
        
        return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
    }

    public function isUpload($input): bool
    {
        if (!is_array($input)) {
            return !empty($_FILES[$input]['name']) && !empty($_FILES[$input]['tmp_name']);            
        }
        
        $name = key($input);
        $field = current($input);
        return !empty($_FILES[$name]['name'][$field]) && !empty($_FILES[$name]['tmp_name'][$field]);
    }
    
    public function isUploadImage($input): bool
    {
        if (!$this->isUpload($input)) {
            return false;
        }

        if (!is_array($input)) {
            return false !== getimagesize($_FILES[$input]['tmp_name']);
        }
        
        return false !== getimagesize($_FILES[key($input)]['tmp_name'][current($input)]);
    }

    public function isUploaded(string $pathname): bool
    {
        return is_uploaded_file($pathname);
    }

    public function isImage(string $filename): bool
    {
        $mime_type = $this->getMimeType($filename);
        switch ($mime_type) {
            case 'image/png': 
            case 'image/ico': 
            case 'image/svg': 
            case 'image/bmp': 
            case 'image/gif': 
            case 'image/jpeg': return true;
        }
        
        return false;
    }

    public function isDir(string $filename): bool
    {
        return is_dir($filename);
    }

    public function upload($input, string $uploadpath, array $allowed = [], $overwrite = false, $sizelimit = false)
    {
        if (is_array($input)) {
            $field = current($input);
            $input = key($input);
        } else {
            $field = false;
        }
        
        $size = $field ? $_FILES[$input]['size'][$field] : $_FILES[$input]['size'];
        if ($sizelimit && $size > $sizelimit) {
            $this->_error = 3;
            return false;
        }
        
        if (!$overwrite) {
            $file = $this->generateName($uploadpath, $this->getName($field ? $_FILES[$input]['name'][$field] : $_FILES[$input]['name']));
        } else {
            $file = $this->getName($field ? $_FILES[$input]['name'][$field] : $_FILES[$input]['name']);
        }
        $ext = $this->getExt($file);
        if (!in_array($ext, $allowed)) {
            $this->_error = 2;
            return false;
        }
        
        if (!$this->exists($uploadpath)
                || !$this->isDir($uploadpath)
        ) {
            $this->makeDir($uploadpath, 0755, true);
        }
        
        $tmp_file = $field ? $_FILES[$input]['tmp_name'][$field] : $_FILES[$input]['tmp_name'];
        if (move_uploaded_file($tmp_file, $uploadpath . $file)) {
            $this->_error = 0;
            return array(
                'dir' => $uploadpath,
                'name' => $file,
                'ext' => $ext,
                'size' => $size
            );
        } else {
            $this->_error = 1; // failed to move tmp uploaded file
        }
        
        return false;
    }

    public function makeDir(string $pathname, $mode = 0755, $recursive = true, $context = null): bool
    {
        if (isset($context)) {
            return mkdir($pathname, $mode, $recursive, $context);
        } else {
            return mkdir($pathname, $mode, $recursive);
        }
    }
    
    public function open(string $filename, string $mode, bool $useincludepath = false, $context = null)
    {
        if (isset($context)) {
            $this->handle = fopen($filename, $mode, $useincludepath, $context);
        } else {
            $this->handle = fopen($filename, $mode, $useincludepath);
        }
        
        return $this->handle;
    }
    
    public function read(int $length = 4096): string
    {
        return fread($this->handle, $length);
    }

    public function readFull(int $length = 4096): array
    {
        $lines = [];
        while (!feof($this->handle)) { 
            $buffer = fgets($this->handle, $length);
            $lines[] = $buffer;
        }
        
        return $lines;
    }

    public function rename(string $oldname, string $newname, $context = null): bool
    {
        return rename($oldname, $newname, $context);
    }

    public function write(string $content)
    {
        return fwrite($this->handle, $content);
    }

    public function copyImage(string $src, $dest, int $w, int $h, int $quality = null)
    {
        $ext = $this->getExt($src);
        
        $available = ['jpg', 'jpeg', 'jpe', 'png', 'gif'];
        if (!in_array($ext, $available)) {
            return false;
        }
        
        $destination = imagecreatetruecolor($w, $h);
        
        imageantialias($destination, true);
        
        $size = getimagesize($src);
        switch ($size[2]) {
            case 1: $source = imagecreatefromgif($src); break;
            case 2: $source = imagecreatefromjpeg($src); break;
            case 3: $source = imagecreatefrompng($src); break;
            default: return false;
        }
        
        imagecopyresampled($destination, $source, 0, 0, 0, 0, $w, $h, $size[0], $size[1]);
        
        switch ($size[2]) {
            case 1: imagegif($destination, $dest); break;
            case 2: imagejpeg($destination, $dest, $quality); break;
            case 3: imagepng($destination, $dest); break;
        }
        
        return $dest;
    }
    
    public function getLastError(): int
    {
        return $this->_error;
    }
}
