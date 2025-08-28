<?php

use Phare\Auth\Sanctum\PersonalAccessToken;
use Phare\Auth\Sanctum\Sanctum;
use Phare\Auth\Sanctum\SanctumGuard;

test('sanctum guard can authenticate user with valid token', function () {
    $request = Mockery::mock('Phalcon\Http\RequestInterface');
    $request->shouldReceive('getHeader')
        ->with('Authorization')
        ->andReturn('Bearer valid-token');

    $user = Mockery::mock('User');
    $user->shouldReceive('withAccessToken')->andReturnSelf();

    $token = Mockery::mock(PersonalAccessToken::class);
    $token->shouldReceive('isExpired')->andReturn(false);
    $token->shouldReceive('touch');
    $token->tokenable = $user;

    Sanctum::shouldReceive('findToken')
        ->with('valid-token')
        ->andReturn($token);

    $guard = new SanctumGuard($request);
    $authenticatedUser = $guard->user();

    expect($authenticatedUser)->toBe($user);
});

test('sanctum guard returns null for invalid token', function () {
    $request = Mockery::mock('Phalcon\Http\RequestInterface');
    $request->shouldReceive('getHeader')
        ->with('Authorization')
        ->andReturn('Bearer invalid-token');

    Sanctum::shouldReceive('findToken')
        ->with('invalid-token')
        ->andReturn(null);

    $guard = new SanctumGuard($request);
    $user = $guard->user();

    expect($user)->toBeNull();
});

test('sanctum guard returns null for expired token', function () {
    $request = Mockery::mock('Phalcon\Http\RequestInterface');
    $request->shouldReceive('getHeader')
        ->with('Authorization')
        ->andReturn('Bearer expired-token');

    $token = Mockery::mock(PersonalAccessToken::class);
    $token->shouldReceive('isExpired')->andReturn(true);

    Sanctum::shouldReceive('findToken')
        ->with('expired-token')
        ->andReturn($token);

    $guard = new SanctumGuard($request);
    $user = $guard->user();

    expect($user)->toBeNull();
});

test('sanctum guard returns null without authorization header', function () {
    $request = Mockery::mock('Phalcon\Http\RequestInterface');
    $request->shouldReceive('getHeader')
        ->with('Authorization')
        ->andReturn(null);

    $guard = new SanctumGuard($request);
    $user = $guard->user();

    expect($user)->toBeNull();
});

test('sanctum guard can validate credentials', function () {
    $request = Mockery::mock('Phalcon\Http\RequestInterface');
    $request->shouldReceive('getHeader')
        ->with('Authorization')
        ->andReturn('Bearer valid-token');

    $user = Mockery::mock('User');
    $user->shouldReceive('withAccessToken')->andReturnSelf();

    $token = Mockery::mock(PersonalAccessToken::class);
    $token->shouldReceive('isExpired')->andReturn(false);
    $token->shouldReceive('touch');
    $token->tokenable = $user;

    Sanctum::shouldReceive('findToken')
        ->with('valid-token')
        ->andReturn($token);

    $guard = new SanctumGuard($request);

    expect($guard->validate())->toBeTrue();
    expect($guard->check())->toBeTrue();
    expect($guard->guest())->toBeFalse();
});

test('sanctum guard can get user id', function () {
    $request = Mockery::mock('Phalcon\Http\RequestInterface');
    $request->shouldReceive('getHeader')
        ->with('Authorization')
        ->andReturn('Bearer valid-token');

    $user = Mockery::mock('User');
    $user->shouldReceive('withAccessToken')->andReturnSelf();
    $user->shouldReceive('getKey')->andReturn(123);

    $token = Mockery::mock(PersonalAccessToken::class);
    $token->shouldReceive('isExpired')->andReturn(false);
    $token->shouldReceive('touch');
    $token->tokenable = $user;

    Sanctum::shouldReceive('findToken')
        ->with('valid-token')
        ->andReturn($token);

    $guard = new SanctumGuard($request);

    expect($guard->id())->toBe(123);
});

test('sanctum guard handles malformed bearer token', function () {
    $request = Mockery::mock('Phalcon\Http\RequestInterface');
    $request->shouldReceive('getHeader')
        ->with('Authorization')
        ->andReturn('InvalidFormat token');

    $guard = new SanctumGuard($request);
    $user = $guard->user();

    expect($user)->toBeNull();
});
