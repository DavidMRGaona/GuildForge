<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Navigation\Enums\LinkTarget;
use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\Enums\MenuVisibility;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItemModel>
 */
final class MenuItemModelFactory extends Factory
{
    protected $model = MenuItemModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => fake()->uuid(),
            'location' => MenuLocation::Header,
            'parent_id' => null,
            'label' => fake()->words(2, true),
            'url' => fake()->optional()->url(),
            'route' => null,
            'route_params' => [],
            'icon' => null,
            'target' => LinkTarget::Self,
            'visibility' => MenuVisibility::Public,
            'permissions' => [],
            'sort_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
            'module' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function forModule(string $module): static
    {
        return $this->state(fn (array $attributes): array => [
            'module' => $module,
        ]);
    }
}
