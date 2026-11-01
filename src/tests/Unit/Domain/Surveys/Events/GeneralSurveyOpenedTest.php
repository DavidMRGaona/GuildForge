<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Surveys\Events;

use App\Domain\Surveys\Events\GeneralSurveyOpened;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GeneralSurveyOpenedTest extends TestCase
{
    private const string SURVEY_ID = '0b5c3a1e-8f7d-4c2b-9a6e-1d2f3a4b5c6d';

    public function test_it_keeps_the_announcement_data(): void
    {
        $closesAt = new DateTimeImmutable('2026-11-08T18:00:00Z');

        $event = new GeneralSurveyOpened(
            surveyId: self::SURVEY_ID,
            title: 'Horario del local',
            description: 'Queremos saber qué días os viene mejor.',
            isPoll: false,
            closesAt: $closesAt,
            url: 'https://example.com/encuestas/'.self::SURVEY_ID,
        );

        $this->assertSame(self::SURVEY_ID, $event->surveyId);
        $this->assertSame('Horario del local', $event->title);
        $this->assertSame('Queremos saber qué días os viene mejor.', $event->description);
        $this->assertFalse($event->isPoll);
        $this->assertSame($closesAt, $event->closesAt);
        $this->assertSame('https://example.com/encuestas/'.self::SURVEY_ID, $event->url);
    }

    public function test_description_and_closing_date_may_be_missing(): void
    {
        $event = new GeneralSurveyOpened(self::SURVEY_ID, '¿Qué jugamos?', null, true, null, 'http://localhost/encuestas/x');

        $this->assertNull($event->description);
        $this->assertNull($event->closesAt);
        $this->assertTrue($event->isPoll);
    }

    public function test_it_rejects_a_blank_survey_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GeneralSurveyOpened(' ', 'Horario del local', null, false, null, 'https://example.com/encuestas/x');
    }

    public function test_it_rejects_a_blank_title(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GeneralSurveyOpened(self::SURVEY_ID, '  ', null, false, null, 'https://example.com/encuestas/x');
    }

    #[DataProvider('invalidUrls')]
    public function test_it_rejects_a_url_that_is_not_absolute_http(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GeneralSurveyOpened(self::SURVEY_ID, 'Horario del local', null, false, null, $url);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUrls(): array
    {
        return [
            'blank' => [''],
            'relative' => ['/encuestas/x'],
            'other scheme' => ['javascript:alert(1)'],
            'no host' => ['https:///encuestas/x'],
        ];
    }
}
