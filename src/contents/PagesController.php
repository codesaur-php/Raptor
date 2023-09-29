<?php

namespace Raptor\Contents;

use Psr\Log\LogLevel;

use Indoraptor\Contents\PagesModel;

use Raptor\Dashboard\DashboardController;
use Raptor\File\FilesController;

class PagesController extends DashboardController
{
    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }
        
        $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/pages-index.html');
        $dashboard->set('title', $this->text('pages'));
        $dashboard->render();
        
        $this->indolog('content', LogLevel::NOTICE, 'Хуудас жагсаалтыг нээж үзэж байна', ['model' => PagesModel::class]);
    }
    
    public function datatable()
    {
        try {
            $rows = [];
            
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $images = $this->getImages();
            $featured = $this->getFeaturedImages();
            $files_counts = $this->getFilesCounts();
            $pages_query = 
                'SELECT id, title, code, category, type, position, published ' .
                'FROM indo_pages WHERE is_active=1';
            $pages = $this->indo('/statement', ['query' => $pages_query]);
            $infos = $this->getPagesInfos();
            foreach ($pages as $record) {
                if (!empty($record['code'])) {
                    $lang = '<img src="https://cdn.jsdelivr.net/gh/codesaur-php/HTML-Assets@2.5.3/flags/' . $record['code'] . '.png"> ' . $record['code'];
                } else {
                    $lang = '';
                }
                
                $id = $record['id'];
                $row = [$id, $lang];
                
                $image = $featured[$id] ?? $images[$id] ?? 'https://via.placeholder.com/60?text=no+photo';
                $row[] = "<img style=\"max-width:60px;max-height:60px\" src=\"$image\"\>";
                
                $title = '';
                if (isset($infos[$id]['parent_titles'])) {
                    $title .= '<span class="fw-lighter"><small>' . \htmlentities($infos[$id]['parent_titles']) . '</small></span> ';
                }
                $caption = \htmlentities($record['title']);
                $title .= '<span class="fw-medium">' . $caption . '</span>';
                $row[] = $title;
                
                $row[] = $files_counts[$id] ?? 0;
                
                $row[] =
                    '<span class="badge bg-dark">' . \htmlentities($record['category']) . '</span> ' .
                    '<span class="badge bg-warning text-dark">' . \htmlentities($record['type']) . '</span>';
                    
                $row[] = $record['position'];
                
                $row[] = $record['published'] == 1 ? '<i class="bi bi-emoji-heart-eyes-fill text-success"></i>' : '<i class="bi bi-eye-slash"></i>';

                $action =
                    '<a class="btn btn-sm btn-info shadow-sm" href="' .
                    $this->generateLink('page-view', ['id' => $id]) . '"><i class="bi bi-eye"></i></a>';
                
                if ($this->getUser()->can('system_content_update')) {
                    $action .=
                        ' <a class="btn btn-sm btn-primary shadow-sm" href="'
                        . $this->generateLink('page-update', ['id' => $id]) . '"><i class="bi bi-pencil-square"></i></a>';
                }
                
                if ($this->getUser()->can('system_content_delete')) {
                    $action .= 
                        ' <a class="delete-page btn btn-sm btn-danger shadow-sm" href="' . $id .
                        '" data-title="' . $caption . '"><i class="bi bi-trash"></i></a>';
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
            $context = ['model' => PagesModel::class];
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            
            if (!$this->isUserCan('system_content_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
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
                
                $id = $this->indopost('/record?model=' . PagesModel::class, $record);
                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success'),
                    'href' => $this->generateLink('pages')
                ]);
                
                $level = LogLevel::INFO;
                $message = "Шинэ хуудас [{$record['title']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
                
                if (!isset($files)
                    || empty($files)
                    || !\is_array($files)
                ) {
                    return;
                }
                $table = 'indo_pages';
                $filesController = new FilesController($this->getRequest());
                foreach ($files as $file_id) {
                    $result = $this->indosafe(
                        "/files/$table/update",
                        ['record' => ['record_id' => $id], 'condition' => ['WHERE' => "id=$file_id"]]);
                    if (!empty($result)) {                        
                        $this->indolog(
                            'files',
                            LogLevel::INFO,
                            "$id-р хуудаст зориулж $file_id дугаартай файлыг бүртгэлээ",
                            ['reason' => 'register-file', 'table' => $table, 'record_id' => $id, 'file_id' => $file_id]
                        );
                        $filesController->moveToFolder($table, $file_id, "/pages/$id");
                    }
                }
            } else {
                $vars = [
                    'infos' => $this->getPagesInfos()
                ];
                $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/page-insert.html', $vars);
                $dashboard->set('title', $this->text('add-record') . ' | Pages');
                $dashboard->render();
                
                $level = LogLevel::NOTICE;
                $message = 'Шинэ хуудас үүсгэх үйлдлийг эхлүүллээ';
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = 'Шинэ хуудас үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function view(int $id)
    {
        try {
            $context = ['id' => $id, 'model' => PagesModel::class];
            
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $record = $this->indoget('/record?model=' . PagesModel::class, ['id' => $id]);
            $context['record'] = $record;
            $context['files'] = $this->indosafe(
                '/files/records/indo_pages', ['WHERE' => "record_id=$id AND is_active=1"]);
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/page-view.html',
                $context + [
                    'accounts' => $this->getAccounts(),
                    'infos' => $this->getPagesInfos("(id=$id OR id={$record['parent_id']})")
                ]
            );
            $dashboard->set('title', $this->text('view-record') . ' | Pages');
            $dashboard->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['title']} - хуудасны мэдээллийг нээж үзэж байна";
        } catch (\Throwable $e ){
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = 'Хуудасны мэдээллийг нээж үзэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function update(int $id)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $context = ['id' => $id, 'model' => PagesModel::class];
            
            if (!$this->isUserCan('system_content_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $table = 'indo_pages';
            
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
                
                $this->indoput('/record?model=' . PagesModel::class,
                    ['record' => $record, 'condition' => ['WHERE' => "id=$id"]]
                );
                
                $this->respondJSON([
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success'),
                    'href' => $this->generateLink('pages')
                ]);
                
                $level = LogLevel::INFO;
                $message = "{$record['title']} - хуудасны мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
                
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
                            "$id-р хуудаст зориулж $fid дугаартай файлыг бүртгэлээ",
                            ['reason' => 'register-file', 'table' => $table, 'record_id' => $id, 'file_id' => $fid]
                        );
                    }
                }
            } else {
                $context['record'] = $this->indoget(
                    '/record?model=' . PagesModel::class, ['id' => $id]);
                $context['files'] = $this->indosafe(
                    "/files/records/$table", ['WHERE' => "record_id=$id AND is_active=1"]);
                $vars = $context + [
                    'accounts' => $this->getAccounts(),
                    'infos' => $this->getPagesInfos("id!=$id AND parent_id!=$id")
                ];
                $dashboard = $this->twigDashboard(\dirname(__FILE__) . '/page-update.html', $vars);
                $dashboard->set('title', $this->text('edit-record') . ' | Pages');
                $dashboard->render();
                
                $level = LogLevel::NOTICE;
                $message = "{$context['record']['title']} - хуудасны мэдээллийг шинэчлэхээр нээж байна";
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $message = 'Хуудсыг засах үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        try {
            $context = ['model' => PagesModel::class];
            
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
            $this->indodelete("/record?model=" . PagesModel::class, ['WHERE' => "id=$id"]);
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
            
            $level = LogLevel::ALERT;
            $message = "{$payload['title']} - хуудсыг устгалаа";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Хуудсыг устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('content', $level, $message, $context);
        }
    }
    
    private function getPagesInfos(string $condition = ''): array
    {
        $pages_query = 
            'SELECT id, parent_id, title ' .
            'FROM indo_pages WHERE is_active=1';
        $result = $this->indosafe('/statement', ['query' => $pages_query]);
        $pages = [];
        foreach ($result as $record) {
            $pages[$record['id']] = $record;
        }
        if (!empty($condition)) {
            $pages_query .= " AND $condition";
            $result_specified = $this->indosafe('/statement', ['query' => $pages_query]);
            $pages_specified = [];
            foreach ($result_specified as $row) {
                $pages_specified[$row['id']] = $row;
            }
        }
        foreach ($pages as $page) {
            $id = $page['id'];
            $ancestry = $this->findAncestry($id, $pages);
            if (\array_key_exists($id, $ancestry)) {
                unset($ancestry[$id]);
                
                \error_log(__CLASS__ . ": Page $id misconfigured with parenting path!");
            }
            if (empty($ancestry)) {
                continue;
            }
            
            $path = '';
            $ancestry_keys = \array_flip($ancestry);
            for ($i = \count($ancestry_keys); $i > 0; $i--) {
                $path .= "{$pages[$ancestry_keys[$i]]['title']} » ";
            }
            $pages[$id]['parent_titles'] = $path;
            if (isset($pages_specified[$id])) {
                $pages_specified[$id]['parent_titles'] = $path;
            }
        }
        
        return $pages_specified ?? $pages;
    }
    
    private function findAncestry(int $id, array $pages, array &$ancestry = []): array
    {
        $parent = $pages[$id]['parent_id'];
        if (empty($parent)
            || !isset($pages[$parent])
            || \array_key_exists($parent, $ancestry)
        ) {
            return $ancestry;
        }
        
        $ancestry[$parent] = \count($ancestry) + 1;
        return $this->findAncestry($parent, $pages, $ancestry);
    }
    
    private function getFilesCounts(): array
    {
        $files_count_query = 
            'SELECT n.id as id, COUNT(*) as files ' .
            'FROM indo_pages as n JOIN indo_pages_files as f ON n.id=f.record_id ' .
            'WHERE n.is_active=1 AND f.is_active=1 ' .
            'GROUP BY f.record_id';
        $result =  $this->indo('/statement', ['query' => $files_count_query]);
        $counts = [];
        foreach ($result as $count) {
            $counts[$count['id']] = $count['files'];
        }
        return $counts;
    }
    
    private function getImages(): array
    {
        $images_query = 
            'SELECT n.id as id, f.path as image ' .
            'FROM indo_pages as n JOIN indo_pages_files as f ON n.id=f.record_id ' .
            "WHERE n.is_active=1 AND f.is_active=1 AND f.type='image' " .
            'GROUP BY f.record_id';
        $result =  $this->indo('/statement', ['query' => $images_query]);
        $images = [];
        foreach ($result as $file) {
            $images[$file['id']] = $file['image'];
        }
        return $images;
    }
    
    private function getFeaturedImages(): array
    {
        $featured_query = 
            'SELECT n.id as id, f.path as image, f.id as file_id ' .
            'FROM indo_pages as n JOIN indo_pages_files as f ON n.id=f.record_id ' .
            "WHERE n.is_active=1 AND f.is_active=1 AND f.type='image' AND f.category='featured' " .
            'ORDER BY f.updated_at desc';
        $result = $this->indo('/statement', ['query' => $featured_query]);
        $featured = [];
        foreach ($result as $file) {
            if (isset($featured[$file['id']])) {
                $this->indosafe(
                    '/files/indo_pages_files/update',
                    ['record' => ['category' => ''], 'condition' => ['WHERE' => "id={$file['file_id']}"]]);
            } else {
                $featured[$file['id']] = $file['image'];
            }
        }
        return $featured;
    }
}
