<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Services;

use App\Application\Services\UserModelQueryServiceInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Services\UserModelQueryService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class UserModelQueryServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private UserModelQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UserModelQueryService();
    }

    public function test_it_implements_user_model_query_service_interface(): void
    {
        $this->assertInstanceOf(UserModelQueryServiceInterface::class, $this->service);
    }

    public function test_it_finds_user_model_by_id(): void
    {
        $model = UserModel::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $foundModel = $this->service->findModelById(new UserId($model->id));

        $this->assertNotNull($foundModel);
        $this->assertInstanceOf(UserModel::class, $foundModel);
        $this->assertEquals($model->id, $foundModel->id);
        $this->assertEquals('John Doe', $foundModel->name);
    }

    public function test_it_returns_null_when_user_not_found_by_id(): void
    {
        $foundModel = $this->service->findModelById(UserId::generate());

        $this->assertNull($foundModel);
    }

    public function test_it_finds_user_model_by_id_with_trashed(): void
    {
        $model = UserModel::factory()->create([
            'name' => 'Deleted User',
            'email' => 'deleted@example.com',
        ]);
        $model->delete();

        $foundModel = $this->service->findModelByIdWithTrashed(new UserId($model->id));

        $this->assertNotNull($foundModel);
        $this->assertEquals($model->id, $foundModel->id);
        $this->assertNotNull($foundModel->deleted_at);
    }

    public function test_it_finds_user_model_by_email(): void
    {
        $model = UserModel::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $foundModel = $this->service->findByEmail('jane@example.com');

        $this->assertNotNull($foundModel);
        $this->assertInstanceOf(UserModel::class, $foundModel);
        $this->assertEquals($model->id, $foundModel->id);
        $this->assertEquals('Jane Doe', $foundModel->name);
        $this->assertEquals('jane@example.com', $foundModel->email);
    }

    public function test_it_returns_null_when_user_not_found_by_email(): void
    {
        $model = $this->service->findByEmail('nonexistent@example.com');

        $this->assertNull($model);
    }
}
