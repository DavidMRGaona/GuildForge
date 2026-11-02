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
}
