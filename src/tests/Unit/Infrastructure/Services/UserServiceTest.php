<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\RoleAuditLoggerInterface;
use App\Application\Calendar\Services\CalendarFeedTokenServiceInterface;
use App\Application\DTOs\AnonymizeUserDTO;
use App\Application\Proposals\Services\ProposalServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\UserModelQueryServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use App\Domain\Exceptions\UserNotFoundException;
use App\Domain\Mail\Repositories\MailDigestItemRepositoryInterface;
use App\Domain\Mail\Repositories\MailPreferenceRepositoryInterface;
use App\Domain\SiteNotifications\Repositories\SiteNotificationPreferenceRepositoryInterface;
use App\Domain\SiteNotifications\Repositories\SiteNotificationRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Services\UserService;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class UserServiceTest extends TestCase
{
    private UserServiceInterface $service;

    private UserModelQueryServiceInterface&MockInterface $userModelQuery;

    private AuthorizationServiceInterface&MockInterface $authService;

    private SettingsServiceInterface&MockInterface $settingsService;

    private RoleAuditLoggerInterface&MockInterface $audit;

    private CalendarFeedTokenServiceInterface&MockInterface $calendarFeedTokens;

    private MailPreferenceRepositoryInterface&MockInterface $mailPreferences;

    private SiteNotificationRepositoryInterface&MockInterface $siteNotifications;

    private SiteNotificationPreferenceRepositoryInterface&MockInterface $siteNotificationPreferences;

    private SiteNotificationServiceInterface&MockInterface $siteNotificationService;

    private MailDigestItemRepositoryInterface&MockInterface $mailDigestItems;

    private ProposalServiceInterface&MockInterface $proposals;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userModelQuery = Mockery::mock(UserModelQueryServiceInterface::class);
        $this->authService = Mockery::mock(AuthorizationServiceInterface::class);
        $this->settingsService = Mockery::mock(SettingsServiceInterface::class);
        $this->audit = Mockery::mock(RoleAuditLoggerInterface::class);
        $this->calendarFeedTokens = Mockery::mock(CalendarFeedTokenServiceInterface::class);
        $this->mailPreferences = Mockery::mock(MailPreferenceRepositoryInterface::class);
        $this->siteNotifications = Mockery::mock(SiteNotificationRepositoryInterface::class);
        $this->siteNotificationPreferences = Mockery::mock(SiteNotificationPreferenceRepositoryInterface::class);
        $this->siteNotificationService = Mockery::mock(SiteNotificationServiceInterface::class);
        $this->mailDigestItems = Mockery::mock(MailDigestItemRepositoryInterface::class);
        $this->proposals = Mockery::mock(ProposalServiceInterface::class);
        $this->proposals->shouldReceive('deleteUnpublishedOf')->byDefault()->andReturn(0);

        $this->service = new UserService(
            $this->userModelQuery,
            $this->authService,
            $this->settingsService,
            $this->audit,
            $this->calendarFeedTokens,
            $this->mailPreferences,
            $this->siteNotifications,
            $this->siteNotificationPreferences,
            $this->siteNotificationService,
            $this->mailDigestItems,
            $this->proposals,
        );
    }

    public function test_it_returns_true_when_user_can_access_panel_with_admin_permission(): void
    {
        $userId = UserId::generate();
        $userModel = Mockery::mock(UserModel::class);

        $this->userModelQuery
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

    public function test_it_returns_false_when_user_cannot_access_panel(): void
    {
        $userId = UserId::generate();
        $userModel = Mockery::mock(UserModel::class);

        $this->userModelQuery
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

        $this->userModelQuery
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

        $this->userModelQuery
            ->shouldReceive('findModelByIdWithTrashed')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->value() === $userId->value()))
            ->andReturn($userModel);

        $this->settingsService
            ->shouldReceive('get')
            ->once()
            ->with('anonymized_user_name', 'Anónimo')
            ->andReturn('Usuario anónimo');

        $updates = [];
        $userModel
            ->shouldReceive('update')
            ->once()
            ->andReturnUsing(function (array $data) use (&$updates): bool {
                $updates[] = $data;

                return true;
            });

        $rolesRelation = Mockery::mock(BelongsToMany::class);
        $rolesRelation->shouldReceive('pluck')->once()->with('roles.name')->andReturn(collect(['member']));
        $rolesRelation->shouldReceive('detach')->once();
        $userModel->shouldReceive('roles')->twice()->andReturn($rolesRelation);
        // Every role taken away leaves an entry in the role history
        $this->audit->shouldReceive('roleRemoved')->once()->with($userModel, 'member');
        // Without its roles the user must lose access at once, not when the cache expires
        $this->authService->shouldReceive('invalidateCacheFor')->once()->with($userModel);
        // The personal calendar address goes too
        $this->calendarFeedTokens->shouldReceive('revoke')->once()->with($userId->value());
        // And the mail preferences, which would still describe the person
        $this->mailPreferences->shouldReceive('deleteForUser')->once()->with(Mockery::on(fn (UserId $id): bool => $id->value() === $userId->value()));
        // And the notification centre with its choices (core#491)
        $this->siteNotifications->shouldReceive('deleteForUser')->once()->with(Mockery::on(fn (UserId $id): bool => $id->value() === $userId->value()));
        $this->siteNotificationPreferences->shouldReceive('deleteForUser')->once()->with(Mockery::on(fn (UserId $id): bool => $id->value() === $userId->value()));
        $this->siteNotificationService->shouldReceive('forgetActor')->once()->with($userId->value());
        $this->mailDigestItems->shouldReceive('deleteForActor')->once()->with($userId->value());

        $this->service->anonymize($userId->value());

        $this->assertCount(1, $updates);
        $this->assertSame('Usuario anónimo', $updates[0]['name']);
        $this->assertNull($updates[0]['display_name']);
        $this->assertNull($updates[0]['avatar_public_id']);
        $this->assertNotNull($updates[0]['anonymized_at']);
    }

    public function test_it_throws_exception_when_anonymizing_nonexistent_user(): void
    {
        $userId = UserId::generate();

        $this->userModelQuery
            ->shouldReceive('findModelByIdWithTrashed')
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

        $this->userModelQuery
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

        $this->userModelQuery
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

        $this->userModelQuery
            ->shouldReceive('findModelById')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->value() === $userId->value()))
            ->andReturn(null);

        $result = $this->service->isAdmin($userId->value());

        $this->assertFalse($result);
    }

    public function test_anonymize_with_content_transfer_throws_exception_when_user_not_found(): void
    {
        $userId = UserId::generate();

        $dto = new AnonymizeUserDTO(
            userId: $userId->value(),
            contentAction: 'anonymize',
        );

        $this->userModelQuery
            ->shouldReceive('findModelByIdWithTrashed')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->value() === $userId->value()))
            ->andReturn(null);

        $this->expectException(UserNotFoundException::class);

        $this->service->anonymizeWithContentTransfer($dto);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
