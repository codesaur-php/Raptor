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
use Raptor\Authentication\JWTAuthMiddleware;

$autoload = require_once '../vendor/autoload.php';

$request = (new ServerRequest())->initFromGlobal();

$indo = new IndoApplication();
$indo->use(new PDOConnectMiddleware());

$application = new Application();
$application->use(new JWTAuthMiddleware());
$application->handle($request->withAttribute('indo', $indo));
