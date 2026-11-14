<?php

declare(strict_types=1);

namespace App\Application\Footer\Services;

use App\Domain\Footer\ValueObjects\FooterLayout;

/**
 * The configurable footer as the panel stored it (core#697), in the settings row `footer_layout`.
 */
interface FooterLayoutServiceInterface
{
    public const string SETTING_KEY = 'footer_layout';

    /**
     * The stored layout; empty when there is none or it cannot be read (the site then keeps its fixed footer).
     */
    public function layout(): FooterLayout;

    /**
     * Stores the layout; an empty one clears the row, which brings the fixed footer back.
     */
    public function save(FooterLayout $layout): void;
}
