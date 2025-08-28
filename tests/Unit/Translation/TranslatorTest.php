<?php

use Phare\Filesystem\Filesystem;
use Phare\Translation\Translator;

function createTestTranslations($langPath, $files)
{
    $enPath = $langPath . '/en';
    $esPath = $langPath . '/es';

    $files->makeDirectory($enPath, 0755, true);
    $files->makeDirectory($esPath, 0755, true);

    // English messages
    $files->put($enPath . '/messages.php', '<?php return [
        "welcome" => "Welcome to our application",
        "greeting" => "Hello :name",
        "items" => "no items|one item|:count items",
        "nested" => [
            "message" => "This is nested",
            "deep" => [
                "value" => "Deep nested value"
            ]
        ]
    ];');

    $files->put($enPath . '/validation.php', '<?php return [
        "required" => "The :attribute field is required.",
        "email" => "The :attribute must be a valid email address."
    ];');

    // Spanish messages
    $files->put($esPath . '/messages.php', '<?php return [
        "welcome" => "Bienvenido a nuestra aplicación",
        "greeting" => "Hola :name",
        "items" => "sin elementos|un elemento|:count elementos"
    ];');
}

beforeEach(function () {
    $this->translator = new Translator('en', 'en');
    $this->files = new Filesystem();

    // Create test translation directory
    $this->langPath = __DIR__ . '/../../Mock/resources/lang';
    if (!$this->files->isDirectory($this->langPath)) {
        $this->files->makeDirectory($this->langPath, 0755, true);
    }

    // Create test translations
    createTestTranslations($this->langPath, $this->files);
    $this->translator->addPath($this->langPath);
});

afterEach(function () {
    // Clean up test files
    if ($this->files->isDirectory($this->langPath)) {
        $this->files->deleteDirectory($this->langPath);
    }
});

test('can get simple translation', function () {
    expect($this->translator->get('messages.welcome'))->toBe('Welcome to our application');
});

test('can get translation with replacements', function () {
    $result = $this->translator->get('messages.greeting', ['name' => 'John']);
    expect($result)->toBe('Hello John');
});

test('can get nested translation', function () {
    expect($this->translator->get('messages.nested.message'))->toBe('This is nested');
    expect($this->translator->get('messages.nested.deep.value'))->toBe('Deep nested value');
});

test('returns key when translation not found', function () {
    expect($this->translator->get('messages.nonexistent'))->toBe('messages.nonexistent');
});

test('falls back to fallback locale', function () {
    $translator = new Translator('fr', 'en');
    $translator->addPath($this->langPath);

    expect($translator->get('messages.welcome'))->toBe('Welcome to our application');
});

test('can set and get locale', function () {
    $this->translator->setLocale('es');
    expect($this->translator->getLocale())->toBe('es');
    expect($this->translator->get('messages.welcome'))->toBe('Bienvenido a nuestra aplicación');
});

test('can set and get fallback locale', function () {
    $this->translator->setFallback('es');
    expect($this->translator->getFallback())->toBe('es');
});

test('can check if translation exists', function () {
    expect($this->translator->has('messages.welcome'))->toBeTrue();
    expect($this->translator->has('messages.nonexistent'))->toBeFalse();
});

test('can flush loaded translations', function () {
    // Load translations
    $this->translator->get('messages.welcome');

    // Flush
    $this->translator->flush();

    // Should reload translations
    expect($this->translator->get('messages.welcome'))->toBe('Welcome to our application');
});

test('handles pluralization correctly', function () {
    expect($this->translator->choice('messages.items', 0))->toBe('no items');
    expect($this->translator->choice('messages.items', 1))->toBe('one item');
    expect($this->translator->choice('messages.items', 5))->toBe('5 items');
});

test('handles pluralization with replacements', function () {
    $result = $this->translator->choice('messages.items', 3, ['count' => 3]);
    expect($result)->toBe('3 items');
});

test('handles pluralization fallback', function () {
    $this->translator->setLocale('es');
    expect($this->translator->choice('messages.items', 0))->toBe('sin elementos');
    expect($this->translator->choice('messages.items', 1))->toBe('un elemento');
    expect($this->translator->choice('messages.items', 5))->toBe('5 elementos');
});

test('trans method works as alias', function () {
    expect($this->translator->trans('messages.welcome'))->toBe('Welcome to our application');
});

test('transChoice method works as alias', function () {
    expect($this->translator->transChoice('messages.items', 1))->toBe('one item');
});

test('handles case variations in replacements', function () {
    $result = $this->translator->get('validation.required', ['attribute' => 'email']);
    expect($result)->toBe('The email field is required.');

    // Laravel-style case variations - based on the placeholder case
    $this->files->put($this->langPath . '/en/test.php', '<?php return [
        "uppercase" => "The :ATTRIBUTE field is required.",
        "lowercase" => "The :attribute field is required.",
        "title" => "The :Attribute field is required."
    ];');

    // Flush to force reload
    $this->translator->flush();

    expect($this->translator->get('test.uppercase', ['ATTRIBUTE' => 'email']))->toBe('The EMAIL field is required.');
    expect($this->translator->get('test.lowercase', ['attribute' => 'email']))->toBe('The email field is required.');
    expect($this->translator->get('test.title', ['Attribute' => 'email']))->toBe('The Email field is required.');
});

test('handles missing translation files gracefully', function () {
    $translator = new Translator('fr', 'en');
    $translator->addPath(__DIR__ . '/nonexistent');

    expect($translator->get('messages.test'))->toBe('messages.test');
});

test('can add multiple paths', function () {
    $secondPath = __DIR__ . '/../../Mock/resources/lang2';
    $this->files->makeDirectory($secondPath . '/en', 0755, true);
    $this->files->put($secondPath . '/en/custom.php', '<?php return [
        "message" => "Custom message"
    ];');

    $this->translator->addPath($secondPath);

    expect($this->translator->get('custom.message'))->toBe('Custom message');

    // Cleanup
    $this->files->deleteDirectory($secondPath);
});

test('handles invalid translation files', function () {
    $invalidPath = $this->langPath . '/en/invalid.php';
    $this->files->put($invalidPath, '<?php return "not an array";');

    expect($this->translator->get('invalid.test'))->toBe('invalid.test');
});
