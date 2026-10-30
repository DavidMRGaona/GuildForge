<?php

declare(strict_types=1);

namespace App\Application\Surveys\DTOs;

use InvalidArgumentException;

/**
 * A participant without an account who may answer a survey through a signed link.
 *
 * Sources only offer guests whose email address has been confirmed. The reference is
 * chosen by the source (e.g. its participant id) and must be stable and unique within
 * the subject; the email is only used to deliver the link, never as an identity. The
 * display name is what a named (non-anonymous) survey keeps for the guest's answers.
 */
final readonly class SurveyGuestDTO
{
    public function __construct(
        public string $reference,
        public string $displayName,
        public string $email,
    ) {
        if (trim($reference) === '') {
            throw new InvalidArgumentException('A survey guest needs a reference.');
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Survey guest '{$reference}' has no valid email address.");
        }
    }
}
