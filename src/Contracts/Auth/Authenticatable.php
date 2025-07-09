<?php

namespace Phare\Contracts\Auth;

interface Authenticatable
{
    /**
     * Get the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifier();

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword();

    /**
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public static function getAuthIdentifierName();

    /**
     * Get the name of the password for the user.
     *
     * @return string
     */
    public static function getAuthPasswordName();
}
