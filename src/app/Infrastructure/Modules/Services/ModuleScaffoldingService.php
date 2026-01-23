<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\ScaffoldResultDTO;
use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use App\Domain\Modules\Exceptions\InvalidModuleNameException;
use Illuminate\Support\Str;

final class ModuleScaffoldingService implements ModuleScaffoldingServiceInterface
{
    private const RESERVED_NAMES = [
        'core', 'app', 'admin', 'system', 'modules',
        'api', 'web', 'auth', 'user', 'filament',
        'laravel', 'vendor', 'public', 'storage',
    ];

    private string $modulesPath;

    public function __construct(
        private readonly StubRenderer $stubRenderer,
        ?string $modulesPath = null,
    ) {
        $this->modulesPath = $modulesPath ?? base_path('modules');
    }

    public function createModule(string $name, ?string $description = null, ?string $author = null): ScaffoldResultDTO
    {
        // Validate module name
        $validation = $this->validateModuleName($name);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath . '/' . $name;
        $variables = $this->stubRenderer->getModuleVariables($name, $description, $author);
        $files = [];
        $warnings = [];

        // Create directory structure
        $this->createDirectoryStructure($modulePath);

        // Create module files
        $fileMap = [
            'module/module.json.stub' => 'module.json',
            'module/ModuleServiceProvider.php.stub' => 'src/' . $variables['moduleNameStudly'] . 'ServiceProvider.php',
            'module/config.php.stub' => 'config/module.php',
            'module/routes-web.php.stub' => 'routes/web.php',
            'module/routes-api.php.stub' => 'routes/api.php',
            'module/lang-messages.php.stub' => 'lang/es/messages.php',
        ];

        foreach ($fileMap as $stub => $destination) {
            $destPath = $modulePath . '/' . $destination;
            $result = $this->renderStub($stub, $destPath, $variables);
            $files[$destination] = $result;
        }

        // Create .gitkeep files for empty directories
        $emptyDirs = [
            'database/migrations',
            'resources/views',
            'src/Domain/Entities',
            'src/Domain/ValueObjects',
            'src/Domain/Repositories',
            'src/Application/DTOs',
            'src/Application/Services',
            'src/Infrastructure/Persistence/Eloquent',
            'src/Infrastructure/Services',
            'src/Http/Controllers',
            'src/Http/Requests',
            'src/Filament/Resources',
            'src/Policies',
            'resources/js/pages',
            'resources/js/components',
            'resources/js/types',
            'tests/Unit',
            'tests/Feature',
        ];

        foreach ($emptyDirs as $dir) {
            $gitkeepPath = $modulePath . '/' . $dir . '/.gitkeep';
            if (! file_exists($gitkeepPath)) {
                file_put_contents($gitkeepPath, '');
                $files[$dir . '/.gitkeep'] = ScaffoldResultDTO::STATUS_CREATED;
            }
        }

        $createdCount = count(array_filter($files, fn (string $s): bool => $s === ScaffoldResultDTO::STATUS_CREATED));

        return ScaffoldResultDTO::success(
            "Module '{$name}' created successfully with {$createdCount} files.",
            $files,
            $warnings,
        );
    }

    public function createEntity(string $module, string $name, bool $withMigration = true): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath . '/' . $module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        // Create entity files
        $fileMap = [
            'domain/entity.php.stub' => "src/Domain/Entities/{$variables['nameStudly']}.php",
            'domain/entity-id.php.stub' => "src/Domain/ValueObjects/{$variables['nameStudly']}Id.php",
            'domain/repository-interface.php.stub' => "src/Domain/Repositories/{$variables['nameStudly']}RepositoryInterface.php",
            'infrastructure/eloquent-model.php.stub' => "src/Infrastructure/Persistence/Eloquent/{$variables['nameStudly']}Model.php",
            'infrastructure/eloquent-repository.php.stub' => "src/Infrastructure/Persistence/Eloquent/Eloquent{$variables['nameStudly']}Repository.php",
        ];

        foreach ($fileMap as $stub => $destination) {
            $destPath = $modulePath . '/' . $destination;
            $result = $this->renderStub($stub, $destPath, $variables);
            $files[$destination] = $result;
        }

        // Create migration if requested
        if ($withMigration) {
            $migrationResult = $this->createMigration($module, "create_{$variables['tableName']}_table", $variables['tableName']);
            $files = array_merge($files, $migrationResult->files);
        }

