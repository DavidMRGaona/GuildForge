<?php

declare(strict_types=1);

namespace App\Application\Mail\Services;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailPreferenceSectionDTO;
use App\Domain\Mail\Exceptions\MailPreferenceNotEditableException;

/**
 * Which mail each account receives. Only accounts have preferences: anything else (a guest,
 * an address) gets no sections and receives everything.
 */
interface MailPreferenceServiceInterface
{
    /**
     * The categories the person sees, grouped by section in registration order, with their
     * state. A category whose audience check fails is left out.
     *
     * @return list<MailPreferenceSectionDTO>
     */
    public function sectionsFor(object $user): array;

    /**
     * Save the person's choices (key => enabled). All or nothing: if one key is unknown,
     * mandatory or not shown to the person, nothing is saved.
     *
     * @param  array<string, bool>  $changes
     *
     * @throws MailPreferenceNotEditableException
     */
    public function update(object $user, array $changes): void;

    /**
     * Whether a mail of this category reaches the person. Mandatory categories always do; an
     * optional one does unless a delivery rule holds it back. When the preferences cannot be
     * read, the mail goes out.
     */
    public function receives(object $user, MailCategoryDTO $category): bool;
}
