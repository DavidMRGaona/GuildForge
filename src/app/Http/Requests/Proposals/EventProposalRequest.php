<?php

declare(strict_types=1);

namespace App\Http\Requests\Proposals;

use App\Application\Proposals\DTOs\EventProposalDataDTO;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Proposals\Enums\ProposalType;
use Illuminate\Validation\Validator;

/**
 * An event proposal: title, dates and times (as people read them in the display zone), place,
 * description, image, tags and a private note. No prices, download links, slug or publication.
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
        );
    }

    private function wallTime(string $prefix): string
    {
        return sprintf('%s %s', $this->validated($prefix.'_date'), $this->validated($prefix.'_time'));
    }
}
