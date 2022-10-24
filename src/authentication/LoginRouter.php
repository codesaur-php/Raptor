<?php

namespace Raptor\Authentication;

use codesaur\Router\Router;

class LoginRouter extends Router
{
    function __construct()
    {
        $this->GET('/login', [LoginController::class, 'index'])->name('login');
        $this->POST('/login/try', [LoginController::class, 'entry'])->name('entry');
        $this->GET('/login/logout', [LoginController::class, 'logout'])->name('logout');
        $this->POST('/login/signup', [LoginController::class, 'signup'])->name('signup');
        $this->GET('/login/language/{language}', [LoginController::class, 'language'])->name('language');
        $this->PUT('/login/set/password', [LoginController::class, 'setPassword'])->name('login-set-password');
        $this->POST('/login/request/password', [LoginController::class, 'requestPassword'])->name('login-request-password');
        $this->GET('/login/organization/{uint:id}', [LoginController::class, 'selectOrganization'])->name('login-select-organization');
    }
}
