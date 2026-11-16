<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Application\Profiles\DTOs\ProfileHeaderDTO;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\ResolvedProfileDTO;
use App\Application\Profiles\Services\ProfileRendererInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Domain\Profiles\ProfileVisibility;
use App\Domain\Profiles\ValueObjects\ProfileHandle;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileHandleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * `/miembros/{handle}` (core#741): the page for whoever may see it, and for everybody else the very same
 * 404 a missing address gets, so nobody learns whether an account exists or where it moved.
 */
final class PublicProfileControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Every request starts afresh, as it does in production: what is bound scoped (the profile rows the
     * renderer and the lookup share) lasts one request, not the whole test.
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

    public function test_a_public_profile_shows_its_header_to_a_guest(): void
    {
        $owner = $this->owner(ProfileVisibility::Public, ['avatar_public_id' => 'avatars/ana.jpg']);

        $response = $this->get('/miembros/ana-prueba');

        $response->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Profiles/Show')
                ->where('profile.header.handle', 'ana-prueba')
                ->where('profile.header.publicName', 'Ana Prueba')
                ->where('profile.header.avatarPublicId', 'avatars/ana.jpg')
                ->where('profile.header.bannerPublicId', null)
                ->where('profile.header.showsMembership', false)
                ->where('profile.header.isOwner', false)
                ->has('profile.stats')
                ->has('profile.sections.main')
                ->has('profile.sections.aside')
                ->where('notices.onlyOwner', false)
                ->where('notices.choosePublicName', false)
                ->where('accountUrl', null)
                ->where('seo.robots', 'noindex, nofollow')
                ->missing('profile.header.email')
                ->missing('profile.header.name'));

        $this->assertStringNotContainsString($owner->email, (string) $response->getContent());
        $this->assertStringNotContainsString('Nombre de cuenta', (string) $response->getContent());
    }

    public function test_members_profiles_need_a_signed_in_account_that_need_not_verify(): void
    {
        $this->owner(ProfileVisibility::Members);
        $this->requireEmailVerification();

        $this->get('/miembros/ana-prueba')->assertNotFound();
        $this->actingAs(UserModel::factory()->unverified()->create())->get('/miembros/ana-prueba')->assertNotFound();
        $this->actingAs(UserModel::factory()->create())->get('/miembros/ana-prueba')->assertOk();
    }

    public function test_association_profiles_are_for_association_members_only(): void
    {
        $this->owner(ProfileVisibility::Association);

        $this->actingAs(UserModel::factory()->create())->get('/miembros/ana-prueba')->assertNotFound();
        $this->actingAs($this->socio())->get('/miembros/ana-prueba')->assertOk();
    }

    public function test_a_hidden_profile_is_for_its_owner_only_with_the_notice(): void
    {
        $owner = $this->owner(ProfileVisibility::Hidden);

        $this->actingAs(UserModel::factory()->admin()->create())->get('/miembros/ana-prueba')->assertNotFound();
        $this->actingAs($owner)->get('/miembros/ana-prueba')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('profile.header.isOwner', true)
                ->where('notices.onlyOwner', true)
                ->where('notices.choosePublicName', false)
                ->where('accountUrl', route('profile.show')));
    }

    public function test_an_owner_without_public_name_is_the_only_one_who_sees_it_and_is_asked_for_one(): void
    {
        $owner = UserModel::factory()->create(['name' => 'Nombre de cuenta', 'display_name' => null]);
        $handle = $this->handleOf($owner);
        $this->setVisibility($owner, ProfileVisibility::Public);

        $this->get("/miembros/{$handle}")->assertNotFound();
        $this->actingAs($owner)->get("/miembros/{$handle}")
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('profile.header.publicName', null)
                ->where('notices.choosePublicName', true)
                ->where('notices.onlyOwner', false)
                // The account name is the owner's own `auth.user`, never part of the profile
                ->where('seo.title', "Perfil de @{$handle}")
                ->missing('profile.header.name'));
    }

    public function test_the_owner_of_a_hidden_profile_without_public_name_gets_both_notices(): void
    {
        $owner = UserModel::factory()->create(['display_name' => null]);
        $this->setVisibility($owner, ProfileVisibility::Hidden);

        $this->actingAs($owner)->get('/miembros/'.$this->handleOf($owner))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('notices.onlyOwner', true)
                ->where('notices.choosePublicName', true));
    }

    public function test_a_deactivated_account_answers_the_404_of_an_unknown_address(): void
    {
        $owner = $this->owner(ProfileVisibility::Public);
        $owner->delete();

        $this->assertSameNotFound($this->get('/miembros/nadie-con-esto'), $this->get('/miembros/ana-prueba'));
    }

    public function test_an_anonymized_account_answers_the_404_on_its_current_and_old_addresses(): void
    {
        $owner = $this->owner(ProfileVisibility::Public);
        $owner->update(['display_name' => 'Ana Nueva']);
        app(UserServiceInterface::class)->anonymize((string) $owner->id);

        $unknown = $this->get('/miembros/nadie-con-esto');
        $this->assertSameNotFound($unknown, $this->get('/miembros/ana-nueva'));
        $this->assertSameNotFound($unknown, $this->get('/miembros/ana-prueba'));
    }

    public function test_a_reserved_address_without_owner_answers_the_404_of_an_unknown_one(): void
    {
        UserProfileHandleModel::query()->create([
            'handle_hash' => ProfileHandle::hashOf('fantasma'),
            'handle' => null,
            'user_id' => null,
            'retired_at' => now(),
        ]);

        $this->assertSameNotFound($this->get('/miembros/nadie-con-esto'), $this->get('/miembros/fantasma'));
    }

    public function test_an_account_without_a_profile_row_has_no_profile(): void
    {
        $owner = $this->owner(ProfileVisibility::Public);
        UserProfileModel::query()->whereKey($owner->id)->delete();

        $this->get('/miembros/ana-prueba')->assertNotFound();
    }

    public function test_hidden_forbidden_and_unknown_answer_the_very_same_404(): void
    {
        $this->owner(ProfileVisibility::Hidden);
        $other = UserModel::factory()->create(['display_name' => 'Berta Prueba']);
        $this->setVisibility($other, ProfileVisibility::Association);

        $hidden = $this->get('/miembros/ana-prueba');
        $forbidden = $this->get('/miembros/berta-prueba');
        $unknown = $this->get('/miembros/nadie-con-esto');
        $malformed = $this->get('/miembros/--no--');
        $reserved = $this->get('/miembros/editar');

        $unknown->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        foreach ([$hidden, $forbidden, $malformed, $reserved] as $response) {
            $this->assertSameNotFound($unknown, $response);
        }
    }

    public function test_an_old_address_moves_to_the_current_one_once_the_profile_may_be_seen(): void
    {
        $owner = $this->owner(ProfileVisibility::Public);
        $owner->update(['display_name' => 'Ana Nueva']);
        $this->assertSame('ana-nueva', $this->handleOf($owner));

        $moved = $this->get('/miembros/ana-prueba')
            ->assertStatus(301)
            ->assertRedirect('/miembros/ana-nueva')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        // It moves only for whoever may see it: no shared cache may keep it
        $this->assertStringContainsString('no-store', (string) $moved->headers->get('Cache-Control'));
        $this->get('/miembros/ANA-Nueva')->assertStatus(301)->assertRedirect('/miembros/ana-nueva');
    }

    public function test_an_old_address_never_reveals_where_a_profile_it_may_not_see_went(): void
    {
        $owner = $this->owner(ProfileVisibility::Members);
        $owner->update(['display_name' => 'Ana Nueva']);

        $this->assertSameNotFound($this->get('/miembros/nadie-con-esto'), $this->get('/miembros/ana-prueba'));

        $this->setVisibility($owner, ProfileVisibility::Hidden);
        $this->actingAs(UserModel::factory()->create());
        $this->assertSameNotFound($this->get('/miembros/nadie-con-esto'), $this->get('/miembros/ana-prueba'));
    }

    public function test_the_membership_badge_needs_both_the_owners_choice_and_the_membership(): void
    {
        $owner = $this->owner(ProfileVisibility::Public);
        $this->assertBadge(false);

        UserProfileModel::query()->whereKey($owner->id)->update(['show_membership' => true]);
        $this->assertBadge(false);

        app(AuthorizationServiceInterface::class)->assignRole($owner, AssociationMembershipServiceInterface::DEFAULT_ROLE);
        $this->assertBadge(true);

        UserProfileModel::query()->whereKey($owner->id)->update(['show_membership' => false]);
        $this->assertBadge(false);
    }

    public function test_a_failing_membership_check_leaves_the_badge_out(): void
    {
        $owner = $this->owner(ProfileVisibility::Public);
        UserProfileModel::query()->whereKey($owner->id)->update(['show_membership' => true]);
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

        $this->assertBadge(false);
    }

    public function test_the_joining_month_is_the_display_zones(): void
    {
        config(['app.display_timezone' => 'Europe/Madrid']);
        $owner = $this->owner(ProfileVisibility::Public);
        // 23:30 UTC on 31 May is already June in Madrid
        $owner->forceFill(['created_at' => Carbon::parse('2024-05-31 23:30:00', 'UTC')])->save();

        $this->get('/miembros/ana-prueba')->assertInertia(fn (Assert $page): Assert => $page->where('profile.header.memberSince', '2024-06-01T01:30:00+02:00'));
    }

    public function test_while_impersonating_it_is_decided_for_the_impersonated_account(): void
    {
        $admin = UserModel::factory()->admin()->create();
        app(AuthorizationServiceInterface::class)->assignRole($admin, AssociationMembershipServiceInterface::DEFAULT_ROLE);
        $member = UserModel::factory()->create(['display_name' => 'Carla Prueba']);
        $owner = $this->owner(ProfileVisibility::Association);

        $this->actingAs($admin)->get('/miembros/ana-prueba')->assertOk();

        app(ImpersonationServiceInterface::class)->start($admin, $member, ImpersonationMode::ReadOnly);
        $this->get('/miembros/ana-prueba')->assertNotFound();
        app(ImpersonationServiceInterface::class)->stop(ImpersonationEndReason::Stopped);

        $this->setVisibility($owner, ProfileVisibility::Hidden);
        app(ImpersonationServiceInterface::class)->start($admin, $owner, ImpersonationMode::ReadOnly);
        $this->get('/miembros/ana-prueba')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->where('profile.header.isOwner', true)->where('notices.onlyOwner', true));
    }

    public function test_an_address_given_before_it_became_a_reserved_word_still_opens(): void
    {
        $owner = $this->owner(ProfileVisibility::Public);
        UserProfileModel::query()->whereKey($owner->id)->update(['handle' => 'staff']);

        $this->get('/miembros/staff')->assertOk()->assertInertia(fn (Assert $page): Assert => $page->where('profile.header.handle', 'staff'));
        $this->get('/miembros/STAFF')->assertRedirect('/miembros/staff');
        $this->actingAs($owner)->get('/miembros/staff')->assertOk();
    }

    public function test_the_history_never_resolves_a_reserved_word(): void
    {
        $owner = $this->owner(ProfileVisibility::Public);
        // A reserved word left in the history (given before it was reserved, then retired)
        UserProfileHandleModel::query()->insert([
            'handle_hash' => ProfileHandle::hashOf('staff'),
            'handle' => 'staff',
            'user_id' => $owner->id,
            'retired_at' => now(),
        ]);

        $this->assertSameNotFound($this->get('/miembros/nadie-con-esto'), $this->get('/miembros/staff'));
    }

    public function test_the_sections_are_drawn_once_the_profile_may_be_seen(): void
    {
        $owner = $this->owner(ProfileVisibility::Public);
        UserProfileModel::query()->whereKey($owner->id)->update(['bio' => 'Juego a rol desde 1998.']);

        $this->get('/miembros/ana-prueba')->assertInertia(fn (Assert $page): Assert => $page
            ->where('profile.sections.aside.0.key', 'core.about')
            ->where('profile.sections.aside.0.label', 'Sobre mí'));
    }

    public function test_the_renderer_is_only_asked_once_the_access_rule_let_the_person_see(): void
    {
        $owner = $this->owner(ProfileVisibility::Hidden);
        $owner->update(['display_name' => 'Ana Nueva']);
        $renderer = new class implements ProfileRendererInterface
        {
            public int $calls = 0;

            public function render(ProfileOwnerDTO $owner, ?Authenticatable $viewer): ResolvedProfileDTO
            {
                $this->calls++;

                return new ResolvedProfileDTO(new ProfileHeaderDTO($owner->handle, $owner->publicName, null, null, null, false, false), [], [], []);
            }
        };
        $this->app->scoped(ProfileRendererInterface::class, static fn (): ProfileRendererInterface => $renderer);

        $this->get('/miembros/ana-nueva')->assertNotFound();
        $this->get('/miembros/ana-prueba')->assertNotFound();
        $this->get('/miembros/nadie-con-esto')->assertNotFound();
        $this->assertSame(0, $renderer->calls);

        $this->setVisibility($owner, ProfileVisibility::Public);
        $this->get('/miembros/ana-prueba')->assertStatus(301);
        $this->assertSame(0, $renderer->calls);

        $this->get('/miembros/ana-nueva')->assertOk();
        $this->assertSame(1, $renderer->calls);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function owner(ProfileVisibility $visibility, array $attributes = []): UserModel
    {
        $owner = UserModel::factory()->create(['name' => 'Nombre de cuenta', 'display_name' => 'Ana Prueba', ...$attributes]);
        $this->assertSame('ana-prueba', $this->handleOf($owner));
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

    private function handleOf(UserModel $owner): string
    {
        return UserProfileModel::query()->findOrFail($owner->id)->handle;
    }

    private function requireEmailVerification(): void
    {
        app(SettingsServiceInterface::class)->set('auth_email_verification_required', '1');
    }

    private function assertBadge(bool $expected): void
    {
        $this->get('/miembros/ana-prueba')->assertInertia(fn (Assert $page): Assert => $page->where('profile.header.showsMembership', $expected));
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
