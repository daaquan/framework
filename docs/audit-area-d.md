# Audit — Area D: Async & Extras (Queue, Events, Broadcasting, Notifications, Mail, Scheduler)

**Laravel 13 reference:** `/opt/laravel-framework` @ `13.2.0` (read-only)
**Phare package:** `phare/framework`, namespace `Phare\`, `src/Phare/`
**Method:** per-subsystem 1:1 public-API diff vs Laravel 13 + Wrapper Rule (§2) grep.
**Scope:** read-and-record only — no framework source edited.

---

### Queue

- 現状 (Current) — Phare:
  Twelve files in `src/Phare/Queue/` (1131 LOC total): `QueueInterface.php`
  (5 pub: `push/pop/size/clear/delete`), `Job.php` (240 LOC, abstract — 14
  pub instance methods + 1 static + 1 abstract `handle()` + 1 hookable
  `failed(\Exception)`), `QueueManager.php` (362 LOC, extends
  `Phare\Support\Manager`, ~20 pub methods), `SyncQueue.php` (58 LOC),
  `DatabaseQueue.php` (136 LOC), `RedisQueue.php` (193 LOC),
  `Queueable.php` (47 LOC trait, 4 static methods), `QueueServiceProvider.php`
  (64 LOC), `Connectors/{ConnectorInterface,DatabaseConnector,RedisConnector,SyncConnector}.php`
  (4 files, 1 method each: `connect(array $config): QueueInterface`).

  - **`Phare\Queue\QueueInterface`** — 5 methods: `push(Job, ?string $queue): string`,
    `pop(?string $queue): ?Job`, `size(?string $queue): int`,
    `clear(?string $queue): int`, `delete(Job): bool`. All five are
    typed on the concrete `Phare\Queue\Job` abstract — no contract for
    a `Job` interface.
  - **`Phare\Queue\Job`** abstract — base class for USER jobs. Public:
    abstract `handle(): void`, `failed(\Exception): void` (default no-op),
    builder methods `onQueue(string): static`, `delay(int $seconds): static`,
    `timeout(int $seconds): static`, `retries(int): static` (sets
    `$maxRetries`, NOT current retry count), `withData(array): static`;
    accessors `getJobId/getQueue/getDelay/getTimeout/getRetries/getMaxRetries/getData/getAvailableAt/getCreatedAt`;
    state methods `incrementRetries(): void`, `canRetry(): bool`,
    `isAvailable(): bool`; serialization `serialize(): array`, static
    `deserialize(array): static`. Fields: `$jobId`, `$queue='default'`,
    `$delay=0`, `$timeout=60`, `$retries=0`, `$maxRetries=3`,
    `$data=[]`, `$availableAt`, `$createdAt`. JobId via `uniqid('job_', true)`.
  - **`Phare\Queue\QueueManager extends Manager`** — ctor takes
    `array|ContainerContract`, registers `sync`/`database`/`redis`
    default connectors, `defaultConnection='sync'`. Public:
    `getDefaultDriver()`, `connection(?string): QueueInterface`,
    `extend(string $driver, \Closure): static`, `push(Job, ?string $queue, ?string $connection): string`,
    `later(Job, int $delay, ?string $queue, ?string $connection): string`,
    `pop(?string $queue, ?string $connection): ?Job`,
    `size`, `clear`, `work(?string $queue, ?string $connection, int $maxJobs=0): void`,
    `getDefaultConnection/setDefaultConnection`, `getConnections`,
    `getConfig`, `connected`, `before/after/looping/failing(callable)`.
    `work()` is an INFINITE LOOP — `while(true)` with `sleep(1)` when
    empty; no signal handling, no `$shouldQuit` flag, no `--once`/`--stop-when-empty`/`--max-time`,
    no memory check.
  - **`Phare\Queue\SyncQueue implements QueueInterface`** — `push()`
    calls `$job->handle()` IMMEDIATELY inside push, rethrows on
    failure. `pop`/`size`/`clear`/`delete` are STUBS returning
    `null`/`0`/`0`/`true`.
  - **`Phare\Queue\DatabaseQueue implements QueueInterface`** —
    **FAKE.** Stores jobs in `protected array $jobs = []` (instance
    in-memory array). Source comment line 27-28 is verbatim:
    `// In a real implementation, this would insert into database`
    `// For now, store in memory for testing`. The `'jobs'` table
    name is read from config but NEVER referenced in a SQL string.
    Exposes `getJobs()` + `flush()` "for testing purposes" methods
    on the production class. Job state (`reserved_at`, `attempts`,
    `available_at`) lives in the array element, not in a DB row.
    **Process-end = all queued jobs lost.**
  - **`Phare\Queue\RedisQueue implements QueueInterface`** — **FAKE.**
    Stores jobs in `protected array $queues = []`. Source comment
    line 9 verbatim: `// Mock Redis storage`. No `phpredis`/`predis`
    client import, no `LPUSH`/`BRPOPLPUSH`/`ZADD` — the "Redis"
    semantics are a manual `array_shift` + a `delayed:<queue>` array
    bucket. Also exposes `getQueues()` + `flush()` testing methods on
    the production class. Same process-end data loss.
  - **`Phare\Queue\Queueable`** trait — 4 statics: `dispatch(...$arguments): string`,
    `dispatchAfter(int $delay, ...$arguments)`, `dispatchOn(string $queue, ...$arguments)`,
    `dispatchUsing(string $connection, ...$arguments)`. All four
    `new static(...$arguments)` and `app('queue')->push(...)`. Returns
    the job ID string — NOT a chainable builder.
  - **`Phare\Queue\QueueServiceProvider`** — `register()` binds
    `'queue'` singleton (the QueueManager), `'queue.manager'` alias
    (closure that returns `$app['queue']`), `QueueManager::class`
    binding (same), `QueueInterface::class` binding (resolves
    `$app['queue']->connection()`). Default config block hardcoded:
    `'default' => 'sync'`, three drivers. **NO `queue.connection`
    binding, NO `queue.failer` binding, NO `queue.worker` binding.**
    `boot()` defines THREE global helpers conditionally on
    `function_exists` — `queue()`, `dispatch()`, `dispatch_after()` —
    declared INSIDE `boot()`'s method body (PHP allows this; the
    declarations run only on first `boot()` call but emit
    `Cannot redeclare` on a second one. Same pattern as Phare's
    other helpers).
  - **`Phare\Queue\Connectors\{Sync,Database,Redis}Connector`** —
    one-method `connect(array): QueueInterface`. Each connector just
    `new XxxQueue($config)`. No driver discovery, no `setConnectionName`,
    no `setContainer` (Laravel injects the container into each
    connection for job-class resolution).
  - **Wiring:** `QueueServiceProvider` registered in
    `Foundation\AbstractApplication` (need to verify in E-area
    audit). No queue console command in
    `Console/Commands/` (grep returns zero `QueueWorkCommand`-style
    files — DEFERRED to US-E04 console audit).

