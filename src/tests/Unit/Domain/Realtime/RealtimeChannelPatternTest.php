<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Realtime;

use App\Domain\Realtime\ValueObjects\RealtimeChannelPattern;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RealtimeChannelPatternTest extends TestCase
{
    public function test_it_reads_the_prefix_and_the_parameters(): void
    {
        $pattern = RealtimeChannelPattern::tryFrom('forum.discussion.{discussion}');

        $this->assertNotNull($pattern);
        $this->assertSame('forum', $pattern->prefix());
        $this->assertSame(['discussion'], $pattern->parameters());
        $this->assertSame('forum.discussion.{discussion}', $pattern->value);
    }

    public function test_it_matches_a_channel_and_gives_its_values_by_name(): void
    {
        $pattern = RealtimeChannelPattern::tryFrom('game-tables.table.{table}.seat.{seat}');

        $this->assertNotNull($pattern);
        $this->assertSame(
            ['table' => '0198f1c2-7a3b-7c4d-9e5f-a1b2c3d4e5f6', 'seat' => '3'],
            $pattern->match('game-tables.table.0198f1c2-7a3b-7c4d-9e5f-a1b2c3d4e5f6.seat.3'),
        );
    }

    #[DataProvider('channelsThatDoNotMatch')]
    public function test_it_does_not_match_other_channels(string $channel): void
    {
        $pattern = RealtimeChannelPattern::tryFrom('forum.discussion.{discussion}');

        $this->assertNotNull($pattern);
        $this->assertNull($pattern->match($channel));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function channelsThatDoNotMatch(): array
    {
        return [
            'another literal' => ['forum.category.42'],
            'one segment more' => ['forum.discussion.42.extra'],
            'one segment less' => ['forum.discussion'],
            'an empty value' => ['forum.discussion.'],
            'a value with a dot' => ['forum.discussion.4.2'],
            'a value with other characters' => ['forum.discussion.4 2'],
            'a value too long' => ['forum.discussion.'.str_repeat('a', 65)],
            'another prefix' => ['forums.discussion.42'],
            'with the private prefix' => ['private-forum.discussion.42'],
        ];
    }

    #[DataProvider('invalidPatterns')]
    public function test_it_rejects_invalid_patterns(string $pattern): void
    {
        $this->assertNull(RealtimeChannelPattern::tryFrom($pattern));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPatterns(): array
    {
        return [
            'empty' => [''],
            'a single segment' => ['forum'],
            'a placeholder first' => ['{module}.discussion'],
            'uppercase' => ['Forum.discussion.{id}'],
            'an empty segment' => ['forum..{id}'],
            'a partial placeholder' => ['forum.discussion-{id}'],
            'a repeated placeholder' => ['forum.{id}.{id}'],
            'the private prefix' => ['private-forum.discussion.{id}'],
            'a wildcard' => ['forum.*'],
            'too long' => ['forum.'.str_repeat('a', 150)],
        ];
    }

    public function test_it_tells_a_concrete_channel_name(): void
    {
        $this->assertTrue(RealtimeChannelPattern::isChannelName('forum.discussion.42'));
        $this->assertTrue(RealtimeChannelPattern::isChannelName('core.site-notifications.0198f1c2-7a3b-7c4d-9e5f-a1b2c3d4e5f6'));
        $this->assertFalse(RealtimeChannelPattern::isChannelName('forum.discussion.{discussion}'));
        $this->assertFalse(RealtimeChannelPattern::isChannelName('private-forum.discussion.42'));
        $this->assertFalse(RealtimeChannelPattern::isChannelName('forum'));
        $this->assertFalse(RealtimeChannelPattern::isChannelName('42.discussion'));
    }
}
