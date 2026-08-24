# Phalcon Decoupling — Staged Plan (L1 structural-inheritance leak)

**Status:** B05, SessionManager, B01, B02, B03 shipped 2026-08-25. E01b is BLOCKED — see below.
**Author:** review pass 2026-06-13.
**Context:** The last remaining parity defect class from `docs/audit-summary.md` §1 L1 —
concrete Phare types `extends` Phalcon C-classes, leaking the full Phalcon public
surface into Phare's public API. All behavioral/security bugs from the review are
already fixed (suite 1377 green, phpstan ≤ baseline); this is the only XL item left.

## The leak sites (L1)

| Phare type | extends | Blast radius |
|------------|---------|--------------|
| `Eloquent\Model` | `Phalcon\Mvc\Model` | ORM core; every model, every relation |
| `Eloquent\Builder` | `Phalcon\Mvc\Model\Criteria` | query surface (betweenWhere/inWhere/cache… leak) |
| `Eloquent\Relations\Relation` | `Phalcon\Mvc\Model\Relation` | all 16 relation classes |
| `Session\SessionManager` | `Phalcon\Session\Manager` | session API |
| `Container\Container` | (already composition — E01a done) | — |

Plus the L3 dependency leak: `Schema\*`, `Database\Migrator`, `Database\Factory`
take a raw `Phalcon\Db\Adapter\Pdo\AbstractPdo` as a constructor/param type.

## Constraint status (updated 2026-08-25)

**RESOLVED.** The earlier pass reported `tests/Eloquent` and `tests/Database` as
unverifiable (no `pdo_sqlite`). That is no longer true on this box: `PDO::getAvailableDrivers()`
returns `["pgsql","sqlite"]`. Both dirs are now in the `<testsuite>` whitelist in
`phpunit.xml.dist` and the full suite is **1581 passed / 3133 assertions** green.

Those 169 ORM/schema tests are the spec for B01/B02/B03. Every phase below must keep
them green without editing them (a test edit = a public-API break that needs a decision).

## Recommended order (smallest-blast first; each unblocks the next)

### Phase B05 — `Phare\Database\Connection` wrapper (M) — DONE 2026-08-25
Introduce a Phare `Connection` that wraps `AbstractPdo`. Change `SchemaBuilder`,
`Blueprint::toSql`, `Grammar::compileBlueprint`, `Migrator`, `Factory` ctors/params
to take the Phare `Connection`, not the raw Phalcon adapter. Closes the L3 leak and
gives every later phase a Phare-typed DB seam. Lowest risk; no inheritance change yet.

**Shipped as:** `Phare\Database\Connection` (select / selectOne / statement /
lastInsertId / beginTransaction / commit / rollBack / getDriverName / getAdapter).
`SchemaBuilder`, `Blueprint::toSql`, `Grammar::compileBlueprint`, `Migrator`, `Factory`,
`Seeder`, `SeederTable` all hold a `Connection`. Public ctors take `Connection|AbstractPdo`
and normalize via `Connection::wrap()`, so no BC break for existing callers — narrowing
the union to `Connection` alone is a future major-version cleanup.
Every raw Phalcon adapter call under `src/Phare/Database` now lives in `Connection`.
Container: `db` stays a raw Phalcon adapter (Phalcon's ORM resolves it from the DI and
needs the native type); `db.connection` and `Connection::class` are the Phare seam.

### Phase B01 — `Model` composition (L–XL) — DONE 2026-08-25
Remove `extends Phalcon\Mvc\Model`. Phare `Model` holds/uses a persistence gateway
(via the B05 `Connection`) instead of inheriting Phalcon's active-record. Preserve the
**current public semantics** verified by tests — notably:
- `create()/update()/delete(): bool` where the bool is a *cancellation signal*
  (creating/updating event returning false → method returns false). Keep this.
- `save()` delegating to create/update.
This is where the bulk of the work and risk sits. Gate behind E01b (Container can drop
`implements DiInterface` once Models no longer need to be a Phalcon model in a Phalcon DI).

