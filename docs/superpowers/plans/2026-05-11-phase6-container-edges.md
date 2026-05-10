# Phase 6 Implementation Plan: Laravel 13 Container Edge Behaviors

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the remaining gap between `Phare\Container\Container` and `Illuminate\Container\Container` (Laravel 13) per the design spec at `docs/superpowers/specs/2026-05-11-phase6-container-edges-design.md`. This plan implements Groups A–E. Group F (environment helpers) is deferred.

**Architecture:** Add methods to `Phare\Container\Container` (and update `Phare\Contracts\Foundation\Container` where Laravel exposes the new method publicly). Extract `Phare\Container\BoundMethod` helper for `call()`/`bindMethod()` so the main Container class stays under ~1500 LOC. Each behavior lands as one TDD commit.

**Tech Stack:** PHP 8.2+, Pest PHP. References:
- `/opt/laravel-framework/src/Illuminate/Container/Container.php`
- `/opt/laravel-framework/src/Illuminate/Container/BoundMethod.php`
- `/opt/framework/src/Phare/Container/Container.php` (current target, 1011 LOC)
- `/opt/framework/docs/superpowers/specs/2026-05-11-phase6-container-edges-design.md`

---

## File Structure

### New files
- `src/Phare/Container/BoundMethod.php` — autowired closure / method-binding invocation helper.
- `tests/Container/Lifecycle/InstanceAndExtendTest.php` — Group A behavioral tests.
- `tests/Container/Lifecycle/ScopedInstancesTest.php` — scoped + forgetScopedInstances coverage.
- `tests/Container/Lifecycle/ForgetTest.php` — forgetInstance(s) / forgetExtenders / flush.
- `tests/Container/MethodBinding/CallAndWrapTest.php` — Group B coverage.
- `tests/Container/Hooks/ResolutionHooksTest.php` — beforeResolving / currentlyResolving / refresh.
- `tests/Container/Attributes/WhenHasAttributeTest.php` — Group D coverage.
- `tests/Container/PSR11AndArrayAccessTest.php` — Group E coverage.

### Modified files
- `src/Phare/Container/Container.php` — new public/protected methods per group.
- `src/Phare/Contracts/Foundation/Container.php` — extend contract for the new public methods Laravel exposes (`instance`, `extend`, `scoped`, `bindMethod`, `call`, `wrap`, `factory`, `beforeResolving`, `whenHasAttribute`).
- `docs/laravel13-phalcon-architecture.md` — append Phase 6 progress per group.
- `composer.json` — add `psr/container` dep if not already present.

---

## Group A — Lifecycle

### Task A1: `instance($abstract, $instance)`

**Files:** `src/Phare/Container/Container.php`, `tests/Container/Lifecycle/InstanceAndExtendTest.php`

- [ ] **Step 1: Failing test**

```php
<?php

use Phare\Container\Container;

it('registers an already-built instance and marks it resolved', function () {
    $c = new Container();
    $obj = new stdClass();
    $obj->mark = 'pre-built';

    $returned = $c->instance('foo', $obj);

    expect($returned)->toBe($obj)
        ->and($c->make('foo'))->toBe($obj)
        ->and($c->resolved('foo'))->toBeTrue();
});

it('fires rebinding callbacks when instance() replaces an existing binding', function () {
    $c = new Container();
    $c->singleton('foo', fn () => new stdClass());
    $c->make('foo'); // resolve once so we know it is shared

    $rebindings = [];
    $c->rebinding('foo', function ($app, $new) use (&$rebindings) {
        $rebindings[] = $new;
    });

    $replacement = new stdClass();
    $c->instance('foo', $replacement);

    expect($rebindings)->toHaveCount(1)
        ->and($rebindings[0])->toBe($replacement);
});
```

- [ ] **Step 2: Run, verify failure** — `pest tests/Container/Lifecycle/InstanceAndExtendTest.php --filter="instance"`

