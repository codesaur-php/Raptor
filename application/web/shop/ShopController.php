<?php

namespace Web\Shop;

use Psr\Log\LogLevel;

use codesaur\Template\MemoryTemplate;

use Raptor\Content\FilesModel;

use Dashboard\Shop\ProductsModel;
use Dashboard\Shop\ProductOrdersModel;
use Dashboard\Shop\ReviewsModel;

use Web\Template\TemplateController;

/**
 * Class ShopController
 * ---------------------------------------------------------------
 * Вэб сайтын дэлгүүр (Shop) модулийн контроллер.
 *
 * Энэ контроллер нь:
 *   - Бүтээгдэхүүний жагсаалт харуулах (products)
 *   - Бүтээгдэхүүнийг slug эсвэл ID-аар харуулах (review, rating мэдээлэлтэй)
 *   - Захиалгын форм харуулах (order)
 *   - Захиалга илгээх (orderSubmit) - spam хамгаалалттай
 *   - Бүтээгдэхүүнд үнэлгээ илгээх (reviewSubmit) - spam хамгаалалттай
 *   - Захиалга амжилттай болсон тухай имэйл илгээх
 *
 * ---------------------------------------------------------------
 * Spam хамгаалалтын механизм (orderSubmit)
 * ---------------------------------------------------------------
 *   1) Honeypot талбар - бот бөглөвөл хаяна
 *   2) HMAC token - хуурамч form илрүүлэх
 *   3) Хугацааны шалгалт - 3 секундээс хурдан бөглөвөл бот
 *   4) 1 цагаас хэтэрсэн form хүчингүй
 *   5) Session rate limit - 10 секундэд 1 захиалга
 *
 * @package Web\Shop
 */
class ShopController extends TemplateController
{
    use \Raptor\SpamProtectionTrait;
    /**
     * Бүтээгдэхүүний жагсаалтыг харуулах.
     *
     * Сонгосон хэл дээрх нийтлэгдсэн бүх бүтээгдэхүүнийг
     * огноогоор буурахаар эрэмбэлж харуулна.
     *
     * @return void
     */
    public function products()
    {
        $code = $this->getLanguageCode();
        $table = (new ProductsModel($this->pdo))->getName();
        $reviewsTable = (new ReviewsModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT p.id, p.title, p.slug, p.description, p.photo, p.price, p.sale_price,
                    rv.avg_rating, rv.review_count
             FROM $table p
             LEFT JOIN (
                 SELECT product_id, AVG(rating) as avg_rating, COUNT(*) as review_count
                 FROM $reviewsTable WHERE is_active=1 GROUP BY product_id
             ) rv ON rv.product_id=p.id
             WHERE p.is_active=1 AND p.published=1 AND p.code=:code
             ORDER BY p.published_at DESC"
        );
        $products = $stmt->execute([':code' => $code]) ? $stmt->fetchAll() : [];

        $this->twigWebLayout(__DIR__ . '/products.html', [
            'products' => $products,
            'title' => $this->text('products')
        ])->render();

        $this->log('web', LogLevel::NOTICE, '[{server_request.code}] Бүтээгдэхүүний жагсаалтыг уншиж байна', ['action' => 'products']);
    }

    /**
     * ID-аар бүтээгдэхүүн хайж slug-аар чиглүүлэх.
     *
     * @param int $id Бүтээгдэхүүний ID дугаар
     * @return void
     * @throws \Error Бүтээгдэхүүн олдохгүй бол 404 алдаа шидэнэ
     */
    public function productById(int $id)
    {
        $model = new ProductsModel($this->pdo);
        $table = $model->getName();
        $stmt = $this->prepare("SELECT slug FROM $table WHERE id=:id AND is_active=1");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if (empty($row)) {
            throw new \Exception('Бүтээгдэхүүн олдсонгүй', 404);
        }
        return $this->product($row['slug']);
    }

