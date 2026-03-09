<?php

namespace Web\Home;

use Psr\Log\LogLevel;

use Web\Template\TemplateController;

use Dashboard\Shop\ProductsModel;
use Dashboard\Shop\OrdersModel;
use Raptor\Content\FilesModel;
use codesaur\Template\MemoryTemplate;

class ShopController extends TemplateController
{
    public function products()
    {
        $code = $this->getLanguageCode();
        $table = (new ProductsModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT id, title, slug, description, photo, price, sale_price
             FROM $table
             WHERE is_active=1 AND published=1 AND code=:code
             ORDER BY published_at DESC"
        );
        $products = $stmt->execute([':code' => $code]) ? $stmt->fetchAll() : [];

        $template = $this->template(__DIR__ . '/products.html', ['products' => $products]);
        $template->set('record_title', $this->text('products'));
        $template->render();

        $this->log('web', LogLevel::NOTICE, '[{server_request.code}] Бүтээгдэхүүний жагсаалтыг уншиж байна', ['action' => 'products']);
    }

    public function productById(int $id)
    {
        $model = new ProductsModel($this->pdo);
        $table = $model->getName();
        $stmt = $this->prepare("SELECT slug FROM $table WHERE id=:id AND is_active=1");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if (empty($row)) {
            throw new \Error('Бүтээгдэхүүн олдсонгүй', 404);
        }
        return $this->product($row['slug']);
    }

