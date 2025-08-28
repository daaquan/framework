<?php

namespace Phare\Auth\Sanctum;

class NewAccessToken
{
    public PersonalAccessToken $accessToken;

    public string $plainTextToken;

    public function __construct(PersonalAccessToken $accessToken, string $plainTextToken)
    {
        $this->accessToken = $accessToken;
        $this->plainTextToken = $plainTextToken;
    }

    public function toArray(): array
    {
        return [
            'accessToken' => $this->accessToken,
            'plainTextToken' => $this->plainTextToken,
        ];
    }

    public function __toString(): string
    {
        return $this->plainTextToken;
    }
}
