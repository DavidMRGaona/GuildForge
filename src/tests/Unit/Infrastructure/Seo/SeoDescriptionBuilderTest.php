<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Seo;

use App\Application\Seo\DTOs\SeoScheduleDTO;
use App\Infrastructure\Seo\SeoDescriptionBuilder;
use DateTimeImmutable;
use Tests\TestCase;

final class SeoDescriptionBuilderTest extends TestCase
{
    private SeoDescriptionBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('es');
        $this->builder = new SeoDescriptionBuilder('Europe/Madrid');
    }

    public function test_single_day_schedule_prefixes_date_time_and_location(): void
    {
        $schedule = new SeoScheduleDTO(
            start: new DateTimeImmutable('2026-01-15T17:00:00Z'),
            end: new DateTimeImmutable('2026-01-15T21:00:00Z'),
            location: 'La Taberna',
        );

        $this->assertSame(
            '15 de enero de 2026, 18:00 · La Taberna · Traed vuestro libro & ganas',
            $this->builder->build('<p>Traed vuestro libro &amp; ganas</p>', $schedule, 'Fallback'),
        );
    }

    public function test_day_is_decided_in_the_venue_timezone_not_utc(): void
    {
        // 00:30 and 10:00 on 10 July in Madrid, but 9 and 10 July in UTC.
        $schedule = new SeoScheduleDTO(
            start: new DateTimeImmutable('2026-07-09T22:30:00Z'),
            end: new DateTimeImmutable('2026-07-10T08:00:00Z'),
        );

        $this->assertSame('10 de julio de 2026, 00:30 · Texto', $this->builder->build('Texto', $schedule, ''));
    }

    public function test_event_ending_at_midnight_is_a_single_day(): void
    {
        // 20:00 to 00:00 in Madrid (winter, UTC+1)
        $schedule = new SeoScheduleDTO(
            start: new DateTimeImmutable('2026-01-15T19:00:00Z'),
            end: new DateTimeImmutable('2026-01-15T23:00:00Z'),
            location: 'La Taberna',
        );

        $this->assertSame('15 de enero de 2026, 20:00 · La Taberna · Texto', $this->builder->build('Texto', $schedule, ''));
    }

    public function test_schedule_without_end_is_a_single_day(): void
    {
        $schedule = new SeoScheduleDTO(start: new DateTimeImmutable('2026-03-02T09:00:00Z'));

        $this->assertSame('2 de marzo de 2026, 10:00 · Texto', $this->builder->build('Texto', $schedule, ''));
    }

    public function test_multi_day_in_the_same_month(): void
    {
        $schedule = new SeoScheduleDTO(
            start: new DateTimeImmutable('2026-01-15T09:00:00Z'),
            end: new DateTimeImmutable('2026-01-17T13:00:00Z'),
        );

        $this->assertSame('15–17 de enero de 2026 · Texto', $this->builder->build('Texto', $schedule, ''));
    }

    public function test_multi_day_across_months(): void
    {
        $schedule = new SeoScheduleDTO(
            start: new DateTimeImmutable('2026-01-30T09:00:00Z'),
            end: new DateTimeImmutable('2026-02-02T13:00:00Z'),
        );

        $this->assertSame('30 de enero – 2 de febrero de 2026 · Texto', $this->builder->build('Texto', $schedule, ''));
    }

    public function test_multi_day_across_years(): void
    {
        $schedule = new SeoScheduleDTO(
            start: new DateTimeImmutable('2026-12-30T09:00:00Z'),
            end: new DateTimeImmutable('2027-01-02T13:00:00Z'),
        );

        $this->assertSame(
            '30 de diciembre de 2026 – 2 de enero de 2027 · Texto',
            $this->builder->build('Texto', $schedule, ''),
        );
    }

    public function test_empty_text_falls_back_to_the_site_description(): void
    {
        $this->assertSame('Asociación de juegos', $this->builder->build('<p> </p>', null, 'Asociación de juegos'));
        $this->assertSame('Asociación de juegos', $this->builder->build(null, null, 'Asociación de juegos'));
    }

    public function test_schedule_alone_when_there_is_no_text_at_all(): void
    {
        $schedule = new SeoScheduleDTO(start: new DateTimeImmutable('2026-03-02T09:00:00Z'), location: 'Local');

        $this->assertSame('2 de marzo de 2026, 10:00 · Local', $this->builder->build(null, $schedule, ''));
    }

    public function test_block_tags_become_spaces(): void
    {
        $this->assertSame('Uno Dos', $this->builder->plainText('<p>Uno</p><p>Dos</p>'));
    }

    public function test_long_text_is_cut_on_a_word_boundary_with_an_ellipsis(): void
    {
        $result = $this->builder->build(str_repeat('palabra ', 40), null, '');

        $this->assertLessThanOrEqual(SeoDescriptionBuilder::MAX_LENGTH, mb_strlen($result));
        $this->assertMatchesRegularExpression('/ palabra…$/u', $result);
    }

    public function test_cut_never_splits_multibyte_characters(): void
    {
        $result = $this->builder->build(str_repeat('señal · ', 40), null, '');

        $this->assertTrue(mb_check_encoding($result, 'UTF-8'));
        $this->assertStringEndsWith('señal…', $result);
    }
}
