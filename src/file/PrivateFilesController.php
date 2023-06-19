<?php

namespace Raptor\File;

use Twig\TwigFilter;

use codesaur\Http\Message\ReasonPrhase;
use Psr\Log\LogLevel;

use Indoraptor\File\FileModel;
use Indoraptor\File\FilesModel;

class PrivateFilesController extends PublicFilesController
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
            if (isset($this->getQueryParams()['id'])) {
                $this->setTable($table);
                $record = $this->getById((int) $this->getQueryParams()['id']);
            } else {
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
            $context = ['model' => [FileModel::class, FilesModel::class], 'table' => $table, 'id' => $id];
            
            if (!$this->isUserCan('system_content_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $record = [];
            $content = [];
            $payload = $this->getParsedBody();
            foreach ($payload as $index => $value) {
                if (\is_array($value)) {
                    foreach ($value as $key => $value) {
                        $content[$key][$index] = $value;
                    }
                } else {
                    $record[$index] = $value;
                }
            }
            $context['payload'] = $payload;
            var_dump($context, $record, $content, $table, $id);exit;

            foreach ($content as $lang) {
                if (!empty($lang['sadsae'])){
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
            }

            if (empty($record['publish_date'])) {
                $record['publish_date'] = \date('Y-m-d H:i:s');
            }
            foreach ($content as &$visible)
            {
                $visible['is_visible'] = ($visible['is_visible'] ?? 'off' ) == 'on' ? 1 : 0;
            }

            $this->indoput('/record?model=' . PagesModel::class,
                ['record' => $record, 'content' => $content, 'condition' => ['WHERE' => "p.id=$id"]]
            );

            $this->respondJSON([
                'status' => 'success',
                'type' => 'primary',
                'message' => $this->text('record-update-success'),
                'href' => $this->generateLink('pages')
            ]);

            $level = LogLevel::INFO;
            $message = "{$content[$this->getLanguageCode()]['title']} - хуудасны мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            
            $level = LogLevel::ERROR;
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $message = 'Файл мэдээлэл засах үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
        } finally {
            $this->indolog('file', $level, $message, $context);
        }
    }
}
