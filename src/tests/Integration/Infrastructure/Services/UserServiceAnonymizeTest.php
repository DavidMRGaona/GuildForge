<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Services;

use App\Application\Authorization\Services\RoleAuditLoggerInterface;
use App\Application\DTOs\AnonymizeUserDTO;
use App\Application\Services\UserServiceInterface;
use App\Domain\Authorization\Enums\RoleAuditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * The avatar is deleted from storage as soon as the account row changes, so the roles (and
 * their history entries) go first: a failed history write leaves the account and its
 * avatar as they were.
 */
final class UserServiceAnonymizeTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @param  Closure(UserServiceInterface, string): void  $anonymize
     */
    #[DataProvider('anonymizers')]
    public function test_a_failed_history_write_keeps_the_avatar_and_the_account(Closure $anonymize): void
    {
        Storage::fake('images');
        Storage::disk('images')->put('avatars/jane.jpg', 'image');
        $user = UserModel::factory()->create(['avatar_public_id' => 'avatars/jane.jpg']);
        $user->roles()->attach(RoleModel::query()->create(['name' => 'vocal', 'display_name' => 'Vocal'])->id);
        $this->app->instance(RoleAuditLoggerInterface::class, new class implements RoleAuditLoggerInterface
        {
            public function roleAssigned(object $user, string $roleName): void {}

            public function roleAssignedToMany(array $userIds, string $roleName): void {}

            public function roleRemoved(object $user, string $roleName): void
            {
                throw new RuntimeException('history unavailable');
            }

            public function roleChanged(string $roleId, string $roleName, RoleAuditEvent $event, array $changes): void {}
        });

        try {
            $anonymize(app(UserServiceInterface::class), (string) $user->id);
            $this->fail('The failed history write should have stopped the anonymization.');
        } catch (RuntimeException $exception) {
            $this->assertSame('history unavailable', $exception->getMessage());
        }

        Storage::disk('images')->assertExists('avatars/jane.jpg');
        $user->refresh();
        $this->assertNull($user->anonymized_at);
        $this->assertSame('avatars/jane.jpg', $user->avatar_public_id);
        $this->assertSame(['vocal'], $user->roles()->pluck('roles.name')->all());
    }

    /**
     * @return array<string, array{Closure(UserServiceInterface, string): void}>
     */
    public static function anonymizers(): array
    {
        return [
            'anonymize' => [static function (UserServiceInterface $service, string $userId): void {
                $service->anonymize($userId);
            }],
            'anonymize with content transfer' => [static function (UserServiceInterface $service, string $userId): void {
                $service->anonymizeWithContentTransfer(new AnonymizeUserDTO($userId, 'delete'));
            }],
        ];
    }
}
