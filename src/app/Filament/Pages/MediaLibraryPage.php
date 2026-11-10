<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\DTOs\MediaDeletionResultDTO;
use App\Application\Media\Services\MediaDeletionServiceInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Media\Enums\MediaDeletionRefusal;
use App\Domain\Media\Enums\MediaFileStatus;
use App\Domain\Media\Exceptions\InvalidMediaFileIdException;
use App\Domain\Media\Exceptions\MediaDeletionDisabledException;
use App\Domain\Media\MediaFileStatusRule;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Filament\Actions\RowDetailsAction;
use App\Filament\Concerns\ChecksPermissions;
use App\Filament\Concerns\HasKnowledgeGuideAction;
use App\Filament\Support\FileSize;
use App\Filament\Support\PanelNavigationGroup;
use App\Infrastructure\Media\Jobs\SyncMediaLibraryJob;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaDeletionModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Media\Services\MediaLibraryQueryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Seo\CloudinaryImageUrlBuilder;
use DateTimeImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Js;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Attributes\Session;
use Throwable;

/**
 * The panel media library: every file the site keeps in Cloudinary (but avatars), with who uses it. Files
 * provably unused past the grace period can be deleted, always through MediaDeletionServiceInterface,
 * which checks everything again; statuses here are only what the index says.
 */
final class MediaLibraryPage extends Page implements HasTable
{
    use ChecksPermissions;
    use HasKnowledgeGuideAction;
    use InteractsWithTable;

    public const string LAYOUT_GRID = 'grid';

    public const string LAYOUT_LIST = 'list';

    /** Value of the origin filter for files no active source claims */
    public const string UNKNOWN_ORIGIN = 'unknown';

    private const string VIEW_PERMISSION = 'media.view_any';

    private const string DELETE_PERMISSION = 'media.delete';

    /** Rows of the deletions log shown */
    private const int DELETIONS_SHOWN = 100;

    /** Covers the ~280 px cards of the grid on high-density screens */
    private const int THUMBNAIL_SIZE = 400;

    private const int PREVIEW_WIDTH = 1200;

    /** Refused files listed by name in the notification; the rest are counted */
    private const int REFUSALS_LISTED = 5;

    /**
     * Files one bulk deletion takes: each is checked against every source and destroyed in Cloudinary one by one,
     * which must end well inside the request time limit.
     */
    public const int MAX_BULK_DELETE = 50;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'biblioteca-de-medios';

    protected string $view = 'filament.pages.media-library';

    /**
     * Files before and after each file shown in the details, for this request only (not Livewire state).
     *
     * @var array<string, array{0: ?string, 1: ?string}>
     */
    private array $neighbours = [];

    /** Grid or list, remembered per browser session. Not `$layout`: Filament's Page has a static one */
    #[Session]
    public string $display = self::LAYOUT_GRID;

    public static function getNavigationGroup(): string
    {
        return PanelNavigationGroup::Content->getLabel();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.media_library.title');
    }

    public function getTitle(): string
    {
        return __('filament.media_library.title');
    }

    public static function canAccess(): bool
    {
        return self::userCan(self::VIEW_PERMISSION);
    }

    public function isList(): bool
    {
        return $this->display === self::LAYOUT_LIST;
    }

    /**
     * The table is built before client updates are applied: rebuilt for the new layout.
     */
    public function updatedDisplay(): void
    {
        $this->resetTable();
    }

    public function isDeletionEnabled(): bool
    {
        return resolve(MediaDeletionServiceInterface::class)->isEnabled();
    }

