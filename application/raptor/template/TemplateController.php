<?php

namespace Raptor\Template;

use Psr\Log\LogLevel;

use codesaur\DataObject\Constants;

use Raptor\Organization\OrganizationModel;
use Raptor\RBAC\Permissions;

/**
 * Class TemplateController
 *
 * Raptor Framework-ийн Template (UI/UX) модульд зориулсан Controller.
 * Dashboard-ийн меню, хэрэглэгчийн UI тохиргоо, олон хэл дээрх localized menu
 * зэрэг контентыг удирдах үндсэн үйлдлүүдийг хариуцна.
 *
 * Үндсэн боломжууд:
 *   - Хэрэглэгчийн UI тохиргооны modal харуулах
 *   - Dashboard менюг харах, үүсгэх, засах, устгах
 *   - LocalizedModel-ын бүтэцтэй меню дээр олон хэлтэй контент удирдах
 *   - RBAC эрх дээр тулгуурлан зөвшөөрөл шалгах
 *   - Бүх үйлдлийг Logger (log) ашиглан протоколд бүртгэх
 *
 * @package Raptor\Template
 */
class TemplateController extends \Raptor\Controller
{
    use DashboardTrait;

    /**
     * Хэрэглэгчийн DASHBOARD UI тохиргооны modal-ийг рендерлэнэ.
     *
     * @return void
     */
    public function userOption()
    {
        $this->template(__DIR__ . '/user-option-modal.html')->render();
    }

