<?php

declare(strict_types=1);

namespace Phare\Auth\Passkeys;

use RuntimeException;

class PasskeyAuthenticator
{
    public function __construct(
        private PasskeyCredentialRepository $credentials,
        private PasskeyAssertionVerifier $verifier,
        private ChallengeStore $challengeStore,
        private int $challengeTtlSeconds = 300
    ) {}

    /**
     * @return array{challenge:string,expiresIn:int,userHandle:string|int|null}
     */
    public function begin(string|int|null $userHandle = null): array
    {
        $challenge = random_bytes(32);
        $this->challengeStore->put($this->challengeKey($userHandle), $challenge, $this->challengeTtlSeconds);

        return [
            'challenge' => $this->base64UrlEncode($challenge),
            'expiresIn' => $this->challengeTtlSeconds,
            'userHandle' => $userHandle,
        ];
    }

    /**
     * @param array<string, mixed> $assertion
     */
    public function verify(array $assertion, string|int|null $userHandle = null): bool
    {
        $credentialId = $assertion['rawId'] ?? null;
        if (!is_string($credentialId) || $credentialId === '') {
            throw new RuntimeException('Passkey assertion is missing rawId.');
        }

        $challengeKey = $this->challengeKey($userHandle);

        $expectedChallenge = $this->challengeStore->get($challengeKey);
        if ($expectedChallenge === null) {
            throw new RuntimeException('Passkey challenge is missing or expired.');
        }

        // Challenges are single-use: consume it on the first verification attempt
        // regardless of the outcome, so a captured assertion cannot be replayed
        // until the TTL elapses.
        $this->challengeStore->forget($challengeKey);

        $credential = $this->credentials->findByCredentialId($credentialId, $userHandle);
        if ($credential === null) {
            return false;
        }

        return $this->verifier->verify($assertion, $credential, $expectedChallenge);
    }

    private function challengeKey(string|int|null $userHandle): string
    {
        return 'passkey:challenge:' . (string)($userHandle ?? 'anonymous');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
