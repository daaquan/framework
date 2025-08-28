<?php

use Phare\Container\Container;
use Phare\View\Factory;
use Phare\View\Template\TemplateEngine;
use Phare\View\View;

beforeEach(function () {
    $this->container = new Container();
    $this->factory = new Factory($this->container);
    $this->engine = new TemplateEngine($this->factory);
});

test('template engine can be instantiated', function () {
    expect($this->engine)->toBeInstanceOf(TemplateEngine::class);
});

test('template engine can start and stop sections', function () {
    $this->engine->startSection('content');
    echo 'Section content';
    $result = $this->engine->stopSection();

    expect($result)->toBe('Section content');
    expect($this->engine->yieldContent('content'))->toBe('Section content');
});

test('template engine can start section with content', function () {
    $this->engine->startSection('title', 'Page Title');

    expect($this->engine->yieldContent('title'))->toBe('Page Title');
});

test('template engine can append to sections', function () {
    $this->engine->startSection('scripts');
    echo 'script1.js';
    $this->engine->appendSection();

    $this->engine->startSection('scripts');
    echo 'script2.js';
    $this->engine->appendSection();

    expect($this->engine->yieldContent('scripts'))->toBe('script1.jsscript2.js');
});

test('template engine throws exception when stopping section without starting', function () {
    expect(fn () => $this->engine->stopSection())
        ->toThrow(InvalidArgumentException::class);
});

test('template engine can extend parent template', function () {
    $this->engine->extend('layouts.app');

    expect($this->engine->getExtends())->toBe('layouts.app');
});

test('template engine yields default content for empty sections', function () {
    expect($this->engine->yieldContent('nonexistent', 'default'))->toBe('default');
});

test('template engine can check if section has content', function () {
    $this->engine->startSection('content', 'Some content');

    expect($this->engine->hasSection('content'))->toBeTrue();
    expect($this->engine->hasSection('nonexistent'))->toBeFalse();
});

test('template engine can get all sections', function () {
    $this->engine->startSection('title', 'Page Title');
    $this->engine->startSection('content', 'Page Content');

    $sections = $this->engine->getSections();

    expect($sections)->toHaveKey('title');
    expect($sections)->toHaveKey('content');
    expect($sections['title'])->toBe('Page Title');
});

test('template engine can flush all sections', function () {
    $this->engine->startSection('content', 'Some content');
    $this->engine->flushSections();

    expect($this->engine->hasSection('content'))->toBeFalse();
    expect($this->engine->getSections())->toBeEmpty();
});

test('template engine can push content to stacks', function () {
    $this->engine->push('scripts', 'script1.js');
    $this->engine->push('scripts', 'script2.js');

    expect($this->engine->stack('scripts'))->toBe('script1.jsscript2.js');
});

test('template engine can start and stop push', function () {
    $this->engine->startPush('styles');
    echo 'style1.css';
    $this->engine->stopPush();

    $this->engine->startPush('styles');
    echo 'style2.css';
    $this->engine->stopPush();

    expect($this->engine->stack('styles'))->toBe('style1.cssstyle2.css');
});

test('template engine can prepend content to stacks', function () {
    $this->engine->push('scripts', 'script2.js');
    $this->engine->prepend('scripts', 'script1.js');

    expect($this->engine->stack('scripts'))->toBe('script1.jsscript2.js');
});

test('template engine can start and stop prepend', function () {
    $this->engine->push('scripts', 'script3.js');

    $this->engine->startPrepend('scripts');
    echo 'script1.js';
    $this->engine->stopPrepend();

    $this->engine->startPrepend('scripts');
    echo 'script2.js';
    $this->engine->stopPrepend();

    expect($this->engine->stack('scripts'))->toBe('script2.jsscript1.jsscript3.js');
});

test('template engine throws exception when stopping push without starting', function () {
    expect(fn () => $this->engine->stopPush())
        ->toThrow(InvalidArgumentException::class);
});

test('template engine throws exception when stopping prepend without starting', function () {
    expect(fn () => $this->engine->stopPrepend())
        ->toThrow(InvalidArgumentException::class);
});

test('template engine can start push with content', function () {
    $this->engine->startPush('scripts', 'inline_script.js');

    expect($this->engine->stack('scripts'))->toBe('inline_script.js');
});

test('template engine stack returns empty string for nonexistent stack', function () {
    expect($this->engine->stack('nonexistent'))->toBe('');
});

test('template engine handles include method', function () {
    // Mock the factory to return a view
    $mockView = new View($this->container);
    $mockView->setView('partial');

    // Create a custom factory that returns our mock view
    $factory = new class($this->container) extends Factory
    {
        private $mockView;

        public function __construct($container)
        {
            parent::__construct($container);
            $this->mockView = new View($container);
            $this->mockView->setView('partial');
        }

        public function make(string $view, array $data = [], array $mergeData = []): View
        {
            $this->mockView->with($data);

            return $this->mockView;
        }
    };

    $engine = new TemplateEngine($factory);

    $result = $engine->include('partial', ['key' => 'value']);

    expect($result)->toBeString();
    expect($result)->toContain('partial');
});

test('template engine includeIf returns empty when view does not exist', function () {
    $result = $this->engine->includeIf('nonexistent.view');

    expect($result)->toBe('');
});

test('template engine includeFirst returns first existing view', function () {
    // Mock factory to simulate view existence
    $factory = new class($this->container) extends Factory
    {
        public function exists(string $view): bool
        {
            return $view === 'second.view';
        }

        public function make(string $view, array $data = [], array $mergeData = []): View
        {
            $mockView = new View($this->container);
            $mockView->setView($view);

            return $mockView;
        }
    };

    $engine = new TemplateEngine($factory);

    $result = $engine->includeFirst(['first.view', 'second.view', 'third.view']);

    expect($result)->toContain('second.view');
});

test('template engine includeUnless includes when condition is false', function () {
    // Mock factory
    $factory = new class($this->container) extends Factory
    {
        public function make(string $view, array $data = [], array $mergeData = []): View
        {
            $mockView = new View($this->container);
            $mockView->setView($view);

            return $mockView;
        }
    };

    $engine = new TemplateEngine($factory);

    $result = $engine->includeUnless(false, 'test.view');
    expect($result)->toContain('test.view');

    $result = $engine->includeUnless(true, 'test.view');
    expect($result)->toBe('');
});

test('template engine includeWhen includes when condition is true', function () {
    // Mock factory
    $factory = new class($this->container) extends Factory
    {
        public function make(string $view, array $data = [], array $mergeData = []): View
        {
            $mockView = new View($this->container);
            $mockView->setView($view);

            return $mockView;
        }
    };

    $engine = new TemplateEngine($factory);

    $result = $engine->includeWhen(true, 'test.view');
    expect($result)->toContain('test.view');

    $result = $engine->includeWhen(false, 'test.view');
    expect($result)->toBe('');
});

test('template engine can handle nested sections', function () {
    $this->engine->startSection('outer');
    echo 'Outer start ';

    $this->engine->startSection('inner');
    echo 'Inner content';
    $this->engine->stopSection();

    echo ' Outer end';
    $this->engine->stopSection();

    expect($this->engine->yieldContent('outer'))->toBe('Outer start  Outer end');
    expect($this->engine->yieldContent('inner'))->toBe('Inner content');
});

test('template engine section overwrite works correctly', function () {
    $this->engine->startSection('content');
    echo 'Original content';
    $this->engine->stopSection();

    $this->engine->startSection('content');
    echo 'New content';
    $this->engine->stopSection(true); // Overwrite

    expect($this->engine->yieldContent('content'))->toBe('New content');
});
