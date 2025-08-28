<?php

namespace Phare\Http;

class Response extends \Phalcon\Http\Response implements \Phare\Contracts\Http\Response
{
    public function json(mixed $data, int $status = 200, array $headers = []): static
    {
        $this->setStatusCode($status)
             ->setJsonContent($data)
             ->setContentType('application/json', 'UTF-8');

        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }

        return $this;
    }

    public function status(int $status): static
    {
        $this->setStatusCode($status);
        return $this;
    }

    public function cookie(string $name, mixed $value = null, int $expire = 0, string $path = '/', ?string $domain = null, bool $secure = false, bool $httponly = true): static
    {
        $this->getCookies()->set($name, $value, $expire, $path, $secure, $domain, $httponly);
        return $this;
    }

    public function withHeaders(array $headers): static
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
        return $this;
    }

    public function header(string $name, mixed $value): static
    {
        $this->setHeader($name, $value);
        return $this;
    }

    public function view(string $view, array $data = []): static
    {
        // This would need integration with the view system
        // For now, just set the content
        $this->setContent("View: $view with data: " . json_encode($data));
        return $this;
    }

    public function redirect($location = null, bool $externalRedirect = false, int $statusCode = 302): \Phalcon\Http\ResponseInterface
    {
        return parent::redirect($location, $externalRedirect, $statusCode);
    }

    public function back(int $status = 302): \Phalcon\Http\ResponseInterface
    {
        // Get the referrer from the request or use a default fallback
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        return $this->redirect($referer, false, $status);
    }

    /**
     * Convenience method for Laravel-style redirects
     */
    public function redirectTo(string $location, int $status = 302): \Phalcon\Http\ResponseInterface
    {
        return $this->redirect($location, false, $status);
    }
}
