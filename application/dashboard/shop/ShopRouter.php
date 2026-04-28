<?php

namespace Dashboard\Shop;

use codesaur\Router\Router;

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
        $this->GET('/dashboard/products', [ProductsController::class, 'index'])->name('products');
        $this->GET('/dashboard/products/list', [ProductsController::class, 'list'])->name('products-list');
        $this->GET_POST('/dashboard/products/insert', [ProductsController::class, 'insert'])->name('product-insert');
        $this->GET_PUT('/dashboard/products/{uint:id}', [ProductsController::class, 'update'])->name('product-update');
        $this->GET('/dashboard/products/view/{uint:id}', [ProductsController::class, 'view']);
        $this->DELETE('/dashboard/products/delete', [ProductsController::class, 'delete'])->name('product-delete');
        $this->DELETE('/dashboard/products/reset', [ProductsController::class, 'reset'])->name('products-sample-reset');

        // Reviews
        $this->GET_POST('/dashboard/products/reviews', [ReviewsController::class, 'index'])->name('products-reviews');
        $this->DELETE('/dashboard/products/reviews/delete', [ReviewsController::class, 'delete'])->name('products-reviews-delete');

        // Orders
        $this->GET('/dashboard/orders', [OrdersController::class, 'index'])->name('orders');
        $this->GET('/dashboard/orders/list', [OrdersController::class, 'list'])->name('orders-list');
        $this->GET('/dashboard/orders/view/{uint:id}', [OrdersController::class, 'view']);
        $this->PATCH('/dashboard/orders/{uint:id}/status', [OrdersController::class, 'updateStatus'])->name('order-status');
        $this->DELETE('/dashboard/orders/delete', [OrdersController::class, 'delete'])->name('order-delete');
    }
}
    