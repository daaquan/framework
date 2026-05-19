# Audit — Area A: Core Web Stack

**Laravel 13 reference:** `/opt/laravel-framework` @ `13.2.0` (read-only)
**Phare package:** `phare/framework`, namespace `Phare\`, `src/Phare/`
**Method:** per-subsystem 1:1 public-API diff vs Laravel 13 + Wrapper Rule (§2) grep.
**Scope:** read-and-record only — no framework source edited.

---

### Routing

- 現状 (Current):
  `Phare\Routing\Router` (implements `Phare\Contracts\Routing\Router`) is a thin
  in-memory route collector. Public surface: `group($options,$callback)`,
  `get/post/put/delete/patch/options($path,$handler,$middleware=[])`,
  `addRoute($method,$path,$handler,$middleware=[])`, `addParams($params)`,
  `name($name)`, `resource($path,$controller,$middleware=[])`, `getRoutes()`,
  `isMatched($uri,$method)`. Handlers are `"Controller@action"` strings only.
  Route registration/dispatch is delegated to `RouteLoader` (`abstract`, with
  `FileRouteLoader` / `ControllerRouteLoader` subclasses) which builds a real
  `Phalcon\Mvc\Router` and binds it as the `router` service; public surface there
  is `create(Application): RouteLoader` and `isCacheUpToDate(): bool`.
  Attribute routing: `#[Route(pattern, methods, middlewares, name)]` (method-level)
  and `#[RouteAttribute(middlewares)]` (class-level) — `getPattern/getName/getMethods/getMiddlewares/hasParams/fetchParams` / `getMiddlewares/getParameters`.
- 期待 (Expected):
  Laravel `Illuminate\Routing\Router` exposes a much wider surface — verb methods
  `get/post/put/patch/delete/options` plus `any/match/fallback/redirect/permanentRedirect/view`;
  `group`, `resource/resources/apiResource/apiResources`,
  `singleton/singletons/apiSingleton/apiSingletons`, `softDeletableResources`;
  named-route helpers (`currentRouteName/currentRouteNamed/currentRouteAction/currentRouteUses`);
  route-model binding (`model/bind/substituteBindings/substituteImplicitBindings/substituteImplicitBindingsUsing`);
  pattern constraints (`pattern/patterns`); middleware group registry
  (`aliasMiddleware/middlewareGroup/pushMiddlewareToGroup/prependMiddlewareToGroup/...`);
  dispatch (`dispatch/dispatchToRoute`). Fluent registration via
  `RouteRegistrar` (`name/middleware/prefix/domain/where/controller/...` chaining,
  `__call`). `Route` objects carry `name()/middleware()/where()/defaults()/...`.
- 差分 (Gaps):
  - **Missing — verb/registration helpers:** `any`, `match`, `fallback`,
    `redirect`, `permanentRedirect`, `view`.
  - **Missing — resource API:** `resources`, `apiResource`, `apiResources`,
    `singleton`/`singletons`/`apiSingleton`/`apiSingletons`,
    `softDeletableResources`; no `PendingResourceRegistration` (cannot
    `only()/except()/names()/parameters()/scoped()` a resource). Phare's
    `resource()` hard-codes the 7 RESTful routes and the `{id}` parameter name.
  - **Missing — named routes:** no fluent `RouteRegistrar`; `name()` mutates the
    last-added route only. No `currentRoute*` accessors.
  - **Missing — route model binding:** `model()`, `bind()`,
    `substituteBindings()`, implicit binding, `ImplicitRouteBinding`,
    enum/`scoped` binding — entirely absent. (`RouteParamsBinder` is unrelated.)
  - **Missing — pattern constraints:** `pattern()`/`patterns()` global `where`
    constraints; only the `#[Route]` attribute supports inline `<regex>`.
  - **Missing — middleware groups & aliases at router level:** `aliasMiddleware`,
    `middlewareGroup`, `pushMiddlewareToGroup`, etc. (handled, if at all,
    outside the router — see `### Middleware`).
  - **Missing — dispatch/current-request API:** `dispatch`, `dispatchToRoute`,
    `getCurrentRequest`, `getCurrentRoute`, `matched`, `prepareResponse`.
  - **Type mismatch:** Phare verb methods are untyped (no param or return types);
    Laravel returns a `Route`. Phare `get()` etc. return `$this` (the collector),
    not a route object — so `Router::get(...)->name(...)` chains onto the wrong
    receiver and `name()` returns `void`. `group($options,...)` takes a plain
    array, not Laravel's prefix/middleware/namespace/as/domain group attributes
    with nesting + `mergeWithLastGroup`. Handler is restricted to a
    `"Class@method"` string — no closure, `[Class,'method']`, invokable-class,
    or `uses` array action.
  - **Phalcon leak:** none on a *public* signature. `Phare\Routing\Router` methods
    are fully untyped. `RouteLoader` imports `Phalcon\Mvc\Router` and constructs
    it inside a `protected` constructor / DI closure; `extractRoutes($router)` is
    `protected` and untyped. Public `RouteLoader` API (`create`, `isCacheUpToDate`)
    exposes no `Phalcon\*` type. **No Wrapper Rule violation on the routing
    public surface**, but the real router bound as the `router` service *is* a
    raw `Phalcon\Mvc\Router` — any consumer resolving `router` from the container
    receives a Phalcon object (container-resolution leak, track under `### HTTP Kernel` / container audit).
