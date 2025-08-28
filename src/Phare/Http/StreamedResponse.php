<?php

namespace Phare\Http;

use Phalcon\Http\Response;

class StreamedResponse extends Response
{
    protected \Closure $callback;
    protected bool $streamed = false;

    public function __construct(\Closure $callback, int $status = 200, array $headers = [])
    {
        parent::__construct();
        
        $this->callback = $callback;
        $this->setStatusCode($status);
        
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    public static function create(\Closure $callback, int $status = 200, array $headers = []): static
    {
        return new static($callback, $status, $headers);
    }

    public function send(): static
    {
        if ($this->streamed) {
            return $this;
        }

        $this->streamed = true;

        // Send headers
        if (!headers_sent()) {
            http_response_code($this->getStatusCode());
            
            foreach ($this->getHeaders() as $header) {
                header($header->getName() . ': ' . $header->getValue());
            }
        }

        // Execute callback
        call_user_func($this->callback);

        return $this;
    }

    public function setCallback(\Closure $callback): static
    {
        $this->callback = $callback;
        return $this;
    }

    public function isStreamed(): bool
    {
        return $this->streamed;
    }
}