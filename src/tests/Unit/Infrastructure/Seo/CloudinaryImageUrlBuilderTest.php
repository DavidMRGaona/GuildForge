<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Seo;

use App\Infrastructure\Seo\CloudinaryImageUrlBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CloudinaryImageUrlBuilderTest extends TestCase
{
    public function test_open_graph_url_matches_the_client_side_builder_format(): void
    {
        $builder = new CloudinaryImageUrlBuilder('demo', 'guildforge');

        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/e_trim/w_1200,h_630,c_fill,g_auto,q_auto,f_jpg/guildforge/events/2026/01/abc',
            $builder->openGraph('events/2026/01/abc.jpg'),
        );
    }

    public function test_open_graph_trims_uniform_borders_before_cropping(): void
    {
        $url = (string) (new CloudinaryImageUrlBuilder('demo', ''))->openGraph('campaigns/poster.png');

        // A poster uploaded with padding bands would keep them through c_fill; e_trim must run first
        $this->assertStringContainsString('/upload/e_trim/w_1200,', $url);
    }

    public function test_avatar_url_matches_the_client_side_avatar_preset(): void
    {
        $builder = new CloudinaryImageUrlBuilder('demo', 'guildforge');

        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/w_64,h_64,c_fill,g_face,q_auto,f_auto/guildforge/avatars/abc',
            $builder->avatar('avatars/abc.jpg', 64),
        );
    }

    public function test_animated_avatar_keeps_its_animation(): void
    {
        $url = (string) (new CloudinaryImageUrlBuilder('demo', ''))->avatar('avatars/abc-animated.gif', 64);

        $this->assertStringContainsString('q_auto,f_auto,fl_animated/avatars/abc-animated', $url);
    }

    public function test_dark_logo_is_padded_on_black(): void
    {
        $builder = new CloudinaryImageUrlBuilder('demo', 'guildforge');

        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/w_600,h_600,c_pad,b_black,q_auto,f_png/guildforge/settings/logo-dark',
            $builder->logo('settings/logo-dark.png', darkBackground: true),
        );
    }

    public function test_logo_url_is_padded_square_png(): void
    {
        $builder = new CloudinaryImageUrlBuilder('demo', 'guildforge');

        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/w_600,h_600,c_pad,b_white,q_auto,f_png/guildforge/settings/logo',
            $builder->logo('settings/logo.svg'),
        );
    }

    public function test_prefix_is_not_added_twice(): void
    {
        $builder = new CloudinaryImageUrlBuilder('demo', 'guildforge');

        $this->assertStringEndsWith('/guildforge/events/abc', (string) $builder->openGraph('guildforge/events/abc.png'));
    }

    public function test_without_prefix_the_public_id_is_used_as_is(): void
    {
        $builder = new CloudinaryImageUrlBuilder('demo', '');

        $this->assertStringEndsWith(',f_jpg/events/abc', (string) $builder->openGraph('events/abc.webp'));
    }

    public function test_unknown_extensions_are_kept(): void
    {
        $builder = new CloudinaryImageUrlBuilder('demo', '');

        $this->assertStringEndsWith(',f_jpg/events/v1.2', (string) $builder->openGraph('events/v1.2'));
    }

    #[DataProvider('unusableInputs')]
    public function test_returns_null_when_a_url_cannot_be_built(string $cloudName, string $publicId): void
    {
        $builder = new CloudinaryImageUrlBuilder($cloudName, 'guildforge');

        $this->assertNull($builder->openGraph($publicId));
        $this->assertNull($builder->logo($publicId));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unusableInputs(): iterable
    {
        yield 'no cloud name' => ['', 'events/abc.jpg'];
        yield 'empty public id' => ['demo', ''];
    }

    public function test_media_library_urls_are_built_from_the_file_id(): void
    {
        $builder = new CloudinaryImageUrlBuilder('demo', 'site');

        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/w_400,h_400,c_fill,g_auto,q_auto,f_auto/site/events/2026/11/abc',
            $builder->mediaThumbnail('events/2026/11/abc', 400),
        );
        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/w_1200,c_limit,q_auto,f_auto/site/events/abc',
            $builder->mediaPreview('events/abc', 1200),
        );
        $this->assertSame('https://res.cloudinary.com/demo/image/upload/site/events/abc', $builder->mediaOriginal('events/abc'));
    }

    public function test_svg_files_are_shown_as_uploaded_because_cloudinary_refuses_to_rasterize_some_that_browsers_draw(): void
    {
        $builder = new CloudinaryImageUrlBuilder('demo', 'site');
        $original = 'https://res.cloudinary.com/demo/image/upload/site/branding/logo';

        $this->assertSame($original, $builder->mediaThumbnail('branding/logo', 400, 'svg'));
        $this->assertSame($original, $builder->mediaPreview('branding/logo', 1200, 'SVG'));
        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/w_400,h_400,c_fill,g_auto,q_auto,f_auto/site/events/abc',
            $builder->mediaThumbnail('events/abc', 400, 'png'),
        );
        $this->assertNull((new CloudinaryImageUrlBuilder('', 'site'))->mediaThumbnail('branding/logo', 400, 'svg'));
    }

    public function test_media_library_urls_always_add_the_prefix_and_encode_each_segment(): void
    {
        $builder = new CloudinaryImageUrlBuilder('demo', 'site');

        // Library ids are relative to the prefix, even one whose first folder is named like it
        $this->assertSame('https://res.cloudinary.com/demo/image/upload/site/site/a%20b%23c', $builder->mediaOriginal('site/a b#c'));
        $this->assertSame('https://res.cloudinary.com/demo/raw/upload/site/docs/a', $builder->mediaOriginal('docs/a', 'raw'));
        $this->assertNull($builder->mediaOriginal('docs/a', 'raw/../x'));
        $this->assertNull((new CloudinaryImageUrlBuilder('', 'site'))->mediaThumbnail('events/a', 400));
    }
}
