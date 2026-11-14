<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Linking\DTOs;

use App\Application\Linking\DTOs\LinkableRecordDTO;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LinkableRecordDTOTest extends TestCase
{
    #[Test]
    public function it_keeps_what_the_source_gives(): void
    {
        $date = new DateTimeImmutable('2026-11-20 18:00:00');

        $record = new LinkableRecordDTO(
            type: 'game-table',
            id: 'abc-123',
            title: 'Example table',
            date: $date,
            panelUrl: 'https://example.test/admin/tables/abc-123/edit',
        );

        $this->assertSame('game-table', $record->type);
        $this->assertSame('abc-123', $record->id);
        $this->assertSame('Example table', $record->title);
        $this->assertSame($date, $record->date);
        $this->assertFalse($record->dateIsDay);
        $this->assertSame('https://example.test/admin/tables/abc-123/edit', $record->panelUrl);
    }

    #[Test]
    public function date_and_panel_url_are_optional(): void
    {
        $record = new LinkableRecordDTO(type: 'event', id: 'abc', title: 'Example event');

        $this->assertNull($record->date);
        $this->assertFalse($record->dateIsDay);
        $this->assertNull($record->panelUrl);
    }

    #[Test]
    public function a_date_can_be_a_calendar_day(): void
    {
        $record = new LinkableRecordDTO(type: 'booking', id: 'abc', title: 'Example booking', date: new DateTimeImmutable('2026-11-20'), dateIsDay: true);

        $this->assertTrue($record->dateIsDay);
    }

    #[Test]
    public function it_refuses_a_calendar_day_without_a_date(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LinkableRecordDTO(type: 'booking', id: 'abc', title: 'Example booking', dateIsDay: true);
    }

    #[Test]
    #[DataProvider('invalidTypes')]
    public function it_refuses_a_type_that_is_not_kebab_case(string $type): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LinkableRecordDTO(type: $type, id: 'abc', title: 'Example');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidTypes(): array
    {
        return [
            'empty' => [''],
            'upper case' => ['Event'],
            'underscore' => ['game_table'],
            'leading digit' => ['1event'],
            'trailing dash' => ['event-'],
            'trailing newline' => ["event\n"],
        ];
    }

    #[Test]
    #[DataProvider('invalidIds')]
    public function it_refuses_an_empty_or_too_long_id(string $id): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LinkableRecordDTO(type: 'event', id: $id, title: 'Example');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIds(): array
    {
        return [
            'empty' => [''],
            'blank' => ['   '],
            'too long' => [str_repeat('a', LinkableRecordDTO::MAX_ID_LENGTH + 1)],
        ];
    }

    #[Test]
    public function it_refuses_a_blank_title(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LinkableRecordDTO(type: 'event', id: 'abc', title: '  ');
    }

    #[Test]
    #[DataProvider('invalidPanelUrls')]
    public function it_refuses_a_panel_url_that_is_not_absolute_http(string $panelUrl): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LinkableRecordDTO(type: 'event', id: 'abc', title: 'Example', panelUrl: $panelUrl);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPanelUrls(): array
    {
        return [
            'relative' => ['/admin/events/abc/edit'],
            'javascript' => ['javascript:alert(1)'],
            'no host' => ['https:///admin'],
            'empty' => [''],
        ];
    }
}
