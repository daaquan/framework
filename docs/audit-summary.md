# Phare — Laravel 13 Parity Audit: Cross-Area Synthesis

**Status:** complete audit pass; **stops here for human review.**
**Reference:** Laravel 13.2.0 at `/opt/laravel-framework/src/Illuminate/`
**Areas audited:** A (Core Web Stack) · B (Database & ORM) · C (Auth/Session/Security) · D (Async & Extras) · E (Foundation)
**Stories:** US-A01–A07 · US-B01–B07 · US-C01–C08 · US-D01–D06 · US-E01–E07

---

## §1 — Phalcon Leak Taxonomy

Six distinct leak forms found across the codebase. Ordered by fix complexity:

| # | Form | Canonical example | Fix shape |
|---|------|-------------------|-----------|
| L1 | **Structural inheritance** | `Model extends Phalcon\Mvc\Model`, `Container extends Phalcon\Di\Di`, `Builder extends Phalcon\Mvc\Model\Criteria`, `Relation extends Phalcon\Mvc\Model\Relation`, `SessionManager extends Phalcon\Session\Manager` | Composition-over-inheritance; introduce a Phare wrapper/proxy |
| L2 | **Contract extension** | `Contracts\Http\Kernel extends Phalcon\Http\*`, `BuilderInterface extends CriteriaInterface`, `Contracts\Http\Response extends Phalcon\Http\ResponseInterface`, `Contracts\Session\Session extends Phalcon\Session\ManagerInterface` | Rewrite contract to extend only Phare/PSR interfaces |
| L3 | **Published-dependency** | `SchemaBuilder::__construct(AbstractPdo)`, `ServiceProvider::__construct(Application\|DiInterface)`, `Manager::__construct(Session, ConfigInterface, ...)`, `HashServiceProvider::register(Application\|DiInterface)` | Replace with Phare wrapper parameter (Connection, Config\Repository, Foundation\Application) |
| L4 | **Provider-boundary** | `AuthServiceProvider implements Phalcon\Di\ServiceProviderInterface`, `BladeViewProvider`, `SessionProvider`, `HashServiceProvider`, `EncrypterProvider`, `MailServiceProvider` (7+ providers) | `implements` clause removed; switch to Phare\Support\ServiceProvider pattern |
| L5 | **DI-slot** | `EncrypterProvider` binds `'encrypter'` → `Phalcon\Encryption\Crypt`; `Container::$reservedServices` maps 23 slots to Phalcon types | Wire the Phare class (`Phare\Encryption\Encrypter`) to the slot |
| L6 | **Phalcon-DI-facade-as-service-locator** | `PasswordBroker::__construct` uses `Phalcon\Di\Di::getDefault()`, `HasEvents` uses `Di::getDefault()`, `QueueManager` imports `Phalcon\Config\Config`, `D02 HasEvents`/`D03`/`D05` providers, `Auth\Manager::__call` → `Di` fallback | Replace with constructor injection or container-resolved dependency |

Additional recurring form (not a Wrapper-Rule leak per se but tracked):
- **L7 — Helper-return-type leak**: `encrypter(): Crypt`, `security(): mixed` (real return is Phalcon Security). Fix: rewire DI slot (L5) then return-type becomes correct.

**Leak hotspots by area:**

| Area | Clean namespaces | Leaked (any form) |
|------|-----------------|-------------------|
| A | A06 Validation | A02 Kernel, A03 Middleware interfaces, A04 Request, A05 Response, A07 BladeViewProvider |
| B | B04 Soft Deletes, B07 Pagination | B01 Model (L1), B02 Builder (L1+L2), B03 Relations (L1+L2), B05 Schema (L3×4), B06 Seeders (L3) |
| C | C03 Hashing namespace, C04 Encryption namespace, C05 CSRF namespace, C06 RateLimit namespace | C01 Auth (L3+L4+L6×2), C02 Session (L1+L2), C03 HashServiceProvider (L3+L4), C04 EncrypterProvider (L4+L5), C05 VerifyCsrfToken signature (L1-inherited), C07 PasswordBroker (L6) |
| D | D04 Notifications | D01 QueueManager (L6), D02 HasEvents (L6), D03 BroadcastServiceProvider (L4+L6), D05 MailServiceProvider (L4) |
| E | E03 Config, E04 Console | E01 Container (L1+L5), E02 ServiceProvider base (L3) |

---

## §2 — Defect Class Catalogue (25 distinct patterns)

