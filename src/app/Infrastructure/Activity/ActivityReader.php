<?php

declare(strict_types=1);

namespace App\Infrastructure\Activity;

use App\Application\Activity\ActivityContractChecks;
use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\Contracts\OffersActivityAttributes;
use App\Application\Activity\DTOs\ActivityAttributeDTO;
use App\Application\Activity\DTOs\ActivityAttributeValueDTO;
use App\Application\Activity\DTOs\ActivityDescriptionsResultDTO;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\DTOs\ActivityFactsResultDTO;
use App\Application\Activity\DTOs\ActivityHistoryPageDTO;
use App\Application\Activity\DTOs\ActivityHistoryResultDTO;
use App\Application\Activity\DTOs\ActivitySourceRegistrationDTO;
use App\Application\Activity\DTOs\ActivitySourceSummaryDTO;
use App\Application\Activity\DTOs\ActivitySubjectDTO;
use App\Application\Activity\DTOs\ActivitySubjectFactsDTO;
use App\Application\Activity\Services\ActivityReaderInterface;
use App\Application\Activity\Services\ActivitySourceRegistryInterface;
use App\Domain\Activity\ActivityIdentifierRule;
use App\Domain\SiteBlocks\LinkTargetRule;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Support\DailyWarnings;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use Throwable;

/**
 * Reads activity sources for their consumers (core#774), failing closed per source: each call builds
 * the source from the container (so it never carries one request's state into another), catches
 * anything it throws and checks what it returns against the DTOs' rules. Anything wrong is a
 * `Failed` result, warned once a day per type and reason, never an empty answer: a consumer only
 * replaces what it keeps with what was read. `Missing` is only for a type no module registers.
 * Facts of anonymized accounts and of bots (core#863: not people) are dropped (one query per call),
 * so a recalculation never brings back what was erased; deactivated accounts keep theirs. Bound scoped, so a long-running worker
 * warns again the next day.
 */
