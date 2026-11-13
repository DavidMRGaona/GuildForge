<?php

declare(strict_types=1);

namespace Tests\Feature\Realtime;

use App\Application\Realtime\Services\RealtimeChannelRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Realtime\EnablesRealtime;
use Tests\Support\Realtime\ExampleRoomRealtimeChannel;
use Tests\Support\Realtime\FailingRealtimeChannel;
use Tests\Support\Realtime\UnprefixedRealtimeChannel;
use Tests\TestCase;

/**
 * Who may listen to a private channel: decided on the server, once per subscription, and never
 * granted when the check is missing, fails or is ambiguous.
 */
final class RealtimeChannelAuthorizationTest extends TestCase
{
    use EnablesRealtime;
    use LazilyRefreshDatabase;

    private const string SOCKET = '1234.5678';

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableRealtime();
        ExampleRoomRealtimeChannel::$asked = [];
    }

    public function test_an_account_may_listen_to_its_own_notices(): void
    {
        $user = UserModel::factory()->create();
        $channel = 'private-core.site-notifications.'.$user->id;

        $this->actingAs($user)
            ->postJson(route('realtime.auth'), ['socket_id' => self::SOCKET, 'channel_name' => $channel])
            ->assertOk()
            ->assertExactJson(['auth' => $this->expectedRealtimeAuth(self::SOCKET, $channel)]);
    }

    public function test_an_account_may_not_listen_to_the_notices_of_another(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();

        $this->subscribe($user, 'private-core.site-notifications.'.$other->id)->assertForbidden();
    }

    public function test_a_guest_may_not_listen(): void
    {
        $user = UserModel::factory()->create();

        $this->postJson(route('realtime.auth'), [
            'socket_id' => self::SOCKET,
            'channel_name' => 'private-core.site-notifications.'.$user->id,
        ])->assertUnauthorized();
    }

    public function test_a_channel_nobody_registered_is_refused(): void
    {
        $this->subscribe(UserModel::factory()->create(), 'private-example-module.room.open')->assertForbidden();
    }

    public function test_a_module_channel_answers_with_its_own_check(): void
    {
        $this->registry()->register(ExampleRoomRealtimeChannel::class, 'example-module');
        $user = UserModel::factory()->create();

        $this->subscribe($user, 'private-example-module.room.open')
            ->assertOk()
            ->assertExactJson(['auth' => $this->expectedRealtimeAuth(self::SOCKET, 'private-example-module.room.open')]);
        $this->subscribe($user, 'private-example-module.room.closed')->assertForbidden();

        $this->assertSame([['room' => 'open'], ['room' => 'closed']], ExampleRoomRealtimeChannel::$asked);
    }

    public function test_a_check_that_throws_refuses_and_is_logged(): void
    {
        $log = Log::spy();
        $this->registry()->register(FailingRealtimeChannel::class, 'example-module');

        $this->subscribe(UserModel::factory()->create(), 'private-example-module.broken.42')->assertForbidden();

        $log->shouldHaveReceived('warning')->withArgs(
            static fn (string $message, array $context): bool => str_contains($message, 'real-time channel')
                && ($context['channel'] ?? null) === 'example-module.broken.42',
        )->once();
    }

    public function test_a_module_channel_without_the_module_prefix_is_never_registered(): void
    {
        $log = Log::spy();
        $this->registry()->register(UnprefixedRealtimeChannel::class, 'example-module');
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();

        // The module's check would say yes; only the core's own check answers
        $this->subscribe($user, 'private-core.site-notifications.'.$other->id)->assertForbidden();

        $log->shouldHaveReceived('warning')->withArgs(
            static fn (string $message, array $context): bool => str_contains($message, 'module')
                && ($context['class'] ?? null) === UnprefixedRealtimeChannel::class,
        )->once();
    }

    public function test_a_channel_of_a_module_disabled_since_is_refused(): void
    {
        $this->registry()->register(ExampleRoomRealtimeChannel::class, 'example-module');
        $this->registry()->unregisterModule('example-module');

        $this->subscribe(UserModel::factory()->create(), 'private-example-module.room.open')->assertForbidden();
    }

    public function test_a_class_that_is_not_a_channel_is_left_out(): void
    {
        $this->registry()->register(UserModel::class, 'example-module');

        $this->subscribe(UserModel::factory()->create(), 'private-example-module.room.open')->assertForbidden();
    }

    public function test_only_private_channels_are_signed(): void
    {
        $this->registry()->register(ExampleRoomRealtimeChannel::class, 'example-module');
        $user = UserModel::factory()->create();

        $this->subscribe($user, 'example-module.room.open')->assertForbidden();
        $this->subscribe($user, 'presence-example-module.room.open')->assertForbidden();
        $this->subscribe($user, 'private-encrypted-example-module.room.open')->assertForbidden();
    }

    public function test_a_malformed_request_is_refused(): void
    {
        $this->registry()->register(ExampleRoomRealtimeChannel::class, 'example-module');
        $user = UserModel::factory()->create();

        $this->subscribe($user, 'private-example-module.room.open', 'not-a-socket')->assertForbidden();
        $this->actingAs($user)->postJson(route('realtime.auth'), ['channel_name' => 'private-example-module.room.open'])->assertForbidden();
        $this->actingAs($user)->postJson(route('realtime.auth'), ['socket_id' => self::SOCKET, 'channel_name' => ['x']])->assertForbidden();
    }

    public function test_an_anonymized_account_may_not_listen(): void
    {
        $user = UserModel::factory()->create(['anonymized_at' => now()]);

        $this->subscribe($user, 'private-core.site-notifications.'.$user->id)->assertForbidden();
    }

    public function test_the_endpoint_does_not_exist_without_realtime(): void
    {
        config(['broadcasting.default' => 'log']);
        $user = UserModel::factory()->create();

        $this->subscribe($user, 'private-core.site-notifications.'.$user->id)->assertNotFound();
    }

    /**
     * @return TestResponse<Response>
     */
    private function subscribe(UserModel $user, string $channel, string $socket = self::SOCKET): TestResponse
    {
        return $this->actingAs($user)->postJson(route('realtime.auth'), ['socket_id' => $socket, 'channel_name' => $channel]);
    }

    private function registry(): RealtimeChannelRegistryInterface
    {
        return $this->app->make(RealtimeChannelRegistryInterface::class);
    }
}
