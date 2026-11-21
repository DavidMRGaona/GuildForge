<?php

declare(strict_types=1);

namespace Tests\Feature\WebPush;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\SiteNotifications\SiteTestNotification;
use Tests\Support\WebPush\EnablesWebPush;
use Tests\TestCase;

/**
 * The service worker reads a notice with the person's session: the push message carried its id only.
 */
final class WebPushNoticeControllerTest extends TestCase
{
    use EnablesWebPush;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(MailCategoryRegistryInterface::class)->register(MailCategoryDTO::optional(
            key: 'example.replies',
            section: 'Example section',
            label: 'Replies',
            description: 'Replies in topics you follow',
            site: true,
        ), 'example');
    }

    public function test_the_owner_reads_what_the_notification_shows(): void
    {
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $id = $this->deliver($user);

        $response = $this->actingAs($user)->getJson("/avisos/{$id}/push");

        $response->assertOk()->assertExactJson([
            'title' => 'Ana respondió en «La tumba»',
            'body' => 'Nos vemos el sábado',
            'url' => "/avisos/{$id}",
            'tag' => "notice-{$id}",
            // A notice's own icon names a drawing of the bell, not an image: the site's icon instead
            'icon' => '/favicons/light/android-chrome-192x192.png',
        ]);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    public function test_notices_of_one_group_share_their_tag(): void
    {
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $id = $this->deliver($user, 'forum.topic:la-tumba');

        $this->actingAs($user)->getJson("/avisos/{$id}/push")->assertJsonPath('tag', 'notice-forum.topic:la-tumba');
    }

    public function test_a_notice_of_another_account_answers_exactly_like_an_unknown_one(): void
    {
        $this->enableWebPush();
        // As in production: with debug on, the body carries a trace of each request
        config(['app.debug' => false]);
        $id = $this->deliver(UserModel::factory()->create());
        $this->actingAs(UserModel::factory()->create());

        $foreign = $this->getJson("/avisos/{$id}/push");
        $unknown = $this->getJson('/avisos/0193a6b2-7c1d-7e3f-8a9b-0c1d2e3f4a5b/push');

        $foreign->assertNotFound();
        $this->assertSame($unknown->getStatusCode(), $foreign->getStatusCode());
        $this->assertSame($unknown->getContent(), $foreign->getContent());
        foreach (['Content-Type', 'Cache-Control', 'X-Robots-Tag', 'Content-Security-Policy'] as $header) {
            $this->assertSame($unknown->headers->get($header), $foreign->headers->get($header), $header);
        }
    }

    public function test_reading_it_does_not_mark_it_read(): void
    {
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $id = $this->deliver($user);

        $this->actingAs($user)->getJson("/avisos/{$id}/push")->assertOk();

        $this->assertNull(DB::table('site_notifications')->where('id', $id)->value('read_at'));
    }

    public function test_nobody_else_reads_it(): void
    {
        $this->enableWebPush();
        $id = $this->deliver(UserModel::factory()->create());

        $this->actingAs(UserModel::factory()->create())->getJson("/avisos/{$id}/push")->assertNotFound();
    }

    public function test_a_hidden_notice_is_gone(): void
    {
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $id = $this->deliver($user);
        DB::table('site_notifications')->where('id', $id)->update(['hidden_at' => now(), 'read_at' => now()]);

        $this->actingAs($user)->getJson("/avisos/{$id}/push")->assertNotFound();
    }

    public function test_without_a_session_the_worker_gets_no_content(): void
    {
        $this->enableWebPush();
        $id = $this->deliver(UserModel::factory()->create());

        $this->getJson("/avisos/{$id}/push")->assertUnauthorized();
    }

    public function test_the_route_does_not_exist_while_push_is_off(): void
    {
        $user = UserModel::factory()->create();
        $id = $this->deliver($user);

        $this->actingAs($user)->getJson("/avisos/{$id}/push")->assertNotFound();
    }

    private function deliver(UserModel $user, ?string $groupKey = null): string
    {
        $user->notify(new SiteTestNotification('example.replies', new SiteNotificationDTO(
            title: 'Ana respondió en «La tumba»',
            url: '/foro/temas/la-tumba#m42',
            body: 'Nos vemos el sábado',
            icon: 'message-square',
            groupKey: $groupKey,
        )));

        return (string) DB::table('site_notifications')->where('user_id', $user->id)->value('id');
    }
}
