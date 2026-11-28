<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Bots;

use App\Domain\Bots\ValueObjects\BotKey;
use App\Domain\SiteBlocks\ValueObjects\SiteBlockKey;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BotKeyTest extends TestCase
{
    public function test_a_key_is_its_module_and_a_name(): void
    {
        $key = BotKey::of('forum.publisher');

        $this->assertSame('forum.publisher', $key->value());
        $this->assertSame('forum', $key->module());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidKeys(): array
    {
        return [
            'empty' => [''],
            'no module' => ['publisher'],
            'upper case' => ['Forum.publisher'],
            'empty name' => ['forum.'],
            'empty module' => ['.publisher'],
            'three segments' => ['forum.a.b'],
            'too long' => ['forum.'.str_repeat('a', SiteBlockKey::MAX_LENGTH)],
            'one hundred and one characters' => [str_repeat('a', 101)],
        ];
    }

    #[DataProvider('invalidKeys')]
    public function test_invalid_keys_are_refused(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);

        BotKey::of($key);
    }
}
