# A07 View Dual-Stack Resolution — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the debug-string stub in `Phare\View\View::render()` with a real renderer wired through a new `Contracts\View\Engine` seam + `BladeEngine` adapter, making the modern `Factory`/`View` stack functional and resolving the D08 dual-stack defect.

**Architecture:** A 1-method `Engine` interface decouples the `View` value object from the BladeOne engine. `BladeEngine` adapts `Phare\View\Blade` (BladeOne). `Factory` resolves an engine and injects it into every `View` it builds. `ViewServiceProvider` wires a `BladeEngine` (built from app paths) into the Factory and is registered in the canonical mock config.

**Tech Stack:** PHP 8.2, Pest 2, Phalcon 5 ext, BladeOne v4.9 (vendored), Pint, PHPStan L8.

**Working rules (every task):** TDD RED→GREEN. After each task: `./bin/pest` full green; phpstan 0-regression vs baseline on touched files (`git stash push -- <file>` → count → pop → count, compare); `vendor/bin/pint <files>` clean. Commit per task. No DB (tests/Database, tests/Eloquent, tests/Console excluded from suite).

---

### Task 1: `Contracts\View\Engine` interface

**Files:**
- Create: `src/Phare/Contracts/View/Engine.php`
- Test: `tests/Unit/View/EngineContractTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/View/EngineContractTest.php

use Phare\Contracts\View\Engine;

it('declares a render method returning string', function () {
    $rm = new ReflectionMethod(Engine::class, 'render');

    expect($rm->getNumberOfParameters())->toBe(2);
    expect($rm->getReturnType()?->getName())->toBe('string');
    expect($rm->getParameters()[0]->getName())->toBe('view');
    expect($rm->getParameters()[1]->getName())->toBe('data');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./bin/pest tests/Unit/View/EngineContractTest.php`
Expected: FAIL — `Interface "Phare\Contracts\View\Engine" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace Phare\Contracts\View;

interface Engine
{
    /**
     * Render a template to a string.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./bin/pest tests/Unit/View/EngineContractTest.php`
Expected: PASS.

- [ ] **Step 5: Verify gates + commit**

```bash
cd /opt/framework
vendor/bin/pint src/Phare/Contracts/View/Engine.php tests/Unit/View/EngineContractTest.php
git add src/Phare/Contracts/View/Engine.php tests/Unit/View/EngineContractTest.php
git commit -m "feat(view): add Contracts\\View\\Engine interface (A07)"
```

---

### Task 2: `BladeEngine` adapter (wraps BladeOne)

**Files:**
- Create: `src/Phare/View/Engines/BladeEngine.php`
- Test: `tests/Unit/View/Engines/BladeEngineTest.php`

`Phare\View\Blade extends BladeOne`. BladeOne ctor: `__construct($templatePath = null, $compiledPath = null, $mode = 0)`. Render: `run($view = null, $variables = []): string`. BladeOne resolves `'foo'` → `{templatePath}/foo.blade.php`, compiling into `{compiledPath}`.

- [ ] **Step 1: Write the failing test** (real Blade + tmp fixture, no app bootstrap)

```php
<?php
// tests/Unit/View/Engines/BladeEngineTest.php

use Phare\View\Blade;
use Phare\View\Engines\BladeEngine;

beforeEach(function () {
    $this->tmpViews = sys_get_temp_dir() . '/phare_views_' . getmypid();
    $this->tmpCompiled = sys_get_temp_dir() . '/phare_compiled_' . getmypid();
    @mkdir($this->tmpViews, 0777, true);
    @mkdir($this->tmpCompiled, 0777, true);
    file_put_contents($this->tmpViews . '/hello.blade.php', 'Hello {{ $name }}!');
});

afterEach(function () {
    array_map('unlink', glob($this->tmpViews . '/*') ?: []);
    array_map('unlink', glob($this->tmpCompiled . '/*') ?: []);
    @rmdir($this->tmpViews);
    @rmdir($this->tmpCompiled);
});

it('renders a blade template through the BladeOne engine', function () {
    $blade = new Blade($this->tmpViews, $this->tmpCompiled, Blade::MODE_DEBUG);
    $engine = new BladeEngine($blade);

    expect($engine->render('hello', ['name' => 'World']))->toBe('Hello World!');
});

it('is a View Engine', function () {
    $engine = new BladeEngine(new Blade($this->tmpViews, $this->tmpCompiled));
    expect($engine)->toBeInstanceOf(\Phare\Contracts\View\Engine::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./bin/pest tests/Unit/View/Engines/BladeEngineTest.php`
