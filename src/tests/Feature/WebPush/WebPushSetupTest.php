<?php

declare(strict_types=1);

namespace Tests\Feature\WebPush;

use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Facade;
use Inertia\Testing\AssertableInertia;
use Tests\Support\WebPush\EnablesWebPush;
use Tests\TestCase;

final class WebPushSetupTest extends TestCase
{
    use EnablesWebPush;
    use LazilyRefreshDatabase;

    public function test_the_keys_command_prints_a_new_pair_for_the_environment(): void
    {
        Artisan::call('web-push:keys');
        $output = Artisan::output();

        $this->assertMatchesRegularExpression('/^WEB_PUSH_PUBLIC_KEY=[A-Za-z0-9_-]{87}$/m', $output);
        $this->assertMatchesRegularExpression('/^WEB_PUSH_PRIVATE_KEY=[A-Za-z0-9_-]{43}$/m', $output);
        $this->assertMatchesRegularExpression('/^WEB_PUSH_SUBJECT=/m', $output);
    }

    public function test_the_keys_command_never_prints_the_same_pair_twice(): void
    {
        Artisan::call('web-push:keys');
        $first = Artisan::output();
        Artisan::call('web-push:keys');

        $this->assertNotSame($first, Artisan::output());
    }

    public function test_the_page_gets_the_public_key_of_a_signed_in_account_while_push_is_on(): void
    {
        $this->enableWebPush();

        $this->actingAs(UserModel::factory()->create())->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('webPush', ['publicKey' => self::VAPID_PUBLIC_KEY]));
    }

    public function test_the_page_gets_nothing_while_push_is_off_for_a_guest_or_while_impersonating(): void
    {
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('webPush', null));

        $this->enableWebPush();
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('webPush', null));

        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);
        $this->app->make(ImpersonationServiceInterface::class)->start($admin, UserModel::factory()->create(), ImpersonationMode::ReadOnly);
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('webPush', null));
    }

    public function test_the_prune_runs_every_day(): void
    {
        $this->app->forgetInstance(Schedule::class);
        Facade::clearResolvedInstance(Schedule::class);
        require base_path('routes/console.php');

        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(static fn (Event $event): bool => str_contains((string) $event->command, 'web-push:prune'));

        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', $events->first()?->expression);
    }
}