### Phase B02 — `Builder` real query builder (L) — DONE 2026-08-25
Remove `extends Criteria`. Build a Phare query builder on the B05 `Connection`
(the current `$params`-based condition compiler is already most of the way there —
see `phalconCondition`/`compilePositionalCondition`). Unblocks B03/B04/B07.

### Phase B03 — Relations rebuild (L) — DONE 2026-08-25
Remove `extends Phalcon\Mvc\Model\Relation`; rebuild the 16 relation classes on B02.

### SessionManager (M) — DONE 2026-08-25
Wrap `Phalcon\Session\Manager` by composition rather than inheritance; expose a
Phare-typed session contract.

**Shipped as:** `SessionManager` holds a `Phalcon\Session\Manager` and delegates;
`getPhalconManager()` is the escape hatch. `Phare\Contracts\Session\Session` no longer
extends `Phalcon\Session\ManagerInterface` — it declares the surface itself, so Phare
consumers (e.g. `Auth\Manager`) type-hint nothing from Phalcon.

**Why the interface stayed:** `SessionManager` still `implements Phalcon\Session\ManagerInterface`.
`Phalcon\Flash\Session` resolves the DI service named `session` and type-checks it against
that interface — same shape as `db` staying a raw adapter in B05. Class inheritance is gone;
the contract is kept deliberately, and covered by a Flash interop regression test
(`tests/Session/SessionManagerCompositionTest.php`), which did not exist before.

Also updated the `Session` facade docblock, which advertised Phalcon return types.
PHPStan for `src/Phare/Session` + `src/Phare/Contracts/Session`: 22 errors -> 0.

### E01b — Container type cleanup — BLOCKED (premise was wrong)

The plan assumed the Container could drop `implements DiInterface` once Models
stopped being Phalcon models. Models are done, and it still cannot: Phalcon's
MVC/HTTP components are the real blockers, and they are still in use.

Verified 2026-08-25 — every one of these takes `Phalcon\Di\DiInterface` and is
handed the Phare Container today:

| Consumer | Where |
|---|---|
| `Phalcon\Encryption\Security::setDI()` | `Providers/EncrypterProvider.php:27` |
| `Phalcon\Mvc\View::setDI()` | view providers |
| `Phalcon\Mvc\Router::setDI()` | `Routing/RouteLoader.php` |
| `Phalcon\Http\Response\Cookies::setDI()` | container reserved services |
| `Phalcon\Flash\Session::setDI()` | `View/ViewServiceProvider.php` |

E01b is therefore not an S-sized cleanup after B01. It is gated on replacing or
wrapping the Phalcon MVC/HTTP layer (View, Router, Cookies, Flash, Security) —
a separate piece of work, larger than E01b as written.

## Remaining Phalcon inheritance in `src/Phare` (audit 2026-08-25)

Outside the ORM, these still extend Phalcon classes. None were in the L1 list;
recording them so the next pass starts from facts.

| Type | extends |
|---|---|
| `Collections\Collection` | `Phalcon\Support\Collection` |
| `Http\Request` | `Phalcon\Http\Request` |
| `Http\Response` | `Phalcon\Http\Response` |
| `Foundation\Cache` | `Phalcon\Cache` |

Also found while wiring the executor: `Collection::pluck()` goes through
`Arr::pluck`, which reads array keys, so it returns nothing for a collection of
models. Pre-existing bug, not fixed here.

## Execution recommendation
- One worktree per phase (NOT symlinked vendor — see buglog bug-049; run real
  `composer install` in the worktree).
- A running DB + the Eloquent/Database test dirs added to the whitelist BEFORE
  touching B01/B02/B03 — these are the spec.
- subagent-driven-development per phase, each phase TDD + 2-stage review, as E01a was.
- Estimated: B05 ~days, B01 ~1-2wk, B02 ~1-2wk, B03 ~1wk. Sprint+.

## Decisions deliberately NOT taken in the review fix-passes
- **`Model::create()` return type left `bool`** — the bool is a meaningful cancel
  signal (EventLifecycleTest:185), `save()` depends on it, and a real static
  `User::create([...])` parity helper name-collides with the instance method. Changing
  it is an API redesign for the owner, not a bug fix. See Decision Log 2026-06-13.
