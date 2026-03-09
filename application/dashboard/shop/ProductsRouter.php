<?php

namespace Dashboard\Shop;

use codesaur\Router\Router;

/**
 * Class ProductsRouter
 *
 * Бүтээгдэхүүний модулийн маршрутуудыг бүртгэх Router.
 *
 * @package Dashboard\Shop
 */
class ProductsRouter extends Router
{
    public function __construct()
    {
        $this->GET('/dashboard/products', [ProductsController::class, 'index'])->name('products');
        $this->GET('/dashboard/products/list', [ProductsController::class, 'list'])->name('products-list');
        $this->GET_POST('/dashboard/products/insert', [ProductsController::class, 'insert'])->name('product-insert');
        $this->GET_PUT('/dashboard/products/{uint:id}', [ProductsController::class, 'update'])->name('product-update');
        $this->GET('/dashboard/products/read/{slug}', [ProductsController::class, 'read'])->name('product-read');
        $this->GET('/dashboard/products/view/{uint:id}', [ProductsController::class, 'view'])->name('product-view');
        $this->DELETE('/dashboard/products/deactivate', [ProductsController::class, 'deactivate'])->name('product-deactivate');
        $this->DELETE('/dashboard/products/reset', [ProductsController::class, 'reset'])->name('products-sample-reset');
    }
}
