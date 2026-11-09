<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publications;

use App\Application\Publications\DTOs\PublicationDTO;
use App\Application\Publications\DTOs\PublicationTopicDTO;
use App\Application\Publications\Enums\PublicationState;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicationDTOTest extends TestCase
{
    public function test_it_keeps_what_the_source_gives(): void
    {
        $startsAt = new DateTimeImmutable('2027-01-08T18:00:00Z');
        $endsAt = new DateTimeImmutable('2027-01-08T22:00:00Z');
        $closesAt = new DateTimeImmutable('2027-01-07T18:00:00Z');
        $topic = new PublicationTopicDTO('game-system.0195f3a2', 'Example system');

        $publication = new PublicationDTO(
            type: 'game-table',
            id: 'table-1',
            title: 'Example table',
            summary: 'A short summary',
            url: 'https://example.test/partidas/example-table',
            state: PublicationState::Active,
            startsAt: $startsAt,
            endsAt: $endsAt,
            topics: [$topic],
            isFull: true,
            registrationOpen: true,
            registrationClosesAt: $closesAt,
        );

        $this->assertSame('game-table', $publication->type);
        $this->assertSame('table-1', $publication->id);
        $this->assertSame(PublicationState::Active, $publication->state);
        $this->assertSame($startsAt, $publication->startsAt);
        $this->assertSame($endsAt, $publication->endsAt);
        $this->assertSame([$topic], $publication->topics);
        $this->assertTrue($publication->isFull);
        $this->assertTrue($publication->registrationOpen);
        $this->assertSame($closesAt, $publication->registrationClosesAt);
    }

    public function test_records_without_registrations_are_neither_full_nor_open(): void
    {
        $publication = $this->make();

        $this->assertFalse($publication->isFull);
        $this->assertFalse($publication->registrationOpen);
        $this->assertNull($publication->registrationClosesAt);
        $this->assertSame([], $publication->topics);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidPublications(): array
    {
        return [
            'type not kebab-case' => [['type' => 'Game Table']],
            'empty id' => [['id' => ' ']],
            'id too long' => [['id' => str_repeat('a', PublicationDTO::MAX_ID_LENGTH + 1)]],
            'blank title' => [['title' => '  ']],
            'relative url' => [['url' => '/partidas/example']],
            'other scheme' => [['url' => 'javascript:alert(1)']],
            'ends before it starts' => [['startsAt' => new DateTimeImmutable('2027-01-08T18:00:00Z'), 'endsAt' => new DateTimeImmutable('2027-01-08T17:00:00Z')]],
            'repeated topic' => [['topics' => [new PublicationTopicDTO('system.a', 'A'), new PublicationTopicDTO('system.a', 'B')]]],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPublications')]
    public function test_it_rejects_an_invalid_publication(array $overrides): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->make($overrides);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidTopics(): array
    {
        return [
            'uppercase key' => ['System.A', 'A'],
            'key starting with a dot' => ['.system', 'A'],
            'key too long' => [str_repeat('a', 65), 'A'],
            'blank label' => ['system.a', ' '],
        ];
    }

    #[DataProvider('invalidTopics')]
    public function test_it_rejects_an_invalid_topic(string $key, string $label): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PublicationTopicDTO($key, $label);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function make(array $overrides = []): PublicationDTO
    {
        /** @var array{type: string, id: string, title: string, summary: string, url: string, state: PublicationState, startsAt?: DateTimeImmutable, endsAt?: DateTimeImmutable, topics?: list<PublicationTopicDTO>} $arguments */
        $arguments = array_merge([
            'type' => 'event',
            'id' => 'event-1',
            'title' => 'Example event',
            'summary' => '',
            'url' => 'https://example.test/eventos/example-event',
            'state' => PublicationState::Active,
        ], $overrides);

        return new PublicationDTO(...$arguments);
    }
}
