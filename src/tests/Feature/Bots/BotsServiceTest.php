<?php

declare(strict_types=1);

namespace Tests\Feature\Bots;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\RoleAuditLoggerInterface;
use App\Application\Bots\Services\BotRegistryInterface;
use App\Application\Bots\Services\BotsInterface;
use App\Application\Profiles\Services\UserProfileServiceInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\WebPush\Services\WebPushSubscriptionServiceInterface;
use App\Domain\Bots\Exceptions\UnknownBotException;
use App\Infrastructure\Authorization\Services\RoleAuditContext;
use App\Infrastructure\Bots\BotAdoptionRule;
use App\Infrastructure\Bots\BotRegistry;
use App\Infrastructure\Bots\EloquentBots;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\Bots\FakeBot;
use Tests\TestCase;

final class BotsServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $siteName = 'Asociación de ejemplo';

    protected function setUp(): void
    {
        parent::setUp();

        $branding = Mockery::mock(SiteBrandingServiceInterface::class);
        $branding->shouldReceive('siteName')->andReturnUsing(fn (): string => $this->siteName);
        $this->app->instance(SiteBrandingServiceInterface::class, $branding);

        $registry = new BotRegistry($this->app);
        $registry->register(FakeBot::class, 'forum');
        $this->app->instance(BotRegistryInterface::class, $registry);
    }

    private function bots(): BotsInterface
    {
        return $this->app->make(BotsInterface::class);
    }

    private function botRows(): int
    {
        return UserModel::query()->withBots()->whereNotNull('bot_key')->count();
    }

    public function test_the_service_is_bound_scoped(): void
    {
        $this->assertInstanceOf(EloquentBots::class, $this->bots());
    }

    public function test_the_first_call_creates_the_account_and_the_second_reuses_it(): void
    {
        $id = $this->bots()->id('forum.publisher');

        $this->assertTrue(Str::isUuid($id));
        $this->assertSame(strtolower($id), $id);
        $row = UserModel::query()->withBots()->findOrFail($id);
        $this->assertSame('forum.publisher', $row->bot_key);
        $this->assertNull($row->display_name);
        $this->assertSame('Publicador del foro', $row->name);
        $this->assertMatchesRegularExpression('/^bot-[0-9a-f-]{36}@bots\.invalid$/', $row->email);
        $this->assertNull($row->email_verified_at);
        $this->assertSame(0, DB::table('user_role')->where('user_id', $id)->count());

        $this->assertSame($id, $this->bots()->id('forum.publisher'));
        $this->assertSame(1, $this->botRows());
    }

    public function test_the_second_read_inside_the_lock_prevents_a_duplicate(): void
    {
        $bots = new class($this->app) extends EloquentBots
        {
            public ?string $racedId = null;

            public function __construct(Application $app)
            {
                parent::__construct(
                    $app->make(BotRegistryInterface::class),
                    $app->make(SiteBrandingServiceInterface::class),
                    $app->make(BotAdoptionRule::class),
                    $app->make(UserProfileServiceInterface::class),
                    $app->make(ActivityPublisherInterface::class),
                    $app->make(WebPushSubscriptionServiceInterface::class),
                    $app->make(RoleAuditLoggerInterface::class),
                    $app->make(RoleAuditContext::class),
                    $app->make(AuthorizationServiceInterface::class),
                );
            }

            /** Simulates another process creating the bot between the first read and the lock */
            protected function afterFirstLookup(string $key): void
            {
                $this->racedId = (string) Str::uuid();
                $row = new UserModel;
                $row->forceFill([
                    'id' => $this->racedId,
                    'name' => 'Racer',
                    'email' => "bot-{$this->racedId}@bots.invalid",
                    'password' => 'x',
                    'bot_key' => $key,
                ])->save();
            }
        };

        $id = $bots->id('forum.publisher');

        $this->assertSame($bots->racedId, $id);
        $this->assertSame(1, $this->botRows());
    }

    public function test_an_unregistered_key_throws(): void
    {
        $this->expectException(UnknownBotException::class);

        $this->bots()->id('forum.unknown');
    }

    public function test_a_module_disabled_after_creation_cannot_get_the_id_but_the_account_is_still_known(): void
    {
        $id = $this->bots()->id('forum.publisher');

        $this->app->instance(BotRegistryInterface::class, new BotRegistry($this->app));
        $this->app->forgetInstance(BotsInterface::class);

        $this->assertTrue($this->bots()->isBot($id));
        $this->assertArrayHasKey($id, $this->bots()->describe([$id]));
        $this->expectException(UnknownBotException::class);
        $this->bots()->id('forum.publisher');
    }

    public function test_describe_follows_the_site_name_without_writing(): void
    {
        $id = $this->bots()->id('forum.publisher');

        $dto = $this->bots()->describe([$id])[$id];
        $this->assertSame($id, $dto->id);
        $this->assertSame('forum.publisher', $dto->key);
        $this->assertSame('forum', $dto->module);
        $this->assertSame('Asociación de ejemplo', $dto->name);
        $this->assertNull($dto->avatarPublicId);

        $this->siteName = 'Otro nombre';
        $this->assertSame('Otro nombre', $this->bots()->describe([$id])[$id]->name);
        $this->assertNull(UserModel::query()->withBots()->findOrFail($id)->display_name);
    }

    public function test_describe_uses_the_display_name_when_set(): void
    {
        $id = $this->bots()->id('forum.publisher');
        UserModel::query()->withBots()->whereKey($id)->update(['display_name' => 'Heraldo']);

        $this->assertSame('Heraldo', $this->bots()->describe([$id])[$id]->name);
    }

    public function test_describe_ignores_people_unknown_and_malformed_ids_and_accepts_upper_case(): void
    {
        $id = $this->bots()->id('forum.publisher');
        $person = UserModel::factory()->create();

        $result = $this->bots()->describe([$person->id, (string) Str::uuid(), 'not-a-uuid', strtoupper($id)]);

        $this->assertSame([$id], array_keys($result));
    }

    public function test_is_bot(): void
    {
        $id = $this->bots()->id('forum.publisher');
        $person = UserModel::factory()->create();

        $this->assertFalse($this->bots()->isBot(null));
        $this->assertFalse($this->bots()->isBot($person->id));
        $this->assertFalse($this->bots()->isBot('nope'));
        $this->assertTrue($this->bots()->isBot($id));
        $this->assertTrue($this->bots()->isBot(strtoupper($id)));
    }
}
