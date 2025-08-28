<?php

namespace Phare\Hashing;

class Argon2idHasher extends ArgonHasher
{
    protected function algorithm(): string
    {
        return PASSWORD_ARGON2ID;
    }
}
