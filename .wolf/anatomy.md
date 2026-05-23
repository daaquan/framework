# anatomy.md

> Auto-maintained by OpenWolf. Last scanned: 2026-05-23T02:10:48.562Z
> Files: 510 tracked | Anatomy hits: 0 | Misses: 0

## ../../tmp/

- `prog-b04.md` — 2026-05-23 - US-B04 Audit Soft Deletes + Global Scopes (~795 tok)
- `prog-b05.md` — 2026-05-23 - US-B05 Audit Migrations + Schema Builder (~922 tok)
- `section-b04.md` — ## Soft Deletes + Global Scopes (~2192 tok)
- `section-b05.md` — ## Migrations + Schema Builder (~3673 tok)

## ./

- `.editorconfig` — Editor configuration (~59 tok)
- `.gitattributes` — Git attributes (~148 tok)
- `.gitignore` — Git ignore rules (~205 tok)
- `.phpunit.baseline.xml` (~13 tok)
- `CLAUDE.md` — OpenWolf (~648 tok)
- `composer.json` — PHP package manifest (~662 tok)
- `Dockerfile` — Docker container definition (~187 tok)
- `LICENSE` — Project license (~286 tok)
- `phpstan.neon.dist` (~42 tok)
- `phpunit.xml.dist` (~401 tok)
- `pint.json` (~146 tok)
- `README.ja.md` — Phare フレームワーク (~411 tok)
- `README.md` — Project documentation (~580 tok)
- `README.zh-CN.md` — Phare 框架 (~353 tok)

## .claude/

- `settings.json` (~441 tok)

## .claude/rules/

- `openwolf.md` (~313 tok)

## .claude/specs/eloquent-orm-expansion/

- `dev-plan.yaml` — # Eloquent ORM Expansion — Development Plan (~1268 tok)

## bin/

- `pest` (~37 tok)

## config/

- `broadcasting.php` — Declares of (~462 tok)
- `environments.php` (~708 tok)
- `sanctum.php` (~638 tok)

## database/migrations/

- `2024_01_01_000000_create_personal_access_tokens_table.php` — Database migration (~210 tok)

## docs/

- `audit-area-a.md` — Audit — Area A: Core Web Stack (~12604 tok)
- `audit-area-b.md` — Audit — Area B: Database & ORM (~13471 tok)
- `auth.md` — Authentication (~693 tok)
- `cache.md` — Cache (~507 tok)
- `console.md` — Console (~761 tok)
- `container.md` — Service Container (~741 tok)
- `database.md` — Database (~1110 tok)
- `eloquent.md` — Eloquent ORM (~1275 tok)
- `http.md` — HTTP Layer (~884 tok)
- `index.md` — Phare Framework — Documentation (~312 tok)
- `installation.md` — Installation (~375 tok)
- `laravel13-phalcon-architecture.md` — Laravel 13 Compatibility Architecture for Phare (Phalcon) (~4368 tok)
- `routing.md` — Routing (~620 tok)

## docs/superpowers/plans/

- `2026-05-07-container-phase4-attributes.md` — Container Phase 4 — Attribute Injection Implementation Plan (~11704 tok)
- `2026-05-11-phase3-finalize.md` — Phase 3 Finalize: Kernel::registerRoutes() wiring-only orchestration (~5590 tok)
- `2026-05-11-phase5-finalize.md` — Phase 5 Finalize: Log channel-stack + Manager base extraction (~9051 tok)
- `2026-05-11-phase6-container-edges.md` — Phase 6 Implementation Plan: Laravel 13 Container Edge Behaviors (~5586 tok)

## docs/superpowers/specs/

- `2026-05-07-container-phase4-attributes-design.md` — Container Phase 4 — Attribute Injection Compatibility (~2187 tok)
- `2026-05-11-phase6-container-edges-design.md` — Phase 6 Design Spec: Laravel 13 Container Edge Behaviors (~2928 tok)

## src/Phare/Attributes/

- `Route.php` — [\Attribute(\Attribute::TARGET_METHOD)] (~415 tok)
- `RouteAttribute.php` — [\Attribute(\Attribute::TARGET_CLASS)] (~130 tok)

## src/Phare/Auth/

- `Authenticatable.php` — Interface Authenticatable (~243 tok)
- `AuthenticationException.php` — Declares AuthenticationException (~80 tok)
- `AuthManager.php` — Owns the configured auth guards and resolves them on demand. (~1123 tok)
- `Manager.php` — Indicates if the logout method has been called. (~1530 tok)

## src/Phare/Auth/Events/

- `Attempting.php` — Declares Attempting (~36 tok)
- `Authenticated.php` — Declares Authenticated (~50 tok)
- `Failed.php` — Declares Failed (~57 tok)
- `Login.php` — Declares Login (~47 tok)
- `Logout.php` — Declares Logout (~48 tok)
- `Validated.php` — Declares Validated (~58 tok)

## src/Phare/Auth/Middleware/

- `Authenticate.php` — Authenticate: handle (~280 tok)
- `EnsureRole.php` — Role-based authorization middleware. (~396 tok)

## src/Phare/Auth/Passkeys/

- `ChallengeStore.php` — Interface: ChallengeStore (3 methods) (~72 tok)
- `InMemoryChallengeStore.php` — InMemoryChallengeStore: put, get, forget (~238 tok)
- `PasskeyAssertionVerifier.php` — Interface: PasskeyAssertionVerifier (1 methods) (~84 tok)
- `PasskeyAuthenticator.php` — PasskeyAuthenticator: begin, verify (~581 tok)
- `PasskeyCredentialRepository.php` — Find a stored credential by its credential ID. (~164 tok)
- `PasskeyRegistrar.php` — Handles the WebAuthn registration ceremony (create credential). (~692 tok)
- `PasskeyRegistrationVerifier.php` — Verify a passkey registration (attestation) response. (~163 tok)

## src/Phare/Auth/Passwords/

- `PasswordBroker.php` — Password reset token manager. (~656 tok)

## src/Phare/Auth/Sanctum/

- `HasApiTokens.php` — Trait: HasApiTokens (~594 tok)
- `NewAccessToken.php` — NewAccessToken: toArray, __toString (~164 tok)
- `PersonalAccessToken.php` — Model — table: personal_access_tokens, 5 fields, 6 casts, 1 rels (~591 tok)
- `Sanctum.php` — Sanctum: usePersonalAccessTokenModel, personalAccessTokenModel, actingAs, createToken + 4 more (~768 tok)
- `SanctumGuard.php` — SanctumGuard: user, validate, check, guest + 2 more (~478 tok)
- `SanctumServiceProvider.php` — Service provider: SanctumServiceProvider (~238 tok)

