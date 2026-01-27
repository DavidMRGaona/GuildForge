<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModuleModel>
 */
final class ModuleModelFactory extends Factory
{
    protected $model = ModuleModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word().'-module';

        $displayName = str_replace('-', '', ucwords($name, '-'));

        return [
            'id' => $this->faker->uuid(),
            'name' => $name,
            'display_name' => $displayName,
            'version' => $this->faker->semver(),
            'description' => $this->faker->sentence(),
            'author' => $this->faker->name(),
            'status' => 'disabled',
            'path' => base_path("modules/{$name}"),
            'namespace' => 'Modules\\'.$displayName,
            'provider' => $displayName.'ServiceProvider',
            'requires' => null,
            'dependencies' => null,
            'discovered_at' => now(),
            'enabled_at' => null,
        ];
    }

    /**
     * Indicate that the module is enabled.
     */
    public function enabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'enabled',
            'enabled_at' => now(),
        ]);
    }

    /**
     * Indicate that the module is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'disabled',
            'enabled_at' => null,
        ]);
    }

    /**
     * Set module dependencies.
     *
     * @param  array<string>  $dependencies
     */
    public function withDependencies(array $dependencies): static
    {
        return $this->state(fn (array $attributes): array => [
            'dependencies' => $dependencies,
        ]);
    }

    /**
     * Set module requirements.
     *
     * @param  array<string, string>  $requires
     */
    public function withRequirements(array $requires): static
    {
        return $this->state(fn (array $attributes): array => [
            'requires' => $requires,
        ]);
    }
}
