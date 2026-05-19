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
