<?php

if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
    test('eloquent event lifecycle tests require sqlite driver', function () {
        $this->markTestSkipped('PDO sqlite driver is required for eloquent event lifecycle tests.');
    });

    return;
}

require_once __DIR__ . '/../Mock/Models/User.php';

use Phalcon\Di\Di;
use Phare\Database\MySql\DatabaseManager;
use Tests\Mock\Models\User;

if (!class_exists('EventLifecycleObserver')) {
    class EventLifecycleObserver
    {
        public static array $events = [];

        public function creating(User $model): void
        {
            self::$events[] = 'creating:' . $model->email;
        }

        public function updated(User $model): void
        {
            self::$events[] = 'updated:' . $model->name;
        }
    }
}

if (!class_exists('EventLifecycleCreated')) {
    class EventLifecycleCreated
    {
        public function __construct(public User $model)
        {
        }
    }
}

if (!class_exists('EventLifecycleUser')) {
    class EventLifecycleUser extends User
    {
        protected ?string $table = 'users';

        protected array $dispatchesEvents = [
            'created' => EventLifecycleCreated::class,
        ];
    }
}

beforeEach(function () {
    /** @var DatabaseManager $dbManager */
    $dbManager = Di::getDefault()->getShared('dbManager');
    $db = $dbManager->getConnection(['driver' => 'sqlite', 'database' => 'db']);

    foreach (glob(database_path('migrations/*.sql')) as $file) {
        $db->query(file_get_contents($file));
    }

    $db->execute('DELETE FROM users');
    EventLifecycleObserver::$events = [];
    $this->makeLifecycleUser = function (string $emailPrefix = 'event-user', string $name = 'Event User'): User {
        $user = new User();
        $user->fill([
            'email' => $emailPrefix . '-' . uniqid('', true) . '@example.com',
            'name' => $name,
            'password' => 'secret',
            'email_verified_at' => new DateTime('2026-03-26 10:00:00'),
        ]);

        return $user;
    };
});

it('registers observers and invokes matching callbacks', function () {
    User::observe(EventLifecycleObserver::class);

    $user = ($this->makeLifecycleUser)();
    $user->create();
    $user->name = 'Renamed User';
    $user->save();

    expect(EventLifecycleObserver::$events)->toBe([
        'creating:' . $user->email,
        'updated:Renamed User',
    ]);
});

it('fires creating and created events on insert', function () {
    $events = [];

    User::creating(function (User $model) use (&$events) {
        $events[] = 'creating:' . $model->email;
    });

    User::created(function (User $model) use (&$events) {
        $events[] = 'created:' . $model->email;
    });

    $user = ($this->makeLifecycleUser)();
    $user->create();

    expect($events)->toBe([
        'creating:' . $user->email,
        'created:' . $user->email,
    ]);
});

it('fires updating and updated events on update', function () {
    $events = [];
    $user = ($this->makeLifecycleUser)();
    $user->create();

    User::updating(function (User $model) use (&$events) {
        $events[] = 'updating:' . $model->name;
    });

    User::updated(function (User $model) use (&$events) {
        $events[] = 'updated:' . $model->name;
    });

    $user->name = 'Updated Name';
    $user->save();

    expect($events)->toBe([
        'updating:Updated Name',
        'updated:Updated Name',
    ]);
});

it('fires deleting and deleted events on delete', function () {
    $events = [];
    $user = ($this->makeLifecycleUser)();
    $user->create();

    User::deleting(function (User $model) use (&$events) {
        $events[] = 'deleting:' . $model->email;
    });

    User::deleted(function (User $model) use (&$events) {
        $events[] = 'deleted:' . $model->email;
    });

    $user->delete();

    expect($events)->toBe([
        'deleting:' . $user->email,
        'deleted:' . $user->email,
    ]);
});