    /**
     * Цэсний (menu) жагсаалтыг харах Dashboard хуудас.
     *
     * Шалгах зүйлс:
     *   - Хэрэглэгч system_coder role-тэй эсэх
     *   - raptor_menu хүснэгтээс бүх идэвхтэй menu-г авах
     *   - created_by / updated_by -> хэрэглэгчийн нэр, имэйл болгон хөрвүүлэх
     *   - Байгууллагын alias-уудыг цуглуулах (common + active organizations)
     *   - RBAC permissions жагсаалт үүсгэх (alias_name форматтай)
     *
     * @return void
     */
    public function manageMenu()
    {
        try {
            if (!$this->isUser('system_coder')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            // Меню жагсаалт
            $model = new MenuModel($this->pdo);
            $menu = $model->getRows(['ORDER BY' => 'p.position']);

            // Хэрэглэгчдийн нэр, имэйл илүү ойлгомжтой болгох
            $users = $this->retrieveUsersDetail();
            foreach ($menu as &$item) {
                if (isset($users[$item['created_by']])) {
                    $item['created_by'] = $users[$item['created_by']];
                }
                if (isset($users[$item['updated_by']])) {
                    $item['updated_by'] = $users[$item['updated_by']];
                }
            }

            // Байгууллагын alias жагсаалт
            $aliases = ['common'];
            // organization хүснэгтийн нэрийг OrganizationModel::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
            $org_table = (new OrganizationModel($this->pdo))->getName();
            $alias_results = $this->query(
                "SELECT alias FROM $org_table WHERE alias!='common' AND is_active=1 GROUP BY alias"
            )->fetchAll();
            foreach ($alias_results as $row) {
                $aliases[] = $row['alias'];
            }

            // Permission жагсаалт
            $permissions = [];
            // permissions хүснэгтийн нэрийг Permissions::getName() ашиглан динамикаар авна. Ирээдүйд refactor хийхэд бэлэн байна.
            $permissions_table = (new Permissions($this->pdo))->getName();
            $concat_expr = ($this->getDriverName() == Constants::DRIVER_PGSQL)
                ? "alias || '_' || name"
                : "CONCAT(alias, '_', name)";
            $permission_results = $this->query(
                "SELECT $concat_expr as permission FROM $permissions_table"
            )->fetchAll();
            foreach ($permission_results as $row) {
                $permissions[] = $row['permission'];
            }

            // Dashboard руу дамжуулах
            $this->dashboardTemplate(
                __DIR__ . '/manage-menu.html',
                ['menu' => $menu, 'aliases' => $aliases, 'permissions' => $permissions]
            )->render();
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            // Logging
            $context = ['action' => 'template-menu-manage'];
            if (isset($err)) {
                $this->log(
                    'dashboard',
                    LogLevel::ERROR,
                    'Цэсний жагсаалтыг нээх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо',
                    $context + ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]]
                );
            } else {
                $this->log(
                    'dashboard',
                    LogLevel::NOTICE,
                    'Цэсний жагсаалтыг үзэж байна',
                    $context + ['aliases' => $aliases, 'permissions' => $permissions, 'menu' => $menu]
                );
            }
        }
    }

    /**
     * Шинэ меню үүсгэх.
     *
     * Payload бүтэц:
     *    - payload[field] = value       (Үндсэн menu баганууд)
     *    - content[lang][title] = value (Localized багана)
     *
     * @return void
     */
    public function manageMenuInsert()
    {
        try {
            if (!$this->isUser('system_coder')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $payload = [];
            $content = [];
            $parsedBody = $this->getParsedBody();
            // Payload болон Контентыг салгах
            foreach ($parsedBody as $index => $value) {
                if (\is_array($value)) {
                    foreach ($value as $key => $value) {
                        $content[$key][$index] = $value;
                    }
                } else {
                    $payload[$index] = $value;
                }
            }

            if (empty($payload) || empty($content)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }

            // Мэдээллийн сан руу бичих
            $model = new MenuModel($this->pdo);
            $record = $model->insert(
                $payload + ['created_by' => $this->getUserId()],
                $content
            );
            if (empty($record)) {
                throw new \Exception($this->text('record-insert-error'));
            }

            $this->invalidateCache('menu.{code}');
            $this->respondJSON([
                'status' => 'success',
                'message' => $this->text('record-insert-success')
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        } finally {
            $context = ['action' => 'template-menu-create'];
            if (isset($err)) {
                $this->log(
                    'dashboard',
                    LogLevel::ERROR,
                    'Цэс үүсгэх үйлдлийг гүйцэтгэх үед алдаа гарч зогслоо',
                    $context + ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]]
                );
            } else {
                $this->log(
                    'dashboard',
                    LogLevel::INFO,
                    'Цэс үүсгэх үйлдлийг амжилттай гүйцэтгэлээ',
                    $context + ['record' => $record]
                );
            }
        }
    }

    /**
     * Цэсний мэдээллийг шинэчлэх.
     *
     * Шалгалт:
     *   - id заавал байх
     *   - is_visible -> checkbox -> 1/0
     *   - Localized контент өөрчлөгдсөн эсэхийг шалгана
     *
     * @return void
     */
    public function manageMenuUpdate()
    {
        try {
            if (!$this->isUser('system_coder')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $parsedBody = $this->getParsedBody();
            if (empty($parsedBody)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            if (!isset($parsedBody['id'])
                || !\filter_var($parsedBody['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = (int) $parsedBody['id'];
            unset($parsedBody['id']);

            // Record-н хуучин мэдээллийг авах
            $model = new MenuModel($this->pdo);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            // Шинэ data-г payload/content болгон салгах
            $payload = [];
            $content = [];
            $updates = [];
            foreach ($parsedBody as $index => $value) {
                if (\is_array($value)) {
                    foreach ($value as $key => $value) {
                        $content[$key][$index] = $value;

                        if ($record['localized'][$key][$index] != $value) {
                            $updates[] = "{$key}_{$index}";
                        }
                    }
                } else {
                    $payload[$index] = $value;

                    if ($record[$index] != $value) {
                        $updates[] = $index;
                    }
                }
            }

            if (empty($updates)) {
                throw new \InvalidArgumentException('No update!');
            }

            $updated = $model->updateById(
                $id,
                $payload + ['updated_by' => $this->getUserId()],
                $content
            );
            if (empty($updated)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            $this->invalidateCache('menu.{code}');
            $this->respondJSON([
                'type' => 'primary',
                'status' => 'success',
                'message' => $this->text('record-update-success')
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        } finally {
            $context = ['action' => 'template-menu-update'];
            if (isset($err)) {
                $this->log(
                    'dashboard',
                    LogLevel::ERROR,
                    'Цэсний мэдээллийг шинэчлэх үед алдаа гарлаа',
                    $context + ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]]
                );
            } else {
                $this->log(
                    'dashboard',
                    LogLevel::INFO,
                    '{record.id} дугаартай цэсний мэдээллийг амжилттай шинэчлэлээ',
                    $context + ['updates' => $updates, 'record' => $updated]
                );
            }
        }
    }

    /**
     * Цэсийг устгах.
     *
     * Анхаарах зүйл:
     *   - system_coder role шаардлагатай
     *   - Идэвхтэй цэс дээр л устгах боломжтой
     *
     * @return void
     */
    public function manageMenuDelete()
    {
        try {
            if (!$this->isUser('system_coder')) {
                throw new \Exception(
                    'No permission for an action [delete]!',
                    401
                );
            }

            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = (int) $payload['id'];

            $model = new MenuModel($this->pdo);
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            $model->deleteById($id);
            (new \Raptor\Trash\TrashModel($this->pdo))->store(
                'dashboard', $model->getName(), $id, $record, $this->getUserId()
            );

            $this->invalidateCache('menu.{code}');
            $this->respondJSON([
                'status'  => 'success',
                'title'   => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status'  => 'error',
                'title'   => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        } finally {
            $context = ['action' => 'template-menu-delete'];
            if (isset($err)) {
                $this->log(
                    'dashboard',
                    LogLevel::ERROR,
                    'Цэс устгах үед алдаа гарлаа',
                    $context + ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]]
                );
            } else {
                $this->log(
                    'dashboard',
                    LogLevel::ALERT,
                    '[{server_request.body.caption}] цэсийг [{server_request.body.reason}] шалтгаанаар устгалаа',
                    $context + ['record' => $record]
                );
            }
        }
    }
}
