<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Search\DTOs;

use App\Application\Search\DTOs\SearchDocumentDTO;
use App\Domain\Search\Enums\SearchAudience;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SearchDocumentDTOTest extends TestCase
{
    public function test_rejects_blank_title_and_url(): void
    {
        foreach ([['  ', '/x'], ['Title', ' '], ['', '']] as [$title, $url]) {
            try {
                new SearchDocumentDTO('1', $title, 'Body', $url);
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_defaults(): void
    {
        $dto = new SearchDocumentDTO('1', 'Title', 'Body', '/x');

        $this->assertSame(SearchAudience::Public, $dto->audience);
        $this->assertFalse($dto->finished);
        $this->assertNull($dto->startsAt);
    }

    public function test_rejects_an_id_longer_than_the_stored_key(): void
    {
        // source_id is a string(100): a longer id would fail on PostgreSQL and be cut nowhere
        $this->assertSame(str_repeat('é', 100), (new SearchDocumentDTO(id: str_repeat('é', 100), title: 'Título', body: '', url: '/a'))->id);

        $this->expectException(InvalidArgumentException::class);

        new SearchDocumentDTO(id: str_repeat('a', 101), title: 'Título', body: '', url: '/a');
    }
}
