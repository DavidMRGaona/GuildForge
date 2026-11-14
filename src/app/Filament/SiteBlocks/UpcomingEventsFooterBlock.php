<?php

declare(strict_types=1);

namespace App\Filament\SiteBlocks;

use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The next published events (core#698), one query for the whole block. «Today» and every date are the
 * site's display zone's: an event that starts later today still shows until the day ends, and the
 * start travels as an ISO instant for the browser to draw in the same zone.
 *
 * Events have no audience of their own: what a visitor may see is what is published, which is all that
 * is read. The block is the same for everybody, so it reads nothing about the person.
 */
final class UpcomingEventsFooterBlock extends CoreFooterBlock
{
    public const int DEFAULT_LIMIT = 3;

    public const int MAX_LIMIT = 5;

    public function __construct(
        private readonly TimezoneServiceInterface $timezone,
    ) {}

    public function key(): string
    {
        return 'core.upcoming_events';
    }

    public function label(): string
    {
        return __('filament.footer.blocks.upcoming_events.label');
    }

    public function settingsSchema(): array
    {
        return [
            Select::make('limit')
                ->label(__('filament.footer.blocks.upcoming_events.limit'))
                ->options(array_map(strval(...), range(1, self::MAX_LIMIT)))
                ->default(self::DEFAULT_LIMIT)
                ->required()
                ->native(false),
            Toggle::make('show_all_link')
                ->label(__('filament.footer.blocks.upcoming_events.show_all_link'))
                ->default(true),
        ];
    }

    public function component(): string
    {
        return 'components/siteBlocks/UpcomingEventsBlock.vue';
    }

    public function props(array $settings, ?Authenticatable $user): array
    {
        // Bindings bypass the storage-zone cast: the bound is a storage-zone wall time
        $from = $this->timezone->toStorage($this->timezone->displayDayRange($this->timezone->today())[0])->format('Y-m-d H:i:s');

        $events = EventModel::query()
            ->select(['id', 'title', 'slug', 'start_date', 'location'])
            ->where('is_published', true)
            ->where('start_date', '>=', $from)
            ->orderedByStart('asc')
            ->limit($this->limit($settings))
            ->get();

        return [
            'events' => $events->map(fn (EventModel $event): array => [
                'id' => (string) $event->getKey(),
                'title' => (string) $event->getAttribute('title'),
                'url' => route('events.show', ['slug' => $event->getAttribute('slug')], false),
                'startDate' => $this->timezone->iso($event->start_date),
                'location' => self::text(['location' => $event->getAttribute('location')], 'location'),
            ])->values()->all(),
            'allUrl' => self::enabled($settings, 'show_all_link') ? route('events.index', [], false) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function limit(array $settings): int
    {
        $limit = $settings['limit'] ?? null;

        if (is_string($limit) && ctype_digit($limit)) {
            $limit = (int) $limit;
        }

        return is_int($limit) ? max(1, min(self::MAX_LIMIT, $limit)) : self::DEFAULT_LIMIT;
    }
}
