<?php

declare(strict_types=1);

namespace App\Domain\Modules\ValueObjects;

use App\Domain\Modules\Enums\RequirementType;

final readonly class CompatibilityIssue
{
    public const string UNSATISFIED = 'modules.compatibility.reasons.unsatisfied';

    public const string MISSING_EXTENSION = 'modules.compatibility.reasons.missing_extension';

    public const string INVALID_CONSTRAINT = 'modules.compatibility.reasons.invalid_constraint';

    public const string UNKNOWN_HOST_VERSION = 'modules.compatibility.reasons.unknown_host_version';

    public const string MANIFEST_MISSING = 'modules.compatibility.reasons.manifest_missing';

    public const string MANIFEST_INVALID = 'modules.compatibility.reasons.manifest_invalid';

    /**
     * @param  string  $required  Constraint (or extension name) as written in the manifest, '' when not applicable
     * @param  string|null  $found  Host version, when known
     * @param  string  $reasonKey  Translation key, never text
     */
    public function __construct(
        public RequirementType $requirement,
        public string $required,
        public ?string $found,
        public string $reasonKey,
    ) {
    }

    /**
     * English, for logs and exception messages; the panel translates $reasonKey instead.
     */
    public function describe(): string
    {
        $label = $this->requirement->label();

        return match ($this->reasonKey) {
            self::UNSATISFIED => "requires {$label} {$this->required}, found {$this->found}",
            self::MISSING_EXTENSION => "requires PHP extension {$this->required}",
            self::INVALID_CONSTRAINT => "invalid {$label} constraint '{$this->required}'",
            self::UNKNOWN_HOST_VERSION => "requires {$label} {$this->required} but its installed version is unknown",
            self::MANIFEST_MISSING => 'module.json not found',
            default => 'module.json is not valid',
        };
    }

    /**
     * @return array{requirement: string, required: string, found: string|null, reason_key: string}
     */
    public function toArray(): array
    {
        return [
            'requirement' => $this->requirement->value,
            'required' => $this->required,
            'found' => $this->found,
            'reason_key' => $this->reasonKey,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $requirement = is_string($data['requirement'] ?? null) ? RequirementType::tryFrom($data['requirement']) : null;
        $found = $data['found'] ?? null;
        $reasonKey = $data['reason_key'] ?? null;

        return new self(
            requirement: $requirement ?? RequirementType::Manifest,
            required: is_string($data['required'] ?? null) ? $data['required'] : '',
            found: is_string($found) ? $found : null,
            reasonKey: is_string($reasonKey) ? $reasonKey : self::MANIFEST_INVALID,
        );
    }
}
