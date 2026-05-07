# Container Phase 4 — Attribute Injection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close container parity with Laravel 13 attribute injection by adding `afterResolvingAttribute` callbacks plus 9 contextual attributes (Phalcon-fit subset), delivered in three independently-shippable sub-phases.

**Architecture:** Extend `Phare\Container\Container` with an `afterResolvingAttributeCallbacks` registry plus fire helpers wired into parameter resolution and class-level resolve. Each new attribute implements `Phare\Contracts\Container\ContextualAttribute` with a static `resolve(self $attribute, Container $container)` method, mirroring Laravel's API while delegating to existing Phare managers (`auth`, `cache`, `log`, `filesystem`, `db`, `routeParams`).

**Tech Stack:** PHP 8.2+, Phalcon 5.4, Pest PHP. Existing Container at `src/Phare/Container/Container.php`. Manager bindings from existing service providers (`Phare\Providers\DatabaseProvider`, `LogServiceProvider`, etc.).

**Spec:** `docs/superpowers/specs/2026-05-07-container-phase4-attributes-design.md`

**Reference:**
- `/opt/laravel-framework/src/Illuminate/Container/Container.php` (lines 195, 1152, 1170, 1200, 1381, 1495, 1573, 1585)
- `/opt/laravel-framework/src/Illuminate/Container/Attributes/*`

**Important deviations from spec, locked in during plan:**
- `Auth`, `Cache`, `Storage` take no selector arg (Phare managers are single-instance; selector deferred to Phase 5).
- `Log(?driver)` uses `LogManager::driver()` (Phare's channel equivalent).
- `DB(?connection)` uses `DatabaseManager::connection()`.
- Missing static `resolve()` continues to throw `\RuntimeException` (existing behavior at `Container.php:729`); test asserts that exact class.
- New exception `Phare\Auth\AuthenticationException` introduced in sub-phase 4b for `Authenticated` attribute.

---

## Sub-phase 4a — `afterResolvingAttribute` infra + baseline attributes

### Task 1: Add `afterResolvingAttributeCallbacks` registry + register method

**Files:**
- Modify: `src/Phare/Container/Container.php`
- Test: `tests/Container/AfterResolvingAttributeTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Container/AfterResolvingAttributeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Container;

use PHPUnit\Framework\TestCase;
use Phare\Container\Container;
use Phare\Container\Attributes\Tag as TagAttribute;

class AfterResolvingAttributeTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function test_register_callback_stores_under_attribute_class(): void
    {
        $callback = function ($attribute, $object, $container) {};

        $this->container->afterResolvingAttribute(TagAttribute::class, $callback);

        $reflection = new \ReflectionClass($this->container);
        $prop = $reflection->getProperty('afterResolvingAttributeCallbacks');
        $prop->setAccessible(true);
        $registry = $prop->getValue($this->container);

        $this->assertArrayHasKey(TagAttribute::class, $registry);
        $this->assertSame([$callback], $registry[TagAttribute::class]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Container/AfterResolvingAttributeTest.php --filter=test_register_callback_stores_under_attribute_class`
Expected: FAIL with "Property afterResolvingAttributeCallbacks does not exist" or "Method afterResolvingAttribute does not exist".

- [ ] **Step 3: Add property and method**

In `src/Phare/Container/Container.php`, add after the existing `$reboundCallbacks` property declaration (search for `protected array $reboundCallbacks` to locate the cluster of callback registries):

```php
/**
 * Callbacks indexed by contextual attribute class name.
 *
 * @var array<class-string, array<int, \Closure>>
 */
protected array $afterResolvingAttributeCallbacks = [];
```

Add the registration method, placing it adjacent to `afterResolving()` (currently at `Container.php:303`):

```php
/**
 * Register a callback to fire after a contextual attribute resolves.
 */
public function afterResolvingAttribute(string $attribute, \Closure $callback): void
{
    $this->afterResolvingAttributeCallbacks[$attribute][] = $callback;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Container/AfterResolvingAttributeTest.php --filter=test_register_callback_stores_under_attribute_class`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Phare/Container/Container.php tests/Container/AfterResolvingAttributeTest.php
git commit -m "feat(container): add afterResolvingAttribute callback registry"
```

---

### Task 2: Add `fireAfterResolvingAttributeCallbacks` helper

**Files:**
- Modify: `src/Phare/Container/Container.php`
- Test: `tests/Container/AfterResolvingAttributeTest.php`

- [ ] **Step 1: Replace the file with the consolidated test scaffold**

Note: `Phare\Container\Attributes\Tag` targets only `TARGET_PARAMETER`. To exercise both class-level and parameter-level fire, define a local test attribute with `TARGET_ALL`. Keep production attributes scoped strictly.

Replace the entire content of `tests/Container/AfterResolvingAttributeTest.php` with:

```php
<?php

declare(strict_types=1);

namespace Tests\Container;

use PHPUnit\Framework\TestCase;
use Phare\Container\Container;
use Phare\Container\Attributes\Tag as TagAttribute;
use Phare\Contracts\Container\ContextualAttribute as ContextualAttributeContract;

class AfterResolvingAttributeTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function test_register_callback_stores_under_attribute_class(): void
    {
        $callback = function ($attribute, $object, $container) {};

        $this->container->afterResolvingAttribute(TestContextualAttribute::class, $callback);

        $reflection = new \ReflectionClass($this->container);
        $prop = $reflection->getProperty('afterResolvingAttributeCallbacks');
        $prop->setAccessible(true);
        $registry = $prop->getValue($this->container);

        $this->assertArrayHasKey(TestContextualAttribute::class, $registry);
        $this->assertSame([$callback], $registry[TestContextualAttribute::class]);
    }

    public function test_fire_invokes_registered_callback_with_attribute_instance_object_container(): void
    {
        $captured = null;
        $this->container->afterResolvingAttribute(
            TestContextualAttribute::class,
            function ($attribute, $object, $container) use (&$captured) {
                $captured = [$attribute, $object, $container];
            }
        );

        $reflection = new \ReflectionClass(StubWithTestAttribute::class);
        $attributes = $reflection->getAttributes();

        $sentinel = new \stdClass();
        $sentinel->id = 'resolved-object';

        $fire = (new \ReflectionMethod($this->container, 'fireAfterResolvingAttributeCallbacks'))
            ->getClosure($this->container);
        $fire($attributes, $sentinel);

        $this->assertNotNull($captured);
        $this->assertInstanceOf(TestContextualAttribute::class, $captured[0]);
        $this->assertSame('logger', $captured[0]->value);
        $this->assertSame($sentinel, $captured[1]);
        $this->assertSame($this->container, $captured[2]);
    }

    public function test_fire_skips_non_contextual_attributes(): void
    {
        $invoked = false;
        $this->container->afterResolvingAttribute(
            \Attribute::class,
            function () use (&$invoked) { $invoked = true; }
        );

        $reflection = new \ReflectionClass(StubWithPlainAttribute::class);
        $fire = (new \ReflectionMethod($this->container, 'fireAfterResolvingAttributeCallbacks'))
            ->getClosure($this->container);
        $fire($reflection->getAttributes(), new \stdClass());

        $this->assertFalse($invoked);
    }
}

