# Phalcon Decoupling — Staged Plan (L1 structural-inheritance leak)

**Status:** plan only, awaiting greenlight. No implementation started.
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

### Phase B05 — `Phare\Database\Connection` wrapper (M, do FIRST)
Introduce a Phare `Connection` that wraps `AbstractPdo`. Change `SchemaBuilder`,
`Blueprint::toSql`, `Grammar::compileBlueprint`, `Migrator`, `Factory` ctors/params
to take the Phare `Connection`, not the raw Phalcon adapter. Closes the L3 leak and
gives every later phase a Phare-typed DB seam. Lowest risk; no inheritance change yet.

### Phase B01 — `Model` composition (L–XL)
Remove `extends Phalcon\Mvc\Model`. Phare `Model` holds/uses a persistence gateway
(via the B05 `Connection`) instead of inheriting Phalcon's active-record. Preserve the
**current public semantics** verified by tests — notably:
- `create()/update()/delete(): bool` where the bool is a *cancellation signal*
  (creating/updating event returning false → method returns false). Keep this.
- `save()` delegating to create/update.
This is where the bulk of the work and risk sits. Gate behind E01b (Container can drop
`implements DiInterface` once Models no longer need to be a Phalcon model in a Phalcon DI).

### Phase B02 — `Builder` real query builder (L)
Remove `extends Criteria`. Build a Phare query builder on the B05 `Connection`
(the current `$params`-based condition compiler is already most of the way there —
see `phalconCondition`/`compilePositionalCondition`). Unblocks B03/B04/B07.

### Phase B03 — Relations rebuild (L)
Remove `extends Phalcon\Mvc\Model\Relation`; rebuild the 16 relation classes on B02.

### SessionManager (M)
Wrap `Phalcon\Session\Manager` by composition rather than inheritance; expose a
Phare-typed session contract.

### E01b — Container type cleanup (S, after B01)
Once nothing requires the Container to BE a `Phalcon\Di\DiInterface` (Models no longer
extend Phalcon\Mvc\Model, so `setDI(DiInterface)` sites in Relations/Model disappear),
drop `implements DiInterface` from `Container` (composition seam already shipped E01a).

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
