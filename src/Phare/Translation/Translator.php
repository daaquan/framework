<?php

namespace Phare\Translation;

use Phare\Filesystem\Filesystem;

class Translator
{
    protected array $loaded = [];

    protected string $locale;

    protected string $fallback;

    protected array $paths = [];

    protected Filesystem $files;

    public function __construct(string $locale = 'en', string $fallback = 'en')
    {
        $this->locale = $locale;
        $this->fallback = $fallback;
        $this->files = new Filesystem();
    }

    public function addPath(string $path): void
    {
        $this->paths[] = $path;
    }

    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?: $this->locale;

        $this->load($locale);

        $line = $this->getLine($key, $locale);

        if (is_null($line)) {
            if ($locale !== $this->fallback) {
                return $this->get($key, $replace, $this->fallback);
            }

            return $key;
        }

        return $this->makeReplacements($line, $replace);
    }

    public function choice(string $key, int $number, array $replace = [], ?string $locale = null): string
    {
        $line = $this->get($key, [], $locale);

        if (!str_contains($line, '|')) {
            return $this->makeReplacements($line, array_merge(['count' => $number], $replace));
        }

        $segments = explode('|', $line);

        if (count($segments) < 2) {
            return $this->makeReplacements($line, array_merge(['count' => $number], $replace));
        }

        // Simple pluralization logic
        $selectedSegment = '';
        if ($number === 0 && isset($segments[0])) {
            $selectedSegment = trim($segments[0]);
        } elseif ($number === 1 && isset($segments[1])) {
            $selectedSegment = trim($segments[1]);
        } elseif (isset($segments[2])) {
            $selectedSegment = trim($segments[2]);
        } else {
            $selectedSegment = trim($segments[1]);
        }

        return $this->makeReplacements($selectedSegment, array_merge(['count' => $number], $replace));
    }

    protected function load(string $locale): void
    {
        if (isset($this->loaded[$locale])) {
            return;
        }

        $this->loaded[$locale] = [];

        foreach ($this->paths as $path) {
            $localeFiles = $this->files->glob("{$path}/{$locale}/*.php");

            foreach ($localeFiles as $file) {
                $namespace = $this->files->name($file);
                $translations = include $file;

                if (is_array($translations)) {
                    $this->loaded[$locale][$namespace] = $translations;
                }
            }
        }
    }

    protected function getLine(string $key, string $locale): ?string
    {
        $segments = explode('.', $key);
        $namespace = array_shift($segments);

        if (!isset($this->loaded[$locale][$namespace])) {
            return null;
        }

        $line = $this->loaded[$locale][$namespace];

        foreach ($segments as $segment) {
            if (!is_array($line) || !isset($line[$segment])) {
                return null;
            }
            $line = $line[$segment];
        }

        return is_string($line) ? $line : null;
    }

    protected function makeReplacements(string $line, array $replace): string
    {
        if (empty($replace)) {
            return $line;
        }

        foreach ($replace as $key => $value) {
            $lowerKey = strtolower($key);
            $upperKey = strtoupper($key);
            $ucfirstKey = ucfirst($lowerKey);

            $line = str_replace(
                [':' . $lowerKey, ':' . $upperKey, ':' . $ucfirstKey, ':' . $key],
                [$value, strtoupper($value), ucfirst($value), $value],
                $line
            );
        }

        return $line;
    }

    public function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        return $this->get($key, $replace, $locale);
    }

    public function transChoice(string $key, int $number, array $replace = [], ?string $locale = null): string
    {
        return $this->choice($key, $number, $replace, $locale);
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getFallback(): string
    {
        return $this->fallback;
    }

    public function setFallback(string $fallback): void
    {
        $this->fallback = $fallback;
    }

    public function has(string $key, ?string $locale = null): bool
    {
        $locale = $locale ?: $this->locale;
        $this->load($locale);

        return !is_null($this->getLine($key, $locale));
    }

    public function flush(): void
    {
        $this->loaded = [];
    }
}
