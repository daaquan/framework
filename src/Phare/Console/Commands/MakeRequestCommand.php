<?php

namespace Phare\Console\Commands;

use Phare\Console\Command;

class MakeRequestCommand extends Command
{
    protected string $signature = 'make:request {name : The name of the form request}';
    protected string $description = 'Create a new form request class';

    public function handle(): int
    {
        $name = $this->argument('name');

        $requestName = $this->getRequestName($name);
        $path = $this->getRequestPath($requestName);

        if ($this->files->exists($path)) {
            $this->error("Request [{$requestName}] already exists.");
            return 1;
        }

        $stub = $this->getStub();
        $content = $this->buildClass($requestName, $stub);

        $this->makeDirectory($path);
        $this->files->put($path, $content);

        $relativePath = str_replace($this->app->basePath() . '/', '', $path);
        $this->info("Request created successfully at [{$relativePath}].");

        return 0;
    }

    protected function getRequestName(string $name): string
    {
        $name = trim(str_replace('/', '\\', $name), '\\');
        
        if (!str_ends_with($name, 'Request')) {
            $name .= 'Request';
        }

        return $name;
    }

    protected function getRequestPath(string $name): string
    {
        $path = str_replace('\\', '/', $name) . '.php';
        return $this->app->basePath('app/Requests/' . $path);
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
        
        $namespace = 'App\\Requests';
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

use Phare\Http\FormRequest;

class {{ class }} extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Add your validation rules here
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Add custom validation messages here
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            // Add custom attribute names here
        ];
    }
}
STUB;
    }
}