- [ ] **Step 3: Implement `instance()` in Container**

```php
public function instance(string $abstract, mixed $instance): mixed
{
    $abstract = $this->getAlias($abstract);
    $isBound = $this->bound($abstract);

    // Store directly in the shared instance map.  Phare uses Phalcon's Di
    // `setShared()` under the hood; that path skips wrap-closures.
    $this->setShared($abstract, $instance);

    if ($isBound) {
        $this->fireReboundCallbacks($abstract);
    }

    return $instance;
}
```

- [ ] **Step 4: Verify pass + run full suite**

```bash
./vendor/bin/pest tests/Container
```

- [ ] **Step 5: Commit**

```bash
git add src/Phare/Container/Container.php tests/Container/Lifecycle/InstanceAndExtendTest.php
git commit -m "feat(container): instance() registers pre-built objects with rebinding"
```

### Task A2: `extend($abstract, Closure)`

**Files:** `src/Phare/Container/Container.php`, `tests/Container/Lifecycle/InstanceAndExtendTest.php`

- [ ] **Step 1: Failing tests**

```php
it('decorates a freshly resolved binding with the registered extender', function () {
    $c = new Container();
    $c->singleton('value', fn () => 'hello');

    $c->extend('value', fn ($v, $app) => $v . ' world');

    expect($c->make('value'))->toBe('hello world');
});

it('applies extenders in registration order, latest last', function () {
    $c = new Container();
    $c->singleton('value', fn () => 'a');
    $c->extend('value', fn ($v) => $v . 'b');
    $c->extend('value', fn ($v) => $v . 'c');

    expect($c->make('value'))->toBe('abc');
});

it('replaces the already-resolved shared instance when extend() is called after resolution', function () {
    $c = new Container();
    $obj = new stdClass();
    $obj->n = 1;
    $c->instance('thing', $obj);

    $first = $c->make('thing');
    $c->extend('thing', function ($v) {
        $clone = clone $v;
        $clone->n = $clone->n + 10;
        return $clone;
    });

    expect($c->make('thing')->n)->toBe(11)
        ->and($c->make('thing'))->not->toBe($first);
});
```

- [ ] **Step 2: Run, verify failure**

- [ ] **Step 3: Implement `extend()`**

Add `protected array $extenders = [];` to the Container.

```php
public function extend(string $abstract, Closure $closure): void
{
    $abstract = $this->getAlias($abstract);
    $this->extenders[$abstract][] = $closure;

    // If already resolved, apply extender immediately and rebind.
    if ($this->resolved($abstract)) {
        $current = $this->make($abstract);
        $decorated = $closure($current, $this);
        $this->setShared($abstract, $decorated);
        $this->fireReboundCallbacks($abstract);
    }
}

protected function applyExtenders(string $abstract, mixed $instance): mixed
{
    $abstract = $this->getAlias($abstract);
    foreach ($this->extenders[$abstract] ?? [] as $extender) {
        $instance = $extender($instance, $this);
    }
    return $instance;
}
```

Wire `applyExtenders()` into the build path inside `resolve()`/`resolveInstance()` so newly built values pass through extenders before being cached.

- [ ] **Step 4: Run tests; full suite green**

- [ ] **Step 5: Commit**

```bash
git add src/Phare/Container/Container.php tests/Container/Lifecycle/InstanceAndExtendTest.php
git commit -m "feat(container): extend() decorates resolved bindings"
```

### Task A3: `forgetInstance` / `forgetInstances` / `forgetExtenders` / `flush`

**Files:** `src/Phare/Container/Container.php`, `tests/Container/Lifecycle/ForgetTest.php`

- [ ] **Step 1: Failing tests**

