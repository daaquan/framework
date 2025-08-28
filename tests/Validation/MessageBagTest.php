<?php

use Phare\Validation\MessageBag;

it('can create empty message bag', function () {
    $bag = new MessageBag();
    
    expect($bag->isEmpty())->toBe(true);
    expect($bag->isNotEmpty())->toBe(false);
    expect($bag->count())->toBe(0);
});

it('can create message bag with initial messages', function () {
    $messages = [
        'name' => ['Name is required'],
        'email' => ['Email is invalid', 'Email must be unique']
    ];
    
    $bag = new MessageBag($messages);
    
    expect($bag->isEmpty())->toBe(false);
    expect($bag->isNotEmpty())->toBe(true);
    expect($bag->count())->toBe(3);
});

it('can add single message', function () {
    $bag = new MessageBag();
    $bag->add('name', 'Name is required');
    
    expect($bag->has('name'))->toBe(true);
    expect($bag->first('name'))->toBe('Name is required');
    expect($bag->count())->toBe(1);
});

it('can add multiple messages to same key', function () {
    $bag = new MessageBag();
    $bag->add('email', 'Email is required');
    $bag->add('email', 'Email must be valid');
    
    expect($bag->has('email'))->toBe(true);
    expect($bag->first('email'))->toBe('Email is required');
    expect($bag->get('email'))->toBe(['Email is required', 'Email must be valid']);
    expect($bag->count())->toBe(2);
});

it('can check if key has messages', function () {
    $bag = new MessageBag(['name' => ['Name is required']]);
    
    expect($bag->has('name'))->toBe(true);
    expect($bag->has('email'))->toBe(false);
    expect($bag->has('nonexistent'))->toBe(false);
});

it('can get first message for key', function () {
    $bag = new MessageBag([
        'email' => ['First error', 'Second error']
    ]);
    
    expect($bag->first('email'))->toBe('First error');
    expect($bag->first('nonexistent'))->toBe(null);
});

it('can get first message from any key', function () {
    $bag = new MessageBag([
        'name' => ['Name error'],
        'email' => ['Email error']
    ]);
    
    $first = $bag->first();
    expect($first)->toBeIn(['Name error', 'Email error']);
});

it('can get all messages for key', function () {
    $bag = new MessageBag([
        'email' => ['Required', 'Invalid format', 'Must be unique']
    ]);
    
    $messages = $bag->get('email');
    expect($messages)->toBe(['Required', 'Invalid format', 'Must be unique']);
    
    $empty = $bag->get('nonexistent');
    expect($empty)->toBe([]);
});

it('can get all messages with format', function () {
    $bag = new MessageBag(['name' => ['Required']]);
    
    $formatted = $bag->get('name', '<li>:message</li>');
    expect($formatted)->toBe(['<li>Required</li>']);
});

it('can get all messages from all keys', function () {
    $bag = new MessageBag([
        'name' => ['Name required'],
        'email' => ['Email invalid', 'Email taken']
    ]);
    
    $all = $bag->all();
    expect($all)->toBe(['Name required', 'Email invalid', 'Email taken']);
});

it('can get all messages with format', function () {
    $bag = new MessageBag(['name' => ['Required'], 'email' => ['Invalid']]);
    
    $formatted = $bag->all('<p>:message</p>');
    expect($formatted)->toBe(['<p>Required</p>', '<p>Invalid</p>']);
});

it('can get all message keys', function () {
    $bag = new MessageBag([
        'name' => ['Name error'],
        'email' => ['Email error'],
        'password' => ['Password error']
    ]);
    
    $keys = $bag->keys();
    expect($keys)->toBe(['name', 'email', 'password']);
});

it('can merge with another message bag', function () {
    $bag1 = new MessageBag(['name' => ['Name required']]);
    $bag2 = new MessageBag(['email' => ['Email invalid']]);
    
    $bag1->merge($bag2);
    
    expect($bag1->has('name'))->toBe(true);
    expect($bag1->has('email'))->toBe(true);
    expect($bag1->count())->toBe(2);
});

it('can merge with array of messages', function () {
    $bag = new MessageBag(['name' => ['Name required']]);
    
    $bag->merge(['email' => ['Email invalid', 'Email taken']]);
    
    expect($bag->has('name'))->toBe(true);
    expect($bag->has('email'))->toBe(true);
    expect($bag->count())->toBe(3);
});

it('merges messages to same key', function () {
    $bag1 = new MessageBag(['email' => ['First error']]);
    $bag2 = new MessageBag(['email' => ['Second error']]);
    
    $bag1->merge($bag2);
    
    expect($bag1->get('email'))->toBe(['First error', 'Second error']);
});

it('can convert to array', function () {
    $messages = [
        'name' => ['Name required'],
        'email' => ['Email invalid']
    ];
    
    $bag = new MessageBag($messages);
    
    expect($bag->toArray())->toBe($messages);
});

it('can be json serialized', function () {
    $messages = ['name' => ['Required']];
    $bag = new MessageBag($messages);
    
    expect($bag->jsonSerialize())->toBe($messages);
    expect(json_encode($bag))->toBe('{"name":["Required"]}');
});

it('can convert to string', function () {
    $bag = new MessageBag(['name' => ['Required']]);
    
    expect((string) $bag)->toBe('{"name":["Required"]}');
});

it('implements array access interface', function () {
    $bag = new MessageBag();
    
    // offsetSet
    $bag['name'] = 'Name required';
    expect($bag->has('name'))->toBe(true);
    
    // offsetExists
    expect(isset($bag['name']))->toBe(true);
    expect(isset($bag['nonexistent']))->toBe(false);
    
    // offsetGet
    expect($bag['name'])->toBe(['Name required']);
    
    // offsetUnset
    unset($bag['name']);
    expect($bag->has('name'))->toBe(false);
});

it('implements countable interface', function () {
    $bag = new MessageBag([
        'name' => ['Error 1'],
        'email' => ['Error 2', 'Error 3']
    ]);
    
    expect(count($bag))->toBe(3);
});

it('handles empty first message correctly', function () {
    $bag = new MessageBag();
    
    expect($bag->first())->toBe(null);
    expect($bag->first('nonexistent'))->toBe(null);
});

it('handles complex message structures', function () {
    $bag = new MessageBag();
    $bag->add('user.name', 'Name is required');
    $bag->add('user.email', 'Email is invalid');
    $bag->add('user.profile.bio', 'Bio is too long');
    
    expect($bag->has('user.name'))->toBe(true);
    expect($bag->has('user.email'))->toBe(true);
    expect($bag->has('user.profile.bio'))->toBe(true);
    expect($bag->count())->toBe(3);
});