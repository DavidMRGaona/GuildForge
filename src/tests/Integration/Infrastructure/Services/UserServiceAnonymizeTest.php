<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Services;

use App\Application\Authorization\Services\RoleAuditLoggerInterface;
use App\Application\DTOs\AnonymizeUserDTO;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Domain\Authorization\Enums\RoleAuditEvent;
use App\Domain\Mail\Repositories\MailDigestItemRepositoryInterface;
use App\Domain\Mail\Repositories\MailPauseRepositoryInterface;
use App\Domain\Mail\Repositories\MailPreferenceRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\SiteNotificationModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\SiteNotifications\SiteTestNotification;
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
     * @param  Closure(UserServiceInterface, string): void  $anonymize
     */
    #[DataProvider('anonymizers')]
    public function test_anonymizing_deletes_the_mail_preferences(Closure $anonymize): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $preferences = app(MailPreferenceRepositoryInterface::class);
        $preferences->save(UserId::fromString($user->id), ['example.reminders' => false]);
        $preferences->save(UserId::fromString($other->id), ['example.reminders' => false]);

        $anonymize(app(UserServiceInterface::class), (string) $user->id);

        $this->assertSame([], $preferences->disabledFor(UserId::fromString($user->id)));
        $this->assertSame(['example.reminders'], $preferences->disabledFor(UserId::fromString($other->id)));
    }

    /**
     * @param  Closure(UserServiceInterface, string): void  $anonymize
     */
    #[DataProvider('anonymizers')]
    public function test_anonymizing_deletes_the_mail_pause(Closure $anonymize): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $pauses = app(MailPauseRepositoryInterface::class);
        $pauses->pause(UserId::fromString($user->id), null);
        $pauses->pause(UserId::fromString($other->id), null);

        $anonymize(app(UserServiceInterface::class), (string) $user->id);

        $this->assertNull($pauses->find(UserId::fromString($user->id)));
        $this->assertNotNull($pauses->find(UserId::fromString($other->id)));
    }

    /**
     * @param  Closure(UserServiceInterface, string): void  $anonymize
     */
    #[DataProvider('anonymizers')]
    public function test_anonymizing_deletes_the_muted_mail_subjects(Closure $anonymize): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $mutes = app(MailSubjectMuteServiceInterface::class);
        $mutes->mute($user, new MailSubjectDTO('example.campaign', 'c-1', 'La tumba'));
        $mutes->mute($other, new MailSubjectDTO('example.campaign', 'c-1', 'La tumba'));

        $anonymize(app(UserServiceInterface::class), (string) $user->id);

        $this->assertSame([], $mutes->mutedFor($user));
        $this->assertCount(1, $mutes->mutedFor($other));
    }

    /**
     * @param  Closure(UserServiceInterface, string): void  $anonymize
     */
    #[DataProvider('anonymizers')]
    public function test_anonymizing_deletes_the_notices_kept_for_the_daily_digest(Closure $anonymize): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $items = app(MailDigestItemRepositoryInterface::class);
        foreach ([$user, $other] as $owner) {
            $items->add(UserId::fromString($owner->id), 'example.sign-ups', 'Ana se ha apuntado', null, null, null);
        }

        $anonymize(app(UserServiceInterface::class), (string) $user->id);

        $this->assertSame(0, DB::table('user_mail_digest_items')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('user_mail_digest_items')->where('user_id', $other->id)->count());
    }

    /**
     * @param  Closure(UserServiceInterface, string): void  $anonymize
     */
    #[DataProvider('anonymizers')]
    public function test_anonymizing_forgets_the_notices_other_accounts_got_because_of_it(Closure $anonymize): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->app->make(MailCategoryRegistryInterface::class)->register(
            MailCategoryDTO::optional(key: 'example.replies', section: 'Example', label: 'Replies', description: 'D', site: true),
            'example',
        );
        $other->notify(new SiteTestNotification('example.replies', new SiteNotificationDTO(title: 'Ana te ha mencionado', url: '/a', actorId: (string) $user->id)));
        $other->notify(new SiteTestNotification('example.replies', new SiteNotificationDTO(title: 'Luis te ha mencionado', url: '/b', actorId: $other->id.'x')));
        $user->notify(new SiteTestNotification('example.replies', new SiteNotificationDTO(title: 'Propio', url: '/c', actorId: (string) $other->id)));
        app(MailDigestItemRepositoryInterface::class)->add(UserId::fromString($other->id), 'example.sign-ups', 'Ana se ha apuntado', null, null, null, (string) $user->id);

        $anonymize(app(UserServiceInterface::class), (string) $user->id);

        $this->assertSame(['/b'], SiteNotificationModel::query()->pluck('url')->all());
        $this->assertSame(0, DB::table('user_mail_digest_items')->count());
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
