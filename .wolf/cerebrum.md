# Cerebrum

> OpenWolf's learning memory. Updated automatically as the AI learns from interactions.
> Do not edit manually unless correcting an error.
> Last updated: 2026-05-13

## User Preferences

<!-- How the user likes things done. Code style, tools, patterns, communication. -->

## Key Learnings

- **Project:** framework
- **Description:** Phare is a lightweight PHP framework built on the [Phalcon](https://phalcon.io/) C extension.
- **Audit pattern — contract leak severity ranking:** when a `Contracts/*` interface itself `extends` a `Phalcon\…` interface, that is a HIGHER-severity leak than a concrete class extending one — the Phalcon type is baked into the *published contract*. Cross-ref `Contracts\Http\Kernel` (US-A02), `Contracts\Http\Response` (US-A05), `Eloquent\BuilderInterface` (US-B02). Distinguish from `Eloquent\ModelInterface` (US-B01) — stub interface Model doesn't implement = LOW severity (no real contract).
- **Audit pattern — "same name, opposite shape" porting hazards:** `Model::create()` (B01 instance bool vs Laravel static returns Model), `Builder::paginate($page,$limit)` (B02 inverted arg order, returns Builder vs LengthAwarePaginator), and `Migrator::rollback($steps)` (B05 positional int = batch-count vs Laravel options-array `['step'=>n]` counting migrations) are the three so far. Worth a dedicated section in the US-S01 synthesis.
- **Audit pattern — Phalcon-leak SHAPES (rank for §2):** (1) inheritance leak (`extends Phalcon\…` — B01 Model, B02 Builder, B03 Relation); (2) contract leak (`Contracts/* extends Phalcon\…` — A02/A05/B02, highest); (3) signature/union-return leak (B03 `hasOne(): …|Phalcon\…\Relation`); (4) **dependency/param leak** (B05 — class is Phalcon-clean by inheritance but a public ctor/param takes raw `\Phalcon\Db\Adapter\Pdo\AbstractPdo`: `Blueprint::toSql`, `SchemaBuilder::__construct`, `Grammar::compileBlueprint`, `Migrator::__construct`; no Phare `Connection` wrapper exists). Fix for (4) = wrap the connection (shared DB-layer work), not local.
- **Audit pattern — silent event-bypass defects** (distinct class from stub-defects): `Builder::update()` (B02 raw UPDATE SQL skips model events/mutators), Model `castAttribute` not invoking custom CastsAttributes contract (B01). When auditing a write/query path, check whether mutators + events fire.
- **Laravel Eloquent Builder real surface** = 93 public methods + 33-entry `$passthru` table forwarding to Query Builder (228 methods) + `BuildsQueries` trait + `QueriesRelationships` trait + `Macroable`. Enumerate ALL when diffing a facade-over-query subsystem.

## Do-Not-Repeat

<!-- Mistakes made and corrected. Each entry prevents the same mistake recurring. -->
<!-- Format: [YYYY-MM-DD] Description of what went wrong and what to do instead. -->

## Decision Log

<!-- Significant technical decisions with rationale. Why X was chosen over Y. -->
