<?php

declare(strict_types=1);

namespace Tests\Support\Search;

use App\Application\Search\Contracts\SearchSourceInterface;
use App\Application\Search\DTOs\SearchResultDTO;

class FakeSearchSource implements SearchSourceInterface
{
    public function key(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return 'Fake source';
    }

    public function search(string $query, ?object $user, int $limit): array
    {
        return [new SearchResultDTO('Result for '.$query, '/fake', 'Context', [['text' => $query, 'match' => true]])];
    }
}
