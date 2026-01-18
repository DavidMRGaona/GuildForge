<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use App\Domain\Exceptions\InvalidPriceException;

final readonly class Price
{
    public function __construct(
        public float $value
    ) {
        if ($value < 0) {
            throw InvalidPriceException::create();
        }
    }

    public function isFree(): bool
    {
        return $this->value === 0.0;
    }
}
