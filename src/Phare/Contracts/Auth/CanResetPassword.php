<?php

namespace Phare\Contracts\Auth;

interface CanResetPassword
{
    /**
     * Get the e-mail address where password reset links are sent.
     */
    public function getEmailForPasswordReset(): string;
}