- 期待 (Expected) — Laravel 13 reference:
  Forty+ files across `Illuminate\Queue\`, `Illuminate\Bus\`,
  `Illuminate\Contracts\Queue\`, `Illuminate\Queue\Failed\`,
  `Illuminate\Queue\Console\`, `Illuminate\Queue\Connectors\`,
  `Illuminate\Queue\Jobs\`, `Illuminate\Queue\Events\`,
  `Illuminate\Queue\Middleware\`:
  - **Contracts:** `Contracts\Queue\Queue` (push/pushOn/pushRaw/later/laterOn/bulk/pop/size),
    `Contracts\Queue\Factory` (connection), `Contracts\Queue\Job`
    (the QUEUE-SIDE wrapper — fire/release/delete/attempts/etc.),
    `Contracts\Queue\ShouldQueue` (marker),
    `Contracts\Queue\ShouldBeUnique`, `ShouldBeUniqueUntilProcessing`,
    `ShouldBeEncrypted`, `ClearableQueue`, `EntityResolver`,
    `QueueableCollection`, `QueueableEntity`.
  - **`Illuminate\Queue\QueueManager implements Factory, Queue,
    ClearableQueue, Monitor`** (~430 LOC) — public surface includes
    `connection(?string)`, `addConnector(string, Closure)`,
    `resolve(string)`, `getName(?string)`, `extend(string, Closure)`,
    `setApplication(Application)`, plus the full Queue facade
    interface (push/pushOn/pushRaw/later/laterOn/bulk/pop/size/clear),
    event handlers (`before(Closure)`, `after(Closure)`,
    `looping(Closure)`, `failing(Closure)`, `exceptionOccurred(Closure)`,
    `stopping(Closure)`, `createPayloadUsing(Closure)`).
  - **Concrete queue drivers:** `Illuminate\Queue\SyncQueue`,
    `DatabaseQueue` (real PDO INSERT/UPDATE, `released_at`/`reserved_at`/`attempts`
    DB columns, transactional pop via SELECT FOR UPDATE),
    `RedisQueue` (real `Illuminate\Redis\Connections\Connection`
    via phpredis/predis; ZADD for delayed/reserved sets, RPOPLPUSH
    for atomic pop), `BeanstalkdQueue`, `SqsQueue`, `NullQueue`.
  - **Queue-side job wrappers (the missing layer in Phare):**
    `Jobs\Job` abstract, `Jobs\DatabaseJob`, `Jobs\RedisJob`,
    `Jobs\BeanstalkdJob`, `Jobs\SqsJob`, `Jobs\SyncJob`. Each wraps
    the raw payload and exposes `getJobId`, `payload`,
    `attempts`, `release`, `delete`, `markAsFailed`, `failed`,
    `fire`, `getName`, `resolveName`, `getQueue`, `getConnectionName`,
    `getRawBody`. Laravel separates USER jobs (a class with `handle()`)
    from QUEUE-SIDE jobs (the wrapper that runs the user job).
  - **`Illuminate\Bus\Queueable` trait** (the real one): `onConnection`/`onQueue`/`allOnConnection`/`allOnQueue`/`withChain`/`delay`/`withoutDelay`/`afterCommit`/`beforeCommit`,
    `$connection`/`$queue`/`$chained`/`$delay`/`$afterCommit`
    fields. NOT a `dispatch` trait — dispatching goes through
    `Bus\Dispatcher`.
  - **`Illuminate\Bus\Dispatcher`** + `Illuminate\Bus\PendingDispatch`
    + `Illuminate\Bus\PendingChain`. The `dispatch($job)` helper
    returns `PendingDispatch`, which is chainable
    (`->onQueue('emails')->delay(60)->afterCommit()`). The terminal
    call is `__destruct()` (auto-dispatch on out-of-scope).
  - **`Illuminate\Queue\Worker`** (~530 LOC) — the daemon. Handles
    SIGTERM/SIGUSR1/SIGUSR2, memory ceilings, max-time,
    `--once`/`--stop-when-empty`/`--rest`/`--sleep`/`--max-jobs`/`--backoff`
    options, exception handling via the framework `ExceptionHandler`.
  - **`Illuminate\Queue\Listener`** — supervisor that spawns Worker
    subprocesses (the pre-2018 model; still present for legacy parity).
  - **`Illuminate\Queue\Failed\*`** — `FailedJobProviderInterface`,
    `DatabaseFailedJobProvider` (real DB-backed `failed_jobs` table),
    `DatabaseUuidFailedJobProvider` (newer Laravel default),
    `NullFailedJobProvider`, `FileFailedJobProvider`,
    `DynamoDbFailedJobProvider`.
  - **`Illuminate\Queue\Console\*`** — `WorkCommand`, `ListenCommand`,
    `RestartCommand`, `RetryCommand`, `RetryBatchCommand`,
    `ForgetCommand`, `FlushCommand`, `FailedTableCommand`,
    `TableCommand`, `BatchesTableCommand`, `ClearCommand`,
    `PruneBatchesCommand`, `PruneFailedJobsCommand`, `MonitorCommand`.
  - **`Illuminate\Queue\Events\*`** — `JobQueued`, `JobQueueing`,
    `JobProcessing`, `JobProcessed`, `JobFailed`, `JobReleasedAfterException`,
    `JobExceptionOccurred`, `JobAttempted`, `Looping`, `WorkerStopping`.
  - **`Illuminate\Queue\Middleware\*`** — `RateLimited`,
    `RateLimitedWithRedis`, `Skip`, `WithoutOverlapping`,
    `ThrottlesExceptions`, `ThrottlesExceptionsWithRedis`.
  - **`Illuminate\Queue\InteractsWithQueue`** trait — used by USER
    jobs to access the queue-side wrapper (`$this->job->attempts()`,
    `$this->job->fail()`, `$this->release(60)`).
  - **`Illuminate\Queue\SerializesModels`** trait — when a User job
    is queued, every Eloquent `Model` argument is serialized as
    `(class, id)` and refetched on dequeue. CRITICAL for queue
    correctness — never serialize live model state.
  - **`Illuminate\Queue\CallQueuedHandler`** — central runner that
    calls `$user_job->handle()`, manages middleware, handles
    `ShouldBeUnique` lock, fires events.
  - **`Illuminate\Bus\Batchable`** + `Illuminate\Bus\Batch` +
    `Illuminate\Bus\PendingBatch` — full batch API (`Bus::batch([...])->then()->catch()->finally()`).
  - **Per-job behavior knobs (on the USER job class):** `$tries`
    (int) OR `tries(): int`, `$maxExceptions`, `$timeout`, `$retryAfter`
    OR `backoff(): int|array`, `$failOnTimeout`, `$deleteWhenMissingModels`,
    `$shouldBeEncrypted`, `$connection`, `$queue`, `$delay`,
    `middleware(): array`, `failed(Throwable $e): void`.

- 差分 (Gaps):

  **Phalcon\ leaks (1 leak class, 1 new instance):**
  - ➀ **Phalcon-class-import-in-subsystem (recurs from C07).**
    `Phare\Queue\QueueManager` imports `Phalcon\Config\Config;`
    and calls `$value instanceof \Phalcon\Config\Config` +
    `$value->toArray()` inside `normalizeConfig()`. Subsystem-namespace
    `src/Phare/Queue/` therefore fails the Phalcon-clean check
    by direct import. Distinct from C07's `Phalcon\Di\Di::getDefault()`
    use case (C07 = service-locator; D01 = config-shape coercion)
    but the same defect CLASS: a Phare subsystem importing a
    Phalcon class. Counting RULE going forward: each subsystem
    that fails the namespace check counts ONCE, regardless of
    which Phalcon class is imported. Running tally: C01, C07,
    **D01** — third instance.

  **Critical correctness/security defects (fake-driver class — NEW
  for US-S01):**
  - **DatabaseQueue is FAKE — stores jobs in a `protected array
    $jobs = []` instance field instead of inserting into the
    `jobs` DB table.** Source-code comment line 27-28 says
    verbatim `// In a real implementation, this would insert
    into database / // For now, store in memory for testing`.
    Production impact: every queued job is lost when the PHP
    process terminates (request end on fpm, worker exit on cli).
    Multi-process workers cannot dequeue each other's jobs.
    Same family as the `view()` stub flagged in A05 Response —
    but vastly more severe because the failure mode is silent
    data loss, not a debug-string emit. **Blocks any production
    use.** NEW US-S01 defect class: **FAKE-DRIVER** (a class that
    claims a transport but uses in-memory storage). Distinct
    from STUB-DEFECT (no-op method) and ORPHAN-SUBSYSTEM
    (no callers).
  - **RedisQueue is FAKE — stores jobs in a `protected array
    $queues = []` instance field.** Source-code comment line 9
    verbatim `// Mock Redis storage`. No `phpredis`/`predis`
    composer import in the file. Same in-process data-loss
    semantics as DatabaseQueue. Second instance of the
    FAKE-DRIVER defect class.
  - **No `Beanstalkd` driver despite `pheanstalk` in
    composer.json.** Per the project CLAUDE.md, Beanstalk via
    Pheanstalk is the documented queue transport. Grep
    `grep -rn 'Pheanstalk\|Beanstalk' src/Phare/Queue/` returns
    zero. The composer dep is unused.
  - **Failed-job storage is a NO-OP.** `Job::failed(\Exception)`
    is `// Default implementation - can be overridden`. No
    `failed_jobs` table, no `FailedJobProviderInterface`, no
    `DatabaseFailedJobProvider`. A job that exhausts retries
    DISAPPEARS — no audit trail, no `queue:failed`/`queue:retry`
    artisan flow, no observability. Same severity as
    DatabaseQueue fake-driver: silent data loss.
  - **`work()` is an unbounded `while(true)` with `sleep(1)`.**
    No SIGTERM/SIGUSR handlers, no memory ceiling, no `--rest`,
    no `--once`, no `--max-time`, no `--max-jobs` (the
    `$maxJobs` arg exists but defaults to 0 = forever).
    Long-running workers WILL leak memory and never recycle.
    Composes with the fake-driver defect: even if drivers were
    real, the worker has no graceful-shutdown semantics.
  - **Hardcoded 60s backoff in `handleFailedJob` —
    silent-config-dropthrough family, 8th instance** (continues
    the C03/C04/C05/C06/C07 pattern). Phare ignores any per-job
    `backoff()` / `$backoff` / `$retryAfter` knob and unconditionally
    `$job->delay(60)`. Laravel reads either an integer (constant
    delay), an array (escalating delays `[1, 5, 10]` per attempt),
    or the result of a `Closure` returning either, AND respects
    the job's `$tries` / `tries()` override.

  **Missing wholesale (the largest missing-surface in the audit
  so far — larger than C07):**
  - **No QUEUE-SIDE job wrapper layer.** Laravel separates USER
    jobs (a plain class with `handle()`) from QUEUE-SIDE jobs
    (`Jobs\DatabaseJob`/`Jobs\RedisJob`/etc. — the wrapper that
    runs the user job, tracks attempts, calls release/delete).
    Phare COLLAPSES the two into `Phare\Queue\Job` which is the
    user-class base AND carries the queue-side state
    (`$retries`, `$availableAt`). Effect: a user job
    deserialized at pop time cannot distinguish "this is my
    first attempt" from "this is my 3rd retry after release"
    because BOTH live on the same object's fields. NEW
    porting-hazard class for US-S01: **layer-collapse** (two
    Laravel layers merged into one Phare class — a port must
    unmerge them).
  - **No `Bus\Dispatcher` / `PendingDispatch` / `PendingChain` /
    `PendingBatch` / `Batchable` / `Batch`.** The entire
    chainable-dispatch fluent API is absent. `Phare\Queue\Queueable::dispatch($args)`
    returns `string` (job ID) — TERMINAL. Laravel `dispatch($job)`
    returns `PendingDispatch` — CHAINABLE
    (`dispatch(new SendEmail($u))->onQueue('emails')->delay(60)->afterCommit()`).
    A direct port of any Laravel app code touching the dispatch
    chain breaks at the call site.
  - **No `ShouldQueue` / `ShouldBeUnique` /
    `ShouldBeUniqueUntilProcessing` / `ShouldBeEncrypted`
    interfaces.** Marker interfaces drive Laravel's
    `CallQueuedHandler` to apply locks, encryption, uniqueness
    enforcement. Without them, even patching Phare drivers
    cannot replicate Laravel semantics.
  - **No `SerializesModels` trait.** A USER job that takes an
    Eloquent `Model` argument and queues it will serialize the
    LIVE model state — including stale relations, dirty
    attributes, and the entire object graph. Laravel serializes
    only `(class, id)` and refetches at dequeue. Composes with
    every Eloquent audit gap (B01-B04) to produce stale-data
    bugs in production.
  - **No `InteractsWithQueue` trait.** User jobs cannot release
    themselves with backoff (`$this->release(60)`), cannot
    `fail($e)` voluntarily, cannot inspect attempts mid-run.
  - **No `Queue::middleware()` system.** RateLimited /
    WithoutOverlapping / ThrottlesExceptions / Skip — entire
    job-middleware pipeline absent. The `before/after/failing/looping`
    callbacks in QueueManager are NOT middleware — they are
    global hooks. Laravel's job-level middleware lets a USER
    job specify `public function middleware()` returning per-job
    middleware instances.
  - **No queue events.** `JobQueued`/`JobProcessing`/`JobProcessed`/`JobFailed`/`JobReleasedAfterException`
    do not exist. Composes with the (yet-to-audit) US-D02
    Events subsystem to mute every observability hook a Laravel
    app would attach to queue lifecycle.
  - **No worker daemon command, no failed-jobs commands, no
    batches commands, no restart signal.** All 14 Laravel
    `Queue\Console\*` commands missing. Composes with US-E04
    artisan audit.
  - **No `setApplication()` / `setContainer()` on queue
    connections.** Laravel injects the container into every
    queue so `CallQueuedHandler` can resolve queued classes
    by name. Phare's `deserialize()` instantiates via
    `static::deserialize($payload)` — a bare `new static()`
    with no container — every user job's constructor must
    accept zero args or use service-locator inside `handle()`.
    Service-locator antipattern recurs (same family as C07
    `Di::getDefault()`).
  - **`Job::deserialize` uses `new static()` then field
    assignment — bypasses ctor.** A USER job ctor that runs
    setup (e.g. `__construct(User $u, string $msg)`) is NEVER
    invoked on dequeue. Same severity as the SerializesModels
    gap — user jobs cannot rely on ctor invariants.
  - **JobId via `uniqid('job_', true)`** — Laravel uses
    `Illuminate\Support\Str::random(40)` or a UUID. `uniqid`
    is microtime-based and predictable at scale; not a
    cryptographic ID but Laravel's pattern is harder to
    collision-attack.
  - **No `pushRaw(string $payload, ?string $queue, array $options)`.**
    Phare's `QueueInterface` is Job-typed only; Laravel
    `Contracts\Queue\Queue::pushRaw` is required for non-Job
    payloads (manual JSON, integration with external systems).
  - **No `bulk(array $jobs, $data, $queue)`.** Phare cannot
    push N jobs atomically.
  - **`SyncQueue::push` rethrows the exception.** Laravel's
    `SyncQueue::push` catches and processes through the failed
    job pipeline — `Sync` is a real driver-shape, not a
    handle-and-throw shortcut. Phare's rethrow breaks the
    contract that `$queue->push()` is non-throwing on job
    failure.
  - **Connectors are barebones.** Laravel's
    `Connectors\DatabaseConnector::connect` reads the connection
    DB resolver from the container, passes it into
    `DatabaseQueue` along with the `failed_job_ids` callback.
    Phare's Connectors just `new DatabaseQueue($config)` with
    no DB resolver, no failed-job hook, no container.

  **Same-name-different-shape porting hazards (3 new — running
  list now 16 entries):**
  - **14th — `Queueable::dispatch(...$arguments): string`** —
    static TERMINAL on a trait. Laravel `dispatch($job): PendingDispatch`
    is a GLOBAL HELPER returning a CHAINABLE BUILDER. Same name,
    opposite layering AND opposite shape.
  - **15th — `Job::delay(int $seconds): static`** — instance
    setter on the USER job class. Laravel `delay($delay): $this`
    lives on the `PendingDispatch` builder, NOT on the user job.
    Phare port: any code calling `MyJob::dispatch()->delay(60)`
    fails because Phare's `dispatch()` returns `string`, not a
    builder; calling `$jobInstance->delay(60)` works but is
    semantically different (mutates the job that's already been
    constructed for queuing).
  - **16th — `Job::retries(int $retries): static`** — sets
    `$this->maxRetries`. Laravel has NO `retries($n)` method on
    the user job; the override is the `$tries` property or
    `public function tries(): int`. Phare's `retries($n)` is
    same-name-different-meaning vs Laravel's
    `Job::getJobBackoff()`/`Job::tries()` cluster.

  **Silent-arg-drop family — 8th instance** (B02 `update`,
  B03 pivots × 2, C01 `$remember`, C03 driver config, C05
  `$encrypter`, C06 `$amount`, C07 hasher; now **D01
  worker-options**). `QueueManager::work(?string $queue,
  ?string $connection, int $maxJobs=0)` drops the entire
  Laravel worker option set: `$sleep`, `$timeout`, `$delay`,
  `$tries`, `$backoff`, `$memory`, `$rest`, `--once`,
  `--stop-when-empty`. The signature accepts only the 3 above.

  **No-contracts streak — 12th consecutive subsystem.**
  `Phare\Queue\QueueInterface` is the ONLY contract; the
  Laravel set has 12 contracts (`Queue`, `Factory`, `Job`,
  `ShouldQueue`, `ShouldBeUnique`, `ShouldBeUniqueUntilProcessing`,
  `ShouldBeEncrypted`, `ClearableQueue`, `EntityResolver`,
  `QueueableCollection`, `QueueableEntity`, `Monitor`). None
  shipped in `Phare\Contracts\Queue\`.

  **Orphan-driver-instance pattern (NEW for US-S01).** Phare
  ships `'database'` and `'redis'` as default connectors and
  default-config entries, AND wires them into the
  `QueueServiceProvider` default fallback — but their
  implementations are FAKE. This is distinct from the
  orphan-subsystem class (B07/C07/Passkeys, where the class
  has zero callers): here the DRIVERS are heavily callable
  but functionally INERT. The defect is "wired but fake" vs
  "shipped but unwired". Promote both as separate US-S01
  taxonomy entries.

- 工数感 (Effort): **XL.** Phare Queue is the LARGEST
  parity gap in the audit so far. Per-axis severity:
  - **Correctness**: CRITICAL. Two of three drivers
    (`Database`, `Redis`) are in-memory fakes. Production
    use of Phare queues today silently loses every job. A
    real port must rewrite both drivers (~500-800 LOC each)
    with proper transactional pop semantics and an external
    transport client.
  - **Layer architecture**: BLOCKED. The Job vs Jobs\<Driver>Job
    layer collapse means a port cannot just "add the wrapper
    layer" — every USER job in any downstream app would
    re-type. Migration path: introduce
    `Phare\Contracts\Queue\Job` (the wrapper contract), keep
    `Phare\Queue\Job` as a deprecated alias for the user-job
    base, split out `Phare\Queue\Jobs\{Sync,Database,Redis}Job`
    classes. Then update `Phare\Queue\QueueInterface::pop()` to
    return the wrapper, not the user job.
  - **Async infrastructure**: BLOCKED on US-D02 Events
    (queue events depend on the Events dispatcher), US-D04
    Notifications + US-D05 Mail (those wire mail-via-queue),
    US-E04 Console (the `queue:work`/`queue:failed`/`queue:retry`
    commands). The unblock order is D02 → D01 wrapper layer
    → D04/D05/E04.
  - **Missing surface**: a clean port needs ~25 new classes
    plus 12 contracts plus 14 console commands plus 10 events
    plus 6 middleware plus the `Bus\Dispatcher`/`PendingDispatch`
    cluster. The 88-LOC C07 PasswordBroker was "L" because of
    the missing flow infrastructure (notifications, signed URLs);
    D01 is "XL" because the existing CODE is fake, requiring
    rewrite-plus-add rather than just add. Estimated effort:
    multiple weeks per engineer.

  Recommended port sequence: (1) wire the contracts namespace
  (`Phare\Contracts\Queue\*`) — no behavior change, unblocks
  type-checking; (2) replace `DatabaseQueue` with a real
  PDO-backed impl using `Phare\Database\MySql\DatabaseManager`
  (composes with B05 Migrations audit findings); (3) replace
  `RedisQueue` with an actual `predis`/`phpredis` client (new
  composer dep); (4) ship `Beanstalkd` via `pheanstalk` (already
  in composer.json — unused); (5) introduce the
  `Jobs\<Driver>Job` wrapper layer + `InteractsWithQueue` +
  `SerializesModels`; (6) ship `Bus\Dispatcher` + `PendingDispatch`;
  (7) ship `Worker` + signal handling + `queue:work` command;
  (8) ship `FailedJobProvider` + `failed_jobs` migration +
  `queue:failed`/`queue:retry` commands; (9) wire job events.
