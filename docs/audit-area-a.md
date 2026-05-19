# Audit — Area A: Core Web Stack

**Laravel 13 reference:** `/opt/laravel-framework` @ `13.2.0` (read-only)
**Phare package:** `phare/framework`, namespace `Phare\`, `src/Phare/`
**Method:** per-subsystem 1:1 public-API diff vs Laravel 13 + Wrapper Rule (§2) grep.
**Scope:** read-and-record only — no framework source edited.

---

### Routing

- Current:
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
- Expected:
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
- Gaps:
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
- Effort: **L** — Phare routing covers only basic verb + a hard-coded
  `resource()`; named routes, route-model binding, pattern constraints, the
  fluent `RouteRegistrar`, resource customization, and closure/array actions are
  all absent. Reaching Laravel-13 parity is a near-rewrite of the routing layer,
  not an incremental patch.

---

### HTTP Kernel

*Re-verification of a previously `[x]` subsystem against the Wrapper Rule (§2).*

- Current:
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

- Expected:
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

- Gaps:
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

- Effort: **M** — two distinct fixes. (1) Re-type `handle()`/`terminate()`
  in both the abstract class and the contract to Phare's own `Request`/`Response`
  wrappers (or a Phare HTTP interface) — small but contract-breaking. (2) Add the
  ~18 middleware accessor methods — mechanical, mostly array operations over the
  existing `protected` fields. No structural rewrite needed; the pipeline already
  exists.

### Middleware

- Current:
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

- Expected — Laravel 13:
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

- Gaps:
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

- Effort: **L** — three independent workstreams. (1) Re-type the
  `Middleware` interface + `MiddlewareContract` to Phare's `Request`/`Response`
  wrappers — contract-breaking, cascades to every middleware class. (2) Port the
  `$middlewarePriority` system + its mutators (depends on the Kernel-accessor work
  from `### HTTP Kernel`). (3) Port ~6 missing built-in middleware. Plus
  `withinTransaction()` on the Pipeline (S on its own). The dual-mode
  Phalcon-native vs Pipeline execution path is a structural divergence that any
  fix must preserve or deliberately retire.

### Request

- Current — Phare:
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

- Expected — Laravel 13:
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

- Gaps:
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

- Effort: **L** — three large workstreams. (1) Decouple Request from
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

- Current: Phare's response surface is three classes plus two global
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

- Expected: Laravel 13 splits the response surface across
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

- Gaps:
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

- Effort: **L** — the response layer is the least wrapped subsystem
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
