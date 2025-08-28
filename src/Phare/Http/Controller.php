<?php

namespace Phare\Http;

use Phalcon\Mvc\Controller as BaseController;

/**
 * Base controller providing convenient access to services.
 */
abstract class Controller extends BaseController
{
    /**
     * Retrieve a configuration value using dot notation.
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->di->getShared('config')->get($key, $default);
    }

    /**
     * Return the cache repository instance.
     */
    protected function cache(): \Phare\Foundation\Cache
    {
        return $this->di->getShared('cache');
    }

    /**
     * Get the request instance.
     */
    protected function request(): Request
    {
        return $this->di->getShared('request');
    }

    /**
     * Get the response instance.
     */
    protected function response(): Response
    {
        return $this->di->getShared('response');
    }

    /**
     * Validate the given request with the given rules.
     */
    protected function validate(array $rules, array $messages = []): array
    {
        $request = $this->request();
        $validator = new Request($rules);

        if (!$validator->validate($request->all())) {
            throw new \Phare\Foundation\Http\Validation\ValidationException(
                'Validation failed: ' . json_encode($validator->getMessages())
            );
        }

        return $request->all();
    }

    /**
     * Return a JSON response.
     */
    protected function json(mixed $data, int $status = 200, array $headers = []): Response
    {
        return $this->response()->json($data, $status, $headers);
    }

    /**
     * Redirect to a given URL.
     */
    protected function redirect(string $url, int $status = 302): Response
    {
        return $this->response()->redirect($url, $status);
    }

    /**
     * Redirect back to the previous location.
     */
    protected function back(int $status = 302): Response
    {
        return $this->response()->back($status);
    }

    /**
     * Return a view response.
     */
    protected function view(string $view, array $data = []): Response
    {
        return $this->response()->view($view, $data);
    }

    /**
     * Abort the request with a given status code.
     */
    protected function abort(int $code, string $message = '', array $headers = []): never
    {
        $this->response()
            ->setStatusCode($code)
            ->setContent($message)
            ->withHeaders($headers)
            ->send();
        exit;
    }
}
