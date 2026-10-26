<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Application\Authorization\Services\RoleAuditLoggerInterface;
use App\Domain\Authorization\Enums\RoleAuditEvent;
use App\Infrastructure\Persistence\Eloquent\Models\RoleAuditEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Nothing here catches a failed write: the caller's transaction must roll the change back.
 * It never depends on the authorization service, which depends on it.
 */
final readonly class RoleAuditLogger implements RoleAuditLoggerInterface
{
    public function __construct(
        private RoleAuditSourceResolver $source,
    ) {}

    public function roleAssigned(object $user, string $roleName): void
    {
        $this->write($this->userId($user), $this->roleId($roleName), $roleName, RoleAuditEvent::Assigned, null);
    }

    public function roleAssignedToMany(array $userIds, string $roleName): void
    {
        $this->insertForUsers($userIds, $this->roleId($roleName), $roleName, RoleAuditEvent::Assigned);
    }

    public function roleRemoved(object $user, string $roleName): void
    {
        $this->write($this->userId($user), $this->roleId($roleName), $roleName, RoleAuditEvent::Removed, null);
    }

    public function roleChanged(string $roleId, string $roleName, RoleAuditEvent $event, array $changes): void
    {
        if ($event === RoleAuditEvent::RoleDeleted) {
            // Deleting the role takes it from every holder (the pivot cascades): each one gets an entry
            /** @var list<string> $holders */
            $holders = array_values(DB::table('user_role')->where('role_id', $roleId)->pluck('user_id')->map(strval(...))->all());
            $this->insertForUsers($holders, $roleId, $roleName, RoleAuditEvent::Removed);
        }

        $this->write(null, $roleId, $roleName, $event, $changes === [] ? null : $changes);
    }

    /**
     * @param  array<string, mixed>|null  $changes
     */
    private function write(?string $userId, ?string $roleId, string $roleName, RoleAuditEvent $event, ?array $changes): void
    {
        RoleAuditEntryModel::query()->create([
            'user_id' => $userId,
            'role_id' => $roleId,
            'role_name' => $roleName,
            'event' => $event->value,
            'changes' => $changes,
            'actor_id' => $this->actorId(),
            'source' => $this->source->resolve()->value,
        ]);
    }

    /**
     * One insert for every account.
     *
     * @param  list<string>  $userIds
     */
    private function insertForUsers(array $userIds, ?string $roleId, string $roleName, RoleAuditEvent $event): void
    {
        if ($userIds === []) {
            return;
        }

        $actorId = $this->actorId();
        $source = $this->source->resolve()->value;
        $now = now();

        RoleAuditEntryModel::query()->insert(array_map(
            static fn (string $userId): array => [
                // UUIDv7, like the model's own ids: entries keep their order
                'id' => (string) Str::uuid7(),
                'user_id' => $userId,
                'role_id' => $roleId,
                'role_name' => $roleName,
                'event' => $event->value,
                'changes' => null,
                'actor_id' => $actorId,
                'source' => $source,
                'created_at' => $now,
            ],
            $userIds,
        ));
    }

    private function roleId(string $roleName): ?string
    {
        $id = RoleModel::query()->where('name', $roleName)->value('id');

        return is_string($id) ? $id : null;
    }

    private function userId(object $user): string
    {
        /** @var Model $user */
        $key = $user->getKey();

        return is_scalar($key) ? (string) $key : '';
    }

    private function actorId(): ?string
    {
        $id = Auth::id();

        return is_scalar($id) ? (string) $id : null;
    }
}
