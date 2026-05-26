# Audit — Area C: Auth, Sessions & Security

**Laravel 13 reference:** `/opt/laravel-framework` @ `13.2.0` (read-only)
**Phare package:** `phare/framework`, namespace `Phare\`, `src/Phare/`
**Method:** per-subsystem 1:1 public-API diff vs Laravel 13 + Wrapper Rule (§2) grep.
**Scope:** read-and-record only — no framework source edited.

---

### AuthManager

- Current — Phare:
  `Phare\Auth\AuthManager extends Phare\Support\Manager` (the generic
  multi-driver base in `src/Phare/Support/Manager.php`). Wired in
  `Providers\AuthServiceProvider` as **two** container singletons —
  `auth.manager` and `auth` (alias). The provider itself
  `implements Phalcon\Di\ServiceProviderInterface`, a contract-coupling
  leak (same shape as A07 `BladeViewProvider`).
  - **Own public surface (3):** `__construct(ContainerContract $app)`,
    `guard(?string $name = null): object` (just calls `driver($name)`),
    `getDefaultDriver(): string` (reads `auth.defaults.guard`,
    default `'web'`). `createDriver(string)`, `createSessionDriver`,
    `buildSessionGuardConfig`, `normalizeConfig`,
    `legacyDefaultGuardConfig` are all `protected`.
  - **Inherited from `Support\Manager` (7 public):** `driver(?string): mixed`,
    `extend(string, Closure): static`, `getDrivers(): array`,
    `getContainer(): ContainerContract`,
    `setContainer(ContainerContract): static`,
    `forgetDrivers(): static`, `__call(string, array): mixed` (forwards
    to the default driver). Driver instance cache lives on the base as
    `$drivers`, not Laravel's named `$guards`.
  - **What `createSessionDriver` returns:** a `Phare\Auth\Manager` —
    a separately-named class, NOT a `SessionGuard`, NOT a
    `StatefulGuard` implementation (there is no `StatefulGuard`
    contract in Phare). `Manager`'s ctor signature:
    `__construct(Phare\Contracts\Session\Session $session,
    Phalcon\Config\ConfigInterface $config,
    ?Phare\Events\Contracts\Dispatcher $events = null)` — see Phalcon
    leak ➀. Public methods: `user(): ?User`, `guest(): bool`,
    `attempt(array $credentials = []): bool`, `check(): bool`,
    `logout(): void`, `retrieveIdentifier()`, `login(User): bool`,
    `loginUsingId(int $id): User|\Phalcon\Mvc\ModelInterface`
    (leak ➁), `id(): int|string|null`, `validate(array): bool` — ~10
    methods. `retrieveUserBy*` are protected. No `setUser/hasUser/getUser`,
    no remember-me, no basic auth, no timebox, no Hasher injection
    (uses raw `password_verify`).
  - **No user provider abstraction at all.** `Manager::retrieveUserById`
    does `$class::findFirst($id)` against a class name read from
    `config['model']`. No `EloquentUserProvider`, no
    `DatabaseUserProvider`, no `GenericUser`, no
    `CreatesUserProviders` trait, no `Contracts\Auth\UserProvider`.
  - **Contracts shipped:** only `Phare\Contracts\Auth\Authenticatable`
    (4 methods: `getAuthIdentifier`, `getAuthPassword`,
    `getAuthIdentifierName`, `getAuthPasswordName` — but the latter
    two are `static` in Phare vs instance in Laravel; see §Gaps).
    `Phare\Auth\Authenticatable` (NOTE: same short name) is a **trait**
    that implements the contract for an Eloquent user. There is **no**
    `Contracts\Auth\Factory`, `Guard`, `StatefulGuard`, `SupportsBasicAuth`,
    `UserProvider`, `CanResetPassword`, `MustVerifyEmail`, `Recaller`.
  - **AuthenticationException:** `Phare\Auth\AuthenticationException
    extends \RuntimeException`, ctor only. No `$guards`/`$redirectTo`
    properties, no `guards()`/`redirectTo()` accessors — the Laravel
    L13 `Auth\AuthenticationException` carries both, and they are how
    `Middleware\Authenticate` decides where to redirect/JSON-401.
  - **Sibling auth subsystems present** (out of scope for this section,
    deferred): `src/Phare/Auth/Sanctum/` (token-style guard with
    `setUser`), `src/Phare/Auth/Passkeys/`, `src/Phare/Auth/Passwords/`,
    `src/Phare/Auth/Middleware/{Authenticate, EnsureRole}`. Listed only
    to flag that they bypass the (non-existent) `Guard`/`StatefulGuard`
    contracts entirely.

- Expected — Laravel 13:
  `Illuminate\Auth\AuthManager implements
  Illuminate\Contracts\Auth\Factory`, `use CreatesUserProviders`,
  `@mixin Guard|StatefulGuard` for IDE hinting.
  - **Public surface — AuthManager (14):** `__construct($app)`,
    `guard($name = null): Guard|StatefulGuard`, `createSessionDriver`,
    `createTokenDriver`, `getDefaultDriver`, `shouldUse($name)`,
    `setDefaultDriver($name)`, `viaRequest($driver, callable)`,
    `userResolver(): Closure`, `resolveUsersUsing(Closure)`,
    `extend($driver, Closure)`, `provider($name, Closure)`,
    `hasResolvedGuards(): bool`, `forgetGuards()`, `setApplication($app)`.
    Plus `__call` → default guard.
  - **CreatesUserProviders trait (2 public + 2 protected
    `createDatabaseUserProvider`/`createEloquentUserProvider`):**
    `createUserProvider($provider = null): ?UserProvider`,
    `getDefaultUserProvider(): string`.
  - **`Contracts\Auth\Factory` (2):** `guard($name = null)`,
    `shouldUse($name)`.
  - **`Contracts\Auth\Guard` (7):** `check()`, `guest()`, `user()`,
    `id()`, `validate(array $credentials = [])`, `hasUser()`,
    `setUser(Authenticatable $user)`.
  - **`Contracts\Auth\StatefulGuard` extends Guard (7 added):**
    `attempt(array, $remember = false)`, `once(array)`,
    `login(Authenticatable, $remember = false)`,
    `loginUsingId($id, $remember = false)`, `onceUsingId($id)`,
    `viaRemember(): bool`, `logout()`.
  - **`Contracts\Auth\UserProvider` (5):**
    `retrieveById`, `retrieveByToken`, `updateRememberToken`,
    `retrieveByCredentials`, `validateCredentials`,
    `rehashPasswordIfRequired`.
  - **SessionGuard (~31 public):** the StatefulGuard methods PLUS
    `basic`, `onceBasic`, `attemptWhen`, `hashPasswordForCookie`,
    `logoutCurrentDevice`, `logoutOtherDevices`, `attempting`,
    `getLastAttempted`, `getName`, `getRecallerName`, `viaRemember`,
    `setRememberDuration`, `getCookieJar`, `setCookieJar`,
    `getDispatcher`, `setDispatcher`, `getSession`, `getUser`,
    `setUser`, `getRequest`, `setRequest`, `getTimebox`.
  - **RequestGuard (3):** `user`, `validate(array)`, `setRequest`.
  - **Container bindings:** Laravel binds `auth`, `auth.driver`,
    `auth.password`, `auth.password.broker` — not `auth.manager`.
  - **AuthenticationException carries** `array $guards`,
    `?string $redirectTo`, accessors `guards()`/`redirectTo()` —
    consumed by `Middleware\Authenticate::unauthenticated()`.

- Gaps:
  - **Missing — AuthManager (10/14):** `createTokenDriver`,
    `shouldUse`, `setDefaultDriver`, `viaRequest`, `userResolver`,
    `resolveUsersUsing`, `provider`, `hasResolvedGuards`,
    `forgetGuards` (Phare has the analogous `forgetDrivers()` on the
    base — same intent, different name), `setApplication`. No
    `userResolver` means Gate/Request/Authenticatable can't share the
    "who's logged in" resolution — every consumer has to call
    `app('auth')->user()` directly (see `Container/Attributes/Auth.php`,
    `Authenticated.php`, `CurrentUser.php`).
  - **Missing — UserProvider abstraction wholesale:** no `UserProvider`
    contract, no `EloquentUserProvider`, no `DatabaseUserProvider`,
    no `GenericUser`, no `CreatesUserProviders`. `auth.providers.*`
    config is parsed in `AuthManager::buildSessionGuardConfig` for the
    sole purpose of extracting `model` — the `driver` field
    (`eloquent`/`database`) is read-but-ignored.
  - **Missing — Guard/StatefulGuard contracts:** Phare publishes no
    interface. Phare's `Manager::guard()` returns plain `object`;
    Laravel's returns `Guard|StatefulGuard`. Pattern-confirmed:
    the no-contracts pattern is now Area-wide
    (A06 Validation, A07 View, B06/B07 Pagination — and now
    C01 Auth). Record as type-mismatch, not missing-method.
  - **Missing — SessionGuard surface (~25/31):** the entire
    remember-me cookie path (`setCookieJar`/`getCookieJar`/
    `getRecallerName`/`setRememberDuration`/`viaRemember`/
    `hashPasswordForCookie`/Recaller); basic-auth
    (`basic`/`onceBasic`); the no-session `once`/`onceUsingId`;
    `attemptWhen`; `logoutCurrentDevice`/`logoutOtherDevices`
    + matching events `CurrentDeviceLogout`/`OtherDeviceLogout`;
    `attempting()` callback; `getLastAttempted`; `getName`;
    `getDispatcher`/`setDispatcher`; `getSession`; `getUser`/
    `setUser`/`hasUser`; `getRequest`/`setRequest`; `getTimebox`.
    Phare events present: `Attempting`, `Authenticated`, `Failed`,
    `Login`, `Logout`, `Validated` — i.e. 6/8 (missing
    `CurrentDeviceLogout`, `OtherDeviceLogout`).
  - **Missing — RequestGuard, TokenGuard:** no callback-based
    request guard (so `Auth::viaRequest()` couldn't work even if it
    were exposed), no token guard.
  - **Missing — bindings:** `auth.driver`, `auth.password`,
    `auth.password.broker`. Phare binds both `auth` and the
    non-Laravel `auth.manager`.
  - **Missing — AuthenticationException context:** no `$guards`
    array, no `$redirectTo`, no `guards()`/`redirectTo()`. Consumers
    that want guard-aware redirect must subclass or rebuild.
  - **Type mismatch — `Authenticatable` contract:**
    `getAuthIdentifierName()` and `getAuthPasswordName()` are
    declared `static` in Phare's interface but **instance** in
    Laravel's `Illuminate\Contracts\Auth\Authenticatable`. Plus
    Laravel adds `getRememberToken`, `setRememberToken`,
    `getRememberTokenName`, `getAuthPasswordName` (instance),
    `getAuthIdentifierForBroadcasting` — 5 methods missing /
    re-shaped. Static-vs-instance is a porting hazard (would-be 6th
    same-name/opposite-shape entry; running list:
    B01 `create`, B02 `paginate`, B05 `rollback`, B06 `for`,
    B07 `simplePaginate`).
  - **Behavioural — `Manager::attempt(array): bool`** omits
    Laravel's `$remember` second argument. **Silent-arg-drop
    defect family** (3rd occurrence after B02 `update()`,
    B03 `BelongsToMany::attach/detach/sync/toggle` pivot ops).
    Same for `login(User): bool` (Laravel: `login($user, $remember=false): void`)
    and `loginUsingId(int): ...` (Laravel: `loginUsingId($id, $remember=false)`).
    The `$remember` parameter is the entire remember-me protocol
    in Laravel — dropping it makes the protocol unreachable.
  - **Behavioural — `Manager::login(User): bool`** returns `bool`
    where Laravel returns `void`; `Manager::logout(): void` matches
    Laravel; `Manager::attempt` returns `bool` and matches.
  - **Behavioural — `loginUsingId(int $id)`** narrows the id type
    to `int`; Laravel accepts `mixed` (UUIDs, ULIDs, composite
    string keys — all common). Returns `User|\Phalcon\Mvc\ModelInterface`
    where Laravel returns `Authenticatable|false`. Same union-with-Phalcon
    leak shape as B03 `HasRelationships` factory methods.
  - **Behavioural — no Hasher / no Timebox.**
    `Manager::retrieveUserByCredentials()` calls `password_verify($hash,
    $user->getAuthPassword())` directly, in a non-constant-time call
    path. Laravel `SessionGuard::hasValidCredentials()` runs inside
    `Timebox::call(..., $this->timeboxDuration)` (default 200000μs) to
    defeat user-enumeration timing attacks, and delegates the actual
    verify to `UserProvider::validateCredentials($user, $creds)` →
    `Hash::check($plain, $hashed)`. Phare has zero of this — direct
    `password_verify`, no Timebox, no Hasher injection, no
    `rehashPasswordIfRequired` (the L11+ silent re-hash on login).
    Security defect, not just a parity gap.
  - **Behavioural — `getDefaultDriver()` always falls back to `'web'`.**
    Reads `config('auth.defaults.guard', 'web')`. The base
    `Support\Manager::driver()` is happy to throw on null, but the
    string fallback masks "config not loaded" — should explicitly
    throw, matching Laravel's
    `$this->app['config']['auth.defaults.guard']` (un-defaulted, null
    bubbles up as an "Auth guard [null] is not defined." error).
  - **Behavioural — `__call` on `AuthManager` forwards to the default
    guard.** Matches Laravel. But because Phare's default guard
    (`Manager`) lacks `setUser`/`hasUser`/`getUser`,
    `$auth->setUser($u)` (used by `Sanctum/Sanctum.php:44`) BLOWS
    UP unless the resolved guard happens to be `SanctumGuard`. The
    Sanctum integration only works when the default guard is the
    Sanctum one — a hidden coupling, not declared in any contract.
  - **Behavioural — `auth.providers.{name}.driver` parsed but ignored.**
    Confirmed: `buildSessionGuardConfig` extracts `model` only.
    Whether you configure `'driver' => 'eloquent'` or
    `'driver' => 'database'`, the runtime path is identical (Phalcon
    `Model::findFirst`). **Silent-arg-drop / stub-defect** at config
    level: shape matches Laravel, behaviour doesn't.
  - **Behavioural — `createDriver` allows the "legacy" path** where
    only `auth.model` is set (no `auth.guards.*` block); the
    `legacyDefaultGuardConfig` fabricates `['driver' => 'session']`.
    Laravel has no such fallback — config is required.

- Phalcon leaks (§2):
  - ➀ **`Phare\Auth\Manager::__construct(Session, Phalcon\Config\ConfigInterface, ?Dispatcher)`**
    — public-signature leak. The session-guard implementation publishes
    a raw Phalcon config interface as its second constructor argument.
    Same defect class as the **published-dependency** leak first
    surfaced in B05 (`Blueprint::toSql(AbstractPdo)`,
    `SchemaBuilder::__construct(AbstractPdo)`,
    `Migrator::__construct(AbstractPdo)`) and confirmed in B06
    (`SeederTable::__construct(AbstractPdo)`) — but here the leaked
    dependency is `Phalcon\Config\ConfigInterface`, not `AbstractPdo`.
    Phare has a `Phare\Config\Repository` (per US-E03 PRD) that should
    be the wrapped type instead.
  - ➁ **`Phare\Auth\Manager::loginUsingId(int $id): User|\Phalcon\Mvc\ModelInterface`**
    — public-signature **union return leak**. Same shape as B03
    `HasRelationships::hasOne/hasMany/belongsTo/hasOneThrough`
    return unions with `\Phalcon\Mvc\Model\Relation`. The user-facing
    `Authenticatable` half of the union is irrelevant if a caller
    type-narrows on the Phalcon side.
  - ➂ **`Phare\Providers\AuthServiceProvider implements
    Phalcon\Di\ServiceProviderInterface`** — published-contract
    Phalcon coupling on the provider class. Same shape as A07's
    `BladeViewProvider`. Note: AuthManager itself implements no
    contract (`Phare\Contracts\Auth\Factory` does not exist),
    so the "Contracts\... extends Phalcon\..." subclass of leak
    (A02 Kernel, A05 Response, B02 Builder) does NOT apply here —
    the leak is at the provider boundary, not the contract.
  - **Internal coupling (non-public, recorded not counted):**
    `AuthManager::normalizeConfig()` checks `instanceof
    Phalcon\Config\Config` and uses `Phalcon\Config\Config` directly
    in `buildSessionGuardConfig` to build the returned `Config`.
    `Manager::__construct` types the field as Phalcon's interface
    internally too.
  - **NO inheritance/contract leak** on `AuthManager` itself (extends
    `Phare\Support\Manager`, which is Phalcon-clean) nor on `Manager`
    (extends nothing). This subsystem's leak signature is **published
    dependency + provider contract + union return** — three leak
    forms, none of them inheritance. Contrast with B01/B02/B03 which
    were all `extends Phalcon\…` structural.

- Effort: **L**:
  - Architecture inversion in addition to backfill. Closing the gap
    needs (1) a real `Contracts\Auth\Factory`/`Guard`/`StatefulGuard`/
    `UserProvider` set, (2) splitting `Phare\Auth\Manager` into
    `SessionGuard implements StatefulGuard` + `UserProvider`
    implementations (`EloquentUserProvider` / `DatabaseUserProvider`),
    (3) wiring `userResolver`/`shouldUse`/`viaRequest`/`provider` on
    AuthManager, (4) the entire remember-me cookie protocol +
    `Recaller`, (5) Timebox + Hasher injection (cross-cuts US-C03
    Hashing), (6) wrapping `Phalcon\Config\ConfigInterface` behind
    `Phare\Config\Repository` for the `Manager` ctor (shared with
    US-E03 Config), (7) re-shaping `Authenticatable` (static→instance,
    remember-token methods), (8) wrapping the provider boundary so
    `AuthServiceProvider` no longer publishes a Phalcon contract.
    Partly blocked on Hashing (C03), Session-auth (C02 — overlaps
    `Manager`'s login/logout/attempt), Encrypter (C04 — needed for
    Recaller cookie payload), and Config (E03 — needed to retire the
    `ConfigInterface` leak).

---

### Session Auth (login / logout / attempt / remember / viaRemember)

C01 catalogued the **factory** side — `AuthManager` and the missing
`Factory`/`Guard`/`StatefulGuard`/`UserProvider` contracts. This
section drills into the **session-auth runtime**: the
`Phare\Auth\Manager` instance that `AuthManager::createSessionDriver`
returns, the auth flow itself (login / logout / attempt / remember /
viaRemember), and the session integration that backs it
(`Phare\Session\SessionManager`, `Phare\Session\SessionStoreManager`,
`Phare\Contracts\Session\Session`).

- Current — Phare:

  - **`Phare\Auth\Manager`** (`src/Phare/Auth/Manager.php`, 252 LOC):
    the only session-guard implementation. Implements **no contract**
    (`Contracts\Auth\Guard`/`StatefulGuard` do not exist in Phare —
    inherited from C01). Constructor takes
    `Session $session, Phalcon\Config\ConfigInterface $config,
    ?Phare\Events\Contracts\Dispatcher $events = null` — see Phalcon
    leak ➊.
  - **Public surface (10):**
    `user(): ?User`,
    `guest(): bool`,
    `attempt(array $credentials = []): bool`,
    `check(): bool`,
    `logout(): void`,
    `retrieveIdentifier()`,
    `login(User $user): bool`,
    `loginUsingId(int $id): User|\Phalcon\Mvc\ModelInterface`
    (leak ➋),
    `id(): int|string|null`,
    `validate(array $credentials = []): bool`. Helpers
    `retrieveUserById/ByIdentifier/ByCredentials`,
    `regenerateSessionId`, `sessionKey`, `modelClass`,
    `dispatchEvent` are protected/private.
  - **Auth flow as wired today (login):**
    `login($user)` → `regenerateSessionId()` (Phalcon `$session->regenerateId()`)
    → `$session->set($this->sessionKey(), $user->getAuthIdentifier())`
    → set `$user`, clear `loggedOut`, mark `authEventDispatched=true`,
    dispatch `Login($user)`, **return `true`**. Note the return:
    Laravel `SessionGuard::login(): void`; Phare returns `bool`.
  - **Auth flow as wired today (logout):**
    `logout()` → resolve `user()`, null `$user`, set `loggedOut=true`,
    clear `authEventDispatched`,
    **`$this->session->destroy()`** (destroys the ENTIRE session — see
    Behavioural §1), dispatch `Logout($user)`. No remember-me cookie
    cleanup, no recaller cycle, no per-device variant.
  - **Auth flow as wired today (attempt):**
    `attempt($credentials)` → dispatch `Attempting($credentials)`
    (note: ctor missing `$guard` name and `$remember`; see §events) →
    `retrieveUserByCredentials($credentials)` (loads the configured
    model by identifier, then runs **raw `password_verify($hash,
    $user->getAuthPassword())`** — no Hasher injection, no Timebox; see
    Behavioural §2) → on success dispatch `Validated($user, $credentials)`
    and return `login($user)`; on failure dispatch
    `Failed($user, $credentials)` and return `false`.
  - **Session key** comes from `$this->config->session_id` (a single
    runtime-bound value set by `AuthManager::buildSessionGuardConfig`
    to `auth.session_id` falling back to `"auth.{$guardName}"`).
    There is **no per-class hash** equivalent to Laravel's
    `'login_'.$this->name.'_'.sha1(static::class)` — two guards
    pointed at the same session can race on the same key if
    `auth.session_id` is set globally.
  - **`Phare\Auth\Manager` has NO** `setUser`, `getUser`, `hasUser`,
    `forgetUser`, `authenticate`, `getProvider`, `setProvider`,
    `getName`, `getRecallerName`, `getLastAttempted`, `getSession`,
    `getRequest`, `setRequest`, `getCookieJar`, `setCookieJar`,
    `getDispatcher`, `setDispatcher`, `getTimebox`,
    `setRememberDuration`, `hashPasswordForCookie`, `attempting`,
    `viaRemember`, `attemptWhen`, `once`, `onceUsingId`, `basic`,
    `onceBasic`, `logoutCurrentDevice`, `logoutOtherDevices` —
    confirmed by grep.
  - **`Phare\Auth\Events\*`:** ships
    `Attempting`, `Authenticated`, `Failed`, `Login`, `Logout`,
    `Validated` (6 of Laravel's 8). Missing
    `CurrentDeviceLogout`, `OtherDeviceLogout`. Constructor shapes
    are slimmer than Laravel's: e.g. `Attempting(array $credentials)`
    vs Laravel `Attempting(string $guard, array $credentials,
    bool $remember = false)`; `Login(User $user)` vs Laravel
    `Login(string $guard, Authenticatable $user, bool $remember)`;
    every Phare event drops the `$guard` name parameter so
    multi-guard listeners can't disambiguate.

  - **Session integration — `Phare\Session\SessionManager`**
    (`src/Phare/Session/SessionManager.php`, 79 LOC) **`extends
    Phalcon\Session\Manager implements Phare\Contracts\Session\Session`**.
    Adds 6 Laravel-shaped helpers on top of Phalcon: `pull`, `put`,
    `add`, `clear` (destroy+start), `replace`, `forget`. Everything
    else (`get/set/remove/has/destroy/regenerateId/start/getId/setId/
    getName/setName/getId/setHandler/getHandler/exists`) is the
    Phalcon adapter API published verbatim. Total public surface
    visible to a session-store consumer: **~6 added + ~12 inherited
    Phalcon methods = 18** vs Laravel `Session\Store`'s **54 public
    methods**.
  - **Session integration — `Phare\Contracts\Session\Session`**:
    `interface Session extends Phalcon\Session\ManagerInterface` —
    declares only `pull/put/add/forget/clear/replace`. **Contract
    leak** (leak ➌): the published Phare session contract `extends`
    a `Phalcon\…` interface — same defect class as
    `Contracts\Http\Kernel` (A02), `Contracts\Http\Response` (A05),
    `Eloquent\BuilderInterface` (B02). Cross-ref running list in
    cerebrum.
  - **Session integration — `Phare\Session\SessionStoreManager`**
    (`src/Phare/Session/SessionStoreManager.php`, 112 LOC): registry
    bound to `session.manager`. **Public surface (2):**
    `store(?string $name = null): SessionManager`,
    `getDefaultStore(): ?string`. Resolves drivers via switch:
    `file` → `Phalcon\Session\Adapter\Stream`, `redis` →
    `Phalcon\Session\Adapter\Redis` (or `RedisCluster`). NO
    `database`/`cookie`/`apc`/`memcached`/`null`/`array`/`dynamodb`
    drivers; NO `extend(string, Closure)` for custom drivers; NO
    `getSessionConfig`/`setDefaultDriver`/`shouldBlock`/`blockDriver`/
    `defaultRouteBlockLockSeconds`/`defaultRouteBlockWaitSeconds`.
  - **Provider wiring** — `Phare\Providers\SessionProvider`
    `implements Phalcon\Di\ServiceProviderInterface` (provider-boundary
    Phalcon coupling — same shape as A07 `BladeViewProvider`, C01
    `AuthServiceProvider`; recorded as leak ➍, not double-counted in
    §workload). Binds two services: `session.manager` →
    `SessionStoreManager` (multi-store), `session` → a single
    `SessionManager` instance built directly from
    `config('session.driver')` (so `session` and `session.manager`
    can return different instances; this is a wiring divergence from
    Laravel where the `session` binding is the SessionManager itself
    and `session.store` is the default store).

- Expected — Laravel 13:

  - **`Illuminate\Auth\SessionGuard implements StatefulGuard,
    SupportsBasicAuth`** with `use GuardHelpers, Macroable`
    (1039 LOC). Constructor:
    `__construct(string $name, UserProvider $provider, Session
    $session, ?Request $request = null, ?Timebox $timebox = null,
    bool $rehashOnLogin = true, int $timeboxDuration = 200000,
    ?string $hashKey = null)` — accepts a `UserProvider` (Phare:
    none — the model class is read from config inside the guard),
    a `Timebox` (Phare: none), a `Request` (Phare: none — the
    recaller cookie path doesn't exist), and a `$hashKey` for the
    recaller HMAC.
  - **`SessionGuard` public surface (~31, listed in C01).** The
    auth flow proper:
    - `attempt(array $credentials = [], $remember = false): bool`
      — wraps the credential-check in
      `Timebox::call($callback, $this->timeboxDuration)`
      (200000 µs default) to defeat user-enumeration timing
      attacks; on success runs `rehashPasswordIfRequired` then
      `login($user, $remember)` then `$timebox->returnEarly()`.
    - `attemptWhen(array $credentials = [], $callbacks = null,
      $remember = false): bool` — same flow but applies a callback
      array allowing additional gates after credential check.
    - `validate(array $credentials = []): bool` — Timeboxed
      `provider->retrieveByCredentials` + `hasValidCredentials`,
      stores `$lastAttempted`.
    - `login(AuthenticatableContract $user, $remember = false): void`
      — `updateSession(id)` → `session->put(getName(), id)` +
      `session->regenerate(true)`. If `$remember`:
      `ensureRememberTokenIsSet` →
      `provider->updateRememberToken($user, Str::random(60))` →
      `queueRecallerCookie($user)` which queues an HMAC-signed
      `id|token|hashPasswordForCookie(authPassword)` cookie on the
      cookie jar. Fires `Login($name, $user, $remember)`. Sets the
      cached `$user` via `setUser($user)`.
    - `loginUsingId(mixed $id, $remember = false):
      Authenticatable|false` — accepts arbitrary id types (UUID/ULID).
    - `once(array $credentials = []): bool`,
      `onceUsingId(mixed $id): Authenticatable|false` — login
      without touching the session or queuing a remember cookie.
    - `logout(): void` — `clearUserDataFromStorage()` removes
      `session->remove(getName())` (only the auth key — preserves
      flash/CSRF/other session state), unqueues the recaller
      cookie, then queues a `forget` cookie if one was sent.
      Cycles the remember token if the user had one, dispatches
      `Logout($name, $user)`, nulls `$user`, sets `loggedOut`.
    - `logoutCurrentDevice(): void` — same storage clear but
      does NOT cycle the remember token (keep this device's recaller).
      Fires `CurrentDeviceLogout($name, $user)`.
    - `logoutOtherDevices(string $password): ?Authenticatable` —
      uses `Hash::check($password, user->getAuthPassword())` + force
      `provider->rehashPasswordIfRequired(force: true)` to invalidate
      sibling sessions (only meaningful with `AuthenticateSession`
      middleware enabled). Fires `OtherDeviceLogout($name, $user)`.
    - `basic(string $field = 'email', array $extraConditions = [])`
      / `onceBasic(...)` — HTTP Basic Auth integration via
      `Symfony\…\UnauthorizedHttpException`.
    - `attempting(callable $callback): void` — register listener for
      `Events\Attempting`.
    - `viaRemember(): bool` — flag set when `user()` was resolved via
      the recaller cookie path; lets the application require
      stronger auth for "remembered" sessions.
  - **`GuardHelpers` trait** (124 LOC): `authenticate()`, `hasUser()`,
    `check()`, `guest()`, `id()`, `setUser($user)`, `forgetUser()`,
    `getProvider()`, `setProvider(UserProvider)` — the per-guard
    shared API.
  - **`Auth\Recaller`** (95 LOC): tiny value object parsing the
    `id|token|hash` cookie payload. Methods: `id()`, `token()`,
    `hash()`, `valid()`, `segments()`. Storage path inside
    `SessionGuard`: `recaller()` reads the cookie from `$request`,
    `userFromRecaller(Recaller)` calls
    `provider->retrieveByToken($id, $token)` and sets
    `$viaRemember = ! is_null($user)`.
  - **`Contracts\Session\Session`** (213 LOC, **26 public methods**):
    `getName/setName/getId/setId`, `start/save`, `all/exists/has/
    get/pull/put/flash/token/regenerateToken/remove/forget/flush/
    invalidate/regenerate/migrate/isStarted/previousUrl/
    setPreviousUrl/getHandler/handlerNeedsRequest/setRequestOnHandler`.
    `Illuminate\Session\Store` (the concrete) ships **54 public**
    including the flash family
    (`flash/now/reflash/keep/flashInput/getOldInput/hasOldInput`),
    the array-helpers (`only/except/missing/hasAny`), `increment`,
    `decrement`, `push`, `remember`, `cache`, `passwordConfirmed`,
    `previousUri/setPreviousRoute/previousRoute`, `isValidId`,
    `setExists`, `replace`. Macroable. No `add`/`clear` (Phare-only).
  - **`Illuminate\Session\SessionManager extends Support\Manager`**
    (289 LOC): exposes `getDefaultDriver`, `setDefaultDriver`,
    `getSessionConfig`, `shouldBlock`, `blockDriver`,
    `defaultRouteBlockLockSeconds`, `defaultRouteBlockWaitSeconds`,
    plus protected drivers `null/array/cookie/file/native/database/
    apc/memcached/redis/dynamodb` (10 drivers) and a `buildSession`
    factory that switches on `session.encrypt` to wrap the handler
    in `EncryptedStore`.
  - **Container bindings:** `session` →
    `Illuminate\Session\SessionManager`, `session.store` → the
    default `Store` instance, `auth` → AuthManager,
    `auth.driver` → `Auth::guard()`. Phare binds `session` to a
    single SessionManager instance and `session.manager` to
    `SessionStoreManager` — the inverse of Laravel's binding shape.

- Gaps:

  - **Missing — `Manager` (session-guard) surface (~21/31):** the
    full SessionGuard list — `attemptWhen`, `once`, `onceUsingId`,
    `basic`, `onceBasic`, `logoutCurrentDevice`, `logoutOtherDevices`,
    `attempting`, `viaRemember`, `getLastAttempted`, `getName`,
    `getRecallerName`, `setRememberDuration`, `hashPasswordForCookie`,
    `getCookieJar`/`setCookieJar`, `getDispatcher`/`setDispatcher`,
    `getSession`, `getRequest`/`setRequest`, `getTimebox`. Combined
    with the missing **`GuardHelpers` trait** surface
    (`authenticate`, `hasUser`, `setUser`, `forgetUser`,
    `getProvider`, `setProvider`) the guard is missing **the entire
    user-resolver/provider/cookie/timebox/basic/once axis**.
  - **Missing — Recaller / remember-me protocol wholesale:**
    no `Auth\Recaller`, no `cookie` jar dependency, no
    `recaller()`/`userFromRecaller()`/`createRecaller()`/
    `queueRecallerCookie()`/`ensureRememberTokenIsSet()`/
    `cycleRememberToken()`/`hashPasswordForCookie()`/
    `getRecallerName()`/`setRememberDuration()`/`viaRemember()`.
    Continuation of C01: `attempt/login/loginUsingId` already drop
    the `$remember` parameter (silent-arg-drop, 3rd instance), so
    the protocol is **structurally unreachable** even before the
    component classes are missing. Cross-cuts US-C04 (Encrypter —
    Laravel's cookie jar signs/encrypts recaller payloads via the
    encrypter) and US-C03 (Hashing — `hashPasswordForCookie` uses
    `hash_hmac`).
  - **Missing — events (2/8):** `CurrentDeviceLogout`,
    `OtherDeviceLogout`. Existing event ctors are slimmer than
    Laravel's — every Phare auth event drops the `$guard` name and
    `Attempting`/`Login` drop `$remember`. Multi-guard listeners
    cannot disambiguate which guard fired the event without
    rebuilding the event classes.
  - **Missing — UserProvider abstraction in the auth flow itself:**
    inherited from C01, but re-emphasised here because every method
    on `Manager` (`retrieveUserById`, `retrieveUserByIdentifier`,
    `retrieveUserByCredentials`) inlines `$class::findFirst(...)`
    against a class name from `$this->config->model`. Closing C01's
    `EloquentUserProvider`/`DatabaseUserProvider`/`UserProvider`
    gap eliminates **all three** protected helpers here in one move.
    No `retrieveByToken` / `updateRememberToken` /
    `rehashPasswordIfRequired` even as private helpers.
  - **Missing — `Session\Store` surface (~36/54):** the **flash
    family** (`flash`, `now`, `reflash`, `keep`, `flashInput`,
    `hasOldInput`, `getOldInput`, `ageFlashData`) — the entire
    flash-data mechanism is absent. This is what `with()->withErrors()`
    and the redirect-back-with-errors flow rely on (also called out
    in A05 Response — no `RedirectResponse::withInput`/`withErrors`
    — and A06 FormRequest — no redirect-back-with-errors flow).
    Also missing: `token`/`regenerateToken` (CSRF token in session —
    cross-cuts US-C05 CSRF), `invalidate`, `migrate`, `previousUrl`/
    `setPreviousUrl`, `only`/`except`/`missing`/`hasAny`,
    `increment`/`decrement`/`push`/`remember`,
    `passwordConfirmed`, `isValidId`/`setExists`. Phare's
    `SessionManager::clear()` does `destroy+start` — Laravel
    semantics for "clear all data" use `flush()` instead and keep
    the same session id.
  - **Missing — `SessionStoreManager` driver coverage (7/10):**
    `database`, `cookie`, `apc`, `memcached`, `null`, `array`,
    `dynamodb`. No `extend(string, Closure)` so applications can't
    register custom session drivers; no `EncryptedStore` wrapping
    even when `session.encrypt` is true (NEW correctness gap:
    Phare honours no `encrypt` setting at all — the Phalcon
    `Redis`/`Stream` adapters provide no encryption).
  - **Missing — request-block configuration:** no `shouldBlock`,
    `blockDriver`, `defaultRouteBlockLockSeconds`,
    `defaultRouteBlockWaitSeconds`. Laravel's `StartSession`
    middleware uses these to serialise concurrent requests for the
    same session id (a security/race-condition mitigation).
  - **Missing — `Auth\AuthenticateSession` middleware** (the partner
    that powers `logoutOtherDevices`'s rehash). Phare has only
    `Auth\Middleware\Authenticate` (a thin `Auth::check()` gate)
    and `Auth\Middleware\EnsureRole`. No
    `Authenticate::redirectTo`/`Authenticate::shouldUseGuards`
    introspection of the `AuthenticationException::$guards` path —
    inherited from C01.

  - **Type mismatch — `Manager::login(User): bool`** returns `bool`
    where Laravel `SessionGuard::login(...): void` returns void.
    Same name, opposite return shape — adds a **7th** "same-name /
    opposite-shape" porting hazard entry (running list:
    B01 `create`, B02 `paginate`, B05 `rollback`, B06 `for`,
    B07 `simplePaginate`, C01 `Authenticatable::getAuthIdentifierName/
    getAuthPasswordName` static-vs-instance, now C02 `login`
    bool-vs-void).
  - **Type mismatch — `Manager::attempt(array): bool`** drops the
    `$remember` parameter; same for `login(User): bool` and
    `loginUsingId(int): User|ModelInterface` — silent-arg-drop /
    Phalcon-union-return (already counted in leak ➋).
  - **Type mismatch — `Manager::loginUsingId(int $id)`**: `int`
    narrows from Laravel `mixed`, so UUID/ULID/composite-string
    keys cannot log in. Recurs from C01.
  - **Type mismatch — `Manager::retrieveIdentifier(): mixed`**
    (un-typed) — Laravel has no equivalent public method; the
    closest is `id()` (typed `int|string|null`). Recording as an
    extra non-Laravel public method to flag for removal, not
    parity.
  - **Type mismatch — `SessionManager` published surface untyped.**
    `pull/put/add/clear/replace/forget` declare no parameter types
    or return types (e.g. `public function put($key, $value)`). The
    `Session` contract is similarly untyped. Laravel's `Store` is
    fully typed and the `Contracts\Session\Session` interface uses
    PHPDoc-typed signatures via docblocks (mostly untyped for back
    compat too, but the concrete is typed). Record as inherited
    type-mismatch — typing the contract is a one-shot fix.

  - **Behavioural §1 — `logout()` DESTROYS the entire session.**
    `$this->session->destroy()` (Phalcon) wipes ALL session keys
    plus the storage entry. Laravel `clearUserDataFromStorage()`
    calls `$session->remove($this->getName())` — only the auth key.
    Phare's logout therefore kills the CSRF token, flash data,
    `previousUrl`, queued cookies, anything stored under any other
    guard, and any application-stored session value. NEW
    correctness defect. Cross-references: this is the **logout
    blast-radius defect** — flag for US-S01 synthesis as its own
    category.
  - **Behavioural §2 — credential verification is non-Timeboxed
    and inlines `password_verify`.**
    `Manager::retrieveUserByCredentials()` runs
    `password_verify($credentials[$passwordField], $user->getAuthPassword())`
    directly. No `Timebox::call(..., 200000)` wrapper, no
    `Hasher::check`, no `rehashPasswordIfRequired`. Three security
    deltas vs Laravel:
      (a) user-enumeration timing attack — login response time
          depends on whether `retrieveUserByIdentifier` returned
          a row (short-circuit when null vs running `password_verify`
          when not);
      (b) hash-algorithm upgrade is impossible — no silent re-hash
          on successful login, so an app that bumps `PASSWORD_BCRYPT`
          to `PASSWORD_ARGON2ID` can never rotate stored hashes;
      (c) Hasher is not pluggable — `password_verify` is hardcoded,
          ignoring whatever `hash.driver` config the app sets. The
          Hashing audit (US-C03) will surface this from the other
          direction.
    Recorded as a **security defect**, not just a parity gap.
  - **Behavioural §3 — session key collision risk.** Phare's
    `sessionKey()` returns `$this->config->session_id`, which
    `buildSessionGuardConfig` sets to **`auth.session_id` if set
    globally**, else `"auth.{$guardName}"`. If the app legacy-sets
    `auth.session_id = 'auth.user'` (a common Phare-era convention),
    every guard the app declares writes its user-id to the **same**
    key. Switching guards in a multi-guard app silently overwrites
    the previous guard's session entry. Laravel's
    `'login_'.$name.'_'.sha1(static::class)` guarantees per-guard
    isolation. Worth a callout in US-S01 alongside the running
    same-name-different-meaning hazards.
  - **Behavioural §4 — `login()` regenerates the session id but
    does NOT migrate.** Phare calls `$session->regenerateId()`
    only; Laravel calls `session->regenerate(true)` (the `$destroy
    = true` form) which deletes the OLD session-handler row.
    Without the destroy flag, the previous session blob remains
    readable by anyone still holding the old session id — a
    **session-fixation mitigation gap**. Same severity as §2 but
    a separate root cause (the underlying Phalcon
    `Phalcon\Session\Manager::regenerateId(bool $deleteOldSession
    = true)` accepts the flag — Phare's call site omits it).
  - **Behavioural §5 — `user()` event-dispatch idempotency.**
    `Authenticated` fires once per request via the
    `$authEventDispatched` latch. Laravel fires `Authenticated` on
    every `user()` resolve path (initial id load AND recaller path
    AND `setUser`). Phare's single-fire latch is closer to
    correct for app code, but listeners that depend on multiple
    dispatches per request (e.g. observability counters) will
    silently miss events. Record as a behavioural divergence,
    direction-of-correctness debatable, not a defect.
  - **Behavioural §6 — `attempt` never sets `$lastAttempted`.**
    `Manager` has no `$lastAttempted` field; calling
    `getLastAttempted()` is impossible (the method doesn't exist).
    Laravel exposes the last user attempted regardless of outcome
    so a failed-login-handler can inspect the row. Inherited
    missing-method, restated here because it changes the shape of
    the `Attempting`/`Failed`/`Validated` event flow.
  - **Behavioural §7 — `SessionStoreManager::store()` calls
    `$session->start()` eagerly at resolve-time.** Laravel defers
    session start to the `StartSession` middleware. Phare's eager
    start means: any container `make('session.manager')`/`store()`
    boots a session (writes a cookie, locks the handler) even in
    contexts that have no HTTP request (queue workers, scheduler,
    console commands). NEW behavioural defect — flag for US-S01.
  - **Behavioural §8 — `SessionStoreManager` and `session` binding
    return DIFFERENT instances.** `session.manager` →
    `SessionStoreManager` builds one `SessionManager` per
    `store($name)` call; `session` →
    `SessionProvider::register` builds a separate single
    `SessionManager` from `config('session.driver')`. The
    multi-store registry never knows about the default `session`
    instance and vice versa — calling `app('session.manager')->store()`
    after `app('session')` has been resolved yields two parallel
    session handlers writing to the same backing store. Wiring
    divergence vs Laravel where `session` and the multi-store
    manager are the same object.

- Phalcon leaks (§2):

  - ➊ **`Phare\Auth\Manager::__construct(Session, Phalcon\Config\ConfigInterface, ?Dispatcher)`**
    — published-dependency leak (already counted in C01 as leak ➀
    on the same constructor; re-listed here for completeness — the
    auth-flow root surface still publishes a Phalcon config
    interface). Fix shared with E03 Config (wrap as
    `Phare\Config\Repository`).
  - ➋ **`Phare\Auth\Manager::loginUsingId(int $id):
    User|\Phalcon\Mvc\ModelInterface`** — public-signature union
    return leak (already counted in C01 as leak ➁). Re-listed
    because it's part of the auth-flow surface; not counted again
    in §workload.
  - ➌ **`Phare\Contracts\Session\Session extends
    Phalcon\Session\ManagerInterface`** — **contract leak**, NEW in
    C02. The published session contract `extends` a Phalcon
    interface, so any consumer typing against the contract pulls
    in the entire `Phalcon\Session\ManagerInterface` surface
    (`getId/setId/getName/setName/start/destroy/regenerateId/exists/
    setHandler/getHandler/setOptions/getOptions`). Same defect
    class as A02 (`Contracts\Http\Kernel`), A05
    (`Contracts\Http\Response`), B02 (`Eloquent\BuilderInterface`).
    Cerebrum's contract-leak severity ranking applies — this is
    HIGHER severity than the inheritance leak ➍ below.
  - ➍ **`Phare\Session\SessionManager extends Phalcon\Session\Manager`**
    — **inheritance leak**. Same shape as B01 Model / B02 Builder /
    B03 Relation, but on the session subsystem. Every Phalcon
    `Manager` method (`start/destroy/regenerateId/setAdapter/
    getAdapter/exists/has/get/set/remove/getId/setId/getName/
    setName/setOptions/getOptions/registerHandler/getHandler`) is
    published verbatim on `Phare\Session\SessionManager`. Pair
    with ➌ — both must be removed together to retire the Phalcon
    coupling on the session-store side.
  - ➎ **`Phare\Providers\SessionProvider implements
    Phalcon\Di\ServiceProviderInterface`** — provider-boundary
    contract coupling (recurs from A07 `BladeViewProvider`, C01
    `AuthServiceProvider`). Recorded, not counted as a separate
    leak class — the running list of provider-boundary leaks is
    its own item in the synthesis.

  - **Internal coupling (non-public, recorded not counted):**
    `Manager::regenerateSessionId()` calls
    `$this->session->regenerateId()` (Phalcon adapter call);
    `Manager::dispatchEvent()` is event-dispatcher agnostic but
    the bound `events` instance comes from a Phalcon-flavoured
    provider chain. Neither is publicly typed.

  - **NO inheritance leak on `Manager` itself** (extends nothing).
    This subsystem's leak signature is **inheritance (session
    store) + contract (session) + dependency (config) + union
    return (loginUsingId) + provider boundary** — five forms
    overlapping with previous areas; the only NEW one introduced
    here is the contract leak ➌. Distinct from C01 which had no
    inheritance/contract leak on its own AuthManager.

- Effort: **L**:

  - Closing the gap needs (1) splitting `Phare\Auth\Manager` into
    a real `SessionGuard implements StatefulGuard, SupportsBasicAuth`
    with `GuardHelpers, Macroable` (matches the C01 effort split);
    (2) introducing `Auth\Recaller` + the cookie-jar dependency +
    `setCookieJar/getCookieJar/queueRecallerCookie/createRecaller/
    hashPasswordForCookie/getRecallerName/setRememberDuration/
    viaRemember/userFromRecaller` (cross-cuts US-C04 Encrypter for
    the cookie payload signing); (3) wiring `Support\Timebox` +
    `rehashPasswordIfRequired` (cross-cuts US-C03 Hashing);
    (4) replacing `$this->session->destroy()` in `logout` with
    `session->remove($this->getName())` and cycling the remember
    token on logout (Behavioural §1); (5) adding `attemptWhen`,
    `once`, `onceUsingId`, `basic`, `onceBasic`,
    `logoutCurrentDevice`, `logoutOtherDevices`, `attempting`,
    `getLastAttempted` and the corresponding 8 events with
    `$guard` name + `$remember` in every constructor;
    (6) replacing `Phare\Session\SessionManager extends
    Phalcon\Session\Manager` with a wrapper that holds a Phalcon
    adapter as a dependency and re-exposes a typed Laravel
    `Store`-shaped API including the flash family + token + CSRF
    cookie token + ArrayAccess; (7) rewriting
    `Contracts\Session\Session` to drop the
    `Phalcon\Session\ManagerInterface` extension and publish the
    26-method Laravel session contract; (8) expanding
    `SessionStoreManager` to add `null/array/cookie/database/apc/
    memcached/dynamodb` drivers, `extend(string, Closure)`,
    `EncryptedStore` wrapping based on `session.encrypt`, plus the
    block/`shouldBlock` API; (9) unifying the `session` and
    `session.manager` bindings (Behavioural §8); (10) deferring
    `$session->start()` to a `StartSession` middleware so
    queue/console workers don't write cookies (Behavioural §7);
    (11) fixing `login()` to use `regenerateId(deleteOldSession:
    true)` (Behavioural §4) and per-class session key naming
    (Behavioural §3); (12) widening `loginUsingId` to `mixed $id`
    (Type-mismatch). Partly blocked on US-C03 Hashing (Hasher +
    rehash), US-C04 Encrypter (recaller cookie payload),
    US-C05 CSRF (session-token API), US-E03 Config (Phare config
    wrapper), and the request-binding work needed to feed
    `Request` into the guard for the recaller path.

