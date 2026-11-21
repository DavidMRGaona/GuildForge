<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Activity;

use App\Domain\Activity\ValueObjects\ActivitySourceType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ActivitySourceTypeTest extends TestCase
{
    public function test_a_type_is_its_owner_and_its_name(): void
    {
        $type = ActivitySourceType::tryFrom('game-tables.session');

        $this->assertNotNull($type);
        $this->assertSame('game-tables.session', $type->value);
        $this->assertSame('game-tables', $type->owner());
        $this->assertSame('session', $type->name());
        $this->assertFalse($type->isCore());
    }

    public function test_a_core_type_says_so(): void
    {
        $type = ActivitySourceType::tryFrom('core.profile-completed');

        $this->assertNotNull($type);
        $this->assertTrue($type->isCore());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTypes(): iterable
    {
        yield 'empty' => [''];
        yield 'no owner' => ['session'];
        yield 'capitals' => ['Game-tables.session'];
        yield 'spaces' => ['game tables.session'];
        yield 'three parts' => ['game-tables.session.extra'];
        yield 'trailing newline' => ["game-tables.session\n"];
        yield 'too long' => ['forum.'.str_repeat('a', ActivitySourceType::MAX_LENGTH)];
    }

    #[DataProvider('invalidTypes')]
    public function test_an_invalid_type_is_refused(string $type): void
    {
        $this->assertNull(ActivitySourceType::tryFrom($type));
    }
}
