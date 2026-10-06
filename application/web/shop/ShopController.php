<?php

namespace Web\Shop;

use Psr\Log\LogLevel;

use codesaur\Template\Markup;
use codesaur\Template\MemoryTemplate;

use Dashboard\File\FilesModel;
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
 *   - Сагс харуулах, бүтээгдэхүүн нэмэх/засах (cart, cartAdd, cartUpdate)
 *   - Захиалгын форм харуулах (order) - үргэлж сагсны бүх бараагаар
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
    use \Dashboard\SpamProtectionTrait;

    /**
     * Нөөц хянадаг барааны үлдэгдэл энэ тооноос бага буюу тэнцүү бол
     * "Ердөө N үлдлээ", их бол яг тоогүйгээр "Нөөцөд байгаа" гэж харуулна.
     * Төсөлдөө тохируулан шууд засна.
     */
    public const LOW_STOCK_THRESHOLD = 5;

    /**
     * Бүтээгдэхүүний жагсаалтыг харуулах.
     *
     * Сонгосон хэл дээрх (болон бүх хэлний '*') нийтлэгдсэн бүх
     * бүтээгдэхүүнийг огноогоор буурахаар эрэмбэлж харуулна.
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
                    p.manage_stock, p.stock, rv.avg_rating, rv.review_count
             FROM $table p
             LEFT JOIN (
                 SELECT product_id, AVG(rating) as avg_rating, COUNT(*) as review_count
                 FROM $reviewsTable GROUP BY product_id
             ) rv ON rv.product_id=p.id
             WHERE p.published=1 AND p.code IN (:code, '*')
             ORDER BY p.published_at DESC"
        );
        $products = $stmt->execute([':code' => $code]) ? $stmt->fetchAll() : [];

        $this->webTemplate(__DIR__ . '/products.html', [
            'products' => $products,
            'low_stock_threshold' => self::LOW_STOCK_THRESHOLD,
            'title' => $this->text('products')
        ])->render();

        $this->log('web', LogLevel::NOTICE, '[{server_request.code}] Бүтээгдэхүүний жагсаалтыг уншиж байна', ['action' => 'products']);
    }

    /**
     * ID-аар нийтлэгдсэн бүтээгдэхүүн хайж slug URL руу 301 redirect хийх.
     *
     * @param int $id Бүтээгдэхүүний ID дугаар
     * @return void
     * @throws \Error Бүтээгдэхүүн олдохгүй бол 404 алдаа шидэнэ
     */
    public function productById(int $id)
    {
        $model = new ProductsModel($this->pdo);
        $table = $model->getName();
        $stmt = $this->prepare("SELECT slug FROM $table WHERE id=:id AND published=1");
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if (empty($row)) {
            throw new \Exception('Бүтээгдэхүүн олдсонгүй', 404);
        }
        $this->redirectPermanently('product', ['slug' => $row['slug']]);
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
        $users = (new \Dashboard\User\UsersModel($this->pdo))->getName();
        $stmt = $this->prepare(
            "SELECT p.*, " .
            "CONCAT(c.first_name, ' ', c.last_name) as creator_name, " .
            "CONCAT(pb.first_name, ' ', pb.last_name) as publisher_name " .
            "FROM $table p " .
            "LEFT JOIN $users c ON p.created_by = c.id " .
            "LEFT JOIN $users pb ON p.published_by = pb.id " .
            "WHERE p.slug = :slug AND p.published = 1 LIMIT 1"
        );
        $stmt->bindValue(':slug', $slug);
        $stmt->execute();
        $record = $stmt->fetch();
        if (empty($record)) {
            throw new \Exception('Бүтээгдэхүүн олдсонгүй', 404);
        }

        $id = $record['id'];

        // Үг тоолох ба уншихад шаардлагатай хугацаа
        $plainText = \strip_tags($record['content'] ?? '');
        $record['word_count'] = \preg_match_all('/[\p{L}\p{N}]+/u', $plainText);
        $record['read_time'] = \max(1, (int) \ceil($record['word_count'] / 200));

        // Файлуудыг татах
        $files = new FilesModel($this->pdo);
        $files->setTable($table);
        $record['files'] = $files->getRows([
            'WHERE' => "record_id=$id"
        ]);

        // Үнэлгээнүүдийг татах (review=1 үед)
        if (!empty($record['review'])) {
            $reviewsModel = new ReviewsModel($this->pdo);
            $reviewsTable = $reviewsModel->getName();
            $rstmt = $this->prepare(
                "SELECT id, name, rating, comment, created_at FROM $reviewsTable
                 WHERE product_id=:pid ORDER BY created_at DESC"
            );
            $record['reviews'] = $rstmt->execute([':pid' => $id]) ? $rstmt->fetchAll() : [];

            $avgStmt = $this->prepare(
                "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM $reviewsTable
                 WHERE product_id=:pid"
            );
            $avgStmt->execute([':pid' => $id]);
            $stats = $avgStmt->fetch();
            $record['avg_rating'] = \round((float)($stats['avg_rating'] ?? 0), 1);
            $record['review_count'] = (int)($stats['review_count'] ?? 0);

            $ts = \time();
            $record['spam_ts'] = $ts;
            $record['spam_token'] = $this->generateSpamToken("review-$id", $ts);
            $record['turnstile_site_key'] = $this->getTurnstileSiteKey();
        }

        $record['low_stock_threshold'] = self::LOW_STOCK_THRESHOLD;
        $this->webTemplate(__DIR__ . '/product.html', $record)->render();

        // Read count
        $this->exec("UPDATE $table SET read_count=read_count+1 WHERE id=$id");

        $this->log(
            'web',
            LogLevel::NOTICE,
            '[{server_request.code} : /product/{slug}] {title} - бүтээгдэхүүнийг уншиж байна',
            ['action' => 'product', 'record_id' => $id, 'slug' => $slug, 'title' => $record['title']]
        );
    }

    /**
     * Сагсыг харуулах.
     *
     * Session-д зөвхөн [product_id => quantity] хадгалагддаг тул нэр, үнэ,
     * үлдэгдлийг resolveItems() DB-ээс шинээр уншина. Нийтлэгдээгүй болсон
     * эсвэл устгагдсан бүтээгдэхүүн сагсанд харагдахгүй.
     *
     * @return void
     */
    public function cart()
    {
        $resolved = $this->resolveItems(Cart::lines());
        $this->webTemplate(__DIR__ . '/cart.html', [
            'items' => $resolved['items'],
            'total' => $resolved['total'],
            'has_error' => $this->hasItemError($resolved['items']),
            'low_stock_threshold' => self::LOW_STOCK_THRESHOLD,
            'title' => $this->text('cart')
        ])->render();
    }

    /**
     * Сагсанд бүтээгдэхүүн нэмэх (POST /session/cart/add).
     *
     * Нийтлэгдсэн бүтээгдэхүүнийг л нэмнэ. Нөөц хянадаг барааны тоо
     * ширхэгийг үлдэгдлээр хязгаарлана, үлдэгдэлгүй бол нэмэхгүй.
     *
     * Accept: application/json толгойтой (product.html-ийн fetch) хүсэлтэд
     * {status: success|limited|error, message, in_cart, count} JSON буцаана.
     * Бусад үед (JS-гүй энгийн форм) сагсны хуудас руу 303 redirect хийнэ (PRG).
     *
     * @return void
     */
    public function cartAdd()
    {
        $payload = $this->getParsedBody();
        $productId = (int)($payload['product_id'] ?? 0);
        $quantity = \max(1, (int)($payload['quantity'] ?? 1));

        // Үр дүн: success - бүтэн нэмэгдсэн, limited - үлдэгдлээр хязгаарлагдсан
        // (хэсэгчлэн эсвэл огт нэмэгдээгүй), error - бараа олдсонгүй / үлдэгдэлгүй
        $status = 'error';
        $message = $this->text('invalid-request');
        $inCart = 0;
        $product = $productId > 0 ? ($this->resolveItems([$productId => 1])['items'][0] ?? null) : null;
        if ($product !== null) {
            $current = Cart::lines()[$productId] ?? 0;
            $wanted = $current + $quantity;
            if ($product['manage_stock']) {
                $wanted = \min($wanted, $product['stock']);
            }
            if ($product['manage_stock'] && $product['stock'] <= 0) {
                $message = $this->text('out-of-stock');
            } else {
                if ($wanted > $current) {
                    Cart::set($productId, $wanted);
                }
                $inCart = $wanted;
                $status = $wanted === $current + $quantity ? 'success' : 'limited';
                $message = $status === 'success'
                    ? $product['title']
                    : \sprintf($this->text('only-n-left'), $product['stock']) . '. ' . $this->text('cart') . ": $inCart";
            }
        }

        // fetch()-ээр (Accept: application/json) дуудсан бол хуудаснаас гаралгүй
        // JSON хариу - product.html цонхоор харуулна. JS-гүй үед сагс руу redirect
        if (\str_contains($this->getRequest()->getHeaderLine('Accept'), 'application/json')) {
            $this->respondJSON([
                'status' => $status,
                'message' => $message,
                'in_cart' => $inCart,
                'count' => Cart::count()
            ], $status === 'error' ? 400 : 200);
            return;
        }
        $this->redirectAfterPost('cart');
    }

    /**
     * Сагсны тоо ширхэгийг шинэчлэх, мөр хасах (POST /session/cart/update).
     *
     * Body: quantity[product_id] = тоо (0 бол хасна), remove = product_id.
     *
     * @return void
     */
    public function cartUpdate()
    {
        $payload = $this->getParsedBody();
        $lines = Cart::lines();
        if (\is_array($payload['quantity'] ?? null)) {
            foreach ($payload['quantity'] as $productId => $quantity) {
                // Зөвхөн сагсанд байгаа мөрийг засна - шинэ бараа cartAdd()-аар л нэмэгдэнэ
                if (isset($lines[(int)$productId])) {
                    Cart::set((int)$productId, (int)$quantity);
                }
            }
        }
        if (!empty($payload['remove'])) {
            Cart::set((int)$payload['remove'], 0);
        }
        $this->redirectAfterPost('cart');
    }

    /**
     * Нэрлэсэн route руу 303 See Other redirect (POST-оос дахин илгээгдэхгүй).
     *
     * @param string $routeName Route нэр (жишээ: 'cart')
     */
    private function redirectAfterPost(string $routeName): never
    {
        $link = $this->generateRouteLink($routeName);
        \header('Location: ' . \filter_var($link, \FILTER_SANITIZE_URL), true, 303);
        exit;
    }

    /**
     * Сагсны мөрүүдийг DB дахь нийтлэгдсэн бүтээгдэхүүнээр баяжуулах.
     *
     * Үнэ нь sale_price (0-ээс их бол) эсвэл price. Нөөц хянадаг барааны
     * үлдэгдэл хүрэлцэхгүй бол мөрөнд 'error' ('out-of-stock' эсвэл
     * 'not-enough-stock') тэмдэглэнэ. Нийтлэгдээгүй/устгагдсан бүтээгдэхүүнийг
     * үр дүнд оруулахгүй.
     *
     * @param array<int,int> $lines [product_id => quantity]
     * @return array{items: array<int,array>, total: float}
     */
    private function resolveItems(array $lines): array
    {
        if (empty($lines)) {
            return ['items' => [], 'total' => 0.0];
        }
        $table = (new ProductsModel($this->pdo))->getName();
        $ids = \array_keys($lines);
        $placeholders = [];
        foreach ($ids as $i => $id) {
            $placeholders[] = ":id$i";
        }
        $stmt = $this->prepare(
            'SELECT id, title, slug, photo, price, sale_price, manage_stock, stock ' .
            "FROM $table WHERE published=1 AND id IN (" . \implode(',', $placeholders) . ')'
        );
        foreach ($ids as $i => $id) {
            $stmt->bindValue(":id$i", $id, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[(int)$row['id']] = $row;
        }

        $items = [];
        $total = 0.0;
        foreach ($lines as $id => $quantity) {
            if (!isset($rows[$id])) {
                continue;
            }
            $row = $rows[$id];
            $salePrice = (float)($row['sale_price'] ?? 0);
            $price = $salePrice > 0 ? $salePrice : (float)$row['price'];
            $manageStock = (int)($row['manage_stock'] ?? 0) === 1;
            $stock = (int)($row['stock'] ?? 0);
            $error = null;
            if ($manageStock && $stock <= 0) {
                $error = 'out-of-stock';
            } elseif ($manageStock && $quantity > $stock) {
                $error = 'not-enough-stock';
            }
            $lineTotal = \round($price * $quantity, 2);
            $total += $lineTotal;
            $items[] = [
                'product_id' => $id,
                'title' => (string)$row['title'],
                'slug' => (string)$row['slug'],
                'photo' => (string)($row['photo'] ?? ''),
                'price' => $price,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
                'manage_stock' => $manageStock,
                'stock' => $stock,
                'error' => $error
            ];
        }
        return ['items' => $items, 'total' => \round($total, 2)];
    }

    /**
     * resolveItems()-ийн мөрүүдийн аль нэг нь үлдэгдлийн алдаатай эсэх
     * (сагс, захиалгын хуудас дээр захиалах товчийг хаана).
     */
    private function hasItemError(array $items): bool
    {
        foreach ($items as $item) {
            if ($item['error'] !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * Захиалгын формыг харуулах.
     *
     * Захиалга үргэлж сагсны бүх барааг хамарна: сагсны агуулгыг (items,
     * total) харуулж, доор нь захиалагчийн мэдээллийн форм. Сагс хоосон бол
     * форм биш "cart-empty" мэдэгдэл, аль нэг барааны үлдэгдэл хүрэлцэхгүй
     * бол сагс руу буцах холбоос харагдана.
     *
     * Хуучин "/order?product_id=" холбоос (нэг барааг тусад нь захиалдаг байсан)
     * тухайн барааны хуудас руу 301 redirect хийнэ.
     *
     * Spam хамгаалалтын timestamp болон HMAC token-г бэлтгэж
     * template-д дамжуулна.
     *
     * @return void
     */
    public function order()
    {
        $productId = (int)($this->getQueryParams()['product_id'] ?? 0);
        if ($productId > 0) {
            $product = $this->resolveItems([$productId => 1])['items'][0] ?? null;
            if ($product !== null) {
                $this->redirectPermanently('product', ['slug' => $product['slug']]);
            }
            $this->redirectPermanently('products');
        }

        $resolved = $this->resolveItems(Cart::lines());
        $vars = [
            'items' => $resolved['items'],
            'total' => $resolved['total'],
            'has_error' => $this->hasItemError($resolved['items'])
        ];

        $ts = \time();
        $vars['spam_ts'] = $ts;
        $vars['spam_token'] = $this->generateSpamToken('order-form', $ts);
        $vars['turnstile_site_key'] = $this->getTurnstileSiteKey();

        $vars['title'] = $this->text('order');
        $this->webTemplate(__DIR__ . '/order.html', $vars)->render();

        $context = ['action' => 'order', 'title' => $this->text('cart') . ' (' . \count($vars['items']) . ')'];
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
        try {
            $payload = $this->getParsedBody();
            $code = $this->getLanguageCode();

            $this->validateSpamProtection($payload, 'order-form', '_last_order_at', 10, 3);

            if (empty($payload['customer_name']) || empty($payload['customer_email'])) {
                throw new \Exception(
                    $code === 'mn' ? 'Нэр болон имэйл хаяг шаардлагатай' : 'Name and email are required',
                    400
                );
            }
            if (!\filter_var($payload['customer_email'], \FILTER_VALIDATE_EMAIL)) {
                throw new \Exception(
                    $code === 'mn' ? 'Зөв имэйл хаяг оруулна уу' : 'Please enter a valid email address',
                    400
                );
            }
            $address = \trim((string)($payload['customer_address'] ?? ''));
            if ($address === '') {
                throw new \Exception(
                    $code === 'mn' ? 'Хүргүүлэх хаягаа оруулна уу' : 'Please enter a delivery address',
                    400
                );
            }
            // Утас заавал - хүргэлтийн үед холбогдоно. Улсын формат шахахгүй (гадаад
            // дугаар орж болно): тоо, +, хоосон зай, -, () тэмдэгтүүдээс бүрдсэн 6-32 тэмдэгт
            $phone = \trim((string)($payload['customer_phone'] ?? ''));
            if (!\preg_match('/^\+?[0-9 ()\-]{6,32}$/', $phone)
                || \preg_match_all('/[0-9]/', $phone) < 6
            ) {
                throw new \Exception(
                    $code === 'mn' ? 'Утасны дугаараа зөв оруулна уу' : 'Please enter a valid phone number',
                    400
                );
            }
            // Захиалга үргэлж сагснаас. Нэр, үнийг client-д итгэлгүйгээр
            // нийтлэгдсэн бүтээгдэхүүний бичлэгээс авна. Үлдэгдлийг мөн серверт
            // шалгана - форм нээгдэхгүй байсан ч шууд POST илгээж болох тул
            // template-ийн шалгалт хангалтгүй.
            $lines = Cart::lines();
            if (empty($lines)) {
                throw new \Exception($this->text('cart-empty'), 400);
            }
            $resolved = $this->resolveItems($lines);
            $items = $resolved['items'];
            if (\count($items) !== \count($lines)) {
                throw new \Exception(
                    $code === 'mn' ? 'Зарим бүтээгдэхүүн захиалах боломжгүй болсон байна. Сагсаа шалгана уу' : 'Some products are no longer available. Please check your cart',
                    400
                );
            }
            foreach ($items as $item) {
                if ($item['error'] === 'out-of-stock') {
                    throw new \Exception(
                        $code === 'mn' ? "Уучлаарай, \"{$item['title']}\" нөөцөд байхгүй байна" : "Sorry, \"{$item['title']}\" is out of stock",
                        400
                    );
                }
                if ($item['error'] === 'not-enough-stock') {
                    throw new \Exception(
                        $code === 'mn' ? "\"{$item['title']}\" - үлдэгдэл хүрэлцэхгүй байна. Хамгийн ихдээ {$item['stock']} ширхэг захиалах боломжтой" : "\"{$item['title']}\" - not enough stock. You can order at most {$item['stock']}",
                        400
                    );
                }
            }

            // Захиалгын мөрүүд (items) нь захиалах үеийн нэр, үнийн хуулбар -
            // дараа нь бүтээгдэхүүний үнэ өөрчлөгдсөн ч захиалга хэвээр үлдэнэ.
            // product_title/quantity нь жагсаалт, хайлт, имэйл, Discord-д зориулсан хураангуй.
            $orderItems = [];
            $summary = [];
            $quantity = 0;
            foreach ($items as $item) {
                $orderItems[] = [
                    'product_id' => $item['product_id'],
                    'title' => $item['title'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity']
                ];
                $summary[] = \count($items) > 1 ? "{$item['title']} x{$item['quantity']}" : $item['title'];
                $quantity += $item['quantity'];
            }
            $productTitle = \mb_substr(\implode(', ', $summary), 0, 255);
            $total = $resolved['total'];

            $model = new ProductOrdersModel($this->pdo);
            $orderData = [
                'product_title' => $productTitle,
                'items' => \json_encode($orderItems, \JSON_UNESCAPED_UNICODE),
                'total' => $total,
                'customer_name' => $payload['customer_name'],
                'customer_email' => $payload['customer_email'],
                'customer_phone' => $phone,
                'customer_address' => $address,
                'message' => $payload['message'] ?? '',
                'quantity' => $quantity,
                'code' => $code,
                'status' => 'new'
            ];
            if (\count($items) === 1) {
                $orderData['product_id'] = $items[0]['product_id'];
            }
            $record = $model->insert($orderData);

            if (!isset($record['id'])) {
                throw new \Exception(
                    $code === 'mn' ? 'Захиалга үүсгэхэд алдаа гарлаа' : 'Failed to create order',
                    500
                );
            }

            $_SESSION['_last_order_at'] = \time();
            Cart::clear();

            $this->sendOrderConfirmation(
                (int)$record['id'],
                $payload['customer_name'],
                $payload['customer_email'],
                $productTitle,
                $quantity,
                $orderItems,
                $total,
                $code
            );

            $this->dispatch(new \Dashboard\Notification\OrderEvent(
                'new', (int)$record['id'],
                $payload['customer_name'],
                $payload['customer_email'],
                $phone,
                $productTitle,
                $quantity,
                '', ''
            ));

            $this->sendOrderNotifyEmail(
                (int)$record['id'],
                $payload['customer_name'],
                $payload['customer_email'],
                $productTitle,
                $quantity,
                $orderItems,
                $total,
                $phone,
                $address
            );

            $this->webTemplate(__DIR__ . '/order-success.html', [
                'order_id' => $record['id'],
                'customer_name' => $payload['customer_name'],
                'product_title' => $productTitle,
                'items' => $orderItems,
                'total' => $total,
                'title' => $code === 'mn' ? 'Захиалга амжилттай' : 'Order Success'
            ])->render();

            $this->log(
                'products_orders',
                LogLevel::INFO,
                '{auth_user.username} шинэ захиалга илгээлээ',
                [
                    'action' => 'order',
                    'record_id' => $record['id'],
                    'product_title' => $productTitle,
                    'auth_user' => [
                        'username'   => $payload['customer_name'],
                        'email'      => $payload['customer_email'],
                        'phone'      => $phone,
                        'first_name' => $payload['customer_name'],
                        'last_name'  => ''
                    ]
                ]
            );
        } catch (\Throwable $err) {
            $this->respondJSONError($err);
        }
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

            // Бүтээгдэхүүн байгаа, нийтлэгдсэн, review идэвхтэй эсэх шалгах
            $productsModel = new ProductsModel($this->pdo);
            $product = $productsModel->getById($id);
            if (empty($product) || empty($product['published']) || empty($product['review'])) {
                throw new \Exception('Invalid request', 400);
            }

            $this->validateSpamProtection($parsed, "review-$id", '_last_review_at', 10, 3);

            $name = \trim($parsed['name'] ?? '');
            $email = \trim($parsed['email'] ?? '');
            $rating = (int)($parsed['rating'] ?? 0);
            $comment = \trim($parsed['comment'] ?? '');

            if (empty($name)) {
                throw new \InvalidArgumentException($code === 'mn' ? 'Нэрээ оруулна уу' : 'Please enter your name', 400);
            }
            if ($rating < 1 || $rating > 5) {
                throw new \InvalidArgumentException($code === 'mn' ? 'Үнэлгээ сонгоно уу (1-5)' : 'Please select a rating (1-5)', 400);
            }
            if (empty($comment)) {
                throw new \InvalidArgumentException($code === 'mn' ? 'Сэтгэгдлээ бичнэ үү' : 'Please enter your review', 400);
            }
            if (!empty($email) && !\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException($code === 'mn' ? 'Зөв имэйл хаяг оруулна уу' : 'Please enter a valid email address', 400);
            }
            $this->checkLinkSpam($comment);

            $_SESSION['_last_review_at'] = \time();

            $reviewsModel = new ReviewsModel($this->pdo);
            $reviewsModel->insert([
                'product_id' => $id,
                'name' => $name,
                'email' => $email,
                'rating' => $rating,
                'comment' => $comment
            ]);

            $this->dispatch(new \Dashboard\Notification\ContentEvent(
                'insert', 'review', $product['title'], $id,
                $name,
                ['rating' => $rating, 'comment' => $comment]
            ));

            // Админд email мэдэгдэл
            $this->sendReviewNotifyEmail($name, $email, $rating, $comment, $product['title']);

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
            $this->respondJSONError($err);
        }
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
     * @param int    $quantity      Нийт тоо ширхэг
     * @param array  $items         Захиалгын мөрүүд (product_id, title, price, quantity)
     * @param float  $total         Нийт дүн
     * @param string $code          Хэлний код
     * @return void
     */
    private function sendOrderConfirmation(
        int $orderId,
        string $customerName,
        string $customerEmail,
        string $productTitle,
        int $quantity,
        array $items,
        float $total,
        string $code
    ) {
        try {
            $mailer = $this->getService('mailer');
            if (empty($mailer)) {
                return;
            }

            $templateService = $this->getService('template_service');
            $template = $templateService?->getByKeyword('order-confirmation', $code);
            if (empty($template)) {
                return;
            }

            // Subject нь энгийн текст тул autoescape унтраана
            $subjectTemplate = new MemoryTemplate();
            $subjectTemplate->setAutoEscape(false);
            $subjectTemplate->source($template['title']);
            $subjectTemplate->set('order_id', $orderId);
            $subject = $subjectTemplate->output();

            // Body нь HTML - утга бүрийг autoescape өөрөө escape хийнэ
            $bodyTemplate = new MemoryTemplate();
            $bodyTemplate->source($template['content']);
            $bodyTemplate->set('order_id', $orderId);
            $bodyTemplate->set('customer_name', $customerName);
            $bodyTemplate->set('product_title', $productTitle);
            $bodyTemplate->set('quantity', $quantity);
            $bodyTemplate->set('items', $items);
            $bodyTemplate->set('total', $total);
            $body = $bodyTemplate->output();
            
            $mailer->mail($customerEmail, $customerName, $subject, $body)->send();
        } catch (\Throwable $e) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log("OrderConfirmationEmail: {$e->getMessage()}");
            }
        }
    }

    /**
     * Шинэ захиалга ирсэн тухай админд email мэдэгдэл.
     *
     * Template-д product_title (хураангуй), quantity (нийт), items, total дамжина.
     */
    private function sendOrderNotifyEmail(
        int $orderId,
        string $customerName,
        string $customerEmail,
        string $productTitle,
        int $quantity,
        array $items,
        float $total,
        string $phone,
        string $address
    ) {
        try {
            $notifyEmail = $_ENV['RAPTOR_ORDER_EMAIL_TO'] ?? '';
            if (empty($notifyEmail)) {
                return;
            }

            $mailer = $this->getService('mailer');
            if (empty($mailer)) {
                return;
            }

            $code = $this->getLanguageCode() ?: 'en';
            $templateService = $this->getService('template_service');
            $template = $templateService?->getByKeyword('order-notify', $code);
            if (empty($template)) {
                return;
            }

            // Web app нь /dashboard-д mount хийгдээгүй тул generateRouteLink() энд
            // ажиллахгүй - email-д cross-app absolute URL hardcode хийнэ. '/dashboard'-г
            // Dashboard app-ийн mount path-тай тааруулж байх ёстой.
            $appUrl = \rtrim((string)$this->getRequest()->getUri()->withPath($this->getScriptPath()), '/');
            $ordersLink = $appUrl . '/dashboard/orders';

            // Subject нь энгийн текст тул autoescape унтраана
            $subjectTemplate = new MemoryTemplate();
            $subjectTemplate->setAutoEscape(false);
            $subjectTemplate->source($template['title']);
            $subjectTemplate->set('order_id', $orderId);
            $subjectTemplate->set('customer_name', $customerName);
            $subject = $subjectTemplate->output();

            // Body нь HTML - утга бүрийг autoescape өөрөө escape хийнэ
            $bodyTemplate = new MemoryTemplate();
            $bodyTemplate->source($template['content']);
            $bodyTemplate->set('order_id', $orderId);
            $bodyTemplate->set('customer_name', $customerName);
            $bodyTemplate->set('customer_email', $customerEmail);
            $bodyTemplate->set('customer_phone', $phone);
            $bodyTemplate->set('customer_address', $address);
            $bodyTemplate->set('product_title', $productTitle);
            $bodyTemplate->set('quantity', $quantity);
            $bodyTemplate->set('items', $items);
            $bodyTemplate->set('total', $total);
            $bodyTemplate->set('orders_link', $ordersLink);
            $body = $bodyTemplate->output();

            $mailer->mail($notifyEmail, null, $subject, $body);
            if (!empty($customerEmail)) {
                $mailer->setReplyTo($customerEmail, $customerName);
            }
            $mailer->send();
        } catch (\Throwable $e) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log("OrderNotifyEmail: {$e->getMessage()}");
            }
        }
    }

    /**
     * Шинэ үнэлгээ ирсэн тухай админд email мэдэгдэл.
     */
    private function sendReviewNotifyEmail(
        string $name,
        string $email,
        int $rating,
        string $comment,
        string $productTitle
    ) {
        try {
            $notifyEmail = $_ENV['RAPTOR_REVIEW_EMAIL_TO'] ?? '';
            if (empty($notifyEmail)) {
                return;
            }

            $mailer = $this->getService('mailer');
            if (empty($mailer)) {
                return;
            }

            $code = $this->getLanguageCode() ?: 'en';
            $templateService = $this->getService('template_service');
            $template = $templateService?->getByKeyword('review-notify', $code);
            if (empty($template)) {
                return;
            }

            // Web app нь /dashboard-д mount хийгдээгүй тул generateRouteLink() энд
            // ажиллахгүй - email-д cross-app absolute URL hardcode хийнэ. '/dashboard'-г
            // Dashboard app-ийн mount path-тай тааруулж байх ёстой.
            $appUrl = \rtrim((string)$this->getRequest()->getUri()->withPath($this->getScriptPath()), '/');
            $reviewsLink = $appUrl . '/dashboard/products/reviews';

            // Subject нь энгийн текст тул autoescape унтраана
            $subjectTemplate = new MemoryTemplate();
            $subjectTemplate->setAutoEscape(false);
            $subjectTemplate->source($template['title']);
            $subjectTemplate->set('product_title', $productTitle);
            $subject = $subjectTemplate->output();

            // Body нь HTML - утга бүрийг autoescape өөрөө escape хийнэ.
            // Мөр таслалтай comment-ийг nl2br хийж Markup-аар safe гэж тэмдэглэнэ.
            $bodyTemplate = new MemoryTemplate();
            $bodyTemplate->source($template['content']);
            $bodyTemplate->set('name', $name);
            $bodyTemplate->set('email', $email);
            $bodyTemplate->set('rating', $rating);
            $bodyTemplate->set('comment', new Markup(\nl2br(\htmlspecialchars($comment, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'))));
            $bodyTemplate->set('product_title', $productTitle);
            $bodyTemplate->set('reviews_link', $reviewsLink);
            $body = $bodyTemplate->output();

            $mailer->mail($notifyEmail, null, $subject, $body);
            if (!empty($email)) {
                $mailer->setReplyTo($email, $name);
            }
            $mailer->send();
        } catch (\Throwable $e) {
            if (CODESAUR_DEVELOPMENT) {
                \error_log("ReviewNotifyEmail: {$e->getMessage()}");
            }
        }
    }
}
