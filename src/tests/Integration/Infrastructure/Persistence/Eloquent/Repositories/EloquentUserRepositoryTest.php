<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Application\DTOs\CreateUserDTO;
use App\Domain\Entities\User;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentUserRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class EloquentUserRepositoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private EloquentUserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentUserRepository();
    }

    public function test_it_implements_user_repository_interface(): void
    {
        $this->assertInstanceOf(UserRepositoryInterface::class, $this->repository);
    }

    public function test_it_finds_user_by_id(): void
    {
        $model = UserModel::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $user = $this->repository->findById(new UserId($model->id));

        $this->assertNotNull($user);
        $this->assertEquals($model->id, $user->id()->value);
        $this->assertEquals('John Doe', $user->name());
        $this->assertEquals('john@example.com', $user->email());
    }

    public function test_it_returns_null_when_user_not_found_by_id(): void
    {
        $user = $this->repository->findById(UserId::generate());

        $this->assertNull($user);
    }

    public function test_it_saves_new_user(): void
    {
        $id = UserId::generate();
        $user = new User(
            id: $id,
            name: 'New User',
            email: 'newuser@example.com',
        );

        $this->repository->save($user);

        $this->assertDatabaseHas('users', [
            'id' => $id->value,
            'name' => 'New User',
            'email' => 'newuser@example.com',
        ]);
    }

    public function test_it_updates_existing_user(): void
    {
        $model = UserModel::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'display_name' => null,
        ]);

        $user = new User(
            id: new UserId($model->id),
            name: 'Updated Name',
            email: 'updated@example.com',
            displayName: 'Updated Display',
        );

        $this->repository->save($user);

        $this->assertDatabaseHas('users', [
            'id' => $model->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'display_name' => 'Updated Display',
        ]);

        $this->assertDatabaseMissing('users', [
            'name' => 'Original Name',
        ]);
    }

    public function test_it_creates_user_from_dto(): void
    {
        $dto = new CreateUserDTO(
            name: 'DTO User',
            email: 'dto@example.com',
            password: 'SecurePassword123',
        );

        $user = $this->repository->create($dto);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('DTO User', $user->name());
        $this->assertEquals('dto@example.com', $user->email());
        $this->assertDatabaseHas('users', [
            'id' => $user->id()->value,
            'name' => 'DTO User',
            'email' => 'dto@example.com',
        ]);
    }

    public function test_it_maps_all_user_fields_correctly(): void
    {
        $model = UserModel::factory()->create([
            'name' => 'Complete User',
            'email' => 'complete@example.com',
            'display_name' => 'Complete Display Name',
            'pending_email' => 'pending@example.com',
            'avatar_public_id' => 'avatars/user-123.jpg',
            'email_verified_at' => now(),
        ]);

        $user = $this->repository->findById(new UserId($model->id));

        $this->assertNotNull($user);
        $this->assertEquals($model->id, $user->id()->value);
        $this->assertEquals('Complete User', $user->name());
        $this->assertEquals('complete@example.com', $user->email());
        $this->assertEquals('Complete Display Name', $user->displayName());
        $this->assertEquals('pending@example.com', $user->pendingEmail());
        $this->assertEquals('avatars/user-123.jpg', $user->avatarPublicId());
        $this->assertNotNull($user->emailVerifiedAt());
        $this->assertTrue($user->isEmailVerified());
        $this->assertNull($user->anonymizedAt());
        $this->assertFalse($user->isAnonymized());
        $this->assertNotNull($user->createdAt());
        $this->assertNotNull($user->updatedAt());
    }
}
