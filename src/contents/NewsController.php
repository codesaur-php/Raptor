<?php

namespace Raptor\Contents;

use Psr\Log\LogLevel;

use Indoraptor\Contents\NewsModel;

use Raptor\Dashboard\DashboardController;
use Raptor\File\FilesController;

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
    
    public function datatable()
    {
        try {
            $rows = [];
            
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $news_query = 
                'SELECT id, title, code, category, type, published, publish_date ' .
                'FROM indo_news WHERE is_active=1';
            $news = $this->indo('/statement', ['query' => $news_query]);
            $news_files_query = 
                'SELECT n.id as id, COUNT(*) as files ' .
                'FROM indo_news as n JOIN indo_news_files as f ON n.id=f.record_id ' .
                'WHERE n.is_active=1 AND f.is_active=1 ' .
                'GROUP BY f.record_id';
            $news_files = $this->indo('/statement', ['query' => $news_files_query]);
            $news_image_query = 
                'SELECT n.id as id, min(f.id), f.path as image ' .
                'FROM indo_news as n JOIN indo_news_files as f ON n.id=f.record_id ' .
                "WHERE n.is_active=1 AND f.is_active=1 AND f.type='image' " .
                'GROUP BY f.record_id';
            $news_image = $this->indo('/statement', ['query' => $news_image_query]);
            $news_featured_query = 
                'SELECT n.id as id, min(f.id), f.path as featured ' .
                'FROM indo_news as n JOIN indo_news_files as f ON n.id=f.record_id ' .
                "WHERE n.is_active=1 AND f.is_active=1 AND f.type='image' AND f.category='featured' " .
                'GROUP BY f.record_id';
            $news_featured = $this->indo('/statement', ['query' => $news_featured_query]);
            foreach ($news as $record) {
                if (!empty($record['code'])) {
                    $lang = '<img src="https://cdn.jsdelivr.net/gh/codesaur-php/HTML-Assets@2.5.3/flags/' . $record['code'] . '.png"> ' . $record['code'];
                } else {
                    $lang = '';
                }
                $id = $record['id'];
                $row = [$record['publish_date'], $lang];
                
                $image =
                    $news_featured[$id]['featured']
                    ?? $news_image[$id]['image']
                    ?? 'https://via.placeholder.com/60?text=no+photo';
                $row[] = "<img style=\"max-width:60px;max-height:60px\" src=\"$image\"\>";
                
                $title = \htmlentities($record['title']);
                $row[] = $title;
                
                $row[] = $news_files[$id]['files'] ?? 0;
                
                $row[] =
                    '<span class="badge bg-primary">' . \htmlentities($record['category']) . '</span> ' .
                    '<span class="badge bg-danger">' . \htmlentities($record['type']) . '</span>';
                
                $row[] = $record['published'] == 1 ? '<i class="bi bi-emoji-heart-eyes-fill text-success"></i>' : '<i class="bi bi-eye-slash"></i>';

                $action =
                    '<a class="btn btn-sm btn-info shadow-sm" href="' .
                    $this->generateLink('news-view', ['id' => $id]) . '"><i class="bi bi-eye"></i></a>';
                
                if ($this->getUser()->can('system_content_update')) {
                    $action .=
                        ' <a class="btn btn-sm btn-primary shadow-sm" href="'
                        . $this->generateLink('news-update', ['id' => $id]) . '"><i class="bi bi-pencil-square"></i></a>';
                }
                
                if ($this->getUser()->can('system_content_delete')) {
                    $action .= 
                        ' <a class="delete-news btn btn-sm btn-danger shadow-sm" href="' . $id .
                        '" data-title="' . $title . '"><i class="bi bi-trash"></i></a>';
                }
                
                $row[] = $action;
                
                $rows[] = $row;
            }
        } catch (\Throwable $e) {
            $this->errorLog($e);
        } finally {
            $count = \count($rows);
            $this->respondJSON([
                'data' => $rows,
                'recordsTotal' => $count,
                'recordsFiltered' => $count,
                'draw' => (int) ($this->getQueryParams()['draw'] ?? 0)
            ]);
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
                
                if (empty($record['publish_date'])) {
                    $record['publish_date'] = \date('Y-m-d H:i:s');
                }
                $record['published'] = ($record['published'] ?? 'off' ) == 'on' ? 1 : 0;
                
                if (isset($record['files'])) {
                    $files = $record['files'];
                    unset($record['files']);
                }
                
                $id = $this->indopost('/record?model=' . NewsModel::class, $record);
                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success'),
                    'href' => $this->generateLink('news')
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
                    $result = $this->indosafe(
                        "/files/$table/update",
                        ['record' => ['record_id' => $id], 'condition' => ['WHERE' => "id=$file_id"]]);
                    if (!empty($result)) {                        
                        $this->indolog(
                            'files',
                            LogLevel::INFO,
                            "$id-р мэдээнд зориулж $file_id дугаартай файлыг бүртгэлээ",
                            ['reason' => 'register-file', 'table' => $table, 'record_id' => $id, 'file_id' => $file_id]
                        );
                        $filesController->moveToFolder($table, $file_id, "/news/$id");
                    }
                }
            } else {
                $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/news-insert.html');
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
    
    public function view(int $id)
    {
        try {
            $context = ['id' => $id, 'model' => NewsModel::class];
            
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $record = $this->indoget('/record?model=' . NewsModel::class, ['id' => $id]);
            $context['record'] = $record;
            $context['files'] = $this->indosafe(
                '/files/records/indo_news', ['WHERE' => "record_id=$id AND is_active=1"]);
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/news-view.html',
                $context + ['accounts' => $this->getAccounts()]
            );
            $dashboard->set('title', $this->text('view-record') . ' | News');
            $dashboard->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['title']} - мэдээг нээж үзэж байна";
        } catch (\Throwable $e ){
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
                
                $this->indoput('/record?model=' . NewsModel::class,
                    ['record' => $record, 'condition' => ['WHERE' => "id=$id"]]
                );
                
                $this->respondJSON([
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success'),
                    'href' => $this->generateLink('news')
                ]);
                
                $level = LogLevel::INFO;
                $message = "{$record['title']} - мэдээг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
                
                if (!isset($files)
                    || empty($files)
                    || !\is_array($files)
                ) {
                    return;
                }
                $current_files = $this->indosafe(
                    "/files/records/$table", ['WHERE' => "record_id=$id AND is_active=1"]);
                foreach ($files as $file_id) {
                    $fid = (int) $file_id;
                    if (\array_key_exists($fid, $current_files)) {
                        continue;
                    }
                    $result = $this->indosafe(
                        "/files/$table/update",
                        ['record' => ['record_id' => $id], 'condition' => ['WHERE' => "id=$fid"]]);
                    if (!empty($result)) {                        
                        $this->indolog(
                            'files',
                            LogLevel::INFO,
                            "$id-р мэдээнд зориулж $fid дугаартай файлыг бүртгэлээ",
                            ['reason' => 'register-file', 'table' => $table, 'record_id' => $id, 'file_id' => $fid]
                        );
                    }
                }
            } else {
                $context['record'] = $this->indoget(
                    '/record?model=' . NewsModel::class, ['id' => $id]);
                $context['files'] = $this->indosafe(
                    "/files/records/$table", ['WHERE' => "record_id=$id AND is_active=1"]);
                $vars = $context + ['accounts' => $this->getAccounts()];
                $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/news-update.html', $vars);
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
            if (empty($payload['id'])
                || !isset($payload['title'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            $context['payload'] = $payload;
            
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            
            $this->indodelete("/record?model=" . NewsModel::class, ['WHERE' => "id=$id"]);
            
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
}
