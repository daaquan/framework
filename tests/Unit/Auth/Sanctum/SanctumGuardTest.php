<?php

use Phalcon\Http\Request;
use Phalcon\Http\RequestInterface;
use Phare\Auth\Sanctum\Middleware\EnsureFrontendRequestsAreStateful;
use Phare\Auth\Sanctum\PersonalAccessToken;
use Phare\Auth\Sanctum\SanctumGuard;

class SanctumGuardTestToken extends PersonalAccessToken
{
    public bool $expired = false;

    public bool $touched = false;

    public mixed $tokenable = null;

    public function isExpired(): bool
    {
        return $this->expired;
    }

    public function touch(?string $attribute = null): bool
    {
        $this->touched = true;

        return true;
    }
}

function makeSanctumGuardTestToken(): SanctumGuardTestToken
{
    /** @var SanctumGuardTestToken $token */
    $token = (new ReflectionClass(SanctumGuardTestToken::class))->newInstanceWithoutConstructor();
    $token->expired = false;
    $token->touched = false;
    $token->tokenable = null;

    return $token;
}

class SanctumGuardTestUser
{
    public function __construct(private int $id) {}

    public function withAccessToken(PersonalAccessToken $token): self
    {
        return $this;
    }

    public function getKey(): int
    {
        return $this->id;
    }
}

class TestableSanctumGuard extends SanctumGuard
{
    public ?PersonalAccessToken $nextToken = null;

    protected function findAccessToken(string $token): ?PersonalAccessToken
    {
        return $this->nextToken;
    }
}

class TestableStatefulMiddleware extends EnsureFrontendRequestsAreStateful
{
    public function __construct(private array $domains) {}

    public function isFromFrontend(RequestInterface $request): bool
    {
        return $this->fromFrontend($request);
    }

    protected function statefulDomains(): array
    {
        return $this->domains;
    }
}

function makeSanctumRequest(string $authHeader = '', array $headers = []): RequestInterface
{
    return new class($authHeader, $headers) extends Request
    {
        public function __construct(private string $authHeader, private array $headers) {}

        public function getHeader(string $header): string
        {
            return $header === 'Authorization'
                ? $this->authHeader
                : (string)($this->headers[$header] ?? '');
        }
    };
}

test('sanctum guard can authenticate user with valid token', function () {
    $request = makeSanctumRequest('Bearer valid-token');

    $user = new SanctumGuardTestUser(123);
    $token = makeSanctumGuardTestToken();
    $token->tokenable = $user;

    $guard = new TestableSanctumGuard($request);
    $guard->nextToken = $token;

    $authenticatedUser = $guard->user();

    expect($authenticatedUser)->toBe($user);
    expect($token->touched)->toBeTrue();
});

test('sanctum guard returns null for invalid token', function () {
    $request = makeSanctumRequest('Bearer invalid-token');

    $guard = new TestableSanctumGuard($request);
    $guard->nextToken = null;

    expect($guard->user())->toBeNull();
});

test('sanctum guard returns null for expired token', function () {
    $request = makeSanctumRequest('Bearer expired-token');

    $token = makeSanctumGuardTestToken();
    $token->expired = true;

    $guard = new TestableSanctumGuard($request);
    $guard->nextToken = $token;

    expect($guard->user())->toBeNull();
});

test('sanctum guard returns null without authorization header', function () {
    $request = makeSanctumRequest('');

    $guard = new TestableSanctumGuard($request);

    expect($guard->user())->toBeNull();
});

test('sanctum guard can validate credentials', function () {
    $request = makeSanctumRequest('Bearer valid-token');

    $user = new SanctumGuardTestUser(123);
    $token = makeSanctumGuardTestToken();
    $token->tokenable = $user;

    $guard = new TestableSanctumGuard($request);
    $guard->nextToken = $token;

    expect($guard->validate())->toBeTrue();
    expect($guard->check())->toBeTrue();
    expect($guard->guest())->toBeFalse();
});

test('sanctum guard can get user id', function () {
    $request = makeSanctumRequest('Bearer valid-token');

    $user = new SanctumGuardTestUser(123);
    $token = makeSanctumGuardTestToken();
    $token->tokenable = $user;

    $guard = new TestableSanctumGuard($request);
    $guard->nextToken = $token;

    expect($guard->id())->toBe(123);
});

test('sanctum guard handles malformed bearer token', function () {
    $request = makeSanctumRequest('InvalidFormat token');

    $guard = new TestableSanctumGuard($request);

    expect($guard->user())->toBeNull();
});

test('stateful middleware ignores malformed origin headers', function () {
    $request = makeSanctumRequest('');
    $middleware = new TestableStatefulMiddleware(['example.com']);

    expect($middleware->isFromFrontend($request))->toBeFalse();
});

test('stateful middleware matches referer and subdomains case-insensitively', function () {
    $request = makeSanctumRequest(headers: ['referer' => 'https://API.Example.com/account']);
    $middleware = new TestableStatefulMiddleware(['example.com']);

    expect($middleware->isFromFrontend($request))->toBeTrue();
});
