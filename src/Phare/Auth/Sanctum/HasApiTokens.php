<?php

namespace Phare\Auth\Sanctum;

trait HasApiTokens
{
    protected ?PersonalAccessToken $accessToken = null;

    public function tokens()
    {
        return $this->morphMany(Sanctum::$personalAccessTokenModel, 'tokenable');
    }

    public function createToken(string $name, array $abilities = ['*']): NewAccessToken
    {
        $token = $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken = $this->generateTokenString()),
            'abilities' => $abilities,
        ]);

        return new NewAccessToken($token, $token->getKey() . '|' . $plainTextToken);
    }

    protected function generateTokenString(): string
    {
        return sprintf(
            '%s%s%s',
            config('app.key'),
            time(),
            bin2hex(random_bytes(32))
        );
    }

    public function withAccessToken(PersonalAccessToken $accessToken): self
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function currentAccessToken(): ?PersonalAccessToken
    {
        return $this->accessToken;
    }

    public function tokenCan(string $ability): bool
    {
        return $this->accessToken ? $this->accessToken->can($ability) : false;
    }

    public function tokenCant(string $ability): bool
    {
        return !$this->tokenCan($ability);
    }

    public function createPlainTextToken(string $name, array $abilities = ['*']): string
    {
        $plainTextToken = $this->generateTokenString();

        $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
        ]);

        return $plainTextToken;
    }

    public function revokeCurrentToken(): void
    {
        if ($this->currentAccessToken()) {
            $this->currentAccessToken()->delete();
        }
    }

    public function revokeAllTokens(): void
    {
        $this->tokens()->delete();
    }

    public function revokeTokensExceptCurrent(): void
    {
        $currentToken = $this->currentAccessToken();

        if ($currentToken) {
            $this->tokens()->where('id', '!=', $currentToken->id)->delete();
        }
    }
}