## src/Phare/Auth/Sanctum/Middleware/

- `EnsureFrontendRequestsAreStateful.php` — EnsureFrontendRequestsAreStateful: handle (~335 tok)

## src/Phare/Bootstrap/

- `LoadEnvironmentVariables.php` — Load environment variables from the .env file. (~359 tok)

## src/Phare/Broadcasting/

- `BroadcastEvent.php` — Event: BroadcastEvent (~273 tok)
- `BroadcastException.php` — Declares BroadcastException (~29 tok)
- `BroadcastManager.php` — BroadcastManager: driver, connection, extend, getDefaultDriver + 4 more (~1318 tok)
- `BroadcastServiceProvider.php` — Service provider: BroadcastServiceProvider (~193 tok)
- `Channel.php` — Channel: __toString (~67 tok)
- `InteractsWithSockets.php` — Trait: InteractsWithSockets (~95 tok)
- `PendingBroadcast.php` — PendingBroadcast: via, toOthers, __destruct (~245 tok)
- `PresenceChannel.php` — Declares PresenceChannel (~51 tok)
- `PrivateChannel.php` — Declares PrivateChannel (~50 tok)

## src/Phare/Broadcasting/Broadcasters/

- `Broadcaster.php` — Broadcaster: auth, validAuthenticationResponse, broadcast, channel + 2 more (~875 tok)
- `LogBroadcaster.php` — LogBroadcaster: auth, validAuthenticationResponse, broadcast (~221 tok)
- `NullBroadcaster.php` — NullBroadcaster: auth, validAuthenticationResponse, broadcast (~117 tok)
- `PusherBroadcaster.php` — PusherBroadcaster: auth, validAuthenticationResponse, broadcast, broadcastToEveryone + 1 more (~747 tok)
- `RedisBroadcaster.php` — RedisBroadcaster: auth, validAuthenticationResponse, broadcast, getRedis (~593 tok)

## src/Phare/Cache/

- `ArrayAdapter.php` — In-memory array cache adapter for testing and ephemeral caching. (~1169 tok)
- `CacheManager.php` — Resolve a configured cache store. Null returns the default store. (~1399 tok)
- `NullAdapter.php` — Null cache adapter that never stores values. (~337 tok)

## src/Phare/Cache/Adapter/

- `ArrayAdapter.php` — ArrayAdapter: clear, decrement, delete, deleteMultiple + 10 more (~1127 tok)
- `NullAdapter.php` — NullAdapter: clear, decrement, delete, deleteMultiple + 8 more (~348 tok)
- `RedisCluster.php` — Declares RedisCluster (~28 tok)

## src/Phare/Collections/

- `Arr.php` — ServiceLocator implementation for helpers (~3361 tok)
- `Collection.php` — Collection: first, last, group, values + 48 more (~4306 tok)
- `Str.php` — ServiceLocator implementation for helpers (~4478 tok)

## src/Phare/Config/

- `ConfigCache.php` — Cache the configuration to a file. (~515 tok)
- `ConfigEnvironment.php` — ConfigEnvironment: load, getEnvironment, setEnvironmentOverride, getEnvironmentOverrides + 3 more (~907 tok)
- `EnvironmentDetector.php` — EnvironmentDetector: detect, setEnvironments, getEnvironments (~527 tok)
- `EnvironmentManager.php` — EnvironmentManager: detect, getEnvironment, isEnvironment, isProduction + 6 more (~824 tok)
- `Repository.php` — Get a configuration value using "dot" notation. (~2011 tok)

## src/Phare/Console/

- `Application.php` — Add --env and --language options to all commands. (~747 tok)
- `Command.php` — Framework application/container instance (not Symfony Console Application). (~1615 tok)
- `Config.php` — Get the singleton instance (~1706 tok)
- `Kernel.php` — The Artisan commands provided by your application. (~671 tok)
- `SignatureParser.php` — Parse the command signature and register arguments/options. (~317 tok)

## src/Phare/Console/Commands/

- `CacheClearCommand.php` — [AsCommand(name: 'cache:clear', description: 'Flush the application cache.')] (~320 tok)
- `ConfigClearCommand.php` — [AsCommand(name: 'config:clear', description: 'Remove the configuration cache file.')] (~175 tok)
- `EnvCommand.php` — Artisan command: EnvCommand (~506 tok)
- `KeyGenerateCommand.php` — [AsCommand(name: 'key:generate', description: 'Generate the application key.')] (~377 tok)
- `MakeControllerCommand.php` — Display a listing of the resource. (~1310 tok)
- `MakeMiddlewareCommand.php` — Handle an incoming request. (~743 tok)
- `MakeMigrationCommand.php` — Artisan command: MakeMigrationCommand (~1012 tok)
- `MakeModelCommand.php` — The table associated with the model. (~1179 tok)
- `MakeRequestCommand.php` — Determine if the user is authorized to make this request. (~856 tok)
- `MakeSeederCommand.php` — Artisan command: MakeSeederCommand (~427 tok)
- `MigrateCommand.php` — Artisan command: MigrateCommand (~1158 tok)
- `QueueWorkCommand.php` — Artisan command: QueueWorkCommand (~1216 tok)
- `RouteClearCommand.php` — [AsCommand(name: 'route:clear', description: 'Remove the route cache file.')] (~184 tok)
- `SeedCommand.php` — Artisan command: SeedCommand (~420 tok)
- `ViewClearCommand.php` — [AsCommand(name: 'view:clear', description: 'Clear all compiled view files.')] (~238 tok)

## src/Phare/Console/Concerns/

- `AgentFriendly.php` — AgentFriendly — Netlify-style AI agent support for CLI commands. (~1221 tok)

## src/Phare/Console/Exceptions/

- `FactoryNotFound.php` — Factory definition can not be found. (~90 tok)
- `FileNotFound.php` — The services file can not be resolved. (~370 tok)
- `InvalidCommand.php` — User defined command does not extend parent command. (~95 tok)
- `InvalidConfig.php` — A config value can not be found. (~102 tok)
- `InvalidInput.php` — The given command is invalid. (~93 tok)
- `WriteError.php` — Writing to the filesystem failed. (~565 tok)