Expected: FAIL — `Class "Phare\View\Engines\BladeEngine" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace Phare\View\Engines;

use Phare\Contracts\View\Engine;
use Phare\View\Blade;

class BladeEngine implements Engine
{
    public function __construct(protected Blade $blade) {}

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $view, array $data = []): string
    {
        return $this->blade->run($view, $data);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./bin/pest tests/Unit/View/Engines/BladeEngineTest.php`
Expected: PASS (both). If `Blade::MODE_DEBUG` is undefined, use `Blade::MODE_SLOW` or the numeric mode the BladeOne copy ships — confirm with `grep -n "const MODE_" src/Phare/View/BladeOne.php` and use the slowest/always-recompile mode.

- [ ] **Step 5: Verify gates + commit**

```bash
cd /opt/framework
vendor/bin/pint src/Phare/View/Engines/BladeEngine.php tests/Unit/View/Engines/BladeEngineTest.php
git add src/Phare/View/Engines/BladeEngine.php tests/Unit/View/Engines/BladeEngineTest.php
git commit -m "feat(view): BladeEngine adapter wrapping BladeOne (A07)"
```

---

### Task 3: `View` holds an engine; `render()` delegates

**Files:**
- Modify: `src/Phare/View/View.php` (ctor line 17-20; `render()` line 97-106)
- Test: `tests/Unit/View/ViewTest.php` (update render tests at lines 88-117)

- [ ] **Step 1: Write the failing tests** — append to `tests/Unit/View/ViewTest.php`, and REPLACE the existing `'view render returns string when view is set'` (L93-102) and `'view toString returns render result'` (L104-111) tests with engine-based versions.

First add a fake engine + new tests at the end of the file:

```php
// --- A07 engine delegation ---

function makeFakeEngine(): Phare\Contracts\View\Engine
{
    return new class implements Phare\Contracts\View\Engine {
        public array $calls = [];

        public function render(string $view, array $data = []): string
        {
            $this->calls[] = [$view, $data];

            return "rendered:{$view}:" . json_encode($data);
        }
    };
}

test('render delegates to the injected engine', function () {
    $engine = makeFakeEngine();
    $view = new Phare\View\View($this->container, $engine);
    $view->setView('home.index')->with('a', 1);

    expect($view->render())->toBe('rendered:home.index:' . json_encode(['a' => 1]));
    expect($engine->calls)->toBe([['home.index', ['a' => 1]]]);
});

test('render throws when no engine is bound', function () {
    $view = new Phare\View\View($this->container);
    $view->setView('home.index');

    expect(fn () => $view->render())
        ->toThrow(RuntimeException::class, 'No view engine bound.');
});
```

Then REPLACE the two old debug-string tests (L93-111) with:

```php
test('view render returns the engine output when view is set', function () {
    $engine = makeFakeEngine();
    $view = new Phare\View\View($this->container, $engine);
    $view->setView('test.view')->with('data', 'value');

    expect($view->render())->toContain('rendered:test.view');
});

test('view toString returns render result', function () {
    $engine = makeFakeEngine();
    $view = new Phare\View\View($this->container, $engine);
    $view->setView('test.view');

    expect((string)$view)->toContain('rendered:test.view');
});
```

