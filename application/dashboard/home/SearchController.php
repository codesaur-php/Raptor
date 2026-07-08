<?php

namespace Dashboard\Home;

use Dashboard\Content\CommentsModel;
use Dashboard\Content\MessagesModel;
use Dashboard\Content\NewsModel;
use Dashboard\Content\PagesModel;
use Dashboard\Organization\OrganizationModel;
use Dashboard\User\UsersModel;
use Dashboard\Development\DevRequestModel;
use Dashboard\Shop\ProductsModel;
use Dashboard\Shop\ProductOrdersModel;
use Dashboard\Shop\ReviewsModel;

/**
 * Class SearchController
 *
 * Dashboard-ийн topbar хайлтын modal (Ctrl+K)-ийн endpoint.
 * Бүх үндсэн хүснэгтүүдээс (news, pages, products, orders, users,
 * organizations, dev-requests, messages, comments, reviews) LIKE хайлт
 * хийж JSON үр дүн буцаана.
 *
 * Чухал инвариант: блок бүр тухайн модулийн index хуудасны ижил
 * permission-ээр хамгаалагдана - хайлтын үр дүн нь хэрэглэгч browse
 * хийгээд харж чадах зүйлсийн дэд олонлог байх ёстой.
 *
 * @package Dashboard\Home
 */
