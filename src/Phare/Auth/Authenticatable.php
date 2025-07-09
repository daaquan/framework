<?php

namespace Phare\Auth;

/**
 * Interface Authenticatable
 *
 * Provide all necessary functions to authenticate.
 */
trait Authenticatable
{
    /**
     * Get the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifier()
    {
        return $this->{static::getAuthIdentifierName()};
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->{static::getAuthPasswordName()};
    }

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public static function getAuthIdentifierName()
    {
        return 'id';
    }

    /**
     * Get the name of the password for the user.
     *
     * @return string
     */
    public static function getAuthPasswordName()
    {
        return 'password';
    }
}
