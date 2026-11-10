<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Content\Enums;

use App\Domain\Content\Enums\EditorImageOrigin;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EditorImageOriginTest extends TestCase
{
    public function test_the_directory_is_the_origin_content_folder_of_the_month(): void
    {
        $month = new DateTimeImmutable('2026-11-10 12:00:00');

        $this->assertSame('articles/content/2026/11', EditorImageOrigin::Articles->directory($month));
        $this->assertSame('events/content/2026/11', EditorImageOrigin::Events->directory($month));
        $this->assertSame('knowledge/content/2026/11', EditorImageOrigin::Knowledge->directory($month));
        $this->assertSame('legal/content/2026/11', EditorImageOrigin::Legal->directory($month));
        $this->assertSame('about/content/2026/11', EditorImageOrigin::About->directory($month));
    }

    public function test_a_path_in_a_content_folder_names_its_origin(): void
    {
        $this->assertSame(EditorImageOrigin::Articles, EditorImageOrigin::ofPath('articles/content/2026/11/0b7e7c1e-6a0f-4c56-9d0e-1b2f3a4c5d6e.png'));
        $this->assertSame(EditorImageOrigin::Legal, EditorImageOrigin::ofPath('legal/content/2025/01/a.webp'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notEditorImages(): array
    {
        return [
            'local upload at the disk root' => ['3fJk2Lm9.png'],
            'column image of an article' => ['articles/2026/11/uuid.png'],
            'unknown origin' => ['photos/content/2026/11/uuid.png'],
            'no month' => ['articles/content/uuid.png'],
            'nested further' => ['articles/content/2026/11/extra/uuid.png'],
            'parent segment' => ['articles/content/2026/11/../../../x.png'],
            'absolute' => ['/articles/content/2026/11/uuid.png'],
            'empty' => [''],
        ];
    }

    #[DataProvider('notEditorImages')]
    public function test_other_paths_have_no_origin(string $path): void
    {
        $this->assertNull(EditorImageOrigin::ofPath($path));
    }
}
