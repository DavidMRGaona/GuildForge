<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds demo/test data for development and testing environments.
 * NEVER run in production - creates fake users and demo content.
 */
final class DevelopmentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            EventSeeder::class,
            ArticleSeeder::class,
            GallerySeeder::class,
        ]);

        $this->seedModules();
    }

    /**
     * Seed all enabled modules.
     */
    private function seedModules(): void
    {
        $moduleManager = app(ModuleManagerServiceInterface::class);

        foreach ($moduleManager->enabled() as $module) {
            $moduleManager->seed(new ModuleName($module->name()->value));
        }
    }
}
