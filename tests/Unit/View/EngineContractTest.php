<?php

use Phare\Contracts\View\Engine;

it('declares a render method returning string', function () {
    $rm = new ReflectionMethod(Engine::class, 'render');

    expect($rm->getNumberOfParameters())->toBe(2);
    expect($rm->getReturnType()?->getName())->toBe('string');
    expect($rm->getParameters()[0]->getName())->toBe('view');
    expect($rm->getParameters()[1]->getName())->toBe('data');
});
