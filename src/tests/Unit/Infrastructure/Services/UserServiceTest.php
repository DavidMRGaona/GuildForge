<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Domain\Entities\User;
use App\Domain\Enums\UserRole;
use App\Domain\Exceptions\UserNotFoundException;
use App\Domain\Repositories\UserRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Services\UserService;
use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class UserServiceTest extends TestCase
{
    private UserServiceInterface $service;
    private UserRepositoryInterface&MockInterface $userRepository;
    private AuthorizationServiceInterface&MockInterface $authService;
    private SettingsServiceInterface&MockInterface $settingsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->authService = Mockery::mock(AuthorizationServiceInterface::class);
        $this->settingsService = Mockery::mock(SettingsServiceInterface::class);

        $this->service = new UserService(
            $this->userRepository,
            $this->authService,
            $this->settingsService
        );
    }

    public function test_it_returns_true_when_user_can_access_panel_with_admin_permission(): void
    {
        $userId = UserId::generate();
        $userModel = Mockery::mock(UserModel::class);

        $this->userRepository
            ->shouldReceive('findModelById')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->value() === $userId->value()))
            ->andReturn($userModel);

        $this->authService
            ->shouldReceive('can')
            ->once()
            ->with($userModel, 'admin.access')
            ->andReturn(true);

        $result = $this->service->canAccessPanel($userId->value());

        $this->assertTrue($result);
    }

    public function test_it_returns_true_when_user_has_admin_role_enum(): void
    {
        $userId = UserId::generate();
        $userModel = Mockery::mock(UserModel::class)->makePartial();
        $userModel->role = UserRole::Admin;

        $this->userRepository
            ->shouldReceive('findModelById')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->value() === $userId->value()))
            ->andReturn($userModel);

        $this->authService
            ->shouldReceive('can')
            ->once()
            ->with($userModel, 'admin.access')
            ->andReturn(false);

        $result = $this->service->canAccessPanel($userId->value());

        $this->assertTrue($result);
    }

    public function test_it_returns_false_when_user_cannot_access_panel(): void
    {
        $userId = UserId::generate();
        $userModel = Mockery::mock(UserModel::class)->makePartial();
        $userModel->role = UserRole::Member;

        $this->userRepository
            ->shouldReceive('findModelById')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->value() === $userId->value()))
            ->andReturn($userModel);

        $this->authService
            ->shouldReceive('can')
            ->once()
            ->with($userModel, 'admin.access')
            ->andReturn(false);

        $result = $this->service->canAccessPanel($userId->value());

        $this->assertFalse($result);
    }

    public function test_it_returns_false_when_user_not_found_for_panel_access(): void
    {
        $userId = UserId::generate();

        $this->userRepository
            ->shouldReceive('findModelById')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->value() === $userId->value()))
            ->andReturn(null);

        $result = $this->service->canAccessPanel($userId->value());

        $this->assertFalse($result);
    }

    public function test_it_anonymizes_user_data(): void
    {
        $userId = UserId::generate();
        $userModel = Mockery::mock(UserModel::class)->makePartial();
        $userModel->id = $userId->value();
        $userModel->avatar_public_id = 'some-avatar-id';

        $this->userRepository
            ->shouldReceive('findModelById')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->value() === $userId->value()))
            ->andReturn($userModel);

        $this->settingsService
            ->shouldReceive('get')
            ->once()
            ->with('anonymized_user_name', 'Anónimo')
            ->andReturn('Usuario anónimo');

        $userModel
            ->shouldReceive('anonymize')
            ->once()
            ->andReturnNull();

        $this->service->anonymize($userId->value());

        // No exception means success
        $this->assertTrue(true);
    }

    public function test_it_throws_exception_when_anonymizing_nonexistent_user(): void
    {
        $userId = UserId::generate();

        $this->userRepository
            ->shouldReceive('findModelById')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->value() === $userId->value()))
            ->andReturn(null);

        $this->expectException(UserNotFoundException::class);

        $this->service->anonymize($userId->value());
    }

    public function test_is_admin_returns_true_for_admin_role(): void
    {
        $userId = UserId::generate();
        $userModel = Mockery::mock(UserModel::class);

        $this->userRepository
            ->shouldReceive('findModelById')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->value() === $userId->value()))
            ->andReturn($userModel);

        $this->authService
            ->shouldReceive('hasRole')
            ->once()
            ->with($userModel, 'admin')
            ->andReturn(true);

        $result = $this->service->isAdmin($userId->value());

        $this->assertTrue($result);
    }

    public function test_is_admin_returns_false_for_non_admin_role(): void
    {
        $userId = UserId::generate();
        $userModel = Mockery::mock(UserModel::class);

        $this->userRepository
            ->shouldReceive('findModelById')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->value() === $userId->value()))
            ->andReturn($userModel);

        $this->authService
            ->shouldReceive('hasRole')
            ->once()
            ->with($userModel, 'admin')
            ->andReturn(false);

        $result = $this->service->isAdmin($userId->value());

        $this->assertFalse($result);
    }

    public function test_is_admin_returns_false_when_user_not_found(): void
    {
        $userId = UserId::generate();

        $this->userRepository
            ->shouldReceive('findModelById')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->value() === $userId->value()))
            ->andReturn(null);

        $result = $this->service->isAdmin($userId->value());

        $this->assertFalse($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
