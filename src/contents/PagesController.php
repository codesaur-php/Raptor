<?php

namespace Raptor\Contents;

use Psr\Log\LogLevel;
use Psr\Http\Message\ServerRequestInterface;

use Indoraptor\Contents\PagesModel;

use Raptor\Dashboard\DashboardController;

class PagesController extends DashboardController
{
    function __construct(ServerRequestInterface $request)
    {
        $meta = $request->getAttribute('meta', []);
        $localization = $request->getAttribute('localization');
        if (isset($localization['code'])
            && isset($localization['text']['pages'])
        ) {
            $meta['content']['title'][$localization['code']] = $localization['text']['pages'];
            $request = $request->withAttribute('meta', $meta);
        }
        
        parent::__construct($request);
    }
    
    public function index()
    {
        if (!$this->isUserCan('system_content_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        $this->twigDashboard(\dirname(__FILE__) . '/pages-index.html')->render();
        
        $this->indolog('content', LogLevel::NOTICE, 'Хуудас жагсаалтыг нээж үзэж байна', ['model' => PagesModel::class]);
    }
    
    public function datatable()
    {
        try {
            $rows = [];
            
            if (!$this->isUserCan('system_content_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $code = $this->getLanguageCode();
            $language_codes = \array_keys($this->getLanguages());
            $pages = $this->indoget('/records?model=' . PagesModel::class);
            $infos = $this->getPagesInfos();
            foreach ($pages as $record) {
                $id = $record['id'];
                $row = [$id, '<img src="https://via.placeholder.com/50?text=no+photo">'];
                
                $title = '';
                if (isset($infos[$id]['parent_titles'])) {
                    $title .= '<span class="text-muted"><small>' . \htmlentities($infos[$id]['parent_titles']) . '</small></span> ';
                }
                $caption = \htmlentities($record['content']['title'][$code]);
                $title .= '<span class="text-primary">' . $caption . '</span>';
                $row[] = $title;
                
                $row[] =
                    '<span class="badge bg-dark">' . \htmlentities($record['category']) . '</span> ' .
                    '<span class="badge bg-warning text-dark">' . \htmlentities($record['type']) . '</span>';
                $row[] = $record['position'];
                
                $visiblity = '';
                foreach ($language_codes as $flag) {
                    if (!empty($record['content']['is_visible'][$flag])) {
                        $visiblity .= '<img src="https://cdn.jsdelivr.net/gh/codesaur-php/HTML-Assets@2.5.3/flags/' . $flag . '.png"> ';
                    }
                }
                $row[] = $visiblity;

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
                
                if (empty($record['publish_date'])
                ) {
                    $record['publish_date'] = \date('Y-m-d H:i:s');
                }
                foreach ($content as &$visible)
                {
                    $visible['is_visible'] = ($visible['is_visible'] ?? 'off' ) == 'on' ? 1 : 0;
                }
                
                $this->indopost('/record?model=' . PagesModel::class, ['record' => $record, 'content' => $content]);

                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success'),
                    'href' => $this->generateLink('pages')
                ]);
                
                $level = LogLevel::INFO;
                $message = "Шинэ хуудас [{$content[$this->getLanguageCode()]['title']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $template_path = \dirname(__FILE__) . '/page-insert.html';
                if (!\file_exists($template_path)) {
                    throw new \Exception("$template_path file not found!", 500);
                }
                
                $vars = [
                    'infos'=> $this->getPagesInfos()
                ];
                $this->twigDashboard($template_path, $vars)->render();
                
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
            
            $record = $this->indoget('/record?model=' . PagesModel::class, ['p.id' => $id]);
            $context['record'] = $record;
            
            $template_path = \dirname(__FILE__) . '/page-view.html';
            if (!\file_exists($template_path)) {
                throw new \Exception("$template_path file not found!", 500);
            }
            $this->twigDashboard($template_path, [
                'record' => $record,
                'accounts' => $this->getAccounts(),
                'infos' => $this->getPagesInfos("(p.id=$id OR p.id={$record['parent_id']})")
            ])->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['content']['title'][$this->getLanguageCode()]} - хуудасны мэдээллийг нээж үзэж байна";
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
            
            if ($is_submit) {
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
                
                if (empty($record['publish_date'])
                ) {
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
            } else {
                $record = $this->indoget('/record?model=' . PagesModel::class, ['p.id' => $id]);
                
                $template_path = \dirname(__FILE__) . '/page-update.html';
                if (!\file_exists($template_path)) {
                    throw new \Exception("$template_path file not found!", 500);
                }
                
                $vars = [
                    'record' => $record,
                    'accounts' => $this->getAccounts(),
                    'infos' => $this->getPagesInfos("p.id!=$id AND p.parent_id!=$id")
                ];
                $this->twigDashboard($template_path, $vars)->render();
                
                $level = LogLevel::NOTICE;
                $context['record'] = $record;
                $message = "{$record['content']['title'][$this->getLanguageCode()]} - хуудасны мэдээллийг шинэчлэхээр нээж байна";
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
        $code = \preg_replace('/[^a-z]/', '', $this->getLanguageCode());
        $pages_query = 
            'SELECT p.id, c.title, p.parent_id ' .
            'FROM pages as p JOIN pages_content as c ON p.id=c.parent_id ' .
            "WHERE p.is_active=1 AND c.code='$code'";
        $pages = $this->indosafe('/statement', ['query' => $pages_query]);
        if (!empty($condition)) {
            $pages_query .= " AND $condition";
            $pages_specified = $this->indosafe('/statement', ['query' => $pages_query]);
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
}
