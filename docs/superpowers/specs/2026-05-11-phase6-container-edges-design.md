# Phase 6 Design Spec: Laravel 13 Container Edge Behaviors

**Status:** Draft (2026-05-11)

**Scope:** Identify and close the remaining gap between `Phare\Container\Container` and `Illuminate\Container\Container` (Laravel 13). Phase 4 covered binding semantics, contextual binding, resolving callbacks, tagging, and `afterResolvingAttribute`. Phase 6 covers the lifecycle and ergonomics methods that real-world Laravel apps depend on but that Phare does not yet implement.

This spec is the brainstorming companion to a future implementation plan. It enumerates each gap with: what Laravel does, what Phare does today, whether parity is required, and the proposed surface area.

---

## Method-by-method gap analysis

Source surveyed: `/opt/laravel-framework/src/Illuminate/Container/Container.php` (line ranges from grep output, lines as of Laravel 13). Current Phare surface: `/opt/framework/src/Phare/Container/Container.php` (1011 LOC).

### Group A — Lifecycle (high priority)

#### A1. `instance($abstract, $instance)`
- **Laravel:** Registers an already-built instance in the container, marks it as resolved, fires rebinding callbacks. Lines 601–648.
- **Phare:** Missing. Closest equivalent is `singleton($abstract, fn () => $instance)`, which defers resolution.
- **Required for parity:** Yes — used everywhere in Laravel app code (e.g. `app()->instance('foo', $obj)`), service providers, and tests.
- **Surface:** `public function instance(string $abstract, mixed $instance): mixed` — return the instance; emit rebinding callbacks if `bound()` was true.
- **Open question:** Should it bypass our wrap-closure logic in `bind()`? Yes — instances are stored directly in the shared-instance map.

#### A2. `extend($abstract, Closure $closure)`
- **Laravel:** Decorates a resolved instance via `$closure($instance, $container)`. Stack of extenders applied in registration order. Lines 575–600.
- **Phare:** Missing. Today decoration requires manual `singleton` overwrite + re-resolution.
- **Required:** Yes — service providers compose decorators with `extend()`.
- **Surface:** `public function extend(string $abstract, Closure $closure): void`. Apply during `resolve()` after build, before firing resolving callbacks.
- **Edge:** If the binding is already resolved (shared) when `extend` is called, the existing shared instance must be replaced with the decorated value and rebinding callbacks fired.

#### A3. `scoped($abstract, $concrete = null)` + `scopedIf` + `forgetScopedInstances`
- **Laravel:** Per-request scoped singletons. Reset between HTTP requests, queue jobs, console commands. Lines 527–574 + 1719.
- **Phare:** Missing. Today everything is either transient or process-singleton.
- **Required:** Yes — Laravel 13 ships request-scoped DB connections, request-scoped auth, request-scoped logging context.
- **Surface:** `scoped`, `scopedIf`, internal `scopedInstances[]` map, `forgetScopedInstances()` invoked at request boundary in `Foundation\Http\Kernel::terminate()`.
- **Open question:** Where in Phalcon's lifecycle does "end of request" land? Likely the `application:afterHandleRequest` event. Documented in plan, not here.

#### A4. `forgetInstance($abstract)` + `forgetInstances()` + `forgetExtenders($abstract)`
- **Laravel:** Manual cleanup hooks for tests and long-running processes. Lines 1677–1738.
- **Phare:** Missing.
- **Required:** Yes — needed for Phase 6 test infrastructure once `extend`/`scoped` land.
- **Surface:** Mirror Laravel exactly.

#### A5. `flush()`
- **Laravel:** Resets every internal map. Used between test cases. Line 1761.
- **Phare:** Missing.
- **Required:** Yes — Pest test bootstrap will use it.
- **Surface:** `public function flush(): void`. Document everything it clears.

---

### Group B — Method binding & call autowiring (high priority)

