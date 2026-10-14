<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class UserModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_creates_user_in_database(): void
    {
        $user = UserModel::factory()->create([
            'name' => 'John Doe',
            'display_name' => 'Johnny',
            'email' => 'john@example.com',
            'avatar_public_id' => 'avatars/john.jpg',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'John Doe',
            'display_name' => 'Johnny',
            'email' => 'john@example.com',
            'avatar_public_id' => 'avatars/john.jpg',
            // Legacy column, no longer written: the database default fills it
            'role' => 'member',
        ]);
    }

    public function test_it_has_correct_fillable_attributes(): void
    {
        $fillable = [
            'id',
            'name',
            'display_name',
            'email',
            'pending_email',
            'password',
            'avatar_public_id',
            'anonymized_at',
        ];

        $model = new UserModel;

        $this->assertEquals($fillable, $model->getFillable());
    }

    public function test_it_does_not_cast_the_legacy_role_column(): void
    {
        $this->assertArrayNotHasKey('role', (new UserModel)->getCasts());
    }

    public function test_factory_creates_user_without_roles_by_default(): void
    {
        $user = UserModel::factory()->create();

        $this->assertSame([], $user->roles()->pluck('name')->all());
    }

    public function test_factory_admin_state_creates_admin_user(): void
    {
        $user = UserModel::factory()->admin()->create();

        $this->assertSame(['admin'], $user->roles()->pluck('name')->all());
    }

    public function test_factory_editor_state_creates_editor_user(): void
    {
        $user = UserModel::factory()->editor()->create();

        $this->assertSame(['editor'], $user->roles()->pluck('name')->all());
    }

    public function test_is_admin_returns_true_for_user_with_admin_role(): void
    {
        $user = UserModel::factory()->admin()->create();

        $this->assertTrue($user->isAdmin());
    }

    public function test_is_admin_returns_false_for_user_with_editor_role(): void
    {
        $user = UserModel::factory()->editor()->create();

        $this->assertFalse($user->isAdmin());
    }

    public function test_is_admin_returns_false_for_user_without_roles(): void
    {
        $user = UserModel::factory()->create();

        $this->assertFalse($user->isAdmin());
    }

    public function test_it_normalizes_email_to_lowercase_on_create(): void
    {
        $user = UserModel::factory()->create([
            'email' => 'John.Doe@Example.COM',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'john.doe@example.com',
        ]);
    }

    public function test_it_normalizes_email_to_lowercase_on_update(): void
    {
        $user = UserModel::factory()->create([
            'email' => 'original@example.com',
        ]);

        $user->update(['email' => 'UPDATED@EXAMPLE.COM']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'updated@example.com',
        ]);
    }

    public function test_it_normalizes_email_to_lowercase_via_mass_assignment(): void
    {
        $user = UserModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Mass Assign User',
            'display_name' => 'Mass Assign',
            'email' => 'MASS.ASSIGN@EXAMPLE.COM',
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'mass.assign@example.com',
        ]);
    }
}