final readonly class ActivityReader implements ActivityReaderInterface
{
    /** How many account ids one query asks about */
    private const int ACCOUNT_QUERY_CHUNK = 1000;

    private DailyWarnings $warnings;

    /**
     * @param  Cache|null  $cache  where warnings are remembered; the default store, asked for only when warning
     */
    public function __construct(
        private ActivitySourceRegistryInterface $registry,
        private Application $container,
        ?Cache $cache = null,
    ) {
        $this->warnings = new DailyWarnings(
            ActivitySourceRegistry::WARNING_PREFIX,
            fn (): Cache => $cache ?? $this->container->make(Cache::class),
        );
    }

    public function sources(): array
    {
        $summaries = [];

        foreach ($this->registry->all() as $registration) {
            $source = $this->build($registration);
            if (! $source instanceof ActivitySourceInterface) {
                continue;
            }

            try {
                $label = $source->label();
            } catch (Throwable $exception) {
                $this->warn($registration, 'label', $exception);

                continue;
            }

            if (trim($label) === '') {
                $this->warn($registration, 'label');

                continue;
            }

            $summaries[] = new ActivitySourceSummaryDTO($registration->type, $registration->kind, $label, $registration->module);
        }

        return $summaries;
    }

    public function facts(string $type, string $subjectId): ActivityFactsResultDTO
    {
        $registration = $this->registry->find($type);
        if (! $registration instanceof ActivitySourceRegistrationDTO) {
            return $this->registry->isUnavailable($type) ? ActivityFactsResultDTO::failed() : ActivityFactsResultDTO::missing();
        }

        $subjectId = ActivityIdentifierRule::stableId($subjectId);

        // Nothing the publisher lets through carries such an id: no subject has it
        if ($subjectId === null) {
            return ActivityFactsResultDTO::read([]);
        }

        $source = $this->build($registration);
        if (! $source instanceof ActivitySourceInterface) {
            return ActivityFactsResultDTO::failed();
        }

        try {
            $facts = $source->facts($subjectId);
        } catch (Throwable $exception) {
            $this->warn($registration, 'facts', $exception);

            return ActivityFactsResultDTO::failed();
        }

        try {
            $subject = new ActivitySubjectFactsDTO($subjectId, $facts);
        } catch (InvalidArgumentException $exception) {
            $this->warn($registration, 'invalid_facts', $exception);

            return ActivityFactsResultDTO::failed();
        }

        $kept = $this->withoutAnonymizedAccounts($registration, [$subject]);

        return $kept === null ? ActivityFactsResultDTO::failed() : ActivityFactsResultDTO::read($kept[0]->facts);
    }

    public function history(string $type, ?string $cursor, int $limit): ActivityHistoryResultDTO
    {
        $registration = $this->registry->find($type);
        if (! $registration instanceof ActivitySourceRegistrationDTO) {
            return $this->registry->isUnavailable($type) ? ActivityHistoryResultDTO::failed() : ActivityHistoryResultDTO::missing();
        }

        $source = $this->build($registration);
        if (! $source instanceof ActivitySourceInterface) {
            return ActivityHistoryResultDTO::failed();
        }

        $limit = max(1, min($limit, ActivityHistoryPageDTO::MAX_LIMIT));

        try {
            $page = $source->history($cursor, $limit);
        } catch (Throwable $exception) {
            $this->warn($registration, 'history', $exception);

            return ActivityHistoryResultDTO::failed();
        }

        // A page that points back to itself would make a walk go round forever
        if (count($page->subjects) > $limit || ($cursor !== null && $page->nextCursor === $cursor)) {
            $this->warn($registration, 'invalid_page');

            return ActivityHistoryResultDTO::failed();
        }

        $subjects = $this->withoutAnonymizedAccounts($registration, $page->subjects);

        return $subjects === null ? ActivityHistoryResultDTO::failed() : ActivityHistoryResultDTO::read(new ActivityHistoryPageDTO($subjects, $page->nextCursor));
    }

    public function describe(string $type, array $subjectIds, ?Authenticatable $viewer): ActivityDescriptionsResultDTO
    {
        $registration = $this->registry->find($type);
        if (! $registration instanceof ActivitySourceRegistrationDTO) {
            return $this->registry->isUnavailable($type) ? ActivityDescriptionsResultDTO::failed() : ActivityDescriptionsResultDTO::missing();
        }

        $asked = array_values(array_unique(array_filter(
            array_map(ActivityIdentifierRule::stableId(...), $subjectIds),
            static fn (?string $id): bool => $id !== null,
        )));

        if ($asked === []) {
            return ActivityDescriptionsResultDTO::read([]);
        }

        $source = $this->build($registration);
        if (! $source instanceof ActivitySourceInterface) {
            return ActivityDescriptionsResultDTO::failed();
        }

        try {
            $described = $source->describe($asked, $viewer);
        } catch (Throwable $exception) {
            $this->warn($registration, 'describe', $exception);

            return ActivityDescriptionsResultDTO::failed();
        }

        $wanted = array_fill_keys($asked, true);
        $subjects = [];
        $malformed = 0;

        foreach ($described as $key => $subject) {
            $key = ActivityIdentifierRule::stableId((string) $key);

            // Somebody else's subject would be shown under this one's id
            if (! $this->isSubject($subject) || $subject->subjectId !== $key) {
                $malformed++;

                continue;
            }

            // Only what was asked, each once: never more than the caller knows exists
            if (! isset($wanted[$key])) {
                continue;
            }

            unset($wanted[$key]);
            $link = $subject->url === null ? null : $this->link($subject->url);
            $subjects[] = $link === $subject->url ? $subject : new ActivitySubjectDTO($subject->subjectId, $subject->title, $link);
        }

        if ($malformed > 0) {
            $this->warn($registration, 'invalid_description');

            // Nothing well formed at all (a list instead of a map keyed by id): the source is broken
            if ($malformed === count($described)) {
                return ActivityDescriptionsResultDTO::failed();
            }
        }

        return ActivityDescriptionsResultDTO::read($subjects);
    }

    public function attributes(string $type): array
    {
        $source = $this->attributeSource($type);
        if ($source === null) {
            return [];
        }

        try {
            $attributes = $source[1]->attributes();
        } catch (Throwable $exception) {
            $this->warn($source[0], 'attributes', $exception);

            return [];
        }

        return ActivityContractChecks::isListOf($attributes, ActivityAttributeDTO::class) ? $attributes : $this->malformed($source[0], 'attributes');
    }

    public function attributeValues(string $type, string $key): array
    {
        $source = $this->attributeSource($type);
        if ($source === null) {
            return [];
        }

        try {
            $values = $source[1]->attributeValues($key);
        } catch (Throwable $exception) {
            $this->warn($source[0], 'attribute_values', $exception);

            return [];
        }

        return ActivityContractChecks::isListOf($values, ActivityAttributeValueDTO::class) ? $values : $this->malformed($source[0], 'attribute_values');
    }

    /**
     * The source of a type when it offers attributes, with its registration.
     *
     * @return array{ActivitySourceRegistrationDTO, OffersActivityAttributes}|null
     */
    private function attributeSource(string $type): ?array
    {
        $registration = $this->registry->find($type);
        if (! $registration instanceof ActivitySourceRegistrationDTO) {
            return null;
        }

        $source = $this->build($registration);

        return $source instanceof OffersActivityAttributes ? [$registration, $source] : null;
    }

    /**
     * Built for this call; null (with a warning) when it cannot be built or is no longer what was registered.
     */
    private function build(ActivitySourceRegistrationDTO $registration): ?ActivitySourceInterface
    {
        try {
            $source = $this->container->make($registration->class);

            if ($source instanceof ActivitySourceInterface && $source->type() === $registration->type) {
                return $source;
            }
        } catch (Throwable $exception) {
            $this->warn($registration, 'build', $exception);

            return null;
        }

        $this->warn($registration, 'type_changed');

        return null;
    }

    private function isSubject(mixed $subject): bool
    {
        return $subject instanceof ActivitySubjectDTO;
    }

    /** The link as LinkTargetRule normalises it, or null when it is refused; http only where the site itself runs on it */
    private function link(string $url): ?string
    {
        return LinkTargetRule::normalize($url, $this->container->environment('local', 'testing'));
    }

    /**
     * The subjects without the facts of anonymized accounts and bots, or null (warned) when that cannot be
     * known: never keep what may have been erased.
     *
     * @param  list<ActivitySubjectFactsDTO>  $subjects
     * @return list<ActivitySubjectFactsDTO>|null
     */
    private function withoutAnonymizedAccounts(ActivitySourceRegistrationDTO $registration, array $subjects): ?array
    {
        $userIds = [];
        foreach ($subjects as $subject) {
            foreach ($subject->facts as $fact) {
                $userIds[$fact->userId] = true;
            }
        }

        if ($userIds === []) {
            return $subjects;
        }

        $anonymized = [];

        try {
            foreach (array_chunk(array_map(strval(...), array_keys($userIds)), self::ACCOUNT_QUERY_CHUNK) as $chunk) {
                $found = UserModel::query()->withBots()->withTrashed()->whereIn('id', $chunk)
                    ->where(static fn ($query) => $query->whereNotNull('anonymized_at')->orWhereNotNull('bot_key'))
                    ->pluck('id');

                foreach ($found as $id) {
                    $anonymized[strtolower((string) $id)] = true;
                }
            }
        } catch (Throwable $exception) {
            $this->warn($registration, 'accounts', $exception);

            return null;
        }

        if ($anonymized === []) {
            return $subjects;
        }

        return array_map(static fn (ActivitySubjectFactsDTO $subject): ActivitySubjectFactsDTO => new ActivitySubjectFactsDTO(
            $subject->subjectId,
            array_values(array_filter($subject->facts, static fn (ActivityFactDTO $fact): bool => ! isset($anonymized[$fact->userId]))),
        ), $subjects);
    }

    /**
     * @return array{}
     */
    private function malformed(ActivitySourceRegistrationDTO $registration, string $what): array
    {
        $this->warn($registration, 'invalid_'.$what);

        return [];
    }

    /**
     * Context names the source and the reason, and an exception only by its class: a module's
     * message may carry what a person can see.
     */
    private function warn(ActivitySourceRegistrationDTO $registration, string $reason, ?Throwable $exception = null): void
    {
        $context = [
            'type' => $registration->type,
            'module' => $registration->module,
            'class' => $registration->class,
            'reason' => $reason,
        ];

        if ($exception instanceof Throwable) {
            $context['exception'] = $exception::class;
        }

        $this->warnings->warn('reader|'.$registration->type.'|'.$reason, 'An activity source could not be read; what consumers keep from it stays as it was.', $context);
    }
}