    public function product(string $slug)
    {
        $model = new ProductsModel($this->pdo);
        $table = $model->getName();
        $record = $model->getRowWhere([
            'slug' => $slug,
            'is_active' => 1
        ]);
        if (empty($record)) {
            throw new \Error('Бүтээгдэхүүн олдсонгүй', 404);
        }

        $id = $record['id'];

        // Файлуудыг татах
        $files = new FilesModel($this->pdo);
        $files->setTable($table);
        $record['files'] = $files->getRows([
            'WHERE' => "record_id=$id AND is_active=1"
        ]);

        $template = $this->template(__DIR__ . '/product.html', $record);
        $template->set('record_code', $record['code'] ?? '');
        $template->set('record_title', $record['title'] ?? '');
        $template->set('record_description', $record['description'] ?? '');
        $template->set('record_photo', $record['photo'] ?? '');
        $template->render();

        // Read count
        $read_count = ($record['read_count'] ?? 0) + 1;
        $this->exec("UPDATE $table SET read_count=$read_count WHERE id=$id");

        $this->log(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code} : /product/{slug}] {title} - бүтээгдэхүүнийг уншиж байна',
            ['action' => 'product', 'id' => $id, 'slug' => $slug, 'title' => $record['title']]
        );
    }

    public function order()
    {
        $code = $this->getLanguageCode();
        $vars = [];

        $productId = $this->getQueryParams()['product_id'] ?? null;
        if ($productId) {
            $model = new ProductsModel($this->pdo);
            $table = $model->getName();
            $stmt = $this->prepare(
                "SELECT id, title, photo, price FROM $table WHERE id=:id AND is_active=1 AND published=1"
            );
            $stmt->bindValue(':id', (int)$productId, \PDO::PARAM_INT);
            $stmt->execute();
            $product = $stmt->fetch();
            if ($product) {
                $vars['product'] = $product;
            }
        }

        // Spam хамгаалалтын timestamp + token
        $ts = \time();
        $secret = $_ENV['RAPTOR_JWT_SECRET'] ?? 'raptor-form-secret';
        $vars['spam_ts'] = $ts;
        $vars['spam_token'] = \hash_hmac('sha256', "order-form-$ts", $secret);

        $template = $this->template(__DIR__ . '/order.html', $vars);
        $template->set('record_title', $this->text('order'));
        $template->render();
    }

    public function orderSubmit()
    {
        $payload = $this->getParsedBody();
        $code = $this->getLanguageCode();

        // --- Spam хамгаалалт ---
        // 1) Honeypot: бот бөглөсөн бол хаях
        if (!empty($payload['website'])) {
            throw new \Error('Invalid request', 400);
        }
        // 2) Timestamp + HMAC token: хуурамч/хугацаа дууссан form хаях
        $ts = (int)($payload['_ts'] ?? 0);
        $token = $payload['_token'] ?? '';
        $secret = $_ENV['RAPTOR_JWT_SECRET'] ?? 'raptor-form-secret';
        $expected = \hash_hmac('sha256', "order-form-$ts", $secret);
        if (!\hash_equals($expected, $token)) {
            throw new \Error('Invalid request', 403);
        }
        // 3) 3 секундээс хурдан бөглөсөн бол бот гэж үзэх
        $elapsed = \time() - $ts;
        if ($elapsed < 3) {
            throw new \Error('Invalid request', 429);
        }
        // 4) 1 цагаас хэтэрсэн form хүчингүй
        if ($elapsed > 3600) {
            throw new \Error(
                $code === 'mn' ? 'Формын хугацаа дууссан. Дахин оролдоно уу.' : 'Form expired. Please try again.',
                400
            );
        }
        // 5) Session rate limit: 10 секундэд 1-ээс илүү захиалга хаах
        $now = \time();
        $lastOrder = $_SESSION['_last_order_at'] ?? 0;
        if ($now - $lastOrder < 10) {
            throw new \Error(
                $code === 'mn' ? 'Хэт олон хүсэлт. Түр хүлээнэ үү.' : 'Too many requests. Please wait.',
                429
            );
        }

        if (empty($payload['customer_name']) || empty($payload['customer_email'])) {
            throw new \Error(
                $code === 'mn' ? 'Нэр болон имэйл хаяг шаардлагатай' : 'Name and email are required',
                400
            );
        }

        $model = new OrdersModel($this->pdo);
        $record = $model->insert([
            'product_id' => !empty($payload['product_id']) ? (int)$payload['product_id'] : null,
            'product_title' => $payload['product_title'] ?? '',
            'customer_name' => $payload['customer_name'],
            'customer_email' => $payload['customer_email'],
            'customer_phone' => $payload['customer_phone'] ?? '',
            'message' => $payload['message'] ?? '',
            'quantity' => \max(1, (int)($payload['quantity'] ?? 1)),
            'code' => $code,
            'status' => 'new'
        ]);

        if (!isset($record['id'])) {
            throw new \Error(
                $code === 'mn' ? 'Захиалга үүсгэхэд алдаа гарлаа' : 'Failed to create order',
                500
            );
        }

        $_SESSION['_last_order_at'] = \time();

        $this->sendOrderConfirmation(
            (int)$record['id'],
            $payload['customer_name'],
            $payload['customer_email'],
            $payload['product_title'] ?? '',
            \max(1, (int)($payload['quantity'] ?? 1)),
            $code
        );

        $this->getService('discord')?->newOrder(
            (int)$record['id'],
            $payload['customer_name'],
            $payload['customer_email'],
            $payload['product_title'] ?? '',
            \max(1, (int)($payload['quantity'] ?? 1))
        );

        $template = $this->template(__DIR__ . '/order-success.html', [
            'order_id' => $record['id'],
            'customer_name' => $payload['customer_name'],
            'product_title' => $payload['product_title'] ?? ''
        ]);
        $template->set('record_title', $code === 'mn' ? 'Захиалга амжилттай' : 'Order Success');
        $template->render();

        $this->log(
            (new OrdersModel($this->pdo))->getName(),
            LogLevel::INFO,
            '{auth_user.username} шинэ захиалга илгээлээ',
            [
                'action' => 'order',
                'record_id' => $record['id'],
                'product_title' => $payload['product_title'] ?? '',
                'auth_user' => [
                    'username'   => $payload['customer_name'],
                    'email'      => $payload['customer_email'],
                    'phone'      => $payload['customer_phone'] ?? '',
                    'first_name' => $payload['customer_name'],
                    'last_name'  => ''
                ]
            ]
        );
    }

    /**
     * Захиалга амжилттай үүссэн тухай имэйл илгээх.
     */
    private function sendOrderConfirmation(
        int $orderId,
        string $customerName,
        string $customerEmail,
        string $productTitle,
        int $quantity,
        string $code
    ) {
        try {
            $mailer = $this->getService('mailer');
            if (empty($mailer)) {
                return;
            }

            $templateService = $this->getService('template_service');
            $template = $templateService->getByKeyword('order-confirmation', $code);
            if (empty($template)) {
                return;
            }

            $subjectTemplate = new MemoryTemplate();
            $subjectTemplate->source($template['title']);
            $subjectTemplate->set('order_id', $orderId);
            $subject = $subjectTemplate->output();

            $bodyTemplate = new MemoryTemplate();
            $bodyTemplate->source($template['content']);
            $bodyTemplate->set('order_id', $orderId);
            $bodyTemplate->set('customer_name', $customerName);
            $bodyTemplate->set('product_title', $productTitle);
            $bodyTemplate->set('quantity', $quantity);
            $body = $bodyTemplate->output();

            $mailer->mail($customerEmail, $customerName, $subject, $body)->send();
        } catch (\Throwable $e) {
            \error_log("OrderConfirmationEmail: {$e->getMessage()}");
        }
    }
}
