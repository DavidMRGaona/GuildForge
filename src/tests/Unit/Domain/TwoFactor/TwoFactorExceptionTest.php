<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\TwoFactor;

use App\Domain\TwoFactor\Exceptions\TwoFactorException;
use App\Domain\TwoFactor\Exceptions\TwoFactorUnavailableException;
use App\Domain\TwoFactor\TwoFactorFailure;
use PHPUnit\Framework\TestCase;

final class TwoFactorExceptionTest extends TestCase
{
    public function test_unavailable_is_always_the_subclass_callers_recognise(): void
    {
        $exception = TwoFactorException::because(TwoFactorFailure::Unavailable);

        $this->assertInstanceOf(TwoFactorUnavailableException::class, $exception);
        $this->assertSame(TwoFactorFailure::Unavailable, $exception->reason);
        $this->assertNull($exception->getPrevious());
    }

    public function test_any_other_reason_is_a_refusal(): void
    {
        foreach (TwoFactorFailure::cases() as $reason) {
            if ($reason === TwoFactorFailure::Unavailable) {
                continue;
            }

            $exception = TwoFactorException::because($reason);

            $this->assertNotInstanceOf(TwoFactorUnavailableException::class, $exception);
            $this->assertSame($reason, $exception->reason);
            $this->assertStringContainsString($reason->value, $exception->getMessage());
        }
    }
}
