<?php

declare(strict_types=1);

namespace Tests\Feature\WebPush;

use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Application\WebPush\DTOs\WebPushSubscriptionDTO;
use App\Application\WebPush\Services\WebPushSenderInterface;
use App\Application\WebPush\Services\WebPushSubscriptionServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Domain\WebPush\WebPushDeliveryOutcome;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\WebPushSubscriptionModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\WebPush\EnablesWebPush;
use Tests\TestCase;

/**
 * The devices of the account in the «Avisos» tab (core#804): each one listed, removable, and a
 * test notice for the browser in use.
 */
final class WebPushDeviceControllerTest extends TestCase
{
    use EnablesWebPush;
    use LazilyRefreshDatabase;

    public function test_the_mail_tab_lists_the_devices_of_the_account_and_marks_this_one(): void
    {
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $phone = $this->subscribe($user);
        $laptop = $this->subscribe($user, 'https://web.push.apple.com/laptop', ['browser' => 'safari', 'platform' => 'macos']);
        $this->subscribe(UserModel::factory()->create(), 'https://web.push.apple.com/someone-else');

        $devices = $this->actingAs($user)
            ->withSession([WebPushSubscriptionServiceInterface::SESSION_KEY => $laptop->id])
            ->get('/perfil?tab=mail')
            ->assertOk()
            ->viewData('page')['props']['mailPreferences']['pushDevices'];

        $this->assertIsArray($devices);
        $byId = array_column($devices, null, 'id');
        $this->assertEqualsCanonicalizing([$phone->id, $laptop->id], array_keys($byId));
        $this->assertSame(['id', 'browser', 'platform', 'current', 'createdAt', 'lastUsedAt'], array_keys($byId[$laptop->id]));
        $this->assertTrue($byId[$laptop->id]['current']);
        $this->assertSame('safari', $byId[$laptop->id]['browser']);
        $this->assertSame('macos', $byId[$laptop->id]['platform']);
        $this->assertFalse($byId[$phone->id]['current']);
        $this->assertNull($byId[$phone->id]['lastUsedAt']);
    }

    public function test_without_push_no_device_is_listed(): void
    {
        $user = UserModel::factory()->create();
        $this->subscribe($user);

        $this->actingAs($user)->get('/perfil?tab=mail')
            ->assertInertia(fn (Assert $page) => $page->where('mailPreferences.pushDevices', []));
    }

    public function test_a_device_of_the_account_is_removed(): void
    {
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $phone = $this->subscribe($user);
        $laptop = $this->subscribe($user, 'https://web.push.apple.com/laptop');

        $this->actingAs($user)
            ->deleteJson('/avisos/push/dispositivos/'.$phone->id)
            ->assertNoContent();

        $this->assertSame([$laptop->id], WebPushSubscriptionModel::query()->pluck('id')->all());
    }

    public function test_removing_this_device_forgets_it_in_the_session_too(): void
    {
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $phone = $this->subscribe($user);

        $this->actingAs($user)
            ->withSession([WebPushSubscriptionServiceInterface::SESSION_KEY => $phone->id])
            ->deleteJson('/avisos/push/dispositivos/'.$phone->id)
            ->assertNoContent()
            ->assertSessionMissing(WebPushSubscriptionServiceInterface::SESSION_KEY);
    }

    public function test_a_device_of_someone_else_answers_exactly_like_an_unknown_one(): void
    {
        config(['app.debug' => false]);
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $someoneElses = $this->subscribe(UserModel::factory()->create());

        $theirs = $this->actingAs($user)->deleteJson('/avisos/push/dispositivos/'.$someoneElses->id)->assertNotFound();
        $unknown = $this->actingAs($user)->deleteJson('/avisos/push/dispositivos/019a0000-0000-7000-8000-000000000000')->assertNotFound();

        $this->assertSame($unknown->getContent(), $theirs->getContent());
        $this->assertNotNull(WebPushSubscriptionModel::query()->find($someoneElses->id));
    }

    public function test_a_test_notice_goes_to_this_device_only(): void
    {
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $phone = $this->subscribe($user);
        $this->subscribe($user, 'https://web.push.apple.com/laptop');
        $this->expectSending(WebPushDeliveryOutcome::Delivered, static fn (WebPushSubscriptionDTO $subscription, string $payload): bool => $subscription->id === $phone->id
            && json_decode($payload, true) === ['test' => true]);

        $this->actingAs($user)
            ->withSession([WebPushSubscriptionServiceInterface::SESSION_KEY => $phone->id])
            ->postJson('/avisos/push/prueba')
            ->assertNoContent();

        $this->assertNotNull(WebPushSubscriptionModel::query()->findOrFail($phone->id)->last_used_at);
    }

