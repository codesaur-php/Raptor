<?php

namespace Raptor;

class Application extends \codesaur\Http\Application\Application
{
    public function __construct()
    {
        parent::__construct();
        
        $this->use(new Exception\ErrorHandler());
        $this->use(new Authentication\SessionMiddleware());
        $this->use(new Authentication\JWTAuthMiddleware());
        $this->use(new Localization\LocalizationMiddleware());
        $this->use(new Contents\SettingsMiddleware());

        $this->use(new Authentication\LoginRouter());
        $this->use(new Account\AccountRouter());
        $this->use(new Organization\OrganizationRouter());
        $this->use(new RBAC\RBACRouter());
        $this->use(new Localization\LocalizationRouter());
        $this->use(new Contents\ContentsRouter());
        $this->use(new File\FileRouter());
        $this->use(new Log\LogsRouter());
        
        $this->GET('/', [Dashboard\DashboardController::class, 'home'])->name('home');
    }
}