Leave the `'view render throws exception when no view is set'` (L88-91) and `'view toString returns empty string on exception'` (L113-117) tests unchanged — they still hold (no-view throws `InvalidArgumentException`; `__toString` catches).

- [ ] **Step 2: Run test to verify it fails**

Run: `./bin/pest tests/Unit/View/ViewTest.php`
Expected: FAIL — `View::__construct()` does not accept a 2nd arg / `render delegates` fails because the engine isn't used.

- [ ] **Step 3: Write minimal implementation** — edit `src/Phare/View/View.php`.

Add the import and an `$engine` property + ctor param. Replace lines 17-20:

```php
    public function __construct(Container $container, ?\Phare\Contracts\View\Engine $engine = null)
    {
        $this->container = $container;
        $this->engine = $engine;
    }
```

Add the property near line 15 (after `public Container $container;`):

```php
    protected ?\Phare\Contracts\View\Engine $engine = null;
```

Replace `render()` body (lines 97-106) with:

```php
    public function render(): string
    {
        if (!$this->view) {
            throw new \InvalidArgumentException('No view specified.');
        }

        if ($this->engine === null) {
            throw new \RuntimeException('No view engine bound.');
        }

        return $this->engine->render($this->view, $this->getData());
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./bin/pest tests/Unit/View/ViewTest.php`
Expected: PASS (all).

- [ ] **Step 5: Verify gates + commit**

```bash
cd /opt/framework
./bin/pest 2>&1 | tail -3   # full suite must stay green
vendor/bin/pint src/Phare/View/View.php tests/Unit/View/ViewTest.php
git add src/Phare/View/View.php tests/Unit/View/ViewTest.php
git commit -m "feat(view): View::render() delegates to injected Engine, drop debug stub (A07)"
```

---

### Task 4: `Factory` injects the engine into each `View`

**Files:**
- Modify: `src/Phare/View/Factory.php` (ctor line 22-26; `make()` line 35)
- Test: `tests/Unit/View/FactoryTest.php` (append)

- [ ] **Step 1: Write the failing test** — append to `tests/Unit/View/FactoryTest.php`:

```php
test('factory injects its engine into the views it makes', function () {
    $engine = new class implements Phare\Contracts\View\Engine {
        public function render(string $view, array $data = []): string
        {
            return "E:{$view}";
        }
    };

    $factory = new Phare\View\Factory(new Phare\Container\Container(), $engine);
    $view = $factory->make('dashboard', ['x' => 1]);

    expect($view->render())->toBe('E:dashboard');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./bin/pest tests/Unit/View/FactoryTest.php`
Expected: FAIL — `Factory::__construct()` does not accept a 2nd arg (or the made View has no engine → throws `No view engine bound.`).

- [ ] **Step 3: Write minimal implementation** — edit `src/Phare/View/Factory.php`.

Add a property after line 20 (`protected array $paths = [];`):

```php
    protected ?\Phare\Contracts\View\Engine $engine = null;
```

Replace the ctor (lines 22-26):

```php
    public function __construct(Container $container, ?\Phare\Contracts\View\Engine $engine = null)
    {
        $this->container = $container;
        $this->engine = $engine;
        $this->paths = ['resources/views'];
    }
```

Replace line 35 (`$viewInstance = new View($this->container);`):

```php
        $viewInstance = new View($this->container, $this->engine);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./bin/pest tests/Unit/View/FactoryTest.php`
Expected: PASS.

- [ ] **Step 5: Verify gates + commit**

```bash
cd /opt/framework
./bin/pest 2>&1 | tail -3
vendor/bin/pint src/Phare/View/Factory.php tests/Unit/View/FactoryTest.php
git add src/Phare/View/Factory.php tests/Unit/View/FactoryTest.php
git commit -m "feat(view): Factory injects Engine into built Views (A07)"
```

---

### Task 5: `ViewServiceProvider` wires a `BladeEngine`; register in mock config

