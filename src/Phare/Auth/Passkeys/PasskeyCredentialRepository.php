<?php

declare(strict_types=1);

namespace Phare\Auth\Passkeys;

interface PasskeyCredentialRepository
{
    /**
     * Find a stored credential by its credential ID.
     *
     * @return array<string, mixed>|null
     */
    public function findByCredentialId(string $credentialId, string|int|null $userHandle = null): ?array;

    /**
     * Persist a new credential after a successful registration ceremony.
     *
     * @param  array<string, mixed>  $credential  Normalised credential data from the verifier
     */
    public function storeCredential(string|int|null $userHandle, array $credential): void;
}
