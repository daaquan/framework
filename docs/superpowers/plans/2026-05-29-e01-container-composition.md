# E01 — Container Composition (drop `extends Phalcon\Di\Di`) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace `class Container extends Phalcon\Di\Di` with a composition seam — Container *holds* an inner `Phalcon\Di\Di` as its service-store backend and `implements DiInterface` by delegation — removing the inheritance downgrade without changing any external behavior, then migrate the 27 service-provider signatures off the Phalcon `DiInterface` type onto the clean `Phare\Contracts\Foundation\Container` contract.

**Architecture:** Phare already owns its resolution layer (`bindings`, `resolved`, `resolvedInstances`, `doMake`, `resolve`, contextual/tags/extenders). The only thing `extends Di` buys is (a) the raw Phalcon service-store ops (`get`/`getShared`/`set`/`getService`/`has`) that `doMake`/`resolveInstance` call internally, and (b) type-identity as a `DiInterface` so Phalcon's MVC stack and Injectable models accept the Container as their service locator. We swap (a) to an injected inner `Phalcon\Di\Di` field and preserve (b) by `implements DiInterface` with 14 delegating methods. `Di::setDefault($container)` still registers the Container itself as Phalcon's default, so `Di::getDefault()` keeps returning the full Phare Container (make/bind + getShared). Net behavior change: **zero**. The test suite is the spec.

**Tech Stack:** PHP 8.2+, Phalcon C-ext `^5.9.2`, Pest 4, PHPStan level 8, Laravel Pint. No DB available in this env (no sqlite) — `tests/Database`, `tests/Eloquent`, `tests/Console` are excluded from the phpunit whitelist and will not run; verification relies on `tests/Container` + the rest of the whitelist.

---

## Background & Discovery

Investigated `2026-05-29`. Findings that shape this plan:

1. **Container barely uses Phalcon's store.** Only ONE `parent::` call exists (`parent::has($alias)` at `Container.php:1283`); no `setShared`/`setRaw`. Phare replaced Phalcon's resolution machinery with its own. The inherited ops that *are* used (`get`, `getShared`, `set`, `getService`) appear in `doMake()` (~`Container.php:582-700`) and `resolveInstance()` (`Container.php:1070-1080`, which calls `$this->set($abstract, $instance, $shared)`).

2. **The app IS the container IS a Phalcon Di.** `AbstractApplication extends Container` (`AbstractApplication.php:20`) and its constructor does `self::setDefault($this)` (`AbstractApplication.php:102`), then passes itself into `Phalcon\Mvc\Micro`/`Application` (`$this->app = $this->createApplication()`). Phalcon's MVC stack requires a `DiInterface` locator.

3. **Eloquent depends on Container-as-DiInterface.** Models extend `Phalcon\Mvc\Model` (the B01 leak) and call `$model->setDI($container)` / `$this->getDI()` across `Eloquent/Relations/*` and `Eloquent/Model.php` (≈12 sites). `setDI()` requires a `DiInterface`.

4. **Static `Di::getDefault()` callers expect raw store reads only.** `helpers.php:105`, `Eloquent/Concerns/HasEvents.php:44,210`, `Eloquent/Concerns/HasAttributes.php:670`, `Console/Config.php:35,79`, `Testing/TestCase.php:83` — every one calls `->getShared(...)` on the result. None call `->make()`/`->bind()` via `Di::getDefault()`. So as long as `Di::getDefault()` still returns something that answers `getShared`, they are safe (and under this design it returns the full Container).

5. **The published Phare contract is already clean.** `Phare\Contracts\Foundation\Container` declares `bound/resolved/isShared/bind/bindIf/singleton/singletonIf/alias/make` with NO Phalcon types. The leak is only on the concrete (`extends Di`) and on provider signatures.

### Scope decision — E01a now, E01b gated; provider migration is E02-remainder

