<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Media;

use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Filament\Media\Sources\ArticleMediaSource;
use App\Filament\Media\Sources\EventMediaSource;
use App\Filament\Media\Sources\GalleryPhotoMediaSource;
use App\Filament\Media\Sources\HeroSlideMediaSource;
use App\Filament\Media\Sources\RichTextMediaSource;
use App\Filament\Media\Sources\SiteSettingsMediaSource;
use App\Infrastructure\Media\Sources\AvatarMediaSource;
use PHPUnit\Framework\TestCase;

final class CoreSourcesReleaseThroughCoreTest extends TestCase
{
    public function test_core_sources_release_through_the_core(): void
    {
        foreach ([
            ArticleMediaSource::class,
            EventMediaSource::class,
            GalleryPhotoMediaSource::class,
            HeroSlideMediaSource::class,
            RichTextMediaSource::class,
            SiteSettingsMediaSource::class,
        ] as $source) {
            $this->assertContains(ReleasesMediaThroughCoreInterface::class, (array) class_implements($source), $source);
        }
    }

    public function test_avatars_are_not_reusable(): void
    {
        $this->assertNotContains(ReleasesMediaThroughCoreInterface::class, (array) class_implements(AvatarMediaSource::class));
    }
}
