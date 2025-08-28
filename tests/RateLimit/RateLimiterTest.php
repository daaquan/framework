<?php

use Phare\RateLimit\Limit;
use Phare\RateLimit\RateLimiter;
use Phare\RateLimit\TooManyRequestsException;
use Tests\Support\SimpleApplication;

beforeEach(function () {
    $this->app = new SimpleApplication();
    $this->app->singleton('cache', function () {
        return new class()
        {
            private array $data = [];

            public function get($key, $default = null)
            {
                return $this->data[$key] ?? $default;
            }

            public function put($key, $value, $seconds = null)
            {
                $this->data[$key] = $value;

                return true;
            }

            public function add($key, $value, $seconds = null)
            {
                if (!isset($this->data[$key])) {
                    $this->data[$key] = $value;

                    return true;
                }

                return false;
            }

            public function increment($key, $value = 1)
            {
                $current = $this->data[$key] ?? 0;
                $this->data[$key] = $current + $value;

                return $this->data[$key];
            }

            public function forget($key)
            {
                unset($this->data[$key]);

                return true;
            }

            public function has($key)
            {
                return isset($this->data[$key]);
            }
        };
    });

    $this->limiter = new RateLimiter($this->app);
});

it('tracks attempts correctly', function () {
    $key = 'test-key';

    expect($this->limiter->attempts($key))->toBe(0);

    $this->limiter->hit($key);
    expect($this->limiter->attempts($key))->toBe(1);

    $this->limiter->hit($key);
    expect($this->limiter->attempts($key))->toBe(2);
});

it('detects too many attempts', function () {
    $key = 'test-key';
    $maxAttempts = 3;

    expect($this->limiter->tooManyAttempts($key, $maxAttempts))->toBeFalse();

    for ($i = 0; $i < $maxAttempts; $i++) {
        $this->limiter->hit($key);
    }

    expect($this->limiter->tooManyAttempts($key, $maxAttempts))->toBeTrue();
});

it('calculates remaining attempts', function () {
    $key = 'test-key';
    $maxAttempts = 5;

    expect($this->limiter->remaining($key, $maxAttempts))->toBe(5);

    $this->limiter->hit($key);
    expect($this->limiter->remaining($key, $maxAttempts))->toBe(4);

    $this->limiter->hit($key);
    expect($this->limiter->remaining($key, $maxAttempts))->toBe(3);
});

it('can clear attempts', function () {
    $key = 'test-key';

    $this->limiter->hit($key);
    $this->limiter->hit($key);
    expect($this->limiter->attempts($key))->toBe(2);

    $this->limiter->clear($key);
    expect($this->limiter->attempts($key))->toBe(0);
});

it('executes callback when within limits', function () {
    $key = 'test-key';
    $executed = false;

    $result = $this->limiter->attempt($key, 5, 1, function () use (&$executed) {
        $executed = true;

        return 'success';
    });

    expect($executed)->toBeTrue();
    expect($result)->toBe('success');
});

it('throws exception when limits exceeded', function () {
    $key = 'test-key';
    $maxAttempts = 2;

    // Use up the attempts
    for ($i = 0; $i < $maxAttempts; $i++) {
        $this->limiter->hit($key);
    }

    expect(fn () => $this->limiter->attempt($key, $maxAttempts, 1, fn () => 'should not execute'))
        ->toThrow(TooManyRequestsException::class);
});

it('registers named limiters', function () {
    $this->limiter->for('api', function ($request) {
        return Limit::perMinute(100);
    });

    $limiterCallback = $this->limiter->limiter('api');
    expect($limiterCallback)->toBeInstanceOf(\Closure::class);

    expect($this->limiter->limiter('nonexistent'))->toBeNull();
});

it('creates different limit types', function () {
    $perMinute = Limit::perMinute(60);
    expect($perMinute->maxAttempts)->toBe(60);
    expect($perMinute->decayMinutes)->toBe(1);

    $perHour = Limit::perHour(1000);
    expect($perHour->maxAttempts)->toBe(1000);
    expect($perHour->decayMinutes)->toBe(60);

    $perDay = Limit::perDay(10000);
    expect($perDay->maxAttempts)->toBe(10000);
    expect($perDay->decayMinutes)->toBe(1440);

    $none = Limit::none();
    expect($none->maxAttempts)->toBe(PHP_INT_MAX);
});

it('allows custom limit keys', function () {
    $limit = Limit::perMinute(100)->by('custom-key');
    expect($limit->key)->toBe('custom-key');
});

it('cleans rate limiter keys', function () {
    $dirtyKey = 'user@example.com&action=login';
    $cleanKey = $this->limiter->cleanRateLimiterKey($dirtyKey);

    expect($cleanKey)->not->toContain('&');
    expect($cleanKey)->toContain('user');
});
