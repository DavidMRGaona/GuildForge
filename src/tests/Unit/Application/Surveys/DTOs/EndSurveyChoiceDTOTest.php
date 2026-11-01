<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Surveys\DTOs;

use App\Application\Surveys\DTOs\EndSurveyChoiceDTO;
use App\Application\Surveys\DTOs\EndSurveyOptionDTO;
use App\Application\Surveys\DTOs\EndSurveySelection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EndSurveyChoiceDTOTest extends TestCase
{
    public function test_it_keeps_the_options_in_order(): void
    {
        $default = new EndSurveyOptionDTO('type-default', 'Plantilla del tipo: Valoración');
        $other = new EndSurveyOptionDTO('template:1', 'Torneo de ejemplo', 'Plantillas');
        $none = new EndSurveyOptionDTO('none', 'Ninguna');

        $choice = new EndSurveyChoiceDTO([$default, $other, $none], 'type-default');

        $this->assertSame([$default, $other, $none], $choice->options);
        $this->assertSame('type-default', $choice->selected);
        $this->assertFalse($choice->locked);
        $this->assertNull($choice->editUrl);
    }

    public function test_it_needs_at_least_one_option(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EndSurveyChoiceDTO([], 'type-default');
    }

    public function test_the_selected_value_must_be_one_of_the_options(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EndSurveyChoiceDTO([new EndSurveyOptionDTO('none', 'Ninguna')], 'type-default');
    }

    public function test_the_own_survey_value_must_be_one_of_the_options(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EndSurveyChoiceDTO(
            [new EndSurveyOptionDTO('none', 'Ninguna')],
            'none',
            ownValue: 'own',
            baseTemplates: [new EndSurveyOptionDTO('template:1', 'Valoración')],
        );
    }

    public function test_an_own_survey_needs_templates_to_start_from(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EndSurveyChoiceDTO(
            [new EndSurveyOptionDTO('own', 'Una encuesta propia'), new EndSurveyOptionDTO('none', 'Ninguna')],
            'none',
            ownValue: 'own',
        );
    }

    public function test_a_locked_choice_says_why(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EndSurveyChoiceDTO([new EndSurveyOptionDTO('none', 'Ninguna')], 'none', locked: true, lockedReason: ' ');
    }

    public function test_a_blank_edit_url_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EndSurveyChoiceDTO([new EndSurveyOptionDTO('none', 'Ninguna')], 'none', editUrl: '');
    }

    public function test_an_option_needs_a_value_and_a_label(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EndSurveyOptionDTO('none', ' ');
    }

    public function test_a_selection_needs_a_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EndSurveySelection('');
    }

    public function test_a_selection_keeps_the_template_to_start_from(): void
    {
        $selection = new EndSurveySelection('own', 'template:1');

        $this->assertSame('own', $selection->value);
        $this->assertSame('template:1', $selection->baseTemplate);
    }
}
