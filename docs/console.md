# Console

Phare's console layer wraps Symfony Console with a Laravel Artisan-like API.

## Defining a command

Extend `Phare\Console\Command` and implement `handle()`:

```php
use Phare\Console\Command;

class SendDigestCommand extends Command
{
    protected ?string $signature = 'mail:digest {--force : Skip confirmation}';
    protected ?string $description = 'Send the daily digest email to all subscribers';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Send digest now?')) {
            $this->info('Aborted.');
            return 0;
        }

        $this->info('Sending digest...');
        // ...
        $this->info('Done.');

        return 0;
    }
}
```

## Signature syntax

```
command:name                        # simple command
command:name {argument}             # required argument
command:name {argument?}            # optional argument
command:name {argument=default}     # argument with default
command:name {--flag}               # boolean option (flag)
command:name {--option=}            # option that accepts a value
command:name {--option=default}     # option with default value
```

## Input helpers

```php
// Arguments and options
$this->argument('name');           // single argument
$this->argument();                 // all arguments as array
$this->option('format');           // single option
$this->option();                   // all options as array
$this->hasArgument('name');
$this->hasOption('format');
```

## Output helpers

```php
$this->info('Operation succeeded.');    // green
$this->error('Something went wrong.');  // red
$this->comment('Note: ...');            // yellow
$this->line('Plain output.');
```

## Interactive prompts

```php
// Yes/no confirmation
$confirmed = $this->confirm('Are you sure?'); // bool

// Free-form question
$name = $this->ask('What is your name?', 'Anonymous');

// Password (hidden input)
$password = $this->askPassword('Enter password:');

// Single-choice from list
$env = $this->choose('Select environment:', ['local', 'staging', 'production'], 'local');

// Multi-choice from list
$features = $this->choice('Enable features:', ['cache', 'queue', 'mail']);

// Question with autocomplete
$country = $this->anticipate('Country:', ['Canada', 'France', 'Germany'], 'Canada');
```

## Registering commands

Register commands in a service provider or the console kernel:

```php
// In a provider
$app->make(\Phare\Console\Application::class)
    ->resolveCommands([
        SendDigestCommand::class,
        CacheClearCommand::class,
    ]);
```

## Calling commands programmatically

```php
/** @var \Phare\Console\Application $artisan */
$artisan->call('mail:digest', ['--force' => true]);
$output = $artisan->output();
```

## Scheduling (cron)

Register scheduled commands in `app/Console/Kernel.php`:

```php
protected function schedule(\Phare\Console\Scheduling\Schedule $schedule): void
{
    $schedule->command('mail:digest')->dailyAt('08:00');
    $schedule->command('cache:prune')->hourly();
}
```
