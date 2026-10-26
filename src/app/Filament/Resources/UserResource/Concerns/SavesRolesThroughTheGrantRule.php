<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Concerns;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\BaseRoleRule;
use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Application\Authorization\Services\RoleGrantPolicy;
use App\Domain\Authorization\Exceptions\RoleGrantDeniedException;
use App\Domain\Authorization\Exceptions\RoleHolderLimitReachedException;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The roles of the user form are not a relationship of the form: they are saved here, through
 * the grant rule (RoleGrantPolicy) and the authorization service (holder limit, cache), in the
 * same transaction as the account row. A refused change saves nothing, not even the other
 * fields, and tells the actor why.
 */
trait SavesRolesThroughTheGrantRule
{
    /**
     * The submitted form data without its roles, and those roles: null when the data carries no
     * roles at all, which means "no change", never "untick them all".
     *
     * @param  array<string, mixed>  $data
     * @return array{array<string, mixed>, mixed}
     */
    private function splitSubmittedRoles(array $data): array
    {
        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        return [$data, $roles];
    }

    /**
     * @param  mixed  $submittedRoles  null when no roles were submitted: they stay as they are
     * @param  callable(): UserModel  $save  writes the account row
     */
    private function saveAccountWithRoles(UserModel $target, mixed $submittedRoles, callable $save): UserModel
    {
        $actor = Auth::user();

        if (! $actor instanceof UserModel) {
            throw new LogicException('The user form needs a signed-in user.');
        }

        $roles = $this->rolesToSave($actor, $target, $submittedRoles);

        try {
            return DB::transaction(static function () use ($actor, $target, $roles, $save): UserModel {
                if ($roles !== null) {
                    // Before anything is written: the rule reads the target's current roles from
                    // the database. An account not saved yet starts with none.
                    app(RoleGrantPolicy::class)->assertCanChangeRoles($actor, $target, [], $roles);
                }

                $saved = $save();

                if ($roles !== null) {
                    app(AuthorizationServiceInterface::class)->syncRoles($saved, $roles);
                }

                // Also when the roles were left as they are: an account that lost its base role
                // gets it back. Anonymized accounts hold no roles on purpose.
                if ($saved->anonymized_at === null) {
                    app(MemberRoleServiceInterface::class)->assignTo($saved);
                }

                return $saved;
            });
        } catch (RoleGrantDeniedException|RoleHolderLimitReachedException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt(true);

            throw $exception;
        }
    }

    /**
     * The roles the account must end up with, or null for no change: when no roles were
     * submitted, when the actor may not assign roles (the submitted state is then ignored) and
     * on an anonymized account, which holds no roles on purpose.
     *
     * Every submitted role is kept, so a forged one reaches the rule and is refused rather than
     * dropped in silence. A role the account holds that the actor cannot grant is kept as well
     * (its option is disabled, so only a forged state can leave it out): it is never removed from
     * here. The base role is always kept.
     *
     * @return list<string>|null
     */
    private function rolesToSave(UserModel $actor, UserModel $target, mixed $submittedRoles): ?array
    {
        if ($submittedRoles === null || $target->anonymized_at !== null) {
            return null;
        }

        if (! app(AuthorizationServiceInterface::class)->can($actor, RoleGrantPolicy::ASSIGN_ROLES_PERMISSION)) {
            return null;
        }

        $submitted = is_array($submittedRoles)
            ? array_values(array_filter($submittedRoles, is_string(...)))
            : [];

        /** @var list<string> $held */
        $held = $target->exists ? $target->roles()->pluck('roles.name')->all() : [];
        $keptHeld = array_diff($held, app(RoleGrantPolicy::class)->grantableRoleNames($actor));

        return array_values(array_unique([...$submitted, ...$keptHeld, BaseRoleRule::NAME]));
    }
}
