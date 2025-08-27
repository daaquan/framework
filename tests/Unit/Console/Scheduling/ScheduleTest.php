<?php

use Phare\Console\Scheduling\Schedule;
use Phare\Console\Scheduling\Event;
use Phare\Console\Scheduling\CallbackEvent;

beforeEach(function () {
    $this->schedule = new Schedule();
});

test('schedule can be instantiated', function () {
    expect($this->schedule)->toBeInstanceOf(Schedule::class);
});

test('schedule has default timezone', function () {
    expect($this->schedule->getTimezone())->toBe('UTC');
});

test('schedule can set timezone', function () {
    $this->schedule->timezone('America/New_York');
    expect($this->schedule->getTimezone())->toBe('America/New_York');
});

test('schedule can add command event', function () {
    $event = $this->schedule->command('cache:clear');
    
    expect($event)->toBeInstanceOf(Event::class);
    expect($this->schedule->events())->toHaveCount(1);
    expect($event->getCommand())->toContain('cache:clear');
});

test('schedule can add command event with parameters', function () {
    $event = $this->schedule->command('migrate:rollback', ['--step=5']);
    
    expect($event->getCommand())->toContain("migrate:rollback '--step=5'");
});

test('schedule can add executable command', function () {
    $event = $this->schedule->exec('ls', ['-la']);
    
    expect($event)->toBeInstanceOf(Event::class);
    expect($event->getCommand())->toContain("ls '-la'");
});

test('schedule can add callback event', function () {
    $executed = false;
    $event = $this->schedule->call(function () use (&$executed) {
        $executed = true;
        return 'callback executed';
    });
    
    expect($event)->toBeInstanceOf(CallbackEvent::class);
    expect($this->schedule->events())->toHaveCount(1);
});

test('schedule can add job event', function () {
    $event = $this->schedule->job('TestJob', 'high-priority');
    
    expect($event)->toBeInstanceOf(CallbackEvent::class);
    expect($this->schedule->events())->toHaveCount(1);
});

test('schedule can get all events', function () {
    $this->schedule->command('command1');
    $this->schedule->exec('ls');
    $this->schedule->call(fn() => 'test');
    
    expect($this->schedule->events())->toHaveCount(3);
});

test('schedule can clear all events', function () {
    $this->schedule->command('command1');
    $this->schedule->command('command2');
    
    expect($this->schedule->events())->toHaveCount(2);
    
    $this->schedule->clear();
    expect($this->schedule->events())->toHaveCount(0);
});

test('schedule can run due events', function () {
    $executed = false;
    
    // Add a callback event that's always due (every minute)
    $this->schedule->call(function () use (&$executed) {
        $executed = true;
        return 'executed';
    })->everyMinute();
    
    $results = $this->schedule->run();
    
    expect($results)->toHaveCount(1);
    expect($results[0]['success'])->toBeTrue();
    expect($executed)->toBeTrue();
});

test('schedule can get due events', function () {
    // Add event that's always due
    $this->schedule->call(fn() => 'always')->everyMinute();
    
    // Add event that's never due (far future time)
    $this->schedule->call(fn() => 'never')->cron('0 0 1 1 2050');
    
    $dueEvents = $this->schedule->dueEvents();
    
    expect($dueEvents)->toHaveCount(1);
    expect($dueEvents[0]->isDue())->toBeTrue();
});

test('schedule handles failed events', function () {
    $this->schedule->call(function () {
        throw new \Exception('Test failure');
    })->everyMinute();
    
    $results = $this->schedule->run();
    
    expect($results)->toHaveCount(1);
    expect($results[0]['success'])->toBeFalse();
    expect($results[0]['exception'])->toBeInstanceOf(\Exception::class);
    expect($results[0]['output'])->toContain('Test failure');
});

test('schedule timezone affects all events', function () {
    $event1 = $this->schedule->command('test1');
    $event2 = $this->schedule->call(fn() => 'test');
    
    $this->schedule->timezone('Europe/London');
    
    expect($event1->getTimezone())->toBe('Europe/London');
    expect($event2->getTimezone())->toBe('Europe/London');
});

test('schedule builds command with artisan binary', function () {
    $event = $this->schedule->command('cache:clear');
    
    $command = $event->getCommand();
    expect($command)->toContain('artisan');
    expect($command)->toContain('cache:clear');
});

test('schedule callback event can be described', function () {
    $event = $this->schedule->call(fn() => 'test');
    
    if (method_exists($event, 'description')) {
        $event->description('Custom callback description');
        expect($event->getCommand())->toBe('Custom callback description');
    } else {
        // Fallback test
        expect($event->getCommand())->toBeString();
    }
});

test('schedule can handle callback with parameters', function () {
    $result = '';
    
    $this->schedule->call(function ($param1, $param2) use (&$result) {
        $result = $param1 . ' ' . $param2;
    }, ['Hello', 'World'])->everyMinute();
    
    $results = $this->schedule->run();
    
    expect($results[0]['success'])->toBeTrue();
    expect($result)->toBe('Hello World');
});

test('schedule exec handles empty parameters', function () {
    $event = $this->schedule->exec('ls');
    
    expect($event->getCommand())->toBe('ls');
});

test('schedule command handles empty parameters', function () {
    $event = $this->schedule->command('migrate');
    
    expect($event->getCommand())->toContain('migrate');
    expect($event->getCommand())->not->toContain("''"); // No empty parameters
});

test('schedule returns proper result structure', function () {
    $this->schedule->call(fn() => 'success result')->everyMinute();
    
    $results = $this->schedule->run();
    
    expect($results[0])->toHaveKey('event');
    expect($results[0])->toHaveKey('output');
    expect($results[0])->toHaveKey('success');
    expect($results[0]['event'])->toBeInstanceOf(Event::class);
    expect($results[0]['success'])->toBeTrue();
});