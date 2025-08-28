<?php

use Phare\Container\Container;
use Phare\View\View;

beforeEach(function () {
    $this->container = new Container();
    $this->view = new View($this->container);
});

test('view can be instantiated', function () {
    expect($this->view)->toBeInstanceOf(View::class);
});

test('view can add data with string key', function () {
    $result = $this->view->with('key', 'value');

    expect($result)->toBe($this->view);
    expect($this->view->get('key'))->toBe('value');
});

test('view can add data with array', function () {
    $data = ['key1' => 'value1', 'key2' => 'value2'];
    $this->view->with($data);

    expect($this->view->get('key1'))->toBe('value1');
    expect($this->view->get('key2'))->toBe('value2');
});

test('view can get data with default value', function () {
    expect($this->view->get('nonexistent'))->toBeNull();
    expect($this->view->get('nonexistent', 'default'))->toBe('default');
});

test('view can check if data exists', function () {
    $this->view->with('exists', 'value');

    expect($this->view->has('exists'))->toBeTrue();
    expect($this->view->has('nonexistent'))->toBeFalse();
});

test('view can get all data', function () {
    $this->view->with('key1', 'value1');
    $this->view->with('key2', 'value2');
    $this->view->share('shared', 'shared_value');

    $data = $this->view->getData();

    expect($data)->toHaveKey('key1');
    expect($data)->toHaveKey('key2');
    expect($data)->toHaveKey('shared');
    expect($data['shared'])->toBe('shared_value');
});

test('view can set and get view name', function () {
    expect($this->view->getView())->toBeNull();

    $result = $this->view->setView('test.view');

    expect($result)->toBe($this->view);
    expect($this->view->getView())->toBe('test.view');
});

test('view can share data', function () {
    $this->view->share('shared_key', 'shared_value');

    expect($this->view->get('shared_key'))->toBe('shared_value');
});

test('view can get shared data', function () {
    $this->view->share('shared1', 'value1');
    $this->view->share('shared2', 'value2');

    $shared = $this->view->getShared();

    expect($shared)->toHaveKey('shared1');
    expect($shared)->toHaveKey('shared2');
    expect($shared['shared1'])->toBe('value1');
});

test('view shared data takes precedence over regular data', function () {
    $this->view->with('key', 'regular');
    $this->view->share('key', 'shared');

    expect($this->view->get('key'))->toBe('shared');
});

test('view render throws exception when no view is set', function () {
    expect(fn () => $this->view->render())
        ->toThrow(InvalidArgumentException::class, 'No view specified.');
});

test('view render returns string when view is set', function () {
    $this->view->setView('test.view');
    $this->view->with('data', 'value');

    $result = $this->view->render();

    expect($result)->toBeString();
    expect($result)->toContain('test.view');
    expect($result)->toContain('data');
});

test('view toString returns render result', function () {
    $this->view->setView('test.view');

    $string = (string)$this->view;

    expect($string)->toBeString();
    expect($string)->toContain('test.view');
});

test('view toString returns empty string on exception', function () {
    // No view set, should cause exception in render
    $string = (string)$this->view;

    expect($string)->toBe('');
});

test('view supports magic get method', function () {
    $this->view->with('magic_key', 'magic_value');

    expect($this->view->magic_key)->toBe('magic_value');
});

test('view supports magic set method', function () {
    $this->view->magic_key = 'magic_value';

    expect($this->view->get('magic_key'))->toBe('magic_value');
});

test('view supports magic isset method', function () {
    $this->view->with('exists', 'value');

    expect(isset($this->view->exists))->toBeTrue();
    expect(isset($this->view->nonexistent))->toBeFalse();
});

test('view data merges correctly', function () {
    $this->view->share('shared', 'shared_value');
    $this->view->with('local', 'local_value');
    $this->view->with('override', 'local_override');
    $this->view->share('override', 'shared_override');

    $data = $this->view->getData();

    expect($data['shared'])->toBe('shared_value');
    expect($data['local'])->toBe('local_value');
    expect($data['override'])->toBe('local_override'); // Local data wins
});

test('view handles complex data types', function () {
    $object = new stdClass();
    $object->property = 'value';

    $array = ['nested' => ['deep' => 'value']];

    $this->view->with('object', $object);
    $this->view->with('array', $array);

    expect($this->view->get('object'))->toBe($object);
    expect($this->view->get('array'))->toBe($array);
    expect($this->view->get('array')['nested']['deep'])->toBe('value');
});

test('view with method is fluent', function () {
    $result = $this->view
        ->with('key1', 'value1')
        ->with('key2', 'value2')
        ->setView('test.view');

    expect($result)->toBe($this->view);
    expect($this->view->get('key1'))->toBe('value1');
    expect($this->view->get('key2'))->toBe('value2');
    expect($this->view->getView())->toBe('test.view');
});

test('view handles null and false values correctly', function () {
    $this->view->with('null_value', null);
    $this->view->with('false_value', false);
    $this->view->with('zero_value', 0);
    $this->view->with('empty_string', '');

    expect($this->view->has('null_value'))->toBeTrue();
    expect($this->view->has('false_value'))->toBeTrue();
    expect($this->view->has('zero_value'))->toBeTrue();
    expect($this->view->has('empty_string'))->toBeTrue();

    expect($this->view->get('null_value'))->toBeNull();
    expect($this->view->get('false_value'))->toBeFalse();
    expect($this->view->get('zero_value'))->toBe(0);
    expect($this->view->get('empty_string'))->toBe('');
});