#[\Attribute(\Attribute::TARGET_ALL)]
final class TestContextualAttribute implements ContextualAttributeContract
{
    public function __construct(public string $value) {}

    public static function resolve(self $attribute, Container $container): string
    {
        return $attribute->value;
    }
}

#[TestContextualAttribute('logger')]
class StubWithTestAttribute {}

#[\Attribute]
class StubWithPlainAttribute {}
```

The replacement above is self-contained: it includes Task 1's registration test (with `TestContextualAttribute` for consistency) plus the two new fire tests, and the test attribute fixtures.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Container/AfterResolvingAttributeTest.php`
Expected: FAIL with "Method fireAfterResolvingAttributeCallbacks does not exist".

- [ ] **Step 3: Add the helper method**

In `src/Phare/Container/Container.php`, add adjacent to `resolveFromAttribute()` (currently at `Container.php:720`):

```php
/**
 * Fire after-resolving callbacks registered against contextual attributes.
 *
 * @param array<int, \ReflectionAttribute> $reflectionAttributes
 */
protected function fireAfterResolvingAttributeCallbacks(array $reflectionAttributes, mixed $object): void
{
    foreach ($reflectionAttributes as $reflectionAttribute) {
        $name = $reflectionAttribute->getName();

        if (!is_a($name, ContextualAttributeContract::class, true)) {
            continue;
        }

        $callbacks = $this->afterResolvingAttributeCallbacks[$name] ?? [];
        if ($callbacks === []) {
            continue;
        }

        $instance = $reflectionAttribute->newInstance();
        foreach ($callbacks as $callback) {
            $callback($instance, $object, $this);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Container/AfterResolvingAttributeTest.php`
Expected: PASS for all three tests.

- [ ] **Step 5: Commit**

```bash
git add src/Phare/Container/Container.php tests/Container/AfterResolvingAttributeTest.php
git commit -m "feat(container): fire afterResolvingAttribute callbacks for contextual attrs only"
```

---

### Task 3: Wire fire into parameter resolution

**Files:**
- Modify: `src/Phare/Container/Container.php` (around line 474-486)
- Test: `tests/Container/AfterResolvingAttributeTest.php`

- [ ] **Step 1: Write the failing test**

Append to `tests/Container/AfterResolvingAttributeTest.php`:

```php
public function test_param_resolution_fires_after_resolving_attribute_callback(): void
{
    $captured = [];
    $this->container->afterResolvingAttribute(
        TestContextualAttribute::class,
        function ($attribute, $object, $container) use (&$captured) {
            $captured[] = [$attribute->value, $object];
        }
    );

    $instance = $this->container->make(StubConsumer::class);

    $this->assertCount(1, $captured);
    $this->assertSame('hello', $captured[0][0]);
    $this->assertSame('hello', $captured[0][1]);
    $this->assertSame('hello', $instance->value);
}
```

