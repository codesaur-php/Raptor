<?php

namespace Raptor\Organization;

use Psr\Log\LogLevel;

use Indoraptor\Auth\OrganizationModel;

use Raptor\Dashboard\DashboardController;
use Raptor\File\FileController;

class OrganizationController extends DashboardController
{
    public function index()
    {
        try {
            $context = ['model' => OrganizationModel::class];
            
            if (!$this->isUserCan('system_organization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $organizations = $this->indo('/execute/fetch/all', [
                'query' => 'SELECT id,name,logo,alias FROM indo_organizations WHERE is_active=1'
            ]);
            $dashboard = $this->twigDashboard(
                \dirname(__FILE__) . '/organization-index.html',
                ['organizations' => $organizations]
            );
            $dashboard->set('title', $this->text('organizations'));
            $dashboard->render();
            
            $message = 'Байгууллагуудын жагсаалтыг нээж үзэж байна';
        } catch (\Throwable $e) {
            $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();

            $level = LogLevel::ERROR;
            $message = 'Байгууллагуудын жагсаалтыг нээж үзэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо';
            $context['error'] = ['code' => $e->getCode(), 'message' => $e->getMessage()];
        } finally {
            $this->indolog('organization', $level ?? LogLevel::NOTICE, $message, $context);
        }
    }
    
    public function list()
    {
        try {
            if (!$this->isUserCan('system_organization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $this->respondJSON([
                'status' => 'success',
                'list' => $this->indo('/execute/fetch/all', [
                    'query' => 'SELECT id,name,alias,logo FROM indo_organizations WHERE is_active=1'
                ])
            ]);
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
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
                if (empty($parsedBody['alias'])
                    || empty($parsedBody['name'])
                ) {
                    throw new \Exception($this->text('invalid-request'), 400);
                }
                
                $record = [
                    'name' => $parsedBody['name'],
                    'alias' => \preg_replace('/[^A-Za-z0-9_-]/', '', $parsedBody['alias'])
                ];
                $parent_id = \filter_var($parsedBody['parent_id'] ?? 0, \FILTER_VALIDATE_INT);
                if ($parent_id !== false && $parent_id > 0) {
                    $record['parent_id'] = $parent_id;
                }
                $context['record'] = $record;
                
                $id = $this->indopost('/record?model=' . OrganizationModel::class, $record);
                $context['id'] = $id;
                
                $file = new FileController($this->getRequest());
                $file->setFolder("/organizations/$id");
                $file->allowImageOnly();
                $logo = $file->moveUploaded('logo');
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
                    'message' => $this->text('record-insert-success')
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
        try {
            return $this->indo(
                '/records?model=' . OrganizationModel::class,
                ['WHERE' => 'parent_id=0 OR parent_id is null']
            );
        } catch (\Throwable $e) {
            return [];
        }
    }
    
    public function view(int $id)
    {
        try {
            $context = ['id' => $id, 'model' => OrganizationModel::class];
            
            if (!$this->isUserCan('system_organization_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            
            $record = $this->indoget('/record?model=' . OrganizationModel::class, ['id' => $id]);
            $record['rbac_accounts'] = $this->getRBACAccounts($record['created_by'], $record['updated_by']);
            $context['record'] = $record;
            if (!empty($record['parent_id'])) {
                try {
                    $record['parent_name'] = $this->indo(
                        '/record?model=' . OrganizationModel::class,
                        ['id' => $record['parent_id']]
                    )['name'];
                } catch (\Throwable $e) {
                    $record['parent_name'] = '- no parent because its deleted -';
                }
            }
            $this->twigTemplate(\dirname(__FILE__) . '/organization-retrieve-modal.html', ['record' => $record])->render();

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
                if (empty($payload['alias'])
                    || empty($payload['name'])
                ) {
                    throw new \Exception($this->text('invalid-request'), 400);
                }
                
                $record = [
                    'name' => $payload['name'],
                    'alias' => \preg_replace('/[^A-Za-z0-9_-]/', '', $payload['alias'])
                ];
                
                if (isset($payload['parent_id'])) {
                    $parent_id = \filter_var($payload['parent_id'], \FILTER_VALIDATE_INT);
                    if ($parent_id !== false && $parent_id >= 0) {
                        $record['parent_id'] = $parent_id;
                    }
                }
                
                $context['record'] = $record;
                $context['record']['id'] = $id;
                
                try {
                    $current_record = $this->indo(
                        '/record?model=' . OrganizationModel::class,
                        ['id' => $id]
                    );
                    if (empty($current_record['logo'])) {
                        throw new \Exception('Current record had no logo!');
                    }
                    $current_logo_file = \basename($current_record['logo']);
                } catch (\Throwable $e) {
                    $current_logo_file = '';
                }
                
                $file = new FileController($this->getRequest());
                $file->setFolder("/organizations/$id");
                $file->allowImageOnly();
                $logo = $file->moveUploaded('logo');
                if ($logo) {
                    $record['logo'] = $logo['path'];
                }
                if (!empty($current_logo_file)) {
                    if ($file->getLastError() == -1) {
                        $file->tryDeleteFile($current_logo_file);
                        $record['logo'] = '';
                    } elseif (isset($record['logo'])
                        && \basename($record['logo']) != $current_logo_file
                    ) {
                        $file->tryDeleteFile($current_logo_file);
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
                    'message' => $this->text('record-update-success')
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
            if (!isset($payload['id'])
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
