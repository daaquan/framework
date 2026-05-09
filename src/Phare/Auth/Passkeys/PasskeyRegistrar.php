<?php

declare(strict_types=1);

namespace Phare\Auth\Passkeys;

use RuntimeException;

/**
 * Handles the WebAuthn registration ceremony (create credential).
 *
 * Usage:
 *   1. `$registrar->begin($userId, $username)` → send options to browser
 *   2. `$registrar->complete($attestation, $userId)` → verify + store credential
 */
class PasskeyRegistrar
{
    public function __construct(
        private PasskeyCredentialRepository $credentials,
        private PasskeyRegistrationVerifier $verifier,
        private ChallengeStore $challengeStore,
        private int $challengeTtlSeconds = 300
    ) {}

    /**
     * Begin registration: generate a challenge and return options for the browser.
     *
     * @return array{challenge:string,expiresIn:int,userHandle:string|int|null,userName:string}
     */
    public function begin(string|int|null $userHandle, string $userName): array
    {
        $challenge = random_bytes(32);
        $this->challengeStore->put($this->challengeKey($userHandle), $challenge, $this->challengeTtlSeconds);

        return [
            'challenge'  => $this->base64UrlEncode($challenge),
            'expiresIn'  => $this->challengeTtlSeconds,
            'userHandle' => $userHandle,
            'userName'   => $userName,
        ];
    }

    /**
     * Complete registration: verify the attestation and persist the credential.
     *
     * Returns the stored credential data on success, throws on invalid challenge,
     * and returns null when the verifier rejects the attestation.
     *
     * @param  array<string, mixed>  $attestation
     * @return array<string, mixed>|null
     */
    public function complete(array $attestation, string|int|null $userHandle = null): ?array
    {
        $expectedChallenge = $this->challengeStore->get($this->challengeKey($userHandle));

        if ($expectedChallenge === null) {
            throw new RuntimeException('Passkey registration challenge is missing or expired.');
        }

        $credential = $this->verifier->verify($attestation, $expectedChallenge);

        if ($credential !== null) {
            $this->challengeStore->forget($this->challengeKey($userHandle));
            $this->credentials->storeCredential($userHandle, $credential);
        }

        return $credential;
    }

    private function challengeKey(string|int|null $userHandle): string
    {
        return 'passkey:register:' . (string) ($userHandle ?? 'anonymous');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
