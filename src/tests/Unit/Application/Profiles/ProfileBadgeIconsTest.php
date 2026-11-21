<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Profiles;

use App\Application\Profiles\ProfileBadgeIcons;
use App\Domain\Profiles\ProfileBadgeColour;
use PHPUnit\Framework\TestCase;

/**
 * The closed lists a profile badge chooses from (core#776): the icons and the colours the browser
 * knows how to draw, kept equal on both sides by these tests.
 */
final class ProfileBadgeIconsTest extends TestCase
{
    private const string ICONS_FILE = 'resources/js/utils/icons.ts';

    private const string COLOURS_FILE = 'resources/js/utils/profileBadges.ts';

    private const string TYPES_FILE = 'resources/js/types/profileSections.ts';

    public function test_it_knows_the_icons_a_badge_may_have(): void
    {
        $this->assertTrue(ProfileBadgeIcons::isKnown('trophy'));
        $this->assertTrue(ProfileBadgeIcons::isKnown('dices'));
        $this->assertFalse(ProfileBadgeIcons::isKnown('not-an-icon'));
        $this->assertFalse(ProfileBadgeIcons::isKnown(''));
    }

    public function test_the_list_holds_each_icon_once_in_order(): void
    {
        $sorted = ProfileBadgeIcons::NAMES;
        sort($sorted);

        $this->assertSame(array_values(array_unique(ProfileBadgeIcons::NAMES)), ProfileBadgeIcons::NAMES);
        $this->assertSame($sorted, ProfileBadgeIcons::NAMES);
    }

    public function test_the_list_is_what_the_browser_draws(): void
    {
        $drawn = $this->mapKeys(self::ICONS_FILE, 'export const profileBadgeIconMap: Record<string, Component> = {');
        sort($drawn);

        $this->assertSame(ProfileBadgeIcons::NAMES, $drawn);
    }

    public function test_every_colour_has_its_classes_in_the_browser_and_nothing_else_has(): void
    {
        $drawn = $this->mapKeys(self::COLOURS_FILE, 'export const profileBadgeColourClasses: Record<ProfileBadgeColour, string> = {');
        sort($drawn);

        $colours = array_map(static fn (ProfileBadgeColour $colour): string => $colour->value, ProfileBadgeColour::cases());
        sort($colours);

        $this->assertSame($colours, $drawn);
    }

    public function test_the_colour_type_of_the_browser_is_the_same_list(): void
    {
        $source = (string) file_get_contents($this->path(self::TYPES_FILE));
        $this->assertSame(1, preg_match('/export type ProfileBadgeColour =([^;]+);/', $source, $type), 'ProfileBadgeColour type not found.');
        preg_match_all("/'([a-z]+)'/", $type[1], $names);

        $this->assertSame(
            array_map(static fn (ProfileBadgeColour $colour): string => $colour->value, ProfileBadgeColour::cases()),
            $names[1],
        );
    }

    /**
     * @return list<string>
     */
    private function mapKeys(string $file, string $opening): array
    {
        $source = (string) file_get_contents($this->path($file));
        $start = strpos($source, $opening);
        $this->assertNotFalse($start, sprintf('"%s" not found in %s.', $opening, $file));

        $end = strpos($source, "\n};", $start);
        $this->assertNotFalse($end);
        $block = substr($source, $start + strlen($opening), $end - $start - strlen($opening));

        preg_match_all("/^\\s*'?([a-z0-9-]+)'?:/m", $block, $keys);

        return $keys[1];
    }

    private function path(string $relative): string
    {
        return dirname(__DIR__, 4).'/'.$relative;
    }
}
