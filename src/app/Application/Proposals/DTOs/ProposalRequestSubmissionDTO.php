<?php

declare(strict_types=1);

namespace App\Application\Proposals\DTOs;

/**
 * What EventProposalRequestServiceInterface::check() made of the requests a form sent: the clean
 * values of every request asked for, and the refused fields. Requests not offered (unknown, of a
 * module that is not enabled, unavailable) are left out of both.
 */
final readonly class ProposalRequestSubmissionDTO
{
    /**
     * @param  array<string, array<string, bool|int|string|null>>  $requests  key => values, only the ones asked for
     * @param  array<string, array<string, string>>  $errors  key => field => code (see ProposalRequestValuesDTO)
     */
    public function __construct(
        public array $requests,
        public array $errors,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