```php
<?php

use Phare\Container\Container;

it('forgetInstance() drops a single shared instance', function () {
    $c = new Container();
    $c->singleton('a', fn () => new stdClass());
    $first = $c->make('a');

    $c->forgetInstance('a');

    expect($c->make('a'))->not->toBe($first);
});

it('forgetInstances() drops every shared instance', function () {
    $c = new Container();
    $c->singleton('a', fn () => new stdClass());
    $c->singleton('b', fn () => new stdClass());
    $c->make('a'); $c->make('b');

    $c->forgetInstances();

    expect($c->resolved('a'))->toBeFalse()
        ->and($c->resolved('b'))->toBeFalse();
});

it('forgetExtenders() removes registered extenders for an abstract', function () {
    $c = new Container();
    $c->singleton('v', fn () => 'base');
    $c->extend('v', fn ($x) => $x . '+e');

    $c->forgetExtenders('v');

    expect($c->make('v'))->toBe('base');
});

it('flush() clears bindings, instances, aliases, and extenders', function () {
    $c = new Container();
    $c->singleton('a', fn () => new stdClass());
    $c->alias('a', 'A');
    $c->extend('a', fn ($x) => $x);
    $c->make('a');

    $c->flush();

    expect($c->bound('a'))->toBeFalse()
        ->and($c->resolved('a'))->toBeFalse();
});
```

- [ ] **Step 2: Verify failure**

- [ ] **Step 3: Implement four methods**

```php
public function forgetInstance(string $abstract): void
{
    $abstract = $this->getAlias($abstract);
    $this->remove($abstract); // Phalcon Di::remove
    unset($this->resolved[$abstract]);
}

public function forgetInstances(): void
{
    foreach ($this->getServices() as $name => $_) {
        $this->remove($name);
    }
    $this->resolved = [];
}

public function forgetExtenders(string $abstract): void
{
    unset($this->extenders[$this->getAlias($abstract)]);
}

public function flush(): void
{
    $this->aliases = [];
    $this->resolved = [];
    $this->bindings = [];
    $this->instances = [];
    $this->extenders = [];
    $this->customCreators = [];
    $this->reset(); // Phalcon Di::reset
}
```

> Adjust names to match actual Phare Container properties — read `Container.php` for the existing property set and reuse those.

- [ ] **Step 4: Verify**

- [ ] **Step 5: Commit**

```bash
git add src/Phare/Container/Container.php tests/Container/Lifecycle/ForgetTest.php
git commit -m "feat(container): add forgetInstance/Instances/Extenders + flush()"
```

### Task A4: `scoped` / `scopedIf` / `forgetScopedInstances`

**Files:** `src/Phare/Container/Container.php`, `tests/Container/Lifecycle/ScopedInstancesTest.php`

- [ ] **Step 1: Failing tests**

```php
<?php

use Phare\Container\Container;

it('scoped() resolves the same instance until forgetScopedInstances() runs', function () {
    $c = new Container();
    $c->scoped('req', fn () => new stdClass());

    $a = $c->make('req');
    $b = $c->make('req');
    expect($a)->toBe($b);

    $c->forgetScopedInstances();

    expect($c->make('req'))->not->toBe($a);
});

it('scopedIf() skips registration when an existing binding is present', function () {
    $c = new Container();
    $c->scoped('req', fn () => 'first');
    $c->scopedIf('req', fn () => 'second');

    expect($c->make('req'))->toBe('first');
});
```

- [ ] **Step 2: Verify failure**

- [ ] **Step 3: Implement**

Add `protected array $scopedInstances = [];` plus `scoped()`, `scopedIf()`, `forgetScopedInstances()`. Implementation mirrors `singleton()` but tracks the abstract in `$scopedInstances` so `forgetScopedInstances()` can clear them with `forgetInstance()`.

- [ ] **Step 4: Verify + integration test that `Kernel::terminate()` clears scoped instances**

