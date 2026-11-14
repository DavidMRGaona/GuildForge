<?php

declare(strict_types=1);

namespace App\Application\Navigation\Services;

use App\Domain\Navigation\Enums\MenuLocation;

/**
 * The footer menus (MenuLocation::footers()) and the names the administration gives them, so the
 * menu editor and the footer's links blocks tell them apart (core#697). Names only live in the panel:
 * the public footer shows the title of the block, never the menu's name.
 */
interface FooterMenusInterface
{
    public const int MAX_NAME_LENGTH = 60;

    /**
     * The menu's name, or its default one («Pie de página», «Pie de página 2»…). The header gets its own label.
     */
    public function label(MenuLocation $location): string;

    /**
     * The name a footer menu has until one is given: «Pie de página», «Pie de página 2»…
     */
    public function defaultLabel(MenuLocation $location): string;

    /**
     * Every footer menu with its label, in order, keyed by location value.
     *
     * @return array<string, string>
     */
    public function options(): array;

    /**
     * The names given, keyed by location value: only footer menus, never blank.
     *
     * @return array<string, string>
     */
    public function names(): array;

    /**
     * Stores the names; a blank one brings the default back. Keys that are not footer menus are ignored.
     *
     * @param  array<array-key, mixed>  $names
     */
    public function saveNames(array $names): void;
}
