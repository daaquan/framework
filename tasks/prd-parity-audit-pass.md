# PRD: Laravel 13 Parity Audit Pass (Areas A–E)

## Introduction

Phare claims a Laravel 13 parity milestone (`docs/completion-criteria.md`). Most
subsystems are marked `[~]` — *implemented but parity-unverified*. Before any gap can
be closed, every `[~]` public API must be diffed 1:1 against the Laravel 13 reference
source. This PRD covers **step 1 (Audit pass)** and **step 2 (Wrapper Rule audit)** of
`completion-criteria.md §8`.

This is a **read-and-record effort**: no framework code is written. Each subsystem is
audited and the result recorded in a per-area audit doc. The output is the real work
list that later implementation PRDs draw from.

**Repos / conventions**
- `/opt/framework` — `phare/framework` package, `Phare\` namespace, `src/Phare/`. English.
- `/opt/laravel-framework/src/Illuminate/` — Laravel 13 reference, read-only.
- `/opt/phare` — starter app, used only for Gate 2 (not touched by this PRD).
- Wrapper Rule (`completion-criteria.md §2`): no raw `Phalcon\*` type on a public API.

## Goals

- Diff every `[~]` and `[ ]` subsystem in Parity Scope Areas A–E against Laravel 13.
- Re-verify the two `[x]` items the task list flags (HTTP kernel, Container, Config).
- Fold the Wrapper Rule grep (§2) into each subsystem audit — every public method
  signature checked for `Phalcon\` leaks.
- Produce five audit docs (`docs/audit-area-{a,b,c,d,e}.md`), one per area, with a
  uniform per-subsystem record: **現状 / 期待 / 差分 / 工数感**.
- Produce a final cross-area gap list sorted by effort (smallest first) as the
  proposed implementation order — for review, not execution.

## Standard Audit Procedure (applies to every subsystem story)

Each subsystem user story performs the same four steps:

1. **Extract** the Phare public API from the named `src/Phare/...` file(s):
   public class names, namespaces, public method names, parameter signatures,
   return types. Ignore `protected`/`private` members.
2. **Diff 1:1** against the named Laravel 13 reference file(s): match class name,
   namespace tail, method name, parameter list, return type.
3. **List gaps** in three categories:
   - **Missing** — method/class present in Laravel, absent in Phare.
   - **Type mismatch** — signature or return type differs from Laravel.
   - **Phalcon leak** — a `public` method takes or returns a raw `Phalcon\*` type
     (Wrapper Rule §2 violation). A documented inherited exception (e.g. container
     `get()/has()`) is recorded as a known exception, not a gap.
4. **Record** a section in the area's audit doc with this exact shape:

   ```
   ### <Subsystem>
   - 現状 (Current):  <what Phare exposes today>
   - 期待 (Expected): <Laravel 13 reference surface>
   - 差分 (Gaps):     <Missing / Type mismatch / Phalcon leak — itemized>
   - 工数感 (Effort): S | M | L  + one-line rationale
   ```

No framework source file is edited by any story in this PRD.

## User Stories

### Area A — Core web stack → `docs/audit-area-a.md`

#### US-A01: Audit Routing
**Description:** As a maintainer, I want the routing public API diffed against Laravel so I know which router features are missing or mistyped.

**Files:** Phare `src/Phare/Routing/`, `src/Phare/Attributes/` · Laravel `Routing/{Router,Route,RouteCollection,RouteRegistrar}.php`

**Acceptance Criteria:**
- [ ] Phare routing public API extracted (groups, resources, named routes, route model binding, `#[Route]` attrs)
- [ ] 1:1 diff vs Laravel reference files completed
- [ ] Gaps itemized (missing / type mismatch / Phalcon leak)
- [ ] Public signatures grepped for `Phalcon\` leaks
- [ ] `### Routing` section appended to `docs/audit-area-a.md` in the standard shape
- [ ] No framework code changed

#### US-A02: Re-verify HTTP kernel pipeline `[x]`
**Description:** As a maintainer, I want the already-`[x]` kernel re-checked specifically for Wrapper Rule compliance.

