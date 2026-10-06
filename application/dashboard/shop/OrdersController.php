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
class OrdersController extends \Dashboard\Controller
{
    use \Dashboard\Template\DashboardTrait;

    /**
     * Бүтээгдэхүүний үлдэгдлээс хасагдсан байх статусууд.
     * Захиалга эдгээрийн аль нэгэнд орвол үлдэгдэл хасагдаж,
     * эндээс гарвал (cancelled, new, processing) буцаж нэмэгдэнэ.
     */
    private const STOCK_HOLDING_STATUSES = ['confirmed', 'shipped', 'completed'];

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
            $filters['code']['values'][$row['code']] = $row['code'] === '*'
                ? $this->text('all-languages')
                : ($languages[$row['code']]['title'] ?? $row['code']) . " [{$row['code']}]";
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
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode() ?: 500);
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
            // Сагснаас үүссэн захиалгын мөрүүд. Хоосон бол хуучин нэг бүтээгдэхүүнтэй захиалга
            $items = \json_decode((string)($record['items'] ?? ''), true);
            $dashboard = $this->dashboardTemplate(
                __DIR__ . '/orders-view.html',
                ['table' => $table, 'record' => $record, 'items' => \is_array($items) ? $items : []]
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

            // Үлдэгдэл ба статус нэг transaction-д өөрчлөгдөнө - статус хадгалагдаагүй
            // үед үлдэгдэл хасагдсан хэвээр үлдэх (эсвэл эсрэгээр) боломжгүй
            $this->pdo->beginTransaction();
            try {
                $stockChange = $this->applyStockChange($record, $payload['status']);
                $updated = $model->updateById($id, [
                    'status' => $payload['status'],
                    'stock_reduced' => match (true) {
                        $stockChange < 0 => 1,
                        $stockChange > 0 => 0,
                        default => (int)($record['stock_reduced'] ?? 0)
                    },
                    'updated_at' => \date('Y-m-d H:i:s'),
                    'updated_by' => $this->getUserId()
                ]);
                if (empty($updated)) {
                    throw new \Exception($this->text('no-record-selected'));
                }
                $this->pdo->commit();
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                throw $e;
            }

            $this->sendStatusNotification($record, $payload['status']);

            $this->dispatch(new \Dashboard\Notification\OrderEvent(
                'status_changed', $id, $record['customer_name'] ?? '', '', '', '', 0,
                $record['status'] ?? '', $payload['status']
            ));

            $this->respondJSON([
                'status' => 'success',
                'type' => 'primary',
                'message' => $this->text('record-update-success')
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode() ?: 500);
        } finally {
            $context = ['action' => 'update-status', 'record_id' => $id];
            if (isset($err) && $err instanceof \Throwable) {
                $level = LogLevel::ERROR;
                $message = '{record_id} дугаартай захиалгын статус шинэчлэх үед алдаа гарч зогслоо';
                $context += ['error' => ['code' => $err->getCode(), 'message' => $err->getMessage()]];
            } else {
                $level = LogLevel::INFO;
                $message = '{record_id} ({name}) дугаартай захиалгын статусыг амжилттай шинэчлэлээ';
                $context += ['old_status' => $record['status'], 'new_status' => $payload['status'], 'name' => $record['customer_name'], 'stock_change' => $stockChange, 'record' => $updated];
            }
            $this->log('products_orders', $level, $message, $context);
        }
    }

    /**
     * Статус өөрчлөгдөхөд бүтээгдэхүүний үлдэгдлийг хасах/буцаах.
     *
     * Захиалга STOCK_HOLDING_STATUSES-ийн аль нэгэнд орох үед (баталгаажих)
     * захиалгын бүтээгдэхүүн бүрийн үлдэгдлээс тоо ширхэгийг нь хасна (orderLines). Тэндээс гарах үед (цуцлах, эсвэл new/processing
     * руу буцаах) хассан тоог буцааж нэмнэ. Аль хэдийн хасагдсан эсэхийг
     * захиалгын stock_reduced талбар тэмдэглэдэг тул давхар хасалт гарахгүй.
     * Нөөц хянадаггүй (manage_stock=0) эсвэл устгагдсан бүтээгдэхүүнд хасалт хийхгүй.
     *
     * Transaction дотор дуудагдана (updateStatus).
     *
     * @param array  $order     Захиалгын одоогийн бичлэг
     * @param string $newStatus Шинэ статус
     * @return int Үлдэгдлийн өөрчлөлт: сөрөг = хассан, эерэг = буцаасан, 0 = өөрчлөлтгүй
     * @throws \Exception Үлдэгдэл хүрэлцэхгүй бол (статус хадгалагдахгүй)
     */
    private function applyStockChange(array $order, string $newStatus): int
    {
        $reduced = (int)($order['stock_reduced'] ?? 0) === 1;
        $holds = \in_array($newStatus, self::STOCK_HOLDING_STATUSES, true);
        $lines = $this->orderLines($order);
        if (empty($lines) || $reduced === $holds) {
            return 0;
        }

        $products = (new ProductsModel($this->pdo))->getName();
        $change = 0;
        if ($reduced) {
            // Цуцлах / буцаах - хассан тоог буцааж нэмнэ. Баталгаажуулахад зөвхөн нөөц
            // хянадаг бүтээгдэхүүнээс хассан тул буцаахдаа мөн тэдгээрт л нэмнэ
            $stmt = $this->prepare("UPDATE $products SET stock=stock+:qty WHERE id=:id AND manage_stock=1");
            foreach ($lines as $productId => $quantity) {
                $stmt->bindValue(':qty', $quantity, \PDO::PARAM_INT);
                $stmt->bindValue(':id', $productId, \PDO::PARAM_INT);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    $change += $quantity;
                }
            }
            return $change;
        }

        // Баталгаажуулах - мөр бүрийг түгжиж үлдэгдэл шалгаад хасна. Аль нэг
        // бүтээгдэхүүний үлдэгдэл хүрэлцэхгүй бол exception - дуудагч transaction-ийг
        // rollback хийж өмнө хасагдсан мөрүүд ч буцна
        $select = $this->prepare("SELECT title, manage_stock, stock FROM $products WHERE id=:id FOR UPDATE");
        $update = $this->prepare("UPDATE $products SET stock=stock-:qty WHERE id=:id");
        foreach ($lines as $productId => $quantity) {
            $select->bindValue(':id', $productId, \PDO::PARAM_INT);
            $select->execute();
            $product = $select->fetch();
            $select->closeCursor();
            if (empty($product) || (int)$product['manage_stock'] !== 1) {
                continue;
            }
            $stock = (int)$product['stock'];
            if ($stock < $quantity) {
                throw new \Exception($this->text('not-enough-stock') . " - {$product['title']} ($stock < $quantity)", 400);
            }
            $update->bindValue(':qty', $quantity, \PDO::PARAM_INT);
            $update->bindValue(':id', $productId, \PDO::PARAM_INT);
            $update->execute();
            $change -= $quantity;
        }
        return $change;
    }

    /**
     * Захиалгын бүтээгдэхүүн бүрийн тоо ширхэг [product_id => quantity].
     *
     * items JSON (сагснаас үүссэн захиалга) байвал түүнээс, үгүй бол хуучин
     * нэг бүтээгдэхүүнтэй захиалгын product_id/quantity-аас уншина.
     * Устгагдсан бүтээгдэхүүний (product_id хоосон) мөрийг алгасна.
     *
     * @param array $order Захиалгын бичлэг
     * @return array<int,int>
     */
    private function orderLines(array $order): array
    {
        $lines = [];
        $items = \json_decode((string)($order['items'] ?? ''), true);
        if (\is_array($items) && !empty($items)) {
            foreach ($items as $item) {
                $productId = (int)($item['product_id'] ?? 0);
                if ($productId > 0) {
                    $lines[$productId] = ($lines[$productId] ?? 0) + \max(1, (int)($item['quantity'] ?? 1));
                }
            }
            return $lines;
        }
        $productId = (int)($order['product_id'] ?? 0);
        if ($productId > 0) {
            $lines[$productId] = \max(1, (int)($order['quantity'] ?? 1));
        }
        return $lines;
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
            (new \Dashboard\Trash\TrashModel($this->pdo))->store(
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

            // Subject нь энгийн текст тул autoescape унтраана
            $subjectTemplate = new MemoryTemplate();
            $subjectTemplate->setAutoEscape(false);
            $subjectTemplate->source($template['title']);
            $subjectTemplate->set('order_id', $order['id']);
            $subjectTemplate->set('status', $statusText);
            $subject = $subjectTemplate->output();

            // Body нь HTML - утга бүрийг autoescape өөрөө escape хийнэ
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
