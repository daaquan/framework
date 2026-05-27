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

---

### Hashing

- Current — Phare:
  `src/Phare/Hashing/` ships 6 files — `HasherInterface`,
  `HashManager extends Phare\Support\Manager`, `BcryptHasher
  implements HasherInterface`, `ArgonHasher implements
  HasherInterface`, `Argon2iHasher extends ArgonHasher`,
  `Argon2idHasher extends ArgonHasher`. The service provider lives
  outside the subsystem at `src/Phare/Providers/HashServiceProvider.php`
  and `implements Phalcon\Di\ServiceProviderInterface`. Container
  attribute `src/Phare/Container/Attributes/Hash.php` resolves
  `make('hash')` or `make('hash.manager')->driver($driver)`.

  - **Container bindings (2):** `'hash.manager'` (HashManager
    singleton, ctor receives the string default driver resolved
    from `config('hashing.driver')` with `'bcrypt'` fallback inside
    the provider closure) and `'hash'` (alias singleton resolving
    back to `'hash.manager'`). NO `'hash.driver'` binding.
  - **`HasherInterface` public surface (4):**
    `make(string $value, array $options = []): string`,
    `check(string $value, string $hashedValue, array $options = []): bool`,
    `needsRehash(string $hashedValue, array $options = []): bool`,
    `info(string $hashedValue): array`. Lives at
    `Phare\Hashing\HasherInterface` — NOT under
    `src/Phare/Contracts/Hashing/` (the directory does not exist).
  - **`HashManager` public surface (8 own + 7 inherited from
    `Support\Manager`):**
    - Own: `__construct(string|ContainerContract|null $defaultDriver = null)`,
      `getDefaultDriver(): string`, `setDefaultDriver(string): void`,
      `make(string, array): string`, `check(string, string, array): bool`,
      `needsRehash(string, array): bool`, `info(string): array`,
      `extend(string $driver, Closure|HasherInterface $hasher): static`
      (signature WIDENS Laravel's `extend(string, Closure)` to
      accept a ready hasher instance — porting hazard).
    - Protected driver factories: `createBcryptDriver(): HasherInterface`,
      `createArgonDriver(): HasherInterface`,
      `createArgon2iDriver(): HasherInterface`,
      `createArgon2idDriver(): HasherInterface` — **4 factories vs
      Laravel's 3**.
    - Inherited from `Support\Manager`: `driver(?string): mixed`,
      `extend(string, Closure)` (overridden), `getDrivers()`,
      `getContainer()`, `setContainer()`, `forgetDrivers()`, `__call()`.
  - **`BcryptHasher`:** `__construct(array $options = [])`,
    `make`, `check`, `needsRehash`, `info`, `setRounds(int): void`.
    `$rounds = 10` (DEFAULT). No `$verifyAlgorithm`, no `$limit`,
    no `verifyConfiguration`, no `isUsingCorrectAlgorithm`, no
    `isUsingValidOptions`, no `cost()` helper.
  - **`ArgonHasher`:** `__construct(array $options = [])`,
    `make`, `check`, `needsRehash`, `info`; protected
    `algorithm(): string` (defaults to `PASSWORD_ARGON2I`). Fields
    `$memory = 1024, $time = 2, $threads = 2`. No `$verifyAlgorithm`,
    no `verifyConfiguration`, no `setMemory/setTime/setThreads`, no
    sodium-provider thread-count override.
  - **`Argon2iHasher` / `Argon2idHasher`:** each is a 1-method override
    of `algorithm()`. `Argon2idHasher` does NOT override `check()` —
    inherits the base `password_verify` directly (no algorithm-verify
    branch).
  - **Helper:** `Support\helpers.php` has NO global `hash()` /
    `bcrypt()` helper. The `hash()` line in the file is a comment
    above a `function_exists('hash')` guard around PHP's built-in.
    No `Phare\Support\Facades\Hash` facade either.

- Expected — Laravel 13
  `/opt/laravel-framework/src/Illuminate/Hashing/` +
  `Contracts/Hashing/Hasher.php`:
  - **`Contracts\Hashing\Hasher` (4 methods):** `info($hashedValue)`,
    `make(#[\SensitiveParameter] $value, array $options = [])`,
    `check(#[\SensitiveParameter] $value, $hashedValue, array $options = [])`,
    `needsRehash($hashedValue, array $options = [])`. Every password
    parameter is annotated `#[\SensitiveParameter]` so the value is
    redacted from stack traces / exception messages.
  - **`AbstractHasher`** (no contract on its own, just a base class):
    public `info` (default `password_get_info`) + public `check`
    (default `password_verify` with null/empty guard).
  - **`HashManager extends Manager implements Hasher` (12 public):**
    `createBcryptDriver`, `createArgonDriver`, `createArgon2idDriver`
    (factories are PUBLIC), `info`, `make`, `check`, `needsRehash`,
    `isHashed(#[\SensitiveParameter] $value): bool`,
    `getDefaultDriver`, `verifyConfiguration($value)` (internal,
    delegates to driver). NO `createArgon2iDriver` — the single
    `createArgonDriver` IS the argon2i factory.
    `getDefaultDriver` reads `$this->config->get('hashing.driver',
    'bcrypt')` on every call (not cached in a field).
  - **`BcryptHasher extends AbstractHasher implements Hasher`:**
    `__construct(array $options = [])`, `make`, `check`, `needsRehash`,
    `verifyConfiguration`, `setRounds($rounds)`. Fields `$rounds = 12`
    (DEFAULT), `$verifyAlgorithm = false`, `$limit = null`. Protected
    `isUsingCorrectAlgorithm`, `isUsingValidOptions`, `cost`.
    `make()` throws `InvalidArgumentException` when
    `strlen($value) > $this->limit`.
  - **`ArgonHasher extends AbstractHasher implements Hasher`:**
    `__construct`, `make`, `check`, `needsRehash`, `verifyConfiguration`,
    `setMemory(int)`, `setTime(int)`, `setThreads(int)`. Field
    `$verifyAlgorithm`. Protected `algorithm`, `memory`, `time`,
    `threads`, `isUsingCorrectAlgorithm`, `isUsingValidOptions`.
    `threads()` returns `1` when `defined('PASSWORD_ARGON2_PROVIDER')
    && PASSWORD_ARGON2_PROVIDER === 'sodium'` regardless of options
    — handles libsodium's single-thread Argon implementation.
  - **`Argon2IdHasher extends ArgonHasher`:** overrides `check()`
    (re-checks algorithm match before falling through to
    `password_verify`), `algorithm()` (`PASSWORD_ARGON2ID`),
    `isUsingCorrectAlgorithm`.
  - **`HashServiceProvider extends ServiceProvider implements
    DeferrableProvider`:** binds `'hash'` (HashManager singleton) AND
    `'hash.driver'` (singleton resolving the default driver of
    `$app['hash']`); `provides()` returns both keys. Deferred — only
    boots when one of the keys is resolved.
  - **`Foundation/helpers.php`** ships `bcrypt(#[\SensitiveParameter]
    $value, $rounds = []): string` and `Hash::make()` via the Hash
    facade. The Hash facade `__callStatic`s the manager.

