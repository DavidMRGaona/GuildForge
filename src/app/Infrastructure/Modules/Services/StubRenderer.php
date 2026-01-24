<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class StubRenderer
{
    private string $stubsPath;

    public function __construct(?string $stubsPath = null)
    {
        $this->stubsPath = $stubsPath ?? base_path('stubs/modules');
    }

    /**
     * Render a stub template with the given variables.
     *
     * @param  array<string, string>  $variables
     */
    public function render(string $stubName, array $variables): string
    {
        $stubPath = $this->getStubPath($stubName);

        if (! file_exists($stubPath)) {
            throw new InvalidArgumentException("Stub file not found: {$stubName}");
        }

        $content = file_get_contents($stubPath);
        if ($content === false) {
            throw new RuntimeException("Failed to read stub file: {$stubName}");
        }

        return $this->replaceVariables($content, $variables);
    }

    /**
     * Render a stub and save it to a file.
     *
     * @param  array<string, string>  $variables
     */
    public function renderTo(string $stubName, string $destination, array $variables, bool $force = false): bool
    {
        if (file_exists($destination) && ! $force) {
            return false;
        }

        $content = $this->render($stubName, $variables);
        $directory = dirname($destination);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return file_put_contents($destination, $content) !== false;
    }

    /**
     * Check if a stub file exists.
     */
    public function stubExists(string $stubName): bool
    {
        return file_exists($this->getStubPath($stubName));
    }

    /**
     * Get the full path to a stub file.
     */
    public function getStubPath(string $stubName): string
    {
        // Handle both "module/service-provider.php.stub" and "module/service-provider" formats
        if (! str_ends_with($stubName, '.stub')) {
            $stubName .= '.stub';
        }

        return $this->stubsPath.'/'.$stubName;
    }

    /**
     * Get variables for a module.
     *
     * @return array<string, string>
     */
    public function getModuleVariables(string $moduleName, ?string $description = null, ?string $author = null): array
    {
        $namespace = 'Modules\\'.Str::studly($moduleName);

        return [
            'moduleName' => $moduleName,
            'moduleNameStudly' => Str::studly($moduleName),
            'moduleNameSnake' => Str::snake(Str::studly($moduleName)),
            'moduleNameCamel' => Str::camel($moduleName),
            'moduleNamespace' => $namespace,
            'moduleNamespaceJson' => str_replace('\\', '\\\\', $namespace),
            'moduleDescription' => $description ?? '',
            'moduleAuthor' => $author ?? '',
            'timestamp' => now()->format('Y_m_d_His'),
        ];
    }

    /**
     * Get variables for an entity or component.
     *
     * @return array<string, string>
     */
    public function getComponentVariables(string $moduleName, string $name): array
    {
        $moduleVars = $this->getModuleVariables($moduleName);

        return array_merge($moduleVars, [
            'name' => $name,
            'nameStudly' => Str::studly($name),
            'nameSnake' => Str::snake($name),
            'nameCamel' => Str::camel($name),
            'namePlural' => Str::plural($name),
            'namePluralStudly' => Str::studly(Str::plural($name)),
            'namePluralSnake' => Str::snake(Str::plural($name)),
            'nameKebab' => Str::kebab($name),
            'tableName' => Str::snake(Str::studly($moduleName)).'_'.Str::snake(Str::plural($name)),
        ]);
    }

    /**
     * Replace variables in content.
     *
     * @param  array<string, string>  $variables
     */
    private function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            // Support both {{ variable }} and {{variable}} formats
            $content = str_replace(
                ['{{ '.$key.' }}', '{{'.$key.'}}'],
                $value,
                $content
            );
        }

        return $content;
    }
}
