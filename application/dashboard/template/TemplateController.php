<?php

namespace Dashboard\Template;

use Psr\Log\LogLevel;

use codesaur\DataObject\Constants;

use Dashboard\Organization\OrganizationModel;
use Dashboard\RBAC\Permissions;

/**
 * Class TemplateController
 *
 * Dashboard-ийн меню, хэрэглэгчийн UI тохиргоо, олон хэл дээрх localized menu
 * зэрэг контентыг удирдах үндсэн үйлдлүүдийг хариуцна.
 *
 * Үндсэн боломжууд:
 *   - Dashboard менюг харах, үүсгэх, засах, устгах
 *   - LocalizedModel-ын бүтэцтэй меню дээр олон хэлтэй контент удирдах
 *   - RBAC эрх дээр тулгуурлан зөвшөөрөл шалгах
 *   - Бүх үйлдлийг Logger (log) ашиглан протоколд бүртгэх
 *
 * @package Dashboard\Template
 */
class TemplateController extends \Dashboard\Controller
{
    use DashboardTrait;

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
            unset($item);

            // Байгууллагын alias жагсаалт
            $aliases = ['common'];
            $org_table = (new OrganizationModel($this->pdo))->getName();
            $alias_results = $this->query(
                "SELECT alias FROM $org_table WHERE alias!='common' AND is_active=1 GROUP BY alias"
            )->fetchAll();
            foreach ($alias_results as $row) {
                $aliases[] = $row['alias'];
            }

            // Permission жагсаалт.
            // 'system_coder' нь role боловч Permissions::RESERVED нэр тул бодит
            // permission хэзээ ч үүсэхгүй - isUserCan() зөвхөн coder-т true буцаана.
            // Ингэснээр зөвхөн coder харах цэсийг UI-аас тохируулах боломжтой.
            //
            // Coupling (English): 'system_coder' is a role, offered here so coder-only
            // menus can be set from the UI. Safe only while 'system_coder' stays in
            // Permissions::RESERVED (see MenuSeed coder section comment).
            $permissions = ['system_coder'];
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

            $this->dashboardTemplate(
                __DIR__ . '/manage-menu.html',
                [
                    'menu' => $menu,
                    'menu_groups' => $this->groupMenuTree($menu),
                    'aliases' => $aliases,
                    'permissions' => $permissions
                ]
            )->render();
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
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
                    $context
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

            // Дарааллыг гараар оруулахгүй - шинэ цэс бүлгийнхээ төгсгөлд орж,
            // дараа нь чирж байрлуулна (manageMenuSort)
            $model = new MenuModel($this->pdo);
            $payload['position'] = $this->nextMenuPosition($model);
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
                    '{record_id} дугаартай цэс үүсгэх үйлдлийг амжилттай гүйцэтгэлээ',
                    $context + ['record_id' => $record['id'] ?? null, 'payload' => $payload, 'content' => $content]
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
            // position-ийг зөвхөн чирэх (manageMenuSort) өөрчилнө
            unset($parsedBody['id'], $parsedBody['position']);

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