        return ScaffoldResultDTO::success(
            "Entity '{$name}' created for module '{$module}'.",
            $files,
        );
    }

    public function createController(string $module, string $name, string $type = 'default'): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath . '/' . $module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        $stubMap = [
            'default' => 'http/controller.php.stub',
            'resource' => 'http/controller-resource.php.stub',
            'api' => 'http/controller-api.php.stub',
            'invokable' => 'http/controller-invokable.php.stub',
        ];

        $stub = $stubMap[$type] ?? $stubMap['default'];
        $subDir = $type === 'api' ? 'src/Http/Controllers/Api' : 'src/Http/Controllers';
        $destination = "{$subDir}/{$variables['nameStudly']}Controller.php";
        $destPath = $modulePath . '/' . $destination;

        // Create directory if needed
        $dir = dirname($destPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $result = $this->renderStub($stub, $destPath, $variables);
        $files[$destination] = $result;

        return ScaffoldResultDTO::success(
            "Controller '{$name}' created for module '{$module}'.",
            $files,
        );
    }

    public function createRequest(string $module, string $name): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath . '/' . $module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        $destination = "src/Http/Requests/{$variables['nameStudly']}Request.php";
        $destPath = $modulePath . '/' . $destination;

        $result = $this->renderStub('http/request.php.stub', $destPath, $variables);
        $files[$destination] = $result;

        return ScaffoldResultDTO::success(
            "Request '{$name}' created for module '{$module}'.",
            $files,
        );
    }

    public function createService(string $module, string $name, bool $withInterface = true): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath . '/' . $module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        // Create service implementation
        $destination = "src/Infrastructure/Services/{$variables['nameStudly']}Service.php";
        $destPath = $modulePath . '/' . $destination;
        $result = $this->renderStub('application/service.php.stub', $destPath, $variables);
        $files[$destination] = $result;

        // Create interface if requested
        if ($withInterface) {
            $interfaceDestination = "src/Application/Services/{$variables['nameStudly']}ServiceInterface.php";
            $interfaceDestPath = $modulePath . '/' . $interfaceDestination;
            $result = $this->renderStub('application/service-interface.php.stub', $interfaceDestPath, $variables);
            $files[$interfaceDestination] = $result;
        }

        return ScaffoldResultDTO::success(
            "Service '{$name}' created for module '{$module}'.",
            $files,
        );
    }

    public function createDto(string $module, string $name, bool $isResponse = false): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath . '/' . $module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        $stub = $isResponse ? 'application/response-dto.php.stub' : 'application/dto.php.stub';
        $suffix = $isResponse ? 'ResponseDTO' : 'DTO';
        $destination = "src/Application/DTOs/{$variables['nameStudly']}{$suffix}.php";
        $destPath = $modulePath . '/' . $destination;

        $result = $this->renderStub($stub, $destPath, $variables);
        $files[$destination] = $result;

        return ScaffoldResultDTO::success(
            "DTO '{$name}' created for module '{$module}'.",
            $files,
        );
    }

    public function createFilamentResource(string $module, string $name): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath . '/' . $module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        // Create resource directory
        $resourceDir = $modulePath . "/src/Filament/Resources/{$variables['nameStudly']}Resource/Pages";
        if (! is_dir($resourceDir)) {
            mkdir($resourceDir, 0755, true);
        }

        // Create resource files
        $fileMap = [
            'filament/resource.php.stub' => "src/Filament/Resources/{$variables['nameStudly']}Resource.php",
            'filament/page-list.php.stub' => "src/Filament/Resources/{$variables['nameStudly']}Resource/Pages/List{$variables['namePluralStudly']}.php",
            'filament/page-create.php.stub' => "src/Filament/Resources/{$variables['nameStudly']}Resource/Pages/Create{$variables['nameStudly']}.php",
            'filament/page-edit.php.stub' => "src/Filament/Resources/{$variables['nameStudly']}Resource/Pages/Edit{$variables['nameStudly']}.php",
        ];

        foreach ($fileMap as $stub => $destination) {
            $destPath = $modulePath . '/' . $destination;
            $result = $this->renderStub($stub, $destPath, $variables);
            $files[$destination] = $result;
        }

        // Create or update Filament translations
        $translationsResult = $this->createOrUpdateFilamentTranslations($modulePath, $variables);
        $files = array_merge($files, $translationsResult);

        // Create policy for the resource
        $policyResult = $this->createPolicy($module, $name);
        if ($policyResult->success) {
            $files = array_merge($files, $policyResult->files);
        }

        return ScaffoldResultDTO::success(
            "Filament resource '{$name}' created for module '{$module}'.",
            $files,
        );
    }

    public function createPolicy(string $module, string $name): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath . '/' . $module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        // Ensure Policies directory exists
        $policiesDir = $modulePath . '/src/Policies';
        if (! is_dir($policiesDir)) {
            mkdir($policiesDir, 0755, true);
        }

        $destination = "src/Policies/{$variables['nameStudly']}Policy.php";
        $destPath = $modulePath . '/' . $destination;

        $result = $this->renderStub('policy/policy.php.stub', $destPath, $variables);
        $files[$destination] = $result;

        return ScaffoldResultDTO::success(
            "Policy '{$name}' created for module '{$module}'.",
            $files,
        );
    }

    public function createMigration(string $module, string $name, ?string $table = null): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath . '/' . $module;
        $variables = $this->stubRenderer->getModuleVariables($module);

        // Generate table name from migration name if not provided
        if ($table === null) {
            $table = $this->extractTableName($name, $module);
        }

        $variables['tableName'] = $table;
        $files = [];

        $timestamp = now()->format('Y_m_d_His');
        $migrationName = Str::snake($name);
        $destination = "database/migrations/{$timestamp}_{$migrationName}.php";
        $destPath = $modulePath . '/' . $destination;

        $result = $this->renderStub('infrastructure/migration.php.stub', $destPath, $variables);
        $files[$destination] = $result;

        return ScaffoldResultDTO::success(
            "Migration '{$name}' created for module '{$module}'.",
            $files,
        );
    }

    public function createTest(string $module, string $name, string $type = 'unit'): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath . '/' . $module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        $stubMap = [
            'unit' => 'test/unit-entity.php.stub',
            'unit-dto' => 'test/unit-dto.php.stub',
            'feature' => 'test/feature-controller.php.stub',
            'feature-filament' => 'test/feature-filament.php.stub',
        ];

        $stub = $stubMap[$type] ?? $stubMap['unit'];
        $dir = str_starts_with($type, 'feature') ? 'Feature' : 'Unit';
        $destination = "tests/{$dir}/{$variables['nameStudly']}Test.php";
        $destPath = $modulePath . '/' . $destination;

        $result = $this->renderStub($stub, $destPath, $variables);
        $files[$destination] = $result;

        return ScaffoldResultDTO::success(
            "Test '{$name}' created for module '{$module}'.",
            $files,
        );
    }

    public function createVuePage(string $module, string $name): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath . '/' . $module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        $destination = "resources/js/pages/{$variables['nameStudly']}.vue";
        $destPath = $modulePath . '/' . $destination;

        $result = $this->renderStub('vue/page.vue.stub', $destPath, $variables);
        $files[$destination] = $result;

        // Also create types file
        $typesDestination = "resources/js/types/{$variables['nameStudly']}.ts";
        $typesDestPath = $modulePath . '/' . $typesDestination;
        $typesResult = $this->renderStub('vue/types.ts.stub', $typesDestPath, $variables);
        $files[$typesDestination] = $typesResult;

        return ScaffoldResultDTO::success(
            "Vue page '{$name}' created for module '{$module}'.",
            $files,
        );
    }

    public function createVueComponent(string $module, string $name): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath . '/' . $module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        $destination = "resources/js/components/{$variables['nameStudly']}.vue";
        $destPath = $modulePath . '/' . $destination;

        $result = $this->renderStub('vue/component.vue.stub', $destPath, $variables);
        $files[$destination] = $result;

        return ScaffoldResultDTO::success(
            "Vue component '{$name}' created for module '{$module}'.",
            $files,
        );
    }

    private function validateModuleName(string $name): ?ScaffoldResultDTO
    {
        // Check for reserved names
        if (in_array(strtolower($name), self::RESERVED_NAMES, true)) {
            return ScaffoldResultDTO::failure(
                "Cannot create module '{$name}': name is reserved.",
                ["The name '{$name}' is a reserved module name."]
            );
        }

        // Validate format (kebab-case)
        try {
            new \App\Domain\Modules\ValueObjects\ModuleName($name);
        } catch (InvalidModuleNameException $e) {
            return ScaffoldResultDTO::failure(
                "Invalid module name '{$name}'.",
                [$e->getMessage()]
            );
        }

        // Check if module already exists
        $modulePath = $this->modulesPath . '/' . $name;
        if (is_dir($modulePath)) {
            return ScaffoldResultDTO::failure(
                "Module '{$name}' already exists.",
                ["Directory '{$modulePath}' already exists."]
            );
        }

        return null;
    }

    private function validateModuleExists(string $module): ?ScaffoldResultDTO
    {
        $modulePath = $this->modulesPath . '/' . $module;

        if (! is_dir($modulePath)) {
            return ScaffoldResultDTO::failure(
                "Module '{$module}' does not exist.",
                ["Directory '{$modulePath}' not found."]
            );
        }

        return null;
    }

    private function createDirectoryStructure(string $modulePath): void
    {
        $directories = [
            '',
            'config',
            'database/migrations',
            'lang/es',
            'resources/views',
            'resources/js/pages',
            'resources/js/components',
            'resources/js/types',
            'routes',
            'src',
            'src/Domain/Entities',
            'src/Domain/ValueObjects',
            'src/Domain/Repositories',
            'src/Application/DTOs',
            'src/Application/Services',
            'src/Infrastructure/Persistence/Eloquent',
            'src/Infrastructure/Services',
            'src/Http/Controllers',
            'src/Http/Controllers/Api',
            'src/Http/Requests',
            'src/Filament/Resources',
            'src/Policies',
            'tests/Unit',
            'tests/Feature',
        ];

        foreach ($directories as $dir) {
            $path = $modulePath . ($dir !== '' ? '/' . $dir : '');
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    private function renderStub(string $stub, string $destination, array $variables, bool $force = false): string
    {
        // Ensure parent directory exists
        $dir = dirname($destination);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_exists($destination) && ! $force) {
            return ScaffoldResultDTO::STATUS_SKIPPED;
        }

        $rendered = $this->stubRenderer->renderTo($stub, $destination, $variables, $force);

        if ($rendered) {
            return $force && file_exists($destination)
                ? ScaffoldResultDTO::STATUS_OVERWRITTEN
                : ScaffoldResultDTO::STATUS_CREATED;
        }

        return ScaffoldResultDTO::STATUS_FAILED;
    }

    private function extractTableName(string $migrationName, string $module): string
    {
        // Extract table name from migration name like "create_games_table"
        $name = Str::snake($migrationName);

        if (preg_match('/^create_(.+)_table$/', $name, $matches)) {
            return Str::snake(Str::studly($module)) . '_' . $matches[1];
        }

        return Str::snake(Str::studly($module)) . '_' . Str::snake($name);
    }

    /**
     * Create or update Filament translations for a resource.
     *
     * @param array<string, string> $variables
     *
     * @return array<string, string>
     */
    private function createOrUpdateFilamentTranslations(string $modulePath, array $variables): array
    {
        $files = [];
        $translationFile = $modulePath . '/lang/es/filament.php';
        $nameSnake = $variables['nameSnake'];

        // Ensure lang/es directory exists
        $langDir = dirname($translationFile);
        if (! is_dir($langDir)) {
            mkdir($langDir, 0755, true);
        }

        // New resource translations
        $newTranslations = [
            $nameSnake => [
                'label' => $variables['nameStudly'],
                'plural_label' => $variables['namePluralStudly'],
                'fields' => [
                    'name' => 'Nombre',
                    'created_at' => 'Creado',
                    'updated_at' => 'Actualizado',
                ],
            ],
        ];

        if (file_exists($translationFile)) {
            // Load existing translations and merge
            $existing = include $translationFile;
            if (! is_array($existing)) {
                $existing = [];
            }

            // Only add if resource doesn't already exist
            if (! isset($existing[$nameSnake])) {
                $translations = array_merge($existing, $newTranslations);
                $this->writeTranslationFile($translationFile, $translations);
                $files['lang/es/filament.php'] = ScaffoldResultDTO::STATUS_OVERWRITTEN;
            } else {
                $files['lang/es/filament.php'] = ScaffoldResultDTO::STATUS_SKIPPED;
            }
        } else {
            // Create new file
            $this->writeTranslationFile($translationFile, $newTranslations);
            $files['lang/es/filament.php'] = ScaffoldResultDTO::STATUS_CREATED;
        }

        return $files;
    }

    /**
     * Write translations array to a PHP file.
     *
     * @param array<string, mixed> $translations
     */
    private function writeTranslationFile(string $path, array $translations): void
    {
        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . $this->varExport($translations) . ";\n";
        file_put_contents($path, $content);
    }

    /**
     * Export array with proper formatting.
     *
     * @param array<string, mixed> $array
     */
    private function varExport(array $array, int $indent = 0): string
    {
        $spaces = str_repeat('    ', $indent);
        $innerSpaces = str_repeat('    ', $indent + 1);
        $output = "[\n";

        foreach ($array as $key => $value) {
            $output .= $innerSpaces . "'" . addslashes((string) $key) . "' => ";

            if (is_array($value)) {
                $output .= $this->varExport($value, $indent + 1);
            } else {
                $output .= "'" . addslashes((string) $value) . "'";
            }

            $output .= ",\n";
        }

        $output .= $spaces . ']';

        return $output;
    }
}
