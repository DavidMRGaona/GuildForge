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
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * RFC 8058: the mail client posts `List-Unsubscribe=One-Click` to the List-Unsubscribe address,
 * with no session, no cookies and no CSRF token.
 */
final class OneClickMailUnsubscribeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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
        ], 'example');
    }

    public function test_the_one_click_post_turns_off_only_that_category(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();

        $response = $this->post($this->oneClick($user, 'example.reminders'), ['List-Unsubscribe' => 'One-Click']);

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        $response->assertCookieMissing((string) config('session.cookie'));
        $this->assertSame(['example.reminders'], $this->disabledFor($user));
        $this->assertSame([], $this->disabledFor($other));
        $this->assertGuest();
    }

    public function test_the_one_click_route_is_the_only_one_without_csrf_and_session(): void
    {
        $oneClick = RouteFacade::getRoutes()->getByName('mail.unsubscribe.one-click');
        $page = RouteFacade::getRoutes()->getByName('mail.unsubscribe');

        $this->assertInstanceOf(Route::class, $oneClick);
        $this->assertInstanceOf(Route::class, $page);
        $this->assertContains(PreventRequestForgery::class, $oneClick->excludedMiddleware());
        $this->assertContains(StartSession::class, $oneClick->excludedMiddleware());
        $this->assertNotContains(PreventRequestForgery::class, $page->excludedMiddleware());
    }

    public function test_opening_the_one_click_address_only_leads_to_the_confirmation(): void
    {
        $user = UserModel::factory()->create();
        $page = $this->app->make(MailUnsubscribeLinksInterface::class)->unsubscribeUrl($user->id, 'example.reminders');

        $this->get($this->oneClick($user, 'example.reminders'))->assertRedirect($page);

        $this->assertSame([], $this->disabledFor($user));
    }

    public function test_a_mandatory_category_cannot_be_turned_off(): void
    {
        $user = UserModel::factory()->create();

        $this->post($this->oneClick($user, 'example.confirmations'))->assertStatus(422);

        $this->assertSame([], $this->disabledFor($user));
    }

    public function test_an_unknown_category_or_account_is_not_found(): void
    {
        $user = UserModel::factory()->create();
        $anonymized = UserModel::factory()->create(['anonymized_at' => now()]);

        $this->post($this->oneClick($user, 'example.missing'))->assertNotFound();
        $this->post($this->oneClick($anonymized, 'example.reminders'))->assertNotFound();
    }

    public function test_a_tampered_or_unsigned_address_is_forbidden(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $link = $this->oneClick($user, 'example.reminders');

        $this->post((string) strtok($link, '?'))->assertForbidden();
        $this->post(str_replace($user->id, $other->id, $link))->assertForbidden();
        $this->post(str_replace('example.reminders', 'example.news', $link))->assertForbidden();

        $this->assertSame([], $this->disabledFor($user));
        $this->assertSame([], $this->disabledFor($other));
    }

    public function test_the_page_signature_does_not_open_the_one_click_address(): void
    {
        $user = UserModel::factory()->create();
        $page = $this->app->make(MailUnsubscribeLinksInterface::class)->unsubscribeUrl($user->id, 'example.reminders');
        $signature = (string) parse_url($page, PHP_URL_QUERY);

        $this->post(strtok($this->oneClick($user, 'example.reminders'), '?').'?'.$signature)->assertForbidden();

        $this->assertSame([], $this->disabledFor($user));
    }

    public function test_it_works_while_the_site_is_in_maintenance(): void
    {
        app(SettingsServiceInterface::class)->set('maintenance_enabled', '1');
        $user = UserModel::factory()->create();

        $this->post($this->oneClick($user, 'example.reminders'))->assertOk();

        $this->assertSame(['example.reminders'], $this->disabledFor($user));
    }

    public function test_requests_are_rate_limited(): void
    {
        $user = UserModel::factory()->create();
        $link = $this->oneClick($user, 'example.reminders');

        for ($i = 0; $i < 20; $i++) {
            $this->post($link)->assertOk();
        }

        $this->post($link)->assertTooManyRequests();
    }

    private function oneClick(UserModel $user, string $category): string
    {
        return $this->app->make(MailUnsubscribeLinksInterface::class)->oneClickUrl($user->id, $category);
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
