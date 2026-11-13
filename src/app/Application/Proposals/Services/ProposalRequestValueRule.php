<?php

declare(strict_types=1);

namespace App\Application\Proposals\Services;

use App\Application\Proposals\DTOs\ProposalRequestFieldDTO;
use App\Application\Proposals\DTOs\ProposalRequestValuesDTO;
use App\Domain\Proposals\Enums\ProposalRequestFieldType;

/**
 * The only rule for the values of a module request (core#581), used by the web form, the panel
 * and the approval alike: what is not a declared field is dropped, every value is cast to its
 * type and checked against its limits, and a field whose dependency hides it is null and never
 * refused. Browsers send text ("1", "20"), so strings of the right shape are accepted.
 */
final class ProposalRequestValueRule
{
    /**
     * @param  list<ProposalRequestFieldDTO>  $fields
     * @param  array<array-key, mixed>  $input  field name => sent value
     */
    public function check(array $fields, array $input): ProposalRequestValuesDTO
    {
        $values = [];
        $errors = [];

        // In order: a dependency names an earlier field, so its clean value is already known
        foreach ($fields as $field) {
            if (! $field->isVisibleWith($values)) {
                $values[$field->name] = null;

                continue;
            }

            [$value, $error] = $this->clean($field, $input[$field->name] ?? null);

            if ($error === null && $value === null && $field->required) {
                $error = 'required';
            }

            $values[$field->name] = $error === null ? $value : null;

            if ($error !== null) {
                $errors[$field->name] = $error;
            }
        }

        return new ProposalRequestValuesDTO($values, $errors);
    }

    /**
     * The names of the fields that show with these values, in order (what check() validates).
     *
     * @param  list<ProposalRequestFieldDTO>  $fields
     * @param  array<array-key, mixed>  $input
     * @return list<string>
     */
    public function visibleFields(array $fields, array $input): array
    {
        $values = [];
        $visible = [];

        foreach ($fields as $field) {
            if ($field->isVisibleWith($values)) {
                $visible[] = $field->name;
                $values[$field->name] = $this->clean($field, $input[$field->name] ?? null)[0];
            } else {
                $values[$field->name] = null;
            }
        }

        return $visible;
    }

    /**
     * @return array{0: bool|int|string|null, 1: ?string}
     */
    private function clean(ProposalRequestFieldDTO $field, mixed $value): array
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return match ($field->type) {
            ProposalRequestFieldType::Boolean => $this->boolean($value),
            ProposalRequestFieldType::Number => $this->number($field, $value),
            ProposalRequestFieldType::Select => $this->option($field, $value),
            ProposalRequestFieldType::Text => $this->text($field, $value),
        };
    }

    /**
     * @return array{0: ?bool, 1: ?string}
     */
    private function boolean(mixed $value): array
    {
        return match (true) {
            $value === null, $value === '', $value === false, $value === 0, $value === '0', $value === 'false' => [false, null],
            $value === true, $value === 1, $value === '1', $value === 'true' => [true, null],
            default => [null, 'boolean'],
        };
    }

    /**
     * @return array{0: ?int, 1: ?string}
     */
    private function number(ProposalRequestFieldDTO $field, mixed $value): array
    {
        if ($value === null || $value === '') {
            return [null, null];
        }

        if (is_string($value) && preg_match('/^-?\d{1,15}$/', $value) === 1) {
            $value = (int) $value;
        }

        if (! is_int($value)) {
            return [null, 'integer'];
        }

        return match (true) {
            $field->min !== null && $value < $field->min => [null, 'min'],
            $field->max !== null && $value > $field->max => [null, 'max'],
            default => [$value, null],
        };
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function option(ProposalRequestFieldDTO $field, mixed $value): array
    {
        if ($value === null || $value === '') {
            return [null, null];
        }

        if (! is_string($value) && ! is_int($value)) {
            return [null, 'option'];
        }

        $key = (string) $value;

        foreach (array_keys($field->options) as $option) {
            if ((string) $option === $key) {
                return [$key, null];
            }
        }

        return [null, 'option'];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function text(ProposalRequestFieldDTO $field, mixed $value): array
    {
        if ($value === null || $value === '') {
            return [null, null];
        }

        if (! is_string($value)) {
            return [null, 'text'];
        }

        // One line: the core shows it as plain text, never as HTML
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);

        if ($value === null) {
            return [null, 'text'];
        }

        $value = trim($value);

        if ($value === '') {
            return [null, null];
        }

        return mb_strlen($value) > (int) $field->maxLength ? [null, 'max_length'] : [$value, null];
    }
}
