<?php

namespace Tests\Foundation;

use Phalcon\Di\Di;
use Phalcon\Http\Request;
use Phalcon\Http\Response;
use Phalcon\Mvc\Micro;
use Tests\Mock\app\Http\Controllers\Api\IndexController;
use Tests\TestCase;

class BinderTest extends TestCase
{
    public function test_controller()
    {
        $di = Di::getDefault();
        $di->setShared('router', function () {
            return new \Phalcon\Mvc\Router();
        });
        $di->setShared('request', function () {
            return new Request();
        });
        $di->setShared('response', function () {
            return new Response();
        });

        $app = new Micro($di);
        $app->notFound(function () {
            return '404 Not Found';
        });

        $collection = new Micro\Collection();
        $collection->setHandler(IndexController::class, true);
        $collection->get('/index/{id}', 'indexAction');

        $app->mount($collection);
        $response = $app->handle('/index/1?foo=bar');

        $this->assertIsArray($response);
    }
}
