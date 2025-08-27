<?php

namespace Phare\View\Template;

use Phare\View\Factory;

class TemplateEngine
{
    protected Factory $factory;
    protected array $sections = [];
    protected array $sectionStack = [];
    protected array $layouts = [];
    protected ?string $extends = null;
    protected array $yields = [];
    protected array $includes = [];

    public function __construct(Factory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Start a section.
     */
    public function startSection(string $section, ?string $content = null): void
    {
        if ($content === null) {
            if (ob_get_level()) {
                ob_start();
            }
            $this->sectionStack[] = $section;
        } else {
            $this->sections[$section] = $content;
        }
    }

    /**
     * Stop the current section.
     */
    public function stopSection(bool $overwrite = false): string
    {
        if (empty($this->sectionStack)) {
            throw new \InvalidArgumentException('Cannot end a section without first starting one.');
        }

        $last = array_pop($this->sectionStack);
        
        if (ob_get_level()) {
            $content = ob_get_clean();
            
            if ($overwrite || !isset($this->sections[$last])) {
                $this->sections[$last] = $content;
            } else {
                $this->sections[$last] .= $content;
            }
        }

        return $this->sections[$last] ?? '';
    }

    /**
     * Append content to a section.
     */
    public function appendSection(): string
    {
        if (empty($this->sectionStack)) {
            throw new \InvalidArgumentException('Cannot end a section without first starting one.');
        }

        $last = array_pop($this->sectionStack);
        
        if (ob_get_level()) {
            $content = ob_get_clean();
            
            if (!isset($this->sections[$last])) {
                $this->sections[$last] = '';
            }
            
            $this->sections[$last] .= $content;
        }

        return $this->sections[$last] ?? '';
    }

    /**
     * Get the content of a section.
     */
    public function yieldContent(string $section, string $default = ''): string
    {
        return $this->sections[$section] ?? $default;
    }

    /**
     * Extend a parent template.
     */
    public function extend(string $view): void
    {
        $this->extends = $view;
    }

    /**
     * Get the parent template.
     */
    public function getExtends(): ?string
    {
        return $this->extends;
    }

    /**
     * Include a partial template.
     */
    public function include(string $view, array $data = []): string
    {
        $viewInstance = $this->factory->make($view, $data);
        return $viewInstance->render();
    }

    /**
     * Include a template if it exists.
     */
    public function includeIf(string $view, array $data = []): string
    {
        if ($this->factory->exists($view)) {
            return $this->include($view, $data);
        }

        return '';
    }

    /**
     * Include the first template that exists.
     */
    public function includeFirst(array $views, array $data = []): string
    {
        foreach ($views as $view) {
            if ($this->factory->exists($view)) {
                return $this->include($view, $data);
            }
        }

        return '';
    }

    /**
     * Include a template unless a condition is true.
     */
    public function includeUnless(bool $condition, string $view, array $data = []): string
    {
        if (!$condition) {
            return $this->include($view, $data);
        }

        return '';
    }

    /**
     * Include a template when a condition is true.
     */
    public function includeWhen(bool $condition, string $view, array $data = []): string
    {
        if ($condition) {
            return $this->include($view, $data);
        }

        return '';
    }

    /**
     * Check if a section has content.
     */
    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    /**
     * Get all sections.
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * Flush all sections.
     */
    public function flushSections(): void
    {
        $this->sections = [];
        $this->sectionStack = [];
    }

    /**
     * Start injecting content into a push stack.
     */
    public function startPush(string $section, string $content = null): void
    {
        if ($content === null) {
            if (ob_get_level()) {
                ob_start();
            }
            $this->sectionStack[] = $section;
        } else {
            $this->push($section, $content);
        }
    }

    /**
     * Stop injecting content into a push stack.
     */
    public function stopPush(): string
    {
        if (empty($this->sectionStack)) {
            throw new \InvalidArgumentException('Cannot end a push without first starting one.');
        }

        $last = array_pop($this->sectionStack);
        
        if (ob_get_level()) {
            $content = ob_get_clean();
            $this->push($last, $content);
        }

        return $this->sections[$last] ?? '';
    }

    /**
     * Append content to a push stack.
     */
    public function push(string $section, string $content): void
    {
        if (!isset($this->sections[$section])) {
            $this->sections[$section] = '';
        }

        $this->sections[$section] .= $content;
    }

    /**
     * Get the content of a stack.
     */
    public function stack(string $section): string
    {
        return $this->sections[$section] ?? '';
    }

    /**
     * Start prepending content to a push stack.
     */
    public function startPrepend(string $section): void
    {
        if (ob_get_level()) {
            ob_start();
        }
        $this->sectionStack[] = $section . '.prepend';
    }

    /**
     * Stop prepending content to a push stack.
     */
    public function stopPrepend(): string
    {
        if (empty($this->sectionStack)) {
            throw new \InvalidArgumentException('Cannot end a prepend without first starting one.');
        }

        $last = array_pop($this->sectionStack);
        $section = str_replace('.prepend', '', $last);
        
        if (ob_get_level()) {
            $content = ob_get_clean();
            $this->prepend($section, $content);
        }

        return $this->sections[$section] ?? '';
    }

    /**
     * Prepend content to a push stack.
     */
    public function prepend(string $section, string $content): void
    {
        if (!isset($this->sections[$section])) {
            $this->sections[$section] = '';
        }

        $this->sections[$section] = $content . $this->sections[$section];
    }
}