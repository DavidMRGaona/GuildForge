<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Domain\Proposals\Enums\ProposalStatus;
use App\Infrastructure\Persistence\Eloquent\Concerns\DeletesCloudinaryImages;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasProposalReview;
use App\Infrastructure\Persistence\Eloquent\Concerns\HasSlug;
use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use Database\Factories\EventModelFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $title
 * @property string $slug
 * @property string $description
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string|null $location
 * @property string|null $image_public_id
 * @property string|null $member_price
 * @property string|null $non_member_price
 * @property array<array{label: string, url: string, description: string}>|null $download_links
 * @property bool $is_published
 * @property ProposalStatus|null $proposal_status
 * @property string|null $proposed_by
 * @property string|null $proposal_note
 * @property string|null $proposal_reason
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 * @property string|null $reviewed_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, TagModel> $tags
 * @property-read UserModel|null $proposer
 * @property-read UserModel|null $reviewer
 *
 * @method static Builder<EventModel> includingUnsubmittedProposals()
 * @method static Builder<EventModel> unapprovedProposals()
 * @method static Builder<EventModel> orderedByStart(string $direction = 'asc')
 * @method static Builder<EventModel> orderedUpcomingFirst(DateTimeInterface $now)
 */
final class EventModel extends Model
{
    use DeletesCloudinaryImages;

    /** @use HasFactory<EventModelFactory> */
    use HasFactory;

    use HasProposalReview;
    use HasSlug;
    use HasUuids;
    use StoresInstantsInStorageTimezone;

    /** @var array<string> */
    protected array $cloudinaryImageFields = ['image_public_id'];

    protected $table = 'events';

    protected $fillable = [
        'id',
        'title',
        'slug',
        'description',
        'start_date',
        'end_date',
        'location',
        'member_price',
        'non_member_price',
        'image_public_id',
        'download_links',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'member_price' => 'decimal:2',
            'non_member_price' => 'decimal:2',
            'download_links' => 'array',
            'is_published' => 'boolean',
            'proposal_status' => ProposalStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): EventModelFactory
    {
        return EventModelFactory::new();
    }

    /**
     * @return BelongsToMany<TagModel, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TagModel::class, 'event_tag', 'event_id', 'tag_id')
            ->withTimestamps()
            ->ordered();
    }

    /**
     * Scope to order events by start, with a stable order for events starting at the same time.
     *
     * @param  Builder<EventModel>  $query
     * @param  'asc'|'desc'  $direction
     * @return Builder<EventModel>
     */
    public function scopeOrderedByStart(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('start_date', $direction)->orderBy('created_at', $direction)->orderBy('id', $direction);
    }

    /**
     * Scope to order events not started yet soonest first, followed by the rest latest first.
     *
     * Past events get NULL in the upcoming keys, so they tie there and fall through to the
     * descending order; ties only compare NULL with NULL, which SQLite and PostgreSQL agree on.
     *
     * @param  Builder<EventModel>  $query
     * @return Builder<EventModel>
     */
    public function scopeOrderedUpcomingFirst(Builder $query, DateTimeInterface $now): Builder
    {
        $upcoming = 'CASE WHEN start_date >= ? THEN %s END';

        return $query
            ->orderByRaw('CASE WHEN start_date >= ? THEN 0 ELSE 1 END', [$now])
            ->orderByRaw(sprintf($upcoming, 'start_date'), [$now])
            ->orderByRaw(sprintf($upcoming, 'created_at'), [$now])
            ->orderByRaw(sprintf($upcoming, 'id'), [$now])
            ->orderedByStart('desc');
    }

    /**
     * Get the entity type for slug redirects.
     */
    public function getSlugEntityType(): string
    {
        return 'event';
    }

    /**
     * Get the route key name for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
