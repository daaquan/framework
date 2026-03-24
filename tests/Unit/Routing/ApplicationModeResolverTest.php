<?php

use Phare\Foundation\Micro;
use Phare\Foundation\Web;
use Phare\Routing\ApplicationModeResolver;

it('resolves web application mode', function () {
    $resolver = new ApplicationModeResolver();
    $app = new Web('/tmp');

    expect($resolver->resolve($app))->toBe(ApplicationModeResolver::MODE_WEB);
});

it('resolves micro application mode', function () {
    $resolver = new ApplicationModeResolver();
    $app = new Micro('/tmp');

    expect($resolver->resolve($app))->toBe(ApplicationModeResolver::MODE_MICRO);
});
