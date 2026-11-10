<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Infrastructure\Persistence\Eloquent\Models\SiteNotificationModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Support\SiteNotifications\SiteTestNotification;
use Tests\TestCase;

/**
 * The bell in the header and the «Avisos» page.
 */
final class SiteNotificationsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(MailCategoryRegistryInterface::class)->register(
            MailCategoryDTO::optional(key: 'example.replies', section: 'Example', label: 'Replies', description: 'D', site: true),
            'example',
        );
    }

    public function test_every_page_shares_the_unread_count_of_the_signed_in_account(): void
    {
        $user = UserModel::factory()->create();
        $this->notify($user, '/foro/1');
        $this->notify($user, '/foro/2');

        $this->actingAs($user)->get('/')->assertInertia(
            static fn (AssertableInertia $page): AssertableInertia => $page->where('siteNotifications.unread', 2),
        );
    }

    public function test_guests_get_no_count(): void
    {
        $this->get('/')->assertInertia(
            static fn (AssertableInertia $page): AssertableInertia => $page->where('siteNotifications', null),
        );
    }

    public function test_the_counter_answers_json(): void
    {
        $user = UserModel::factory()->create();
        $this->notify($user, '/foro/1');

        $this->actingAs($user)->getJson('/avisos/contador')->assertOk()->assertExactJson(['unread' => 1]);
    }

    public function test_the_recent_notices_come_newest_first_with_their_link_through_the_core(): void
    {
        $user = UserModel::factory()->create();
        $this->notify($user, '/foro/1', 'Primero');
        $this->notify($user, '/foro/2', 'Segundo');

        $response = $this->actingAs($user)->getJson('/avisos/recientes')->assertOk();

        $response->assertJsonPath('unread', 2);
        $response->assertJsonPath('items.0.title', 'Segundo');
        $response->assertJsonPath('items.1.title', 'Primero');
        $id = $response->json('items.0.id');
        $this->assertIsString($id);
        $response->assertJsonPath('items.0.href', '/avisos/'.$id);
        $response->assertJsonPath('items.0.unread', true);
        $response->assertJsonPath('items.0.count', 1);
        $this->assertArrayNotHasKey('url', $response->json('items.0'));
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->getJson('/avisos/contador')->assertUnauthorized();
        $this->get('/avisos')->assertRedirect('/iniciar-sesion');
    }

    public function test_opening_a_notice_marks_it_read_and_goes_where_it_leads(): void
    {
        $user = UserModel::factory()->create();
        $this->notify($user, '/foro/temas/la-tumba#m42');
        $notice = SiteNotificationModel::query()->firstOrFail();

        $this->actingAs($user)->get('/avisos/'.$notice->id)->assertRedirect('/foro/temas/la-tumba#m42');

        $this->assertNotNull($notice->fresh()?->read_at);
    }

    public function test_someone_else_s_notice_is_not_found_and_stays_unread(): void
    {
        $owner = UserModel::factory()->create();
        $this->notify($owner, '/foro/1');
        $notice = SiteNotificationModel::query()->firstOrFail();

        $this->actingAs(UserModel::factory()->create())->get('/avisos/'.$notice->id)->assertNotFound();
        $this->actingAs($owner)->get('/avisos/'.Str::uuid()->toString())->assertNotFound();

        $this->assertNull($notice->fresh()?->read_at);
    }

    public function test_a_stored_url_that_left_the_site_goes_home(): void
    {
        $user = UserModel::factory()->create();
        $this->notify($user, '/foro/1');
        $notice = SiteNotificationModel::query()->firstOrFail();
        // Written by hand: the channel never stores one
        $notice->forceFill(['url' => '//example.org/phishing'])->save();

        $this->actingAs($user)->get('/avisos/'.$notice->id)->assertRedirect('/');
    }

    public function test_marking_everything_read(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->notify($user, '/foro/1');
        $this->notify($user, '/foro/2');
        $this->notify($other, '/foro/3');

        $this->actingAs($user)->from('/avisos')->post('/avisos/leidos')->assertRedirect('/avisos');

        $this->assertSame(0, SiteNotificationModel::query()->where('user_id', $user->id)->whereNull('read_at')->count());
        $this->assertSame(1, SiteNotificationModel::query()->where('user_id', $other->id)->whereNull('read_at')->count());
    }

    public function test_marking_everything_read_answers_json_to_the_bell(): void
    {
        $user = UserModel::factory()->create();
        $this->notify($user, '/foro/1');

        $this->actingAs($user)->postJson('/avisos/leidos')->assertOk()->assertExactJson(['unread' => 0]);
    }

    public function test_the_page_lists_every_notice_paginated(): void
    {
        $user = UserModel::factory()->create();
        foreach (range(1, 22) as $n) {
            $this->notify($user, '/foro/'.$n, 'Aviso '.$n);
        }

        $this->actingAs($user)->get('/avisos')->assertOk()->assertInertia(
            static fn (AssertableInertia $page): AssertableInertia => $page
                ->component('Notifications/Index')
                ->has('notifications.data', 20)
                ->where('notifications.data.0.title', 'Aviso 22')
                ->where('notifications.meta.total', 22)
                ->where('notifications.meta.lastPage', 2)
                ->where('unread', 22),
        );

        $this->actingAs($user)->get('/avisos?page=2')->assertInertia(
            static fn (AssertableInertia $page): AssertableInertia => $page->has('notifications.data', 2),
        );
    }

    private function notify(UserModel $user, string $url, string $title = 'Aviso'): void
    {
        $user->notify(new SiteTestNotification('example.replies', new SiteNotificationDTO(title: $title, url: $url)));
    }
}
