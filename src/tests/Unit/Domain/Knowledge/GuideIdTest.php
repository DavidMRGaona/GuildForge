<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Knowledge;

use App\Domain\Knowledge\Exceptions\InvalidGuideIdException;
use App\Domain\Knowledge\ValueObjects\GuideId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GuideIdTest extends TestCase
{
    public function test_it_accepts_prefixed_kebab_ids(): void
    {
        $id = GuideId::fromString('game-tables.join-table');

        $this->assertSame('game-tables', $id->prefix());
        $this->assertSame('game-tables.join-table', $id->value());
    }

    public function test_it_compares_by_value(): void
    {
        $this->assertTrue(GuideId::fromString('core.a')->equals(GuideId::fromString('core.a')));
        $this->assertFalse(GuideId::fromString('core.a')->equals(GuideId::fromString('core.b')));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIds(): array
    {
        return [
            'empty' => [''],
            'no dot' => ['core'],
            'uppercase' => ['Core.x'],
            'double dot' => ['core..x'],
            'underscore' => ['core.x_y'],
            'leading dot' => ['.core'],
            'trailing dot' => ['core.'],
            'space' => ['core.x y'],
        ];
    }

    #[DataProvider('invalidIds')]
    public function test_it_rejects_invalid_ids(string $value): void
    {
        $this->expectException(InvalidGuideIdException::class);

        GuideId::fromString($value);
    }
}
