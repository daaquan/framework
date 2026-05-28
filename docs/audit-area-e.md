# Audit — Area E: Foundation (Container, Providers, Config, Console, Facades, Helpers, Translation)

**Laravel 13 reference:** `/opt/laravel-framework` @ `13.2.0` (read-only)
**Phare package:** `phare/framework`, namespace `Phare\`, `src/Phare/`
**Method:** per-subsystem 1:1 public-API diff vs Laravel 13 + Wrapper Rule (§2) grep.
**Scope:** read-and-record only — no framework source edited.

---

### Container

- 現状 (Current) — Phare:
  `src/Phare/Container/Container.php` (~12 350 tok, ~800 LOC) extends
  `Phalcon\Di\Di` and implements `Phare\Contracts\Foundation\Container` +
  `Psr\Container\ContainerInterface`. Additional files:
  `BoundMethod.php` (autowired invocation, ~90 LOC),
  `ContextualBindingBuilder.php` / `ContextualBindingNeedsBuilder.php`
  (contextual-binding fluent builders),
  `Exceptions/{ContainerException,ServiceNotFoundException}.php`,
  `Attributes/` (16 contextual-attribute resolvers shipped — full Laravel 13
  attribute set).

  - **`Phare\Container\Container`** — 40 own public methods (alias,
    singleton/If, instance, bind/If, extend, tag/tagged, make, resolving,
    afterResolving, afterResolvingAttribute, resolved, scoped/scopedIf,
    forgetScopedInstances, forgetInstance/s, forgetExtenders, flush,
    bound, isShared, getAlias, hasMethodBinding, bindMethod,
    callMethodBinding, call, factory, rebinding, refresh,
    addContextualBinding, when, whenHasAttribute, resolveFromAttribute,
    beforeResolving, currentlyResolving, wrap, isReserved, isAliasReserved,
    psrGet) + all `Phalcon\Di\Di` inherited methods (see leaks).
  - **`Phare\Contracts\Foundation\Container`** — 10 methods (bound,
    resolved, isShared, bind, bindIf, singleton, singletonIf, alias, make).

- 期待 (Expected) — Laravel 13:
  `Illuminate\Container\Container` (50 pub, not extending any Phalcon class)
  + `Illuminate\Contracts\Container\Container` (24 methods).
  Laravel's container is PSR-11, ArrayAccess, Macroable.

- 差分 (Gaps):

  **Phalcon leaks**

  - **Structural inheritance leak** — `Container extends Phalcon\Di\Di`
    publishes the ENTIRE Phalcon DI API as Container's public surface:
    `set`, `setShared`, `setRaw`, `setAlias`, `remove`, `getService`,
    `getServices`, `getServiceByType`, `getDefaultBuilder`, `getDefault`,
    `resetDefault`, `loadFromConfig`, `loadFromPhp`, `loadFromYaml`,
    `loadFromIni`, `loadFromJson`, `__set`, `__get`, `__isset` etc. — all
    Phalcon-DI-specific plumbing is part of Phare's published Container
    surface. Documented exception (per PRD §2): `get()/has()` are
    inherited from Phalcon\Di\Di and are NOT new gaps.

  - **Reserved-services map** — `$reservedServices` and
    `$reservedServiceAlias` hard-code 23 Phalcon service slot names
    (config/dispatcher/router/request/response/session/encrypter/…) to
    Phalcon type classes. The `isReserved()`/`isAliasReserved()` methods
    expose this coupling as public API — slot names and Phalcon type
    expectations are part of the published surface.

  **Missing vs Laravel Container**

  - `build(string $concrete): mixed` — public in Laravel (exposes
    the concrete instantiation step; enables test doubles).
  - `makeWith(string $abstract, array $parameters)` — Laravel alias
    for `make()` with params; improves readability.
  - `currentEnvironmentIs(string ...$environments): bool` — not present.
  - `resolveEnvironmentUsing(Closure $callback): static` — L13 hook.
  - `getBindings(): array` — introspect registered bindings.
  - `fireAfterResolvingAttributeCallbacks(...)` — public in L13.
  - ArrayAccess (`offsetExists/Get/Set/Unset`) — Laravel Container
    implements ArrayAccess directly; Phare inherits Phalcon\Di\Di's
    array-access (if any), but not under the same contract.

  **Thin contract**

  `Phare\Contracts\Foundation\Container` is 10 methods vs Laravel's
  `Contracts\Container\Container` 24 methods. Missing from contract:
  call, extend, factory, flush, addContextualBinding, afterResolving,
  beforeResolving, bindMethod, resolving, scoped, scopedIf, tag, tagged,
  when.

  **Status downgrade** — [x] → [~]. The structural inheritance leak means
  the entire Phalcon\Di\Di surface (Phalcon-specific service loader,
  Phalcon DI introspection, etc.) is published as Phare's Container API.
  The Container is functionally capable for Phare use but is NOT
  Wrapper-Rule-clean. Effort to fix: M (wrap Di rather than extend it).

- 工数感 (Effort): **M** — container core is well-implemented and tests pass.
  Fix requires composition over inheritance (`Container` wraps `Di` rather
  than extends it), adapting the reserved-services slot lookup, and
  expanding the contract to match Laravel's 24 methods.

---

### Service Providers

- 現状 (Current) — Phare:
  `src/Phare/Support/ServiceProvider.php` (23 LOC): abstract class with
  3 public methods (register, boot, __construct). No DeferrableProvider
  abstraction. 26 concrete providers live in `src/Phare/Providers/`.

  - **`Phare\Support\ServiceProvider`** — abstract; 3 pub:
    `__construct(Application|DiInterface $app)`, abstract `register(): void`,
    `boot(): void` (default empty).

- 期待 (Expected) — Laravel 13:
  `Illuminate\Support\ServiceProvider` (10 pub: __construct/register/
  booted/booting/callBootedCallbacks/callBootingCallbacks/commands/
  isDeferred/provides/when) + `DeferrableProvider` contract (provides
  method only). Also supports `$bindings`/`$singletons` convenience arrays,
  `callAfterResolving()`, `ensurePublishArrayInitialized()`,
  `publishes()`, `loadMigrationsFrom()`, `loadViewsFrom()`, etc.

- 差分 (Gaps):

  **Phalcon leaks**

  - **Published-dependency leak** — `__construct(Application|DiInterface $app)`
    — `Phalcon\Di\DiInterface` is one half of the union type on the base
    class constructor, published to every concrete provider inheriting it.
    Same shape as C03 HashServiceProvider::register(Application|DiInterface),
    C04 EncrypterProvider::register, C01 AuthServiceProvider, etc.
    The fix: remove the DiInterface union arm; only accept `Application`.

  **Missing**

  - `booted(Closure $callback)` / `booting(Closure $callback)` /
    `callBootedCallbacks()` / `callBootingCallbacks()` — lifecycle
    callbacks absent; no way to defer work until after all providers boot.
  - `commands(array $commands)` — register artisan commands from provider.
  - `isDeferred(): bool` / `provides(): array` / `when(): array` —
    deferred-provider protocol absent; no DeferrableProvider contract.
    Every Phare provider is loaded eagerly.
  - `$bindings` / `$singletons` convenience arrays — absent; providers
    must write explicit `$this->app->bind/singleton()` calls.
  - Resource publishing (`publishes`, `loadMigrationsFrom`,
    `loadViewsFrom`, `loadRoutesFrom`, etc.) — absent.
  - `callAfterResolving()` — helper for hook-on-resolution patterns.

  **No-contracts** — no `Phare\Contracts\Support\ServiceProvider` and no
  `DeferrableProvider` contract (18th no-contracts subsystem).

- 工数感 (Effort): **S** — structural shape is correct; missing surface is
  mechanical backfill. The constructor-leak fix is a one-line union removal.

---

### Config

- 現状 (Current) — Phare:
  `src/Phare/Config/Repository.php` (~2 011 tok): standalone class
  implementing only `ArrayAccess`; zero Phalcon inheritance; 22 public
  methods. Additional: `ConfigCache.php` (cache-to-file), `ConfigEnvironment.php`
  (env overrides), `EnvironmentDetector.php`, `EnvironmentManager.php`.

  - **`Phare\Config\Repository`** (22 pub): all, array, boolean, collection,
    __construct, float, get, getMany, has, integer, merge, offsetExists,
    offsetGet, offsetSet, offsetUnset, path, prepend, push, set, setMany,
    string, toArray.

- 期待 (Expected) — Laravel 13:
  `Illuminate\Config\Repository` (18 pub) + `Contracts\Config\Repository`
  (6 methods: all, get, has, prepend, push, set). Also `Macroable`.

- 差分 (Gaps):

  **Phalcon leaks** — NONE. `Repository` is FULLY Phalcon-CLEAN (only a
  comment mentioning Phalcon for context). [x] STATUS HOLDS.

  **Phare superset** — Phare adds `merge()`, `path()`, `setMany()`,
  `toArray()` — extras relative to Laravel, not gaps. `path()` is the
  dot-notation traversal that the `config()` helper uses.

  **Missing**

  - `Macroable` trait — not applied; `when()` / custom macros unavailable.
  - `Phare\Contracts\Config\Repository` — no contract interface published
    (19th no-contracts subsystem). Concrete classes that type-hint on the
    repository must use the concrete class, not an interface.

  **[x] CONFIRMED.** Config is Phalcon-clean. Functionally equivalent to
  Laravel's implementation. Minor gap: no Macroable, no published contract.

- 工数感 (Effort): **S** — add Macroable (one trait use) and publish a
  Contracts\Config\Repository interface (extract from the existing class).

---

### Console / Artisan

- 現状 (Current) — Phare:
  6 core files + 14 commands + 5 input/output helpers + 4 console helpers.
  `Application extends SymfonyApplication` (Symfony\Component\Console 6/7)
  + `Command extends SymfonyCommand`. No Phalcon inheritance in any of these.

  - **`Phare\Console\Application`** (6 own pub: __construct, add, call,
    getFrameworkApplication, output, resolveCommands) — extends
    Symfony Application.
  - **`Phare\Console\Command`** (9 own pub: __construct, addInput, anticipate,
    ask, askPassword, choice, choose, confirm, setFrameworkApplication)
    + Symfony Command inherited surface. `handle(): int` abstract.
  - **`Phare\Console\Kernel`** implements `Contracts\Console\Kernel` (2
    methods: handle/resolve). Registers commands + ScheduleServiceProvider.
  - **14 shipped commands**: schedule:run/list, cache:clear, config:clear,
    env, key:generate, make:controller/middleware/migration/model/request/
    seeder, migrate, queue:work, route:clear, seed, view:clear.

- 期待 (Expected) — Laravel 13:
  `Illuminate\Console\Application` (9 pub: add, addCommand, call, __construct,
  getLaravel, output, resolve, resolveCommands, setContainerCommandLoader) +
  `Illuminate\Console\Command` (7 pub: __construct, fail, getLaravel,
  isHidden, run, setHidden, setLaravel + ~40 IO methods via trait) +
  `Illuminate\Foundation\Console\Kernel` (full artisan lifecycle: handle,
  queue, all, output, call/withOutput, terminate, bootstrap,
  bootstrappers, whenCommandLifecycleIsLongerThan, getArtisan) +
  ~60 make:* and framework commands.

- 差分 (Gaps):

  **Phalcon leaks** — NONE in core console files. All extend Symfony, not
  Phalcon.

  **Application missing**

  - `addCommand(string|Command)` — register by class-string (Phare uses
    `resolveCommands(array)` only).
  - `resolve(string $command): Command` — resolve a command from container.
  - `setContainerCommandLoader(ContainerCommandLoader)` — lazy command
    loader.
  - `getLaravel(): Application` — access the framework application from
    within the Symfony app.

  **Command missing**

  - `fail(Throwable|string $message)` — short-circuit with exception.
  - `setLaravel(Container)` / `getLaravel()` — bind application to command.
  - `isHidden()` / `setHidden()` — hide commands from list.
  - Full IO trait surface (table/newLine/twoColumnDetail/withProgressBar/
    createProgressBar/title/section/listing/definitionList) — partial
    in Phare (ask/anticipate/choice/confirm present; newLine/table/progress
    absent).
  - No `$description` / `$help` convention — Phare uses `$name` (Symfony
    style); Laravel uses `$signature` parsing via `Parser`.

  **Kernel missing**

  - Full lifecycle: `bootstrap()`, `bootstrappers()`, `terminate()`,
    `whenCommandLifecycleIsLongerThan()`.
  - `call()` / `callWithOutput()` — programmatic artisan invocation.
  - `queue()` — dispatch artisan command to the queue.
  - `output()` — last-output capture.

  **Missing commands (~46 of Laravel's ~60)**

  Phare ships 14; absent: make:cast/channel/class/console/contract/enum/
  event/exception/job/listener/mail/notification/observer/policy/provider/
  resource/rule/scope/test/view, about, auth:clear-resets, cache:forget/prune/
  table, channel:list, clear-compiled, db/db:table/db:show, event:list,
  inspire, lang:publish/update, list, make:class/interface/trait, model:prune/
  show, optimize/optimize:clear, package:discover, queue:clear/failed/
  failed-table/flush/forget/monitor/prune-batches/restart/retry/table/
  work (partial), route:cache/list, schema:dump/wipe, serve, tinker/test.

  **No Tinker, no serve** — both are installed as separate packages in
  Laravel; Phare ships neither.

  **No-contracts** — `Contracts\Console\Application` has 1 method (call)
  and `Contracts\Console\Kernel` has 2 (handle/resolve) — minimal; no
  `Contracts\Console\Command` (20th no-contracts).

- 工数感 (Effort): **L** — core Application/Command/Kernel shape exists;
  gap is in the ~46 missing make:* and management commands, the missing IO
  surface (table/progress/listing), and the Kernel lifecycle methods. Large
  backfill, no structural blockers.

---

### Facades

- 現状 (Current) — Phare:
  16 facades in `src/Phare/Support/Facades/` (excluding `Facade.php` base):
  Application, Artisan, Auth, Broadcast, Cache, DB, DebugLogger, Event,
  Log, Request, Response, Sanctum, Security, Session, Sqids.

  - **`Phare\Support\Facades\Facade`** base class — Phalcon-CLEAN (zero
    `Phalcon\` refs). Standard `getFacadeAccessor()` + `__callStatic()`
    pattern. No Phalcon inheritance.

- 期待 (Expected) — Laravel 13:
  45 facades in `Illuminate\Support\Facades\`.

- 差分 (Gaps):

  **Phalcon leaks** — NONE. Facade base is Phalcon-CLEAN. Facades themselves
  are thin stubs — leaks live in the underlying services.

  **Missing facades (≈29)**

  | Missing | Notes |
  |---------|-------|
  | Blade | View/Blade compiler |
  | Bus | Job dispatcher |
  | Concurrency | L11 concurrent closures |
  | Config | Config repository |
  | Context | L11 context values |
  | Cookie | Cookie factory |
  | Crypt | Encrypter (C04 dual-stack — `app('encrypter')` returns Phalcon Crypt) |
  | Date | Carbon/Chronos factory |
  | Exceptions | L11 exception reporting |
  | File | Filesystem |
  | Gate | Authorization |
  | Hash | HashManager (C03 audited) |
  | Http | HTTP client |
  | Lang | Translator |
  | Mail | Mailer (D05 audited) |
  | MaintenanceMode | Down/up |
  | Notification | NotificationManager (D04) |
  | ParallelTesting | Test runner |
  | Password | PasswordBroker (C07) |
  | Pipeline | Pipeline facade |
  | Process | External processes |
  | Queue | QueueManager (D01) |
  | RateLimiter | RateLimiter (C06) |
  | Redirect | RedirectResponse factory |
  | Redis | Redis manager |
  | Route | Router facade |
  | Schedule | Scheduler (D06) |
  | Schema | SchemaBuilder (B05) |
  | Storage | Filesystem |
  | URL | URL generator |
  | Validator | Validator factory |
  | View | View factory |
  | Vite | Asset compilation |

  **Phare-only** (no Laravel equivalent): DebugLogger, Security (maps to
  Phare\\Security\\Xss), Sanctum (Laravel ships as a package facade),
  Sqids.

  **No-contracts** — `Phare\Contracts\Support\Facade` does not exist
  (21st no-contracts).

- 工数感 (Effort): **S-M** — Facade base is clean; each missing facade is
  a ~10-line file; mechanical backfill of ~29 files. Most are blocked on
  the underlying service being implemented first (Bus, Gate, Cookie, etc.).

---

### Helpers

- 現状 (Current) — Phare:
  `src/Phare/Support/helpers.php` (~4 075 tok): ~25 functions guarded
  by `function_exists` checks. Imports 5 Phalcon classes at file scope.

  - **Phare functions**: `array_any` (PHP 8.4 polyfill), `config`,
    `config_set_path`, `env`, `container`, `app`, `response`, `request`,
    `redirect`, `route`, `abort`, `view`, `asset`, `queue`, `event`,
    `report`, `info`, `logger`, `fake`, `encrypter`, `encrypt`, `decrypt`,
    `bcrypt`, `security`, `hash`, `hashStringWithSalt`, `dd`, `dump`
    (approximately — anatomy says 40+ entries).

- 期待 (Expected) — Laravel 13:
  64 Foundation helpers + 23 Support helpers = 87 total (though 3 are
  Laravel-Cloud-specific: `laravel_cloud`, `broadcast_if`, `broadcast_unless`).

- 差分 (Gaps):

  **Phalcon leaks**

  - **Helper-return-type leak ×2**:
    - `encrypter(): Crypt` — return type is `Phalcon\Encryption\Crypt`
      (the alias imported at line 7). Publishes Phalcon class in the
      global helper return type.
    - `security(): mixed` — returns `app('security')` which is bound to
      `Phalcon\Encryption\Security` in the DI container (C04 DI-slot
      leak). Typed `mixed` to hide it; but the actual return is Phalcon.
  - File-scope imports: `Phalcon\Config\Config`, `Phalcon\Di\Di`,
    `Phalcon\Encryption\Crypt`, `Phalcon\Support\Debug\Dump`,
    `Phalcon\Support\Helper\Str\Random` — five Phalcon classes
    imported in the global helpers file. Any call using these
    at runtime gets Phalcon objects.

  **Naming-defect family (2 instances in helpers.php)**

  - `hash(string $value): string` — returns `encrypter()->encryptBase64(...)`,
    i.e. an ENCRYPTED CIPHERTEXT, not a hash. Also collides with PHP's
    built-in `hash()`. C04 naming-defect first documented; this is the
    same function.
  - `bcrypt(string $value): string` — calls `security()->hash($value, $key)`
    where `security()` is `Phalcon\Encryption\Security` — uses Phalcon's
    Security `hash()` method, not Phare's `Hashing\BcryptHasher::make()`.
    C03 hashing subsystem is bypassed entirely; config `hashing.bcrypt.rounds`
    is not consulted.

  **Missing helpers (~60+ of Laravel's 87)**

  Foundation helpers absent: `__` (translation), `abort_if`, `abort_unless`,
  `action`, `app_path`, `auth`, `back`, `base_path`, `broadcast`,
  `broadcast_if`, `broadcast_unless`, `cache`, `config_path`, `context`,
  `cookie`, `csrf_field`, `csrf_token`, `database_path`, `defer`,
  `dispatch`, `dispatch_sync`, `lang_path`, `logs`, `method_field`, `mix`,
  `now`, `old`, `policy`, `precognitive`, `public_path`, `report_if`,
  `report_unless`, `rescue`, `resolve`, `resource_path`, `secure_asset`,
  `secure_url`, `session`, `storage_path`, `to_action`, `today`,
  `to_route`, `trans`, `trans_choice`, `uri`, `url`, `validator`, `vite`.

  Support helpers absent: `append_config`, `blank`, `class_basename`,
  `class_uses_recursive`, `e` (HTML escaping), `filled`, `fluent`,
  `literal`, `object_get`, `once`, `optional`, `preg_replace_array`,
  `retry`, `str`, `tap`, `throw_if`, `throw_unless`,
  `trait_uses_recursive`, `transform`, `with`.

  **Correctness defect** — `config()` calls `$config->path($key, $default)`;
  `path()` is a Phare-extension method. If the config repository is ever
  swapped for a Laravel-compatible one without `path()`, all `config()`
  calls break.

- 工数感 (Effort): **M** — fixing the named-defects (hash/bcrypt) is S;
  adding the missing ~60 helpers is mechanical M. Phalcon-return-type leaks
  (encrypter/security) are S fixes each but blocked on wiring the real
  Phare\Encryption\Encrypter (C04 dual-stack resolution needed).

---

### Translation

- 現状 (Current) — Phare:
  `src/Phare/Translation/Translator.php` (~1 295 tok): standalone class;
  zero Phalcon inheritance; 12 pub methods.
  `TranslationServiceProvider.php` (~667 tok): registers translator.

  - **`Phare\Translation\Translator`** (12 pub): addPath, choice,
    __construct, flush, get, getFallback, getLocale, has, setFallback,
    setLocale, trans, transChoice.

- 期待 (Expected) — Laravel 13:
  `Illuminate\Translation\Translator` (23 pub) +
  `Contracts\Translation\Translator` (4 methods: get/choice/addLines/
  setLocale). Supports JSON translation files, namespaced translations,
  `__()` global helper, plural-form selector.

- 差分 (Gaps):

  **Phalcon leaks** — NONE. Translator is Phalcon-CLEAN.

  **Missing (~11 of 23 public methods)**

  - `addJsonPath(string $path)` — JSON translation files (key-based
    single-file format) unsupported.
  - `addLines(array $lines, string $locale, string $namespace = '*')` —
    add in-memory lines; blocks service-provider `loadTranslationsFrom`.
  - `addNamespace(string $namespace, string $hint)` — namespaced packages
    cannot ship their own translations.
  - `determineLocalesUsing(callable $callback)` — dynamic locale
    resolution hook.
  - `getLoader(): Loader` — introspect the file loader.
  - `getSelector(): MessageSelector` — plural-form rule engine.
  - `handleMissingKeysUsing(callable $callback)` — missing-key handler
    (L10+).
  - `hasForLocale(string $key, string $locale): bool` — locale-specific
    key existence check.
  - `load(string $namespace, string $group, string $locale)` — explicit
    cache load.
  - `locale(string $locale): static` — fluent alias for `setLocale`.
  - `parseKey(string $key): array` — namespace::group.item parsing.
  - `setLoaded(array $loaded)` — inject pre-loaded translations.
  - `setSelector(MessageSelector $selector)` — swap plural-form engine.
  - `stringable(callable $handler)` — add Stringable cast handler.

  **Missing contract** — no `Phare\Contracts\Translation\Translator` (22nd
  no-contracts subsystem); Phare ships `trans()` / `transChoice()` on
  `Translator` directly rather than satisfying a contract.

  **`__()` global helper absent** — the most-used Laravel translation helper
  is missing; tracked in US-E06.

  **No namespace/package translations** — `addNamespace` / namespaced
  `file::group.key` lookup absent; any package shipping its own
  `lang/` directory cannot register translations.

  **JSON translations absent** — modern Laravel applications that use a
  single JSON file per locale (`lang/en.json`) are unsupported.

- 工数感 (Effort): **M** — core translator loop present and functional;
  gap is the plural-selector engine, JSON file loader, namespace system, and
  missing methods. MessageSelector pluralisation is the hard part; the rest
  is mechanical.

