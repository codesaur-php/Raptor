<?php

namespace Raptor\Example;

/* DEV: v2.2021.08.20
 * 
 * This is an example script!
 */

define('CODESAUR_DEVELOPMENT', true);

ini_set('display_errors', 'On');
error_reporting(\E_ALL);

use codesaur\Http\Message\ServerRequest;

use Raptor\Application;
use Indoraptor\IndoApplication;

$autoload = require_once '../vendor/autoload.php';

(new Application())->handle(
    ((new ServerRequest())->initFromGlobal())
    ->withAttribute('indo', new IndoApplication()));
