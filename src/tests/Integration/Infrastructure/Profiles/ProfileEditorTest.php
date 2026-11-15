<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Profiles;

use App\Application\Profiles\Contracts\DescribesProfileStat;
use App\Application\Profiles\Services\ProfileEditorInterface;
use App\Application\Profiles\Services\ProfileLayoutOptionsInterface;
use App\Application\Profiles\Services\ProfileSectionRegistryInterface;
use App\Application\Profiles\Services\ProfileStatRegistryInterface;
use App\Domain\Profiles\ValueObjects\ProfileLayout;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Closure;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\Support\Profiles\FakeProfileSection;
use Tests\Support\Profiles\FakeProfileStat;
use Tests\TestCase;

/**
 * What the owner's editor offers and starts from (core#743): only sections and stats available now, in
 * the owner's order, and only for the owner.
 */
final class ProfileEditorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $bindings = 0;

    public function test_only_what_can_be_offered_now_is_offered(): void
    {
        $this->section('core.extra', order: 50);
        $this->section('core.unavailable', available: false);
        $this->section('core.broken', label: static fn (): string => throw new RuntimeException('Nope'));
        $this->stat('core.posts', order: 20);
        $this->describedStat('core.tables', order: 30);
        $this->stat('core.hidden', available: false);
        $log = Log::spy();

        $options = app(ProfileLayoutOptionsInterface::class);

        $this->assertSame(['core.about', 'core.extra'], array_map(static fn ($option): string => $option->key, $options->sections()));
        $this->assertSame(['core.posts', 'core.tables'], array_map(static fn ($option): string => $option->key, $options->stats()));
        // The description is optional: a stat written for the contract of core#742 still works without it
        $this->assertSame('', $options->stats()[0]->description);
        $this->assertSame('Mesas que dirige.', $options->stats()[1]->description);
        $log->shouldHaveReceived('warning')->once()->withArgs(static fn (string $message, array $context): bool => $context['key'] === 'core.broken' && $context['exception'] === RuntimeException::class);
    }

    public function test_the_editor_follows_the_owners_order_then_the_default_one(): void
    {
        $this->section('core.extra', order: 50, onByDefault: false);
        $this->section('core.more', order: 60);
        $owner = UserModel::factory()->create(['display_name' => 'Ana Prueba']);
        UserProfileModel::query()->findOrFail($owner->id)->update(['layout' => ProfileLayout::fromArray([
            'sections' => [['key' => 'core.more', 'enabled' => false], ['key' => 'gone.section', 'enabled' => true], ['key' => 'core.about', 'enabled' => true]],
            'stats' => [],
        ])]);

        $editor = app(ProfileEditorInterface::class)->editorFor($owner->id)?->toArray();

        $this->assertNotNull($editor);
        $this->assertSame(
            [['core.more', false], ['core.about', true], ['core.extra', false]],
            array_map(static fn (array $section): array => [$section['key'], $section['enabled']], $editor['sections']),
        );
    }

    public function test_only_the_owner_at_the_current_address_is_the_owner(): void
    {
        $owner = UserModel::factory()->create(['display_name' => 'Ana Prueba']);
        $other = UserModel::factory()->create(['display_name' => 'Berta Prueba']);
        $editor = app(ProfileEditorInterface::class);

        $this->assertSame($owner->id, $editor->ownerId('ana-prueba', $owner));
        $this->assertNull($editor->ownerId('ana-prueba', $other));
        $this->assertNull($editor->ownerId('ana-prueba', null));
        $this->assertNull($editor->ownerId('Ana-Prueba', $owner));

        $owner->delete();
        $this->app->forgetScopedInstances();
        $this->assertNull(app(ProfileEditorInterface::class)->ownerId('ana-prueba', $owner));
    }

    /**
     * @param  (Closure(): string)|null  $label
     */
    private function section(string $key, int $order = 100, bool $available = true, bool $onByDefault = true, ?Closure $label = null): void
    {
        $instance = new class($key, $order, $available, $onByDefault, $label) extends FakeProfileSection
        {
            public function __construct(
                private readonly string $sectionKey,
                private readonly int $position,
                private readonly bool $available,
                private readonly bool $onByDefault,
                private readonly ?Closure $labelOf,
            ) {}

            public function key(): string
            {
                return $this->sectionKey;
            }

            public function label(): string
            {
                return $this->labelOf instanceof Closure ? ($this->labelOf)() : parent::label();
            }

            public function order(): int
            {
                return $this->position;
            }

            public function enabledByDefault(): bool
            {
                return $this->onByDefault;
            }

            public function isAvailable(): bool
            {
                return $this->available;
            }
        };

        $id = 'test-section-'.(++$this->bindings);
        $this->app->bind($id, static fn (): FakeProfileSection => $instance);
        app(ProfileSectionRegistryInterface::class)->register($id);
    }

    private function stat(string $key, int $order = 100, bool $available = true): void
    {
        $instance = new class($key, $order, $available) extends FakeProfileStat
        {
            public function __construct(
                private readonly string $statKey,
                private readonly int $position,
                private readonly bool $available,
            ) {}

            public function key(): string
            {
                return $this->statKey;
            }

            public function order(): int
            {
                return $this->position;
            }

            public function isAvailable(): bool
            {
                return $this->available;
            }
        };

        $id = 'test-stat-'.(++$this->bindings);
        $this->app->bind($id, static fn (): FakeProfileStat => $instance);
        app(ProfileStatRegistryInterface::class)->register($id);
    }

    private function describedStat(string $key, int $order): void
    {
        $instance = new class($key, $order) extends FakeProfileStat implements DescribesProfileStat
        {
            public function __construct(
                private readonly string $statKey,
                private readonly int $position,
            ) {}

            public function key(): string
            {
                return $this->statKey;
            }

            public function order(): int
            {
                return $this->position;
            }

            public function description(): string
            {
                return 'Mesas que dirige.';
            }
        };

        $id = 'test-stat-'.(++$this->bindings);
        $this->app->bind($id, static fn (): FakeProfileStat => $instance);
        app(ProfileStatRegistryInterface::class)->register($id);
    }
}
