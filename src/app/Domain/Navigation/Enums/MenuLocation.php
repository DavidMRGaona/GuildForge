<?php

declare(strict_types=1);

namespace App\Domain\Navigation\Enums;

/**
 * Where a menu item is shown. `footer` is the footer menu of the fixed footer (and the first footer
 * menu of the configurable one); `footer_2` to `footer_4` are the other footer menus a links block of
 * the configurable footer can draw (core#697), one per column at most.
 */
enum MenuLocation: string
{
    case Header = 'header';
    case Footer = 'footer';
    case Footer2 = 'footer_2';
    case Footer3 = 'footer_3';
    case Footer4 = 'footer_4';

    /**
     * The footer menus, in order.
     *
     * @return list<self>
     */
    public static function footers(): array
    {
        return [self::Footer, self::Footer2, self::Footer3, self::Footer4];
    }

    public function isFooter(): bool
    {
        return $this !== self::Header;
    }

    /**
     * Its number among the footer menus (1 to 4), or null for the header.
     */
    public function footerNumber(): ?int
    {
        $position = array_search($this, self::footers(), true);

        return $position === false ? null : $position + 1;
    }
}
