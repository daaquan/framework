# Database

## Configuration

Database settings live in `config/database.php`:

```php
return [
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
        ],
        'mysql' => [
            'driver'   => 'mysql',
            'host'     => env('DB_HOST', '127.0.0.1'),
            'port'     => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'phare'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset'  => 'utf8mb4',
        ],
    ],
];
```

## Migrations

### Creating a migration

```bash
php artisan make:migration create_users_table
```

Migration files are stored in `database/migrations/` and follow the naming convention
`YYYY_MM_DD_HHMMSS_description.php`.

### Migration structure

```php
use Phare\Database\Migrations\Migration;
use Phare\Database\Schema\Blueprint;
use Phare\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

### Blueprint column types

| Method | SQL type |
|--------|----------|
| `$table->id()` | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY |
| `$table->string('col', 255)` | VARCHAR |
| `$table->text('col')` | TEXT |
| `$table->integer('col')` | INT |
| `$table->bigInteger('col')` | BIGINT |
| `$table->boolean('col')` | TINYINT(1) |
| `$table->decimal('col', 8, 2)` | DECIMAL |
| `$table->float('col')` | FLOAT |
| `$table->date('col')` | DATE |
| `$table->dateTime('col')` | DATETIME |
| `$table->timestamp('col')` | TIMESTAMP |
| `$table->timestamps()` | `created_at` + `updated_at` TIMESTAMP NULL |
| `$table->softDeletes()` | `deleted_at` TIMESTAMP NULL |
| `$table->json('col')` | JSON |
| `$table->enum('col', ['a','b'])` | ENUM |
| `$table->foreignId('user_id')` | BIGINT UNSIGNED + FK constraint helper |

### Column modifiers

```php
$table->string('email')->nullable();
$table->string('status')->default('active');
$table->integer('sort_order')->unsigned();
$table->string('token')->unique();
```

### Running migrations

```bash
php artisan migrate            # run pending migrations
php artisan migrate:rollback   # rollback last batch
php artisan migrate:reset      # rollback all
php artisan migrate:refresh    # reset + migrate
```

Via `Migrator` directly:

```php
/** @var \Phare\Database\Migrator $migrator */
$migrator->run();
$migrator->rollback(steps: 1);
$migrator->reset();
$migrator->refresh();
```

## Schema builder

Manipulate existing tables:

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('phone')->nullable()->after('email');
    $table->dropColumn('legacy_field');
});

Schema::rename('old_table', 'new_table');
Schema::drop('old_table');
Schema::dropIfExists('old_table');
Schema::hasTable('users');
Schema::hasColumn('users', 'email');
```

## Seeders

```php
use Phare\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            PostSeeder::class,
        ]);
    }
}

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->count(50)->create();
    }
}
```

```bash
php artisan db:seed
php artisan db:seed --class=UserSeeder
```

## Factories

```php
use Phare\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected string $model = User::class;

    public function definition(): array
    {
        return [
            'name'     => $this->faker->name(),
            'email'    => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('password'),
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }
}
```

```php
// Create model instances (persisted)
User::factory()->create();
User::factory()->count(10)->create();
User::factory()->admin()->create();

// Make instances (not persisted)
User::factory()->make();
```
