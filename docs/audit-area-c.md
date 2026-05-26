# Audit — Area C: Auth, Sessions & Security

**Laravel 13 reference:** `/opt/laravel-framework` @ `13.2.0` (read-only)
**Phare package:** `phare/framework`, namespace `Phare\`, `src/Phare/`
**Method:** per-subsystem 1:1 public-API diff vs Laravel 13 + Wrapper Rule (§2) grep.
**Scope:** read-and-record only — no framework source edited.

---

### AuthManager

- 現状 (Current) — Phare:
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
    two are `static` in Phare vs instance in Laravel; see §差分).
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

- 期待 (Expected) — Laravel 13:
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

- 差分 (Gaps):
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

- 工数感 (Effort: **L**):
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