    /**
     * Totals above the table, formatted.
     *
     * @return array{files: string, bytes: string, unusedFiles: string, unusedBytes: string, lastSyncedAt: string, lastSyncFailedAt: ?string}
     */
    public function getStats(): array
    {
        $stats = $this->library()->stats();
        $locale = app()->getLocale();
        $timezone = resolve(TimezoneServiceInterface::class);

        return [
            'files' => (string) Number::format($stats->files, locale: $locale),
            'bytes' => FileSize::format($stats->bytes),
            'unusedFiles' => (string) Number::format($stats->unusedFiles, locale: $locale),
            'unusedBytes' => FileSize::format($stats->unusedBytes),
            'lastSyncedAt' => $stats->lastSyncedAt instanceof DateTimeImmutable
                ? $timezone->format($stats->lastSyncedAt)
                : __('filament.media_library.stats.never'),
            // Only while the newest reconciliation is the failed one
            'lastSyncFailedAt' => $stats->lastSyncFailed() && $stats->lastSyncFailedAt instanceof DateTimeImmutable
                ? $timezone->format($stats->lastSyncFailedAt)
                : null,
        ];
    }

    public function table(Table $table): Table
    {
        $table
            ->query(fn (): Builder => $this->library()->query()->with('usages'))
            ->columns($this->isList() ? $this->listColumns() : $this->gridColumns())
            ->filters([
                SelectFilter::make('origin')
                    ->label(__('filament.media_library.filters.origin'))
                    ->options(fn (): array => $this->originOptions())
                    ->query(fn (Builder $query, array $data): Builder => is_string($data['value'] ?? null) && $data['value'] !== ''
                        ? $this->library()->applyOrigin($query, $data['value'] === self::UNKNOWN_ORIGIN ? null : $data['value'])
                        : $query),
                SelectFilter::make('status')
                    ->label(__('filament.media_library.filters.status'))
                    ->options(collect(MediaFileStatus::cases())->mapWithKeys(fn (MediaFileStatus $status): array => [
                        $status->value => self::statusLabel($status),
                    ])->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $status = is_string($data['value'] ?? null) ? MediaFileStatus::tryFrom($data['value']) : null;

                        return $status instanceof MediaFileStatus ? $this->library()->applyStatus($query, $status) : $query;
                    }),
            ])
            ->defaultSort('uploaded_at', 'desc')
            // Files uploaded in the same second keep one order, which «‹ ›» in the details follow
            ->defaultKeySort()
            // A click runs «Ver ficha», which hands over to the page action: «‹ ›» replace it there
            ->recordAction('viewDetails')
            ->recordUrl(null)
            ->checkIfRecordIsSelectableUsing(fn (MediaFileModel $record): bool => $this->library()->statusOf($record) === MediaFileStatus::Unused)
            // Otherwise every render loads the whole library to count what «Seleccionar todos» would take
            ->selectCurrentPageOnly()
            ->toolbarActions([$this->bulkDeleteAction()])
            ->paginationPageOptions([12, 24, 48, 96])
            ->defaultPaginationPageOption(24)
            ->emptyStateHeading(__('filament.media_library.empty.heading'))
            ->emptyStateDescription(__('filament.media_library.empty.description'));

        $rowDelete = $this->deleteAction()->hidden(fn (): bool => ! self::userCan(self::DELETE_PERMISSION));

        if ($this->isList()) {
            // «Ver detalles» first, for the columns hidden on narrow screens (core#383, core#399)
            return $table->recordActions([
                ActionGroup::make([RowDetailsAction::make(), $this->viewDetailsAction(), $rowDelete]),
            ]);
        }

