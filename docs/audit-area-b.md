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
