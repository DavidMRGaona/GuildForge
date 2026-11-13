# Impersonation

An administrator can view the site and the panel as another account (**Personas → Usuarios → Impersonar**) to reproduce a bug without saving anything (core epic #569). This page explains what the core already covers, what a module has to do in the few cases it does not, and what a module's panel pages and frontend can use. For the rest of the module SDK, see the [module development guide](./README.md).

## How it works

- Only the `admin` role can impersonate (`users.impersonate` is an admin-only permission) and never an administrator, oneself, a deactivated account or an anonymized one. The session stays the administrator's; on every site and panel request (Livewire included) the core checks again that the impersonation may go on and, if not, ends it and goes back to the administrator.
- There are two modes, chosen when starting and never changed halfway: `read_only` («Solo mirar», the default) and `simulate` («Ejecutar y deshacer»). **Nothing is saved in either of them.**
- In `read_only`, site submissions and panel actions are refused with a notice; in `simulate` they run and are undone.
- Every impersonation is logged (`impersonation_log`) and shown in the account's page in the panel.

## What the safety net already covers

While an impersonation lasts, the request runs in a database transaction that is always rolled back (also on an exception), and what does not wait for a commit is neutralised, in both modes:

| Output | What happens |
|--------|--------------|
| Database | Rolled back at the end of the request. `DB::afterCommit()` callbacks never run |
| Mail | Goes to the `array` transport: nothing is sent |
| Queue | Every connection drops its jobs |
| Cache | Every store becomes one of the request, so a `forget()` followed by `remember()` cannot leave rolled back data for the whole site |
| Search index | Scout updates go to the queue that drops them |
| Upload disks | Read from the real disk; writes and deletes are discarded |
| Real time | `RealtimePublisherInterface::publish()` does nothing |
| Media storage | Deleting files in the media library is refused |

A module does not need to do anything for these: its models, jobs, notifications, events and uploads are covered because they go through the framework. Mind that `Auth::user()` is the impersonated account and the real administrator is `impersonator()`.

## When a module must ask

The net cannot undo a call that leaves the application **during the request** and writes somewhere else: an HTTP call to an external API when saving, a test message sent to a channel, a deletion in a third-party SDK. A module that does one injects `App\Application\Impersonation\Services\ImpersonationContextInterface` and does not do it while `isActive()` is true:

```php
use App\Application\Impersonation\Services\ImpersonationContextInterface;

public function __construct(private ImpersonationContextInterface $impersonation) {}

public function sendTest(): void
{
    if ($this->impersonation->isActive()) {
        throw new SomeUnavailableException('an impersonation is in progress');
    }

    // call the external service
}
```

- It is only active in web and panel requests: never in queued jobs or console commands (jobs are dropped anyway).
- `impersonator()` (the administrator), `target()` (the impersonated account) and `mode()` (`ImpersonationMode::ReadOnly` or `Simulate`) are also there. A module normally only needs `isActive()`: refuse in both modes, because a call outside the database cannot be undone.
- Prefer to refuse (an exception your caller already handles, or a notice) over silently skipping, so nobody thinks the call happened.
- Anything that is queued or deferred needs nothing: the queue drops it.

Requirement: set `requires.core` in `module.json` to the core release that introduces impersonation, otherwise an older core does not bind the interface.

## Panel pages in `read_only`

In `read_only` the panel refuses, by default, everything that is not browsing: running or submitting a Filament action (row, header, bulk or form), saving or creating on resource and settings pages, and any public Livewire method of a page. Filtering, sorting, paginating, searching and opening forms, confirmations and «Ver detalles» keep working. It fails closed, so a module's actions and pages are covered without doing anything.

Mark only what you are sure writes nothing:

- A **Filament action** that only reads (it switches a layout, moves between files): `App\Filament\Impersonation\ReadOnlyActions::allow($action)`. Actions that only open a modal with nothing to submit need no mark.
- A **public Livewire method** of a page that only changes what the page shows: the attribute `#[App\Filament\Impersonation\ReadsOnly]`.

```php
use App\Filament\Impersonation\ReadOnlyActions;

ReadOnlyActions::allow(
    Action::make('toggleLayout')->action(fn () => $this->compact = ! $this->compact),
);
```

The mark travels with the action's extra attributes: an action whose attributes are replaced afterwards loses it and is refused. Never mark something that writes, calls an external service or sends a notice: in `simulate` the mark makes no difference (everything runs and is undone), and in `read_only` it is the only way out.

## The site in `read_only`

Every site request that is not `GET` or `HEAD` is refused before its controller (except the way out, logout and `POST /broadcasting/auth`):

- **Page visits** (Inertia) go back with the notice «Estás viendo el sitio como…: no se guardan cambios».
- **JSON requests** get a `403` with `error: "impersonation_read_only"` and the header `X-Impersonation-Read-Only: 1`.

A page that sends JSON with `fetch()` or axios would show its generic failure for any 403. Recognise this one by its code and header, never by the translated text:

```ts
const response = await fetch(url, { method: 'POST', headers: { Accept: 'application/json' } });
if (response.status === 403 && response.headers.get('X-Impersonation-Read-Only') === '1') {
    // nothing was saved: say so instead of showing the generic error
}
```

The core's own pages use the composable `useImpersonationReadOnly()` (`resources/js/composables/useImpersonationReadOnly.ts`: `isReadOnlyRefusal()`, `notifyIfReadOnly()`, `notifyRefusal()`), which does this check and shows the notice. It is not yet part of the public frontend API (`public-api.json`), so a module build cannot import it. The banner and the shared Inertia prop `impersonation` (`{ targetName, mode }` or `null`) are drawn by the core: a module does not draw its own.

## Testing

- With the safety net: act as an administrator, start the impersonation through `ImpersonationServiceInterface` and assert that nothing was written, queued or sent.
- For an external call: bind a test double of `ImpersonationContextInterface` that answers `isActive() === true` and assert that the call is not made.
