<?php

$router = new \Phare\Routing\Router();

$router->post('/', '\Tests\Mock\Http\Controllers\Api\IndexController@index')->name('index');

return $router;