> Adds a one-line call to `Container::forgetScopedInstances()` from `Foundation\Http\Kernel::terminate()` (or a new `Application::terminating()` hook if Phalcon's lifecycle exposes a more natural seam).

- [ ] **Step 5: Commit**

```bash
git add src/Phare/Container/Container.php src/Phare/Foundation/Http/Kernel.php tests/Container/Lifecycle/ScopedInstancesTest.php
git commit -m "feat(container): scoped()/scopedIf() bindings with forgetScopedInstances()"
```

---

## Group B — Method binding & call autowiring

### Task B1: BoundMethod helper + Container::call()

**Files:** create `src/Phare/Container/BoundMethod.php`, modify `src/Phare/Container/Container.php`, test `tests/Container/MethodBinding/CallAndWrapTest.php`

- [ ] **Step 1: Failing tests**

```php
<?php

use Phare\Container\Container;

class CallTarget
{
    public function greet(string $name): string
    {
        return "hello {$name}";
    }
}

it('call() invokes a Class@method string with autowired dependencies', function () {
    $c = new Container();

    expect($c->call(CallTarget::class . '@greet', ['name' => 'alice']))->toBe('hello alice');
});

it('call() invokes a Closure with autowired dependencies', function () {
    $c = new Container();
    $c->instance('greeting', 'hi');

    $result = $c->call(function (Container $self) {
        return $self->make('greeting');
    });

    expect($result)->toBe('hi');
});

it('call() invokes an array callable [$instance, "method"]', function () {
    $c = new Container();
    $obj = new CallTarget();

    expect($c->call([$obj, 'greet'], ['name' => 'bob']))->toBe('hello bob');
});
```

- [ ] **Step 2: Verify failure**

- [ ] **Step 3: Implement BoundMethod + `call()`**

Mirror `Illuminate\Container\BoundMethod` (parse Class@method, resolve dependencies via Container, return value). Implementation hints:
- For Closures: reflect parameters with `\ReflectionFunction`, resolve typed deps via `$container->make`, accept overrides from `$parameters`.
- For `'Class@method'`: split, instantiate class via `$container->make`, then call same method-resolution helper.
- For `[$obj, 'method']`: reflect with `\ReflectionMethod`, resolve, invoke.

```php
public function call($callback, array $parameters = [], $defaultMethod = null): mixed
{
    return BoundMethod::call($this, $callback, $parameters, $defaultMethod);
}
```

- [ ] **Step 4: Verify**

- [ ] **Step 5: Commit**

```bash
git add src/Phare/Container/BoundMethod.php src/Phare/Container/Container.php tests/Container/MethodBinding/CallAndWrapTest.php
git commit -m "feat(container): add BoundMethod + call() for autowired invocation"
```

### Task B2: `bindMethod` / `hasMethodBinding` / `callMethodBinding`

**Files:** `src/Phare/Container/Container.php`, extend `tests/Container/MethodBinding/CallAndWrapTest.php`

- [ ] **Step 1: Failing tests**

```php
it('routes Class@method calls through registered method bindings', function () {
    $c = new Container();
    $c->bindMethod(CallTarget::class . '@greet', function ($target, $params) {
        return 'OVERRIDDEN ' . $target->greet($params['name'] ?? 'x');
    });

    expect($c->call(CallTarget::class . '@greet', ['name' => 'eve']))->toBe('OVERRIDDEN hello eve');
});

it('hasMethodBinding() reports registered bindings', function () {
    $c = new Container();
    $c->bindMethod('Foo@bar', fn () => null);

    expect($c->hasMethodBinding('Foo@bar'))->toBeTrue()
        ->and($c->hasMethodBinding('Foo@baz'))->toBeFalse();
});
```

- [ ] **Step 2-3: Add `bindMethod`/`hasMethodBinding`/`callMethodBinding` and wire into `BoundMethod::call`** so `Class@method` invocations consult `$methodBindings` before resolving normally.

- [ ] **Step 4: Verify**

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(container): bindMethod()/hasMethodBinding()/callMethodBinding()"
```

### Task B3: `wrap` + `factory`

- [ ] **Step 1: Failing tests**

```php
it('wrap() returns a closure that defers call() execution', function () {
    $c = new Container();
    $wrapped = $c->wrap(function (Container $self) { return 'wrapped'; });
    expect($wrapped)->toBeInstanceOf(Closure::class)
        ->and($wrapped())->toBe('wrapped');
});

it('factory() returns a closure that resolves the abstract on each call', function () {
    $c = new Container();
    $c->bind('thing', fn () => new stdClass());

    $factory = $c->factory('thing');

    $a = $factory(); $b = $factory();
    expect($a)->toBeInstanceOf(stdClass::class)
        ->and($b)->toBeInstanceOf(stdClass::class)
        ->and($a)->not->toBe($b);
});
```

- [ ] **Step 2-3: Implement** trivial wrappers around `call()` and `make()`.

- [ ] **Step 4-5: Verify, commit**

```bash
git commit -m "feat(container): wrap() and factory() helpers"
```

---

## Group C — Resolution lifecycle hooks

### Task C1: `beforeResolving`

- [ ] **Step 1: Failing tests** — register callback, resolve abstract, assert callback fired with `(abstract, parameters, container)`.

- [ ] **Step 2-3: Add `beforeResolvingCallbacks` map + fire in `resolve()` before build.** Mirror `resolving()` callback infrastructure that already exists (`fireResolvingCallbacks`).

- [ ] **Step 4-5: Verify, commit**

```bash
git commit -m "feat(container): beforeResolving() lifecycle hook"
```

### Task C2: `currentlyResolving`

- [ ] **Step 1: Failing test** — within a resolving callback, assert `currentlyResolving()` returns the current build stack as an array.

- [ ] **Step 2-3: Expose `buildStack` (already maintained internally) via a public getter `currentlyResolving()`.**

- [ ] **Step 4-5: Verify, commit**

```bash
git commit -m "feat(container): currentlyResolving() exposes build stack"
```

### Task C3: `refresh($abstract, $target, $method)`

- [ ] **Step 1: Failing test** — `refresh` wires a `rebinding` callback that calls `$target->{$method}($newInstance)` whenever `$abstract` is rebound, returns the freshly-resolved instance.

- [ ] **Step 2-3: Implement** per Laravel:

```php
public function refresh(string $abstract, $target, string $method)
{
    return tap($this->make($abstract), function ($instance) use ($abstract, $target, $method) {
        $this->rebinding($abstract, function ($app, $new) use ($target, $method) {
            $target->$method($new);
        });
    });
}
```

- [ ] **Step 4-5: Verify, commit**

```bash
git commit -m "feat(container): refresh() couples rebinding to a target method"
```

---

## Group D — Attribute-driven contextual binding

### Task D1: `whenHasAttribute(string, Closure)`

**Files:** `src/Phare/Container/Container.php`, `tests/Container/Attributes/WhenHasAttributeTest.php`

- [ ] **Step 1: Failing tests**

```php
<?php

use Phare\Container\Container;

#[Attribute]
class MyTag
{
    public function __construct(public string $label = '') {}
}

class Consumer
{
    public function __construct(#[MyTag('alpha')] public string $value) {}
}

it('resolveFromAttribute() honors a registered whenHasAttribute handler', function () {
    $c = new Container();
    $c->whenHasAttribute(MyTag::class, function (MyTag $attr, Container $app) {
        return strtoupper($attr->label);
    });

    $consumer = $c->make(Consumer::class);

    expect($consumer->value)->toBe('ALPHA');
});
```

- [ ] **Step 2-3: Add `$attributeHandlers` map; in `resolveFromAttribute()` check this map before delegating to the existing `ContextualAttribute::resolve()` path.**

- [ ] **Step 4-5: Verify, commit**

```bash
git commit -m "feat(container): whenHasAttribute() extension point for attribute resolvers"
```

---

## Group E — PSR-11 + ArrayAccess completeness

### Task E1: Implement Psr\Container\ContainerInterface

**Files:** `src/Phare/Container/Container.php`, `composer.json` (if needed), `tests/Container/PSR11AndArrayAccessTest.php`

- [ ] **Step 1: Failing tests**

```php
<?php

use Phare\Container\Container;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

it('implements Psr\\Container\\ContainerInterface', function () {
    expect(new Container())->toBeInstanceOf(ContainerInterface::class);
});

it('get() delegates to make()', function () {
    $c = new Container();
    $c->singleton('thing', fn () => 'value');

    expect($c->get('thing'))->toBe('value');
});

it('has() reports bound abstracts and rejects missing ones', function () {
    $c = new Container();
    $c->singleton('present', fn () => 1);

    expect($c->has('present'))->toBeTrue()
        ->and($c->has('missing'))->toBeFalse();
});

it('get() throws NotFoundExceptionInterface for unknown ids', function () {
    $c = new Container();
    expect(fn () => $c->get('does-not-exist'))->toThrow(NotFoundExceptionInterface::class);
});
```

- [ ] **Step 2: Verify failure**

- [ ] **Step 3: Implement**

Add `implements ContainerInterface`. Add a small `ServiceNotFoundException extends \RuntimeException implements NotFoundExceptionInterface`. Implement `get()` to call `make()` wrapped in a try/catch that converts unknown-binding failures into the PSR-11 exception. Implement `has()` to delegate to `bound() || isShared()`.

> `composer.json` must declare `psr/container` ^2.0 if not already.

- [ ] **Step 4-5: Verify, commit**

```bash
git commit -m "feat(container): implement Psr\\Container\\ContainerInterface (get/has)"
```

### Task E2: ArrayAccess offsetSet / offsetUnset + __set

- [ ] **Step 1: Failing tests** — assert `$c['foo'] = $obj` binds, `$c->foo = $obj` binds, `unset($c['foo'])` clears.

- [ ] **Step 2-3: Implement**

```php
public function offsetSet($offset, $value): void
{
    $this->bind($offset, $value instanceof \Closure ? $value : fn () => $value);
}

public function offsetUnset($offset): void
{
    $this->forgetInstance($offset);
}

public function __set($key, $value): void
{
    $this[$key] = $value;
}
```

- [ ] **Step 4-5: Verify, commit**

```bash
git commit -m "feat(container): full ArrayAccess + __set sugar"
```

---

## Final verification

### Task V1: Full suite + style

- [ ] **Step 1: Run framework tests**

```bash
cd /opt/framework && ./vendor/bin/pest
```

- [ ] **Step 2: Run pint on touched files**

```bash
cd /opt/framework && ./vendor/bin/pint src/Phare/Container tests/Container
```

- [ ] **Step 3: Re-run tests after pint**

### Task V2: Update roadmap

- [ ] Append a new section in `docs/laravel13-phalcon-architecture.md`:

```
### Phase 6 (done): Container edge behaviors

- Group A landed: instance(), extend(), forgetInstance(s), forgetExtenders, flush(), scoped()/scopedIf()/forgetScopedInstances().
- Group B landed: BoundMethod helper, call(), wrap(), factory(), bindMethod()/hasMethodBinding()/callMethodBinding().
- Group C landed: beforeResolving(), currentlyResolving(), refresh().
- Group D landed: whenHasAttribute().
- Group E landed: Psr\Container\ContainerInterface implemented (get/has), offsetSet/offsetUnset/__set.

Group F (environment helpers) deferred — wire only when a concrete use case emerges.
```

Commit:

```bash
git commit -m "docs: Phase 6 (container edge behaviors) complete"
```

---

## Notes

- Each method lands as one focused commit. Aim for clean, small diffs.
- When the spec says `mirror Laravel`, do so verbatim where possible — divergences invite parity bugs later.
- If extracting `BoundMethod` reveals deeper coupling, document and stop; revisit before continuing the rest of Group B.
- Backward compat: every existing `Phare\Container\Container` API stays as-is. New methods are purely additive.
