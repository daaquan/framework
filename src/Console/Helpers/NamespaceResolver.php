<?php

namespace Phare\Console\Helpers;

use Phare\Collections\Str;
use Phare\Console\Config;

class NamespaceResolver
{
    /**
     * Resolve namespace for the given dir path, add additional values.
     */
    public static function resolve(string $dir, string $additional = ''): ?string
    {
        $config = self::getConfig();

        if ($config->has(['namespaces', $dir])) {
            $namespace = $config->namespaces->$dir;

            return Str::appendWith($namespace, ucfirst($additional), '\\');
        }

        if ($config->has(['application', $dir . 'Dir'])) {
            return self::resolveFromRegisteredDir($dir, $additional);
        }

        return self::resolveFromRelativePath($dir, $additional);
    }

    public static function resolveFromRegisteredDir(string $dir, string $additional): ?string
    {
        $config = self::getConfig();
        $method = 'get' . ucfirst($dir) . 'Directory';
        if (method_exists($config, $method)) {
            return self::resolveFromAbsolutePath($config->$method(), $additional);
        }

        return null;
    }

    public static function resolveFromAbsolutePath(string $path, string $additional): ?string
    {
        $appDir = self::getAppDir();
        $real = realpath($path) ?: $path;
        $relative = str_starts_with($real, $appDir . DIRECTORY_SEPARATOR)
            ? substr($real, strlen($appDir) + 1)
            : $real;

        return self::resolveFromRelativePath($relative, $additional);
    }

    public static function resolveFromRelativePath(string $path, string $additional): string
    {
        $parts = array_filter(explode(DIRECTORY_SEPARATOR, $path));
        array_unshift($parts, self::getNamespace());
        $namespace = implode('\\', array_map('ucfirst', $parts));

        return Str::appendWith($namespace, ucfirst($additional), '\\');
    }

    public static function getNamespace(?string $namespace = null): ?string
    {
        $config = self::getConfig();
        if (!$config->has(['namespaces', 'root'])) {
            return null;
        }

        return $namespace
            ? $config->namespaces->root . "\\{$namespace}"
            : $config->namespaces->root;
    }

    public static function getClassesInNamespace(string $namespace): array
    {
        $dir = self::getNamespaceDirectory($namespace);
        if (!$dir || !is_dir($dir)) {
            return [];
        }

        $classes = [];
        foreach (scandir($dir) as $file) {
            if (str_ends_with($file, '.php')) {
                $class = $namespace . '\\' . pathinfo($file, PATHINFO_FILENAME);
                if (class_exists($class)) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }

    private static function getDefinedNamespaces(): array
    {
        $composerPath = self::getAppDir() . '/composer.json';
        if (!is_readable($composerPath)) {
            return [];
        }
        $composer = json_decode(file_get_contents($composerPath));

        return (array)($composer->autoload->{'psr-4'} ?? []);
    }

    private static function getNamespaceDirectory(string $namespace): string|false
    {
        $composerNs = self::getDefinedNamespaces();
        $fragments = explode('\\', trim($namespace, '\\'));
        $unknown = [];

        while ($fragments) {
            $try = implode('\\', $fragments) . '\\';
            if (isset($composerNs[$try])) {
                $dir = self::getAppDir() . DIRECTORY_SEPARATOR . $composerNs[$try];
                if ($unknown) {
                    $dir .= implode('/', $unknown);
                }
                $real = realpath($dir);
                if ($real) {
                    return $real;
                }
            }
            array_unshift($unknown, array_pop($fragments));
        }

        return false;
    }

    public static function getAppDir(): string
    {
        $candidate = dirname(__DIR__, 5) . '/vendor/autoload.php';

        return file_exists($candidate)
            ? dirname(__DIR__, 5)
            : dirname(__DIR__, 2);
    }

    private static function getConfig(): Config
    {
        $config = Config::getInstance();
        if ($config === null) {
            throw new \RuntimeException('Config not loaded');
        }

        return $config;
    }
}
