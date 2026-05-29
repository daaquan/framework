<?php

use Phalcon\Config\Config;
use Phalcon\Session\Adapter\Stream;
use Phare\Auth\Events\Attempting;
use Phare\Auth\Events\Authenticated;
use Phare\Auth\Events\Failed;
use Phare\Auth\Events\Login;
use Phare\Auth\Events\Logout;
use Phare\Auth\Events\Validated;
use Phare\Auth\Manager;
use Phare\Container\Container;
use Phare\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Phare\Events\Dispatcher;
use Phare\Session\SessionManager;

class ManagerTestUser implements AuthenticatableContract
{
    public static array $records = [];

    public function __construct(
        public int $id,
        public string $email,
        public string $password,
    ) {}

    public static function seed(array $records): void
    {
        self::$records = $records;
    }

    public static function findFirst(mixed $params): ?self
    {
        if (is_int($params) || is_string($params)) {
            foreach (self::$records as $record) {
                if ((string)$record->id === (string)$params) {
                    return $record;
                }
            }

            return null;
        }

        if (is_array($params) && isset($params['bind']['auth_identifier'])) {
            $identifier = (string)$params['bind']['auth_identifier'];
            foreach (self::$records as $record) {
                if ((string)$record->{self::getAuthIdentifierName()} === $identifier) {
                    return $record;
                }
            }
        }

        return null;
    }

    public function getAuthIdentifier(): int
    {
        return $this->id;
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }

    public static function getAuthIdentifierName(): string
    {
        return 'email';
    }

    public static function getAuthPasswordName(): string
    {
        return 'password';
    }
}

beforeEach(function () {
    ManagerTestUser::seed([
        new ManagerTestUser(10, 'alice@example.com', password_hash('secret', PASSWORD_BCRYPT)),
    ]);

    $adapter = new Stream(['savePath' => sys_get_temp_dir()]);
    $this->session = (new SessionManager())
        ->setAdapter($adapter);
    $this->session->start();
    $this->config = new Config([
        'model' => ManagerTestUser::class,
        'session_id' => 'auth.user',
    ]);

    $container = new Container();
    $this->events = new Dispatcher($container);

    $this->capturedEvents = [];
    foreach ([Attempting::class, Authenticated::class, Validated::class, Failed::class, Login::class, Logout::class] as $eventClass) {
        $this->events->listen($eventClass, function (object $event) use ($eventClass) {
            $this->capturedEvents[] = [$eventClass, $event];
        });
    }

    $this->manager = new Manager($this->session, $this->config, $this->events);
});

afterEach(function () {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
});

test('attempt success dispatches auth events and logs user in', function () {
    $result = $this->manager->attempt([
        'email' => 'alice@example.com',
        'password' => 'secret',
    ]);

    expect($result)->toBeTrue();
    expect($this->manager->id())->toBe(10);
    expect($this->session->get('auth.user'))->toBe(10);
    expect(array_map(fn (array $entry) => $entry[0], $this->capturedEvents))->toBe([
        Attempting::class,
        Validated::class,
        Login::class,
    ]);
});

test('attempt failure dispatches failed event', function () {
    $result = $this->manager->attempt([
        'email' => 'alice@example.com',
        'password' => 'bad-secret',
    ]);

    expect($result)->toBeFalse();
    expect($this->session->get('auth.user'))->toBeNull();
    expect(array_map(fn (array $entry) => $entry[0], $this->capturedEvents))->toBe([
        Attempting::class,
        Failed::class,
    ]);
});

test('user retrieval from session dispatches authenticated once', function () {
    $this->session->set('auth.user', 'alice@example.com');

    $userA = $this->manager->user();
    $userB = $this->manager->user();

    expect($userA)->toBeInstanceOf(ManagerTestUser::class);
    expect($userB)->toBe($userA);
    expect(array_map(fn (array $entry) => $entry[0], $this->capturedEvents))->toBe([
        Authenticated::class,
    ]);
});

test('logout clears auth state and dispatches logout event', function () {
    $this->manager->attempt([
        'email' => 'alice@example.com',
        'password' => 'secret',
    ]);

    $this->manager->logout();

    expect($this->manager->user())->toBeNull();
    expect($this->session->get('auth.user'))->toBeNull();
    expect(array_map(fn (array $entry) => $entry[0], $this->capturedEvents))->toBe([
        Attempting::class,
        Validated::class,
        Login::class,
        Logout::class,
    ]);
});

test('logout clears only the auth key and preserves other session data', function () {
    $this->session->set('cart', ['item-1']);
    $this->session->set('_csrf_token', 'tok-123');

    $this->manager->attempt([
        'email' => 'alice@example.com',
        'password' => 'secret',
    ]);
    expect($this->session->get('auth.user'))->toBe(10);

    $this->manager->logout();

    expect($this->session->get('auth.user'))->toBeNull();
    expect($this->session->get('cart'))->toBe(['item-1']);
    expect($this->session->get('_csrf_token'))->toBe('tok-123');
});

test('validate checks credentials without mutating session state', function () {
    $valid = $this->manager->validate([
        'email' => 'alice@example.com',
        'password' => 'secret',
    ]);

    expect($valid)->toBeTrue();
    expect($this->session->get('auth.user'))->toBeNull();
    expect(array_map(fn (array $entry) => $entry[0], $this->capturedEvents))->toBe([
        Attempting::class,
        Validated::class,
    ]);
});