## src/Phare/Console/Helpers/

- `Creator.php` — Phalcon config. (~335 tok)
- `Filesystem.php` — Create all directories listed in directories array. (~306 tok)
- `NamespaceResolver.php` — Resolve namespace for the given dir path, add additional values. (~1160 tok)
- `PathHelpers.php` — Trait: PathHelpers (~800 tok)

## src/Phare/Console/Input/

- `Argument.php` — Default argument value. (~487 tok)
- `Input.php` — Input. (~624 tok)
- `Option.php` — Option shortcut. (~570 tok)

## src/Phare/Console/Output/

- `Logger.php` — Log of received messages. (~258 tok)
- `Output.php` — Output verbosity. (~290 tok)
- `SymfonyOutput.php` — Symfony command output. (~202 tok)

## src/Phare/Console/Scheduling/

- `CallbackEvent.php` — Set the human-friendly description of the event. (~431 tok)
- `Event.php` — The Cron expression representing the event's frequency. (~3319 tok)
- `Schedule.php` — Add a new command event to the schedule. (~970 tok)
- `ScheduleListCommand.php` — Artisan command: ScheduleListCommand (~262 tok)
- `ScheduleRunCommand.php` — Artisan command: ScheduleRunCommand (~365 tok)
- `ScheduleServiceProvider.php` — Register commands with the application. (~394 tok)

## src/Phare/Container/

- `BoundMethod.php` — Laravel-parity helper for autowired invocation of Closures, method (~1263 tok)
- `Container.php` — Phalcon standard services (~12350 tok)
- `ContextualBindingBuilder.php` — ContextualBindingBuilder: needs (~107 tok)
- `ContextualBindingNeedsBuilder.php` — ContextualBindingNeedsBuilder: give, giveTagged, giveConfig (~316 tok)

## src/Phare/Container/Attributes/

- `Auth.php` — Auth: resolve (~159 tok)
- `Authenticated.php` — Authenticated: resolve (~202 tok)
- `Broadcast.php` — Broadcast: resolve (~167 tok)
- `Cache.php` — Cache: resolve (~160 tok)
- `Config.php` — Config: resolve (~186 tok)
- `CurrentUser.php` — CurrentUser: resolve (~172 tok)
- `DB.php` — DB: resolve (~163 tok)
- `Give.php` — Give: resolve (~159 tok)
- `Hash.php` — Hash: resolve (~160 tok)
- `Log.php` — Log: resolve (~131 tok)
- `Mail.php` — Mail: resolve (~161 tok)
- `Queue.php` — Queue: resolve (~169 tok)
- `RouteParameter.php` — RouteParameter: resolve (~181 tok)
- `Session.php` — Session: resolve (~162 tok)
- `Storage.php` — Storage: resolve (~162 tok)
- `Tag.php` — Tag: resolve (~126 tok)

## src/Phare/Container/Exceptions/

- `ContainerException.php` — Declares ContainerException (~48 tok)
- `ServiceNotFoundException.php` — Declares ServiceNotFoundException (~51 tok)

## src/Phare/Contracts/Auth/

- `Authenticatable.php` — Get the unique identifier for the user. (~168 tok)

## src/Phare/Contracts/Cache/

- `Cache.php` — Interface: Cache (21 methods) (~464 tok)

## src/Phare/Contracts/Console/

- `Application.php` — Run an Artisan console command by name. (~146 tok)
- `Kernel.php` — Bootstrap the application for artisan commands. (~351 tok)

## src/Phare/Contracts/Container/

- `ContextualAttribute.php` — Interface: ContextualAttribute (0 methods) (~28 tok)

## src/Phare/Contracts/Debug/

- `ExceptionHandler.php` — Report or log an exception. (~221 tok)

## src/Phare/Contracts/Foundation/

- `Application.php` — Get the version number of the application. (~253 tok)
- `Container.php` — Determine if the given abstract type has been bound. (~401 tok)

## src/Phare/Contracts/Foundation/Bus/

- `Dispatchable.php` — Trait: Dispatchable (~332 tok)
- `PendingDispatch.php` — PendingDispatch: onQueue, onConnection, delay, resolve + 3 more (~478 tok)

## src/Phare/Contracts/Http/

- `Kernel.php` — Bootstrap the application for HTTP requests. (~190 tok)
- `Middleware.php` — Interface: Middleware (1 methods) (~61 tok)
- `MiddlewareContract.php` — MiddlewareContract: call, handle (~288 tok)
- `Request.php` — Interface: Request (2 methods) (~54 tok)
- `Response.php` — Interface: Response (0 methods) (~34 tok)

## src/Phare/Contracts/Http/Validation/

- `Validator.php` — Interface: Validator (5 methods) (~118 tok)

## src/Phare/Contracts/Queue/

- `ShouldQueue.php` — Queued job: (~28 tok)

## src/Phare/Contracts/Routing/

- `Router.php` — Interface: Router (9 methods) (~169 tok)

## src/Phare/Contracts/Session/

- `Session.php` — Interface: Session (6 methods) (~100 tok)

## src/Phare/Contracts/Support/

- `Arrayable.php` — Interface: Arrayable (1 methods) (~70 tok)
- `Jsonable.php` — Convert the object to its JSON representation. (~66 tok)

## src/Phare/Database/

- `BaseFactory.php` — Model factory: BaseFactory (~66 tok)
- `Factory.php` — Model factory: Factory (~958 tok)
- `Migration.php` — Migration: setSchema, up, down (~371 tok)
- `Migrator.php` — Migrator: run, rollback, reset, refresh (~1888 tok)
- `Seeder.php` — Database seeder: Seeder (~775 tok)

## src/Phare/Database/Events/

- `ConnectionEvent.php` — Event: ConnectionEvent (~38 tok)
- `TransactionBeginning.php` — Declares TransactionBeginning (~26 tok)
- `TransactionCommitted.php` — Declares TransactionCommitted (~26 tok)
- `TransactionCommitting.php` — Declares TransactionCommitting (~26 tok)
- `TransactionRolledBack.php` — Declares TransactionRolledBack (~26 tok)

## src/Phare/Database/Exceptions/

- `DatabaseException.php` — Declares DatabaseException (~32 tok)

## src/Phare/Database/MySql/

