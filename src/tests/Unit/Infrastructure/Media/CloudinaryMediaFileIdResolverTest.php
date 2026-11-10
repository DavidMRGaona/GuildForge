<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Media;

use App\Infrastructure\Media\Services\CloudinaryMediaFileIdResolver;
use PHPUnit\Framework\TestCase;

final class CloudinaryMediaFileIdResolverTest extends TestCase
{
    private function resolver(string $prefix = 'guildforge'): CloudinaryMediaFileIdResolver
    {
        return new CloudinaryMediaFileIdResolver($prefix);
    }

    public function test_stored_path_drops_extension(): void
    {
        $id = $this->resolver()->fromStoredPath('events/2026/11/a.jpg');

        $this->assertSame('events/2026/11/a', $id?->value);
        $this->assertSame('image', $this->resolver()->resourceType('events/2026/11/a.jpg'));
    }

    public function test_upper_case_extension_is_dropped(): void
    {
        $this->assertSame('events/a', $this->resolver()->fromStoredPath('events/a.JPG')?->value);
    }

    public function test_cloudinary_url_of_this_site_is_resolved(): void
    {
        $url = 'https://res.cloudinary.com/demo/image/upload/v1/guildforge/articles/content/2026/11/b.webp';

        $this->assertSame('articles/content/2026/11/b', $this->resolver()->fromStoredPath($url)?->value);
    }

    public function test_url_with_transformations_is_resolved(): void
    {
        $url = 'https://res.cloudinary.com/demo/image/upload/c_fill,w_300/v12/guildforge/events/a.jpg';

        $this->assertSame('events/a', $this->resolver()->fromStoredPath($url)?->value);
    }

    public function test_url_of_another_prefix_is_null(): void
    {
        $url = 'https://res.cloudinary.com/demo/image/upload/v1/guildforge-smoke/events/a.jpg';

        $this->assertNull($this->resolver()->fromStoredPath($url));
    }

    public function test_non_cloudinary_url_is_null(): void
    {
        $this->assertNull($this->resolver()->fromStoredPath('https://example.com/images/a.jpg'));
    }

    public function test_public_id_without_prefix_slash_is_null(): void
    {
        $this->assertNull($this->resolver()->fromPublicId('guildforge-smoke/events/a'));
        $this->assertSame('events/a', $this->resolver()->fromPublicId('guildforge/events/a')?->value);
    }

    public function test_prefix_is_exposed(): void
    {
        $this->assertSame('guildforge', $this->resolver()->prefix());
    }
}
