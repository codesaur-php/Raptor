<?php

namespace Raptor;

class Application extends \codesaur\Http\Application\Application
{
    function __construct()
    {
        parent::__construct();
        
        $this->use(new Exception\ErrorHandler());
        $this->use(new Authentication\SessionMiddleware());
        $this->use(new Localization\LocalizationMiddleware());

        $this->use(new Authentication\LoginRouter());
        $this->use(new Account\AccountRouter());
        $this->use(new Localization\LocalizationRouter());
        $this->use(new Contents\ContentsRouter());
        $this->use(new Log\LogsRouter());
        
        $this->GET('/', [Dashboard\DashboardController::class, 'index'])->name('home');
    }
}