Add fixture at bottom of file:

```php
class StubConsumer
{
    public function __construct(
        #[TestContextualAttribute('hello')] public string $value
    ) {}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Container/AfterResolvingAttributeTest.php --filter=test_param_resolution_fires`
Expected: FAIL — `$captured` is empty because fire is not wired yet.

- [ ] **Step 3: Wire fire into parameter resolution**

In `src/Phare/Container/Container.php`, modify the existing block at lines 474-486. Replace:

```php
if (($attribute = $this->getContextualAttributeFromDependency($param)) !== null) {
    $resolved = $this->resolveFromAttribute($attribute);

    if ($param->isVariadic()) {
        foreach ($this->normalizeVariadicAttributeResolved($resolved) as $item) {
            $dependencies[] = $item;
        }
    } else {
        $dependencies[] = $resolved;
    }

    continue;
}
```

with:

```php
if (($attribute = $this->getContextualAttributeFromDependency($param)) !== null) {
    $resolved = $this->resolveFromAttribute($attribute);

    if ($param->isVariadic()) {
        foreach ($this->normalizeVariadicAttributeResolved($resolved) as $item) {
            $dependencies[] = $item;
        }
    } else {
        $dependencies[] = $resolved;
    }

    $this->fireAfterResolvingAttributeCallbacks($param->getAttributes(), $resolved);

    continue;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Container/AfterResolvingAttributeTest.php --filter=test_param_resolution_fires`
Expected: PASS.

- [ ] **Step 5: Run full Container suite for regression**

Run: `./vendor/bin/pest tests/Container/`
Expected: All existing tests still pass.

- [ ] **Step 6: Commit**

```bash
git add src/Phare/Container/Container.php tests/Container/AfterResolvingAttributeTest.php
git commit -m "feat(container): fire afterResolvingAttribute callbacks on parameter resolution"
```

---

### Task 4: Wire fire into class-level resolution + first-resolve gate

**Files:**
- Modify: `src/Phare/Container/Container.php` (around lines 228-271 — `make` method)
- Test: `tests/Container/AfterResolvingAttributeTest.php`

- [ ] **Step 1: Write the failing tests**

Append to `tests/Container/AfterResolvingAttributeTest.php`:

```php
public function test_class_level_attribute_fires_once_on_first_resolve(): void
{
    $count = 0;
    $this->container->afterResolvingAttribute(
        TestContextualAttribute::class,
        function ($attribute, $object, $container) use (&$count) {
            $count++;
        }
    );

    $this->container->singleton(StubWithTag::class);

    $this->container->make(StubWithTag::class);
    $this->container->make(StubWithTag::class);
    $this->container->make(StubWithTag::class);

    $this->assertSame(1, $count);
}

public function test_class_level_attribute_fires_each_resolve_for_non_shared(): void
{
    $count = 0;
    $this->container->afterResolvingAttribute(
        TestContextualAttribute::class,
        function ($attribute, $object, $container) use (&$count) {
            $count++;
        }
    );

    $this->container->bind(StubWithTag::class);

    $this->container->make(StubWithTag::class);
    $this->container->make(StubWithTag::class);

    $this->assertSame(2, $count);
}

public function test_class_level_attribute_callback_throw_propagates(): void
{
    $this->container->afterResolvingAttribute(
        TestContextualAttribute::class,
        function () { throw new \LogicException('boom'); }
    );

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('boom');

    $this->container->make(StubWithTag::class);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Container/AfterResolvingAttributeTest.php`
Expected: 3 failures — class-level fire is not wired.

- [ ] **Step 3: Wire fire into `make()`**

In `src/Phare/Container/Container.php`, modify `make()` (lines 228-271). The first-resolve gate is critical: shared singletons must not re-fire on subsequent `make()` calls.

Replace the existing method body with:

```php
public function make(string $abstract, array $parameters = [])
{
    $abstract = $this->getAlias($abstract);
    $alreadyResolved = $this->resolved($abstract);

    if ($alreadyResolved) {
        $getter = $this->isShared($abstract) ? 'getShared' : 'get';

        $instance = $this->$getter($abstract, $parameters);
        $this->fireResolvingCallbacks($abstract, $instance);

        return $instance;
    }

    if ($this->isShared($abstract) || $this->isReserved($abstract)) {
        try {
            $service = $this->getService($abstract);
            if ($service->isShared()) {
                $instance = $this->getShared($abstract, $parameters);
                $this->resolved[$abstract] = true;
                if (is_object($instance)) {
                    $this->aliases[get_class($instance)] = $abstract;
                }

                $this->fireAfterResolvingClassAttributes($instance);

                return $instance;
            }
        } catch (Exception $e) {
            // Service not found in Phalcon DI, continue to resolve
        }
    }

    $instance = $this->resolve($abstract, $parameters);

    if (is_object($instance)) {
        $instanceClass = get_class($instance);
        if ($instanceClass !== $abstract) {
            $this->aliases[$instanceClass] = $abstract;
        }
    }

    $this->fireResolvingCallbacks($abstract, $instance);
    $this->fireAfterResolvingClassAttributes($instance);

    return $instance;
}

/**
 * Fire after-resolving attribute callbacks for the resolved object's class-level attributes.
 */
protected function fireAfterResolvingClassAttributes(mixed $instance): void
{
    if (!is_object($instance)) {
        return;
    }

    $reflection = new \ReflectionClass($instance);
    $this->fireAfterResolvingAttributeCallbacks($reflection->getAttributes(), $instance);
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Container/AfterResolvingAttributeTest.php`
Expected: All tests pass.

