# Phare — Completion Criteria (Laravel 13 Parity Milestone)

> Companion to [laravel13-phalcon-architecture.md](laravel13-phalcon-architecture.md).
> That doc tracks *internal phases* (bootstrap, container, kernel). **This doc defines
> what "done" means for the whole project**: a measurable Laravel 13 parity milestone.

## 1. Definition of Done

The project is **complete** when:

1. Every subsystem in the **Parity Scope** (§3) passes its parity checklist (§4) — each
   public API verified 1:1 against the Laravel 13 reference source.
2. The **Wrapper Rule** (§2) holds: no raw Phalcon type leaks through a public API.
3. Both **verification gates** (§5) are green:
   - Framework test suite passes.
   - The `/opt/phare` starter app boots and exercises every Parity Scope area.
4. The **Exit Criteria** checklist (§6) is fully checked.

This is a *parity milestone*, **not** full Laravel parity. Anything outside §3 is §7
(out of scope). Realistic bar: a developer who knows Laravel 13 can build a standard
web + API app on Phare without reading Phalcon docs.

## 2. The Wrapper Rule

**Even where Phalcon already ships a usable component, expose a Laravel-compatible
wrapper.** Phalcon stays the execution engine; it must not be the public surface.

A public API is parity-compliant only if:

- Class names, namespaces, method names, and signatures match Laravel 13.
- Return types are Phare/Laravel-compat types, never raw `Phalcon\*` objects.
- A raw `Phalcon\*` type is acceptable **only** as an internal `protected`/`private`
  field, never a parameter or return of a `public` method a user app calls.

**Audit task:** grep public method signatures for `Phalcon\` leaks; each hit is either
wrapped or logged as a known exception with a reason (see container `get()/has()` —
inherited from `Phalcon\Di\Di`, documented exception).

## 3. Parity Scope (the measured Laravel 13 subset)

Reference source (read-only): `/opt/laravel-framework/src/Illuminate/`.

| # | Area | Subsystems |
|---|------|-----------|
| A | Core web stack | Routing, HTTP kernel, middleware, controllers, Request/Response, FormRequest + Validation, View (Blade) |
| B | ORM + Database | Eloquent model, query Builder, relations, casts, scopes, soft deletes, migrations, Schema builder, seeders, factories, pagination |
| C | Auth + Security | Guards, session auth, Hashing, Encryption, CSRF, rate limiting, password reset, Sanctum/passkeys |
| D | Async + Extras | Queue, Events, Broadcasting, Notifications, Mail, console scheduler |
| E | Foundation | Container, service providers, config, console/artisan, facades, helpers, translation |

## 4. Per-Area Parity Checklist

Status legend: `[x]` implemented + parity-verified · `[~]` implemented, parity
**unverified** · `[ ]` missing. Current marks are from a directory survey only —
**every `[~]` must become `[x]` via an explicit Laravel-reference diff before done.**

### A — Core web stack
- [~] Routing: groups, resources, named routes, route model binding, `#[Route]` attrs
- [x] HTTP kernel pipeline (Phases 1–3, `laravel13-phalcon-architecture.md`)
- [~] Middleware: global / group / route aliases, ordered application
- [~] `Request` — verify `input/query/only/except/validate/file/header` parity
- [~] `Response` — verify `json/redirect/download/stream/header` parity
- [~] `FormRequest` + `Validator` — verify rule set vs Laravel `Validation\Rule`
- [~] View / Blade — directive coverage vs Laravel Blade compiler

### B — ORM + Database
- [~] `Eloquent\Model` — fillable/guarded, casts, `$hidden`, events, `$with`
- [~] `Builder` — verify `where*/when/whereHas/with/scopes/aggregates` parity
- [~] Relations — hasOne/hasMany/belongsTo/belongsToMany/morph*
- [~] Soft deletes + global scopes
- [~] Migrations + `Schema` builder — column types vs Laravel `Blueprint`
- [~] Seeders + factories — verify `Factory` state/relationship API
- [~] Pagination — `LengthAwarePaginator` / `CursorPaginator` link rendering

### C — Auth + Security
- [~] `AuthManager` multi-guard (roadmap Phase 5)
- [~] Session auth: login/logout/remember
- [~] Hashing — bcrypt/argon parity (roadmap Phase 5)
- [~] `Encrypter` — verify `encrypt/decrypt/encryptString` parity
- [ ] CSRF middleware — confirm present + token rotation
- [~] Rate limiting — verify `RateLimiter` named-limiter API
- [~] Password reset (`Auth/Passwords/`)
- [~] Sanctum / passkeys — scope: confirm intended vs out-of-scope

