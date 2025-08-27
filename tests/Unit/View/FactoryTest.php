<?php

use Phare\View\Factory;
use Phare\View\View;
use Phare\Container\Container;

beforeEach(function () {
    $this->container = new Container();
    $this->factory = new Factory($this->container);
});

test('factory can be instantiated', function () {
    expect($this->factory)->toBeInstanceOf(Factory::class);
});

test('factory can make view instance', function () {
    $view = $this->factory->make('test.view');
    
    expect($view)->toBeInstanceOf(View::class);
    expect($view->getView())->toBe('test/view');
});

test('factory can make view with data', function () {
    $data = ['key' => 'value'];
    $view = $this->factory->make('test.view', $data);
    
    expect($view->get('key'))->toBe('value');
});

test('factory can make view with merge data', function () {
    $data = ['key1' => 'value1'];
    $mergeData = ['key2' => 'value2'];
    
    $view = $this->factory->make('test.view', $data, $mergeData);
    
    expect($view->get('key1'))->toBe('value1');
    expect($view->get('key2'))->toBe('value2');
});

test('factory merge data overwrites regular data', function () {
    $data = ['key' => 'original'];
    $mergeData = ['key' => 'merged'];
    
    $view = $this->factory->make('test.view', $data, $mergeData);
    
    expect($view->get('key'))->toBe('merged');
});

test('factory normalizes view names', function () {
    $view = $this->factory->make('admin.users.index');
    
    expect($view->getView())->toBe('admin/users/index');
});

test('factory can add view locations', function () {
    $this->factory->addLocation('/custom/views');
    
    $paths = $this->factory->getPaths();
    
    expect($paths)->toContain('/custom/views');
});

test('factory can share data globally', function () {
    $this->factory->share('global_key', 'global_value');
    
    $view = $this->factory->make('test.view');
    
    expect($view->get('global_key'))->toBe('global_value');
});

test('factory can share array of data', function () {
    $this->factory->share(['key1' => 'value1', 'key2' => 'value2']);
    
    $view = $this->factory->make('test.view');
    
    expect($view->get('key1'))->toBe('value1');
    expect($view->get('key2'))->toBe('value2');
});

test('factory can get shared data', function () {
    $this->factory->share('shared_key', 'shared_value');
    
    $shared = $this->factory->getShared();
    
    expect($shared)->toHaveKey('shared_key');
    expect($shared['shared_key'])->toBe('shared_value');
});

test('factory can register view composer with closure', function () {
    $this->factory->composer('test.view', function ($view) {
        $view->with('composer_data', 'from_closure');
    });
    
    $view = $this->factory->make('test.view');
    
    expect($view->get('composer_data'))->toBe('from_closure');
});

test('factory can register view composer with class name', function () {
    $composerClass = new class {
        public function compose($view) {
            $view->with('composer_data', 'from_class');
        }
    };
    
    $className = get_class($composerClass);
    $this->container->bind($className, fn() => $composerClass);
    
    $this->factory->composer('test.view', $className);
    
    $view = $this->factory->make('test.view');
    
    expect($view->get('composer_data'))->toBe('from_class');
});

test('factory can register view composer for multiple views', function () {
    $this->factory->composer(['view1', 'view2'], function ($view) {
        $view->with('multi_composer', 'multi_value');
    });
    
    $view1 = $this->factory->make('view1');
    $view2 = $this->factory->make('view2');
    
    expect($view1->get('multi_composer'))->toBe('multi_value');
    expect($view2->get('multi_composer'))->toBe('multi_value');
});

test('factory supports wildcard composers', function () {
    $this->factory->composer('admin.*', function ($view) {
        $view->with('admin_data', 'admin_value');
    });
    
    $view = $this->factory->make('admin.dashboard');
    
    expect($view->get('admin_data'))->toBe('admin_value');
});

test('factory can register view creators', function () {
    $this->factory->creator('test.view', function ($view) {
        $view->with('creator_data', 'from_creator');
    });
    
    $view = $this->factory->make('test.view');
    
    expect($view->get('creator_data'))->toBe('from_creator');
});

test('factory creators are called before composers', function () {
    $this->factory->creator('test.view', function ($view) {
        $view->with('order', 'creator_first');
    });
    
    $this->factory->composer('test.view', function ($view) {
        $existing = $view->get('order', '');
        $view->with('order', $existing . '_composer_second');
    });
    
    $view = $this->factory->make('test.view');
    
    expect($view->get('order'))->toBe('creator_first_composer_second');
});

test('factory can add view extensions', function () {
    $this->factory->addExtension('.twig', 'twig');
    
    $extensions = $this->factory->getExtensions();
    
    expect($extensions)->toHaveKey('.twig');
    expect($extensions['.twig'])->toBe('twig');
});

test('factory exists method returns boolean', function () {
    // This is a simplified test since we don't have actual file system
    expect($this->factory->exists('nonexistent.view'))->toBeFalse();
});

test('factory can handle namespaced views', function () {
    $result = $this->factory->addNamespace('admin', '/admin/views');
    
    expect($result)->toBe($this->factory);
});

test('factory composer patterns work correctly', function () {
    $this->factory->composer('users.*', function ($view) {
        $view->with('users_composer', 'users_value');
    });
    
    $this->factory->composer('*.index', function ($view) {
        $view->with('index_composer', 'index_value');
    });
    
    $view = $this->factory->make('users.index');
    
    expect($view->get('users_composer'))->toBe('users_value');
    expect($view->get('index_composer'))->toBe('index_value');
});

test('factory handles multiple composers for same view', function () {
    $this->factory->composer('test.view', function ($view) {
        $view->with('composer1', 'value1');
    });
    
    $this->factory->composer('test.view', function ($view) {
        $view->with('composer2', 'value2');
    });
    
    $view = $this->factory->make('test.view');
    
    expect($view->get('composer1'))->toBe('value1');
    expect($view->get('composer2'))->toBe('value2');
});

test('factory shared data is included in every view', function () {
    $this->factory->share('app_name', 'Phare Framework');
    
    $view1 = $this->factory->make('view1');
    $view2 = $this->factory->make('view2');
    
    expect($view1->get('app_name'))->toBe('Phare Framework');
    expect($view2->get('app_name'))->toBe('Phare Framework');
});

test('factory shared data takes precedence over view data', function () {
    $this->factory->share('key', 'shared_value');
    
    $view = $this->factory->make('test.view', ['key' => 'view_value']);
    
    expect($view->get('key'))->toBe('shared_value');
});

test('factory composer can access container through view', function () {
    $this->container->bind('test_service', fn() => 'service_data');
    
    $this->factory->composer('test.view', function ($view) {
        $service = $view->container->make('test_service');
        $view->with('from_service', $service);
    });
    
    $view = $this->factory->make('test.view');
    
    expect($view->get('from_service'))->toBe('service_data');
});