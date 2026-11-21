<?php

declare(strict_types=1);

namespace Tests\Feature\WebPush;

use App\Application\DTOs\AnonymizeUserDTO;
use App\Application\Services\UserServiceInterface;
use App\Application\WebPush\Services\WebPushSubscriptionServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\WebPushSubscriptionModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Support\WebPush\EnablesWebPush;
use Tests\TestCase;

/**
 * Subscriptions go when the device signs out, when the account stops being somebody, and when
 * nothing has used them for 90 days.
 */
final class WebPushCleanupTest extends TestCase
{
    use EnablesWebPush;
    use LazilyRefreshDatabase;

    public function test_signing_out_forgets_the_subscription_of_this_browser_only(): void
    {
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $thisBrowser = $this->subscribe($user);
        $otherBrowser = $this->subscribe($user, 'https://web.push.apple.com/other-device');

        $this->actingAs($user)
            ->withSession([WebPushSubscriptionServiceInterface::SESSION_KEY => $thisBrowser->id])
            ->post('/cerrar-sesion');

        $this->assertNull(WebPushSubscriptionModel::query()->find($thisBrowser->id));
        $this->assertNotNull(WebPushSubscriptionModel::query()->find($otherBrowser->id));
    }

    public function test_signing_out_never_forgets_a_subscription_of_another_account(): void
    {
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $someoneElses = $this->subscribe(UserModel::factory()->create());

        $this->actingAs($user)
            ->withSession([WebPushSubscriptionServiceInterface::SESSION_KEY => $someoneElses->id])
            ->post('/cerrar-sesion');

        $this->assertNotNull(WebPushSubscriptionModel::query()->find($someoneElses->id));
    }

    public function test_signing_out_this_device_only_forgets_its_subscription_too(): void
    {
        // EnsureSessionPassedTwoFactor signs a session out of this device alone (core#779)
        $user = UserModel::factory()->create();
        $thisBrowser = $this->subscribe($user);
        $this->actingAs($user);
        session()->put(WebPushSubscriptionServiceInterface::SESSION_KEY, $thisBrowser->id);

        Auth::guard('web')->logoutCurrentDevice();

        $this->assertNull(WebPushSubscriptionModel::query()->find($thisBrowser->id));
    }

    public function test_a_deactivated_account_loses_its_subscriptions(): void
    {
        $user = UserModel::factory()->create();
        $this->subscribe($user);
        $other = $this->subscribe(UserModel::factory()->create(), 'https://web.push.apple.com/other');

        $user->delete();

        $this->assertSame([$other->id], WebPushSubscriptionModel::query()->pluck('id')->all());
    }

    public function test_an_anonymized_account_loses_its_subscriptions(): void
    {
        $user = UserModel::factory()->create();
        $this->subscribe($user);

        app(UserServiceInterface::class)->anonymize($user->id);

        $this->assertDatabaseCount('web_push_subscriptions', 0);
    }

    public function test_an_account_anonymized_with_its_content_transferred_loses_its_subscriptions(): void
    {
        $user = UserModel::factory()->create();
        $heir = UserModel::factory()->create();
        $this->subscribe($user);
        $kept = $this->subscribe($heir, 'https://web.push.apple.com/heir');

        app(UserServiceInterface::class)->anonymizeWithContentTransfer(new AnonymizeUserDTO(
            userId: $user->id,
            contentAction: 'transfer',
            transferToUserId: $heir->id,
        ));

        $this->assertSame([$kept->id], WebPushSubscriptionModel::query()->pluck('id')->all());
    }

    public function test_a_deleted_account_takes_its_subscriptions_along(): void
    {
        $user = UserModel::factory()->create();
        $this->subscribe($user);

        $user->forceDelete();

        $this->assertDatabaseCount('web_push_subscriptions', 0);
    }

    public function test_subscriptions_unused_for_90_days_are_pruned(): void
    {
        $user = UserModel::factory()->create();
        $stale = $this->subscribe($user, 'https://web.push.apple.com/stale', ['last_used_at' => now()->subDays(91)]);
        $neverUsed = $this->subscribe($user, 'https://web.push.apple.com/never', ['created_at' => now()->subDays(91), 'last_used_at' => null]);
        $recent = $this->subscribe($user, 'https://web.push.apple.com/recent', ['created_at' => now()->subDays(200), 'last_used_at' => now()->subDays(89)]);
        $fresh = $this->subscribe($user);

        $this->artisanCommand('web-push:prune')->assertSuccessful();

        $left = WebPushSubscriptionModel::query()->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$recent->id, $fresh->id], $left);
        $this->assertNotContains($stale->id, $left);
        $this->assertNotContains($neverUsed->id, $left);
    }
}
