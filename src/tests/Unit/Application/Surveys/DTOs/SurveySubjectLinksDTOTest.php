<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Surveys\DTOs;

use App\Application\Surveys\DTOs\SurveyLinkDTO;
use App\Application\Surveys\DTOs\SurveySubjectLinksDTO;
use App\Application\Surveys\Enums\SurveyLinkAction;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SurveySubjectLinksDTOTest extends TestCase
{
    public function test_none_has_no_links(): void
    {
        $links = SurveySubjectLinksDTO::none();

        $this->assertNull($links->manageUrl);
        $this->assertSame([], $links->surveys);
        $this->assertTrue($links->isEmpty());
    }

    public function test_a_manage_url_alone_is_not_empty(): void
    {
        $links = new SurveySubjectLinksDTO(manageUrl: 'https://example.com/encuestas/sujeto/example-thing/1');

        $this->assertFalse($links->isEmpty());
    }

    public function test_it_keeps_the_survey_links_in_order(): void
    {
        $first = new SurveyLinkDTO('Valoración', 'https://example.com/encuestas/1', SurveyLinkAction::Respond);
        $second = new SurveyLinkDTO('Fecha de la próxima', 'https://example.com/encuestas/2', SurveyLinkAction::ViewResults);

        $links = new SurveySubjectLinksDTO(surveys: [$first, $second]);

        $this->assertSame([$first, $second], $links->surveys);
        $this->assertFalse($links->isEmpty());
    }

    public function test_a_blank_manage_url_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SurveySubjectLinksDTO(manageUrl: ' ');
    }

    public function test_a_survey_link_needs_a_title(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SurveyLinkDTO(' ', 'https://example.com/encuestas/1', SurveyLinkAction::Respond);
    }

    public function test_a_survey_link_needs_a_url(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SurveyLinkDTO('Valoración', '', SurveyLinkAction::Respond);
    }
}
