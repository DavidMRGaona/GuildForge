<?php

declare(strict_types=1);

namespace Tests\Feature\Mail\Statistics;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailCategoryOptOutDTO;
use App\Application\Mail\Services\MailCategoryOptOutStatisticsInterface;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class MailCategoryOptOutStatisticsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private MailCategoryRegistryInterface $registry;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->registry = $this->app->make(MailCategoryRegistryInterface::class);
    }

    public function test_it_counts_opt_outs_over_active_accounts_per_optional_category(): void
    {
        $this->registerNews();
        $first = UserModel::factory()->create();
        $second = UserModel::factory()->create();
        UserModel::factory()->count(2)->create();
        $this->optOut($first, 'test.news');
        $this->optOut($second, 'test.news');

        $row = $this->row('test.news');

        $this->assertSame(2, $row->optedOut);
        $this->assertSame(4, $row->eligible);
        $this->assertSame(50.0, $row->percentage);
    }

    public function test_it_leaves_out_anonymized_and_deactivated_accounts(): void
    {
        $this->registerNews();
        $active = UserModel::factory()->create();
        $anonymized = UserModel::factory()->create(['anonymized_at' => now()]);
        $deactivated = UserModel::factory()->create();
        $deactivated->delete();
        foreach ([$active, $anonymized, $deactivated] as $user) {
            $this->optOut($user, 'test.news');
        }

        $row = $this->row('test.news');

        $this->assertSame(1, $row->optedOut);
        $this->assertSame(1, $row->eligible);
        $this->assertSame(100.0, $row->percentage);
    }

    public function test_an_enabled_row_is_not_an_opt_out(): void
    {
        $this->registerNews();
        $user = UserModel::factory()->create();
        DB::table('user_mail_preferences')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $user->id,
            'category' => 'test.news', 'enabled' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(0, $this->row('test.news')->optedOut);
    }

    public function test_mandatory_categories_are_not_listed_and_unregistered_keys_are_ignored(): void
    {
        $this->registerNews();
        $user = UserModel::factory()->create();
        $this->optOut($user, 'gone-module.reminders');

        $keys = array_map(
            static fn (MailCategoryOptOutDTO $row): string => $row->key,
            $this->service()->report()->categories,
        );

        $this->assertContains('test.news', $keys);
        $this->assertNotContains('core.account', $keys);
        $this->assertNotContains('gone-module.reminders', $keys);
    }

    public function test_the_percentage_is_zero_when_nobody_can_receive_the_category(): void
    {
        $this->registerNews();

        $row = $this->row('test.news');

        $this->assertSame(0, $row->eligible);
        $this->assertSame(0.0, $row->percentage);
    }

    public function test_a_category_with_audience_counts_only_the_accounts_that_see_it(): void
    {
        $this->registry->register(MailCategoryDTO::optional(
            key: 'test.moderation',
            section: 'Pruebas',
            label: 'Moderación',
            description: 'Avisos de moderación',
            audience: static fn (object $user): bool => $user instanceof UserModel && str_starts_with($user->name, 'Mod'),
        ), 'test');
        $mods = [
            UserModel::factory()->create(['name' => 'Mod uno']),
            UserModel::factory()->create(['name' => 'Mod dos']),
            UserModel::factory()->create(['name' => 'Mod tres']),
        ];
        $other = UserModel::factory()->create(['name' => 'Otra persona']);
        $this->optOut($mods[0], 'test.moderation');
        // Opted out, but no longer sees the category: not counted
        $this->optOut($other, 'test.moderation');

        $row = $this->row('test.moderation');

        $this->assertSame(1, $row->optedOut);
        $this->assertSame(3, $row->eligible);
    }

    public function test_an_audience_that_throws_counts_nobody(): void
    {
        $this->registry->register(MailCategoryDTO::optional(
            key: 'test.broken',
            section: 'Pruebas',
            label: 'Rota',
            description: 'Rota',
            audience: static function (object $user): bool {
                throw new \RuntimeException('boom');
            },
        ), 'test');
        UserModel::factory()->create();

        $this->assertSame(0, $this->row('test.broken')->eligible);
    }

    public function test_it_counts_accounts_paused_right_now(): void
    {
        $timezone = $this->app->make(TimezoneServiceInterface::class);
        $format = static fn (\DateTimeInterface $at): string => $timezone->toStorage($at)->format('Y-m-d H:i:s');
        $indefinite = UserModel::factory()->create();
        $running = UserModel::factory()->create();
        $over = UserModel::factory()->create();
        UserModel::factory()->create();
        $deactivated = UserModel::factory()->create();
        $deactivated->delete();
        $this->pause($indefinite, null);
        $this->pause($running, $format(now()->addDay()));
        $this->pause($over, $format(now()->subMinute()));
        $this->pause($deactivated, null);

        $this->assertSame(2, $this->service()->report()->pausedAccounts);
    }

    private function registerNews(): void
    {
        $this->registry->register(MailCategoryDTO::optional(
            key: 'test.news',
            section: 'Pruebas',
            label: 'Novedades',
            description: 'Novedades de prueba',
        ), 'test');
    }

    private function optOut(UserModel $user, string $category): void
    {
        DB::table('user_mail_preferences')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $user->id,
            'category' => $category, 'enabled' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function pause(UserModel $user, ?string $until): void
    {
        DB::table('user_mail_pauses')->insert([
            'user_id' => $user->id, 'paused_until' => $until, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function row(string $key): MailCategoryOptOutDTO
    {
        foreach ($this->service()->report()->categories as $row) {
            if ($row->key === $key) {
                return $row;
            }
        }

        $this->fail("No row for {$key}");
    }

    private function service(): MailCategoryOptOutStatisticsInterface
    {
        return $this->app->make(MailCategoryOptOutStatisticsInterface::class);
    }
}
