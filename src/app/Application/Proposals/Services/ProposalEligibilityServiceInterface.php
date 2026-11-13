<?php

declare(strict_types=1);

namespace App\Application\Proposals\Services;

use App\Application\Proposals\DTOs\ProposalEligibilityDTO;
use App\Domain\Proposals\Enums\ProposalType;

/**
 * Who may propose (create, edit and submit) events or articles from the web: proposals turned
 * on for the type, an account that is active and has a verified email (always, whatever the site's
 * verification setting) and the type's access level. It fails closed: an unreadable level, a level
 * by roles without roles or a check that throws lets nobody propose.
 */
interface ProposalEligibilityServiceInterface
{
    public function check(ProposalType $type, ?string $userId): ProposalEligibilityDTO;
}
