<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Publications;

use App\Domain\Publications\Events\PublicationRosterChanged;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicationRosterChangedTest extends TestCase
{
    private const string USER = '0195f3a2-7c4e-7b1a-9d3f-2a6b8c4e1f07';

    public function test_it_names_the_publication_and_the_accounts_once_in_lowercase(): void
    {
        $event = new PublicationRosterChanged('campaign', 'campaign-1', [self::USER, strtoupper(self::USER)]);

        $this->assertSame('campaign', $event->type);
        $this->assertSame('campaign-1', $event->id);
        $this->assertSame([self::USER], $event->userIds);
    }

    /**
     * @param  list<string>  $userIds
     */
    #[DataProvider('invalidChanges')]
    public function test_an_incomplete_change_is_rejected(string $type, string $id, array $userIds): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PublicationRosterChanged($type, $id, $userIds);
    }

    /**
     * @return array<string, array{string, string, list<string>}>
     */
    public static function invalidChanges(): array
    {
        return [
            'no type' => ['', 'campaign-1', [self::USER]],
            'no id' => ['campaign', ' ', [self::USER]],
            'nobody' => ['campaign', 'campaign-1', []],
            'not a user id' => ['campaign', 'campaign-1', ['someone']],
        ];
    }
}
