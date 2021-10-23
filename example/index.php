<?php

namespace Raptor\Example;

/* DEV: v2.2021.08.20
 * 
 * This is an example script!
 */

define('CODESAUR_DEVELOPMENT', true);

ini_set('display_errors', 'On');
error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE);

use codesaur\Http\Message\ServerRequest;

use Indoraptor\IndoApplication;
use Indoraptor\PDOConnectMiddleware;

use Raptor\Application;
use Raptor\Exception\ErrorHandler;
use Raptor\Authentication\SessionMiddleware;
use Raptor\Authentication\JWTAuthMiddleware;
use Raptor\Authentication\LocalizationMiddleware;

$autoload = require_once '../vendor/autoload.php';

$application = new class extends Application
{
    function __construct()
    {
        parent::__construct();
        
        $this->use(new ErrorHandler());

        $this->use(function ($request, $handler)
        {
            $indo = new IndoApplication();
            $indo->use(new PDOConnectMiddleware());
            return $handler->handle($request->withAttribute('indo', $indo));
        });

        $this->use(new SessionMiddleware());
        $this->use(new JWTAuthMiddleware());
        $this->use(new LocalizationMiddleware());
    }
};

$application->handle((new ServerRequest())->initFromGlobal());