### D — Async + Extras
- [~] Queue — sync/database/redis drivers, `Job` retry/backoff/`failed()`
- [~] Events — `Dispatcher` listen/dispatch/subscribe/queued listeners
- [~] Broadcasting — channel auth, `BroadcastManager` (roadmap Phase 5)
- [~] Notifications — mail/database channels, `Notifiable`
- [~] Mail — `Mailable` build/markdown, `MailManager` (roadmap Phase 5)
- [~] Console scheduler (`Console/Scheduling/`)

### E — Foundation
- [x] Container (roadmap Phases 4 + 6)
- [~] Service providers — `register/boot`, deferred providers
- [x] Config load + cache (roadmap Phase 2)
- [~] Console / artisan — command registration, signature parsing, I/O helpers
- [~] Facades — verify alias map vs Laravel facade set
- [~] Helpers — verify global helper coverage
- [~] Translation — `Translator` `get/choice`, replacements

## 5. Verification Gates

### Gate 1 — Framework test suite
- `cd /opt/framework && ./vendor/bin/pest` → all green (baseline: 998 tests, roadmap).
- Each area in §4 reaching `[x]` adds a parity test asserting Laravel-compat
  signature/behavior (TDD per `~/.claude/rules/common/testing.md`).

### Gate 2 — Working app (`/opt/phare`)
The starter app is currently too thin (1 model, ~4 controllers, 4 test files) to
prove parity. Expand it into a **reference app** that exercises every §3 area:

- [ ] Migrations + seeders + factories for ≥2 related models
- [ ] Eloquent relations used in a controller (A + B)
- [ ] Auth flow: register / login / logout / protected route (C)
- [ ] FormRequest validation on an API endpoint (A)
- [ ] Blade view rendering a paginated list (A + B)
- [ ] One queued job + one event listener + one notification (D)
- [ ] An artisan command (E)
- [ ] Feature tests covering each of the above
- [ ] `php artisan serve` boots clean; `./vendor/bin/pest` green in `/opt/phare`

## 6. Exit Criteria

Project is **done** when all are checked:

- [ ] §4 — every checklist item is `[x]` (parity-verified) or moved to §7 with a reason
- [ ] §2 Wrapper Rule audit complete; Phalcon leaks wrapped or logged as exceptions
- [ ] Gate 1 green
- [ ] Gate 2 green (reference app exercises all areas)
- [ ] `docs/` guides updated to match final public API
- [ ] `composer.json` version tagged; CHANGELOG written
- [ ] `roadmap` doc and this doc agree on status

## 7. Out of Scope (explicitly not required for this milestone)

Deferred — document, do not build:

- Container Group F environment helpers (Phalcon C-extension landmine — roadmap §285)
- Octane / long-running server mode
- Horizon-style queue dashboard
- Full Laravel package-discovery ecosystem
- Vite/asset pipeline beyond existing Laravel Mix setup
- **Sanctum (Phare `Auth/Sanctum/`)** — `laravel/sanctum` is an official
  Laravel package but not part of Illuminate (§3 reference path). Per
  US-C08 (2026-05-28), deferred to a future "Area F — Official Laravel
  packages" milestone. Forward-visibility notes recorded in
  `docs/audit-area-c.md` § Sanctum / Passkeys.
- **Passkeys (Phare `Auth/Passkeys/`)** — Phare-original subsystem; Laravel
  ships no passkey/WebAuthn primitive in core or official packages. Per
  US-C08 (2026-05-28), no parity diff is meaningful; defer indefinitely.
- Any subsystem not listed in §3

Re-scope decision: if an item proves too costly, move it from §4 to §7 with a written
reason rather than silently dropping it.

## 8. Execution Order (suggested)

1. **Audit pass** — for each `[~]` in §4, diff Phare public API vs the Laravel
   reference file; record gaps. Cheap, no code; produces the real work list.
2. **Wrapper Rule audit** (§2) — grep `Phalcon\` leaks; this often overlaps step 1.
3. Close gaps area by area (A → E), TDD, smallest gaps first for momentum.
4. Build the reference app (§5 Gate 2) incrementally as each area reaches `[x]`.
5. Final gates + Exit Criteria sign-off.
