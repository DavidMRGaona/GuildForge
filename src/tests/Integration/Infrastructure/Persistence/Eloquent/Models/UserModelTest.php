<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Models;

use App\Domain\Enums\UserRole;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
            'role' => UserRole::Member,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'John Doe',
            'display_name' => 'Johnny',
            'email' => 'john@example.com',
            'avatar_public_id' => 'avatars/john.jpg',
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
            'role',
            'anonymized_at',
        ];

        $model = new UserModel;

        $this->assertEquals($fillable, $model->getFillable());
    }

    public function test_it_casts_role_to_user_role_enum(): void
    {
        $user = UserModel::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->assertInstanceOf(UserRole::class, $user->role);
        $this->assertEquals(UserRole::Admin, $user->role);
    }

    public function test_factory_creates_member_by_default(): void
    {
        $user = UserModel::factory()->create();

        $this->assertEquals(UserRole::Member, $user->role);
    }

    public function test_factory_admin_state_creates_admin_user(): void
    {
        $user = UserModel::factory()->admin()->create();

        $this->assertEquals(UserRole::Admin, $user->role);
    }

    public function test_factory_editor_state_creates_editor_user(): void
    {
        $user = UserModel::factory()->editor()->create();

        $this->assertEquals(UserRole::Editor, $user->role);
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
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Mass Assign User',
            'display_name' => 'Mass Assign',
            'email' => 'MASS.ASSIGN@EXAMPLE.COM',
            'password' => 'password',
            'role' => UserRole::Member,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'mass.assign@example.com',
        ]);
    }
}
