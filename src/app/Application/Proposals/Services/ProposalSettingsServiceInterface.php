<?php

declare(strict_types=1);

namespace App\Application\Proposals\Services;

use App\Application\Proposals\DTOs\ProposalSettingsDTO;

/**
 * Reads and writes the proposals settings, kept in the settings table. Reading fails closed: an
 * unreadable switch is off and an unreadable access level lets nobody propose; an unreadable or
 * out-of-range number falls back to its default.
 */
interface ProposalSettingsServiceInterface
{
    public function settings(): ProposalSettingsDTO;

    /**
     * Saves every setting. Role names that do not exist are dropped and numbers are clamped to
     * their bounds.
     */
    public function save(ProposalSettingsDTO $settings): void;
}
