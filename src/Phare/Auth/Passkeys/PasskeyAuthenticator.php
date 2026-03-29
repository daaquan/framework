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
     * @param  array<string, mixed>  $assertion
     */
    public function verify(array $assertion, string|int|null $userHandle = null): bool
    {
        $credentialId = $assertion['rawId'] ?? null;
        if (!is_string($credentialId) || $credentialId === '') {
            throw new RuntimeException('Passkey assertion is missing rawId.');
        }

        $expectedChallenge = $this->challengeStore->get($this->challengeKey($userHandle));
        if ($expectedChallenge === null) {
            throw new RuntimeException('Passkey challenge is missing or expired.');
        }

        $credential = $this->credentials->findByCredentialId($credentialId, $userHandle);
        if ($credential === null) {
            return false;
        }

        $verified = $this->verifier->verify($assertion, $credential, $expectedChallenge);

        if ($verified) {
            $this->challengeStore->forget($this->challengeKey($userHandle));
        }

        return $verified;
    }

    private function challengeKey(string|int|null $userHandle): string
    {
        return 'passkey:challenge:' . (string) ($userHandle ?? 'anonymous');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