**Files:** Phare `src/Phare/Http/Kernel.php`, `src/Phare/Foundation/` (HTTP kernel) · Laravel `Foundation/Http/Kernel.php`, `Contracts/Http/Kernel.php`

**Acceptance Criteria:**
- [ ] Kernel public API extracted (`handle`, `terminate`, middleware accessors)
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Public signatures grepped for `Phalcon\` leaks; any leak recorded
- [ ] `### HTTP Kernel` section appended to `docs/audit-area-a.md`
- [ ] Confirms `[x]` still holds, or flags downgrade with reason
- [ ] No framework code changed

#### US-A03: Audit Middleware
**Description:** As a maintainer, I want middleware registration and ordering diffed against Laravel.

**Files:** Phare `src/Phare/Middleware/`, `src/Phare/Pipeline/` · Laravel `Foundation/Http/Middleware/*`, `Routing/Middleware/*`, `Pipeline/Pipeline.php`

**Acceptance Criteria:**
- [ ] Phare middleware API extracted (global / group / route alias registration, ordered application)
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Middleware` section appended to `docs/audit-area-a.md`
- [ ] No framework code changed

#### US-A04: Audit Request
**Description:** As a maintainer, I want `Request` input methods verified against Laravel.

**Files:** Phare `src/Phare/Http/Request.php` · Laravel `Http/Request.php`, `Http/Concerns/InteractsWithInput.php`

**Acceptance Criteria:**
- [ ] Phare `Request` public API extracted — verify `input/query/only/except/validate/file/header` parity
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Request` section appended to `docs/audit-area-a.md`
- [ ] No framework code changed

#### US-A05: Audit Response
**Description:** As a maintainer, I want `Response` factories verified against Laravel.

**Files:** Phare `src/Phare/Http/` (`Response`, `JsonResponse`, `RedirectResponse`) · Laravel `Http/{Response,JsonResponse,RedirectResponse}.php`

**Acceptance Criteria:**
- [ ] Phare response public API extracted — verify `json/redirect/download/stream/header` parity
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Response` section appended to `docs/audit-area-a.md`
- [ ] No framework code changed

#### US-A06: Audit FormRequest + Validation
**Description:** As a maintainer, I want the FormRequest hook and validator rule set diffed against Laravel.

**Files:** Phare `src/Phare/Http/FormRequest.php`, `src/Phare/Validation/` · Laravel `Foundation/Http/FormRequest.php`, `Validation/{Validator,Rule,ValidationRuleParser}.php`

**Acceptance Criteria:**
- [ ] Phare FormRequest + Validator public API extracted (rule methods, `authorize`, `rules`, `validated`)
- [ ] Rule set diffed vs Laravel `Validation\Rule` (enumerate each available rule)
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### FormRequest + Validation` section appended to `docs/audit-area-a.md`
- [ ] No framework code changed

#### US-A07: Audit View / Blade
**Description:** As a maintainer, I want Blade directive coverage diffed against the Laravel compiler.

**Files:** Phare `src/Phare/View/` · Laravel `View/Compilers/BladeCompiler.php`, `View/Compilers/Concerns/*`, `View/Factory.php`

**Acceptance Criteria:**
- [ ] Phare view + Blade public API extracted; directive list enumerated
- [ ] Directive coverage diffed vs Laravel Blade compiler (per-directive present/absent)
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### View / Blade` section appended to `docs/audit-area-a.md`
- [ ] No framework code changed

### Area B — ORM + Database → `docs/audit-area-b.md`

#### US-B01: Audit Eloquent Model
**Files:** Phare `src/Phare/Eloquent/Model.php` · Laravel `Database/Eloquent/Model.php`, `Eloquent/Concerns/*`

**Acceptance Criteria:**
- [ ] Phare `Model` public API extracted — fillable/guarded, casts, `$hidden`, events, `$with`
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Eloquent Model` section appended to `docs/audit-area-b.md`
- [ ] No framework code changed

#### US-B02: Audit Query Builder
**Files:** Phare `src/Phare/Eloquent/` (Builder) · Laravel `Database/Eloquent/Builder.php`, `Database/Query/Builder.php`

**Acceptance Criteria:**
- [ ] Phare `Builder` public API extracted — verify `where*/when/whereHas/with/scopes/aggregates` parity
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Builder` section appended to `docs/audit-area-b.md`
- [ ] No framework code changed

