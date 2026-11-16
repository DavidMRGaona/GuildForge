<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\TwoFactor;

use App\Domain\TwoFactor\TotpWindowRule;
use PHPUnit\Framework\TestCase;

final class TotpWindowRuleTest extends TestCase
{
    public function test_steps_are_thirty_seconds_since_the_epoch(): void
    {
        $rule = new TotpWindowRule;

        $this->assertSame(0, $rule->timestepAt(0));
        $this->assertSame(0, $rule->timestepAt(29));
        $this->assertSame(1, $rule->timestepAt(30));
        $this->assertSame(56666666, $rule->timestepAt(1_700_000_000));
    }

    public function test_it_accepts_the_current_step_and_one_on_each_side_current_first(): void
    {
        $this->assertSame([100, 99, 101], (new TotpWindowRule)->candidateSteps(100 * 30 + 7, null));
    }

    public function test_never_a_step_already_used_or_earlier(): void
    {
        $rule = new TotpWindowRule;
        $now = 100 * 30;

        $this->assertSame([100, 101], $rule->candidateSteps($now, 99));
        $this->assertSame([101], $rule->candidateSteps($now, 100));
        $this->assertSame([], $rule->candidateSteps($now, 101));
        $this->assertSame([], $rule->candidateSteps($now, 500));
        $this->assertSame([100, 99, 101], $rule->candidateSteps($now, 50));
    }

    public function test_no_negative_step_before_the_epoch(): void
    {
        $this->assertSame([0, 1], (new TotpWindowRule)->candidateSteps(3, null));
    }
}