| Sub-milestone | Removes | Gated on |
|---|---|---|
| **E01a (this plan)** | the `extends Di` inheritance downgrade — Container holds an inner `Di` and `implements DiInterface` by delegation | nothing — shippable now |
| **E01b (documented, NOT executed)** | `implements DiInterface` from Container entirely (no Phalcon type on the class at all); the ≈12 Eloquent `setDI/getDI` sites; passing Container into `Phalcon\Mvc\Micro` | **B01** (Eloquent Model composition) + app-core rework (replace `Phalcon\Mvc\Micro`/`Application` as the kernel). Both are independently XL. |
| **Provider signature cleanup** | the Phalcon `DiInterface`/`ServiceProviderInterface` type from the 27 `Providers/*ServiceProvider` | **NOT E01 — this is E02-remainder.** See note below. |

E01a is the correct standalone deliverable: it eliminates the fragile inheritance and gives Phare full control of every store operation (interceptable, swappable backend) — all with a green-suite invariant. Full type removal (E01b) is impossible while a Phalcon C-class (`Mvc\Model`) is the ORM base, so it is explicitly deferred and tracked.

**Why provider migration is NOT in this plan (investigation `2026-05-29`):** PHP forbids parameter-type *narrowing*. The 27 providers `implements Phalcon\Di\ServiceProviderInterface`, whose `register(DiInterface $di)` cannot be narrowed to `register(ContractsContainer $app)` — that is a fatal "Declaration must be compatible" error, not a mechanical edit. The real fix is migrating each provider onto the **already-shipped** E02 base `Phare\Support\ServiceProvider` (`register(): void`, ctor-injected `Phare\Contracts\Foundation\Container`, no Phalcon import). The registration site `AbstractApplication::registerConfiguredProviders()` (`AbstractApplication.php:305-326`) **already dispatches both conventions** (`is_subclass_of(..., Phare\Support\ServiceProvider)` → `new $p($this); $p->register()` vs. Phalcon interface → `new $p(); $p->register($this)`), so per-provider migration is independent and incremental: `implements ServiceProviderInterface` → `extends \Phare\Support\ServiceProvider`, `register(Application|DiInterface $app): void` → `register(): void`, body `$app` → `$this->app`, drop the three Phalcon/Application imports. This is orthogonal to the composition seam (Container implements `DiInterface` either way) and belongs in the E02-remainder milestone, not here.

---

## Target Architecture

### Before
```php
use Phalcon\Di\Di;

class Container extends Di implements ContractsContainer, PsrContainerInterface
{
    // doMake(): $this->getShared($abstract, $parameters)   // inherited from Di
    // resolveInstance(): $this->set($abstract, $instance, $shared)  // inherited
    // ...1390 lines of Phare resolution layer
}
```

### After (E01a)
```php
use Phalcon\Di\Di;
use Phalcon\Di\DiInterface;

class Container implements ContractsContainer, PsrContainerInterface, DiInterface
{
    /** Inner Phalcon service-store backend (was the inherited base). */
    protected DiInterface $phalconDi;

    public function __construct()
    {
        $this->phalconDi = new Di();
    }

    // Phare resolution layer UNCHANGED, except internal store ops now go to $this->phalconDi:
    //   doMake():        $this->phalconDi->getShared($abstract, $parameters)
    //   resolveInstance(): $this->phalconDi->set($abstract, $instance, $shared)
    //   getService():    $this->phalconDi->getService($abstract)
    //   has(): wraps $this->phalconDi->has($alias)  (replaces parent::has)

    // --- DiInterface delegation bridge (14 methods) ---
    public function get(string $name, $parameters = null): mixed { return $this->phalconDi->get($name, $parameters); }
    public function getShared(string $name, $parameters = null): mixed { return $this->phalconDi->getShared($name, $parameters); }
    public function set(string $name, $definition, bool $shared = false): \Phalcon\Di\ServiceInterface { return $this->phalconDi->set($name, $definition, $shared); }
    public function setShared(string $name, $definition): \Phalcon\Di\ServiceInterface { return $this->phalconDi->setShared($name, $definition); }
    public function has(string $name): bool { return $this->phalconDi->has($name); }
    public function remove(string $name): void { $this->phalconDi->remove($name); }
    public function attempt(string $name, $definition, bool $shared = false) { return $this->phalconDi->attempt($name, $definition, $shared); }
    public function getRaw(string $name): mixed { return $this->phalconDi->getRaw($name); }
    public function getService(string $name): \Phalcon\Di\ServiceInterface { return $this->phalconDi->getService($name); }
    public function setService(string $name, \Phalcon\Di\ServiceInterface $rawDefinition): \Phalcon\Di\ServiceInterface { return $this->phalconDi->setService($name, $rawDefinition); }
    public function getServices(): array { return $this->phalconDi->getServices(); }
    // static trio — keep delegating to the Phalcon static registry:
    public static function setDefault(DiInterface $container): void { Di::setDefault($container); }
    public static function getDefault(): ?DiInterface { return Di::getDefault(); }
    public static function reset(): void { Di::reset(); }
}
```

