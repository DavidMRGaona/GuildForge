<?php

declare(strict_types=1);

namespace App\Filament\SiteBlocks;

use App\Application\DTOs\Response\EventResponseDTO;
use App\Application\Services\EventQueryServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Application\SiteBlocks\Exceptions\NothingToDrawException;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The next published events that have not ended yet (core#698), like the rest of the site
 * (EventQueryService::getNextEvents(), one query for the whole block): one already under way still
 * shows. The start travels as an ISO instant in the display zone, for the browser to draw in it.
 *
 * Events have no audience of their own: what a visitor may see is what is published, which is all that
 * is read. The block is the same for everybody, so it reads nothing about the person.
 */
final class UpcomingEventsFooterBlock extends CoreFooterBlock
{
    public const int DEFAULT_LIMIT = 3;

    public const int MAX_LIMIT = 5;

    public function __construct(
        private readonly EventQueryServiceInterface $events,
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
                ->options(array_combine(range(1, self::MAX_LIMIT), array_map(strval(...), range(1, self::MAX_LIMIT))))
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
        $allUrl = self::enabled($settings, 'show_all_link') ? route('events.index', [], false) : null;
        $events = array_map(fn (EventResponseDTO $event): array => [
            'id' => $event->id,
            'title' => $event->title,
            'url' => route('events.show', ['slug' => $event->slug], false),
            'startDate' => $this->timezone->iso($event->startDate),
            'location' => self::text(['location' => $event->location], 'location'),
        ], $this->events->getNextEvents($this->limit($settings)));

        // Neither events nor the link to all of them: only the title would be left
        if ($events === [] && $allUrl === null) {
            throw NothingToDrawException::make();
        }

        return ['events' => $events, 'allUrl' => $allUrl];
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
