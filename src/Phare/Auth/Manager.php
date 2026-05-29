<?php

declare(strict_types=1);

namespace Phare\Auth;

use Phalcon\Config\ConfigInterface;
use Phalcon\Mvc\ModelInterface as Model;
use Phare\Auth\Events\Attempting;
use Phare\Auth\Events\Authenticated;
use Phare\Auth\Events\Failed;
use Phare\Auth\Events\Login;
use Phare\Auth\Events\Logout;
use Phare\Auth\Events\Validated;
use Phare\Collections\Arr;
use Phare\Contracts\Auth\Authenticatable as User;
use Phare\Contracts\Session\Session;
use Phare\Events\Contracts\Dispatcher as EventsDispatcher;

class Manager
{
    /**
     * Indicates if the logout method has been called.
     */
    protected bool $loggedOut = false;

    protected ?User $user = null;

    protected string|User $model;

    protected bool $authEventDispatched = false;

    public function __construct(
        private Session $session,
        private ConfigInterface $config,
        private ?EventsDispatcher $events = null
    ) {}

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

            if ($this->user !== null && !$this->authEventDispatched) {
                $this->dispatchEvent(new Authenticated($this->user));
                $this->authEventDispatched = true;
            }
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
        $this->dispatchEvent(new Attempting($credentials));

        $user = $this->retrieveUserByCredentials($credentials);

        if ($user) {
            $this->dispatchEvent(new Validated($user, $credentials));

            return $this->login($user);
        }

        $this->dispatchEvent(new Failed($user, $credentials));

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
        $user = $this->user();

        $this->user = null;
        $this->loggedOut = true;
        $this->authEventDispatched = false;

        // Remove only the auth identifier, not the entire session, so CSRF
        // tokens, flash data and other session state survive logout.
        $this->session->remove($this->sessionKey());

        $this->dispatchEvent(new Logout($user));
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
        $this->authEventDispatched = true;

        $this->dispatchEvent(new Login($user));

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

    public function id(): int|string|null
    {
        if ($this->loggedOut) {
            return null;
        }

        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        $this->dispatchEvent(new Attempting($credentials));
        $user = $this->retrieveUserByCredentials($credentials);

        if ($user !== null) {
            $this->dispatchEvent(new Validated($user, $credentials));

            return true;
        }

        $this->dispatchEvent(new Failed($user, $credentials));

        return false;
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

    protected function dispatchEvent(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
