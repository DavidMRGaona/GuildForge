<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Time;

use App\Infrastructure\Time\StorageTimezoneConverter;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

/**
 * Runs on its own SQLite connection, so the refusal holds whatever connection the suite uses.
 */
final class StorageTimezoneConverterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.converter_sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'database.default' => 'converter_sqlite',
        ]);
    }

    public function test_does_not_support_a_connection_other_than_postgresql(): void
    {
        $this->assertFalse(app(StorageTimezoneConverter::class)->supportsConnection());
    }

    public function test_refuses_to_convert_on_a_connection_other_than_postgresql(): void
    {
        $this->expectException(LogicException::class);

        DB::transaction(fn (): array => app(StorageTimezoneConverter::class)->convert('Europe/Madrid', 'UTC'));
    }
}
