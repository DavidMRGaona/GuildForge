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

    public function test_the_source_type_must_be_kebab_case(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SurveySubjectDTO('Example Thing', 'subject-1', 'Example thing', null, null);
    }

    public function test_the_subject_id_cannot_be_blank(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SurveySubjectDTO('example-thing', ' ', 'Example thing', null, null);
    }

    public function test_it_cannot_end_before_it_starts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SurveySubjectDTO(
            'example-thing',
            'subject-1',
            'Example thing',
            new DateTimeImmutable('2026-11-01 22:00:00'),
            new DateTimeImmutable('2026-11-01 18:00:00'),
        );
    }

    public function test_dates_are_optional(): void
    {
        $subject = new SurveySubjectDTO('example-thing', 'subject-1', 'Example thing', null, null);

        $this->assertNull($subject->startsAt);
        $this->assertNull($subject->endsAt);
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
