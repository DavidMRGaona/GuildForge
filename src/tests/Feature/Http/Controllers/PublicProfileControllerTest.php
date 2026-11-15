<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Domain\Profiles\ProfileVisibility;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
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

    public function test_a_public_profile_shows_its_header_to_a_guest(): void
    {
        $owner = $this->owner(ProfileVisibility::Public, ['avatar_public_id' => 'avatars/ana.jpg']);

        $response = $this->get('/miembros/ana-prueba');

        $response->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Profiles/Show')
                ->where('profile.handle', 'ana-prueba')
                ->where('profile.publicName', 'Ana Prueba')
                ->where('profile.avatarPublicId', 'avatars/ana.jpg')
                ->where('profile.bannerPublicId', null)
                ->where('profile.membershipBadge', false)
                ->where('isOwner', false)
                ->where('notices.onlyOwner', false)
                ->where('notices.choosePublicName', false)
                ->where('editUrl', null)
                ->where('seo.robots', 'noindex, nofollow')
                ->missing('profile.email')
                ->missing('profile.name'));

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
                ->where('isOwner', true)
                ->where('notices.onlyOwner', true)
                ->where('notices.choosePublicName', false)
                ->where('editUrl', route('profile.show')));
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
                ->where('profile.publicName', null)
                ->where('notices.choosePublicName', true)
                ->where('notices.onlyOwner', false)
                // The account name is the owner's own `auth.user`, never part of the profile
                ->where('seo.title', "Perfil de @{$handle}")
                ->missing('profile.name'));
    }

    public function test_a_deactivated_account_has_no_profile(): void
    {
        $owner = $this->owner(ProfileVisibility::Public);
        $owner->delete();

        $this->get('/miembros/ana-prueba')->assertNotFound();
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

        foreach ([$hidden, $forbidden, $malformed, $reserved] as $response) {
            $this->assertSameNotFound($unknown, $response);
        }
    }

    public function test_an_old_address_moves_to_the_current_one_once_the_profile_may_be_seen(): void
    {
        $owner = $this->owner(ProfileVisibility::Public);
        $owner->update(['display_name' => 'Ana Nueva']);
        $this->assertSame('ana-nueva', $this->handleOf($owner));

        $this->get('/miembros/ana-prueba')
            ->assertStatus(301)
            ->assertRedirect('/miembros/ana-nueva')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
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

        $this->get('/miembros/ana-prueba')->assertInertia(fn (Assert $page): Assert => $page->where('profile.memberSince', '2024-06'));
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
            ->assertInertia(fn (Assert $page): Assert => $page->where('isOwner', true)->where('notices.onlyOwner', true));
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
        $this->get('/miembros/ana-prueba')->assertInertia(fn (Assert $page): Assert => $page->where('profile.membershipBadge', $expected));
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
