<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\Proposals\DTOs\ProposalRequestDefinitionDTO;
use App\Application\Proposals\DTOs\ProposalRequestFieldDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A module request the event proposal form draws (core#581): the question that opens it and its
 * fields, with their limits and dependencies. The server checks every value again.
 *
 * @property ProposalRequestDefinitionDTO $resource
 */
final class ProposalRequestDefinitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource->key,
            'label' => $this->resource->label,
            'question' => $this->resource->question,
            'fields' => array_map($this->field(...), $this->resource->fields),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function field(ProposalRequestFieldDTO $field): array
    {
        $options = [];

        foreach ($field->options as $value => $label) {
            $options[] = ['value' => (string) $value, 'label' => $label];
        }

        return [
            'name' => $field->name,
            'type' => $field->type->value,
            'label' => $field->label,
            'required' => $field->required,
            'helperText' => $field->helperText,
            'min' => $field->min,
            'max' => $field->max,
            'maxLength' => $field->maxLength,
            'options' => $options,
            'default' => $field->default,
            'visibleWhen' => $field->visibleWhenField === null ? null : [
                'field' => $field->visibleWhenField,
                // null: while that field is filled
                'values' => $field->visibleWhenValues,
            ],
        ];
    }
}
