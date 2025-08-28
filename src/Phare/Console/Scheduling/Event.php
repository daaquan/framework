<?php

namespace Phare\Console\Scheduling;

class Event
{
    protected string $command;

    protected string $expression = '* * * * *';

    protected string $timezone;

    protected array $filters = [];

    protected array $rejects = [];

    protected ?string $user = null;

    protected array $environments = [];

    protected bool $evenInMaintenanceMode = false;

    protected bool $runInBackground = false;

    protected ?string $output = null;

    protected bool $shouldAppendOutput = false;

    protected $beforeCallback = null;

    protected $afterCallback = null;

    public function __construct(string $timezone, string $command)
    {
        $this->timezone = $timezone;
        $this->command = $command;
    }

    /**
     * The Cron expression representing the event's frequency.
     */
    public function cron(string $expression): static
    {
        $this->expression = $expression;

        return $this;
    }

    /**
     * Schedule the event to run every minute.
     */
    public function everyMinute(): static
    {
        return $this->spliceIntoPosition(1, '*');
    }

    /**
     * Schedule the event to run every X minutes.
     */
    public function everyTwoMinutes(): static
    {
        return $this->spliceIntoPosition(1, '*/2');
    }

    /**
     * Schedule the event to run every three minutes.
     */
    public function everyThreeMinutes(): static
    {
        return $this->spliceIntoPosition(1, '*/3');
    }

    /**
     * Schedule the event to run every four minutes.
     */
    public function everyFourMinutes(): static
    {
        return $this->spliceIntoPosition(1, '*/4');
    }

    /**
     * Schedule the event to run every five minutes.
     */
    public function everyFiveMinutes(): static
    {
        return $this->spliceIntoPosition(1, '*/5');
    }

    /**
     * Schedule the event to run every ten minutes.
     */
    public function everyTenMinutes(): static
    {
        return $this->spliceIntoPosition(1, '*/10');
    }

    /**
     * Schedule the event to run every fifteen minutes.
     */
    public function everyFifteenMinutes(): static
    {
        return $this->spliceIntoPosition(1, '*/15');
    }

    /**
     * Schedule the event to run every thirty minutes.
     */
    public function everyThirtyMinutes(): static
    {
        return $this->spliceIntoPosition(1, '0,30');
    }

    /**
     * Schedule the event to run hourly.
     */
    public function hourly(): static
    {
        return $this->spliceIntoPosition(1, 0);
    }

    /**
     * Schedule the event to run hourly at a given offset in the hour.
     */
    public function hourlyAt(int $offset): static
    {
        return $this->spliceIntoPosition(1, $offset);
    }

