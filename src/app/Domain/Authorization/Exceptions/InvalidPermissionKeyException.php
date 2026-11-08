<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Exceptions;

use DomainException;

final class InvalidPermissionKeyException extends DomainException
{
    public static function empty(): self
    {
        return new self('Permission key cannot be empty.');
    }

    public static function invalidFormat(string $value): self
    {
        return new self(
            sprintf(
                "Invalid permission key format: '%s'. Permission key must be in format 'resource.action' or 'module:resource.action'.",
                $value
            )
        );
    }

    /**
     * A registered permission whose key is not "resource.action" (or "module:resource.action").
     */
    public static function notResourceAction(string $key, ?string $module): self
    {
        if ($module !== null) {
            return new self(sprintf(
                'Module "%s" declares the permission "%s", whose name must be in the form "resource.action" (for example "settings.manage").',
                $module,
                $key,
            ));
        }

        return new self(sprintf('The permission "%s" must be in the form "resource.action".', $key));
    }
}
