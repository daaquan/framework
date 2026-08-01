<?php

use Phalcon\Http\RequestInterface;
use Phare\Contracts\Http\Validation\Validator;
use Phare\Http\Request;
use Phare\Routing\ControllerActionParameterResolver;
use Phare\Validation\MessageBag;

class FakePassingValidator implements Validator
{
    public static function make(array $data, array $rules, array $messages = [], array $customAttributes = []): Validator
    {
        return new static();
    }

    public function passes(): bool
    {
        return true;
    }

    public function fails(): bool
    {
        return false;
    }

    public function errors(): MessageBag
    {
        return new MessageBag();
    }

    public function validated(): array
    {
        return ['ok' => true];
    }

    public function safe(): array
    {
        return $this->validated();
    }

    public function validate(array $data): bool
    {
        return true;
    }

    public function getMessages(): array
    {
        return [];
    }

    public function all(): array
    {
        return ['ok' => true];
    }
}

class FakeFailingValidator implements Validator
{
    public static function make(array $data, array $rules, array $messages = [], array $customAttributes = []): Validator
    {
        return new static();
    }

    public function passes(): bool
    {
        return false;
    }

    public function fails(): bool
    {
        return true;
    }

    public function errors(): MessageBag
    {
        return new MessageBag(['message' => ['invalid']]);
    }

    public function validated(): array
    {
        return ['ok' => false];
    }

    public function safe(): array
    {
        return $this->validated();
    }

    public function validate(array $data): bool
    {
        return false;
    }

    public function getMessages(): array
    {
        return ['message' => 'invalid'];
    }

    public function all(): array
    {
        return ['ok' => false];
    }
}

it('resolves untyped and scalar parameters from url params', function () {
    $resolver = new ControllerActionParameterResolver();

    $resolved = $resolver->resolve(
        [null, 'int', 'bool', 'float', 'string'],
        ['first' => '42', 'second' => '1', 'third' => 'true', 'fourth' => '3.14', 'fifth' => 'alice'],
        fn (string $type) => new $type(),
        fn (RequestInterface $request) => null
    );

    expect($resolved[0])->toBe('42');
    expect($resolved[1])->toBe(1);
    expect($resolved[2])->toBeTrue();
    expect($resolved[3])->toBe(3.14);
    expect($resolved[4])->toBe('alice');
});

it('resolves object parameters via factory callback', function () {
    $resolver = new ControllerActionParameterResolver();

    $resolved = $resolver->resolve(
        [FakePassingValidator::class],
        [],
        fn (string $type) => new $type(),
        fn (RequestInterface $request) => null
    );

    expect($resolved)->toHaveCount(1);
    expect($resolved[0])->toBeInstanceOf(FakePassingValidator::class);
});

it('throws when validator resolution fails validation', function () {
    $resolver = new ControllerActionParameterResolver();

    expect(function () use ($resolver) {
        $resolver->resolve(
            [FakeFailingValidator::class],
            [],
            fn (string $type) => new $type(),
            fn (RequestInterface $request) => null
        );
    })->toThrow(RuntimeException::class, 'Request validation failed. invalid');
});

it('invokes request callback when resolved instance is request interface', function () {
    $resolver = new ControllerActionParameterResolver();
    $request = new Request();
    $called = false;

    $resolved = $resolver->resolve(
        [RequestInterface::class],
        [],
        fn (string $type) => $request,
        function (RequestInterface $resolvedRequest) use (&$called, $request) {
            $called = ($resolvedRequest === $request);
        }
    );

    expect($resolved[0])->toBe($request);
    expect($called)->toBeTrue();
});
