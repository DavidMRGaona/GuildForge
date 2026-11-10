<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Media;

use App\Domain\Media\Exceptions\MediaCatalogUnavailableException;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Infrastructure\Media\Cloudinary\DeferredMediaCatalog;
use ArrayObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Media\InMemoryMediaCatalog;

final class DeferredMediaCatalogTest extends TestCase
{
    public function test_builds_the_catalog_once_on_first_use(): void
    {
        $builds = new ArrayObject;
        $inner = new InMemoryMediaCatalog;
        $inner->add('events/a');
        $catalog = new DeferredMediaCatalog(static function () use ($builds, $inner): InMemoryMediaCatalog {
            $builds->append(true);

            return $inner;
        });

        $this->assertCount(0, $builds);
        $this->assertCount(1, $catalog->all());
        $this->assertNotNull($catalog->find(new MediaFileId('events/a'), 'image'));
        $this->assertCount(1, $builds);
    }

    public function test_a_catalog_that_cannot_be_built_is_unavailable(): void
    {
        $catalog = new DeferredMediaCatalog(static fn (): never => throw new RuntimeException('Invalid configuration'));

        $this->expectException(MediaCatalogUnavailableException::class);

        $catalog->find(new MediaFileId('events/a'), 'image');
    }
}
