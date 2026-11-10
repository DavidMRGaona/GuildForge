<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Media;

use App\Domain\Media\Exceptions\InvalidMediaFileIdException;
use App\Domain\Media\ValueObjects\MediaFileId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MediaFileIdTest extends TestCase
{
    public function test_folder_is_the_directory_with_trailing_slash(): void
    {
        $this->assertSame('events/2026/11/', (new MediaFileId('events/2026/11/abc'))->folder());
    }

    public function test_file_at_root_has_empty_folder(): void
    {
        $this->assertSame('', (new MediaFileId('abc'))->folder());
    }

    public function test_it_compares_by_value(): void
    {
        $this->assertTrue((new MediaFileId('a/b'))->equals(new MediaFileId('a/b')));
        $this->assertFalse((new MediaFileId('a/b'))->equals(new MediaFileId('a/c')));
    }

    #[DataProvider('invalid')]
    public function test_rejects_invalid_values(string $value): void
    {
        $this->expectException(InvalidMediaFileIdException::class);

        new MediaFileId($value);
    }

    /** @return list<array{string}> */
    public static function invalid(): array
    {
        return [[''], ['/events/a'], ['events/'], ['events/../a']];
    }
}
