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
 *   - Захиалгыг устгах (delete)
 *   - Статус өөрчлөгдсөн тухай имэйл илгээх
 *   - Discord мэдэгдэл илгээх
 *   зэрэг үйлдлүүдийг гүйцэтгэнэ.
 *
 * Боломжит статусууд:
 *   new -> processing -> confirmed -> shipped -> completed
 *                                             -> cancelled
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
            "SELECT DISTINCT status FROM $table"
        )->fetchAll();
        $filters['status']['title'] = $this->text('status');
        foreach ($status_result as $row) {
            $filters['status']['values'][$row['status']] = $row['status'];
        }
        $codes_result = $this->query(
            "SELECT DISTINCT code FROM $table"
        )->fetchAll();
        $languages = $this->getLanguages();
        $filters['code']['title'] = $this->text('language');
        foreach ($codes_result as $row) {
            $filters['code']['values'][$row['code']] = "{$languages[$row['code']]['title']} [{$row['code']}]";
        }
        $settings = $this->getAttribute('settings', []);
        $dashboard = $this->dashboardTemplate(__DIR__ . '/orders-index.html', [
            'filters' => $filters,
            'order_email_notify' => !empty($_ENV['RAPTOR_ORDER_EMAIL_TO'] ?? ''),
            'notify_email' => $_ENV['RAPTOR_ORDER_EMAIL_TO'] ?? '',
            'settings_email' => $settings['email'] ?? ''
        ]);
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
            $conditions = [];
            $allowed = ['status', 'code'];
            foreach (\array_keys($params) as $name) {
                if (\in_array($name, $allowed)) {
                    $conditions[] = "$name=:$name";
                } else {
                    unset($params[$name]);
                }
            }
            $where = !empty($conditions) ? 'WHERE ' . \implode(' AND ', $conditions) : '';
            $table = (new ProductOrdersModel($this->pdo))->getName();
            $select_orders =
                'SELECT id, product_id, product_title, customer_name, customer_email, customer_phone, ' .
                'quantity, status, code, date(created_at) as created_date ' .
                "FROM $table $where ORDER BY created_at desc";
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
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'));
            }
            $dashboard = $this->dashboardTemplate(
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
                $message = '{record.id} ({record.customer_name}) дугаартай захиалгыг үзэж байна';
                $context += ['record' => $record];
            }
            $this->log('products_orders', $level, $message, $context);
        }
    }

    /**
     * Захиалгын статусыг хэсэгчлэн шинэчлэх.
     *
     * PATCH /dashboard/orders/{id}/status
     * Body: { "status": "processing" }
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
                'id' => $id
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

            $this->dispatch(new \Raptor\Notification\OrderEvent(
                'status_changed', $id, $record['customer_name'] ?? '', '', '', '', 0,
                $record['status'] ?? '', $payload['status']
            ));

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
                $message = '{record_id} ({name}) дугаартай захиалгын статусыг амжилттай шинэчлэлээ';
                $context += ['old_status' => $record['status'], 'new_status' => $payload['status'], 'name' => $record['customer_name'], 'record' => $updated];
            }
            $this->log('products_orders', $level, $message, $context);
        }
    }

    /**
     * Захиалгыг устгах.
     *
     * Permission: system_product_delete
     *
     * @return void JSON response буцаана
     */
    public function delete()
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
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'), 404);
            }
            $model->deleteById($id);
            (new \Raptor\Trash\TrashModel($this->pdo))->store(
                'products_orders', $model->getName(), $id, $record, $this->getUserId()
            );
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
            $context = ['action' => 'delete'];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = 'Захиалгыг устгах үйлдлийг гүйцэтгэх явцад алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::ALERT;
                $message = '{record_id} ({name}) дугаартай захиалгыг устгалаа';
                $context += ['record_id' => $id, 'name' => $record['customer_name'] ?? ''];
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
