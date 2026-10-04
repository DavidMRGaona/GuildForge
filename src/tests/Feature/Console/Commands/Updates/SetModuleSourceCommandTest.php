<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Updates;

use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class SetModuleSourceCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_sets_the_module_repository(): void
    {
        ModuleModel::factory()->enabled()->create(['name' => 'game-tables']);

        $this->artisan('module:set-source', ['name' => 'game-tables', 'repository' => 'acme/guildforge-game-tables'])
            ->assertExitCode(0);

        $module = $this->app->make(ModuleRepositoryInterface::class)->findByName(new ModuleName('game-tables'));
        $this->assertSame('acme', $module?->sourceOwner());
        $this->assertSame('guildforge-game-tables', $module?->sourceRepo());
    }

    public function test_it_rejects_a_malformed_repository(): void
    {
        ModuleModel::factory()->enabled()->create(['name' => 'game-tables']);

        $this->artisan('module:set-source', ['name' => 'game-tables', 'repository' => 'https://github.com/acme/repo'])
            ->assertExitCode(1);
    }

    public function test_it_fails_for_an_unknown_module(): void
    {
        $this->artisan('module:set-source', ['name' => 'missing', 'repository' => 'acme/repo'])
            ->assertExitCode(1);
    }
}
