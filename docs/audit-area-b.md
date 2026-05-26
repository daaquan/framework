# Audit — Area B: Database & ORM

**Laravel 13 reference:** `/opt/laravel-framework` @ `13.2.0` (read-only)
**Phare package:** `phare/framework`, namespace `Phare\`, `src/Phare/`
**Method:** per-subsystem 1:1 public-API diff vs Laravel 13 + Wrapper Rule (§2) grep.
**Scope:** read-and-record only — no framework source edited.

---

### Eloquent Model

- 現状 (Current) — Phare:
  `Phare\Eloquent\Model extends Phalcon\Mvc\Model implements \ArrayAccess`
  (`#[\AllowDynamicProperties]`). It is NOT a standalone ORM — it is a thin
  Laravel-shaped facade bolted onto Phalcon's active-record `Phalcon\Mvc\Model`.
  Composed of 6 traits: `GuardsAttributes`, `HasAttributes`, `HasEvents`,
  `HasGlobalScopes`, `HasRelationships`, `HidesAttributes`.
  - **Mass assignment** (`GuardsAttributes`): `$fillable=[]`, `$guarded=['*']`,
    `$unguarded`. Methods `unguard`, `reguard`, `guard`, `fillableFromArray`,
    `isFillable`, `isGuarded`, `totallyGuarded`, `getFillable`, `getGuarded`,
    plus `Model::fill(array): static`. Present and close to Laravel.
  - **Casts** (`HasAttributes`): `$casts=[]` array property only (no `casts()`
    method). `getCastType` supports: `int/integer`, `real/float/double`,
    `string`, `bool/boolean`, `object`, `array/json`, `collection`,
    `date`, `datetime`, `timestamp`, `immutable_date`, `immutable_datetime`,
    `encrypted*`, `decimal:n`, backed-enum. `mergeCasts`, `getCasts`,
    `hasCast`. Get/set mutators: classic `getXAttribute`/`setXAttribute` AND
    `Attribute`-object mutators (`hasAttributeGetMutator`/`hasAttributeSetMutator`).
  - **Serialization hiding** (`HidesAttributes`): `$hidden=[]`, `$visible=[]`,
    `getHidden/setHidden/getVisible/setVisible`, `makeVisible/makeVisibleIf`,
    `makeHidden/makeHiddenIf`. `$appends=[]` lives in `HasAttributes`.
  - **Events** (`HasEvents`): static event-registrar methods `retrieved`,
    `creating`, `created`, `updating`, `updated`, `saving`, `saved`,
    `deleting`, `deleted`, `restoring`, `restored`, `softDeleted`,
    `forceDeleting`, `forceDeleted`, `replicating`. `observe`, `withoutEvents`,
    `withoutTouching`, `set/unset/getEventDispatcher`, `saveQuietly`,
    `deleteQuietly`. `$dispatchesEvents` custom-event map on the Model class.
  - **Timestamps** (`HasTimestamps` — *used by `Model` only via SoftDeletes/sub
    traits; note `Model.php` does NOT `use HasTimestamps` directly* — see Gaps):
    `$timestamps`, `freshTimestamp`, `usesTimestamps`, `touch`, `touchQuietly`,
    `updateTimestamps`, `getCreatedAtColumn`/`getUpdatedAtColumn`.
  - **Persistence/finder surface**: `create`, `update`, `save`, `delete`,
    `assign`, `all`, `find`, `findFirst`, `first`, `firstOrFail`, `rawFind`,
    `rawFindFirst`, `where`, `query`, `newQuery*`, `hydrate`, `markAsRetrieved`,
    `toArray`, `morphMap`, `getMorphClass`, `getActualClassNameForMorph`.
  - **Properties**: `$connection`, `$table`, `$primaryKey='id'`,
    `$passwordAttributes` (non-Laravel — auto-hashes columns on set),
    `$dispatchesEvents`, `$exists`, static `$morphMap`.

- 期待 (Expected) — Laravel 13:
  `Illuminate\Database\Eloquent\Model` is an abstract base implementing
  `Arrayable`, `ArrayAccess`, `CanBeEscapedWhenCastToString`, `HasBroadcastChannel`,
  `Jsonable`, `JsonSerializable`, `QueueableEntity`, `UrlRoutable`. ~129 public
  methods + ~16 composed `Concerns/*` traits (`HasAttributes`, `HasEvents`,
  `HasGlobalScopes`, `HasRelationships`, `HasTimestamps`, `HasUlids`, `HasUuids`,
  `HidesAttributes`, `GuardsAttributes`, `PreventsCircularRecursion`, `Prunable`,
  `MassPrunable`, `BroadcastsEvents`, `TransformsToResource`, etc.).
  Key properties: `$keyType='int'`, `$incrementing=true`, `$with=[]`,
  `$withCount=[]`, `$perPage=15`, `$exists`, `$wasRecentlyCreated`,
  `$escapeWhenCastingToString`, static `$globalScopes`, `$ignoreOnTouch`,
  `$modelsShouldPreventLazyLoading`, `$modelsShouldPreventAccessingMissingAttributes`,
  `$modelsShouldPreventSilentlyDiscardingAttributes`.

- 差分 (Gaps):

  Missing — eager-load / lazy-load surface:
  - `$with` / `$withCount` default-eager-load properties — absent. Phare has no
    model-level default eager loading; `with()` only works ad-hoc on the Builder.
  - `load`, `loadMissing`, `loadCount`, `loadSum/Avg/Min/Max`, `loadAggregate`,
    `loadMorph*`, `loadExists` — entire post-hoc relation-loading API absent.
  - `preventLazyLoading` / `preventsLazyLoading` / `$preventsLazyLoading` /
    `handleLazyLoadingViolationUsing` / `automaticallyEagerLoadRelationships` —
    no lazy-loading guard rails.

  Missing — persistence convenience:
  - `*OrFail` variants: `saveOrFail`, `updateOrFail`, `deleteOrFail`. Absent.
  - `saveOrIgnore` — absent.
  - `updateQuietly`, `pushQuietly`, `push` — absent (only `saveQuietly`/
    `deleteQuietly` exist).
  - `destroy` (static bulk delete by id list) — absent.
  - `forceDelete` / `forceDestroy` — live in `SoftDeletes` (US-B04 scope) but
    no base-class fallback.
  - `fresh`, `refresh` — absent (no re-hydrate-from-DB).
  - `replicate`, `replicateQuietly` — absent.
  - `newInstance`, `newFromBuilder`, `forceFill` — absent.
  - `firstOrCreate`, `updateOrCreate`, `firstOrNew`, `createOrFirst` — absent on
    the Model (Builder may carry some — see US-B02).

  Missing — keys / routing / serialization:
  - `$keyType`, `$incrementing`, `getKeyType/setKeyType`, `getIncrementing/
    setIncrementing` — absent. Phare assumes int auto-increment in `create()`
    (`ctype_digit` coercion of `lastInsertId`).
  - `getRouteKey`, `getRouteKeyName`, `resolveRouteBinding`,
    `resolveChildRouteBinding`, `resolveRouteBindingQuery` — `UrlRoutable`
    contract not implemented (route-model-binding already flagged missing in
    Area A `### Routing`).
  - `toJson`, `toPrettyJson`, `jsonSerialize`, `__toString` — absent. Phare
    Model is NOT `Jsonable`/`JsonSerializable`; only `toArray()` exists
    (and `ModelInterface` declares just `toArray()`).
  - `$perPage` / `getPerPage` / `setPerPage` — absent (pagination default).
  - `qualifyColumn`, `qualifyColumns`, `getQualifiedKeyName` — absent.
  - `is`, `isNot` — model-identity comparison — absent.
  - Queueable surface (`getQueueableId`, `getQueueableRelations`,
    `getQueueableConnection`) — absent (`QueueableEntity` not implemented).
  - Broadcast surface (`broadcastChannel`, `broadcastChannelRoute`,
    `withoutBroadcasting`) — absent.

  Missing — strict-mode / DX guards:
  - `shouldBeStrict`, `preventSilentlyDiscardingAttributes`,
    `preventAccessingMissingAttributes`, `handleDiscardedAttributeViolationUsing`,
    `handleMissingAttributeViolationUsing` — none of Laravel's strict-mode
    guards exist.

  Missing — UUID/ULID keys:
  - `HasUuids`, `HasUlids`, `HasUniqueStringIds` traits — absent. No
    non-incrementing primary-key support.

  Missing — casts API shape:
  - No `casts()` method (Laravel 11+ preferred form) — only the legacy
    `$casts` array property.
  - No custom-cast contract: there is no `Phare\Contracts\…\CastsAttributes`
    interface check in `getCastType`/`castAttribute`. `src/Phare/Eloquent/Casts/`
    ships `CastsAttributes`/`CastsInboundAttributes`/`AsArrayObject`/
    `AsCollection`/`AsStringable`/`AsEncrypted*` *class files*, but the cast
    resolver only switches on built-in string cast types — class-string casts
    appear unsupported by `castAttribute()`. Record as a likely correctness gap
    (custom cast classes shipped but not wired into the resolver).

  Type mismatch:
  - **`Model::create()`** — Phare: instance method `create(?array): bool`
    returning a success bool. Laravel: `create()` is a static Builder method
    returning the new **model instance**. Same name, different shape, different
    return — a major porting hazard.
  - **`Model::update()`** — Phare: instance `update(?array): bool`. Laravel
    base `update()` is also instance-level but returns `bool` *after* a
    `save()`; semantics align but Phare bypasses the query layer (raw
    `UPDATE … SET … WHERE` SQL string in `performUpdate`).
  - **`Model::all()`** — Phare returns `Phalcon\Mvc\Model\ResultsetInterface`;
    Laravel returns an Eloquent `Collection`. (See Phalcon leak below.)
  - **`find` / `findFirst` / `first` / `firstOrFail`** — Phare keeps Phalcon's
    `find/findFirst` naming and parameter array (`['conditions'=>…,'bind'=>…]`);
    Laravel uses `find($id)`/`findOrFail`/`first()` on the Builder with a
    different signature. `firstOrFail` throws `PhModel\Exception`, not
    `ModelNotFoundException`.
  - **`assign()`** — Phalcon-inherited signature
    `assign(array,$fillable,$dataColumnMap): PhalconModelInterface`; no Laravel
    analogue, and the return type is a raw Phalcon interface.
  - **`$guarded` default** — Phare defaults to `['*']` (fully guarded);
    Laravel 13 defaults to `[]` (Laravel removed the `['*']`-ish guard long
    ago — base `$guarded = []`). Behavioural divergence: a fresh Phare model
    with neither `$fillable` nor `$guarded` set is **totally guarded** and
    silently mass-assigns nothing.
  - Untyped vs typed: Phare property declarations are typed, but several
    inherited Phalcon finder signatures (`$parameters = null`) are loose
    `mixed` where Laravel is strict.

  Phalcon leak (Wrapper Rule §2):
  - **Structural / class-level**: `Model extends Phalcon\Mvc\Model` — the
    entire Phalcon active-record API (`getDI`, `setSource`, `useDynamicUpdate`,
    `skipAttributesOnUpdate`, `assign`, `save`/`create`/`update` Phalcon
    overloads, metadata, validation) is published on every Phare model. This is
    the single largest leak in Area B.
  - **Signature-level — return types**: `all()` and `rawFind()` return
    `Phalcon\Mvc\Model\ResultsetInterface`; `find()`/`findFirst()` declare
    `ResultsetInterface` returns; `assign()` returns
    `Phalcon\Mvc\ModelInterface`. Raw Phalcon types on Phare's own public
    signatures.
  - **Imports**: `Model.php` imports `Phalcon\Di\DiInterface`,
    `Phalcon\Mvc\Model`, `Phalcon\Mvc\Model\ResultsetInterface`,
    `Phalcon\Mvc\ModelInterface` and uses `?DiInterface $container = null` as a
    public parameter on `query()`/`newQuery()`/`newModelQuery()`/
    `newQueryWithoutScopes()` — a Phalcon DI type leaked into four public
    method signatures.
  - `HasEvents` couples to `Phalcon\Di\Di` (`Di::getDefault()`) internally —
    not a public-signature leak but a Phalcon dependency inside a Concern.
  - `ModelInterface` (`Phare\Eloquent\ModelInterface`) is a 1-method stub
    (`toArray()`); it is not even implemented by `Model` (which implements only
    `\ArrayAccess`). No real Phare contract publishes the model surface — so
    there is no contract-level leak, but also no contract.

- 工数感 (Effort): **L** — the Model is structurally welded to
  `Phalcon\Mvc\Model`, so closing the gap is not additive method work but a
  re-platforming decision. Three workstreams: (1) Decide whether to keep the
  Phalcon-AR base (accept the documented structural leak, like Container's
  `Di` exception) or build a real query-backed Model — the latter is XL and
  cascades into US-B02 (Builder). (2) Additive, lower-risk: port the missing
  pure-PHP surface — `$with`/`$withCount`, `load*`, `*OrFail`/`*Quietly`/
  `destroy`/`fresh`/`refresh`/`replicate`, JSON serialization (`toJson`/
  `jsonSerialize`/`__toString`), key-type API, `is`/`isNot`, `qualifyColumn*`.
  (3) Wire the already-shipped `Casts/*` custom-cast classes into
  `castAttribute()` and add a `casts()` method. The `create()` static-vs-
  instance return-shape clash and the `$guarded=['*']` default should be
  called out to the maintainer as the two highest-impact behavioural
  divergences before any porting starts.

---

### Builder

