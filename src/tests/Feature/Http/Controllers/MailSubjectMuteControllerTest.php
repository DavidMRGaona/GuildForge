<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Application\Mail\Services\MailUnsubscribeLinksInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The page a mail footer links to for muting one subject (`/correos/silenciar/...`, signed).
 */
final class MailSubjectMuteControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
        app('translator')->addLines(['example-subjects.campaign' => 'Campaña'], 'es');
    }

    public function test_opening_the_link_shows_the_confirmation_and_changes_nothing(): void
    {
        $user = UserModel::factory()->create();

        $this->get($this->link($user))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mail/MuteSubject')
                ->where('subject.label', 'La tumba')
                ->where('subject.typeLabel', 'Campaña')
                ->where('muted', false)
                ->where('actionUrl', $this->link($user))
                ->where('preferencesUrl', route('profile.show', ['tab' => 'mail']))
            );

        $this->assertSame([], $this->mutedIds($user));
        $this->assertGuest();
    }

    public function test_the_page_is_not_cached_nor_indexed(): void
    {
        $response = $this->get($this->link(UserModel::factory()->create()));

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex, nofollow'));
    }

    public function test_an_unknown_type_label_is_left_out(): void
    {
        $user = UserModel::factory()->create();
        $subject = new MailSubjectDTO('example.campaign', 'c-1', 'La tumba', 'example::missing.key');

        $this->get($this->link($user, $subject))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('subject.typeLabel', null));
    }

    public function test_confirming_mutes_only_that_subject_for_that_person(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();

        $this->post($this->link($user))->assertRedirect($this->link($user));

        $this->assertSame(['c-1'], $this->mutedIds($user));
        $this->assertSame([], $this->mutedIds($other));
        $muted = $this->service()->mutedFor($user)[0];
        $this->assertSame('example.campaign', $muted->type);
        $this->assertSame('La tumba', $muted->label);
        $this->assertSame('example-subjects.campaign', $muted->typeLabel);
        $this->assertGuest();
    }

    public function test_after_confirming_the_page_offers_to_undo(): void
    {
        $user = UserModel::factory()->create();
        $this->post($this->link($user));

        $this->get($this->link($user))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('muted', true));
    }

    public function test_undoing_unmutes_the_subject(): void
    {
        $user = UserModel::factory()->create();
        $this->post($this->link($user));

        $this->delete($this->link($user))
            ->assertRedirect($this->link($user))
            ->assertSessionHas('success', 'Vuelves a recibir avisos de «La tumba».');

        $this->assertSame([], $this->mutedIds($user));
    }

    public function test_an_anonymized_or_deactivated_account_is_not_found(): void
    {
        $anonymized = UserModel::factory()->create(['anonymized_at' => now()]);
        $deactivated = UserModel::factory()->create();
        $link = $this->link($deactivated);
        $deactivated->delete();

        $this->get($this->link($anonymized))->assertNotFound();
        $this->post($this->link($anonymized))->assertNotFound();
        $this->get($link)->assertNotFound();
        $this->post($link)->assertNotFound();

        $this->assertSame([], $this->mutedIds($anonymized));
    }

    public function test_a_link_without_a_signature_is_forbidden(): void
    {
        $user = UserModel::factory()->create();
        $unsigned = (string) preg_replace('/[&?]signature=[0-9a-f]+/', '', $this->link($user));

        $this->get($unsigned)->assertForbidden();
        $this->post($unsigned)->assertForbidden();
        $this->delete($unsigned)->assertForbidden();

        $this->assertSame([], $this->mutedIds($user));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function tamperedParts(): array
    {
        return [
            'subject id' => ['/c-1?', '/c-2?'],
            'subject type' => ['example.campaign', 'example.table'],
            'name' => ['La%20tumba', 'Otra%20cosa'],
            'type label' => ['example-subjects.campaign', 'example-subjects.table'],
        ];
    }

    #[DataProvider('tamperedParts')]
    public function test_a_tampered_link_is_forbidden(string $search, string $replace): void
    {
        $user = UserModel::factory()->create();
        $link = $this->link($user);
        $this->assertStringContainsString($search, $link);
        $tampered = str_replace($search, $replace, $link);

        $this->get($tampered)->assertForbidden();
        $this->post($tampered)->assertForbidden();

        $this->assertSame([], $this->mutedIds($user));
    }

    public function test_a_link_for_another_person_is_forbidden(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $tampered = str_replace($user->id, $other->id, $this->link($user));

        $this->get($tampered)->assertForbidden();
        $this->post($tampered)->assertForbidden();

        $this->assertSame([], $this->mutedIds($other));
    }

    public function test_the_link_never_signs_in_even_when_someone_else_is_signed_in(): void
    {
        $user = UserModel::factory()->create();
        $signedIn = UserModel::factory()->create();

        $this->actingAs($signedIn)->post($this->link($user));

        $this->assertAuthenticatedAs($signedIn);
        $this->assertSame(['c-1'], $this->mutedIds($user));
        $this->assertSame([], $this->mutedIds($signedIn));
    }

    public function test_it_works_while_the_site_is_in_maintenance(): void
    {
        app(SettingsServiceInterface::class)->set('maintenance_enabled', '1');
        $user = UserModel::factory()->create();

        $this->get($this->link($user))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Mail/MuteSubject'));
        $this->post($this->link($user))->assertRedirect();

        $this->assertSame(['c-1'], $this->mutedIds($user));
    }

    public function test_it_works_for_a_signed_in_account_that_must_verify_its_email(): void
    {
        $settings = app(SettingsServiceInterface::class);
        $settings->set('auth_email_verification_required', '1');
        $settings->set('email_verification_enforced_since', '2026-01-01T00:00:00+00:00');
        $user = UserModel::factory()->unverified()->create();

        $this->actingAs($user)
            ->get($this->link($user))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Mail/MuteSubject'));
        $this->actingAs($user)->post($this->link($user))->assertRedirect();

        $this->assertSame(['c-1'], $this->mutedIds($user));
    }

    public function test_requests_are_rate_limited(): void
    {
        $user = UserModel::factory()->create();
        $link = $this->link($user);

        for ($i = 0; $i < 20; $i++) {
            $this->post($link)->assertRedirect();
        }

        $this->post($link)->assertTooManyRequests();
    }

    private function link(UserModel $user, ?MailSubjectDTO $subject = null): string
    {
        return $this->app->make(MailUnsubscribeLinksInterface::class)->muteSubjectUrl(
            $user->id,
            $subject ?? new MailSubjectDTO('example.campaign', 'c-1', 'La tumba', 'example-subjects.campaign'),
        );
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
