<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Domain\Knowledge\Entities\Guide;

interface GuideRendererInterface
{
    /**
     * Safe HTML of the guide body for this user (callouts, action buttons and placeholders resolved).
     * Never throws because of an action button: one that cannot be resolved renders nothing.
     */
    public function toHtml(Guide $guide, object $user): string;

    /**
     * Single-line plain text of a Markdown body, for the search index: placeholders resolved,
     * button labels kept, callout markers removed.
     */
    public function toPlainText(string $markdown): string;
}
