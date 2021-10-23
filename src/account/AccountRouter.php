<?php

namespace Raptor\Account;

use codesaur\Router\Router;

class AccountRouter extends Router
{
    function __construct()
    {
        $this->GET('/accounts', [AccountController::class, 'index'])->name('accounts');
    }
}
