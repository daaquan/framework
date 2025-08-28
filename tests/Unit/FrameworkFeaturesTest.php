<?php

use Phare\Collections\Collection;
use Phare\Http\Request;
use Phare\Http\Response;

it('demonstrates Laravel-style Collection features', function () {
    $collection = new Collection([
        ['name' => 'John', 'age' => 30],
        ['name' => 'Jane', 'age' => 25],
        ['name' => 'Bob', 'age' => 35],
    ]);

    // Test chaining Laravel-style methods
    $result = $collection
        ->filter(fn ($user) => $user['age'] > 25)
        ->sortBy(fn ($user) => $user['age'])
        ->pluck('name');

    expect($result->toArray())->toBe(['John', 'Bob']);
});

it('demonstrates Request helper methods', function () {
    $request = new class() extends Request
    {
        public function __construct()
        {
            $this->data = ['name' => 'John', 'email' => 'john@test.com', 'empty' => ''];
        }
    };

    expect($request->has('name'))->toBe(true);
    expect($request->filled('name'))->toBe(true);
    expect($request->filled('empty'))->toBe(false);
    expect($request->missing('nonexistent'))->toBe(true);
    expect($request->only(['name']))->toBe(['name' => 'John']);
    expect($request->except(['email']))->toHaveKey('name');
    expect($request->except(['email']))->not->toHaveKey('email');
});

it('demonstrates Response helper methods', function () {
    $response = new class() extends Response
    {
        protected array $statusCode = [200];

        protected array $headers = [];

        public function __construct() {}

        public function setStatusCode($code, $message = null)
        {
            $this->statusCode = [$code, $message];

            return $this;
        }

        public function setHeader($name, $value)
        {
            $this->headers[$name] = $value;

            return $this;
        }

        public function setJsonContent($data)
        {
            $this->content = json_encode($data);

            return $this;
        }

        public function setContentType($contentType, $charset = null)
        {
            $this->headers['Content-Type'] = $contentType . ($charset ? '; charset=' . $charset : '');

            return $this;
        }

        public function getCookies()
        {
            return new class()
            {
                public function set()
                {
                    return true;
                }
            };
        }

        public function getTestHeaders()
        {
            return $this->headers;
        }

        public function getTestStatusCode()
        {
            return $this->statusCode;
        }
    };

    $chainedResponse = $response
        ->status(201)
        ->header('X-Custom', 'test')
        ->json(['message' => 'success']);

    expect($chainedResponse->getTestStatusCode())->toBe([201, null]);
    expect($chainedResponse->getTestHeaders())->toHaveKey('X-Custom');
    expect($chainedResponse->getTestHeaders())->toHaveKey('Content-Type');
});

it('shows framework provides Laravel-like features', function () {
    // Collection methods work like Laravel Collections
    $numbers = new Collection([1, 2, 3, 4, 5]);
    expect($numbers->filter(fn ($n) => $n > 3)->sum())->toBe(9); // 4 + 5

    // Basic collection operations
    expect($numbers->count())->toBe(5);
    expect($numbers->first())->toBe(1);
    expect($numbers->last())->toBe(5);
});
