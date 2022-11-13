<?php

namespace Raptor\File;

class PrivateFileController extends FileController
{
    public function setFolder(string $folder, bool $relative = true)
    {
        $script_path = $this->getScriptPath();
        $public_folder = "$script_path/private/file?name={$folder}";
        
        $this->local = dirname($_SERVER['SCRIPT_FILENAME']) . '/../private' . $folder;
        $this->public = $relative ? $public_folder : (string)$this->getRequest()->getUri()->withPath($public_folder);        
    }
    
    public function getPathUrl(string $fileName) : string
    {
        return $this->public . urlencode("/$fileName");
    }
    
    public function read()
    {
        if (!$this->isUserAuthorized()) {
            return $this->respondError(401);
        }

        $fileName = $this->getQueryParams()['name'] ?? '';
        $document = dirname($_SERVER['SCRIPT_FILENAME']);
        $filePath = "$document/../private/$fileName";
        if (empty($fileName) || !file_exists($filePath)) {
            return $this->respondError(404);
        }

        $mimeType = mime_content_type($filePath);
        if ($mimeType === false) {
            return $this->respondError(204);
        }
        
        header("Content-Type: $mimeType");
        readfile($filePath);
    }
    
    function respondError(int $code)
    {
        if (!headers_sent()) {
            http_response_code($code);
        }        
        return http_response_code();
    }
}
