<?php

use Phare\Console\Scheduling\Event;

beforeEach(function () {
    $this->event = new Event('UTC', 'test command');
});

test('event can be instantiated', function () {
    expect($this->event)->toBeInstanceOf(Event::class);
    expect($this->event->getCommand())->toBe('test command');
    expect($this->event->getTimezone())->toBe('UTC');
});

test('event has default cron expression', function () {
    expect($this->event->getExpression())->toBe('* * * * *');
});

test('event can set custom cron expression', function () {
    $this->event->cron('0 0 * * *');
    expect($this->event->getExpression())->toBe('0 0 * * *');
});

test('event can be scheduled every minute', function () {
    $this->event->everyMinute();
    expect($this->event->getExpression())->toBe('* * * * *');
});

test('event can be scheduled every two minutes', function () {
    $this->event->everyTwoMinutes();
    expect($this->event->getExpression())->toBe('*/2 * * * *');
});

test('event can be scheduled every five minutes', function () {
    $this->event->everyFiveMinutes();
    expect($this->event->getExpression())->toBe('*/5 * * * *');
});

test('event can be scheduled every ten minutes', function () {
    $this->event->everyTenMinutes();
    expect($this->event->getExpression())->toBe('*/10 * * * *');
});

test('event can be scheduled every fifteen minutes', function () {
    $this->event->everyFifteenMinutes();
    expect($this->event->getExpression())->toBe('*/15 * * * *');
});

test('event can be scheduled every thirty minutes', function () {
    $this->event->everyThirtyMinutes();
    expect($this->event->getExpression())->toBe('0,30 * * * *');
});

test('event can be scheduled hourly', function () {
    $this->event->hourly();
    expect($this->event->getExpression())->toBe('0 * * * *');
});

test('event can be scheduled hourly at specific offset', function () {
    $this->event->hourlyAt(15);
    expect($this->event->getExpression())->toBe('15 * * * *');
});

test('event can be scheduled daily', function () {
    $this->event->daily();
    expect($this->event->getExpression())->toBe('0 0 * * *');
});

test('event can be scheduled daily at specific time', function () {
    $this->event->dailyAt('14:30');
    expect($this->event->getExpression())->toBe('30 14 * * *');
});

test('event can be scheduled daily at time without minutes', function () {
    $this->event->dailyAt('14');
    expect($this->event->getExpression())->toBe('0 14 * * *');
});

test('event can be scheduled twice daily', function () {
    $this->event->twiceDaily(8, 20);
    expect($this->event->getExpression())->toBe('0 8,20 * * *');
});

test('event can be scheduled twice daily with defaults', function () {
    $this->event->twiceDaily();
    expect($this->event->getExpression())->toBe('0 1,13 * * *');
});

test('event can be scheduled weekly', function () {
    $this->event->weekly();
    expect($this->event->getExpression())->toBe('0 0 * * 0');
});

test('event can be scheduled weekly on specific day', function () {
    $this->event->weeklyOn(3, '14:30'); // Wednesday at 2:30 PM
    expect($this->event->getExpression())->toBe('30 14 * * 3');
});

test('event can be scheduled monthly', function () {
    $this->event->monthly();
    expect($this->event->getExpression())->toBe('0 0 1 * *');
});

test('event can be scheduled monthly on specific day', function () {
    $this->event->monthlyOn(15, '09:00');
    expect($this->event->getExpression())->toBe('0 9 15 * *');
});

test('event can be scheduled yearly', function () {
    $this->event->yearly();
    expect($this->event->getExpression())->toBe('0 0 1 1 *');
});

test('event can set specific days', function () {
    $this->event->days(['monday', 'wednesday', 'friday']);
    expect($this->event->getExpression())->toBe('* * * * 1,3,5');
});

test('event can set days with abbreviations', function () {
    $this->event->days(['mon', 'wed', 'fri']);
    expect($this->event->getExpression())->toBe('* * * * 1,3,5');
});

test('event can set days with numbers', function () {
    $this->event->days([1, 3, 5]);
    expect($this->event->getExpression())->toBe('* * * * 1,3,5');
});

test('event can set single day', function () {
    $this->event->days('sunday');
    expect($this->event->getExpression())->toBe('* * * * 0');
});

test('event can set timezone', function () {
    $this->event->timezone('America/New_York');
    expect($this->event->getTimezone())->toBe('America/New_York');
});

