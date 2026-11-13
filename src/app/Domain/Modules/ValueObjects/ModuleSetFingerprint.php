<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use App\Domain\Modules\Entities\Module;

/**
 * Which modules, at which versions, a process runs with. Two processes with the same
 * fingerprint have the same module classes available, whatever order the modules came in.
 */
final readonly class ModuleSetFingerprint
{
    private function __construct(
        public string $value,
    ) {}

    /**
     * @param  iterable<Module>  $modules
     */
    public static function of(iterable $modules): self
    {
        $entries = [];

        foreach ($modules as $module) {
            $entries[] = $module->name()->value.'@'.$module->version()->value();
        }

        sort($entries);

        return new self(hash('sha256', implode("\n", $entries)));
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function equals(?self $other): bool
    {
        return $other instanceof self && hash_equals($this->value, $other->value);
    }
}
