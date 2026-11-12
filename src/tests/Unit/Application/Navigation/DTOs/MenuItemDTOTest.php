<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Navigation\DTOs;

use App\Application\Navigation\DTOs\MenuItemDTO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A link is named for modules by its module and route, never by its text or address.
 */
final class MenuItemDTOTest extends TestCase
{
    public function test_a_link_with_a_module_and_a_route_has_a_key_that_reaches_the_page(): void
    {
        $item = new MenuItemDTO('id', 'Foro', '/foro', '_self', null, key: MenuItemDTO::keyFor('forum', 'forum.index'));

        self::assertSame('forum:forum.index', $item->toArray()['key']);
    }

    /**
     * @return iterable<string, array{?string, ?string}>
     */
    public static function linksWithoutKey(): iterable
    {
        yield 'no module' => [null, 'forum.index'];
        yield 'empty module' => ['', 'forum.index'];
        yield 'no route' => ['forum', null];
        yield 'empty route' => ['forum', ''];
        yield 'neither' => [null, null];
    }

    #[DataProvider('linksWithoutKey')]
    public function test_a_link_missing_either_part_has_no_key(?string $module, ?string $route): void
    {
        self::assertNull(MenuItemDTO::keyFor($module, $route));
        self::assertNull((new MenuItemDTO('id', 'Enlace', '/x', '_self', null))->toArray()['key']);
    }
}