**Why `implements DiInterface` and not a bare `phalconDi()` accessor:** external code (`AbstractApplication`→`Phalcon\Mvc\Micro`, Eloquent `setDI($container)`) passes the Container *where a `DiInterface` is required by Phalcon's own typed signatures*. Until B01 removes that requirement, the Container must satisfy the type. Delegation gives us full control (every call is interceptable) while satisfying it — strictly better than inheritance.

**Default-registration unchanged:** `AbstractApplication::__construct` currently calls `self::setDefault($this)`. After the refactor `self::setDefault` resolves to the new static delegating method above, which forwards to `Di::setDefault($this)`. The Container (implementing `DiInterface`) is accepted, and `Di::getDefault()` returns the Container itself — so the 7 `Di::getDefault()->getShared(...)` call sites are unaffected.

### `has()` collision note
`Container` currently has no `has(string $abstract): bool` of its own — the PSR `has()` and Phalcon `has()` are both satisfied by the inherited `Di::has`. After the change, the single delegating `has()` above serves both `PsrContainerInterface::has` and `DiInterface::has` (identical `(string): bool` shape). The one internal `parent::has($alias)` at line 1283 becomes `$this->phalconDi->has($alias)`.

---

## File Structure

| File | Change | Responsibility after |
|---|---|---|
| `src/Phare/Container/Container.php` | Modify | `implements DiInterface` via delegation to `$this->phalconDi`; resolution layer routes store ops to the inner Di. |
| `src/Phare/Foundation/AbstractApplication.php` | Modify (1-2 lines) | constructor calls `parent::__construct()` to init the seam; `self::setDefault($this)` still valid (now a delegating static). |
| `tests/Container/ContainerCompositionTest.php` | Create | Characterization + seam tests: behavior preserved, `extends Di` gone, inner Di distinct. |
| `.wolf/anatomy.md`, `.wolf/memory.md`, `.wolf/cerebrum.md` | Modify | OpenWolf logging. |

(Provider files are intentionally absent — see the E02-remainder note above.)

---

## Verification protocol (run after every task)

Per session rules — the gate is **zero regression vs. baseline**, not zero errors.

```bash
# baseline capture (once, before Task 1)
./bin/pest 2>&1 | tail -5 > /tmp/e01-pest-baseline.txt          # expect ~1326 passing
git stash && vendor/bin/phpstan analyse 2>&1 | tail -1 > /tmp/e01-phpstan-baseline.txt && git stash pop

# after each task
./bin/pest 2>&1 | tail -5                                       # passing count must not drop
git stash && vendor/bin/phpstan analyse 2>&1 | tail -1 && git stash pop   # compare error count to baseline
```
Run `vendor/bin/pint` before each commit. **Pint gotcha (cerebrum):** pint can relocate a class-level `@param` docblock across a `const`/property block — re-check the delegation docblocks after running it.

---

## Task 1: Capture baseline + characterization test