#### B1. `bindMethod($method, $callback)` / `hasMethodBinding($method)` / `callMethodBinding($method, $instance)`
- **Laravel:** Allows binding a closure for `'Class@method'` calls so jobs, listeners, queueable handlers get a custom dispatch path. Lines 422–473.
- **Phare:** Missing.
- **Required:** Yes — queue/listener dispatch hits this path.
- **Surface:** Match Laravel exactly. Storage: `protected array $methodBindings = []`.

#### B2. `call($callback, array $parameters = [], $defaultMethod = null)`
- **Laravel:** Autowired invocation of `'Class@method'`, `[$instance, 'method']`, or `Closure`. Lines 785–831. Internally uses `Util::unwrapIfClosure` + `BoundMethod::call`.
- **Phare:** Missing.
- **Required:** Yes — controller dispatch, listener dispatch, scheduled commands all use it.
- **Surface:** Same signature. Implementation can mirror `BoundMethod::call` in a new file `src/Phare/Container/BoundMethod.php`.

#### B3. `wrap(Closure $callback, array $parameters = [])`
- **Laravel:** Returns a closure that, when invoked, calls `call($callback, $parameters)`. Used in middleware pipelines. Line 770.
- **Phare:** Missing.
- **Required:** Yes — opens cleaner pipeline composition.
- **Surface:** Trivial wrapper around `call()`.

#### B4. `factory($abstract)`
- **Laravel:** Returns a closure that resolves `$abstract` each invocation. Line 832.
- **Phare:** Missing.
- **Required:** Medium — convenience method.
- **Surface:** `public function factory(string $abstract): Closure`.

---

### Group C — Resolution lifecycle hooks

#### C1. `beforeResolving($abstract, ?Closure $callback = null)`
- **Laravel:** Fired before resolution begins, complementing the existing `resolving`/`afterResolving`. Line 1439.
- **Phare:** Missing.
- **Required:** Yes — Laravel service providers rely on it for instrumentation.
- **Surface:** Same signature as `resolving`. Internal map `beforeResolvingCallbacks`.

#### C2. `currentlyResolving()`
- **Laravel:** Returns the current build stack. Line 1632.
- **Phare:** Missing.
- **Required:** Low — diagnostic helper.
- **Surface:** `public function currentlyResolving(): array`.

#### C3. `refresh($abstract, $target, $method)`
- **Laravel:** Sugar for setting up rebinding-driven re-injection. Line 728.
- **Phare:** Missing.
- **Required:** Medium.
- **Surface:** Mirror Laravel.

---

### Group D — Attribute-driven contextual binding (medium priority)

#### D1. `whenHasAttribute(string $attribute, Closure $handler)`
- **Laravel:** Registers an attribute-level contextual handler so `Container::resolveFromAttribute()` can hand off to user code. Line 226.
- **Phare:** Missing. Today contextual attributes (`Cache`, `Storage`, `Auth`, etc.) hard-code their resolvers via `ContextualAttribute::resolve()`. There is no extension point.
- **Required:** Medium — required for downstream apps that want to register their own attribute resolvers without subclassing every attribute.
- **Surface:** `public function whenHasAttribute(string $attribute, Closure $handler): void`. Storage: `protected array $attributeHandlers = []`. Wire into `resolveFromAttribute()` ahead of the default `$attribute->resolve($container)` path.

---

### Group E — PSR-11 + ArrayAccess completeness

#### E1. `get(string $id): mixed` (PSR-11)
- **Laravel:** Implements `Psr\Container\ContainerInterface`. Line 878.
- **Phare:** Implements `make` but does not formally satisfy PSR-11.
- **Required:** Yes — required for interop with PSR-11 consumers (e.g. external middleware libs).
- **Surface:** Implement `Psr\Container\ContainerInterface`; `get()` delegates to `make()`, `has()` delegates to `bound() || isShared() || isset(instances)`.

#### E2. `offsetSet($offset, $value): void` / `offsetUnset($offset): void` / `__set($key, $value)`
- **Laravel:** Full ArrayAccess + magic property support for `$container[$key] = $value;` and `$container->foo = $bar;`. Lines 1798–1862.
- **Phare:** Provides `offsetGet` (used heavily) but `offsetSet`/`offsetUnset`/`__set` are absent or partial.
- **Required:** Medium — Laravel-style sugar.
- **Surface:** Match Laravel exactly. `offsetSet` calls `bind`; `offsetUnset` removes from `bindings/instances`.

