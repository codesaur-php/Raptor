<?php

namespace Dashboard\Home;

use Raptor\Content\NewsModel;
use Raptor\Content\PagesModel;
use Raptor\User\UsersModel;

use Dashboard\Shop\ProductsModel;
use Dashboard\Shop\ProductOrdersModel;

/**
 * Class SearchController
 *
 * Dashboard-ийн topbar хайлтын endpoint.
 * Бүх үндсэн хүснэгтүүдээс (news, pages, products, orders, users гэх мэт)
 * LIKE хайлт хийж JSON үр дүн буцаана.
 *
 * @package Dashboard\Home
 */
class SearchController extends \Raptor\Controller
{
    /**
     * Dashboard-ийн topbar search.
     *
     * Бүх үндсэн хүснэгтүүдээс (news, pages, products, orders, users)
     * title/name талбараар LIKE хайлт хийж JSON үр дүн буцаана.
     * Хэрэглэгчийн RBAC эрхийг шалгана.
     */
    public function search()
    {
        try {
            if (!$this->isUserAuthorized()) {
                throw new \Exception('Unauthorized', 401);
            }

            $q = \trim($this->getQueryParams()['q'] ?? '');
            if (\mb_strlen($q) < 2) {
                $this->respondJSON(['status' => 'success', 'results' => []]);
                return;
            }

            $like = '%' . $q . '%';
            $results = [];
            
            // NEWS
            if ($this->isUserCan('system_content_index')) {
                $table = (new NewsModel($this->pdo))->getName();
                $stmt = $this->prepare(
                    "SELECT id, title, description, code, type, published, 'news' AS source
                     FROM $table
                     WHERE title LIKE :q OR description LIKE :q2 OR content LIKE :q3 OR source LIKE :q4
                     ORDER BY created_at DESC LIMIT 10"
                );
                $stmt->bindValue(':q', $like);
                $stmt->bindValue(':q2', $like);
                $stmt->bindValue(':q3', $like);
                $stmt->bindValue(':q4', $like);
                if ($stmt->execute()) {
                    while ($row = $stmt->fetch()) {
                        $results[] = $row;
                    }
                }
            }

            // PAGES
            if ($this->isUserCan('system_content_index')) {
                $table = (new PagesModel($this->pdo))->getName();
                $stmt = $this->prepare(
                    "SELECT id, title, description, code, type, published, link, 'pages' AS source
                     FROM $table
                     WHERE title LIKE :q OR description LIKE :q2 OR content LIKE :q3 OR link LIKE :q4 OR source LIKE :q5
                     ORDER BY created_at DESC LIMIT 10"
                );
                $stmt->bindValue(':q', $like);
                $stmt->bindValue(':q2', $like);
                $stmt->bindValue(':q3', $like);
                $stmt->bindValue(':q4', $like);
                $stmt->bindValue(':q5', $like);
                if ($stmt->execute()) {
                    while ($row = $stmt->fetch()) {
                        $results[] = $row;
                    }
                }
            }

            // PRODUCTS
            if ($this->isUserCan('system_content_index')) {
                try {
                    $table = (new ProductsModel($this->pdo))->getName();
                    $stmt = $this->prepare(
                        "SELECT id, title, description, code, type, published, sku, 'products' AS source
                         FROM $table
                         WHERE title LIKE :q OR description LIKE :q2 OR sku LIKE :q3 OR content LIKE :q4
                         ORDER BY created_at DESC LIMIT 10"
                    );
                    $stmt->bindValue(':q', $like);
                    $stmt->bindValue(':q2', $like);
                    $stmt->bindValue(':q3', $like);
                    $stmt->bindValue(':q4', $like);
                    if ($stmt->execute()) {
                        while ($row = $stmt->fetch()) {
                            $results[] = $row;
                        }
                    }
                } catch (\Throwable) {
                }
            }

            // ORDERS
            if ($this->isUserCan('system_content_index')) {
                try {
                    $table = (new ProductOrdersModel($this->pdo))->getName();
                    $stmt = $this->prepare(
                        "SELECT id, product_title AS title, customer_name, customer_email, customer_phone, message, status, 'orders' AS source
                         FROM $table
                         WHERE product_title LIKE :q OR customer_name LIKE :q2 OR customer_email LIKE :q3 OR customer_phone LIKE :q4 OR message LIKE :q5
                         ORDER BY created_at DESC LIMIT 10"
                    );
                    $stmt->bindValue(':q', $like);
                    $stmt->bindValue(':q2', $like);
                    $stmt->bindValue(':q3', $like);
                    $stmt->bindValue(':q4', $like);
                    $stmt->bindValue(':q5', $like);
                    if ($stmt->execute()) {
                        while ($row = $stmt->fetch()) {
                            $results[] = $row;
                        }
                    }
                } catch (\Throwable) {
                }
            }

            // USERS
            if ($this->isUserCan('system_user_index')) {
                $table = (new UsersModel($this->pdo))->getName();
                $stmt = $this->prepare(
                    "SELECT id, username, first_name, last_name, email, phone, 'users' AS source
                     FROM $table
                     WHERE is_active=1
                       AND (username LIKE :q OR first_name LIKE :q2 OR last_name LIKE :q3 OR email LIKE :q4 OR phone LIKE :q5)
                     ORDER BY id DESC LIMIT 10"
                );
                $stmt->bindValue(':q', $like);
                $stmt->bindValue(':q2', $like);
                $stmt->bindValue(':q3', $like);
                $stmt->bindValue(':q4', $like);
                $stmt->bindValue(':q5', $like);
                if ($stmt->execute()) {
                    while ($row = $stmt->fetch()) {
                        $row['title'] = \trim($row['first_name'] . ' ' . $row['last_name']);
                        unset($row['first_name'], $row['last_name']);
                        $results[] = $row;
                    }
                }
            }

            // HTML tag доторх текстээс олдсон үр дүнг шүүх:
            // title, description зэрэг талбарт хайлтын үг байвал үлдээнэ,
            // зөвхөн content-ийн HTML tag дотор олдсон бол хасна.
            $best_results = \array_values(\array_filter($results, function ($row) use ($q) {
                $start = \mb_strtolower($q);
                foreach ($row as $key => $value) {
                    if (\in_array($key, ['id', 'source', 'published'])) {
                        continue;
                    }
                    if (\is_string($value) && \mb_stripos(\strip_tags($value), $start) !== false) {
                        return true;
                    }
                }
                return false;
            }));

            $this->respondJSON([
                'status' => 'success',
                'results' => $best_results
            ]);
        } catch (\Throwable $err) {
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode());
        }
    }
}
