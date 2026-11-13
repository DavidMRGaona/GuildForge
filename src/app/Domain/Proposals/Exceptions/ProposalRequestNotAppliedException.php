<?php

declare(strict_types=1);

namespace App\Domain\Proposals\Exceptions;

use Throwable;

/**
 * A module request ticked to be applied could not be (core#581), so nothing was approved:
 * - request_unavailable: its module is no longer active, or the request no longer is available;
 *   untick it to approve without it;
 * - request_invalid: its values no longer fit what the request accepts (an option gone);
 * - request_failed: its apply() threw. $detail is the module's own message when it refused with
 *   ProposalRequestRefusedException, null for anything else (reported instead).
 */
final class ProposalRequestNotAppliedException extends ProposalException
{
    public private(set) string $requestLabel = '';

    public private(set) ?string $detail = null;

    public static function unavailable(string $requestLabel): self
    {
        return self::make('request_unavailable', $requestLabel, null, 'The module request is not available.');
    }

    public static function invalid(string $requestLabel): self
    {
        return self::make('request_invalid', $requestLabel, null, 'The module request values are not valid.');
    }

    public static function failed(string $requestLabel, Throwable $previous): self
    {
        $detail = $previous instanceof ProposalRequestRefusedException ? $previous->getMessage() : null;

        return self::make('request_failed', $requestLabel, $detail, 'The module request could not be applied: '.$previous->getMessage());
    }

    private static function make(string $reason, string $requestLabel, ?string $detail, string $message): self
    {
        $exception = new self($reason, $message);
        $exception->requestLabel = $requestLabel;
        $exception->detail = $detail;

        return $exception;
    }
}
