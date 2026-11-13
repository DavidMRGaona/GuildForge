<?php

declare(strict_types=1);

namespace App\Application\Proposals\DTOs;

use App\Domain\Proposals\Enums\ProposalRequestFieldType;
use InvalidArgumentException;

/**
 * One field a module request asks for in an event proposal (core#581): yes/no, a whole number, one
 * of some options or a line of text, with its limits and, optionally, the earlier field it depends
 * on. The core draws it on the web and in the panel and checks every value against it on the
 * server (ProposalRequestValueRule). Build it with the named constructors.
 *
 * A dependent field shows only while the field it names (declared before it, in the same request)
 * holds one of the given values, or, with visibleWhenFilled(), any value other than null, false,
 * 0 or ''. A hidden field is never validated and its value is null.
 *
 * $default only sets what the web form starts with; a field left empty is null (false for yes/no).
 */
final readonly class ProposalRequestFieldDTO
{
    public const string NAME_PATTERN = '/^[a-z][a-z0-9_]{0,39}$/';

    public const int MAX_TEXT_LENGTH = 255;

    /**
     * @param  array<array-key, string>  $options  select only: key => translated label (PHP turns numeric
     *                                             string keys into ints; values always carry the key as a string)
     * @param  ?list<bool|int|string>  $visibleWhenValues  null (with $visibleWhenField): shown while that field is filled
     */
    public function __construct(
        public string $name,
        public ProposalRequestFieldType $type,
        public string $label,
        public bool $required = false,
        public ?string $helperText = null,
        public ?int $min = null,
        public ?int $max = null,
        public ?int $maxLength = null,
        public array $options = [],
        public bool|int|string|null $default = null,
        public ?string $visibleWhenField = null,
        public ?array $visibleWhenValues = null,
    ) {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException(sprintf('The request field name [%s] must be snake_case, at most 40 characters.', $name));
        }

        if (trim($label) === '') {
            throw new InvalidArgumentException(sprintf('The request field [%s] needs a label.', $name));
        }

        match ($type) {
            ProposalRequestFieldType::Boolean => $this->assertBoolean(),
            ProposalRequestFieldType::Number => $this->assertNumber(),
            ProposalRequestFieldType::Select => $this->assertSelect(),
            ProposalRequestFieldType::Text => $this->assertText(),
        };

        if ($visibleWhenField === $name) {
            throw new InvalidArgumentException(sprintf('The request field [%s] cannot depend on itself.', $name));
        }

        if ($visibleWhenField === null && $visibleWhenValues !== null) {
            throw new InvalidArgumentException(sprintf('The request field [%s] has values to depend on but no field.', $name));
        }

        if ($visibleWhenValues === []) {
            throw new InvalidArgumentException(sprintf('The request field [%s] depends on an empty list of values.', $name));
        }
    }

    public static function boolean(string $name, string $label, bool $default = false, ?string $helperText = null): self
    {
        return new self($name, ProposalRequestFieldType::Boolean, $label, helperText: $helperText, default: $default);
    }

    public static function number(
        string $name,
        string $label,
        ?int $min = 0,
        ?int $max = null,
        bool $required = false,
        ?int $default = null,
        ?string $helperText = null,
    ): self {
        return new self($name, ProposalRequestFieldType::Number, $label, $required, $helperText, $min, $max, default: $default);
    }

    /**
     * @param  array<array-key, string>  $options  key => translated label
     */
    public static function select(
        string $name,
        string $label,
        array $options,
        bool $required = false,
        ?string $default = null,
        ?string $helperText = null,
    ): self {
        return new self($name, ProposalRequestFieldType::Select, $label, $required, $helperText, options: $options, default: $default);
    }

    public static function text(
        string $name,
        string $label,
        int $maxLength = self::MAX_TEXT_LENGTH,
        bool $required = false,
        ?string $helperText = null,
    ): self {
        return new self($name, ProposalRequestFieldType::Text, $label, $required, $helperText, maxLength: $maxLength);
    }

    /**
     * Shown only while the earlier field $field holds one of $values.
     */
    public function visibleWhen(string $field, bool|int|string ...$values): self
    {
        return $this->dependingOn($field, array_values($values));
    }

    /**
     * Shown only while the earlier field $field holds something: not null, false, 0 nor ''.
     */
    public function visibleWhenFilled(string $field): self
    {
        return $this->dependingOn($field, null);
    }

    /**
     * Whether the field shows, given the values of the fields declared before it.
     *
     * @param  array<string, bool|int|string|null>  $values
     */
    public function isVisibleWith(array $values): bool
    {
        if ($this->visibleWhenField === null) {
            return true;
        }

        $value = $values[$this->visibleWhenField] ?? null;

        return $this->visibleWhenValues === null
            ? self::isFilled($value)
            : in_array($value, $this->visibleWhenValues, true);
    }

    public static function isFilled(mixed $value): bool
    {
        return ! in_array($value, [null, false, 0, ''], true);
    }

    /**
     * @param  ?list<bool|int|string>  $values
     */
    private function dependingOn(string $field, ?array $values): self
    {
        return new self(
            $this->name,
            $this->type,
            $this->label,
            $this->required,
            $this->helperText,
            $this->min,
            $this->max,
            $this->maxLength,
            $this->options,
            $this->default,
            $field,
            $values,
        );
    }

    private function assertBoolean(): void
    {
        if ($this->required) {
            throw new InvalidArgumentException(sprintf('The yes/no field [%s] cannot be required: no is an answer.', $this->name));
        }

        $this->assertDefault(is_bool($this->default) || $this->default === null);
    }

    private function assertNumber(): void
    {
        if ($this->min !== null && $this->max !== null && $this->min > $this->max) {
            throw new InvalidArgumentException(sprintf('The number field [%s] has a minimum above its maximum.', $this->name));
        }

        $this->assertDefault($this->default === null || (is_int($this->default)
            && ($this->min === null || $this->default >= $this->min)
            && ($this->max === null || $this->default <= $this->max)));
    }

    private function assertSelect(): void
    {
        if ($this->options === []) {
            throw new InvalidArgumentException(sprintf('The select field [%s] needs options.', $this->name));
        }

        foreach ($this->options as $key => $label) {
            // PHP turns numeric string keys into ints: compare them as strings everywhere
            if ((string) $key === '' || trim($label) === '') {
                throw new InvalidArgumentException(sprintf('The select field [%s] has an option without key or label.', $this->name));
            }
        }

        $this->assertDefault($this->default === null || (is_string($this->default) && array_key_exists($this->default, $this->options)));
    }

    private function assertText(): void
    {
        if ($this->maxLength === null || $this->maxLength < 1 || $this->maxLength > self::MAX_TEXT_LENGTH) {
            throw new InvalidArgumentException(sprintf('The text field [%s] must allow between 1 and %d characters.', $this->name, self::MAX_TEXT_LENGTH));
        }

        $this->assertDefault($this->default === null || (is_string($this->default) && mb_strlen($this->default) <= $this->maxLength));
    }

    private function assertDefault(bool $valid): void
    {
        if (! $valid) {
            throw new InvalidArgumentException(sprintf('The default of the request field [%s] does not fit the field.', $this->name));
        }
    }
}
