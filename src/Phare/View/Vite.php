<?php

namespace Phare\View;

/**
 * Resolves Vite assets for Blade, mirroring laravel-vite-plugin's server Vite.
 *
 * Two modes:
 *  - "hot" (dev): a public/hot file holds the dev server URL; assets load from it.
 *  - "build" (prod): public/build/manifest.json maps entrypoints to hashed files.
 */
class Vite
{
    public function __construct(
        protected ?string $publicPath = null,
        protected string $buildDirectory = 'build',
        protected string $hotFile = 'hot',
    ) {}

    /**
     * @param string|array<int, string> $entrypoints
     */
    public function __invoke(string|array $entrypoints): string
    {
        $entrypoints = (array)$entrypoints;

        return $this->isRunningHot()
            ? $this->hotTags($entrypoints)
            : $this->buildTags($entrypoints);
    }

    /**
     * The React Fast Refresh preamble; empty unless the dev server is running.
     */
    public function reactRefresh(): string
    {
        if (!$this->isRunningHot()) {
            return '';
        }

        $url = $this->devServerUrl();

        return <<<HTML
        <script type="module">
        import RefreshRuntime from '{$url}/@react-refresh'
        RefreshRuntime.injectIntoGlobalHook(window)
        window.\$RefreshReg\$ = () => {}
        window.\$RefreshSig\$ = () => (type) => type
        window.__vite_plugin_react_preamble_installed__ = true
        </script>
        HTML;
    }

    protected function hotTags(array $entrypoints): string
    {
        $url = $this->devServerUrl();
        $tags = ['<script type="module" src="' . $url . '/@vite/client"></script>'];

        foreach ($entrypoints as $entry) {
            $tags[] = $this->isCss($entry)
                ? '<link rel="stylesheet" href="' . $url . '/' . $entry . '">'
                : '<script type="module" src="' . $url . '/' . $entry . '"></script>';
        }

        return implode("\n", $tags);
    }

    protected function buildTags(array $entrypoints): string
    {
        $manifest = $this->manifest();
        $tags = [];

        foreach ($entrypoints as $entry) {
            if (!isset($manifest[$entry])) {
                throw new \RuntimeException("Unable to locate file in Vite manifest: {$entry}.");
            }

            $chunk = $manifest[$entry];

            if ($this->isCss($entry)) {
                $tags[] = $this->styleTag($chunk['file']);

                continue;
            }

            $tags[] = $this->scriptTag($chunk['file']);

            foreach ($chunk['css'] ?? [] as $css) {
                $tags[] = $this->styleTag($css);
            }
        }

        return implode("\n", $tags);
    }

    protected function styleTag(string $file): string
    {
        return '<link rel="stylesheet" href="' . $this->assetUrl($file) . '">';
    }

    protected function scriptTag(string $file): string
    {
        return '<script type="module" src="' . $this->assetUrl($file) . '"></script>';
    }

    protected function assetUrl(string $file): string
    {
        return '/' . trim($this->buildDirectory, '/') . '/' . ltrim($file, '/');
    }

    protected function isCss(string $path): bool
    {
        return (bool)preg_match('/\.(css|less|sass|scss|styl|stylus|pcss|postcss)$/', $path);
    }

    protected function isRunningHot(): bool
    {
        return is_file($this->hotFilePath());
    }

    protected function devServerUrl(): string
    {
        $url = trim((string)file_get_contents($this->hotFilePath()));

        return $url !== '' ? rtrim($url, '/') : 'http://localhost:5173';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function manifest(): array
    {
        $path = $this->manifestPath();

        if (!is_file($path)) {
            throw new \RuntimeException("Vite manifest not found at: {$path}.");
        }

        return json_decode((string)file_get_contents($path), true) ?? [];
    }

    protected function manifestPath(): string
    {
        $base = $this->basePath() . '/' . trim($this->buildDirectory, '/');

        // Vite 5+ writes the manifest under a .vite/ subdirectory; support both.
        $default = $base . '/manifest.json';

        return is_file($default) ? $default : $base . '/.vite/manifest.json';
    }

    protected function hotFilePath(): string
    {
        return $this->basePath() . '/' . $this->hotFile;
    }

    protected function basePath(): string
    {
        return $this->publicPath ?? public_path();
    }
}
