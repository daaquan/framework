# Container Phase 4 — Attribute Injection Compatibility

Date: 2026-05-07
Status: Approved (design)
Scope: `phare/framework` — Container layer
Roadmap reference: `docs/laravel13-phalcon-architecture.md` § Phase 4 / Next Implementation Slice

## Goal

Close container parity with Laravel 13 attribute injection. Land in three sub-phases, each green-on-merge.

Reference source:
- `/opt/laravel-framework/src/Illuminate/Container/Container.php`
- `/opt/laravel-framework/src/Illuminate/Container/Attributes/*`
- `/opt/laravel-framework/src/Illuminate/Contracts/Container/ContextualAttribute.php`

## Sub-phases

### 4a — `afterResolvingAttribute` infra + baseline attributes

- Add `protected array $afterResolvingAttributeCallbacks = []` to `Phare\Container\Container`.
- Public method:
  ```php
  public function afterResolvingAttribute(string $attribute, \Closure $callback): void
  ```
- Protected method:
  ```php
  protected function fireAfterResolvingAttributeCallbacks(array $reflectionAttributes, mixed $object): void
  ```
  - Iterates `ReflectionAttribute[]`.
  - Skips attributes not implementing `Phare\Contracts\Container\ContextualAttribute`.
  - For each matching attribute class, runs callbacks registered under that exact class name.
  - Closure receives `(attribute instance, resolved object, container)`.
- Wire fire points (Laravel parity):
  - On parameter resolution: after `resolveFromAttribute()` returns value, fire with `$param->getAttributes()`.
  - On class resolution: after instance built and existing resolving + after-resolving callbacks fired, fire with class reflection's attributes.
  - First-resolve gate: do NOT re-fire on shared singleton re-resolution.
- Baseline attributes:
  - `Phare\Container\Attributes\Give` — manual concrete + params (Laravel parity).
  - `Phare\Container\Attributes\RouteParameter` — read from `routeParams` container binding by name.

### 4b — Auth attribute cluster

- `Phare\Container\Attributes\Auth(?string $guard = null)` → `$container->make('auth')->guard($guard)`.
- `Phare\Container\Attributes\Authenticated(?string $guard = null)` → guard user; throws `Phare\Auth\AuthenticationException` (or framework equivalent verified during implementation) when null.
- `Phare\Container\Attributes\CurrentUser(?string $guard = null)` → guard user (nullable).

### 4c — Infra attribute cluster + edge tests

- `Phare\Container\Attributes\Cache(?string $store = null)` → `$container->make('cache')->store($store)`.
- `Phare\Container\Attributes\Log(?string $channel = null)` → `$container->make('log')->channel($channel)`.
- `Phare\Container\Attributes\Storage(?string $disk = null)` → `$container->make('filesystem')->disk($disk)`.
- `Phare\Container\Attributes\DB(?string $connection = null)` → `$container->make('db')->connection($connection)`.
- Edge tests for contextual binding (Next Implementation Slice item 2):
  - Variadic class dep + `giveTagged`.
  - Primitive `give()` + alias-mapped abstract.
  - Rebinding-after-resolve callback firing exactly once.
  - Already-shared singleton: resolving callbacks fire once total across multiple `make()` calls.

## Out of scope

- `Illuminate\Container\Attributes\Context` — no Phare equivalent of `Illuminate\Support\Facades\Context`.
- `Illuminate\Container\Attributes\Bind` — no Phare FormRequest binder.
- `Illuminate\Container\Attributes\Singleton` / `Scoped` (class-level binding markers) — require autowire-time class scanner not yet present. Phase 5 candidate.

## Architecture

```
make($abstract)
  → build($concrete)
    → resolveDependencies(ReflectionParameter[])
      ↳ for each param:
          getContextualAttributeFromDependency() → ?ReflectionAttribute
          if attribute → resolveFromAttribute() → value
          fireAfterResolvingAttributeCallbacks(param->getAttributes(), value)
    → instance
  → fireResolvingCallbacks($abstract, $instance)
  → fireAfterResolvingCallbacks($abstract, $instance)
  → fireAfterResolvingAttributeCallbacks(class->getAttributes(), $instance)   # first-resolve only
```

## Files

