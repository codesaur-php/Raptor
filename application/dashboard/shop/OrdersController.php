<?php

namespace Dashboard\Shop;

use Psr\Log\LogLevel;

use codesaur\Template\MemoryTemplate;

/**
 * Class OrdersController
 * ---------------------------------------------------------------
 * Захиалга (Orders) удирдах controller.
 *
 * Энэ controller нь:
 *   - Захиалгын жагсаалт харуулах (index, list)
 *   - Захиалгын дэлгэрэнгүй мэдээлэл харуулах (view)
 *   - Захиалгын статус шинэчлэх (updateStatus)
 *   - Захиалгыг идэвхгүй болгох (deactivate)
 *   - Статус өөрчлөгдсөн тухай имэйл илгээх
 *   - Discord мэдэгдэл илгээх
 *   зэрэг үйлдлүүдийг гүйцэтгэнэ.
 *
 * Боломжит статусууд:
 *   new -> processing -> confirmed -> shipped -> completed
 *                                            -> cancelled
 *
 * @package Dashboard\Shop
 */
class OrdersController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Захиалгын жагсаалтын dashboard хуудсыг харуулах.
     *
     * Permission: system_product_index
     *
     * @return void
     */
    public function index()
    {
        if (!$this->isUserCan('system_product_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        $filters = [];
        $table = (new ProductOrdersModel($this->pdo))->getName();
        $status_result = $this->query(
            "SELECT DISTINCT status FROM $table WHERE is_active=1"
        )->fetchAll();
        $filters['status']['title'] = $this->text('status');
        foreach ($status_result as $row) {
            $filters['status']['values'][$row['status']] = $row['status'];
        }
        $codes_result = $this->query(
            "SELECT DISTINCT code FROM $table WHERE is_active=1"
        )->fetchAll();
        $languages = $this->getLanguages();
        $filters['code']['title'] = $this->text('language');
        foreach ($codes_result as $row) {
            $filters['code']['values'][$row['code']] = "{$languages[$row['code']]['title']} [{$row['code']}]";
        }
        $dashboard = $this->twigDashboard(__DIR__ . '/orders-index.html', ['filters' => $filters]);
        $dashboard->set('title', $this->text('orders'));
        $dashboard->render();

        $this->log('products_orders', LogLevel::NOTICE, 'Захиалгын жагсаалтыг үзэж байна', ['action' => 'index']);
    }

    /**
     * Захиалгын жагсаалтыг JSON хэлбэрээр буцаах.
     *
     * Permission: system_product_index
     *
     * @return void JSON response буцаана
     */
    public function list()
    {
        try {
            if (!$this->isUserCan('system_product_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $params = $this->getQueryParams();
            if (!isset($params['is_active'])) {
                $params['is_active'] = 1;
            }
            $conditions = [];
            $allowed = ['status', 'code', 'is_active'];
            foreach (\array_keys($params) as $name) {
                if (\in_array($name, $allowed)) {
                    $conditions[] = "$name=:$name";
                } else {
                    unset($params[$name]);
                }
            }
            $where = \implode(' AND ', $conditions);
            $table = (new ProductOrdersModel($this->pdo))->getName();
            $select_orders =
                'SELECT id, product_id, product_title, customer_name, customer_email, customer_phone, ' .
                'quantity, status, code, date(created_at) as created_date ' .
                "FROM $table WHERE $where ORDER BY created_at desc";
            $orders_stmt = $this->prepare($select_orders);
            foreach ($params as $name => $value) {
                $orders_stmt->bindValue(":$name", $value);
            }
            $orders = $orders_stmt->execute() ? $orders_stmt->fetchAll() : [];

            $this->respondJSON([
                'status' => 'success',
                'list' => $orders
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        }
    }

    /**
     * Захиалгын дэлгэрэнгүй мэдээллийг dashboard-д харуулах.
     *
     * Permission: system_product_index
     *
     * @param int $id Үзэх захиалгын ID
     * @return void
     */
    public function view(int $id)
    {
        try {
            $model = new ProductOrdersModel($this->pdo);
            $table = $model->getName();
            if (!$this->isUserCan('system_product_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $dashboard = $this->twigDashboard(
                __DIR__ . '/orders-view.html',
                ['table' => $table, 'record' => $record]
            );
            $dashboard->set('title', $this->text('view-record') . ' | Orders');
            $dashboard->render();
        } catch (\Throwable $err) {
            $this->dashboardProhibited($err->getMessage(), $err->getCode())->render();
        } finally {
            $context = ['action' => 'view', 'record_id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай захиалгыг нээх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::NOTICE;
                $message = '{record.id} дугаартай захиалгыг үзэж байна';
                $context += ['record' => $record];
            }
            $this->log('products_orders', $level, $message, $context);
        }
    }

    /**
     * Захиалгын статусыг шинэчлэх.
     *
     * Боломжит статусууд: new, processing, confirmed, shipped, completed, cancelled.
     * Статус өөрчлөгдсөн тухай захиалагчид имэйл, Discord мэдэгдэл илгээнэ.
     *
     * Permission: system_product_update
     *
     * @param int $id Захиалгын ID
     * @return void
     */
    public function updateStatus(int $id)
    {
        try {
            if (!$this->isUserCan('system_product_update')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $model = new ProductOrdersModel($this->pdo);
            $record = $model->getRowWhere([
                'id' => $id,
                'is_active' => 1
            ]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            $payload = $this->getParsedBody();
            if (empty($payload['status'])) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }

            $validStatuses = ['new', 'processing', 'confirmed', 'shipped', 'completed', 'cancelled'];
            if (!\in_array($payload['status'], $validStatuses)) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }

            if ($record['status'] === $payload['status']) {
                throw new \InvalidArgumentException('No update!');
            }

            $updated = $model->updateById($id, [
                'status' => $payload['status'],
                'updated_at' => \date('Y-m-d H:i:s'),
                'updated_by' => $this->getUserId()
            ]);
            if (empty($updated)) {
                throw new \Exception($this->text('no-record-selected'));
            }

            $this->sendStatusNotification($record, $payload['status']);

            $adminName = \trim(($this->getUser()->profile['first_name'] ?? '') . ' ' . ($this->getUser()->profile['last_name'] ?? ''));
            $appUrl = \rtrim((string)$this->getRequest()->getUri()->withPath($this->getScriptPath()), '/') . '/dashboard';
            $this->getService('discord')?->orderStatusChanged(
                $id, $record['customer_name'], $record['status'], $payload['status'], $adminName, $appUrl
            );

            $this->respondJSON([
                'status' => 'success',
                'type' => 'primary',
                'message' => $this->text('record-update-success')
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        } finally {
            $context = ['action' => 'update-status', 'record_id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай захиалгын статус шинэчлэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::INFO;
                $message = '{record_id} дугаартай захиалгын статусыг амжилттай шинэчлэлээ';
                $context += ['old_status' => $record['status'], 'new_status' => $payload['status'], 'record' => $updated];
            }
            $this->log('products_orders', $level, $message, $context);
        }
    }

    /**
     * Захиалгыг идэвхгүй болгох (soft delete).
     *
     * Permission: system_product_delete
     *
     * @return void JSON response буцаана
     */
    public function deactivate()
    {
        try {
            if (!$this->isUserCan('system_product_delete')) {
                throw new \Exception('No permission for an action [delete]!', 401);
            }

            $model = new ProductOrdersModel($this->pdo);
            $payload = $this->getParsedBody();
            if (!isset($payload['id'])
                || !\filter_var($payload['id'], \FILTER_VALIDATE_INT)
            ) {
                throw new \InvalidArgumentException($this->text('invalid-request'), 400);
            }
            $id = \filter_var($payload['id'], \FILTER_VALIDATE_INT);
            $deactivated = $model->deactivateById(
                $id,
                [
                    'updated_by' => $this->getUserId(),
                    'updated_at' => \date('Y-m-d H:i:s')
                ]
            );
            if (!$deactivated) {
                throw new \Exception($this->text('no-record-selected'));
            }
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
            $context = ['action' => 'deactivate'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Захиалгыг идэвхгүй болгох үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = '{record_id} дугаартай захиалгыг идэвхгүй болголоо';
                $context += ['record_id' => $id];
            }
            $this->log('products_orders', $level, $message, $context);
        }
    }

    /**
     * Захиалгын төлөв өөрчлөгдсөн тухай захиалагчид имэйл илгээх.
     *
     * Reference template service-ээс 'order-status-update' template-г
     * тухайн хэл дээр хайж, MemoryTemplate ашиглан рендерлээд
     * mailer service-ээр захиалагчид илгээнэ.
     *
     * @param array  $order     Захиалгын бичлэг
     * @param string $newStatus Шинэ статус
     * @return void
     */
    private function sendStatusNotification(array $order, string $newStatus)
    {
        try {
            $mailer = $this->getService('mailer');
            if (empty($mailer)) {
                return;
            }

            $code = $order['code'] ?: 'mn';
            $templateService = $this->getService('template_service');
            $template = $templateService->getByKeyword('order-status-update', $code);
            if (empty($template)) {
                return;
            }

            $statusLabels = [
                'new'        => $code === 'mn' ? 'Шинэ' : 'New',
                'processing' => $code === 'mn' ? 'Боловсруулж байна' : 'Processing',
                'confirmed'  => $code === 'mn' ? 'Баталгаажсан' : 'Confirmed',
                'shipped'    => $code === 'mn' ? 'Илгээгдсэн' : 'Shipped',
                'completed'  => $code === 'mn' ? 'Дууссан' : 'Completed',
                'cancelled'  => $code === 'mn' ? 'Цуцлагдсан' : 'Cancelled'
            ];
            $statusText = $statusLabels[$newStatus] ?? $newStatus;

            $subjectTemplate = new MemoryTemplate();
            $subjectTemplate->source($template['title']);
            $subjectTemplate->set('order_id', $order['id']);
            $subjectTemplate->set('status', $statusText);
            $subject = $subjectTemplate->output();

            $bodyTemplate = new MemoryTemplate();
            $bodyTemplate->source($template['content']);
            $bodyTemplate->set('order_id', $order['id']);
            $bodyTemplate->set('customer_name', $order['customer_name']);
            $bodyTemplate->set('product_title', $order['product_title']);
            $bodyTemplate->set('status', $statusText);
            $body = $bodyTemplate->output();

            $mailer->mail($order['customer_email'], $order['customer_name'], $subject, $body)->send();
        } catch (\Throwable $e) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log("OrderStatusEmail: {$e->getMessage()}");
            }
        }
    }
}