- `DatabaseManager.php` — DatabaseManager: connection, getDefaultConnection, setDefaultConnection, getConnectionService + 3 more (~1602 tok)
- `HandlesTransactions.php` — Trait: HandlesTransactions (~960 tok)

## src/Phare/Database/Schema/

- `Blueprint.php` — Blueprint: getTable, getColumns, getCommands, isUpdating + 34 more (~1779 tok)
- `ColumnDefinition.php` — ColumnDefinition: getType, getName, getAttributes, nullable + 14 more (~674 tok)
- `ForeignKeyDefinition.php` — ForeignKeyDefinition: references, on, onDelete, onUpdate + 14 more (~600 tok)
- `Grammar.php` — Grammar: compileCreate, compileAdd, compileDrop, compileDropIfExists + 2 more (~1372 tok)
- `SchemaBuilder.php` — SchemaBuilder: create, table, drop, dropIfExists + 4 more (~1337 tok)

## src/Phare/Database/Schema/Grammars/

- `MySqlGrammar.php` — MySqlGrammar: compileCreate, compileAdd, compileDrop, compileDropIfExists + 1 more (~1381 tok)
- `PostgresGrammar.php` — PostgresGrammar: compileCreate, compileAdd, compileDrop, compileDropIfExists + 1 more (~1316 tok)
- `SqliteGrammar.php` — SqliteGrammar: compileCreate, compileAdd, compileDrop, compileDropIfExists + 1 more (~1226 tok)

## src/Phare/Debug/

- `DebugLogger.php` — DebugLogger: logServiceProviderBooting, logRouteMounted, logMiddlewareStart, logMiddlewareEnd + 5 more (~822 tok)

## src/Phare/Eloquent/

- `Builder.php` — Eloquent Builder for Phalcon (~5563 tok)
- `BuilderInterface.php` — Interface: BuilderInterface (26 methods) (~528 tok)
- `Model.php` — Model: afterFetch, resolveConnectionName, create, update + 23 more (~5069 tok)
- `ModelInterface.php` — Interface: ModelInterface (1 methods) (~26 tok)
- `Scope.php` — Query scope: (~32 tok)
- `SoftDeletingScope.php` — Query scope: SoftDeletingScope (~914 tok)

## src/Phare/Eloquent/Casts/

- `AsArrayObject.php` — AsArrayObject: get, set (~155 tok)
- `AsCollection.php` — AsCollection: get, set (~153 tok)
- `AsEncryptedArrayObject.php` — AsEncryptedArrayObject: get, set (~195 tok)
- `AsEncryptedCollection.php` — AsEncryptedCollection: get, set (~193 tok)
- `AsStringable.php` — AsStringable: get, __toString, set (~189 tok)
- `Attribute.php` — Attribute: make, withoutObjectCaching, shouldCache (~174 tok)
- `CastsAttributes.php` — Interface: CastsAttributes (2 methods) (~58 tok)
- `CastsInboundAttributes.php` — Interface: CastsInboundAttributes (1 methods) (~40 tok)
- `Json.php` — Json: decode, encode (~174 tok)

## src/Phare/Eloquent/Concerns/

- `GuardsAttributes.php` — Trait: GuardsAttributes (~526 tok)
- `HasAttributes.php` — Trait: HasAttributes (~5811 tok)
- `HasEvents.php` — Trait: HasEvents (~2661 tok)
- `HasGlobalScopes.php` — Trait: HasGlobalScopes (~533 tok)
- `HasRelationships.php` — Trait: HasRelationships (~3961 tok)
- `HasTimestamps.php` — Trait: HasTimestamps (~761 tok)
- `HidesAttributes.php` — Trait: HidesAttributes (~479 tok)
- `SoftDeletes.php` — Trait: SoftDeletes (~1227 tok)

## src/Phare/Eloquent/Relations/

- `BelongsTo.php` — BelongsTo: addConstraints, addEagerConstraints, getResults, initRelation + 4 more (~905 tok)
- `BelongsToMany.php` — BelongsToMany: addConstraints, addEagerConstraints, initRelation, match + 17 more (~4712 tok)
- `HasMany.php` — HasMany: getResults, initRelation, match (~202 tok)
- `HasManyThrough.php` — HasManyThrough: getResults, initRelation, match (~326 tok)
- `HasOne.php` — HasOne: getResults, initRelation, match (~183 tok)
- `HasOneOrMany.php` — HasOneOrMany: addConstraints, addEagerConstraints, save, create (~800 tok)
- `HasOneOrManyThrough.php` — HasOneOrManyThrough: addConstraints, addEagerConstraints, getEager, getRelationExistenceQuery + 1 more (~1470 tok)
- `HasOneThrough.php` — HasOneThrough: getResults, initRelation, match (~318 tok)
- `MorphedByMany.php` — Declares MorphedByMany (~191 tok)
- `MorphMany.php` — MorphMany: addConstraints, addEagerConstraints (~307 tok)
- `MorphOne.php` — MorphOne: getResults, initRelation, match, getRelationExistenceQuery (~233 tok)
- `MorphPivot.php` — MorphPivot: setMorphType, setMorphClass, getDeleteQuery (~214 tok)
- `MorphTo.php` — MorphTo: addConstraints, addEagerConstraints, initRelation, match + 2 more (~1358 tok)
- `MorphToMany.php` — MorphToMany: newPivot (~780 tok)
- `Pivot.php` — Model: Pivot (~715 tok)
- `Relation.php` — Relation: noConstraints, addConstraints, addEagerConstraints, initRelation + 12 more (~793 tok)

## src/Phare/Encryption/

- `DecryptException.php` — Declares DecryptException (~26 tok)
- `Encrypter.php` — Encrypter: encrypt, decrypt, encryptString, decryptString + 4 more (~1473 tok)
- `EncryptException.php` — Declares EncryptException (~26 tok)

## src/Phare/Events/

- `Dispatcher.php` — Dispatcher: setTransactionManagerResolver, listen, hasListeners, hasWildcardListeners + 11 more (~3373 tok)
- `Event.php` — Event: Event (~37 tok)
- `EventServiceProvider.php` — The event listener mappings for the application. (~775 tok)
- `Listener.php` — Event listener: Listener (~30 tok)

## src/Phare/Events/Contracts/

