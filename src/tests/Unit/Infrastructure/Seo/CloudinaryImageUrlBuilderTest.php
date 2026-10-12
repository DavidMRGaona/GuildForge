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
            'https://res.cloudinary.com/demo/image/upload/w_1200,h_630,c_fill,g_auto,q_auto,f_jpg/guildforge/events/2026/01/abc',
            $builder->openGraph('events/2026/01/abc.jpg'),
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
}
