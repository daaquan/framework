<?php

namespace Phare\Foundation\Testing\Concerns;

use Phare\Testing\TestResponse;

use function Phare\Foundation\Testing\Concerns\msgpack_pack;
use function Phare\Foundation\Testing\Concerns\protobuf_pack;

trait MakesHttpRequests
{
    public function get(string $uri, array $headers = [])
    {
        return $this->call('GET', $uri, $headers);
    }

    public function post(string $uri, array $data = [], array $headers = [])
    {
        return $this->call('POST', $uri, $data, $headers);
    }

    public function put(string $uri, array $data = [], array $headers = [])
    {
        return $this->call('PUT', $uri, $data, $headers);
    }

    public function patch(string $uri, array $data = [], array $headers = [])
    {
        return $this->call('PATCH', $uri, $data, $headers);
    }

    public function delete(string $uri, array $data = [], array $headers = [])
    {
        return $this->call('DELETE', $uri, $data, $headers);
    }

    public function json(string $method, string $uri, array $data = [], array $headers = [])
    {
        $headers['Content-Type'] = 'application/json';

        return $this->call($method, $uri, json_encode($data), $headers);
    }

    public function msgpack(string $method, string $uri, array $data = [], array $headers = [])
    {
        $headers['Content-Type'] = 'application/x-msgpack';

        return $this->call($method, $uri, msgpack_pack($data), $headers);
    }

    public function protobuf(string $method, string $uri, array $data = [], array $headers = [])
    {
        $headers['Content-Type'] = 'application/x-protobuf';

        return $this->call($method, $uri, protobuf_pack($data), $headers);
    }

    /**
     * Execute the request and return the response.
     */
    public function call(string $method, string $uri, array $data = [], array $headers = [])
    {
        $this->setUpApplication();

        $this->transformHeadersToServerVars($uri, $method, $headers);
        $this->initializeRequestData($method, $data);

        /** @var \Phare\Contracts\Http\Kernel $kernel */
        $kernel = $this->app->make(\Phare\Contracts\Http\Kernel::class);

        /** @var \Phalcon\Http\Request $request */
        $request = $this->app->make(\Phalcon\Http\Request::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return new TestResponse($response);
    }

    /**
     * Initialize server variables.
     */
    protected function transformHeadersToServerVars(string $uri, string $method, array $headers = []): void
    {
        global $_SERVER;

        $_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'] = $_SERVER['HOST_NAME'] = $_SERVER['SERVER_ADDR']
            = parse_url($this->app['url']->getBaseUri(), PHP_URL_HOST);
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
        $_SERVER['REQUEST_TIME'] = (int)$_SERVER['REQUEST_TIME_FLOAT'];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        foreach ($headers as $key => $value) {
            if ($key === 'Content-Type') {
                $_SERVER['CONTENT_TYPE'] = $value;
            } elseif ($key === 'Content-Length') {
                $_SERVER['CONTENT_LENGTH'] = $value;
            }

            $key = str_replace('-', '_', strtoupper($key));
            $_SERVER['HTTP_' . $key] = $value;
        }
    }

    /**
     * Initialize request data.
     */
    protected function initializeRequestData(string $method, $data): void
    {
        global $_GET, $_POST, $_PUT, $_DELETE, $_PATCH, $_OPTIONS;

        switch ($method) {
            case 'GET':
                $_GET = $data;
                break;
            case 'POST':
                $_POST = $data;
                break;
            case 'PUT':
                $_PUT = $data;
                break;
            case 'DELETE':
                $_DELETE = $data;
                break;
            case 'PATCH':
                $_PATCH = $data;
                break;
            case 'OPTIONS':
                $_OPTIONS = $data;
                break;
        }

        $_REQUEST = $data;
    }
}