- `Dispatcher.php` — Register an event listener with the dispatcher. (~489 tok)
- `ShouldBroadcast.php` — Interface: ShouldBroadcast (4 methods) (~67 tok)
- `ShouldDispatchAfterCommit.php` — Interface: ShouldDispatchAfterCommit (0 methods) (~22 tok)

## src/Phare/Exceptions/

- `InvalidFormatException.php` — Declares InvalidFormatException (~28 tok)

## src/Phare/Filesystem/

- `Filesystem.php` — Filesystem: exists, get, put, replace + 27 more (~2092 tok)
- `FilesystemManager.php` — Resolve a configured filesystem disk. Null returns the default disk. (~725 tok)
- `LocalFilesystem.php` — LocalFilesystem: exists, get, put, delete + 9 more (~654 tok)
- `NullFilesystem.php` — NullFilesystem: exists, get, put, delete + 8 more (~338 tok)

## src/Phare/Filesystem/Contracts/

- `Filesystem.php` — Determine if a file exists. (~546 tok)

## src/Phare/Foundation/

- `AbstractApplication.php` — This abstract class serves as the foundation for all applications built on the framework. (~4590 tok)
- `Cache.php` — Cache: has, get, getMultiple, pull + 16 more (~1416 tok)
- `Micro.php` — Micro: handle, mount, terminate (~540 tok)
- `Web.php` — Web: handle, mount, terminate (~248 tok)

## src/Phare/Foundation/Bootstrap/

- `DetectEnvironment.php` — DetectEnvironment: bootstrap (~400 tok)
- `HandleExceptions.php` — Error handling bootstrapper. (~3840 tok)
- `LoadConfiguration.php` — Load various configuration settings. (~859 tok)
- `LoadEnvironmentVariables.php` — Bootstrap the given application. (~125 tok)
- `RegisterFacades.php` — Bootstrap the given application. (~110 tok)
- `RegisterProviders.php` — Bootstrap the given application. (~111 tok)

## src/Phare/Foundation/Events/

- `ApplicationBooted.php` — Event: ApplicationBooted (~58 tok)
- `ApplicationBooting.php` — Event: ApplicationBooting (~58 tok)
- `RequestHandled.php` — Event: RequestHandled (~76 tok)

## src/Phare/Foundation/Exceptions/

- `Handler.php` — A list of the exception types that are not reported. (~1083 tok)

## src/Phare/Foundation/Http/

- `Kernel.php` — The application's global HTTP middleware stack. (~2880 tok)
- `RequestMethod.php` — Interface for Request methods (~157 tok)
- `ResponseStatusCode.php` — Http response status codes (~9896 tok)

## src/Phare/Foundation/Http/Concerns/

- `AfterMiddleware.php` — Interface: AfterMiddleware (0 methods) (~22 tok)
- `BeforeMiddleware.php` — Interface: BeforeMiddleware (0 methods) (~22 tok)

## src/Phare/Foundation/Http/Middleware/

- `CheckForMaintenanceMode.php` — CheckForMaintenanceMode: handle (~272 tok)

## src/Phare/Foundation/Http/Validation/

- `ValidationException.php` — Declares ValidationException (~31 tok)

## src/Phare/Foundation/Testing/Concerns/

- `MakesHttpRequests.php` — Execute the request and return the response. (~1080 tok)

## src/Phare/Hashing/

- `Argon2idHasher.php` — Argon2idHasher: algorithm (~45 tok)
- `Argon2iHasher.php` — Argon2iHasher: algorithm (~44 tok)
- `ArgonHasher.php` — ArgonHasher: make, check, needsRehash, info (~458 tok)
- `BcryptHasher.php` — BcryptHasher: make, check, needsRehash, info + 1 more (~339 tok)
- `HasherInterface.php` — Hash the given value. (~168 tok)
- `HashManager.php` — HashManager: driver, make, check, needsRehash + 5 more (~528 tok)

## src/Phare/Http/

- `Controller.php` — Base controller providing convenient access to services. (~685 tok)
- `FileHelpers.php` — Trait: FileHelpers (~1127 tok)
- `FileResponse.php` — FileResponse: create, send, deleteFileAfterSend, stream + 3 more (~997 tok)
- `FormRequest.php` — Form validation: FormRequest (~689 tok)
- `Request.php` — Request: make, rules, validate, getMessages + 18 more (~1870 tok)
- `Response.php` — Convenience method for Laravel-style redirects (~602 tok)
- `StreamedResponse.php` — StreamedResponse: create, send, setCallback, isStreamed (~370 tok)
- `UploadedFile.php` — UploadedFile: fake, createFromArray, store, storeAs + 17 more (~1686 tok)

## src/Phare/Http/Resources/

- `JsonResource.php` — API resource: JsonResource (~1422 tok)
- `JsonResourceResponse.php` — JsonResourceResponse: toResponse, withResponse, getStatusCode, getHeaders (~598 tok)
- `MergeValue.php` — Declares MergeValue (~46 tok)
- `MissingValue.php` — MissingValue: make (~58 tok)
- `ResourceCollection.php` — ResourceCollection: toArray, mapIntoResource, jsonSerialize, getIterator + 1 more (~1053 tok)

## src/Phare/Log/

- `Logger.php` — Dynamically pass log calls into the writer. (~956 tok)
- `LogManager.php` — The array of resolved channels. (~3153 tok)

## src/Phare/Mail/

- `HtmlMailable.php` — Mail: HtmlMailable (~152 tok)
- `Mailable.php` — Mail: Mailable (~1420 tok)
- `Mailer.php` — Mailer: send, raw, html, getConfig + 2 more (~600 tok)
- `MailException.php` — Declares MailException (~20 tok)
- `MailManager.php` — MailManager: mailer, getDefaultMailer, __call (~655 tok)
- `MailServiceProvider.php` — Service provider: MailServiceProvider (~222 tok)
- `Message.php` — Message: to, cc, bcc, replyTo + 6 more (~492 tok)
- `RawMailable.php` — Mail: RawMailable (~151 tok)

## src/Phare/Middleware/

- `ThrottleRequests.php` — ThrottleRequests: handle (~1145 tok)
- `TokenMismatchException.php` — Declares TokenMismatchException (~44 tok)
- `VerifyCsrfToken.php` — VerifyCsrfToken: handle, addExcept (~755 tok)

## src/Phare/Notifications/

