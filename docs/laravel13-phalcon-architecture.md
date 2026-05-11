# Laravel 13 Compatibility Architecture for Phare (Phalcon)

## Goal

Build a Phalcon-based framework that preserves Laravel 13 developer ergonomics and contracts, while keeping Phalcon runtime primitives (DI, Micro/Application, Router, Dispatcher).

Reference source:
- `/opt/laravel-framework/src/Illuminate/Foundation/Application.php`
- `/opt/laravel-framework/src/Illuminate/Foundation/Bootstrap/LoadConfiguration.php`
- `/opt/laravel-framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php`
- `/opt/laravel-framework/src/Illuminate/Foundation/Http/Kernel.php`

## Design Principles

1. Contract-first compatibility
- Reproduce Laravel lifecycle semantics at framework boundaries (app bootstrapping, kernel pipeline, config loading, container resolution).

2. Adapter, not rewrite
- Keep Phalcon-native components as execution engine, and introduce thin compatibility layers around them.

3. Test-first + safe refactor
- Add behavioral tests for each compatibility boundary before changing internals.

4. Backward compatibility for existing Phare code
- Keep legacy extension points working during migration (e.g. `register()` bootstrappers).

## Layered Architecture

1. Foundation layer
- `AbstractApplication` lifecycle (`booting`, `bootstrapWith`, `booted`, termination).
- Bootstrapper callback registry (`beforeBootstrapping` / `afterBootstrapping`).

2. Container layer
- Laravel-like binding semantics (`bind`, `singleton`, contextual resolution) on top of current DI integration.

3. Configuration layer
- Deterministic config loading and cache generation.
- Environment-aware cache invalidation in local/testing.

4. HTTP / Routing layer
- Kernel pipeline split into:
  - Route source resolver (cached vs generated)
  - Route matcher (static + parameterized)
  - Controller parameter resolver (scalars, request, validators)
  - Middleware applicator (global, group, route)

5. Console / Commands layer
- Command registration and cache-clear behaviors aligned with Laravel command contracts.

## Phased Delivery Plan (TDD)

### Phase 1 (done): Bootstrap lifecycle compatibility

Implemented:
- `bootstrapWith()` now supports Laravel-style `bootstrap(Application)` and keeps legacy `register(Application)` fallback.
- `bootstrap()` is preferred when both exist.
- Per-bootstrapper lifecycle hooks added:
  - `beforeBootstrapping(string, Closure)`
  - `afterBootstrapping(string, Closure)`
- App marked as bootstrapped before bootstrapper execution (Laravel-compatible timing).

Tests:
- `tests/Foundation/BootstrapLifecycleTest.php`

### Phase 2 (done): Configuration lifecycle hardening

Implemented:
- `LoadConfiguration` now supports Laravel-style `bootstrap()` entry (with `register()` compatibility).
- Stable stale-check logic: cache freshness no longer depends on cache file mtime.
- Safe cache directory creation before writing compiled config.
- `AbstractApplication` now uses `make('config')` in config lifecycle methods to prevent singleton overwrite bugs.
- Config metadata key (`@timestamp`) is no longer merged into runtime config repository.

Tests:
- `tests/Foundation/LoadConfigurationTest.php`
  - generates + loads cache on miss
  - does not regenerate when unchanged
  - regenerates when source config changes

### Phase 3 (in progress): HTTP Kernel decomposition

Implemented:
- Extracted parameterized route pattern matching into dedicated component:
  - `src/Phare/Routing/RoutePatternMatcher.php`
- `Foundation\Http\Kernel` now delegates route-pattern matching to this component.
- Extracted route data source resolution (cache vs fallback generation):
  - `src/Phare/Routing/RouteDataSourceResolver.php`
- Extracted controller action parameter resolution:
  - `src/Phare/Routing/ControllerActionParameterResolver.php`
  - Handles scalar casting, container resolution, validator invocation, and request rebinding callback.
- Extracted dispatcher forward payload builder:
  - `src/Phare/Routing/DispatchForwardPayloadBuilder.php`
  - Isolates forward payload generation for typed and untyped route parameters.
- Extracted route middleware alias resolution:
  - `src/Phare/Routing/RouteMiddlewareResolver.php`
  - Centralizes alias validation and unknown-alias failure behavior.
- Extracted middleware application strategy:
  - `src/Phare/Routing/MiddlewareApplicator.php`
  - Kernel now applies global/group/route middlewares with one consistent ordered strategy.
