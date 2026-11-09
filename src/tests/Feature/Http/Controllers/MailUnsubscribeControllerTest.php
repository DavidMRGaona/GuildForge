<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailUnsubscribeLinksInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Mail\Repositories\MailPreferenceRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class MailUnsubscribeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');

        $this->app->make(MailCategoryRegistryInterface::class)->registerMany([
            MailCategoryDTO::mandatory(
                key: 'example.confirmations',
                section: 'Ejemplo',
                label: 'Confirmaciones',
                description: 'Lo que has hecho',
                reason: 'Confirma algo que has hecho',
            ),
            MailCategoryDTO::optional(
                key: 'example.reminders',
                section: 'Ejemplo',
                label: 'Recordatorios',
                description: 'Antes de empezar',
            ),
            MailCategoryDTO::optional(
                key: 'example.news',
                section: 'Ejemplo',
                label: 'Novedades',
                description: 'Lo nuevo',
            ),
            MailCategoryDTO::optional(
                key: 'example.hidden',
                section: 'Ejemplo',
                label: 'Oculta',
                description: 'Solo para algunas personas',
                audience: static fn (object $user): bool => false,
            ),
        ], 'example');
    }

    public function test_opening_the_link_shows_the_confirmation_and_changes_nothing(): void
    {
        $user = UserModel::factory()->create();

        $this->get($this->link($user, 'example.reminders'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mail/Unsubscribe')
                ->where('category.label', 'Recordatorios')
                ->where('category.mandatory', false)
                ->where('unsubscribed', false)
                ->where('actionUrl', $this->link($user, 'example.reminders'))
                ->where('preferencesUrl', route('profile.show', ['tab' => 'mail']))
            );

        $this->assertSame([], $this->disabledFor($user));
        $this->assertGuest();
    }

    public function test_the_page_is_not_cached_nor_indexed(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->get($this->link($user, 'example.reminders'));

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertInertia(fn (Assert $page) => $page->where('seo.robots', 'noindex, nofollow'));
    }

    public function test_confirming_turns_off_only_that_category_for_that_person(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();

        $this->post($this->link($user, 'example.reminders'))
            ->assertRedirect($this->link($user, 'example.reminders'));

        $this->assertSame(['example.reminders'], $this->disabledFor($user));
        $this->assertSame([], $this->disabledFor($other));
        $this->assertGuest();
    }

    public function test_after_confirming_the_page_offers_to_undo(): void
    {
        $user = UserModel::factory()->create();
        $this->post($this->link($user, 'example.reminders'));

        $this->get($this->link($user, 'example.reminders'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Mail/Unsubscribe')
                ->where('unsubscribed', true)
            );
    }

    public function test_undoing_turns_the_category_back_on(): void
    {
        $user = UserModel::factory()->create();
        $this->post($this->link($user, 'example.reminders'));
        $this->post($this->link($user, 'example.news'));

        $this->delete($this->link($user, 'example.reminders'))
            ->assertRedirect($this->link($user, 'example.reminders'))
            ->assertSessionHas('success', 'Vuelves a recibir correos de «Recordatorios».');

        $this->assertSame(['example.news'], $this->disabledFor($user));
    }

    public function test_a_mandatory_category_cannot_be_turned_off_by_link(): void
    {
        $user = UserModel::factory()->create();

        $this->get($this->link($user, 'example.confirmations'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('category.mandatory', true)
                ->where('category.mandatoryReason', 'Confirma algo que has hecho')
            );

        $this->post($this->link($user, 'example.confirmations'))->assertStatus(422);

        $this->assertSame([], $this->disabledFor($user));
    }

    public function test_an_unknown_or_hidden_category_is_not_found(): void
    {
        $user = UserModel::factory()->create();

        $this->get($this->link($user, 'example.missing'))->assertNotFound();
        $this->post($this->link($user, 'example.missing'))->assertNotFound();
        $this->get($this->link($user, 'example.hidden'))->assertNotFound();
        $this->post($this->link($user, 'example.hidden'))->assertNotFound();

        $this->assertSame([], $this->disabledFor($user));
    }

    public function test_an_anonymized_or_deactivated_account_is_not_found(): void
    {
        $anonymized = UserModel::factory()->create(['anonymized_at' => now()]);
        $deactivated = UserModel::factory()->create();
        $link = $this->link($deactivated, 'example.reminders');
        $deactivated->delete();

        $this->get($this->link($anonymized, 'example.reminders'))->assertNotFound();
        $this->post($this->link($anonymized, 'example.reminders'))->assertNotFound();
        $this->get($link)->assertNotFound();
    }

    public function test_a_link_without_a_signature_is_forbidden(): void
    {
        $user = UserModel::factory()->create();
        $unsigned = strtok($this->link($user, 'example.reminders'), '?');

        $this->get((string) $unsigned)->assertForbidden();
        $this->post((string) $unsigned)->assertForbidden();
        $this->delete((string) $unsigned)->assertForbidden();

        $this->assertSame([], $this->disabledFor($user));
    }

    public function test_a_link_for_another_person_is_forbidden(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $tampered = str_replace($user->id, $other->id, $this->link($user, 'example.reminders'));

        $this->get($tampered)->assertForbidden();
        $this->post($tampered)->assertForbidden();

        $this->assertSame([], $this->disabledFor($other));
    }

    public function test_a_link_for_another_category_is_forbidden(): void
    {
        $user = UserModel::factory()->create();
        $tampered = str_replace('example.reminders', 'example.news', $this->link($user, 'example.reminders'));

        $this->get($tampered)->assertForbidden();
        $this->post($tampered)->assertForbidden();

        $this->assertSame([], $this->disabledFor($user));
    }

    public function test_a_forged_signature_is_forbidden(): void
    {
        $user = UserModel::factory()->create();
        $forged = preg_replace('/signature=[0-9a-f]+/', 'signature='.str_repeat('0', 64), $this->link($user, 'example.reminders'));

        $this->post((string) $forged)->assertForbidden();

        $this->assertSame([], $this->disabledFor($user));
    }

    public function test_the_link_never_signs_in_even_when_someone_else_is_signed_in(): void
    {
        $user = UserModel::factory()->create();
        $signedIn = UserModel::factory()->create();

        $this->actingAs($signedIn)->post($this->link($user, 'example.reminders'));

        $this->assertAuthenticatedAs($signedIn);
        $this->assertSame(['example.reminders'], $this->disabledFor($user));
        $this->assertSame([], $this->disabledFor($signedIn));
    }

    public function test_it_works_while_the_site_is_in_maintenance(): void
    {
        app(SettingsServiceInterface::class)->set('maintenance_enabled', '1');
        $user = UserModel::factory()->create();

        $this->get($this->link($user, 'example.reminders'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Mail/Unsubscribe'));
        $this->post($this->link($user, 'example.reminders'))->assertRedirect();

        $this->assertSame(['example.reminders'], $this->disabledFor($user));
    }

    public function test_it_works_for_a_signed_in_account_that_must_verify_its_email(): void
    {
        $settings = app(SettingsServiceInterface::class);
        $settings->set('auth_email_verification_required', '1');
        $settings->set('email_verification_enforced_since', '2026-01-01T00:00:00+00:00');
        $user = UserModel::factory()->unverified()->create();

        $this->actingAs($user)
            ->get($this->link($user, 'example.reminders'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Mail/Unsubscribe'));
        $this->actingAs($user)->post($this->link($user, 'example.reminders'))->assertRedirect();

        $this->assertSame(['example.reminders'], $this->disabledFor($user));
    }

    public function test_requests_are_rate_limited(): void
    {
        $user = UserModel::factory()->create();
        $link = $this->link($user, 'example.reminders');

        for ($i = 0; $i < 20; $i++) {
            $this->post($link)->assertRedirect();
        }

        $this->post($link)->assertTooManyRequests();
    }

    private function link(UserModel $user, string $category): string
    {
        return $this->app->make(MailUnsubscribeLinksInterface::class)->unsubscribeUrl($user->id, $category);
    }

    /**
     * @return list<string>
     */
    private function disabledFor(UserModel $user): array
    {
        $disabled = $this->app->make(MailPreferenceRepositoryInterface::class)->disabledFor(UserId::fromString($user->id));
        sort($disabled);

        return $disabled;
    }
}
