<?php

declare(strict_types=1);

namespace App\Domain\TwoFactor;

/**
 * Which time steps a code may belong to (RFC 6238, 30-second steps): the current one and one on
 * each side, for clocks a little off, and never one at or before the last step accepted for the
 * account, so a code (or an older one) is never accepted twice.
 *
 * Narrower on purpose than Filament's default of eight steps (about four minutes) on each side.
 */
final readonly class TotpWindowRule
{
    public const int PERIOD_SECONDS = 30;

    public const int WINDOW_STEPS = 1;

    public function timestepAt(int $unixTime): int
    {
        return intdiv($unixTime, self::PERIOD_SECONDS);
    }

    /**
     * The steps to try, the current one first.
     *
     * @return list<int>
     */
    public function candidateSteps(int $unixTime, ?int $lastUsedStep): array
    {
        $current = $this->timestepAt($unixTime);
        $steps = [$current];

        for ($offset = 1; $offset <= self::WINDOW_STEPS; $offset++) {
            $steps[] = $current - $offset;
            $steps[] = $current + $offset;
        }

        return array_values(array_filter(
            $steps,
            static fn (int $step): bool => $step >= 0 && ($lastUsedStep === null || $step > $lastUsedStep),
        ));
    }
}