        // Cards hide no column, so they need no «Ver detalles»
        return $table
            ->contentGrid(['md' => 3, 'xl' => 4])
            // Scopes the theme rules that lay the selection checkbox over the thumbnail (core#495)
            ->extraAttributes(['class' => 'media-library-grid'])
            ->recordActions([ActionGroup::make([$this->viewDetailsAction(), $rowDelete])]);
    }

    public function detailsAction(): Action
    {
        return Action::make('details')
            ->record(fn (array $arguments): ?MediaFileModel => $this->findFile($arguments['file'] ?? null))
            ->visible(fn (?MediaFileModel $record): bool => $record instanceof MediaFileModel)
            ->slideOver()
            ->modalHeading(fn (?MediaFileModel $record): string => $record instanceof MediaFileModel ? basename($record->file_id) : '')
            ->modalContent(fn (?MediaFileModel $record): ?View => $record instanceof MediaFileModel
                ? view('filament.pages.media-library.details', $this->detailsData($record))
                : null)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament.media_library.actions.close'))
            ->extraModalFooterActions(fn (?MediaFileModel $record): array => $record instanceof MediaFileModel
                ? $this->detailsFooterActions($record)
                : []);
    }

    /**
     * @return array<Action|null>
     */
    protected function getHeaderActions(): array
    {
        return array_filter([
            Action::make('toggleLayout')
                ->label(fn (): string => $this->isList()
                    ? __('filament.media_library.layout.grid')
                    : __('filament.media_library.layout.list'))
                ->icon(fn (): string => $this->isList() ? 'heroicon-o-squares-2x2' : 'heroicon-o-list-bullet')
                ->color('gray')
                ->action(function (): void {
                    $this->display = $this->isList() ? self::LAYOUT_GRID : self::LAYOUT_LIST;
                    $this->resetTable();
                }),
            Action::make('refresh')
                ->label(__('filament.media_library.actions.refresh'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    if (SyncMediaLibraryJob::dispatchSafely()) {
                        Notification::make()
                            ->title(__('filament.media_library.notifications.refresh_queued'))
                            ->body(__('filament.media_library.notifications.refresh_queued_body'))
                            ->success()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('filament.media_library.notifications.refresh_failed'))
                        ->danger()
                        ->send();
                }),
            Action::make('deletions')
                ->label(__('filament.media_library.actions.deletions'))
                ->icon('heroicon-o-archive-box-x-mark')
                ->color('gray')
                ->visible(fn (): bool => self::userCan(self::DELETE_PERMISSION))
                ->slideOver()
                ->modalHeading(__('filament.media_library.deletions.heading'))
                ->modalContent(fn (): View => view('filament.pages.media-library.deletions', [
                    'rows' => $this->deletionRows(),
                    'limit' => self::DELETIONS_SHOWN,
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('filament.media_library.actions.close')),
            $this->knowledgeGuideAction('core.panel.media-library'),
        ]);
    }

    /**
     * @return list<Column|Stack|Split>
     */
    private function gridColumns(): array
    {
        return [
            Stack::make([
                $this->thumbnailColumn()
                    ->imageWidth('100%')
                    ->imageHeight('auto')
                    ->extraImgAttributes(['class' => 'aspect-square w-full rounded-lg object-cover', 'loading' => 'lazy', 'alt' => '']),
                $this->titleColumn()
                    ->weight(FontWeight::Medium)
                    ->wrap(),
                // A card of a file nobody uses has no title: its origin line carries the upload day
                $this->originColumn()
                    ->formatStateUsing(fn (string $state, MediaFileModel $record): string => $record->usages->isEmpty()
                        ? __('filament.media_library.uploaded_on', [
                            'origin' => $state,
                            'date' => resolve(TimezoneServiceInterface::class)->format($record->uploaded_at, 'd/m/Y'),
                        ])
                        : $state)
                    ->size(TextSize::Small)
                    ->color('gray'),
                // Size and uses are here so the grid can be sorted by them too
                Split::make([
                    $this->statusColumn()->grow(false),
                    $this->sizeColumn()
                        ->size(TextSize::Small)
                        ->color('gray')
                        ->alignEnd(),
                    $this->usagesCountColumn()
                        ->formatStateUsing(fn (int $state): string => trans_choice('filament.media_library.usages_count', $state, ['count' => $state]))
                        ->size(TextSize::Small)
                        ->color('gray')
                        ->alignEnd()
                        ->grow(false),
                ]),
            ])->space(2),
        ];
    }

    /**
     * @return list<Column>
     */
    private function listColumns(): array
    {
        return [
            $this->thumbnailColumn()
                ->label(__('filament.media_library.columns.thumbnail'))
                ->square()
                ->imageSize(48)
                ->extraImgAttributes(['loading' => 'lazy', 'alt' => '']),
            $this->titleColumn()
                ->label(__('filament.media_library.columns.title'))
                ->placeholder(__('filament.media_library.no_usages'))
                ->wrap(),
            // Room with the sidebar open (core#383): ~343 px on a phone, 608-640 from sm to lg, 896 from xl,
            // 1152 from 2xl. A phone shows the picture, the title and the menu; «Ver detalles» shows the rest
            $this->originColumn()
                ->label(__('filament.media_library.columns.origin'))
                ->wrap()
                ->visibleFrom('xl'),
            $this->sizeColumn()
                ->label(__('filament.media_library.columns.bytes'))
                ->visibleFrom('sm'),
            $this->usagesCountColumn()
                ->label(__('filament.media_library.columns.usages_count'))
                ->alignCenter()
                ->visibleFrom('xl'),
            TextColumn::make('uploaded_at')
                ->label(__('filament.media_library.columns.uploaded_at'))
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->visibleFrom('2xl'),
            $this->statusColumn()
                ->label(__('filament.media_library.columns.status'))
                ->visibleFrom('sm'),
        ];
    }

    private function thumbnailColumn(): ImageColumn
    {
        return ImageColumn::make('thumbnail')
            ->state(fn (MediaFileModel $record): ?string => $this->urls()->mediaThumbnail($record->file_id, self::THUMBNAIL_SIZE));
    }

    /**
     * The title of its first use, and «+N» for the others; searching looks in every use and the folder.
     */
    private function titleColumn(): TextColumn
    {
        return TextColumn::make('title')
            ->state(function (MediaFileModel $record): ?string {
                $usages = $record->usages->sortBy([['title', 'asc'], ['id', 'asc']])->values();
                $first = $usages->first();

                if (! $first instanceof MediaUsageModel) {
                    return null;
                }

                return $usages->count() > 1
                    ? __('filament.media_library.title_more', ['title' => $first->title, 'count' => $usages->count() - 1])
                    : $first->title;
            })
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                fn (Builder $matches): Builder => $matches
                    ->whereHas('usages', fn (Builder $usages): Builder => $usages->whereLike('title', '%'.$search.'%'))
                    ->orWhereLike('media_files.folder', '%'.$search.'%'),
            ));
    }

    /**
     * The source claiming the folder.
     */
    private function originColumn(): TextColumn
    {
        return TextColumn::make('origin')
            ->state(fn (MediaFileModel $record): string => $this->originLabel($record));
    }

    private function sizeColumn(): TextColumn
    {
        return TextColumn::make('bytes')
            ->formatStateUsing(fn (int $state): string => FileSize::format($state))
            ->sortable();
    }

    private function usagesCountColumn(): TextColumn
    {
        return TextColumn::make('usages_count')->sortable();
    }

    private function statusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->state(fn (MediaFileModel $record): string => $this->library()->statusOf($record)->value)
            ->badge()
            ->formatStateUsing(fn (string $state): string => self::statusLabel(MediaFileStatus::from($state)))
            ->color(fn (string $state): string => self::statusColor(MediaFileStatus::from($state)));
    }

    /**
     * «Ver ficha», also what a click on the row or card runs. The details are a page action, so the arrows
     * inside them can move to another file without going through the table.
     */
    private function viewDetailsAction(): Action
    {
        return Action::make('viewDetails')
            ->label(__('filament.media_library.actions.view_details'))
            ->icon('heroicon-o-photo')
            ->action(function (MediaFileModel $record): void {
                $this->replaceMountedAction('details', ['file' => (string) $record->getKey()]);
            });
    }

    /**
     * @return array<Action>
     */
    private function detailsFooterActions(MediaFileModel $file): array
    {
        [$previous, $next] = $this->adjacentFiles($file);
        $original = $this->urls()->mediaOriginal($file->file_id, $file->resource_type);

        return array_values(array_filter([
            Action::make('previous')
                ->label(__('filament.media_library.actions.previous'))
                ->icon('heroicon-o-chevron-left')
                ->iconButton()
                ->tooltip(__('filament.media_library.actions.previous'))
                ->color('gray')
                ->disabled($previous === null)
                ->action(function () use ($previous): void {
                    if ($previous !== null) {
                        $this->replaceMountedAction('details', ['file' => $previous]);
                    }
                }),
            Action::make('next')
                ->label(__('filament.media_library.actions.next'))
                ->icon('heroicon-o-chevron-right')
                ->iconButton()
                ->tooltip(__('filament.media_library.actions.next'))
                ->color('gray')
                ->disabled($next === null)
                ->action(function () use ($next): void {
                    if ($next !== null) {
                        $this->replaceMountedAction('details', ['file' => $next]);
                    }
                }),
            $original !== null ? Action::make('openOriginal')
                ->label(__('filament.media_library.actions.open_original'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url($original, shouldOpenInNewTab: true) : null,
            $original !== null ? Action::make('copyUrl')
                ->label(__('filament.media_library.actions.copy_url'))
                ->icon('heroicon-o-clipboard-document')
                ->color('gray')
                ->alpineClickHandler(sprintf(
                    'window.navigator.clipboard.writeText(%s); $tooltip(%s, { theme: $store.theme, timeout: 2000 })',
                    Js::from($original),
                    Js::from(__('filament.media_library.actions.copied')),
                )) : null,
            $this->deleteAction()
                ->record($file)
                // The file is gone: its details close with it
                ->cancelParentActions(),
        ]));
    }

    /**
     * Single deletion, with a dialog and a box to tick. Stays in sight when it cannot be used, with the reason.
     */
    private function deleteAction(): Action
    {
        return Action::make('delete')
            ->label(__('filament.media_library.actions.delete'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('filament.media_library.delete.heading'))
            ->modalDescription(fn (MediaFileModel $record): string => __('filament.media_library.delete.description', [
                'file' => $record->file_id,
                'size' => FileSize::format($record->bytes),
            ]))
            ->schema([
                Checkbox::make('confirmation')
                    ->label(__('filament.media_library.delete.confirmation_check'))
                    ->accepted()
                    ->required(),
            ])
            ->modalSubmitActionLabel(__('filament.media_library.delete.submit'))
            ->disabled(fn (MediaFileModel $record): bool => $this->deleteBlockedBecause($record) !== null)
            ->tooltip(fn (MediaFileModel $record): ?string => $this->deleteBlockedBecause($record))
            ->action(function (MediaFileModel $record): void {
                $this->deleteFiles([$record]);
            });
    }

    private function bulkDeleteAction(): BulkAction
    {
        return BulkAction::make('deleteSelected')
            ->label(__('filament.media_library.actions.delete_selected'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->hidden(fn (): bool => ! self::userCan(self::DELETE_PERMISSION))
            ->disabled(fn (): bool => ! $this->isDeletionEnabled())
            ->tooltip(fn (): ?string => $this->isDeletionEnabled() ? null : (string) __('filament.media_library.disabled_reasons.switch_off'))
            ->requiresConfirmation()
            ->modalHeading(__('filament.media_library.delete.bulk_heading'))
            // The records are only loaded once the selection is known to be within the limit
            ->modalDescription(function (BulkAction $action): string {
                if ($this->selectionExceedsBulkLimit()) {
                    return __('filament.media_library.notifications.too_many', ['max' => self::MAX_BULK_DELETE]);
                }

                $records = $action->getSelectedRecords();

                return trans_choice('filament.media_library.delete.bulk_description', $records->count(), [
                    'count' => $records->count(),
                    'size' => FileSize::format((int) $records->sum(fn (MediaFileModel $record): int => $record->bytes)),
                ]).' '.__('filament.media_library.delete.bulk_limit', ['max' => self::MAX_BULK_DELETE]);
            })
            ->schema([
                Checkbox::make('confirmation')
                    ->label(__('filament.media_library.delete.bulk_confirmation_check'))
                    ->accepted()
                    ->required(),
            ])
            ->modalSubmitActionLabel(__('filament.media_library.delete.submit'))
            ->deselectRecordsAfterCompletion()
            ->action(function (BulkAction $action): void {
                // Checked again here: the page never sends more, a crafted request may
                if ($this->selectionExceedsBulkLimit()) {
                    $this->notifyFailure(__('filament.media_library.notifications.too_many', ['max' => self::MAX_BULK_DELETE]));

                    return;
                }

                $this->deleteFiles($action->getSelectedRecords()->all());
            });
    }

    /**
     * Read from the raw selection, before any record is loaded: «every record but these» is never sent by a
     * selection limited to the current page.
     */
    private function selectionExceedsBulkLimit(): bool
    {
        return $this->isTrackingDeselectedTableRecords || count($this->selectedTableRecords) > self::MAX_BULK_DELETE;
    }

    /**
     * Why a file cannot be deleted from here, or null when it can (the service still checks it all again).
     */
    private function deleteBlockedBecause(MediaFileModel $file): ?string
    {
        return match (true) {
            ! $this->isDeletionEnabled() => (string) __('filament.media_library.disabled_reasons.switch_off'),
            ! self::userCan(self::DELETE_PERMISSION) => (string) __('filament.media_library.disabled_reasons.permission'),
            $this->library()->statusOf($file) !== MediaFileStatus::Unused => (string) __('filament.media_library.disabled_reasons.status'),
            default => null,
        };
    }

    /**
     * @param  array<MediaFileModel>  $files
     */
    private function deleteFiles(array $files): void
    {
        $user = auth()->user();

        // Checked again here: a disabled button does not stop a crafted request
        if ($user === null || ! self::userCan(self::DELETE_PERMISSION)) {
            $this->notifyFailure(__('filament.media_library.notifications.forbidden'));

            return;
        }

        $ids = [];

        foreach ($files as $file) {
            try {
                $ids[] = new MediaFileId($file->file_id);
            } catch (InvalidMediaFileIdException) {
                // Never indexed under such an id; nothing to delete
            }
        }

        try {
            $result = resolve(MediaDeletionServiceInterface::class)->delete($ids, $user);
        } catch (MediaDeletionDisabledException) {
            $this->notifyFailure(__('filament.media_library.notifications.disabled'));

            return;
        } catch (AuthorizationException) {
            $this->notifyFailure(__('filament.media_library.notifications.forbidden'));

            return;
        }

        $this->notifyDeletion($result);
    }

    private function notifyDeletion(MediaDeletionResultDTO $result): void
    {
        $lines = [];

        if ($result->deleted > 0) {
            $lines[] = trans_choice('filament.media_library.notifications.deleted_count', $result->deleted, [
                'count' => $result->deleted,
                'size' => FileSize::format($result->freedBytes),
            ]);
        }

        if ($result->missing > 0) {
            $lines[] = trans_choice('filament.media_library.notifications.missing_count', $result->missing, ['count' => $result->missing]);
        }

        if ($result->refused !== []) {
            $lines[] = trans_choice('filament.media_library.notifications.refused_count', count($result->refused), ['count' => count($result->refused)]);

            foreach (array_slice($result->refused, 0, self::REFUSALS_LISTED, preserve_keys: true) as $fileId => $reason) {
                $lines[] = __('filament.media_library.notifications.refused_item', [
                    'file' => (string) $fileId,
                    'reason' => self::refusalText($reason),
                ]);
            }

            if (count($result->refused) > self::REFUSALS_LISTED) {
                $lines[] = __('filament.media_library.notifications.refused_more', ['count' => count($result->refused) - self::REFUSALS_LISTED]);
            }
        }

        $removed = $result->deleted + $result->missing;
        $notification = Notification::make()
            ->title($removed > 0
                ? __('filament.media_library.notifications.deleted')
                : __('filament.media_library.notifications.nothing_deleted'))
            // Filament renders the body as sanitized HTML; file ids come from the remote store, so each line is escaped
            ->body(implode('<br>', array_map(e(...), $lines)));

        match (true) {
            $removed === 0 => $notification->danger(),
            $result->refused !== [] => $notification->warning(),
            default => $notification->success(),
        };

        $notification->send();
    }

    private function notifyFailure(string $title): void
    {
        Notification::make()->title($title)->danger()->send();
    }

    public static function refusalText(MediaDeletionRefusal $refusal): string
    {
        return __('filament.media_library.refusals.'.$refusal->value, ['days' => MediaFileStatusRule::GRACE_DAYS]);
    }

    public static function statusLabel(MediaFileStatus $status): string
    {
        return __('filament.media_library.statuses.'.$status->value);
    }

    public static function statusColor(MediaFileStatus $status): string
    {
        return match ($status) {
            MediaFileStatus::InUse => 'success',
            MediaFileStatus::Unused => 'warning',
            MediaFileStatus::Recent => 'info',
            MediaFileStatus::UnknownOrigin => 'gray',
        };
    }

    /**
     * The files before and after this one in the table as it is filtered, searched and sorted now.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function adjacentFiles(MediaFileModel $file): array
    {
        $key = (string) $file->getKey();

        // Filament reads the footer actions several times per request
        if (isset($this->neighbours[$key])) {
            return $this->neighbours[$key];
        }

        /** @var list<string> $keys */
        $keys = $this->getFilteredSortedTableQuery()?->pluck('media_files.id')->map(strval(...))->all() ?? [];
        $position = array_search($key, $keys, true);

        return $this->neighbours[$key] = is_int($position)
            ? [$keys[$position - 1] ?? null, $keys[$position + 1] ?? null]
            : [null, null];
    }

    private function findFile(mixed $key): ?MediaFileModel
    {
        // Ids are UUIDs; anything else would make PostgreSQL reject the query
        if (! is_string($key) || ! Str::isUuid($key)) {
            return null;
        }

        // Through the library query, so a file in a hidden folder never opens
        $file = $this->library()->query()->with('usages')->whereKey($key)->first();

        return $file instanceof MediaFileModel ? $file : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function detailsData(MediaFileModel $file): array
    {
        $status = $this->library()->statusOf($file);
        $user = auth()->user();
        $registry = resolve(MediaSourceRegistryInterface::class);

        $usages = $file->usages
            ->sortBy([['source_type', 'asc'], ['title', 'asc'], ['id', 'asc']])
            ->values()
            ->map(function (MediaUsageModel $usage) use ($registry, $user): array {
                $source = $registry->source($usage->source_type);

                return [
                    'origin' => $this->sourceLabel($source) ?? $usage->source_type,
                    'title' => $usage->title,
                    'role' => $usage->role,
                    'editUrl' => $user !== null && $source instanceof MediaUsageSourceInterface ? $this->editUrl($source, $usage->record_id, $user) : null,
                ];
            })
            ->all();

        return [
            'imageUrl' => $this->urls()->mediaPreview($file->file_id, self::PREVIEW_WIDTH),
            'fileId' => $file->file_id,
            'dimensions' => $file->width !== null && $file->height !== null ? $file->width.' × '.$file->height : null,
            'size' => FileSize::format($file->bytes),
            'format' => $file->format !== null ? mb_strtoupper($file->format) : null,
            'uploadedAt' => resolve(TimezoneServiceInterface::class)->format($file->uploaded_at),
            'folder' => $file->folder,
            'origin' => $this->originLabel($file),
            'statusLabel' => self::statusLabel($status),
            'statusColor' => self::statusColor($status),
            'usages' => $usages,
        ];
    }

    /**
     * A link the source gives for this person, only when it is one of ours (a path or an http URL).
     */
    private function editUrl(MediaUsageSourceInterface $source, string $recordId, object $user): ?string
    {
        try {
            $url = $source->editUrl($recordId, $user);
        } catch (Throwable) {
            return null;
        }

        if (! is_string($url) || $url === '') {
            return null;
        }

        return preg_match('#^(https?://|/(?!/))#i', $url) === 1 ? $url : null;
    }

    /**
     * @return list<array{fileId: string, origin: string, size: string, dimensions: ?string, outcome: string, deletedBy: string, deletedAt: string}>
     */
    private function deletionRows(): array
    {
        $registry = resolve(MediaSourceRegistryInterface::class);
        $timezone = resolve(TimezoneServiceInterface::class);

        return array_values(MediaDeletionModel::query()
            ->with('deletedBy')
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->limit(self::DELETIONS_SHOWN)
            ->get()
            ->map(fn (MediaDeletionModel $row): array => [
                'fileId' => $row->file_id,
                'origin' => $row->source_type !== null
                    ? ($this->sourceLabel($registry->source($row->source_type)) ?? $row->source_type)
                    : (string) __('filament.media_library.unknown_origin'),
                'size' => $row->bytes !== null ? FileSize::format($row->bytes) : '—',
                'dimensions' => $row->width !== null && $row->height !== null ? $row->width.' × '.$row->height : null,
                'outcome' => (string) __('filament.media_library.deletions.outcomes.'.($row->outcome === 'missing' ? 'missing' : 'deleted')),
                // The account may have been deleted since (the key is set to null then)
                'deletedBy' => $row->deletedBy instanceof UserModel ? $row->deletedBy->name : (string) __('filament.media_library.deletions.removed_account'),
                'deletedAt' => $timezone->format($row->deleted_at),
            ])
            ->all());
    }

    /**
     * Types of the sources whose files are listed, and files nobody claims.
     *
     * @return array<string, string>
     */
    private function originOptions(): array
    {
        $options = [];

        foreach (resolve(MediaSourceRegistryInterface::class)->sources() as $source) {
            try {
                if (! $source->hidden()) {
                    $options[$source->type()] = $source->label();
                }
            } catch (Throwable) {
                // A source that cannot describe itself is left out of the filter
            }
        }

        $options[self::UNKNOWN_ORIGIN] = __('filament.media_library.unknown_origin');

        return $options;
    }

    private function originLabel(MediaFileModel $file): string
    {
        try {
            $source = resolve(MediaSourceRegistryInterface::class)->claimingSource(new MediaFileId($file->file_id));
        } catch (InvalidMediaFileIdException) {
            $source = null;
        }

        return $this->sourceLabel($source) ?? __('filament.media_library.unknown_origin');
    }

    private function sourceLabel(?MediaUsageSourceInterface $source): ?string
    {
        if (! $source instanceof MediaUsageSourceInterface) {
            return null;
        }

        try {
            return $source->label();
        } catch (Throwable) {
            return null;
        }
    }

    private function library(): MediaLibraryQueryInterface
    {
        return resolve(MediaLibraryQueryInterface::class);
    }

    /**
     * Ids in the index are relative to the storage prefix, so URLs use the same one.
     */
    private function urls(): CloudinaryImageUrlBuilder
    {
        $cloudName = config('cloudinary.cloud_name');

        return new CloudinaryImageUrlBuilder(
            is_string($cloudName) ? $cloudName : '',
            resolve(MediaFileIdResolverInterface::class)->prefix(),
        );
    }
}