- 工数感 (Effort): **L** — Phare routing covers only basic verb + a hard-coded
  `resource()`; named routes, route-model binding, pattern constraints, the
  fluent `RouteRegistrar`, resource customization, and closure/array actions are
  all absent. Reaching Laravel-13 parity is a near-rewrite of the routing layer,
  not an incremental patch.

---

### HTTP Kernel

*Re-verification of a previously `[x]` subsystem against the Wrapper Rule (§2).*

- 現状 (Current):
  - `Phare\Foundation\Http\Kernel` (abstract) implements `Phare\Contracts\Http\Kernel`.
    Public surface: `__construct(Phare\Contracts\Foundation\Application $app)`,
    `abstract handle(Phalcon\Http\RequestInterface $request): Phalcon\Http\ResponseInterface`,
    `bootstrap()` (untyped return), `terminate(Phalcon\Http\RequestInterface $request,
    Phalcon\Http\ResponseInterface $response): void`, `getApplication(): Application`.
  - `Phare\Contracts\Http\Kernel` declares the same four methods — `handle` and
    `terminate` carry `Phalcon\Http\RequestInterface` / `Phalcon\Http\ResponseInterface`
    in their public signatures.
  - Middleware state (`$middlewares`, `$middlewareGroups`, `$routeMiddleware`,
    `$bootstrappers`, `$pipelineMiddlewareStack`) is held in `protected` fields with
    `protected` sync/apply/register helpers — no public accessors or mutators.
  - Constructor eagerly runs `bootstrap()` → `registerRoutes()` → `syncMiddleware()`.
  - Optional Laravel-style `Phare\Pipeline\Pipeline` path gated by config flag
    `app.http.use_pipeline_middleware`; default path delegates to Phalcon-native
    middleware via `$app->middleware()`.

- 期待 (Expected):
  - Laravel `Foundation\Http\Kernel` — public surface: `__construct(Application $app,
    Router $router)`, `handle($request)` (untyped param, Symfony Response return),
    `bootstrap()`, `terminate($request, $response)`, `getApplication()`,
    `setApplication(Application $app)`, `requestStartedAt()`,
    `whenRequestLifecycleIsLongerThan($threshold, $handler)`, plus the middleware
    accessor API: `hasMiddleware`, `prependMiddleware`, `pushMiddleware`,
    `prependMiddlewareToGroup`, `appendMiddlewareToGroup`, `prependToMiddlewarePriority`,
    `appendToMiddlewarePriority`, `addToMiddlewarePriorityBefore`,
    `addToMiddlewarePriorityAfter`, `getMiddlewarePriority`, `setMiddlewarePriority`,
    `getGlobalMiddleware`, `setGlobalMiddleware`, `getMiddlewareGroups`,
    `setMiddlewareGroups`, `getRouteMiddleware`, `getMiddlewareAliases`,
    `setMiddlewareAliases`.
  - `Contracts\Http\Kernel` — four methods (`bootstrap`, `handle`, `terminate`,
    `getApplication`), all parameters untyped; HTTP types only in docblocks
    (`Symfony\Component\HttpFoundation\Request/Response`).

- 差分 (Gaps):
  - **Missing:** the entire middleware accessor/mutator API (18 methods listed
    above) — Phare keeps middleware in `protected` fields, so no runtime
    inspection or programmatic registration is possible. `setApplication`,
    `requestStartedAt`, `whenRequestLifecycleIsLongerThan` also absent.
  - **Type mismatch:** Phare `handle()`/`terminate()` are typed but with `Phalcon\*`
    types; Laravel leaves params untyped and documents Symfony in docblocks.
    Phare constructor takes only `Application` (Laravel also injects `Router`).
    `bootstrap()` untyped return on both sides — match.
  - **Phalcon leak (Wrapper Rule §2 violation):** `handle()` exposes a raw
    `Phalcon\Http\RequestInterface` param and a raw `Phalcon\Http\ResponseInterface`
    return; `terminate()` exposes both raw `Phalcon\Http\*` types as params. The
    leak is present **both in the abstract `Foundation\Http\Kernel` and in the
    `Contracts\Http\Kernel` interface** — i.e. baked into the published contract.
    Phare ships `Phare\Http\Request` / `Phare\Http\Response` wrappers that should
    be the public types here. This is a genuine public-surface leak, not a
    container-resolution one.
  - **Status decision:** `[x]` does **NOT** hold — **downgrade to `[~]`**. Reason:
    the kernel functions, but its core public lifecycle methods (`handle`,
    `terminate`) violate the Wrapper Rule by typing on `Phalcon\Http\*` directly,
    and the violation is enshrined in the `Contracts\Http\Kernel` interface.

- 工数感 (Effort): **M** — two distinct fixes. (1) Re-type `handle()`/`terminate()`
  in both the abstract class and the contract to Phare's own `Request`/`Response`
  wrappers (or a Phare HTTP interface) — small but contract-breaking. (2) Add the
  ~18 middleware accessor methods — mechanical, mostly array operations over the
  existing `protected` fields. No structural rewrite needed; the pipeline already
  exists.
