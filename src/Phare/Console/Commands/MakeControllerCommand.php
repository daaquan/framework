<?php

namespace Phare\Console\Commands;

use Phare\Console\Command;

class MakeControllerCommand extends Command
{
    protected string $signature = 'make:controller {name : The name of the controller} {--resource : Generate a resource controller}';

    protected string $description = 'Create a new controller class';

    public function handle(): int
    {
        $name = $this->argument('name');
        $resource = $this->option('resource');

        $controllerName = $this->getControllerName($name);
        $path = $this->getControllerPath($controllerName);

        if ($this->files->exists($path)) {
            $this->error("Controller [{$controllerName}] already exists.");

            return 1;
        }

        $stub = $resource ? $this->getResourceStub() : $this->getStub();
        $content = $this->buildClass($controllerName, $stub);

        $this->makeDirectory($path);
        $this->files->put($path, $content);

        $relativePath = str_replace($this->app->basePath() . '/', '', $path);
        $this->info("Controller created successfully at [{$relativePath}].");

        return 0;
    }

    protected function getControllerName(string $name): string
    {
        $name = trim(str_replace('/', '\\', $name), '\\');

        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }

        return $name;
    }

    protected function getControllerPath(string $name): string
    {
        $path = str_replace('\\', '/', $name) . '.php';

        return $this->app->basePath('app/Controllers/' . $path);
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

        $namespace = 'App\\Controllers';
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

use Phare\Http\Controller;

class {{ class }} extends Controller
{
    public function index()
    {
        // Display a listing of the resource
    }

    public function show($id)
    {
        // Display the specified resource
    }

    public function create()
    {
        // Show the form for creating a new resource
    }

    public function store()
    {
        // Store a newly created resource in storage
    }

    public function edit($id)
    {
        // Show the form for editing the specified resource
    }

    public function update($id)
    {
        // Update the specified resource in storage
    }

    public function destroy($id)
    {
        // Remove the specified resource from storage
    }
}
STUB;
    }

    protected function getResourceStub(): string
    {
        return <<<'STUB'
<?php

namespace {{ namespace }};

use Phare\Http\Controller;
use Phare\Http\Request;
use Phare\Http\Response;

class {{ class }} extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return response()->json(['message' => 'Index method']);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return response()->json(['message' => 'Create form']);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): Response
    {
        return response()->json(['message' => 'Resource created']);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): Response
    {
        return response()->json(['message' => "Show resource {$id}"]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): Response
    {
        return response()->json(['message' => "Edit form for resource {$id}"]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): Response
    {
        return response()->json(['message' => "Resource {$id} updated"]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): Response
    {
        return response()->json(['message' => "Resource {$id} deleted"]);
    }
}
STUB;
    }
}
