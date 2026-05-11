<?php

use Phare\Container\Container;

#[Attribute(Attribute::TARGET_PARAMETER)]
class MyTagAttribute
{
    public function __construct(public string $label = '') {}
}

class WhenHasAttributeConsumer
{
    public function __construct(#[MyTagAttribute('alpha')] public string $value) {}
}

it('whenHasAttribute() handler resolves attribute parameters', function () {
    $c = new Container();
    $c->whenHasAttribute(MyTagAttribute::class, function (MyTagAttribute $attr, Container $app) {
        return strtoupper($attr->label);
    });

    $consumer = $c->make(WhenHasAttributeConsumer::class);

    expect($consumer->value)->toBe('ALPHA');
});

it('whenHasAttribute() handler wins over attribute static resolve()', function () {
    $c = new Container();
    $c->whenHasAttribute(MyTagAttribute::class, fn ($attr) => 'handler:' . $attr->label);

    $consumer = $c->make(WhenHasAttributeConsumer::class);

    expect($consumer->value)->toBe('handler:alpha');
});
