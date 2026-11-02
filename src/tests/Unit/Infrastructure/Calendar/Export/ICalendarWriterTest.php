<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Calendar\Export;

use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Application\Calendar\Services\ICalendarWriterInterface;
use App\Domain\Calendar\Enums\CalendarSourceColor;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ICalendarWriterTest extends TestCase
{
    private ICalendarWriterInterface $writer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writer = app(ICalendarWriterInterface::class);
    }

    #[Test]
    public function it_writes_a_published_calendar_with_one_event(): void
    {
        $document = $this->writer->write([$this->entry()]);

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $document);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $document);
        $this->assertStringContainsString("\r\nVERSION:2.0\r\n", $document);
        $this->assertStringContainsString("\r\nMETHOD:PUBLISH\r\n", $document);
        $this->assertStringContainsString("\r\nBEGIN:VEVENT\r\n", $document);
        $this->assertStringContainsString("\r\nSUMMARY:Torneo de primavera\r\n", $document);
        $this->assertMatchesRegularExpression('/\r\nPRODID:[^\r\n]+\r\n/', $document);
        $this->assertMatchesRegularExpression('/\r\nDTSTAMP:\d{8}T\d{6}Z\r\n/', $document);
    }

    #[Test]
    public function it_writes_instants_in_utc(): void
    {
        // 10:00 in Madrid in January is 09:00 UTC; in July it is 08:00 UTC.
        $document = $this->writer->write([
            $this->entry(
                start: new DateTimeImmutable('2026-01-15 10:00:00', new DateTimeZone('Europe/Madrid')),
                end: new DateTimeImmutable('2026-07-15 10:00:00', new DateTimeZone('Europe/Madrid')),
            ),
        ]);

        $this->assertStringContainsString("\r\nDTSTART:20260115T090000Z\r\n", $document);
        $this->assertStringContainsString("\r\nDTEND:20260715T080000Z\r\n", $document);
    }

    #[Test]
    public function an_entry_without_end_lasts_one_hour(): void
    {
        $document = $this->writer->write([
            $this->entry(
                start: new DateTimeImmutable('2026-03-01 18:30:00', new DateTimeZone('UTC')),
                end: null,
            ),
        ]);

        $this->assertStringContainsString("\r\nDTEND:20260301T193000Z\r\n", $document);
    }

    #[Test]
    public function the_uid_is_stable_per_source_and_id(): void
    {
        $host = parse_url(url('/'), PHP_URL_HOST);

        $first = $this->writer->write([$this->entry()]);
        $second = $this->writer->write([$this->entry(title: 'Otro título')]);

        $uid = "\r\nUID:event-0198a1b2-0000-7000-8000-000000000001@{$host}\r\n";
        $this->assertStringContainsString($uid, $first);
        $this->assertStringContainsString($uid, $second);
    }

    #[Test]
    public function it_links_the_absolute_page_url(): void
    {
        $document = $this->unfold($this->writer->write([$this->entry()]));

        $this->assertStringContainsString("\r\nURL:".url('/eventos/torneo-de-primavera')."\r\n", $document);
        $this->assertStringContainsString(url('/eventos/torneo-de-primavera'), $this->property($document, 'DESCRIPTION'));
    }

    #[Test]
    public function the_description_is_plain_text(): void
    {
        $document = $this->unfold($this->writer->write([
            $this->entry(description: '<p>Trae tu <strong>ejército</strong> &amp; dados.</p><p>Inscripción abierta.</p>'),
        ]));

        $description = $this->property($document, 'DESCRIPTION');

        $this->assertStringNotContainsString('<', $description);
        $this->assertStringContainsString('Trae tu ejército & dados.', $description);
        $this->assertStringContainsString('Inscripción abierta.', $description);
    }

    #[Test]
    public function it_escapes_text_values(): void
    {
        $document = $this->unfold($this->writer->write([
            $this->entry(title: 'Rol; mesa 1, sala\\B', location: "Local social\nPlanta 2"),
        ]));

        $this->assertStringContainsString("\r\nSUMMARY:Rol\\; mesa 1\\, sala\\\\B\r\n", $document);
        $this->assertStringContainsString("\r\nLOCATION:Local social\\nPlanta 2\r\n", $document);
    }

    #[Test]
    public function it_leaves_out_an_empty_location(): void
    {
        $document = $this->writer->write([$this->entry(location: null)]);

        $this->assertStringNotContainsString("\r\nLOCATION:", $document);
    }

    #[Test]
    public function long_lines_are_folded_without_splitting_characters(): void
    {
        $title = str_repeat('Campaña ñoña de rol épico ', 12);

        $document = $this->writer->write([$this->entry(title: $title)]);

        foreach (explode("\r\n", $document) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), "Line too long: {$line}");
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'), "Line splits a character: {$line}");
        }

        $this->assertStringContainsString('SUMMARY:'.trim($title), $this->unfold($document));
    }

    #[Test]
    public function it_writes_one_event_per_entry(): void
    {
        $document = $this->writer->write([
            $this->entry(),
            $this->entry(id: 'table-1', sourceType: 'game-table', title: 'Partida de rol', url: '/mesas/partida-de-rol'),
        ]);

        $this->assertSame(2, substr_count($document, "\r\nBEGIN:VEVENT\r\n"));
        $this->assertStringContainsString('UID:game-table-table-1@', $document);
    }

    private function entry(
        string $id = '0198a1b2-0000-7000-8000-000000000001',
        string $sourceType = 'event',
        string $title = 'Torneo de primavera',
        ?DateTimeImmutable $start = null,
        ?DateTimeImmutable $end = new DateTimeImmutable('2026-04-18 20:00:00', new DateTimeZone('UTC')),
        string $url = '/eventos/torneo-de-primavera',
        ?string $description = 'Torneo abierto a todo el mundo.',
        ?string $location = 'Local de la asociación',
    ): CalendarEntryDTO {
        return new CalendarEntryDTO(
            id: $id,
            sourceType: $sourceType,
            sourceLabel: 'Evento',
            color: CalendarSourceColor::Primary,
            title: $title,
            start: $start ?? new DateTimeImmutable('2026-04-18 10:00:00', new DateTimeZone('UTC')),
            end: $end,
            url: $url,
            details: [
                'description' => $description,
                'location' => $location,
            ],
        );
    }

    /** Joins folded lines back (RFC 5545 §3.1). */
    private function unfold(string $document): string
    {
        return str_replace("\r\n ", '', $document);
    }

    private function property(string $document, string $name): string
    {
        preg_match('/\r\n'.$name.':([^\r\n]*)\r\n/', $document, $matches);

        return $matches[1] ?? '';
    }
}
