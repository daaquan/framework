<?php

declare(strict_types=1);

namespace Phare\Auth;

use Phalcon\Config\ConfigInterface;
use Phalcon\Mvc\ModelInterface as Model;
use Phare\Collections\Arr;
use Phare\Contracts\Auth\Authenticatable as User;
use Phare\Contracts\Session\Session;

class Manager
{
    /**
     * Indicates if the logout method has been called.
     */
    protected bool $loggedOut = false;

    protected ?User $user = null;

    protected string|User $model;

    public function __construct(private Session $session, private ConfigInterface $config) {}

    public function user(): ?User
    {
        if ($this->loggedOut) {
            return null;
        }

        if ($this->user !== null) {
            return $this->user;
        }

        $id = $this->retrieveIdentifier();

        if ($id !== null) {
            $this->user = $this->retrieveUserByIdentifier($id);
        }

        return $this->user;
    }

    /**
     * If user is NOT logged into the system return true else false;
     *
     * @return bool Guest is true, Loggedin is false
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Authenticate user
     */
    public function attempt(array $credentials = []): bool
    {
        $user = $this->retrieveUserByCredentials($credentials);

        if ($user) {
            return $this->login($user);
        }

        return false;
    }

    /**
     * Determine if user is authenticated
     */
    public function check(): bool
    {
        return !is_null($this->user());
    }

    /**
     * Log out of the application
     */
    public function logout(): void
    {
        $this->user = null;
        $this->loggedOut = true;

        $this->session->destroy();
    }

    /**
     * Get currently logged user's id
     *
     * @return mixed|null
     */
    public function retrieveIdentifier()
    {
        return $this->session->get($this->sessionKey());
    }

    /**
     * Log a user into the application
     */
    public function login(User $user): bool
    {
        $this->regenerateSessionId();

        $this->session->set($this->sessionKey(), $user->getAuthIdentifier());

        $this->user = $user;
        $this->loggedOut = false;

        return true;
    }

    /**
     * Log a user into the application using id
     */
    public function loginUsingId(int $id): User|Model
    {
        $user = $this->retrieveUserById($id);

        $this->login($user);

        return $user;
    }

    /**
     * Retrieve a user by his id
     */
    protected function retrieveUserById(int $id): User
    {
        $class = $this->modelClass();

        return $class::findFirst($id);
    }

    /**
     * Retrieve a user by his identifier
     */
    protected function retrieveUserByIdentifier(int|string $id): ?User
    {
        $class = $this->modelClass();

        return $class::findFirst([
            'conditions' => $class::getAuthIdentifierName() . ' = :auth_identifier:',
            'bind' => ['auth_identifier' => $id],
        ]);
    }

    /**
     * Retrieve a user by credentials
     */
    protected function retrieveUserByCredentials(array $credentials): ?User
    {
        $class = $this->modelClass();

        $identifier = $class::getAuthIdentifierName();
        $password = $class::getAuthPasswordName();

        $user = $this->retrieveUserByIdentifier(Arr::fetch($credentials, $identifier));

        $hash = Arr::fetch($credentials, $password);
        if ($user && password_verify($hash, $user->getAuthPassword())) {
            return $user;
        }

        return null;
    }

    /**
     * Regenerate Session ID
     */
    protected function regenerateSessionId(): void
    {
        $this->session->regenerateId();
    }

    /**
     * Retrieve session id
     *
     * @return mixed
     */
    private function sessionKey()
    {
        return $this->config->session_id;
    }

    private function modelClass(): string|User
    {
        return $this->model = $this->config->model;
    }
}
