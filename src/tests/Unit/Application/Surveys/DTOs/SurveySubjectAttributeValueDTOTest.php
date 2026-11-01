<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Surveys\DTOs;

use App\Application\Surveys\DTOs\SurveySubjectAttributeValueDTO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SurveySubjectAttributeValueDTOTest extends TestCase
{
    public function test_it_holds_an_id_and_a_label(): void
    {
        $value = new SurveySubjectAttributeValueDTO('system-1', 'Example RPG');

        $this->assertSame('system-1', $value->id);
        $this->assertSame('Example RPG', $value->label);
    }

    public function test_the_id_cannot_be_blank(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SurveySubjectAttributeValueDTO(' ', 'Example RPG');
    }

    public function test_the_label_cannot_be_blank(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SurveySubjectAttributeValueDTO('system-1', '');
    }
}
