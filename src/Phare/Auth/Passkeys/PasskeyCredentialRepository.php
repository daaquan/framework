<?php

declare(strict_types=1);

namespace Phare\Auth\Passkeys;

interface PasskeyCredentialRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByCredentialId(string $credentialId, string|int|null $userHandle = null): ?array;
}