- `Notifiable.php` — Get the entity's notifications. (~651 tok)
- `Notification.php` — Get the notification's delivery channels. (~865 tok)
- `NotificationManager.php` — Send the given notification to the given notifiable entities. (~1186 tok)
- `NotificationServiceProvider.php` — Service provider: NotificationServiceProvider (~387 tok)

## src/Phare/Notifications/Channels/

- `ChannelInterface.php` — Send the given notification. (~67 tok)
- `ChannelManager.php` — Register the default notification channel drivers. (~645 tok)
- `DatabaseChannel.php` — Send the given notification. (~527 tok)
- `MailChannel.php` — Send the given notification. (~527 tok)
- `SlackChannel.php` — Send the given notification. (~469 tok)
- `SmsChannel.php` — Send the given notification. (~525 tok)

## src/Phare/Notifications/Messages/

- `MailMessage.php` — Set the subject of the notification. (~2164 tok)
- `SlackMessage.php` — Create a new Slack message. (~814 tok)
- `SmsMessage.php` — Create a new SMS message. (~357 tok)

## src/Phare/Pagination/

- `Cursor.php` — Cursor: parameter, parameters, pointsToNextItems, pointsToPreviousItems + 3 more (~559 tok)
- `CursorPaginator.php` — CursorPaginator: items, through, perPage, hasMorePages + 21 more (~1927 tok)
- `LengthAwarePaginator.php` — LengthAwarePaginator: total, hasMorePages, lastPage, firstItem + 8 more (~1480 tok)
- `Paginator.php` — Paginator: resolveCurrentPage, currentPageResolver, resolveCurrentPath, currentPathResolver + 35 more (~2404 tok)
- `UrlWindow.php` — UrlWindow: make, get, getAdjacentUrlRange, getStart + 2 more (~835 tok)

## src/Phare/Pipeline/

- `Pipeline.php` — Pipeline: protected array $pipes = [];, Set the array of pipes., Push additional pipes onto the pipeline., Set the method to call on the pipes. + 4... (~1416 tok)
- `PipelineServiceProvider.php` — Service provider: PipelineServiceProvider (~78 tok)

## src/Phare/Providers/

- `AuthServiceProvider.php` — Service provider: AuthServiceProvider (~189 tok)
- `BladeViewProvider.php` — BladeViewProvider: register (~879 tok)
- `CacheProvider.php` — CacheProvider: register (~169 tok)
- `ChronosProvider.php` — ChronosProvider: register (~232 tok)
- `ConfigProvider.php` — ConfigProvider: register (~101 tok)
- `DatabaseProvider.php` — DatabaseProvider: register (~332 tok)
- `DebugLoggerProvider.php` — DebugLoggerProvider: register (~118 tok)
- `DebugWhoopsProvider.php` — DebugWhoopsProvider: register (~166 tok)
- `DispatcherProvider.php` — DispatcherProvider: register (~373 tok)
- `EncrypterProvider.php` — Service provider for security and encryption. (~438 tok)
- `ErrorHandlerProvider.php` — ErrorHandlerProvider: register (~108 tok)
- `EventsManagerProvider.php` — EventsManagerProvider: register (~144 tok)
- `FilesystemProvider.php` — FilesystemProvider: register (~167 tok)
- `FilterProvider.php` — FilterProvider: register (~101 tok)
- `HashServiceProvider.php` — Service provider: HashServiceProvider (~213 tok)
- `LogServiceProvider.php` — Service provider: LogServiceProvider (~118 tok)
- `ModelProvider.php` — ModelProvider: register (~282 tok)
- `PasskeyServiceProvider.php` — Register passkey (WebAuthn) authentication services. (~628 tok)
- `QueueServiceProvider.php` — Service provider: QueueServiceProvider (~249 tok)
- `RequestProvider.php` — RequestProvider: register (~124 tok)
- `ResponseProvider.php` — ResponseProvider: register (~102 tok)
- `RouteServiceProvider.php` — Service provider: RouteServiceProvider (~215 tok)
- `SessionProvider.php` — SessionProvider: register (~510 tok)
- `SqidsProvider.php` — SqidsProvider: register (~136 tok)
- `TranslateProvider.php` — Registers a service provider. (~404 tok)
- `ViewProvider.php` — ViewProvider: register (~105 tok)
- `VoltViewProvider.php` — VoltViewProvider: register (~774 tok)

## src/Phare/Queue/

- `DatabaseQueue.php` — Push a job onto the queue. (~940 tok)
- `Job.php` — Execute the job. (~1310 tok)
- `Queueable.php` — Dispatch the job to the queue. (~279 tok)
- `QueueInterface.php` — Push a job onto the queue. (~159 tok)
- `QueueManager.php` — Register the default queue connectors. (~2151 tok)
- `QueueServiceProvider.php` — Service provider: QueueServiceProvider (~541 tok)
- `RedisQueue.php` — Push a job onto the queue. (~1401 tok)
- `SyncQueue.php` — Push a job onto the queue and execute it immediately. (~348 tok)

## src/Phare/Queue/Connectors/

- `ConnectorInterface.php` — Establish a queue connection. (~59 tok)
- `DatabaseConnector.php` — Establish a queue connection. (~130 tok)
- `RedisConnector.php` — Establish a queue connection. (~128 tok)
- `SyncConnector.php` — Establish a queue connection. (~84 tok)

## src/Phare/RateLimit/

- `Limit.php` — Limit: perMinute, perMinutes, perHour, perDay + 3 more (~324 tok)
- `RateLimiter.php` — RateLimiter: for, attempt, tooManyAttempts, hit + 9 more (~848 tok)
- `TooManyRequestsException.php` — TooManyRequestsException: getRetryAfter (~120 tok)

## src/Phare/Routing/

