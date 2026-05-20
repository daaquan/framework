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