Listed in order of first discovery. Instances = total occurrences across all areas.

| ID | Class | Instances | Key examples | US-S01 note |
|----|-------|-----------|--------------|-------------|
| D01 | **Stub-defect** (ships body, produces wrong/debug answer) | 9+ | A04 Request::route(), A05 Response::view(), A06 exists/unique no-op, A07 View::render(), B01 custom-cast wiring, B03 Relation::getRelationExistenceQuery, B05 Migrator::runDown error-swallow, C03 config dropthrough, C06 Limit::response() never read, D06 ScheduleRunCommand counter | **HIGH priority**: each causes silent wrong behaviour in production |
| D02 | **Silent-arg-drop** (param accepted, value ignored) | 14+ | B02 Builder::update/isOperator, B03 BelongsToMany $touch, C01 attempt\|login\|loginUsingId $remember, C03 Bcrypt/Argon config, C05 VerifyCsrfToken $encrypter, C06 RateLimiter::hit $amount, C07 PasswordBroker ctor, D03 channel() $options, D04 sendNow/notifyNow $channels | **HIGH**: drops security features ($remember, $encrypter, hashing config) |
| D03 | **Silent-config-dropthrough** (reads config key, uses hard-coded default) | 7+ | C03 BcryptHasher/ArgonHasher `[]` options, C06 decayMinutes units, C07 expire/throttle/table/connection, B03 BelongsToMany $touch options | **MEDIUM** |
| D04 | **Same-name / opposite-shape porting hazard** | 21+ | B01 Model::create (instance bool), B02 Builder::paginate (inverted args), B05 Migrator::rollback, B06 Factory::for, B07 simplePaginate, C01 Authenticatable static-vs-instance, C02 Manager::login returns bool, C03 HashManager::extend, C04 Encrypter::generateKey instance vs static, C05 addExcept vs except, C06 RateLimiter::attempt throws vs returns, C07 createToken/validateToken, D03 resolveBinding public vs protected | **CRITICAL** for any code porting from Laravel to Phare or vice versa; must be in migration guide |
| D05 | **Raw-SQL-bypass** (skips model events, mutators, casts) | 5+ | B02 Builder::update, B03 BelongsToMany pivots/through-relations, B04 SoftDeletes::restore/delete, B06 Factory::saveInstance | **HIGH**: breaks event sourcing, audit trails, encrypted attributes |
| D06 | **FAKE-DRIVER** (wired-but-inert; source comment says "mock") | 6+ | D01 DatabaseQueue/RedisQueue (in-memory array), D04 DatabaseChannel/SmsChannel/SlackChannel, D05 Mailer::send → $sentMails[] | **CRITICAL**: data loss in production |
| D07 | **ORPHANED/UNWIRED subsystem** (class exists, nothing calls it) | 3 | B07 Pagination (Builder::paginate never constructs Paginator), C07 PasswordBroker (no callers), C08 Passkeys (no providers) | **MEDIUM**: classes must be wired before they provide value |
| D08 | **DUAL-STACK** (two implementations in same container slot; incompatible wire formats) | 3 | A07 BladeView (Phare\View dead stub vs Phalcon BladeView), C04 Encrypter (Phare\Encryption\Encrypter only in casts; Phalcon Crypt wins at slot), D02 Events (Phare dispatcher coexists with Phalcon eventsManager) | **CRITICAL**: calling code gets wrong implementation; cast data is wire-incompatible |
| D09 | **TRIPLE-STACK** (three writers to same state) | 1 | C05 CSRF: BladeOne writes `$_SESSION[token\|ip]`, Phare\Security\Csrf writes `_csrf_token`, Laravel Stack writes `_token` | **CRITICAL**: form roundtrips silently 419 |
| D10 | **Logout blast-radius** | 1 | C02 Manager::logout() destroys ENTIRE session (not auth key only) | **SECURITY CRITICAL** |
| D11 | **Session-fixation mitigation gap** | 1 | C02 regenerateId() omits deleteOldSession=true flag | **SECURITY** |
| D12 | **Key-rotation unreachable** | 3 | C04 Encrypter (no $previousKeys loop), C05 CSRF token rotation, C07 password reset token expiry rotation | **SECURITY**: APP_KEY rotation strands all encrypted data |
| D13 | **Infinite-rehash loop** | 1 | C03 BcryptHasher::needsRehash on sodium Argon builds always returns true (no thread-count override) | **SECURITY**: only manifests on sodium PHP builds |
| D14 | **Naming-defect** (name implies A, body does B) | 3 | C04 `hash()` returns encrypted ciphertext, E06 `hash()` helper same, E06 `bcrypt()` calls Phalcon Security not BcryptHasher | **HIGH**: data stored under wrong assumption |
| D15 | **Silent-unit-divergence** | 1+ | C06 decayMinutes vs Laravel decaySeconds — silent 60× time factor | **HIGH**: rate limits fire 60× too late/early |
| D16 | **Service-locator-over-DI** | 3+ | C06 RateLimiter::__construct(Application) + getCache(), C07 PasswordBroker Di::getDefault(), D02 HasEvents Di::getDefault() | **MEDIUM**: latent DI-slot-leak risk, untestable |
| D17 | **Silent-reset on un-cleaned key** | 1 | C06 tooManyAttempts passes HTML-entity key without cleanRateLimiterKey() — fail-open AND counter-wipes | **SECURITY** |
| D18 | **Un-qualified column** | 2 | B04 SoftDeletingScope::apply uses bare column name → ambiguous on JOINs | **MEDIUM**: silent query error on joined tables |
| D19 | **LAYER-COLLAPSE** (merges two Laravel layers into one) | 5+ | B03 MorphMany extends HasMany (skips MorphOneOrMany), B07 Paginator IS the abstract, D01 Job conflates user+queue-side, D04 Notification merges sender+recipient, D05 Mailable merges build+transport | **MEDIUM**: re-implementation must insert the missing layer |
| D20 | **TEST-SCAFFOLDING-IN-PRODUCTION-CLASS** | 5+ | D04 DatabaseChannel.getSent*/clear*, D05 Mailer.$sentMails | **HIGH**: production data leaks into test-helper methods |
| D21 | **MARKER-CONTRACT-WITHOUT-READER** | 2 | D02 ShouldBroadcast defined, never checked; D02 ShouldHandleEventsAfterCommit defined, never checked | **MEDIUM**: silent feature drop |
| D22 | **WILDCARD-AS-EXACT-KEY** | 1 | D03 BroadcastController wildcard channel auth: Phare does exact-key lookup, Laravel matches regex | **HIGH**: private/presence channel auth broken for parameterised channels |
| D23 | **CONFIG-PROMISES-UNSHIPPED-DRIVER** | 1 | D03 `config/broadcasting.php` references `ably` driver; AblyBroadcaster does not exist | **LOW**: config is misleading, not blocking |
| D24 | **SERVICE-LOCATOR + AUTO-RESOLVED-SINGLETON-INSTANCE-STATE-LOSS** | 1 | C06 RateLimiter: named-limiter registrations via `for()` lost on each auto-resolve | **HIGH**: rate limit configurations are invisible after first resolution |
| D25 | **METHOD-NAME-LIES** | 3 | D03 BroadcastManager::queue() is synchronous, D04 NotificationManager::sendNow() = send(), D05 Mailer::send() returns true without delivering | **HIGH**: calling code trusts the name |

