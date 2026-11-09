<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Services\EmailVerificationPolicyInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Mail\Preferences\MailCategoryRegistry;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class MailPreferencesControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $registry = new MailCategoryRegistry(rejectsInvalidCategories: true);
        $this->app->instance(MailCategoryRegistryInterface::class, $registry);
        $registry->registerMany([
            MailCategoryDTO::mandatory(
                key: 'example.confirmations',
                section: 'Example section',
                label: 'Confirmations',
                description: 'What you did',
                reason: 'It confirms something you did',
            ),
            MailCategoryDTO::optional(
                key: 'example.reminders',
                section: 'Example section',
                label: 'Reminders',
                description: 'Before it starts',
            ),
            MailCategoryDTO::optional(
                key: 'example.moderation',
                section: 'Other section',
                label: 'Moderation',
                description: 'Pending review',
                audience: static fn (object $user): bool => $user instanceof UserModel && $user->name === 'Moderator',
            ),
        ], 'example');
    }

    public function test_the_mail_tab_receives_the_sections_the_person_sees(): void
    {
        $user = UserModel::factory()->create();
        $this->disable($user, 'example.reminders');

        $this->actingAs($user)->get('/perfil?tab=mail')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Show')
                ->has('mailPreferences.sections', 1)
                ->where('mailPreferences.sections.0.label', 'Example section')
                ->has('mailPreferences.sections.0.categories', 2)
                ->where('mailPreferences.sections.0.categories.0.key', 'example.confirmations')
                ->where('mailPreferences.sections.0.categories.0.mandatory', true)
                ->where('mailPreferences.sections.0.categories.0.mandatoryReason', 'It confirms something you did')
                ->where('mailPreferences.sections.0.categories.0.enabled', true)
                ->where('mailPreferences.sections.0.categories.1.key', 'example.reminders')
                ->where('mailPreferences.sections.0.categories.1.mandatory', false)
                ->where('mailPreferences.sections.0.categories.1.mandatoryReason', null)
                ->where('mailPreferences.sections.0.categories.1.enabled', false));
    }

    public function test_a_category_the_person_does_not_see_is_left_out(): void
    {
        $user = UserModel::factory()->create(['name' => 'Someone']);

        $this->actingAs($user)->get('/perfil?tab=mail')
            ->assertInertia(fn (Assert $page) => $page->has('mailPreferences.sections', 1));

        $moderator = UserModel::factory()->create(['name' => 'Moderator']);

        $this->actingAs($moderator)->get('/perfil?tab=mail')
            ->assertInertia(fn (Assert $page) => $page
                ->has('mailPreferences.sections', 2)
                ->where('mailPreferences.sections.1.categories.0.key', 'example.moderation'));
    }

    public function test_other_tabs_do_not_load_the_preferences(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)->get('/perfil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->missing('mailPreferences'));
    }

    public function test_it_saves_an_optional_category(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->put('/perfil/correos', ['preferences' => ['example.reminders' => false]])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(
            0,
            (int) DB::table('user_mail_preferences')
                ->where('user_id', $user->id)
                ->where('category', 'example.reminders')
                ->value('enabled'),
        );

        $this->actingAs($user)
            ->put('/perfil/correos', ['preferences' => ['example.reminders' => true]])
            ->assertSessionHas('success');

        $this->actingAs($user)->get('/perfil?tab=mail')
            ->assertInertia(fn (Assert $page) => $page
                ->where('mailPreferences.sections.0.categories.1.enabled', true));
    }

    public function test_it_rejects_a_mandatory_category(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->put('/perfil/correos', ['preferences' => ['example.confirmations' => false]])
            ->assertSessionHasErrors('preferences.example.confirmations');

        $this->assertSame(0, DB::table('user_mail_preferences')->count());
    }

    public function test_it_rejects_a_category_the_person_does_not_see(): void
    {
        $user = UserModel::factory()->create(['name' => 'Someone']);

        $this->actingAs($user)
            ->put('/perfil/correos', ['preferences' => ['example.moderation' => false]])
            ->assertSessionHasErrors('preferences.example.moderation');

        $this->assertSame(0, DB::table('user_mail_preferences')->count());
    }

    public function test_it_rejects_an_unknown_category(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->put('/perfil/correos', ['preferences' => ['example.nothing' => false]])
            ->assertSessionHasErrors('preferences.example.nothing');
    }

    public function test_one_invalid_choice_saves_nothing(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->put('/perfil/correos', ['preferences' => [
                'example.reminders' => false,
                'example.confirmations' => false,
            ]])
            ->assertSessionHasErrors('preferences.example.confirmations');

        $this->assertSame(0, DB::table('user_mail_preferences')->count());
    }

    public function test_it_rejects_values_that_are_not_booleans(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->put('/perfil/correos', ['preferences' => ['example.reminders' => 'maybe']])
            ->assertSessionHasErrors('preferences.example.reminders');

        $this->actingAs($user)
            ->put('/perfil/correos', ['preferences' => 'everything'])
            ->assertSessionHasErrors('preferences');

        $this->actingAs($user)
            ->put('/perfil/correos', [])
            ->assertSessionHasErrors('preferences');
    }

    public function test_it_keeps_a_choice_the_person_no_longer_sees(): void
    {
        $user = UserModel::factory()->create(['name' => 'Moderator']);
        $this->disable($user, 'example.moderation');

        $user->forceFill(['name' => 'Someone'])->save();

        $this->actingAs($user)
            ->put('/perfil/correos', ['preferences' => ['example.reminders' => false]])
            ->assertSessionHas('success');

        $this->assertSame(2, DB::table('user_mail_preferences')->where('user_id', $user->id)->count());
        $this->assertSame(
            0,
            (int) DB::table('user_mail_preferences')
                ->where('user_id', $user->id)
                ->where('category', 'example.moderation')
                ->value('enabled'),
        );
    }

    public function test_a_guest_is_sent_to_the_login(): void
    {
        $this->get('/perfil?tab=mail')->assertRedirect('/iniciar-sesion');
        $this->put('/perfil/correos', ['preferences' => ['example.reminders' => false]])
            ->assertRedirect('/iniciar-sesion');
    }

    public function test_an_anonymized_account_cannot_save(): void
    {
        $user = UserModel::factory()->create();
        $user->forceFill(['anonymized_at' => now()])->save();

        $this->actingAs($user)
            ->put('/perfil/correos', ['preferences' => ['example.reminders' => false]])
            ->assertForbidden();

        $this->assertSame(0, DB::table('user_mail_preferences')->count());
    }

    public function test_an_account_that_must_verify_its_email_is_sent_to_the_notice(): void
    {
        $settings = app(SettingsServiceInterface::class);
        $settings->set('auth_email_verification_required', '1');
        $settings->set('email_verification_enforced_since', '2000-01-01T00:00:00+00:00');
        $user = UserModel::factory()->unverified()->create();
        $this->assertTrue(app(EmailVerificationPolicyInterface::class)->mustVerify($user));

        $this->actingAs($user)
            ->put('/perfil/correos', ['preferences' => ['example.reminders' => false]])
            ->assertRedirect(route('verification.notice'));

        $this->assertSame(0, DB::table('user_mail_preferences')->count());
    }

    private function disable(UserModel $user, string $category): void
    {
        DB::table('user_mail_preferences')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'category' => $category,
            'enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
