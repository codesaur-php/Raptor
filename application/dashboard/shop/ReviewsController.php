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
     * Үнэлгээнүүдийн жагсаалт.
     *
     *   GET  -> dashboard HTML хуудас
     *   POST -> JSON жагсаалт (AJAX-д зориулагдсан)
     *
     * @return void
     */
    public function index()
    {
        $isPost = $this->getRequest()->getMethod() === 'POST';

        try {
            if (!$this->isUserCan('system_product_index')) {
                throw new \Exception($this->text('system-no-permission'), 401);
            }

            if ($isPost) {
                $reviewsTable = (new ReviewsModel($this->pdo))->getName();
                $productsTable = (new ProductsModel($this->pdo))->getName();
                $stmt = $this->query(
                    "SELECT r.id, r.product_id, r.name, r.email, r.rating, r.comment, r.created_at,
                            p.title as product_title
                     FROM $reviewsTable r
                     LEFT JOIN $productsTable p ON p.id=r.product_id
                     ORDER BY r.created_at DESC"
                );
                $rows = $stmt ? $stmt->fetchAll() : [];
                $this->respondJSON(['status' => 'success', 'list' => $rows]);
                return;
            }

            $settings = $this->getAttribute('settings', []);
            $dashboard = $this->dashboardTemplate(__DIR__ . '/reviews-index.html', [
                'review_email_notify' => !empty($_ENV['RAPTOR_REVIEW_EMAIL_TO'] ?? ''),
                'notify_email' => $_ENV['RAPTOR_REVIEW_EMAIL_TO'] ?? '',
                'settings_email' => $settings['email'] ?? ''
            ]);
            $dashboard->set('title', $this->text('reviews'));
            $dashboard->render();

            $this->log('products', LogLevel::NOTICE, 'Бүтээгдэхүүний үнэлгээнүүдийн жагсаалтыг үзэж байна', ['action' => 'review-index']);
        } catch (\Throwable $e) {
            if ($isPost) {
                $this->respondJSON(['message' => $e->getMessage()], $e->getCode() ?: 500);
            } else {
                $this->dashboardProhibited($e->getMessage(), $e->getCode())->render();
            }
        }
    }

    /**
     * Үнэлгээг устгах.
     *
     * @return void
     */
    public function delete()
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
            $record = $model->getById($id);
            if (empty($record)) {
                throw new \Exception($this->text('no-record-selected'), 404);
            }

            $model->deleteById($id);
            (new \Raptor\Trash\TrashModel($this->pdo))->store(
                'products', $model->getName(), $id, $record, $this->getUserId()
            );

            $this->respondJSON([
                'status' => 'success',
                'title' => $this->text('success'),
                'message' => $this->text('record-successfully-deleted')
            ]);

            $this->log('products', LogLevel::WARNING, '{record_id} бүтээгдэхүүний #{id} ({name}) үнэлгээг устгалаа', ['action' => 'review-delete', 'record_id' => $record['product_id'], 'id' => $id, 'name' => $record['name']]);

            $this->dispatch(new \Raptor\Notification\ContentEvent(
                'delete', 'review', "#{$id} by {$record['name']}", (int)($record['product_id'] ?? 0)
            ));
        } catch (\Throwable $err) {
            $this->respondJSON([
                'status' => 'error',
                'title' => $this->text('error'),
                'message' => $err->getMessage()
            ], $err->getCode());
        }
    }
}
