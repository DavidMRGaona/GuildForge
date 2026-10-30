<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Surveys\DTOs;

use App\Application\Surveys\DTOs\SurveySubjectAttributeDTO;
use App\Application\Surveys\DTOs\SurveySubjectDTO;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SurveySubjectDTOTest extends TestCase
{
    public function test_organizer_ids_are_collapsed(): void
    {
        $subject = $this->subject(organizerIds: ['u1', 'u2', 'u1']);

        $this->assertSame(['u1', 'u2'], $subject->organizerIds);
    }

    public function test_attribute_is_found_by_key(): void
    {
        $subject = $this->subject(attributes: [
            new SurveySubjectAttributeDTO('game_system', 'Game system', 'Example RPG'),
            new SurveySubjectAttributeDTO('format', 'Format', 'one_shot'),
        ]);

        $this->assertSame('Example RPG', $subject->attribute('game_system')?->value);
        $this->assertNull($subject->attribute('missing'));
    }

    public function test_attribute_keys_must_be_snake_case(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SurveySubjectAttributeDTO('Game System', 'Game system', 'Example RPG');
    }

    public function test_attribute_keys_must_be_unique(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->subject(attributes: [
            new SurveySubjectAttributeDTO('game_system', 'Game system', 'Example RPG'),
            new SurveySubjectAttributeDTO('game_system', 'Game system', 'Other RPG'),
        ]);
    }

    /**
     * @param  list<string>  $organizerIds
     * @param  list<SurveySubjectAttributeDTO>  $attributes
     */
    private function subject(array $organizerIds = [], array $attributes = []): SurveySubjectDTO
    {
        return new SurveySubjectDTO(
            sourceType: 'example-thing',
            subjectId: 'subject-1',
            title: 'Example thing',
            startsAt: new DateTimeImmutable('2026-11-01 18:00:00'),
            endsAt: new DateTimeImmutable('2026-11-01 22:00:00'),
            organizerIds: $organizerIds,
            attributes: $attributes,
        );
    }
}
