<?php

declare(strict_types=1);

namespace Phare\Auth\Passkeys;

class InMemoryChallengeStore implements ChallengeStore
{
    /** @var array<string, array{challenge:string,expiresAt:int}> */
    private array $entries = [];

    public function put(string $key, string $challenge, int $ttlSeconds): void
    {
        $this->entries[$key] = [
            'challenge' => $challenge,
            'expiresAt' => time() + max(1, $ttlSeconds),
        ];
    }

    public function get(string $key): ?string
    {
        if (!isset($this->entries[$key])) {
            return null;
        }

        $entry = $this->entries[$key];
        if ($entry['expiresAt'] < time()) {
            unset($this->entries[$key]);

            return null;
        }

        return $entry['challenge'];
    }

    public function forget(string $key): void
    {
        unset($this->entries[$key]);
    }
}
