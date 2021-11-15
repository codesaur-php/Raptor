<?php

namespace Raptor;

class Application extends \codesaur\Http\Application\Application
{
    function __construct()
    {
        parent::__construct();
        
        $this->use(new Dashboard\BaseRouter());
        $this->use(new Authentication\LoginRouter());
        $this->use(new Account\AccountRouter());
        $this->use(new Log\LogsRouter());
    }
}
