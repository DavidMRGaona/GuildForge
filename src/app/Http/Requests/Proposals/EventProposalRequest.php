<?php

declare(strict_types=1);

namespace App\Http\Requests\Proposals;

use App\Application\Proposals\DTOs\EventProposalDataDTO;
use App\Application\Proposals\DTOs\ProposalRequestDefinitionDTO;
use App\Application\Proposals\DTOs\ProposalRequestFieldDTO;
use App\Application\Proposals\Services\EventProposalRequestServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Proposals\Enums\ProposalType;
use Illuminate\Validation\Validator;

/**
 * An event proposal: title, dates and times (as people read them in the display zone), place,
 * description, image, tags and a private note. No prices, download links, slug or publication.
 * Below them, the requests of the enabled modules (core#581): `requests[<key>][wanted]` and
 * `requests[<key>][values][<field>]`, checked against each request's fields. Their errors are
 * keyed `requests.<key>.<field>` (keys hold dots, so they are never read with dot notation).
 */
final class EventProposalRequest extends ProposalRequest
{
    protected function type(): ProposalType
    {
        return ProposalType::Event;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            'description' => ['required', 'string', 'max:100000'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_date' => ['required', 'date_format:Y-m-d'],
            'end_time' => ['required', 'date_format:H:i'],
            'requests' => ['nullable', 'array', 'max:50'],
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['start_date', 'start_time', 'end_date', 'end_time'])) {
                    return;
                }

                // Same zone on both sides, so comparing the wall times as text is exact
                if ($this->wallTime('end') < $this->wallTime('start')) {
                    $validator->errors()->add('end_date', (string) __('proposals.errors.end_before_start'));
                }
            },
            function (Validator $validator): void {
                if ($validator->errors()->has('requests')) {
                    return;
                }

                $this->addRequestErrors($validator);
            },
        ];
    }

    public function toDto(TimezoneServiceInterface $timezone, ?string $imagePublicId): EventProposalDataDTO
    {
        $title = $this->validated('title');
        $description = $this->validated('description');

        return new EventProposalDataDTO(
            title: is_string($title) ? $title : '',
            description: is_string($description) ? $description : '',
            startDate: $timezone->fromDisplayWallTime($this->wallTime('start')),
            endDate: $timezone->fromDisplayWallTime($this->wallTime('end')),
            location: $this->optionalString('location'),
            imagePublicId: $imagePublicId,
            tagIds: $this->tagIds(),
            note: $this->optionalString('note'),
            moduleRequests: $this->submittedRequests(),
        );
    }

    /**
     * What the form sent for the module requests, as it came: the service checks it again.
     *
     * @return array<array-key, mixed>
     */
    private function submittedRequests(): array
    {
        $requests = $this->input('requests');

        return is_array($requests) ? $requests : [];
    }

    private function addRequestErrors(Validator $validator): void
    {
        $service = app(EventProposalRequestServiceInterface::class);
        $errors = $service->check($this->submittedRequests())->errors;

        if ($errors === []) {
            return;
        }

        foreach ($service->definitions() as $definition) {
            foreach ($errors[$definition->key] ?? [] as $name => $code) {
                $field = $this->fieldOf($definition, $name);

                if ($field instanceof ProposalRequestFieldDTO) {
                    $validator->errors()->add('requests.'.$definition->key.'.'.$name, self::requestError($field, $code));
                }
            }
        }
    }

    private function fieldOf(ProposalRequestDefinitionDTO $definition, string $name): ?ProposalRequestFieldDTO
    {
        foreach ($definition->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    public static function requestError(ProposalRequestFieldDTO $field, string $code): string
    {
        $key = 'proposals.requests.errors.'.$code;

        return (string) __(trans()->has($key) ? $key : 'proposals.errors.invalid_requests', [
            'field' => $field->label,
            'min' => $field->min,
            'max' => $field->max,
            'max_length' => $field->maxLength,
        ]);
    }

    private function wallTime(string $prefix): string
    {
        return sprintf('%s %s', $this->validated($prefix.'_date'), $this->validated($prefix.'_time'));
    }
}