#### US-B03: Audit Relations
**Files:** Phare `src/Phare/Eloquent/` (Relations) · Laravel `Database/Eloquent/Relations/*`

**Acceptance Criteria:**
- [ ] Phare relations public API extracted — hasOne/hasMany/belongsTo/belongsToMany/morph*
- [ ] 1:1 diff vs Laravel reference completed (per relation type)
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Relations` section appended to `docs/audit-area-b.md`
- [ ] No framework code changed

#### US-B04: Audit Soft Deletes + Global Scopes
**Files:** Phare `src/Phare/Eloquent/` · Laravel `Database/Eloquent/SoftDeletes.php`, `Eloquent/Scope.php`

**Acceptance Criteria:**
- [ ] Phare soft-delete + global-scope public API extracted
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Soft Deletes + Global Scopes` section appended to `docs/audit-area-b.md`
- [ ] No framework code changed

#### US-B05: Audit Migrations + Schema builder
**Files:** Phare `src/Phare/Database/` · Laravel `Database/Schema/{Blueprint,Builder}.php`, `Database/Migrations/*`

**Acceptance Criteria:**
- [ ] Phare migration + Schema public API extracted; column types enumerated vs Laravel `Blueprint`
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Migrations + Schema` section appended to `docs/audit-area-b.md`
- [ ] No framework code changed

#### US-B06: Audit Seeders + Factories
**Files:** Phare `src/Phare/Database/` · Laravel `Database/Seeder.php`, `Database/Eloquent/Factories/Factory.php`

**Acceptance Criteria:**
- [ ] Phare seeder + factory public API extracted — verify `Factory` state/relationship API
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Seeders + Factories` section appended to `docs/audit-area-b.md`
- [ ] No framework code changed

#### US-B07: Audit Pagination
**Files:** Phare `src/Phare/Pagination/` · Laravel `Pagination/{LengthAwarePaginator,CursorPaginator,Paginator}.php`

**Acceptance Criteria:**
- [ ] Phare pagination public API extracted — `LengthAwarePaginator` / `CursorPaginator` link rendering
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Pagination` section appended to `docs/audit-area-b.md`
- [ ] No framework code changed

### Area C — Auth + Security → `docs/audit-area-c.md`

#### US-C01: Audit AuthManager multi-guard
**Files:** Phare `src/Phare/Auth/` · Laravel `Auth/{AuthManager,SessionGuard,RequestGuard}.php`, `Contracts/Auth/{Guard,Factory}.php`

**Acceptance Criteria:**
- [ ] Phare `AuthManager` public API extracted (guard resolution, driver registration)
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### AuthManager` section appended to `docs/audit-area-c.md`
- [ ] No framework code changed

#### US-C02: Audit Session auth (login/logout/remember)
**Files:** Phare `src/Phare/Auth/`, `src/Phare/Session/` · Laravel `Auth/SessionGuard.php`

**Acceptance Criteria:**
- [ ] Phare session-auth public API extracted — `login/logout/attempt/remember/viaRemember`
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Session Auth` section appended to `docs/audit-area-c.md`
- [ ] No framework code changed

#### US-C03: Audit Hashing
**Files:** Phare `src/Phare/Hashing/` · Laravel `Hashing/{HashManager,BcryptHasher,ArgonHasher}.php`

**Acceptance Criteria:**
- [ ] Phare hashing public API extracted — bcrypt/argon, `make/check/needsRehash`
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Hashing` section appended to `docs/audit-area-c.md`
- [ ] No framework code changed

#### US-C04: Audit Encrypter
**Files:** Phare `src/Phare/Encryption/` · Laravel `Encryption/Encrypter.php`

