<?php

namespace Dashboard\Shop;

use codesaur\Router\Router;

/**
 * Class ReviewsRouter
 *
 * Бүтээгдэхүүний үнэлгээнүүдийн маршрутууд.
 *
 * @package Dashboard\Shop
 */
class ReviewsRouter extends Router
{
    public function __construct()
    {
        $this->GET('/dashboard/reviews', [ReviewsController::class, 'index'])->name('reviews');
        $this->GET('/dashboard/reviews/list', [ReviewsController::class, 'list'])->name('reviews-list');
        $this->GET('/dashboard/reviews/product/{uint:id}', [ReviewsController::class, 'view'])->name('reviews-view');
        $this->DELETE('/dashboard/reviews/deactivate', [ReviewsController::class, 'deactivate'])->name('reviews-deactivate');
    }
}
