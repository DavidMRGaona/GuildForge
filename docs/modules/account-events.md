# Account and role events

A module that mirrors accounts in another system (the forum bridge is the first) has to hear about account and role changes as they happen, not at the next sign-in. The core publishes them as domain events. For the rest of the module SDK, see the [module development guide](./README.md).

Requirement: set `requires.core` in `module.json` to the core release that introduces them (the one with core#461; `RoleDeleted` and `UserActiveRolesChanged` come with core#466, a later one). An older core only has `UserProfileUpdated` (from the person's own profile), `UserLoggedIn`, `UserLoggedOut` and `AssociationRoleLost`.

## Guarantees

- All live in `App\Domain\Events`, are `final readonly` and carry only ids and names (no personal data).
- Dispatched with `DB::afterCommit()`: a rolled back change dispatches nothing, and inside a transaction they fire when the outermost one commits.
- Once per change, and only when something really changed: assigning a role already held, saving a form without changes or syncing the same permissions dispatches nothing.
- They only announce what already happens: no event changes the roles logic, the authorization cache or the role history.
- Authorization cache: the cache is invalidated before the event, except in `users:assign-member-role`, which invalidates once at the end of the run. Take roles and permissions from the payload, not from the cache.
- Listen with an ordinary listener (`Event::listen()` in the provider, or `make:module-listener`); queue it when the work leaves the request.

## The events

| Event | Payload | Dispatched when |
|---|---|---|
| `UserAnonymized` | `userId` | An account is anonymized (`UserService::anonymize()` and `anonymizeWithContentTransfer()`, so also from the panel). Personal data and every role are already gone, and so are the notices other people got because of the account (their notification-centre rows with it as `actorId` and the daily-digest lines that name it: core#537). |
| `UserRolesChanged` | `userId`, `rolesBefore`, `rolesAfter` | The roles stored for an account change: `assignRole()`, `removeRole()`, `syncRoles()` (user form of the panel included, also when creating an account), the holder limit dropping roles after a restore and `users:assign-member-role` (one event per account). |
| `RolePermissionsChanged` | `roleId`, `roleName`, `permissionsBefore`, `permissionsAfter` | The permissions of a role change: `RoleService` (`update`, `assignPermissions`, `syncPermissions`, so also `RolePermissionGrants` in migrations) and the role form of the panel. |
| `RoleDeleted` | `roleId`, `roleName`, `permissions`, `holderIds` | A role is deleted (`RoleService::delete()` and the panel's delete actions). One event per deletion, with the role as it was (permission keys, sorted) and the ids of every account that held it, deactivated ones included. Its holders get no `UserRolesChanged`: revoke what the role granted from `holderIds`. Take the ids in chunks if the role has many holders. |
| `UserActiveRolesChanged` | `userId`, `rolesActivated`, `rolesPaused` | Roles that require the association member role turn on or off for an account without being granted or taken away: the association member role setting changes (accounts that stop or start being members), or a role gains or loses «Requiere ser socio» (its holders who are not members). One event per affected live account, only when something really turned on or off. The roles stored for the account do not change, so there is no `UserRolesChanged`. |
| `UserDeactivated` | `userId` | An account is deactivated (soft deleted), from wherever. A permanent deletion does not dispatch it. |
| `UserRestored` | `userId` | A deactivated account is restored. Roles dropped because they filled up meanwhile come afterwards as a `UserRolesChanged`. |
| `UserProfileUpdated` | `userId` | Already existing. Now also when the panel's user form changes `name`, `display_name`, `email` or `avatar_public_id`; saving only roles, a password or nothing does not dispatch it. |

Role lists are role **names**, sorted, and are the roles stored for the account, inactive ones included (the same view as the role history). An inactive role (one that requires the association member role) still counts. Permission lists are permission keys, sorted.

## What does not come through

- Anonymizing: the roles it removes come with `UserAnonymized`, not as `UserRolesChanged`.
- Creating or renaming a role.
- Deleting a role: it comes as one `RoleDeleted`, not as a `UserRolesChanged` per holder.
- Changing the association member role or a role's requirement: it comes as `UserActiveRolesChanged`, not as `UserRolesChanged`, because the stored roles stay the same. Changes made outside the panel (a seeder, tinker, a migration that writes `roles.requires_association_member`) are not announced.
- Creating an account from the panel: it dispatches `UserRolesChanged` (it gets `member`), not `UserProfileUpdated`.

## Example

```php
use App\Domain\Events\UserAnonymized;
use App\Domain\Events\UserRolesChanged;
use Illuminate\Support\Facades\Event;

Event::listen(UserAnonymized::class, MirrorAnonymizedAccount::class);

Event::listen(UserRolesChanged::class, static function (UserRolesChanged $event): void {
    $added = array_diff($event->rolesAfter, $event->rolesBefore);
    $removed = array_diff($event->rolesBefore, $event->rolesAfter);
    // ...
});
```
