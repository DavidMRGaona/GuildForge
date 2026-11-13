<?php

declare(strict_types=1);

namespace App\Filament\Proposals;

use App\Application\Proposals\Services\ProposalServiceInterface;
use App\Domain\Proposals\Enums\ProposalStatus;
use App\Domain\Proposals\Enums\ProposalType;
use App\Domain\Proposals\Exceptions\ProposalException;
use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\EventResource;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * «Aprobar y publicar», «Devolver» and «Rechazar» in the header of a proposal awaiting review
 * (core#575). They show only to whoever holds the type's review permission, Filament refuses to
 * run a hidden action, and the proposals service checks the permission and the status again on
 * its own: nothing here trusts the button.
 */
final class ProposalReviewActions
{
    public const string APPROVE = 'approveProposal';

    public const string RETURN = 'returnProposal';

    public const string REJECT = 'rejectProposal';

    /** The panel guide «Revisar propuestas» */
    public const string GUIDE = 'core.panel.review-proposals';

    /** The service's own limit for a reason */
    private const int MAX_REASON = 5000;

    /**
     * @return list<Action>
     */
    public static function for(ProposalType $type): array
    {
        return [self::approve($type), self::returnToDraft($type), self::reject($type)];
    }

    private static function approve(ProposalType $type): Action
    {
        return self::base(self::APPROVE, $type, 'approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-check-circle')
            ->action(function (EditRecord $livewire, Model $record) use ($type): void {
                // What gets published is what the reviewer sees: unsaved retouches are saved first
                $livewire->save(shouldRedirect: false, shouldSendSavedNotification: false);

                self::run($livewire, $record, 'approve', fn (ProposalServiceInterface $service, string $reviewerId, string $id) => $service->approve($reviewerId, $type, $id), catchAll: true);
            });
    }

    private static function returnToDraft(ProposalType $type): Action
    {
        return self::base(self::RETURN, $type, 'return')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->modalIcon('heroicon-o-arrow-uturn-left')
            ->schema([self::reasonField('return')])
            ->action(function (EditRecord $livewire, Model $record, array $data) use ($type): void {
                $reason = self::reason($data);
                self::run($livewire, $record, 'return', fn (ProposalServiceInterface $service, string $reviewerId, string $id) => $service->returnToDraft($reviewerId, $type, $id, $reason));
            });
    }

    private static function reject(ProposalType $type): Action
    {
        return self::base(self::REJECT, $type, 'reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->modalIcon('heroicon-o-x-circle')
            ->schema([
                self::reasonField('reject'),
                // Rejecting closes the proposal for good: the author cannot send it again
                Checkbox::make('confirmation')
                    ->label(__('filament.proposals.actions.reject.confirmation'))
                    ->accepted()
                    ->required(),
            ])
            ->action(function (EditRecord $livewire, Model $record, array $data) use ($type): void {
                $reason = self::reason($data);
                self::run($livewire, $record, 'reject', fn (ProposalServiceInterface $service, string $reviewerId, string $id) => $service->reject($reviewerId, $type, $id, $reason));
            });
    }

    private static function base(string $name, ProposalType $type, string $key): Action
    {
        return Action::make($name)
            ->label(__("filament.proposals.actions.{$key}.label"))
            ->modalHeading(__("filament.proposals.actions.{$key}.heading"))
            ->modalDescription(__("filament.proposals.actions.{$key}.description"))
            ->modalSubmitActionLabel(__("filament.proposals.actions.{$key}.submit"))
            ->visible(fn (?Model $record): bool => self::awaitsReview($record) && ProposalReviewPanel::canReview($type));
    }

    private static function reasonField(string $key): Textarea
    {
        return Textarea::make('reason')
            ->label(__('filament.proposals.actions.reason'))
            ->helperText(__("filament.proposals.actions.{$key}.reason_help"))
            ->required()
            ->maxLength(self::MAX_REASON)
            ->rows(4);
    }

    public static function isProposal(?Model $record): bool
    {
        return ($record instanceof EventModel || $record instanceof ArticleModel)
            && $record->proposalStatus() instanceof ProposalStatus;
    }

    private static function awaitsReview(?Model $record): bool
    {
        return ($record instanceof EventModel || $record instanceof ArticleModel)
            && $record->proposalStatus() === ProposalStatus::PendingReview;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function reason(array $data): string
    {
        return is_string($data['reason'] ?? null) ? $data['reason'] : '';
    }

    /**
     * Runs the review through the service and tells how it went. On success the page reloads, so
     * the toggle, the actions and the module tabs follow the new status. A refusal keeps the page
     * as it is, with its reason translated. Approving runs the modules' approval steps, which may
     * fail in any way: whatever they throw rolls the approval back and is reported.
     *
     * @param  Closure(ProposalServiceInterface, string, string): void  $review
     */
    private static function run(EditRecord $livewire, Model $record, string $key, Closure $review, bool $catchAll = false): void
    {
        try {
            $review(app(ProposalServiceInterface::class), (string) auth()->id(), (string) $record->getKey());
        } catch (ProposalException $e) {
            self::refused($e->reason);

            return;
        } catch (Throwable $e) {
            if (! $catchAll) {
                throw $e;
            }

            report($e);
            self::refused('unexpected');

            return;
        }

        Notification::make()
            ->success()
            ->title(__("filament.proposals.actions.{$key}.done"))
            ->send();

        $resource = $record instanceof ArticleModel ? ArticleResource::class : EventResource::class;
        $livewire->redirect($resource::getUrl('edit', ['record' => $record]));
    }

    private static function refused(string $reason): void
    {
        $key = 'filament.proposals.errors.'.$reason;

        Notification::make()
            ->danger()
            ->title(trans()->has($key) ? __($key) : __('filament.proposals.errors.unexpected'))
            ->persistent()
            ->send();
    }
}
