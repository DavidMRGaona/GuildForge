<?php

declare(strict_types=1);

namespace App\Console\Concerns;

use Illuminate\Console\Command;

/**
 * Arguments and options with their type: Laravel types them as any console value
 * (array|bool|float|int|string|null), while a command declares what each one is.
 *
 * @phpstan-require-extends Command
 */
trait ReadsTypedInput
{
    /** A string argument; '' when it was not given */
    protected function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }

    /** An option that takes a value; null when it was not given */
    protected function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : null;
    }

    /** A switch (an option without a value) */
    protected function flag(string $name): bool
    {
        return $this->option($name) === true;
    }

    /**
     * An option that may be repeated
     *
     * @return list<string>
     */
    protected function stringListOption(string $name): array
    {
        $value = $this->option($name);

        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }
}