- Extracted web router hydration strategy:
  - `src/Phare/Routing/WebRouterHydrator.php`
  - Isolates route registration for web app mode.
- Extracted application mode resolver:
  - `src/Phare/Routing/ApplicationModeResolver.php`
  - Centralizes app mode decision (`web` / `micro`) instead of class-branching in kernel.
- Extracted route mount dispatcher:
  - `src/Phare/Routing/RouteMountDispatcher.php`
  - Isolates web/micro dispatch branching and unknown-mode error behavior.
- Extracted route selection resolver:
  - `src/Phare/Routing/RouteSelectionResolver.php`
  - Isolates static-vs-parameterized route selection and not-found exception behavior.
- Extracted web dispatch forward listener registrar:
  - `src/Phare/Routing/WebDispatchForwardRegistrar.php`
  - Isolates `dispatch:beforeExecuteRoute` listener attachment and forward dispatch trigger.
- Extracted route params binder:
  - `src/Phare/Routing/RouteParamsBinder.php`
  - Isolates matched route-params binding into container singleton (`routeParams`).
- Extracted route registration orchestrator:
  - `src/Phare/Routing/RouteRegistrationOrchestrator.php`
  - Centralizes `registerRoutes()` flow as wiring-oriented orchestration.
- Extracted route handlers (2026-05-11):
  - `src/Phare/Routing/WebRouteHandler.php` — owns Phalcon Router registration, controller singleton binding, web middleware application, and forward-listener wiring for typed/url-param routes.
  - `src/Phare/Routing/MicroRouteHandler.php` — owns Phalcon Micro `Collection` mounting and api middleware application.
- `Kernel::registerRoutes()` reduced to wiring-only: `handleWebRoutes`, `handleMicroRoutes`, and `matchParameterizedRoute` deleted from `Foundation\Http\Kernel`. Test seams `webRouteHandler` / `microRouteHandler` exposed for swap-in.

Tests:
- `tests/Unit/Routing/RoutePatternMatcherTest.php`
  - named params extraction
  - inline regex constraints
  - no-match behavior for invalid constraints
  - method-specific matching
- `tests/Unit/Routing/RouteDataSourceResolverTest.php`
  - cached file path resolution
  - fallback loader path
  - generated file fallback
  - failure path when neither source is available
- `tests/Unit/Routing/ControllerActionParameterResolverTest.php`
  - scalar + untyped URL param resolution
  - object resolution via factory callback
  - validator failure behavior
  - request instance callback invocation
- `tests/Unit/Routing/DispatchForwardPayloadBuilderTest.php`
  - untyped route forwarding path
  - typed route forwarding path via resolver callback
- `tests/Unit/Routing/RouteMiddlewareResolverTest.php`
  - middleware alias mapping
  - unknown alias exception behavior
- `tests/Unit/Routing/MiddlewareApplicatorTest.php`
  - ordered application guarantee
  - start/apply/end lifecycle callbacks
- `tests/Unit/Routing/WebRouterHydratorTest.php`
  - router hydration behavior
  - invalid route-group safety
- `tests/Unit/Routing/ApplicationModeResolverTest.php`
  - web mode resolution
  - micro mode resolution
- `tests/Unit/Routing/RouteMountDispatcherTest.php`
  - web dispatch path
  - micro dispatch path
  - unknown mode failure path
- `tests/Unit/Routing/RouteSelectionResolverTest.php`
  - static route selection
  - parameterized route selection fallback
  - not-found path
- `tests/Unit/Routing/WebDispatchForwardRegistrarTest.php`
  - listener registration + forward path
  - already-forwarded no-op path
- `tests/Unit/Routing/RouteParamsBinderTest.php`
  - binds when params exist
  - no-op when params are empty
- `tests/Unit/Foundation/Http/KernelOrchestrationTest.php`
  - web mode orchestration flow (selection, params bind, middleware order, listener registration)
- `tests/Unit/Routing/RouteRegistrationOrchestratorTest.php`
  - web orchestration flow
  - micro orchestration flow

### Phase 4 (done): Container compatibility gaps

Implemented:
- Added resolving callbacks:
  - global resolving callbacks
  - abstract-specific resolving callbacks
- Added after-resolving callbacks with immediate execution for already-resolved abstractions.
- Added rebinding callbacks triggered when an existing binding is replaced.
- Added alias-chain resolution helper:
  - `getAlias(string $abstract): string`