                        if (($record['localized'][$key][$index] ?? null) != $value) {
                            $updates[] = "{$key}_{$index}";
                        }
                    }
                } else {
                    $payload[$index] = $value;

                    if (($record[$index] ?? null) != $value) {
                        $updates[] = $index;
                    }
                }
            }

            if (empty($updates)) {
                throw new \InvalidArgumentException('No update!');
            }

            // Өөр parent руу шилжвэл шинэ бүлгийнхээ төгсгөлд орно
            if (\in_array('parent_id', $updates, true)) {
                $payload['position'] = $this->nextMenuPosition($model);
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
                    '{record_id} дугаартай цэсний мэдээллийг амжилттай шинэчлэлээ',
                    $context + ['record_id' => $id, 'changes' => $this->recordChanges($record, $updated)]
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
            (new \Dashboard\Trash\TrashModel($this->pdo))->store(
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
                    '{record_id} дугаартай [{caption}] цэсийг [{reason}] шалтгаанаар устгалаа',
                    $context + [
                        'record_id' => $id,
                        'caption' => \strip_tags((string) ($payload['caption'] ?? '')),
                        'reason' => (string) ($payload['reason'] ?? '')
                    ]
                );
            }
        }
    }

    /**
     * Цэсний дарааллыг чирж (drag & drop) өөрчилсний дараа хадгалах.
     *
     * Body: {"ids": [...], "parents": {childId: parentId, ...}}
     *   - ids - manage-menu хүснэгтийн бүх мөрийн id, дэлгэцэн дээрх дарааллаар
     *   - parents - child мөр бүрийн (дэлгэцэн дээр) харьяалах parent. Child-ийг
     *     өөр бүлэг рүү чирвэл parent_id нь солигдоно. Зөвхөн child (DB-д
     *     parent_id != 0) мөрийг, зөвхөн үндсэн (parent_id = 0) цэс рүү шилжүүлнэ -
     *     parent-ыг child болгох, child-ыг үндсэн цэс болгох нь чирэхээр
     *     боломжгүй (2 түвшний бүтэц, засах цонхны Parent-аар).
     *
     * Бүх мөрийг дарааллаар нь 1, 2, 3 ... гэж дахин дугаарлана (parent, дараа нь
     * түүний child-ууд, дараагийн parent ...). Sidebar (getUserMenu) нь мөрүүдийг
     * position-оор нэг дор эрэмбэлээд parent_id-аар бүлэглэдэг тул child-ийн
     * position parent-аасаа хойш, дараагийн parent-аас өмнө байх ёстой - бүлэг
     * тус бүрт тусад нь 1-ээс дугаарлавал бүлгийн дараалал эвдэрнэ.
     *
     * Coupling (English): positions are renumbered globally in tree order
     * (parent, its children, next parent ...) because getUserMenu() sorts all
     * rows by position in one query and groups them by parent_id in arrival
     * order. Per-group numbering would let a child arrive before an earlier
     * parent and reorder the sidebar sections.
     *
     * @return void
     */
    public function manageMenuSort()
    {
        try {
            if (!$this->isUser('system_coder')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $ids = $this->getParsedBody()['ids'] ?? null;
            if (!\is_array($ids) || empty($ids)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $order = [];
            foreach ($ids as $id) {
                $id = \filter_var($id, \FILTER_VALIDATE_INT);
                if ($id === false || isset($order[$id])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                $order[$id] = \count($order);
            }

            $table = (new MenuModel($this->pdo))->getName();
            $rows = $this->query("SELECT id, parent_id, position FROM $table")->fetchAll();
            // Өөр цонхонд цэс нэмэгдсэн/устсан бол хуучин дарааллаар бичихгүй
            if (\count($rows) !== \count($order)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 409);
            }
            $current = [];
            foreach ($rows as $row) {
                if (!isset($order[$row['id']])) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 409);
                }
                $current[(int) $row['id']] = $row;
            }

            // Өөр бүлэг рүү чирсэн child-уудын шинэ parent
            $moves = [];
            $parents = $this->getParsedBody()['parents'] ?? [];
            if (!\is_array($parents)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            foreach ($parents as $childId => $parentId) {
                $childId = \filter_var($childId, \FILTER_VALIDATE_INT);
                $parentId = \filter_var($parentId, \FILTER_VALIDATE_INT);
                if ($childId === false || $parentId === false
                    || !isset($current[$childId], $current[$parentId])
                    || (int) $current[$childId]['parent_id'] === 0
                    || (int) $current[$parentId]['parent_id'] !== 0
                ) {
                    throw new \InvalidArgumentException($this->text('invalid-request'), 400);
                }
                if ((int) $current[$childId]['parent_id'] !== $parentId) {
                    $moves[$childId] = ['old' => (int) $current[$childId]['parent_id'], 'new' => $parentId];
                    $current[$childId]['parent_id'] = $parentId;
                }
            }

            $sequence = [];
            foreach ($this->groupMenuTree($current, $order) as $group) {
                if ($group['parent'] !== null) {
                    $sequence[] = (int) $group['parent']['id'];
                }
                foreach ($group['children'] as $child) {
                    $sequence[] = (int) $child['id'];
                }
            }

            $positions = [];
            $stmt = $this->prepare("UPDATE $table SET position=:position WHERE id=:id");
            $this->pdo->beginTransaction();
            if (!empty($moves)) {
                $move = $this->prepare(
                    "UPDATE $table SET parent_id=:parent_id, updated_at=:updated_at, updated_by=:updated_by WHERE id=:id"
                );
                foreach ($moves as $id => $change) {
                    $move->bindValue(':parent_id', $change['new'], \PDO::PARAM_INT);
                    $move->bindValue(':updated_at', \date('Y-m-d H:i:s'));
                    $move->bindValue(':updated_by', $this->getUserId(), \PDO::PARAM_INT);
                    $move->bindValue(':id', $id, \PDO::PARAM_INT);
                    $move->execute();
                }
            }
            foreach ($sequence as $index => $id) {
                $position = $index + 1;
                if ((int) $current[$id]['position'] !== $position) {
                    $stmt->bindValue(':position', $position, \PDO::PARAM_INT);
                    $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
                    $stmt->execute();
                    $positions[$id] = $position;
                }
            }
            $this->pdo->commit();

            $this->invalidateCache('menu.{code}');
            $this->respondJSON([
                'type' => 'primary',
                'status' => 'success',
                'message' => $this->text('record-update-success')
            ]);
        } catch (\Throwable $err) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        } finally {
            $context = ['action' => 'template-menu-sort'];
            if (isset($err)) {
                $this->log(
                    'dashboard',
                    LogLevel::ERROR,
                    'Цэсний дарааллыг өөрчлөх үед алдаа гарлаа',
                    $context + ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]]
                );
            } else {
                $this->log(
                    'dashboard',
                    LogLevel::INFO,
                    'Цэсний дарааллыг амжилттай өөрчиллөө',
                    $context + ['positions' => $positions]
                        + (empty($moves) ? [] : ['parent_changes' => $moves])
                );
            }
        }
    }

    /**
     * Цэсний мөрүүдийг мод бүтцээр бүлэглэх: [['parent' => row|null, 'children' => [...]], ...].
     *
     * Parent-ууд болон бүлэг доторх child-ууд $order (id => эрэмбэ) байвал
     * түүгээр, үгүй бол position-оор эрэмбэлэгдэнэ. Parent нь олдохгүй
     * (orphan) child-ууд parent=null бүлэгт хамгийн сүүлд орно.
     *
     * @param array $rows  id => мөр (id, parent_id, position багана заавал)
     * @param array|null $order id => эрэмбийн дугаар
     * @return array
     */
    private function groupMenuTree(array $rows, ?array $order = null): array
    {
        $rank = fn(array $row) => $order[$row['id']] ?? (int) $row['position'];
        $sort = fn(array $a, array $b) => [$rank($a), (int) $a['id']] <=> [$rank($b), (int) $b['id']];

        $parents = [];
        $children = [];
        foreach ($rows as $row) {
            if ((int) $row['parent_id'] === 0) {
                $parents[(int) $row['id']] = $row;
            } else {
                $children[(int) $row['parent_id']][] = $row;
            }
        }
        \uasort($parents, $sort);

        $groups = [];
        foreach ($parents as $id => $parent) {
            $kids = $children[$id] ?? [];
            \usort($kids, $sort);
            $groups[] = ['parent' => $parent, 'children' => $kids];
            unset($children[$id]);
        }
        if (!empty($children)) {
            $orphans = \array_merge(...\array_values($children));
            \usort($orphans, $sort);
            $groups[] = ['parent' => null, 'children' => $orphans];
        }
        return $groups;
    }

    /**
     * Шинэ (эсвэл parent сольсон) цэсний position: одоогийн хамгийн их + 1.
     * Бүх мөрийн дээд утгаас авдаг тул parent бол хамгийн сүүлд, child бол
     * parent-ынхаа бүлгийн төгсгөлд орно (manageMenuSort-ийн тайлбарыг үз).
     *
     * @param MenuModel $model
     * @return int
     */
    private function nextMenuPosition(MenuModel $model): int
    {
        $max = $this->query("SELECT MAX(position) FROM {$model->getName()}")->fetchColumn();
        return ((int) $max) + 1;
    }
}