---

## §3 — Security-Critical Defects (flag for immediate attention)

| Severity | Location | Defect |
|----------|----------|--------|
| CRITICAL | D05 Mail | `Mailable::renderView` uses raw `str_replace` on template variables — no HTML escaping; XSS in every mail body |
| CRITICAL | D01 Queue | `DatabaseQueue`/`RedisQueue` are in-memory mocks; all jobs silently lost at process end |
| CRITICAL | D05 Mail | `Mailer::send()` writes to `$sentMails[]` only — zero transport; no email is delivered |
| CRITICAL | C07 Password Reset | Tokens stored PLAINTEXT; DB compromise yields working reset tokens for all users |
| CRITICAL | C04 Encryption | DI-slot leak: `app('encrypter')` returns Phalcon Crypt; Phare Encrypter used only in casts — wire-incompatible ciphertext in mixed codebase |
| HIGH | C02 Session | `logout()` destroys ENTIRE session (CSRF token, flash, all data) not just auth key |
| HIGH | C02 Session | `regenerateId()` omits `deleteOldSession=true` — session-fixation mitigation gap |
| HIGH | C03 Hashing | Default bcrypt rounds=10 (Laravel 12 default; ~4× weaker on fresh install) |
| HIGH | C03 Hashing | `#[\SensitiveParameter]` absent on ALL password parameters across Auth/Hashing/Encryption |
| HIGH | C06 Rate Limiting | `resolveMaxAttempts`/`resolveRequestSignature` read `$request->get('authenticated_user')` (Phalcon GET∪POST input bag) — trivially client-spoofable auth bypass |
| HIGH | C04/C05/C07 | Key/token rotation unreachable — `APP_KEY` rotation strands all encrypted data |
| HIGH | E06 Helpers | `hash()` helper stores encrypted ciphertext where callers expect a hash |
| MEDIUM | C03 Hashing | Infinite-rehash loop on sodium Argon builds |
| MEDIUM | C06 Rate Limiting | HTML-entity key fail-open + counter-wipe security defect |