- `ApplicationModeResolver.php` — ApplicationModeResolver: resolve (~170 tok)
- `ControllerActionParameterResolver.php` — Resolve controller action parameters from route metadata and URL params. (~519 tok)
- `ControllerRouteLoader.php` — Generate the routes cache file. (~1345 tok)
- `DispatchForwardPayloadBuilder.php` — Build a dispatcher forward payload from route metadata and resolved params. (~339 tok)
- `FileRouteLoader.php` — Generate the routes cache file. (~503 tok)
- `MicroRouteHandler.php` — Owns the "mount a single micro Collection" flow. (~371 tok)
- `MiddlewareApplicator.php` — Apply middleware list in order with optional lifecycle callbacks. (~226 tok)
- `RouteDataSourceResolver.php` — Resolve route definitions from cache file or fallback loader. (~210 tok)
- `RouteLoader.php` — Determine if the routes cache is up to date with the routes files. (~1038 tok)
- `RouteMiddlewareResolver.php` — Resolve route middleware aliases to concrete middleware classes. (~200 tok)
- `RouteMountDispatcher.php` — Dispatch to web/micro mount strategy callback by resolved mode. (~188 tok)
- `RouteParamsBinder.php` — Bind matched route params into the application container when available. (~138 tok)
- `RoutePatternMatcher.php` — Match a URI against parameterized route patterns. (~364 tok)
- `Router.php` — Router: group, get, post, put + 9 more (~872 tok)
- `RouteRegistrationOrchestrator.php` — Run route registration orchestration for current request. (~505 tok)
- `RouteSelectionResolver.php` — Resolve route data by direct map lookup, then parameterized matching. (~272 tok)
- `WebDispatchForwardRegistrar.php` — Register web dispatch forwarding listener. (~248 tok)
- `WebRouteHandler.php` — Owns the "register a single web route against the Phalcon Router" flow. (~522 tok)
- `WebRouterHydrator.php` — Hydrate router with all cached route definitions for web application mode. (~286 tok)

## src/Phare/Routing/Middleware/

- `CorsMiddleware.php` — CorsMiddleware: handle (~242 tok)

## src/Phare/Security/

- `Csrf.php` — Generate a new CSRF token. (~662 tok)
- `Xss.php` — Xss: clean, escape, stripTags, removeDangerousPatterns + 10 more (~1510 tok)

## src/Phare/Session/

- `SessionManager.php` — Class SessionManager (~432 tok)
- `SessionStoreManager.php` — SessionStoreManager: store, getDefaultStore (~956 tok)

## src/Phare/Session/Adapter/

- `RedisCluster.php` — Declares RedisCluster (~77 tok)

## src/Phare/Storage/Adapter/

- `RedisCluster.php` — Returns the already connected adapter or connects to the Redis cluster. (~836 tok)

## src/Phare/Support/

- `Chronos.php` — Chronos: parse, now, copy, diffForHumans + 1 more (~848 tok)
- `DataTransferObject.php` — DataTransferObject: fill, toArray, only, except + 1 more (~538 tok)
- `Env.php` — Indicates if the putenv function is enabled. (~365 tok)
- `helpers.php` — array_any: config, config_set_path, env + 40 more (~4075 tok)
- `HigherOrderTapProxy.php` — The target being tapped. (~175 tok)
- `Manager.php` — Laravel-parity abstract manager for multi-driver services. (~1123 tok)
- `ServiceProvider.php` — Service provider: ServiceProvider (~116 tok)

## src/Phare/Support/Facades/

- `Application.php` — Application: class Application extends Facade (~360 tok)
- `Artisan.php` — Artisan: getFacadeAccessor (~264 tok)
- `Auth.php` — Auth: getFacadeAccessor (~182 tok)
- `Broadcast.php` — Broadcast: getFacadeAccessor (~242 tok)
- `Cache.php` — Cache: getFacadeAccessor (~431 tok)
- `DB.php` — DB: getFacadeAccessor (~561 tok)
- `DebugLogger.php` — DebugLogger: getFacadeAccessor (~208 tok)
- `Event.php` — Event: Event (~359 tok)
- `Facade.php` — The application instance being facaded. (~576 tok)
- `Log.php` — Log: getFacadeAccessor (~204 tok)
- `Request.php` — Request: getFacadeAccessor (~735 tok)
- `Response.php` — Response: getFacadeAccessor (~177 tok)
- `Sanctum.php` — Sanctum: getFacadeAccessor (~228 tok)
- `Security.php` — Security: getFacadeAccessor (~369 tok)
- `Session.php` — Session: getFacadeAccessor (~181 tok)
- `Sqids.php` — Sqids: getFacadeAccessor (~75 tok)

## src/Phare/Support/Traits/

- `ReflectsClosures.php` — Resolve event class names from the first typed Closure parameter. (~380 tok)

## src/Phare/Testing/

- `Assert.php` — is: abstract class Assert extends PHPUnit (~281 tok)
- `TestCase.php` — Sets the Dependency Injector. (~562 tok)
- `TestResponse.php` — Create a new test response instance. (~1647 tok)

## src/Phare/Testing/Constraints/

- `ArraySubset.php` — ArraySubset: Evaluates the constraint for parameter $other., Returns a string representation of the constraint. (~912 tok)

## src/Phare/Translation/

- `TranslationServiceProvider.php` — Service provider: TranslationServiceProvider (~667 tok)
- `Translator.php` — Translator: addPath, get, choice, trans + 7 more (~1295 tok)

## src/Phare/Validation/

- `MessageBag.php` — MessageBag: add, merge, has, first + 13 more (~863 tok)
- `ValidationException.php` — ValidationException: getValidator, errors, getStatus, setStatus + 6 more (~517 tok)
- `Validator.php` — Validator: passes, fails, errors, validated + 3 more (~2608 tok)

## src/Phare/View/

- `Blade.php` — Declares Blade (~46 tok)
- `BladeOne.php` — BladeOne - A Blade Template implementation in a single file (~34897 tok)
- `BladeOneHtml.php` — trait BladeOneHtml (~13237 tok)
- `BladeView.php` — BladeView: with, render (~128 tok)
- `Factory.php` — Create a new view instance. (~1821 tok)
- `MessageContainer.php` — Class MessageList (~6982 tok)
- `MessageLocker.php` — Class MessageLocker (~4454 tok)
- `View.php` — Add a piece of data to the view. (~759 tok)
- `ViewComposer.php` — Bind data to the view. (~134 tok)
- `ViewServiceProvider.php` — Register view composers. (~533 tok)

## src/Phare/View/Concerns/

- `SharesData.php` — The shared view data. (~300 tok)

## src/Phare/View/Tags/

- `BladeFunction.php` — Trait: BladeFunction (~90 tok)
- `BladeHtml.php` — Trait: BladeHtml (~969 tok)

## src/Phare/View/Template/

- `TemplateEngine.php` — Start a section. (~1817 tok)

## tasks/

- `prd.json` — Declares mismatch (~9457 tok)
- `progress.txt` — Ralph Progress — Laravel 13 Parity Audit Pass (~6148 tok)