- Gaps:

  - **Missing — entire `verifyConfiguration` / algorithm-verify
    machinery.** No `BcryptHasher::verifyConfiguration /
    isUsingCorrectAlgorithm / isUsingValidOptions`, no
    `ArgonHasher` equivalents, no `HashManager::verifyConfiguration`,
    no `$verifyAlgorithm` field on either hasher. Loss of two
    capabilities: (1) the per-driver guard against verifying a hash
    of a different algorithm (e.g. an old argon2i hash being checked
    by the bcrypt driver), and (2) the `verifyConfiguration` startup
    check that catches "your config asked for cost=14 but a hash
    in the DB encoded cost=15" (downgrade-attack-window check).
  - **Missing — `HashManager::isHashed`.** Used by Laravel's mutator
    system (`Attributes\Hashed` cast) to short-circuit re-hashing
    when the value is already a hash. Phare's `HashManager` has no
    `isHashed`; downstream code wanting that check must call
    `info()` and inspect `algo`/`algoName` directly.
  - **Missing — bcrypt `$limit` + InvalidArgumentException.** Laravel
    `BcryptHasher::make` throws when `strlen($value) > $this->limit`
    (bcrypt silently truncates at 72 bytes — the `limit` option is
    the explicit guardrail). Phare has no `$limit` field, never
    enforces a max length — silently relies on the underlying
    bcrypt truncation. Functional+security defect: passwords past
    byte 72 are not actually used but no error is raised.
  - **Missing — sodium-provider thread-count override.** Laravel
    `ArgonHasher::threads()` returns `1` when running on the sodium
    Argon backend (libsodium's Argon supports only `threads = 1`).
    Phare passes whatever was configured (default `2`) to
    `password_hash`; on a sodium build that silently flips behaviour
    — `needsRehash` will then report `true` forever because the
    stored hash records `threads=1` but the configured cost is
    `threads=2`. **Correctness defect — silent infinite-rehash loop.**
  - **Missing — `Hasher` contract publication.** No
    `src/Phare/Contracts/Hashing/` directory; `HasherInterface`
    lives **inside the `Phare\Hashing\` namespace**, not under
    `Phare\Contracts\Hashing\Hasher`. `HashManager` does NOT
    implement `HasherInterface`. Same no-contracts pattern logged
    for A06/A07/B06/B07/C01/C02 — recorded as inherited type-mismatch,
    not re-narrated.
  - **Missing — `#[\SensitiveParameter]` on every password
    parameter.** Laravel annotates `$value` on `HasherInterface::make`,
    `check`, `isHashed`, `HashManager::make`, `check`, `isHashed`,
    `BcryptHasher::make`, `check`, `ArgonHasher::make`, `check`,
    `Argon2IdHasher::check`. Phare has zero `#[\SensitiveParameter]`
    annotations in this subsystem. **Security defect** (plaintext
    passwords appear in stack traces / dumped exception messages /
    error-handler payloads). Cheap to fix (one-attribute-per-param),
    so flag as a security defect with effort S.
  - **Missing — `'hash.driver'` container binding.** Laravel binds
    both `'hash'` (manager) AND `'hash.driver'` (the default driver
    instance). Phare binds only `'hash.manager'` + `'hash'` (alias
    to manager). Code expecting `app('hash.driver')` to return a
    `HasherInterface` gets a `BindingResolutionException`.
  - **Missing — `DeferrableProvider`.** Laravel's HashServiceProvider
    `implements DeferrableProvider` + `provides()`. Phare's
    HashServiceProvider is non-deferred and always boots; on every
    boot it instantiates `HashManager` regardless of whether any
    code asks for it. Parity gap shared with the broader US-E02
    deferred-provider gap.
  - **Missing — global helper `bcrypt()` + `Hash` facade.** Laravel
    ships `bcrypt($value, $rounds = [])` in `Foundation/helpers.php`
    + `Illuminate\Support\Facades\Hash`. Phare's `Support/helpers.php`
    has no `bcrypt()` global; resolution requires `app('hash')->make()`.
    No `Phare\Support\Facades\Hash` either (cross-references US-E05).
  - **Missing — default `config/hashing.php`.** No `config/hashing.php`
    ships in the framework. `config('hashing.driver')` therefore
    returns `null` unless an app supplies one; the provider closure's
    `?? 'bcrypt'` fallback masks the missing file but `'hashing.bcrypt'`
    / `'hashing.argon'` cost-tuning keys cannot be configured at all.

  - **Type mismatch — BcryptHasher default rounds 10 vs Laravel 12.**
    Phare `protected int $rounds = 10`; Laravel `protected $rounds
    = 12`. Concrete impact: a fresh Phare app's stored bcrypt hashes
    are ~4× weaker (cost 2¹⁰ vs 2¹²) by default. Recorded as a
    **security defect**, not just a type-mismatch. Stacks
    multiplicatively with C02 §2 (no Hasher injection, no
    `rehashPasswordIfRequired`) — even if the default were corrected,
    existing hashes could never be rotated upward.
  - **Type mismatch — `HashManager::extend(string, Closure|HasherInterface)`
    widens Laravel's `extend(string, Closure)`.** Phare accepts a
    pre-built hasher instance (legacy API) OR a Closure factory.
    Two effects: (a) calling code that passes a closure works in
    both; (b) calling code that passes a hasher INSTANCE works in
    Phare but throws a `TypeError` in Laravel. Porting hazard
    in the **Phare→Laravel direction**. Same widening pattern as
    A02 union returns / B03 union returns.
  - **Type mismatch — Phare manager constructor is
    `string|ContainerContract|null` vs Laravel `Manager(Container)`.**
    Phare's HashManager allows construction without a container,
    using a hard-coded default driver string. Useful for tests,
    but the resulting manager has no `config`, and any `extend()`
    creator referencing `$this->config` would NPE. Type boundary
    widened in Phare.
  - **Type mismatch — return type on factory methods.**
    Phare's `createBcryptDriver(): HasherInterface` is typed; Laravel
    leaves the factories untyped and returns the concrete `BcryptHasher`
    in PHPDoc. Phare is stricter here — recorded as a forward-compatible
    type-mismatch (not a defect).
  - **Type mismatch — `getDefaultDriver` caches default in a field.**
    Phare reads `config('hashing.driver')` only inside the
    HashServiceProvider closure and inside the no-container ctor
    branch — once frozen on the manager instance via `$defaultDriver`,
    a runtime `config(['hashing.driver' => 'argon'])` change has
    no effect. Laravel re-reads config on every call. Behavioural
    divergence, small effort.

  - **Behavioural §1 — driver name `argon` vs `argon2i`
    duplication (NEW behavioural defect).** Phare ships BOTH
    `createArgonDriver` (returns `ArgonHasher` whose `algorithm()`
    defaults to `PASSWORD_ARGON2I`) AND `createArgon2iDriver`
    (returns `Argon2iHasher extends ArgonHasher` whose `algorithm()`
    ALSO returns `PASSWORD_ARGON2I`). Configuration
    `hashing.driver = 'argon'` and `hashing.driver = 'argon2i'`
    resolve to functionally identical hashers under different class
    names; verifying a hash made with one against the other works
    (both call `password_verify`). Laravel has only
    `createArgonDriver` (= argon2i) and `createArgon2idDriver` (=
    argon2id). Phare's extra `argon2i` driver-name has no parity
    counterpart and risks confusion. Flag for US-S01 under "name
    divergence / dead driver".
  - **Behavioural §2 — driver factories ignore the hashing config
    (silent-config-dropthrough).** Laravel `createBcryptDriver` does
    `new BcryptHasher($this->config->get('hashing.bcrypt') ?? [])`;
    Phare `createBcryptDriver` does `new BcryptHasher()` — the
    ctor options array is hard-coded `[]`. Effect: `hashing.bcrypt.rounds`,
    `hashing.argon.memory`, `hashing.argon.time`, `hashing.argon.threads`,
    `hashing.bcrypt.verify`, `hashing.argon.verify`, `hashing.bcrypt.limit`
    config keys are SILENTLY IGNORED. The only way to change Phare
    bcrypt rounds is `$hasher->setRounds(...)` at runtime.
    **Correctness defect — silent config dropthrough.** Same defect
    family as B03 silent-arg-drop (BelongsToMany attach `$touch`).
  - **Behavioural §3 — `check()` ignores the `verify` config flag.**
    Laravel's `BcryptHasher::check()` raises if the stored hash is
    not bcrypt while `$verifyAlgorithm = true`. Phare has no
    `$verifyAlgorithm`, so an Argon hash silently `password_verify`s
    against a bcrypt-driver `check()` (returning false) without
    raising — apps that intended to fail-loud on algorithm drift
    silently fail-quiet.
  - **Behavioural §4 — bcrypt 72-byte truncation is undetectable.**
    With no `$limit`, a password longer than 72 bytes is silently
    truncated by bcrypt — the prefix forms the actual key. Two
    users with identical first 72 chars but different suffixes
    verify against each other's hashes. Security/UX defect.
  - **Behavioural §5 — `info()` array shape parity.** Phare just
    forwards `password_get_info`, identical to Laravel. The only
    cost-of-parity issue is that callers comparing to Laravel-fixture
    output should expect identical shapes. Not-a-gap.

  - **Type mismatch (no-contracts) — inherited.** No
    `Phare\Contracts\Hashing\Hasher`; consumers cannot type-hint the
    contract. Same pattern as A06 / A07 / B06 / B07 / C01 / C02.
    Inherited.

- Phalcon leaks (§2):

  - ➀ **`Phare\Providers\HashServiceProvider implements
    Phalcon\Di\ServiceProviderInterface`** — provider-boundary
    contract coupling. Same shape as A07 `BladeViewProvider`, C01
    `AuthServiceProvider`, C02 `SessionProvider`. Recorded; counted
    under the running provider-boundary leak family in S01.
  - ➁ **`HashServiceProvider::register(Application|DiInterface $app): void`**
    — published-dependency leak: the public method signature declares
    `Phalcon\Di\DiInterface` as an accepted type. Same shape as
    B05 schema (AbstractPdo), B06 seeders (AbstractPdo), C01 manager
    (ConfigInterface). Recorded; counted under the published-dependency
    leak family in S01.

  - **Confirmed CLEAN inside `src/Phare/Hashing/`** — zero Phalcon
    references in `HashManager`, `HasherInterface`, `BcryptHasher`,
    `ArgonHasher`, `Argon2iHasher`, `Argon2idHasher`. The leak
    surface for Hashing lives entirely in the **out-of-namespace
    provider** (`src/Phare/Providers/HashServiceProvider.php`).
    Pattern note: when a subsystem's namespace is Phalcon-clean,
    open `src/Phare/Providers/<Subsystem>ServiceProvider.php` —
    that is where the published Phalcon dependency typically hides
    (recurs A07 BladeViewProvider, C01 AuthServiceProvider, C02
    SessionProvider, and now C03 HashServiceProvider).

- Effort: **M**:

  - Smallest-surface Area-C subsystem so far (6 source files in the
    namespace, 8 public manager methods, 4 hasher methods on the
    contract). But closing the gap touches every file and is
    **security-critical**, so M not S:
    (1) publish `Phare\Contracts\Hashing\Hasher` with the 4-method
        Laravel-shape signatures and `#[\SensitiveParameter]` on
        every `$value` param; make `HashManager` and every concrete
        hasher implement it;
    (2) bump `BcryptHasher::$rounds` default to `12` (security);
    (3) add `$verifyAlgorithm` + `$limit` to `BcryptHasher`, plus
        `verifyConfiguration / isUsingCorrectAlgorithm /
        isUsingValidOptions`;
    (4) add `$verifyAlgorithm` + sodium-provider threads override
        + `setMemory/setTime/setThreads` + `verifyConfiguration`
        family to `ArgonHasher`;
    (5) override `check()` in `Argon2idHasher` to match Laravel's
        algorithm-verify branch;
    (6) decide whether to **retire** Phare's redundant
        `Argon2iHasher` + `createArgon2iDriver` (collapse to
        `createArgonDriver` = argon2i) OR keep with a documented
        rationale (§7 candidate — flag for S01);
    (7) wire `HashManager` to pass `config('hashing.bcrypt')` /
        `config('hashing.argon')` arrays into each driver factory
        ctor (closes Behavioural §2 silent-config-dropthrough);
    (8) add `HashManager::isHashed` + `verifyConfiguration`;
    (9) drop the `Closure|HasherInterface` widening on
        `HashManager::extend`, accept Closure only (Laravel parity);
    (10) rewrite `HashServiceProvider` to extend
         `Phare\Support\ServiceProvider implements DeferrableProvider`
         (cross-cuts US-E02), drop `Phalcon\Di\ServiceProviderInterface`,
         add `'hash.driver'` binding, add `provides()`;
    (11) add `bcrypt()` global helper to `Support\helpers.php` +
         `Phare\Support\Facades\Hash` (cross-cuts US-E05/E06);
    (12) ship a default `config/hashing.php` (cost defaults, verify
         flags, bcrypt limit). Blocked partly on US-E02 (Phare
         ServiceProvider base) and US-E03 (Config wrapper for the
         `verifyConfiguration` startup hook). Cross-cuts US-C02 §2
         (the `rehashPasswordIfRequired` hook on the session guard
         requires the Hasher contract published here).

### Encrypter

- Current — Phare:
  `src/Phare/Encryption/` ships 3 files — `Encrypter` (concrete,
  implements NO interface), `EncryptException extends \RuntimeException`,
  `DecryptException extends \RuntimeException`. The service provider
  lives outside the subsystem at
  `src/Phare/Providers/EncrypterProvider.php` and
  `implements Phalcon\Di\ServiceProviderInterface`. There is NO
  `src/Phare/Contracts/Encryption/` directory.

  - **Container bindings (3):** `'random'` → singleton
    `Phalcon\Encryption\Security\Random`; `'security'` → singleton
    `Phalcon\Encryption\Security` with `setWorkFactor(12)` + `setDI`;
    `'encrypter'` → singleton `Phalcon\Encryption\Crypt` (Phalcon's
    crypt class, NOT `Phare\Encryption\Encrypter`). The Phare-namespace
    `Encrypter` is **NEVER bound to the container** — the container
    slot `'encrypter'` resolves to a Phalcon class.
  - **`Phare\Encryption\Encrypter` public surface (9):**
    - `__construct(string $key, string $cipher = 'aes-256-cbc')`
      (default cipher diverges from Laravel `'aes-128-cbc'`).
    - `encrypt(mixed $value, bool $serialize = true): string`
    - `decrypt(string $payload, bool $unserialize = true): mixed`
    - `encryptString(string $value): string`
    - `decryptString(string $payload): string`
    - `generateKey(string $cipher = 'aes-256-cbc'): string` —
      **instance method** returning raw bytes (NOT static, NOT base64).
    - `generateKeyString(string $cipher = 'aes-256-cbc'): string` —
      static, returns `base64_encode(random_bytes(...))`. Phare-only
      method with no Laravel counterpart.
    - `getKey(): string`
    - `getCipher(): string` — Phare-only getter, no Laravel
      counterpart.
  - **Internal helpers:** `validateKey`, `isAEAD`, `createMac(string
    $iv, string $encrypted): string`, `validateMac(array $payload)`,
    `getJsonPayload(string): array`, `validPayload(array): bool`.
  - **Supported ciphers:** identical 4-cipher table to Laravel
    (`aes-128-cbc`, `aes-256-cbc`, `aes-128-gcm`, `aes-256-gcm`).
  - **Consumers of Phare\Encryption\Encrypter:** exactly ONE —
    `src/Phare/Eloquent/Concerns/HasAttributes::resolveAttributeEncrypter()`
    (`new Encrypter(...)` directly for the `encrypted` cast on
    Eloquent attributes). No facade, no helper, no provider wires it.
  - **Helpers in `Phare\Support\helpers.php`:**
    `encrypter(): Crypt` (return type is `Phalcon\Encryption\Crypt` —
    helper PUBLISHES the Phalcon class in its return type);
    `encrypt(string $value, ?string $key = null): string` →
    `encrypter()->encryptBase64($value, $key)`;
    `decrypt(string $value, ?string $key = null): string` →
    `encrypter()->decryptBase64($value, $key)`;
    `bcrypt(string, ?string): string` → `security()->hash(...)`
    (already covered by US-C03 — flagged here because it pivots off
    the Phalcon `security` slot wired in this same provider);
    `hash(string, ?string): string` → `encrypter()->encryptBase64`
    (mis-named — `hash()` is not a hash, it's an encrypted ciphertext;
    Laravel has no such helper).
  - **PRD path note:** the AC `src/Phare/Encryption/` is correct;
    no Encrypter wrapper hides under `Phare\Crypt`, `Phare\Security`,
    or `Phare\Auth`.

- Expected — Laravel 13 reference
  `src/Illuminate/Encryption/`:
  - **Encrypter (concrete) implements `Contracts\Encryption\Encrypter`
    AND `Contracts\Encryption\StringEncrypter`** (Phare publishes
    neither; first leak shape — no-contracts, same family as A06/A07/
    B06/B07/C01/C02/C03).
  - **`Contracts\Encryption\Encrypter` (5 methods):**
    `encrypt(#[\SensitiveParameter] $value, $serialize = true)`,
    `decrypt($payload, $unserialize = true)`, `getKey()`,
    `getAllKeys()`, `getPreviousKeys()`.
  - **`Contracts\Encryption\StringEncrypter` (2 methods):**
    `encryptString(#[\SensitiveParameter] $value)`,
    `decryptString($payload)`.
  - **`Encrypter` concrete public surface (12) — ALL `$key`/`$value`/
    `$payload` params carry `#[\SensitiveParameter]`:**
    - `__construct($key, $cipher = 'aes-128-cbc')` — 128-CBC default.
    - `static supported($key, $cipher): bool` — public probe (Phare
      has it as protected `validateKey` and throws instead).
    - `static generateKey($cipher): string` — **STATIC**, returns raw
      bytes (Phare has BOTH `generateKey` non-static and
      `generateKeyString` static — opposite of Laravel's shape).
    - `encrypt(#[\SensitiveParameter] $value, $serialize = true)`
    - `encryptString(#[\SensitiveParameter] $value)`
    - `decrypt($payload, $unserialize = true)` — **iterates over
      `getAllKeys()`** for key-rotation decrypt fallback.
    - `decryptString($payload)`
    - `static appearsEncrypted($value): bool` — Phare missing.
    - `getKey()`, `getAllKeys()`, `getPreviousKeys()`,
      `previousKeys(array $keys): $this` — entire key-rotation API
      (Phare missing all 4).
  - **Wire-format additions on Laravel:**
    (a) `encrypt()` always emits BOTH `mac` and `tag` fields (mac
        is `''` for AEAD ciphers, tag is `''` for non-AEAD); JSON
        is encoded with `JSON_UNESCAPED_SLASHES`.
    (b) `validPayload()` validates that `base64_decode($payload['iv'])`
        has length exactly `openssl_cipher_iv_length(strtolower(
        $this->cipher))` — Phare omits this length check; truncated
        / oversized IVs are accepted silently.
    (c) `validPayload()` also accepts payloads where `tag` is absent
        as long as the rest is well-formed.
    (d) `ensureTagIsValid($tag)` enforces AEAD tag length = 16 bytes
        and refuses non-AEAD payloads that contain a tag — Phare
        does neither check.
    (e) `decrypt()` walks `getAllKeys()` so previous keys can still
        decrypt legacy ciphertext.
    (f) cipher comparisons all run through `strtolower(...)` so
        `'AES-256-CBC'` is accepted.
  - **`EncryptionServiceProvider`:** binds `'encrypter'` to
    `new Encrypter($this->parseKey($config), $config['cipher'])` then
    `->previousKeys([...])`. `parseKey()` strips a `base64:` prefix
    and `base64_decode`s the rest. Throws `MissingAppKeyException` if
    `config('app.key')` is empty. Also signs `SerializableClosure`
    with the same key when that package is present.
  - **`MissingAppKeyException`:** dedicated exception (Phare missing).
  - **Helper surface:** `encrypt($value, $serialize = true)`,
    `decrypt($payload, $unserialize = true)` route through the
    `'encrypter'` slot — both return Laravel's `Encrypter` payload
    shape (not Phalcon `Crypt::encryptBase64`). NO `encrypter()`
    helper in Laravel 13.

- Gaps:

  Phalcon leaks:
  - ➀ **Provider-boundary contract-coupling leak** (recurs A07/
    C01/C02/C03 — fifth instance): `Phare\Providers\EncrypterProvider
    implements Phalcon\Di\ServiceProviderInterface`. The subsystem
    namespace itself is Phalcon-clean by inheritance and namespace;
    the leak lives in the provider exactly where the recurring
    pattern predicts.
  - ➁ **Published-dependency leak** (recurs B05 AbstractPdo, B06
    AbstractPdo, C01 ConfigInterface, C03 DiInterface — fifth
    instance):
    `EncrypterProvider::register(Application|DiInterface $app): void`
    publishes `\Phalcon\Di\DiInterface` in the public union.
  - ➂ **Container-slot leak (NEW shape for C04 — distinct from §1/§2).**
    The `'encrypter'` container slot is bound to
    `Phalcon\Encryption\Crypt` (not `Phare\Encryption\Encrypter`).
    Every consumer that does `app('encrypter')` or `make('encrypter')`
    receives the raw Phalcon class with Phalcon's `encryptBase64` /
    `decryptBase64` shape (NOT Laravel's `encrypt`/`decrypt` payload
    shape). This is the DUAL-STACK pattern from A07 (BladeView
    Stack 2 vs the Phare View stack) repeating verbatim:
    Stack 1 = `Phare\Encryption\Encrypter` (Phalcon-clean class,
    not wired); Stack 2 = `Phalcon\Encryption\Crypt` (wired into
    `'encrypter'`, drives the helpers). Stack 1 is essentially dead
    except for the encrypted-cast call site in
    `Eloquent\Concerns\HasAttributes::resolveAttributeEncrypter()`.
    Record as the **DI-slot leak** family alongside §1/§2.
  - ➃ **Helper-return-type leak (NEW shape for C04).**
    `Phare\Support\helpers.php::encrypter(): Crypt` — global helper
    publishes `Phalcon\Encryption\Crypt` in its return type. First
    helper-level signature leak recorded in the audit (distinct from
    a binding leak because it is the helper, not the container, that
    publishes the type).
  - ➄ **Provider-internal calls** (informational, not a new leak
    class): the closure body of the `'encrypter'` binding instantiates
    `new Crypt()->setKey($key)->setCipher(...)` — drives Phalcon
    Crypt's fluent API. Same shape as A07 BladeView Stack 2.
  - The `Phare\Encryption\Encrypter` class itself, `EncryptException`,
    `DecryptException` are **Phalcon-clean by namespace AND
    inheritance** — same shape as A07 Stack 1 / C03 Hashing
    namespace.

  Missing (vs Laravel `Contracts\Encryption\Encrypter` +
  `StringEncrypter` + concrete `Encrypter` + provider):
  - **Contracts:** no `Phare\Contracts\Encryption\Encrypter`, no
    `Phare\Contracts\Encryption\StringEncrypter`, no
    `Phare\Contracts\Encryption\EncryptException`/`DecryptException`.
    Continues the no-contracts pattern (A06/A07/B06/B07/C01/C02/C03).
  - **Key rotation (entire surface — 4 public methods + internal
    plumbing):** `$previousKeys` field, `previousKeys(array $keys): $this`,
    `getAllKeys(): array`, `getPreviousKeys(): array`, and the
    `foreach ($this->getAllKeys() as $key)` loop inside `decrypt()`
    that tries each key. Key rotation is **unreachable** in Phare —
    a key change is a hard cut-over that strands every existing
    ciphertext. Flag as a new defect class for S01: **key-rotation
    unreachable** (distinct from logout-blast-radius and infinite-
    rehash-loop, all C-area runtime-only defects).
  - **Static probe:** `static supported($key, $cipher): bool` — no
    pre-construction key/cipher validation; callers must `try { new
    Encrypter(...) } catch (\InvalidArgumentException)`.
  - **Static detector:** `static appearsEncrypted($value): bool` —
    payload-shape probe used by the `encrypted` cast machinery
    (Laravel's `HasAttributes` consults it).
  - **`ensureTagIsValid()` AEAD-tag length check** (16 bytes) and
    refusal of non-AEAD payloads carrying a `tag` — Phare's AEAD
    branch never validates tag length, so a truncated AEAD payload
    can reach OpenSSL with a malformed tag.
  - **IV length check in `validPayload()`** — `strlen(base64_decode(
    $payload['iv'])) === openssl_cipher_iv_length(...)`. Phare
    accepts any string for `iv` and only fails when OpenSSL itself
    rejects, producing an unspecific `DecryptException`.
  - **`MissingAppKeyException`** dedicated exception (Phare throws
    `\InvalidArgumentException` from `validateKey()`, but the missing-
    key case is currently silently routed to the same exception with
    a length message — the more specific signal is gone).
  - **`#[\SensitiveParameter]`** on every `$key` / `$value` /
    `$payload` / `$iv` parameter — Phare has ZERO occurrences in
    `Encrypter.php`. Same security-defect family as C03 (a):
    plaintext values + the AES key leak into stack traces, crash
    reports, and error-handler frames.
  - **`Encrypter::__construct(#[\SensitiveParameter] $key, $cipher
    = 'aes-128-cbc')`** — Phare's default cipher is `'aes-256-cbc'`,
    which is **stronger** but **wire-incompatible** with a Laravel
    app that did not explicitly set `app.cipher` (a default-key
    install on each side produces non-interchangeable ciphertext).
    Record as a porting hazard.
  - **`strtolower($cipher)` everywhere Laravel does it** — Phare's
    `validateKey()` and `isAEAD()` lookups are case-sensitive on
    `$cipher`. `'AES-256-CBC'` (a common copy-paste from docs) is
    rejected by Phare but accepted by Laravel.
  - **JSON_UNESCAPED_SLASHES flag** on the payload `json_encode` —
    Phare's encrypt() omits it; the cosmetic difference is benign
    for OpenSSL but the resulting payload differs byte-for-byte from
    a Laravel-produced one for the same plaintext + iv + key, which
    means any logged-ciphertext comparison across stacks fails even
    when the underlying data is equal.
  - **`json_encode` failure check after encode** — Laravel re-checks
    `json_last_error()` after `json_encode()` and throws
    `EncryptException('Could not encrypt the data.')`. Phare skips
    this; on JSON encode failure Phare's `base64_encode` is fed
    `false`, which silently produces an empty payload. Defect.
  - **`Encrypter` does NOT implement `EncrypterContract` or
    `StringEncrypter`** — declared type checks (`instanceof
    EncrypterContract`) elsewhere in any port would fail.
  - **Service provider:** no Phare wrapper exists; the provider
    binds the Phalcon class directly. Missing: `'encrypter'` →
    `Phare\Encryption\Encrypter` binding, `parseKey()` with the
    `base64:` prefix handling, `previousKeys` config wiring,
    `MissingAppKeyException` on empty key, `SerializableClosure`
    signing hook (also touches Q01 Queue serialisation).
  - **Helpers:** Laravel has no `encrypter()` helper; the Phare
    `encrypter()` helper is gratuitous AND publishes a Phalcon type
    (➃). Also missing: an `encrypted` Blade directive (cross-cuts
    A07) and the `Crypt` facade (cross-cuts US-E05).

  Type mismatch:
  - Phare public surface is fully type-hinted (`string`, `mixed`,
    `bool`); Laravel's is untyped on most params. Record as
    inverted-from-the-usual direction: porting Laravel → Phare
    tightens types (safe direction), Phare → Laravel signature
    parity would require removing the type hints (no real cost).
    NOTE: this is the FIRST audit section where Phare's stricter
    typing is the porting hazard rather than the asset — because
    `static generateKey($cipher)` in Laravel returns raw bytes vs
    Phare's INSTANCE `generateKey(...)` returning raw bytes PLUS a
    static `generateKeyString(...)` returning base64. The same
    function name `generateKey` resolves to **instance vs static**
    across the two stacks.
  - `getCipher(): string` — Phare-only getter with no Laravel
    counterpart; `getKey()` exists in both, `getCipher` is a
    one-way extension.
  - `extend` / driver factories: N/A — Encrypter is a single class,
    not a Manager. No `EncrypterManager` (no multi-cipher manager
    surface). Laravel also has none — not a gap, but worth noting
    that the encrypter has no driver indirection in either stack.

  Behavioural defects (NEW classes for US-S01 synthesis where flagged):
  - §1 **Key-rotation unreachable** (NEW C-area defect class):
    no `$previousKeys` plumbing in `Phare\Encryption\Encrypter`
    AND the `'encrypter'` slot resolves to Phalcon `Crypt`, which
    has no previous-keys API either. A `APP_KEY` rotation in
    production strands every existing encrypted cell, encrypted
    cookie, and `Encryptable` queue payload. Distinct from C02's
    logout-blast-radius (those are auth-side) and C03's infinite-
    rehash (hash-side).
  - §2 **Dual-stack drift**: encrypted-cast attributes (`HasAttributes`)
    are encrypted by `Phare\Encryption\Encrypter` (Stack 1, AES-256-CBC
    by default, Laravel-shape payload), while everything routed
    through `encrypter()`/`encrypt()`/`decrypt()` helpers is
    encrypted by `Phalcon\Encryption\Crypt` (Stack 2, `encryptBase64`
    shape). The two stacks produce **incompatible ciphertext** —
    a cell written by the cast cannot be decrypted by the helper
    and vice versa. Recurs the A07 dual-stack drift exactly.
  - §3 **Helper `hash($value, $key)` returns encrypted ciphertext,
    not a hash** — `return encrypter()->encryptBase64($value, $key);`.
    Misnamed at the boundary (PHP also has a built-in `hash()`);
    callers reasoning about a hash get an encrypted blob that
    cannot be compared in constant time AND requires the key to
    "verify". Recurs the silent-config-dropthrough family (C03 §2)
    as a NAMING defect — pattern note: any helper whose body reaches
    for `encrypter()` should be re-grepped for this shape.
  - §4 **`encrypt()` JSON-encode failure path silently produces an
    empty payload** — no `json_last_error()` re-check between
    `json_encode(...)` and `base64_encode(...)`. The downstream
    `decrypt` raises a payload-shape error, not an encrypt error,
    which buries the root cause. Stub-defect family (recurs A05
    `view()` debug stub, B02 raw-SQL bypass, etc., but on the
    error-handling axis).
  - §5 **AEAD tag-length not validated**: no `ensureTagIsValid`.
    A truncated or oversized AEAD `tag` is passed straight to
    OpenSSL and fails with a generic `Could not decrypt` rather
    than a specific tag-shape `DecryptException`. Security-defect
    family extends from C03 (a: missing `#[\SensitiveParameter]`,
    d: bcrypt 72-byte limit).
  - §6 **IV byte-length not validated**: no
    `strlen(base64_decode($iv)) === openssl_cipher_iv_length(...)`
    in `validPayload`. Caller-supplied payload with a 12-byte IV
    on AES-CBC silently routes to `openssl_decrypt` which clamps/
    pads — undefined-behaviour surface. Same security family.
  - §7 **`#[\SensitiveParameter]` absent** on `__construct($key)`,
    `encrypt($value)`, `encryptString($value)`, `validateKey($key,
    $cipher)`, `createMac($iv, $encrypted)`, `decrypt($payload)` —
    plaintext + key leak into stack traces. Direct continuation of
    C03 (a).
  - §8 **`generateKey` shape mismatch — STATIC vs INSTANCE**:
    Phare's instance `generateKey(...)` collides with Laravel's
    static `generateKey($cipher)`. Calling
    `Phare\Encryption\Encrypter::generateKey('aes-256-cbc')`
    statically raises a non-static-method warning under PHP 8.x.
    Add to the running list of same-name/opposite-shape porting
    hazards — running list now: B01 `Model::create`, B02
    `Builder::paginate`, B05 `Migrator::rollback`, B06
    `Factory::for`, B07 `LengthAwarePaginator::simplePaginate`,
    C01 `Authenticatable::getAuthIdentifierName`/`getAuthPasswordName`,
    C02 `Manager::login`, C03 `HashManager::extend`, and now
    **C04 `Encrypter::generateKey` (instance vs static)**.
  - §9 **Cipher case-sensitive**: `'AES-256-CBC'` rejected by
    `validateKey()`; Laravel accepts. Porting hazard.
  - §10 **Provider closure swallows malformed `app.key`**:
    `[$method, $encoded] = explode(':', $appKey);` — if `app.key`
    has no `:` separator (a raw key with no `base64:` prefix), this
    raises a destructure warning and assigns `null` to `$encoded`,
    which then `substr($encoded, ...)` returns `false`, fed to
    `base64_decode(false)` → empty string → `setKey('')` → Phalcon
    crypt throws at first use with a generic error. Distinct from
    Laravel's explicit `MissingAppKeyException` path.
  - §11 **`encrypter` slot does NOT call `previousKeys()`** — even
    if the Phare\Encryption\Encrypter were bound here, the provider
    closure has no `->previousKeys($config['previous_keys'] ?? [])`
    line. Wiring gap.
  - §12 **`SerializableClosure` signing not wired**: the provider
    does not call `SerializableClosure::setSecretKey($key)`. Queue
    serialisation (US-D01) cannot rely on signed closures here.
    Cross-cuts US-D01.

  Cross-area cuts (record for US-S01):
  - US-C02 §3 + §4 (Session): the recaller cookie (currently
    missing) needs `Encrypter::encrypt(...)` for the
    `{id}|{token}|{password}` payload — blocked on this story
    introducing the Phare encrypter binding.
  - US-C05 (CSRF): encrypted cookies for the CSRF token flow need
    the same binding.
  - US-D01 (Queue): `SerializableClosure::setSecretKey` (provider
    body) is the queue-side hook for signed closure payloads.
  - US-E02 (Service providers): `EncrypterProvider` rewrite is
    blocked on a Phare ServiceProvider base + DeferrableProvider
    contract.
  - US-E03 (Config): `parseKey()` + `MissingAppKeyException` need
    the Phare Config wrapper to read `app.key` / `app.cipher` /
    `app.previous_keys`.
  - US-E05 (Facades): no `Crypt` facade.
  - US-A07 (Blade): no `@encrypted` directive — re-grep at A07 follow-up.

- Effort: **M**:

  - Larger surface than C03 (Hashing) by API breadth (12 vs 8 public
    methods) but the concrete class is already ~95% Laravel-shaped
    — the bulk of the work is **wiring**, not rewriting:
    (1) publish `Phare\Contracts\Encryption\Encrypter` (5 methods)
        and `Phare\Contracts\Encryption\StringEncrypter` (2 methods)
        with `#[\SensitiveParameter]` on every `$key`/`$value`/
        `$payload` parameter; make `Phare\Encryption\Encrypter`
        implement BOTH;
    (2) add the key-rotation surface: `$previousKeys` field,
        `previousKeys(array $keys): $this`, `getAllKeys(): array`,
        `getPreviousKeys(): array`, and rewrite `decrypt()` to
        `foreach ($this->getAllKeys() ...)` (closes §1 key-rotation
        unreachable);
    (3) add `static supported($key, $cipher): bool`,
        `static generateKey($cipher): string` (Laravel-shape STATIC)
        — decide whether to RETIRE the instance `generateKey` (BC
        break) or keep both with a deprecation note (§7 candidate
        for S01); add `static appearsEncrypted($value): bool`;
    (4) add `ensureTagIsValid($tag)` and the AEAD/non-AEAD branches;
        add IV byte-length check inside `validPayload()`; add
        `json_last_error()` check after `json_encode` (closes §4/§5/
        §6);
    (5) lowercase every `$cipher` lookup (`strtolower($cipher)` in
        `validateKey`, `isAEAD`, `getJsonPayload`);
    (6) add `#[\SensitiveParameter]` to every relevant param (closes
        §7) — security parity with C03;
    (7) add `MissingAppKeyException` and route empty-key from
        `validateKey()` through it;
    (8) rewrite `EncrypterProvider`:
        (a) extend `Phare\Support\ServiceProvider implements
            DeferrableProvider` (cross-cuts US-E02), drop
            `Phalcon\Di\ServiceProviderInterface`;
        (b) bind `'encrypter'` to `Phare\Encryption\Encrypter`
            (not `Phalcon\Encryption\Crypt`) — **DECISION POINT
            for S01**: is the dual stack intentional (e.g., Phalcon
            Crypt kept for `encryptBase64`-format legacy data) or
            should Stack 2 be retired wholesale? Recommended retire;
        (c) add `parseKey()` with `base64:` prefix handling and
            `MissingAppKeyException` on empty key (closes §10);
        (d) add `->previousKeys($config['app.previous_keys'] ?? [])`
            wiring (closes §11);
        (e) add `SerializableClosure::setSecretKey($key)` when the
            package is present (closes §12 — cross-cuts US-D01);
        (f) decide whether `'random'` and `'security'` bindings
            stay (currently Phalcon-typed) or migrate — they belong
            under US-C03 + a future Random subsystem audit;
    (9) retire or rewrite `Phare\Support\helpers.php::encrypter()`:
        either delete (Laravel has no such helper) or change return
        type to `Phare\Contracts\Encryption\Encrypter` (closes ➃);
        rewrite `encrypt()` / `decrypt()` / `hash()` helpers to go
        through the new `'encrypter'` binding instead of
        `encryptBase64`/`decryptBase64`. Rename or DELETE the
        `hash()` helper — it is misnamed and PHP-builtin-colliding
        (closes §3);
    (10) add the `Crypt` facade under `Phare\Support\Facades\`
         (cross-cuts US-E05);
    (11) cut over `HasAttributes::resolveAttributeEncrypter()` to
         resolve through the new container binding instead of `new
         Encrypter(...)` — closes §2 dual-stack drift.

  - Blocked partly on:
    US-E02 (Phare ServiceProvider base + DeferrableProvider — same
    block as C03 (10));
    US-E03 (Config wrapper for `app.key` / `app.cipher` /
    `app.previous_keys`);
    US-D01 (queue side of `SerializableClosure::setSecretKey`).
  - Cross-cuts: US-C02 §3/§4 (recaller cookie), US-C05 (CSRF
    encrypted cookie), US-A07 (`@encrypted` Blade directive),
    US-E05 (Crypt facade).

---

### CSRF Middleware

- Current — Phare:
  CSRF middleware IS present. Three small files form the whole
  subsystem, plus a third tokenizer hidden inside the vendored
  Blade engine.
  - `src/Phare/Middleware/VerifyCsrfToken.php` (114 LOC) —
    `implements Phare\Contracts\Http\Middleware`. Public surface
    is 2 methods: `__construct(Application $app)` and
    `handle(Phalcon\Http\RequestInterface $request, \Closure $next): Phalcon\Http\ResponseInterface`
    + the public `addExcept(array $routes): static` setter.
    Internal protected: `shouldSkip`, `isReading`, `tokensMatch`,
    `getTokenFromRequest`, `inExceptArray`, `normalizePath`.
    Reads/writes the token via a second class (`Csrf`) — does
    NOT call the session store directly.
  - `src/Phare/Middleware/TokenMismatchException.php` (10 LOC) —
    `extends \Exception` with fixed `$message = 'CSRF token mismatch.'`
    and `$code = 419`. NO `$guards`/`$redirectTo` (analogous to
    `AuthenticationException` in C01).
  - `src/Phare/Security/Csrf.php` (116 LOC) — standalone helper
    bound nowhere (resolved through container auto-wiring via
    `$app->make(Csrf::class)`). Public methods (6):
    `__construct(Application $app)`, `generateToken(): string`,
    `getToken(): string`, `verifyToken(string $token): bool`,
    `clearToken(): void`, `getTokenName(): string` (returns
    `'_token'`), `field(): string`, `metaTag(): string`. Stores
    the token in session key `'_csrf_token'` (mismatch with the
    form-input name `'_token'` returned by `getTokenName()`).
    Uses `bin2hex(random_bytes(20))` for a 40-char token (Laravel
    `Session\Store::regenerateToken()` uses `Str::random(40)`).
  - `src/Phare/View/BladeOne.php` (vendored EFTEC v4.9, lines
    72/1590-1631) — a SEPARATE CSRF subsystem inside the Blade
    engine: `public $csrf_token`, `getCsrfToken($fullToken,
    $tokenId='_token')`, `regenerateToken($tokenId='_token')`.
    Writes raw `$_SESSION[$tokenId] = $token.'|'.$ipClient`.
    Bypasses BOTH `Phare\Security\Csrf` AND `app('session')`
    entirely.
  - **Wiring:** `VerifyCsrfToken` is NOT registered in
    `Foundation\Http\Kernel`'s default `$middlewares`/
    `$middlewareGroups['web']` (both empty by default). Apps
    must add it manually. `Phare\Security\Csrf` is NOT bound in
    any `Providers/` file (zero `Csrf::class` or `'csrf'` slot
    hits under `src/Phare/Providers/`).
  - Subsystem namespace `src/Phare/Middleware/` and
    `src/Phare/Security/` are Phalcon-CLEAN by `extends`
    (TokenMismatchException extends `\Exception`, Csrf extends
    nothing, VerifyCsrfToken implements only `Phare\Contracts\Http\Middleware`).
    BUT `VerifyCsrfToken::handle()` publishes raw
    `Phalcon\Http\RequestInterface` (param) and
    `Phalcon\Http\ResponseInterface` (return) — signature-level
    leak (same shape as A02 Kernel, inherited from the
    `Contracts\Http\Middleware` contract whose own
    `handle(RequestInterface, \Closure): ResponseInterface` is
    typed Phalcon — see A03 finding).

- Expected — Laravel 13:
  `Illuminate\Foundation\Http\Middleware\PreventRequestForgery`
  (321 LOC, 14 public methods including statics) is the canonical
  class. `VerifyCsrfToken` and `ValidateCsrfToken` are both
  empty `@deprecated` subclasses kept for back-compat.
  - **Public surface (14):** `__construct(Application $app, Encrypter $encrypter)`,
    `handle($request, Closure $next)`, `shouldAddXsrfTokenCookie(): bool`,
    `getExcludedPaths(): array` (from `ExcludesPaths` trait),
    static `except($uris): void`, static `allowSameSite($allow=true): void`,
    static `useOriginOnly($originOnly=true): void`, static
    `serialized(): bool`, static `flushState(): void`,
    plus 5 protected (`isReading`, `runningUnitTests`,
    `hasValidOrigin`, `tokensMatch`, `getTokenFromRequest`,
    `addCookieToResponse`, `newCookie`).
  - Trait composition: `use ExcludesPaths, InteractsWithTime`.
    `ExcludesPaths::inExceptArray` checks `$request->fullUrlIs()`
    OR `$request->is()` — full URL glob + path glob. Globally
    ignored paths live on `static $neverVerify = []` — class-wide,
    survives middleware re-instantiation, opt-in via static
    `PreventRequestForgery::except(['/api/*'])`.
  - `handle()` short-circuits in this exact order:
    `isReading($request) || runningUnitTests() ||
    inExceptArray($request) || hasValidOrigin($request) ||
    tokensMatch($request)`. Origin check (Sec-Fetch-Site) added
    in L13.x — `same-origin` ALWAYS passes; `same-site` passes
    only if `static $allowSameSite=true`; if `static $originOnly`
    is set and Sec-Fetch-Site is anything else, throws
    `Http\Exceptions\OriginMismatchException` (a SEPARATE
    exception from `TokenMismatchException`).
  - `tokensMatch()` reads CSRF from session via
    `$request->session()->token()` — i.e. `Session\Store::token()`
    (auto-generated/rotated by `Store::start()` /
    `regenerateToken()`). Token CSRF rotation happens in
    `Session\Store::migrate(true)` / `regenerate(true)` which
    both call `regenerateToken()`. The middleware uses
    `hash_equals` (constant-time compare).
  - `getTokenFromRequest()` reads `_token` input OR
    `X-CSRF-TOKEN` header, with the third path being
    `X-XSRF-TOKEN` header decrypted via the **injected
    Encrypter** + `CookieValuePrefix::remove(...)`. On
    `DecryptException` falls back to `''`. Laravel apps can
    therefore send the CSRF token as an ENCRYPTED cookie
    (`XSRF-TOKEN` set on the response by `addCookieToResponse`)
    that JavaScript reads and re-sends as `X-XSRF-TOKEN`.
  - `addCookieToResponse()` sets the `XSRF-TOKEN` Symfony Cookie
    on the response (path/domain/secure/same_site/partitioned
    from `config('session')`, lifetime `60*lifetime`, NOT
    HttpOnly so JS can read). Suppressed by
    `static::$originOnly`. The injected `Encrypter` is used
    here too: when `EncryptCookies::serialized('XSRF-TOKEN')`
    is true, the cookie payload is encrypted-serialized.
  - `TokenMismatchException` lives at `Illuminate\Session\TokenMismatchException`
    and is an EMPTY `extends Exception` — Laravel does NOT set
    `$code = 419`; the HTTP status comes from
    `Foundation\Exceptions\Handler::renderHttpException()` /
    a dedicated render case (`instanceof TokenMismatchException`
    → 419 Page Expired).

- Gaps — three buckets:

  **Missing (whole features absent):**
  (1) Encrypter integration — `VerifyCsrfToken::__construct`
      takes ONE arg (`Application`), Laravel takes TWO
      (`Application`, `Encrypter`). Silent-arg-drop family
      (after C01 `$remember`, B03 `$touch`, C03 silent-config-
      dropthrough). Downstream: NO decrypt of `X-XSRF-TOKEN`
      header → Phare middleware reads the X-XSRF-TOKEN header
      AS PLAINTEXT (`$request->getHeader('X-XSRF-TOKEN')`),
      which is **wire-incompatible** with Laravel SPA / Axios
      clients that send the encrypted cookie back as the
      header.
  (2) XSRF-TOKEN cookie write-back — NO `addCookieToResponse`,
      NO `shouldAddXsrfTokenCookie`, NO `newCookie`. The
      response-side cookie protocol is unreachable. Phare apps
      cannot drive an Axios/SPA front-end the Laravel way.
      (Cross-cuts: NO `Phare\Cookie\*` subsystem at all — same
      gap noted in C02 for the Recaller cookie.)
  (3) Origin verification — NO `hasValidOrigin`, NO
      `Sec-Fetch-Site` handling, NO
      `OriginMismatchException`. Phare ships only the legacy
      token-only check; the L13 origin-as-alternative path is
      missing.
  (4) `static useOriginOnly(...)` / `static allowSameSite(...)`
      / `static $originOnly` / `static $allowSameSite` / `static
      $neverVerify` — every static API is missing. App can only
      configure exclusions per-instance via `addExcept()`, which
      means tests cannot global-ignore.
  (5) `static flushState()` — no test reset hook.
  (6) `static serialized()` — no `EncryptCookies::serialized
      ('XSRF-TOKEN')` toggle (and no `EncryptCookies`/cookie-
      encryption subsystem exists, so the toggle has nothing
      to pair with).
  (7) `runningUnitTests()` skip — Phare's `Application::runningUnitTests`
      and `Application::runningInConsole` ARE present (per A01
      audit), but the middleware never consults them. Tests
      cannot bypass without a manual `addExcept`.
  (8) `ExcludesPaths` trait — Laravel composes shared
      excludes-path logic with `Maintenance` middleware via the
      trait; Phare reimplements in
      `VerifyCsrfToken::inExceptArray` locally. (Same defect
      shape as A03's `Routing\RouteMiddlewareResolver`
      duplicating `Kernel::resolveRouteMiddlewareAlias`.)
  (9) Token rotation — `Csrf::generateToken()` is only called
      from `getToken()` when the session key is missing.
      `Session\Store::regenerateToken()` does not exist on
      Phare's session (C02 §coverage 18/54). NO call site
      rotates the CSRF token on login, on `regenerate()`,
      or on any other event. Once minted, the token lives
      forever in session. **Token-rotation unreachable** —
      same defect class as C04 §1 (key-rotation unreachable),
      C03 (e) infinite-rehash loop (config-vs-runtime
      mismatch), C02 §4 (deleteOldSession default). Logout
      DOES clear it transitively because `Manager::logout`
      destroys the entire session (C02 §1 logout
      blast-radius) — but that side-effect is the wrong reason.
  (10) Timebox/timing-safe compare — Phare uses
       `hash_equals` (good) but no `Timebox` wrapper.
       Marginal: hash_equals is already constant-time;
       Timebox in Laravel SessionGuard equalizes the OUTER
       round-trip latency rather than the compare itself.
       Same blocked-on-Timebox flag as C02 §2.
  (11) `Contracts\Security\Csrf` / `Contracts\Csrf\Token`
       interface — none exists. No-contracts pattern continues
       (A06 Validation, A07 View, B06 Factories, B07
       Pagination, C01 Auth, C02 GuardHelpers, C03 Hashing,
       C04 Encryption, now C05 CSRF — 9th subsystem in a row).
  (12) `XSRF-TOKEN`/`X-XSRF-TOKEN` cookie+header round-trip —
       see (1)/(2). Without it, the third path in
       `getTokenFromRequest` is structurally absent.
  (13) `addExcept` is INSTANCE-only (Laravel's `except` is
       static + class-wide). Apps that boot the middleware
       multiple times — or compose route-group middleware —
       lose any addExcept() state. Same per-instance-vs-
       static divergence as C02 §3 (session-key isolation).
  (14) `BladeOne::regenerateToken` writes the wire format
       `$token.'|'.$ipClient` into `$_SESSION[$tokenId]`
       directly. **Triple-stack:** Phare\Security\Csrf reads
       `$_SESSION['_csrf_token']` (plain token), BladeOne reads
       `$_SESSION['_token']` (token+ip), Laravel uses
       `Session\Store::token()` → session key `_token` (plain
       token). Three writers, three formats, one session — IF
       a Phare app enables BladeOne's `@csrf` directive AND
       uses Phare\Security\Csrf via Blade `field()`, the two
       store under different keys and a form roundtrip can
       false-fail. (Extends the dual-stack defect class from
       A07/C04 — first **triple-stack** instance recorded.)

  **Type mismatch / Phare-vs-Laravel signature drift:**
  - `VerifyCsrfToken::__construct(Application $app)` 1 arg vs
    `PreventRequestForgery::__construct(Application $app,
    Encrypter $encrypter)` 2 args — silent-arg-drop (see
    Missing (1)).
  - `handle(RequestInterface, \Closure): ResponseInterface`
    — Phalcon types on both sides; Laravel `handle($request,
    Closure $next)` is untyped and returns `mixed`. Phare
    signature is also stricter on `$next` (must return a
    `ResponseInterface`).
  - `addExcept(array): static` vs static `except($uris): void`
    — same NAME family, opposite binding (instance vs static),
    opposite return shape, opposite scope (per-instance vs
    static `$neverVerify`). **10th** same-name/opposite-shape
    porting hazard (running list now: B01 create, B02
    paginate, B05 rollback, B06 for, B07 simplePaginate, C01
    Authenticatable static names, C02 Manager::login, C03
    HashManager::extend, C04 generateKey, now C05
    addExcept/except).
  - `TokenMismatchException` in `Phare\Middleware`
    namespace vs Laravel `Illuminate\Session` — namespace
    divergence flagged before (B01 firstOrFail ⇒ Phalcon
    Exception not ModelNotFoundException; C01
    AuthenticationException is namespaced-correct but
    feature-poor). Phare's also hard-codes `$code = 419`;
    Laravel encodes 419 via the exception handler, not the
    exception itself. Phare apps that compare on
    exception class will be looking under the wrong
    namespace.
  - `Csrf::getTokenName()` returns `'_token'` (form-input
    name) but `Csrf::storeToken` writes session key
    `'_csrf_token'`. Internal name inconsistency; Laravel
    uses `_token` on both sides (`Session\Store::token()`
    reads/writes session key `_token`).
  - `Csrf` class implements NO contract — see Missing (11).

  **Phalcon leaks:**
  ➀ **Signature-level leak (concrete):**
    `VerifyCsrfToken::handle(Phalcon\Http\RequestInterface $request,
    \Closure $next): Phalcon\Http\ResponseInterface`.
    Two Phalcon types on a single public signature, same
    shape as A02 Kernel and A05 Response. NOT a new leak
    CLASS — it's inherited from the
    `Contracts\Http\Middleware`/`MiddlewareContract`
    contract leak already recorded in A03 (`Phare\Contracts\Http\Middleware::handle`
    publishes the same Phalcon pair).
  ➁ **Trickle-down: NO Phalcon\Mvc\Application here.**
    Unlike the kernel/route registry, the CSRF middleware
    does not consume `Phalcon\Mvc\Application` /
    `Phalcon\Mvc\Micro` directly.
  ➂ **`Phare\Security\Csrf` IS namespace + inheritance
    Phalcon-clean** — zero `Phalcon\` references in its
    file. Only `Phare\Contracts\Foundation\Application` is
    imported.
  ➃ **TokenMismatchException** is `extends \Exception` —
    fully Phalcon-clean.
  ➄ **Provider-boundary leak NOT applicable here** — there
    is no `Providers/CsrfServiceProvider.php` / `SecurityServiceProvider.php`.
    The middleware and Csrf class are container-auto-resolved
    (constructor injection). This breaks the
    A07/C01/C02/C03/C04 streak (5 consecutive subsystems with
    that exact leak shape). Worth flagging in US-S01 — when
    a subsystem has NO provider, the provider-boundary leak
    class is structurally inapplicable.
  ➅ **DI-slot leak NOT applicable here** — no `singleton('csrf', ...)`
    or `singleton(Csrf::class, ...)` in any provider. Csrf
    is purely auto-resolved.
  ➆ **Helper-return-type leak NOT applicable here** —
    `Phare\Support\helpers.php` has NO `csrf()` /
    `csrf_token()` / `csrf_field()` helpers (these exist in
    Laravel `Foundation/helpers.php`). Missing helpers are
    flagged under (15) below.

  **Defects (correctness/behavioural):**
  - §1 **CSRF token rotation unreachable** — see Missing (9).
    Most severe. APP_KEY rotation / privilege change / login
    do not rotate the token. Same family as C04 §1.
  - §2 **Wire-incompatibility with Laravel SPAs** — see
    Missing (1)/(2). Phare middleware reads `X-XSRF-TOKEN`
    as plaintext where Laravel sends it encrypted. Any
    Laravel-shaped frontend will silently 419.
  - §3 **Triple-stack divergence** — see Missing (14).
  - §4 **Three `make('session')` calls per request** —
    `VerifyCsrfToken::tokensMatch` →
    `Csrf::verifyToken` → `Csrf::getSessionToken` →
    `$this->app->make('session')`; plus
    `Csrf::getToken` and `Csrf::clearToken` each call
    `make('session')` separately. With the C02 §8 wiring
    divergence (`session` vs `session.manager` resolve to
    different SessionManager instances), the middleware
    can read from one session graph while a downstream
    component writes to the other.
  - §5 **`runningUnitTests` skip absent** — see Missing (7).
    Phare tests that send POSTs cannot opt into the
    runtime-tests bypass; they must clutter test bootstrap
    with `addExcept()` calls.
  - §6 **`Csrf::getToken` mints lazily inside a getter** —
    side-effect on read. Laravel's
    `Session\Store::token()` returns `$this->get('_token')`
    and Store mints during `start()`. Phare's getter writes.
    Concurrent requests for the same fresh session can race
    on `storeToken`.
  - §7 **`addExcept` collisions** — instance state, so two
    Kernel boots (Web + Console, or test isolation) each
    rebuild the list independently. No way to declare
    excludes from config.
  - §8 **`tokensMatch` does not verify the session is
    started** — `Csrf::getSessionToken` calls
    `$session->get` which on `Phare\Session\SessionManager`
    (extends `Phalcon\Session\Manager`) will silently start
    the session if not already started, but the eager
    `SessionStoreManager::store()` (C02 §7) already
    arranges that — so the order-of-startup matters. Mark
    coupling.
  - §9 **`field()`/`metaTag()` are emitter-side stubs** —
    OK if rendered from Blade explicitly, but no Blade
    directive (`@csrf`/`@csrf_meta`) wires to them. Laravel's
    `@csrf` directive compiles to `csrf_field()`. A07
    audit already recorded `@csrf` as a missing BladeOne
    compile method.

  **Helpers — Missing (15):**
  - No `csrf_token()`, `csrf_field()` global helpers (Laravel
    `Foundation/helpers.php`). They would be the natural
    invocation surface for `@csrf` / forms. Flag for US-E06.

- Effort: **M**.
  - The middleware skeleton + a clean Csrf helper are already
    in place — the boilerplate is the cheap part.
  - The expensive part is what's MISSING and cross-cuts other
    subsystems:
    (a) Encrypter injection + `X-XSRF-TOKEN` decrypt path —
        blocked on C04 (Encrypter must wire through the
        container as `Phare\Encryption\Encrypter`, not Phalcon
        Crypt, so payload format matches Laravel);
    (b) `XSRF-TOKEN` cookie write-back + the entire response
        cookie subsystem — blocked on a non-existent
        `Phare\Cookie\*` package (same gap noted in C02 for
        the Recaller cookie + `CookieJar::setCookie`);
    (c) Session integration — `Session\Store::token()` /
        `regenerateToken()` are absent (C02 coverage
        18/54). Adding `token`/`regenerateToken` on
        `Phare\Session\SessionManager` is a 2-method addition,
        but it must replace the parallel `Phare\Security\Csrf`
        store path — i.e. delete a class. Architecturally
        Laravel has NO `Csrf` helper class — the token is a
        property of the session, not a separate service.
    (d) Origin verification (Sec-Fetch-Site) +
        `OriginMismatchException` — local, no cross-cut.
    (e) Statics (`except`/`allowSameSite`/`useOriginOnly`/
        `flushState`) + `ExcludesPaths` trait extraction —
        local, no cross-cut.
    (f) BladeOne triple-stack — Stack 3 is part of a vendored
        engine. Either pin a BladeOne fork that suppresses
        its built-in `getCsrfToken`/`regenerateToken`, or
        accept the divergence and disable BladeOne's `@csrf`
        directive. Same family as A07 Stack 1 vs Stack 2.
    (g) Token-rotation surface — needs (c) to land first
        (the rotation must live on Session\Store, not on Csrf).
  - Phalcon leak count for §2 synthesis: 1 signature-level
    leak (➀, inherited via Contracts\Http\Middleware → already
    counted in A03). Subsystem-namespace clean (Csrf +
    TokenMismatchException + VerifyCsrfToken own bodies).
  - Cross-cuts: US-C04 (Encrypter binding + X-XSRF-TOKEN
    decrypt + payload format), US-C02 (Session\Store::token /
    regenerateToken — currently missing in coverage 18/54;
    plus the `session` vs `session.manager` wiring), US-A03
    (Middleware contract leak already recorded — Phalcon types
    inherited by VerifyCsrfToken::handle), US-A07 (BladeOne
    Stack-3 CSRF state + missing `@csrf` Blade directive),
    US-E06 (global helpers `csrf_token()`/`csrf_field()`).
  - Blocked partly on: US-C04 (encrypter), US-C02 (Session\Store
    coverage + token/regenerateToken), and on a NEW
    `Phare\Cookie\*` subsystem that does not exist anywhere
    in the audit corpus yet — same NEW-subsystem block flagged
    in C02 for Recaller.

---

### Rate Limiting

- Current — Phare:
  Four files total. Subsystem namespace `src/Phare/RateLimit/` is
  **Phalcon-clean** by inheritance + namespace (zero `Phalcon\*`
  references); leaks concentrate in the out-of-namespace middleware
  `src/Phare/Middleware/ThrottleRequests.php`. No
  `Providers/RateLimitServiceProvider.php` exists — `RateLimiter` is
  container-auto-resolved via type-hinted ctor (continues the C05
  "no provider" pattern: A07/C01/C02/C03/C04 streak broken at C05,
  continues into C06).
  - **`Phare\RateLimit\RateLimiter`** (138 LOC, 11 own public + 1
    protected `getCache`). Ctor `__construct(Phare\Contracts\Foundation\Application $app)`
    — takes the Application bag, then service-locates the cache via
    `$this->app->make('cache')`. **NO** `Contracts\Cache\Repository`
    constructor injection. Public methods (11):
    `for(string $name, \Closure $callback): static`,
    `attempt(string $key, int $maxAttempts, int $decayMinutes = 1, ?\Closure $callback = null): mixed`,
    `tooManyAttempts(string $key, int $maxAttempts): bool`,
    `hit(string $key, int $decaySeconds = 60): int`,
    `attempts(string $key): int`,
    `resetAttempts(string $key): bool`,
    `remaining(string $key, int $maxAttempts): int`,
    `retriesLeft(string $key, int $maxAttempts): int`,
    `clear(string $key): void`,
    `availableIn(string $key): int`,
    `cleanRateLimiterKey(string $key): string`,
    `limiter(string $name): ?\Closure`,
    `limit(int $maxAttempts): Limit` (a Phare-only factory method
    not on Laravel's RateLimiter). No `InteractsWithTime` trait;
    `currentTime()` / `availableAt()` reimplemented inline as
    protected helpers.
  - **`Phare\RateLimit\Limit`** (60 LOC). Public fields:
    `$maxAttempts: int`, `$decayMinutes: int`, `$key: string`,
    `$responseCallback: ?\Closure`. Ctor
    `__construct(int $maxAttempts = 60, int $decayMinutes = 1)`.
    Six methods total: static `perMinute($maxAttempts)`,
    `perMinutes($decayMinutes, $maxAttempts)`,
    `perHour($maxAttempts)`, `perDay($maxAttempts)`, `none()`;
    instance `by(string $key): static`,
    `response(\Closure $callback): static`. No `perSecond`, no
    `after()`, no `fallbackKey()`, no `$afterCallback` field.
  - **`Phare\RateLimit\TooManyRequestsException`** —
    `extends \RuntimeException` (NOT Laravel's
    `Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException`).
    Hardcoded `parent::__construct($message, 429, $previous)` in
    its own ctor (continues C05 hardcoded-status-code-in-exception
    pattern: `TokenMismatchException` `$code = 419`). One own
    method: `getRetryAfter(): int`.
  - **`Phare\Middleware\ThrottleRequests`** — `implements
    Phare\Contracts\Http\Middleware`. Ctor
    `__construct(Application $app, RateLimiter $limiter)` —
    takes Application bag PLUS RateLimiter (RateLimiter is
    redundant since Application could resolve it, but the
    middleware never actually uses `$this->app`). Public surface
    is one method: `handle(Phalcon\Http\RequestInterface $request,
    \Closure $next, int $maxAttempts = 60, int $decayMinutes = 1,
    string $prefix = ''): Phalcon\Http\ResponseInterface`.
    Protected helpers `handleRequestUsingNamedLimiter`,
    `handleRequest`, `resolveRequestSignature`,
    `resolveMaxAttempts`, `calculateRemainingAttempts`,
    `getTimeUntilNextRetry`, `addHeaders` — all of them type
    parameters/returns on raw `Phalcon\Http\Request` /
    `Phalcon\Http\Response`. No `static using($name)`, no
    `static with($maxAttempts, $decayMinutes, $prefix)`, no
    `static shouldHashKeys(bool)`, no
    `protected static $shouldHashKeys` flag, no `InteractsWithTime`.
  - **Wiring:** no `'rate-limiter'` / `'cache.limiter'` /
    `'rate-limit'` binding in any provider. No
    `RouteServiceProvider::configureRateLimiting()`. No
    `RateLimiter::for(...)` invocation anywhere in `src/` — the
    named-limiter feature is plumbed but never exercised by the
    framework itself.

- Expected — Laravel 13 reference:
  Five files. `Illuminate\Cache\RateLimiter` (322 LOC, 15 public
  methods incl. ctor, uses `InteractsWithTime` trait, ctor takes
  `Illuminate\Contracts\Cache\Repository` — **typed** constructor
  injection). `Illuminate\Routing\Middleware\ThrottleRequests`
  (355 LOC, 5 public — `__construct`, `handle`, static `using`,
  static `with`, static `shouldHashKeys`; plus the
  `protected static $shouldHashKeys = true` flag). `ThrottleRequestsWithRedis`
  extends `ThrottleRequests` (160 LOC, ctor takes
  `Contracts\Redis\Factory`, overrides
  `handleRequest`/`tooManyAttempts`/`hit`/`calculateRemainingAttempts`/`getTimeUntilNextRetry`,
  uses `Redis\Limiters\DurationLimiter`). Three value objects
  under `Cache\RateLimiting\` —
  `Limit` (177 LOC, 6 statics — `perSecond`/`perMinute`/`perMinutes`/`perHour`/`perDay`/`none`
  — plus `by`/`after`/`response`/`fallbackKey`,
  `$decaySeconds: int`, `$afterCallback: ?callable`,
  `$responseCallback: callable`), `GlobalLimit extends Limit`
  (key forced to `''`), `Unlimited extends GlobalLimit`
  (PHP_INT_MAX, middleware short-circuits on `instanceof`).
  Two exceptions — `Http\Exceptions\ThrottleRequestsException
  extends Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException`
  (ctor passes `null` retry-after via Symfony,
  attached headers `Retry-After` + `X-RateLimit-Reset`);
  `Routing\Exceptions\MissingRateLimiterException extends \Exception`
  with `static forLimiter($limiter)` /
  `static forLimiterAndUser($limiter, $model)` factories.

- Gaps:

  **Phalcon\ leaks (3 leak shapes counted, 1 inherited from A03):**
  - ➀ **Inherited contract leak (NOT a new instance — counted at A03).**
    `ThrottleRequests::handle(Phalcon\Http\RequestInterface,
    \Closure, …): Phalcon\Http\ResponseInterface`. The `Phalcon\*`
    types come from `Phare\Contracts\Http\Middleware`, already
    recorded as a contract leak in US-A03. Per the C05 dedupe
    rule ("inherited contract-leak does NOT count as a new
    leak"), do not re-itemise for US-S01.
  - ➁ **Signature-level on protected methods (signature-level
    leak, second confirmed Area-C instance after C05).** Every
    protected helper in `ThrottleRequests` (`handleRequestUsingNamedLimiter`,
    `handleRequest`, `resolveRequestSignature`,
    `resolveMaxAttempts`, `addHeaders`) types its params/returns
    on raw `Phalcon\Http\Request`/`Phalcon\Http\Response`. A
    subclass overriding any of these inherits the Phalcon
    coupling. Severity is below public-surface leak but above
    pure-internal (B02 `eagerLoadRelations` private-param)
    because subclass authors see the leak in their override
    signatures.
  - ➂ **NEW DEFECT CLASS for US-S01 — Service-locator-over-DI
    (NOT a Phalcon-leak by type, but a Wrapper-Rule-adjacent
    coupling).** `RateLimiter::__construct(Application $app)`
    + `$this->app->make('cache')` and
    `ThrottleRequests::__construct(Application $app, RateLimiter)`
    type the **bag-of-services** (`Application`) instead of the
    concrete dependency (`Contracts\Cache\Repository`). Laravel
    `RateLimiter::__construct(Cache $cache)` types the dependency
    directly. Effect: (a) cache dependency is invisible at
    type-check; (b) unit-testing requires booting the container
    not stubbing a Repository; (c) `app->make('cache')` could
    silently return a Phalcon class if the binding changed (the
    binding currently returns a Phare cache; this is a latent
    DI-slot leak risk, not a confirmed leak today). Same shape
    as a half-step toward the C04 DI-slot-leak — flag for
    US-S01 as a new "service-locator-over-DI" defect class.

  **Missing / type mismatches:**

  - **`RateLimiter::attempt()` — same-name/opposite-shape porting
    hazard (11th instance for US-S01).** Phare
    `attempt($key, $maxAttempts, $decayMinutes=1, ?\Closure $callback=null): mixed`
    THROWS `TooManyRequestsException` on rejection. Laravel
    `attempt($key, $maxAttempts, Closure $callback, $decaySeconds=60): mixed`
    RETURNS FALSE on rejection. Two opposite control-flow
    contracts. Plus: arg order differs (Phare puts `$callback`
    LAST and optional; Laravel puts it 3rd and REQUIRED). Plus:
    arg names diverge — **silent 60× unit divergence** (see next
    defect). Running same-name/opposite-shape list now 11 entries
    (B01 create / B02 paginate / B05 rollback / B06 for / B07
    simplePaginate / C01 Authenticatable static-vs-instance /
    C02 login / C03 extend / C04 generateKey / C05 except /
    **C06 attempt**).
  - **NEW DEFECT CLASS for US-S01 — silent unit-divergence.**
    Phare `RateLimiter::attempt($key, $maxAttempts, int
    $decayMinutes=1, ...)` interprets the 3rd arg as MINUTES;
    Laravel `attempt($key, $maxAttempts, Closure, int
    $decaySeconds=60)` interprets the 4th arg as SECONDS. Phare
    `Limit::$decayMinutes` vs Laravel `Limit::$decaySeconds` —
    same divergence on the value object. Phare
    `ThrottleRequests::handle(..., int $decayMinutes=1)` and the
    `handleRequest` loop `$this->limiter->hit($limit->key,
    $limit->decayMinutes * 60)` MULTIPLY by 60; Laravel's
    `decaySeconds` does not. A literal call
    `$limiter->attempt('foo', 5, 60, $cb)` means "60 minutes"
    in Phare and "60 seconds" in Laravel — a 60× silent
    divergence undetectable without integration tests. Same
    family as C03 silent-config-dropthrough but on TIME UNITS,
    not config values. Watch for this pattern on session
    timeouts, queue retry-after, password-reset token TTLs.
  - **`RateLimiter` missing methods (~6 vs Laravel 15).** No
    `increment($key, $decaySeconds, $amount=1)`, no
    `decrement($key, $decaySeconds, $amount=1)`, no
    `withoutSerializationOrCompression(callable)` Redis hook —
    Phare `hit()` is hardcoded `amount=1` (silent-arg-drop family,
    6th instance after B02 `update`/B03 pivots/C01
    `$remember`/C03 silent-config-dropthrough/C05
    `$encrypter`/**C06 `$amount`**). Phare `limiter($name)`
    returns the raw `?\Closure` from `$this->limiters` — Laravel
    wraps the result in a duplicate-key fallback closure that
    rewrites `$limit->key = $limit->fallbackKey()` when multiple
    Limit objects share a key (uses the missing
    `Limit::fallbackKey()` method). With duplicate keys, Phare
    silently double-hits the same cache entry. No `\UnitEnum`
    support on `for($name)` and `limiter($name)` (Phare typed
    `string $name`, Laravel `\UnitEnum|string` — Phare cannot
    accept an enum as a limiter name).
  - **`tooManyAttempts` un-cleaned key (NEW correctness defect,
    same family as B04 un-qualified-column).** Phare reads
    `hasKey($key . ':timer')` where `hasKey` calls
    `$this->getCache()->has($key)` — the raw key is passed
    through without `cleanRateLimiterKey()` first. Laravel:
    `if ($this->cache->has($this->cleanRateLimiterKey($key).':timer'))`.
    Effect: if a key contains HTML entities (e.g. user-controlled
    email with `&amp;`), `tooManyAttempts` looks up a DIFFERENT
    cache key than `hit()` writes to, so the timer lookup misses,
    the `>= $maxAttempts` branch silently calls `resetAttempts`,
    and lockout never engages. Identical bug shape exists in
    Phare `attempts($key)`, `resetAttempts($key)`, `remaining($key,
    ...)`, `clear($key)`, `availableIn($key)` — none of them
    pre-clean the key, but `hit()` writes via
    `$cache->add($key . ':timer', ...)` against the un-cleaned
    key too, so writes and reads are MUTUALLY consistent at the
    raw-key layer. Only `cleanRateLimiterKey()` itself is parity
    — every consumer of it is missing.
  - **`ThrottleRequests` missing methods (~5 vs Laravel + class
    extension).**
    - No `static using($name): string` — used in route
      definitions: `Route::middleware(ThrottleRequests::using('api'))`.
      Phare requires hardcoded `'throttle:api'` strings.
    - No `static with($maxAttempts=60, $decayMinutes=1, $prefix='')`
      — used as `Route::middleware(ThrottleRequests::with(60, 1))`.
    - No `static shouldHashKeys(bool)` + no
      `static $shouldHashKeys = true` flag. Effect: named-limiter
      keys are NEVER hashed at all. Laravel's
      `handleRequestUsingNamedLimiter` does `self::$shouldHashKeys
      ? md5($limiterName.$limit->key) : $limiterName.':'.$limit->key`.
      Phare's version just sets `$limit->key = $prefix . $limit->key`
      or `$prefix . $this->resolveRequestSignature($request)` —
      no namespace prefix on the limiter name at all, so two named
      limiters with the same `Limit::by(...)` value will collide.
      Plus the raw user-controlled identifier ends up in the cache
      key for named limiters (PII-in-cache-key risk).
    - No `Unlimited` short-circuit. Laravel:
      `if ($limiterResponse instanceof Unlimited) return $next($request);`.
      Phare's `Limit::none()` returns a plain `Limit(PHP_INT_MAX)`
      — the middleware still pretends to throttle (calls
      `tooManyAttempts` on `PHP_INT_MAX`, calls `hit`, sets
      headers), wasting cache RTs.
    - No `afterCallback` plumbing. Laravel `Limit::after($cb)`
      sets `$afterCallback`; middleware loops twice — first
      checks `tooManyAttempts`, then ONLY `hit()`s if
      `! $limit->afterCallback`, runs `$next`, then in second
      loop calls `$limit->afterCallback($response)` and hits
      based on the boolean returned. Phare drops this entire
      axis — `hit()` always fires BEFORE `$next($request)` and
      cannot conditionally consume quota based on response. Use
      case "only count failed login attempts as throttled" is
      structurally unreachable.
    - No response-callback dispatch in error path. Phare's
      `Limit::response($callback)` setter EXISTS but is read
      NOWHERE in `Middleware\ThrottleRequests::handleRequest`.
      Laravel `buildException` dispatches
      `$responseCallback($request, $headers)` via
      `HttpResponseException` for custom 429 bodies.
      Confirmed dead-shipped-API (stub-defect family — A04
      Request::route, A05 Response::view, A06 validator
      exists/unique, A07 View::render, B01 castAttribute, B03
      Relation::getRelationExistenceQuery, B05 Migrator::runDown,
      C03 silent-config-dropthrough — now **C06
      Limit::response()**).
    - `addHeaders` always overrides. Laravel returns `[]` when
      the response already has `X-RateLimit-Remaining` less than
      the new value — Phare doesn't check, so downstream
      middleware that sets a tighter `X-RateLimit-Remaining`
      gets overwritten.
  - **`resolveMaxAttempts` divergence.** Phare reads
    `$request->get('authenticated_user')['rate_limit']` —
    `$request->get(...)` is Phalcon's `GET ∪ POST` input bag, NOT
    Laravel's `Request::user()` model accessor. Effect: Phare
    treats a POST-body field literally named `authenticated_user`
    as the source-of-truth — trivially client-spoofable, AND
    unreachable from the actual Auth\Manager. Laravel parses
    `'60|120'` (guest|user, `explode('|')` on `request->user()`
    presence) AND `$user->hasAttribute($attr)` for per-model
    rate limits; AND throws `MissingRateLimiterException` if
    nothing is numeric.
  - **`resolveRequestSignature` divergence.** Phare:
    `if ($user = $request->get('user')) { return sha1($user); }
    return sha1($request->getClientAddress().'|'.$request->getURI());`
    — uses Phalcon input bag for user (same spoofability as
    above), uses `getClientAddress()` (Phalcon-only) + full URI
    (changes per query-string, so the cache key explodes
    cardinality on requests with random query params). Laravel:
    `request->user()->getAuthIdentifier()` (authoritative auth
    layer) or `route->getDomain().'|'.request->ip()` (route is
    optional). Phare can never throw "Unable to generate request
    signature" — silently always returns sha1 of something.
  - **Missing exceptions.** `Illuminate\Http\Exceptions\ThrottleRequestsException
    extends Symfony\…\TooManyRequestsHttpException` is absent —
    Phare has `RateLimit\TooManyRequestsException extends
    \RuntimeException`, hardcoded `$code = 429` in its ctor (same
    pattern as C05 `TokenMismatchException` hardcoding 419,
    flagged in US-S01 as "hardcoded HTTP status codes inside
    Exception classes"). `Routing\Exceptions\MissingRateLimiterException`
    is absent — Phare throws raw `\RuntimeException("Rate
    limiter [{$limiterName}] is not defined.")` from
    `handleRequestUsingNamedLimiter`. Effect: error handlers
    can't distinguish "limiter undefined" from any other
    runtime error (same defect class as B01 firstOrFail
    throwing Phalcon's `Mvc\Model\Exception` not Laravel's
    `ModelNotFoundException`).
  - **`Retry-After` header dropped on 429.** Phare's
    `TooManyRequestsException` stores `$retryAfter` and exposes
    `getRetryAfter()` — but `Middleware\ThrottleRequests`
    constructs the exception via `throw new TooManyRequestsException('Too
    many attempts', $this->getTimeUntilNextRetry($limit->key))`
    and then **DOES NOTHING WITH IT** at the middleware boundary.
    No `Retry-After` response header, no `X-RateLimit-Reset`
    header (Phare only sets these in `addHeaders` when
    `$retryAfter` param is non-null — but `addHeaders` is only
    called on the SUCCESS path with `$retryAfter` defaulting
    null). Laravel's `buildException` calls `getHeaders(...,
    $retryAfter)` BEFORE throwing and the resulting
    `TooManyRequestsHttpException` carries the headers through
    the exception handler. Effect: a Phare 429 response has no
    way for clients to know when to retry — unless a project's
    exception handler reads `$e->getRetryAfter()` manually,
    which Phare doesn't ship.
  - **`Limit` value-object gaps (4 missing methods, 1 missing
    field, 1 missing subclass tree).** No `perSecond($maxAttempts,
    $decaySeconds=1)`, no `after(callable)`, no `fallbackKey()`,
    no `$afterCallback` field. `none()` returns plain
    `Limit(PHP_INT_MAX)` not the `Unlimited` subclass. No
    `GlobalLimit` (key forced to `''`), no `Unlimited` (PHP_INT_MAX
    + middleware short-circuit). `response()` accepts `\Closure`
    only (Laravel: `callable`) — porting hazard for code that
    passes a `[$obj, 'method']` callable.
  - **`Middleware\ThrottleRequests` named-limiter return-type
    handling (correctness defect).** Phare's
    `handleRequestUsingNamedLimiter` calls
    `call_user_func($limiterCallback, $request)` then wraps in
    array if non-array. Laravel checks the result for
    `instanceof Response` (returns it directly as the limiter's
    chosen 429 body) AND `instanceof Unlimited` (short-circuit).
    Phare has neither check — if a limiter returns a Response,
    Phare tries to access `$response->key` and `$response->maxAttempts`
    on it, fatal-error-ing in the request lifecycle.
  - **`ThrottleRequestsWithRedis` missing entirely.** No
    Redis-aware variant ships. `DurationLimiter` is absent.
    Effect: high-concurrency apps with a Redis cache backend
    cannot use atomic LUA-based rate limiting; the
    `cache->add`/`cache->increment` two-step in `RateLimiter::hit`
    is non-atomic across concurrent requests (the C04 silent
    write-skew family — two parallel requests can both pass
    `tooManyAttempts` and both `hit`, over-counting then
    under-locking; or both miss the `add` and tie on
    `$hits == 1`).
  - **No-contracts pattern continues (10th subsystem).** No
    `Phare\Contracts\Cache\RateLimiter` interface. RateLimiter
    publishes no contract. Same as A06/A07/B06/B07/C01/C02/C03/C04/C05
    — record as inherited, do not re-narrate.
  - **No provider, no binding.** No
    `Providers/RateLimitServiceProvider.php`. No `'cache.limiter'`
    / `'rate-limiter'` binding. Laravel binds `RateLimiter` as
    a container singleton in `CacheServiceProvider` and aliases
    it. Phare relies on type-hinted constructor injection (which
    auto-resolves a NEW RateLimiter per resolution — `for()`-
    registered limiters are LOST across resolutions since
    `$this->limiters` is instance state). Same "no provider"
    pattern as C05.
  - **`RateLimiter::limit(int)` is a Phare-only factory.** No
    Laravel counterpart. It just `new Limit($maxAttempts)` —
    duplicates `Limit::perMinute($maxAttempts)`. Dead surface
    in the porting direction (Laravel→Phare); on the
    Phare→Laravel direction, app code calling it breaks.
  - **`tooManyAttempts` reset-on-no-timer side effect (NEW for
    US-S01 — silent-reset defect).** Phare matches Laravel here:
    `if (attempts >= max) { if (!hasKey(timer)) resetAttempts(); }`.
    Both behave the same — but Phare's MISSING
    `cleanRateLimiterKey` in `hasKey` (above) effectively
    GUARANTEES this reset fires on every >= max check for any
    key with HTML entities. Combined effect: `tooManyAttempts`
    not only returns false (no lockout), but ALSO resets the
    counter — the bug is "fail-open AND wipe history". Worse
    than B04's un-qualified-column (which just errors loudly on
    joins).

- Effort: **M**:
  Small surface (3 RateLimit files ~210 LOC + 1 middleware
  ~135 LOC), but five families of work: (1) typed-DI rewire of
  RateLimiter ctor (`Contracts\Cache\Repository` injection),
  (2) unit-correction `$decaySeconds` across RateLimiter +
  Limit + ThrottleRequests (silent 60× divergence — mechanical
  but app-breaking), (3) backfill missing API (`increment`/
  `decrement`/`withoutSerializationOrCompression`/`Unlimited`/
  `GlobalLimit`/`fallbackKey`/`after`/`perSecond`/
  `ThrottleRequests::using`/`with`/`shouldHashKeys` +
  `afterCallback` plumbing + `responseCallback` dispatch + duplicate-key
  wrap on `limiter()`), (4) Phare-shaped `ThrottleRequestsException`
  + `MissingRateLimiterException` (Symfony-extending +
  static-factory shaped), (5) `ThrottleRequestsWithRedis` is a
  whole atomic-Redis path (likely blocked on US-D01 Queue audit
  for `DurationLimiter` equivalent). Plus correctness fix:
  `cleanRateLimiterKey` pre-call across `tooManyAttempts`,
  `attempts`, `resetAttempts`, `remaining`, `clear`, `availableIn`,
  `hit` write side. Cross-cuts US-A03 (Middleware contract leak
  is the source of leak ➀), US-A04 (Phare Request lacks
  `user()`/`route()` — `resolveRequestSignature` cannot be
  ported without a Laravel-shaped Request), US-C01 (Auth
  guard's `user()->getAuthIdentifier()` is the correct
  identifier source — currently Phare reads input-bag), US-D01
  (queue Redis path shares DurationLimiter), and US-S01
  synthesis (3 NEW defect classes: service-locator-over-DI,
  silent-unit-divergence, silent-reset). Blocked partly on:
  US-C01 (`request->user()` shape), US-A04 (Request::route()
  is currently a stub returning null — flagged in A04), and a
  proper `Contracts\Cache\Repository` shape (Phare cache contract
  audit is outside Areas A–E and would land in a future Area F
  if Cache is in-scope).

### Password Reset

- Current — Phare:
  ONE file total. `src/Phare/Auth/Passwords/PasswordBroker.php`
  — 88 LOC, 3 own public methods. No `PasswordBrokerManager`, no
  `DatabaseTokenRepository`, no `TokenRepositoryInterface`, no
  `Contracts\Auth\PasswordBroker`, no `CanResetPassword` trait,
  no `ResetPassword` notification, no `PasswordResetServiceProvider`.
  Subsystem-namespace is **NOT Phalcon-clean** (first Area-C
  subsystem since C01 to fail at namespace level): the ctor imports
  `Phalcon\Di\Di;` and calls `Di::getDefault()` as a static
  service-locator inside `__construct`, then resolves dbManager →
  PDO directly. `class PasswordBroker` (no `implements`, no
  `extends`). Ctor `__construct(int $expireMinutes = 60)` — takes
  ONLY the expiry; the DB connection is service-located, NOT
  injected. Public methods (3):
  `createToken(object $user): string`,
  `validateToken(string $email, string $token): bool`,
  `deleteToken(string $email): void`.
  - `createToken(object $user)` reads `$user->email` as a raw
    property access (no trait method, no `getEmailForPasswordReset()`
    call). Generates `bin2hex(random_bytes(32))` (64-char hex).
    Deletes any existing row for the email, then INSERTs the token
    **VERBATIM AS PLAINTEXT** into `password_reset_tokens (email,
    token, created_at)`. No `Hash::make($token)` step, no
    `Contracts\Hashing\Hasher` dependency.
  - `validateToken(string $email, string $token)` SELECTs `token,
    created_at`, `hash_equals($row['token'], $token)`, then
    `strtotime($row['created_at']) + ($expireMinutes * 60) >=
    time()`. Returns bool. Does NOT delete on success, does NOT
    rate-limit, does NOT fire an event, does NOT throw on failure.
  - `deleteToken(string $email)` — single DELETE by email. No
    `deleteExpired()` companion, no maintenance cron hook.
  - Hardcoded table name `password_reset_tokens` everywhere
    (4 SQL strings). Hardcoded connection name `'db'` via
    `$dbManager->getConnectionService('db')`. Hardcoded default
    `$expireMinutes = 60`. NO `config/auth.php` `passwords`
    block read anywhere.
  - **Wiring:** zero. `grep -rn "PasswordBroker\|password\.broker\|sendResetLink\|ResetPassword" src/Phare/Providers/ tests/` returns
    nothing. The class is never bound in any provider, never
    constructed by any other Phare class, and has zero test
    coverage. Pure orphan subsystem (same "orphaned/unwired"
    defect class as B07 Pagination — second confirmed instance).

- Expected — Laravel 13 reference:
  Eight+ files across `Illuminate\Auth\Passwords\` +
  `Illuminate\Contracts\Auth\` + `Illuminate\Auth\Notifications\`:
  - `Illuminate\Auth\Passwords\PasswordBroker implements
    Contracts\Auth\PasswordBroker` (~340 LOC). Constants from the
    contract: `RESET_LINK_SENT`, `PASSWORD_RESET`, `INVALID_USER`,
    `INVALID_TOKEN`, `RESET_THROTTLED`. Public methods (8):
    `sendResetLink(array $credentials, ?Closure $callback = null): string`,
    `reset(array $credentials, Closure $callback): mixed`,
    `createToken(CanResetPassword $user): string`,
    `deleteToken(CanResetPassword $user): void`,
    `tokenExists(CanResetPassword $user, string $token): bool`,
    `getRepository(): TokenRepositoryInterface`,
    `getUser(array $credentials): ?CanResetPassword`,
    `validator(Closure $callback): void`. Ctor:
    `__construct(TokenRepositoryInterface $tokens, UserProvider $users,
    ?Dispatcher $dispatcher = null)` — **typed** ctor injection of
    TokenRepository + UserProvider + Events Dispatcher.
  - `Illuminate\Auth\Passwords\PasswordBrokerManager implements
    Contracts\Auth\PasswordBrokerFactory` — multi-broker factory:
    `broker(?string $name = null): PasswordBroker`, `resolve($name)`,
    `createTokenRepository(array $config)`, `getDefaultDriver()`,
    `setDefaultDriver(string $name)`. Mirrors AuthManager shape.
  - `Illuminate\Auth\Passwords\DatabaseTokenRepository implements
    TokenRepositoryInterface` — `create(CanResetPassword $user)`,
    `exists($user, $token)`, `recentlyCreatedToken(CanResetPassword
    $user): bool` (THROTTLE — default 60s), `delete($user)`,
    `deleteExpired(): void`, `tokenExpired($createdAt)`,
    `tokenRecentlyCreated($createdAt)`, `getTable`, `getConnection`,
    `getHasher`, `getHashKey`. **Tokens are HASHED** via
    `Contracts\Hashing\Hasher::make($token)` at insert,
    `Hash::check($plain, $row->token)` at exists().
  - `Illuminate\Auth\Passwords\TokenRepositoryInterface` — public
    contract (6 methods: create/exists/recentlyCreatedToken/delete/
    deleteExpired plus `getHasher`).
  - `Illuminate\Contracts\Auth\PasswordBroker` + `PasswordBrokerFactory`
    — contracts (2nd contract NEVER published by Phare).
  - `Illuminate\Contracts\Auth\CanResetPassword` interface + the
    `Illuminate\Auth\Passwords\CanResetPassword` trait — two
    public methods: `getEmailForPasswordReset()`,
    `sendPasswordResetNotification(string $token)`.
  - `Illuminate\Auth\Notifications\ResetPassword` notification —
    `toMail($notifiable)` builds a `Notifications\Messages\MailMessage`
    with a SIGNED `password.reset` route URL (TTL = `passwords.<broker>.expire`
    minutes). Static `createUrlUsing(?Closure)` /
    `toMailUsing(?Closure)` customisation hooks.
  - `Illuminate\Auth\Passwords\PasswordResetServiceProvider extends
    ServiceProvider implements DeferrableProvider` — registers
    `'auth.password'` singleton (the PasswordBrokerManager) and
    `'auth.password.broker'` (default broker). `provides(): array`
    returns both.
  - `config/auth.php` passwords block: `provider`, `table`,
    `expire` (minutes), `throttle` (seconds), per-broker.
  - Events: `Illuminate\Auth\Events\PasswordReset` fired by `reset()`
    on success.

- Gaps:

  **Phalcon\ leaks (1 new leak shape — subsystem-namespace fails):**
  - ➀ **NEW DEFECT CLASS for US-S01 — Phalcon-DI-facade-as-service-locator
    (6th leak class for the audit).** PasswordBroker imports
    `Phalcon\Di\Di;` and calls `Di::getDefault()` inside `__construct`.
    This is the FIRST Area-C subsystem since C01 where the
    SUBSYSTEM NAMESPACE itself fails the Phalcon-clean check
    (C02/C03/C04/C05/C06 all clean at namespace level). Distinct
    from the C06 §3 "service-locator-over-DI" defect (C06 typed
    `Phare\Contracts\Foundation\Application` as the bag — at
    least the Application is a Phare type). Here the bag IS
    a raw Phalcon class, called statically, with no Phare wrapper
    in between. Repository-wide: this exact `use Phalcon\Di\Di;`
    + `Di::getDefault()` pattern occurs in **7** files
    (`Auth/Passwords/PasswordBroker.php`, `Testing/TestCase.php`,
    `Support/helpers.php`, `Container/Container.php`,
    `Eloquent/Concerns/HasEvents.php`,
    `Eloquent/Concerns/HasAttributes.php`,
    `Console/Config.php`) — only the PasswordBroker instance
    falls inside Areas A–E and is counted here; the other 6 are
    out-of-scope but worth a US-S01 footnote because removing
    them is a prerequisite for any Phalcon-DI removal.

  **Missing / type mismatches:**

  - **TOKEN STORED PLAINTEXT — CRITICAL security defect (NEW
    Area-C security-family entry, joins C03 default-rounds, C03
    `#[\SensitiveParameter]` absence, C03 infinite-rehash, C04
    key-rotation, C04 default-cipher, C05 wire-incompat).**
    Phare INSERTs the raw bin2hex token VERBATIM into
    `password_reset_tokens.token`. A read-only DB compromise
    (backup leak, replica access, SQL injection on any other
    table) yields working reset tokens for every user with an
    outstanding reset. Laravel's `DatabaseTokenRepository::create`
    calls `Contracts\Hashing\Hasher::make($token)` before INSERT
    and `Hash::check($plain, $row->token)` at validate time, so
    DB compromise yields opaque bcrypt hashes. **Severity: blocks
    any production use of PasswordBroker.** Compounded by C03
    hashing defects: even if Phare added Hash::make here, the
    bcrypt rounds default is 10 (vs Laravel 12) — but plaintext
    is the dominant problem.
  - **Token throttling absent (security defect, second instance
    of the "no rate-limit on credential surface" family).** No
    `recentlyCreatedToken` check, no `RESET_THROTTLED` status,
    no `throttle` config key, no `Limit::perMinute` integration.
    `createToken()` DELETEs the previous row and INSERTs a fresh
    one on every call — an attacker harvesting emails can flood
    the password-reset notification queue at unlimited rate (DoS
    + email-reputation damage). Same security-shape as C06
    ThrottleRequests' missing `MissingRateLimiterException`
    flow but for credential issuance instead of generic routes.
  - **Wholesale missing — orchestration surface (~14 absent
    methods).** Phare ships 3 public methods; Laravel ships 8
    on the broker + the manager + the repository. Specifically
    missing on Phare's broker: `sendResetLink($credentials,
    ?Closure $callback = null)` (the entire user-lookup +
    notification dispatch flow), `reset($credentials, Closure
    $callback)` (the entire commit flow that takes a closure,
    persists the new password via the callback, fires
    `Auth\Events\PasswordReset`, and DELETEs the token on
    success), `tokenExists($user, $token)` (alias for validate
    with the right shape), `getRepository()`, `getUser($credentials)`,
    `validator(Closure)`. Phare's `validateToken` returns bool
    only — Laravel's `reset()` returns one of the 5 status
    constants. NEW for US-S01: same "ship the data layer but
    skip the orchestration" defect as the B07 Pagination
    orphan but on a security-critical surface.
  - **`createToken` same-name/opposite-shape porting hazard
    (12th instance for US-S01).** Phare `createToken(object
    $user): string` accepts **any object** with a public
    `->email` property; Laravel `createToken(CanResetPassword
    $user): string` types the contract interface and calls
    `$user->getEmailForPasswordReset()`. (a) Phare accepts a
    DTO/stdClass — Laravel requires the model implement the
    contract. (b) Phare bypasses the `getEmailForPasswordReset()`
    indirection — any model whose login key isn't literally
    `$user->email` (e.g. `username`, `login`, computed
    `primary_email`) silently fails the lookup. Running
    same-name/opposite-shape list now 12 entries (B01/B02/
    B05/B06/B07/C01/C02/C03/C04/C05/C06/**C07**).
  - **`validateToken` same-name/opposite-shape (13th instance).**
    Phare `validateToken(string $email, string $token): bool`
    takes **two strings**; Laravel `tokenExists(CanResetPassword
    $user, string $token): bool` takes **the user model**.
    Effect: Phare's validate path NEVER touches the user
    provider — a stale email row in `password_reset_tokens`
    keeps validating even if the user was deleted from `users`.
    Laravel re-resolves the user from the provider, breaking
    the link on user deletion. Same family as C02's
    `viaRemember` shape divergence — security-relevant in
    the C04/C05/C07 cluster.
  - **Silent-config-dropthrough family — 7th instance** (C03
    hashing config, C04 cipher config, plus C03 driver factories
    × 2, C05 csrf-cookie config, C06 throttle config; now
    **C07 passwords config**). NO read of `config('auth.passwords.users.expire')`,
    `config('auth.passwords.users.throttle')`,
    `config('auth.passwords.users.table')`,
    `config('auth.passwords.users.connection')`. The ctor
    sig says `int $expireMinutes = 60` and that's the entire
    config surface. Laravel reads all four from the
    per-broker `users` (or `customer`, etc.) sub-block.
  - **Silent-arg-drop family — 7th instance** (B02 `update`,
    B03 pivots × 2, C01 `$remember`, C03 driver config, C05
    `$encrypter`, C06 `$amount`; now **C07 hasher
    dependency**). `PasswordBroker::__construct(int
    $expireMinutes = 60)` drops both the `TokenRepositoryInterface`
    AND the `UserProvider` AND the `Dispatcher` arguments
    Laravel's ctor declares. The "hasher dropped" sub-case
    is the most severe because it directly enables the
    plaintext-storage defect above.
  - **Wrapper-Rule connection-name hardcoding (NEW correctness
    defect, same family as C03 connection naming).** Phare
    hard-codes `$dbManager->getConnectionService('db')` —
    on a multi-DB app where `users` lives in a non-`'db'`
    connection (e.g. `mysql_users`), every reset attempt
    SILENTLY hits the wrong connection (or throws on a
    missing `password_reset_tokens` table). Laravel's
    `DatabaseTokenRepository` accepts `$connection` from the
    config block and calls `$db->connection($this->connection)`.
  - **Contracts gap — 11th no-contracts subsystem in the
    audit** (A06/A07/B06/B07/C01/C02/C03/C04/C05/C06/**C07**).
    No `Phare\Contracts\Auth\PasswordBroker`,
    `PasswordBrokerFactory`, `TokenRepositoryInterface`,
    `CanResetPassword`. Status constants `RESET_LINK_SENT`/
    `PASSWORD_RESET`/`INVALID_USER`/`INVALID_TOKEN`/
    `RESET_THROTTLED` simply do not exist — any port that
    expects these (`PasswordController` returning `back()->with('status',
    Password::RESET_LINK_SENT)`) silently dies on a missing
    constant.
  - **No notification integration.** Missing `CanResetPassword`
    trait (`getEmailForPasswordReset` / `sendPasswordResetNotification`),
    missing `ResetPassword` notification class, missing signed-URL
    route registration. A port that scaffolds `$user->sendPasswordResetNotification($token)`
    hits a non-existent method on the User model. Composes with
    A07 (no Blade `@can`/`@route`) and C04 (no encrypter for
    signed-URL signatures) to block the full reset flow even
    if PasswordBroker were patched.
  - **No `PasswordResetServiceProvider`** (continues C05's
    "no provider" mini-streak after C06 also had no provider —
    pattern: subsystems shipped late/incomplete tend to lack
    providers entirely; subsystems shipped as Phalcon-clean
    wrappers tend to have providers but with provider-boundary
    leaks). No `'auth.password'` / `'auth.password.broker'` /
    `'auth.password.tokens'` bindings → `app('auth.password')`
    throws on resolve.
  - **No event firing.** Laravel's `reset()` fires
    `Auth\Events\PasswordReset` post-commit. Phare has no
    `reset()` to begin with, but even the `deleteToken`
    completion event is silent. Composes with the C01-flagged
    missing event taxonomy (Login/Logout events on AuthManager).
  - **No `deleteExpired()` maintenance hook.** Laravel ships
    `php artisan auth:clear-resets` (the
    `ClearResetsCommand` calls `deleteExpired()` on every
    broker). Phare has no command and no method — stale tokens
    accumulate forever. Composes with E02 console-command audit
    (the artisan parity gap).

  **Same-name-different-shape running list now: 13 entries
  (B01/B02/B05/B06/B07/C01/C02/C03/C04/C05/C06/C07×2).**
  **Silent-arg-drop family: 7 instances.**
  **Silent-config-dropthrough family: 7 instances.**
  **No-contracts streak: 11 consecutive subsystems.**
  **Orphaned/unwired subsystem: 2nd instance after B07.**
  **Phalcon-DI-facade-as-service-locator: NEW 6th leak class.**

- Effort: **L.** Smallest subsystem-file-count in
  Area-C (1 file, 88 LOC, 3 methods) but the missing surface
  is the largest of Area-C so far: a full 8-class subsystem
  + 2 contracts + 1 trait + 1 notification + 1 provider need
  to be rebuilt from scratch, AND the existing file has a
  CRITICAL security defect (plaintext token storage) that
  blocks production use today. Port path: (1) introduce
  `Contracts\Auth\{PasswordBroker,PasswordBrokerFactory,
  CanResetPassword}` + `Auth\Passwords\TokenRepositoryInterface`
  with the 5 status constants; (2) write
  `DatabaseTokenRepository` that calls
  `Contracts\Hashing\Hasher::make()` at insert (depends on the
  C03 Hashing port shipping `Hash` facade + binding); (3) write
  the broker with `sendResetLink`/`reset` orchestration (depends
  on C04 Encrypter for signed URLs + a Mail/Notification subsystem
  not yet audited — likely Area D); (4) write `CanResetPassword`
  trait + `ResetPassword` notification; (5) write
  `PasswordResetServiceProvider` + `'auth.password*'` bindings;
  (6) keep the legacy `PasswordBroker` class only as a
  deprecation shim if any downstream app uses it. Dependencies
  on un-audited subsystems (Notifications, Mail, Events
  dispatcher) raise the effort to L despite the tiny existing
  footprint.

### Sanctum / Passkeys (scope decision)

This subsection records the **scope decision** required by US-C08
(per `docs/completion-criteria.md` §3 + §7). It is NOT a full audit.

- Current — Phare:
  Two subsystems exist under `src/Phare/Auth/`:
  - **`src/Phare/Auth/Sanctum/`** (6 files + Middleware/):
    `Sanctum.php` (104 LOC, 9 static methods: `usePersonalAccessTokenModel`,
    `personalAccessTokenModel`, `actingAs`, `createToken`,
    `generateTokenString` (protected), `findToken`, `hasValidToken`,
    `ignoreMigrations`, `defaultTokenExpiration`. Token storage uses
    `hash('sha256', $plainText)` — NOT plaintext; severity below
    C07 PasswordBroker), `SanctumGuard.php` (86 LOC, 7 pub:
    `__construct`/`user`/`validate`/`check`/`guest`/`id`/`setUser`
    — `setUser` is a no-op stub on the API guard),
    `PersonalAccessToken.php` model + `NewAccessToken.php` value
    object, `HasApiTokens.php` trait, `SanctumServiceProvider.php`
    provider, `Middleware/` directory.
  - **`src/Phare/Auth/Passkeys/`** (7 files):
    `ChallengeStore.php` (interface), `InMemoryChallengeStore.php`
    (default impl), `PasskeyAssertionVerifier.php` (interface),
    `PasskeyAuthenticator.php` (72 LOC — `begin`/`verify`),
    `PasskeyCredentialRepository.php` (interface),
    `PasskeyRegistrar.php`, `PasskeyRegistrationVerifier.php` (interface).
    Subsystem-namespace is Phalcon-clean (zero `Phalcon\*` refs).

- Expected — Laravel 13 reference:
  - **`/opt/laravel-framework/src/Illuminate/Auth/` (the §3 reference
    path for Area C) ships NEITHER subsystem.** Laravel Sanctum is
    the separate `laravel/sanctum` composer package
    (`vendor/laravel/sanctum/src/`); it is an official Laravel
    package but not part of Illuminate. Laravel core ships **no**
    passkey/WebAuthn subsystem at all (third-party packages
    exist — e.g. `webauthn/webauthn-lib` — but none under
    `laravel/*`).
  - **`§3 Parity Scope` explicitly bounds the reference to
    Illuminate.** Sanctum and passkeys therefore have no
    in-scope reference to diff against under the C-area rubric.

- Gaps: none recorded here — this section defers the
  diff per the §7 decision below. One Phalcon leak is noted
  inline for US-S01 traceability:
  - **`SanctumGuard::__construct(Phalcon\Http\RequestInterface $request, ...)` —
    constructor-injected raw `Phalcon\Http\RequestInterface`.**
    This is a NEW Wrapper-Rule violation (constructor-level
    Phalcon-type publication on a public class). Distinct from
    the A03 contract-leak shape because the leak is on a
    concrete class ctor, not on a contract method. Logged for
    US-S01 §7-adjacent visibility — porting Sanctum (whether
    inside or outside the milestone) must remove this. Passkeys
    namespace is Phalcon-clean by inspection.

- **Decision: BOTH MOVE TO §7 (out of scope for this parity
  milestone).** Rationale recorded below; a follow-up edit to
  `docs/completion-criteria.md` §7 enumerates the two
  subsystems as deferred items.

- Rationale:
  1. **§3 reference-source constraint.** `completion-criteria.md`
     §3 binds the measured Laravel subset to
     `/opt/laravel-framework/src/Illuminate/`. Neither
     `laravel/sanctum` nor any passkey subsystem ships in
     Illuminate. The Wrapper-Rule + 1:1-diff methodology that
     governs Areas A-E cannot be applied without an
     in-tree reference.
  2. **Sanctum is an out-of-tree official package.** A future
     parity pass could declare a "Phare-Sanctum vs laravel/sanctum"
     diff as a separate milestone (proposed: "Area F — Official
     Laravel packages"). For this milestone, treat Phare's Sanctum
     as a Phare-original implementation and audit nothing.
  3. **Passkeys are Phare-original.** No Laravel core or
     official-package reference exists. No diff is meaningful;
     audit effort would be pure code review, which is outside the
     "parity audit" remit.
  4. **Existing implementation is non-trivial but consistent
     with the rest of Phare auth.** Phare's Sanctum mirrors
     `laravel/sanctum`'s public shape (`Sanctum::actingAs`,
     `Sanctum::createToken`, `HasApiTokens` trait,
     `PersonalAccessToken` model, sha256-hashed token storage).
     A future port would be a "package adoption" task, not a
     parity-gap rebuild — different operating model from C01-C07.

- Forward-visibility (US-S01 inputs, recorded but not counted
  in the parity §1-§6 inventory):
  - `SanctumGuard` ctor publishes `Phalcon\Http\RequestInterface`
    — same Wrapper-Rule break as A03/A04/C05/C06, but on a §7
    subsystem so it does not change the parity totals.
  - Phare Sanctum uses sha256 token hashing (better than C07
    PasswordBroker's plaintext); HOWEVER, sha256 is unsalted and
    NOT a password-hashing function — a constant-time comparison
    via `hash_equals` is in place, but timing-equivalence does
    not protect against rainbow-table attack on the (token, hash)
    pairs at rest. Laravel sanctum stores sha256 too — same
    upstream shape — so this is informational, not a defect
    relative to laravel/sanctum.
  - `Sanctum::generateTokenString()` concatenates
    `config('app.key') . time() . bin2hex(random_bytes(32))` —
    the `config('app.key')` prefix is redundant (the random
    32-byte suffix already supplies the entropy). The plaintext
    holder cannot recover the key (the output is hashed before
    storage and never returned unhashed), but the intent diverges
    from laravel/sanctum's plain `Str::random(40)`. Log for §7
    cleanup-pass visibility only.
  - Passkeys: subsystem looks structurally complete but is
    completely unbound — `grep -rn 'PasskeyAuthenticator\|PasskeyRegistrar' src/Phare/Providers/`
    returns nothing. Orphan status matches B07 / C07 pattern
    but is out of parity scope.

- Effort: **0 (decision-only).** No audit work
  performed; ~80 LOC of audit narrative recorded as a scope
  decision and a forward-visibility log for US-S01.


