<?php

namespace Dashboard\Shop;

use Psr\Log\LogLevel;

/**
 * Class ReviewsController
 *
 * Бүтээгдэхүүний үнэлгээнүүдийг удирдах dashboard контроллер.
 *
 * @package Dashboard\Shop
 */
class ReviewsController extends \Raptor\Controller
{
    use \Raptor\Template\DashboardTrait;

    /**
     * Үнэлгээнүүдийн жагсаалт хуудас.
     *
     * @return void
     */
    public function index()
    {
        if (!$this->isUserCan('system_product_index')) {
            $this->dashboardProhibited(null, 401)->render();
            return;
        }

        $dashboard = $this->twigDashboard(__DIR__ . '/reviews-index.html');
        $dashboard->set('title', $this->text('reviews'));
        $dashboard->render();

        $this->log('products', LogLevel::NOTICE, 'Бүтээгдэхүүний үнэлгээнүүдийн жагсаалтыг үзэж байна', ['action' => 'review-index']);
    }

    /**
     * Үнэлгээнүүдийн жагсаалтыг JSON хэлбэрээр буцаах.
     *
     * @return void
     */
    public function list()
    {
        try {
            if (!$this->isUserCan('system_product_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $reviewsTable = (new ReviewsModel($this->pdo))->getName();
            $productsTable = (new ProductsModel($this->pdo))->getName();

            $stmt = $this->query(
                "SELECT r.id, r.product_id, r.name, r.email, r.rating, r.comment, r.created_at,
                        p.title as product_title
                 FROM $reviewsTable r
                 LEFT JOIN $productsTable p ON p.id=r.product_id
                 WHERE r.is_active=1
                 ORDER BY r.created_at DESC"
            );
            $rows = $stmt ? $stmt->fetchAll() : [];

            $this->respondJSON(['status' => 'success', 'list' => $rows]);
        } catch (\Throwable $e) {
            $this->respondJSON(['message' => $e->getMessage()], $e->getCode());
        }
    }

    /**
     * Product ID-аар тухайн бүтээгдэхүүний view руу reviews-д focus хийж чиглүүлэх.
     *
     * @param int $id Product ID
     * @return void
     */
    public function view(int $id)
    {
        $path = $this->getScriptPath();
        \header("Location: $path/dashboard/products/view/$id#reviews-section");
        exit;
    }

    /**
     * Үнэлгээг идэвхгүй болгох (soft delete).
     *
     * @return void
     */
    public function deactivate()
    {
        try {
            if (!$this->isUserCan('system_product_delete')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            $payload = $this->getParsedBody();
            $id = (int)($payload['id'] ?? 0);
            if (empty($id)) {
                throw new \Exception($this->text('no-record-selected'), 400);
            }

            $model = new ReviewsModel($this->pdo);
            $table = $model->getName();
            $record = $model->getRowWhere(['id' => $id]);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'), 404);
            }

            $this->exec("UPDATE $table SET is_active=0 WHERE id=$id");

            $this->respondJSON([
                'status' => 'success',
                'title' => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);

            $this->log('products', LogLevel::WARNING, '{record_id} бүтээгдэхүүний #{id} үнэлгээг устгалаа', ['action' => 'review-deactivate', 'record_id' => $record['product_id'], 'id' => $id]);

            $adminName = \trim(($this->getUser()->profile['first_name'] ?? '') . ' ' . ($this->getUser()->profile['last_name'] ?? ''));
            $appUrl = \rtrim((string)$this->getRequest()->getUri()->withPath($this->getScriptPath()), '/') . '/dashboard';
            $this->getService('discord')?->contentAction('review', 'delete', "#{$id} by {$record['name']}", (int)($record['product_id'] ?? 0), $adminName, $appUrl);
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status' => 'error',
                'title' => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        }
    }
}
