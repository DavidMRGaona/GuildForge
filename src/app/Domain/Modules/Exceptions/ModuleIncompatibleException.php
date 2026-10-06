<?php

declare(strict_types=1);

namespace App\Domain\Modules\Exceptions;

use App\Domain\Modules\ValueObjects\CompatibilityIssue;
use App\Domain\Modules\ValueObjects\CompatibilityResult;
use DomainException;

final class ModuleIncompatibleException extends DomainException
{
    /**
     * @param  list<CompatibilityIssue>  $issues
     */
    private function __construct(
        string $message,
        public readonly string $moduleName,
        public readonly ?string $version,
        public readonly array $issues,
    ) {
        parent::__construct($message);
    }

    public static function forModule(string $moduleName, ?string $version, CompatibilityResult $result): self
    {
        $subject = $version === null ? "Module \"{$moduleName}\"" : "Module \"{$moduleName}\" {$version}";

        return new self("{$subject} is not compatible with this site: {$result->summary()}", $moduleName, $version, $result->issues);
    }
}