#### E3. `__set($key, $value)`
- Covered above.

---

### Group F — Environment helpers (low priority)

#### F1. `resolveEnvironmentUsing(?callable $callback)` / `currentEnvironmentIs($environments)`
- **Laravel:** Plug-in environment resolver used by `App::environment()`. Lines 1738, 1749.
- **Phare:** Foundation Application already exposes `environment()`. Container methods are convenience.
- **Required:** Low — wire only if a Phase 6 use case appears.
- **Recommendation:** Defer until a concrete need emerges.

---

## Out of scope for Phase 6

- Async / fiber resolution — Laravel does not have it; not required.
- DI graph visualization tools — separate plugin territory.
- Auto-discovery of bindings via config — already handled by service providers.
- Anything in the `Concerns/` trait directory of Laravel's container — Phare keeps everything in one class today; revisit only if Container.php exceeds ~1500 LOC.

---

## Implementation order recommendation

Each item lands as its own commit, TDD per behavior. Recommended ordering:

1. **Group A first** — `instance`, `extend`, `forgetInstance(s)`, `forgetExtenders`, `flush`, then `scoped`/`scopedIf`/`forgetScopedInstances`. These are the highest-impact lifecycle gaps and unblock the rest.
2. **Group B** — `bindMethod` / `callMethodBinding` / `call` / `wrap` / `factory`. Add `BoundMethod` helper class to keep `Container.php` from growing further.
3. **Group C** — `beforeResolving`, `currentlyResolving`, `refresh`.
4. **Group D** — `whenHasAttribute` (depends on Group C wiring).
5. **Group E** — PSR-11 + `offsetSet`/`offsetUnset` + `__set`.
6. **Group F** — defer.

Estimated tasks: ~30, one commit per behavior. Estimated effort: 2–3 working days of focused execution.

---

## Risks

1. **Scoped instances + Phalcon lifecycle.** Phalcon's `Application` does not have a first-class "end of request" event in `Foundation\Http\Kernel`. We may need to invoke `forgetScopedInstances()` manually from `Kernel::terminate()`. Verify before implementing Group A's scoped slice.
2. **PSR-11 interface conflict.** Phare may already declare a partial `ContainerInterface` somewhere (`Phare\Contracts\Container\Container`). Adding `Psr\Container\ContainerInterface` should be additive; double-check no method signatures collide on `get()`.
3. **`call()` and method binding** — Laravel's `BoundMethod` resolves dependency-injection-with-overrides, including variadic and union-typed parameters. Re-using Phare's existing autowiring (in `Container::resolve()`'s build stack) is preferred over a forked implementation.
4. **Backward compat.** Some Phare-only consumers may rely on the lack of `instance()` / `extend()` and use `singleton` overwrites. Audit `/opt/phare` for `singleton($key, fn () => $obj)` patterns that could migrate to `instance($key, $obj)`. Migration is opt-in; no breaking changes.

---

## Acceptance criteria (for the eventual implementation plan)

- Every Group A–E method has a Pest test covering: happy path, edge case (e.g. re-extending an already-resolved instance, scoped reset across simulated requests, PSR-11 `NotFoundExceptionInterface` on missing id), and rebinding behavior where applicable.
- `Container.php` LOC stays under 1500 — extract helpers (e.g. `BoundMethod`) when it grows.
- All 9 existing manager bindings, all contextual attributes, and the full app-side `/opt/phare` Pest suite stay green throughout the rollout.
- Roadmap doc updated per group as it lands.

---

## Next step

Author the implementation plan at `docs/superpowers/plans/YYYY-MM-DD-phase6-container-edges.md` using this spec. Break Group A into ~10 tasks, Group B into ~6, Group C/D/E into the rest. Run `superpowers:writing-plans` to scaffold.
