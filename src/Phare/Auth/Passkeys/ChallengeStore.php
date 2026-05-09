<?php

declare(strict_types=1);

namespace Phare\Auth\Passkeys;

interface ChallengeStore
{
    public function put(string $key, string $challenge, int $ttlSeconds): void;

    public function get(string $key): ?string;

    public function forget(string $key): void;
}
