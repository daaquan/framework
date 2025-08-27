<?php

use Phare\Console\Scheduling\CallbackEvent;

beforeEach(function () {
    $this->callback = function () {
        return 'callback result';
    };
    $this->event = new CallbackEvent('UTC', $this->callback);
});

test('callback event can be instantiated', function () {
    expect($this->event)->toBeInstanceOf(CallbackEvent::class);
});

test('callback event has default description', function () {
    expect($this->event->getCommand())->toBe('Callback');
});

test('callback event can set description', function () {
    $this->event->description('Custom callback task');
    expect($this->event->getCommand())->toBe('Custom callback task');
});

test('callback event can execute callback', function () {
    $executed = false;
    $event = new CallbackEvent('UTC', function () use (&$executed) {
        $executed = true;
        return 'executed';
    });
    
    $event->everyMinute(); // Make it due
    $result = $event->run();
    
    expect($executed)->toBeTrue();
    expect($result)->toBe('executed');
});

test('callback event handles callback without return value', function () {
    $executed = false;
    $event = new CallbackEvent('UTC', function () use (&$executed) {
        $executed = true;
        // No return value
    });
    
    $event->everyMinute();
    $result = $event->run();
    
    expect($executed)->toBeTrue();
    expect($result)->toBe('Callback executed successfully');
});

test('callback event handles callback exceptions', function () {
    $event = new CallbackEvent('UTC', function () {
        throw new \Exception('Callback failed');
    });
    
    expect(function () {
        $event->run();
    })->toThrow(\Exception::class, 'Callback failed');
});

test('callback event can write output to file', function () {
    $outputFile = tempnam(sys_get_temp_dir(), 'test_callback_output');
    
    $event = new CallbackEvent('UTC', function () {
        return 'callback output';
    });
    
    $event->sendOutputTo($outputFile);
    $result = $event->run();
    
    expect($result)->toBe('callback output');
    expect(file_exists($outputFile))->toBeTrue();
    expect(file_get_contents($outputFile))->toContain('callback output');
    
    // Cleanup
    unlink($outputFile);
});

test('callback event can append output to file', function () {
    $outputFile = tempnam(sys_get_temp_dir(), 'test_callback_append');
    file_put_contents($outputFile, "existing content\n");
    
    $event = new CallbackEvent('UTC', function () {
        return 'appended output';
    });
    
    $event->appendOutputTo($outputFile);
    $event->run();
    
    $content = file_get_contents($outputFile);
    expect($content)->toContain('existing content');
    expect($content)->toContain('appended output');
    
    // Cleanup
    unlink($outputFile);
});

test('callback event handles file output errors gracefully', function () {
    $event = new CallbackEvent('UTC', function () {
        throw new \Exception('Task error');
    });
    
    $outputFile = tempnam(sys_get_temp_dir(), 'test_callback_error');
    $event->sendOutputTo($outputFile);
    
    expect(function () {
        $event->run();
    })->toThrow(\Exception::class, 'Task error');
    
    expect(file_exists($outputFile))->toBeTrue();
    expect(file_get_contents($outputFile))->toContain('Callback failed: Task error');
    
    // Cleanup
    unlink($outputFile);
});

test('callback event accepts closure', function () {
    $closure = function () {
        return 'closure result';
    };
    
    $event = new CallbackEvent('UTC', $closure);
    $result = $event->run();
    
    expect($result)->toBe('closure result');
});

test('callback event accepts callable array', function () {
    $object = new class {
        public function method() {
            return 'method result';
        }
    };
    
    $event = new CallbackEvent('UTC', [$object, 'method']);
    $result = $event->run();
    
    expect($result)->toBe('method result');
});

test('callback event accepts callable string', function () {
    if (!function_exists('test_callback_function')) {
        function test_callback_function() {
            return 'function result';
        }
    }
    
    $event = new CallbackEvent('UTC', 'test_callback_function');
    $result = $event->run();
    
    expect($result)->toBe('function result');
});

test('callback event inherits scheduling methods', function () {
    $event = new CallbackEvent('UTC', fn() => 'test');
    
    $result = $event->daily()->timezone('America/New_York');
    
    expect($result)->toBe($event);
    expect($event->getExpression())->toBe('0 0 * * *');
    expect($event->getTimezone())->toBe('America/New_York');
});

test('callback event can be chained with description', function () {
    $event = new CallbackEvent('UTC', fn() => 'test');
    
    $result = $event
        ->description('Daily cleanup task')
        ->daily()
        ->timezone('UTC');
    
    expect($result)->toBe($event);
    expect($event->getCommand())->toBe('Daily cleanup task');
    expect($event->getExpression())->toBe('0 0 * * *');
});

test('callback event description is fluent', function () {
    $result = $this->event->description('New description');
    
    expect($result)->toBe($this->event);
    expect($this->event->getCommand())->toBe('New description');
});

test('callback event handles non-string return values', function () {
    $event = new CallbackEvent('UTC', function () {
        return ['array', 'result'];
    });
    
    $result = $event->run();
    
    expect($result)->toBe('Callback executed successfully');
});

test('callback event handles null return value', function () {
    $event = new CallbackEvent('UTC', function () {
        return null;
    });
    
    $result = $event->run();
    
    expect($result)->toBe('Callback executed successfully');
});