- 現状 (Current) — Phare:
  `Phare\Eloquent\Builder extends Phalcon\Mvc\Model\Criteria implements
  Phare\Eloquent\BuilderInterface`, and
  `BuilderInterface extends Phalcon\Mvc\Model\CriteriaInterface`.
  The whole "Eloquent query" surface is a thin facade that mutates Phalcon
  Criteria's `$params` (`conditions`/`bind`/`columns`/`order`/`group`/`limit`)
  via a custom `phalconCondition()` operator parser, then calls
  `Phalcon\Mvc\Model::rawFind($params)` or `parent::execute()` to materialise
  rows. There is **no Laravel-style `Query\Builder` separation** — Eloquent
  Builder, Query Builder and Phalcon Criteria are collapsed into one class.
  ~52 public methods.

  Public surface (counts approximate, grouped):
  - **Bootstrap / model coupling**: `setModel(Model): static`,
    `setEloquentModel(Model): static`, `getEloquentModel(): ?Model`.
  - **Scopes**: `withGlobalScope($id, $scope): static`,
    `withoutGlobalScope($scope)`, `withoutGlobalScopes($scopes = null)`,
    `applyScopes(): static`, `removedScopes(): array`, `macro(name, Closure)`.
    Scope resolution via private `resolveScopeIdentifier()`.
  - **Soft-delete dispatch stubs**: `withTrashed(bool = true)`,
    `onlyTrashed()`, `withoutTrashed()` — call `invokeMacro(__FUNCTION__)`
    and fall back to `$this`. Real impl arrives via
    `SoftDeletingScope::extend()` registering each name as a macro
    (US-B04 scope).
  - **Terminals**: `get(): ResultsetInterface|Collection`,
    `first(): ?Phalcon\Mvc\ModelInterface`,
    `last(): ?Phalcon\Mvc\ModelInterface` (non-Laravel), `update(array): int`,
    `delete(): int` (iterates models — N round-trips, not one DELETE).
  - **WHERE family**: `where`, `andWhere` (alias of `where`), `orWhere`,
    `whereIn`, `orWhereIn`, `whereNotIn`, `whereBetween`, `whereNotBetween`,
    `whereNull`, `whereNotNull`, `whereLike`, `whereNotLike`, `whereRaw`,
    `orWhereRaw`, `whereColumn`, `orWhereColumn`. Operators recognised by
    `isOperator()`: `=`, `!=`, `<>`, `>`, `<`, `>=`, `<=`, `LIKE`, `NOT LIKE`,
    `IS`, `IS NOT` (11 ops).
  - **Select / order / group / limit**: `select($cols = ['*'])`,
    `addSelect($col)`, `columns($cols)`, `orderBy($col, $dir = null)`,
    `orderByDesc(string)`, `orderByRaw(string)`, `reorder(?col, $dir='asc')`,
    `latest($col='created_at')`, `oldest($col='created_at')`,
    `groupBy($group)`, `limit($n, $offset=0)`, `forPage($p, $perPage=15)`,
    `paginate($page, $limit)`.
  - **Flow control**: `when($v, $cb=null, $default=null)`,
    `unless($v, $cb=null, $default=null)`, `tap(callable): BuilderInterface`.
  - **Eager loading**: `with($relations, $callback=null)`,
    `eagerLoadModels(array): Collection`. Internals
    (`eagerLoadRelations`/`eagerLoadRelation`/`parseWithRelations`/
    `addNestedWithRelation`/`combineConstraints`) are private.
  - **Dispatch**: `__call($method, $arguments)` — tries `macro` first,
    then `scope<Name>` on the bound model, else `BadMethodCallException`.
    No `passthru` table.

  Plus the entire inherited `Phalcon\Mvc\Model\Criteria` API
  (`bind`, `bindTypes`, `setDI`/`getDI`, `setModelName`/`getModelName`,
  `conditions`, `join`/`innerJoin`/`leftJoin`/`rightJoin`, `having`, `cache`,
  `execute`, `getParams`, `fromInput`, `betweenWhere`/`notBetweenWhere`,
  `inWhere`/`notInWhere`) — published verbatim on every Phare Builder
  (Phalcon Criteria, not Phare-authored).

- 期待 (Expected) — Laravel 13:
  `Illuminate\Database\Eloquent\Builder` (93 public methods, uses
  `BuildsQueries` + `QueriesRelationships` traits) wraps an
  `Illuminate\Database\Query\Builder` (228 public methods) via composition
  (`$this->query`). `__call` forwards a 33-entry `$passthru` table
  (`aggregate`/`avg`/`count`/`exists`/`insert`/`insertGetId`/`max`/`min`/`raw`/
  `sum`/`toSql`/`toRawSql`/`getBindings`/`dd`/`dump`/`explain`/...) plus all
  scopes/macros/dynamic `where<Column>`. Public Eloquent surface includes:
  - Find/first/sole/value: `find`, `findMany`, `findOr`, `findOrFail`,
    `findOrNew`, `findSole`, `firstOr`, `firstOrCreate`, `firstOrFail`,
    `firstOrNew`, `firstWhere`, `createOrFirst`, `sole`, `soleValue`,
    `value`, `valueOrFail`.
  - Writes: `create`, `createQuietly`, `forceCreate`, `forceCreateQuietly`,
    `update`, `updateOrCreate`, `upsert`, `forceDelete`, `delete`,
    `increment`, `incrementOrCreate`, `decrement`, `touch`,
    `fillAndInsert`, `fillAndInsertGetId`, `fillAndInsertOrIgnore`,
    `fillForInsert`, `fromQuery`, `hydrate`.
  - Streaming: `cursor`, `chunk`, `chunkById`, `chunkByIdDesc`, `each`,
    `eachById`, `lazy`, `lazyById`, `lazyByIdDesc`, `cursorPaginate`,
    `simplePaginate`, `paginate(perPage, columns, pageName, page, total)`.
  - Relations (`QueriesRelationships`): `has`, `doesntHave`, `whereHas`,
    `orWhereHas`, `whereDoesntHave`, `orWhereDoesntHave`, `withWhereHas`,
    `whereRelation`, `orWhereRelation`, `whereHasMorph`, `withCount`,
    `withSum`, `withAvg`, `withMin`, `withMax`, `withExists`.
  - Eager-load surface: `with`, `withOnly`, `without`, `withoutEagerLoad`,
    `withoutEagerLoads`, `getEagerLoads`, `setEagerLoads`,
    `eagerLoadRelations`, `withCasts`, `withAttributes`.
  - Scopes: `scopes`, `hasNamedScope`, `withoutGlobalScopesExcept`,
    `hasMacro`, `getMacro`, `applyScopes`, `removedScopes`,
    `withGlobalScope`, `withoutGlobalScope`, `withoutGlobalScopes`.
  - Key / column helpers: `whereKey`, `whereKeyNot`, `whereNot`,
    `orWhereNot`, `qualifyColumn`, `qualifyColumns`.
  - Lifecycle: `afterQuery`, `applyAfterQueryCallbacks`, `onDelete`,
    `onClone`, `clone`, `__clone`, `toBase`, `getQuery`/`setQuery`/`getModel`/
    `setModel`/`newModelInstance`, `getLimit`/`getOffset`,
    `withSavepointIfNeeded`.

  Plus the Query Builder surface (228 methods) reachable via passthru/__call:
  - **Aggregates**: `count`, `sum`, `avg`/`average`, `max`, `min`,
    `aggregate`, `numericAggregate`, `exists`, `doesntExist`, `existsOr`,
    `doesntExistOr`, `value`, `pluck`, `implode`, `rawValue`.
  - **Joins**: `join`, `leftJoin`, `rightJoin`, `crossJoin`, `joinSub`,
    `joinLateral`, `leftJoinLateral`, `joinWhere`, `leftJoinWhere`,
    `rightJoinWhere`, `crossJoinSub`, `leftJoinSub`, `rightJoinSub`,
    `straightJoin`, `straightJoinSub`, `straightJoinWhere`.
  - **WHERE extensions**: `whereNot`/`orWhereNot`, `whereAny`/`whereAll`/
    `whereNone` (+ or* variants), `whereBetweenColumns`/`whereNotBetweenColumns`
    (+ or* variants), `whereValueBetween`/`whereValueNotBetween`,
    `whereExists`/`whereNotExists` (+ or*), `whereFullText` (+ or),
    `whereJsonContains`/`whereJsonContainsKey`/`whereJsonDoesntContain`/
    `whereJsonDoesntContainKey`/`whereJsonLength`/`whereJsonOverlaps`/
    `whereJsonDoesntOverlap` (+ or* — 14 JSON methods total),
    `whereDate`/`whereDay`/`whereMonth`/`whereYear`/`whereTime` (+ or*),
    `whereIntegerInRaw`/`whereIntegerNotInRaw` (+ or*), `whereRowValues`,
    `whereNullSafeEquals` (+ or*), `whereVectorDistanceLessThan`/
    `whereVectorSimilarTo`/`orderByVectorDistance`/`selectVectorDistance`.
  - **HAVING**: `having`, `havingNested`, `havingRaw`, `havingBetween`,
    `havingNotBetween`, `havingNull`, `havingNotNull` (+ or* variants — 11).
  - **Modifiers**: `distinct`, `inRandomOrder`, `inOrderOf`, `offset`,
    `skip`, `take`, `union`, `unionAll`, `lock`/`lockForUpdate`/`sharedLock`,
    `useIndex`/`forceIndex`/`ignoreIndex`, `useWritePdo`, `timeout`,
    `beforeQuery`, `reorderDesc`, `groupByRaw`, `groupLimit`.
  - **Writes**: `insert`, `insertGetId`, `insertOrIgnore`,
    `insertOrIgnoreReturning`, `insertUsing`, `insertOrIgnoreUsing`,
    `update`, `updateFrom`, `updateOrInsert`, `upsert`, `delete`,
    `truncate`, `decrementEach`, `incrementEach`,
    `forPageAfterId`/`forPageBeforeId`.
  - **Compilation introspection**: `toSql`, `toRawSql`, `getBindings`,
    `getRawBindings`, `addBinding`, `setBindings`, `mergeBindings`,
    `castBinding`, `cleanBindings`, `dd`, `ddRawSql`, `dump`, `dumpRawSql`,
    `getColumns`, `getConnection`, `getGrammar`, `getProcessor`,
    `getCountForPagination`, `fetchUsing`, `cloneWithout`,
    `cloneWithoutBindings`.

  Public contract: Laravel does NOT publish a Builder interface — the class
  itself is the API. (Contracts/Database/Query/Builder is removed; eloquent
  builder duck-typed.)