- [ ] **Step 5: Run full Container suite for regression**

Run: `./vendor/bin/pest tests/Container/`
Expected: All existing tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/Phare/Container/Container.php tests/Container/AfterResolvingAttributeTest.php
git commit -m "feat(container): fire class-level afterResolvingAttribute callbacks once per resolve"
```

---

### Task 5: Add `Give` attribute

**Files:**
- Create: `src/Phare/Container/Attributes/Give.php`
- Test: `tests/Container/Attributes/GiveTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Container/Attributes/GiveTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use PHPUnit\Framework\TestCase;
use Phare\Container\Container;
use Phare\Container\Attributes\Give;

class GiveTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function test_give_builds_concrete_with_params(): void
    {
        $instance = $this->container->make(GiveStubConsumer::class);

        $this->assertSame(42, $instance->target->id);
        $this->assertSame('alpha', $instance->target->label);
    }

    public function test_give_resolves_for_variadic_param_as_single_item_array(): void
    {
        $instance = $this->container->make(GiveStubVariadicConsumer::class);

        $this->assertCount(1, $instance->targets);
        $this->assertSame('beta', $instance->targets[0]->label);
    }
}

class GiveStubTarget
{
    public function __construct(public int $id, public string $label) {}
}

class GiveStubConsumer
{
    public function __construct(
        #[Give(GiveStubTarget::class, ['id' => 42, 'label' => 'alpha'])]
        public GiveStubTarget $target
    ) {}
}

class GiveStubVariadicConsumer
{
    /** @var array<int, GiveStubTarget> */
    public array $targets;

    public function __construct(
        #[Give(GiveStubTarget::class, ['id' => 1, 'label' => 'beta'])]
        GiveStubTarget ...$targets
    ) {
        $this->targets = $targets;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Container/Attributes/GiveTest.php`
Expected: FAIL — `Phare\Container\Attributes\Give` does not exist.

- [ ] **Step 3: Implement the attribute**

Create `src/Phare/Container/Attributes/Give.php`:

```php
<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Give implements ContextualAttribute
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public string $class,
        public array $params = []
    ) {
    }

