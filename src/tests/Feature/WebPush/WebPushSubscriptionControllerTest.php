<?php

declare(strict_types=1);

namespace Tests\Feature\WebPush;

use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Application\WebPush\Services\WebPushSubscriptionServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\WebPushSubscriptionModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\WebPush\EnablesWebPush;
use Tests\TestCase;

final class WebPushSubscriptionControllerTest extends TestCase
{
    use EnablesWebPush;
    use LazilyRefreshDatabase;

    private const string URL = '/avisos/push/suscripcion';

    public function test_a_signed_in_account_subscribes_this_browser(): void
    {
        $this->enableWebPush();
        $user = UserModel::factory()->create();

        $response = $this->actingAs($user)
            ->withHeader('User-Agent', self::CHROME_ON_ANDROID)
            ->postJson(self::URL, $this->payload());

        $response->assertCreated()->assertJsonStructure(['id']);
        $subscription = WebPushSubscriptionModel::query()->sole();
        $this->assertSame($user->id, $subscription->user_id);
        $this->assertSame(self::ENDPOINT, $subscription->endpoint);
        $this->assertSame(['chrome', 'android'], [$subscription->browser, $subscription->platform]);
        $this->assertSame(self::BROWSER_P256DH, $subscription->p256dh);
        $response->assertSessionHas(WebPushSubscriptionServiceInterface::SESSION_KEY, $subscription->id);
    }

    public function test_the_browser_keys_are_encrypted_at_rest(): void
    {
        $this->enableWebPush();

        $this->actingAs(UserModel::factory()->create())->postJson(self::URL, $this->payload())->assertCreated();

        $row = DB::table('web_push_subscriptions')->sole();
        $this->assertNotSame(self::BROWSER_P256DH, $row->p256dh);
        $this->assertNotSame(self::BROWSER_AUTH, $row->auth);
        $this->assertStringNotContainsString(self::BROWSER_AUTH, (string) $row->auth);
    }

    public function test_subscribing_again_from_the_same_browser_keeps_one_row_and_follows_the_account(): void
    {
        $this->enableWebPush();
        $first = UserModel::factory()->create();
        $second = UserModel::factory()->create();

        $this->actingAs($first)->postJson(self::URL, $this->payload())->assertCreated();
        $this->actingAs($first)->postJson(self::URL, $this->payload())->assertCreated();
        // The same browser, signed in with another account on a shared device
        $this->actingAs($second)->postJson(self::URL, $this->payload())->assertCreated();

        $this->assertSame($second->id, WebPushSubscriptionModel::query()->sole()->user_id);
    }

    public function test_only_push_services_are_accepted_as_endpoints(): void
    {
        $this->enableWebPush();
        $user = UserModel::factory()->create();

        foreach (['https://example.test/collect', 'http://fcm.googleapis.com/fcm/send/x', 'https://127.0.0.1/x'] as $endpoint) {
            $this->actingAs($user)->postJson(self::URL, $this->payload(['endpoint' => $endpoint]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('endpoint');
        }

        $this->assertDatabaseCount('web_push_subscriptions', 0);
    }

    public function test_keys_must_be_what_a_browser_sends(): void
    {
        $this->enableWebPush();
        $user = UserModel::factory()->create();

        $this->actingAs($user)->postJson(self::URL, $this->payload(['keys' => ['p256dh' => 'short', 'auth' => self::BROWSER_AUTH]]))
            ->assertJsonValidationErrors('keys.p256dh');
        $this->actingAs($user)->postJson(self::URL, $this->payload(['keys' => ['p256dh' => self::BROWSER_P256DH, 'auth' => 'a+b/c==']]))
            ->assertJsonValidationErrors('keys.auth');
    }

    public function test_an_account_removes_this_browser_and_only_its_own(): void
    {
        $this->enableWebPush();
        $owner = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->subscribe($owner);

        $this->actingAs($other)->deleteJson(self::URL, ['endpoint' => self::ENDPOINT])->assertNotFound();
        $this->assertDatabaseCount('web_push_subscriptions', 1);

        $this->actingAs($owner)->deleteJson(self::URL, ['endpoint' => self::ENDPOINT])->assertNoContent();
        $this->assertDatabaseCount('web_push_subscriptions', 0);
    }

    public function test_the_routes_do_not_exist_while_push_is_off(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)->postJson(self::URL, $this->payload())->assertNotFound();
        $this->actingAs($user)->deleteJson(self::URL, ['endpoint' => self::ENDPOINT])->assertNotFound();
    }

    public function test_guests_cannot_subscribe(): void
    {
        $this->enableWebPush();

        $this->postJson(self::URL, $this->payload())->assertUnauthorized();
    }

    public function test_nobody_subscribes_while_impersonating(): void
    {
        $this->enableWebPush();
        $admin = UserModel::factory()->admin()->create();
        $target = UserModel::factory()->create();
        $this->actingAs($admin);
        $this->app->make(ImpersonationServiceInterface::class)->start($admin, $target, ImpersonationMode::Simulate);

        $this->postJson(self::URL, $this->payload())
            ->assertForbidden()
            ->assertJson(['error' => 'impersonating']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'endpoint' => self::ENDPOINT,
            'keys' => ['p256dh' => self::BROWSER_P256DH, 'auth' => self::BROWSER_AUTH],
            ...$overrides,
        ];
    }
}