- 差分 (Gaps):

  Missing — Eloquent Builder surface (≈ 60 of 93 methods):
  - Find / first / sole: `find`, `findMany`, `findOrFail`, `findOrNew`,
    `findOr`, `findSole`, `firstOr`, `firstOrCreate`, `firstOrFail`,
    `firstOrNew`, `firstWhere`, `createOrFirst`, `sole`, `soleValue`,
    `value`, `valueOrFail` — all 16 absent. Phare's `first()` exists but
    returns Phalcon's `?ModelInterface` and has no `*OrFail` family.
  - Writes: `create`, `createQuietly`, `forceCreate`, `forceCreateQuietly`,
    `updateOrCreate`, `upsert`, `forceDelete`, `increment`,
    `incrementOrCreate`, `decrement`, `touch`, `fillAndInsert*`,
    `fillForInsert`, `fromQuery`, `hydrate`, `newModelInstance` — absent.
    Phare carries only `update(array): int` (raw SQL UPDATE — bypasses
    Eloquent events) and `delete(): int` (per-row loop — no batch DELETE).
  - Streaming: `cursor`, `chunk`, `chunkById`, `chunkByIdDesc`, `each`,
    `eachById`, `lazy`, `lazyById`, `lazyByIdDesc`, `cursorPaginate`,
    `simplePaginate` — absent. Phare cannot stream large result sets.
  - Relation-aware queries (`QueriesRelationships`): `has`, `doesntHave`,
    `whereHas`, `orWhereHas`, `whereDoesntHave`, `orWhereDoesntHave`,
    `withWhereHas`, `whereRelation`, `orWhereRelation`, `whereHasMorph`,
    `withCount`, `withSum`, `withAvg`, `withMin`, `withMax`, `withExists`
    — all 16 absent. Eager loading exists (`with`) but no aggregate eager
    loads and no relation-based WHERE constraints.
  - Eager-load shaping: `withOnly`, `without`, `withoutEagerLoad`,
    `withoutEagerLoads`, `getEagerLoads`, `setEagerLoads`, `withCasts`,
    `withAttributes` — absent.
  - Scopes / macros: `scopes(array)`, `hasNamedScope`, `hasMacro`,
    `getMacro`, `withoutGlobalScopesExcept` — absent. Phare has `macro()`
    (only `Closure`, no `Macroable` trait) and `withoutGlobalScopes()`.
  - Key / column helpers: `whereKey`, `whereKeyNot`, `whereNot`,
    `orWhereNot`, `qualifyColumn`, `qualifyColumns` — absent.
  - Lifecycle / clone: `afterQuery`, `applyAfterQueryCallbacks`, `onDelete`,
    `onClone`, `clone`, `__clone`, `toBase`, `getQuery`, `setQuery`,
    `getModel`, `getLimit`, `getOffset`, `withSavepointIfNeeded` — absent.
    Phare has `getEloquentModel()` only; no decoupled query handle.

  Missing — Query Builder surface (≈ 180 of 228 methods):
  - Aggregates / introspection: `count`, `sum`, `avg`/`average`, `max`,
    `min`, `aggregate`, `numericAggregate`, `exists`, `doesntExist`,
    `existsOr`, `doesntExistOr`, `value`, `pluck`, `implode`, `rawValue`,
    `toSql`, `toRawSql`, `getBindings`, `getRawBindings`, `dd`, `dump`,
    `ddRawSql`, `dumpRawSql`, `explain`. Total absence of the SQL-emit /
    inspection / aggregate surface — auditing query output, eager-load
    SQL or counts is impossible from Phare's Builder.
  - Joins: `join`, `leftJoin`, `rightJoin`, `crossJoin`, `joinSub`,
    `joinLateral`, `leftJoinLateral`, `joinWhere`, `leftJoinWhere`,
    `rightJoinWhere`, `crossJoinSub`, `leftJoinSub`, `rightJoinSub`,
    `straightJoin*` — all absent on the Phare wrapper. (Phalcon Criteria
    DOES carry `join`/`innerJoin`/`leftJoin`/`rightJoin` — Phare publishes
    them by inheritance via the structural leak, but their Phalcon
    signature `(string $model, ?string $conditions, ?string $alias)` is
    not Laravel-compatible.)
  - HAVING: `having`, `havingNested`, `havingRaw`, `havingBetween`,
    `havingNotBetween`, `havingNull`, `havingNotNull`, `orHaving*` (~11) —
    no `having*` method on Phare's wrapper. Phalcon Criteria has a single
    `having($conditions)` only.
  - WHERE extensions (~50 methods absent): `whereNot`/`orWhereNot`,
    `whereAny`/`whereAll`/`whereNone` (+ or*),
    `whereBetweenColumns`/`whereNotBetweenColumns` (+ or*),
    `whereValueBetween`/`whereValueNotBetween` (+ or*),
    `whereExists`/`whereNotExists` (+ or*), `whereFullText` (+ or),
    `whereJson*` (14 methods), `whereDate`/`whereDay`/`whereMonth`/
    `whereYear`/`whereTime` (+ or*), `whereIntegerInRaw`/
    `whereIntegerNotInRaw` (+ or*), `whereRowValues`/`orWhereRowValues`,
    `whereNullSafeEquals` (+ or), `whereVectorDistanceLessThan`/
    `whereVectorSimilarTo`/`orderByVectorDistance`/`selectVectorDistance`.
  - Writes: `insert`, `insertGetId`, `insertOrIgnore`,
    `insertOrIgnoreReturning`, `insertUsing`, `insertOrIgnoreUsing`,
    `updateFrom`, `updateOrInsert`, `upsert`, `truncate`, `decrementEach`,
    `incrementEach`, `forPageAfterId`/`forPageBeforeId`, `groupLimit`,
    `inOrderOf` — absent. Bulk write API absent — every insert/update
    must round-trip through `Model::save()` / Phare's raw-SQL `update()`.
  - Modifiers: `distinct`, `inRandomOrder`, `offset`, `skip`, `take`,
    `union`, `unionAll`, `lock`/`lockForUpdate`/`sharedLock`,
    `useIndex`/`forceIndex`/`ignoreIndex`, `useWritePdo`, `timeout`,
    `beforeQuery`, `reorderDesc`, `groupByRaw`, `havingRaw`, `selectRaw`,
    `fromRaw`, `fromSub`, `selectSub`, `selectExpression`, `from`,
    `dynamicWhere`, `mergeWheres`, `forNestedWhere`, `addNestedWhereQuery`,
    `addNestedHavingQuery`, `addWhereExistsQuery`, `whereNested`,
    `prepareValueAndOperator`, `raw`, `cloneWithout`, `cloneWithoutBindings`.

  Missing — passthru / dispatch:
  - No `$passthru` table — `__call` only routes to macro then `scope*` then
    throws. Cannot do `Model::query()->count()` / `->toSql()` /
    `->exists()` — these throw `BadMethodCallException`. (Phalcon Criteria's
    `getParams()` is the only inspection escape hatch.)
  - No dynamic `where<Column>` magic (e.g. `whereName('foo')`).

  Type mismatch:
  - **`paginate($page, $limit): BuilderInterface`** — Laravel:
    `paginate($perPage = null, $columns = ['*'], $pageName = 'page',
    $page = null, $total = null): LengthAwarePaginator`.
    **Inverted argument order** (Phare puts `$page` first, `$limit` second;
    Laravel puts `$perPage` first), and Phare returns the builder (chainable)
    rather than a paginator. Same method name, opposite shape, opposite
    return — same class of porting hazard as `Model::create()` (US-B01).
    Phare's `forPage()` is closer to Laravel's `paginate` semantically
    (page + per-page → applies LIMIT/OFFSET).
  - **`first(): ?Phalcon\Mvc\ModelInterface`** — Laravel:
    `first($columns = ['*']): Model|object|static|null`. Phare's return
    type leaks Phalcon's interface and there is no `$columns` argument.
  - **`last(): ?Phalcon\Mvc\ModelInterface`** — not a Laravel Builder
    method (Laravel has no `last()` on Builder; idiomatic Laravel uses
    `latest()->first()` or `Collection::last()`). Phare-only, also leaks
    Phalcon's interface.
  - **`get(): ResultsetInterface|Collection`** — Laravel:
    `get($columns = ['*']): Collection`. Phare returns a union of Phalcon
    + Phare collection types (the branch depends on whether `eagerLoad` is
    populated). Type mismatch + Phalcon leak + behavioural divergence
    (consumers must handle two iterable shapes).
  - **`where(...)` operator handling** — Phare's `isOperator()` recognises
    11 operators (`=`, `!=`, `<>`, `>`, `<`, `>=`, `<=`, `LIKE`, `NOT LIKE`,
    `IS`, `IS NOT`). Laravel `Query\Builder::$operators` lists 30+ (incl.
    `<=>`, `not like`, `ilike`, `~`, `~*`, `!~`, `!~*`, `&`, `|`, `^`,
    `<<`, `>>`, `&~`, `is`, `is not`, `rlike`, `not rlike`, `regexp`,
    `not regexp`, `~~`, `~~*`, `!~~`, `!~~*`). Many Postgres / MySQL
    operators silently mis-bind in Phare (the operator becomes the value).
  - **`update(array): int`** — Phare builds a raw `UPDATE … SET … WHERE
    pk IN (?...)` SQL via `$model->getWriteConnection()->execute()`,
    fetching matched rows first then issuing the UPDATE. Model events
    (`updating`/`updated`/`saving`/`saved`) and `HasAttributes` mutators
    are **skipped**. Laravel `Eloquent\Builder::update()` runs the
    Query Builder UPDATE without events but at least respects timestamps
    and cast-out values via `addUpdatedAtColumn()`/`addTimestampsToUpsert
    Values()`. Silent event-bypass = correctness defect, not just parity.
  - **`delete(): int`** — Phare iterates `get()` and calls `$model->delete()`
    per row — N+1 DELETEs, fires events but is O(n) round-trips. Laravel
    emits one `DELETE … WHERE ...` SQL statement.
  - **`select($columns)` / `addSelect($column)` / `columns($cols)`** —
    Phare stores a CSV string in `$this->params['columns']`. `addSelect`
    explodes / re-implodes via comma, which corrupts column expressions
    containing commas (`COUNT(*), MAX(x)`). Laravel keeps an array of
    column expressions.
  - **`orderBy($column, ?string $direction = null)`** — Phare accepts
    `string|array` $column and emits it raw (no direction validation).
    Laravel validates direction in `['asc','desc']`, throws
    `InvalidArgumentException` otherwise. Phare also has no `orderBy`
    with sub-query / Expression.
  - **`groupBy($group)`** — single-column only, signature `(string $group)`,
    stores raw. Laravel: variadic `groupBy(...$groups)`.
  - **`limit($limit, $offset = 0)`** — Laravel separates `limit($value)`
    and `offset($value)`; Phare collapses them. No standalone
    `offset()`/`skip()`/`take()`.
  - **`with($relations, $callback = null)`** — typed return
    `BuilderInterface` (vs Laravel `static`). Otherwise close.
  - **`__call()` no-passthru** — Laravel forwards 33 builder methods to
    Query Builder via `$passthru`; Phare throws on every non-scope /
    non-macro call. Behavioural divergence affecting fluent chains
    (`->count()`, `->toSql()`, `->exists()`).
  - **`withGlobalScope` return** — Phare: `static`. Laravel: `static`.
    Aligned.
  - **`removedScopes(): array`** — aligned with Laravel.
  - Untyped Phare parameters: `$field`, `$operator`, `$value`,
    `$relations`, `$callback`, `$columns`, `$scope`, `$identifier` — all
    no type-hint; Laravel uses union/string/array/Closure types on these.

  Phalcon leak (Wrapper Rule §2):
  - **Structural / class-level**: `Builder extends Phalcon\Mvc\Model\Criteria`
    — every method on Criteria (`bind`, `bindTypes`, `setDI`, `getDI`,
    `setModelName`, `getModelName`, `conditions`, `join`, `innerJoin`,
    `leftJoin`, `rightJoin`, `having`, `cache`, `execute`, `getParams`,
    `fromInput`, `betweenWhere`, `notBetweenWhere`, `inWhere`, `notInWhere`)
    is published on every Phare Builder. Mirrors the `Model extends
    Phalcon\Mvc\Model` shape from US-B01 — second-largest single
    structural leak in Area B.
  - **Contract-level**: `BuilderInterface extends
    Phalcon\Mvc\Model\CriteriaInterface` — Phalcon's Criteria interface is
    baked into Phare's own *published* contract. Worse than the Model case
    (US-B01 has `ModelInterface` as a 1-method `toArray()` stub that the
    Model never implements; here the contract genuinely extends a Phalcon
    interface and is actually implemented). Same defect class as
    `Contracts\Http\Kernel extends Phalcon\Http\…` (US-A02) and
    `Contracts\Http\Response extends Phalcon\Http\ResponseInterface`
    (US-A05).
  - **Signature-level — return types**:
    - `get(): ResultsetInterface|Collection` (Phalcon return half).
    - `first(): ?ModelInterface` — `Phalcon\Mvc\ModelInterface`.
    - `last(): ?ModelInterface` — `Phalcon\Mvc\ModelInterface`.
    All three are public Phare methods and the interface variants of `get`,
    `first`, `last` carry the same return types — leak is published in
    `BuilderInterface` too.
  - **Imports**: `Builder.php` imports `Phalcon\Mvc\Model\Criteria`,
    `Phalcon\Mvc\Model\ResultsetInterface`, `Phalcon\Mvc\ModelInterface`.
    `BuilderInterface.php` imports `Phalcon\Mvc\Model\CriteriaInterface`,
    `Phalcon\Mvc\Model\ResultsetInterface`, `Phalcon\Mvc\ModelInterface`
    — three Phalcon imports inside Phare's own contract.
  - **Internal coupling** (not public-signature leak): private
    `eagerLoadRelations(ResultsetInterface $results)` — Phalcon type as a
    private parameter; `get()`'s branch on `is_string($modelName) &&
    method_exists($modelName, 'rawFind')` couples to Phalcon's
    `Model::rawFind()`; `$this->params` is Phalcon Criteria's protected
    state mutated directly (`$this->params['conditions']`/`['bind']`/
    `['columns']`/`['order']`/`['group']`/`['limit']`).

- 工数感 (Effort): **L** — the Builder is the second pillar of the
  Eloquent-shaped facade and its gap is structurally similar to Model
  (US-B01) but **larger in surface area**: ≈ 60 Eloquent + ≈ 180 Query
  Builder methods absent, no SQL-emit path (`toSql`/`getBindings`), no
  aggregate/JOIN/HAVING/JSON/insert surface, no relation-aware queries,
  no streaming, no passthru dispatch. Three workstreams parallel US-B01:
  (1) Decide whether to keep the Phalcon-Criteria base (accept the
  structural + contract leak as documented exceptions) or stand up a
  real `Query\Builder` (own SQL emitter or wrap `Phalcon\Db\Adapter\Pdo`
  + a Grammar) — the latter is XL and is the prerequisite for closing
  most of the missing surface. (2) Additive, lower-risk on the current
  base: port the *pure-PHP* surface — `whereNot`/`whereAny`/`whereAll`/
  `whereNone`, `whereDate*` family (compose to existing `whereRaw`),
  the relation-aware `whereHas`/`whereDoesntHave`/`withCount`/`withSum`
  family (these can be expressed on Phalcon Criteria via sub-queries),
  the `find*OrFail`/`firstOr*`/`createOrFirst`/`updateOrCreate` family,
  `value`/`pluck`/`sole`, `chunk`/`each`/`lazy` (iterate `get()`),
  `qualifyColumn(s)`, `whereKey`/`whereKeyNot`, `getQuery`/`toBase`.
  (3) Fix the named correctness defects before parity work: invert
  `paginate()`'s argument order and make it return a paginator (current
  signature will break Laravel-shaped call sites silently); make
  `update()` either emit one SQL UPDATE *and* fire model events, or fall
  back to a per-row save loop so events aren't silently skipped; expand
  `isOperator()` to cover the 30+ operator set; fix `select()`/
  `addSelect()`'s CSV-explode round-trip so expressions containing commas
  survive; add the `$passthru` dispatch so `->count()`/`->exists()`/
  `->toSql()` don't throw. The `paginate()` argument inversion and the
  silent-event `update()` are the two highest-impact behavioural
  divergences — call them out alongside US-B01's `create()`/`$guarded`
  flags before any porting starts.

---

### Relations

- 現状 (Current) — Phare:
  `src/Phare/Eloquent/Relations/` (15 files) + factory methods on the
  `HasRelationships` trait (US-B01). Inheritance tree:
  - `abstract Relation extends Phalcon\Mvc\Model\Relation` — root.
  - `abstract HasOneOrMany extends Relation` → `HasOne`, `HasMany`.
  - `BelongsTo extends Relation`.
  - `BelongsToMany extends Relation` → `MorphToMany extends BelongsToMany`
    → `MorphedByMany extends MorphToMany`.
  - `abstract HasOneOrManyThrough extends Relation` → `HasOneThrough`,
    `HasManyThrough`.
  - `MorphMany extends HasMany` (**not** the Laravel
    `MorphOneOrMany` layer — Phare collapses it). `MorphOne extends MorphMany`.
  - `MorphTo extends Relation`.
  - `Pivot extends Phare\Eloquent\Model` (no `AsPivot` trait, no
    `Contracts\Database\Eloquent\Pivot` interface). `MorphPivot extends Pivot`.

  Public surface per file (`grep -cE '^\s*public function ' Relations/*.php`):
  `Relation 16 / HasOneOrMany 5 / HasOne 3 / HasMany 3 / BelongsTo 11 /
   BelongsToMany 27 / MorphTo 9 / MorphMany 4 / MorphOne 4 /
   MorphToMany 2 / MorphedByMany 1 / HasOneOrManyThrough 9 /
   HasOneThrough 3 / HasManyThrough 3 / Pivot 8 / MorphPivot 3`.

  Factory entry points from `HasRelationships` (US-B01): `hasOne`,
  `hasMany`, `belongsTo`, `hasOneThrough`, `hasManyThrough`, `morphOne`,
  `morphMany`, `morphTo`, `belongsToMany`, `morphToMany`, `morphedByMany`,
  `newQuery(?Phalcon\Di\DiInterface)`. No `through(...)->has(...)` chain.

  Eager-load runtime — `Builder::with($relations, $callback = null)` (US-B02)
  is the single entry; `Builder::eagerLoadRelations()` (private, takes
  `Phalcon\Mvc\Model\Resultset\ResultsetInterface`) dispatches per-name
  to `Relation::addEagerConstraints()`, `getEager()`, `match()`.
  `Model::load($relations): static` re-runs the factory on the parent
  instance one relation at a time.

- 期待 (Expected) — Laravel 13:
  `Illuminate/Database/Eloquent/Relations/` (16 files + `Concerns/`):
  - `Relation` 20 public methods (incl. `sole`, `touch`, `rawUpdate`,
    `getRelationExistenceCountQuery`, `getRelationExistenceQuery`,
    `getRelationCountHash`, `getBaseQuery`, `toBase`, `createdAt`,
    `updatedAt`, `relatedUpdatedAt`, `__clone`).
  - `HasOneOrMany` 35 public (full save/create/upsert family +
    `make`/`makeMany`/`findOrNew`/`firstOrNew`/`firstOrCreate`/
    `createOrFirst`/`updateOrCreate`/`upsert`/`save{,Quietly}`/`saveMany{,Quietly}`/
    `create{,Quietly}`/`forceCreate{,Quietly}`/`createMany{,Quietly}`/
    `forceCreateMany{,Quietly}` + `take`/`limit`/key accessors).
  - `BelongsTo` 19 (`associate`/`dissociate`/`disassociate`/`touch` +
    `getRelationExistenceQueryForSelfRelation` + key accessors +
    `getRelationName`).
  - `BelongsToMany` 84 + `InteractsWithPivotTable` concern 21 (sync*,
    toggle*, attach*, detach*, updateExistingPivot*, syncWithoutDetaching,
    syncWithPivotValues, hasPivotColumn, newPivot{Statement,Query},
    withPivot, wherePivot{,In,NotIn,Null,NotNull,Between,NotBetween},
    orWherePivot{...}, orderByPivot{,Desc}, withPivotValue, find{,Many,Sole,OrFail,Or},
    firstWhere, first{,OrFail,Or,OrCreate,OrNew}, paginate/simplePaginate/
    cursorPaginate, chunk{,ById,ByIdDesc}, each{,ById}, lazy{,ById,ByIdDesc},
    cursor, touchIfTouching, allRelatedIds, save{,Quietly,Many,ManyQuietly},
    create, createMany, getRelationExistenceQuery{,ForSelfJoin}, take/limit,
    using, as, getExistenceCompareKey, withTimestamps, createdAt/updatedAt,
    ~22 key/column getters, qualifyPivotColumn).
  - `MorphTo` 18 (`morphWith`/`morphWithCount`/`constrain`/`withTrashed`/
    `withoutTrashed`/`onlyTrashed`/`associate`/`dissociate`/`touch`/
    `createModelByType`/`getDictionary`/`getMorphType`/...).
  - `MorphOneOrMany` 9 (`forceCreate`/`upsert`/`getRelationExistenceQuery`/
    `getQualifiedMorphType`/`getMorphType`/`getMorphClass`). Phare collapses
    this layer entirely.
  - `HasOneOrManyThrough` 45 (full first/find/firstOr/findOr family,
    paginate/simplePaginate/cursorPaginate, chunk*/each*/lazy*/cursor,
    `throughParentSoftDeletes`, `withTrashedParents`, key accessors).
  - `HasOne` 8 (adds CanBeOneOfMany hooks + `newRelatedInstanceFor`).
  - `Pivot` is a 0-method skeleton; `Concerns/AsPivot` ships 13 public
    methods (`getQueueableId`, `newQueryForRestoration`,
    `setRelatedModel`, `getOtherKey`, `setPivotKeys`, `unsetRelations`,
    `delete`, `hasTimestampAttributes`, `getCreatedAtColumn`,
    `getUpdatedAtColumn`, `getTable`, `getForeignKey`, `getRelatedKey`).
  - `MorphPivot` 6 (`getMorphType`/`setMorphType`/`setMorphClass`/`delete`/
    `getQueueableId`/`newQueryForRestoration`).
  - Relation-level concerns (Phare has **none** of these):
    `CanBeOneOfMany` 7 (`ofMany`/`latestOfMany`/`oldestOfMany`/
    `addOneOfManySubQueryConstraints`/`getOneOfManySubQuerySelectColumns`/
    `addOneOfManyJoinSubQueryConstraints`/`getOneOfManySubQuery`/
    `isOneOfMany`/`qualifySubSelectColumn`).
    `SupportsDefaultModels` 1 (`withDefault`).
    `SupportsInverseRelations` 5 (`inverse`/`chaperone`/
    `getInverseRelationship`/`withoutInverse`/`withoutChaperone`).
    `ComparesRelatedModels` 3 (`is`/`isNot`/`getParentKey`).
    `InteractsWithDictionary` (protected dictionary helpers).
  - `QueriesRelationships` 43 methods bolted on `Eloquent\Builder`
    (`has`/`orHas`/`doesntHave`/`orDoesntHave`/`whereHas`/`withWhereHas`/
    `orWhereHas`/`whereDoesntHave`/`orWhereDoesntHave`/`hasMorph` family/
    `whereHasMorph` family/`whereRelation`/`withWhereRelation`/
    `whereDoesntHaveRelation`/`whereMorphedTo`/`whereNotMorphedTo`/
    `whereBelongsTo`/`whereAttachedTo`/`withAggregate`/`withCount`/
    `withMax`/`withMin`/`withSum`/`withAvg`/`withExists`/
    `mergeConstraintsFrom`). Phare ships ZERO of these.

