<?php

declare(strict_types=1);

namespace App\Application\Content\Services;

use App\Domain\Content\Enums\EditorImageOrigin;

/**
 * Sanitises the HTML a person wrote in the web rich editor before it is stored (core#573).
 * Never trust what arrives: the result holds only what the panel's RichEditor knows.
 */
interface RichTextSanitizerInterface
{
    /**
     * The core's rich text pipeline (HTMLPurifier "richtext"). Images are kept only when they are
     * editor images of $origin (their delivery URL together with their "data-id", the stored path),
     * so the editor text and media sources claim them; any other image is removed.
     */
    public function sanitize(string $html, EditorImageOrigin $origin): string;
}
