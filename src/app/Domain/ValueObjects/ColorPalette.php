<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class ColorPalette
{
    private const array REQUIRED_SHADES = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

    /**
     * @param array<int, HexColor> $shades
     */
    public function __construct(
        private string $name,
        private array $shades,
    ) {
        $this->validate($shades);
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array<int, HexColor>
     */
    public function shades(): array
    {
        return $this->shades;
    }

    public function shade(int $key): ?HexColor
    {
        return $this->shades[$key] ?? null;
    }

    /**
     * Get the base color (shade 600).
     */
    public function baseColor(): HexColor
    {
        return $this->shades[600];
    }

    /**
     * Generate CSS variables with hex values.
     */
    public function toCssVariables(): string
    {
        $lines = [];

        foreach ($this->shades as $shade => $color) {
            $lines[] = "--color-{$this->name}-{$shade}: {$color->value};";
        }

        return implode("\n", $lines);
    }

    /**
     * Generate CSS variables with OKLCH values.
     */
    public function toOklchCssVariables(): string
    {
        $lines = [];

        foreach ($this->shades as $shade => $color) {
            $oklch = $color->toOklch();
            $l = round($oklch['l'], 4);
            $c = round($oklch['c'], 4);
            $h = round($oklch['h'], 2);
            $lines[] = "--color-{$this->name}-{$shade}: oklch({$l} {$c} {$h});";
        }

        return implode("\n", $lines);
    }

    /**
     * Convert to array representation.
     *
     * @return array{name: string, shades: array<int, string>}
     */
    public function toArray(): array
    {
        $shades = [];

        foreach ($this->shades as $key => $color) {
            $shades[$key] = $color->value;
        }

        return [
            'name' => $this->name,
            'shades' => $shades,
        ];
    }

    public function equals(self $other): bool
    {
        if ($this->name !== $other->name) {
            return false;
        }

        foreach ($this->shades as $key => $color) {
            if (! isset($other->shades[$key]) || ! $color->equals($other->shades[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, HexColor> $shades
     */
    private function validate(array $shades): void
    {
        $providedKeys = array_keys($shades);
        sort($providedKeys);

        if ($providedKeys !== self::REQUIRED_SHADES) {
            throw new InvalidArgumentException(
                sprintf(
                    'ColorPalette requires shades: %s. Got: %s',
                    implode(', ', self::REQUIRED_SHADES),
                    implode(', ', $providedKeys)
                )
            );
        }
    }
}