---

## §4 — No-Contracts Pattern (Area-wide)

22 subsystems lack a `Phare\Contracts\*` interface. Laravel ships 50+ contracts.
This means: all dependency injection in Phare is on concrete classes, not
interfaces. Testability, swappability, and API stability are all degraded.

**Complete no-contracts list:**
A06 Validation, A07 View, B06 Factories, B07 Pagination, C01 Auth Factory/Guard/StatefulGuard/UserProvider, C02 Session Guard/Helpers, C03 Hashing (HasherInterface inside Hashing namespace, not Contracts/), C04 Encryption (no Contracts\Encryption\Encrypter/StringEncrypter), C05 CSRF Security, C06 RateLimiter, C07 PasswordBroker, D01 Queue extended, D04 Notifications, D05 Mail, D06 Scheduler, E01 Container (thin 10-method only), E02 ServiceProvider, E03 Config (no published interface), E04 Console, E05 Facades, E06 Helpers, E07 Translation.

---

## §5 — Version-Anchor Estimate per Subsystem

Based on public API shape (approximate):

| Subsystem | Estimated Laravel equiv. | Basis |
|-----------|--------------------------|-------|
| A01 Routing | v8 | Attribute-route style, no RouteRegistrar |
| A03 Middleware | v8 | Pipeline shipped but dual-mode |
| A07 View/Blade | v8 (BladeOne v4.9) | BladeOne directive count |
| B01 Eloquent Model | v7-8 | No HasUuids/HasUlids, casts() override absent |
| B06 Factories | v6-7 | Array-return; no `has()`/`for()`-as-parent |
| D01 Queue | Incomplete mock | FAKE drivers |
| D03 Broadcasting | v11 | BroadcastManager shape |
| D04 Notifications | v9 | Slack/SMS inline, no broadcast channel |
| D05 Mail | v6-7 | No Address/Envelope/Content DTOs, no transport |
| D06 Scheduler | v9 | Basic frequency methods only |
| E04 Console | v9 | Symfony-based, limited commands |

---

## §6 — Gap Table (by Effort, smallest first)

> Effort bands: S = hours, M = days, L = 1-2 weeks, XL = sprint+

### S (hours) — quick wins

| Area | Subsystem | Gap |
|------|-----------|-----|
| E02 | ServiceProvider | Remove `DiInterface` from constructor union type |
| E03 | Config | Add Macroable + Contracts\Config\Repository interface |
| E05 | Facades | Add ~29 missing facade stubs (mechanical, blocked on services) |
| A06 | Validation | Add `exists`/`unique` validateExists/validateUnique stubs (at minimum) |
| C03 | Hashing | Add `#[\SensitiveParameter]` to all password params |
| C03 | Hashing | Fix default bcrypt rounds to match current Laravel default |
| E06 | Helpers | Fix `hash()` naming-defect (rename/rewire) |
| E06 | Helpers | Fix `bcrypt()` to call `app('hashing')->make()` |
| B04 | Soft Deletes | Qualify `deleted_at` column in SoftDeletingScope::apply |
| C04 | Encryption | Wire `'encrypter'` DI slot to `Phare\Encryption\Encrypter` |
| D06 | Scheduler | Fix ScheduleRunCommand success-counter bug (line 43) |
| C02 | Session | Fix `regenerateId()` — pass `deleteOldSession=true` |

### M (days) — medium effort

