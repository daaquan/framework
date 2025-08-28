<?php

use Phare\View\ViewComposer;
use Phare\View\View;
use Phare\Container\Container;

class TestViewComposer extends ViewComposer
{
    public function compose(View $view): void
    {
        $view->with('composer_data', 'from composer');
        $this->share($view, [
            'shared_from_composer' => 'shared value',
            'user_count' => 100
        ]);
    }

    protected function data(): array
    {
        return [
            'default_data' => 'default value',
            'items' => ['item1', 'item2']
        ];
    }
}

class CallableComposer
{
    public function compose(View $view): void
    {
        $view->with('callable_data', 'from callable');
    }
}

beforeEach(function () {
    $this->container = new Container();
    $this->view = new View($this->container);
});

test('view composer can be extended', function () {
    $composer = new TestViewComposer();
    
    expect($composer)->toBeInstanceOf(ViewComposer::class);
});

test('view composer compose method is abstract', function () {
    $reflection = new ReflectionClass(ViewComposer::class);
    $method = $reflection->getMethod('compose');
    
    expect($method->isAbstract())->toBeTrue();
});

test('view composer can share data with view', function () {
    $composer = new TestViewComposer();
    $composer->compose($this->view);
    
    expect($this->view->get('composer_data'))->toBe('from composer');
    expect($this->view->get('shared_from_composer'))->toBe('shared value');
    expect($this->view->get('user_count'))->toBe(100);
});

test('view composer data method returns array', function () {
    $composer = new TestViewComposer();
    $reflection = new ReflectionClass($composer);
    $method = $reflection->getMethod('data');
    $method->setAccessible(true);
    
    $data = $method->invoke($composer);
    
    expect($data)->toBeArray();
    expect($data)->toHaveKey('default_data');
    expect($data)->toHaveKey('items');
});

test('view composer share method adds data to view', function () {
    $composer = new TestViewComposer();
    $reflection = new ReflectionClass($composer);
    $method = $reflection->getMethod('share');
    $method->setAccessible(true);
    
    $data = [
        'test_key' => 'test_value',
        'another_key' => ['nested', 'array']
    ];
    
    $method->invoke($composer, $this->view, $data);
    
    expect($this->view->get('test_key'))->toBe('test_value');
    expect($this->view->get('another_key'))->toBe(['nested', 'array']);
});

test('view composer can work with callable objects', function () {
    $composer = new CallableComposer();
    $composer->compose($this->view);
    
    expect($this->view->get('callable_data'))->toBe('from callable');
});

test('view composer handles empty data method', function () {
    $composer = new class extends ViewComposer {
        public function compose(View $view): void
        {
            $view->with('empty_composer', true);
        }
    };
    
    $composer->compose($this->view);
    
    expect($this->view->get('empty_composer'))->toBeTrue();
});

test('view composer can modify existing view data', function () {
    $this->view->with('existing_data', 'original');
    
    $composer = new class extends ViewComposer {
        public function compose(View $view): void
        {
            $existing = $view->get('existing_data', '');
            $view->with('existing_data', $existing . ' modified');
        }
    };
    
    $composer->compose($this->view);
    
    expect($this->view->get('existing_data'))->toBe('original modified');
});

test('view composer can access container through view', function () {
    $this->container->bind('test_service', fn() => 'service_value');
    
    $composer = new class extends ViewComposer {
        public function compose(View $view): void
        {
            $container = $view->container;
            $view->with('service_data', $container->make('test_service'));
        }
    };
    
    $composer->compose($this->view);
    
    expect($this->view->get('service_data'))->toBe('service_value');
});

test('view composer share method handles multiple calls', function () {
    $composer = new TestViewComposer();
    $reflection = new ReflectionClass($composer);
    $method = $reflection->getMethod('share');
    $method->setAccessible(true);
    
    $method->invoke($composer, $this->view, ['first' => 'value1']);
    $method->invoke($composer, $this->view, ['second' => 'value2']);
    
    expect($this->view->get('first'))->toBe('value1');
    expect($this->view->get('second'))->toBe('value2');
});

test('view composer can work with complex data structures', function () {
    $composer = new class extends ViewComposer {
        public function compose(View $view): void
        {
            $this->share($view, [
                'user' => [
                    'id' => 1,
                    'name' => 'John Doe',
                    'roles' => ['admin', 'user']
                ],
                'settings' => (object) [
                    'theme' => 'dark',
                    'notifications' => true
                ]
            ]);
        }
    };
    
    $composer->compose($this->view);
    
    expect($this->view->get('user'))->toBeArray();
    expect($this->view->get('user')['name'])->toBe('John Doe');
    expect($this->view->get('settings'))->toBeObject();
    expect($this->view->get('settings')->theme)->toBe('dark');
});