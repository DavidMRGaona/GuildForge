<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\ModuleManifestDTO;
use App\Application\Modules\DTOs\ScaffoldResultDTO;
use App\Application\Modules\Services\ModuleScaffoldingServiceInterface;
use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Domain\Modules\Exceptions\InvalidModuleNameException;
use App\Domain\Modules\ValueObjects\ModuleName;
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
        private readonly ?CoreVersionServiceInterface $coreVersion = null,
    ) {
        $this->modulesPath = $modulesPath ?? base_path('modules');
    }

    public function createModule(
        string $name,
        ?string $description = null,
        ?string $author = null,
        ?string $displayName = null,
        ?string $repository = null,
    ): ScaffoldResultDTO {
        // Validate module name
        $validation = $this->validateModuleName($name);
        if ($validation !== null) {
            return $validation;
        }

        if ($repository !== null && ! ModuleManifestDTO::isValidRepository($repository)) {
            return ScaffoldResultDTO::failure(
                "Invalid repository '{$repository}'.",
                ["The repository must be a GitHub repository in 'owner/repo' form."]
            );
        }

        $modulePath = $this->modulesPath.'/'.$name;
        $displayName ??= Str::ucfirst(str_replace('-', ' ', $name));
        $variables = $this->stubRenderer->getModuleVariables($name, $description, $author);
        $variables['coreConstraint'] = $this->coreConstraint();
        $variables['moduleDisplayNamePhp'] = addcslashes($displayName, "'\\");
        $variables['moduleDisplayNameJson'] = $this->jsonString($displayName);
        $variables['moduleDisplayNameTs'] = addcslashes($displayName, "'\\");
        $variables['moduleDescriptionJson'] = $this->jsonString($description ?? '');
        $variables['moduleAuthorJson'] = $this->jsonString($author ?? '');
        $variables['moduleRepositoryJson'] = $repository !== null
            ? "\n    \"repository\": \"{$repository}\","
            : '';
        $files = [];
        $warnings = [];

        // Create directory structure
        $this->createDirectoryStructure($modulePath);

        // Create module files
        $fileMap = [
            'module/module.json.stub' => 'module.json',
            'module/ModuleServiceProvider.php.stub' => 'src/'.$variables['moduleNameStudly'].'ServiceProvider.php',
            'module/config.php.stub' => 'config/module.php',
            'module/routes-web.php.stub' => 'routes/web.php',
            'module/routes-api.php.stub' => 'routes/api.php',
            'module/lang-messages.php.stub' => 'lang/es/messages.php',
            'module/vite.config.ts.stub' => 'vite.config.ts',
            'module/package.json.stub' => 'package.json',
            'module/tsconfig.json.stub' => 'tsconfig.json',
            'module/module.css.stub' => 'resources/css/module.css',
            'module/phpunit.xml.stub' => 'phpunit.xml',
            'module/gitignore.stub' => '.gitignore',
            'module/release.yml.stub' => '.github/workflows/release.yml',
            'module/locale.ts.stub' => 'resources/js/locales/es.ts',
        ];

        foreach ($fileMap as $stub => $destination) {
            $destPath = $modulePath.'/'.$destination;
            $result = $this->renderStub($stub, $destPath, $variables);
            $files[$destination] = $result;
        }

        // Same keys in English until someone translates them
        $files['resources/js/locales/en.ts'] = $this->renderStub('module/locale.ts.stub', $modulePath.'/resources/js/locales/en.ts', $variables);

        // Create .gitkeep files for empty directories
        $emptyDirs = [
            'database/migrations',
            'resources/views',
            'src/Domain/Entities',
            'src/Domain/ValueObjects',
            'src/Domain/Repositories',
            'src/Application/DTOs',
            'src/Application/Services',
            'src/Infrastructure/Persistence/Eloquent/Models',
            'src/Infrastructure/Persistence/Eloquent/Repositories',
            'src/Infrastructure/Services',
            'src/Http/Controllers',
            'src/Http/Requests',
            'src/Filament/Resources',
            'src/Policies',
            'resources/js/pages',
            'resources/js/components',
            'resources/js/types',
            'tests/Unit',
            'tests/Integration',
            'tests/Feature',
        ];

        foreach ($emptyDirs as $dir) {
            $gitkeepPath = $modulePath.'/'.$dir.'/.gitkeep';
            if (! file_exists($gitkeepPath)) {
                file_put_contents($gitkeepPath, '');
                $files[$dir.'/.gitkeep'] = ScaffoldResultDTO::STATUS_CREATED;
            }
        }

        $createdCount = count(array_filter($files, fn (string $s): bool => $s === ScaffoldResultDTO::STATUS_CREATED));

        $nextSteps = [
            'Check "displayName" in module.json and "title" in lang/es/messages.php: both are shown in the admin panel',
            'Run php artisan module:discover to register the module',
            "Run php artisan module:enable {$name} to enable it",
        ];
        if ($repository === null) {
            $nextSteps[] = "Set \"repository\" (owner/repo) in module.json, or run php artisan module:set-source {$name} owner/repo once installed, so updates are found";
        }
        $nextSteps[] = "Once it has Vue pages or components, run php artisan module:build {$name} (or npm install && npm run build in the module)";
        $nextSteps[] = "Run its tests with vendor/bin/phpunit -c modules/{$name}/phpunit.xml";

        return ScaffoldResultDTO::success(
            "Module '{$name}' created successfully with {$createdCount} files.",
            $files,
            $warnings,
            $nextSteps,
        );
    }

    public function createEntity(string $module, string $name, bool $withMigration = true): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath.'/'.$module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        // Create entity files
        $fileMap = [
            'domain/entity.php.stub' => "src/Domain/Entities/{$variables['nameStudly']}.php",
            'domain/entity-id.php.stub' => "src/Domain/ValueObjects/{$variables['nameStudly']}Id.php",
            'domain/repository-interface.php.stub' => "src/Domain/Repositories/{$variables['nameStudly']}RepositoryInterface.php",
            'infrastructure/eloquent-model.php.stub' => "src/Infrastructure/Persistence/Eloquent/Models/{$variables['nameStudly']}Model.php",
            'infrastructure/eloquent-repository.php.stub' => "src/Infrastructure/Persistence/Eloquent/Repositories/Eloquent{$variables['nameStudly']}Repository.php",
        ];

        foreach ($fileMap as $stub => $destination) {
            $destPath = $modulePath.'/'.$destination;
            $result = $this->renderStub($stub, $destPath, $variables);
            $files[$destination] = $result;
        }

        // Create migration if requested
        if ($withMigration) {
            $migrationResult = $this->createMigration($module, "create_{$variables['tableName']}_table", $variables['tableName']);
            $files = array_merge($files, $migrationResult->files);
        }

        $studly = $variables['nameStudly'];

        return ScaffoldResultDTO::success(
            "Entity '{$name}' created for module '{$module}'.",
            $files,
            nextSteps: [
                "Bind the repository in the module service provider's register(): \$this->app->bind({$studly}RepositoryInterface::class, Eloquent{$studly}Repository::class);",
                $withMigration
                    ? "Enabling the module runs its migrations; if it is already enabled, run php artisan module:migrate {$module}"
                    : "Create its table ({$variables['tableName']}): php artisan module:make-migration {$module} create_{$variables['namePluralSnake']}_table (or rerun this command with --migration)",
            ],
        );
    }

    public function createController(string $module, string $name, string $type = 'default'): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath.'/'.$module;
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
        $destPath = $modulePath.'/'.$destination;

        // Create directory if needed
        $dir = dirname($destPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $result = $this->renderStub($stub, $destPath, $variables);
        $files[$destination] = $result;

        if ($type === 'resource') {
            $files = array_merge($files, $this->mergeTranslations($modulePath, 'messages', [
                $variables['nameSnake'] => [
                    'created' => 'Registro creado.',
                    'updated' => 'Registro actualizado.',
                    'deleted' => 'Registro eliminado.',
                ],
            ]));
        }

        return ScaffoldResultDTO::success(
            "Controller '{$name}' created for module '{$module}'.",
            $files,
            nextSteps: $this->controllerNextSteps($module, $type, $variables),
        );
    }

    public function createRequest(string $module, string $name): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath.'/'.$module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        $destination = "src/Http/Requests/{$variables['nameStudly']}Request.php";
        $destPath = $modulePath.'/'.$destination;

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

        $modulePath = $this->modulesPath.'/'.$module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        $variables['serviceImplements'] = $withInterface ? " implements {$variables['nameStudly']}ServiceInterface" : '';
        $variables['serviceInterfaceImport'] = $withInterface
            ? "use {$variables['moduleNamespace']}\\Application\\Services\\{$variables['nameStudly']}ServiceInterface;\n"
            : '';

        // Create service implementation
        $destination = "src/Infrastructure/Services/{$variables['nameStudly']}Service.php";
        $destPath = $modulePath.'/'.$destination;
        $result = $this->renderStub('application/service.php.stub', $destPath, $variables);
        $files[$destination] = $result;

        // Create interface if requested
        if ($withInterface) {
            $interfaceDestination = "src/Application/Services/{$variables['nameStudly']}ServiceInterface.php";
            $interfaceDestPath = $modulePath.'/'.$interfaceDestination;
            $result = $this->renderStub('application/service-interface.php.stub', $interfaceDestPath, $variables);
            $files[$interfaceDestination] = $result;
        }

        $studly = $variables['nameStudly'];
        $nextSteps = [
            "The service works on the {$studly} entity and its DTOs: run module:make-entity {$module} {$studly}, module:make-dto {$module} {$studly} and module:make-dto {$module} {$studly} --response if they do not exist",
        ];
        if ($withInterface) {
            $nextSteps[] = "Bind it in the module service provider's register(): \$this->app->bind({$studly}ServiceInterface::class, {$studly}Service::class);";
        }

        return ScaffoldResultDTO::success(
            "Service '{$name}' created for module '{$module}'.",
            $files,
            nextSteps: $nextSteps,
        );
    }

    public function createDto(string $module, string $name, bool $isResponse = false): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath.'/'.$module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        $stub = $isResponse ? 'application/response-dto.php.stub' : 'application/dto.php.stub';
        $suffix = $isResponse ? 'ResponseDTO' : 'DTO';
        $destination = "src/Application/DTOs/{$variables['nameStudly']}{$suffix}.php";
        $destPath = $modulePath.'/'.$destination;

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

        $modulePath = $this->modulesPath.'/'.$module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        // Create resource directory
        $resourceDir = $modulePath."/src/Filament/Resources/{$variables['nameStudly']}Resource/Pages";
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
            $destPath = $modulePath.'/'.$destination;
            $result = $this->renderStub($stub, $destPath, $variables);
            $files[$destination] = $result;
        }

        $files = array_merge($files, $this->mergeTranslations($modulePath, 'filament', [
            'navigation_group' => $this->moduleDisplayName($modulePath, $module),
            $variables['nameSnake'] => [
                'label' => $this->label($name),
                'plural_label' => $this->sentence(Str::plural($name)),
                'fields' => [
                    'name' => 'Nombre',
                    'created_at' => 'Creado',
                    'updated_at' => 'Actualizado',
                ],
            ],
        ]));

        // Create policy for the resource
        $policyResult = $this->createPolicy($module, $name);
        if ($policyResult->success) {
            $files = array_merge($files, $policyResult->files);
        }

        return ScaffoldResultDTO::success(
            "Filament resource '{$name}' created for module '{$module}'.",
            $files,
            nextSteps: array_merge(
                ["It manages {$variables['nameStudly']}Model: run module:make-entity {$module} {$variables['nameStudly']} if it does not exist"],
                $policyResult->nextSteps,
            ),
        );
    }

    public function createPolicy(string $module, string $name): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath.'/'.$module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        // Ensure Policies directory exists
        $policiesDir = $modulePath.'/src/Policies';
        if (! is_dir($policiesDir)) {
            mkdir($policiesDir, 0755, true);
        }

        $destination = "src/Policies/{$variables['nameStudly']}Policy.php";
        $destPath = $modulePath.'/'.$destination;

        $result = $this->renderStub('policy/policy.php.stub', $destPath, $variables);
        $files[$destination] = $result;

        $studly = $variables['nameStudly'];
        $permissions = $variables['namePluralSnake'];

        return ScaffoldResultDTO::success(
            "Policy '{$name}' created for module '{$module}'.",
            $files,
            nextSteps: [
                "Activate the policy in the module service provider's registerPolicies(): {$studly}Model::class => {$studly}Policy::class",
                "Declare its permissions in registerPermissions() with module: '{$module}' and the names {$permissions}.view_any, {$permissions}.view, {$permissions}.create, {$permissions}.update and {$permissions}.delete (admins pass every check; other roles need them)",
            ],
        );
    }

    public function createMigration(string $module, string $name, ?string $table = null, bool $modify = false): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath.'/'.$module;
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
        $destPath = $modulePath.'/'.$destination;

        $stub = $modify ? 'infrastructure/migration-update.php.stub' : 'infrastructure/migration.php.stub';
        $result = $this->renderStub($stub, $destPath, $variables);
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

        $modulePath = $this->modulesPath.'/'.$module;
        $files = [];

        // Stub, directory and class suffix of each type: the class is named after its file
        $types = [
            'unit' => ['test/unit-entity.php.stub', 'Unit/Domain/Entities', ''],
            'unit-dto' => ['test/unit-dto.php.stub', 'Unit/Application/DTOs', 'DTO'],
            'feature' => ['test/feature-controller.php.stub', 'Feature/Http/Controllers', 'Controller'],
            'feature-filament' => ['test/feature-filament.php.stub', 'Feature/Filament', 'Resource'],
        ];
        $type = isset($types[$type]) ? $type : 'unit';
        [$stub, $dir, $suffix] = $types[$type];

        // "PostController" and "Post" both name the test of PostController
        $subject = Str::studly($name);
        if ($suffix !== '' && $subject !== $suffix && str_ends_with($subject, $suffix)) {
            $subject = substr($subject, 0, -strlen($suffix));
        }

        $variables = $this->stubRenderer->getComponentVariables($module, $subject);
        $destination = "tests/{$dir}/{$variables['nameStudly']}{$suffix}Test.php";
        $destPath = $modulePath.'/'.$destination;

        $result = $this->renderStub($stub, $destPath, $variables);
        $files[$destination] = $result;

        $nextSteps = ["Run the module tests with vendor/bin/phpunit -c modules/{$module}/phpunit.xml"];
        if ($type === 'feature') {
            $nextSteps[] = "The test requests the route module:make-controller prints: Route::get('{$variables['namePluralKebab']}', [{$variables['nameStudly']}Controller::class, 'index'])->name('{$variables['namePluralSnake']}.index'); in routes/web.php";
        }

        return ScaffoldResultDTO::success(
            "Test '{$name}' created for module '{$module}'.",
            $files,
            nextSteps: $nextSteps,
        );
    }

    public function createVuePage(string $module, string $name): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath.'/'.$module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        // Under the module's page prefix (registerPagePrefixes()), rendered as "<ModuleStudly>/<Name>"
        $destination = "resources/js/pages/{$variables['moduleNameStudly']}/{$variables['nameStudly']}.vue";
        $destPath = $modulePath.'/'.$destination;

        $result = $this->renderStub('vue/page.vue.stub', $destPath, $variables);
        $files[$destination] = $result;

        // Also create types file
        $typesDestination = "resources/js/types/{$variables['nameStudly']}.ts";
        $typesDestPath = $modulePath.'/'.$typesDestination;
        $typesResult = $this->renderStub('vue/types.ts.stub', $typesDestPath, $variables);
        $files[$typesDestination] = $typesResult;

        return ScaffoldResultDTO::success(
            "Vue page '{$name}' created for module '{$module}'.",
            $files,
            nextSteps: [
                "Render it from a controller with Inertia::render('{$variables['moduleNameStudly']}/{$variables['nameStudly']}') (module:make-controller {$module} {$variables['nameStudly']})",
                "Its texts come from resources/js/locales/es.ts ({$variables['moduleNameCamel']}.*)",
                "Build the module assets: php artisan module:build {$module}",
            ],
        );
    }

    public function createVueComponent(string $module, string $name): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath.'/'.$module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        $destination = "resources/js/components/{$variables['nameStudly']}.vue";
        $destPath = $modulePath.'/'.$destination;

        $result = $this->renderStub('vue/component.vue.stub', $destPath, $variables);
        $files[$destination] = $result;

        return ScaffoldResultDTO::success(
            "Vue component '{$name}' created for module '{$module}'.",
            $files,
            nextSteps: [
                "Build the module assets: php artisan module:build {$module}",
            ],
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
            new ModuleName($name);
        } catch (InvalidModuleNameException $e) {
            return ScaffoldResultDTO::failure(
                "Invalid module name '{$name}'.",
                [$e->getMessage()]
            );
        }

        // Check if module already exists
        $modulePath = $this->modulesPath.'/'.$name;
        if (is_dir($modulePath)) {
            return ScaffoldResultDTO::failure(
                "Module '{$name}' already exists.",
                ["Directory '{$modulePath}' already exists."]
            );
        }

        return null;
    }

    /**
     * The inside of a JSON string literal holding the given text.
     */
    private function jsonString(string $value): string
    {
        return substr(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 1, -1);
    }

    /**
     * New modules require the core minor they were generated on: "^2.6" on core 2.6.x.
     */
    private function coreConstraint(): string
    {
        $version = ($this->coreVersion ?? app(CoreVersionServiceInterface::class))->getCurrentVersion();

        return "^{$version->major}.{$version->minor}";
    }

    private function validateModuleExists(string $module): ?ScaffoldResultDTO
    {
        $modulePath = $this->modulesPath.'/'.$module;

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
            'src/Infrastructure/Listeners',
            'src/Infrastructure/Persistence/Eloquent/Models',
            'src/Infrastructure/Persistence/Eloquent/Repositories',
            'src/Infrastructure/Services',
            'src/Notifications',
            'src/Http/Controllers',
            'src/Http/Controllers/Api',
            'src/Http/Requests',
            'src/Filament/Resources',
            'src/Policies',
            'tests/Unit',
            'tests/Integration',
            'tests/Feature',
        ];

        foreach ($directories as $dir) {
            $path = $modulePath.($dir !== '' ? '/'.$dir : '');
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $variables
     */
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
            return Str::snake(Str::studly($module)).'_'.$matches[1];
        }

        return Str::snake(Str::studly($module)).'_'.Str::snake($name);
    }

    /**
     * Add translations to lang/es/{file}.php, keeping every key already there.
     *
     * @param  array<string, mixed>  $translations
     * @return array<string, string>
     */
    private function mergeTranslations(string $modulePath, string $file, array $translations): array
    {
        $relativePath = "lang/es/{$file}.php";
        $path = $modulePath.'/'.$relativePath;

        if (! file_exists($path)) {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            $this->writeTranslationFile($path, $translations);

            return [$relativePath => ScaffoldResultDTO::STATUS_CREATED];
        }

        $existing = include $path;
        $existing = is_array($existing) ? $existing : [];
        $merged = $this->addMissingKeys($existing, $translations);

        if ($merged === $existing) {
            return [$relativePath => ScaffoldResultDTO::STATUS_SKIPPED];
        }

        $this->writeTranslationFile($path, $merged);

        return [$relativePath => ScaffoldResultDTO::STATUS_OVERWRITTEN];
    }

    /**
     * Add the keys of $translations that $existing lacks, keeping its values and order.
     *
     * @param  array<mixed>  $existing
     * @param  array<mixed>  $translations
     * @return array<mixed>
     */
    private function addMissingKeys(array $existing, array $translations): array
    {
        foreach ($translations as $key => $value) {
            if (! array_key_exists($key, $existing)) {
                $existing[$key] = $value;
            } elseif (is_array($existing[$key]) && is_array($value)) {
                $existing[$key] = $this->addMissingKeys($existing[$key], $value);
            }
        }

        return $existing;
    }

    /**
     * The module name shown in the admin panel ("displayName" in module.json).
     */
    private function moduleDisplayName(string $modulePath, string $module): string
    {
        $manifest = file_exists($modulePath.'/module.json')
            ? json_decode((string) file_get_contents($modulePath.'/module.json'), true)
            : null;

        $displayName = is_array($manifest) ? ($manifest['displayName'] ?? null) : null;

        return is_string($displayName) && $displayName !== '' ? $displayName : $this->sentence($module);
    }

    /**
     * A lowercase label: "BlogPost" becomes "blog post".
     */
    private function label(string $name): string
    {
        return Str::lower(Str::headline($name));
    }

    /**
     * A Spanish-style title: "BlogPosts" or "blog-posts" becomes "Blog posts".
     */
    private function sentence(string $name): string
    {
        return Str::ucfirst($this->label($name));
    }

    /**
     * @param  array<string, string>  $variables
     * @return list<string>
     */
    private function controllerNextSteps(string $module, string $type, array $variables): array
    {
        $studly = $variables['nameStudly'];
        $page = $variables['moduleNameStudly'].'/'.$studly;
        $path = $variables['namePluralKebab'];
        $routeName = $variables['namePluralSnake'];
        $stack = "It uses the {$studly} service, DTOs and request: run module:make-entity {$module} {$studly}, module:make-dto {$module} {$studly}, module:make-dto {$module} {$studly} --response, module:make-service {$module} {$studly} --interface and module:make-request {$module} {$studly} if they do not exist";

        return match ($type) {
            'resource' => [
                $stack,
                "Create its pages: module:make-vue-page {$module} {$studly}Index (and {$studly}Create, {$studly}Show, {$studly}Edit)",
                "Add the route to routes/web.php: Route::resource('{$path}', {$studly}Controller::class);",
            ],
            'api' => [
                $stack,
                "Add the route to routes/api.php: Route::apiResource('{$path}', Api\\{$studly}Controller::class);",
            ],
            'invokable' => [
                "Create its page: module:make-vue-page {$module} {$studly} (rendered as {$page})",
                "Add the route to routes/web.php: Route::get('{$variables['nameKebab']}', {$studly}Controller::class)->name('{$variables['nameSnake']}');",
            ],
            default => [
                "Create its page: module:make-vue-page {$module} {$studly} (rendered as {$page})",
                "Add the route to routes/web.php: Route::get('{$path}', [{$studly}Controller::class, 'index'])->name('{$routeName}.index');",
            ],
        };
    }

    /**
     * Write translations array to a PHP file.
     *
     * @param  array<string, mixed>  $translations
     */
    private function writeTranslationFile(string $path, array $translations): void
    {
        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn ".$this->varExport($translations).";\n";
        file_put_contents($path, $content);
    }

    /**
     * Export array with proper formatting.
     *
     * @param  array<string, mixed>  $array
     */
    private function varExport(array $array, int $indent = 0): string
    {
        $spaces = str_repeat('    ', $indent);
        $innerSpaces = str_repeat('    ', $indent + 1);
        $output = "[\n";

        foreach ($array as $key => $value) {
            $output .= $innerSpaces.$this->phpString((string) $key).' => ';

            if (is_array($value)) {
                $output .= $this->varExport($value, $indent + 1);
            } else {
                $output .= $this->phpString((string) $value);
            }

            $output .= ",\n";
        }

        $output .= $spaces.']';

        return $output;
    }

    /**
     * A single-quoted PHP string literal.
     */
    private function phpString(string $value): string
    {
        return "'".addcslashes($value, "'\\")."'";
    }

    public function createEnum(string $module, string $name): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath.'/'.$module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        // Ensure Enums directory exists
        $enumsDir = $modulePath.'/src/Domain/Enums';
        if (! is_dir($enumsDir)) {
            mkdir($enumsDir, 0755, true);
        }

        $destination = "src/Domain/Enums/{$variables['nameStudly']}.php";
        $destPath = $modulePath.'/'.$destination;

        $result = $this->renderStub('domain/enum.php.stub', $destPath, $variables);
        $files[$destination] = $result;

        $files = array_merge($files, $this->mergeTranslations($modulePath, 'messages', [
            'states' => [
                'pending' => 'Pendiente',
                'active' => 'Activo',
                'completed' => 'Completado',
                'cancelled' => 'Cancelado',
            ],
        ]));

        return ScaffoldResultDTO::success(
            "Enum '{$name}' created for module '{$module}'.",
            $files,
        );
    }

    public function createDomainEvent(string $module, string $name): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath.'/'.$module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        // Ensure Events directory exists
        $eventsDir = $modulePath.'/src/Domain/Events';
        if (! is_dir($eventsDir)) {
            mkdir($eventsDir, 0755, true);
        }

        $destination = "src/Domain/Events/{$variables['nameStudly']}.php";
        $destPath = $modulePath.'/'.$destination;

        $result = $this->renderStub('domain/domain-event.php.stub', $destPath, $variables);
        $files[$destination] = $result;

        return ScaffoldResultDTO::success(
            "Domain event '{$name}' created for module '{$module}'.",
            $files,
        );
    }

    public function createListener(string $module, string $name, string $eventName, bool $queued = false): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath.'/'.$module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $variables['eventNameStudly'] = Str::studly($eventName);
        $files = [];

        // Ensure Listeners directory exists
        $listenersDir = $modulePath.'/src/Infrastructure/Listeners';
        if (! is_dir($listenersDir)) {
            mkdir($listenersDir, 0755, true);
        }

        $stub = $queued ? 'domain/listener-queued.php.stub' : 'domain/listener.php.stub';
        $destination = "src/Infrastructure/Listeners/{$variables['nameStudly']}.php";
        $destPath = $modulePath.'/'.$destination;

        $result = $this->renderStub($stub, $destPath, $variables);
        $files[$destination] = $result;

        return ScaffoldResultDTO::success(
            "Listener '{$name}' created for module '{$module}'.",
            $files,
            nextSteps: [
                "Register it in the module service provider's boot(): Event::listen({$variables['eventNameStudly']}::class, {$variables['nameStudly']}::class);",
            ],
        );
    }

    public function createNotification(string $module, string $name): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath.'/'.$module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $files = [];

        // Ensure Notifications directory exists
        $notificationsDir = $modulePath.'/src/Notifications';
        if (! is_dir($notificationsDir)) {
            mkdir($notificationsDir, 0755, true);
        }

        $destination = "src/Notifications/{$variables['nameStudly']}.php";
        $destPath = $modulePath.'/'.$destination;

        $result = $this->renderStub('domain/notification.php.stub', $destPath, $variables);
        $files[$destination] = $result;

        $files = array_merge($files, $this->mergeTranslations($modulePath, 'emails', [
            $variables['nameSnake'] => [
                'subject' => $this->sentence($name),
                'greeting' => '¡Hola!',
                'intro' => 'Tienes una nueva notificación.',
            ],
        ]));

        return ScaffoldResultDTO::success(
            "Notification '{$name}' created for module '{$module}'.",
            $files,
        );
    }

    public function createRelationManager(string $module, string $name, ?string $resource = null): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath.'/'.$module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $variables['relationshipName'] = Str::camel(Str::plural($name));
        $variables['resourceNameStudly'] = $resource !== null ? Str::studly($resource) : 'Event';
        $variables['nameKebab'] = Str::kebab($name);
        $files = [];

        // Ensure RelationManagers directory exists
        $relationManagersDir = $modulePath.'/src/Filament/RelationManagers';
        if (! is_dir($relationManagersDir)) {
            mkdir($relationManagersDir, 0755, true);
        }

        $destination = "src/Filament/RelationManagers/{$variables['nameStudly']}RelationManager.php";
        $destPath = $modulePath.'/'.$destination;

        $result = $this->renderStub('filament/relation-manager.php.stub', $destPath, $variables);
        $files[$destination] = $result;

        $files = array_merge($files, $this->mergeTranslations($modulePath, 'filament', [
            $variables['nameSnake'] => [
                'title' => $this->sentence($name),
                'fields' => [
                    'name' => 'Nombre',
                    'created_at' => 'Creado',
                ],
            ],
        ]));

        $studly = $variables['nameStudly'];

        return ScaffoldResultDTO::success(
            "RelationManager '{$name}' created for module '{$module}'.",
            $files,
            nextSteps: [
                "Register the Livewire component in the module service provider's boot(): Livewire::component('modules.{$module}.filament.relation-managers.{$variables['nameKebab']}-relation-manager', {$studly}RelationManager::class);",
                "Extend the core resource: {$variables['resourceNameStudly']}Resource::extendRelations([{$studly}RelationManager::class]);",
                "The owner model needs the '{$variables['relationshipName']}' relationship (resolveRelationUsing() in the provider's register())",
            ],
        );
    }

    public function createWidget(string $module, string $name): ScaffoldResultDTO
    {
        $validation = $this->validateModuleExists($module);
        if ($validation !== null) {
            return $validation;
        }

        $modulePath = $this->modulesPath.'/'.$module;
        $variables = $this->stubRenderer->getComponentVariables($module, $name);
        $variables['nameKebab'] = Str::kebab($name);
        $files = [];

        // Ensure Widgets directory exists
        $widgetsDir = $modulePath.'/src/Filament/Widgets';
        if (! is_dir($widgetsDir)) {
            mkdir($widgetsDir, 0755, true);
        }

        $destination = "src/Filament/Widgets/{$variables['nameStudly']}Widget.php";
        $destPath = $modulePath.'/'.$destination;

        $result = $this->renderStub('filament/widget.php.stub', $destPath, $variables);
        $files[$destination] = $result;

        $files = array_merge($files, $this->mergeTranslations($modulePath, 'filament', [
            'widgets' => [
                $variables['nameSnake'] => [
                    'heading' => $this->sentence($name),
                    'total' => 'Total',
                    'total_description' => 'Registros en total',
                    'pending' => 'Pendientes',
                    'pending_description' => 'A la espera de revisión',
                    'active' => 'Activos',
                    'active_description' => 'En curso',
                ],
            ],
        ]));

        return ScaffoldResultDTO::success(
            "Widget '{$name}' created for module '{$module}'.",
            $files,
            nextSteps: [
                "Add it to the dashboard from the module service provider: return [{$variables['nameStudly']}Widget::class] in registerFilamentWidgets()",
            ],
        );
    }
}