- Improved rebinding safety by clearing previous shared resolution before rebinding.
- Added contextual binding support:
  - `when(...)->needs(...)->give(...)`
  - supports class-string and closure implementations.
  - supports primitive needs (e.g. `$region`) and variadic class dependencies.
  - supports alias-mapped abstract keys in `needs(...)`.
- Added tagging support:
  - `tag(...)`
  - `tagged(...)`
- Added contextual `giveTagged(...)` support for contextual binding.
- Added contextual `giveConfig(...)` support for primitive dependency injection.

Tests:
- `tests/Container/ContainerCompatibilityTest.php`
  - global/abstract resolving callbacks
  - immediate after-resolving behavior
  - rebinding callback behavior
  - chained alias resolution
- contextual binding (class)
- contextual binding (closure)
- contextual binding (primitive)
- contextual binding (variadic)
- contextual binding via alias
- tag / tagged resolution
- contextual giveTagged for variadic dependencies
- contextual giveConfig + default fallback
- route registration orchestration extraction

Phase 4 (continued — 2026-05-09):
- Added `afterResolvingAttribute(string, Closure)` callback registration.
- Added `fireAfterResolvingAttributeCallbacks()` helper, fires only for `ContextualAttribute` implementors.
- Wired fire into parameter resolution (post `resolveFromAttribute`) and class-level resolution (post-build, first-resolve only for shared singletons).
- Added contextual attributes: `Give`, `RouteParameter`, `Auth`, `CurrentUser`, `Authenticated`, `Cache`, `Log`, `Storage`, `DB`.
- Added `Phare\Auth\AuthenticationException`.
- Added contextual binding edge tests: variadic + giveTagged, primitive give + alias-mapped abstract, rebinding-after-resolve, singleton resolving fires once.
- Container fixes uncovered while completing Phase 4:
  - `make($abstract, $parameters)` honors caller-supplied named parameter overrides during autowiring.
  - `rebinding()` no longer fires the registered callback on registration (eager `make()` only).
  - Resolved-branch in `make()` skips resolving callbacks for shared singletons so they fire once total.
  - Resolving / after-resolving fire helpers no longer double-invoke when `$abstract === get_class($instance)`.
  - `getContextualConcrete()` now walks abstract → concrete and alias chains so `when('alias')->needs('$primitive')` resolves when the build stack holds the underlying concrete class.
  - `bind()` wraps closure concretes so the container is auto-passed to `function ($app)` providers, while preserving `function (array $parameters)` semantics.
  - `bind()` wraps class-string concretes into a closure routed through `resolve()` for autowiring; `resolve()` gains `$skipAliasReserved` to break the reserved-alias recursion that the wrap could otherwise trigger.
  - Constructor autowiring resolves untyped optional/nullable parameters to their declared default or `null` instead of throwing.

Tests:
- `tests/Container/AfterResolvingAttributeTest.php`
- `tests/Container/Attributes/{Give,RouteParameter,Auth,CurrentUser,Authenticated,Cache,Log,Storage,DB}Test.php`
- `tests/Container/ContextualBindingEdgeTest.php`

### Phase 5 (in progress): Multi-driver attribute selectors + manager bindings

Each manager now exposes a Laravel-parity `driver/connection/store/disk/mailer/guard` selector, the corresponding container binding is registered (`<service>.manager`), and the contextual attribute accepts an optional name argument:

- `feat(cache)` (`a809da1`) — multi-store `CacheManager` + `#[Cache(?store)]`.
- `feat(filesystem)` (`11596a3`) — multi-disk `FilesystemManager` + `#[Storage(?disk)]`.
- `feat(auth)` (`b568b9a`) — multi-guard `AuthManager` + `#[Auth/CurrentUser/Authenticated(?guard)]`.
- `feat(database)` (`2cdb9b4`) — bind `db.manager` + `#[DB(?connection)]` selector.
- `feat(queue)` (`242b3ff`) — bind `queue.manager` + `#[Queue(?connection)]` selector.
- `feat(hashing)` (`1d27cd3`) — bind `hash.manager` + `#[Hash(?driver)]` selector.
- `feat(broadcasting)` (`ab14942`) — bind `broadcast.manager` + `#[Broadcast(?driver)]` selector.
- `feat(session)` (`a4844c1`) — bind `session.manager` + `#[Session(?store)]` selector.
- `feat(mail)` (`04b04fe`) — bind `mail.manager` + `#[Mail(?mailer)]` selector.

