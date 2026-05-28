# Audit — Area D: Async & Extras (Queue, Events, Broadcasting, Notifications, Mail, Scheduler)

**Laravel 13 reference:** `/opt/laravel-framework` @ `13.2.0` (read-only)
**Phare package:** `phare/framework`, namespace `Phare\`, `src/Phare/`
**Method:** per-subsystem 1:1 public-API diff vs Laravel 13 + Wrapper Rule (§2) grep.
**Scope:** read-and-record only — no framework source edited.

---

### Queue

- Current — Phare:
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

- Expected — Laravel 13 reference:
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

- Gaps:

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

- Effort: **XL.** Phare Queue is the LARGEST
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

---

### Events

- Current — Phare:
  Seven files in `src/Phare/Events/` (657 LOC total):
  - `Dispatcher.php` (437 LOC, implements `Phare\Events\Contracts\Dispatcher`,
    uses `Phare\Support\Traits\ReflectsClosures`). 16 public methods +
    9 protected. Public surface:
    `__construct(Container $app)`,
    `setTransactionManagerResolver(?callable): static`,
    `listen(mixed $events, mixed $listener = null): void`,
    `hasListeners(string): bool`,
    `hasWildcardListeners(string): bool`,
    `push(string $event, object|array $payload = []): void`,
    `dispatch(string|object $event, mixed $payload = [], bool $halt = false): mixed`,
    `dispatchIf(bool|\Closure, string|object, mixed, bool): mixed`,
    `dispatchUnless(bool|\Closure, string|object, mixed, bool): mixed`,
    `until(string|object, mixed = [])`,
    `flush(string $event): void`,
    `forget(string $event): void`,
    `forgetPushed(): void`,
    `defer(callable, ?array = null): mixed`,
    `subscribe(object|string): void`,
    `getListeners(string): array`,
    `getRawListeners(): array`.
  - `Event.php` (11 LOC, abstract — `getName(): string` returns `static::class`).
  - `Listener.php` (8 LOC, abstract — `handle($event): void`).
  - `EventServiceProvider.php` (114 LOC). Registers `events` singleton +
    binds `DispatcherContract::class => $app['events']`. Reads `$listen`
    (event => listener[]) + `$subscribe` (subscriber[]) properties from
    child providers. Discovery hook `discoveredEvents()` returns `[]`.
    Cache hook reads `$this->app->eventsAreCached()` if present.
  - `Contracts/Dispatcher.php` (66 LOC, 12-method interface).
  - `Contracts/ShouldBroadcast.php` (14 LOC, 4-method marker:
    `broadcastOn/broadcastAs/broadcastWith/broadcastWhen`).
  - `Contracts/ShouldDispatchAfterCommit.php` (7 LOC marker).

  In addition (events-adjacent infra):
  - `src/Phare/Auth/Events/` (6 event classes: `Attempting`, `Authenticated`,
    `Failed`, `Login`, `Logout`, `Validated`).
  - `src/Phare/Database/Events/` (5 event classes: `ConnectionEvent`,
    `TransactionBeginning/Committing/Committed/RolledBack`).
  - `src/Phare/Eloquent/Concerns/HasEvents.php` (~360 LOC trait, 8 public
    methods, fires model lifecycle events through `static::$eventDispatcher`).
  - `src/Phare/Providers/EventsManagerProvider.php` (22 LOC) — registers a
    SEPARATE `eventsManager` singleton (Phalcon `Phalcon\Events\Manager`,
    not the Phare `events` dispatcher).
  - `src/Phare/Providers/DispatcherProvider.php` (48 LOC) — also registers
    `eventsManager` (Phalcon\Events\Manager) plus `dispatcher`
    (Phalcon\Mvc\Dispatcher). Both providers bind the same `eventsManager`
    key — last-loaded wins (silent override).

- Reference — Laravel 13 `Illuminate\Events\*`:
  Five files (`/opt/laravel-framework/src/Illuminate/Events/`):
  - `Dispatcher.php` (900 LOC, ~30 public/protected methods).
  - `NullDispatcher.php` (143 LOC) — silent no-op dispatcher that
    forwards `listen/hasListeners/subscribe/flush/forget/forgetPushed`
    to the real one while suppressing `dispatch/push/until`.
  - `CallQueuedListener.php` (262 LOC) — `ShouldQueue`-implementing job
    used to queue-route a class listener. 19 public fields (`tries`,
    `backoff`, `retryUntil`, `timeout`, `failOnTimeout`,
    `shouldBeEncrypted`, `deleteWhenMissingModels`, `shouldBeUnique`,
    `shouldBeUniqueUntilProcessing`, `uniqueId`, `uniqueFor`, …) plus
    `displayName/__clone/failed/uniqueId/uniqueFor/uniqueVia`.
  - `QueuedClosure.php` (179 LOC) — builder for `Event::listen(...)`
    when the closure should be queued (`onConnection/onQueue/onGroup/
    delay/catch/withDeduplicator/resolve`).
  - `InvokeQueuedClosure.php` (36 LOC) — companion `handle/failed` for
    the SerializableClosure wrapper.
  - `EventServiceProvider.php` (28 LOC) — wires `events` singleton with
    `setQueueResolver(QueueFactoryContract)` AND
    `setTransactionManagerResolver(db.transactions)`.

  `Illuminate\Contracts\Events\Dispatcher.php` (83 LOC, 10-method interface).
  Two markers in `Illuminate\Contracts\Events\`: `ShouldDispatchAfterCommit`
  + `ShouldHandleEventsAfterCommit`.

- Public-API diff — Dispatcher implementation:
  - `listen` — Laravel: `($events, $listener = null)` accepts
    `QueuedClosure|callable|array|class-string|string` for `$events`;
    if `$events` is a `Closure` it returns the `Collection` of
    `firstClosureParameterTypes()` (chainable); if `$listener` is a
    `QueuedClosure` it resolves to a closure via `$listener->resolve()`.
    Phare: signature `(mixed $events, mixed $listener = null): void` —
    void return (NOT chainable), no `QueuedClosure` branch (silently
    drops queueable closures), throws `InvalidArgumentException` when
    `$listener` is not Closure/array/string (Laravel does NOT validate
    — it accepts `null` and any callable). Phare's `void`-typed
    `listen` is a behaviour-divergent contract.
  - `dispatch` — Laravel: `($event, $payload = [], $halt = false)`
    returns `array|null`; ON object payload that implements
    `ShouldBroadcast` it `$this->broadcastEvent($payload[0])` BEFORE
    invoking listeners. Phare: `dispatch()` does NOT honour
    `ShouldBroadcast`; no `broadcastEvent()` method exists; the
    `ShouldBroadcast` marker contract is defined but UNREAD by the
    dispatcher. Behavioural gap: a `ShouldBroadcast` event emitted
    through Phare's dispatcher will run listeners but will NEVER
    queue a broadcast — silent feature drop.
  - `subscribe` — Laravel: requires `$subscriber->subscribe($this)` to
    return an `array<event, listeners>`; no fallback. Phare ADDS a
    fallback path — if `subscribe()` returns non-array (or throws
    `ArgumentCountError`), Phare introspects every `on<Event>(...)`
    method via `discoverSubscriberMethods()` and auto-registers
    them. Convention departure. Affects subscriber classes that
    define `onCreated`/`onUpdated`/etc. methods without an
    explicit `subscribe()` — Laravel ignores; Phare auto-wires.
    Subtle compatibility break in either direction.
  - `defer` — present in both with same shape; Phare wraps
    `$result = $callback()` in `try/finally` plus restores
    `deferringEvents/deferredEvents/eventsToDefer` (parity).
    Identical semantics.
  - `until` — both: dispatch with `$halt=true`. Parity.
  - `dispatchIf/dispatchUnless` — **NOT in Laravel's `Dispatcher`
    class**. Laravel exposes these as instance methods on event
    classes through the `Dispatchable` trait, NOT on the dispatcher.
    Phare HAS them on `Dispatcher` — out-of-place API surface.
    Implementation also returns `[]` on skip (when `!$halt`),
    which is inconsistent with Laravel's broadcast-style dispatcher
    semantics.
  - `push/flush` — parity (both implement the same `_pushed` event
    wrapper trick).
  - `forget` — Laravel: separates wildcard (`unset($this->wildcards[$event])`)
    from non-wildcard (`unset($this->listeners[$event])`), then
    invalidates cache by walking `wildcardsCache` and `Str::is()`
    matching. Phare: indiscriminately unsets ALL three arrays
    (`listeners/wildcards/wildcardsCache`) and resets the entire
    cache when the event contains `*`. Phare's variant is over-broad
    but functional.
  - `forgetPushed` — Laravel: walks `$this->listeners` and forgets
    any key ending in `_pushed`. Phare: tracks pushed events in a
    SEPARATE `$pushedEvents` array, then unsets `<event>_pushed`
    listeners. Same outer effect; differing internal state.
  - `setQueueResolver` — **MISSING from Phare**. Laravel: stores a
    `callable(): Queue` resolver, retrieved via `resolveQueue()` to
    push `CallQueuedListener` jobs. Without it, queued listeners
    cannot be wired. This is THE single most consequential missing
    method — it is the bridge between the events subsystem and the
    queue subsystem.
  - `setTransactionManagerResolver` — present in Phare with the
    same signature; resolver call path is parity.
  - `getRawListeners` — both: returns `$this->listeners` raw.
  - `hasWildcardListeners` — present in both; Laravel marks it
    public, Phare also public. Parity.
  - `getListeners/prepareListeners/getWildcardListeners/
    addInterfaceListeners/makeListener/createClassListener/
    createClassCallable/parseClassCallable` — Phare ports all of
    these but with TWO behavioural deltas:
    1. Phare's `parseClassCallable` parses `Class@method` and
       defaults to `handle`; Laravel uses `Str::parseCallback($listener,
       'handle')` (same defaulting but routes through `Str` helper).
       Functionally equivalent. Parity.
    2. Phare's `createClassCallable` does NOT honour `ShouldQueue` —
       Laravel calls `handlerShouldBeQueued($class)` and routes
       through `createQueuedHandlerCallable()` (which uses the queue
       resolver). Phare always returns `[$instance, $method]` —
       a class listener marked `implements ShouldQueue` runs
       SYNCHRONOUSLY. Silent feature drop. Pairs with the missing
       `setQueueResolver`.
  - `eventMatches` — Phare uses native `fnmatch()`; Laravel uses
    `Str::is($key, $eventName)`. Functionally similar for `*`
    patterns but `Str::is` accepts arrays and `\` escaping.

- Public methods present in Laravel, absent in Phare:
  - `setQueueResolver(callable): $this` — the queue bridge. Pairs
    with `resolveQueue()`. **CRITICAL.**
  - `handlerShouldBeQueued/createQueuedHandlerCallable/
    handlerShouldBeDispatchedAfterDatabaseTransactions/
    handlerWantsToBeQueued/createListenerAndJob/propagateListenerOptions/
    queueHandler/createCallbackForListenerRunningAfterCommits` —
    the entire queued-listener pipeline (8 protected methods).
  - `broadcastEvent/shouldBroadcast/broadcastWhen` — broadcast
    bridge (3 protected methods). Pairs with the missing `BroadcastFactory`
    binding under `Phare\Contracts\Broadcasting\Factory` (covered
    in US-D03).

- Same-name / different-shape:
  - 17th entry — `listen()` Phare returns `void`, Laravel returns
    `Collection|null` (chainable when entry is a Closure or
    QueuedClosure).
  - 18th entry — `dispatchIf/dispatchUnless` belong on the
    `Dispatchable` trait in Laravel, NOT the dispatcher class. Phare
    publishes them on the dispatcher — API-shape mislocation.

- Silent-arg-drop 9th instance:
  - `Dispatcher::listen($events, $listener = null)` — Phare drops
    `QueuedClosure` handling entirely; a caller passing a
    `QueuedClosure` falls through to the `InvalidArgumentException`
    branch in Phare (Laravel resolves it to a closure).

- Silent-config-dropthrough 9th instance:
  - `EventServiceProvider::register()` wires the transaction-manager
    resolver against `dbManager` (Phalcon naming), NOT
    `db.transactions` (Laravel naming, as in Laravel's
    `Illuminate\Events\EventServiceProvider`). Any user code that
    expects `db.transactions` binding to drive after-commit dispatch
    will silently fall through (resolver returns null → events
    dispatch immediately, NOT after commit).

- No-contracts streak — 13th:
  - `Phare\Events\Contracts\Dispatcher` ships (12 methods).
  - `ShouldDispatchAfterCommit` ships (marker, parity with Laravel).
  - **MISSING:** `Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit`
    (NO Phare counterpart). This is the listener-side AfterCommit
    marker. Without it, listeners cannot opt in to deferred-until-commit
    dispatch.

- Dual-stack 3rd defect-class instance:
  - `Phare\Events\Dispatcher` ($app['events']) coexists with
    Phalcon `Phalcon\Events\Manager` ($app['eventsManager'] +
    $app['dispatcher']). The Phalcon dispatcher is wired in
    `DispatcherProvider` AND `EventsManagerProvider` (the latter
    silently overrides the former when both load). Two different
    event subsystems active in the same container — confusion
    surface high. Same defect class as A07 (Cache) + C04 (Encryption).

- Wrapper-Rule (§2) leaks via `src/Phare/Events/`:
  - `grep -n "Phalcon\\\\" src/Phare/Events/**` → **ZERO** matches.
    The `src/Phare/Events/` namespace itself is Phalcon-clean.
  - However events-adjacent infra LEAKS:
    1. `src/Phare/Eloquent/Concerns/HasEvents.php:6` imports
       `Phalcon\Di\Di` and uses `Phalcon\Di\Di::getDefault()` to
       resolve `events` (lines 44-49). This is THE binding that
       fires every model lifecycle event — a Phalcon-rooted
       resolver. 4th occurrence of the C07/D01 subsystem-namespace
       leak class.
    2. `src/Phare/Providers/EventsManagerProvider.php:5-7`
       imports `Phalcon\Di\DiInterface`,
       `Phalcon\Di\ServiceProviderInterface`,
       `Phalcon\Events\Manager`. Same in `DispatcherProvider.php`
       (lines 7-11 also import `Phalcon\Mvc\Dispatcher`,
       `Phalcon\Mvc\Dispatcher\Exception`, `Phalcon\Events\Event`).
       These providers are part of the events binding graph.

- File-count ratio:
  - Phare: 7 files / 657 LOC.
  - Laravel `Illuminate\Events\*`: 5 files / ~1,520 LOC.
  - Phare ships ~43 % of Laravel's events LOC, but the missing
    LOC is concentrated in the queued-listener pipeline (`CallQueuedListener`
    + `QueuedClosure` + `InvokeQueuedClosure`) — i.e. the bridge
    to Queue. Without D01 fix, this gap is moot; with D01 fixed,
    this becomes the next blocker.

- Standard Laravel events present?:
  - `Auth/Events/` — Phare ships 6: `Attempting/Authenticated/
    Failed/Login/Logout/Validated`. Laravel ships 13: above 6 +
    `CurrentDeviceLogout/Lockout/OtherDeviceLogout/PasswordReset/
    Registered/Verified`. 7 Auth events missing.
  - `Database/Events/` — Phare ships 5: `ConnectionEvent/
    TransactionBeginning/Committing/Committed/RolledBack`.
    Laravel ships 13 (`ConnectionEstablished/DatabaseBusy/
    DatabaseRefreshed/MigrationsStarted/MigrationsEnded/QueryExecuted/
    SchemaDumped/SchemaLoaded/StatementPrepared/Transaction*4`).
    8 Database events missing.
  - **MISSING wholesale event namespaces:**
    `Cache/Events/*` (10 events: `CacheHit/CacheMissed/KeyForgotten/
    KeyWritten/...`), `Mail/Events/*` (2 events: `MessageSending/Sent`),
    `Notifications/Events/*` (4 events: `BroadcastNotificationCreated/
    NotificationFailed/NotificationSending/Sent`), `Queue/Events/*`
    (10 events: per D01 audit), `Routing/Events/*` (2 events:
    `RouteMatched/Routing`), `Console/Events/*` (4 events:
    `ArtisanStarting/CommandFinished/CommandStarting/ScheduledTask*`),
    `Foundation/Events/*` — Phare ships 3 here
    (`ApplicationBooted/ApplicationBooting/RequestHandled`), Laravel
    ships ~12 (`DiagnosingHealth/Locale*/MaintenanceModeDisabled/
    MaintenanceModeEnabled/PublishingStubs/VendorTagPublished/...`).

- Impact / port-effort:
  - Effort tier: **M-L** (smaller than D01 Queue's XL — Dispatcher
    core is mostly here, the missing pieces are well-bounded).
  - Risk tier: **CRITICAL** — three open behavioural defects:
    (1) `ShouldQueue` listeners run sync (silent failure mode
    indistinguishable from working in dev, breaks at scale);
    (2) `ShouldBroadcast` events skip broadcast (silent gap);
    (3) dual-stack `events` vs `eventsManager` ambiguity.
  - Blocking dependencies:
    * D01 Queue (must be real first — wiring `setQueueResolver` to
      fake drivers buys nothing).
    * D03 Broadcasting (must exist for `ShouldBroadcast` routing).
    * D04 Notifications + D05 Mail (both fire events through `events`
      binding; current default works but event names diverge).
  - **Layer-collapse risk** in `HasEvents` — the trait does its own
    `Phalcon\Di\Di::getDefault()->getShared('events')` resolution,
    bypassing the dispatcher contract. A port must NOT touch
    `HasEvents` without also re-routing through Container DI
    (otherwise the Phalcon-Di leak persists transitively).

- Recommended port sequence:
  (1) Add `Phare\Events\Contracts\ShouldHandleEventsAfterCommit`
      marker (parity with Laravel; zero behaviour).
  (2) Add `setQueueResolver(callable): $this` + `resolveQueue()` to
      `Phare\Events\Dispatcher` — no behaviour until step 3.
  (3) Add `handlerShouldBeQueued/createQueuedHandlerCallable/
      handlerWantsToBeQueued/queueHandler/createListenerAndJob/
      propagateListenerOptions` (the queued-listener pipeline).
      Depends on D01 having real drivers.
  (4) Add `CallQueuedListener` + `InvokeQueuedClosure` +
      `QueuedClosure` siblings; rework `listen()` to honour
      `QueuedClosure` instances and return the chainable
      `Collection`.
  (5) Add `broadcastEvent/shouldBroadcast/broadcastWhen` —
      depends on D03 ship.
  (6) Decommission `Phalcon\Events\Manager` binding from
      `EventsManagerProvider`/`DispatcherProvider`; route all
      callsites through `Phare\Events\Dispatcher`. Update
      `HasEvents` to resolve via `Container` rather than
      `Phalcon\Di\Di::getDefault()`.
  (7) Rename transaction-manager binding from `dbManager` to
      `db.transactions` (Laravel parity); update existing callsites.
  (8) Move `dispatchIf/dispatchUnless` off the dispatcher and onto
      a `Dispatchable` trait (matches Laravel's location).
  (9) Add a `NullDispatcher` decorator (test-time silence).
  (10) Backfill missing Auth/Database/Foundation event classes.

- Learnings — for future iterations:
  - **Marker-contract-without-reader pattern** (NEW US-S01 defect class):
    `ShouldBroadcast` is defined under `Phare\Events\Contracts\` but
    NO code in `src/Phare/` reads it. Audit query:
    `grep -rn 'instanceof.*ShouldBroadcast' src/Phare/` returns
    zero (well, the contract is referenced in `Dispatcher.php`
    `use` but never type-checked). Distinct from the
    ORPHAN-SUBSYSTEM defect (no callers anywhere) — here the
    contract exists but the runtime never honours it. Promote to
    US-S01.
  - **Silent-config-dropthrough sub-pattern: NAMING-DIVERGENCE.**
    `dbManager` vs `db.transactions` is identical to A04's
    `cache.store` vs `cache.repository` (or pick the right A-area
    example) — a binding-name mismatch that means the Laravel
    convention silently fails. Document distinct sub-pattern.
  - **Dual-stack 3rd instance: a recurring shape.** A07 (Cache:
    Phalcon\Cache\* vs Phare\Cache), C04 (Encryption: Phalcon\Crypt
    vs Phare\Encryption), now D02 (Events:
    Phalcon\Events\Manager vs Phare\Events\Dispatcher). Project-
    wide query: `grep -rln 'Phalcon\\\\.*Manager\|Phalcon\\\\.*\\\\Dispatcher' src/Phare/Providers/`
    will find more.
  - **Trait-resolves-via-Container-of-its-own pattern.**
    `HasEvents` resolves the dispatcher via
    `Phalcon\Di\Di::getDefault()` instead of accepting an injected
    one. Same pattern likely in B-area concern traits
    (`HasTimestamps`/`HasGlobalScopes`/`HasUuids`). Audit query:
    `grep -rn 'Di::getDefault\|getDi()' src/Phare/Eloquent/Concerns/`.
  - **Auto-wire-via-on-prefix-method pattern in `subscribe()`.**
    Phare's fallback `discoverSubscriberMethods()` auto-registers
    every `on<Event>` method when `subscribe()` returns non-array.
    This is a Phalcon-style convention (events manager attaches
    via on-prefix). Worth flagging as a UX divergence from Laravel
    — subscribers in downstream Phare apps may rely on it; a port
    to Laravel-shape will silently regress them.
  - **`fnmatch` vs `Str::is` matters when patterns contain `\\`.**
    Phare's `eventMatches` will incorrectly match on backslash
    boundaries; Laravel's `Str::is` escapes them. Edge case but
    real for namespaced event-class patterns
    (`App\Events\*` etc.).
  - **`getRawListeners` is the lone Phare ADDITION above
    Laravel's surface.** Both have it (it IS in Laravel 13 — confirmed
    line 896). Parity. No anomaly.
  - **Effort-tier-by-blocker-pattern.** D02 is M-L (the core is
    here) but its IMPACT is gated by D01 (queue real-ness) and D03
    (broadcasting). The "size of code change" axis underestimates
    the "size of unblock" axis — track both per US-S01 entry.

---

### Broadcasting

- Current — Phare:
  Fourteen files in `src/Phare/Broadcasting/` (714 LOC):
  - `BroadcastManager.php` (160 LOC, extends `Phare\Support\Manager`).
    8 public methods:
    `__construct(Container)`,
    `connection(?string): Broadcaster` (delegates to `driver()`),
    `extend(string, Closure): static`,
    `getDefaultDriver(): string`,
    `setDefaultDriver(string): void`,
    `purge(?string): void`,
    `queue(mixed $event): void`,
    plus 5 protected `create*Driver` methods + `getConfig`. Drivers
    registered: `pusher`, `redis`, `log`, `null` (NO `ably`).
  - `BroadcastServiceProvider.php` (23 LOC) — registers
    `'broadcast.manager'` AND alias `'broadcast'` (back-compat).
    IMPORTS `Phalcon\Di\DiInterface` + `Phalcon\Di\ServiceProviderInterface`.
  - `Broadcasters/Broadcaster.php` (123 LOC, abstract).
    Public surface: `auth(mixed)`, `validAuthenticationResponse(mixed,
    mixed)`, `broadcast(array, string, array)`, `channel(string,
    callable)`, `resolveBinding(mixed, string): mixed`,
    `resolveImplicitBindingIfPossible(string, mixed): mixed`. Plus
    protected: `normalizeChannelName/isGuardedChannel/retrieveUser/
    retrieveChannelOptions/verifyUserCanAccessChannel/extractParameters/
    extractChannelKeys/formatChannels`.
  - `Broadcasters/PusherBroadcaster.php` (104 LOC).
  - `Broadcasters/RedisBroadcaster.php` (78 LOC) — accepts EITHER
    `\Redis` (phpredis) or `Predis\Client`; runtime branches via
    `instanceof`.
  - `Broadcasters/LogBroadcaster.php` (36 LOC) — uses
    `Psr\Log\LoggerInterface`.
  - `Broadcasters/NullBroadcaster.php` (21 LOC).
  - `BroadcastEvent.php` (58 LOC, abstract — implements
    `Phare\Events\Contracts\ShouldBroadcast`). 9 instance methods
    incl. `broadcastQueue(): ?string`, `broadcastConnection(): ?string`,
    `broadcastVia(): array` (default `['pusher']`),
    `dontBroadcastToCurrentUser/toOthers`.
  - `BroadcastException.php` (10 LOC, `extends \Exception`).
  - `PendingBroadcast.php` (41 LOC) — constructed with a `BroadcastManager`
    and `ShouldBroadcast`; `__destruct()` calls
    `$this->broadcaster->queue($this->event)`. Pattern: garbage-collection
    triggers the broadcast.
  - `Channel.php` (18 LOC) — `public string $name; __toString()`.
  - `PrivateChannel.php` (11 LOC) — prepends `private-`.
  - `PresenceChannel.php` (11 LOC) — prepends `presence-`.
  - `InteractsWithSockets.php` (20 LOC trait) — `dontBroadcastToCurrentUser/
    toOthers`, sets `$socket` from `X-Socket-ID` header.

  Adjacent:
  - `src/Phare/Events/Contracts/ShouldBroadcast.php` — 4-method
    contract under `Phare\Events\Contracts\` (NOT
    `Phare\Broadcasting\Contracts\`). Phare collapses Laravel's two
    namespaces.
  - `src/Phare/Support/Facades/Broadcast.php` — facade accessor
    `'broadcast'`.
  - `src/Phare/Support/helpers.php` line 624-639 — global
    `broadcast()` helper.
  - `config/broadcasting.php` — config skeleton: `default => env('BROADCAST_DRIVER', 'null')`,
    connections `pusher/ably/redis/log/null` declared (even though
    Phare has NO `ably` driver — silent config-template mismatch).

- Reference — Laravel 13 `Illuminate\Broadcasting\*`
  + `Illuminate\Contracts\Broadcasting\*`:
  - `BroadcastManager.php` (496 LOC, 22 public methods). Implements
    `Illuminate\Contracts\Broadcasting\Factory`. Public:
    `__construct/routes/userRoutes/channelRoutes/socket/on/private/
    presence/event/queue/connection/driver/pusher/ably/getDefaultDriver/
    setDefaultDriver/purge/extend/getApplication/setApplication/
    forgetDrivers/__call`.
  - `Broadcasters/Broadcaster.php` (~390 LOC, implements
    `Contracts\Broadcasting\Broadcaster`). 4 public methods +
    ~15 protected. Public: `resolveAuthenticatedUser/
    resolveAuthenticatedUserUsing/channel/getChannels`. PLUS abstracts
    inherited from contract: `auth/validAuthenticationResponse/broadcast`.
  - `Broadcasters/AblyBroadcaster.php` (NEW driver Phare lacks).
  - `Broadcasters/{Pusher,Redis,Log,Null}Broadcaster.php`.
  - `Broadcasters/UsePusherChannelConventions.php` (trait).
  - `BroadcastController.php` (47 LOC) — controller for
    `/broadcasting/auth` + `/broadcasting/user-auth`.
  - `BroadcastEvent.php` (210 LOC) — `ShouldQueue` job; auto-fans-out
    across `broadcastConnections()` array.
  - `AnonymousEvent.php` (149 LOC) — fluent on-the-fly broadcast
    event.
  - `UniqueBroadcastEvent.php` — `ShouldBeUnique` variant.
  - `PendingBroadcast.php` (76 LOC) — constructed with a
    `Dispatcher` (events), `__destruct` calls
    `$this->events->dispatch(...)`.
  - `FakePendingBroadcast.php` — test double.
  - `Channel.php` (37 LOC) — `Stringable`, accepts
    `HasBroadcastChannel|string`.
  - `PrivateChannel.php`, `PresenceChannel.php`,
    `EncryptedPrivateChannel.php`.
  - `BroadcastServiceProvider.php` (29 LOC) — registers
    `'Illuminate\Broadcasting\BroadcastManager'` AND `Factory`
    contract.
  - `InteractsWithSockets.php`, `InteractsWithBroadcasting.php`.
  - `Contracts/Broadcasting/` — 7 contracts: `Broadcaster`, `Factory`,
    `HasBroadcastChannel`, `ShouldBroadcast`, `ShouldBroadcastNow`,
    `ShouldBeUnique`, `ShouldRescue`.

- Public-API diff:
  - **BroadcastManager:**
    - `connection($name = null)` — Laravel: returns `$this->driver($name)`
      after lazy-resolution from `$this->drivers[]` keyed by name
      AND on a `Contracts\Broadcasting\Broadcaster` return type
      (signature via `Factory` contract). Phare: same delegation
      pattern (also returns `Broadcaster`). Parity.
    - `driver($name = null)` — Laravel: explicit, returns
      `$this->drivers[$name] ??= $this->resolve($name)`. Phare:
      inherits from `Phare\Support\Manager` (not redeclared in
      `BroadcastManager`). Parity at API level; differs in cache
      mechanism.
    - `routes(?array $attributes = null)` — **MISSING from Phare.**
      No `BroadcastController` exists, no `/broadcasting/auth`
      route registered. Critical gap: clients using Pusher/Echo
      need this endpoint for channel auth. Auth flow cannot work
      without it.
    - `userRoutes(?array $attributes = null)` — **MISSING.**
    - `channelRoutes(?array $attributes = null)` — **MISSING.**
    - `socket($request = null): ?string` — **MISSING from Phare.**
      Laravel: reads `X-Socket-ID` header. Phare's
      `InteractsWithSockets` trait reads the same header but only
      on the event side — no manager-side accessor.
    - `on(Channel|string|array $channels): AnonymousEvent` —
      **MISSING.**
    - `private(string $channel): AnonymousEvent` — **MISSING.**
    - `presence(string $channel): AnonymousEvent` — **MISSING.**
    - `event($event = null): PendingBroadcast` — **MISSING.**
      Phare's global `broadcast()` helper constructs
      `PendingBroadcast` directly. The manager itself has no such
      entry point.
    - `queue(mixed $event): void` — present in BOTH but
      BEHAVIORAL DIVERGENCE:
      * Laravel: routes through `BusDispatcherContract::dispatchNow(
        new BroadcastEvent(clone $event))` for `ShouldBroadcastNow`
        events, queues `BroadcastEvent` job otherwise. Pulls
        `broadcastQueue/broadcastConnection/broadcastConnections`
        from event. Handles `ShouldRescue/ShouldBeUnique`. Calls
        `getAttributeValue` for `#[Connection]`/`#[Queue]` attributes.
      * Phare: synchronously iterates `$event->broadcastVia()`
        (default `['pusher']` — hard-coded driver default in
        `BroadcastEvent::broadcastVia()`), then `connection($driver)
        ->broadcast(...)`. **NO queueing.** **NO ShouldBroadcastNow
        branch.** **NO ShouldRescue.** **NO ShouldBeUnique lock.**
        **NO Bus dispatcher.** A `ShouldBroadcast` event is
        broadcast synchronously regardless. **The "queue" verb is
        a lie in Phare.** NEW US-S01 defect class:
        METHOD-NAME-LIES.
    - `pusher(array $config): Pusher` — **MISSING from Phare** as
      a public manager method.
    - `ably(array $config)` — **MISSING** (no Ably driver at all).
    - `getDefaultDriver()` — parity.
    - `setDefaultDriver($name)` — parity.
    - `purge($name = null)` — parity.
    - `extend($driver, Closure)` — parity.
    - `getApplication/setApplication` — **MISSING from Phare.**
    - `forgetDrivers()` — **MISSING from Phare.**
    - `__call($method, $parameters)` — Laravel forwards to default
      driver via `@mixin Broadcaster`. **MISSING from Phare** at
      BroadcastManager level.
  - **Broadcaster (abstract):**
    - `auth(mixed)`, `validAuthenticationResponse(mixed, mixed)`,
      `broadcast(array, string, array): void` — parity.
    - `channel(string, callable)` — Phare: `channel($name, callable):
      void`, stores `$this->channels[$name] = $callback`. Laravel:
      `channel($channel, $callback, $options = [])` — third arg
      `$options` for `guards`. **Silent-arg-drop 10th instance.**
      The `retrieveUser` method internally reads `$options['guards']`
      from an array that the public API has no way to populate.
    - `resolveAuthenticatedUser` — **MISSING from Phare.**
    - `resolveAuthenticatedUserUsing(Closure)` — **MISSING.**
    - `getChannels(): Collection` — **MISSING.** Phare exposes no
      read accessor for registered channels.
    - `resolveBinding/resolveImplicitBindingIfPossible` — Phare
      ADDS these as PUBLIC methods (line 65, 119 of
      `Broadcaster.php`). Laravel keeps `resolveBinding` PROTECTED
      and `resolveImplicitBindingIfPossible` PROTECTED. Same-name/
      different-visibility shape divergence.
  - **Broadcaster::verifyUserCanAccessChannel** — behavioural
    divergence:
    * Laravel: walks `$this->channels` looking for the FIRST PATTERN
      that matches via `channelNameMatchesPattern()` (regex-based
      with `{param}` extraction), extracts auth parameters, runs
      the callback with `($user, ...$parameters)`. Wildcard channels
      (`order.{order_id}`) work.
    * Phare: looks up `$this->channels[$channelName]` directly by
      EXACT-MATCH KEY. **NO wildcard/parameter-extraction.** A
      channel like `order.{id}` registered in Phare can ONLY be
      authed when the inbound channel name matches EXACTLY
      "order.{id}" — useless. Wildcard channel auth is BROKEN.
      Critical gap.
  - **PendingBroadcast:**
    - Laravel: ctor `(Dispatcher $events, $event)`, `__destruct`
      calls `$this->events->dispatch($this->event)`. Routes the
      event through the events dispatcher, which then routes
      through `Dispatcher::broadcastEvent → BroadcastManager::queue`.
    - Phare: ctor `(BroadcastManager $broadcaster, ShouldBroadcast
      $event)`, `__destruct` calls `$this->broadcaster->queue($this->event)`.
      Skips the events dispatcher; broadcasts directly. Pairs with
      D02's missing `broadcastEvent` in Dispatcher — Phare bypasses
      the dispatcher entirely by wiring `PendingBroadcast →
      BroadcastManager` directly. Architecturally divergent — the
      events dispatcher is not in the broadcast path.