| Area | Subsystem | Gap |
|------|-----------|-----|
| A02 | HTTP Kernel | Remove Phalcon\Http\* from handle()/terminate() public signatures |
| A04 | Request | Unwrap from Phalcon\Http\Request (composition) + JSON body support |
| A05 | Response | Unwrap from Phalcon\Http\Response; add JsonResponse/RedirectResponse |
| C02 | Session | Fix logout() to remove auth key only, not destroy entire session |
| C06 | RateLimit | Fix un-cleaned key path; add cleanRateLimiterKey() calls |
| C06 | RateLimit | Fix decayMinutes→decaySeconds unit divergence |
| E01 | Container | Wrap Di (composition over inheritance); remove structural leak |
| E07 | Translation | Add JSON loader + namespace system |
| D06 | Scheduler | Add ~30 missing frequency methods (mechanical) |
| D06 | Scheduler | Swap to dragonmantank/cron-expression |
| B05 | Schema | Introduce Phare\Database\Connection wrapper (fixes B05+B06 AbstractPdo leaks) |
| C05 | CSRF | Resolve triple-stack; implement session-keyed token + rotation |
| C07 | Password Reset | Hash tokens at INSERT; add PasswordBrokerManager + DatabaseTokenRepository |
| E04 | Console | Add missing IO surface (table/progress/listing/newLine) |
| E06 | Helpers | Add ~60 missing helpers (mechanical once services exist) |

### L (1-2 weeks) — structural work

| Area | Subsystem | Gap |
|------|-----------|-----|
| B01 | Eloquent Model | Remove Phalcon\Mvc\Model inheritance; build on Phare's own persistence layer |
| B02 | Query Builder | Remove Phalcon Criteria inheritance; implement real query builder |
| B03 | Relations | Remove Phalcon\Mvc\Model\Relation inheritance; rebuild on B02 |
| C01/C02 | Auth | Implement UserProvider abstraction; add remember-me; fix guard protocol |
| D01 | Queue | Implement real DatabaseQueue/RedisQueue (replace FAKE drivers) |
| D04 | Notifications | Replace FAKE channels; add broadcast channel; add ShouldQueue path |
| D05 | Mail | Implement real transport via Symfony Mailer; add all transports |
| A06 | Validation | Expand rule set from 19 to ~110; add Rule builder |
| A07 | View | Resolve dual-stack; wire ViewServiceProvider to functional renderer |
| B06 | Factory | Rebuild as Model-returning factory (architecture inversion) |
| B07 | Pagination | Wire Builder::paginate to LengthAwarePaginator; add cursor paginator |
| E04 | Console | Add ~46 missing artisan commands |

### XL (sprint+) — architectural re-foundations

| Area | Subsystem | Gap |
|------|-----------|-----|
| B01-B03 | Eloquent ORM | Full ORM re-base off Phare's own PHQL/PDO layer (blocks B04-B07) |
| D01 | Queue | Full Worker daemon + FailedJobProvider + Bus\Dispatcher + queue middleware |
| D03 | Broadcasting | BroadcastController + /broadcasting/auth route + Ably driver |
| D05 | Mail | Full 12-transport Mailer + Markdown + v9+ Mailable DTOs |
| A03 | Middleware | Implement $middlewarePriority; add 6 missing built-in middleware |

---

## §7 — Re-scope Candidates (§7 per completion-criteria.md)

| Subsystem | Reason |
|-----------|--------|
| Auth/Sanctum | No Laravel in-tree reference (`laravel/sanctum` is an official out-of-tree package). Phare's shape mirrors it. Move to Area F — Official Packages milestone. |
| Auth/Passkeys | Not a Laravel primitive at all; no reference path exists. Move to Area F. |
| `broadcast_if`/`broadcast_unless` helpers | Laravel-Cloud-specific; not in Illuminate core. |
| Tinker, serve | Separate packages in Laravel ecosystem; not in `Illuminate/`. |

---

## §8 — Proposed Area-by-Area Implementation Order

Follows the principle: smallest-effort foundations first; each layer unblocks
the next.

