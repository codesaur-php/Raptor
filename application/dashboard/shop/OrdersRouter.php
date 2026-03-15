<?php

namespace Dashboard\Shop;

use codesaur\Router\Router;

/**
 * Class OrdersRouter
 *
 * Захиалгын модулийн маршрутуудыг бүртгэх Router.
 *
 * @package Dashboard\Shop
 */
class OrdersRouter extends Router
{
    /**
     * Захиалгын модулийн маршрутуудыг бүртгэх.
     */
    public function __construct()
    {
        $this->GET('/dashboard/orders', [OrdersController::class, 'index'])->name('orders');
        $this->GET('/dashboard/orders/list', [OrdersController::class, 'list'])->name('orders-list');
        $this->GET('/dashboard/orders/view/{uint:id}', [OrdersController::class, 'view']);
        $this->PUT('/dashboard/orders/{uint:id}/status', [OrdersController::class, 'updateStatus'])->name('order-status');
        $this->DELETE('/dashboard/orders/deactivate', [OrdersController::class, 'deactivate'])->name('order-deactivate');
    }
}
