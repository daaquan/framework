<?php

namespace Phare\Hashing;

class Argon2iHasher extends ArgonHasher
{
    protected function algorithm(): string
    {
        return PASSWORD_ARGON2I;
    }
}
