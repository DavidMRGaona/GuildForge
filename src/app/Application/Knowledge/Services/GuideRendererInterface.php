<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Domain\Knowledge\Entities\Guide;
use App\Domain\Knowledge\Enums\GuideBodyFormat;

interface GuideRendererInterface
{
    /**
     * Safe HTML of the guide body for this user (callouts, action buttons and placeholders resolved).
     * Never throws because of an action button: one that cannot be resolved renders nothing.
     */
    public function toHtml(Guide $guide, object $user): string;

    /**
     * Single-line plain text of a body, for the search index: placeholders resolved,
     * button labels kept, callout markers removed.
     */
    public function toPlainText(string $body, GuideBodyFormat $format = GuideBodyFormat::Markdown): string;
}
