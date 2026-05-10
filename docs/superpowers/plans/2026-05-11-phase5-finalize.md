# Phase 5 Finalize: Log channel-stack + Manager base extraction

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close remaining Phase 5 items in the Laravel-13 compatibility roadmap: (1) align `LogManager` channel-stack semantics with Laravel, and (2) extract a shared `AbstractManager` base class to remove duplication across the nine multi-driver managers.

**Architecture:** Two independent slices. Slice A adds `stack()` + `channel()` to `LogManager` mirroring Laravel's `Illuminate\Log\LogManager`. Slice B introduces `Phare\Support\Manager` modeled on `Illuminate\Support\Manager` (191 LOC reference), then migrates each existing manager to extend it while preserving its current public API (`store/disk/guard/connection/mailer/channel/driver`) as thin aliases over `driver()`.

**Tech Stack:** PHP 8.2+, Phalcon 5.4+, Pest PHP, Laravel Pint. Reference: `/opt/laravel-framework/src/Illuminate/Support/Manager.php` and `/opt/laravel-framework/src/Illuminate/Log/LogManager.php`.

**Reference targets (read before starting):**
- `/opt/framework/src/Phare/Log/LogManager.php` (current Phare log manager — has `channels[]` map, no `stack()` / no `channel()` alias).
- `/opt/laravel-framework/src/Illuminate/Log/LogManager.php` lines 79–280 (`stack`, `channel`, `parseDriver`, `createStackDriver`).
- `/opt/laravel-framework/src/Illuminate/Support/Manager.php` (the base class to mirror).
- `/opt/framework/src/Phare/{Cache,Auth,Filesystem,Queue,Hashing,Broadcasting,Mail,Session}/` (managers to migrate).
- `/opt/framework/docs/laravel13-phalcon-architecture.md` (roadmap, especially Phase 5 section).

---

## File Structure

### New files
- `src/Phare/Support/Manager.php` — abstract base mirroring `Illuminate\Support\Manager`. Container-injected, `driver()`/`createDriver()`/`extend()`/`forgetDrivers()`/`__call()`.
- `tests/Unit/Support/ManagerTest.php` — base class behavior, including custom creators, default-driver fallback, drivers cache.
- `tests/Unit/Log/LogManagerStackTest.php` — Log stack + channel alias tests.

### Modified files
- `src/Phare/Log/LogManager.php` — add `channel()`, `stack(array $channels, ?string $channel = null)`, `createStackDriver()`, and `parseDriver()`.
- `src/Phare/Cache/CacheManager.php` — extend `Phare\Support\Manager`; rename internal cache to `drivers` (keep `stores()`/`store()` public alias).
- `src/Phare/Filesystem/FilesystemManager.php` — same pattern with `disk()` alias.
- `src/Phare/Auth/AuthManager.php` — same with `guard()` alias.
- `src/Phare/Queue/QueueManager.php` — same with `connection()` alias.
- `src/Phare/Hashing/HashManager.php` — same; constructor must accept container.
- `src/Phare/Broadcasting/BroadcastManager.php` — already container-aware; refactor to base.
- `src/Phare/Mail/MailManager.php` — same with `mailer()` alias.
- `src/Phare/Log/LogManager.php` — extend base AFTER slice A lands.
- `docs/laravel13-phalcon-architecture.md` — mark items complete as each task wraps.

### Files explicitly out of scope
- `src/Phare/Session/SessionManager.php` — extends Phalcon's own `Manager`. Document as a deliberate exception; do NOT migrate in this plan.
- `src/Phare/Database/MySql/DatabaseManager.php` — namespaced by driver, not a multi-driver manager in the Laravel sense. Out of scope.

---

## Slice A: LogManager channel-stack semantics

### Task A1: Add channel() alias test

**Files:**
- Test: `tests/Unit/Log/LogManagerStackTest.php` (create)

- [ ] **Step 1: Write the failing test**

```php
<?php

use Phare\Foundation\AbstractApplication;
use Phare\Log\LogManager;

beforeEach(function () {
    $this->app = $this->createApplicationWithLoggingConfig([
        'default' => 'single',
        'channels' => [
            'single' => ['driver' => 'single', 'path' => sys_get_temp_dir().'/phare-test.log'],
            'errorlog' => ['driver' => 'errorlog'],
        ],
    ]);
});

it('returns the same instance from channel() and driver()', function () {
    $manager = new LogManager($this->app);

    $viaDriver = $manager->driver('single');
    $viaChannel = $manager->channel('single');

    expect($viaChannel)->toBe($viaDriver);
});

it('returns the default channel when channel() called with null', function () {
    $manager = new LogManager($this->app);

    expect($manager->channel())->toBe($manager->driver());
});
```

