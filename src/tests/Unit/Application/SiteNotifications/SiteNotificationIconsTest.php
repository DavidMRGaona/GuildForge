<?php

declare(strict_types=1);

namespace Tests\Unit\Application\SiteNotifications;

use App\Application\SiteNotifications\SiteNotificationIcons;
use App\Domain\SiteNews\SiteNewsKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SiteNotificationIconsTest extends TestCase
{
    private const string ICONS_FILE = 'resources/js/utils/icons.ts';

    public function test_it_knows_the_icons_the_bell_draws(): void
    {
        $this->assertTrue(SiteNotificationIcons::isKnown('message-square'));
        $this->assertTrue(SiteNotificationIcons::isKnown('calendar-check'));
        $this->assertFalse(SiteNotificationIcons::isKnown('not-an-icon'));
        $this->assertFalse(SiteNotificationIcons::isKnown(''));
    }

    public function test_the_list_holds_each_icon_once_in_order(): void
    {
        $sorted = SiteNotificationIcons::NAMES;
        sort($sorted);

        $this->assertSame(array_values(array_unique(SiteNotificationIcons::NAMES)), SiteNotificationIcons::NAMES);
        $this->assertSame($sorted, SiteNotificationIcons::NAMES);
    }

    /**
     * The bell draws the mail section icons plus its own: both maps of icons.ts together must
     * be exactly this list, so an icon added on one side only breaks this test.
     */
    public function test_the_list_is_what_the_bell_draws(): void
    {
        $source = (string) file_get_contents($this->path(self::ICONS_FILE));

        $drawn = array_merge(
            $this->mapKeys($source, 'mailSectionIconMap'),
            $this->mapKeys($source, 'siteNotificationIconMap'),
        );
        sort($drawn);

        $this->assertSame(SiteNotificationIcons::NAMES, $drawn);
    }

    #[DataProvider('coreNoticeIcons')]
    public function test_every_core_notice_asks_for_an_icon_the_bell_draws(string $icon, string $where): void
    {
        $this->assertTrue(SiteNotificationIcons::isKnown($icon), sprintf('%s asks for the unknown icon "%s".', $where, $icon));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function coreNoticeIcons(): iterable
    {
        $files = glob(dirname(__DIR__, 4).'/app/Notifications/*.php') ?: [];

        foreach ($files as $file) {
            preg_match_all("/\\bicon: '([a-z0-9-]+)'/", (string) file_get_contents($file), $matches);

            foreach ($matches[1] as $icon) {
                yield basename($file).' '.$icon => [$icon, basename($file)];
            }
        }

        foreach (SiteNewsKind::cases() as $kind) {
            yield 'SiteNewsKind::'.$kind->name => [$kind->icon(), 'SiteNewsKind::'.$kind->name];
        }
    }

    public function test_the_core_notices_are_found(): void
    {
        $where = array_column(iterator_to_array(self::coreNoticeIcons()), 1);

        $this->assertContains('ProposalToReviewNotification.php', $where);
        $this->assertContains('InactiveRolesNotification.php', $where);
    }

    /**
     * @return list<string>
     */
    private function mapKeys(string $source, string $map): array
    {
        $found = preg_match('/export const '.$map.': Record<string, Component> = \{(.*?)\n\};/s', $source, $block);
        $this->assertSame(1, $found, sprintf('%s not found in %s.', $map, self::ICONS_FILE));

        preg_match_all("/^\\s*'?([a-z0-9-]+)'?:/m", $block[1], $keys);

        return $keys[1];
    }

    private function path(string $relative): string
    {
        return dirname(__DIR__, 4).'/'.$relative;
    }
}
