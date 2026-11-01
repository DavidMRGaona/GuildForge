<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Surveys\DTOs;

use App\Application\Surveys\DTOs\SurveySubjectAttributeDTO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SurveySubjectAttributeDTOTest extends TestCase
{
    public function test_the_value_id_is_optional(): void
    {
        $attribute = new SurveySubjectAttributeDTO('game_system', 'Game system', 'Example RPG');

        $this->assertNull($attribute->valueId);
    }

    public function test_it_carries_the_stable_id_of_its_value(): void
    {
        $attribute = new SurveySubjectAttributeDTO('game_system', 'Game system', 'Example RPG', 'system-1');

        $this->assertSame('Example RPG', $attribute->value);
        $this->assertSame('system-1', $attribute->valueId);
    }

    public function test_a_value_id_cannot_be_blank(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SurveySubjectAttributeDTO('game_system', 'Game system', 'Example RPG', '  ');
    }

    public function test_a_value_id_needs_a_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SurveySubjectAttributeDTO('game_system', 'Game system', null, 'system-1');
    }
}
