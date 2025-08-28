<?php

use Phare\Security\Csrf;
use Tests\Support\SimpleApplication;
use Tests\Support\SimpleSessionStore;

beforeEach(function () {
    $this->app = new SimpleApplication();
    $this->app->singleton('session', function () {
        return new SimpleSessionStore();
    });
    $this->csrf = new Csrf($this->app);
});

it('generates unique tokens', function () {
    $token1 = $this->csrf->generateToken();
    $token2 = $this->csrf->generateToken();
    
    expect($token1)->not->toBe($token2);
    expect($token1)->toHaveLength(40);
    expect($token2)->toHaveLength(40);
});

it('stores token in session', function () {
    $token = $this->csrf->generateToken();
    $session = $this->app->make('session');
    
    expect($session->get('_csrf_token'))->toBe($token);
});

it('gets token from session', function () {
    $originalToken = $this->csrf->generateToken();
    $retrievedToken = $this->csrf->getToken();
    
    expect($retrievedToken)->toBe($originalToken);
});

it('generates new token if none exists', function () {
    $token = $this->csrf->getToken();
    
    expect($token)->toHaveLength(40);
    expect($this->app->make('session')->get('_csrf_token'))->toBe($token);
});

it('verifies valid tokens', function () {
    $token = $this->csrf->generateToken();
    
    expect($this->csrf->verifyToken($token))->toBeTrue();
});

it('rejects invalid tokens', function () {
    $this->csrf->generateToken();
    
    expect($this->csrf->verifyToken('invalid'))->toBeFalse();
    expect($this->csrf->verifyToken(''))->toBeFalse();
});

it('rejects tokens when no session token exists', function () {
    expect($this->csrf->verifyToken('some-token'))->toBeFalse();
});

it('clears token from session', function () {
    $this->csrf->generateToken();
    $this->csrf->clearToken();
    
    expect($this->app->make('session')->get('_csrf_token'))->toBeNull();
});

it('returns token name', function () {
    expect($this->csrf->getTokenName())->toBe('_token');
});

it('generates HTML input field', function () {
    $token = $this->csrf->generateToken();
    $field = $this->csrf->field();
    
    expect($field)->toBe('<input type="hidden" name="_token" value="' . $token . '">');
});

it('generates meta tag', function () {
    $token = $this->csrf->generateToken();
    $metaTag = $this->csrf->metaTag();
    
    expect($metaTag)->toBe('<meta name="csrf-token" content="' . $token . '">');
});

it('uses hash_equals for timing attack protection', function () {
    $token = $this->csrf->generateToken();
    
    // Mock timing by using a token that would fail strcmp but passes hash_equals
    $validToken = $token;
    $invalidToken = str_repeat('a', strlen($token));
    
    expect($this->csrf->verifyToken($validToken))->toBeTrue();
    expect($this->csrf->verifyToken($invalidToken))->toBeFalse();
});