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

### Middleware

- 現状 (Current):
  - **Pipeline** — `Phare\Pipeline\Pipeline` mirrors Laravel's `Pipeline` closely.
    Public API: `__construct(?Container)`, `send()`, `through(...$pipes)`,
    `pipe(...$pipes)`, `via(string)`, `finally(Closure)`, `then(Closure)`,
    `thenReturn()`, `setContainer()`. Pipe resolution supports callables, objects,
    and `name:param,param` strings (`parsePipeString`). No Phalcon types on any
    public signature.
  - **Middleware contracts** — `Phare\Contracts\Http\Middleware` interface declares
    `handle(Phalcon\Http\RequestInterface, Closure): Phalcon\Http\ResponseInterface`.
    `Phare\Contracts\Http\MiddlewareContract` is an abstract class implementing both
    `Middleware` and `Phalcon\Mvc\Micro\MiddlewareInterface`; it wires Phalcon event
    hooks (`beforeHandleRequest`/`beforeSendResponse`) keyed off the marker
    interfaces `Foundation\Http\Concerns\BeforeMiddleware` / `AfterMiddleware`, and a
    public `call(Phalcon\Mvc\Micro $app)`.
  - **Registration (in `Foundation\Http\Kernel`)** — three `protected` fields:
    `$middlewares` (global), `$middlewareGroups` (`web`/`api`), `$routeMiddleware`
    (alias → class map). `syncMiddleware()`/`syncMiddlewareGroup()`/
    `syncRouteMiddleware()` push entries through `registerMiddleware()`. Alias+param
    strings (`alias:p1,p2`) resolved by `resolveRouteMiddlewareAlias()`.
  - **Two execution modes** — Phalcon-native (`$app->middleware()`) by default, or a
    `Pipeline`-based stack when `app.http.use_pipeline_middleware` is true
    (`pipelineMiddlewareStack`, `dispatchThroughMiddleware()`, `sendThroughPipeline()`).
  - **Helper classes (Phare-only)** — `Routing\MiddlewareApplicator` (apply a list in
    order with `onStart`/`onEnd` callbacks) and `Routing\RouteMiddlewareResolver`
    (alias array → class array; duplicates Kernel logic).
  - **Built-in middleware** — `Middleware\` ships `ThrottleRequests`, `VerifyCsrfToken`,
    `TokenMismatchException`; `Routing\Middleware\CorsMiddleware`;
    `Foundation\Http\Middleware\CheckForMaintenanceMode`.

- 期待 (Expected) — Laravel 13:
  - `Pipeline\Pipeline` public API: `__construct`, `send`, `through`, `pipe`, `via`,
    `then`, `thenReturn`, `finally`, `withinTransaction`, `setContainer`.
  - Middleware is duck-typed (`handle($request, Closure $next)`) — Laravel has **no**
    single `Middleware` interface; controller middleware uses the
    `Routing\Controllers\HasMiddleware` contract + `Middleware` value object.
  - Kernel registration fields: `$middleware`, `$middlewareGroups`, `$routeMiddleware`
    /`$middlewareAliases`, **plus `$middlewarePriority`** (ordered priority list).
  - Built-in `Foundation/Http/Middleware/*`: `CheckForMaintenanceMode`,
    `ConvertEmptyStringsToNull`, `HandlePrecognitiveRequests`,
    `InvokeDeferredCallbacks`, `PreventRequestForgery`,
    `PreventRequestsDuringMaintenance`, `TransformsRequest`, `TrimStrings`,
    `ValidateCsrfToken`, `ValidatePostSize`, `VerifyCsrfToken`.
  - Built-in `Routing/Middleware/*`: `SubstituteBindings`, `ThrottleRequests`,
    `ThrottleRequestsWithRedis`, `ValidateSignature`.

- 差分 (Gaps):
  - **Missing — Pipeline:** `withinTransaction()` absent. (Phare adds extra
    try/catch handling — `prepareDestination`/`carry`/`handleException`/
    `handleCarry` — which Laravel does not have; an intentional superset, not a gap.)
  - **Missing — priority:** no `$middlewarePriority` field and none of the priority
    mutators (`prependToMiddlewarePriority`, `appendToMiddlewarePriority`,
    `addToMiddlewarePriorityBefore/After`, `getMiddlewarePriority`,
    `setMiddlewarePriority`). Middleware runs purely in declared array order — no
    way to enforce relative ordering. (The Kernel-accessor gap itself is itemised
    in the `### HTTP Kernel` section above; not repeated here.)
  - **Missing — built-in middleware:** `ConvertEmptyStringsToNull`, `TrimStrings`,
    `TransformsRequest`, `ValidatePostSize`, `HandlePrecognitiveRequests`,
    `InvokeDeferredCallbacks` (Foundation); `SubstituteBindings` (route-model
    binding — also flagged in `### Routing`), `ValidateSignature`,
    `ThrottleRequestsWithRedis` (Routing). `CheckForMaintenanceMode` exists but is
    the legacy single-file form, not Laravel 13's `PreventRequestsDuringMaintenance`.
  - **Type mismatch:** Phare imposes a single `Middleware` interface forcing the
    `handle()` shape; Laravel keeps middleware duck-typed and only contracts
    *controller* middleware (`HasMiddleware`). Phare has no `HasMiddleware`
    equivalent. Phare `$middlewares` field is named with a trailing `s` vs Laravel
    `$middleware`. `RouteMiddlewareResolver` duplicates `resolveRouteMiddlewareAlias`
    logic in the Kernel — divergence risk.
  - **Phalcon leak (Wrapper Rule §2 violation):**
    - `Contracts\Http\Middleware::handle()` — raw `Phalcon\Http\RequestInterface`
      param and `Phalcon\Http\ResponseInterface` return on a **published interface**.
      Every middleware class (`ThrottleRequests`, `VerifyCsrfToken`, `CorsMiddleware`)
      inherits this leak in its public `handle()`.
    - `Contracts\Http\MiddlewareContract` — abstract class leaks `Phalcon\Mvc\Micro`
      on the public `call()` method, and `Phalcon\Events\Event` /
      `Phalcon\Mvc\Application` on its `protected` hook methods; also implements the
      raw `Phalcon\Mvc\Micro\MiddlewareInterface`.
    - Correct public types would be `Phare\Http\Request` / `Phare\Http\Response`.

- 工数感 (Effort): **L** — three independent workstreams. (1) Re-type the
  `Middleware` interface + `MiddlewareContract` to Phare's `Request`/`Response`
  wrappers — contract-breaking, cascades to every middleware class. (2) Port the
  `$middlewarePriority` system + its mutators (depends on the Kernel-accessor work
  from `### HTTP Kernel`). (3) Port ~6 missing built-in middleware. Plus
  `withinTransaction()` on the Pipeline (S on its own). The dual-mode
  Phalcon-native vs Pipeline execution path is a structural divergence that any
  fix must preserve or deliberately retire.

### Request

- 現状 (Current) — Phare:
  - `Phare\Http\Request extends \Phalcon\Http\Request implements \Phare\Contracts\Http\Request`,
    `use FileHelpers`. The published contract is
    `Phare\Contracts\Http\Request extends Phalcon\Http\RequestInterface` and declares
    only `all()` + `input()`.
  - Constructor is `__construct(protected array $rules = [])` — Request is coupled to
    a set of **validation rules**, not to HTTP payload params. It eagerly snapshots
    input into `$this->data = $this->get()` (Phalcon GET∪POST merge) at construct time.
  - Input accessors: `all()`, `input($name,$default=null)`, `only(array $keys)`,
    `except(array $keys)`, `query(?string $key=null,mixed $default=null)`,
    `has(string|array $key)`, `filled(string|array $key)`, `missing(string $key)`.
  - HTTP meta: `ip()`, `header(string $name,$default=null)`, `headers()`,
    `bearerToken(): ?string`, `url()`, `fullUrl(): string`, `isJson()`, `wantsJson()`,
    `isMethod($methods,bool $strict=true)`, `route(?string $param=null): mixed`.
  - Files (`FileHelpers` trait): `file(?string $key=null): ?UploadedFile`,
    `allFiles(): array`, `hasFile(string $key): bool`, plus non-Laravel extras
    `validateFileUpload()`, `validateImage()`.
  - Validation surface baked into Request: static `$validators` map (Phalcon
    validator classes), `make(array $data,array $rules,array $messages,array $customAttributes): static`,
    `rules(): array`, `validate($data): bool`, `getMessages()`, protected
    `firstRuleName()`/`formatValidationMessage()`.
  - Everything else (`getQuery`, `getPost`, `getHeader`, `getHeaders`, `getURI`,
    `getMethod`, `getClientAddress`, `hasFiles`, `getUploadedFiles`, `getServer`, …)
    is **inherited verbatim from `\Phalcon\Http\Request`** and surfaces on the public API.

- 期待 (Expected) — Laravel 13:
  - `Illuminate\Http\Request extends SymfonyRequest implements Arrayable, ArrayAccess`,
    composing traits `CanBePrecognitive`, `InteractsWithContentTypes`,
    `InteractsWithFlashData`, `InteractsWithInput` (which pulls
    `Support\Traits\InteractsWithData`), `Conditionable`, `Macroable`.
  - Input — `InteractsWithInput` + `InteractsWithData`: `all`, `input`, `query`,
    `post`, `server`, `keys`, `fluent`, `cookie`/`hasCookie`, `header`/`hasHeader`,
    `bearerToken`, `allFiles`/`hasFile`/`file`, `exists`, `has`, `hasAny`, `whenHas`,
    `filled`, `isNotFilled`, `anyFilled`, `whenFilled`, `missing`, `whenMissing`,
    `only`, `except`, `str`/`string`, `boolean`, `integer`, `float`, `clamp`, `date`,
    `interval`, `enum`, `enums`, `array`, `collect`, `dump`.
  - URL/meta — `Request.php`: `method`, `root`, `url`, `fullUrl`,
    `fullUrlWithQuery`, `fullUrlWithoutQuery`, `uri`, `path`, `decodedPath`,
    `segment`, `segments`, `is`, `routeIs`, `fullUrlIs`, `host`, `httpHost`,
    `schemeAndHttpHost`, `ajax`, `pjax`, `prefetch`, `secure`, `ip`, `ips`,
    `userAgent`, `getAcceptableContentTypes`, `merge`, `mergeIfMissing`, `replace`,
    `get`, `json`, `instance`, `duplicate`, `toArray`, `offsetExists/Get/Set/Unset`,
    `__isset`/`__get`.
  - Routing/session/user: `route`, `routeIs`, `fingerprint`, `hasSession`,
    `getSession`, `session`, `setLaravelSession`, `user`, `getUserResolver`/
    `setUserResolver`, `getRouteResolver`/`setRouteResolver`.
  - Content negotiation — `InteractsWithContentTypes`: `isJson`, `expectsJson`,
    `wantsJson`, `wantsMarkdown`, `accepts`, `prefers`, `acceptsAnyContentType`,
    `acceptsJson`, `acceptsMarkdown`, `acceptsHtml`, `format`.
  - Flash — `InteractsWithFlashData`: `old`, `flash`, `flashOnly`, `flashExcept`,
    `flush`.
  - Precognition — `CanBePrecognitive`: `filterPrecognitiveRules`,
    `isAttemptingPrecognition`, `isPrecognitive`.
  - `$request->validate(array $rules, ...)` is a **macro** (registered by the
    validation layer) returning the **validated data array**, not bool.

- 差分 (Gaps):
  - **Missing — input shape helpers:** `post`, `server`, `cookie`/`hasCookie`,
    `hasHeader`, `keys`, `fluent`, `json`, `merge`, `mergeIfMissing`, `replace`,
    `exists`, `hasAny`, `whenHas`, `whenFilled`, `whenMissing`, `anyFilled`,
    `isNotFilled`, `str`/`string`, `boolean`, `integer`, `float`, `clamp`, `date`,
    `interval`, `enum`, `enums`, `array`, `collect`, `dump`. Phare ships only the
    bare `input/query/all/only/except/has/filled/missing` set.
  - **Missing — URL/path API:** `method`, `path`, `decodedPath`, `segment`,
    `segments`, `is`, `routeIs`, `fullUrlIs`, `host`, `httpHost`,
    `schemeAndHttpHost`, `root`, `uri`, `fullUrlWithQuery`, `fullUrlWithoutQuery`,
    `ajax`, `pjax`, `prefetch`, `secure`, `ips`, `userAgent`,
    `getAcceptableContentTypes`. (`url`/`fullUrl`/`ip` exist; the rest do not.)
  - **Missing — content negotiation:** `expectsJson`, `wantsMarkdown`, `accepts`,
    `prefers`, `acceptsAnyContentType`, `acceptsJson`, `acceptsMarkdown`,
    `acceptsHtml`, `format`. Only `isJson`/`wantsJson` exist, and they are naive
    substring checks (`str_contains(..., '/json')`).
  - **Missing — flash / old input:** entire `InteractsWithFlashData` surface
    (`old`, `flash`, `flashOnly`, `flashExcept`, `flush`) absent — no
    flash-on-error round-trip.
  - **Missing — precognition:** entire `CanBePrecognitive` surface absent.
  - **Missing — session/user/route resolvers:** `hasSession`, `getSession`,
    `session`, `user`, `getUserResolver`/`setUserResolver`, `getRouteResolver`/
    `setRouteResolver`, `fingerprint`. Critically, `route()` is a **stub**: its
    body is a placeholder comment that always `return null` — route-model binding
    and route param access are non-functional. (Routing gap already itemised in
    `### Routing`; the Request-side stub is the consumer of that missing wiring.)
  - **Missing — interop:** `Arrayable`/`ArrayAccess` not implemented (`toArray`,
    `offsetExists/Get/Set/Unset`, `__get`/`__isset`); no `Conditionable`/`Macroable`,
    so no `$request->validate()` macro and no `when()`.
  - **Type mismatch — Request is a validation object.** The constructor
    `__construct(array $rules)` couples a *transport* object to *validation rules*;
    Laravel's Request constructor mirrors Symfony's
    `($query,$request,$attributes,$cookies,$files,$server,$content)`. `make()`
    builds a validation harness and returns a Request whose `validate()` has
    already run — colliding by name with Laravel's `Request::create()` (build a
    request from URI/method) while doing something unrelated. `make()`'s
    `$messages` and `$customAttributes` params are accepted but **silently ignored**
    (dead params).
  - **Type mismatch — `validate()` semantics.** Phare `validate($data): bool`
    takes the data as an argument, returns bool, and on failure keeps **only the
    first error** in `$messages` as a flat `['field'=>,'type'=>,'message'=>]` array.
    Laravel `validate(array $rules)` validates `$this` input, throws
    `ValidationException` on failure, and returns the full validated data array;
    errors are a `MessageBag`. `getMessages()` returns this single-error array, not
    a bag — incompatible with any Laravel error-rendering path.
  - **Type mismatch — untyped signatures.** `all()`, `input()`, `only()`,
    `except()`, `ip()`, `header()`, `headers()`, `url()`, `getMessages()`,
    `rules()`-consumers carry no/loose param+return types where Laravel is fully
    typed. `input()` reads only the construct-time `$this->data` snapshot (Phalcon
    `get()` = GET∪POST) — it never consults the JSON body or route params, so
    `input()` on a JSON request silently misses payload keys. `missing()` accepts
    only `string` (no array form) unlike `has`/`filled`.
  - **Phalcon leak (Wrapper Rule §2 violation):**
    - `Phare\Http\Request extends \Phalcon\Http\Request` — the entire Phalcon
      Request public API (`getQuery`, `getPost`, `getHeader(s)`, `getURI`,
      `getMethod`, `getClientAddress`, `hasFiles`, `getUploadedFiles`, `getServer`,
      `getJsonRawBody`, …) is published on Phare's `Request`. Phare's own wrappers
      then re-delegate to these (`query→getQuery`, `header→getHeader`,
      `url→getURI`, `ip→getClientAddress`, `isMethod→getMethod`).
    - `Phare\Contracts\Http\Request extends Phalcon\Http\RequestInterface` — the
      **published contract** directly extends a raw Phalcon interface (cf. the
      `### HTTP Kernel` learning: a leak baked into `Contracts/` is the worst form).
      Any consumer typed against the contract is bound to Phalcon.
    - No raw `Phalcon\*` type appears on Phare's *own* method param/return
      signatures — the leak is structural (inheritance + contract extension), not
      signature-level. The correct shape is a `Request` that wraps, not extends,
      `Phalcon\Http\Request` and a contract free of `RequestInterface`.

- 工数感 (Effort): **L** — three large workstreams. (1) Decouple Request from
  validation: drop the `$rules` constructor + `make()`/`validate()`/`$validators`
  into the FormRequest/validation layer (overlaps `US-A06`), restoring a
  transport-shaped constructor. (2) Sever the Phalcon inheritance + contract
  extension and re-expose ~50+ missing Laravel methods (input-shape helpers,
  URL/path API, content negotiation, flash, precognition, `ArrayAccess`/`Macroable`)
  over a wrapped Phalcon request. (3) Implement a real `route()` backed by the
  routing layer (blocked on `### Routing` route-object/model-binding gaps). The
  `input()`-misses-JSON-body bug is a correctness defect that should be fixed
  regardless of the larger re-architecture.

### Response

- 現状 (Current): Phare's response surface is three classes plus two global
  helpers — there is **no** `JsonResponse`, **no** `RedirectResponse`, and **no**
  response factory.
  - `Phare\Http\Response extends \Phalcon\Http\Response implements
    Phare\Contracts\Http\Response`. Own methods: `json(data,status=200,headers=[])`,
    `status(int)` (a **setter** returning `$this`), `cookie(name,value,expire,path,
    domain,secure,httponly)`, `withHeaders(array)`, `header(name,value)`,
    `view(view,data=[])`, `redirect($location=null,$externalRedirect=false,
    $statusCode=302)`, `back(status=302)`, `redirectTo(location,status=302)`.
  - `Phare\Http\FileResponse extends \Phalcon\Http\Response` (downloads): static
    `create()`, `send()`, `deleteFileAfterSend()`, `stream()`, `inline()`,
    `download()`, `setHeaders()`. Streams the file by `echo fread()` itself.
  - `Phare\Http\StreamedResponse extends \Phalcon\Http\Response`: ctor
    `(Closure,status=200,headers=[])`, static `create()`, `send()`,
    `setCallback()`, `isStreamed()`.
  - `Phare\Contracts\Http\Response interface extends Phalcon\Http\ResponseInterface`
    (empty body).
  - Helpers in `src/Phare/Support/helpers.php`: `response($content=null,
    ResponseStatusCode $statusCode=OK): Phare\Contracts\Http\Response` and
    `redirect(string $location, int $statusCode=302): Response`. `'response'` is
    bound as a **singleton** of `Phare\Http\Response` (`ResponseProvider`).
    `FileResponse`/`StreamedResponse` are not bound and have no factory entry.

- 期待 (Expected): Laravel 13 splits the response surface across
  `Http/Response.php` (+ `Http/ResponseTrait.php`), `Http/JsonResponse.php`,
  `Http/RedirectResponse.php`, and the `Routing/ResponseFactory.php` factory
  (contract `Contracts/Routing/ResponseFactory.php`). `response()` with no args
  returns the **factory**; the factory exposes `make`, `noContent`, `view`,
  `json`, `jsonp`, `eventStream`, `stream`, `streamJson`, `streamDownload`,
  `download`, `file`, `redirectTo`, `redirectToRoute`, `redirectToAction`,
  `redirectGuest`, `redirectToIntended`. `ResponseTrait` adds `status()` (a
  **getter** → int), `statusText()`, `content()`, `getOriginalContent()`,
  `header(key,values,replace=true)`, `withHeaders()`, `withoutHeader()`,
  `cookie()`/`withCookie()`/`withoutCookie()`, `getCallback()`, `withException()`,
  `throwResponse()`. `RedirectResponse` adds session-flash chaining: `with()`,
  `withInput()`, `onlyInput()`, `exceptInput()`, `withErrors()`, `withCookies()`,
  `withFragment()`/`withoutFragment()`, `get/setSession()`, `get/setRequest()`.
  `Response`/`JsonResponse` are `Macroable` and track `$original` content.

- 差分 (Gaps):
  - **Missing:**
    - `JsonResponse` class — none. `Response::json()` mutates and returns the
      shared `Response` singleton; there is no distinct JSON response type, no
      `getData()`/`setData()`/`setEncodingOptions()`/`hasEncodingOption()`, no
      JSON encoding-option handling, no `Arrayable`/`Jsonable`/`JsonSerializable`
      morphing.
    - `RedirectResponse` class — none. `redirect()`/`back()`/`redirectTo()` return
      a bare Phalcon response; **all** session-flash redirect chaining is absent
      (`with`, `withInput`, `withErrors`, `withCookies`, `onlyInput`,
      `exceptInput`, `withFragment`, `get/setSession`, `get/setRequest`).
    - `ResponseFactory` (class + contract) — none. The factory verbs `make`,
      `noContent`, `jsonp`, `eventStream`, `streamJson`, `streamDownload`,
      `redirectToRoute`, `redirectToAction`, `redirectGuest`,
      `redirectToIntended` have no Phare equivalent. `download`/`file` exist only
      as the unbound `FileResponse` class, `stream` only as `StreamedResponse`.
    - `ResponseTrait` accessors: `statusText()`, `content()`,
      `getOriginalContent()`, `withoutHeader()`, `withCookie()`,
      `withoutCookie()`, `getCallback()`, `withException()`, `throwResponse()`.
    - `$original` content tracking and `Renderable`/`Jsonable` auto-morphing in
      `setContent()`.
    - `Macroable` on every response type.
  - **Type mismatch:**
    - `Response::status(int): static` is a **setter** that returns `$this`;
      Laravel's `ResponseTrait::status(): int` is a **getter**. Same name,
      opposite semantics — a porting hazard.
    - `Response::view(view,data=[])` is a **stub**: it sets the literal string
      `"View: <name> with data: <json>"` as content (`// This would need
      integration with the view system`). No actual View rendering.
    - `redirect()`, `back()`, `redirectTo()` declare return type
      `Phalcon\Http\ResponseInterface` instead of `static`/`RedirectResponse`,
      and produce no redirect-specific object.
    - `cookie()` signature is positional `(name,value,expire,path,domain,secure,
      httponly)`; Laravel `cookie()` takes a `Symfony\...\Cookie` or delegates to
      the `cookie()` helper. `header()` lacks the `$replace` third argument.
    - `response()` helper takes `($content, ResponseStatusCode $statusCode)` and
      always returns a `Response` — it never returns a factory, has no `$headers`
      param, and array content is hardwired to a JSON response.
    - Phare response methods are typed, but inconsistently — `json()`/`status()`
      are typed while `redirect()`/`back()` use loose `Phalcon\*` returns.
  - **Phalcon leak (Wrapper Rule §2 violation):**
    - `Phare\Http\Response extends \Phalcon\Http\Response` — the entire Phalcon
      Response API (`setStatusCode`, `setContent`, `setJsonContent`,
      `setContentType`, `setHeader`, `getHeaders`, `getCookies`, `send`, …) is
      published on Phare's `Response`. Phare's own methods then re-delegate to it.
    - `Phare\Contracts\Http\Response extends Phalcon\Http\ResponseInterface` — the
      **published contract** directly extends a raw Phalcon interface (cf. the
      `### HTTP Kernel` / `### Request` learning: a leak baked into `Contracts/`
      is the worst form).
    - Signature-level leak: `redirect()`, `back()`, `redirectTo()` all declare
      `: ResponseInterface` (raw `Phalcon\Http\ResponseInterface`) as the return
      type — unlike `### Request`, the leak here reaches Phare's *own* method
      signatures, not just inheritance.
    - The `response()` helper return type `Phare\Contracts\Http\Response`
      transitively leaks the Phalcon interface to every helper caller.
    - `FileResponse` and `StreamedResponse` extend `Phalcon\Http\Response`
      **directly** (not Phare's `Response`), so they are entirely outside the
      wrapper and miss even Phare's own `json()`/`header()`/`withHeaders()`.

- 工数感 (Effort): **L** — the response layer is the least wrapped subsystem
  audited so far. Three workstreams: (1) introduce real `JsonResponse` and
  `RedirectResponse` types plus a `ResponseFactory` (class + contract) so
  `response()` can return a factory matching Laravel's 17-verb surface;
  (2) sever the Phalcon inheritance — `Response` (and `FileResponse`/
  `StreamedResponse`) should *wrap* `Phalcon\Http\Response`, the contract must
  drop `extends ResponseInterface`, and `redirect()/back()/redirectTo()` must
  stop returning `Phalcon\Http\ResponseInterface`; (3) resolve the `status()`
  setter/getter semantic clash, implement a non-stub `view()` wired to the View
  layer, and add `ResponseTrait` accessors + `Macroable` + `$original` tracking.
  The `view()` stub is a correctness defect (returns a debug string as the
  response body) that should be fixed regardless of the larger re-architecture.

### FormRequest + Validation

- 現状 (Current) — Phare:
  - `Phare\Http\FormRequest extends Phare\Http\Request` (abstract). It transitively
    extends `\Phalcon\Http\Request` — the structural class-level leak documented in
    `### Request` is inherited by every FormRequest subclass.
  - Hooks: `rules(): array`, `messages(): array`, `attributes(): array`,
    `authorize(): bool`, protected `prepareForValidation(): void`,
    `passedValidation(): void`, `failedValidation(Validator): void`,
    `failedAuthorization(): void`, `createValidator(): Validator`.
  - Accessors: `validated(): array`, `safe(): array`, `validator(): Validator`,
    `getValidatorInstance(): Validator`, `validateResolved(): void`.
  - `validateResolved()` runs `authorize()` then `validator()->fails()` and throws
    `ValidationException`. There is **no redirect path** — failure is always an
    exception, never a redirect-back-with-errors. `prepareForValidation()` and
    `passedValidation()` are declared but **never invoked** by `validateResolved()`
    (only `getValidatorInstance()` calls `prepareForValidation()`).
  - `Phare\Validation\Validator`: ctor `(array $data, array $rules, array $messages=[],
    array $customAttributes=[])`; `passes()`, `fails()`, `errors(): MessageBag`,
    `validated(): array`, `safe(): array`, `addCustomRule(string,\Closure): void`,
    static `make(...)`. Rules are `string` (pipe-delimited) or `array` of strings only.
  - Built-in rules — **19 total**: `required`, `string`, `integer`, `numeric`,
    `email`, `min`, `max`, `between`, `in`, `not_in`, `confirmed`, `same`,
    `different`, `array`, `boolean`, `date`, `url`, `regex`, `nullable`.
  - `Phare\Validation\MessageBag implements ArrayAccess, Countable, JsonSerializable`:
    `add`, `merge`, `has`, `first`, `get`, `all`, `keys`, `isEmpty`, `isNotEmpty`,
    `count`, `toArray`, `jsonSerialize`, `__toString`.
  - `Phare\Validation\ValidationException extends \Exception`: `getValidator()`,
    `errors(): MessageBag`, `getStatus(): int`, `setStatus(int): self`,
    `errorBag(): string`, static `withMessages(array): self`.

- 期待 (Expected) — Laravel 13:
  - `Illuminate\Foundation\Http\FormRequest extends Request implements ValidatesWhenResolved`,
    `use ValidatesWhenResolvedTrait`. Surface: `validationData()`, `validationRules()`,
    `validated()`, `safe(): ValidatedInput`, `messages()`, `attributes()`,
    `setValidator()`, `setRedirector()`, `setContainer()`, `getValidatorInstance()`,
    `createDefaultValidator()`, `failedValidation()`, `getRedirectUrl()`,
    `passesAuthorization()`, `failedAuthorization()`, `validateResolved()`,
    `prepareForValidation()`, `passedValidation()`, `configureFromAttributes()`.
    Redirect properties `$redirect`, `$redirectRoute`, `$redirectAction`, plus
    `$errorBag`, `$stopOnFirstFailure`, `$after`.
  - `Illuminate\Validation\Validator implements ValidatorContract` — ~60 public
    methods incl. `after`, `whenPasses`/`whenFails`, `validate`, `validateWithBag`,
    `safe`, `valid`/`invalid`/`failed`, `messages`/`errors`/`getMessageBag`,
    `hasRule`, `sometimes`, `stopOnFirstFailure`, `setData`/`setRules`/`addRules`/
    `appendRules`, `addExtension`/`addImplicit*`/`addDependent*`, `addReplacer`,
    `setCustomMessages`/`setAttributeNames`/`addCustomAttributes`, presence verifier,
    translator. `__construct` takes a `Translator`.
  - Built-in rules — **~110**: Accepted(If), Declined(If), ActiveUrl, Ascii, Bail,
    Before/After(OrEqual), Alpha(Dash/Num), Array, List, RequiredArrayKeys, Between,
    Boolean, Confirmed, Contains/DoesntContain, Date/DateFormat/DateEquals, Decimal,
    Different, Digits(Between), Dimensions, Distinct, Email, Encoding, Exists, Unique,
    Extensions, File, Filled, Gt/Lt/Gte/Lte, Lowercase/Uppercase, HexColor, Image,
    In/InArray(Keys), Integer, Ip, MacAddress, Json, Max(Digits), Mimes/Mimetypes,
    Min(Digits), Missing(If/Unless/With/WithAll), MultipleOf, Nullable, NotIn,
    Numeric, Present(If/Unless/With/WithAll), Regex/NotRegex, Required(If/Unless/
    With/WithAll/Without/WithoutAll/IfAccepted/IfDeclined), Prohibited(If/Unless/
    IfAccepted/IfDeclined)/Prohibits, Exclude(If/Unless/With/Without), Same, Size,
    Sometimes, Starts/EndsWith, DoesntStart/EndsWith, String, Timezone, Url, Ulid,
    Uuid.
  - `Illuminate\Validation\Rule` — 28 static builders (`unique`, `exists`, `in`,
    `notIn`, `requiredIf`, `enum`, `file`, `imageFile`, `dimensions`, `date`,
    `email`, `password`, `array`, `forEach`, `when`/`unless`, `can`, `anyOf`,
    `contains`, …) returning rich `Rules/*` objects (`Password`, `File`, `Email`,
    `Enum`, `Numeric`, `StringRule`, `Dimensions`, `Unique`, `Exists`, …).
  - Object-rule support: closure rules (`ClosureValidationRule`), `ValidationRule`/
    `InvokableRule`/`Rule` contracts, `DataAwareRule`, `ValidatorAwareRule`,
    `NestedRules`, `ConditionalRules`. Nested/array data via dot-notation and `.*`
    wildcards (`ValidationRuleParser`, `ValidationData`).
  - `Contracts\Validation\{Validator, Factory, Rule, ValidationRule, InvokableRule,
    ImplicitRule, DataAwareRule, ValidatorAwareRule, CompilableRules,
    ValidatesWhenResolved, UncompromisedVerifier}` — a full contract surface.
  - `Illuminate\Validation\ValidationException`: props `$status=422`, `$errorBag`,
    `$redirectTo`, `$response`; methods `status()`, `errorBag()`, `redirectTo()`,
    `getResponse()`, static `withMessages()`.

- 差分 (Gaps):
  - **Missing — Validator rules (~91 of ~110 absent).** No conditional/dependent
    rules at all: `sometimes`, `bail`, `required_if/unless/with/without/...`,
    `exclude*`, `prohibited*`, `prohibits`, `missing*`, `present*`. No `size`,
    `digits`, `gt/lt/gte/lte`, `distinct`, `alpha*`, `accepted/declined`,
    `before/after`, `date_format`, `uuid/ulid`, `ip`, `json`, `timezone`,
    `mimes/file/image/dimensions`, `decimal`, `multiple_of`, `starts_with/ends_with`,
    `lowercase/uppercase`, `active_url`, etc.
  - **Missing — FormRequest redirect flow.** No `$redirect`/`$redirectRoute`/
    `$redirectAction`, `getRedirectUrl()`, `setRedirector()`, `validationData()`,
    `validationRules()`, `setValidator()`, `setContainer()`, `$errorBag`,
    `$stopOnFirstFailure`, `$after`, `configureFromAttributes()`. Validation
    failure can only throw — the redirect-back-with-errors UX is unsupported.
  - **Missing — Validator surface.** No `after()` callbacks, `sometimes()`,
    `validateWithBag()`/named error bags, `valid()`/`invalid()`/`failed()`,
    `getMessageBag()`/`messages()`, `setData`/`setRules`/`addRules`, custom
    extensions/replacers, translator integration, presence verifier.
  - **Missing — object rules.** Rules must be plain strings; no support for
    closure rules, `Rule::*()` builders, `Rules/*` objects, or the
    `ValidationRule`/`InvokableRule` contracts. No `Rule` facade equivalent.
  - **Missing — nested/array validation.** No dot-notation or `.*` wildcard
    expansion; `Validator` only iterates top-level `array_keys($rules)`.
  - **Missing — `Contracts\Validation\*`.** Phare publishes no validation
    interfaces; `Validator`/`FormRequest` are concrete-only (cf. the absent
    `Contracts/Validation/` namespace).
  - **Type mismatch — `safe()` return.** Phare `FormRequest::safe()` and
    `Validator::safe()` return a plain `array`; Laravel returns a `ValidatedInput`
    object (`only`/`except`/`collect`/`merge`). Porting hazard.
  - **Type mismatch — `boolean` rule.** Phare accepts the strings `'true'`/`'false'`;
    Laravel's `boolean` rule does not (only `true,false,1,0,'1','0'`).
  - **Type mismatch — `confirmed` rule.** Phare hardcodes the `_confirmation`
    suffix; Laravel 9+ accepts `confirmed:other_field`.
  - **Type mismatch — custom-message placeholders.** Phare substitutes rule params
    only into its own *default* messages; user-supplied `messages()` get no
    `:attribute`/`:min`/`:other` placeholder replacement.
  - **Type mismatch — untyped signatures.** `Validator` rule methods take an
    untyped `$value`; `addCustomRule` callback contract is undocumented.
  - **Correctness defect — `exists`/`unique` silently pass.** Both appear in
    `getDefaultMessage()` but have **no** `validateExists`/`validateUnique` method
    and there is no presence verifier. `validateRule()` finds no handler and no
    custom rule, so it does nothing — a rule of `unique`/`exists` is a guaranteed
    false-pass. Same class of stub defect as `Request::route()` (US-A04) and
    `Response::view()` (US-A05).
  - **Phalcon leak — inherited structural.** `FormRequest` extends
    `Phare\Http\Request extends \Phalcon\Http\Request`; the entire
    `Phalcon\Http\Request` API is published on every FormRequest subclass. This is
    the `### Request` leak inherited, not a new one — do not double-count.
  - **Phalcon leak — Validation namespace is clean.** `grep -rn "Phalcon"`
    `src/Phare/Validation/` and `src/Phare/Http/FormRequest.php` returns nothing:
    `Validator`, `MessageBag`, `ValidationException` carry no Phalcon types.

- 工数感 (Effort: L) — The validator is a 19-rule toy beside Laravel's ~110-rule
  engine; closing it means the conditional/dependent/array-aware rule machinery,
  the `Rule` builder + `Rules/*` object family, object/closure-rule contracts,
  nested dot-notation parsing, the translator-driven message layer, and the full
  `Contracts\Validation\*` surface. `FormRequest` additionally needs the entire
  redirect-back-with-errors flow and the missing accessor/hook surface. The
  `exists`/`unique` false-pass is a correctness defect that should be fixed (or the
  rules removed from `getDefaultMessage()`) regardless of the larger build-out.

### View / Blade

- 現状 (Current) — Phare ships **two parallel, conflicting view stacks**:

  - **Stack 1 — `src/Phare/View/` (modern, non-functional).**
    `ViewServiceProvider` binds `'view'` to `Phare\View\Factory`. `Factory` builds
    `Phare\View\View` value objects (`make/exists/addLocation/addNamespace/`
    `composer/creator/share/addExtension/getExtensions/getShared/getPaths`, plus
    wildcard composer/creator matching). **`View::render()` is a debug-string
    stub** — it returns `"View: {$this->view} with data: " . json_encode(...)` and
    never invokes any compiler. `Factory` stores `extensions` as a string map but
    has no engine resolver, so a `.blade.php` extension resolves to nothing.
    `ViewComposer` is an abstract `compose(View $view)` hook.
  - **Stack 2 — `BladeViewProvider` (Phalcon-native, functional).**
    `src/Phare/Providers/BladeViewProvider` binds `'blade'` to `Phare\View\Blade`
    and **re-binds `'view'`** to `Phare\View\BladeView`. Rendering is wired through
    a Phalcon `dispatch:afterExecuteRoute` event that calls `$app['blade']->run()`.
    This is the path that actually renders templates.
  - **Engine — `Phare\View\BladeOne`** is a vendored copy of the third-party
    EFTEC/BladeOne **v4.9** single-file Blade engine (~4160 lines). `Blade extends
    BladeOne` and mixes in two trait files: `Tags\BladeFunction` (adds `@lang`,
    `@config`) and `Tags\BladeHtml` (`useTailwind()`, `useDaisyui()`). `BladeOneHtml`
    is a further HTML-helper subclass. Phare has **no Blade compiler of its own** —
    directive coverage is entirely whatever BladeOne 4.9 ships.
  - **`TemplateEngine`** (`View/Template/`) is a separate runtime helper holding
    section/stack/include logic (`startSection/yieldContent/extend/include*/`
    `push/stack/prepend/...`) — Laravel keeps this logic in the *compiler*; Phare
    keeps a runtime parallel to it.
  - No `Phare\Contracts\View\*` namespace exists — the view layer publishes no
    interface.

- 期待 (Expected) — Laravel 13:

  - `View\Factory` resolves an `EngineResolver` → `CompilerEngine`/`PhpEngine`/
    `FileEngine`; `FileViewFinder` (`ViewFinderInterface`) locates templates;
    7 `Concerns\Manages*` traits (Layouts, Stacks, Components, Fragments, Loops,
    Translations, Events) live in the factory.
  - `View\Compilers\BladeCompiler` composes **21 `Concerns\Compiles*` traits** —
    **124 `compile*` methods**. Public compiler API includes `directive()`,
    `if()`, `component()`/`components()`, `componentNamespace()`, anonymous-
    component paths/namespaces, `aliasComponent/aliasInclude`, `precompiler()`,
    `prepareStringsForCompilationUsing()`, `with/withoutDoubleEncoding()`,
    `withoutComponentTags()`.
  - `ComponentTagCompiler` handles the `<x-foo>` / `<x-slot>` tag syntax;
    `Component`, `AnonymousComponent`, `DynamicComponent`, `ComponentAttributeBag`,
    `ComponentSlot` back the class-component system.
  - `Middleware\ShareErrorsFromSession` auto-shares the `$errors` MessageBag.

- 差分 (Gaps):

  - **Missing — Blade directives (~30 absent vs Laravel 13).** BladeOne 4.9
    exposes ~98 `compile*` methods; Laravel 13 has 124. Diffing the user-facing
    set, BladeOne is missing:
    - Component system: `@props`, `@aware`, `@componentFirst`, and the **entire
      `<x-component>` / `<x-slot>` tag compiler** (`ComponentTagCompiler`) — the
      single largest gap. BladeOne's `@component` is the legacy string-based form
      only; there is no class-component / anonymous-component machinery.
    - HTML-attribute directives: `@class`, `@style`, `@checked`, `@selected`,
      `@disabled`, `@readonly`, `@required`.
    - Security / assets: `@csrf`, `@vite`, `@viteReactRefresh`, `@js`.
    - Environment: `@env`/`@endenv`, `@production`/`@endproduction`.
    - Sections / stacks: `@once`, `@prependOnce`, `@pushIf`/`@elsePushIf`,
      `@elsePush`, `@hasStack`, `@sectionMissing`, `@session`/`@endsession`,
      `@fragment`/`@endfragment`, `@extendsFirst`.
    - Includes: `@includeUnless`, `@includeIsolated`.
    - Misc: `@context`/`@endcontext`, `@bool`, `@choice` (translation choice).
    Present in both (parity OK): echoes (raw/escaped/regular), `@if/@unless/`
    `@for/@foreach/@forelse/@while/@switch`, `@isset/@empty`, `@section/@yield/`
    `@show/@stop/@overwrite/@append/@parent/@hasSection`, `@push/@pushOnce/`
    `@prepend/@stack`, `@include/@includeIf/@includeWhen/@includeFirst`,
    `@extends`, `@auth/@guest/@can/@cannot/@canany` (+`@else*`/`@end*`),
    `@php/@unset/@use/@inject/@json/@dd/@dump/@method/@each`, `@slot/@component`
    (legacy). BladeOne also ships **non-Laravel extras** (`@canonical`, `@base`,
    `@relative`, `@splitForeach`, `@includeFast`, `@compileStamp`, `@asset`) —
    divergent surface, not parity.
  - **Type mismatch — `@lang` semantics.** Phare's `Tags\BladeFunction::`
    `compileLang()` emits `<?= __$expression ?>` — an inline translation echo.
    Laravel's `@lang ... @endlang` is a *block* directive; the inline equivalent
    is `{{ __() }}`. Same directive name, different shape.
  - **Correctness defect — `View::render()` is a stub.** Stack 1's `Factory`
    produces `View` objects whose `render()` returns a debug string and never
    compiles a template. Any code resolving `'view'` before `BladeViewProvider`
    re-binds it, or using `Phare\View\Factory` directly, renders garbage. Same
    stub-defect class as `Request::route()` (US-A04), `Response::view()` (US-A05),
    and the validator `exists`/`unique` no-op (US-A06).
  - **Architecture defect — two providers bind `'view'`.** `ViewServiceProvider`
    and `BladeViewProvider` both register a `'view'` service with incompatible
    types (`Phare\View\Factory` vs `Phare\View\BladeView`). Resolution depends on
    registration order — a divergence/footgun, flagged like the
    `RouteMiddlewareResolver`/`Kernel` duplication in `### Middleware`.
  - **Missing — Factory parity.** No `EngineResolver`/`CompilerEngine`/`PhpEngine`,
    no `ViewFinderInterface`/`FileViewFinder` resolution, no `first()`/
    `renderWhen`/`renderEach`, no view events (`composing:`/`creating:`), none of
    the 7 `Manages*` concerns as factory methods (section/stack/component/loop
    state lives in the separate runtime `TemplateEngine` instead). `composer()`/
    `creator()`/`share()` with wildcard matching ARE present — a genuine parity
    point.
  - **Missing — `$errors` auto-share.** No `ShareErrorsFromSession` equivalent;
    ties to the redirect-back-with-errors gap recorded in `### FormRequest +
    Validation` (US-A06).
  - **Phalcon leak — structural, Stack 2 only.** `BladeView extends`
    `\Phalcon\Mvc\View` (class-level leak — full Phalcon view API published).
    `BladeViewProvider implements Phalcon\Di\ServiceProviderInterface`, its
    `register()` param is typed `Application|DiInterface` (Phalcon), and the body
    pulls in `Phalcon\Mvc\Dispatcher`, `Phalcon\Html\Escaper`,
    `Phalcon\Flash\Session`. The functional view stack is deeply Phalcon-coupled.
  - **Phalcon leak — none in Stack 1 / engine.** `Factory`, `View`,
    `ViewComposer`, `TemplateEngine`, `Blade`, `BladeOne` carry no `Phalcon\`
    types — the modern stack and the vendored engine are Phalcon-clean (but the
    modern stack is non-functional, per the correctness defect above).

- 工数感 (Effort): **L** — three independent workstreams. (1) Reconcile the two
  view stacks into one and make `Factory`/`View::render()` actually invoke the
  engine (engine resolver + view finder + real `render()`) — currently the
  Laravel-shaped stack is a dead stub. (2) Re-home the functional path off
  `Phalcon\Mvc\View`/`ServiceProviderInterface` to satisfy the Wrapper Rule.
  (3) Directive parity: ~30 missing directives, dominated by the absent
  `<x-component>` tag compiler and the class/anonymous-component system — that
  alone is a large build-out, since BladeOne 4.9 has no equivalent architecture.