    /**
     * Slug-аар бүтээгдэхүүнийг харуулах.
     *
     * Бүтээгдэхүүний бүрэн мэдээлэл, хавсаргасан файлуудыг авч,
     * product.html template-ээр рендерлэнэ. Уншсан тоог нэмэгдүүлнэ.
     *
     * @param string $slug Бүтээгдэхүүний slug
     * @return void
     * @throws \Error Бүтээгдэхүүн олдохгүй бол 404 алдаа шидэнэ
     */
    public function product(string $slug)
    {
        $model = new ProductsModel($this->pdo);
        $table = $model->getName();
        $record = $model->getRowWhere([
            'slug' => $slug,
            'is_active' => 1
        ]);
        if (empty($record)) {
            throw new \Exception('Бүтээгдэхүүн олдсонгүй', 404);
        }

        $id = $record['id'];

        // Файлуудыг татах
        $files = new FilesModel($this->pdo);
        $files->setTable($table);
        $record['files'] = $files->getRows([
            'WHERE' => "record_id=$id AND is_active=1"
        ]);

        // Үнэлгээнүүдийг татах (review=1 үед)
        if (!empty($record['review'])) {
            $reviewsModel = new ReviewsModel($this->pdo);
            $reviewsTable = $reviewsModel->getName();
            $rstmt = $this->prepare(
                "SELECT id, name, rating, comment, created_at FROM $reviewsTable
                 WHERE product_id=:pid AND is_active=1 ORDER BY created_at DESC"
            );
            $record['reviews'] = $rstmt->execute([':pid' => $id]) ? $rstmt->fetchAll() : [];

            $avgStmt = $this->prepare(
                "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM $reviewsTable
                 WHERE product_id=:pid AND is_active=1"
            );
            $avgStmt->execute([':pid' => $id]);
            $stats = $avgStmt->fetch();
            $record['avg_rating'] = \round((float)($stats['avg_rating'] ?? 0), 1);
            $record['review_count'] = (int)($stats['review_count'] ?? 0);

            $ts = \time();
            $secret = $this->getJwtSecret();
            $record['spam_ts'] = $ts;
            $record['spam_token'] = \hash_hmac('sha256', "review-$id-$ts", $secret);
            $record['turnstile_site_key'] = $this->getTurnstileSiteKey();
        }

        $this->twigWebLayout(__DIR__ . '/product.html', $record)->render();

        // Read count
        $this->exec("UPDATE $table SET read_count=read_count+1 WHERE id=$id");

        $this->log(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code} : /product/{slug}] {title} - бүтээгдэхүүнийг уншиж байна',
            ['action' => 'product', 'id' => $id, 'slug' => $slug, 'title' => $record['title']]
        );
    }

    /**
     * Захиалгын формыг харуулах.
     *
     * Spam хамгаалалтын timestamp болон HMAC token-г бэлтгэж
     * template-д дамжуулна. product_id query parameter-аар
     * бүтээгдэхүүний мэдээллийг урьдчилан дуудна.
     *
     * @return void
     */
    public function order()
    {
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

        $ts = \time();
        $secret = $this->getJwtSecret();
        $vars['spam_ts'] = $ts;
        $vars['spam_token'] = \hash_hmac('sha256', "order-form-$ts", $secret);
        $vars['turnstile_site_key'] = $this->getTurnstileSiteKey();

        $vars['title'] = $this->text('order');
        $this->twigWebLayout(__DIR__ . '/order.html', $vars)->render();

        $context = ['action' => 'order'];
        if (isset($product)) {
            $context['product_id'] = $product['id'];
            $context['title'] = $product['title'];
        }
        $this->log('web', LogLevel::NOTICE, '[{server_request.code}] {title} - бүтээгдэхүүний захиалгын формыг нээж байна', $context);
    }

    /**
     * Захиалга илгээх.
     *
     * Spam хамгаалалтын 5 шатлалтай шалгалт хийсний дараа
     * захиалгыг DB-д хадгалж, имэйл болон Discord мэдэгдэл илгээнэ.
     *
     * @return void
     * @throws \Error Spam илэрвэл эсвэл validation алдаа
     */
    public function orderSubmit()
    {
        $payload = $this->getParsedBody();
        $code = $this->getLanguageCode();

        $this->validateSpamProtection($payload, 'order-form', '_last_order_at', 10, 3);

        if (empty($payload['customer_name']) || empty($payload['customer_email'])) {
            throw new \Exception(
                $code === 'mn' ? 'Нэр болон имэйл хаяг шаардлагатай' : 'Name and email are required',
                400
            );
        }

        $model = new ProductOrdersModel($this->pdo);
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
            throw new \Exception(
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

        $appUrl = \rtrim((string)$this->getRequest()->getUri()->withPath($this->getScriptPath()), '/');
        $this->getService('discord')?->newOrder(
            (int)$record['id'],
            $payload['customer_name'],
            $payload['customer_email'],
            $payload['product_title'] ?? '',
            \max(1, (int)($payload['quantity'] ?? 1)),
            $payload['customer_phone'] ?? '',
            $appUrl
        );

        $this->twigWebLayout(__DIR__ . '/order-success.html', [
            'order_id' => $record['id'],
            'customer_name' => $payload['customer_name'],
            'product_title' => $payload['product_title'] ?? '',
            'title' => $code === 'mn' ? 'Захиалга амжилттай' : 'Order Success'
        ])->render();

        $this->log(
            'products_orders',
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
     * Бүтээгдэхүүнд үнэлгээ илгээх.
     *
     * Spam хамгаалалтын шалгалт хийсний дараа
     * үнэлгээг DB-д хадгалж, Discord мэдэгдэл илгээнэ.
     *
     * @param int $id Бүтээгдэхүүний ID
     * @return void
     */
    public function reviewSubmit(int $id)
    {
        try {
            $parsed = $this->getParsedBody();
            $code = $this->getLanguageCode();

            // Бүтээгдэхүүн байгаа эсэх, comment идэвхтэй эсэх шалгах
            $productsModel = new ProductsModel($this->pdo);
            $product = $productsModel->getRowWhere(['id' => $id, 'is_active' => 1]);
            if (empty($product) || empty($product['review'])) {
                throw new \Exception('Invalid request', 400);
            }

            $this->validateSpamProtection($parsed, "review-$id", '_last_review_at', 10, 3);

            $name = \trim($parsed['name'] ?? '');
            $email = \trim($parsed['email'] ?? '');
            $rating = (int)($parsed['rating'] ?? 0);
            $comment = \trim($parsed['comment'] ?? '');

            if (empty($name)) {
                throw new \InvalidArgumentException($code === 'mn' ? 'Нэрээ оруулна уу' : 'Please enter your name');
            }
            if ($rating < 1 || $rating > 5) {
                throw new \InvalidArgumentException($code === 'mn' ? 'Үнэлгээ сонгоно уу (1-5)' : 'Please select a rating (1-5)');
            }
            if (empty($comment)) {
                throw new \InvalidArgumentException($code === 'mn' ? 'Сэтгэгдлээ бичнэ үү' : 'Please enter your review');
            }
            if (!empty($email) && !\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException($code === 'mn' ? 'Зөв имэйл хаяг оруулна уу' : 'Please enter a valid email address');
            }
            $this->checkLinkSpam($comment);

            $_SESSION['_last_review_at'] = \time();

            $reviewsModel = new ReviewsModel($this->pdo);
            $reviewsModel->insert([
                'product_id' => $id,
                'name' => $name,
                'email' => $email,
                'rating' => $rating,
                'comment' => $comment,
                'created_at' => \date('Y-m-d H:i:s')
            ]);

            // Discord мэдэгдэл
            $appUrl = \rtrim((string)$this->getRequest()->getUri()->withPath($this->getScriptPath()), '/');
            $this->getService('discord')?->contentAction('review', 'insert', $product['title'], $id, $name, $appUrl);

            $this->respondJSON([
                'status' => 'success',
                'message' => $code === 'mn'
                    ? 'Таны үнэлгээ амжилттай нэмэгдлээ!'
                    : 'Your review has been posted successfully!'
            ]);

            $this->log('products', LogLevel::INFO, '{record_id} бүтээгдэхүүнд үнэлгээ бичлээ', [
                'action' => 'review-insert',
                'record_id' => $id,
                'auth_user' => [
                    'username' => $name,
                    'first_name' => $name,
                    'last_name' => '',
                    'phone' => '',
                    'email' => $email
                ]
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode() ?: 500);
        }
    }

    /**
     * JWT нууц түлхүүрийг environment-ээс авах.
     *
     * @return string JWT secret
     * @throws \RuntimeException Environment variable тохируулаагүй бол
     */
    private function getJwtSecret(): string
    {
        $secret = $_ENV['RAPTOR_JWT_SECRET'] ?? '';
        if (empty($secret)) {
            throw new \RuntimeException('RAPTOR_JWT_SECRET environment variable is not set');
        }
        return $secret;
    }

    /**
     * Захиалга амжилттай үүссэн тухай имэйл илгээх.
     *
     * Reference template service-ээс 'order-confirmation' template-г
     * тухайн хэл дээр хайж, MemoryTemplate ашиглан рендерлээд
     * mailer service-ээр захиалагчид илгээнэ.
     *
     * @param int    $orderId       Захиалгын ID
     * @param string $customerName  Захиалагчийн нэр
     * @param string $customerEmail Захиалагчийн имэйл
     * @param string $productTitle  Бүтээгдэхүүний нэр
     * @param int    $quantity      Тоо ширхэг
     * @param string $code          Хэлний код
     * @return void
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
            if (CODESAUR_DEVELOPMENT) {
                \error_log("OrderConfirmationEmail: {$e->getMessage()}");
            }
        }
    }
}
