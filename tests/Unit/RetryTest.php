<?php

it('retries the operation up to a maximum number of times', function () {
    $tries = 0;
    $maxRetries = 3;
    $sleep = 100; // 100 milliseconds

    // Callback that always throws an exception
    $operation = function () use (&$tries) {
        $tries++;
        throw new Exception('Error Processing Request');
    };

    try {
        retry($maxRetries, $operation, $sleep);
    } catch (Exception $e) {
        // Verify that the maximum retry count was reached
        expect($tries)->toBe($maxRetries);
    }
});

it('does not retry the operation if not necessary', function () {
    $tries = 0;

    // Callback that succeeds on the first attempt
    $operation = function () use (&$tries) {
        $tries++;

        return 'Success';
    };

    $response = retry(3, $operation, 100);

    // Ensure the callback was executed only once
    expect($tries)->toBe(1);
    // Verify the return value
    expect($response)->toBe('Success');
});

it('waits for the specified time before retrying', function () {
    $tries = 0;
    $maxRetries = 3;
    $sleep = 100; // 100 milliseconds

    // Callback that always throws an exception
    $operation = function () use (&$tries) {
        $tries++;
        throw new Exception('Error Processing Request');
    };

    $start = microtime(true);
    try {
        retry($maxRetries, $operation, $sleep);
    } catch (Exception $e) {
        $end = microtime(true);
        $elapsed = $end - $start;

        // Ensure the elapsed time accounts for the sleep duration
        expect($elapsed)->toBeGreaterThanOrEqual($sleep * ($maxRetries - 1) / 1000);
    }
});
