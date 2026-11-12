<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\Publications\DTOs\PublicationDTO;
use App\Application\Publications\DTOs\PublicationTopicDTO;
use App\Application\Publications\Enums\PublicationState;
use App\Application\Surveys\DTOs\SurveySubjectAttributeDTO;
use App\Application\Surveys\DTOs\SurveySubjectDTO;
use App\Application\Surveys\Enums\SurveySubjectStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** A final newline must not pass the identifier patterns of the DTOs modules fill in. */
final class DtoPatternsTrailingNewlineTest extends TestCase
{
    public function test_a_publication_type_with_a_trailing_newline_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PublicationDTO("game-tables\n", '1', 'Title', '', 'https://example.test/a', PublicationState::Active);
    }

    public function test_a_publication_topic_key_with_a_trailing_newline_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PublicationTopicDTO("rpg\n", 'Rol');
    }

    public function test_a_survey_source_type_with_a_trailing_newline_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SurveySubjectDTO("events\n", '1', 'Title', null, null, [], [], null, SurveySubjectStatus::Active);
    }

    public function test_a_survey_attribute_key_with_a_trailing_newline_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SurveySubjectAttributeDTO("game_system\n", 'Sistema', 'D&D');
    }
}
