<?php

declare(strict_types=1);

namespace App\Infrastructure\Activity\Sources;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\Contracts\OffersActivityAttributes;
use App\Application\Activity\DTOs\ActivityAttributeDTO;
use App\Application\Activity\DTOs\ActivityAttributeValueDTO;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\DTOs\ActivityHistoryPageDTO;
use App\Application\Activity\DTOs\ActivitySubjectDTO;
use App\Application\Activity\DTOs\ActivitySubjectFactsDTO;
use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Domain\Activity\ActivityKind;
use App\Domain\Activity\ActivityRole;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Approved proposals (core#777, core#570): an event or an article somebody proposed from the web
 * that a reviewer approved. The subject is the proposed content, with the id `events:<uuid>` or
 * `articles:<uuid>` (the two tables may not share ids, but the type is part of the subject so it
 * never needs guessing); the fact is its proposer as `proposer`, dated when it was approved, with
 * the attribute `content_type` (`event` or `article`).
 *
 * It counts only while the content is approved and published, which is what any member (any guest)
 * can see on the site, and its proposer's account is active. An event proposal counts only while the
 * site shows who proposed it (the `events` proposals setting «Mostrar quién propone», off by
 * default): a member could not know who proposed it otherwise. Articles always count, since the
 * proposer is the author the site shows. Changing the setting publishes every approved event
 * proposal (PublishApprovedEventProposalsJob). Unpublishing it in the panel, deleting
 * it or deactivating or anonymizing the account stops it counting; ProposalActivityObserver and
 * UserActivityObserver publish each of those. Content made in the panel is not a proposal.
 *
 * The history goes through events first, then articles, each by id.
 */
final readonly class ProposalActivitySource implements ActivitySourceInterface, OffersActivityAttributes
{
    public const string TYPE = 'core.proposal';

    public const string CONTENT_TYPE = 'content_type';

    public function __construct(
        private ProposalSettingsServiceInterface $settings,
    ) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function kind(): ActivityKind
    {
        return ActivityKind::Proposal;
    }

    public function label(): string
    {
        return __('activity.proposal.label');
    }

    public function attributes(): array
    {
        return [new ActivityAttributeDTO(self::CONTENT_TYPE, __('activity.proposal.content_type'))];
    }

    public function attributeValues(string $key): array
    {
        if ($key !== self::CONTENT_TYPE) {
            return [];
        }

        return array_map($this->contentType(...), ProposalType::cases());
    }

    public function facts(string $subjectId): array
    {
        $parsed = self::parse($subjectId);

        if ($parsed === null) {
            return [];
        }

        $content = $this->counted($parsed[0])->whereKey($parsed[1])->first();

        return $content === null ? [] : [$this->factOf($parsed[0], $content)];
    }

    public function history(?string $cursor, int $limit): ActivityHistoryPageDTO
    {
        [$from, $after] = $cursor === null ? [ProposalType::Event, null] : $this->readCursor($cursor);
        $types = ProposalType::cases();
        $start = (int) array_search($from, $types, true);
        $subjects = [];
        $remaining = $limit;
        $next = null;

        foreach ($types as $index => $type) {
            if ($index < $start || ! $this->countsType($type)) {
                continue;
            }

            $query = $this->counted($type)->orderBy('id')->limit($remaining + 1);

            if ($after !== null && $type === $from) {
                $query->where('id', '>', $after);
            }

            $rows = $query->get();
            $taken = $rows->take($remaining)->values();

            foreach ($taken as $content) {
                $subjects[] = new ActivitySubjectFactsDTO(self::subjectId($type, $content), [$this->factOf($type, $content)]);
            }

            $remaining -= $taken->count();

            if ($rows->count() > $taken->count()) {
                $last = $taken->last();
                $next = OpaqueCursor::encode($type->value.'|'.strtolower((string) $last?->getKey()));

                break;
            }

            if ($remaining === 0) {
                // Filled the page exactly: point at what follows only when there is something
                $next = $this->cursorToWhatFollows($types, $index);

                break;
            }
        }

        return new ActivityHistoryPageDTO($subjects, $next);
    }

    public function describe(array $subjectIds, ?Authenticatable $viewer): array
    {
        $byType = [];

        foreach ($subjectIds as $subjectId) {
            $parsed = self::parse($subjectId);

            if ($parsed !== null) {
                $byType[$parsed[0]->value][] = $parsed[1];
            }
        }

        $described = [];

        foreach (ProposalType::cases() as $type) {
            if (! isset($byType[$type->value])) {
                continue;
            }

            // Counting is being approved and published: what anybody, a guest included, can open
            foreach ($this->counted($type)->whereKey($byType[$type->value])->get() as $content) {
                $id = self::subjectId($type, $content);
                $described[$id] = new ActivitySubjectDTO($id, (string) $content->title, $this->urlOf($type, $content));
            }
        }

        return $described;
    }

    /**
     * The id of a proposed content as a subject, for whoever publishes it.
     */
    public static function subjectIdOf(ProposalType $type, string $contentId): string
    {
        return $type->value.':'.strtolower($contentId);
    }

    /**
     * @return Builder<EventModel>|Builder<ArticleModel>
     */
    private function counted(ProposalType $type): Builder
    {
        $query = match ($type) {
            ProposalType::Event => EventModel::query(),
            ProposalType::Article => ArticleModel::query(),
        };

        // Nothing at all while the type does not count
        if (! $this->countsType($type)) {
            return $query->whereRaw('0 = 1');
        }

        return $query
            ->where('proposal_status', ProposalStatus::Approved->value)
            ->where('is_published', true)
            ->whereNotNull('proposed_by')
            // Soft deleted accounts are left out by the model
            ->whereHas('proposer', static fn (Builder $proposer): Builder => $proposer->whereNull('anonymized_at'));
    }

    /**
     * Whether a type's proposals count. An article's proposer is the author the site shows, so
     * articles always do; an event's proposer is shown only when the site says so (`events` setting
     * «Mostrar quién propone», off by default), and a member could not know who proposed it
     * otherwise. Fails closed: an unreadable setting is off.
     */
    private function countsType(ProposalType $type): bool
    {
        if ($type === ProposalType::Article) {
            return true;
        }

        try {
            return $this->settings->settings()->events->showProposer;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  list<ProposalType>  $types
     */
    private function cursorToWhatFollows(array $types, int $index): ?string
    {
        foreach (array_slice($types, $index + 1) as $following) {
            if ($this->countsType($following) && $this->counted($following)->exists()) {
                return OpaqueCursor::encode($following->value.'|');
            }
        }

        return null;
    }

    private function factOf(ProposalType $type, EventModel|ArticleModel $content): ActivityFactDTO
    {
        // Stable: never the moment it is read. Approving writes reviewed_at; the others are the best a row without it has
        $approvedAt = $content->reviewed_at ?? $content->updated_at ?? $content->created_at;

        return new ActivityFactDTO(
            strtolower((string) $content->proposed_by),
            ActivityRole::Proposer,
            $approvedAt !== null ? DateTimeImmutable::createFromInterface($approvedAt) : new DateTimeImmutable('@0'),
            [self::CONTENT_TYPE => $this->contentType($type)],
        );
    }

    private function contentType(ProposalType $type): ActivityAttributeValueDTO
    {
        return match ($type) {
            ProposalType::Event => new ActivityAttributeValueDTO('event', __('activity.proposal.content_types.event')),
            ProposalType::Article => new ActivityAttributeValueDTO('article', __('activity.proposal.content_types.article')),
        };
    }

    private function urlOf(ProposalType $type, EventModel|ArticleModel $content): string
    {
        return route($type === ProposalType::Event ? 'events.show' : 'articles.show', ['slug' => $content->slug], false);
    }

    private static function subjectId(ProposalType $type, EventModel|ArticleModel $content): string
    {
        return self::subjectIdOf($type, (string) $content->getKey());
    }

    /**
     * @return array{ProposalType, string}|null the type and the content id in lower case; null for anything that is not a subject of this source
     */
    private static function parse(string $subjectId): ?array
    {
        $parts = explode(':', $subjectId, 2);
        $type = ProposalType::tryFrom($parts[0]);

        // A malformed uuid would abort a PostgreSQL transaction
        if (! $type instanceof ProposalType || ! isset($parts[1]) || ! Str::isUuid($parts[1])) {
            return null;
        }

        return [$type, strtolower($parts[1])];
    }

    /**
     * @return array{ProposalType, ?string} where to go on: the type and the last id read in it (null: its start)
     *
     * @throws InvalidArgumentException when the cursor is not one this source gave
     */
    private function readCursor(string $cursor): array
    {
        $parts = explode('|', OpaqueCursor::decode($cursor), 2);
        $type = ProposalType::tryFrom($parts[0]);
        $id = $parts[1] ?? null;

        if (! $type instanceof ProposalType || $id === null || ($id !== '' && ! Str::isUuid($id))) {
            throw new InvalidArgumentException('The activity history cursor cannot be read.');
        }

        return [$type, $id === '' ? null : strtolower($id)];
    }
}
