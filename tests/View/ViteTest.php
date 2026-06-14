<?php

use Phare\View\Vite;

beforeEach(function () {
    $this->public = sys_get_temp_dir() . '/phare_vite_' . getmypid() . '_' . uniqid();
    @mkdir($this->public . '/build', 0777, true);
});

afterEach(function () {
    foreach ([$this->public . '/hot', $this->public . '/build/manifest.json'] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    @rmdir($this->public . '/build');
    @rmdir($this->public);
});

function writeManifest(string $public, array $manifest): void
{
    file_put_contents($public . '/build/manifest.json', json_encode($manifest));
}

it('emits the dev client and module tags when running hot', function () {
    file_put_contents($this->public . '/hot', 'http://localhost:5173');
    $vite = new Vite($this->public);

    $html = $vite(['resources/js/app.js']);

    expect($html)->toContain('http://localhost:5173/@vite/client')
        ->and($html)->toContain('http://localhost:5173/resources/js/app.js')
        ->and($html)->toContain('type="module"');
});

it('emits a stylesheet link for a CSS entry in build mode', function () {
    writeManifest($this->public, [
        'resources/css/app.css' => ['file' => 'assets/app-xyz.css', 'src' => 'resources/css/app.css', 'isEntry' => true],
    ]);
    $vite = new Vite($this->public);

    $html = $vite(['resources/css/app.css']);

    expect($html)->toContain('<link')
        ->and($html)->toContain('rel="stylesheet"')
        ->and($html)->toContain('/build/assets/app-xyz.css');
});

it('emits a module script for a JS entry in build mode', function () {
    writeManifest($this->public, [
        'resources/js/app.js' => ['file' => 'assets/app-abc.js', 'src' => 'resources/js/app.js', 'isEntry' => true],
    ]);
    $vite = new Vite($this->public);

    $html = $vite(['resources/js/app.js']);

    expect($html)->toContain('<script')
        ->and($html)->toContain('type="module"')
        ->and($html)->toContain('/build/assets/app-abc.js');
});

it('emits links for CSS imported by a JS entry', function () {
    writeManifest($this->public, [
        'resources/js/app.js' => [
            'file' => 'assets/app-abc.js',
            'src' => 'resources/js/app.js',
            'isEntry' => true,
            'css' => ['assets/app-def.css'],
        ],
    ]);
    $vite = new Vite($this->public);

    $html = $vite(['resources/js/app.js']);

    expect($html)->toContain('/build/assets/app-abc.js')
        ->and($html)->toContain('/build/assets/app-def.css');
});

it('throws when the manifest is missing in build mode', function () {
    $vite = new Vite($this->public);

    expect(fn () => $vite(['resources/js/app.js']))
        ->toThrow(RuntimeException::class);
});

it('emits the react refresh preamble only when running hot', function () {
    $vite = new Vite($this->public);
    expect($vite->reactRefresh())->toBe('');

    file_put_contents($this->public . '/hot', 'http://localhost:5173');
    expect((new Vite($this->public))->reactRefresh())
        ->toContain('@react-refresh')
        ->toContain('$RefreshReg$');
});