test('event can set user', function () {
    $user = (new ReflectionClass($this->event))->getProperty('user');
    $user->setAccessible(true);

    $this->event->user('www-data');
    expect($user->getValue($this->event))->toBe('www-data');
});

test('event can set environments', function () {
    $environments = (new ReflectionClass($this->event))->getProperty('environments');
    $environments->setAccessible(true);

    $this->event->environments(['production', 'staging']);
    expect($environments->getValue($this->event))->toBe(['production', 'staging']);
});

test('event can set single environment', function () {
    $environments = (new ReflectionClass($this->event))->getProperty('environments');
    $environments->setAccessible(true);

    $this->event->environments('production');
    expect($environments->getValue($this->event))->toBe(['production']);
});

test('event can run in maintenance mode', function () {
    $maintenance = (new ReflectionClass($this->event))->getProperty('evenInMaintenanceMode');
    $maintenance->setAccessible(true);

    $this->event->evenInMaintenanceMode();
    expect($maintenance->getValue($this->event))->toBeTrue();
});

test('event can run in background', function () {
    $background = (new ReflectionClass($this->event))->getProperty('runInBackground');
    $background->setAccessible(true);

    $this->event->runInBackground();
    expect($background->getValue($this->event))->toBeTrue();
});

test('event can set output file', function () {
    $output = (new ReflectionClass($this->event))->getProperty('output');
    $output->setAccessible(true);

    $this->event->sendOutputTo('/var/log/task.log');
    expect($output->getValue($this->event))->toBe('/var/log/task.log');
});

test('event can append output to file', function () {
    $output = (new ReflectionClass($this->event))->getProperty('output');
    $shouldAppend = (new ReflectionClass($this->event))->getProperty('shouldAppendOutput');
    $output->setAccessible(true);
    $shouldAppend->setAccessible(true);

    $this->event->appendOutputTo('/var/log/task.log');
    expect($output->getValue($this->event))->toBe('/var/log/task.log');
    expect($shouldAppend->getValue($this->event))->toBeTrue();
});

test('event can register before callback', function () {
    $beforeCallback = (new ReflectionClass($this->event))->getProperty('beforeCallback');
    $beforeCallback->setAccessible(true);

    $callback = fn () => 'before';
    $this->event->before($callback);
    expect($beforeCallback->getValue($this->event))->toBe($callback);
});

test('event can register after callback', function () {
    $afterCallback = (new ReflectionClass($this->event))->getProperty('afterCallback');
    $afterCallback->setAccessible(true);

    $callback = fn () => 'after';
    $this->event->after($callback);
    expect($afterCallback->getValue($this->event))->toBe($callback);
});

test('event can register when filter', function () {
    $filters = (new ReflectionClass($this->event))->getProperty('filters');
    $filters->setAccessible(true);

    $callback = fn () => true;
    $this->event->when($callback);
    expect($filters->getValue($this->event))->toContain($callback);
});

test('event can register skip filter', function () {
    $rejects = (new ReflectionClass($this->event))->getProperty('rejects');
    $rejects->setAccessible(true);

    $callback = fn () => false;
    $this->event->skip($callback);
    expect($rejects->getValue($this->event))->toContain($callback);
});

test('event is due when filters pass', function () {
    $this->event->everyMinute(); // Always due
    expect($this->event->isDue())->toBeTrue();
});

test('event is not due when when filter fails', function () {
    $this->event->everyMinute()->when(fn () => false);
    expect($this->event->isDue())->toBeFalse();
});

test('event is not due when skip filter passes', function () {
    $this->event->everyMinute()->skip(fn () => true);
    expect($this->event->isDue())->toBeFalse();
});

test('event cron expression matching works for wildcard', function () {
    $this->event->cron('* * * * *');
    expect($this->event->isDue())->toBeTrue();
});

test('event can chain method calls', function () {
    $result = $this->event
        ->daily()
        ->timezone('UTC')
        ->user('www-data')
        ->when(fn () => true);

    expect($result)->toBe($this->event);
    expect($this->event->getExpression())->toBe('0 0 * * *');
});

test('windows os helper function works', function () {
    expect(function_exists('Phare\Console\Scheduling\windows_os'))->toBeTrue();

    $isWindows = \Phare\Console\Scheduling\windows_os();
    expect($isWindows)->toBeBool();
});
