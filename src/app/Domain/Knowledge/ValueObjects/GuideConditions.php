<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\ValueObjects;

use App\Domain\Knowledge\Exceptions\InvalidGuideDocumentException;

final readonly class GuideConditions
{
    private const array KNOWN_KEYS = ['capabilities', 'association_member', 'modules', 'permissions'];

    /**
     * @param  list<string>  $capabilities
     * @param  list<string>  $negatedCapabilities
     * @param  list<string>  $permissionsAll
     * @param  list<string>  $permissionsAny
     * @param  list<string>  $modules
     */
    public function __construct(
        public array $capabilities = [],
        public array $negatedCapabilities = [],
        public array $permissionsAll = [],
        public array $permissionsAny = [],
        public ?bool $associationMember = null,
        public array $modules = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $frontMatterConditions
     */
    public static function fromArray(array $frontMatterConditions): self
    {
        foreach (array_keys($frontMatterConditions) as $key) {
            if (! in_array($key, self::KNOWN_KEYS, true)) {
                throw InvalidGuideDocumentException::because(sprintf("unknown condition '%s'", $key));
            }
        }

        $capabilities = [];
        $negated = [];
        foreach (self::stringList($frontMatterConditions['capabilities'] ?? [], 'capabilities') as $capability) {
            if (str_starts_with($capability, '!')) {
                $negated[] = substr($capability, 1);
            } else {
                $capabilities[] = $capability;
            }
        }

        $all = [];
        $any = [];
        if (array_key_exists('permissions', $frontMatterConditions)) {
            $permissions = $frontMatterConditions['permissions'];
            if (! is_array($permissions) || $permissions === []) {
                throw InvalidGuideDocumentException::because("'permissions' must define 'all' or 'any'");
            }
            foreach (array_keys($permissions) as $key) {
                if ($key !== 'all' && $key !== 'any') {
                    throw InvalidGuideDocumentException::because("'permissions' must define 'all' or 'any'");
                }
            }
            $all = self::stringList($permissions['all'] ?? [], 'permissions.all');
            $any = self::stringList($permissions['any'] ?? [], 'permissions.any');
        }

        $member = $frontMatterConditions['association_member'] ?? null;
        if ($member !== null && ! is_bool($member)) {
            throw InvalidGuideDocumentException::because("'association_member' must be true or false");
        }

        return new self(
            capabilities: $capabilities,
            negatedCapabilities: $negated,
            permissionsAll: $all,
            permissionsAny: $any,
            associationMember: $member,
            modules: self::stringList($frontMatterConditions['modules'] ?? [], 'modules'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [];

        $capabilities = [
            ...$this->capabilities,
            ...array_map(static fn (string $key): string => '!'.$key, $this->negatedCapabilities),
        ];
        if ($capabilities !== []) {
            $out['capabilities'] = $capabilities;
        }
        if ($this->associationMember !== null) {
            $out['association_member'] = $this->associationMember;
        }
        if ($this->modules !== []) {
            $out['modules'] = $this->modules;
        }

        $permissions = [];
        if ($this->permissionsAll !== []) {
            $permissions['all'] = $this->permissionsAll;
        }
        if ($this->permissionsAny !== []) {
            $permissions['any'] = $this->permissionsAny;
        }
        if ($permissions !== []) {
            $out['permissions'] = $permissions;
        }

        return $out;
    }

    /**
     * Capability keys, normal and negated, without the negation prefix.
     *
     * @return list<string>
     */
    public function capabilityKeys(): array
    {
        return [...$this->capabilities, ...$this->negatedCapabilities];
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, string $field): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw InvalidGuideDocumentException::because(sprintf("'%s' must be a list of strings", $field));
        }

        foreach ($value as $item) {
            if (! is_string($item) || $item === '' || $item === '!') {
                throw InvalidGuideDocumentException::because(sprintf("'%s' must be a list of strings", $field));
            }
        }

        /** @var list<string> $value */
        return $value;
    }
}
