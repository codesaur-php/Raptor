<?php

namespace Raptor\Organization;

use Exception;
use Throwable;

use Psr\Log\LogLevel;
use Psr\Http\Message\ServerRequestInterface;

use Indoraptor\Auth\OrganizationModel;

use Raptor\Dashboard\DashboardController;
use Raptor\File\FileController;

class OrganizationController extends DashboardController
{
    function __construct(ServerRequestInterface $request)
    {
        $meta = $request->getAttribute('meta', array());
        $localization = $request->getAttribute('localization');
        if (isset($localization['code'])
            && isset($localization['text']['organizations'])
        ) {
            $meta['content']['title'][$localization['code']] = $localization['text']['organizations'];
            $request = $request->withAttribute('meta', $meta);
        }
        
        parent::__construct($request);
    }
    
    public function index()
    {
        if (!$this->isUserCan('system_organization_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }
        
        $this->twigDashboard(dirname(__FILE__) . '/organization-index.html')->render();        
        
        $this->indolog('organization', LogLevel::NOTICE, 'Байгууллагуудын жагсаалтыг нээж үзэж байна', array('model' => OrganizationModel::class));
    }
    
    public function insert()
    {
        $context = array('model' => OrganizationModel::class);
        $is_submit = $this->getRequest()->getMethod() == 'POST';
        
        try {            
            if (!$this->isUserCan('system_organization_insert')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            if ($is_submit) {
                $parsedBody = $this->getParsedBody();
                if (empty($parsedBody['org_alias'])
                    || empty($parsedBody['org_name'])
                ) {
                    throw new Exception($this->text('invalid-request'), 400);
                }
                
                $record = array(
                    'name' => $parsedBody['org_name'],
                    'alias' => preg_replace('/[^A-Za-z0-9_-]/', '', $parsedBody['org_alias'])
                );
                $parent_id = filter_var($parsedBody['org_parent_id'] ?? 0, FILTER_VALIDATE_INT);
                if ($parent_id !== false && $parent_id > 0) {
                    $record['parent_id'] = $parent_id;
                }
                $context['record'] = $record;
                
                $id = $this->indopost('/record?model=' . OrganizationModel::class, array('record' => $record));
                $context['id'] = $id;
                
                $file = new FileController($this->getRequest());
                $file->init("/organizations/$id");
                $file->allowType(3);
                $logo = $file->moveUploaded('org_logo');
                if (isset($logo['name'])) {
                    $logo_path = $file->getPathUrl($logo['name']);
                    $payload = array(
                        'record' => array('logo' => $logo_path),
                        'condition' => array('WHERE' => "id=$id")
                    );
                    $context['logo'] = $logo_path;
                    $this->indoput('/record?model=' . OrganizationModel::class, $payload);
                }
                
                $this->respondJSON(array(
                    'status' => 'success',
                    'message' => $this->text('record-insert-success'),
                    'href' => $this->generateLink('organizations')
                ));
                
                $level = LogLevel::INFO;
                $message = "Байгууллага [{$record['name']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $template_path = dirname(__FILE__) . '/organization-insert-modal.html';
                if (!file_exists($template_path)) {
                    throw new Exception("$template_path file not found!", 500);
                }
                $this->twigTemplate($template_path, array('parents' => $this->getParents()))->render();
                
                $level = LogLevel::NOTICE;
                $message = 'Байгууллага үүсгэх үйлдлийг эхлүүллээ';
            }
        } catch (Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(array('message' => $e->getMessage()), $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = 'Байгууллага үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('organization', $level, $message, $context);
        }
    }
    
    function getParents()
    {
        return $this->indosafe('/record/rows?model=' . OrganizationModel::class, array('WHERE' => 'parent_id=0 OR parent_id is null')) ?: array();
    }
    
    public function view(int $id)
    {
        $context = array('id' => $id, 'model' => OrganizationModel::class);
        
        try {            
            if (!$this->isUserCan('system_organization_index')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            $record = $this->indo('/record?model=' . OrganizationModel::class, array('id' => $id));
            $context['record'] = $record;
            
            if (!empty($record['parent_id'])) {
                $record['parent_name'] = $this->indosafe('/record?model=' . OrganizationModel::class, array('id' => $record['parent_id']))['name'] ?? '- no parent because its deleted -';
            }
            
            $template_path = dirname(__FILE__) . '/organization-retrieve-modal.html';
            if (!file_exists($template_path)) {
                throw new Exception("$template_path file not found!", 500);
            }
            $this->twigTemplate($template_path, array('record' => $record, 'accounts' => $this->getAccounts()))->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['name']} байгууллагын мэдээллийг нээж үзэж байна";
        } catch (Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = 'Байгууллагын мэдээллийг нээж үзэх үед алдаа гарч зогслоо байна';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('organization', $level, $message, $context);
        }
    }
    
    public function update(int $id)
    {
        $is_submit = $this->getRequest()->getMethod() == 'PUT';
        $context = array('id' => $id, 'model' => OrganizationModel::class);
        
        try {            
            if (!$this->isUserCan('system_organization_update')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            if ($is_submit) {
                $payload = $this->getParsedBody();
                if (empty($payload['org_alias'])
                    || empty($payload['org_name'])
                ) {
                    throw new Exception($this->text('invalid-request'), 400);
                }
                
                $record = array(
                    'name' => $payload['org_name'],
                    'alias' => preg_replace('/[^A-Za-z0-9_-]/', '', $payload['org_alias'])
                );
                
                if (isset($payload['org_parent_id'])) {
                    $parent_id = filter_var($payload['org_parent_id'], FILTER_VALIDATE_INT);
                    if ($parent_id !== false && $parent_id >= 0) {
                        $record['parent_id'] = $parent_id;
                    }
                }
                
                $context['record'] = $record;
                $context['record']['id'] = $id;
                
                $existing = $this->indosafe('/record?model=' . OrganizationModel::class, array('id' => $id, 'is_active' => 1));
                $old_logo_file = basename($existing['logo'] ?? '');
                $file = new FileController($this->getRequest());
                $file->init("/organizations/$id");
                $file->allowType(3);
                $logo = $file->moveUploaded('org_logo');
                if (isset($logo['name'])) {
                    $record['logo'] = $file->getPathUrl($logo['name']);
                }
                if (!empty($old_logo_file)) {
                    if ($file->getLastError() == -1) {
                        $this->tryDeleteFile(dirname($_SERVER['SCRIPT_FILENAME']) . "/public/organizations/$id/$old_logo_file");
                        $record['logo'] = '';
                    } else if (isset($logo['name']) && $logo['name'] != $old_logo_file) {
                        $this->tryDeleteFile(dirname($_SERVER['SCRIPT_FILENAME']) . "/public/organizations/$id/$old_logo_file");
                    }
                }
                if (isset($record['logo'])) {
                    $context['record']['logo'] = $record['logo'];
                }
                
                $this->indoput('/record?model=' . OrganizationModel::class,
                        array('record' => $record, 'condition' => ['WHERE' => "id=$id"]));
                
                $this->respondJSON(array(
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success'),
                    'href' => $this->generateLink('organizations')
                ));
                
                $level = LogLevel::INFO;
                $message = "{$record['name']} байгууллагын мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $record = $this->indo('/record?model=' . OrganizationModel::class, array('id' => $id));
                
                $template_path = dirname(__FILE__) . '/organization-update-modal.html';
                if (!file_exists($template_path)) {
                    throw new Exception("$template_path file not found!", 500);
                }
                $this->twigTemplate($template_path, array('record' => $record, 'parents' => $this->getParents()))->render();
                
                $level = LogLevel::NOTICE;
                $context['record'] = $record;
                $message = "{$record['name']} байгууллагын мэдээллийг шинэчлэхээр нээж байна";
            }
        } catch (Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(array('message' => $e->getMessage()), $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
            $message = 'Байгууллагын мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо байна';
        } finally {
            $this->indolog('organization', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        $context = array('model' => OrganizationModel::class);
        
        try {
            if (!$this->isUserCan('system_organization_delete')) {
                throw new Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (empty($payload['id'])
                || !isset($payload['name'])
                || !filter_var($payload['id'], FILTER_VALIDATE_INT)
            ) {
                throw new Exception($this->text('invalid-request'), 400);
            }
            $context['payload'] = $payload;
            
            $table = '';
            if (!empty($payload['table'])) {
                $table = "table={$payload['table']}&";
            }

            if ($this->getUser()->getOrganization()['id'] == $payload['id']) {
                throw new Exception('Cannot remove currently active organization!', 403);
            } else if ($payload['id'] == 1) {
                throw new Exception('Cannot remove first organization!', 403);
            }
            
            $this->indodelete("/record?{$table}model=" . OrganizationModel::class, array('WHERE' => "id='{$payload['id']}'"));
            
            $this->respondJSON(array(
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ));
            
            $level = LogLevel::ALERT;
            $message = "{$payload['name']} байгууллагыг устгалаа";
        } catch (Throwable $e) {
            $this->respondJSON(array(
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ), $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Байгууллагыг устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = array('code' => $e->getCode(), 'message' => $e->getMessage());
        } finally {
            $this->indolog('organization', $level, $message, $context);
        }
    }
    
    public function datatable()
    {
        $rows = array();
        
        try {
            if (!$this->isUserCan('system_organization_index')) {
                throw new Exception($this->text('system-no-permission'), 401);
            }
            
            $code = preg_replace('/[^a-z]/', '', $this->getLanguageCode());
            $statuses = $this->indo('/lookup', array('table' => 'status', 'condition' => array('WHERE' => "c.code='$code' AND p.is_active=1")));
            $organizations = $this->indo('/record/rows?model=' . OrganizationModel::class);
            $rbac_link = $this->generateLink('rbac-alias');
            foreach ($organizations as $record) {
                $id = $record['id'];
                
                $row = array($id);
                
                if (empty($record['logo'])) {
                    $row[] = ' <i class="bi bi-building text-secondary" style="font-size:1.5rem"></i>';
                } else {
                    $row[] = '<img class="img-fluid img-thumbnail" src="' . $record['logo'] . '"  style="max-width:150px;max-height:60px">';
                }                
                
                $row[] = htmlentities($record['name']);
                
                if ($this->getUser()->can('system_rbac')) {
                    $rbac_query = 'alias=' . urlencode($record['alias']) . '&title=' . urlencode($record['name']);
                    $row[] = '<a href="' . $rbac_link . '?' . $rbac_query . '">' . htmlentities($record['alias']) . '</a>';
                } else {
                    $row[] = htmlentities($record['alias']);
                }

                $action = '<a class="ajax-modal btn btn-sm btn-info shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                    'href="' . $this->generateLink('organization-view', array('id' => $id)) . '"><i class="bi bi-eye"></i></a>' . PHP_EOL;
                if ($this->getUser()->can('system_organization_update')) {
                    $action .= '<a class="ajax-modal btn btn-sm btn-primary shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                        'href="' . $this->generateLink('organization-update', array('id' => $id)) . '"><i class="bi bi-pencil-square"></i></a>' . PHP_EOL;
                }
                if ($this->getUser()->can('system_organization_delete')) {
                    $action .= '<a class="delete-organization btn btn-sm btn-danger shadow-sm" href="' . $id . '"><i class="bi bi-trash"></i></a>';
                }
                
                $row[] = $action;

                $rows[] = $row;
            }
        } catch (Throwable $e) {
            $this->errorLog($e);
        } finally {
            $this->respondJSON(array(
                'data' => $rows,
                'recordsTotal' => count($rows),
                'recordsFiltered' => count($rows),
                'draw' => (int)($this->getQueryParams()['draw'] ?? 0)
            ));
        }
    }
}
