<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The muted subjects listed in the «Correos» tab, each with «Volver a recibir».
 */
final class MutedMailSubjectsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string ENDPOINT = '/perfil/correos/silenciados';

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        app('translator')->addLines(['example-subjects.campaign' => 'Campaña'], 'es');
    }

    public function test_the_tab_lists_what_the_person_muted(): void
    {
        $this->travelTo('2026-12-01 10:00:00');
        $user = UserModel::factory()->create();
        $this->service()->mute($user, new MailSubjectDTO('example.campaign', 'c-1', 'La tumba', 'example-subjects.campaign'));
        $this->service()->mute(UserModel::factory()->create(), new MailSubjectDTO('example.campaign', 'c-2', 'Ajena'));

        $this->actingAs($user)->get('/perfil?tab=mail')
            ->assertInertia(fn (Assert $page) => $page
                ->has('mailPreferences.mutedSubjects', 1)
                ->where('mailPreferences.mutedSubjects.0.type', 'example.campaign')
                ->where('mailPreferences.mutedSubjects.0.id', 'c-1')
                ->where('mailPreferences.mutedSubjects.0.label', 'La tumba')
                ->where('mailPreferences.mutedSubjects.0.typeLabel', 'Campaña')
                ->where('mailPreferences.mutedSubjects.0.mutedAt', '2026-12-01T11:00:00+01:00'));
    }

    public function test_a_subject_whose_module_is_gone_keeps_its_stored_name(): void
    {
        $user = UserModel::factory()->create();
        $this->service()->mute($user, new MailSubjectDTO('gone.campaign', 'c-1', 'La tumba', 'gone::mail.subjects.campaign'));

        $this->actingAs($user)->get('/perfil?tab=mail')
            ->assertInertia(fn (Assert $page) => $page
                ->where('mailPreferences.mutedSubjects.0.label', 'La tumba')
                ->where('mailPreferences.mutedSubjects.0.typeLabel', null));
    }

    public function test_other_tabs_do_not_get_the_list(): void
    {
        $this->actingAs(UserModel::factory()->create())->get('/perfil')
            ->assertInertia(fn (Assert $page) => $page->missing('mailPreferences'));
    }

    public function test_it_unmutes_only_that_subject_of_that_person(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->service()->mute($user, new MailSubjectDTO('example.campaign', 'c-1', 'La tumba'));
        $this->service()->mute($user, new MailSubjectDTO('example.campaign', 'c-2', 'Otra'));
        $this->service()->mute($other, new MailSubjectDTO('example.campaign', 'c-1', 'La tumba'));

        $this->actingAs($user)
            ->delete(self::ENDPOINT, ['type' => 'example.campaign', 'id' => 'c-1'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Vuelves a recibir avisos de «La tumba».');

        $this->assertSame(['c-2'], $this->mutedIds($user));
        $this->assertSame(['c-1'], $this->mutedIds($other));
    }

    public function test_unmuting_what_is_not_muted_is_harmless(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->delete(self::ENDPOINT, ['type' => 'example.campaign', 'id' => 'c-1'])
            ->assertRedirect()
            ->assertSessionMissing('success');
    }

    public function test_it_rejects_malformed_subjects(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)->delete(self::ENDPOINT, [])->assertSessionHasErrors(['type', 'id']);
        $this->actingAs($user)->delete(self::ENDPOINT, ['type' => 'Not valid', 'id' => 'a/b'])->assertSessionHasErrors(['type', 'id']);
        $this->actingAs($user)->delete(self::ENDPOINT, ['type' => ['x'], 'id' => str_repeat('a', 65)])->assertSessionHasErrors(['type', 'id']);
    }

    public function test_a_guest_cannot_unmute(): void
    {
        $this->delete(self::ENDPOINT, ['type' => 'example.campaign', 'id' => 'c-1'])->assertRedirect(route('login'));
    }

    public function test_an_anonymized_account_cannot_unmute(): void
    {
        $user = UserModel::factory()->create(['anonymized_at' => now()]);

        $this->actingAs($user)->delete(self::ENDPOINT, ['type' => 'example.campaign', 'id' => 'c-1'])->assertForbidden();
    }

    public function test_an_account_that_must_verify_its_email_is_sent_to_the_notice(): void
    {
        $settings = app(SettingsServiceInterface::class);
        $settings->set('auth_email_verification_required', '1');
        $settings->set('email_verification_enforced_since', '2000-01-01T00:00:00+00:00');
        $user = UserModel::factory()->unverified()->create();
        $this->service()->mute($user, new MailSubjectDTO('example.campaign', 'c-1', 'La tumba'));

        $this->actingAs($user)
            ->delete(self::ENDPOINT, ['type' => 'example.campaign', 'id' => 'c-1'])
            ->assertRedirect(route('verification.notice'));

        $this->assertSame(['c-1'], $this->mutedIds($user));
    }

    /**
     * @return list<string>
     */
    private function mutedIds(UserModel $user): array
    {
        return array_map(static fn ($muted): string => $muted->id, $this->service()->mutedFor($user));
    }

    private function service(): MailSubjectMuteServiceInterface
    {
        return $this->app->make(MailSubjectMuteServiceInterface::class);
    }
}
