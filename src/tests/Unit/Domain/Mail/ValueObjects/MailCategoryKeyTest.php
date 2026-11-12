<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mail\ValueObjects;

use App\Domain\Mail\Exceptions\InvalidMailCategoryException;
use App\Domain\Mail\ValueObjects\MailCategoryKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailCategoryKeyTest extends TestCase
{
    public function test_it_splits_the_prefix_and_the_name(): void
    {
        $key = MailCategoryKey::fromString('game-tables.reminders');

        $this->assertSame('game-tables', $key->prefix());
        $this->assertSame('reminders', $key->name());
        $this->assertSame('game-tables.reminders', $key->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'no prefix' => ['reminders'];
        yield 'uppercase' => ['Game-tables.reminders'];
        yield 'three parts' => ['game-tables.reminders.daily'];
        yield 'spaces' => ['game tables.reminders'];
        yield 'empty name' => ['game-tables.'];
        yield 'trailing newline' => ["game-tables.reminders\n"];
    }

    #[DataProvider('invalidKeys')]
    public function test_it_rejects_a_malformed_key(string $value): void
    {
        $this->expectException(InvalidMailCategoryException::class);

        MailCategoryKey::fromString($value);
    }
}
