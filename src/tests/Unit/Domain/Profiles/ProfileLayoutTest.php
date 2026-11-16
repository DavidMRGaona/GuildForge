<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Profiles;

use App\Domain\Profiles\ValueObjects\ProfileLayout;
use App\Domain\Profiles\ValueObjects\ProfileLayoutEntry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProfileLayoutTest extends TestCase
{
    public function test_it_reads_sections_and_stats_in_order(): void
    {
        $layout = ProfileLayout::fromArray([
            'sections' => [['key' => 'forum.latest-posts', 'enabled' => true], ['key' => 'core.about', 'enabled' => false]],
            'stats' => [['key' => 'forum.posts', 'enabled' => true]],
        ]);

        $this->assertEquals([new ProfileLayoutEntry('forum.latest-posts', true), new ProfileLayoutEntry('core.about', false)], $layout->sections);
        $this->assertEquals([new ProfileLayoutEntry('forum.posts', true)], $layout->stats);
        $this->assertFalse($layout->isEmpty());
    }

    #[DataProvider('unreadable')]
    public function test_anything_unreadable_is_the_default_layout(mixed $data): void
    {
        $layout = ProfileLayout::fromArray($data);

        $this->assertSame([], $layout->sections);
        $this->assertSame([], $layout->stats);
        $this->assertTrue($layout->isEmpty());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function unreadable(): array
    {
        return [
            'null' => [null],
            'string' => ['{"sections":[]}'],
            'number' => [3],
            'empty array' => [[]],
            'lists are not lists' => [['sections' => 'core.about', 'stats' => 7]],
        ];
    }

    public function test_malformed_entries_are_left_out_and_the_rest_kept(): void
    {
        $layout = ProfileLayout::fromArray([
            'sections' => [
                'not an entry',
                ['key' => '', 'enabled' => true],
                ['key' => 7, 'enabled' => true],
                ['key' => 'core.about'],
                ['key' => 'core.about-2', 'enabled' => 'yes'],
                ['key' => 'forum.latest-posts', 'enabled' => false],
            ],
            'stats' => null,
        ]);

        $this->assertEquals([new ProfileLayoutEntry('forum.latest-posts', false)], $layout->sections);
        $this->assertSame([], $layout->stats);
    }

    public function test_a_repeated_key_keeps_its_first_place(): void
    {
        $layout = ProfileLayout::fromArray(['sections' => [
            ['key' => 'core.about', 'enabled' => false],
            ['key' => 'forum.latest-posts', 'enabled' => true],
            ['key' => 'core.about', 'enabled' => true],
        ]]);

        $this->assertEquals([new ProfileLayoutEntry('core.about', false), new ProfileLayoutEntry('forum.latest-posts', true)], $layout->sections);
    }

    public function test_keys_nobody_registers_are_kept(): void
    {
        $layout = ProfileLayout::fromArray(['stats' => [['key' => 'uninstalled.thing', 'enabled' => true]]]);

        $this->assertEquals([new ProfileLayoutEntry('uninstalled.thing', true)], $layout->stats);
    }

    public function test_entries_beyond_the_limit_are_cut(): void
    {
        $entries = array_map(static fn (int $i): array => ['key' => 'example.section-'.$i, 'enabled' => true], range(1, ProfileLayout::MAX_ENTRIES + 5));

        $layout = ProfileLayout::fromArray(['sections' => $entries, 'stats' => $entries]);

        $this->assertCount(ProfileLayout::MAX_ENTRIES, $layout->sections);
        $this->assertCount(ProfileLayout::MAX_ENTRIES, $layout->stats);
    }

    public function test_it_finds_whether_the_owner_turned_a_key_on_or_off(): void
    {
        $layout = ProfileLayout::fromArray([
            'sections' => [['key' => 'core.about', 'enabled' => false]],
            'stats' => [['key' => 'forum.posts', 'enabled' => true]],
        ]);

        $this->assertFalse($layout->sectionEnabled('core.about'));
        $this->assertNull($layout->sectionEnabled('forum.latest-posts'));
        $this->assertTrue($layout->statEnabled('forum.posts'));
        $this->assertNull($layout->statEnabled('core.about'));
    }

    public function test_it_round_trips_through_an_array(): void
    {
        $data = [
            'sections' => [['key' => 'core.about', 'enabled' => true]],
            'stats' => [['key' => 'forum.posts', 'enabled' => false]],
        ];

        $this->assertSame($data, ProfileLayout::fromArray($data)->toArray());
        $this->assertSame(['sections' => [], 'stats' => []], ProfileLayout::empty()->toArray());
    }

    public function test_a_new_choice_replaces_what_is_offered_and_keeps_what_is_not(): void
    {
        $stored = ProfileLayout::fromArray([
            'sections' => [['key' => 'forum.latest-posts', 'enabled' => true], ['key' => 'core.about', 'enabled' => true]],
            'stats' => [['key' => 'forum.posts', 'enabled' => false]],
        ]);

        $layout = $stored->chosen(
            [new ProfileLayoutEntry('core.about', false), new ProfileLayoutEntry('gametables.tables', true)],
            [new ProfileLayoutEntry('gametables.count', true)],
            ['core.about', 'gametables.tables'],
            ['gametables.count'],
        );

        // The forum is not offered now (a disabled module): its choices wait, after the new ones
        $this->assertSame([
            'sections' => [
                ['key' => 'core.about', 'enabled' => false],
                ['key' => 'gametables.tables', 'enabled' => true],
                ['key' => 'forum.latest-posts', 'enabled' => true],
            ],
            'stats' => [
                ['key' => 'gametables.count', 'enabled' => true],
                ['key' => 'forum.posts', 'enabled' => false],
            ],
        ], $layout->toArray());
    }

    public function test_a_new_choice_drops_keys_not_offered_and_repeated_ones(): void
    {
        $layout = ProfileLayout::empty()->chosen(
            [new ProfileLayoutEntry('core.about', true), new ProfileLayoutEntry('forged.section', true), new ProfileLayoutEntry('core.about', false)],
            [],
            ['core.about'],
            [],
        );

        $this->assertSame(['sections' => [['key' => 'core.about', 'enabled' => true]], 'stats' => []], $layout->toArray());
    }

    public function test_a_new_choice_never_grows_past_the_limit(): void
    {
        $stored = ProfileLayout::fromArray([
            'sections' => array_map(static fn (int $i): array => ['key' => 'old.section-'.$i, 'enabled' => true], range(1, ProfileLayout::MAX_ENTRIES)),
            'stats' => [],
        ]);

        $layout = $stored->chosen([new ProfileLayoutEntry('core.about', true)], [], ['core.about'], []);

        $this->assertCount(ProfileLayout::MAX_ENTRIES, $layout->sections);
        $this->assertSame('core.about', $layout->sections[0]->key);
    }
}
