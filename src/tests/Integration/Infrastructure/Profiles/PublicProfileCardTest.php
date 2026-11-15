<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Profiles;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Profiles\DTOs\ProfileCardDTO;
use App\Application\Profiles\DTOs\ProfileHeaderDTO;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\ResolvedProfileDTO;
use App\Application\Profiles\Services\ProfileCardServiceInterface;
use App\Application\Profiles\Services\ProfileSummaryRendererInterface;
use App\Application\Services\UserServiceInterface;
use App\Domain\Profiles\ProfileVisibility;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * ProfileCardServiceInterface::card() (core#744): the summary card of an account's public profile for
 * modules, through the same access rule as the page; null whenever the card cannot be shown.
 */
final class PublicProfileCardTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cloudinary.cloud_name' => 'demo', 'cloudinary.prefix' => '']);
    }

    public function test_a_visible_profile_gives_its_card(): void
    {
        $owner = $this->owner(ProfileVisibility::Public, ['avatar_public_id' => 'avatars/ana.jpg']);

        $card = $this->card($owner->id, null);

        $this->assertInstanceOf(ProfileCardDTO::class, $card);
        $this->assertSame('ana-prueba', $card->handle);
        $this->assertSame('Ana Prueba', $card->publicName);
        $this->assertSame(route('profiles.show', ['handle' => 'ana-prueba']), $card->url);
        $this->assertSame('https://res.cloudinary.com/demo/image/upload/w_128,h_128,c_fill,g_face,q_auto,f_auto/avatars/ana', $card->avatarUrl);
        $this->assertNull($card->bannerUrl);
        $this->assertFalse($card->isOwner);
        $this->assertSame([], $card->stats);
    }

    public function test_an_id_in_capitals_is_the_same_account(): void
    {
        $owner = $this->owner(ProfileVisibility::Public);

        $this->assertSame('ana-prueba', $this->card(strtoupper($owner->id), null)?->handle);
        $this->assertTrue($this->card(strtoupper($owner->id), $owner)?->isOwner);
    }

    public function test_the_owner_gets_their_own_card_whatever_the_visibility(): void
    {
        $owner = $this->owner(ProfileVisibility::Hidden);

        $this->assertTrue($this->card($owner->id, $owner)?->isOwner);
    }

    public function test_no_card_for_whoever_may_not_see_the_profile(): void
    {
        $members = $this->owner(ProfileVisibility::Members);
        $association = UserModel::factory()->create(['display_name' => 'Berta']);
        $this->setVisibility($association, ProfileVisibility::Association);
        $hidden = UserModel::factory()->create(['display_name' => 'Carla']);
        $this->setVisibility($hidden, ProfileVisibility::Hidden);
        $nameless = UserModel::factory()->create(['display_name' => null]);
        $this->setVisibility($nameless, ProfileVisibility::Public);
        $viewer = UserModel::factory()->create();

        $this->assertNull($this->card($members->id, null));
        $this->assertInstanceOf(ProfileCardDTO::class, $this->card($members->id, $viewer));
        $this->assertNull($this->card($association->id, $viewer));
        $this->assertNull($this->card($hidden->id, $viewer));
        $this->assertNull($this->card($nameless->id, $viewer));

        $socio = UserModel::factory()->create();
        app(AuthorizationServiceInterface::class)->assignRole($socio, AssociationMembershipServiceInterface::DEFAULT_ROLE);
        $this->assertInstanceOf(ProfileCardDTO::class, $this->card($association->id, $socio));
    }

    public function test_no_card_for_an_unknown_deactivated_anonymized_or_rowless_account(): void
    {
        $deactivated = $this->owner(ProfileVisibility::Public);
        $deactivated->delete();
        $anonymized = UserModel::factory()->create(['display_name' => 'Berta']);
        $this->setVisibility($anonymized, ProfileVisibility::Public);
        app(UserServiceInterface::class)->anonymize((string) $anonymized->id);
        $rowless = UserModel::factory()->create(['display_name' => 'Carla']);
        UserProfileModel::query()->whereKey($rowless->id)->delete();

        $this->assertNull($this->card('019a0000-0000-7000-8000-000000000000', null));
        $this->assertNull($this->card('no es un id', null));
        $this->assertNull($this->card('', null));
        $this->assertNull($this->card($deactivated->id, $deactivated));
        $this->assertNull($this->card($anonymized->id, null));
        $this->assertNull($this->card($rowless->id, null));
    }

    public function test_a_failing_access_check_gives_no_card(): void
    {
        $owner = $this->owner(ProfileVisibility::Association);
        $this->app->instance(AssociationMembershipServiceInterface::class, new class implements AssociationMembershipServiceInterface
        {
            public function roleName(): string
            {
                return 'socio';
            }

            public function isAssociationMember(object $user): bool
            {
                throw new RuntimeException('roles unreadable');
            }
        });

        $this->assertNull($this->card($owner->id, UserModel::factory()->create()));
    }

    public function test_any_failure_gives_no_card_and_never_throws(): void
    {
        $owner = $this->owner(ProfileVisibility::Public);
        $this->app->scoped(ProfileSummaryRendererInterface::class, static fn (): ProfileSummaryRendererInterface => new class implements ProfileSummaryRendererInterface
        {
            public function summary(ProfileOwnerDTO $owner, ?Authenticatable $viewer): ResolvedProfileDTO
            {
                throw new RuntimeException('Secret detail');
            }
        });
        $log = Log::spy();

        $this->assertNull($this->card($owner->id, null));
        $log->shouldHaveReceived('warning')->withArgs(static fn (string $message, array $context): bool => $context === [
            'user_id' => $owner->id,
            'exception' => RuntimeException::class,
        ])->once();
    }

    public function test_the_summary_is_only_asked_once_the_access_rule_let_the_person_see(): void
    {
        $owner = $this->owner(ProfileVisibility::Hidden);
        $renderer = new class implements ProfileSummaryRendererInterface
        {
            public int $calls = 0;

            public function summary(ProfileOwnerDTO $owner, ?Authenticatable $viewer): ResolvedProfileDTO
            {
                $this->calls++;

                return new ResolvedProfileDTO(new ProfileHeaderDTO($owner->handle, $owner->publicName, null, null, null, false, false), [], [], []);
            }
        };
        $this->app->scoped(ProfileSummaryRendererInterface::class, static fn (): ProfileSummaryRendererInterface => $renderer);

        $this->assertNull($this->card($owner->id, UserModel::factory()->create()));
        $this->assertNull($this->card($owner->id, null));
        $this->assertSame(0, $renderer->calls);

        $this->assertInstanceOf(ProfileCardDTO::class, $this->card($owner->id, $owner));
        $this->assertSame(1, $renderer->calls);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function owner(ProfileVisibility $visibility, array $attributes = []): UserModel
    {
        $owner = UserModel::factory()->create(['name' => 'Nombre de cuenta', 'display_name' => 'Ana Prueba', ...$attributes]);
        $this->setVisibility($owner, $visibility);

        return $owner;
    }

    private function setVisibility(UserModel $owner, ProfileVisibility $visibility): void
    {
        UserProfileModel::query()->whereKey($owner->id)->update(['visibility' => $visibility->value]);
    }

    private function card(string $userId, ?Authenticatable $viewer): ?ProfileCardDTO
    {
        $this->app->forgetScopedInstances();

        return $this->app->make(ProfileCardServiceInterface::class)->card($userId, $viewer);
    }
}