**Files:**
- Create: `tests/Container/ContainerCompositionTest.php`
- Reference: `src/Phare/Container/Container.php`

- [ ] **Step 1: Capture the green baseline**

Run: `./bin/pest 2>&1 | tail -5`
Expected: `Tests: 1326 passed` (or current count — record the exact number here: `____`).

- [ ] **Step 2: Confirm `tests/Container` is in the phpunit whitelist**

Run: `grep -n "Container" phpunit.xml.dist`
Expected: a `<directory>tests/Container</directory>` (or `suffix`/`Unit` umbrella that includes it) line. If absent, ADD it under `<testsuite>` before proceeding — otherwise the new test silently won't run (see [[phpunit testsuite]] learning).

- [ ] **Step 3: Write the characterization test (locks behavior that MUST survive the refactor)**

```php
<?php

namespace Tests\Container;

use Phalcon\Di\Di;
use Phalcon\Di\DiInterface;
use Phare\Container\Container;
use PHPUnit\Framework\TestCase;

class ContainerCompositionTest extends TestCase
{
    private function makeContainer(): Container
    {
        return new Container();
    }

    public function test_basic_bind_and_make_returns_concrete(): void
    {
        $c = $this->makeContainer();
        $c->bind('foo', fn () => new \stdClass());
        $this->assertInstanceOf(\stdClass::class, $c->make('foo'));
    }

    public function test_singleton_returns_same_instance(): void
    {
        $c = $this->makeContainer();
        $c->singleton('foo', fn () => new \stdClass());
        $this->assertSame($c->make('foo'), $c->make('foo'));
    }

    public function test_bind_default_is_not_shared(): void
    {
        $c = $this->makeContainer();
        $c->bind('foo', fn () => new \stdClass());
        $this->assertNotSame($c->make('foo'), $c->make('foo'));
    }

    public function test_closure_singleton_returning_array_bypasses_phalcon_store(): void
    {
        // Regression guard: closure singletons returning non-objects must not
        // hit Phalcon's "Missing 'className' parameter" path (see doMake notes).
        $c = $this->makeContainer();
        $c->singleton('arr', fn () => ['a' => 1]);
        $this->assertSame(['a' => 1], $c->make('arr'));
        $this->assertSame($c->make('arr'), $c->make('arr'));
    }

    public function test_alias_resolves_to_target(): void
    {
        $c = $this->makeContainer();
        $c->bind('foo', fn () => new \stdClass());
        $c->alias('foo', 'bar');
        $this->assertInstanceOf(\stdClass::class, $c->make('bar'));
    }

    public function test_is_shared_reflects_binding(): void
    {
        $c = $this->makeContainer();
        $c->singleton('s', fn () => new \stdClass());
        $c->bind('b', fn () => new \stdClass());
        $this->assertTrue($c->isShared('s'));
        $this->assertFalse($c->isShared('b'));
    }

    public function test_container_is_a_phalcon_di_interface(): void
    {
        // Must remain a DiInterface (E01a preserves this; E01b removes it, gated on B01).
        $this->assertInstanceOf(DiInterface::class, $this->makeContainer());
    }

    public function test_getshared_delegates_to_phalcon_store(): void
    {
        $c = $this->makeContainer();
        $c->set('svc', fn () => new \stdClass(), true);
        $this->assertInstanceOf(\stdClass::class, $c->getShared('svc'));
    }
}
```

- [ ] **Step 4: Run it — must PASS now (characterization, not RED)**

Run: `./bin/pest tests/Container/ContainerCompositionTest.php`
Expected: 8 passed. (These pass against the *current* `extends Di` code; they must still pass after the refactor.)

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint tests/Container/ContainerCompositionTest.php
git add tests/Container/ContainerCompositionTest.php phpunit.xml.dist
git commit -m "test(container): characterization tests pinning Container behavior pre-E01"
```

---

## Task 2: Introduce the inner-Di seam (still `extends Di`)

Intermediate step — add the field and route internal store ops to it while still extending, so the suite proves the routing is correct in isolation before the type change.

**Files:**
- Modify: `src/Phare/Container/Container.php`

- [ ] **Step 1: Add a failing test for the seam**

Append to `tests/Container/ContainerCompositionTest.php`:

```php
    public function test_exposes_inner_phalcon_di_accessor(): void
    {
        $c = $this->makeContainer();
        $this->assertInstanceOf(DiInterface::class, $c->phalconDi());
    }