Tests (added alongside each commit):
- `tests/Container/Attributes/{Cache,Storage,Auth,CurrentUser,Authenticated,DB,Queue,Hash,Broadcast,Session,Mail}Test.php` cover both default-driver and named-selector resolution paths.

Remaining Phase 5 candidates:
- `Log` selector (`#[Log(?channel)]`) aligned with Laravel channel-stack semantics — `channel()` alias, aggregate `stack()` driver, and `driver: stack` config routing landed 2026-05-11 (`tests/Unit/Log/LogManagerStackTest.php`).
- `Phare\Support\Manager` abstract base landed 2026-05-11 (`tests/Unit/Support/ManagerTest.php`) — Laravel-parity `driver()`, `extend()`, `createXxxDriver()` convention, custom-creator-overrides, driver cache, default-driver enforcement.
- Existing nine managers still own their constructors and selector method names (`store/disk/guard/connection/mailer/channel`). Migrating them onto `Phare\Support\Manager` is breaking for individual managers (e.g. `HashManager::extend(string, HasherInterface)` vs base `extend(string, Closure)`). Tracked as a follow-up plan; base class is ready when needed.
- `CacheManager` migration onto `Phare\Support\Manager` landed 2026-05-12 — `store()` remains the public selector, `driver()` is now the Laravel-parity alias, and `extend()` / `forgetDrivers()` are inherited from the base manager while preserving eager default-store validation.
- `FilesystemManager` migration onto `Phare\Support\Manager` landed 2026-05-12 — `disk()` remains the public selector, `driver()` is now the Laravel-parity alias, and `extend()` / `forgetDrivers()` are inherited from the base manager.

## Current Risks

1. Manager bindings (`auth`, `log`, `db`, `cache`, `filesystem`, `queue`, `hash`, `broadcast`, `session`, `mail`) are looked up via container service keys — consumers without a registered binding receive whatever the test seam supplies.

### Phase 6 (in progress): Container edge behaviors

Spec: `docs/superpowers/specs/2026-05-11-phase6-container-edges-design.md`.
Plan: `docs/superpowers/plans/2026-05-11-phase6-container-edges.md`.

Landed 2026-05-11:
- Group A (lifecycle): `instance()`, `extend()` + `applyExtenders()`, `forgetInstance()`, `forgetInstances()`, `forgetExtenders()`, `flush()`, `scoped()`, `scopedIf()`, `forgetScopedInstances()`. Tests under `tests/Container/Lifecycle/`.
- Group B (method binding & call autowiring): `Phare\Container\BoundMethod`, `Container::call()`, `wrap()`, `factory()`, `bindMethod()`, `hasMethodBinding()`, `callMethodBinding()`. Tests under `tests/Container/MethodBinding/`.
- Group C (resolution hooks): `beforeResolving()` (global + abstract-specific), `currentlyResolving()`, `refresh()`. Tests under `tests/Container/Hooks/`.
- Group D (attribute extension): `whenHasAttribute()` registers per-attribute resolvers, picked up by `resolveFromAttribute()` and by parameter discovery when the attribute is not a `ContextualAttribute` contract implementor. Tests at `tests/Container/Attributes/WhenHasAttributeTest.php`.
- Group E (PSR-11): `Container implements Psr\Container\ContainerInterface`. `get()`/`has()` are inherited from Phalcon\Di\Di (overriding them caused infinite recursion via Phalcon's internal `getShared() → get()` path). Added `psrGet()` opt-in helper that throws `Phare\Container\Exceptions\ServiceNotFoundException implements NotFoundExceptionInterface` for unknown ids. Tests at `tests/Container/PSR11AndArrayAccessTest.php`.

Group F (environment helpers `resolveEnvironmentUsing`, `currentEnvironmentIs`) deferred — wire only when a concrete use case emerges.

Suite: 983 tests passing after Phase 6 lands (baseline 946 before Phase 5).

## Next Implementation Slice

Next implementation slice:
1. ~~finalize `Kernel::registerRoutes()` as wiring-only orchestration helper~~ — landed 2026-05-11 via `WebRouteHandler` / `MicroRouteHandler` extraction.
2. ~~align container semantics with additional Laravel 13 edge behaviors in `Illuminate\Container\Container`~~ — Phase 6 Groups A–E landed 2026-05-11; Group F (env helpers) intentionally deferred.
3. Manager migrations onto `Phare\Support\Manager` base (Phase 5 follow-up). `CacheManager` and `FilesystemManager` have landed; continue one-by-one for auth, database, queue, hash, broadcasting, session, and mail with backward-compat shims.
