<?php

declare(strict_types=1);

namespace App\Application\Content\Services;

/**
 * Converts rich text written with the Trix editor (admin panel before core 3.0) into the
 * HTML the TipTap editor stores, so saving it again in the panel changes nothing.
 */
interface TrixHtmlConverterInterface
{
    /**
     * Idempotent: converting already converted HTML returns it unchanged. Empty input stays empty.
     */
    public function convert(string $html): string;
}