```

- [ ] **Step 2: Run — verify it FAILS**

Run: `./bin/pest tests/Container/ContainerCompositionTest.php --filter=phalcon_di_accessor`
Expected: FAIL — `Error: Call to undefined method Phare\Container\Container::phalconDi()`.

- [ ] **Step 3: Add the field, constructor init, and accessor**

In `Container.php`, add the import and (near the top of the class body, before `$reservedServices`):

```php
use Phalcon\Di\Di;
use Phalcon\Di\DiInterface;

// inside the class:
    protected DiInterface $phalconDi;

    public function __construct()
    {
        $this->phalconDi = $this;   // PHASE-2 BRIDGE: still self while extending Di
    }

    /** Inner Phalcon service-store backend. */
    public function phalconDi(): DiInterface
    {
        return $this->phalconDi;
    }
```

> Note: `$this->phalconDi = $this` during Task 2 keeps behavior byte-identical (the store IS still the inherited base) while introducing the indirection point. Task 4 flips it to a separate `new Di()`. Confirm `AbstractApplication::__construct` calls `parent::__construct()` — see Step 5.

- [ ] **Step 4: Route internal store ops through the accessor**

Replace the internal inherited calls (NOT the public API) so they read/write via `$this->phalconDi`:
- In `doMake()` (~line 600-660): `$this->getShared($abstract, $parameters)` → `$this->phalconDi->getShared($abstract, $parameters)`; `$this->get($abstract, ...)` → `$this->phalconDi->get(...)`; `$this->getService($abstract)` → `$this->phalconDi->getService($abstract)`.
- In `resolveInstance()` (~line 1072): `$this->set($abstract, $instance, $shared)` → `$this->phalconDi->set($abstract, $instance, $shared)`.
- At line 1283: `parent::has($alias)` → `$this->phalconDi->has($alias)`.

- [ ] **Step 5: Ensure AbstractApplication initializes the seam**

Read `src/Phare/Foundation/AbstractApplication.php:100-107`. The constructor must call `parent::__construct()` so `$this->phalconDi` is set. Currently it does NOT call parent (it starts with `self::setDefault($this)`). Add as the first line:

```php
    public function __construct(protected string $basePath)
    {
        parent::__construct();           // initialize the composition seam
        self::setDefault($this);
        $this->app = $this->createApplication();
        $this->singleton(ApplicationContract::class, $this);
    }
```

- [ ] **Step 6: Run the accessor test + full suite**

Run: `./bin/pest tests/Container/ContainerCompositionTest.php`
Expected: 9 passed.
Run: `./bin/pest 2>&1 | tail -5`
Expected: passing count == baseline (Step-1 Task-1 number). If lower, STOP and use superpowers:systematic-debugging.

- [ ] **Step 7: phpstan baseline check + commit**

```bash
git stash && vendor/bin/phpstan analyse 2>&1 | tail -1 && git stash pop   # == baseline
vendor/bin/pint src/Phare/Container/Container.php src/Phare/Foundation/AbstractApplication.php
git add src/Phare/Container/Container.php src/Phare/Foundation/AbstractApplication.php tests/Container/ContainerCompositionTest.php
git commit -m "refactor(container): introduce phalconDi() seam routing store ops (still extends Di)"
```

---

## Task 3: Flip to composition — `implements DiInterface`, drop `extends Di`

The structural core. After this the class no longer inherits Phalcon Di; it holds one.

**Files:**
- Modify: `src/Phare/Container/Container.php`

- [ ] **Step 1: Add the failing test asserting `extends` is gone but interface remains**

Append to `tests/Container/ContainerCompositionTest.php`:

```php
    public function test_container_no_longer_extends_phalcon_di_class(): void
    {
        $parent = (new \ReflectionClass(Container::class))->getParentClass();
        $this->assertFalse($parent && $parent->getName() === Di::class,
            'Container must hold a Phalcon Di by composition, not extend it.');
    }

    public function test_inner_di_is_distinct_instance_from_container(): void
    {
        $c = $this->makeContainer();
        $this->assertNotSame($c, $c->phalconDi());
        $this->assertInstanceOf(Di::class, $c->phalconDi());
    }