    public static function resolve(self $attribute, Container $container): mixed
    {
        return $container->make($attribute->class, $attribute->params);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Container/Attributes/GiveTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Phare/Container/Attributes/Give.php tests/Container/Attributes/GiveTest.php
git commit -m "feat(container): add Give contextual attribute"
```

---

### Task 6: Add `RouteParameter` attribute

**Files:**
- Create: `src/Phare/Container/Attributes/RouteParameter.php`
- Test: `tests/Container/Attributes/RouteParameterTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Container/Attributes/RouteParameterTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use PHPUnit\Framework\TestCase;
use Phare\Container\Container;
use Phare\Container\Attributes\RouteParameter;

class RouteParameterTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function test_resolves_named_route_param_from_container_binding(): void
    {
        $this->container->singleton('routeParams', fn () => ['user' => '7', 'slug' => 'hello']);

        $consumer = $this->container->make(RouteParamStubConsumer::class);

        $this->assertSame('7', $consumer->user);
        $this->assertSame('hello', $consumer->slug);
    }

    public function test_returns_null_when_key_missing(): void
    {
        $this->container->singleton('routeParams', fn () => ['user' => '7']);

        $consumer = $this->container->make(RouteParamStubConsumer::class);

        $this->assertSame('7', $consumer->user);
        $this->assertNull($consumer->slug);
    }

    public function test_returns_null_when_route_params_binding_missing(): void
    {
        $consumer = $this->container->make(RouteParamStubConsumer::class);

        $this->assertNull($consumer->user);
        $this->assertNull($consumer->slug);
    }
}

class RouteParamStubConsumer
{
    public function __construct(
        #[RouteParameter('user')] public ?string $user = null,
        #[RouteParameter('slug')] public ?string $slug = null
    ) {}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Container/Attributes/RouteParameterTest.php`
Expected: FAIL — `Phare\Container\Attributes\RouteParameter` does not exist.

- [ ] **Step 3: Implement the attribute**

Create `src/Phare/Container/Attributes/RouteParameter.php`:

```php
<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class RouteParameter implements ContextualAttribute
{
    public function __construct(public string $name)
    {
    }

    public static function resolve(self $attribute, Container $container): mixed
    {
        if (!$container->has('routeParams')) {
            return null;
        }

        $params = $container->make('routeParams');
        if (!is_array($params)) {
            return null;
        }

        return $params[$attribute->name] ?? null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Container/Attributes/RouteParameterTest.php`
Expected: PASS for all three cases.

- [ ] **Step 5: Run full Container suite for regression**

Run: `./vendor/bin/pest tests/Container/`
Expected: All tests pass.

- [ ] **Step 6: Commit + close 4a**

```bash
git add src/Phare/Container/Attributes/RouteParameter.php tests/Container/Attributes/RouteParameterTest.php
git commit -m "feat(container): add RouteParameter contextual attribute"
```

---

## Sub-phase 4b — Auth attribute cluster

### Task 7: Add `Phare\Auth\AuthenticationException`

**Files:**
- Create: `src/Phare/Auth/AuthenticationException.php`
- Test: covered indirectly by Task 9.

- [ ] **Step 1: Create the exception**

Create `src/Phare/Auth/AuthenticationException.php`:

```php
<?php

declare(strict_types=1);

namespace Phare\Auth;

class AuthenticationException extends \RuntimeException
{
    public function __construct(string $message = 'Unauthenticated.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Phare/Auth/AuthenticationException.php
git commit -m "feat(auth): add AuthenticationException"
```

---

### Task 8: Add `Auth` attribute

**Files:**
- Create: `src/Phare/Container/Attributes/Auth.php`
- Test: `tests/Container/Attributes/AuthTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Container/Attributes/AuthTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use PHPUnit\Framework\TestCase;
use Phare\Container\Container;
use Phare\Container\Attributes\Auth;

class AuthTest extends TestCase
{
    public function test_resolves_auth_manager_from_container(): void
    {
        $container = new Container();
        $manager = new FakeAuthManager();
        $container->singleton('auth', fn () => $manager);

        $consumer = $container->make(AuthStubConsumer::class);

        $this->assertSame($manager, $consumer->auth);
    }
}

class FakeAuthManager
{
    public ?object $userInstance = null;

    public function user(): ?object
    {
        return $this->userInstance;
    }
}

class AuthStubConsumer
{
    public function __construct(#[Auth] public mixed $auth) {}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Container/Attributes/AuthTest.php`
Expected: FAIL — `Phare\Container\Attributes\Auth` does not exist.

- [ ] **Step 3: Implement the attribute**

Create `src/Phare/Container/Attributes/Auth.php`:

```php
<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Auth implements ContextualAttribute
{
    public static function resolve(self $attribute, Container $container): mixed
    {
        return $container->make('auth');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Container/Attributes/AuthTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Phare/Container/Attributes/Auth.php tests/Container/Attributes/AuthTest.php
git commit -m "feat(container): add Auth contextual attribute"
```

---

### Task 9: Add `CurrentUser` and `Authenticated` attributes

**Files:**
- Create: `src/Phare/Container/Attributes/CurrentUser.php`
- Create: `src/Phare/Container/Attributes/Authenticated.php`
- Test: `tests/Container/Attributes/CurrentUserTest.php` (create)
- Test: `tests/Container/Attributes/AuthenticatedTest.php` (create)

- [ ] **Step 1: Write `CurrentUser` failing test**

Create `tests/Container/Attributes/CurrentUserTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use PHPUnit\Framework\TestCase;
use Phare\Container\Container;
use Phare\Container\Attributes\CurrentUser;

class CurrentUserTest extends TestCase
{
    public function test_returns_current_user_from_auth_manager(): void
    {
        $container = new Container();
        $manager = new FakeAuthManager();
        $manager->userInstance = (object) ['id' => 99];
        $container->singleton('auth', fn () => $manager);

        $consumer = $container->make(CurrentUserStubConsumer::class);

        $this->assertSame($manager->userInstance, $consumer->user);
    }

    public function test_returns_null_when_no_user(): void
    {
        $container = new Container();
        $container->singleton('auth', fn () => new FakeAuthManager());

        $consumer = $container->make(CurrentUserStubConsumer::class);

        $this->assertNull($consumer->user);
    }
}

class CurrentUserStubConsumer
{
    public function __construct(#[CurrentUser] public ?object $user = null) {}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Container/Attributes/CurrentUserTest.php`
Expected: FAIL — `Phare\Container\Attributes\CurrentUser` does not exist.

- [ ] **Step 3: Implement `CurrentUser`**

Create `src/Phare/Container/Attributes/CurrentUser.php`:

```php
<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class CurrentUser implements ContextualAttribute
{
    public static function resolve(self $attribute, Container $container): mixed
    {
        return $container->make('auth')->user();
    }
}
```

- [ ] **Step 4: Run test — expect PASS**

Run: `./vendor/bin/pest tests/Container/Attributes/CurrentUserTest.php`
Expected: PASS.

- [ ] **Step 5: Write `Authenticated` failing test**

Create `tests/Container/Attributes/AuthenticatedTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use PHPUnit\Framework\TestCase;
use Phare\Auth\AuthenticationException;
use Phare\Container\Container;
use Phare\Container\Attributes\Authenticated;

class AuthenticatedTest extends TestCase
{
    public function test_returns_user_when_authenticated(): void
    {
        $container = new Container();
        $manager = new FakeAuthManager();
        $manager->userInstance = (object) ['id' => 7];
        $container->singleton('auth', fn () => $manager);

        $consumer = $container->make(AuthenticatedStubConsumer::class);

        $this->assertSame($manager->userInstance, $consumer->user);
    }

    public function test_throws_authentication_exception_when_no_user(): void
    {
        $container = new Container();
        $container->singleton('auth', fn () => new FakeAuthManager());

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Unauthenticated.');

        $container->make(AuthenticatedStubConsumer::class);
    }
}

class AuthenticatedStubConsumer
{
    public function __construct(#[Authenticated] public object $user) {}
}
```

- [ ] **Step 6: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Container/Attributes/AuthenticatedTest.php`
Expected: FAIL — `Phare\Container\Attributes\Authenticated` does not exist.

- [ ] **Step 7: Implement `Authenticated`**

Create `src/Phare/Container/Attributes/Authenticated.php`:

```php
<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Auth\AuthenticationException;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Authenticated implements ContextualAttribute
{
    public static function resolve(self $attribute, Container $container): mixed
    {
        $user = $container->make('auth')->user();

        if ($user === null) {
            throw new AuthenticationException();
        }

        return $user;
    }
}
```

- [ ] **Step 8: Run test — expect PASS**

Run: `./vendor/bin/pest tests/Container/Attributes/AuthenticatedTest.php`
Expected: PASS.

- [ ] **Step 9: Run full Container suite for regression**

Run: `./vendor/bin/pest tests/Container/`
Expected: All tests pass.

- [ ] **Step 10: Commit**

```bash
git add src/Phare/Container/Attributes/CurrentUser.php src/Phare/Container/Attributes/Authenticated.php tests/Container/Attributes/CurrentUserTest.php tests/Container/Attributes/AuthenticatedTest.php
git commit -m "feat(container): add CurrentUser and Authenticated contextual attributes"
```

---

## Sub-phase 4c — Infra attribute cluster + edge tests

### Task 10: Add `Cache` attribute

**Files:**
- Create: `src/Phare/Container/Attributes/Cache.php`
- Test: `tests/Container/Attributes/CacheTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Container/Attributes/CacheTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use PHPUnit\Framework\TestCase;
use Phare\Container\Container;
use Phare\Container\Attributes\Cache;

class CacheTest extends TestCase
{
    public function test_resolves_cache_manager(): void
    {
        $container = new Container();
        $manager = new \stdClass();
        $container->singleton('cache', fn () => $manager);

        $consumer = $container->make(CacheStubConsumer::class);

        $this->assertSame($manager, $consumer->cache);
    }
}

class CacheStubConsumer
{
    public function __construct(#[Cache] public mixed $cache) {}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Container/Attributes/CacheTest.php`
Expected: FAIL — class missing.

- [ ] **Step 3: Implement**

Create `src/Phare/Container/Attributes/Cache.php`:

```php
<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Cache implements ContextualAttribute
{
    public static function resolve(self $attribute, Container $container): mixed
    {
        return $container->make('cache');
    }
}
```

- [ ] **Step 4: Run test — expect PASS**

Run: `./vendor/bin/pest tests/Container/Attributes/CacheTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Phare/Container/Attributes/Cache.php tests/Container/Attributes/CacheTest.php
git commit -m "feat(container): add Cache contextual attribute"
```

---

### Task 11: Add `Log` attribute

**Files:**
- Create: `src/Phare/Container/Attributes/Log.php`
- Test: `tests/Container/Attributes/LogTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Container/Attributes/LogTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use PHPUnit\Framework\TestCase;
use Phare\Container\Container;
use Phare\Container\Attributes\Log;

class LogTest extends TestCase
{
    public function test_resolves_default_log_driver_when_no_arg(): void
    {
        $container = new Container();
        $manager = new FakeLogManager();
        $container->singleton('log', fn () => $manager);

        $consumer = $container->make(LogDefaultStubConsumer::class);

        $this->assertSame('default-driver', $consumer->log);
    }

    public function test_resolves_named_log_driver(): void
    {
        $container = new Container();
        $manager = new FakeLogManager();
        $container->singleton('log', fn () => $manager);

        $consumer = $container->make(LogNamedStubConsumer::class);

        $this->assertSame('stack-driver', $consumer->log);
    }
}

class FakeLogManager
{
    public function driver($name = null): string
    {
        return $name === null ? 'default-driver' : "$name-driver";
    }
}

class LogDefaultStubConsumer
{
    public function __construct(#[Log] public mixed $log) {}
}

class LogNamedStubConsumer
{
    public function __construct(#[Log('stack')] public mixed $log) {}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Container/Attributes/LogTest.php`
Expected: FAIL — class missing.

- [ ] **Step 3: Implement**

Create `src/Phare/Container/Attributes/Log.php`:

```php
<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Log implements ContextualAttribute
{
    public function __construct(public ?string $driver = null)
    {
    }

    public static function resolve(self $attribute, Container $container): mixed
    {
        return $container->make('log')->driver($attribute->driver);
    }
}
```

- [ ] **Step 4: Run test — expect PASS**

Run: `./vendor/bin/pest tests/Container/Attributes/LogTest.php`
Expected: PASS for both cases.

- [ ] **Step 5: Commit**

```bash
git add src/Phare/Container/Attributes/Log.php tests/Container/Attributes/LogTest.php
git commit -m "feat(container): add Log contextual attribute"
```

---

### Task 12: Add `Storage` attribute

**Files:**
- Create: `src/Phare/Container/Attributes/Storage.php`
- Test: `tests/Container/Attributes/StorageTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Container/Attributes/StorageTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use PHPUnit\Framework\TestCase;
use Phare\Container\Container;
use Phare\Container\Attributes\Storage;

class StorageTest extends TestCase
{
    public function test_resolves_filesystem_manager(): void
    {
        $container = new Container();
        $fs = new \stdClass();
        $container->singleton('filesystem', fn () => $fs);

        $consumer = $container->make(StorageStubConsumer::class);

        $this->assertSame($fs, $consumer->fs);
    }
}

class StorageStubConsumer
{
    public function __construct(#[Storage] public mixed $fs) {}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Container/Attributes/StorageTest.php`
Expected: FAIL — class missing.

- [ ] **Step 3: Implement**

Create `src/Phare/Container/Attributes/Storage.php`:

```php
<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Storage implements ContextualAttribute
{
    public static function resolve(self $attribute, Container $container): mixed
    {
        return $container->make('filesystem');
    }
}
```

- [ ] **Step 4: Run test — expect PASS**

Run: `./vendor/bin/pest tests/Container/Attributes/StorageTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Phare/Container/Attributes/Storage.php tests/Container/Attributes/StorageTest.php
git commit -m "feat(container): add Storage contextual attribute"
```

---

### Task 13: Add `DB` attribute

**Files:**
- Create: `src/Phare/Container/Attributes/DB.php`
- Test: `tests/Container/Attributes/DBTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Container/Attributes/DBTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Container\Attributes;

use PHPUnit\Framework\TestCase;
use Phare\Container\Container;
use Phare\Container\Attributes\DB;

class DBTest extends TestCase
{
    public function test_resolves_default_connection_when_no_arg(): void
    {
        $container = new Container();
        $manager = new FakeDatabaseManager();
        $container->singleton('db', fn () => $manager);

        $consumer = $container->make(DBDefaultStubConsumer::class);

        $this->assertSame('default-conn', $consumer->db);
    }

    public function test_resolves_named_connection(): void
    {
        $container = new Container();
        $manager = new FakeDatabaseManager();
        $container->singleton('db', fn () => $manager);

        $consumer = $container->make(DBNamedStubConsumer::class);

        $this->assertSame('reports-conn', $consumer->db);
    }
}

class FakeDatabaseManager
{
    public function connection(?string $name = null): string
    {
        return $name === null ? 'default-conn' : "$name-conn";
    }
}

class DBDefaultStubConsumer
{
    public function __construct(#[DB] public mixed $db) {}
}

class DBNamedStubConsumer
{
    public function __construct(#[DB('reports')] public mixed $db) {}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Container/Attributes/DBTest.php`
Expected: FAIL — class missing.

- [ ] **Step 3: Implement**

Create `src/Phare/Container/Attributes/DB.php`:

```php
<?php

declare(strict_types=1);

namespace Phare\Container\Attributes;

use Attribute;
use Phare\Container\Container;
use Phare\Contracts\Container\ContextualAttribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class DB implements ContextualAttribute
{
    public function __construct(public ?string $connection = null)
    {
    }

    public static function resolve(self $attribute, Container $container): mixed
    {
        return $container->make('db')->connection($attribute->connection);
    }
}
```

- [ ] **Step 4: Run test — expect PASS**

Run: `./vendor/bin/pest tests/Container/Attributes/DBTest.php`
Expected: PASS for both cases.

- [ ] **Step 5: Commit**

```bash
git add src/Phare/Container/Attributes/DB.php tests/Container/Attributes/DBTest.php
git commit -m "feat(container): add DB contextual attribute"
```

---

### Task 14: Edge-case contextual binding tests

**Files:**
- Create: `tests/Container/ContextualBindingEdgeTest.php`

These tests cover Roadmap "Next Implementation Slice" item 2. They exercise existing contextual binding mechanics, not new code; failures here indicate previously unobserved bugs.

- [ ] **Step 1: Write all four edge tests**

Create `tests/Container/ContextualBindingEdgeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Container;

use PHPUnit\Framework\TestCase;
use Phare\Container\Container;

class ContextualBindingEdgeTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function test_variadic_class_dep_with_give_tagged(): void
    {
        $this->container->bind(EdgeTransport::class, EdgeSlackTransport::class);
        $this->container->bind('email-transport', EdgeEmailTransport::class);
        $this->container->tag([EdgeTransport::class, 'email-transport'], 'transports');

        $this->container->when(EdgeNotifier::class)
            ->needs(EdgeTransport::class)
            ->giveTagged('transports');

        $instance = $this->container->make(EdgeNotifier::class);

        $this->assertCount(2, $instance->transports);
        $this->assertInstanceOf(EdgeSlackTransport::class, $instance->transports[0]);
        $this->assertInstanceOf(EdgeEmailTransport::class, $instance->transports[1]);
    }

    public function test_primitive_give_with_alias_mapped_abstract(): void
    {
        $this->container->bind(EdgeRegionAware::class, EdgeRegionConsumer::class);
        $this->container->alias(EdgeRegionAware::class, 'region.aware');

        $this->container->when('region.aware')
            ->needs('$region')
            ->give('us-east-1');

        $instance = $this->container->make(EdgeRegionConsumer::class);

        $this->assertSame('us-east-1', $instance->region);
    }

    public function test_rebinding_callback_fires_once_after_resolve(): void
    {
        $this->container->bind('rebind.target', fn () => new \stdClass());

        $count = 0;
        $this->container->rebinding('rebind.target', function ($app, $instance) use (&$count) {
            $count++;
        });

        $this->container->bind('rebind.target', fn () => new \stdClass());

        $this->assertSame(1, $count);
    }

    public function test_singleton_resolving_callbacks_fire_once_total(): void
    {
        $count = 0;
        $this->container->singleton(EdgeSingletonStub::class);
        $this->container->resolving(EdgeSingletonStub::class, function () use (&$count) {
            $count++;
        });

        $this->container->make(EdgeSingletonStub::class);
        $this->container->make(EdgeSingletonStub::class);

        $this->assertSame(1, $count);
    }
}

interface EdgeTransport {}
class EdgeSlackTransport implements EdgeTransport {}
class EdgeEmailTransport implements EdgeTransport {}

class EdgeNotifier
{
    /** @var array<int, EdgeTransport> */
    public array $transports;

    public function __construct(EdgeTransport ...$transports)
    {
        $this->transports = $transports;
    }
}

interface EdgeRegionAware {}

class EdgeRegionConsumer implements EdgeRegionAware
{
    public function __construct(public string $region) {}
}

class EdgeSingletonStub
{
    public function __construct() {}
}
```

- [ ] **Step 2: Run tests — observe outcome**

Run: `./vendor/bin/pest tests/Container/ContextualBindingEdgeTest.php`
Expected: All four PASS. If any fail, the failure indicates a real bug — fix it in `Container.php` before proceeding. Document any fix in the same commit.

- [ ] **Step 3: Run full suite for regression**

Run: `./vendor/bin/pest tests/Container/`
Expected: All tests pass across `ContainerTest.php`, `ContainerCompatibilityTest.php`, `AfterResolvingAttributeTest.php`, `ContextualBindingEdgeTest.php`, plus all attribute test files.

- [ ] **Step 4: Update roadmap doc**

Modify `docs/laravel13-phalcon-architecture.md`. In the Phase 4 section, append below the existing list:

```markdown

Phase 4 (continued — 2026-05-07):
- Added `afterResolvingAttribute(string, Closure)` callback registration.
- Added `fireAfterResolvingAttributeCallbacks()` helper, fires only for `ContextualAttribute` implementors.
- Wired fire into parameter resolution (post `resolveFromAttribute`) and class-level resolution (post-build, first-resolve only for shared singletons).
- Added contextual attributes: `Give`, `RouteParameter`, `Auth`, `CurrentUser`, `Authenticated`, `Cache`, `Log`, `Storage`, `DB`.
- Added `Phare\Auth\AuthenticationException`.
- Added contextual binding edge tests: variadic + giveTagged, primitive give + alias-mapped abstract, rebinding-after-resolve, singleton resolving fires once.

Tests:
- `tests/Container/AfterResolvingAttributeTest.php`
- `tests/Container/Attributes/{Give,RouteParameter,Auth,CurrentUser,Authenticated,Cache,Log,Storage,DB}Test.php`
- `tests/Container/ContextualBindingEdgeTest.php`
```

Update the Current Risks / Next Implementation Slice sections to remove items now completed.

- [ ] **Step 5: Commit + close 4c**

```bash
git add tests/Container/ContextualBindingEdgeTest.php docs/laravel13-phalcon-architecture.md
git commit -m "feat(container): add contextual binding edge tests + close Phase 4"
```

---

## Verification checklist (run after each sub-phase)

- [ ] `./vendor/bin/pest tests/Container/` — all green
- [ ] `./vendor/bin/pest` — full suite green (no regressions outside Container)
- [ ] `./vendor/bin/pint --test` — code style check passes
- [ ] No new files exceed 400 lines (per global coding-style rule)