    /**
     * Schedule the event to run daily.
     */
    public function daily(): static
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0);
    }

    /**
     * Schedule the event to run daily at a given time (HH:MM).
     */
    public function dailyAt(string $time): static
    {
        $segments = explode(':', $time);

        return $this->spliceIntoPosition(2, (int)$segments[0])
            ->spliceIntoPosition(1, count($segments) === 2 ? (int)$segments[1] : 0);
    }

    /**
     * Schedule the event to run twice daily.
     */
    public function twiceDaily(int $first = 1, int $second = 13): static
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, "$first,$second");
    }

    /**
     * Schedule the event to run weekly.
     */
    public function weekly(): static
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(5, 0);
    }

    /**
     * Schedule the event to run weekly on a given day and time.
     */
    public function weeklyOn(int $day, string $time = '0:0'): static
    {
        $this->dailyAt($time);

        return $this->spliceIntoPosition(5, $day);
    }

    /**
     * Schedule the event to run monthly.
     */
    public function monthly(): static
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, 1);
    }

    /**
     * Schedule the event to run monthly on a given day and time.
     */
    public function monthlyOn(int $day = 1, string $time = '0:0'): static
    {
        $this->dailyAt($time);

        return $this->spliceIntoPosition(3, $day);
    }

    /**
     * Schedule the event to run yearly.
     */
    public function yearly(): static
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, 1)
            ->spliceIntoPosition(4, 1);
    }

    /**
     * Set the days of the week the command should run on.
     */
    public function days(array|string $days): static
    {
        if (is_string($days)) {
            $days = [$days];
        }

        $dayMap = [
            'sunday' => 0, 'sun' => 0,
            'monday' => 1, 'mon' => 1,
            'tuesday' => 2, 'tue' => 2,
            'wednesday' => 3, 'wed' => 3,
            'thursday' => 4, 'thu' => 4,
            'friday' => 5, 'fri' => 5,
            'saturday' => 6, 'sat' => 6,
        ];

        $days = array_map(function ($day) use ($dayMap) {
            return is_numeric($day) ? $day : $dayMap[strtolower($day)] ?? $day;
        }, $days);

        return $this->spliceIntoPosition(5, implode(',', $days));
    }

    /**
     * Set the timezone the date should be evaluated on.
     */
    public function timezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Set which user the command should run as.
     */
    public function user(string $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Limit the environments the command should run in.
     */
    public function environments(array|string $environments): static
    {
        $this->environments = is_array($environments) ? $environments : [$environments];

        return $this;
    }

    /**
     * State that the command should run in maintenance mode.
     */
    public function evenInMaintenanceMode(): static
    {
        $this->evenInMaintenanceMode = true;

        return $this;
    }

    /**
     * State that the command should run in the background.
     */
    public function runInBackground(): static
    {
        $this->runInBackground = true;

        return $this;
    }

    /**
     * Write the task to a file.
     */
    public function sendOutputTo(string $location, bool $append = false): static
    {
        $this->output = $location;
        $this->shouldAppendOutput = $append;

        return $this;
    }

    /**
     * Append the output to a file.
     */
    public function appendOutputTo(string $location): static
    {
        return $this->sendOutputTo($location, true);
    }

    /**
     * Register a callback to be called before the operation.
     */
    public function before(callable $callback): static
    {
        $this->beforeCallback = $callback;

        return $this;
    }

    /**
     * Register a callback to be called after the operation.
     */
    public function after(callable $callback): static
    {
        $this->afterCallback = $callback;

        return $this;
    }

    /**
     * Register a callback to further filter the schedule.
     */
    public function when(callable $callback): static
    {
        $this->filters[] = $callback;

        return $this;
    }

    /**
     * Register a callback to reject the schedule.
     */
    public function skip(callable $callback): static
    {
        $this->rejects[] = $callback;

        return $this;
    }

    /**
     * Splice the given value into the given position of the expression.
     */
    protected function spliceIntoPosition(int $position, string $value): static
    {
        $segments = explode(' ', $this->expression);

        $segments[$position - 1] = (string)$value;

        $this->expression = implode(' ', $segments);

        return $this;
    }

    /**
     * Determine if the event is due to run based on the current time.
     */
    public function isDue(): bool
    {
        if (!$this->passesFilters()) {
            return false;
        }

        return $this->expressionPasses();
    }

    /**
     * Determine if the filters pass for the event.
     */
    protected function passesFilters(): bool
    {
        foreach ($this->filters as $callback) {
            if (!$callback()) {
                return false;
            }
        }

        foreach ($this->rejects as $callback) {
            if ($callback()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the cron expression passes.
     */
    protected function expressionPasses(): bool
    {
        $now = new \DateTime('now', new \DateTimeZone($this->timezone));

        return $this->cronMatches($this->expression, $now);
    }

    /**
     * Simple cron expression matching.
     */
    protected function cronMatches(string $expression, \DateTime $dateTime): bool
    {
        $segments = explode(' ', $expression);

        if (count($segments) !== 5) {
            return false;
        }

        $values = [
            (int)$dateTime->format('i'), // minute
            (int)$dateTime->format('G'), // hour
            (int)$dateTime->format('j'), // day
            (int)$dateTime->format('n'), // month
            (int)$dateTime->format('w'), // day of week
        ];

        foreach ($segments as $index => $segment) {
            if (!$this->segmentMatches($segment, $values[$index])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a cron segment matches the current value.
     */
    protected function segmentMatches(string $segment, int $value): bool
    {
        if ($segment === '*') {
            return true;
        }

        if (str_contains($segment, ',')) {
            return in_array($value, explode(',', $segment));
        }

        if (str_contains($segment, '/')) {
            [$range, $step] = explode('/', $segment);

            if ($range === '*') {
                return $value % (int)$step === 0;
            }
        }

        if (str_contains($segment, '-')) {
            [$min, $max] = explode('-', $segment);

            return $value >= (int)$min && $value <= (int)$max;
        }

        return (int)$segment === $value;
    }

    /**
     * Run the command.
     */
    public function run(): string
    {
        if ($this->beforeCallback && is_callable($this->beforeCallback)) {
            ($this->beforeCallback)();
        }

        $output = $this->runCommand();

        if ($this->afterCallback && is_callable($this->afterCallback)) {
            ($this->afterCallback)();
        }

        return $output;
    }

    /**
     * Run the actual command.
     */
    protected function runCommand(): string
    {
        $command = $this->buildCommand();

        if ($this->runInBackground) {
            $command .= ' > /dev/null 2>&1 &';
        }

        ob_start();
        $exitCode = null;
        passthru($command, $exitCode);
        $output = ob_get_clean();

        if ($this->output) {
            $mode = $this->shouldAppendOutput ? 'a' : 'w';
            file_put_contents($this->output, $output, $mode === 'a' ? FILE_APPEND : 0);
        }

        return $output ?: "Command executed with exit code: $exitCode";
    }

    /**
     * Build the full command string.
     */
    protected function buildCommand(): string
    {
        $command = $this->command;

        if ($this->user && !windows_os()) {
            $command = "sudo -u {$this->user} $command";
        }

        return $command;
    }

    /**
     * Get the command string.
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * Get the cron expression.
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * Get the timezone.
     */
    public function getTimezone(): string
    {
        return $this->timezone;
    }
}

/**
 * Determine if the current OS is Windows.
 */
function windows_os(): bool
{
    return PHP_OS_FAMILY === 'Windows';
}
