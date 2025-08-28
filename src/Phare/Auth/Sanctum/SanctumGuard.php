<?php

namespace Phare\Auth\Sanctum;

use Phalcon\Http\RequestInterface;

class SanctumGuard
{
    protected RequestInterface $request;

    protected mixed $provider;

    public function __construct(RequestInterface $request, mixed $provider = null)
    {
        $this->request = $request;
        $this->provider = $provider;
    }

    public function user(): mixed
    {
        if ($token = $this->getTokenFromRequest()) {
            $accessToken = $this->findAccessToken($token);

            if ($accessToken && !$accessToken->isExpired()) {
                $user = $accessToken->tokenable;

                if ($user) {
                    $accessToken->touch();

                    return $user->withAccessToken($accessToken);
                }
            }
        }

        return null;
    }

    public function validate(array $credentials = []): bool
    {
        return !is_null($this->user());
    }

    protected function getTokenFromRequest(): ?string
    {
        $token = $this->request->getHeader('Authorization');

        if (!$token) {
            return null;
        }

        if (str_starts_with($token, 'Bearer ')) {
            return substr($token, 7);
        }

        return null;
    }

    protected function findAccessToken(string $token): ?PersonalAccessToken
    {
        return Sanctum::findToken($token);
    }

    public function check(): bool
    {
        return !is_null($this->user());
    }

    public function guest(): bool
    {
        return is_null($this->user());
    }

    public function id(): mixed
    {
        if ($user = $this->user()) {
            return $user->getKey();
        }

        return null;
    }

    public function setUser(mixed $user): void
    {
        // Not implemented for API guard
    }
}