- Wholesale missing from Phare:
  - `Illuminate\Contracts\Broadcasting\Factory` — no Phare
    counterpart. `BroadcastManager` does not implement a factory
    contract.
  - `Illuminate\Contracts\Broadcasting\Broadcaster` — no contract;
    abstract class only.
  - `Illuminate\Contracts\Broadcasting\HasBroadcastChannel` —
    MISSING.
  - `Illuminate\Contracts\Broadcasting\ShouldBroadcastNow` —
    MISSING (no sync-now branch).
  - `Illuminate\Contracts\Broadcasting\ShouldBeUnique` — MISSING.
  - `Illuminate\Contracts\Broadcasting\ShouldRescue` — MISSING.
  - `AblyBroadcaster` — MISSING (Laravel ships it; Phare config
    references it; silent config promise).
  - `AnonymousEvent`, `UniqueBroadcastEvent`, `BroadcastController`,
    `EncryptedPrivateChannel`, `FakePendingBroadcast`,
    `InteractsWithBroadcasting`, `UsePusherChannelConventions`
    (trait) — ALL MISSING.

- No-contracts streak — 14th:
  - `Phare\Broadcasting\Contracts\` does NOT EXIST. Phare collapses
    the contracts namespace entirely, placing `ShouldBroadcast`
    under `Phare\Events\Contracts\` (cross-subsystem placement) and
    publishing NO `Broadcaster`/`Factory` contract.

- Wrapper-Rule (§2) leaks via `src/Phare/Broadcasting/`:
  - `src/Phare/Broadcasting/BroadcastServiceProvider.php:5-6`
    imports `Phalcon\Di\DiInterface` and
    `Phalcon\Di\ServiceProviderInterface`. 5th occurrence of the
    subsystem-namespace leak class (C01/C07/D01/D02/D03).
  - No `Phalcon\` imports in any of the 13 other files. Leak
    confined to provider.

- Same-name / different-shape running list updates:
  - 19 — `resolveBinding` — Phare PUBLIC, Laravel PROTECTED.
  - 20 — `resolveImplicitBindingIfPossible` — Phare PUBLIC 2-arg,
    Laravel PROTECTED 3-arg (adds `$callbackParameters`).
  - 21 — `channel(string, callable): void` Phare vs
    `channel($channel, $callback, $options = []): $this` Laravel —
    arity drop + return-type drop (not chainable).

- Silent-arg-drop 10th instance:
  - `Broadcaster::channel(string, callable)` drops the `$options`
    array.

- Silent-config-dropthrough 10th instance + NEW
  sub-pattern: **CONFIG-PROMISES-UNSHIPPED-DRIVER.**
  - `config/broadcasting.php` declares an `'ably'` connection block
    with `'driver' => 'ably'`. `BroadcastManager::createDriver()`
    will call `createAblyDriver()` which does not exist → throws
    `InvalidArgumentException("Driver [ably] is not supported.")`.

- Dual-stack check:
  - Phalcon has no broadcasting subsystem — no dual-stack risk.
    Single-stack subsystem.

- File-count ratio:
  - Phare: 14 files / 714 LOC.
  - Laravel `Illuminate\Broadcasting\*`: ~18 files / ~3,000 LOC.
  - Plus 7 contracts under `Illuminate\Contracts\Broadcasting\*`.
  - Phare ships ~24 % of Laravel's broadcasting LOC.

- Impact / port-effort:
  - Effort tier: **L** (between D02's M-L and D01's XL).
  - Risk tier: **HIGH** — three open behaviour defects:
    (1) `queue()` is sync (no real queueing despite name);
    (2) wildcard channel auth broken (regex never runs);
    (3) `BroadcastController`/routes absent — Pusher/Echo auth flow
        cannot work.
  - Blocking dependencies:
    * D01 Queue real-ness (for `queue()` to mean queue).
    * D02 Events dispatcher must route `ShouldBroadcast` for the
      Laravel-shape `PendingBroadcast → Dispatcher::dispatch →
      broadcastEvent` chain to function.
    * Bus dispatcher (NOT in scope — Phare has no `Bus\Dispatcher`
      at all). Confirms the D01 finding: `Bus\*` is wholesale
      missing.

- Learnings — for future iterations:
  - **NEW US-S01 defect class: METHOD-NAME-LIES.** Distinct from
    FAKE-DRIVER, STUB-DEFECT, ORPHAN-SUBSYSTEM, LAYER-COLLAPSE,
    MARKER-CONTRACT-WITHOUT-READER. Pattern: method name implies
    async behaviour but implementation is sync.
    `BroadcastManager::queue()` is canonical. Audit query: any
    method named `queue/enqueue/push/dispatch/defer/schedule/later`
    that does not actually defer execution. Likely cross-cutting;
    expect more in D04 Notifications + D05 Mail.
  - **NEW silent-config sub-pattern: CONFIG-PROMISES-UNSHIPPED-DRIVER.**
    Same shape likely in `config/queue.php` (beanstalkd was missing
    per D01) and `config/cache.php` (TBD A-area). Build a per-config
    audit table for US-S01: every "driver"/"connection" reference
    in `config/*.php` cross-checked against the manager's
    `create*Driver` methods.
  - **NEW pattern: NAMESPACE-COLLAPSE.** Distinct from layer-collapse
    (which merges stacked layers). Here Phare merges two PARALLEL
    namespaces: `Illuminate\Contracts\Broadcasting\ShouldBroadcast`
    + `Illuminate\Contracts\Events\ShouldDispatchAfterCommit` →
    `Phare\Events\Contracts\*`. Audit query:
    `find src/Phare -type d -name Contracts -exec ls {} \;` then
    compare against Laravel's `Illuminate\Contracts\` substructure.
  - **NEW pattern: WILDCARD-AS-EXACT-KEY.** Phare's
    `Broadcaster::resolveBinding` does
    `$this->channels[$normalizedName]` (exact-key array lookup).
    Laravel walks the array calling
    `channelNameMatchesPattern($channel, $pattern)`. Phare WORKS
    for non-parameterized channels but SILENTLY FAILS for
    `{param}` channels. Same shape likely in router middleware
    matching — anywhere Laravel uses pattern-walk-with-extraction,
    Phare may use exact-key-lookup.
  - **Destructor-triggers-action pattern.** Both Phare and Laravel
    use `__destruct()` on `PendingBroadcast` to flush. Testability
    issue: in PHP, `__destruct` fires non-deterministically during
    GC unless the object goes out of scope. Any test that captures
    a `PendingBroadcast` to a variable risks blocking the broadcast
    until end-of-scope.
  - **File-count-ratio as defect proxy.** D01 → 21 % (XL).
    D02 → 43 % (M-L). D03 → 24 % (L). Phare/Laravel LOC ratio
    correlates with — but does not determine — effort tier. Track
    BOTH axes per audit.
  - **Three new defect classes from D03.** METHOD-NAME-LIES,
    CONFIG-PROMISES-UNSHIPPED-DRIVER, NAMESPACE-COLLAPSE +
    carry-forward WILDCARD-AS-EXACT-KEY. Pattern of "3 new defect
    classes per Area-D audit" continues (D01: 3 / D02: 3 / D03: 3).

---

### Notifications

- Current — Phare:
  Thirteen files in `src/Phare/Notifications/` (1,517 LOC):
  - `Notification.php` (164 LOC, abstract). 13 public methods +
    1 protected. `__construct()` auto-generates ID via
    `uniqid('notification_', true)` (predictable — same JOB-ID
    weakness as D01 Queue's `Job::generateId()`). Abstract:
    `via(mixed $notifiable): array`. Concrete defaults:
    `toMail/toDatabase/toArray/toSms/toSlack`, `shouldSend`,
    `with(array): static`, `getId/setId`, `markAsRead/markAsUnread/
    isRead/getReadAt/setReadAt`, `getData/getCreatedAt`. Carries
    its OWN read/unread state — odd shape for a sender-side class.
  - `Notifiable.php` (101 LOC, trait). 8 public methods:
    `notifications/readNotifications/unreadNotifications/notify/
    notifyNow/routeNotificationFor/routeNotificationForMail/
    routeNotificationForSms/routeNotificationForSlack`. **Critical
    finding:** `notifications()` returns `[]` with the verbatim
    comment `// In a real implementation, this would return
    notifications from the database`. STUB-DEFECT (no-op return).
    `readNotifications/unreadNotifications` filter over the empty
    list — both will always return `[]`.
  - `NotificationManager.php` (164 LOC). 10 public methods +
    3 protected. Public: `__construct(ChannelManager, ?EventDispatcher)`,
    `send/sendNow/getChannelManager/getSentNotifications/
    clearSentNotifications/channel/getDefaultDriver/setDefaultDriver`.
    `sendNow` body verbatim: `// For now, sendNow is the same as send
    since we're not implementing queued notifications / $this->send(...)`.
    **STUB-DEFECT 2nd instance in this subsystem.** Tracks all
    sent notifications in `$sentNotifications[]` array.
  - `NotificationServiceProvider.php` (51 LOC) — registers
    `'notification.channel'` (`ChannelManager`) and `'notification'`
    (`NotificationManager`); inside `boot()` declares two global
    functions (`notify()`, `notification()`) with `function_exists`
    guards. **HELPER-INSIDE-BOOT pattern 2nd instance** (D01 had
    the same in `QueueServiceProvider::boot()`).
  - `Channels/ChannelInterface.php` (13 LOC) — single method
    `send(mixed $notifiable, Notification $notification): void`.
  - `Channels/ChannelManager.php` (108 LOC). Does NOT extend
    `Phare\Support\Manager` (unlike Queue/Cache/Broadcast managers).
    Custom driver-registry: `customDrivers['mail'|'database'|'sms'|
    'slack']` Closures populated in ctor. Public: `__construct
    (array $config = [])`, `driver(?string): ChannelInterface`,
    `extend(string, \Closure): static`, `getDefaultDriver/
    setDefaultDriver`, `getDrivers/clearDrivers`.
  - `Channels/MailChannel.php` (79 LOC). Ctor `(?Mailer $mailer =
    null)`. `send()` body line 30-32: `// For testing purposes,
    we'll skip actual sending if no mailer is configured / return;`
    plus the conditional `if (!$this->mailer)` — channel
    silently no-ops when the mailer is null. STUB-DEFECT 3rd
    instance.
  - `Channels/DatabaseChannel.php` (74 LOC). Stores notifications
    in `protected array $storedNotifications = []`. Source comment
    line 18-19 verbatim: `// In a real implementation, this would
    save to database / // For now, we'll store in memory for testing`.
    **FAKE-DRIVER 4th instance** (after D01 DatabaseQueue, RedisQueue,
    plus this).
  - `Channels/SmsChannel.php` (81 LOC). Stores in
    `protected array $sentMessages = []`. Source comment line 29:
    `// For testing purposes, we'll store the message instead of
    actually sending`. **FAKE-DRIVER 5th instance.**
  - `Channels/SlackChannel.php` (72 LOC). Same shape:
    `protected array $sentMessages = []`. Source comment line 29:
    `// For testing purposes, we'll store the message instead of
    actually sending`. **FAKE-DRIVER 6th instance.**
  - `Messages/MailMessage.php` (354 LOC). 26+ fluent builder
    methods (`subject/greeting/line/lines/action/level/success/
    error/to/cc/bcc/replyTo/attach/view`) + 17 read accessors
    (`getSubject/getGreeting/...`) + 3 `has*` predicates +
    `toMailable(string $to, mixed $notifiable): Mailable`. The
    `toMailable` factory anonymously extends `Phare\Mail\Mailable`
    — pairs with D05 audit.
  - `Messages/SlackMessage.php` (175 LOC).
  - `Messages/SmsMessage.php` (82 LOC).

- Reference — Laravel 13 `Illuminate\Notifications\*`:
  ~21 files in the root namespace + 3 channels + 6 messages +
  4 events + 1 console command. Highlights:
  - `ChannelManager.php` (182 LOC) extends `Illuminate\Support\Manager`,
    implements `Contracts\Notifications\Dispatcher` AND
    `Contracts\Notifications\Factory`. Public: `send/sendNow/
    channel/getDefaultDriver/deliversVia/deliverVia/locale` + 3
    protected `create*Driver` (database/broadcast/mail). Composes
    with `NotificationSender`. Inherits `extend/driver/createDriver`
    from base `Manager`.
  - `NotificationSender.php` (391 LOC) — the actual send pipeline.
    Public: `__construct/send/sendNow`. Protected: `preferredLocale/
    sendToNotifiable/shouldSendNotification/queueNotification/
    formatNotifiables/withLocale`. Routes `ShouldQueue` notifications
    through Bus, dispatches `NotificationSending/NotificationSent/
    NotificationFailed` events.
  - `SendQueuedNotifications.php` — the queue-side wrapper job
    (parity with Events' `CallQueuedListener`).
  - `Notification.php` — sender-side base, has `id`, `locale`,
    `broadcastOn`, `locale($locale): static`. **NO** read/unread
    state — that lives on `DatabaseNotification` (the stored model).
  - `DatabaseNotification.php` (~90 LOC) — Eloquent model for the
    `notifications` table. Has `markAsRead/markAsUnread/read/unread`
    scopes. THIS is where the read state lives in Laravel.
  - `DatabaseNotificationCollection.php` — collection with
    `markAsRead/markAsUnread` bulk methods.
  - `HasDatabaseNotifications.php` — trait that auto-wires the
    `notifications()`/`unreadNotifications()`/`readNotifications()`
    Eloquent relations.
  - `Notifiable.php` — composes `RoutesNotifications +
    HasDatabaseNotifications`.
  - `RoutesNotifications.php` (52 LOC trait) — `notify($instance)`
    delegates to `app(Dispatcher::class)->send($this, $instance)`.
    `routeNotificationFor($driver, $notification = null)` uses
    `Str::studly($driver)` for method-name conversion.
  - `AnonymousNotifiable.php` (53 LOC) — fluent on-the-fly notifiable
    (`Notification::route('mail', 'a@b')->notify($notif)`).
  - `Action.php` — value object for action buttons in mail.
  - `Channels/MailChannel.php` (~370 LOC) — wraps `Illuminate\Mail\Mailer`
    + Markdown rendering + Symfony Mailer headers (TagHeader/MetadataHeader).
  - `Channels/BroadcastChannel.php` (~75 LOC) — routes through
    `BroadcastFactory::queue()` + Bus + `BroadcastNotificationCreated`
    event. **MISSING from Phare entirely.**
  - `Channels/DatabaseChannel.php` (69 LOC) — real impl:
    `$notifiable->routeNotificationFor('database', $notification)
    ->create($this->buildPayload(...))`. Uses Eloquent relation
    `create()` — actually persists.
  - `Messages/{MailMessage,SimpleMessage,DatabaseMessage,
    BroadcastMessage}.php` — sender DTOs.
  - `Events/{NotificationSending,NotificationSent,NotificationFailed,
    BroadcastNotificationCreated}.php` — 4 events.
  - `Console/NotificationTableCommand.php` (~35 LOC) — Artisan
    command to generate the `notifications` migration.
  - `Contracts/Notifications/Dispatcher.php` (26 LOC, 2 methods).
  - `Contracts/Notifications/Factory.php` (33 LOC, 3 methods).
  - `Messages/SlackMessage.php` — **MISSING from Laravel core
    notifications.** Laravel removed Slack from core after v9 (now
    in `laravel/slack-notification-channel` package). Phare ships
    it — Phare is on an older Laravel-shape.
  - `Messages/SmsMessage.php` — same: never was in Laravel core
    (always vendor package `vonage/laravel-vonage` or similar).
    Phare ships it inline. NEW pattern: **OUT-OF-CORE-PACKAGE-INLINED**
    — Phare absorbs what Laravel keeps as a first-party package.

- Public-API diff:
  - **NotificationManager:**
    - `send($notifiables, $notification)` — Laravel: routes via
      `NotificationSender::send` which checks `ShouldQueue` and
      forks to `queueNotification`. Phare: iterates notifiables
      and calls `sendToNotifiable` directly. **NO ShouldQueue
      branch.** A `ShouldQueue` notification is sent synchronously.
      Same defect class as D03 Broadcasting's `queue()` (METHOD-
      NAME-LIES — except here `send` is honest about being sync,
      but `sendNow` is the lie: the comment SAYS "sendNow is the
      same as send since we're not implementing queued notifications"
      which inverts the Laravel meaning).
    - `sendNow($notifiables, $notification, ?array $channels = null)` —
      Laravel: 3-arg, third arg overrides channel selection.
      Phare: 2-arg, drops `$channels`. **Silent-arg-drop 11th
      instance.** PLUS the body is a forwarder to `send()` — i.e.
      `send`/`sendNow` are aliases in Phare, but only `sendNow`
      has parity-shaped meaning in Laravel.
    - `channel(string)` — Phare: returns `ChannelInterface` directly.
      Laravel: returns `mixed` (any channel impl). Mostly parity.
    - `getDefaultDriver/setDefaultDriver` — Laravel exposes
      `deliversVia/deliverVia` (alias naming convention).
      Phare uses `get`/`setDefaultDriver` — divergence in method
      naming.
    - `getChannelManager(): ChannelManager` — **Phare ADDITION.**
      Laravel does not expose the manager separately because the
      manager IS the channel registry. Phare has 2-layer:
      `NotificationManager` wraps `ChannelManager`. Architectural
      divergence — Laravel's `ChannelManager extends Manager`
      directly handles everything. Phare splits in two.
    - `getSentNotifications/clearSentNotifications` — **Phare
      ADDITION** (test scaffolding leaking into production class).
      Laravel uses `Notification::fake()` Facade for the same
      purpose; the production manager has no test accessors. NEW
      pattern: **TEST-SCAFFOLDING-IN-PRODUCTION-CLASS** (also
      seen in `DatabaseChannel::getStoredNotifications/
      clearStoredNotifications`, `SmsChannel::getSentMessages/
      clearSentMessages`, `SlackChannel::getSentMessages/
      clearSentMessages`).
    - `locale($locale)` — **MISSING from Phare.** No per-send
      locale override.
  - **ChannelManager:**
    - Inheritance: Laravel `ChannelManager extends
      Illuminate\Support\Manager` (full driver-registry pattern).
      Phare `ChannelManager` does NOT extend `Phare\Support\Manager`
      — it re-implements the driver-resolution logic inline
      (Closure-based `customDrivers` array). **NEW pattern:
      DUPLICATED-DRIVER-RESOLUTION-WITHOUT-MANAGER-BASE.**
    - `send/sendNow` — **MISSING from Phare's ChannelManager**
      (these live on `NotificationManager` in Phare; in Laravel
      they live on `ChannelManager` itself via the
      `Dispatcher`/`Factory` contracts).
    - `channel($name)` — Phare: `driver(?string)`. Laravel:
      `channel($name)`. Method-name divergence.
    - `createBroadcastDriver()` — **MISSING from Phare.** Phare
      has NO `broadcast` channel for notifications. A notification
      that returns `['broadcast']` from `via()` will throw
      `InvalidArgumentException("Driver [broadcast] not supported.")`.
    - `extend($name, \Closure): static` — parity.
    - `locale($locale): $this` — **MISSING.**
    - Contracts: Laravel implements 2 (`Dispatcher` + `Factory`).
      Phare implements 0.
  - **Notification (base class):**
    - `id` field — Laravel: public string. Phare: protected string,
      auto-set in ctor via `uniqid()` (PREDICTABLE). 11th instance
      of UNIQID-AS-IDENTIFIER pattern (after D01 Queue jobId,
      C04 Encryption cipher choice, etc.).
    - `locale` field — **MISSING from Phare.**
    - `locale($locale): static` method — **MISSING.**
    - `broadcastOn(): array` — **MISSING from Phare.** Pairs with
      missing broadcast channel.
    - `markAsRead/markAsUnread/isRead/getReadAt/setReadAt` —
      **Phare ADDITION** on the wrong class. Laravel puts these
      on `DatabaseNotification` (the persisted Eloquent model).
      Phare adds them on the sender-side base class. Read state
      lives on a transient sender-side object — meaningless unless
      you persist it elsewhere. **LAYER-COLLAPSE 2nd instance**
      (sender + recipient state merged onto one class). The first
      LAYER-COLLAPSE was D01's `Queue\Job` (user-job base + queue-
      side state merged).
    - `getCreatedAt(): \DateTimeInterface` — Phare addition;
      Laravel has no such getter on the sender side.
    - `via(mixed)` — abstract in both. Parity.
    - `toMail/toDatabase/toArray/toSms/toSlack` defaults — Phare
      ships all five with concrete defaults. Laravel ships
      `toMail/toDatabase/toArray/toBroadcast` (NO `toSms`,
      NO `toSlack`).
    - `with(array): static` — **Phare ADDITION** (data-bag setter
      on the notification itself). Laravel uses constructor injection
      for notification data. Convention divergence.
  - **Notifiable trait:**
    - `notifications/readNotifications/unreadNotifications` —
      STUB-DEFECT in Phare (returns `[]`). Laravel composes
      `HasDatabaseNotifications` which provides Eloquent
      `MorphMany` to the `notifications` table.
    - `notify(Notification)` — Phare: void return, calls
      `app('notification')->send($this, $notification)`. Laravel:
      `notify($instance)` (1-arg, untyped), calls
      `app(Dispatcher::class)->send($this, $instance)`. Type
      tightening in Phare BREAKS Laravel-shape — any caller
      passing a non-`Notification` (e.g. a class that just walks
      the contract) won't typecheck.
    - `notifyNow(Notification)` — Phare 1-arg. Laravel:
      `notifyNow($instance, ?array $channels = null)` 2-arg.
      Silent-arg-drop 12th instance.
    - `routeNotificationFor($driver, $notification = null)` —
      Phare: 1-arg (drops `$notification`). 13th silent-arg-drop.
      Phare uses `ucfirst($driver)` for method name; Laravel uses
      `Str::studly($driver)` (handles snake_case → CamelCase,
      not just first-letter upper). Snake-case driver names break
      in Phare: `routeNotificationFor('vonage_sms')` →
      `routeNotificationForVonage_sms` (PHP method name with
      underscore) — works but ugly. Laravel:
      `routeNotificationForVonageSms` (correct).
    - `routeNotificationForMail/routeNotificationForSms/
      routeNotificationForSlack` — **Phare ADDITION** (hard-coded
      methods on the trait for three specific channels). Laravel
      does NOT pre-declare these — they're discovered via the
      generic `routeNotificationFor` lookup. Pattern: convention-
      driven discovery vs. hard-coded shims.

- Wholesale missing from Phare:
  - `Illuminate\Contracts\Notifications\Dispatcher` — no Phare
    counterpart.
  - `Illuminate\Contracts\Notifications\Factory` — no Phare
    counterpart.
  - `Illuminate\Notifications\NotificationSender` — Phare folds
    the send-pipeline logic INLINE into `NotificationManager`,
    losing the separate concern. **LAYER-COLLAPSE 3rd instance**
    (sender + manager merged).
  - `Illuminate\Notifications\SendQueuedNotifications` — MISSING
    (queue-side wrapper job).
  - `Illuminate\Notifications\AnonymousNotifiable` — MISSING
    (fluent on-the-fly notifiable; closes the
    `Notification::route('mail', '...')->notify(...)` API).
  - `Illuminate\Notifications\DatabaseNotification` — MISSING
    (Eloquent model for stored notifications). Pairs with
    Phare's `Notifiable::notifications()` stub.
  - `Illuminate\Notifications\DatabaseNotificationCollection` —
    MISSING.
  - `Illuminate\Notifications\HasDatabaseNotifications` — MISSING.
  - `Illuminate\Notifications\RoutesNotifications` — Phare's
    `Notifiable` trait collapses this in (no separation).
  - `Illuminate\Notifications\Channels\BroadcastChannel` —
    MISSING. Channels `via()` returning `['broadcast']` will throw.
  - `Illuminate\Notifications\Messages\{BroadcastMessage,
    DatabaseMessage,SimpleMessage}` — MISSING. Phare's
    `toDatabase` returns plain `array` (no DTO).
  - `Illuminate\Notifications\Events\{NotificationSending,
    NotificationSent,NotificationFailed,
    BroadcastNotificationCreated}` — MISSING (4 events). Phare
    fires events but uses STRING names (`'notification.sending'`,
    `'notification.sent'`, `'notification.failed'`) directly via
    the dispatcher's `dispatch(string, array)` overload — not
    typed event objects. Same pattern as Auth/Database events
    (D02 noted this — Phare uses event-as-class for Auth/Database,
    event-as-string here for Notifications). **CONVENTION
    INCONSISTENCY** within the project.
  - `Illuminate\Notifications\Console\NotificationTableCommand` —
    MISSING (artisan migration generator).
  - `Illuminate\Notifications\Action` — MISSING (value object for
    mail action buttons; Phare's `MailMessage::action(text, url)`
    stores raw strings instead).

- No-contracts streak — 15th:
  - `Phare\Notifications\Contracts\` does NOT exist.
  - 2 Laravel contracts unmapped (Dispatcher + Factory).
  - 14th no-contracts subsystem after D03 (Broadcasting). Two in
    a row.

- Wrapper-Rule (§2) leaks:
  - `grep -rn 'Phalcon\\\\' src/Phare/Notifications/` returns
    **zero**. Notifications subsystem is Phalcon-clean. First
    Phalcon-clean D-area subsystem (D01/D02/D03 all had at least
    one Phalcon leak).

- Silent-arg-drop 11-13:
  - 11 — `NotificationManager::sendNow($notifiables, $notification)`
    drops Laravel's `?array $channels = null`.
  - 12 — `Notifiable::notifyNow(Notification)` drops Laravel's
    `?array $channels = null`.
  - 13 — `Notifiable::routeNotificationFor($driver)` drops
    Laravel's `$notification = null`.

- Same-name / different-shape running list updates:
  - 22 — `notify(Notification)` Phare typed-arg / void vs
    Laravel `notify($instance)` untyped.
  - 23 — `channel($name)` Phare on `NotificationManager` vs
    Laravel `channel($name)` on `ChannelManager` (location
    divergence).
  - 24 — `routeNotificationFor` Phare `ucfirst($driver)` vs
    Laravel `Str::studly($driver)` (method-resolution divergence).
  - 25 — `getDefaultDriver`/`setDefaultDriver` Phare vs
    `deliversVia`/`deliverVia` Laravel (naming divergence on
    `ChannelManager`).

- METHOD-NAME-LIES 2nd instance (after D03 `BroadcastManager::queue()`):
  - `NotificationManager::sendNow()` body is literally
    `$this->send($notifiables, $notification)`. The "now" in the
    name implies it should send synchronously RIGHT NOW even if
    `$notification` is `ShouldQueue` — but `send()` itself has
    no queueing branch either, so both go through the same sync
    path. The Laravel semantic distinction (`send` queues if
    `ShouldQueue`, `sendNow` always runs sync) is collapsed
    because Phare has no queueing path at all. METHOD-NAME-LIES
    where TWO methods both lie.

- STUB-DEFECT inventory (running tally):
  - `Notifiable::notifications()` returns `[]` (stub).
  - `MailChannel::send()` returns when no mailer present (stub —
    silent no-op).
  - `NotificationManager::sendNow()` aliases `send` with a comment
    documenting that queueing is not implemented (stub).

- FAKE-DRIVER inventory (running tally):
  - D01: `DatabaseQueue` (`protected array $jobs`).
  - D01: `RedisQueue` (`protected array $queues`).
  - D04: `DatabaseChannel` (`protected array $storedNotifications`).
  - D04: `SmsChannel` (`protected array $sentMessages`).
  - D04: `SlackChannel` (`protected array $sentMessages`).
  - **5 instances confirmed in D-area alone.** Plus pending audits
    for D05 Mail (likely a 6th: MemoryTransport / LogTransport
    posing as drivers?).

- Dual-stack check:
  - Phalcon has no notifications subsystem — single-stack. Second
    D-area task without dual-stack (after D03).

- File-count ratio:
  - Phare: 13 files / 1,517 LOC.
  - Laravel `Illuminate\Notifications\*` + `Illuminate\Contracts\Notifications\*`:
    ~31 files / ~3,400 LOC (incl. NotificationSender 391 LOC,
    SendQueuedNotifications 175 LOC, MailChannel 370 LOC,
    DatabaseNotification, AnonymousNotifiable).
  - Phare ships ~45 % of Laravel's notifications LOC. Larger
    fraction than D03 (24%) but with more fakes per LOC.

- Impact / port-effort:
  - Effort tier: **L** (similar to D03; smaller than D01 XL).
  - Risk tier: **HIGH-CRITICAL** — five open defects:
    (1) FAKE-DRIVER × 3 channels (Database/Sms/Slack — in-memory
        storage) plus MailChannel no-op-when-null;
    (2) STUB `Notifiable::notifications()` always returns `[]` —
        querying read notifications is broken;
    (3) NO broadcast channel — `via() => ['broadcast']` throws;
    (4) NO ShouldQueue handling — async notifications run sync;
    (5) No typed events — `NotificationSending/Sent/Failed` are
        string event-names not classes, callers cannot type-listen.
  - Blocking dependencies:
    * D01 Queue real-ness (for ShouldQueue + SendQueuedNotifications).
    * D02 Events (for typed event classes, transaction-after-commit).
    * D03 Broadcasting (for BroadcastChannel).
    * D05 Mail (for `MailChannel` to actually deliver).
    * B-area: `DatabaseNotification` Eloquent model + migration.

- Learnings — for future iterations:
  - **NEW US-S01 defect class: TEST-SCAFFOLDING-IN-PRODUCTION-CLASS.**
    `NotificationManager::getSentNotifications/clearSentNotifications`
    + `DatabaseChannel::getStoredNotifications/clear` +
    `SmsChannel::getSentMessages/clear` + `SlackChannel::getSentMessages/clear`.
    All four classes expose test-double accessors as public methods on
    the production class. Laravel uses `Notification::fake()` (Facade
    swaps the binding in tests). Audit query: grep `for testing
    purposes\|for testing\|get*Messages\|getSent*\|getStored*` in
    `src/Phare/`. Likely surfaces 8-10 more classes with the same
    leak.
  - **NEW US-S01 defect class: OUT-OF-CORE-PACKAGE-INLINED.**
    Phare ships `SlackMessage/SmsMessage/SlackChannel/SmsChannel`
    inline; Laravel keeps these as official packages
    (`laravel/slack-notification-channel`, vendor-specific SMS
    packages). Indicates Phare is on an older Laravel-shape (v8/v9)
    and missed the v10+ split. NEW dimension for US-S01:
    "version-of-Laravel-being-mirrored" — Phare's reference may
    not be 13.x for every subsystem; some subsystems mirror
    older shapes. Audit query: cross-check every Phare file's
    namespace against Laravel's CURRENT package layout.
  - **NEW US-S01 defect class:
    DUPLICATED-DRIVER-RESOLUTION-WITHOUT-MANAGER-BASE.**
    `Phare\Notifications\Channels\ChannelManager` does NOT extend
    `Phare\Support\Manager` despite needing the same driver-
    resolution logic. Re-implements `customDrivers` Closure
    registry inline. Audit query: list every class in
    `src/Phare/` whose name ends `Manager` and check `extends
    Manager` clause. Likely surfaces 1-2 more (Auth manager?
    Translation manager?).
  - **CONVENTION INCONSISTENCY pattern.** Phare fires
    `NotificationSending` as a STRING (`'notification.sending'`)
    but fires `Auth\Events\Attempting` as a CLASS (object).
    Same subsystem-author choosing two different idioms.
    Audit query: every `dispatch(` callsite in `src/Phare/` —
    is the first arg a string literal or a class instance?
    Cluster by subsystem.
  - **LAYER-COLLAPSE 2nd and 3rd instances confirmed.**
    1st was D01 Queue's `Job` class merging user-job + queue-side
    state. 2nd is Notifications' `Notification` merging
    sender-side + recipient-side read-state. 3rd is
    `NotificationManager` merging Manager + Sender concerns.
    Three instances IN-AREA-D plus one more in D02 (HasEvents
    bypassing Container DI) = 4 layer-collapse instances total.
    Document distinct sub-patterns under LAYER-COLLAPSE.
  - **HELPER-INSIDE-BOOT pattern 2nd instance.** D01 Queue's
    `QueueServiceProvider::boot()` declared `queue()/dispatch()/
    dispatch_after()`. D04 Notifications' `NotificationServiceProvider::boot()`
    declares `notify()/notification()`. Both use `function_exists`
    guards. Audit query: `grep -rn 'function_exists' src/Phare/`
    inside `ServiceProvider::boot` methods. Likely 3-5 more
    instances.
  - **`uniqid` predictability streak 11+.** D01 Queue jobId, D04
    Notification id, plus likely instances in Cache key generation
    (A-area) and Auth token (C-area). Audit query: `grep -rn
    'uniqid(' src/Phare/` and check whether each call site uses
    the value for security identification.
  - **Manager 2-layer split pattern.** Phare has
    `NotificationManager` wrapping `ChannelManager` — a 2-layer
    wrap that Laravel keeps flat (`ChannelManager extends Manager
    implements Dispatcher,Factory`). Cost: 2× the surface to port
    + more test scaffolding. Benefit: ?? (unclear). Cross-check
    other Phare managers: BroadcastManager (single-layer in Phare)
    vs QueueManager (single-layer) vs CacheManager (single-layer).
    The 2-layer is anomalous to Notifications.
  - **Same-method-on-multiple-classes pattern.** Phare has
    `Notification::markAsRead/markAsUnread/isRead/getReadAt`
    AND would need them on a `DatabaseNotification` model to
    mirror Laravel. Adding the model later means duplicate API.
    Pre-port hazard: document the duplicate explicitly so the
    port can pick a side. (Recommended: move read-state OFF
    `Notification` ONTO a new `DatabaseNotification` model.)
  - **Carrying running counts: STUB-DEFECT 3rd, FAKE-DRIVER 5th,
    LAYER-COLLAPSE 3rd, METHOD-NAME-LIES 2nd, HELPER-INSIDE-BOOT
    2nd, silent-arg-drop 11-13, same-name/different-shape 22-25,
    no-contracts 15th.** Each defect-class instance increment is
    a US-S01 entry. Total instance count across all classes is
    growing fast — by the time S01 runs, we'll have ~100+ entries
    organized by ~12-15 class taxonomies.
  - **Pattern of "3 new defect classes per Area-D audit" broken
    at D04 — 4 new classes here.** TEST-SCAFFOLDING-IN-PRODUCTION-CLASS,
    OUT-OF-CORE-PACKAGE-INLINED, DUPLICATED-DRIVER-RESOLUTION-WITHOUT-MANAGER-BASE,
    plus the CONVENTION INCONSISTENCY pattern. The "3 per audit"
    was an emergent regularity in D01-D03, not a constraint.

---

### Mail

- Current — Phare:
  Eight files in `src/Phare/Mail/` (645 LOC):
  - `Mailer.php` (86 LOC). Public: `__construct(array $config = [])`,
    `send(Mailable): bool`, `raw(string, \Closure): bool`,
    `html(string, \Closure): bool`, `getConfig(): array`,
    `getSentMails(): array`, `clearSentMails(): void`. **CRITICAL
    — `send()` is FAKE.** Body lines 32-44 verbatim:
    `// Store mail for testing/logging purposes / $this->sentMails[] = [...];
    / return true;`. ZERO transport. No SMTP, no Symfony Mailer,
    no PHPMailer. Process end = mails lost. **FAKE-DRIVER 6th
    instance.**
  - `MailManager.php` (136 LOC, extends `Phare\Support\Manager`).
    Public: `__construct(Container)`, `mailer(?string): Mailer`,
    `getDefaultDriver(): string`, `getDefaultMailer(): ?string`.
    Plus 6 protected helpers. **One driver type** — constructs
    `new Mailer($config)` regardless of driver name. ZERO real
    transports.
  - `MailServiceProvider.php` (28 LOC). IMPORTS `Phalcon\Di\DiInterface`
    + `Phalcon\Di\ServiceProviderInterface`. Registers
    `'mail.manager'` + `'mailer'` alias + `Mailer::class` binding.
    Same alias pattern as D03 BroadcastServiceProvider.
  - `Mailable.php` (250 LOC, abstract). 16 builder methods +
    9 getters + 2 `has*` predicates + abstract `build()`.
    **CRITICAL — `renderView()` is STUB.** Lines 236-247: naive
    `str_replace('{{ $key }}', $value, $content)`. NO Blade. NO
    Volt. Source comment: `// Simple template rendering - in a
    full implementation this would / // integrate with the view
    system`. Templates with conditionals/loops silently render
    as literal HTML. SECURITY HAZARD — no HTML escaping on `$value`.
  - `Message.php` (92 LOC). 5 builder methods + 5 getters.
    Lightweight ctor-helper for `Mailer::raw/html` callbacks;
    never read by transport.
  - `HtmlMailable.php` (24 LOC). `build()` body verbatim:
    `// HTML mailable is already built`. NO-OP override.
  - `RawMailable.php` (24 LOC). Same no-op build shape.
  - `MailException.php` (5 LOC, `extends \Exception`).

- Reference — Laravel 13 `Illuminate\Mail\*`:
  ~15 files (~3,400 LOC; `Mailable.php` alone 1,917 LOC):
  - `Mailer.php` (~660 LOC, implements `Contracts\Mail\Mailer +
    MailQueue`). 25 public methods incl. `alwaysFrom/alwaysReplyTo/
    alwaysReturnPath/alwaysTo/to/cc/bcc/html/raw/plain/render/
    send/sendNow/queue/onQueue/queueOn/later/laterOn/
    getSymfonyTransport/getViewFactory/setSymfonyTransport/setQueue`.
    Real SMTP via Symfony Mailer. `queue/later` route through Bus.
  - `MailManager.php` (~620 LOC). Implements `Contracts\Mail\Factory`.
    13 public + ~12 protected `create<Transport>Transport`:
    Smtp, Sendmail, Ses, SesV2, Resend, Mail, Mailgun, Postmark,
    Failover, Roundrobin, Log, Array. **12 real transports.**
  - `Mailable.php` (1,917 LOC) — ~70 public methods incl. modern
    `envelope/content/headers/attachments` (v9+ convention) plus
    test assertions inside the class
    (`assertHasTo/assertSeeInHtml/...`, ~12 methods).
  - `Message.php` (~390 LOC) — wraps Symfony `Email`; ~30 methods
    incl. `getSymfonyMessage()`.
  - `PendingMail.php` (~125 LOC) — fluent intermediary returned
    by `Mailer::to($users)`. Public: `locale/cc/bcc/send/sendNow/
    queue/later`.
  - `SendQueuedMailable.php` — queue-side wrapper job.
  - `SentMessage.php` (~80 LOC) — return DTO.
  - `Markdown.php` (~230 LOC) — Blade-based Markdown renderer.
  - `Attachment.php` (~250 LOC) — first-class attachment value
    object.
  - `TextMessage.php` — text-only convenience.
  - `Transport/{ArrayTransport,LogTransport,ResendTransport,
    SesTransport,SesV2Transport}.php` — bundled transports
    (Symfony provides others).
  - `Mailables/{Address,Attachment,Content,Envelope,Headers}.php`
    — 5 DTOs for the v9+ Mailable shape.
  - `Events/{MessageSending,MessageSent}.php` — 2 events.
  - `MailServiceProvider.php` (~60 LOC).
  - `Contracts/Mail/{Mailer,Mailable,Factory,MailQueue,Attachable}.php`
    — **5 contracts.**

- Public-API diff:
  - **Mailer:**
    - `send(Mailable): bool` Phare — vs Laravel `send($view, array
      $data = [], $callback = null): ?SentMessage`. **Silent-arg-drop
      14th instance** (data + callback). Plus return-type
      divergence (`bool` vs `?SentMessage`). Plus `$view` argument
      widened away (Mailable-only, no view string).
    - `sendNow(...)` — **MISSING from Phare.**
    - `raw($text, $callback)` — sig parity; behaviour divergence
      (Phare wraps in RawMailable; Laravel feeds view system).
    - `html($html, $callback)` — sig parity.
    - `plain($view, array $data, $callback)` — **MISSING.**
    - `render($view, array $data = [])` — **MISSING.**
    - `queue/onQueue/queueOn/later/laterOn` — **5 MISSING** (all
      queue routing).
    - `to/cc/bcc` (manager-level returning PendingMail) — **MISSING.**
      `Mail::to('a@b')->send($mailable)` does not exist.
    - `alwaysFrom/alwaysReplyTo/alwaysReturnPath/alwaysTo` —
      **4 MISSING.**
    - `getSymfonyTransport/setSymfonyTransport/getViewFactory/
      setQueue` — **MISSING.**
    - Ctor: Phare 1-arg `(array $config = [])` vs Laravel 4-arg
      `(string $name, Factory $views, TransportInterface $transport,
      ?Dispatcher $events = null)`. Phare's ctor accepts a config
      array, merges it into `$this->config`, then NEVER READS the
      transport keys. **NEW US-S01 sub-pattern: CONFIG-AS-DECORATION**
      (the `host/port/encryption/username/password` keys are
      decorative — no code reads them).
    - `getConfig/getSentMails/clearSentMails` — Phare additions.
      `getSentMails + clearSentMails` = TEST-SCAFFOLDING-IN-
      PRODUCTION-CLASS 5th instance.
  - **MailManager:**
    - `build($config): Mailer` — **MISSING.**
    - `createSymfonyTransport($config)` — **MISSING.**
    - `create<12 transports>` — **12 MISSING.** Phare ships ZERO.
    - `setDefaultDriver` — **MISSING.** (Phare has
      `getDefaultMailer` instead, asymmetric.)
    - `purge/extend/getApplication/setApplication/forgetMailers/
      __call` — **MISSING from MailManager directly** (some
      inherited from base `Manager`; verify in E-area).
  - **Mailable:**
    - `from($address, $name = null)` — **MISSING from Phare.** No
      sender setter. Emails sent through Phare have NO `From:`
      header.
    - `markdown($view, array $data = [])` — **MISSING.**
    - `priority/tag/metadata` — **MISSING.**
    - `attachFromStorage/attachFromStorageDisk` — **MISSING.**
    - `withSymfonyMessage(\Closure)` — **MISSING.**
    - `locale/onConnection/onQueue/encrypted/afterCommit/
      afterCommitIf` — **MISSING.**
    - `assertHasTo/assertHasCc/assertHasBcc/assertSeeInHtml/
      assertSeeInText/assertSeeInOrderInHtml/assertHasSubject/
      assertHasFrom/assertHasReplyTo/assertHasAttachment` — **~12
      MISSING** (test assertions inside production class — Phare
      offloads to `Mailer::getSentMails` instead).
    - `envelope()/content()/headers()/attachments()` — **MISSING.**
      Entire v9+ Mailable convention absent. Phare mirrors the
      OLDER v6/v7 `build()` shape.
  - **Message:** Phare's `Message` is a 5-method builder bag;
    Laravel's wraps Symfony `Email` with `getSymfonyMessage()`
    accessor. Phare's Message is never read by transport (TRANSIENT-
    BUILDER-CLASS).

- Wholesale missing from Phare:
  - **5 Mail contracts** (`Mailer/Mailable/Factory/MailQueue/
    Attachable`).
  - **12 transport classes** (Symfony/Smtp/Sendmail/Ses/SesV2/
    Resend/Mail/Mailgun/Postmark/Failover/Roundrobin/Log/Array).
  - `PendingMail.php`, `SendQueuedMailable.php`, `SentMessage.php`,
    `TextMessage.php`, `Markdown.php`, `Attachment.php`.
  - **5 missing DTOs in `Mailables\`** (Address/Attachment/Content/
    Envelope/Headers — the v9+ Mailable shape).
  - `Events/{MessageSending,MessageSent}` — 2 events.
  - Markdown renderer + Blade integration.

- No-contracts streak — 16th:
  - `Phare\Mail\Contracts\` does NOT exist.
  - 5 Laravel contracts unmapped.
  - 16th no-contracts subsystem (D03/D04/D05 all consecutive).

- Wrapper-Rule (§2) leaks:
  - `src/Phare/Mail/MailServiceProvider.php:5-6` imports `Phalcon\Di\*`.
    **6th** subsystem-namespace leak class instance (C01/C07/D01/D02/
    D03/D05). D04 was the only Phalcon-clean D-area.

- Silent-arg-drop 14:
  - `Mailer::send(Mailable): bool` drops Laravel's `array $data
    = [], $callback = null`.

- Same-name / different-shape running list updates:
  - 26 — `Mailer::send` arity drop + type widening + return-type
    divergence.
  - 27 — `Mailable::attach(string $path, ?string $name = null,
    ?string $type = null): static` Phare vs `attach($file, array
    $options = []): $this` Laravel — Phare locks out `Attachable`.
  - 28 — `Mailable::view(string, ?string)` Phare (view + optional
    text-view) vs `view($view, array $data = [])` Laravel (view
    + data). SAME name + SAME arity, OPPOSITE meaning of 2nd arg.
    **Cross-call hazard — the silent-most defect class in the
    same-name-different-shape catalog.**

- METHOD-NAME-LIES 3rd instance:
  - `Mailer::send()` returns `true` claiming success without
    delivering. Pattern now confirmed pervasive in D-area: D03
    (`queue` is sync), D04 (`sendNow` aliases `send`), D05
    (`send` is fake).

- STUB-DEFECT inventory (running tally 6).
- FAKE-DRIVER inventory (running tally 6).
- TEST-SCAFFOLDING-IN-PRODUCTION-CLASS inventory (running tally 5).

- Dual-stack check:
  - Phalcon Mailer removed in v5+. `grep -rn 'Phalcon\\\\Mailer'
    src/Phare/` returns nothing. Single-stack.

- File-count ratio:
  - Phare: 8 files / 645 LOC.
  - Laravel: ~30 files / ~3,400 LOC (Mailable alone 1,917 LOC).
  - Phare ships **~19%** of Laravel's mail LOC (incl. contracts).
    Without `Mailable.php` ratio difference, **~8%**. The widest
    gap in D-area. D01=21%, D02=43%, D03=24%, D04=45%, **D05=~9-19%**.

- Impact / port-effort:
  - Effort tier: **XL** (matches D01 Queue). FAKE-DRIVER +
    missing transport pipeline + missing queue routing + missing
    view-system integration + missing v9+ DTO shape.
  - Risk tier: **CRITICAL**:
    (1) `Mailer::send` does not transmit (FAKE);
    (2) `Mailable::renderView` is naive `str_replace` with no
        HTML escaping — SECURITY HAZARD;
    (3) NO 12 transports;
    (4) NO queue routing;
    (5) NO Markdown / no Attachment DTO / no v9+ convention.
  - Blocking dependencies:
    * D01 Queue (for queue/later).
    * D02 Events (for typed MessageSending/MessageSent).
    * View system (Phare wraps Phalcon Volt — Mailable.renderView
      must integrate).
    * D04 Notifications (`MailChannel` depends).
    * Composer dep on `symfony/mailer` (NOT in composer.json —
      must add).

- Learnings — for future iterations:
  - **NEW US-S01 defect class: CONFIG-AS-DECORATION.** Distinct
    from CONFIG-PROMISES-UNSHIPPED-DRIVER (config promises a
    driver the manager throws on) and NAMING-DIVERGENCE
    (binding-key mismatch). Here: `Mailer::__construct` accepts
    a fully-specified config, merges into `$this->config`, but
    NEVER reads transport keys (driver/host/port/encryption/
    username/password). The from-block IS read but only the
    address part. Audit query: every `__construct(array $config)`
    in `src/Phare/` — grep class body for `$this->config[<key>]`
    references; if config keys are set but never read, the block
    is decorative.
  - **NEW US-S01 sub-pattern: TRANSIENT-BUILDER-CLASS.** Phare's
    `Mail\Message` is a 5-method builder bag with no transport-
    side consumer. Its only use is to be copied into a
    `RawMailable/HtmlMailable` constructor. Could be replaced
    with a closure that builds the Mailable directly. Audit
    query: classes named `Builder/Message/Request` that have
    only getters/setters and whose output is consumed by exactly
    one downstream class via field-copy.
  - **METHOD-NAME-LIES 3rd instance.** D03/D04/D05 all surface
    a method whose name implies async/transport/queueing but
    implementation is sync/local. Pattern is now confirmed
    pervasive in D-area. Audit query: every method named
    `send/queue/dispatch/sendNow/now/later/queueOn/onQueue/
    laterOn` — verify body has transport/queue/dispatcher call.
  - **`Mailable::renderView` is SECURITY-CRITICAL STUB-DEFECT.**
    Naive `str_replace('{{ $key }}', $value, $content)`. No
    HTML escaping. `$value` containing `<script>...</script>`
    injects directly. Plus the no-template branch falls through
    to `renderView($this->view)` with the VIEW NAME as the
    template content — templates never load. **Promote to top
    of US-S01 security-impact list.**
  - **`from()` builder method absent on Mailable.** Phare has
    `to/cc/bcc/replyTo` but NO `from`. The sender is supposed to
    come from `Mailer::alwaysFrom` (missing) or `config('mail.from')`
    (set in ctor but `send()` never reads it). Net effect: NO
    `From:` header. SMTP-via-Symfony would reject; the FAKE
    transport silently records without `From:`. Pairs with
    FAKE-DRIVER hiding the defect — together they form a
    DEFECT-PAIR-HIDE pattern (one defect masks the symptom
    of another).
  - **NEW US-S01 pattern: DEFECT-PAIR-HIDE.** Two related defects
    where one masks the other's symptom. Mail's FAKE-DRIVER hides
    Mailable's missing `from()`. Notifications' STUB
    `Notifiable::notifications()` hides the absent
    `DatabaseNotification` model. Notifications' FAKE
    `DatabaseChannel` hides the absent `notifications` table
    migration. Audit query: pair every FAKE-DRIVER / STUB-DEFECT
    with the next missing dependency in the same flow.
  - **Phare mirrors v6/v7 Mailable shape, not 13.x.** Confirmed
    by absent `envelope/content/headers/attachments` methods
    and absent `Mailables\` DTOs. Combined with D04's older
    Notifications shape and D02/D03's near-13.x shapes, US-S01
    must include a per-subsystem "version-anchor" column.
    Likely values: D01 Queue=v7, D02 Events=v11, D03 Broadcasting=
    v11, D04 Notifications=v9, D05 Mail=v7. Audit query: for each
    subsystem find the SHA of the matching Laravel commit (where
    the public API matches Phare's) — gives an objective version
    anchor.
  - **Three new defect classes from D05.** CONFIG-AS-DECORATION,
    TRANSIENT-BUILDER-CLASS, DEFECT-PAIR-HIDE + carry-forward
    METHOD-NAME-LIES 3rd. Running total: D-area defect class
    count is ~14-15 distinct patterns plus carry-forwards.

---

### Console Scheduler

- Current — Phare:
  Six files in `src/Phare/Console/Scheduling/` (all Phalcon-CLEAN — zero
  `Phalcon\` references): `Schedule.php` (158 LOC, 10 pub methods),
  `Event.php` (546 LOC, ~35 pub methods + hand-rolled cron parser),
  `CallbackEvent.php` (63 LOC, extends `Event`, 2 pub methods),
  `ScheduleRunCommand.php` (51 LOC), `ScheduleListCommand.php` (52 LOC),
  `ScheduleServiceProvider.php` (30 LOC, binds `'schedule'` → `Schedule`).

  - **`Phare\Console\Scheduling\Schedule`** (10 pub): `command`, `job`, `call`,
    `exec`, `events`, `dueEvents`, `timezone`, `getTimezone`, `clear`, `run`.
  - **`Phare\Console\Scheduling\Event`** (~35 pub): cron expression, all basic
    frequency methods (everyMinute/…/daily/weekly/monthly/yearly), `days`,
    `timezone`, `user`, `environments`, `evenInMaintenanceMode`,
    `runInBackground`, `sendOutputTo`, `appendOutputTo`, `before`, `after`,
    `when`, `skip`, `isDue`, `run`, `getCommand`, `getExpression`,
    `getTimezone`.
  - **`Phare\Console\Scheduling\CallbackEvent`** extends `Event`: overrides
    `runCommand()`, adds `description(string)`.
  - `ScheduleRunCommand` — `schedule:run` command; reads `$app['schedule']`,
    calls `dueEvents()` and `run()`.
  - `ScheduleListCommand` — `schedule:list` command; lists registered events.

- Expected — Laravel 13:
  `Schedule.php` (12 pub: command/job/call/exec/events/dueEvents/group/
  serverShouldRun/useCache/compileArrayInput/__construct/__call) plus
  `Event.php` (43 pub) + `ManagesFrequencies` trait (52 pub) +
  `ManagesAttributes` trait + `CallbackEvent` + `CommandBuilder` +
  `CronExpressionTimezoneConverter` + 9 Artisan commands (ScheduleRun,
  ScheduleList, ScheduleWork, ScheduleFinish, ScheduleInterrupt, SchedulePause,
  ScheduleResume, ScheduleTest, ScheduleClearCache) + `EventMutex` /
  `SchedulingMutex` / `CacheEventMutex` / `CacheSchedulingMutex` contracts +
  `PendingEventAttributes`. Relies on `dragonmantank/cron-expression` for
  parsing.

- Gaps:

  **Missing**

  1. **`dragonmantank/cron-expression` not used** — Phare ships a hand-rolled
     `cronMatches()` / `segmentMatches()` (~50 LOC). The homebrew parser lacks:
     step-ranges with bounds (`2-10/2`), `L` (last day of month/week),
     `W` (nearest weekday), `#` (nth weekday of month), `?` wildcard.
     Silent mis-scheduling on any expression that uses these features.
     Laravel delegates entirely to the library; Phare's parser is ~L10
     equivalence only.

  2. **No mutex / overlap protection** — `withoutOverlapping()`,
     `onOneServer()`, `mutexName()`, `preventOverlapsUsing()`,
     `createMutexNameUsing()`, `CacheEventMutex`, `CacheSchedulingMutex`,
     `shouldSkipDueToOverlapping()` all absent. Concurrent overlapping
     invocations of long-running jobs are undetectable; no distributed-lock
     support.

  3. **No callback hooks on success/failure** — `onSuccess()`, `onFailure()`,
     `onSuccessWithOutput()`, `onFailureWithOutput()`, `then()`,
     `thenWithOutput()` absent; `before()`/`after()` ship on Phare's `Event`
     but accept a plain callable only (no DI-container injection, no
     `callBeforeCallbacks()`/`callAfterCallbacks()` public dispatchers).

  4. **No ping callbacks** — `pingBefore()`, `pingBeforeIf()`,
     `pingOnSuccess()`, `pingOnSuccessIf()`, `pingOnFailure()`,
     `pingOnFailureIf()`, `thenPing()`, `thenPingIf()` all absent.

  5. **No email-output hooks** — `emailOutputTo()`, `emailWrittenOutputTo()`,
     `emailOutputOnFailure()` absent (blocked also by D05 fake Mailer).

  6. **30+ frequency methods missing** from `ManagesFrequencies`:
     - *Sub-minute:* `everySecond`, `everyTwoSeconds`, `everyFiveSeconds`,
       `everyTenSeconds`, `everyTwentySeconds`, `everyThirtySeconds`,
       `everyFifteenSeconds`.
     - *Day-of-week helpers:* `mondays`, `tuesdays`, `wednesdays`, `thursdays`,
       `fridays`, `saturdays`, `sundays`, `weekdays`, `weekends`.
     - *Multi-period:* `quarterly`, `quarterlyOn`, `twiceDailyAt`,
       `twiceMonthly`, `lastDayOfMonth`, `yearlyOn`, `daysOfMonth`.
     - *Hour-level:* `everyTwoHours`, `everyThreeHours`, `everyFourHours`,
       `everySixHours`, `everyOddHour`.
     - *Range/condition:* `between(string, string)`, `unlessBetween(string, string)`, `at(string)`.

  7. **Schedule missing** `group()` (L11 grouped event definitions),
     `serverShouldRun()` (distributed-lock check for `onOneServer` events),
     `useCache(CacheRepository)`, `compileArrayInput()`, `__call()`.

  8. **7 schedule:* commands missing** — only `schedule:run` and
     `schedule:list` ship. Absent: `schedule:work` (persistent daemon —
     analogous to `queue:work`; no equivalent in Phare), `schedule:finish`,
     `schedule:interrupt`, `schedule:pause`/`schedule:resume`,
     `schedule:test` (dry-run a specific event).

  9. **No `ManagesAttributes` / `PendingEventAttributes`** — no event
     tagging, no `name()` / `description()` on `Event` itself (only
     `CallbackEvent` has `description()`).

  10. **No `getSummaryForDisplay()`** — `ScheduleListCommand` uses
      `getCommand()`; Laravel's list command calls `getSummaryForDisplay()`
      which formats the command + next-run in human-readable form.

  11. **No `nextRunDate()`** — cannot compute the next fire time for a
      display or dormancy check.

  12. **No `isRepeatable()` / `shouldRepeatNow()`** — repeatable
      sub-minute events (L11+ feature) unsupported.

  13. **No `runsInEnvironment()` / `runsInMaintenanceMode()` /
      `runsWhenPaused()`** — missing event-state queries.

  14. **No `storeOutput()` / `getDefaultOutput()`** — no output
      accumulation path.

  **Correctness defects**

  - **`ScheduleRunCommand` success-counter bug** — line 43:
    `count(array_filter($results, fn ($r) => $result['success']))` captures
    the outer loop variable `$result` (last-iteration value) instead of
    lambda param `$r`. Counter always reflects only the last event's
    success status for ALL events — silent always-wrong count. Same
    family as stub-defect (code runs, produces wrong answer silently).

  - **Cron-segment range-step edge case** — `segmentMatches()` handles
    `*/N` but not `A-B/N` (step with bounded range). Strings like
    `10-50/10` fall through to the plain `(int)$segment === $value`
    branch, which returns false for all values. Silent false-negative.

  - **`job()` dispatch via `function_exists('dispatch')`** — calls the
    global `dispatch()` helper if it exists, otherwise silently no-ops.
    No container resolution, no queue routing.

  **No-contracts pattern (16th subsystem)** — no `Phare\Contracts\Console\Scheduling\*`
  namespace; Phare's `Schedule` and `Event` publish no interface, unlike
  Laravel's `EventMutex`/`SchedulingMutex` contracts.

  **Type mismatch**
  - `Schedule::run(): array` returns a Phare-specific array of
    `['event','output','success','exception']` maps; Laravel has no
    `Schedule::run()` method — `ScheduleRunCommand` drives `Event::run()`
    per event directly. The Phare design conflates Schedule (what to run)
    with the runner (when and how to run it).

- Effort: **M** — Phalcon-CLEAN baseline and frequency-method
  backfill are mechanical. Hard parts: (1) swap to `dragonmantank/cron-expression`
  (composer dep + API adaptation), (2) mutex infrastructure (cache-backed
  `EventMutex` + `SchedulingMutex` contracts), (3) `schedule:work` daemon.
  Blocked on D01 (real queue) for `schedule:work` ping and job-scheduling,
  and D05 (real Mailer) for `emailOutputTo`.

