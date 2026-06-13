<?php

namespace Dashboard\Shop;

use codesaur\Router\Router;

use Raptor\CsrfMiddleware;

/**
 * Class ShopRouter
 *
 * Дэлгүүрийн модулийн бүх маршрутыг нэг дор бүртгэх Router.
 * Products, Orders, Reviews гурван дэд хэсэгтэй.
 *
 *   // Products
 *   GET       /dashboard/products                    -> Жагсаалт
 *   GET       /dashboard/products/list               -> JSON жагсаалт
 *   GET|POST  /dashboard/products/insert             -> Шинээр үүсгэх
 *   GET|PUT   /dashboard/products/{id}               -> Засах
 *   GET       /dashboard/products/view/{id}          -> Дэлгэрэнгүй
 *   DELETE    /dashboard/products/delete             -> Устгах
 *   DELETE    /dashboard/products/reset              -> Sample data reset
 *
 *   // Orders
 *   GET       /dashboard/orders                      -> Жагсаалт
 *   GET       /dashboard/orders/list                 -> JSON жагсаалт
 *   GET       /dashboard/orders/view/{id}            -> Дэлгэрэнгүй
 *   PATCH     /dashboard/orders/{id}/status          -> Статус шинэчлэх
 *   DELETE    /dashboard/orders/delete               -> Устгах
 *
 *   // Reviews
 *   GET       /dashboard/products/reviews            -> Dashboard HTML хуудас (index)
 *   POST      /dashboard/products/reviews            -> JSON жагсаалт (index)
 *   DELETE    /dashboard/products/reviews/delete     -> Устгах (delete)
 *
 * @package Dashboard\Shop
 */
class ShopRouter extends Router
{
    public function __construct()
    {
        // Products
        $this->GET('/products', [ProductsController::class, 'index'])->name('products');
        $this->GET('/products/list', [ProductsController::class, 'list'])->name('products-list');
        $this->GET_POST('/products/insert', [ProductsController::class, 'insert'])->name('product-insert')->middleware([CsrfMiddleware::class]);
        $this->GET_PUT('/products/{uint:id}', [ProductsController::class, 'update'])->name('product-update')->middleware([CsrfMiddleware::class]);
        $this->GET('/products/view/{uint:id}', [ProductsController::class, 'view'])->name('product-view');
        $this->DELETE('/products/delete', [ProductsController::class, 'delete'])->name('product-delete')->middleware([CsrfMiddleware::class]);
        $this->DELETE('/products/reset', [ProductsController::class, 'reset'])->name('products-sample-reset')->middleware([CsrfMiddleware::class]);

        // Reviews
        $this->GET_POST('/products/reviews', [ReviewsController::class, 'index'])->name('products-reviews')->middleware([CsrfMiddleware::class]);
        $this->DELETE('/products/reviews/delete', [ReviewsController::class, 'delete'])->name('products-reviews-delete')->middleware([CsrfMiddleware::class]);

        // Orders
        $this->GET('/orders', [OrdersController::class, 'index'])->name('orders');
        $this->GET('/orders/list', [OrdersController::class, 'list'])->name('orders-list');
        $this->GET('/orders/view/{uint:id}', [OrdersController::class, 'view'])->name('order-view');
        $this->PATCH('/orders/{uint:id}/status', [OrdersController::class, 'updateStatus'])->name('order-status')->middleware([CsrfMiddleware::class]);
        $this->DELETE('/orders/delete', [OrdersController::class, 'delete'])->name('order-delete')->middleware([CsrfMiddleware::class]);
    }
}
    