<?php

namespace Raptor\Contents;

use Psr\Log\LogLevel;

use Indoraptor\Contents\NewsModel;

use Raptor\Dashboard\DashboardController;
use Raptor\File\FilesController;
use Raptor\File\FileController;

class NewsController extends DashboardController
{    
    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }
        
        $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/news-index.html');
        $dashboard->set('title', $this->text('news'));
        $dashboard->render();
        
        $this->indolog('content', LogLevel::NOTICE, 'Мэдээний жагсаалтыг нээж үзэж байна', ['model' => NewsModel::class]);
    }
    
    public function list()
    {
        try {
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $exists = $this->indo(
                '/execute/fetch/all',
                ['query' => "SHOW TABLES LIKE 'indo_news'"]
            );
            if (!empty($exists)) {
                $files_counts = $this->getFilesCounts();
                $news_query = 
                    'SELECT id, photo, title, code, category, type, published, published_date ' .
                    'FROM indo_news WHERE is_active=1';
                $news = $this->indo('/execute/fetch/all', ['query' => $news_query]);
            }
            $this->respondJSON([
                'status' => 'success',
                'list' => $news ?? [],
                'files_counts' => $files_counts ?? []
            ]);
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
        }
    }
    
    public function insert()
    {
        try {
            $context = ['model' => NewsModel::class];
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            
            if (!$this->isUserCan('system_content_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $table = 'indo_news';
            
            if ($is_submit) {
                $record = $this->getParsedBody();
                $context['payload'] = $record;
                
                if (empty($record['published_date'])) {
                    $record['published_date'] = \date('Y-m-d H:i:s');
                }
                $record['published'] = ($record['published'] ?? 'off' ) == 'on' ? 1 : 0;
                
                if (isset($record['files'])) {
                    $files = $record['files'];
                    unset($record['files']);
                }
                
                if ($record['published'] == 1
                    && !$this->isUserCan('system_content_publish')
                ) {
                    throw new \Exception($this->text('system-no-permission'), 401);
                }
                
                $id = $this->indopost('/record?model=' . NewsModel::class, $record);
                if ($id == false) {
                    throw new \Exception($this->text('record-insert-error'));
                }
                
                $file = new FileController($this->getRequest());
                $file->setFolder("/news/$id");
                $file->allowImageOnly();
                $photo = $file->moveUploaded('photo');
                if ($photo) {
                    $payload = [
                        'record' => ['photo' => $photo['path']],
                        'condition' => ['WHERE' => "id=$id"]
                    ];
                    $context['photo'] = $photo;
                    $this->indoput('/record?model=' . NewsModel::class, $payload);
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "Шинэ мэдээ [{$record['title']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
                
                if (!isset($files)
                    || empty($files)
                    || !\is_array($files)
                ) {
                    return;
                }
                $filesController = new FilesController($this->getRequest());
                foreach ($files as $file_id) {
                    try {
                        $this->indo(
                            "/files/$table/update",
                            ['record' => ['record_id' => $id], 'condition' => ['WHERE' => "id=$file_id"]]
                        );
                        $this->indolog(
                            'files',
                            LogLevel::INFO,
                            "$id-р мэдээнд зориулж $file_id дугаартай файлыг бүртгэлээ",
                            ['reason' => 'register-file', 'table' => $table, 'record_id' => $id, 'file_id' => $file_id]
                        );
                        $filesController->moveToFolder($table, $file_id, "/news/$id");
                    } catch (\Throwable $e) {
                        $this->errorLog($e);
                    }
                }
            } else {
                $dashboard = $this->twigDashboard(
                    \dirname(__FILE__) . '/news-insert.html',
                    ['max_file_size' => $this->getMaximumFileUploadSize()]
                );
                $dashboard->set('title', $this->text('add-record') . ' | News');
                $dashboard->render();
                
                $level = LogLevel::NOTICE;
                $message = 'Шинэ мэдээ үүсгэх үйлдлийг эхлүүллээ';
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = 'Шинэ мэдээ үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function read(int $id)
    {
        try {
            $context = ['id' => $id, 'model' => NewsModel::class];
            
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $record = $this->indoget('/record?model=' . NewsModel::class, ['id' => $id]);
            $context['record'] = $record;
            try {
                $context['files'] = $this->indo(
                    '/files/records/indo_news_files',
                    [
                        'WHERE' => "record_id=$id AND is_active=1",
                        'ORDER BY' => 'updated_at'
                    ]
                );
            } catch (\Throwable $e) {
                $context['files'] = [];
            }
            $template = $this->twigTemplate(\dirname(__FILE__) . '/news-read.html');
            foreach ($this->getAttribute('settings', []) as $key => $value) {
                $template->set($key, $value);
            }
            foreach ($context as $key => $value) {
                $template->set($key, $value);
            }
            $template->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['title']} - мэдээг уншиж байна";
            
            try {
                $this->indoput(
                    '/record?model=' . NewsModel::class,
                    [
                        'condition' => ['WHERE' => "id=$id"],
                        'record' => ['read_count' => $record['read_count'] + 1]
                    ]
                );
            } catch (\Throwable $e) {
                $this->errorLog($e);
            }
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = 'Мэдээг унших үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function view(int $id)
    {
        try {
            $context = ['id' => $id, 'model' => NewsModel::class];
            
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $record = $this->indoget('/record?model=' . NewsModel::class, ['id' => $id]);
            $record['rbac_accounts'] = $this->getRBACAccounts($record['created_by'], $record['updated_by']);
            $context['record'] = $record;
            try {
                $context['files'] = $this->indo(
                    '/files/records/indo_news_files',
                    ['WHERE' => "record_id=$id AND is_active=1"]
                );
                foreach ($context['files'] as &$file) {
                    unset($file['file']);
                }
            } catch (\Throwable $e) {
                $context['files'] = [];
            }
            $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/news-view.html', $context);
            $dashboard->set('title', $this->text('view-record') . ' | News');
            $dashboard->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['title']} - мэдээг нээж үзэж байна";
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = 'Мэдээг нээж үзэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function update(int $id)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $context = ['id' => $id, 'model' => NewsModel::class];
            
            if (!$this->isUserCan('system_content_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $table = 'indo_news';
            
            if ($is_submit) {
                $record = $this->getParsedBody();
                $context['payload'] = $record;
                
                $record['published'] = ($record['published'] ?? 'off' ) == 'on' ? 1 : 0;
                
                if (isset($record['files'])) {
                    $files = $record['files'];
                    unset($record['files']);
                }
                
                if (empty($record['title'])){
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                
                $current = $this->indoget('/record?model=' . NewsModel::class, ['id' => $id]);
                if ($record['published'] != $current['published']
                    && !$this->isUserCan('system_content_publish')
                ) {
                    throw new \Exception($this->text('system-no-permission'), 401);
                }
                
                try {
                    if (empty($current['photo'])) {
                        throw new \Exception('Current record had no photo!');
                    }
                    $current_photo_file = \basename($current['photo']);
                } catch (\Throwable $e) {
                    $current_photo_file = '';
                }
                
                $file = new FileController($this->getRequest());
                $file->setFolder("/news/$id");
                $file->allowImageOnly();
                $photo = $file->moveUploaded('photo');
                if ($photo) {
                    $record['photo'] = $photo['path'];
                }
                if (!empty($current_photo_file)) {
                    if ($file->getLastError() == -1) {
                        $file->tryDeleteFile($current_photo_file);
                        $record['photo'] = '';
                    } elseif (isset($record['photo'])
                        && \basename($record['photo']) != $current_photo_file
                    ) {
                        $file->tryDeleteFile($current_photo_file);
                    }
                }
                if (isset($record['photo'])) {
                    $context['record']['photo'] = $record['photo'];
                }
                
                $updated = $this->indoput('/record?model=' . NewsModel::class,
                    ['record' => $record, 'condition' => ['WHERE' => "id=$id"]]
                );
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success')
                ]);
                
                $level = LogLevel::INFO;
                $message = "{$record['title']} - мэдээг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
                
                if (!isset($files)
                    || empty($files)
                    || !\is_array($files)
                ) {
                    return;
                }
                try {
                    $current_files = $this->indo(
                        "/files/records/$table",
                        ['WHERE' => "record_id=$id AND is_active=1"]
                    );
                } catch (\Throwable $e) {
                    $current_files = [];
                }
                foreach ($files as $file_id) {
                    $fid = (int) $file_id;
                    if (\array_key_exists($fid, $current_files)) {
                        continue;
                    }
                    try {
                        $this->indo(
                            "/files/$table/update",
                            ['record' => ['record_id' => $id], 'condition' => ['WHERE' => "id=$fid"]]
                        );
                        $this->indolog(
                            'files',
                            LogLevel::INFO,
                            "$id-р мэдээнд зориулж $fid дугаартай файлыг бүртгэлээ",
                            ['reason' => 'register-file', 'table' => $table, 'record_id' => $id, 'file_id' => $fid]
                        );
                    } catch (\Throwable $e) {
                        $this->errorLog($e);
                    }
                }
            } else {
                $record = $this->indoget('/record?model=' . NewsModel::class, ['id' => $id]);
                $record['rbac_accounts'] = $this->getRBACAccounts($record['created_by'], $record['updated_by']);
                $context['record'] = $record;
                try {
                    $context['files'] = $this->indo(
                        "/files/records/$table",
                        ['WHERE' => "record_id=$id AND is_active=1"]
                    );
                    foreach ($context['files'] as &$file) {
                        unset($file['file']);
                    }
                } catch (\Throwable $e) {
                    $context['files'] = [];
                }
                $context['max_file_size'] = $this->getMaximumFileUploadSize();
                $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/news-update.html', $context);
                $dashboard->set('title', $this->text('edit-record') . ' | News');
                $dashboard->render();
                
                $level = LogLevel::NOTICE;
                $message = "{$context['record']['title']} - мэдээг шинэчлэхээр нээж байна";
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $message = 'Мэдээг засах үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        try {
            $context = ['model' => NewsModel::class];
            
            if (!$this->isUserCan('system_content_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !isset($payload['title'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            $context['payload'] = $payload;
            
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            
            $deleted = $this->indodelete('/record?model=' . NewsModel::class, ['WHERE' => "id=$id"]);
            if (empty($deleted)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
            
            $level = LogLevel::ALERT;
            $message = "{$payload['title']} - мэдээг устгалаа";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Мэдээг устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    private function getFilesCounts(): array
    {
        try {
            $files_count_query = 
                'SELECT n.id as id, COUNT(*) as files ' .
                'FROM indo_news as n INNER JOIN indo_news_files as f ON n.id=f.record_id ' .
                'WHERE n.is_active=1 AND f.is_active=1 ' .
                'GROUP BY f.record_id';
            $result =  $this->indo('/execute/fetch/all', ['query' => $files_count_query]);
            $counts = [];
            foreach ($result as $count) {
                $counts[$count['id']] = $count['files'];
            }
            return $counts;
        } catch (\Throwable $e) {
            $this->errorLog($e);
            return [];
        }
    }
    
    private function getMaximumFileUploadSize(): string
    {
        return $this->formatSizeUnits(
            \min(
                $this->convertPHPSizeToBytes(\ini_get('post_max_size')),
                $this->convertPHPSizeToBytes(\ini_get('upload_max_filesize'))
            )
        );
    }
    
    private function convertPHPSizeToBytes($sSize): int
    {
        $sSuffix = \strtoupper(\substr($sSize, -1));
        if (!\in_array($sSuffix, ['P','T','G','M','K'])){
            return (int)$sSize;
        }
        $iValue = \substr($sSize, 0, -1);
        switch ($sSuffix) {
            case 'P':
                $iValue *= 1024;
            case 'T':
                $iValue *= 1024;
            case 'G':
                $iValue *= 1024;
            case 'M':
                $iValue *= 1024;
            case 'K':
                $iValue *= 1024;
                break;
        }
        return (int)$iValue;
    }

    private function formatSizeUnits(?int $bytes): string
    {
        if ($bytes >= 1099511627776) {
            return \number_format($bytes / 1099511627776, 2) . 'tb';
        } elseif ($bytes >= 1073741824) {
            return \number_format($bytes / 1073741824, 2) . 'gb';
        } elseif ($bytes >= 1048576) {
            return \number_format($bytes / 1048576, 2) . 'mb';
        } elseif ($bytes >= 1024) {
            return \number_format($bytes / 1024, 2) . 'kb';
        } else {
            return $bytes . 'b';
        }
    }
}
