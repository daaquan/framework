<?php

use Phare\Routing\Router;

$router = new Router();

$router->post('/', '\Tests\Mock\Http\Controllers\Api\IndexController@index')->name('index');

return $router;