**Acceptance Criteria:**
- [ ] Phare encrypter public API extracted — verify `encrypt/decrypt/encryptString/decryptString` parity
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Encrypter` section appended to `docs/audit-area-c.md`
- [ ] No framework code changed

#### US-C05: Audit CSRF middleware (status `[ ]` — confirm presence)
**Files:** Phare `src/Phare/Middleware/`, `src/Phare/Security/` · Laravel `Foundation/Http/Middleware/VerifyCsrfToken.php`

**Acceptance Criteria:**
- [ ] Confirm whether a CSRF middleware exists in Phare; record present/absent
- [ ] If present, diff public API vs Laravel reference + verify token rotation
- [ ] If absent, record as a confirmed missing item (`[ ]`)
- [ ] Public signatures grepped for `Phalcon\` leaks
- [ ] `### CSRF Middleware` section appended to `docs/audit-area-c.md`
- [ ] No framework code changed

#### US-C06: Audit Rate Limiting
**Files:** Phare `src/Phare/RateLimit/` · Laravel `Cache/RateLimiter.php`, `Routing/Middleware/ThrottleRequests.php`

**Acceptance Criteria:**
- [ ] Phare rate-limit public API extracted — verify `RateLimiter` named-limiter API
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Rate Limiting` section appended to `docs/audit-area-c.md`
- [ ] No framework code changed

#### US-C07: Audit Password reset
**Files:** Phare `src/Phare/Auth/Passwords/` · Laravel `Auth/Passwords/{PasswordBroker,PasswordBrokerManager,DatabaseTokenRepository}.php`

**Acceptance Criteria:**
- [ ] Phare password-reset public API extracted — `sendResetLink/reset/broker`
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Password Reset` section appended to `docs/audit-area-c.md`
- [ ] No framework code changed

#### US-C08: Sanctum / passkeys scope decision
**Description:** As a maintainer, I want an explicit in-scope-or-§7 decision for Sanctum/passkeys before any audit effort is spent.

**Files:** Phare `src/Phare/Auth/` (token/sanctum) · `completion-criteria.md §3 row C`, §7

**Acceptance Criteria:**
- [ ] Confirm whether Sanctum/passkeys support currently exists in Phare
- [ ] Record a decision: in-scope (then diff) or move to `completion-criteria.md §7` with a written reason
- [ ] If in-scope, perform the standard audit; if §7, record the re-scope rationale only
- [ ] `### Sanctum / Passkeys` section appended to `docs/audit-area-c.md`
- [ ] No framework code changed

### Area D — Async + Extras → `docs/audit-area-d.md`

#### US-D01: Audit Queue
**Files:** Phare `src/Phare/Queue/` · Laravel `Queue/{QueueManager,Queue}.php`, `Queue/Jobs/*`, `Bus/Queueable.php`

**Acceptance Criteria:**
- [ ] Phare queue public API extracted — sync/database/redis drivers, `Job` retry/backoff/`failed()`
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Queue` section appended to `docs/audit-area-d.md`
- [ ] No framework code changed

#### US-D02: Audit Events
**Files:** Phare `src/Phare/Events/` · Laravel `Events/Dispatcher.php`, `Contracts/Events/Dispatcher.php`

**Acceptance Criteria:**
- [ ] Phare event public API extracted — `Dispatcher` listen/dispatch/subscribe/queued listeners
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Events` section appended to `docs/audit-area-d.md`
- [ ] No framework code changed

#### US-D03: Audit Broadcasting
**Files:** Phare `src/Phare/Broadcasting/` · Laravel `Broadcasting/BroadcastManager.php`, `Broadcasting/Broadcasters/*`