**Files:**
- Modify: `src/Phare/View/ViewServiceProvider.php` (`register()`)
- Modify: `tests/Mock/config/app.php` (providers array)
- Test: `tests/Unit/View/ViewServiceProviderWiringTest.php`

The provider must build a `Blade` from app paths and wrap it in a `BladeEngine`, then pass it to the `Factory`. App paths: `resourcePath('views')` for templates, `storagePath('framework/views')` for compiled output (matches `BladeViewProvider`).

- [ ] **Step 1: Write the failing test** — fresh container, no bootstrap, tmp fixture:

```php
<?php
// tests/Unit/View/ViewServiceProviderWiringTest.php

use Phare\Container\Container;
use Phare\View\Factory;
use Phare\View\ViewServiceProvider;

beforeEach(function () {
    $this->tmpViews = sys_get_temp_dir() . '/phare_vsp_views_' . getmypid();
    $this->tmpStorage = sys_get_temp_dir() . '/phare_vsp_storage_' . getmypid();
    @mkdir($this->tmpViews, 0777, true);
    @mkdir($this->tmpStorage . '/framework/views', 0777, true);
    file_put_contents($this->tmpViews . '/greet.blade.php', 'Hi {{ $who }}');

    $this->app = new Container();
    // Minimal path stubs the provider needs.
    $this->app->instance('__views_path', $this->tmpViews);
    $this->app->instance('__storage_path', $this->tmpStorage);
});

afterEach(function () {
    array_map('unlink', glob($this->tmpViews . '/*') ?: []);
    array_map('unlink', glob($this->tmpStorage . '/framework/views/*') ?: []);
    @rmdir($this->tmpViews);
    @rmdir($this->tmpStorage . '/framework/views');
    @rmdir($this->tmpStorage);
});

it('binds a functional Factory to the view slot', function () {
    (new ViewServiceProvider($this->app))->register();

    $factory = $this->app->make('view');
    expect($factory)->toBeInstanceOf(Factory::class);
    expect($factory->make('greet', ['who' => 'Sam'])->render())->toBe('Hi Sam');
});
```

NOTE: the provider reads real `resourcePath()`/`storagePath()` off the app. For this isolated test we feed paths through container instances `__views_path` / `__storage_path` and have the provider prefer them when present (a tiny, test-only seam). See implementation.

- [ ] **Step 2: Run test to verify it fails**

Run: `./bin/pest tests/Unit/View/ViewServiceProviderWiringTest.php`
Expected: FAIL — provider's `register()` still binds the old closure (no engine) → `make('greet')->render()` throws `No view engine bound.`

- [ ] **Step 3: Write minimal implementation** — replace `ViewServiceProvider::register()` body with engine wiring. Keep the existing `boot()`, `registerComposers()`, `shareGlobalData()` methods and the dead-`view()`-function block unchanged.

```php
    public function register(): void
    {
        $this->app->singleton('view', function ($app) {
            $views = $app->bound('__views_path')
                ? $app->make('__views_path')
                : $app->resourcePath('views');
            $storage = $app->bound('__storage_path')
                ? $app->make('__storage_path')
                : $app->storagePath('framework/views');

            $blade = new \Phare\View\Blade($views, $storage, \Phare\View\Blade::MODE_DEBUG);
            $engine = new \Phare\View\Engines\BladeEngine($blade);

            $factory = new Factory($app, $engine);
            $factory->addExtension('.blade.php', 'blade');
            $factory->addExtension('.php', 'php');

            return $factory;
        });

        $this->app->bind(Factory::class, fn ($app) => $app['view']);
    }
```

If `Blade::MODE_DEBUG` is undefined, substitute the always-recompile mode found via `grep -n "const MODE_" src/Phare/View/BladeOne.php`.

- [ ] **Step 4: Run test to verify it passes**

