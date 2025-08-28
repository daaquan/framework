<?php

namespace Phare\Security;

use Phare\Contracts\Foundation\Application;

class Csrf
{
    protected Application $app;

    protected string $sessionKey = '_csrf_token';

    protected int $tokenLength = 40;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Generate a new CSRF token.
     */
    public function generateToken(): string
    {
        $token = bin2hex(random_bytes($this->tokenLength / 2));
        $this->storeToken($token);

        return $token;
    }

    /**
     * Get the current CSRF token from session or generate a new one.
     */
    public function getToken(): string
    {
        $session = $this->app->make('session');
        $token = $session->get($this->sessionKey);

        if (!$token) {
            $token = $this->generateToken();
        }

        return $token;
    }

    /**
     * Verify if the given token matches the session token.
     */
    public function verifyToken(string $token): bool
    {
        $sessionToken = $this->getSessionToken();

        if (!$sessionToken || !$token) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Store the token in the session.
     */
    protected function storeToken(string $token): void
    {
        $session = $this->app->make('session');
        $session->set($this->sessionKey, $token);
    }

    /**
     * Get the token from the session.
     */
    protected function getSessionToken(): ?string
    {
        $session = $this->app->make('session');

        return $session->get($this->sessionKey);
    }

    /**
     * Clear the CSRF token from session.
     */
    public function clearToken(): void
    {
        $session = $this->app->make('session');
        $session->remove($this->sessionKey);
    }

    /**
     * Get the CSRF token name for forms.
     */
    public function getTokenName(): string
    {
        return '_token';
    }

    /**
     * Generate HTML input field for CSRF token.
     */
    public function field(): string
    {
        $token = $this->getToken();
        $name = $this->getTokenName();

        return '<input type="hidden" name="' . $name . '" value="' . $token . '">';
    }

    /**
     * Generate meta tag for CSRF token (useful for AJAX).
     */
    public function metaTag(): string
    {
        $token = $this->getToken();

        return '<meta name="csrf-token" content="' . $token . '">';
    }
}