**Acceptance Criteria:**
- [ ] Phare broadcasting public API extracted — channel auth, `BroadcastManager`
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Broadcasting` section appended to `docs/audit-area-d.md`
- [ ] No framework code changed

#### US-D04: Audit Notifications
**Files:** Phare `src/Phare/Notifications/` · Laravel `Notifications/*`

**Acceptance Criteria:**
- [ ] Phare notification public API extracted — mail/database channels, `Notifiable`
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Notifications` section appended to `docs/audit-area-d.md`
- [ ] No framework code changed

#### US-D05: Audit Mail
**Files:** Phare `src/Phare/Mail/` · Laravel `Mail/{Mailable,MailManager,Message}.php`

**Acceptance Criteria:**
- [ ] Phare mail public API extracted — `Mailable` build/markdown, `MailManager`
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Mail` section appended to `docs/audit-area-d.md`
- [ ] No framework code changed

#### US-D06: Audit Console scheduler
**Files:** Phare `src/Phare/Console/` (Scheduling) · Laravel `Console/Scheduling/{Schedule,Event,CallbackEvent}.php`

**Acceptance Criteria:**
- [ ] Phare scheduler public API extracted — `Schedule` frequency methods, hooks
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Console Scheduler` section appended to `docs/audit-area-d.md`
- [ ] No framework code changed

### Area E — Foundation → `docs/audit-area-e.md`

#### US-E01: Re-verify Container `[x]`
**Files:** Phare `src/Phare/Container/` · Laravel `Container/Container.php`, `Contracts/Container/Container.php`

**Acceptance Criteria:**
- [ ] Phare `Container` public API extracted (`bind/singleton/make/resolve/get/has`)
- [ ] 1:1 diff vs Laravel reference completed
- [ ] `Phalcon\` leaks recorded; `get()/has()` inherited from `Phalcon\Di\Di` logged as a known documented exception
- [ ] `### Container` section appended to `docs/audit-area-e.md`
- [ ] Confirms `[x]` still holds, or flags downgrade with reason
- [ ] No framework code changed

#### US-E02: Audit Service providers
**Files:** Phare `src/Phare/Providers/`, `src/Phare/Support/` · Laravel `Support/ServiceProvider.php`

**Acceptance Criteria:**
- [ ] Phare service-provider public API extracted — `register/boot`, deferred providers
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Service Providers` section appended to `docs/audit-area-e.md`
- [ ] No framework code changed

#### US-E03: Re-verify Config `[x]`
**Files:** Phare `src/Phare/Config/` · Laravel `Config/Repository.php`, `Contracts/Config/Repository.php`

**Acceptance Criteria:**
- [ ] Phare config public API extracted (`get/set/has/all`, load + cache)
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Public signatures grepped for `Phalcon\` leaks
- [ ] `### Config` section appended to `docs/audit-area-e.md`
- [ ] Confirms `[x]` still holds, or flags downgrade with reason
- [ ] No framework code changed

#### US-E04: Audit Console / artisan
**Files:** Phare `src/Phare/Console/` · Laravel `Console/{Command,Application,Parser}.php`, `Foundation/Console/Kernel.php`

**Acceptance Criteria:**
- [ ] Phare console public API extracted — command registration, signature parsing, I/O helpers
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Console / Artisan` section appended to `docs/audit-area-e.md`
- [ ] No framework code changed

#### US-E05: Audit Facades
**Files:** Phare `src/Phare/Support/` (Facades) · Laravel `Support/Facades/*`

**Acceptance Criteria:**
- [ ] Phare facade base class + alias map extracted; facade list enumerated
- [ ] Alias map diffed vs Laravel facade set (per-facade present/absent)
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Facades` section appended to `docs/audit-area-e.md`
- [ ] No framework code changed

#### US-E06: Audit Helpers
**Files:** Phare `src/Phare/Support/` (helper functions) · Laravel `Support/helpers.php`, `Foundation/helpers.php`, `Collections/helpers.php`

**Acceptance Criteria:**
- [ ] Phare global helper functions enumerated
- [ ] Coverage diffed vs Laravel helper set (per-helper present/absent)
- [ ] Gaps itemized; signatures grepped for `Phalcon\` leaks
- [ ] `### Helpers` section appended to `docs/audit-area-e.md`
- [ ] No framework code changed

#### US-E07: Audit Translation
**Files:** Phare `src/Phare/Translation/` · Laravel `Translation/Translator.php`, `Contracts/Translation/Translator.php`

**Acceptance Criteria:**
- [ ] Phare translator public API extracted — `Translator` `get/choice`, replacements
- [ ] 1:1 diff vs Laravel reference completed
- [ ] Gaps itemized; public signatures grepped for `Phalcon\` leaks
- [ ] `### Translation` section appended to `docs/audit-area-e.md`
- [ ] No framework code changed

### Synthesis

#### US-S01: Cross-area gap synthesis + implementation proposal
**Description:** As a maintainer, I want all recorded gaps collated into one effort-sorted list so I can approve an implementation order.

**Files:** `docs/audit-area-{a,b,c,d,e}.md` → new `docs/audit-summary.md`

**Acceptance Criteria:**
- [ ] Every gap from the five area docs collated into one table (Area / Subsystem / Gap / Effort)
- [ ] Gaps sorted by effort, smallest first
- [ ] All `Phalcon\` leaks listed separately with wrap-or-exception recommendation
- [ ] Any re-scope candidates (move `[~]` → §7) flagged with rationale
- [ ] Proposed area-by-area implementation order written (A→E, smallest gaps first per `§8 step 3`)
- [ ] `docs/audit-summary.md` written; **stops for human review — no implementation started**

## Functional Requirements

- FR-1: For every `[~]` and `[ ]` subsystem in `completion-criteria.md §4` Areas A–E,
  extract the Phare public API and diff it 1:1 against the named Laravel 13 reference.
- FR-2: Re-verify the three task-flagged `[x]` items (HTTP kernel, Container, Config)
  for Wrapper Rule compliance; confirm `[x]` or flag a downgrade with reason.
- FR-3: For every audited subsystem, grep `public` method signatures for `Phalcon\`
  parameter/return leaks; record each as a gap or a documented known exception.
- FR-4: Record each subsystem in its area doc (`docs/audit-area-{a..e}.md`) using the
  fixed shape: `現状 / 期待 / 差分 / 工数感` with effort rated S/M/L.
- FR-5: Produce `docs/audit-summary.md` — all gaps in one effort-sorted table plus a
  proposed implementation order.
- FR-6: No framework source file under `src/Phare/` is created, edited, or deleted by
  any story in this PRD. The audit is read-and-record only.
- FR-7: For `US-C05` (CSRF) and `US-C08` (Sanctum/passkeys), the story may conclude
  with a presence-confirmation or a §7 re-scope decision instead of a full diff.
- FR-8: All audit docs are written in English (`/opt/framework` repo convention).

## Non-Goals (Out of Scope)

- Writing or modifying any framework code — implementation is a later, separate PRD.
- Touching the `/opt/phare` starter app or Gate 2 reference-app expansion.
- Auditing anything in `completion-criteria.md §7` (Container Group F env helpers,
  Octane, Horizon-style dashboard, package discovery, Vite pipeline).
- Running the test suites (Gate 1 / Gate 2) — audit is static, not behavioral.
- Updating `docs/` user guides — that is an Exit Criteria task, not an audit task.
- Deciding final implementation effort estimates beyond a coarse S/M/L rating.

## Technical Considerations

- Laravel reference is **read-only** at `/opt/laravel-framework/src/Illuminate/`.
  Confirm the checked-out version aligns with Laravel 13 before diffing.
- Phare namespace tail must match Laravel's (e.g. `Phare\Routing\Router` vs
  `Illuminate\Routing\Router`) — namespace head differs by design, tail must match.
- The container `get()/has()` Phalcon inheritance is a pre-documented Wrapper Rule
  exception (`completion-criteria.md §2`); do not re-flag it as a new gap.
- Stories are independent and parallelizable except `US-S01`, which depends on all
  five area docs being complete.
- This PRD feeds the Ralph system; each `US-*` is sized for one focused agent task.

## Success Metrics

- 100% of Area A–E `[~]`/`[ ]` subsystems have an audit doc section.
- Every audited public API has an explicit gap list (or "no gaps" stated).
- Every `Phalcon\` public-surface leak is itemized with a wrap/exception call.
- `docs/audit-summary.md` gives a single, effort-sorted, review-ready work list.

## Resolved Decisions

- **Laravel version:** `/opt/laravel-framework` is confirmed pinned to Laravel 13.x.
  Record the exact minor in each audit doc header.
- **Sequencing:** All 35 subsystem stories run **in parallel** — no Area A sign-off
  gate. `US-S01` (synthesis) is the only story that waits on the rest.
- **Effort rating:** Coarse **S/M/L** is sufficient; no hour estimates needed.

## Open Questions

- For Sanctum/passkeys: in-scope-vs-§7 is itself the deliverable of `US-C08` — that
  story resolves it; no answer needed before the PRD runs.