    public function test_a_test_notice_needs_this_device_subscribed(): void
    {
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $someoneElses = $this->subscribe(UserModel::factory()->create());
        $this->expectNoSending();

        $this->actingAs($user)->postJson('/avisos/push/prueba')->assertNotFound();
        $this->actingAs($user)
            ->withSession([WebPushSubscriptionServiceInterface::SESSION_KEY => $someoneElses->id])
            ->postJson('/avisos/push/prueba')
            ->assertNotFound();
    }

    public function test_a_device_the_push_service_dropped_is_forgotten_and_said_so(): void
    {
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $phone = $this->subscribe($user);
        $this->expectSending(WebPushDeliveryOutcome::Gone);

        $this->actingAs($user)
            ->withSession([WebPushSubscriptionServiceInterface::SESSION_KEY => $phone->id])
            ->postJson('/avisos/push/prueba')
            ->assertStatus(410)
            ->assertJson(['error' => 'gone'])
            ->assertSessionMissing(WebPushSubscriptionServiceInterface::SESSION_KEY);

        $this->assertNull(WebPushSubscriptionModel::query()->find($phone->id));
    }

    /**
     * @return iterable<string, array{WebPushDeliveryOutcome}>
     */
    public static function unavailableOutcomes(): iterable
    {
        yield 'retry later' => [WebPushDeliveryOutcome::RetryLater];
        yield 'failed' => [WebPushDeliveryOutcome::Failed];
    }

    #[DataProvider('unavailableOutcomes')]
    public function test_a_test_notice_that_could_not_be_sent_keeps_the_device(WebPushDeliveryOutcome $outcome): void
    {
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $phone = $this->subscribe($user);
        $this->expectSending($outcome);

        $this->actingAs($user)
            ->withSession([WebPushSubscriptionServiceInterface::SESSION_KEY => $phone->id])
            ->postJson('/avisos/push/prueba')
            ->assertStatus(503)
            ->assertJson(['error' => 'unavailable']);

        $this->assertNotNull(WebPushSubscriptionModel::query()->find($phone->id));
    }

    public function test_test_notices_are_limited_per_account(): void
    {
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $phone = $this->subscribe($user);
        $this->mock(WebPushSenderInterface::class, static function (MockInterface $sender): void {
            $sender->shouldReceive('send')->times(3)->andReturn(WebPushDeliveryOutcome::Delivered);
        });

        foreach ([204, 204, 204, 429] as $status) {
            $this->actingAs($user)
                ->withSession([WebPushSubscriptionServiceInterface::SESSION_KEY => $phone->id])
                ->postJson('/avisos/push/prueba')
                ->assertStatus($status);
        }
    }

    public function test_nobody_removes_a_device_or_sends_a_test_while_impersonating(): void
    {
        $this->enableWebPush();
        $admin = UserModel::factory()->admin()->create();
        $target = UserModel::factory()->create();
        $device = $this->subscribe($target);
        $this->expectNoSending();
        $this->actingAs($admin);
        $this->app->make(ImpersonationServiceInterface::class)->start($admin, $target, ImpersonationMode::Simulate);

        $this->deleteJson('/avisos/push/dispositivos/'.$device->id)
            ->assertForbidden()
            ->assertJson(['error' => 'impersonating']);
        $this->withSession([WebPushSubscriptionServiceInterface::SESSION_KEY => $device->id])
            ->postJson('/avisos/push/prueba')
            ->assertForbidden()
            ->assertJson(['error' => 'impersonating']);
    }

    public function test_without_push_the_routes_do_not_exist(): void
    {
        $user = UserModel::factory()->create();
        $device = $this->subscribe($user);

        $this->actingAs($user)->deleteJson('/avisos/push/dispositivos/'.$device->id)->assertNotFound();
        $this->actingAs($user)->postJson('/avisos/push/prueba')->assertNotFound();
        $this->assertNotNull(WebPushSubscriptionModel::query()->find($device->id));
    }

    public function test_a_guest_cannot_remove_devices_or_send_tests(): void
    {
        $this->enableWebPush();
        $device = $this->subscribe(UserModel::factory()->create());

        $this->deleteJson('/avisos/push/dispositivos/'.$device->id)->assertUnauthorized();
        $this->postJson('/avisos/push/prueba')->assertUnauthorized();
    }

    /**
     * @param  (\Closure(WebPushSubscriptionDTO, string): bool)|null  $check
     */
    private function expectSending(WebPushDeliveryOutcome $outcome, ?\Closure $check = null): void
    {
        $sender = Mockery::mock(WebPushSenderInterface::class);
        $expectation = $sender->shouldReceive('send')->once();
        if ($check instanceof \Closure) {
            $expectation->withArgs($check);
        }
        $expectation->andReturn($outcome);
        $this->app->instance(WebPushSenderInterface::class, $sender);
    }

    private function expectNoSending(): void
    {
        $sender = Mockery::mock(WebPushSenderInterface::class);
        $sender->shouldNotReceive('send');
        $this->app->instance(WebPushSenderInterface::class, $sender);
    }
}