class SearchController extends \Dashboard\Controller
{
    /**
     * Dashboard-ийн topbar хайлтын modal (Ctrl+K) search.
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
                try {
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
                } catch (\Throwable $err) {
                    // Хүснэгт байхгүй (хагас суулгац) бол зүгээр өнгөрнө, гэхдээ
                    // жинхэнэ query алдааг dev горимд харуулж regression-ийг нуухгүй.
                    if (CODESAUR_DEVELOPMENT) {
                        \error_log('Search module query failed: ' . $err->getMessage());
                    }
                }
            }

            // PAGES
            if ($this->isUserCan('system_content_index')) {
                try {
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
                } catch (\Throwable $err) {
                    // Хүснэгт байхгүй (хагас суулгац) бол зүгээр өнгөрнө, гэхдээ
                    // жинхэнэ query алдааг dev горимд харуулж regression-ийг нуухгүй.
                    if (CODESAUR_DEVELOPMENT) {
                        \error_log('Search module query failed: ' . $err->getMessage());
                    }
                }
            }

            // PRODUCTS - shop модулийн index хуудастай ижил permission
            if ($this->isUserCan('system_product_index')) {
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
                } catch (\Throwable $err) {
                    // Хүснэгт байхгүй (хагас суулгац) бол зүгээр өнгөрнө, гэхдээ
                    // жинхэнэ query алдааг dev горимд харуулж regression-ийг нуухгүй.
                    if (CODESAUR_DEVELOPMENT) {
                        \error_log('Search module query failed: ' . $err->getMessage());
                    }
                }
            }

            // ORDERS - захиалагчийн PII агуулдаг тул shop модулийн index
            // хуудастай ижил permission (system_product_index) шаардана
            if ($this->isUserCan('system_product_index')) {
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
                } catch (\Throwable $err) {
                    // Хүснэгт байхгүй (хагас суулгац) бол зүгээр өнгөрнө, гэхдээ
                    // жинхэнэ query алдааг dev горимд харуулж regression-ийг нуухгүй.
                    if (CODESAUR_DEVELOPMENT) {
                        \error_log('Search module query failed: ' . $err->getMessage());
                    }
                }
            }

            // USERS
            if ($this->isUserCan('system_user_index')) {
                try {
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
                } catch (\Throwable $err) {
                    // Хүснэгт байхгүй (хагас суулгац) бол зүгээр өнгөрнө, гэхдээ
                    // жинхэнэ query алдааг dev горимд харуулж regression-ийг нуухгүй.
                    if (CODESAUR_DEVELOPMENT) {
                        \error_log('Search module query failed: ' . $err->getMessage());
                    }
                }
            }

            // ORGANIZATIONS - байгууллагын модулийн index хуудастай ижил permission
            if ($this->isUserCan('system_organization_index')) {
                try {
                    $table = (new OrganizationModel($this->pdo))->getName();
                    $stmt = $this->prepare(
                        "SELECT id, name AS title, alias AS code, 'organizations' AS source
                         FROM $table
                         WHERE is_active=1 AND name LIKE :q
                         ORDER BY name LIMIT 10"
                    );
                    $stmt->bindValue(':q', $like);
                    if ($stmt->execute()) {
                        while ($row = $stmt->fetch()) {
                            $results[] = $row;
                        }
                    }
                } catch (\Throwable $err) {
                    // Хүснэгт байхгүй (хагас суулгац) бол зүгээр өнгөрнө, гэхдээ
                    // жинхэнэ query алдааг dev горимд харуулж regression-ийг нуухгүй.
                    if (CODESAUR_DEVELOPMENT) {
                        \error_log('Search module query failed: ' . $err->getMessage());
                    }
                }
            }

            // DEV REQUESTS - жагсаалтын хуудастай ижил хандалт:
            // system_development -> бүх хүсэлт, бусад -> зөвхөн өөрийн
            // үүсгэсэн эсвэл өөрт хуваарилагдсан хүсэлтүүд
            try {
                $table = (new DevRequestModel($this->pdo))->getName();
                $sql =
                    "SELECT id, title, status, content, 'dev-requests' AS source
                     FROM $table
                     WHERE (title LIKE :q OR content LIKE :q2)";
                if (!$this->isUserCan('system_development')) {
                    $sql .= ' AND (created_by=:uid OR assigned_to=:uid2)';
                }
                $sql .= ' ORDER BY created_at DESC LIMIT 10';
                $stmt = $this->prepare($sql);
                $stmt->bindValue(':q', $like);
                $stmt->bindValue(':q2', $like);
                if (!$this->isUserCan('system_development')) {
                    $stmt->bindValue(':uid', $this->getUserId(), \PDO::PARAM_INT);
                    $stmt->bindValue(':uid2', $this->getUserId(), \PDO::PARAM_INT);
                }
                if ($stmt->execute()) {
                    while ($row = $stmt->fetch()) {
                        $results[] = $row;
                    }
                }
            } catch (\Throwable $err) {
                // Хүснэгт байхгүй (хагас суулгац) бол зүгээр өнгөрнө, гэхдээ
                // жинхэнэ query алдааг dev горимд харуулж regression-ийг нуухгүй.
                if (CODESAUR_DEVELOPMENT) {
                    \error_log('Search module query failed: ' . $err->getMessage());
                }
            }

            // MESSAGES (холбоо барих) - messages index хуудастай ижил permission
            if ($this->isUserCan('system_content_index')) {
                try {
                    $table = (new MessagesModel($this->pdo))->getName();
                    $stmt = $this->prepare(
                        "SELECT id, name AS title, email, message, 'messages' AS source
                         FROM $table
                         WHERE name LIKE :q OR email LIKE :q2 OR message LIKE :q3
                         ORDER BY created_at DESC LIMIT 10"
                    );
                    $stmt->bindValue(':q', $like);
                    $stmt->bindValue(':q2', $like);
                    $stmt->bindValue(':q3', $like);
                    if ($stmt->execute()) {
                        while ($row = $stmt->fetch()) {
                            $results[] = $row;
                        }
                    }
                } catch (\Throwable $err) {
                    // Хүснэгт байхгүй (хагас суулгац) бол зүгээр өнгөрнө, гэхдээ
                    // жинхэнэ query алдааг dev горимд харуулж regression-ийг нуухгүй.
                    if (CODESAUR_DEVELOPMENT) {
                        \error_log('Search module query failed: ' . $err->getMessage());
                    }
                }
            }

            // COMMENTS (мэдээний сэтгэгдэл) - comments index хуудастай ижил permission.
            // comments-view route нь news_id хүлээж аваад мэдээний #comments руу
            // чиглүүлдэг тул холбоосын id болгож news_id-г буцаана.
            if ($this->isUserCan('system_content_index')) {
                try {
                    $table = (new CommentsModel($this->pdo))->getName();
                    $stmt = $this->prepare(
                        "SELECT news_id AS id, name AS title, email, comment, 'comments' AS source
                         FROM $table
                         WHERE name LIKE :q OR comment LIKE :q2
                         ORDER BY created_at DESC LIMIT 10"
                    );
                    $stmt->bindValue(':q', $like);
                    $stmt->bindValue(':q2', $like);
                    if ($stmt->execute()) {
                        while ($row = $stmt->fetch()) {
                            $results[] = $row;
                        }
                    }
                } catch (\Throwable $err) {
                    // Хүснэгт байхгүй (хагас суулгац) бол зүгээр өнгөрнө, гэхдээ
                    // жинхэнэ query алдааг dev горимд харуулж regression-ийг нуухгүй.
                    if (CODESAUR_DEVELOPMENT) {
                        \error_log('Search module query failed: ' . $err->getMessage());
                    }
                }
            }

            // REVIEWS (бүтээгдэхүүний үнэлгээ) - reviews index хуудастай ижил permission.
            // Тусдаа view хуудасгүй тул холбоос reviews index руу очно.
            if ($this->isUserCan('system_product_index')) {
                try {
                    $table = (new ReviewsModel($this->pdo))->getName();
                    $stmt = $this->prepare(
                        "SELECT id, name AS title, email, comment, 'reviews' AS source
                         FROM $table
                         WHERE name LIKE :q OR comment LIKE :q2
                         ORDER BY created_at DESC LIMIT 10"
                    );
                    $stmt->bindValue(':q', $like);
                    $stmt->bindValue(':q2', $like);
                    if ($stmt->execute()) {
                        while ($row = $stmt->fetch()) {
                            $results[] = $row;
                        }
                    }
                } catch (\Throwable $err) {
                    // Хүснэгт байхгүй (хагас суулгац) бол зүгээр өнгөрнө, гэхдээ
                    // жинхэнэ query алдааг dev горимд харуулж regression-ийг нуухгүй.
                    if (CODESAUR_DEVELOPMENT) {
                        \error_log('Search module query failed: ' . $err->getMessage());
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
            $this->respondJSON(['message' => $err->getMessage()], $err->getCode() ?: 500);
        }
    }
}
