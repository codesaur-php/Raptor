<?php

namespace Raptor\File;

use Twig\TwigFilter;

use codesaur\Http\Message\ReasonPrhase;
use Psr\Log\LogLevel;

use Indoraptor\Contents\FilesModel;

class PrivateFilesController extends FilesController
{
    public function setFolder(string $folder, bool $relative = true)
    {
        $script_path = $this->getTargetPath();
        $public_folder = "$script_path/private/file?name=$folder";
        
        $this->local = $this->getDocumentPath('/../private' . $folder);
        $this->public = $relative ? $public_folder : (string) $this->getRequest()->getUri()->withPath($public_folder);
    }
    
    public function getPath(string $fileName): string
    {
        return "$this->public/" . \urlencode($fileName);
    }
    
    public function read()
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception('Unauthorized', 401);
            }

            $fileName = $this->getQueryParams()['name'] ?? '';
            $filePath = $this->getDocumentPath("/../private/$fileName");
            if (empty($fileName) || !\file_exists($filePath)) {
                throw new \Exception('Not Found', 404);
            }

            $mimeType = \mime_content_type($filePath);
            if ($mimeType === false) {
                throw new \Exception('No Content', 204);
            }

            \header("Content-Type: $mimeType");
            \readfile($filePath);
        } catch (\Throwable $e) {
            $this->errorLog($e);
            
            if (\headers_sent()) {
                return;
            }
            
            $code = $e->getCode();
            $status_code = "STATUS_$code";
            $reasonPhraseClass = ReasonPrhase::class;
            if (empty($code)
                || !\is_int($code)
                || !\defined("$reasonPhraseClass::$status_code")
            ) {
                $code = 500;
            }
            
            \http_response_code($code);
        }
    }
    
    public function modal(string $modal, string $table)
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $id = $this->getQueryParams()['id'] ?? null;
            if (!isset($id) || !\is_numeric($id)) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            
            $uri = $this->getRequest()->getUri();
            $scheme = $uri->getScheme();
            $authority = $uri->getAuthority();
            $host = '';
            if ($scheme != '') {
                $host .= "$scheme:";
            }
            if ($authority != '') {
                $host .= "//$authority";
            }            
            $this->setTable($table);
            $record = $this->indo("/files/$table", ['id' => (int) $id]);
            $template = $this->twigTemplate(
                \dirname(__FILE__) . "/$modal-modal.html",
                ['table' => $table, 'record' => $record, 'host' => $host]
            );
            $template->addFilter(new TwigFilter('basename', function (string $path): string
            {
                return \basename($path);
            }));
            $template->render();
        } catch (\Throwable $e) {
            if (!\headers_sent()) {
                $code = $e->getCode();
                $status_code = "STATUS_$code";
                $reasonPhraseClass = ReasonPrhase::class;
                if (\defined("$reasonPhraseClass::$status_code")) {
                    \http_response_code($code);
                }
            }
            
            echo '<div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-body">
                            <div class="alert alert-danger shadow-sm fade mt-3 show" role="alert">
                                <i class="bi bi-shield-fill-exclamation" style="margin-right:5px"></i>'
                            . $e->getMessage() .
                            '</div>
                        </div>
                        <div class="modal-footer modal-footer-solid">
                            <button class="btn btn-secondary shadow-sm" data-bs-dismiss="modal">' . $this->text('close') . '</button>
                        </div>
                    </div>
                </div>';
        }
    }
    
    public function update(string $table, int $id)
    {
        try {
            $context = [
                'model' => FilesModel::class,
                'table' => $table,
                'id' => $id,
                'reason' => 'update-file'
            ];
            
            if (!$this->isUserCan('system_content_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $payload = $this->getParsedBody();
            $context['payload'] = $payload;
            if (empty($payload)) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            
            $record = [];
            foreach ($payload as $k => $v) {
                if (\str_starts_with($k, 'file_')) {
                    $k = \substr($k, 5);
                }
                $record[$k] = $v;
            }

            $this->indoput("/files/$table", [
                'record' => $record, 'condition' => ['WHERE' => "id=$id"]
            ]);

            $this->respondJSON([
                'type' => 'primary',
                'status' => 'success',
                'title' => $this->text('success'),
                'message' => $this->text('record-update-success'),
                'record' => $this->indo("/files/$table", ['id' => $id])
            ]);

            $level = LogLevel::INFO;
            $message = "Мэдээллийн $table хүснэгтэд зориулсан $id дугаартай файлын бичлэгийг засварлалаа";
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            
            $level = LogLevel::ERROR;
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $message = "Мэдээллийн $table хүснэгтэд зориулсан $id дугаартай файлын бичлэгийг засах үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо";
        } finally {
            $this->indolog('file', $level, $message, $context);
        }
    }
    
    public function delete(string $table)
    {
        try {
            $context = ['model' => FilesModel::class, 'table' => $table];
            
            if (!$this->isUserCan('system_content_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (empty($payload['id'])
                || !isset($payload['title'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            $context['payload'] = $payload;
            
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            
            $this->indodelete("/files/$table", ['WHERE' => "id=$id"]);
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
            
            $level = LogLevel::ALERT;
            $message = "Мэдээллийн $table хүснэгтэд зориулсан $id дугаартай файлыг устгалаа";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Файлыг устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('file', $level, $message, $context);
        }
    }
}
