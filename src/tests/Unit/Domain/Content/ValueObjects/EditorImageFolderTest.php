<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Content\ValueObjects;

use App\Domain\Content\Enums\EditorImageOrigin;
use App\Domain\Content\ValueObjects\EditorImageFolder;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The content folder of a scope (a core origin or a module), where the images inserted with a
 * rich editor live: "<scope>/content/<year>/<month>/<file>" (core#496).
 */
final class EditorImageFolderTest extends TestCase
{
    public function test_a_module_folder_gives_its_directory_and_claim(): void
    {
        $folder = EditorImageFolder::of('game-tables');

        $this->assertSame('game-tables', $folder->scope);
        $this->assertSame('game-tables/content/2026/03', $folder->directory(new DateTimeImmutable('2026-03-05')));
        $this->assertSame('game-tables/content/', $folder->claim());
    }

    public function test_a_core_origin_has_the_same_folder(): void
    {
        $this->assertTrue(EditorImageOrigin::Articles->folder()->equals(EditorImageFolder::of('articles')));
        $this->assertSame('legal/content/2026/11', EditorImageOrigin::Legal->directory(new DateTimeImmutable('2026-11-10')));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidScopes(): array
    {
        return [
            'empty' => [''],
            'uppercase' => ['Announcements'],
            'two segments' => ['game-tables/campaigns'],
            'parent segment' => ['..'],
            'trailing slash' => ['announcements/'],
            'leading hyphen' => ['-tables'],
            'trailing hyphen' => ['tables-'],
            'double hyphen' => ['game--tables'],
            'underscore' => ['game_tables'],
            'space' => ['game tables'],
            'content itself' => ['content'],
        ];
    }

    #[DataProvider('invalidScopes')]
    public function test_an_invalid_scope_is_refused(string $scope): void
    {
        $this->expectException(InvalidArgumentException::class);

        EditorImageFolder::of($scope);
    }

    public function test_a_path_in_any_content_folder_names_its_folder(): void
    {
        $this->assertSame('announcements', EditorImageFolder::ofPath('announcements/content/2026/11/a.png')?->scope);
        $this->assertSame('game-tables', EditorImageFolder::ofPath('game-tables/content/2026/01/0b7e7c1e.jpg')?->scope);
        $this->assertSame('articles', EditorImageFolder::ofPath('articles/content/2026/11/a.png')?->scope);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function otherPaths(): array
    {
        return [
            'column image' => ['articles/2026/11/a.png'],
            'no month' => ['announcements/content/2026/a.png'],
            'nested file' => ['announcements/content/2026/11/x/a.png'],
            'legacy local' => ['3fJk2Lm9Qx.png'],
            'parent segment' => ['../content/2026/11/a.png'],
            'url' => ['https://example.org/announcements/content/2026/11/a.png'],
            'uppercase scope' => ['Articles/content/2026/11/a.png'],
        ];
    }

    #[DataProvider('otherPaths')]
    public function test_any_other_path_names_no_folder(string $path): void
    {
        $this->assertNull(EditorImageFolder::ofPath($path));
    }
}
