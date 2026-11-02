<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge;

use App\Application\Knowledge\DTOs\KnowledgeSyncReportDTO;
use App\Application\Knowledge\Services\KnowledgeCatalogSynchronizerInterface;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Infrastructure\Knowledge\Catalog\KnowledgeSourceLocator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Str;

/**
 * Builds temporary guide trees (core and modules) and points the knowledge locator at them.
 *
 * @mixin TestCase
 */
trait KnowledgeFixtures
{
    private string $knowledgeSandbox = '';

    /** @var list<Module> */
    private array $knowledgeModules = [];

    protected function setUpKnowledgeSandbox(): void
    {
        $this->knowledgeSandbox = storage_path('framework/testing/knowledge-'.Str::random(8));
        (new Filesystem)->ensureDirectoryExists($this->knowledgeSandbox.'/core');
        $this->knowledgeModules = [];
        $this->bindKnowledgeLocator();
    }

    protected function tearDownKnowledgeSandbox(): void
    {
        if ($this->knowledgeSandbox !== '') {
            (new Filesystem)->deleteDirectory($this->knowledgeSandbox);
        }
    }

    protected function corePath(): string
    {
        return $this->knowledgeSandbox.'/core';
    }

    protected function writeCoreGuide(string $file, string $id, string $title = 'Un título', string $body = 'Cuerpo.', string $locale = 'es', string $extra = ''): void
    {
        $this->writeFile($this->corePath().'/'.$locale.'/'.$file, $this->guideDocument($id, $title, $body, $extra));
    }

    protected function writeModuleGuide(string $module, string $file, string $id, string $title = 'Un título', string $body = 'Cuerpo.', string $extra = ''): void
    {
        $this->registerKnowledgeModule($module);
        $this->writeFile($this->knowledgeSandbox.'/modules/'.$module.'/resources/knowledge/es/'.$file, $this->guideDocument($id, $title, $body, $extra));
    }

    protected function writeModuleCategories(string $module, string $php): void
    {
        $this->registerKnowledgeModule($module);
        $this->writeFile($this->knowledgeSandbox.'/modules/'.$module.'/resources/knowledge/categories.php', $php);
    }

    protected function registerKnowledgeModule(string $name): void
    {
        foreach ($this->knowledgeModules as $module) {
            if ($module->name()->value === $name) {
                return;
            }
        }

        $this->knowledgeModules[] = new Module(
            id: ModuleId::generate(),
            name: new ModuleName($name),
            displayName: $name,
            description: 'Fixture module',
            version: ModuleVersion::fromString('1.0.0'),
            author: 'Test',
            requirements: new ModuleRequirements(null, null, [], []),
            status: ModuleStatus::Enabled,
            path: $this->knowledgeSandbox.'/modules/'.$name,
        );
        $this->bindKnowledgeLocator();
    }

    protected function disableKnowledgeModule(string $name): void
    {
        $this->knowledgeModules = array_values(array_filter(
            $this->knowledgeModules,
            static fn (Module $module): bool => $module->name()->value !== $name,
        ));
        $this->bindKnowledgeLocator();
    }

    protected function deleteCoreFile(string $file, string $locale = 'es'): void
    {
        unlink($this->corePath().'/'.$locale.'/'.$file);
    }

    protected function syncKnowledge(): KnowledgeSyncReportDTO
    {
        return $this->app->make(KnowledgeCatalogSynchronizerInterface::class)->sync();
    }

    protected function guideDocument(string $id, string $title, string $body, string $extra = ''): string
    {
        return "---\nid: {$id}\ntitle: {$title}\nsummary: Resumen\ncategory: getting-started\naudience: site\n{$extra}---\n{$body}\n";
    }

    protected function writeFile(string $path, string $contents): void
    {
        (new Filesystem)->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $contents);
    }

    private function bindKnowledgeLocator(): void
    {
        $modules = $this->knowledgeModules;
        $resolver = new StaticModulesResolver($modules);

        $this->app->instance(KnowledgeSourceLocator::class, new KnowledgeSourceLocator($resolver, $this->corePath()));
    }
}
