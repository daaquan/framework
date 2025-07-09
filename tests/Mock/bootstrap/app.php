<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use Phare\Foundation\Micro as Application;

if (!defined('APP_RUNNING_UNIT_TEST')) {
    define('APP_RUNNING_UNIT_TEST', true);
}

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| Here we will load the environment and create the application instance
| that serves as the central piece of this framework. We'll use this
| application as an "IoC" container and router for this framework.
|
*/

return new Application(dirname(__DIR__));
