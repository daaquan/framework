<?php

declare(strict_types=1);

namespace Phare\Auth\Passkeys;

interface PasskeyAssertionVerifier
{
    /**
     * @param  array<string, mixed>  $assertion
     * @param  array<string, mixed>  $credential
     */
    public function verify(array $assertion, array $credential, string $expectedChallenge): bool;
}
