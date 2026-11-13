<?php

declare(strict_types=1);

namespace App\Infrastructure\Proposals\Services;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Proposals\Contracts\EventProposalRequestInterface;
use App\Application\Proposals\DTOs\ProposalRequestDefinitionDTO;
use App\Application\Proposals\DTOs\ProposalRequestFieldDTO;
use App\Application\Proposals\DTOs\ProposalRequestSubmissionDTO;
use App\Application\Proposals\DTOs\StoredProposalRequestDTO;
use App\Application\Proposals\Services\EventProposalRequestRegistryInterface;
use App\Application\Proposals\Services\EventProposalRequestServiceInterface;
use App\Application\Proposals\Services\ProposalRequestValueRule;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Domain\Proposals\Exceptions\InvalidProposalDataException;
use App\Domain\Proposals\Exceptions\InvalidProposalTransitionException;
use App\Domain\Proposals\Exceptions\ProposalNotAllowedException;
use App\Domain\Proposals\Exceptions\ProposalNotFoundException;
use App\Domain\Proposals\Exceptions\ProposalRequestNotAppliedException;
use App\Domain\Proposals\Exceptions\ProposalRequestRefusedException;
use App\Domain\ValueObjects\EventId;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventProposalRequestModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final readonly class EventProposalRequestService implements EventProposalRequestServiceInterface
{
    public function __construct(
        private EventProposalRequestRegistryInterface $registry,
        private ProposalRequestValueRule $rule,
        private AuthorizationServiceInterface $authorization,
    ) {}

    public function definitions(): array
    {
        $definitions = [];

        foreach ($this->registry->requests() as $key => $request) {
            $definition = $this->guard($request, 'offer', function () use ($key, $request): ?ProposalRequestDefinitionDTO {
                if (! $request->isAvailable()) {
                    return null;
                }

                return new ProposalRequestDefinitionDTO($key, $request->label(), $request->question(), $request->fields());
            });

            if ($definition instanceof ProposalRequestDefinitionDTO) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    public function check(array $submitted): ProposalRequestSubmissionDTO
    {
        $requests = [];
        $errors = [];

        foreach ($this->definitions() as $definition) {
            $entry = $submitted[$definition->key] ?? null;

            if (! is_array($entry) || ! $this->isTicked($entry['wanted'] ?? null)) {
                continue;
            }

            $values = $entry['values'] ?? [];
            $result = $this->rule->check($definition->fields, is_array($values) ? $values : []);

            if ($result->isValid()) {
                $requests[$definition->key] = $result->values;
            } else {
                $errors[$definition->key] = $result->errors;
            }
        }

        return new ProposalRequestSubmissionDTO($requests, $errors);
    }

    public function saveRequested(string $eventId, array $submitted): void
    {
        $submission = $this->check($submitted);

        if (! $submission->isValid()) {
            throw InvalidProposalDataException::field('requests');
        }

        foreach ($this->definitions() as $definition) {
            $values = $submission->requests[$definition->key] ?? null;

            if ($values === null) {
                $this->rows($eventId)->where('request_key', $definition->key)->delete();

                continue;
            }

            $row = $this->rows($eventId)->where('request_key', $definition->key)->first();

            // Unchanged (a returned draft saved again): what the reviewer decided stays
            if ($row instanceof EventProposalRequestModel && $row->requested_values === $values) {
                continue;
            }

            // New or changed: a new request, so any earlier review starts over
            EventProposalRequestModel::query()->updateOrCreate(
                ['event_id' => $eventId, 'request_key' => $definition->key],
                ['requested_values' => $values, 'approved_values' => null, 'apply' => true, 'applied_at' => null],
            );
        }
    }

    public function forEvent(string $eventId): array
    {
        if (! Str::isUuid($eventId)) {
            return [];
        }

        $requests = $this->registry->requests();
        $positions = array_flip(array_keys($requests));

        return array_values($this->rows($eventId)->get()
            ->sortBy(static fn (EventProposalRequestModel $row): array => [$positions[$row->request_key] ?? PHP_INT_MAX, $row->request_key])
            ->map(fn (EventProposalRequestModel $row): StoredProposalRequestDTO => $this->toDto($row, $requests[$row->request_key] ?? null))
            ->all());
    }

    public function review(string $reviewerId, string $eventId, array $decisions): void
    {
        $reviewer = Str::isUuid($reviewerId) ? UserModel::query()->find($reviewerId) : null;

        if (! $reviewer instanceof UserModel || ! $this->authorization->can($reviewer, ProposalType::Event->reviewPermission())) {
            throw ProposalNotAllowedException::because('review_denied');
        }

        DB::transaction(function () use ($eventId, $decisions): void {
            $this->assertAwaitsReview($eventId);
            $requests = $this->registry->requests();

            foreach ($this->rows($eventId)->lockForUpdate()->get() as $row) {
                $decision = $decisions[$row->request_key] ?? null;

                if (! is_array($decision)) {
                    continue;
                }

                $row->apply = $this->isTicked($decision['apply'] ?? null);
                $fields = $this->activeFields($requests[$row->request_key] ?? null);

                // Inactive: nothing to check the values against, so they stay as they are
                if ($fields !== null) {
                    $values = $decision['values'] ?? [];
                    $result = $this->rule->check($fields, is_array($values) ? $values : []);

                    if (! $result->isValid()) {
                        throw InvalidProposalDataException::field('requests');
                    }

                    $row->approved_values = $result->values;
                }

                $row->save();
            }
        });
    }

    public function applyTicked(string $eventId, bool $directly): void
    {
        $requests = $this->registry->requests();
        $positions = array_flip(array_keys($requests));

        $rows = $this->rows($eventId)
            ->where('apply', true)
            ->whereNull('applied_at')
            ->lockForUpdate()
            ->get()
            ->sortBy(static fn (EventProposalRequestModel $row): array => [$positions[$row->request_key] ?? PHP_INT_MAX, $row->request_key]);

        foreach ($rows as $row) {
            $request = $requests[$row->request_key] ?? null;
            $fields = $this->activeFields($request);

            if (! $request instanceof EventProposalRequestInterface || $fields === null) {
                throw ProposalRequestNotAppliedException::unavailable($this->labelOf($request, $row->request_key));
            }

            $label = $this->labelOf($request, $row->request_key);
            // Checked again: an option may have gone since it was asked for or approved
            $result = $this->rule->check($fields, $directly ? $row->requested_values : ($row->approved_values ?? $row->requested_values));

            if (! $result->isValid()) {
                throw ProposalRequestNotAppliedException::invalid($label);
            }

            try {
                $request->apply(EventId::fromString($eventId), $result->values);
            } catch (Throwable $exception) {
                if (! $exception instanceof ProposalRequestRefusedException) {
                    report($exception);
                }

                throw ProposalRequestNotAppliedException::failed($label, $exception);
            }

            $row->forceFill(['approved_values' => $result->values, 'applied_at' => now()])->save();
        }
    }

    /**
     * @return Builder<EventProposalRequestModel>
     */
    private function rows(string $eventId): Builder
    {
        return EventProposalRequestModel::query()->where('event_id', $eventId);
    }

    private function assertAwaitsReview(string $eventId): void
    {
        $event = Str::isUuid($eventId)
            ? EventModel::query()->includingUnsubmittedProposals()->whereNotNull('proposal_status')->whereKey($eventId)->lockForUpdate()->first()
            : null;

        if (! $event instanceof EventModel) {
            throw ProposalNotFoundException::withId($eventId);
        }

        $status = $event->proposalStatus();

        if ($status !== ProposalStatus::PendingReview) {
            throw InvalidProposalTransitionException::from($status ?? ProposalStatus::Approved, 'reviewed');
        }
    }

    private function toDto(EventProposalRequestModel $row, ?EventProposalRequestInterface $request): StoredProposalRequestDTO
    {
        $fields = $this->activeFields($request);
        $active = $request instanceof EventProposalRequestInterface && $fields !== null;
        $inForce = $row->approved_values ?? $row->requested_values;

        $description = $active
            ? $this->guard($request, 'describe', static function () use ($request, $inForce): ?string {
                $line = trim($request->describe($inForce));

                return $line !== '' ? $line : null;
            })
            : null;

        return new StoredProposalRequestDTO(
            id: $row->id,
            key: $row->request_key,
            label: $this->labelOf($request, $row->request_key),
            active: $active,
            requestedValues: $row->requested_values,
            approvedValues: $row->approved_values,
            apply: $row->apply,
            appliedAt: $row->applied_at !== null ? DateTimeImmutable::createFromInterface($row->applied_at) : null,
            fields: $fields ?? [],
            description: is_string($description) ? $description : null,
        );
    }

    /**
     * The request's fields while it is offered (available and readable), null otherwise.
     *
     * @return ?list<ProposalRequestFieldDTO>
     */
    private function activeFields(?EventProposalRequestInterface $request): ?array
    {
        if (! $request instanceof EventProposalRequestInterface) {
            return null;
        }

        $fields = $this->guard($request, 'read', static fn (): ?array => $request->isAvailable() ? $request->fields() : null);

        /** @var ?list<ProposalRequestFieldDTO> $fields */
        return is_array($fields) ? $fields : null;
    }

    private function labelOf(?EventProposalRequestInterface $request, string $key): string
    {
        if (! $request instanceof EventProposalRequestInterface) {
            return $key;
        }

        $label = $this->guard($request, 'label', static fn (): string => $request->label());

        return is_string($label) && trim($label) !== '' ? $label : $key;
    }

    private function isTicked(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on'], true);
    }

    /**
     * Runs a call into a module request; whatever it throws is logged and gives null.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return T|null
     */
    private function guard(EventProposalRequestInterface $request, string $what, callable $call): mixed
    {
        try {
            return $call();
        } catch (Throwable $exception) {
            Log::warning('[EventProposalRequests] A module request failed; it is left out', [
                'request' => $request::class,
                'call' => $what,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
