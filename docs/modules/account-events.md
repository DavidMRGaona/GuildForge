# Account and role events

A module that mirrors accounts in another system (the forum bridge is the first) has to hear about account and role changes as they happen, not at the next sign-in. The core publishes them as domain events. For the rest of the module SDK, see the [module development guide](./README.md).

Requirement: set `requires.core` in `module.json` to the core release that introduces them (the one with core#461). An older core only has `UserProfileUpdated` (from the person's own profile), `UserLoggedIn`, `UserLoggedOut` and `AssociationRoleLost`.

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
| `UserAnonymized` | `userId` | An account is anonymized (`UserService::anonymize()` and `anonymizeWithContentTransfer()`, so also from the panel). Personal data and every role are already gone. |
| `UserRolesChanged` | `userId`, `rolesBefore`, `rolesAfter` | The roles stored for an account change: `assignRole()`, `removeRole()`, `syncRoles()` (user form of the panel included, also when creating an account), the holder limit dropping roles after a restore and `users:assign-member-role` (one event per account). |
| `RolePermissionsChanged` | `roleId`, `roleName`, `permissionsBefore`, `permissionsAfter` | The permissions of a role change: `RoleService` (`update`, `assignPermissions`, `syncPermissions`, so also `RolePermissionGrants` in migrations) and the role form of the panel. |
| `UserDeactivated` | `userId` | An account is deactivated (soft deleted), from wherever. A permanent deletion does not dispatch it. |
| `UserRestored` | `userId` | A deactivated account is restored. Roles dropped because they filled up meanwhile come afterwards as a `UserRolesChanged`. |
| `UserProfileUpdated` | `userId` | Already existing. Now also when the panel's user form changes `name`, `display_name`, `email` or `avatar_public_id`; saving only roles, a password or nothing does not dispatch it. |

Role lists are role **names**, sorted, and are the roles stored for the account, inactive ones included (the same view as the role history). An inactive role (one that requires the association member role) still counts. Permission lists are permission keys, sorted.

## What does not come through

- Anonymizing: the roles it removes come with `UserAnonymized`, not as `UserRolesChanged`.
- Creating, renaming or deleting a role: deleting one removes it from its holders without a `UserRolesChanged`.
- Changing the association member role in the site settings.
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
