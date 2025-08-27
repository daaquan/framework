<?php

namespace Phare\Console\Commands;

use Phare\Console\Command;

class MakeMiddlewareCommand extends Command
{
    protected string $signature = 'make:middleware {name : The name of the middleware}';
    protected string $description = 'Create a new middleware class';

    public function handle(): int
    {
        $name = $this->argument('name');

        $middlewareName = $this->getMiddlewareName($name);
        $path = $this->getMiddlewarePath($middlewareName);

        if ($this->files->exists($path)) {
            $this->error("Middleware [{$middlewareName}] already exists.");
            return 1;
        }

        $stub = $this->getStub();
        $content = $this->buildClass($middlewareName, $stub);

        $this->makeDirectory($path);
        $this->files->put($path, $content);

        $relativePath = str_replace($this->app->basePath() . '/', '', $path);
        $this->info("Middleware created successfully at [{$relativePath}].");

        return 0;
    }

    protected function getMiddlewareName(string $name): string
    {
        return trim(str_replace('/', '\\', $name), '\\');
    }

    protected function getMiddlewarePath(string $name): string
    {
        $path = str_replace('\\', '/', $name) . '.php';
        return $this->app->basePath('app/Middleware/' . $path);
    }

    protected function buildClass(string $name, string $stub): string
    {
        $namespace = $this->getNamespace($name);
        $className = $this->getClassName($name);

        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $className,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    protected function getNamespace(string $name): string
    {
        $parts = explode('\\', $name);
        array_pop($parts); // Remove class name
        
        $namespace = 'App\\Middleware';
        if (!empty($parts)) {
            $namespace .= '\\' . implode('\\', $parts);
        }

        return $namespace;
    }

    protected function getClassName(string $name): string
    {
        return class_basename($name);
    }

    protected function getStub(): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

use Phalcon\Http\Request;
use Phalcon\Http\Response;

class {{ class }}
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, \Closure $next, ...$parameters): Response
    {
        // Perform action before the request is handled by the application
        
        $response = $next($request);
        
        // Perform action after the request is handled by the application
        
        return $response;
    }
}
STUB;
    }
}