```

- [ ] **Step 2: Run — verify FAIL**

Run: `./bin/pest tests/Container/ContainerCompositionTest.php --filter='no_longer_extends|distinct_instance'`
Expected: FAIL — `extends Di` still true and `phalconDi === $this`.

- [ ] **Step 3: Change the class declaration and constructor**

```php
// line 38:
class Container implements ContractsContainer, PsrContainerInterface, DiInterface

// constructor:
    public function __construct()
    {
        $this->phalconDi = new Di();
    }
```

- [ ] **Step 4: Add the 14 DiInterface delegation methods**

Add the full delegation block from the "Target Architecture / After" section above (`get`, `getShared`, `set`, `setShared`, `has`, `remove`, `attempt`, `getRaw`, `getService`, `setService`, `getServices`, and the static `setDefault`/`getDefault`/`reset`). Copy signatures exactly from `vendor/phalcon/ide-stubs/src/Di/DiInterface.php` to match param/return types (PHPStan level 8 will catch mismatches).

> Resolve the `has()` collision: the single delegating `has(string $name): bool` satisfies both `PsrContainerInterface::has` and `DiInterface::has`. Remove any now-duplicate.

- [ ] **Step 5: Run full suite — the critical checkpoint**

Run: `./bin/pest 2>&1 | tail -15`
Expected: passing count == baseline. Likely failure modes and fixes:
- `Di::setDefault()` rejecting the Container → ensure Container `implements DiInterface` (Phalcon's `setDefault(DiInterface $container)` accepts it).
- A Phalcon MVC component calling a store method not in the 14 → add the missing delegation method (re-check the stub).
- `Di::getDefault()` returning `null` in a test that didn't bootstrap → unrelated to refactor; confirm against baseline behavior.

If anything fails, use superpowers:systematic-debugging — do NOT weaken the tests.

- [ ] **Step 6: phpstan + pint + commit**

```bash
git stash && vendor/bin/phpstan analyse 2>&1 | tail -1 && git stash pop   # == baseline
vendor/bin/pint src/Phare/Container/Container.php
# re-verify delegation docblocks weren't relocated by pint (cerebrum gotcha)
git add src/Phare/Container/Container.php tests/Container/ContainerCompositionTest.php
git commit -m "refactor(container): Container holds Phalcon Di by composition, implements DiInterface"
```

---

## Task 4: Final verification + OpenWolf logging

**Files:**
- Modify: `.wolf/anatomy.md`, `.wolf/memory.md`, `.wolf/cerebrum.md`

- [ ] **Step 1: Full green-suite + zero-regression confirmation**

Run: `./bin/pest 2>&1 | tail -5` → passing == baseline.
Run: `git stash && vendor/bin/phpstan analyse 2>&1 | tail -1 && git stash pop` → error count == baseline.
Run: `vendor/bin/pint --test` → no fixes needed.

- [ ] **Step 2: Grep-confirm the inheritance is fully gone**

Run: `grep -rn "extends Di\b\|extends Phalcon" src/Phare/Container/Container.php`
Expected: no matches.
Run: `grep -rn "instanceof DiInterface" tests/Container/ContainerCompositionTest.php`
Expected: the bridge assertion still present (E01a preserves `implements DiInterface`).

- [ ] **Step 3: Update anatomy.md**

Update the `Container.php` entry (line ~265) to note "composition: holds inner Phalcon Di, implements DiInterface by delegation". Add the two new test files under `tests/Container/`.

- [ ] **Step 4: Update cerebrum.md**

- Decision Log: record E01a done via composition seam; E01b (drop `implements DiInterface`) gated on B01 + Phalcon Mvc app-core rework.
- Key Learnings: "Container replaced Phalcon's resolution store with its own; `extends Di` was only type-identity + a handful of internal store ops + 1 `parent::has`. Composition was tractable, not XL-as-feared, once measured."
- Update the "Area-E learnings" E01 line: `[x] downgrade` → `[~] composition seam shipped; full type-removal gated on B01`.
- Note for E02-remainder: registration site `registerConfiguredProviders()` already dispatches both provider conventions; the 27 `Providers/*` migrate to `Phare\Support\ServiceProvider` per-file (`extends ServiceProvider`, `register(): void`, `$app`→`$this->app`).

- [ ] **Step 5: Append memory.md session line + commit**

```bash
git add .wolf/
git commit -m "chore(wolf): session log — E01a Container composition"
```

- [ ] **Step 6: Finish the branch**

Use superpowers:finishing-a-development-branch to merge `worktree-e01-container-composition` into `main` (or open a PR per user preference). The user commits to `main` directly.

---

## E01b — Deferred (documented, do NOT execute in this plan)

Removing `implements DiInterface` entirely requires, in order:
1. **B01** — Eloquent `Model` stops extending `Phalcon\Mvc\Model`, removing the ≈12 `setDI(DiInterface)`/`getDI()` call sites in `Eloquent/Relations/*` and `Eloquent/Model.php`.
2. **App-core rework** — `AbstractApplication` stops `extends Container` and stops passing itself into `Phalcon\Mvc\Micro`/`Application` (or those are wrapped so the Phalcon DI is the inner `phalconDi`, not the Container).
3. Migrate the remaining external `getShared`/`getDI` consumers (`Http/Controller.php`, `helpers.php:105`, `Console/Config.php`, `Eloquent/Concerns/*`) onto `Phare\Contracts\Foundation\Container::make()` + a narrow `phalconDi()` accessor for genuine Phalcon-service needs.
4. Drop `implements DiInterface`; `Di::setDefault()` then receives `$container->phalconDi()` instead of `$container`, and `Di::getDefault()` callers that need Phare APIs switch to `app()`.

Track as its own plan once B01 lands.

---

## Self-Review

**Spec coverage:**
- Drop `extends Di` → Task 3. ✓
- Preserve all behavior → characterization Task 1 + green-suite gate every task. ✓
- Provider signature cleanup → explicitly scoped OUT (E02-remainder), with the PHP param-narrowing rationale + the already-shipped E02 base + the dual-dispatch registration site documented. ✓ (honest gap, not omission)
- Full DiInterface removal → explicitly scoped OUT (E01b), gated on B01, with rationale. ✓
- OpenWolf logging → Task 4. ✓

**Placeholder scan:** No `TBD`/`TODO`/"handle edge cases". Every code step shows the code; every run step shows the command + expected output. The one fill-in (Task 1 Step 1 "record the exact number: `____`") is a baseline capture the executor reads off the suite, not hand-waving.

**Type consistency:** `phalconDi()` accessor name used identically in Tasks 2/3/4. `ContractsContainer` alias matches the existing import in `Container.php:34`. Delegation method signatures deferred to the canonical stub file (`vendor/phalcon/ide-stubs/src/Di/DiInterface.php`, 14 methods) to avoid drift.

**Known risk flagged:** Task 3 Step 5 is the single critical checkpoint — flipping `extends`→`implements` runs the full suite to surface any Phalcon MVC store-method the 14-method bridge missed; the step lists the likely failure modes and their fixes, and forbids weakening tests (use systematic-debugging instead). The provider param-narrowing hazard that would have sunk a naive "swap the signature" approach was caught during planning and routed to E02-remainder rather than buried in E01.