```
src/Phare/Container/Container.php                    (modified, +~80 lines)
src/Phare/Container/Attributes/Give.php              (new, 4a)
src/Phare/Container/Attributes/RouteParameter.php    (new, 4a)
src/Phare/Container/Attributes/Auth.php              (new, 4b)
src/Phare/Container/Attributes/Authenticated.php     (new, 4b)
src/Phare/Container/Attributes/CurrentUser.php       (new, 4b)
src/Phare/Container/Attributes/Cache.php             (new, 4c)
src/Phare/Container/Attributes/Log.php               (new, 4c)
src/Phare/Container/Attributes/Storage.php           (new, 4c)
src/Phare/Container/Attributes/DB.php                (new, 4c)
tests/Container/AfterResolvingAttributeTest.php      (new, 4a)
tests/Container/Attributes/GiveTest.php              (new, 4a)
tests/Container/Attributes/RouteParameterTest.php    (new, 4a)
tests/Container/Attributes/AuthTest.php              (new, 4b)
tests/Container/Attributes/AuthenticatedTest.php     (new, 4b)
tests/Container/Attributes/CurrentUserTest.php       (new, 4b)
tests/Container/Attributes/CacheTest.php             (new, 4c)
tests/Container/Attributes/LogTest.php               (new, 4c)
tests/Container/Attributes/StorageTest.php           (new, 4c)
tests/Container/Attributes/DBTest.php                (new, 4c)
tests/Container/ContextualBindingEdgeTest.php        (new, 4c)
```

## Error handling

- Missing static `resolve()` on contextual attribute → `BindingResolutionException` with message: `Contextual attribute [%s] must define static resolve().` (already implemented; preserved).
- Attribute resolver throws → propagate, no swallow.
- `Authenticated` with no user → `AuthenticationException`.
- Unknown guard/channel/disk/connection → delegate to underlying manager's existing exceptions. No re-wrap.
- `afterResolvingAttribute` callback throws → propagate up `make()` chain. Object considered resolved before callbacks fire (Laravel parity).
- Non-`ContextualAttribute` attribute on param/class → silently skipped (Laravel parity).
- `RouteParameter` missing key → return `null`; missing `routeParams` binding → return `null` (no throw). Strict callers use `Give` instead.
- Variadic param + attr returning iterable → reuse existing `normalizeContextualVariadicResolution` helper. No new path.
- Attr on already-resolved singleton → callbacks fire on first resolve only. Subsequent `make()` returns shared instance, no re-fire.
- Manager not bound (`auth`/`cache`/`log`/`filesystem`/`db`) → standard `BindingResolutionException` from `make()`. Test asserts message clarity.

## Testing strategy

Pest PHP. TDD per sub-phase: failing test → minimal impl → green → refactor. Coverage 80%+ for new code.

### 4a — infra + baseline

`tests/Container/AfterResolvingAttributeTest.php`:
- Registers callback for `Tag::class`, asserts fires with attribute instance + resolved value.
- Non-`ContextualAttribute` attribute → no fire.
- Class-level attr fires once on first resolve; shared re-`make()` does not re-fire.
- Callback throw propagates.
- Missing static `resolve()` → `BindingResolutionException` with exact message.

`tests/Container/Attributes/GiveTest.php`:
- `Give(Concrete::class, ['id' => 1])` builds with params.
- Works with variadic parameter (iterable spread path).

`tests/Container/Attributes/RouteParameterTest.php`:
- Reads from `routeParams` singleton.
- Missing key → null.
- Missing `routeParams` binding → null (no throw).

### 4b — auth cluster

Fake `Phare\Auth\Manager` bound as `auth` in test setUp.

- `AuthTest`: returns guard for default + named.
- `CurrentUserTest`: returns user; null when none.
- `AuthenticatedTest`: returns user; throws `AuthenticationException` when null.

### 4c — infra cluster + edge

- Per-attr test mirroring auth pattern (named target + default).
- `tests/Container/ContextualBindingEdgeTest.php`:
  - Variadic class dep + `giveTagged` (extend existing assertions).
  - Primitive `give()` + alias-mapped abstract: `bind('App\Foo', Foo::class); alias('App\Foo', 'foo'); when(Bar::class)->needs('$region')->give('us-east-1')` resolves through alias chain.
  - Rebinding-after-resolve fires `rebinding` callback exactly once.
  - Already-shared singleton: `make()` twice → resolving callbacks fire once total.

### Regression gate

`tests/Container/ContainerCompatibilityTest.php` must remain green across all three sub-phases.

## Delivery order

1. Sub-phase 4a — infra + `Give` + `RouteParameter` + tests. Ships independently.
2. Sub-phase 4b — auth cluster + tests. Ships independently after 4a.
3. Sub-phase 4c — infra cluster + edge tests. Ships independently after 4b.

Any sub-phase may halt without blocking earlier sub-phases. Stop early if priorities shift.
