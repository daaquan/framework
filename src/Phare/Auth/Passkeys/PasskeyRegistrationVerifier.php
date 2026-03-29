<?php

declare(strict_types=1);

namespace Phare\Auth\Passkeys;

interface PasskeyRegistrationVerifier
{
    /**
     * Verify a passkey registration (attestation) response.
     *
     * Returns a normalised credential array on success, or null on failure.
     *
     * @param  array<string, mixed>  $attestation   Parsed client response
     * @param  string                $expectedChallenge  Raw challenge bytes
     * @return array<string, mixed>|null  Credential data (credential_id, public_key, counter, …)
     */
    public function verify(array $attestation, string $expectedChallenge): ?array;
}