it('fires saving and saved on both insert and update', function () {
    $events = [];

    User::saving(function (User $model) use (&$events) {
        $events[] = 'saving:' . ($model->getKey() === null ? 'create' : 'update');
    });

    User::saved(function (User $model) use (&$events) {
        $events[] = 'saved:' . ($model->wasChanged() ? 'changed' : 'unchanged');
    });

    $user = ($this->makeLifecycleUser)();
    $user->save();

    $user->name = 'Changed';
    $user->save();

    expect($events)->toBe([
        'saving:create',
        'saved:changed',
        'saving:update',
        'saved:changed',
    ]);
});

it('cancels operations when an ing event returns false', function () {
    User::creating(fn () => false);

    $user = ($this->makeLifecycleUser)('cancelled-user');

    expect($user->create())->toBeFalse()
        ->and(User::where('email', $user->email)->first())->toBeNull();
});

it('suppresses all model events within withoutEvents', function () {
    $events = [];

    User::creating(fn () => $events[] = 'creating');
    User::created(fn () => $events[] = 'created');

    User::withoutEvents(function () {
        $user = ($this->makeLifecycleUser)('quiet-wrapper');
        $user->save();
    });

    expect($events)->toBeEmpty();
});

it('supports saveQuietly and deleteQuietly', function () {
    $events = [];

    User::saving(fn () => $events[] = 'saving');
    User::saved(fn () => $events[] = 'saved');
    User::deleting(fn () => $events[] = 'deleting');
    User::deleted(fn () => $events[] = 'deleted');

    $user = ($this->makeLifecycleUser)('quiet-method');

    expect($user->saveQuietly())->toBeTrue();
    expect($events)->toBeEmpty();

    expect($user->deleteQuietly())->toBeTrue();
    expect($events)->toBeEmpty();
});

it('fires restoring and restored events on soft delete restore', function () {
    $events = [];
    $user = ($this->makeLifecycleUser)('restore-user');
    $user->create();
    $user->delete();

    User::restoring(function (User $model) use (&$events) {
        $events[] = 'restoring:' . $model->email;
    });

    User::restored(function (User $model) use (&$events) {
        $events[] = 'restored:' . $model->email;
    });

    $user->restore();

    expect($events)->toBe([
        'restoring:' . $user->email,
        'restored:' . $user->email,
    ]);
});

it('fires retrieved on find and first', function () {
    $seen = [];
    $user = ($this->makeLifecycleUser)('retrieved-user');
    $user->create();

    User::retrieved(function (User $model) use (&$seen) {
        $seen[] = $model->email;
    });

    User::findFirst($user->id);
    User::where('email', $user->email)->first();

    expect($seen)->toBe([
        $user->email,
        $user->email,
    ]);
});

it('fires trashed and force delete events around soft delete lifecycle', function () {
    $events = [];
    $user = ($this->makeLifecycleUser)('soft-delete-events');
    $user->create();

    User::softDeleted(function (User $model) use (&$events) {
        $events[] = 'trashed:' . $model->email;
    });
    User::forceDeleting(function (User $model) use (&$events) {
        $events[] = 'forceDeleting:' . $model->email;
    });
    User::forceDeleted(function (User $model) use (&$events) {
        $events[] = 'forceDeleted:' . $model->email;
    });

    $user->delete();
    $user->forceDelete();

    expect($events)->toBe([
        'trashed:' . $user->email,
        'forceDeleting:' . $user->email,
        'forceDeleted:' . $user->email,
    ]);
});

it('maps model events to custom event classes via dispatchesEvents', function () {
    $received = [];
    $dispatcher = Di::getDefault()->getShared('events');

    $dispatcher->listen(EventLifecycleCreated::class, function (EventLifecycleCreated $event) use (&$received) {
        $received[] = $event->model->email;
    });

    $user = new EventLifecycleUser();
    $user->fill([
        'email' => 'dispatches-events-' . uniqid('', true) . '@example.com',
        'name' => 'Dispatches Events',
        'password' => 'secret',
        'email_verified_at' => new DateTime('2026-03-26 11:00:00'),
    ]);

    $user->create();

    expect($received)->toBe([$user->email]);
});
