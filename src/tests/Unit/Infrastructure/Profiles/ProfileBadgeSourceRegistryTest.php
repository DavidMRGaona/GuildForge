<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Profiles;

use App\Application\Profiles\DTOs\ProfileBadgeSourceRegistrationDTO;
use App\Application\Profiles\Services\ProfileBadgeSourceRegistryInterface;
use App\Infrastructure\Profiles\ProfileBadgeSourceRegistry;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class ProfileBadgeSourceRegistryTest extends TestCase
{
    private ProfileBadgeSourceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ProfileBadgeSourceRegistry(new Repository(new ArrayStore));
    }

    public function test_the_registry_is_a_singleton_that_starts_empty(): void
    {
        $registry = $this->app->make(ProfileBadgeSourceRegistryInterface::class);

        $this->assertSame($registry, $this->app->make(ProfileBadgeSourceRegistryInterface::class));
        $this->assertSame([], $registry->all());
    }

    public function test_sources_are_kept_as_classes_in_the_order_they_came(): void
    {
        $this->registry->registerMany(['App\\Badges\\Second', 'App\\Badges\\First'], 'achievements');
        $this->registry->register('App\\Badges\\Other', 'tournaments');

        $this->assertEquals([
            new ProfileBadgeSourceRegistrationDTO('App\\Badges\\Second', 'achievements'),
            new ProfileBadgeSourceRegistrationDTO('App\\Badges\\First', 'achievements'),
            new ProfileBadgeSourceRegistrationDTO('App\\Badges\\Other', 'tournaments'),
        ], $this->registry->all());
    }

    public function test_a_module_s_sources_are_forgotten_when_it_goes(): void
    {
        $this->registry->register('App\\Badges\\Mine', 'achievements');
        $this->registry->register('App\\Badges\\Theirs', 'tournaments');

        $this->registry->unregisterModule('achievements');

        $this->assertEquals([new ProfileBadgeSourceRegistrationDTO('App\\Badges\\Theirs', 'tournaments')], $this->registry->all());
    }

    public function test_a_class_keeps_its_first_owner_and_a_second_one_is_warned_about(): void
    {
        $log = Log::spy();

        $this->registry->register('App\\Badges\\Mine', 'achievements');
        $this->registry->register('App\\Badges\\Mine', 'achievements');
        $this->registry->register('App\\Badges\\Mine', 'tournaments');

        $this->assertEquals([new ProfileBadgeSourceRegistrationDTO('App\\Badges\\Mine', 'achievements')], $this->registry->all());
        $log->shouldHaveReceived('warning')->once()->withArgs(
            static fn (string $message, array $context): bool => $context['reason'] === 'owner_conflict'
                && $context['class'] === 'App\\Badges\\Mine'
                && $context['module'] === 'tournaments',
        );
    }
}
