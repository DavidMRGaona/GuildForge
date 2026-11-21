<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Activity;

use App\Domain\Activity\ActivityIdentifierRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ActivityIdentifierRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function stableIds(): iterable
    {
        yield 'uuid' => ['0195f3a2-7c1e-7d4b-9a8e-2f6c1b3d4e5f'];
        yield 'number' => ['42'];
        yield 'slug' => ['warhammer-40k'];
        yield 'with dots and colons' => ['season:2026.spring'];
    }

    #[DataProvider('stableIds')]
    public function test_a_stable_id_is_accepted(string $id): void
    {
        $this->assertTrue(ActivityIdentifierRule::isStableId($id));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unstableIds(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['  '];
        yield 'spaces' => ['a b'];
        yield 'slash' => ['a/b'];
        yield 'leading dash' => ['-a'];
        yield 'trailing newline' => ["42\n"];
        yield 'too long' => [str_repeat('a', ActivityIdentifierRule::MAX_ID_LENGTH + 1)];
    }

    #[DataProvider('unstableIds')]
    public function test_an_unstable_id_is_refused(string $id): void
    {
        $this->assertFalse(ActivityIdentifierRule::isStableId($id));
    }

    public function test_attribute_keys_are_snake_case(): void
    {
        $this->assertTrue(ActivityIdentifierRule::isAttributeKey('game_system'));
        $this->assertTrue(ActivityIdentifierRule::isAttributeKey('profile'));
        $this->assertFalse(ActivityIdentifierRule::isAttributeKey('game-system'));
        $this->assertFalse(ActivityIdentifierRule::isAttributeKey('GameSystem'));
        $this->assertFalse(ActivityIdentifierRule::isAttributeKey('1system'));
        $this->assertFalse(ActivityIdentifierRule::isAttributeKey("system\n"));
        $this->assertFalse(ActivityIdentifierRule::isAttributeKey(str_repeat('a', ActivityIdentifierRule::MAX_ATTRIBUTE_KEY_LENGTH + 1)));
    }

    public function test_a_user_id_is_a_uuid_kept_in_lower_case(): void
    {
        $this->assertSame(
            '0195f3a2-7c1e-7d4b-9a8e-2f6c1b3d4e5f',
            ActivityIdentifierRule::userId(' 0195F3A2-7C1E-7D4B-9A8E-2F6C1B3D4E5F '),
        );
        $this->assertNull(ActivityIdentifierRule::userId('42'));
        $this->assertNull(ActivityIdentifierRule::userId("0195f3a2-7c1e-7d4b-9a8e-2f6c1b3d4e5f\n-x"));
    }
}
