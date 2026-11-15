<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Application\Profiles\Contracts\ProfileSectionInterface;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\ProfileStatDTO;
use App\Application\Profiles\Services\ProfileSectionRegistryInterface;
use App\Application\Profiles\Services\ProfileStatRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Domain\Profiles\ProfileVisibility;
use App\Domain\Profiles\ValueObjects\ProfileHandle;
use App\Domain\Profiles\ValueObjects\ProfileLayout;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileHandleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Profiles\FakeProfileSection;
use Tests\Support\Profiles\FakeProfileStat;
use Tests\TestCase;

/**
 * `/miembros/{handle}/tarjeta` (core#744): the summary card of a public profile, for whoever may see the
 * profile, and for everybody else the very same 404 the page answers.
 */
final class PublicProfileCardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $bindings = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Answers as in production: the debug details of a 404 name the line that asked for it
        config(['app.debug' => false, 'cloudinary.cloud_name' => 'demo', 'cloudinary.prefix' => 'gf']);
    }

    /**
     * Every request starts afresh, as it does in production (see PublicProfileControllerTest).
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $cookies
     * @param  array<string, mixed>  $files
     * @param  array<string, mixed>  $server
     * @return TestResponse<Response>
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app->forgetScopedInstances();

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    public function test_a_public_profile_s_card_tells_who_it_is_and_since_when(): void
    {
        config(['app.display_timezone' => 'Europe/Madrid']);
        $owner = $this->owner(ProfileVisibility::Public, ['avatar_public_id' => 'avatars/ana.jpg']);
        $owner->forceFill(['created_at' => Carbon::parse('2024-05-31 23:30:00', 'UTC')])->save();
        UserProfileModel::query()->whereKey($owner->id)->update(['banner_public_id' => 'profiles/banners/ana.jpg']);

        $response = $this->getJson('/miembros/ana-prueba/tarjeta');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertExactJson([
                'handle' => 'ana-prueba',
                'publicName' => 'Ana Prueba',
                'url' => route('profiles.show', ['handle' => 'ana-prueba']),
                'avatarUrl' => 'https://res.cloudinary.com/demo/image/upload/w_128,h_128,c_fill,g_face,q_auto,f_auto/gf/avatars/ana',
                'bannerUrl' => 'https://res.cloudinary.com/demo/image/upload/w_600,h_200,c_fill,g_auto,q_auto,f_auto/gf/profiles/banners/ana',
                'memberSince' => '2024-06-01T01:30:00+02:00',
                'showsMembership' => false,
                'isOwner' => false,
                'stats' => [],
            ]);
    }

    public function test_the_card_is_never_kept_by_a_shared_cache(): void
    {
        $this->owner(ProfileVisibility::Public);

        $cacheControl = (string) $this->getJson('/miembros/ana-prueba/tarjeta')->assertOk()->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }

    public function test_the_card_carries_nothing_private_about_the_owner(): void
    {
        $owner = $this->owner(ProfileVisibility::Public, ['email' => 'ana.secreta@example.com']);
        app(AuthorizationServiceInterface::class)->assignRole($owner, AssociationMembershipServiceInterface::DEFAULT_ROLE);
        UserProfileModel::query()->whereKey($owner->id)->update(['bio' => 'Texto que solo va en la página.']);

        $content = (string) $this->getJson('/miembros/ana-prueba/tarjeta')->assertOk()->getContent();

        $this->assertStringNotContainsString('ana.secreta@example.com', $content);
        $this->assertStringNotContainsString('Nombre de cuenta', $content);
        $this->assertStringNotContainsString((string) $owner->id, $content);
        $this->assertStringNotContainsString('socio', $content);
        $this->assertStringNotContainsString('Texto que solo va en la página', $content);
    }

    public function test_members_profiles_need_a_signed_in_account_that_need_not_verify(): void
    {
        $this->owner(ProfileVisibility::Members);
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '1');

        $this->getJson('/miembros/ana-prueba/tarjeta')->assertNotFound();
        $this->actingAs(UserModel::factory()->unverified()->create())->getJson('/miembros/ana-prueba/tarjeta')->assertNotFound();
        $this->actingAs(UserModel::factory()->create())->getJson('/miembros/ana-prueba/tarjeta')->assertOk();
    }

    public function test_association_profiles_are_for_association_members_only(): void
    {
        $this->owner(ProfileVisibility::Association);

        $this->getJson('/miembros/ana-prueba/tarjeta')->assertNotFound();
        $this->actingAs(UserModel::factory()->create())->getJson('/miembros/ana-prueba/tarjeta')->assertNotFound();
        $this->actingAs($this->socio())->getJson('/miembros/ana-prueba/tarjeta')->assertOk();
    }

    public function test_a_hidden_profile_s_card_is_for_its_owner_only(): void
    {
        $owner = $this->owner(ProfileVisibility::Hidden);

        $this->actingAs(UserModel::factory()->admin()->create())->getJson('/miembros/ana-prueba/tarjeta')->assertNotFound();
        $this->actingAs($owner)->getJson('/miembros/ana-prueba/tarjeta')
            ->assertOk()
            ->assertJsonPath('isOwner', true);
    }

    public function test_an_owner_without_public_name_is_the_only_one_who_gets_the_card(): void
    {
        $owner = UserModel::factory()->create(['name' => 'Nombre de cuenta', 'display_name' => null]);
        $handle = UserProfileModel::query()->findOrFail($owner->id)->handle;
        $this->setVisibility($owner, ProfileVisibility::Public);

        $this->getJson("/miembros/{$handle}/tarjeta")->assertNotFound();
        $response = $this->actingAs($owner)->getJson("/miembros/{$handle}/tarjeta")
            ->assertOk()
            ->assertJsonPath('publicName', null);

        // The account name never stands in for the public name
        $this->assertStringNotContainsString('Nombre de cuenta', (string) $response->getContent());
    }

    public function test_a_deactivated_or_anonymized_account_has_no_card(): void
    {
        $owner = $this->owner(ProfileVisibility::Public);
        $other = UserModel::factory()->create(['display_name' => 'Berta Prueba']);
        $this->setVisibility($other, ProfileVisibility::Public);
        $owner->delete();
        app(UserServiceInterface::class)->anonymize((string) $other->id);

        $unknown = $this->getJson('/miembros/nadie-con-esto/tarjeta');
        $this->assertSameNotFound($unknown, $this->getJson('/miembros/ana-prueba/tarjeta'));
        $this->assertSameNotFound($unknown, $this->getJson('/miembros/berta-prueba/tarjeta'));
    }

    public function test_an_account_without_a_profile_row_has_no_card(): void
    {
        $owner = $this->owner(ProfileVisibility::Public);
        UserProfileModel::query()->whereKey($owner->id)->delete();

        $this->getJson('/miembros/ana-prueba/tarjeta')->assertNotFound();
    }

    public function test_hidden_forbidden_malformed_reserved_and_unknown_answer_the_very_same_404(): void
    {
        $this->owner(ProfileVisibility::Hidden);
        $other = UserModel::factory()->create(['display_name' => 'Berta Prueba']);
        $this->setVisibility($other, ProfileVisibility::Association);
        UserProfileHandleModel::query()->create([
            'handle_hash' => ProfileHandle::hashOf('fantasma'),
            'handle' => null,
            'user_id' => null,
            'retired_at' => now(),
        ]);

        foreach ([[], ['Accept' => 'application/json']] as $headers) {
            $unknown = $this->get('/miembros/nadie-con-esto/tarjeta', $headers);
            $unknown->assertHeader('X-Robots-Tag', 'noindex, nofollow');

            foreach (['ana-prueba', 'berta-prueba', '--no--', 'editar', 'fantasma'] as $handle) {
                $this->assertSameNotFound($unknown, $this->get("/miembros/{$handle}/tarjeta", $headers));
            }
        }
    }

    public function test_the_404_is_the_one_the_profile_page_answers(): void
    {
        $this->owner(ProfileVisibility::Hidden);

        foreach ([[], ['Accept' => 'application/json']] as $headers) {
            $page = $this->get('/miembros/ana-prueba', $headers);
            $card = $this->get('/miembros/ana-prueba/tarjeta', $headers);

            $this->assertSameNotFound($page, $card);
            $this->assertSame($page->headers->get('Cache-Control'), $card->headers->get('Cache-Control'));
        }
    }

    public function test_an_old_address_gives_the_card_of_the_current_one_once_the_profile_may_be_seen(): void
    {
        $owner = $this->owner(ProfileVisibility::Public);
        $owner->update(['display_name' => 'Ana Nueva']);

        foreach (['ana-prueba', 'ANA-Nueva'] as $handle) {
            $response = $this->getJson("/miembros/{$handle}/tarjeta")
                ->assertOk()
                ->assertJsonPath('handle', 'ana-nueva')
                ->assertJsonPath('url', route('profiles.show', ['handle' => 'ana-nueva']));
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        }
    }

    public function test_an_old_address_never_reveals_where_a_profile_it_may_not_see_went(): void
    {
        $owner = $this->owner(ProfileVisibility::Members);
        $owner->update(['display_name' => 'Ana Nueva']);

        $unknown = $this->getJson('/miembros/nadie-con-esto/tarjeta');
        $this->assertSameNotFound($unknown, $this->getJson('/miembros/ana-prueba/tarjeta'));

        $this->setVisibility($owner, ProfileVisibility::Hidden);
        $this->actingAs(UserModel::factory()->create());
        $this->assertSameNotFound($this->getJson('/miembros/nadie-con-esto/tarjeta'), $this->getJson('/miembros/ana-prueba/tarjeta'));
    }

    public function test_the_membership_badge_needs_both_the_owner_s_choice_and_the_membership(): void
    {
        $owner = $this->owner(ProfileVisibility::Public);
        UserProfileModel::query()->whereKey($owner->id)->update(['show_membership' => true]);
        $this->getJson('/miembros/ana-prueba/tarjeta')->assertJsonPath('showsMembership', false);

        app(AuthorizationServiceInterface::class)->assignRole($owner, AssociationMembershipServiceInterface::DEFAULT_ROLE);
        $this->getJson('/miembros/ana-prueba/tarjeta')->assertJsonPath('showsMembership', true);

        UserProfileModel::query()->whereKey($owner->id)->update(['show_membership' => false]);
        $this->getJson('/miembros/ana-prueba/tarjeta')->assertJsonPath('showsMembership', false);
    }

    public function test_the_card_shows_the_first_four_stats_on_in_the_owner_s_order(): void
    {
        $this->stats([
            $this->stat('forum.messages', 10),
            $this->stat('forum.topics', 20),
            $this->stat('forum.likes', 30),
            $this->stat('forum.quotes', 40),
            $this->stat('forum.polls', 50),
            $this->stat('forum.hidden', 5, enabledByDefault: false),
            $this->stat('forum.nothing', 1, value: null),
        ]);
        $owner = $this->owner(ProfileVisibility::Public);
        UserProfileModel::query()->findOrFail($owner->id)->update(['layout' => ProfileLayout::fromArray(['stats' => [
            ['key' => 'forum.polls', 'enabled' => true],
            ['key' => 'forum.messages', 'enabled' => false],
        ]])]);

        $stats = $this->getJson('/miembros/ana-prueba/tarjeta')->assertOk()->json('stats');

        $this->assertSame(['forum.polls', 'forum.topics', 'forum.likes', 'forum.quotes'], array_column($stats, 'key'));
        $this->assertSame(['key' => 'forum.polls', 'label' => 'Contador de ejemplo', 'value' => 3, 'url' => '/miembros/ana-prueba'], $stats[0]);
    }

    public function test_no_section_is_built_for_a_card(): void
    {
        $built = 0;
        $this->app->bind('watched-section', static function () use (&$built): ProfileSectionInterface {
            $built++;

            return new class extends FakeProfileSection
            {
                public function key(): string
                {
                    return 'forum.watched';
                }
            };
        });
        $this->app->make(ProfileSectionRegistryInterface::class)->registerMany(['watched-section'], 'forum');
        $owner = $this->owner(ProfileVisibility::Public);
        UserProfileModel::query()->whereKey($owner->id)->update(['bio' => 'Juego a rol desde 1998.']);

        $this->getJson('/miembros/ana-prueba/tarjeta')->assertOk()->assertJsonMissingPath('sections');
        $this->assertSame(0, $built);

        // The page does build it
        $this->get('/miembros/ana-prueba')->assertOk();
        $this->assertGreaterThan(0, $built);
    }

    public function test_while_impersonating_it_is_decided_for_the_impersonated_account(): void
    {
        $admin = UserModel::factory()->admin()->create();
        app(AuthorizationServiceInterface::class)->assignRole($admin, AssociationMembershipServiceInterface::DEFAULT_ROLE);
        $member = UserModel::factory()->create(['display_name' => 'Carla Prueba']);
        $this->owner(ProfileVisibility::Association);

        $this->actingAs($admin)->getJson('/miembros/ana-prueba/tarjeta')->assertOk();

        app(ImpersonationServiceInterface::class)->start($admin, $member, ImpersonationMode::ReadOnly);
        $this->getJson('/miembros/ana-prueba/tarjeta')->assertNotFound();
        app(ImpersonationServiceInterface::class)->stop(ImpersonationEndReason::Stopped);
    }

    public function test_a_failing_access_check_answers_the_404(): void
    {
        $this->owner(ProfileVisibility::Association);
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

        $this->actingAs(UserModel::factory()->create())->getJson('/miembros/ana-prueba/tarjeta')->assertNotFound();
    }

    public function test_the_card_is_rate_limited_per_address_for_guests(): void
    {
        $this->owner(ProfileVisibility::Public);

        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/miembros/ana-prueba/tarjeta')->assertOk();
        }

        // Unknown addresses count too: guessing costs the same
        $this->getJson('/miembros/nadie-con-esto/tarjeta')->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->getJson('/miembros/ana-prueba/tarjeta')->assertOk();
    }

    public function test_signed_in_people_behind_one_address_have_a_limit_each(): void
    {
        $this->owner(ProfileVisibility::Public);
        $first = UserModel::factory()->create();
        $second = UserModel::factory()->create();

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($first)->getJson('/miembros/ana-prueba/tarjeta')->assertOk();
        }

        $this->actingAs($first)->getJson('/miembros/ana-prueba/tarjeta')->assertStatus(429);
        $this->actingAs($second)->getJson('/miembros/ana-prueba/tarjeta')->assertOk();
    }

    public function test_the_card_does_not_use_the_page_s_limit(): void
    {
        $this->owner(ProfileVisibility::Public);

        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/miembros/ana-prueba/tarjeta')->assertOk();
        }

        $this->get('/miembros/ana-prueba')->assertOk();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function owner(ProfileVisibility $visibility, array $attributes = []): UserModel
    {
        $owner = UserModel::factory()->create(['name' => 'Nombre de cuenta', 'display_name' => 'Ana Prueba', ...$attributes]);
        $this->assertSame('ana-prueba', UserProfileModel::query()->findOrFail($owner->id)->handle);
        $this->setVisibility($owner, $visibility);

        return $owner;
    }

    private function socio(): UserModel
    {
        $socio = UserModel::factory()->create();
        app(AuthorizationServiceInterface::class)->assignRole($socio, AssociationMembershipServiceInterface::DEFAULT_ROLE);

        return $socio;
    }

    private function setVisibility(UserModel $owner, ProfileVisibility $visibility): void
    {
        UserProfileModel::query()->whereKey($owner->id)->update(['visibility' => $visibility->value]);
    }

    /**
     * @param  list<string>  $ids
     */
    private function stats(array $ids): void
    {
        $this->app->make(ProfileStatRegistryInterface::class)->registerMany($ids, 'forum');
    }

    /**
     * A stat counting 3 for a guest (FakeProfileStat), or nothing when `$value` is null.
     */
    private function stat(string $key, int $order, bool $enabledByDefault = true, ?int $value = 3): string
    {
        $instance = new class($key, $order, $enabledByDefault, $value) extends FakeProfileStat
        {
            public function __construct(
                private readonly string $statKey = '',
                private readonly int $position = 100,
                private readonly bool $onByDefault = true,
                private readonly ?int $value = 3,
            ) {}

            public function key(): string
            {
                return $this->statKey;
            }

            public function order(): int
            {
                return $this->position;
            }

            public function enabledByDefault(): bool
            {
                return $this->onByDefault;
            }

            public function stat(ProfileOwnerDTO $owner, ?Authenticatable $viewer): ?ProfileStatDTO
            {
                return $this->value === null ? null : parent::stat($owner, $viewer);
            }
        };

        $id = 'card-test-stat-'.(++$this->bindings);
        $this->app->bind($id, static fn (): FakeProfileStat => $instance);

        return $id;
    }

    /**
     * @param  TestResponse<Response>  $expected
     * @param  TestResponse<Response>  $actual
     */
    private function assertSameNotFound(TestResponse $expected, TestResponse $actual): void
    {
        $expected->assertNotFound();
        $actual->assertNotFound();
        $this->assertSame($expected->getContent(), $actual->getContent());
        $this->assertSame($expected->headers->get('X-Robots-Tag'), $actual->headers->get('X-Robots-Tag'));
        $this->assertSame($expected->headers->get('Content-Type'), $actual->headers->get('Content-Type'));
    }
}
