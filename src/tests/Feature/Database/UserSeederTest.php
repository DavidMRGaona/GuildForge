<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Database\Seeders\RolesSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class UserSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_seeded_accounts_hold_their_roles_in_the_pivot(): void
    {
        $this->seed([RolesSeeder::class, UserSeeder::class]);

        $this->assertSame(['admin', 'member'], $this->rolesOf('admin@example.local'));
        $this->assertSame(['editor', 'member'], $this->rolesOf('editor@example.local'));
        $this->assertSame(['member'], $this->rolesOf('member@example.local'));
    }

    public function test_seeding_twice_keeps_the_same_roles(): void
    {
        $this->seed([RolesSeeder::class, UserSeeder::class]);
        $this->seed(UserSeeder::class);

        $this->assertSame(['admin', 'member'], $this->rolesOf('admin@example.local'));
        $this->assertSame(['editor', 'member'], $this->rolesOf('editor@example.local'));
    }

    /**
     * @return list<string>
     */
    private function rolesOf(string $email): array
    {
        /** @var list<string> $names */
        $names = UserModel::query()->where('email', $email)->sole()
            ->roles()->pluck('name')->sort()->values()->all();

        return $names;
    }
}
