<?php

use Phalcon\Http\RequestInterface;
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

/**
 * Create a minimal RequestInterface stub that only needs getHeader().
 * Using an anonymous class avoids the PHP 8.4 deprecation that Mockery triggers
 * when generating a full proxy for Phalcon\Http\RequestInterface (implicitly
 * nullable parameter in ::get()).
 *
 * All method signatures match the interface exactly (no declared return types
 * where the interface omits them, no extra methods).
 */
function makeSanctumRequest(string $authHeader): RequestInterface
{
    return new class($authHeader) implements RequestInterface {
        public function __construct(private string $authHeader) {}
        public function getHeader(string $header): string { return $header === 'Authorization' ? $this->authHeader : ''; }
        public function get(?string $name = null, $filters = null, $defaultValue = null, bool $notAllowEmpty = false, bool $noRecursive = false) { return null; }
        public function getPost(?string $name = null, $filters = null, $defaultValue = null, bool $notAllowEmpty = false, bool $noRecursive = false) { return null; }
        public function getPut(?string $name = null, $filters = null, $defaultValue = null, bool $notAllowEmpty = false, bool $noRecursive = false) { return null; }
        public function getQuery(?string $name = null, $filters = null, $defaultValue = null, bool $notAllowEmpty = false, bool $noRecursive = false) { return null; }
        public function getServer(string $name): ?string { return null; }
        public function has(string $name): bool { return false; }
        public function hasPost(string $name): bool { return false; }
        public function hasPut(string $name): bool { return false; }
        public function hasQuery(string $name): bool { return false; }
        public function hasServer(string $name): bool { return false; }
        public function hasHeader(string $header): bool { return false; }
        public function getHeaders(): array { return []; }
        public function getHTTPReferer(): string { return ''; }
        public function getAcceptableContent(): array { return []; }
        public function getBestAccept(): string { return ''; }
        public function getClientAddress(bool $trustForwardedHeader = false) { return false; }
        public function getClientCharsets(): array { return []; }
        public function getBestCharset(): string { return ''; }
        public function getLanguages(): array { return []; }
        public function getBestLanguage(): string { return ''; }
        public function getBasicAuth(): ?array { return null; }
        public function getDigestAuth(): array { return []; }
        public function getMethod(): string { return 'GET'; }
        public function getURI(bool $onlyPath = false): string { return '/'; }
        public function getHttpHost(): string { return ''; }
        public function getPort(): int { return 80; }
        public function getScheme(): string { return 'http'; }
        public function isAjax(): bool { return false; }
        public function isSoap(): bool { return false; }
        public function isGet(): bool { return false; }
        public function isPost(): bool { return false; }
        public function isPut(): bool { return false; }
        public function isHead(): bool { return false; }
        public function isDelete(): bool { return false; }
        public function isOptions(): bool { return false; }
        public function isPurge(): bool { return false; }
        public function isTrace(): bool { return false; }
        public function isConnect(): bool { return false; }
        public function isMethod($methods, bool $strict = false): bool { return false; }
        public function isSecure(): bool { return false; }
        public function getRawBody(): string { return ''; }
        public function getJsonRawBody(bool $associative = false) { return null; }
        public function getServerAddress(): string { return ''; }
        public function getServerName(): string { return ''; }
        public function getContentType(): ?string { return null; }
        public function getUserAgent(): string { return ''; }
        public function getUploadedFiles(bool $onlySuccessful = false, bool $namedKeys = false): array { return []; }
        public function hasFiles(): bool { return false; }
        public function numFiles(bool $onlySuccessful = false): int { return 0; }
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
