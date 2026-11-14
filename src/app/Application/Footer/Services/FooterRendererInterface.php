<?php

declare(strict_types=1);

namespace App\Application\Footer\Services;

use App\Application\Footer\DTOs\FooterDTO;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The configurable footer ready for one person (core#697): each stored block resolved by the site
 * block renderer, so what cannot be drawn now is left out, plus the fixed bottom bar.
 */
interface FooterRendererInterface
{
    /**
     * Null when no layout is stored, it has no block, or none of its blocks can be drawn now: the site
     * then draws its fixed footer, unchanged. Never throws.
     */
    public function forUser(?Authenticatable $user): ?FooterDTO;
}
