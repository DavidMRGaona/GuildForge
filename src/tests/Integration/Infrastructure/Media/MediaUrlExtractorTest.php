<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Media;

use App\Application\Media\Services\MediaUrlExtractorInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * The files of this site a text points to, as every source that reads rich text needs them
 * (RichTextMediaSource and the modules' sources), core#496.
 */
final class MediaUrlExtractorTest extends TestCase
{
    /** Delivery URL base of this site (the test prefix is the default one, guildforge) */
    private const string BASE = 'https://res.cloudinary.com/demo/image/upload/';

    public function test_urls_in_attributes_and_bare_text_name_their_files(): void
    {
        $text = '<p><img src="'.self::BASE.'v1/guildforge/announcements/content/2026/11/a.png" alt="Cartel"></p>'
            .'<p><a href="'.self::BASE.'w_800/guildforge/events/2026/11/b.jpg">Ver</a></p>'
            .'<p>Copiada de '.self::BASE.'guildforge/articles/2026/11/c.webp.</p>';

        $found = $this->extractor()->fileIdsIn($text);

        $this->assertSame(self::BASE.'v1/guildforge/announcements/content/2026/11/a.png', $found['announcements/content/2026/11/a'] ?? null);
        $this->assertSame(self::BASE.'w_800/guildforge/events/2026/11/b.jpg', $found['events/2026/11/b'] ?? null);
        // The full stop may close the sentence or be part of the URL: both forms count
        $this->assertArrayHasKey('articles/2026/11/c', $found);
    }

    public function test_escaped_slashes_and_entities_do_not_hide_a_url(): void
    {
        $json = json_encode(['html' => '<img src="'.self::BASE.'guildforge/events/a.png">']);
        $entities = '<img src="https:&#47;&#47;res.cloudinary.com/demo/image/upload/guildforge/events/b.png?x=1&amp;y=2">';

        $this->assertArrayHasKey('events/a', $this->extractor()->fileIdsIn((string) $json));
        $this->assertArrayHasKey('events/b', $this->extractor()->fileIdsIn($entities));
    }

    public function test_urls_of_other_sites_and_empty_values_name_nothing(): void
    {
        $this->assertSame([], $this->extractor()->fileIdsIn('<img src="https://example.org/guildforge/events/a.png"><img src="/storage/a.png">'));
        $this->assertSame([], $this->extractor()->fileIdsIn('<img src="'.self::BASE.'other-prefix/events/a.png">'));
        $this->assertSame([], $this->extractor()->fileIdsIn(''));
        $this->assertSame([], $this->extractor()->fileIdsIn(null));
        $this->assertSame([], $this->extractor()->fileIdsIn(['not', 'text']));
    }

    public function test_a_text_the_pattern_cannot_read_fails_instead_of_naming_nothing(): void
    {
        $extractor = $this->extractor();
        $jit = ini_get('pcre.jit');
        $limit = ini_get('pcre.backtrack_limit');
        // Any match now fails with a PCRE error, as a huge text could
        ini_set('pcre.jit', '0');
        ini_set('pcre.backtrack_limit', '0');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('could not be read for media URLs');
            $extractor->fileIdsIn('<img src="'.self::BASE.'guildforge/events/a.jpg">');
        } finally {
            ini_set('pcre.jit', (string) $jit);
            ini_set('pcre.backtrack_limit', (string) $limit);
        }
    }

    private function extractor(): MediaUrlExtractorInterface
    {
        return app(MediaUrlExtractorInterface::class);
    }
}