```
Phase 0 — Security critical (hours–days; MUST ship first)
  E02   Remove ServiceProvider DiInterface union
  C04   Wire 'encrypter' slot to Phare\Encryption\Encrypter
  C02   Fix logout() blast-radius + regenerateId() session-fixation
  C07   Hash password-reset tokens at INSERT
  C03   Add #[\SensitiveParameter] everywhere; fix bcrypt rounds
  E06   Fix hash()/bcrypt() naming defects

Phase 1 — Foundation (M effort; unblocks everything above)
  E01   Container: composition-over-inheritance (remove Di extends)
  E03   Config: add Macroable + contract
  E02   ServiceProvider: add deferred-provider protocol + lifecycle hooks
  E04   Console: add IO surface + missing Kernel lifecycle

Phase 2 — HTTP Layer (M effort)
  A02   Kernel: remove Phalcon\Http\* from public signatures
  A04   Request: composition wrapper + JSON body + user() method
  A05   Response: JsonResponse + RedirectResponse + ResponseFactory
  A06   Validation: expand rule set to 50+ (unblocks redirect-back flow)
  A07   View: resolve dual-stack; wire to functional renderer

Phase 3 — Database Foundation (L–XL)
  B05   Introduce Connection wrapper (fixes B05+B06 AbstractPdo leaks)
  B01   Eloquent Model: remove Phalcon\Mvc\Model inheritance
  B02   Query Builder: real builder (unblocks B03/B04/B07)
  B03   Relations: rebuild (unblocks B06 Architecture inversion)
  B04   Soft Deletes: qualify columns; add Quietly variants
  B06   Factory: rebuild as Model-returning (requires B01/B02)
  B07   Pagination: wire to Builder (requires B02)

Phase 4 — Auth & Security (L effort; requires Phase 2+3)
  C01   UserProvider abstraction + full AuthManager surface
  C02   Session: flash family; fix blast-radius; remember-me
  C05   CSRF: resolve triple-stack; add rotation + encrypter injection
  C06   RateLimit: fix security defects; add unit alignment; real singleton
  C08   (§7 — skip)

Phase 5 — Async (L–XL; requires Phase 3+4)
  D01   Queue: real DB/Redis drivers + Worker daemon + Bus dispatcher
  D02   Events: add setQueueResolver + broadcastEvent path
  D03   Broadcasting: auth route + BroadcastController
  D04   Notifications: replace FAKE channels + ShouldQueue
  D05   Mail: Symfony Mailer transport + v9+ Mailable shape
  D06   Scheduler: dragonmantank + mutex + 30 frequency methods

Phase 6 — Backfill (S–M; can be parallelised)
  E05   Facades: add ~29 stubs (each ~10 lines)
  E06   Helpers: add ~60 helpers (blocked on underlying services)
  E07   Translation: JSON loader + namespace system
  E04   Console: add ~46 artisan commands
  C03   Hashing: verifyConfiguration + missing surface
  C04   Encryption: key-rotation surface + contracts
  C07   Password Reset: PasswordBrokerManager + CanResetPassword trait
```

---

## §9 — Running Porting-Hazard List (22+ entries)

Any developer migrating from Laravel to Phare (or vice versa) must
consult this table. All have identical method names but incompatible
signatures or semantics:

| # | Method | Phare | Laravel |
|---|--------|-------|---------|
| 1 | `Model::create()` | instance, returns `bool` | static, returns `Model` |
| 2 | `Builder::paginate()` | `($page, $limit)` inverted, returns Builder | `($perPage, ...)`, returns Paginator |
| 3 | `Migrator::rollback()` | `($steps)` = batch count | `($paths, $options)` with per-migration count |
| 4 | `Factory::for()` | sets the target model | attaches parent belongsTo |
| 5 | `LengthAwarePaginator::simplePaginate()` | returns `string` | Builder method returning Paginator |
| 6 | `Authenticatable::getAuthIdentifierName/getAuthPasswordName` | static | instance |
| 7 | `Auth\Manager::login()` | returns `bool` | returns `void` |
| 8 | `HashManager::extend()` | `Closure\|HasherInterface` | `Closure` only |
| 9 | `Encrypter::generateKey()` | instance | static |
| 10 | `VerifyCsrfToken::addExcept()` | instance static method, `array` | static `except()`, string vararg |
| 11 | `RateLimiter::attempt()` | throws on reject; `$callback` last/optional | returns false; `$callback` 3rd/required |
| 12 | `PasswordBroker::createToken()` | `(string $email)` | `(CanResetPassword $user)` |
| 13 | `PasswordBroker::validateToken()` | `(string $email, string $token)` | `(CanResetPassword $user, string $token)` |
| 14 | `BroadcastManager::queue()` | synchronous | queued |
| 15 | `Broadcaster::resolveBinding()` | public | protected |
| 16 | `Broadcaster::resolveImplicitBindingIfPossible()` | public | protected |
| 17 | `Broadcaster::channel()` | void, no options | returns $this, accepts $options |
| 18 | `Job::dispatch()` | trait-static | Bus helper function |
| 19 | `Job::delay()` | on user class | on PendingDispatch |
| 20 | `NotificationManager::send()` | sends sync | routes to channel driver |
| 21 | `Mailable::attach()` | `(string $file)` | `(Attachable\|string)` |
| 22 | `Response::status()` | setter returning `static` | getter returning `int` |

---

*Synthesis complete. No implementation started. Awaiting human review.*