- 差分 (Gaps):
  - **Missing (per relation class):**
    - `Relation` — `sole`, `touch`, `rawUpdate`,
      `getRelationExistenceCountQuery`, `getRelationCountHash`,
      `getBaseQuery`, `toBase`, `createdAt`/`updatedAt`/`relatedUpdatedAt`,
      `__clone`. Phare's `getRelationExistenceQuery()` is a stub that
      returns `$query` unchanged — same stub-defect class as
      A04/A05/A06/A07/B01/B02. Consequence: no relation-existence
      subquery can be built, even if `whereHas` existed.
    - `HasOneOrMany` — `make`/`makeMany`/`findOrNew`/`firstOrNew`/
      `firstOrCreate`/`createOrFirst`/`updateOrCreate`/`upsert`/
      `saveMany`/`forceCreate*`/`createMany*`/`forceCreateMany*`/
      `*Quietly` variants/`matchOne`/`matchMany`/`take`/`limit`/
      `getExistenceCompareKey`/`getForeignKeyName`/`getQualifiedForeignKeyName`/
      `getLocalKeyName`. Phare ships just `save`+`create` (~30 missing).
    - `BelongsTo` — `touch`, `getRelationExistenceQueryForSelfRelation`,
      `getChild`, `getForeignKeyName`, `getQualifiedForeignKeyName`,
      `getParentKey`, `getOwnerKeyName`, `getQualifiedOwnerKeyName`,
      `getRelationName`.
    - `BelongsToMany` — `using` (custom pivot model), `withPivotValue`,
      ALL `wherePivot{Between,NotBetween,In,NotIn,Null,NotNull}` + their
      `or` variants, `orderByPivotDesc`, full
      `find/first/findOr/firstOr*/findOrFail/findSole/findMany/firstWhere/firstOrCreate/firstOrNew/createOrFirst/updateOrCreate`
      surface, `paginate`/`simplePaginate`/`cursorPaginate`,
      `chunk{,ById,ByIdDesc}`/`each{,ById}`/`lazy{,ById,ByIdDesc}`/
      `cursor`, `touchIfTouching`/`touch`, `allRelatedIds`,
      `saveMany{,Quietly}`/`saveQuietly`/`createMany`, `take`/`limit`,
      `getRelationExistenceQuery`/`getRelationExistenceQueryForSelfJoin`,
      `qualifyPivotColumn` (private in Phare), `getExistenceCompareKey`,
      ~22 key/column accessors. From `InteractsWithPivotTable`: `toggle`
      and `attach`/`detach`/`sync`/`updateExistingPivot` are present
      but their `OrFail`/`syncWithoutDetaching`/`syncWithPivotValues`/
      `hasPivotColumn`/`newPivotStatement{,ForId}`/`newPivotQuery`
      siblings are absent.
    - `MorphTo` — `morphWith`/`morphWithCount`/`constrain`/
      `withTrashed`/`withoutTrashed`/`onlyTrashed`/`associate`/
      `dissociate`/`touch`/`createModelByType`/`getDictionary` (only
      stored internally)/`getQualifiedOwnerKeyName`. Phare's `match()`
      is a no-op — it returns `$models` unchanged and relies on a
      side-effect inside `getEager()` to wire results, departing from
      the Laravel addEager/initRelation/match contract.
    - `MorphMany`/`MorphOne` — the whole `MorphOneOrMany` layer
      (`forceCreate`/`upsert`/`getQualifiedMorphType`/`getMorphType`/
      `getMorphClass` on the relation), plus inherited `HasOneOrMany`
      gaps above.
    - `HasOneOrManyThrough`/`HasOneThrough`/`HasManyThrough` —
      `firstOrNew`/`firstOrCreate`/`createOrFirst`/`updateOrCreate`/
      `firstWhere`/`first`/`firstOrFail`/`firstOr`/`find`/`findSole`/
      `findMany`/`findOrFail`/`findOr`/`paginate`/`simplePaginate`/
      `cursorPaginate`/`chunk*`/`each*`/`cursor`/`lazy*`/`take`/`limit`/
      `throughParentSoftDeletes`/`withTrashedParents`/
      `getRelationExistenceQueryForSelfRelation`/
      `getRelationExistenceQueryForThroughSelfRelation` + key accessors
      (~36 missing of 45).
    - `HasOne` — `CanBeOneOfMany` hooks, `newRelatedInstanceFor`. No
      one-of-many subquery support anywhere.
    - `Pivot` — entire `AsPivot` concern (13 methods). No
      `Contracts\Database\Eloquent\Pivot` marker interface.
    - `MorphPivot` — `getQueueableId`/`newQueryForRestoration`.
    - **Concerns wholesale missing:** `CanBeOneOfMany`,
      `SupportsDefaultModels` (`withDefault`),
      `SupportsInverseRelations` (`inverse`/`chaperone`/
      `withoutInverse`/`withoutChaperone`/`getInverseRelationship`),
      `ComparesRelatedModels` (`is`/`isNot`/`getParentKey`),
      `InteractsWithDictionary`.
    - **`QueriesRelationships` wholesale missing** — Phare `Builder`
      ships no `has`/`whereHas`/`orWhereHas`/`withWhereHas`/`doesntHave`/
      `whereDoesntHave`/`orWhereDoesntHave`/`withCount`/`withMin`/
      `withMax`/`withSum`/`withAvg`/`withExists`/`withAggregate`/
      `hasMorph`/`whereHasMorph`/`whereRelation`/`whereMorphedTo`/
      `whereBelongsTo`/`whereAttachedTo` (43 methods). The `with()`
      eager-load is the ONLY relation-aware Builder method (US-B02
      records this gap as well).
    - `Model::load`: no `loadMissing`/`loadCount`/`loadAggregate`/
      `loadMin`/`loadMax`/`loadSum`/`loadAvg`/`loadExists`/`loadMorph*`.
      Eager-load API is one verb, not the Laravel ~12.

  - **Type mismatch / behavioural divergence:**
    - `HasRelationships::hasOne`/`hasMany`/`belongsTo`/`hasOneThrough`
      return a UNION of the Phare relation class **and**
      `\Phalcon\Mvc\Model\Relation` — `HasOne|\Phalcon\Mvc\Model\Relation`
      etc. This is a **public-signature Phalcon leak** on Phare's most
      visible relationship API (factory methods on every model). The
      union is taken when called with a 3-arg "Phalcon-style" signature
      (`$fields,$referenceModel,$referencedFields[,$options]`) — i.e.
      Phare publishes BOTH Eloquent-shaped and Phalcon-shaped overloads
      from the same method.
    - `Relation::addConstraints/addEagerConstraints/initRelation/match/
      getResults/getRelationFields/getRelatedFields/getRelationType` are
      typed `: void`/`: array`/`: mixed`/`: int` — Laravel signatures
      use `void`/`array`/`Collection|Model|null` and `string` for the
      type. The `int` `getRelationType()` is Phare-specific (mirrors
      Phalcon `Relation::HAS_ONE`/`HAS_MANY`/`BELONGS_TO` constants);
      Laravel has no such concept.
    - `Relation::__call(string, array): mixed` — fluent forwarding to
      `$this->query` that rewrites `$this->query` returns back to
      `$this`. Laravel forwards via `__call` too but does not pun the
      return — Phare's chain-coercion can mask the distinction between
      builder-mutating and builder-terminating calls (e.g. `get()`
      forwarded to Builder vs Relation's own `get()`).
    - `Relation::get(): Collection` — wraps `iterator_to_array(... false)`
      around Phalcon's `ResultsetInterface`; Laravel returns the
      relation-specific Collection/Model and runs eager-load+match in
      one call (Phare's match path is decoupled and only runs through
      `eagerLoadModels`).
    - `Relation::update(array): int` and `Relation::delete(): int` —
      both delegate to `Builder::update/delete`, inheriting the
      silent-event raw-SQL `update()` defect documented in US-B02
      (events/mutators/timestamps not fired).
    - `BelongsTo::associate(Model|int|string|null)` — accepts a scalar
      key. Laravel accepts `Model|int|string`; passing `null` is the
      dissociate path. Phare's `null` branch sets the foreign key to
      `null` then unsets the loaded relation — matches Laravel's
      `dissociate()` behaviour but pretends to be `associate()`. Same
      method, slightly different shape: signed off as a Type-mismatch.
    - `BelongsToMany::attach($id, array $attributes = [], $touch = true)`
      — `$touch` is accepted then **ignored** (no `touchIfTouching`
      call). Silent argument drop. Same for `detach`/`sync`/`toggle`/
      `updateExistingPivot`. (Cf. US-B02's `update()` raw-SQL pattern —
      this is the second instance of "signature accepts a Laravel
      parameter, runtime drops it.")
    - `BelongsToMany::first(): ?Model` — restores limit via a closure
      `(function (array $params){ $this->params = $params; })->call($this->query, $params)`
      that reaches into Phalcon Criteria's private `$params`. Internal
      coupling to Phalcon Criteria internals; not a public-signature
      leak but a Wrapper Rule porting hazard.
    - `BelongsToMany::sync($ids, $detaching = true): array` — return
      shape `compact('attached','detached','updated')` matches Laravel.
      However `$touch` semantics absent (see attach/detach above) and
      no `syncWithoutDetaching`/`syncWithPivotValues`.
    - `BelongsToMany::wherePivot($column,$operator=null,$value=null)`
      — when only `(column,$operator)` is passed (Laravel shorthand for
      `column = $operator`), Phare forwards three args directly to
      `Builder::where()` whose `where($column,$operator,$value=null)`
      treats `$value` as null literal (raw bind). Same shape but the
      operator-shorthand promotion is missing.
    - `MorphTo::match()` is a documented NO-OP — return `$models`
      unchanged. The match step happens inside `getEager()` via
      side-effect on `$model->setRelation()`. This breaks the Laravel
      `addEager → initRelation → match` contract and means anyone
      replacing the builder externally will see relations that never
      get populated.
    - `Pivot::delete(): bool` — Laravel returns `int` rows-affected.
      Type mismatch.
    - `Pivot::getDeleteQuery(): array` and `getForeignKey`/`getRelatedKey`
      — Phare-specific surface; no Laravel counterpart.
    - `HasOneOrManyThrough::compileSqlAndBindings()` — bypasses the
      Phalcon query layer entirely; emits raw SQL via
      `$this->related->getReadConnection()->fetchAll($sql, FETCH_ASSOC, $bind)`.
      Internal coupling, same defect class as `BelongsToMany`'s raw SQL.
    - `HasOneOrManyThrough::hydrateRow()` — calls
      `Phalcon\Mvc\Model::cloneResultMap()` directly. Internal coupling
      to a Phalcon static; non-signature leak.

  - **Phalcon leak (Wrapper Rule §2):**
    - Structural (class-level): `abstract Relation extends Phalcon\Mvc\Model\Relation`.
      Inherits 15 public methods from Phalcon — `getFields`,
      `getForeignKey`, `getIntermediateFields`, `getIntermediateModel`,
      `getIntermediateReferencedFields`, `getOption`, `getOptions`,
      `getParams`, `getType`, `getReferencedFields`,
      `getReferencedModel`, `isForeignKey`, `isThrough`, `isReusable`,
      `setIntermediateRelation`. Every Phare relation publishes these.
      Same defect class as B01 (`Model extends Phalcon\Mvc\Model`) and
      B02 (`Builder extends Phalcon\Mvc\Model\Criteria`) — Area B's
      THIRD structural leak.
    - Signature-level (public union return): `HasRelationships::hasOne`,
      `hasMany`, `belongsTo`, `hasOneThrough` each declare
      `: <PhareRelation>|\Phalcon\Mvc\Model\Relation`. Equivalent to
      A05's `Response::redirect()/back()/redirectTo(): ResponseInterface`
      — the Phare wrapper publishes a raw Phalcon type as one half of
      a union.
    - Signature-level (parameter): `HasRelationships::newQuery(?DiInterface $container=null)`
      already counted in B01; surfaced again through the Relation
      constructor's transitive use.
    - Constant access: `Relation::HAS_ONE`/`HAS_MANY`/`BELONGS_TO` (and
      the `parent::__construct(...)` call in the Relation ctor) bind
      Phare's relations to Phalcon's relation-type enum. No public
      type leak but the contract is Phalcon's, not Phare's.
    - Internal-only (private/protected, not a public-API leak but a
      porting hazard worth noting): `BelongsToMany` and
      `HasOneOrManyThrough` import `Phalcon\Db\Enum`;
      `HasOneOrManyThrough::hydrateRow` calls `Phalcon\Mvc\Model::cloneResultMap`;
      `MorphTo::addConstraints/getResults` mutate the Phare Builder via
      `setModelName`/`setEloquentModel` (Phare-internal, no Phalcon
      leak there).

- 工数感 (Effort: L) — **Largest single Area-B subsystem.** The relations
  package is ~15 classes wrapping a Phalcon\Mvc\Model\Relation root,
  duplicating ~30% of Laravel's relation public surface. The remaining
  ~70% is missing in three families: (1) the find/first/save/create
  *Or*/*Quietly* family (~40 verbs across `HasOneOrMany`/`BelongsToMany`/
  `HasOneOrManyThrough`); (2) the relation traversal/aggregation
  family (`QueriesRelationships` 43 methods, all wholesale absent —
  `whereHas`/`withCount`/`withSum`/etc. are the single biggest user-facing
  hole in the Phare Eloquent surface); (3) the relation-modifier
  concerns (`CanBeOneOfMany` 9, `SupportsDefaultModels` 1,
  `SupportsInverseRelations` 5, `ComparesRelatedModels` 3, AsPivot 13).
  Three structural Phalcon leaks already counted (Model/Builder/Relation)
  plus the union-return leak on `HasRelationships::hasOne`/`hasMany`/
  `belongsTo`/`hasOneThrough` mean every relation factory and every
  relation instance publishes Phalcon types. Behavioural defects
  cluster around two patterns documented earlier in the audit: the
  stub-defect pattern (`getRelationExistenceQuery` returns `$query`
  unchanged — silent failure for any `whereHas` if it ever lands) and
  the silent-arg-drop pattern (`$touch` ignored on attach/detach/sync/
  toggle/updateExistingPivot — touchIfTouching never fires). Porting
  order suggestion for US-S01: (a) wrap `Relation` so it stops
  extending `Phalcon\Mvc\Model\Relation`; (b) excise the union-return
  leak by splitting the Phalcon-style overload off into its own
  protected method; (c) land `QueriesRelationships` as the single
  highest-leverage user-facing feature (unlocks `whereHas`/`withCount`/
  the existence-query stub at the same time); (d) backfill the
  Or/Quietly/find/first/save/create surface on HasOneOrMany and
  BelongsToMany together (shared trait); (e) port `CanBeOneOfMany` and
  `SupportsDefaultModels` (small, well-scoped); (f) split
  `MorphOneOrMany` out from `MorphMany`/`MorphOne` so morph one/many
  share the right ancestor. Note: a real `whereHas` requires a real
  query builder (US-B02), so this story's biggest gap is downstream of
  US-B02's structural-leak fix.

### Soft Deletes + Global Scopes

- 現状 (Current) — Phare ships four files, **all Phalcon-CLEAN** (zero
  `Phalcon\` in signatures or bodies — first Area-B subsystem with no
  Phalcon coupling in its own namespace, like Validation in A06):
  - `Eloquent/Concerns/SoftDeletes.php` — trait. Public:
    `bootSoftDeletes()`, `initializeSoftDeletes()`, `restore(): bool`,
    `delete(): bool`, `forceDelete(): bool`, `trashed(): bool`,
    `getDeletedAtColumn(): string`, `getQualifiedDeletedAtColumn(): string`
    (8). Protected `$forceDeleting`, const `DELETED_AT = 'deleted_at'`,
    protected `deleteKeyValue()`.
  - `Eloquent/Concerns/HasGlobalScopes.php` — trait. Public:
    `addGlobalScope($scope,$impl=null): void`, `hasGlobalScope($scope): bool`,
    `getGlobalScopes(): array` (**static**), `getGlobalScope($scope): Scope|Closure|null`
    (4) + protected `resolveGlobalScopeIdentifier()`. Static
    `$globalScopes` map keyed by class.
  - `Eloquent/Scope.php` — interface, single method
    `apply(Builder $builder, Model $model): void` (Phare types, no leak).
  - `Eloquent/SoftDeletingScope.php` — `implements Scope`. `apply()` +
    `extend(Builder): void` registering 5 builder macros (withTrashed,
    onlyTrashed, withoutTrashed, restore, forceDelete) + protected
    `runDeleteStateUpdate()`/`runForceDelete()`/`modelKeys()`.
  - Wiring confirmed: `Model::newQuery()` → `registerGlobalScopes(newQueryWithoutScopes())`
    iterates `getGlobalScopes()` and calls `$builder->withGlobalScope($id,$scope)`,
    which invokes `$scope->extend($builder)` (extend discovered via
    `method_exists`, matching Laravel's convention — `extend` is NOT on the
    `Scope` interface in either framework). `Builder::applyScopes()` runs
    each scope's `apply()` lazily on first `get()/first()`. So the
    SoftDeletingScope macros and the trashed filter are actually live.

- 期待 (Expected) — Laravel 13 `Database/Eloquent/SoftDeletes.php` (16
  public methods + 2 protected `performDeleteOnModel`/`runSoftDelete`),
  `Eloquent/SoftDeletingScope.php` (6 extensions + `onDelete` hook),
  `Eloquent/Scope.php` (interface, 1 method), and
  `Eloquent/Concerns/HasGlobalScopes.php` (9 public methods). Plus the
  `#[ScopedBy]` and `#[Scope]` attribute classes under
  `Eloquent/Attributes/`.

- 差分 (Gaps):
  - **Missing — SoftDeletes trait (8 of 16 public methods absent):**
    - `forceDeleteQuietly()` — no quiet (event-suppressed) variant.
    - `restoreQuietly()` — ditto.
    - `forceDestroy($ids)` — static bulk hard-delete by id list (accepts
      Collection|array|int|string). No equivalent.
    - `isForceDeleting(): bool` — Phare has the `$forceDeleting` flag but
      publishes no accessor.
    - The 5 static event-registrar shortcuts `softDeleted()`, `restoring()`,
      `restored()`, `forceDeleting()`, `forceDeleted()` (each wraps
      `registerModelEvent`). All absent — callers must use the generic
      event API (and Phare's HasEvents differs from Laravel's, see B01).
    - No `restoreOrCreate` / `createOrRestore` builder macros (Laravel
      registers them from the scope). Phare's scope registers neither.
  - **Missing — HasGlobalScopes trait (5 of 9 public methods absent):**
    - `bootHasGlobalScopes()` + `resolveGlobalScopeAttributes()` — the
      whole `#[ScopedBy]` attribute pipeline. No `Eloquent/Attributes/`
      namespace exists, so attribute-driven scopes are unsupported.
    - `addGlobalScopes(array $scopes)` — bulk register.
    - `getAllGlobalScopes()` / `setAllGlobalScopes($scopes)` — the
      whole-map getter/setter (used for test reset and scope transfer).
  - **Missing — SoftDeletingScope:**
    - `onDelete` hook on the builder: Laravel's scope registers
      `$builder->onDelete(fn → update[deleted_at])` so a **mass**
      `$query->delete()` becomes a single soft-delete UPDATE. Phare's
      `Builder` has no `onDelete`; its `delete()` instead loops
      `applyScopes()->get()` and calls `$model->delete()` per row
      (see behavioural note below). The 5-vs-6 extension gap is the two
      *OrCreate/OrRestore macros above.
  - **Missing — local-scope surface (cross-ref B02):** no `#[Scope]`
    attribute and no `Builder::scopes(array)` bulk applier. Phare supports
    only the implicit `scopeFoo()` magic via `Builder::__call`. Out of
    primary scope for this story but part of the same scope machinery.
  - **Type mismatch:**
    - `addGlobalScope()` returns `void`; Laravel returns the registered
      scope (`mixed`). Also Phare REJECTS a bare class-string: Laravel's
      4th branch `is_string && class_exists && is_subclass_of(Scope)`
      instantiates `new $scope` — Phare throws `InvalidArgumentException`
      instead, so `addGlobalScope(MyScope::class)` is unsupported.
    - `getGlobalScopes()` is **static** in Phare, **instance** in Laravel
      (`Arr::get($globalScopes, static::class, [])`). Same-name,
      different binding — porting hazard (Laravel callers do
      `$model->getGlobalScopes()`).
    - `forceDelete()`/`restore()`/`delete()` return `bool`; Laravel's
      `forceDelete()`/`restore()` return `bool|null`. Minor.
    - `Scope::apply()` declares `: void`; Laravel leaves the return
      untyped — Phare is stricter, compatible (improvement, not a gap).
  - **Phalcon leak:** **NONE in these four files.** The only Phalcon
    coupling is *inherited*: the macros and scope receive a
    `Phare\Eloquent\Builder`, which is structurally welded to
    `Phalcon\Mvc\Model\Criteria` (counted once in US-B02). Do NOT
    re-count that here. The soft-delete/global-scope layer itself
    publishes no Phalcon types — same clean-namespace finding as A06
    Validation.
  - **Behavioural divergences (raw-SQL-bypass + un-qualified-column
    defect classes):**
    1. `SoftDeletingScope::apply()` filters on the **un-qualified**
       `getDeletedAtColumn()` (`whereNull('deleted_at')`); Laravel uses
       `getQualifiedDeletedAtColumn()` (`table.deleted_at`). On any
       joined query this yields an ambiguous-column SQL error. The
       `withoutTrashed`/`onlyTrashed` macros repeat the un-qualified
       form (fallback literal `'deleted_at'`). Recurring class.
    2. `SoftDeletes::restore()` and `::delete()` emit **raw SQL** via
       `getWriteConnection()->execute('UPDATE … SET deleted_at = ? WHERE id = ?')`
       instead of the model save/query pipeline — bypasses casts,
       mutators, and Phalcon's update events. Same raw-SQL-bypass defect
       class as B02 `update()` and B03 pivot ops. Phare also overrides
       the **public** `delete()` wholesale (Laravel overrides the
       *protected* `performDeleteOnModel()`/`runSoftDelete()` and keeps
       the public delete pipeline intact).
    3. `restore()` does not set `$this->exists = true` (Laravel does);
       it patches `attributes[deleted_at]` + `parent::__set` only.
    4. The `restore`/`forceDelete` builder macros use a **load-then-mutate**
       pattern: `iterator_to_array(withTrashed()->get())` then a single
       `UPDATE/DELETE … WHERE id IN (…)`. Laravel's `restore` macro is a
       single `withTrashed()->update([deleted_at=>null])`. Phare's form
       pulls every row into memory first and fires **no** model events on
       the bulk path (whereas the per-row `Builder::delete()` loop fires
       deleting/deleted/trashed for every row — the inverse of Laravel,
       where mass `onDelete` fires none). Net: event semantics on
       bulk soft-delete/restore diverge in both directions.

- 工数感 (Effort: M) — Smaller and cleaner than B01–B03: no structural
  Phalcon leak to unwind here (the dependency is the already-counted
  Builder leak), and the core flow (boot scope → register macros →
  apply whereNull → trashed()/restore()/forceDelete()) is present and
  wired. Closing the gap is mostly **backfill + correctness**:
  (a) qualify the deleted-at column in `apply()` and the trashed macros
  (1-line fixes, high value — un-blocks joined soft-delete queries);
  (b) add the `*Quietly` / `forceDestroy` / `isForceDeleting` /
  static-event-registrar methods (mechanical, depends on a Laravel-shaped
  HasEvents from B01); (c) add `addGlobalScopes`/`getAllGlobalScopes`/
  `setAllGlobalScopes` and the class-string branch + non-void return on
  `addGlobalScope` (small); (d) introduce a real `Builder::onDelete`
  hook so mass delete becomes a single soft-delete UPDATE with
  Laravel-matching (no per-row event) semantics — this is the one item
  coupled to the B02 query-builder rework; (e) the `#[ScopedBy]`/`#[Scope]`
  attribute pipeline and `restoreOrCreate`/`createOrRestore` are
  net-new but self-contained. Route the raw-SQL `restore`/`delete`
  through whatever update path B02 produces so casts/events stop being
  bypassed (shared fix with B02/B03).


---

### Migrations + Schema Builder

- 現状 (Current) — Phare's schema/migration stack lives entirely under
  `src/Phare/Database/` and is **Phalcon-clean by inheritance** (no class
  here `extends` a Phalcon type), but it **publishes raw Phalcon PDO as a
  dependency** rather than wrapping it:

  - **`Database\Schema\Blueprint`** (251 lines) — table-definition collector.
    Column-type verbs (all return `ColumnDefinition`): `id`, `bigIncrements`,
    `increments`, `string($col,$len=255)`, `text`, `longText`, `integer`,
    `bigInteger`, `decimal($col,$p=8,$s=2)`, `float($col,$p=53)`, `double`,
    `boolean`, `date`, `dateTime`, `timestamp`, `json`, `binary`,
    `enum($col,$values)` — **18 types**. Composites: `timestamps(): void`,
    `softDeletes(): void`. FKs: `foreign($col): ForeignKeyDefinition`,
    `foreignId($col)` (= `bigInteger->unsigned`), `foreignIdFor($model,$col=null)`.
    Indexes `primary`/`unique`/`index`/`fulltext` — all return **`void`**
    (not chainable). Drops: `dropColumn`, `dropPrimary`, `dropUnique`,
    `dropIndex`, `dropForeign`, `renameColumn`. Getters
    `getTable`/`getColumns`/`getCommands`/`isUpdating`. Terminal:
    `toSql(\Phalcon\Db\Adapter\Pdo\AbstractPdo $connection, Grammar $grammar): array`.
  - **`Database\Schema\ColumnDefinition`** (140 lines, Phalcon-clean) —
    15 fluent modifiers returning `self`: `nullable($v=true)`, `default($v)`,
    `unsigned`, `autoIncrement`, `primary`, `unique`, `index`, `comment`,
    `after`, `first`, `charset`, `collation`, `change`, `useCurrent`,
    `useCurrentOnUpdate`.
  - **`Database\Schema\ForeignKeyDefinition`** (123 lines, Phalcon-clean) —
    `references`, `on`, `onDelete`, `onUpdate`, `cascadeOnDelete`,
    `cascadeOnUpdate`, `restrictOnDelete`, `restrictOnUpdate`, `nullOnDelete`,
    `noActionOnDelete`, `noActionOnUpdate`, `name` + getters. **Most
    Laravel-complete file in the subsystem.**
  - **`Database\Schema\SchemaBuilder`** (154 lines) — `__construct(\Phalcon\Db\Adapter\Pdo\AbstractPdo $connection)`;
    8 public verbs: `create($t,Closure)`, `table($t,Closure)`, `drop`,
    `dropIfExists`, `rename`, `hasTable`, `hasColumn`, `getColumnListing`.
    Driver introspection (`hasTable`/`hasColumn`/`getColumnListing`) is
    **inline `match($driver)` raw SQL** against `information_schema` /
    `sqlite_master` / `PRAGMA`, using `Phalcon\Db\Enum::FETCH_ASSOC`.
    Grammar is hard-selected by driver string in `getGrammar()`.
  - **`Database\Schema\Grammar`** (abstract) + `Grammars\{MySql,Postgres,Sqlite}Grammar`
    — `compileCreate`/`compileAdd`/`compileDrop`/`compileDropIfExists`/
    `compileRename` + `compileBlueprint(Blueprint, \Phalcon\Db\Adapter\Pdo\AbstractPdo): array`.
  - **`Database\Migrator`** (268 lines) — `__construct(Application $app, \Phalcon\Db\Adapter\Pdo\AbstractPdo $connection)`;
    4 public verbs: `run($paths=[]): array`, `rollback($steps=1): array`,
    `reset(): array`, `refresh($paths=[]): array`. The migration **log is
    inlined** — raw `INSERT/DELETE/SELECT` against a `migrations` table
    interpolated as `{$this->table}` directly inside the Migrator (no
    repository class). Anonymous-class migrations supported (`require`
    returns a `Migration` instance → `setSchema`); else snake→PascalCase
    `new $class`. Each migration wrapped in `begin()/commit()` with
    `rollback()` on exception.
  - **`Database\Migration`** (abstract, 66 lines, Phalcon-clean) —
    `abstract up(): void`, `down(): void`, `setSchema`, + protected proxy
    helpers `table`/`create`/`dropIfExists`/`drop`/`rename`/`hasTable`/`hasColumn`
    that delegate to the injected `SchemaBuilder`. Phare migrations author
    via `$this->create(...)`, **not** the `Schema::` facade.
  - **No published contract** — `Contracts/` has nothing for Database / Schema /
    Migration. There is no `MigrationRepositoryInterface` equivalent and no
    Schema/Builder interface; the entire surface is concrete classes.

- 期待 (Expected) — Laravel 13 `Database/Schema/{Blueprint,Builder}.php`,
  `Schema/ColumnDefinition.php`, `Schema/ForeignKeyDefinition.php`,
  `Database/Migrations/{Migrator,Migration,MigrationRepositoryInterface,DatabaseMigrationRepository,MigrationCreator}.php`:
  - **`Blueprint`** — 123 public methods incl. ~75 column-type/composite verbs:
    `char`, `string`, `tinyText`, `text`, `mediumText`, `longText`,
    `tinyInteger`/`smallInteger`/`mediumInteger`/`integer`/`bigInteger` +
    all `unsigned*` variants, `tinyIncrements`…`bigIncrements`,
    `integerIncrements`, `float`, `double`, `decimal`, `boolean`, `enum`,
    `set`, `json`, `jsonb`, `date`, `dateTime`, `dateTimeTz`, `time`,
    `timeTz`, `timestamp`, `timestampTz`, `timestamps`, `timestampsTz`,
    `nullableTimestamps`, `year`, `binary`, `uuid`, `ulid`, `ipAddress`,
    `macAddress`, `geometry`, `geography`, `vector`, `tsvector`, `computed`,
    `rememberToken`, `morphs`/`nullableMorphs`/`uuidMorphs`/`ulidMorphs`/
    `numericMorphs` (+ nullable variants), `foreignId`, `foreignUuid`,
    `foreignUlid`, `foreignIdFor`; chainable index methods returning
    `IndexDefinition` (`primary`/`unique`/`index`/`fullText`/`spatialIndex`/
    `rawIndex`); the full drop family (`dropMorphs`, `dropSoftDeletes(Tz)`,
    `dropTimestamps(Tz)`, `dropRememberToken`, `dropConstrainedForeignId(For)`,
    `renameIndex`, `dropFullText`, `dropSpatialIndex`); table options
    `engine`/`charset`/`collation`/`temporary`/`comment`; `getState`/`build`.
  - **`Schema\Builder`** — 42 public methods incl. `createDatabase`,
    `dropDatabaseIfExists`, `dropAllTables`/`dropAllViews`/`dropAllTypes`,
    `hasColumn(s)`, `hasIndex`, `hasView`, `getColumns`, `getColumnType`,
    `getTables`/`getTableListing`/`getViews`/`getIndexes`/`getIndexListing`/
    `getForeignKeys`/`getSchemas`, `enable/disable/withoutForeignKeyConstraints`,
    `dropColumns`, `whenTableHasColumn`/`whenTableDoesntHaveColumn` (+ index),
    `getConnection`, `blueprintResolver`. Introspection delegated to the
    grammar, not inlined in the builder.
  - **`ColumnDefinition`** — 30 fluent modifiers (the 15 Phare lacks:
    `always`, `from`, `fulltext`, `generatedAs`, `instant`, `invisible`,
    `lock`, `persisted`, `spatialIndex`, `vectorIndex`, `startingValue`,
    `storedAs`, `type`, `virtualAs`).
  - **`ForeignKeyDefinition`** — same cascade/restrict surface as Phare **plus**
    `deferrable`, `initiallyImmediate`, `lock`; references composite columns;
    `ForeignIdColumnDefinition::constrained()` ergonomic.
  - **`Migrator`** — 22 public methods (`run`, `runPending`, `rollback`,
    `reset`, `resolve`, `path`/`paths`, `getMigrationFiles`, `requireFiles`,
    `getMigrationName`, `getRepository`, `repositoryExists`,
    `hasRunAnyMigrations`, `deleteRepository`, `setOutput`, `getConnection`/
    `setConnection`/`usingConnection`/`resolveConnection`, `getFilesystem`,
    `fireMigrationEvent`). Options-array driven (`['step'=>n,'pretend'=>true]`),
    fires 6 migration events, swappable repository.
  - **`MigrationRepositoryInterface`** (12 methods) +
    `DatabaseMigrationRepository` — log abstraction so the migration history
    store is parameterised and testable.
  - **`Migration`** base — `getConnection`, `shouldRun`, `$connection`,
    `$withinTransaction`. Migrations call `Schema::` facade, not proxy helpers.

- 差分 (Gaps)
  - **Phalcon leak — published-dependency form (NEW shape for Area B).**
    No class here inherits a Phalcon type (unlike B01 Model / B02 Builder /
    B03 Relation structural leaks), yet four public signatures **publish raw
    `\Phalcon\Db\Adapter\Pdo\AbstractPdo`**:
    `Blueprint::toSql(...)`, `SchemaBuilder::__construct(...)`,
    `Grammar::compileBlueprint(...)`, `Migrator::__construct(...)`. There is
    **no Phare `Connection` wrapper** — the whole schema/migration layer
    operates directly on the Phalcon PDO adapter
    (`execute`/`fetchOne`/`fetchAll`/`begin`/`commit`/`rollback`/`getType`)
    and on `Phalcon\Db\Enum::FETCH_ASSOC`. Record this as a distinct leak
    class from B01–B03: *dependency/parameter leak*, not *inheritance/contract
    leak*. Closing it requires wrapping the connection (broader DB-layer work),
    so it is the same root cause across all four files.
  - **Missing — Blueprint column types (~57).** Phare ships 18 of ~75. Absent:
    `char`, `tinyText`, `mediumText`, `tinyInteger`, `smallInteger`,
    `mediumInteger`, all `unsigned*` integer variants, all `*Increments`
    except `increments`/`bigIncrements`, `set`, `jsonb`, `dateTimeTz`,
    `time`/`timeTz`, `timestampTz`, `timestampsTz`, `nullableTimestamps`,
    `year`, `uuid`, `ulid`, `ipAddress`, `macAddress`, `geometry`,
    `geography`, `vector`, `tsvector`, `computed`, `rememberToken`, the
    entire `morphs` family (`morphs`/`nullableMorphs`/`uuidMorphs`/
    `ulidMorphs`/`numericMorphs` + nullable), `foreignUuid`, `foreignUlid`.
    (`id`/`decimal(8,2)`/`float(53)`/`bigIncrements`/`enum` **do** match
    Laravel 13 — including the L11 `float()` scale-drop.)
  - **Missing — Blueprint drops/table-options.** No `dropMorphs`,
    `dropSoftDeletes(Tz)`, `dropTimestamps(Tz)`, `dropRememberToken`,
    `dropConstrainedForeignId(For)`, `renameIndex`, `dropFullText`,
    `dropSpatialIndex`; no `engine`/`charset`/`collation`/`temporary`/`comment`
    table-level options; no `getState`/`build`.
  - **Missing — ColumnDefinition modifiers (~14).** See 期待 list; notably
    no generated/virtual/stored columns (`virtualAs`/`storedAs`/`generatedAs`),
    no `invisible`, no `spatialIndex`/`vectorIndex`, no `type` override,
    no auto-increment `startingValue`/`from`.
  - **Missing — SchemaBuilder methods (~34).** No DB-level
    (`createDatabase`/`dropDatabaseIfExists`), no bulk drop
    (`dropAllTables`/`dropAllViews`/`dropAllTypes`), no introspection
    (`getColumns`/`getColumnType`/`getTables`/`getIndexes`/`getForeignKeys`/
    `getViews`/`getSchemas`), no `hasIndex`/`hasView`/`hasColumns`, no
    FK-constraint toggles (`enable/disable/withoutForeignKeyConstraints`),
    no conditional `whenTableHasColumn` family, no `dropColumns`,
    no `getConnection`/`blueprintResolver`.
  - **Missing — Migrator + repository.** No `runPending`, no options-array
    (`step`/`pretend`/`force`), **no pretend/dry-run** (`getQueries`), no
    `path`/`paths`/`getMigrationName`/`hasRunAnyMigrations`/`getRepository`/
    `repositoryExists`/`deleteRepository`/`setOutput`/`getFilesystem` and no
    connection selection (`setConnection`/`usingConnection`). **No migration
    events** (Laravel fires `MigrationsStarted`/`MigrationStarted`/
    `MigrationEnded`/`MigrationsEnded`/`NoPendingMigrations`/`SchemaLoaded`;
    Phare fires none). **No `MigrationRepositoryInterface`** — the history
    store is inlined SQL, not a swappable abstraction. No `schema:dump`/
    squashing, no `MigrationResult`.
  - **Missing — Migration base.** No `$connection` (per-migration connection),
    no `$withinTransaction` toggle, no `shouldRun`, no `getConnection`.
  - **Type mismatch — index methods non-chainable.** Phare
    `primary`/`unique`/`index`/`fulltext` return `void`; Laravel returns
    `IndexDefinition` → cannot set index `algorithm`/language/name fluently.
  - **Type mismatch — `Migrator::rollback($steps=1)` (porting hazard).**
    Phare's `$steps` is a positional int = *number of distinct batches* to
    roll back (`getLastBatch` `LIMIT steps`). Laravel's
    `rollback($paths=[], array $options=[])` rolls back the **last batch** by
    default and reads `['step'=>n]` to count *migrations*. Same verb, different
    arg shape **and** different unit (batches vs migrations / int vs options
    array) — third instance of the "same name, opposite shape" hazard after
    `Model::create()` (B01) and `Builder::paginate()` (B02). `run()`/`refresh()`
    likewise drop Laravel's options array.
  - **Correctness — `runDown()` swallows failures.** A throwing `down()` is
    caught, returns `false`, the loop **skips `removeFromLog`** and surfaces
    **no error** — a failed rollback silently no-ops while reporting success
    upstream. Error-swallow defect (cf. the stub/no-op defect family:
    Request::route A04, Response::view A05, validator exists/unique A06,
    View::render A07, Relation::getRelationExistenceQuery B03).
  - **Correctness/divergence — illusory DDL transaction.** `runMigration`
    unconditionally wraps `up()` in `begin()/commit()`; on MySQL, DDL
    (`CREATE/ALTER TABLE`) implicitly commits, so the wrap gives **no
    rollback guarantee** there. Laravel only wraps when the grammar reports
    `supportsSchemaTransactions` **and** the migration opts in via
    `$withinTransaction`.
  - **Divergence — introspection inlined in the builder.** `hasTable`/
    `hasColumn`/`getColumnListing` embed per-driver `match()` SQL inside
    `SchemaBuilder` instead of delegating to the grammar (Laravel pushes
    introspection into `Schema/Grammars/*` + `processColumnListing`).
    Maintainability/coupling defect — adding a driver means editing the
    builder, not just a grammar.
  - **Divergence — `foreignId()` has no `constrained()`.** Returns a plain
    `ColumnDefinition`; the `foreignId('user_id')->constrained()`
    auto-FK/auto-index ergonomic and `ForeignIdColumnDefinition` are absent.
    FK references are single-column only (no composite).

- 工数感 (Effort: L) — Largest remaining Area-B subsystem after the Eloquent
  trio. The work splits four ways: (1) **column-type backfill** — ~57 missing
  Blueprint verbs + ~14 modifiers, mostly mechanical but each needs a grammar
  type-map entry across all three `Grammars/*` (and the morphs/uuid/ulid
  composites pull in cast/key conventions); (2) **schema introspection +
  builder breadth** — ~34 `Builder` methods (`getColumns`/`getIndexes`/
  `getForeignKeys`/FK toggles/bulk drops), ideally moved out of the builder
  into the grammars; (3) **migration engine** — repository abstraction
  (`MigrationRepositoryInterface` + DB repository), events, options-array
  (`step`/`pretend`/`force`), pretend/dry-run, per-migration connection +
  `$withinTransaction`, and fixing the `rollback` unit/arg mismatch and the
  silent `runDown` swallow; (4) **the Phalcon-PDO dependency leak** —
  removing raw `AbstractPdo` from `Blueprint`/`SchemaBuilder`/`Grammar`/
  `Migrator` signatures requires a Phare `Connection` wrapper, which is shared
  DB-layer work, not local to this subsystem. FK definitions (the one
  near-complete piece) need only `deferrable`/`initiallyImmediate`/`lock` +
  composite/`constrained()`. Net: deep but mostly additive; no inheritance
  to unwind, but the connection-wrapper item gates true Wrapper-Rule
  compliance.

---

### Seeders + Factories

- 現状 (Current) — Phare ships **three** classes plus two console commands.
  None extend a Phalcon class, but the DB-access surface drives Phalcon PDO
  directly (same "published dependency" shape as Migrations/Schema, B05).
  - **`Phare\Database\Seeder`** (`abstract`): props `protected Application $app`,
    `protected AbstractPdo $db` (resolved in ctor via `$app->make('db')`).
    `__construct(Application $app)`. `abstract public run(): void`.
    Helpers: `protected call(string|array $seeders): void` (loops, instantiates
    string seeders with `new $seeder($app)`, calls `run()`),
    `protected create(string $table, array $data): void` (raw multi-row
    `INSERT` via `$db->execute`), `protected table(string): SeederTable`,
    `protected wrapTable`/`wrapColumn` (backtick-quote, MySQL-only),
    `protected factory(string $model, int $count=1): Factory`
    (`$app->make(Factory::class)->for($model)->count($count)`).
  - **`Phare\Database\Seeder` → bundled `class SeederTable`** (same file): ctor
    `__construct(AbstractPdo $db, string $table)`; `insert(array): void`,
    `truncate(): void`, `delete(): void` — all raw backtick-quoted SQL.
  - **`Phare\Database\Factory`** (concrete, container-bound runtime service —
    NOT a per-model base): props `Application $app`, `protected AbstractPdo $db`,
    `string $model`, `int $count=1`, `array $states=[]`, `afterMaking`/
    `afterCreating` callback arrays. Fluent: `for(string $model): self` (SETS
    the target model), `count(int): self`, `state(array): self` (flat
    `array_merge`), `afterMaking(\Closure): self`, `afterCreating(\Closure):
    self`. Terminal: `make(array=[]): array` (returns a plain assoc array, or
    array-of-arrays when `count>1` — never a model), `create(array=[]): array`
    (calls `make`, then `saveInstance` = raw `INSERT`, fires afterCreating).
    Protected: `makeInstance` (`definition ∪ states ∪ attributes`),
    `saveInstance` (raw `INSERT`, return value discarded — no PK back-fill),
    `getDefinition` (`new Database\Factories\{ModelBasename}Factory` →
    `->definition()`; throws `\RuntimeException` if class missing),
    `getFactoryClass`, `getTableName` (`strtolower(basename($model)).'s'`).
  - **`Phare\Database\BaseFactory`** (`abstract`, the user-facing factory base):
    `abstract definition(): array`; `protected faker(): \Faker\Generator`
    (`Faker\Factory::create()` — fresh generator every call). Phalcon-CLEAN
    (Faker only). This is ALL a Phare factory author gets — no state, no
    relationship, no sequence, no configure.
  - **Console**: `SeedCommand` (`db:seed {--class=} {--force}`) instantiates
    `new $seederClass($app)` and calls `run()` directly (does NOT set
    container/command); resolves bare name → `Database\Seeders\{name}`; blocks
    in `production` without `--force`; wraps in try/catch → `error()` + exit 1.
    `MakeSeederCommand` (`make:seeder {name}`) stubs a `Database\Seeders\*`
    class extending `Phare\Database\Seeder`. **No `make:factory` command, no
    `HasFactory` trait, no `Factory::new()`/`Model::factory()` entry point.**
  - Dead code: `Phare\Console\Exceptions\FactoryNotFound::factoryDefinitionNotFound`
    exists but is never referenced (Factory throws raw `\RuntimeException`).

- 期待 (Expected) — Laravel 13:
  - **`Illuminate\Database\Seeder`** (`abstract`, 8 public methods + `__invoke`):
    `call($class, $silent=false, array $parameters=[]): $this`,
    `callWith($class, array $parameters=[])`, `callSilent($class, $parameters=[])`,
    `callOnce($class, $silent=false, $parameters=[])` (dedup via static
    `$called`), `setContainer(Container): $this`, `setCommand(Command): $this`,
    `protected resolve($class)` (container-resolves + injects
    container/command), `__invoke(array $parameters=[]): mixed` (requires a
    `run` method, container-`call`s it for dependency injection, honours the
    `WithoutModelEvents` trait). `run()` is NOT abstract — discovered
    reflectively. Console output via `TwoColumnDetail` (RUNNING/DONE + ms).
  - **`Illuminate\Database\Eloquent\Factories\Factory`** (`abstract`, ~50
    public/static methods) — a **per-model** definition class returning **model
    instances**:
    - State/attrs (8): `raw`, `state`, `prependState`, `set`, `sequence`,
      `forEachSequence`, `crossJoinSequence`, `configure`.
    - Make/create (12): `make`, `makeOne`, `makeMany`, `create`, `createOne`,
      `createOneQuietly`, `createMany`, `createManyQuietly`, `createQuietly`,
      `lazy`, `insert`, `newModel`. All return `Model`/`Collection`/`callable`.
    - Relationships (6): `has`, `hasAttached`, `for`, `recycle`,
      `getRandomRecycledModel`, `withoutParents`.
    - Lifecycle (4): `afterMaking`, `afterCreating`, `withoutAfterMaking`,
      `withoutAfterCreating`.
    - Config/meta (5): `count`, `connection`, `getConnectionName`, `modelName`,
      `newModel`.
    - Static (10): `new`, `times`, `guessModelNamesUsing`, `useNamespace`,
      `factoryForModel`, `guessFactoryNamesUsing`,
      `expandRelationshipsByDefault`, `dontExpandRelationshipsByDefault`,
      `resolveFactoryName`, `flushState`.
    - `__call` forwards `state`-style magic; `withFaker()` pulls a memoised
      `Faker\Generator` from the container.
  - **Supporting cast**: `HasFactory` trait (`static factory($count=null,
    $state=[])` on the Model), `Sequence`, `CrossJoinSequence`,
    `Relationship`/`BelongsToRelationship`/`BelongsToManyRelationship`,
    `Attributes\UseModel`, `Database\Console\Seeds\WithoutModelEvents`.

- 差分 (Gaps):
  - **Missing — Seeder (≈7 of 8 public methods + plumbing):** `callWith`,
    `callSilent`, `callOnce`+static `$called` dedup, `setContainer`,
    `setCommand`, `resolve`, `__invoke`. Phare's `call()` is `protected`
    (Laravel: `public`, chainable `: $this`), drops the `$silent` and
    `$parameters` args, and does no DI / no console RUNNING-DONE output.
    `WithoutModelEvents` trait integration absent. `run()` is forced `abstract`
    (no reflective discovery / container `call`).
  - **Missing — Factory (≈45 of ~50 methods) + whole architecture:** the
    Phare `Factory` is a single runtime service that returns **arrays**, so the
    entire Laravel model is absent — no `Model::factory()`/`HasFactory`, no
    static `new`/`times`, no model-instance returns, no `raw`, no
    `makeOne`/`makeMany`/`createOne*`/`createMany*`/`*Quietly`/`lazy`/`insert`,
    no relationship factories (`has`/`hasAttached`/`for`-as-parent/`recycle`),
    no `Sequence`/`forEachSequence`/`crossJoinSequence`, no `configure`,
    no `connection`/`getConnectionName`, no `prependState`/`set`, no
    `withoutAfterMaking`/`withoutAfterCreating`/`withoutParents`, no namespace
    guessing statics, no `flushState`. `BaseFactory` exposes only
    `definition()`+`faker()`.
  - **Missing — tooling:** no `make:factory` command; `SeedCommand` never wires
    container/command onto the seeder, so even the present Laravel seeder
    plumbing would be dead; no `--database` connection option.
  - **Type mismatch — `Factory::for()` name-clash (same-name/opposite-meaning
    porting hazard).** Phare `for(string $model): self` SETS the target model;
    Laravel `for($factory, $relationship=null)` attaches a parent *belongsTo*
    relationship. Running list of this hazard: B01 `Model::create()`,
    B02 `Builder::paginate()`, B05 `Migrator::rollback()`, now `Factory::for()`.
  - **Type mismatch — return shapes.** Phare `make`/`create` return
    `array` (or array-of-arrays); Laravel returns `Model`/`Collection`/`mixed`.
    Phare `count()`/`state()`/`afterMaking()`/`afterCreating()` return `self`
    (Laravel `static`/`$this` — close, but Phare classes are untyped against
    Laravel's typed unions e.g. `int|iterable|null`). `state(array)` cannot
    take a closure, so per-instance/sequenced state is impossible.
  - **Type mismatch — Seeder `create()`/`table()`/`factory()` are non-Laravel
    additions** (Laravel `Seeder` has no data-insertion surface at all; seeding
    goes through models/factories). They publish a parallel raw-SQL API.
  - **Phalcon leak — public-signature:** `SeederTable::__construct(AbstractPdo
    $db, string $table)` publishes raw `\Phalcon\Db\Adapter\Pdo\AbstractPdo`
    on a public constructor — a true §2 signature leak (first in this
    subsystem).
  - **Phalcon leak — published dependency (protected property):**
    `Seeder::$db` and `Factory::$db` are typed `AbstractPdo`; both classes are
    designed for extension/use, and every insert path drives Phalcon PDO
    (`execute`) + backtick (MySQL-only) quoting directly. No Phare `Connection`
    wrapper — identical root cause to B05 (Migrations/Schema). `BaseFactory`
    is Phalcon-CLEAN.
  - **Correctness — Factory never instantiates the model (raw-SQL bypass).**
    `create()` builds a plain array and emits a raw `INSERT`; it never news the
    Eloquent model, so casts, mutators, `creating`/`created` events,
    timestamps, and PK back-fill are ALL skipped, and the inserted id is
    discarded (`execute` return ignored). Same raw-SQL-bypass defect family as
    B02 `update()`, B03 pivots/through-relations, B04 soft-delete
    `restore()`/`delete()`.
  - **Correctness — naive pluralization.** `Factory::getTableName()` =
    `strtolower(basename($model)).'s'`, ignoring the model's own
    `$table`/`getTable()`. Breaks irregulars (`Person`→`persons`,
    `Category`→`categorys`, `Company`→`companys`, `Mouse`→`mouses`).
  - **Correctness — `BaseFactory::faker()` calls `Faker\Factory::create()` on
    every invocation** (new heavy generator per attribute), vs Laravel's
    memoised container-bound faker — perf + non-reproducible-seed defect.
  - **Correctness — no dedup / partial-seed.** Seeder `call()` has no
    `callOnce`/`$called`, so re-entrant seeders re-insert; `SeedCommand` runs
    outside any transaction and swallows exceptions after partial inserts.
  - **Dead-code inconsistency** — `FactoryNotFound::factoryDefinitionNotFound`
    is shipped but unused; `Factory::getDefinition()` throws raw
    `\RuntimeException`.

- 工数感 (Effort: L) — Not a backfill; an **architecture inversion**. Phare's
  factory is a runtime array-builder, whereas Laravel's is a per-model,
  model-returning definition class driving the Eloquent persistence pipeline.
  Reaching parity means: (1) introduce `HasFactory` + `Model::factory()` +
  static `new`/`times` discovery; (2) re-base `BaseFactory` as the real
  abstract `Factory` with the ~50-method state/make/create/relationship/
  sequence surface returning **model instances** (which in turn depends on a
  functioning `Model::create()`/save pipeline — gated by B01/B02 defects);
  (3) add `Sequence`/`CrossJoinSequence`/relationship-factory classes;
  (4) bring `Seeder` up to the container/command/`$called`/`__invoke`/
  `WithoutModelEvents` surface and wire `SeedCommand` to inject them;
  (5) add `make:factory`. The Phalcon-PDO dependency leak (`SeederTable` ctor +
  `$db` props) rides on the same missing `Connection` wrapper as B05 — shared
  DB-layer work, not local. Net: deep, and partly blocked on the Eloquent
  model/builder fixes upstream.

---

### Pagination

- 現状 (Current) — Phare ships **5 classes** in `src/Phare/Pagination/`, all
  Phalcon-clean (zero `Phalcon\` refs anywhere). No `Phare\Contracts\Pagination\*`
  namespace exists — none of the classes implement a published interface.
  - **`Phare\Pagination\Paginator`** (the BASE — there is no `AbstractPaginator`):
    `implements \Countable, \IteratorAggregate, \JsonSerializable, Arrayable,
    Jsonable`. ctor `($items, int $perPage, ?int $currentPage = null, array
    $options = [])`; `setItems()` slices to `perPage` and sets `$hasMore` from a
    `count > perPage` probe (simple-paginator "fetch perPage+1" convention).
    Surface: `url(int): string`, `appends`, `fragment`, `nextPageUrl`,
    `previousPageUrl`, `items(): Collection`, `firstItem`, `lastItem`, `perPage`,
    `currentPage`, `hasPages`, `hasMorePages`, `onFirstPage`, `getIterator`,
    `isEmpty`, `isNotEmpty`, `count`, `getCollection`, `setCollection`,
    `getOptions`, `getUrlRange`, `toArray`, `jsonSerialize`, `toJson`,
    `__toString`, `withQueryString`, `path`, `withPath`, `setPath`, `getPageName`,
    `setPageName`, `onEachSide`, static `make`. Resolvers present & at parity:
    static `resolveCurrentPage`/`currentPageResolver`,
    `resolveCurrentPath`/`currentPathResolver`,
    `resolveQueryString`/`queryStringResolver`. Public prop `int $onEachSide = 3`.
  - **`Phare\Pagination\LengthAwarePaginator extends Paginator`**: adds `total`,
    `lastPage`, override `hasMorePages`/`firstItem`/`lastItem`, `through(callable)`,
    `render(?string $view=null, array $data=[]): string`, `links(...)` (alias →
    render), `simplePaginate(): string` (renders `simpleView`), protected
    `defaultView`/`simpleView`/`linkCollection`, override `getUrlRange`/`toArray`,
    static `make` (reads `$options['total']`).
  - **`Phare\Pagination\CursorPaginator`** (standalone — NO shared base, NO
    `AbstractCursorPaginator`): `implements \Countable, \IteratorAggregate,
    \JsonSerializable, Arrayable, Jsonable`. ctor `($items, int $perPage,
    ?Cursor $cursor=null, array $options=[])`. Surface: `items(): array`,
    `through`, `perPage`, `hasMorePages`, `hasPages`, `onFirstPage`, `onLastPage`,
    `getIterator`, `count`, `getOptions`, `path`, `appends`, `withQueryString`,
    `fragment`, `url(?Cursor)`, `previousCursor`, `nextCursor`,
    `previousPageUrl`, `nextPageUrl`, `getCursorForItem`, `getParametersForItem`,
    `toArray`, `jsonSerialize`, `toJson`, `toPrettyJson`. The query/fragment
    helpers (`appends`/`fragment`/`withQueryString`/`addQuery`/`buildFragment`)
    are **copy-pasted** from `Paginator`, not shared.
  - **`Phare\Pagination\Cursor implements Arrayable`** — near-parity (see below).
  - **`Phare\Pagination\UrlWindow`** — structural near-parity with Laravel's, but
    **orphaned** (see Defects); ctor/`make` take the concrete
    `LengthAwarePaginator`, not a contract.

- 期待 (Expected) — Laravel 13 `Illuminate\Pagination`:
  - `AbstractPaginator` (53 public, incl. ArrayAccess `offset*`, `toHtml`,
    `__call` ForwardsCalls→collection, `escapeWhenCastingToString`, view plumbing
    `viewFactory`/`viewFactoryResolver`/`defaultView`/`defaultSimpleView` +
    `useTailwind`/`useBootstrap`/`useBootstrapThree|Four|Five`,
    `loadMorph`/`loadMorphCount`) + `Tappable`/`TransformsToResourceCollection`/
    `Macroable`, `implements CanBeEscapedWhenCastToString, Htmlable, Stringable`.
  - `Paginator extends AbstractPaginator implements Arrayable, ArrayAccess,
    Countable, IteratorAggregate, Jsonable, JsonSerializable, PaginatorContract` —
    adds `links`/`render`/`hasMorePagesWhen`/`hasMorePages`/`nextPageUrl`/`toArray`/
    `jsonSerialize`/`toJson`/`toPrettyJson`.
  - `LengthAwarePaginator … implements …, LengthAwarePaginatorContract` — adds
    `total`/`lastPage`/`linkCollection`/`elements` (windowed links via `UrlWindow`).
  - `AbstractCursorPaginator` (39 public) + `CursorPaginator … implements …,
    PaginatorContract` — incl. `cursor()`, `getCursorName`/`setCursorName`,
    static `resolveCurrentCursor`/`currentCursorResolver`, `render`/`links`,
    `isEmpty`/`isNotEmpty`, `getCollection`/`setCollection`, ArrayAccess, `__call`.
  - 3 contracts `Contracts\Pagination\{Paginator(17),LengthAwarePaginator(3),
    CursorPaginator(17)}`, `PaginationServiceProvider`, `PaginationState`, and
    shipped Blade view templates (`resources/views/*` — tailwind/bootstrap/simple).

- 差分 (Gaps):
  - **Orphaned subsystem (headline — NEW defect class).** Nothing in the ORM
    produces a paginator. `Eloquent\Builder::paginate($page, $limit):
    BuilderInterface` only sets `params['limit'] = {number, offset}` and returns
    `$this` (the Builder) — it never constructs a `LengthAwarePaginator` (already
    flagged in B02 as inverted-args + non-paginator return). There is **no**
    `Builder::simplePaginate` or `Builder::cursorPaginate` at all, and **no**
    `new LengthAwarePaginator/Paginator/CursorPaginator` anywhere under
    `src/Phare/Eloquent/`. The only consumer is `Http\Resources\ResourceCollection`
    (`instanceof LengthAwarePaginator|Paginator` checks) — so a paginator only
    exists if hand-constructed. The whole namespace is shipped-but-unproduced.
  - **Missing — base `Paginator` (vs `AbstractPaginator` + simple `Paginator`):**
    `links()`/`render()`/`hasMorePagesWhen()` (Laravel's SIMPLE paginator renders;
    Phare's base cannot — only `LengthAwarePaginator` got render), `onLastPage`,
    `through` (base lacks it; only on LengthAware+Cursor), ArrayAccess
    `offsetExists/Get/Set/Unset`, `toHtml`/`Htmlable`, `__call` ForwardsCalls→
    collection, `escapeWhenCastingToString`/`CanBeEscapedWhenCastToString`,
    `loadMorph`/`loadMorphCount`, static `viewFactory`/`viewFactoryResolver`/
    `defaultView($view)`/`defaultSimpleView($view)`/`useTailwind`/`useBootstrap`/
    `useBootstrapThree|Four|Five`, `Tappable`/`Macroable`/
    `TransformsToResourceCollection`.
  - **Missing — `CursorPaginator`:** `render`/`links` (no rendering whatsoever),
    `cursor()`, `getCursorName`/`setCursorName`, static `resolveCurrentCursor`/
    `currentCursorResolver` (so it CANNOT read the incoming cursor from the
    request — `$cursor` must be passed manually), `withPath`/`setPath`,
    `isEmpty`/`isNotEmpty`, `getCollection`/`setCollection`, `viewFactory`,
    `loadMorph`/`loadMorphCount`, ArrayAccess, `__call`, `__toString`. `$parameters`
    (cursor columns) defaults to `[]` and is only settable via `$options` —
    Laravel derives it from the query's `orders`, which is unavailable here.
  - **Type mismatch / divergence:**
    - No `Contracts\Pagination\*` — Phare publishes no interface; its classes
      satisfy much of the `Paginator` contract by method NAME but declare none.
      (Recurring no-contracts pattern: Validation A06, View A07.)
    - **Collapsed abstract layer** — `Paginator` IS the base (no
      `AbstractPaginator`), and `CursorPaginator` has NO shared base at all, so the
      page- and cursor-paginator query/fragment helpers are copy-pasted →
      divergence risk. (Collapsed-layer pattern, cf. B03 `MorphMany extends HasMany`.)
    - `items()` return type differs by class: `Paginator::items(): Collection` vs
      `CursorPaginator::items(): array`; Laravel's `items()` is always `array`
      (`$this->items->all()`). Inconsistent within Phare AND vs Laravel.
    - `LengthAwarePaginator::simplePaginate(): string` is a NON-Laravel method —
      in Laravel `simplePaginate` is a *Builder* method that returns a `Paginator`,
      not a paginator method that returns HTML. Same-name/different-layer hazard.
    - `toArray()` schema drift: base `Paginator::toArray` emits a non-Laravel
      `current_page_url` key; Laravel's simple-paginator array has no such key.
  - **Defects (beyond parity):**
    - **No view integration.** `render()`/`links()` ignore `$view`/`$data` and
      return hardcoded inline HTML (`<div class="pagination">…`); there is no view
      factory, no Tailwind/Bootstrap presets, no `PaginationServiceProvider`, no
      Blade templates. The simple `Paginator` and `CursorPaginator` cannot render
      at all. (Stub/hardcoded-output family, cf. A05 `Response::view`,
      A07 `View::render`.)
    - **Unbounded link list.** `LengthAwarePaginator::linkCollection()` and
      `defaultView()` iterate `range(1, lastPage())` directly — every page becomes
      an anchor, so a 10k-page set emits 10k links/array entries. The shipped
      `UrlWindow` (which does `onEachSide` windowing with `first/slider/last`) is
      **never called** by the paginator — a second orphaned piece. Laravel uses
      `elements()`→`UrlWindow` to window links with `…` separators.
  - **Phalcon leak (§2):** **NONE.** Zero `Phalcon\` references in any of the 5
    files — Wrapper Rule PASSES. This is the **first fully Phalcon-clean Area-B
    subsystem in both its own namespace AND its inheritance**: unlike B04 (clean
    namespace but rode the B02 `Builder` leak), the paginators never touch
    `Builder`/`Model`/`AbstractPdo` at all. (B05/B06 had `AbstractPdo`
    published-dependency leaks; this has none.) `Cursor` is the closest-to-parity
    file in the subsystem (matches Laravel `parameter`/`parameters`/`pointsTo*`/
    `toArray`/`encode`/`fromEncoded` 1:1) — the B-area analogue of B05's
    `ForeignKeyDefinition`.

- 工数感 (Effort: **L**). Misleading at the class level — the *data* API is
  ~70% present and the code is Phalcon-clean — but the subsystem is **unwired end
  to end**, so closing it is cross-cutting, not local: (1) wire
  `Builder::paginate`/add `simplePaginate`/`cursorPaginate` to actually COUNT,
  fetch `perPage(+1)`, and CONSTRUCT+return the paginators (Laravel signature
  `paginate($perPage, $columns, $pageName, $page)`, not the inverted `($page,
  $limit)` → Builder) — blocked on a functioning B02 query builder; (2) give
  `CursorPaginator` a `resolveCurrentCursor` and derive `$parameters` from the
  query orders (needs builder order introspection); (3) real link rendering —
  publish a `PaginationServiceProvider` + view templates and route
  `render()`/`links()` through the view factory (blocked on the A07 view stack,
  itself stubbed) and wire the existing `UrlWindow` for windowed links;
  (4) add `Contracts\Pagination\*`, ArrayAccess, `__call`/ForwardsCalls,
  `loadMorph*`, and a shared abstract base to kill the cursor/page copy-paste.
  Net: shippable classes, but dead until B02 + A07 land — hence L.