Run: `./bin/pest tests/Unit/View/ViewServiceProviderWiringTest.php`
Expected: PASS.

- [ ] **Step 5: Register the provider in the canonical mock config**

Edit `tests/Mock/config/app.php`: add the import near the other `use` lines:

```php
use Phare\View\ViewServiceProvider;
```

and add `ViewServiceProvider::class,` to the `'providers'` array (after `ResponseProvider::class,`).

- [ ] **Step 6: Run the FULL suite to verify no bootstrap regression**

Run: `./bin/pest 2>&1 | tail -4`
Expected: full green, count = prior + the new tests. If a bootstrapped test fails because it resolves `'view'` or calls the global `view()` helper, STOP and report — that signals the shared-config registration is unsafe and the provider should be left out of mock config (the isolated wiring test in Step 1 still covers the wiring). The discovery scan showed zero bootstrapped tests resolve `'view'`, so this is expected to pass.

- [ ] **Step 7: Verify gates + commit**

```bash
cd /opt/framework
vendor/bin/pint src/Phare/View/ViewServiceProvider.php tests/Unit/View/ViewServiceProviderWiringTest.php tests/Mock/config/app.php
git add src/Phare/View/ViewServiceProvider.php tests/Unit/View/ViewServiceProviderWiringTest.php tests/Mock/config/app.php
git commit -m "feat(view): ViewServiceProvider wires BladeEngine; register in mock config (A07)"
```

---

### Task 6: Update anatomy + cerebrum + session log

**Files:**
- Modify: `.wolf/anatomy.md`, `.wolf/cerebrum.md`, `.wolf/memory.md`

- [ ] **Step 1: anatomy.md** — add entries for `src/Phare/Contracts/View/Engine.php`, `src/Phare/View/Engines/BladeEngine.php`; update `View.php` and `Factory.php` descriptions (now engine-backed).

- [ ] **Step 2: cerebrum.md** — under Key Learnings, record: "A07 D08 resolved (functional canonical): `View::render()` now delegates to `Contracts\View\Engine` (BladeEngine→BladeOne), injected by Factory. `ViewServiceProvider` is canonical; the global `view()` helper (helpers.php:215, returns `Blade`, Stack-2-coupled) and the competing `'view'` rebinds in BladeViewProvider/VoltViewProvider/ViewProvider remain — full single-slot consolidation + the ~30 missing directives are out of scope."

- [ ] **Step 3: memory.md** — append the session log line.

- [ ] **Step 4: Commit**

```bash
cd /opt/framework
git add .wolf/anatomy.md .wolf/cerebrum.md .wolf/memory.md
git commit -m "chore(wolf): session log — A07 view dual-stack (D08) resolved"
```

---

## Self-Review

- **Spec coverage:** Engine contract (T1) ✓ · BladeEngine adapter (T2) ✓ · View::render() delegation + drop stub (T3) ✓ · Factory injection (T4) ✓ · ViewServiceProvider wiring + mock config (T5) ✓ · error handling (no-view `InvalidArgumentException` retained, no-engine `RuntimeException` T3) ✓ · unit fake-engine test (T3) ✓ · integration real-Blade test (T2) ✓ · wiring/mock-config test (T5) ✓. Out-of-scope items (directives, x-component, multi-engine, global `view()` helper rewrite, removing competing rebinds) explicitly deferred.
- **Type consistency:** `Engine::render(string $view, array $data = []): string` used identically in BladeEngine (T2), View (T3), Factory (T4), fakes (T3/T4/T5). Ctor signatures: `View(Container, ?Engine)`, `Factory(Container, ?Engine)`, `BladeEngine(Blade)` — consistent across tasks.
- **Placeholder scan:** none — every code step shows full code; the only conditional is the documented `Blade::MODE_*` constant verification.
- **Risk note:** T5 Step 6 has an explicit STOP/rollback path if the shared mock-config change breaks bootstrap, with the isolated wiring test as the fallback coverage.
