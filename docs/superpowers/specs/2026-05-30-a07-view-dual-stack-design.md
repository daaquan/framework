# A07 — View Dual-Stack Resolution (D08 CRITICAL)

**Date:** 2026-05-30
**Status:** approved, ready for implementation plan
**Scope:** Resolve the D08 dual-stack defect in the view layer. Wire the `'view'`
container slot to a functional renderer. NOT a full Blade-parity pass.

## Problem

Phare ships two parallel, conflicting view stacks bound to the same `'view'`
container slot:

- **Stack-1 (modern API, non-functional):** `Phare\View\ViewServiceProvider`
  binds `'view'` → `Phare\View\Factory`, which builds `Phare\View\View` value
  objects. **`View::render()` is a debug-string stub** — returns
  `"View: {name} with data: " . json_encode($data)` and never invokes a
  compiler. Has test coverage (`tests/Unit/View/{Factory,View,ViewComposer,Template/TemplateEngine}Test.php`).
- **Stack-2 (Phalcon-native, functional):** `Phare\Providers\BladeViewProvider`
  binds `'blade'` → `Phare\View\Blade` and **re-binds `'view'`** → `BladeView`.
  Real rendering runs through a Phalcon `dispatch:afterExecuteRoute` event that
  calls `$app['blade']->run()`. `BladeView::render()` itself only throws.
- **Engine:** `Phare\View\Blade extends BladeOne` (vendored EFTEC/BladeOne v4.9)
  is the only functional renderer; `run($view, $variables): string`.
- `Phare\Providers\ViewProvider` and `VoltViewProvider` ALSO bind `'view'`
  (to Phalcon `View`), compounding the slot conflict.
- The mock app config (`tests/Mock/config/app.php`) registers **no** view
  provider, so the suite never exercises view rendering — render regressions are
  invisible.
- No `Phare\Contracts\View\*` namespace exists (no published interface).

**Decision (user-approved):** make the BladeOne engine the canonical functional
renderer; wire Stack-1's `View::render()` to it through a contract + adapter
seam. Keep the modern `Factory`/`View` API as the canonical surface.

## Architecture

Introduce an isolation boundary between the view value object and the rendering
engine:

```php
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

`Phare\View\Engines\BladeEngine implements Engine` wraps a `Phare\View\Blade`
(BladeOne) instance and delegates `render()` → `$blade->run($view, $data)`.

`Phare\View\View` holds an optional `Engine`. `View::render()` delegates to it.
`Phare\View\Factory::make()` injects the resolved engine into each `View`.

Benefits: closes the A07 no-contract gap; `View::render()` is unit-testable with
a fake `Engine` (no disk/templates); BladeOne coupling isolated to one adapter.

## Components

| File | Change |
|------|--------|
| `src/Phare/Contracts/View/Engine.php` | NEW — 1-method interface |
| `src/Phare/View/Engines/BladeEngine.php` | NEW — wraps `Blade`, `render()` → `run()` |
| `src/Phare/View/View.php` | hold `?Engine $engine`; `render()` delegates, drops debug-string stub |
| `src/Phare/View/Factory.php` | engine seam; `make()` injects engine into `View` |
| `src/Phare/View/ViewServiceProvider.php` | bind `'view'` → Factory wired with a `BladeEngine` resolved from `'blade'` |
| `tests/Mock/config/app.php` | register a canonical view provider so the suite covers wiring |

## Data flow

```
view('home', $data)
  → Factory::make('home', $data)
  → new View(container, engine)          // engine = BladeEngine(Blade)
  → View::render()
  → BladeEngine::render('home', $data)
  → Blade::run('home', $data)            // BladeOne compile + render
  → compiled HTML string
```

## Error handling

- View name not set → `InvalidArgumentException('No view specified.')` (existing).
- No engine injected → `RuntimeException('No view engine bound.')` (replaces the
  debug-string return; a `View` with no engine cannot render).
- Template missing / compile error → BladeOne's exception propagates unchanged.
- `View::__toString()` keeps its catch → returns `''` on render exception (existing).

## Testing (TDD, RED→GREEN each)

1. **Unit — engine delegation (no disk):** inject a fake `Engine` returning a
   predictable string; assert `View::render()` returns it and forwards
   `($view, $data)`. Asserts the seam, not BladeOne.
2. **Unit — no-engine error:** a `View` with no engine throws
   `RuntimeException('No view engine bound.')`.
3. **Integration — real BladeEngine:** write a tmp `.blade.php` fixture + tmp
   compile dir; build a real `Blade`/`BladeEngine`; assert rendered output
   contains the interpolated data. Tear down tmp files in `afterEach`.
4. **Update `tests/Unit/View/ViewTest.php` L93-111:** the debug-string
   assertions (`toContain('test.view')` on the JSON dump) become engine-delegation
   assertions (fake engine).
5. **Wiring — mock config:** after registering the view provider in the mock
   config, a bootstrapped-app test resolves `'view'` and renders a fixture.

All gates per project rules: full `./bin/pest` green, phpstan 0-regression vs
baseline (stash→count→pop on touched files), `vendor/bin/pint` clean.

## Out of scope (separate XL tasks)

- The ~30 missing Blade directives vs Laravel 13 (`@class`, `@checked`, `@csrf`,
  `@vite`, `@props`, `@once`, env/production blocks, etc.).
- The `<x-component>` / `<x-slot>` tag compiler (`ComponentTagCompiler`).
- A full `EngineResolver` with multiple engines (Php/File/Compiler) + Volt
  consolidation. This spec wires a single Blade engine; multi-engine resolution
  is deferred.
- Removing/deprecating the competing `ViewProvider`/`VoltViewProvider`/
  `BladeViewProvider` bindings beyond what the canonical mock config requires.
