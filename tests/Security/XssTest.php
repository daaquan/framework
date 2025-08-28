<?php

use Phare\Security\Xss;

it('escapes HTML special characters', function () {
    $input = '<script>alert("XSS")</script>';
    $output = Xss::escape($input);

    expect($output)->toBe('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;');
});

it('strips all HTML tags by default', function () {
    $input = '<div><p>Hello <strong>World</strong></p></div>';
    $output = Xss::stripTags($input);

    expect($output)->toBe('Hello World');
});

it('preserves allowed HTML tags', function () {
    $input = '<div><p>Hello <strong>World</strong></p><script>alert("XSS")</script></div>';
    $output = Xss::stripTags($input, ['p', 'strong']);

    expect($output)->toBe('<p>Hello <strong>World</strong></p>alert("XSS")');
});

it('removes dangerous JavaScript patterns', function () {
    $input = '<a href="javascript:alert(\'XSS\')">Click me</a>';
    $output = Xss::removeDangerousPatterns($input);

    expect($output)->not->toContain('javascript:');
});

it('removes script tags', function () {
    $input = 'Safe content <script>alert("XSS")</script> more content';
    $output = Xss::removeDangerousPatterns($input);

    expect($output)->not->toContain('<script>');
    expect($output)->toContain('Safe content');
    expect($output)->toContain('more content');
});

it('removes iframe tags', function () {
    $input = '<iframe src="http://evil.com"></iframe>';
    $output = Xss::removeDangerousPatterns($input);

    expect($output)->not->toContain('<iframe');
});

it('removes onload and other event handlers', function () {
    $input = '<img src="image.jpg" onload="alert(\'XSS\')" />';
    $output = Xss::removeDangerousPatterns($input);

    expect($output)->not->toContain('onload');
});

it('cleans HTML with allowed tags and attributes', function () {
    $input = '<p class="safe" onclick="alert(\'XSS\')">Hello <strong>World</strong></p>';
    $output = Xss::clean($input);

    expect($output)->toContain('<p class="safe">');
    expect($output)->not->toContain('onclick');
    expect($output)->toContain('<strong>World</strong>');
});

it('filters dangerous input patterns', function () {
    $input = "Hello\x00World\x1FTest";
    $output = Xss::filterInput($input);

    expect($output)->toBe('HelloWorldTest');
});

it('sanitizes URLs', function () {
    expect(Xss::sanitizeUrl('https://example.com'))->toBe('https://example.com');
    expect(Xss::sanitizeUrl('javascript:alert("XSS")'))->toBe('');
    expect(Xss::sanitizeUrl('vbscript:msgbox("XSS")'))->toBe('');
    expect(Xss::sanitizeUrl('not-a-url'))->toBe('');
});

it('validates email addresses', function () {
    expect(Xss::validateEmail('user@example.com'))->toBe('user@example.com');
    expect(Xss::validateEmail('invalid-email'))->toBe('');
    expect(Xss::validateEmail('test@'))->toBe('');
});

it('detects base64 encoded content', function () {
    $validBase64 = base64_encode('Hello World');
    $invalidBase64 = 'not-base64!@#';

    expect(Xss::isBase64($validBase64))->toBeTrue();
    expect(Xss::isBase64($invalidBase64))->toBeFalse();
});

it('removes base64 images', function () {
    $input = '<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQV" alt="test">';
    $output = Xss::removeBase64Images($input);

    expect($output)->not->toContain('data:image');
    expect($output)->toContain('alt="test"');
});

it('allows customizing allowed tags', function () {
    $originalTags = ['p', 'strong'];
    Xss::setAllowedTags($originalTags);

    $input = '<p>Hello</p><div>World</div><strong>Test</strong>';
    $output = Xss::clean($input);

    expect($output)->toContain('Hello');
    expect($output)->toContain('Test');
    expect($output)->toContain('World'); // Content is preserved even if tags are stripped
});

it('allows adding individual tags', function () {
    Xss::setAllowedTags(['p']);
    Xss::addAllowedTag('div');

    $input = '<p>Hello</p><div>World</div><span>Test</span>';
    $output = Xss::clean($input);

    expect($output)->toContain('Hello');
    expect($output)->toContain('World');
    expect($output)->toContain('Test'); // Content is preserved
});

it('allows customizing allowed attributes', function () {
    Xss::setAllowedAttributes(['id', 'data-test']);

    $input = '<p id="test" class="danger" data-test="safe">Hello</p>';
    $output = Xss::clean($input, ['p']);

    expect($output)->toContain('id="test"');
    expect($output)->toContain('data-test="safe"');
    expect($output)->not->toContain('class="danger"');
});

it('prevents XSS through different attack vectors', function () {
    $vectors = [
        '<script>alert("XSS")</script>',
        '"><script>alert("XSS")</script>',
        '<img src=x onerror=alert("XSS")>',
        '<svg onload=alert("XSS")>',
        '<iframe src=javascript:alert("XSS")></iframe>',
        '<object data=javascript:alert("XSS")></object>',
        '<embed src=javascript:alert("XSS")></embed>',
        '<form><button formaction=javascript:alert("XSS")>',
    ];

    foreach ($vectors as $vector) {
        $output = Xss::clean($vector);
        // Check that dangerous patterns are removed
        expect($output)->not->toContain('<script');
        expect($output)->not->toContain('<iframe');
        expect($output)->not->toContain('<object');
        expect($output)->not->toContain('<embed');
        expect($output)->not->toContain('<form');
        expect($output)->not->toContain('javascript:');
    }
});
