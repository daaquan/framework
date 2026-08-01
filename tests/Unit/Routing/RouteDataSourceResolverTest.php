<?php

use Phare\Routing\RouteDataSourceResolver;

function createRoutesCacheFile(string $dir, array $routes): string
{
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $path = $dir . '/routes.php';
    file_put_contents($path, '<?php return ' . var_export($routes, true) . ';');

    return $path;
}

function removeDirectoryTree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();
        $item->isDir() ? rmdir($path) : unlink($path);
    }

    rmdir($dir);
}

beforeEach(function () {
    $this->tmpDir = sys_get_temp_dir() . '/phare-routes-resolver-' . bin2hex(random_bytes(6));
});

afterEach(function () {
    removeDirectoryTree($this->tmpDir);
});

it('loads routes from existing cache file', function () {
    $resolver = new RouteDataSourceResolver();
    $expected = ['/' => ['GET' => ['action' => 'index']]];
    $path = createRoutesCacheFile($this->tmpDir, $expected);

    $loaded = $resolver->resolve($path, function () {
        throw new RuntimeException('fallback must not be called');
    });

    expect($loaded)->toBe($expected);
});

it('uses fallback loader when cache file does not exist', function () {
    $resolver = new RouteDataSourceResolver();
    $path = $this->tmpDir . '/routes.php';
    $expected = ['/health' => ['GET' => ['action' => 'status']]];

    $loaded = $resolver->resolve($path, fn (string $cachedPath) => $expected);

    expect($loaded)->toBe($expected);
});

it('loads generated cache file when fallback loader writes file', function () {
    $resolver = new RouteDataSourceResolver();
    $path = $this->tmpDir . '/routes.php';
    $expected = ['/users/{id}' => ['GET' => ['action' => 'show']]];

    $loaded = $resolver->resolve($path, function (string $cachedPath) use ($expected) {
        createRoutesCacheFile(dirname($cachedPath), $expected);

    });

    expect($loaded)->toBe($expected);
});

it('throws when fallback loader provides no routes and no cache file', function () {
    $resolver = new RouteDataSourceResolver();
    $path = $this->tmpDir . '/routes.php';

    expect(fn () => $resolver->resolve($path, fn (string $cachedPath) => null))
        ->toThrow(RuntimeException::class, 'Failed to resolve routes from cache or fallback loader.');
});