> If `createApplicationWithLoggingConfig` does not exist in `tests/Pest.php`, add it as a tiny helper that builds an Application with the given `logging` config block, mirroring `tests/Unit/Container/Attributes/LogTest.php`'s setup pattern.

- [ ] **Step 2: Run test to verify it fails**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Log/LogManagerStackTest.php --filter="channel"
```

Expected: FAIL with `Method Phare\Log\LogManager::channel does not exist`.

- [ ] **Step 3: Add `channel()` alias to LogManager**

In `src/Phare/Log/LogManager.php`, immediately after the existing `driver()` method:

```php
/**
 * Get a log channel instance (Laravel parity alias for driver()).
 */
public function channel(?string $channel = null): LoggerInterface
{
    return $this->driver($channel);
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Log/LogManagerStackTest.php --filter="channel"
```

Expected: 2 passed.

- [ ] **Step 5: Commit**

```bash
cd /opt/framework
git add src/Phare/Log/LogManager.php tests/Unit/Log/LogManagerStackTest.php
git commit -m "feat(log): add channel() alias for driver() (Laravel parity)"
```

---

### Task A2: stack() returns aggregate logger over configured channels

**Files:**
- Modify: `src/Phare/Log/LogManager.php`
- Test: `tests/Unit/Log/LogManagerStackTest.php`

- [ ] **Step 1: Write the failing test (append to existing file)**

```php
it('stack() aggregates multiple channels into one logger', function () {
    $manager = new LogManager($this->app);

    $stack = $manager->stack(['single', 'errorlog']);

    // Should be a Logger (Monolog PSR-3 implementation) with both handlers attached.
    expect($stack)->toBeInstanceOf(\Psr\Log\LoggerInterface::class);

    $handlers = $stack->getHandlers();
    expect($handlers)->toHaveCount(2);
});

it('stack() caches by named channel argument', function () {
    $manager = new LogManager($this->app);

    $a = $manager->stack(['single', 'errorlog'], 'audit');
    $b = $manager->stack(['single', 'errorlog'], 'audit');

    expect($a)->toBe($b);
});

it('stack() returns a fresh instance when channel name is null', function () {
    $manager = new LogManager($this->app);

    $a = $manager->stack(['single']);
    $b = $manager->stack(['single']);

    // Anonymous stacks are not cached.
    expect($a)->not->toBe($b);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Log/LogManagerStackTest.php --filter="stack"
```

Expected: FAIL with `Method Phare\Log\LogManager::stack does not exist`.

- [ ] **Step 3: Implement stack() and createStackDriver()**

In `src/Phare/Log/LogManager.php`:

```php
/**
 * Create an aggregate (stack) log driver over the given channels.
 *
 * @param  array<int, string>  $channels
 */
public function stack(array $channels, ?string $channel = null): LoggerInterface
{
    if ($channel !== null) {
        return $this->channels[$channel] ??= $this->createStackDriver($channels, $channel);
    }

    return $this->createStackDriver($channels, null);
}

protected function createStackDriver(array $channels, ?string $name): LoggerInterface
{
    $handlers = [];
    foreach ($channels as $channelName) {
        $logger = $this->channel($channelName);
        foreach ($logger->getHandlers() as $handler) {
            $handlers[] = $handler;
        }
    }

    return new \Phare\Log\Logger(
        new \Monolog\Logger($name ?? 'stack', $handlers)
    );
}
```

> If `Phare\Log\Logger` does not wrap Monolog with `getHandlers()` access, mirror Laravel's `Illuminate\Log\Logger` decoration and expose `getHandlers()` (see `/opt/laravel-framework/src/Illuminate/Log/Logger.php`).

- [ ] **Step 4: Run test to verify it passes**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Log/LogManagerStackTest.php
```

Expected: all 5 tests pass.

- [ ] **Step 5: Commit**

```bash
cd /opt/framework
git add src/Phare/Log/LogManager.php tests/Unit/Log/LogManagerStackTest.php
git commit -m "feat(log): aggregate stack() driver across multiple channels"
```

---

### Task A3: #[Log(?channel)] resolves stacks declared in config

**Files:**
- Modify: `src/Phare/Container/Attributes/Log.php` (verify; only modify if a stack channel config is not currently routed through `driver()`)
- Test: `tests/Unit/Container/Attributes/LogTest.php` (extend with a stack case)

- [ ] **Step 1: Add stack-resolution test to existing LogTest**

In `tests/Unit/Container/Attributes/LogTest.php` add:

```php
it('resolves a stack channel via #[Log] attribute', function () {
    $this->app['config']->set('logging.channels.audit', [
        'driver' => 'stack',
        'channels' => ['single', 'errorlog'],
    ]);

    $resolved = $this->app->make(StackChannelConsumer::class);

    expect($resolved->logger)->toBeInstanceOf(\Psr\Log\LoggerInterface::class);
});

class StackChannelConsumer
{
    public function __construct(
        #[\Phare\Container\Attributes\Log('audit')] public \Psr\Log\LoggerInterface $logger,
    ) {}
}
```

- [ ] **Step 2: Run test, observe whether it already passes**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Container/Attributes/LogTest.php --filter="stack channel"
```

Expected outcomes:
- PASS → no production code change needed. Skip step 3, go to commit.
- FAIL → continue to step 3.

- [ ] **Step 3 (only if step 2 failed): Route `driver: stack` configs through `createStackDriver`**

In `src/Phare/Log/LogManager.php` `resolve($name, $config)`, before driver dispatch:

```php
if (($config['driver'] ?? null) === 'stack') {
    $channels = $config['channels'] ?? [];
    return $this->createStackDriver($channels, $name);
}
```

- [ ] **Step 4: Re-run the test**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Container/Attributes/LogTest.php
```

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
cd /opt/framework
git add src/Phare/Log/LogManager.php tests/Unit/Container/Attributes/LogTest.php
git commit -m "feat(log): #[Log(channel)] resolves stack channels via createStackDriver"
```

---

### Task A4: Mark Slice A complete in roadmap

**Files:**
- Modify: `docs/laravel13-phalcon-architecture.md` (Phase 5 section, "Remaining Phase 5 candidates")

- [ ] **Step 1: Update the bullet**

Replace the "Log selector" bullet under "Remaining Phase 5 candidates" with:

```
- `Log` selector (`#[Log(?channel)]`) aligned with Laravel channel-stack semantics — `stack()` + `channel()` alias + `driver: stack` config-routing landed 2026-05-11.
```

- [ ] **Step 2: Commit**

```bash
cd /opt/framework
git add docs/laravel13-phalcon-architecture.md
git commit -m "docs: log slice A complete — channel/stack semantics aligned"
```

---

## Slice B: AbstractManager base extraction

### Task B1: Add Phare\Support\Manager abstract base

**Files:**
- Create: `src/Phare/Support/Manager.php`
- Test: `tests/Unit/Support/ManagerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use Phare\Support\Manager;
use Phare\Container\Container;
use InvalidArgumentException;

class FakeDriverA { public string $name = 'a'; }
class FakeDriverB { public string $name = 'b'; }

class FakeManager extends Manager
{
    public function getDefaultDriver(): string { return 'a'; }
    protected function createADriver(): FakeDriverA { return new FakeDriverA(); }
    protected function createBDriver(): FakeDriverB { return new FakeDriverB(); }
}

beforeEach(function () {
    $this->container = new Container();
    $this->container->singleton('config', fn () => new \Phalcon\Config\Config([]));
});

it('resolves the default driver when no name is supplied', function () {
    $m = new FakeManager($this->container);
    expect($m->driver())->toBeInstanceOf(FakeDriverA::class);
});

it('caches resolved drivers by name', function () {
    $m = new FakeManager($this->container);
    expect($m->driver('a'))->toBe($m->driver('a'));
});

it('resolves named drivers via createXxxDriver convention', function () {
    $m = new FakeManager($this->container);
    expect($m->driver('b'))->toBeInstanceOf(FakeDriverB::class);
});

it('throws when an unknown driver is requested', function () {
    $m = new FakeManager($this->container);
    $m->driver('unknown');
})->throws(InvalidArgumentException::class, 'Driver [unknown] not supported.');

it('invokes registered custom creators ahead of createXxxDriver', function () {
    $m = new FakeManager($this->container);
    $m->extend('custom', fn ($app) => 'CUSTOM_VALUE');
    expect($m->driver('custom'))->toBe('CUSTOM_VALUE');
});

it('forgetDrivers() clears the resolved cache', function () {
    $m = new FakeManager($this->container);
    $first = $m->driver('a');
    $m->forgetDrivers();
    expect($m->driver('a'))->not->toBe($first);
});

it('__call() forwards to the default driver', function () {
    $m = new FakeManager($this->container);
    expect($m->__call('__toString', []))->toBeNull(); // FakeDriverA has no __toString; ensure no crash
});

it('throws when the default driver name resolves to null', function () {
    $m = new class($this->container) extends Manager {
        public function getDefaultDriver(): ?string { return null; }
    };
    $m->driver();
})->throws(InvalidArgumentException::class, 'Unable to resolve NULL driver');
```

- [ ] **Step 2: Run to verify it fails**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Support/ManagerTest.php
```

Expected: FAIL with `Phare\Support\Manager not found`.

- [ ] **Step 3: Implement Phare\Support\Manager**

```php
<?php

namespace Phare\Support;

use Closure;
use InvalidArgumentException;
use Phare\Contracts\Container\Container as ContainerContract;

abstract class Manager
{
    protected ContainerContract $container;

    /** @var array<string, mixed> */
    protected array $config = [];

    /** @var array<string, Closure> */
    protected array $customCreators = [];

    /** @var array<string, mixed> */
    protected array $drivers = [];

    public function __construct(ContainerContract $container)
    {
        $this->container = $container;
        if ($container->has('config')) {
            $this->config = $container->make('config');
        }
    }

    abstract public function getDefaultDriver(): ?string;

    public function driver(?string $driver = null): mixed
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if ($driver === null) {
            throw new InvalidArgumentException(
                sprintf('Unable to resolve NULL driver for [%s].', static::class)
            );
        }

        return $this->drivers[$driver] ??= $this->createDriver($driver);
    }

    protected function createDriver(string $driver): mixed
    {
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        $method = 'create' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $driver))) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new InvalidArgumentException("Driver [{$driver}] not supported.");
    }

    protected function callCustomCreator(string $driver): mixed
    {
        return ($this->customCreators[$driver])($this->container);
    }

    public function extend(string $driver, Closure $callback): static
    {
        $this->customCreators[$driver] = Closure::bind($callback, $this, $this);
        return $this;
    }

    public function getDrivers(): array
    {
        return $this->drivers;
    }

    public function getContainer(): ContainerContract
    {
        return $this->container;
    }

    public function setContainer(ContainerContract $container): static
    {
        $this->container = $container;
        return $this;
    }

    public function forgetDrivers(): static
    {
        $this->drivers = [];
        return $this;
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->driver()->$method(...$parameters);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Support/ManagerTest.php
```

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
cd /opt/framework
git add src/Phare/Support/Manager.php tests/Unit/Support/ManagerTest.php
git commit -m "feat(support): add abstract Manager base for multi-driver services"
```

---

### Task B2: Migrate CacheManager onto base

**Files:**
- Modify: `src/Phare/Cache/CacheManager.php`
- Test: `tests/Unit/Cache/CacheManagerTest.php` (extend if exists, create otherwise)

- [ ] **Step 1: Write a regression test that pins current public API**

In `tests/Unit/Cache/CacheManagerTest.php`:

```php
it('keeps store() as the public selector after base migration', function () {
    $manager = $this->app->make('cache.manager');

    expect($manager->store())->toBeInstanceOf(\Phalcon\Cache\Adapter\AdapterInterface::class);
    expect($manager->store())->toBe($manager->store()); // cached
    expect($manager->getDefaultStore())->toBeString();
});

it('exposes Laravel-parity driver() alongside store()', function () {
    $manager = $this->app->make('cache.manager');
    expect($manager->driver())->toBe($manager->store());
});
```

- [ ] **Step 2: Run to verify the second test fails**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Cache/CacheManagerTest.php
```

Expected: the second test fails (`driver()` not defined).

- [ ] **Step 3: Refactor CacheManager to extend Phare\Support\Manager**

```php
<?php

namespace Phare\Cache;

use Phalcon\Cache\Adapter\AdapterInterface as CacheAdapterInterface;
use Phare\Support\Manager;

class CacheManager extends Manager
{
    public function getDefaultDriver(): ?string
    {
        return (string)($this->config['cache']['default'] ?? 'file');
    }

    public function store(?string $name = null): CacheAdapterInterface
    {
        return $this->driver($name);
    }

    public function getDefaultStore(): string
    {
        return (string) $this->getDefaultDriver();
    }

    public function adapter(): CacheAdapterInterface
    {
        return $this->store();
    }

    protected function createDriver(string $driver): CacheAdapterInterface
    {
        // CacheManager's "drivers" are store names; look up each store's adapter config.
        $config = $this->normalizeConfig($this->config["cache.stores.{$driver}"] ?? []);
        if ($config === [] || !isset($config['driver'])) {
            throw new \InvalidArgumentException("Cache config for '{$driver}' is invalid or missing.");
        }
        return $this->makeAdapter($config['driver'], $config);
    }

    // ... existing makeAdapter / makeStreamAdapter / makeRedisAdapter / makeApcuAdapter / makeArrayAdapter / makeNullAdapter / normalizeConfig methods retained unchanged
}
```

> Preserve all `makeXxxAdapter` private methods exactly as today. Drop the eager `$this->store($this->defaultStore)` call in the old constructor — base class lazy-resolves.
> Drop the `defaultStore`/`stores` properties — base provides equivalents (`drivers`, `getDefaultDriver()`).
> Container access path for config: the base class hydrates `$this->config` from container's `config` binding. `$this->config["cache.stores.{$driver}"]` works because `Phalcon\Config\Config` supports dot path via `path()`. Update to `$this->config->path("cache.stores.{$driver}")` if `$this->config` is a Phalcon Config instance.

- [ ] **Step 4: Run all Cache tests to ensure no regression**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Cache tests/Container/Attributes/CacheTest.php
```

Expected: all green.

- [ ] **Step 5: Commit**

```bash
cd /opt/framework
git add src/Phare/Cache/CacheManager.php tests/Unit/Cache/CacheManagerTest.php
git commit -m "refactor(cache): CacheManager extends Phare\\Support\\Manager; keep store() alias"
```

---

### Task B3: Migrate FilesystemManager onto base

**Files:**
- Modify: `src/Phare/Filesystem/FilesystemManager.php`
- Test: `tests/Unit/Filesystem/FilesystemManagerTest.php` (extend or create)

- [ ] **Step 1: Write the regression test**

```php
it('keeps disk() as the public selector after base migration', function () {
    $manager = $this->app->make('filesystem.manager');

    expect($manager->disk())->toBeInstanceOf(\Phare\Filesystem\Filesystem::class);
    expect($manager->disk())->toBe($manager->disk());
});

it('exposes Laravel-parity driver() alongside disk()', function () {
    $manager = $this->app->make('filesystem.manager');
    expect($manager->driver())->toBe($manager->disk());
});
```

- [ ] **Step 2: Run to verify it fails**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Filesystem/FilesystemManagerTest.php
```

Expected: the `driver()` test fails.

- [ ] **Step 3: Refactor FilesystemManager**

Replace the class body with:

```php
<?php

namespace Phare\Filesystem;

use Phare\Support\Manager;

class FilesystemManager extends Manager
{
    public function getDefaultDriver(): ?string
    {
        return (string)($this->config['filesystems']['default'] ?? 'local');
    }

    public function disk(?string $name = null): Filesystem
    {
        return $this->driver($name);
    }

    public function getDefaultDisk(): string
    {
        return (string) $this->getDefaultDriver();
    }

    protected function createDriver(string $driver): Filesystem
    {
        $config = $this->config["filesystems.disks.{$driver}"] ?? [];
        return $this->resolveDriver($config['driver'] ?? $driver, $config);
    }

    // retain existing resolveDriver / createLocalDriver / createNullDriver methods
}
```

- [ ] **Step 4: Run tests**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Filesystem tests/Container/Attributes/StorageTest.php
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
cd /opt/framework
git add src/Phare/Filesystem/FilesystemManager.php tests/Unit/Filesystem/FilesystemManagerTest.php
git commit -m "refactor(filesystem): FilesystemManager extends Phare\\Support\\Manager"
```

---

### Task B4: Migrate AuthManager onto base

**Files:**
- Modify: `src/Phare/Auth/AuthManager.php`
- Test: `tests/Unit/Auth/AuthManagerTest.php` (extend or create)

- [ ] **Step 1: Regression test**

```php
it('keeps guard() as the public selector after base migration', function () {
    $manager = $this->app->make('auth.manager');
    expect($manager->guard())->toBeInstanceOf(\Phare\Auth\Manager::class);
    expect($manager->guard())->toBe($manager->guard());
});

it('exposes Laravel-parity driver() alongside guard()', function () {
    $manager = $this->app->make('auth.manager');
    expect($manager->driver())->toBe($manager->guard());
});
```

- [ ] **Step 2: Verify failure**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Auth/AuthManagerTest.php
```

Expected: `driver()` test fails.

- [ ] **Step 3: Refactor**

```php
<?php

namespace Phare\Auth;

use Phare\Support\Manager as SupportManager;

class AuthManager extends SupportManager
{
    public function getDefaultDriver(): ?string
    {
        return (string)($this->config['auth']['defaults']['guard'] ?? 'web');
    }

    public function guard(?string $name = null): object
    {
        return $this->driver($name);
    }

    // retain createSessionDriver and any other createXxxDriver methods as-is.
}
```

- [ ] **Step 4: Run tests**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Auth tests/Container/Attributes/{Auth,CurrentUser,Authenticated}Test.php
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
cd /opt/framework
git add src/Phare/Auth/AuthManager.php tests/Unit/Auth/AuthManagerTest.php
git commit -m "refactor(auth): AuthManager extends Phare\\Support\\Manager"
```

---

### Task B5: Migrate QueueManager onto base

**Files:**
- Modify: `src/Phare/Queue/QueueManager.php`
- Test: `tests/Unit/Queue/QueueManagerTest.php` (extend or create)

- [ ] **Step 1: Regression test**

```php
it('keeps connection() as the public selector after base migration', function () {
    $manager = $this->app->make('queue.manager');
    expect($manager->connection())->toBeInstanceOf(\Phare\Queue\Contracts\QueueInterface::class);
    expect($manager->connection())->toBe($manager->connection());
});

it('exposes Laravel-parity driver() alongside connection()', function () {
    $manager = $this->app->make('queue.manager');
    expect($manager->driver())->toBe($manager->connection());
});
```

- [ ] **Step 2: Verify failure**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Queue/QueueManagerTest.php
```

Expected: `driver()` test fails.

- [ ] **Step 3: Refactor**

```php
public function getDefaultDriver(): ?string
{
    return (string)($this->config['queue']['default'] ?? 'sync');
}

public function connection(?string $name = null): QueueInterface
{
    return $this->driver($name);
}

protected function createDriver(string $driver): QueueInterface
{
    // existing connection resolution logic moved here; remove old map-based caching (base handles caching).
}
```

Drop the constructor's `array $config = []` parameter — base accepts the container, config is pulled from `$this->config`. If existing call sites pass config explicitly, update them or keep the old constructor signature for backward compatibility as a secondary overload.

> **Backward compat note:** if any consumer constructs `new QueueManager($configArray)` directly, retain a static `fromConfig(array)` factory rather than breaking the signature.

- [ ] **Step 4: Run tests**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Queue tests/Container/Attributes/QueueTest.php
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
cd /opt/framework
git add src/Phare/Queue/QueueManager.php tests/Unit/Queue/QueueManagerTest.php
git commit -m "refactor(queue): QueueManager extends Phare\\Support\\Manager"
```

---

### Task B6: Migrate HashManager onto base

**Files:**
- Modify: `src/Phare/Hashing/HashManager.php`
- Test: `tests/Unit/Hashing/HashManagerTest.php` (extend or create)

- [ ] **Step 1: Regression test**

```php
it('exposes Laravel-parity driver() alongside the existing driver() method', function () {
    $manager = $this->app->make('hash.manager');
    expect($manager->driver())->toBeInstanceOf(\Phare\Hashing\Contracts\HasherInterface::class);
    expect($manager->driver())->toBe($manager->driver());
});
```

- [ ] **Step 2: Verify the current implementation still passes (HashManager already uses `driver()`)**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Hashing tests/Container/Attributes/HashTest.php
```

If green, write an additional caching-behavior test that asserts `$m->driver('bcrypt') === $m->driver('bcrypt')` so the base migration is observable.

- [ ] **Step 3: Refactor to base**

```php
<?php

namespace Phare\Hashing;

use Phare\Hashing\Contracts\HasherInterface;
use Phare\Support\Manager;

class HashManager extends Manager
{
    public function getDefaultDriver(): ?string
    {
        return (string)($this->config['hashing']['driver'] ?? 'bcrypt');
    }

    protected function createBcryptDriver(): HasherInterface
    {
        return new BcryptHasher($this->config['hashing']['bcrypt'] ?? []);
    }

    protected function createArgonDriver(): HasherInterface
    {
        return new ArgonHasher($this->config['hashing']['argon'] ?? []);
    }
}
```

Drop the old `__construct(string $defaultDriver = 'bcrypt')`. Replace with the base constructor (`Container`).

- [ ] **Step 4: Run tests**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Hashing tests/Container/Attributes/HashTest.php
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
cd /opt/framework
git add src/Phare/Hashing/HashManager.php tests/Unit/Hashing/HashManagerTest.php
git commit -m "refactor(hashing): HashManager extends Phare\\Support\\Manager"
```

---

### Task B7: Migrate BroadcastManager onto base

**Files:**
- Modify: `src/Phare/Broadcasting/BroadcastManager.php`
- Test: `tests/Unit/Broadcasting/BroadcastManagerTest.php` (extend or create)

- [ ] **Step 1: Regression test**

```php
it('keeps connection() and driver() in sync after base migration', function () {
    $manager = $this->app->make('broadcast.manager');
    expect($manager->driver())->toBe($manager->connection());
    expect($manager->driver())->toBeInstanceOf(\Phare\Broadcasting\Broadcaster::class);
});
```

- [ ] **Step 2: Verify failure (or pass + write a deeper test)**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Broadcasting tests/Container/Attributes/BroadcastTest.php
```

- [ ] **Step 3: Refactor**

Replace the class to extend `Phare\Support\Manager`. Keep `driver()`/`connection()` aliases. Retain `createLogDriver`/`createNullDriver`/`createRedisDriver` if present.

- [ ] **Step 4: Run tests**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Broadcasting tests/Container/Attributes/BroadcastTest.php
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
cd /opt/framework
git add src/Phare/Broadcasting/BroadcastManager.php tests/Unit/Broadcasting/BroadcastManagerTest.php
git commit -m "refactor(broadcasting): BroadcastManager extends Phare\\Support\\Manager"
```

---

### Task B8: Migrate MailManager onto base

**Files:**
- Modify: `src/Phare/Mail/MailManager.php`
- Test: `tests/Unit/Mail/MailManagerTest.php` (extend or create)

- [ ] **Step 1: Regression test**

```php
it('keeps mailer() and exposes driver() alias', function () {
    $manager = $this->app->make('mail.manager');
    expect($manager->mailer())->toBeInstanceOf(\Phare\Mail\Mailer::class);
    expect($manager->driver())->toBe($manager->mailer());
});
```

- [ ] **Step 2: Verify failure**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Mail tests/Container/Attributes/MailTest.php
```

- [ ] **Step 3: Refactor**

```php
public function getDefaultDriver(): ?string
{
    return (string)($this->config['mail']['default'] ?? 'smtp');
}

public function mailer(?string $name = null): Mailer
{
    return $this->driver($name);
}

protected function createSmtpDriver(): Mailer { /* existing impl */ }
protected function createLogDriver(): Mailer { /* existing impl */ }
// ... other create*Driver methods retained
```

- [ ] **Step 4: Run tests**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Mail tests/Container/Attributes/MailTest.php
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
cd /opt/framework
git add src/Phare/Mail/MailManager.php tests/Unit/Mail/MailManagerTest.php
git commit -m "refactor(mail): MailManager extends Phare\\Support\\Manager"
```

---

### Task B9: Migrate LogManager onto base (after Slice A)

**Files:**
- Modify: `src/Phare/Log/LogManager.php`
- Test: `tests/Unit/Log/LogManagerTest.php` (extend or create)

> **Order dependency:** Run AFTER all of Slice A is green, since Slice A adds `stack()`/`channel()` that must keep working post-migration.

- [ ] **Step 1: Regression test**

```php
it('keeps channel() and driver() in sync after base migration', function () {
    $manager = $this->app->make('log.manager');
    expect($manager->channel())->toBe($manager->driver());
});

it('keeps stack() working after base migration', function () {
    $manager = $this->app->make('log.manager');
    $stack = $manager->stack(['single']);
    expect($stack)->toBeInstanceOf(\Psr\Log\LoggerInterface::class);
});
```

- [ ] **Step 2: Verify they still pass before refactor (sanity check)**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Log
```

- [ ] **Step 3: Refactor LogManager**

```php
class LogManager extends \Phare\Support\Manager implements LoggerInterface
{
    public function getDefaultDriver(): ?string
    {
        return (string)($this->config['logging']['default'] ?? 'single');
    }

    public function channel(?string $channel = null): LoggerInterface
    {
        return $this->driver($channel);
    }

    // stack() / createStackDriver() / parseDriver() retained from Slice A
    // resolve() folded into createDriver() — base handles caching now

    protected function createDriver(string $driver): LoggerInterface
    {
        $config = $this->configurationFor($driver);
        if (($config['driver'] ?? null) === 'stack') {
            return $this->createStackDriver($config['channels'] ?? [], $driver);
        }
        return $this->resolve($driver, $config);
    }

    // configurationFor / resolve / etc. retained
}
```

- [ ] **Step 4: Run tests**

```bash
cd /opt/framework && ./vendor/bin/pest tests/Unit/Log tests/Container/Attributes/LogTest.php
```

Expected: green.

- [ ] **Step 5: Commit**

```bash
cd /opt/framework
git add src/Phare/Log/LogManager.php tests/Unit/Log/LogManagerTest.php
git commit -m "refactor(log): LogManager extends Phare\\Support\\Manager"
```

---

### Task B10: Mark Slice B + Phase 5 complete in roadmap

**Files:**
- Modify: `docs/laravel13-phalcon-architecture.md` (Phase 5 + "Current Risks" sections)

- [ ] **Step 1: Update the Phase 5 section**

Replace "Remaining Phase 5 candidates" with:

```
### Phase 5 (done): Multi-driver attribute selectors + manager bindings

[existing bullets retained]

Final hardening (2026-05-11):
- `LogManager` aligned with Laravel channel-stack semantics: `channel()`, `stack()`, stack driver routing.
- `Phare\Support\Manager` base extracted; CacheManager, FilesystemManager, AuthManager, QueueManager, HashManager, BroadcastManager, MailManager, LogManager all migrated. SessionManager remains a deliberate exception (extends Phalcon's own Manager).
```

Update "Current Risks" item 1 to note that base-class bindings now provide a single fallback path.

- [ ] **Step 2: Commit**

```bash
cd /opt/framework
git add docs/laravel13-phalcon-architecture.md
git commit -m "docs: Phase 5 complete — Manager base extracted, Log semantics aligned"
```

---

## Slice C: Verification & integration test

### Task C1: Run the full framework test suite

- [ ] **Step 1: Run all tests**

```bash
cd /opt/framework && ./vendor/bin/pest
```

Expected: 100% pass rate, no skipped tests outside of the existing skip list.

- [ ] **Step 2: Run pint --test for style**

```bash
cd /opt/framework && ./vendor/bin/pint --test
```

Expected: no style violations.

- [ ] **Step 3: If anything fails, fix in-place and re-run before considering Phase 5 complete.**

### Task C2: Run app-side integration smoke test

- [ ] **Step 1: Update /opt/phare composer if needed**

```bash
cd /opt/phare && composer update phare/framework
```

- [ ] **Step 2: Run app test suite**

```bash
cd /opt/phare && ./vendor/bin/pest
```

Expected: all green.

- [ ] **Step 3: Boot the dev server and hit a route that exercises cache/auth/log**

```bash
cd /opt/phare && php artisan serve &
SERVER_PID=$!
sleep 2
curl -sS http://127.0.0.1:8000/ -o /tmp/phare-smoke.html
kill $SERVER_PID
test -s /tmp/phare-smoke.html
```

Expected: non-empty HTML, no fatal errors in console output.

---

## Notes

- Frequent commits per step keep blast radius small. If any migration task fails mid-stream, revert just that task's commits.
- All migrations preserve existing public APIs (`store/disk/guard/connection/mailer/channel`) as thin aliases. Downstream code should not need to change.
- `SessionManager` is intentionally out of scope; it extends Phalcon's `Phalcon\Session\Manager`, not our base. Document this as a permanent exception when finalizing the roadmap.
- If `$this->config` typing diverges between Phalcon `Config` and array, normalize via a small `protected function configValue(string $key, mixed $default = null)` helper in the base — but only if the divergence causes test failures during migration.
