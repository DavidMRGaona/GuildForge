<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Activity;

use App\Application\Activity\DTOs\ActivityAttributeDTO;
use App\Application\Activity\DTOs\ActivityAttributeValueDTO;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Domain\Activity\ActivityRole;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ActivityFactDTOTest extends TestCase
{
    private const string USER = '0195f3a2-7c1e-7d4b-9a8e-2f6c1b3d4e5f';

    public function test_a_fact_keeps_who_did_what_when(): void
    {
        $at = new DateTimeImmutable('2026-11-20 18:00:00');
        $system = new ActivityAttributeValueDTO('0195f3a2-0000-7000-8000-000000000001', 'Pathfinder');

        $fact = new ActivityFactDTO(strtoupper(self::USER), ActivityRole::Player, $at, ['game_system' => $system], 2);

        $this->assertSame(self::USER, $fact->userId);
        $this->assertSame(ActivityRole::Player, $fact->role);
        $this->assertSame($at, $fact->occurredAt);
        $this->assertSame(['game_system' => $system], $fact->attributes);
        $this->assertSame(2, $fact->placement);
    }

    public function test_attributes_and_placement_are_optional(): void
    {
        $fact = new ActivityFactDTO(self::USER, ActivityRole::Author, new DateTimeImmutable);

        $this->assertSame([], $fact->attributes);
        $this->assertNull($fact->placement);
    }

    public function test_a_user_id_that_is_not_an_account_id_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ActivityFactDTO('42', ActivityRole::Player, new DateTimeImmutable);
    }

    public function test_a_placement_below_one_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ActivityFactDTO(self::USER, ActivityRole::Player, new DateTimeImmutable, [], 0);
    }

    public function test_an_attribute_with_an_invalid_key_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ActivityFactDTO(self::USER, ActivityRole::Player, new DateTimeImmutable, [
            'game-system' => new ActivityAttributeValueDTO('a', 'A'),
        ]);
    }

    public function test_an_attribute_whose_value_is_not_a_value_dto_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore argument.type (what a module may still pass) */
        new ActivityFactDTO(self::USER, ActivityRole::Player, new DateTimeImmutable, ['game_system' => 'pathfinder']);
    }

    public function test_too_many_attributes_are_refused(): void
    {
        $attributes = [];
        for ($i = 0; $i <= ActivityFactDTO::MAX_ATTRIBUTES; $i++) {
            $attributes['key_'.$i] = new ActivityAttributeValueDTO((string) $i, 'Value '.$i);
        }

        $this->expectException(InvalidArgumentException::class);

        new ActivityFactDTO(self::USER, ActivityRole::Player, new DateTimeImmutable, $attributes);
    }

    public function test_an_attribute_value_needs_a_stable_id_and_a_label(): void
    {
        $value = new ActivityAttributeValueDTO('warhammer-40k', 'Warhammer 40K');
        $this->assertSame('warhammer-40k', $value->id);
        $this->assertSame('Warhammer 40K', $value->label);

        foreach ([['', 'Label'], ['a b', 'Label'], ['id', '  ']] as [$id, $label]) {
            try {
                new ActivityAttributeValueDTO($id, $label);
                $this->fail("'{$id}' / '{$label}' should be refused");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_an_attribute_needs_a_snake_case_key_and_a_label(): void
    {
        $attribute = new ActivityAttributeDTO('game_system', 'Sistema de juego');
        $this->assertSame('game_system', $attribute->key);
        $this->assertSame('Sistema de juego', $attribute->label);

        foreach ([['game-system', 'Label'], ['game_system', '']] as [$key, $label]) {
            try {
                new ActivityAttributeDTO($key, $label);
                $this->fail("'{$key}' / '{$label}' should be refused");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
