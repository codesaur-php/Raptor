<?php

namespace Raptor\Organization;

use Psr\Log\LogLevel;
use Psr\Http\Message\ServerRequestInterface;

use Indoraptor\Auth\OrganizationModel;

use Raptor\Dashboard\DashboardController;
use Raptor\File\FileController;

class OrganizationController extends DashboardController
{
    public function __construct(ServerRequestInterface $request)
    {
        $meta = $request->getAttribute('meta', []);
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
        
        $this->twigDashboard(\dirname(__FILE__) . '/organization-index.html')->render();
        
        $this->indolog('organization', LogLevel::NOTICE, 'Байгууллагуудын жагсаалтыг нээж үзэж байна', ['model' => OrganizationModel::class]);
    }
    
    public function datatable()
    {
        try {
            $rows = [];
            
            if (!$this->isUserCan('system_organization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $organizations = $this->indoget('/records?model=' . OrganizationModel::class);
            $rbac_link = $this->generateLink('rbac-alias');
            foreach ($organizations as $record) {
                $id = $record['id'];
                
                $row = [$id];
                
                if (empty($record['logo'])) {
                    $row[] = ' <i class="bi bi-building text-secondary" style="font-size:1.5rem"></i>';
                } else {
                    $row[] = '<img class="img-fluid img-thumbnail" src="' . $record['logo'] . '"  style="max-width:150px;max-height:60px">';
                }
                
                $row[] = \htmlentities($record['name']);
                
                if ($this->getUser()->can('system_rbac')) {
                    $rbac_query = 'alias=' . \urlencode($record['alias']) . '&title=' . \urlencode($record['name']);
                    $row[] = '<a href="' . $rbac_link . '?' . $rbac_query . '">' . \htmlentities($record['alias']) . '</a>';
                } else {
                    $row[] = \htmlentities($record['alias']);
                }

                $action =
                    '<a class="ajax-modal btn btn-sm btn-info shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                    'href="' . $this->generateLink('organization-view', ['id' => $id]) . '"><i class="bi bi-eye"></i></a>';
                
                if ($this->getUser()->can('system_organization_update')) {
                    $action .=
                        ' <a class="ajax-modal btn btn-sm btn-primary shadow-sm" data-bs-target="#dashboard-modal" data-bs-toggle="modal" ' .
                        'href="' . $this->generateLink('organization-update', ['id' => $id]) . '"><i class="bi bi-pencil-square"></i></a>';
                }
                
                if ($this->getUser()->can('system_organization_delete')) {
                    $action .= ' <a class="delete-organization btn btn-sm btn-danger shadow-sm" href="' . $id . '"><i class="bi bi-trash"></i></a>';
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
            $context = ['model' => OrganizationModel::class];
            $is_submit = $this->getRequest()->getMethod() == 'POST';
            
            if (!$this->isUserCan('system_organization_insert')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            if ($is_submit) {
                $parsedBody = $this->getParsedBody();
                if (empty($parsedBody['org_alias'])
                    || empty($parsedBody['org_name'])
                ) {
                    throw new \Exception($this->text('invalid-request'), 400);
                }
                
                $record = [
                    'name' => $parsedBody['org_name'],
                    'alias' => \preg_replace('/[^A-Za-z0-9_-]/', '', $parsedBody['org_alias'])
                ];
                $parent_id = \filter_var($parsedBody['org_parent_id'] ?? 0, \FILTER_VALIDATE_INT);
                if ($parent_id !== false && $parent_id > 0) {
                    $record['parent_id'] = $parent_id;
                }
                $context['record'] = $record;
                
                $id = $this->indopost('/record?model=' . OrganizationModel::class, $record);
                $context['id'] = $id;
                
                $file = new FileController($this->getRequest());
                $file->setFolder("/organizations/$id");
                $file->allowImageOnly();                
                $logo = $file->moveUploaded('org_logo');
                if ($logo) {
                    $payload = [
                        'record' => ['logo' => $logo['path']],
                        'condition' => ['WHERE' => "id=$id"]
                    ];
                    $context['logo'] = $logo;
                    $this->indoput('/record?model=' . OrganizationModel::class, $payload);
                }
                
                $this->respondJSON([
                    'status' => 'success',
                    'message' => $this->text('record-insert-success'),
                    'href' => $this->generateLink('organizations')
                ]);
                
                $level = LogLevel::INFO;
                $message = "Байгууллага [{$record['name']}] үүсгэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $this->twigTemplate(
                    \dirname(__FILE__) . '/organization-insert-modal.html',
                    ['parents' => $this->getParents()])->render();
                
                $level = LogLevel::NOTICE;
                $message = 'Байгууллага үүсгэх үйлдлийг эхлүүллээ';
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $message = 'Байгууллага үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('organization', $level, $message, $context);
        }
    }
    
    private function getParents(): array
    {
        return $this->indosafe('/records?model=' . OrganizationModel::class, ['WHERE' => 'parent_id=0 OR parent_id is null']);
    }
    
    public function view(int $id)
    {
        try {
            $context = ['id' => $id, 'model' => OrganizationModel::class];
            
            if (!$this->isUserCan('system_organization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $record = $this->indoget('/record?model=' . OrganizationModel::class, ['id' => $id]);
            $context['record'] = $record;
            if (!empty($record['parent_id'])) {
                $record['parent_name'] = $this->indosafe('/record?model=' . OrganizationModel::class, ['id' => $record['parent_id']])['name'] ?? '- no parent because its deleted -';
            }
            $this->twigTemplate(
                \dirname(__FILE__) . '/organization-retrieve-modal.html',
                ['record' => $record, 'accounts' => $this->getAccounts()])->render();

            $level = LogLevel::NOTICE;
            $message = "{$record['name']} байгууллагын мэдээллийг нээж үзэж байна";
        } catch (\Throwable $e) {
            $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            
            $level = LogLevel::ERROR;
            $message = 'Байгууллагын мэдээллийг нээж үзэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('organization', $level, $message, $context);
        }
    }
    
    public function update(int $id)
    {
        try {
            $is_submit = $this->getRequest()->getMethod() == 'PUT';
            $context = ['id' => $id, 'model' => OrganizationModel::class];
            
            if (!$this->isUserCan('system_organization_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            if ($is_submit) {
                $payload = $this->getParsedBody();
                if (empty($payload['org_alias'])
                    || empty($payload['org_name'])
                ) {
                    throw new \Exception($this->text('invalid-request'), 400);
                }
                
                $record = [
                    'name' => $payload['org_name'],
                    'alias' => \preg_replace('/[^A-Za-z0-9_-]/', '', $payload['org_alias'])
                ];
                
                if (isset($payload['org_parent_id'])) {
                    $parent_id = \filter_var($payload['org_parent_id'], \FILTER_VALIDATE_INT);
                    if ($parent_id !== false && $parent_id >= 0) {
                        $record['parent_id'] = $parent_id;
                    }
                }
                
                $context['record'] = $record;
                $context['record']['id'] = $id;
                
                $existing = $this->indosafe('/record?model=' . OrganizationModel::class, ['id' => $id, 'is_active' => 1]);
                $old_logo_file = \basename($existing['logo'] ?? '');
                $file = new FileController($this->getRequest());
                $file->setFolder("/organizations/$id");
                $file->allowImageOnly();
                $logo = $file->moveUploaded('org_logo');
                if ($logo) {
                    $record['logo'] = $logo['path'];
                }
                if (!empty($old_logo_file)) {
                    if ($file->getLastError() == -1) {
                        $file->tryDeleteFile($old_logo_file);
                        $record['logo'] = '';
                    } elseif (isset($record['logo'])
                        && \basename($record['logo']) != $old_logo_file
                    ) {
                        $file->tryDeleteFile($old_logo_file);
                    }
                }
                if (isset($record['logo'])) {
                    $context['record']['logo'] = $record['logo'];
                }
                
                $this->indoput(
                    '/record?model=' . OrganizationModel::class,
                    ['record' => $record, 'condition' => ['WHERE' => "id=$id"]]
                );
                
                $this->respondJSON([
                    'status' => 'success',
                    'type' => 'primary',
                    'message' => $this->text('record-update-success'),
                    'href' => $this->generateLink('organizations')
                ]);
                
                $level = LogLevel::INFO;
                $message = "{$record['name']} байгууллагын мэдээллийг шинэчлэх үйлдлийг амжилттай гүйцэтгэлээ";
            } else {
                $record = $this->indoget('/record?model=' . OrganizationModel::class, ['id' => $id]);
                $this->twigTemplate(
                    \dirname(__FILE__) . '/organization-update-modal.html',
                    ['record' => $record, 'parents' => $this->getParents()])->render();
                
                $level = LogLevel::NOTICE;
                $context['record'] = $record;
                $message = "{$record['name']} байгууллагын мэдээллийг шинэчлэхээр нээж байна";
            }
        } catch (\Throwable $e) {
            if ($is_submit) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
            } else {
                $this->modalProhibited($e->getMessage(), $e->getCode())->render();
            }
            
            $level = LogLevel::ERROR;
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
            $message = 'Байгууллагын мэдээллийг өөрчлөх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
        } finally {
            $this->indolog('organization', $level, $message, $context);
        }
    }
    
    public function delete()
    {
        try {
            $context = ['model' => OrganizationModel::class];
            
            if (!$this->isUserCan('system_organization_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }
            
            $payload = $this->getParsedBody();
            if (empty($payload['id'])
                || !isset($payload['name'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \Exception($this->text('invalid-request'), 400);
            }
            $context['payload'] = $payload;
            
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);

            if ($this->getUser()->getOrganization()['id'] == $id) {
                throw new \Exception('Cannot remove currently active organization!', 403);
            } elseif ($id == 1) {
                throw new \Exception('Cannot remove first organization!', 403);
            }
            
            $this->indodelete('/record?model=' . OrganizationModel::class, ['WHERE' => "id=$id"]);
            
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
            
            $level = LogLevel::ALERT;
            $message = "{$payload['name']} байгууллагыг устгалаа";
        } catch (\Throwable $e) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $e->getMessage()
            ], $e->getCode());
            
            $level = LogLevel::ERROR;
            $message = 'Байгууллагыг устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('organization', $level, $message, $context);
        }
    }
}
