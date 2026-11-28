<?php

declare(strict_types=1);

namespace Tests\Unit\Filament\Media;

use App\Application\Media\Contracts\ShowsMediaPreviews;
use App\Filament\Media\Sources\ArticleMediaSource;
use App\Filament\Media\Sources\ArticleTextImageMediaSource;
use App\Filament\Media\Sources\EventMediaSource;
use App\Filament\Media\Sources\FooterMediaSource;
use App\Filament\Media\Sources\GalleryPhotoMediaSource;
use App\Filament\Media\Sources\HeroSlideMediaSource;
use App\Filament\Media\Sources\ProposalImageMediaSource;
use App\Filament\Media\Sources\RichTextMediaSource;
use App\Filament\Media\Sources\SiteSettingsMediaSource;
use App\Infrastructure\Media\Sources\AvatarMediaSource;
use App\Infrastructure\Media\Sources\ProfileBannerMediaSource;
use PHPUnit\Framework\TestCase;

/**
 * The library hides every image unless the source claiming its folder declares it may be shown (core#837).
 */
final class CoreSourcesShowMediaPreviewsTest extends TestCase
{
    public function test_core_sources_show_their_previews(): void
    {
        foreach ([
            ArticleMediaSource::class,
            EventMediaSource::class,
            FooterMediaSource::class,
            GalleryPhotoMediaSource::class,
            HeroSlideMediaSource::class,
            RichTextMediaSource::class,
            SiteSettingsMediaSource::class,
            ProfileBannerMediaSource::class,
            ProposalImageMediaSource::class,
            ArticleTextImageMediaSource::class,
        ] as $source) {
            $this->assertContains(ShowsMediaPreviews::class, (array) class_implements($source), $source);
        }
    }

    public function test_avatars_do_not_show_their_previews(): void
    {
        $this->assertNotContains(ShowsMediaPreviews::class, (array) class_implements(AvatarMediaSource::class));
    }
}