## tests/

- `Pest.php` — is: something (~545 tok)
- `TestCase.php` — TestCase: createApplication (~72 tok)

## tests/Auth/

- `PasskeyAuthenticatorTest.php` — implements: findByCredentialId, storeCredential, verify, put + 14 more (~1068 tok)
- `PasskeyRegistrarTest.php` — implements: findByCredentialId, storeCredential, verify, put + 17 more (~1629 tok)

## tests/Cache/

- `ArrayAdapterTest.php` (~1232 tok)
- `CacheManagerTest.php` — Declares refreshApplication (~1436 tok)
- `CacheRepositoryTest.php` — Declares refreshCacheRepositoryTestApplication (~952 tok)
- `NullAdapterTest.php` (~203 tok)
- `RedisClusterAdapterTest.php` (~274 tok)

## tests/Collections/

- `ArrTest.php` — Declares string (~1400 tok)
- `StrTest.php` (~1876 tok)

## tests/Console/

- `AgentFriendlyTest.php` — Minimal concrete command used for testing the AgentFriendly trait. (~939 tok)
- `MakeMigrationCommandTest.php` — TestableMakeMigrationCommand: setBasePath, setArgument, setOption, getMessages + 6 more (~1460 tok)
- `MigrateCommandTest.php` — FakeMigrator: run, reset, rollback, execute + 5 more (~1465 tok)

## tests/Container/

- `AfterResolvingAttributeTest.php` — AfterResolvingAttributeTest: test_register_callback_stores_under_attribute_class, test_fire_invokes_registered_callback_with_attribute_instance_obj... (~1496 tok)
- `ContainerCompatibilityTest.php` — Interface: ContainerCompatibilityLoggerInterface (11 methods) (~3015 tok)
- `ContainerTest.php` — Interface: LoggerInterface (4 methods) (~1618 tok)
- `ContextualBindingEdgeTest.php` — Interface: EdgeTransport (7 methods) (~806 tok)
- `PSR11AndArrayAccessTest.php` (~307 tok)

## tests/Container/Attributes/

- `AuthenticatedTest.php` — AuthenticatedTest: test_returns_user_when_authenticated, test_throws_authentication_exception_when_no_user, test_resolves_user_via_named_guard, gua... (~711 tok)
- `AuthTest.php` — AuthTest: test_resolves_auth_manager_from_container, test_resolves_named_guard_via_auth_manager, guard (~454 tok)
- `BroadcastTest.php` — BroadcastTest: test_resolves_default_driver_when_no_arg, test_resolves_named_driver_via_broadcast_manager, driver (~395 tok)
- `CacheTest.php` — CacheTest: test_resolves_default_cache_when_no_arg, test_resolves_named_store_via_cache_manager, store (~468 tok)
- `CurrentUserTest.php` — CurrentUserTest: test_returns_current_user_from_auth_manager, test_returns_null_when_no_user, test_resolves_user_from_named_guard, guard (~565 tok)
- `DBTest.php` — DBTest: test_resolves_default_connection_when_no_arg, test_resolves_named_connection_via_db_manager, connection (~368 tok)
- `FakeAuthManager.php` — FakeAuthManager: user (~60 tok)
- `GiveTest.php` — GiveTest: test_give_builds_concrete_with_params, test_give_resolves_for_variadic_param_as_single_item_array (~399 tok)
- `HashTest.php` — HashTest: test_resolves_default_driver_when_no_arg, test_resolves_named_driver_via_hash_manager, driver (~362 tok)
- `LogTest.php` — LogTest: test_resolves_default_log_driver_when_no_arg, test_resolves_named_log_driver, driver (~333 tok)
- `MailTest.php` — MailTest: test_resolves_default_mailer_when_no_arg, test_resolves_named_mailer_via_mail_manager, mailer (~371 tok)
- `QueueTest.php` — QueueTest: test_resolves_default_connection_when_no_arg, test_resolves_named_connection_via_queue_manager, connection (~369 tok)
- `RouteParameterTest.php` — RouteParameterTest: test_resolves_named_route_param_from_container_binding, test_returns_null_when_key_missing, test_returns_null_when_route_params... (~410 tok)
- `SessionTest.php` — SessionTest: test_resolves_default_store_when_no_arg, test_resolves_named_store_via_session_manager, store (~385 tok)
- `StorageTest.php` — StorageTest: test_resolves_default_filesystem, test_resolves_named_disk_via_filesystem_manager, disk (~458 tok)
- `WhenHasAttributeTest.php` — [Attribute(Attribute::TARGET_PARAMETER)] (~259 tok)

## tests/Container/Hooks/

- `ResolutionHooksTest.php` — RefreshTestTarget: setBus (~511 tok)

## tests/Container/Lifecycle/

- `ForgetTest.php` (~357 tok)
- `InstanceAndExtendTest.php` (~663 tok)
- `ScopedInstancesTest.php` (~295 tok)

## tests/Container/MethodBinding/

- `CallAndWrapTest.php` — CallAndWrapTestTarget: greet, withDep (~707 tok)

## tests/Database/

- `BlueprintTest.php` — Declares columns (~1408 tok)
- `FactoryTest.php` — TestUserFactory: definition (~2395 tok)
- `MigrationTest.php` — MigrationTest: test_can_create_table, test_can_drop_table, test_can_add_columns_to_existing_table, test_migration_class_works + 3 more (~1348 tok)
- `MigratorTest.php` — extends: up, down, up, up + 10 more (~2597 tok)
- `SchemaBuilderTest.php` — Declares columns (~2026 tok)
- `SeederTest.php` — Declares run (~1142 tok)

## tests/Eloquent/

- `AttributeCastingTest.php` — UppercaseCast: get, set, getLastNameAttribute, setLastNameAttribute (~1960 tok)
- `BelongsToManyTest.php` — makeUser: makeRole (~1642 tok)
- `BuilderScopesTest.php` — ScopedUser: scopeActive (~1232 tok)
- `DirtyTrackingTest.php` — Model — table: dirty_tracking_models, 2 fields (~641 tok)
- `EloquentBuilderTest.php` (~2070 tok)
- `EventLifecycleTest.php` — EventLifecycleObserver: creating, updated (~2187 tok)
- `MassAssignmentTest.php` — Model — 1 fields (~522 tok)
