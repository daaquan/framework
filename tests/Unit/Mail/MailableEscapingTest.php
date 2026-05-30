<?php

use Phare\Mail\Mailable;

class EscapingTestMailable extends Mailable
{
    public function build(): void {}
}

it('HTML-escapes {{ $var }} interpolation (XSS-safe, Blade-default semantics)', function () {
    $mail = (new EscapingTestMailable())
        ->html('<p>Hi {{ $name }}</p>')
        ->with('name', '<script>alert(1)</script>');

    $body = $mail->getHtmlBody();

    expect($body)->toContain('&lt;script&gt;')
        ->and($body)->not->toContain('<script>');
});

it('escapes quotes in {{ $var }} to block attribute-injection', function () {
    $mail = (new EscapingTestMailable())
        ->html('<a title="{{ $t }}">x</a>')
        ->with('t', '"><img src=x onerror=alert(1)>');

    expect($mail->getHtmlBody())->not->toContain('<img');
});

it('renders {!! $var !!} raw (explicit unescaped opt-in)', function () {
    $mail = (new EscapingTestMailable())
        ->html('<p>{!! $trusted !!}</p>')
        ->with('trusted', '<b>bold</b>');

    expect($mail->getHtmlBody())->toBe('<p><b>bold</b></p>');
});

it('leaves plain values unchanged when escaped', function () {
    $mail = (new EscapingTestMailable())
        ->html('<h1>Hello {{ $name }}!</h1>')
        ->with('name', 'World');

    expect($mail->getHtmlBody())->toBe('<h1>Hello World!</h1>');
});
