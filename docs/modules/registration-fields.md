# Registration fields

A module can add optional fields to the registration form (core ≥ the release with #699): «Quiero recibir el boletín» is the first. The core draws them, checks the values and, once the account exists, tells the module what the person chose. This page explains how a module offers one. For the rest of the module SDK, see the [module development guide](./README.md).

## How it works

- The module declares the field; the core draws it on `/registro` below the password (no module Vue), from the `registrationFields` prop of `Auth/Register`.
- Only checkboxes for now, and they always start **unticked**: ticking one is the person's explicit choice (consent). A module cannot change that.
- When the form arrives, the core reads only the fields available at that moment, checks each value as a boolean and with the module's own rules, and drops everything else (a forged key, the value of a field that is no longer available).
- After the account is created and has its base role, right after `UserRegistered`, the core dispatches `App\Domain\Events\RegistrationFieldsSubmitted` with the account id and the values. The module listens to it.
- **A module never stops a registration**: a field whose methods throw is not drawn, not checked and its value is dropped (logged); a listener that throws is logged and the account stays created and signed in.

Requirement: set `requires.core` in `module.json` to the core release that introduces registration fields. An older core does not know `registerRegistrationFields()` and the module would silently offer nothing.

## The contract

Register the classes from the provider:

```php
public function registerRegistrationFields(): array
{
    return [SubscribeOnRegistrationField::class];
}
```

Each implements `App\Application\Registration\Contracts\RegistrationFieldInterface`:

| Method | Returns |
|--------|---------|
| `key()` | Stable key: the module name, a dot and a kebab-case name (`newsletter.subscribe`). It names the value in the event: never rename it |
| `label()` | The translated text next to the checkbox («Quiero recibir el boletín»). Plain text |
| `description()` | An optional translated line below it («Puedes darte de baja cuando quieras»), or `null`. Plain text |
| `type()` | `RegistrationFieldType::Checkbox` (`App\Domain\Registration\Enums`), the only type for now |
| `order()` | Position among the fields (lower first; ties by key) |
| `isAvailable()` | Whether it is offered now (the module's settings, a provider that is down…). Asked when the page is drawn and again when the form arrives |
| `rules()` | Extra Laravel rules for the value, checked after the core's `nullable` and `boolean`. Usually `[]` |

The fields are optional. Do not make one required (`accepted`) unless the site really cannot register anyone without it: people who do not want what it offers would not be able to sign up.

The registry (`RegistrationFieldRegistryInterface::available()`) fails closed and never throws: a class that cannot be built or is not a field, a key that does not match `KEY_PATTERN` or does not start with its module, a key already taken (the first registered wins, even while it is unavailable), an empty label, rules that are not a list, or any method that throws: that field is left out and logged. An unavailable field is left out silently.

Labels and descriptions are shown as text (Vue escapes them): no HTML, no links. The page sends no rules to the browser.

## The event

`RegistrationFieldsSubmitted` is `final readonly` with:

| Property | Holds |
|----------|-------|
| `userId` | The new account's id |
| `values` | `array<string, bool>` keyed by field key: every field that was available and passed its checks, `false` when unticked |

`ticked(string $key): bool` answers for one key (`false` when it is not there). It is dispatched only when at least one module field was available, so a module that is off never hears of it. `UserRegistered` does not change and carries no field values.

The event carries no email or name. Read the account by its id when the work needs them, at the time the work runs.

### Listen with a queued listener

The account is already stored when the event fires (`AuthService::register()` opens no transaction), but anything that leaves the request (a mailing list, an external API) must not run inside it: the person would wait for it, and its failure would only be logged. Queue the listener and mark it `afterCommit`, so it also stays correct if registration is ever wrapped in a transaction. A synchronous listener is worse than slow: the dispatcher stops at the first listener that throws, so the listeners after it (other modules' included) never hear of the registration. The core only logs that failure; it cannot run the rest:

```php
use App\Domain\Events\RegistrationFieldsSubmitted;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SubscribeAfterRegistration implements ShouldQueue
{
    public bool $afterCommit = true;

    public function handle(RegistrationFieldsSubmitted $event): void
    {
        if (! $event->ticked('newsletter.subscribe')) {
            return;
        }

        // Read the account by $event->userId and subscribe it
    }
}
```

```php
// In the provider's boot()
Event::listen(RegistrationFieldsSubmitted::class, SubscribeAfterRegistration::class);
```

The queue is `database`, so the job's payload is stored in `jobs` and, if it fails, in `failed_jobs`: keep personal data out of it (the event only holds the id and the booleans) and out of the logs.

## Form and errors

The browser sends the values as `registration_fields[<key>]`. Errors of a field come back under `registration_fields.<key>` (with the dots of the key, never read with dot notation) and are shown below the checkbox, which gets `aria-invalid` and `aria-describedby` pointing at its description and error.

## Testing

Register a field in the registry and post the form:

```php
use App\Application\Registration\Services\RegistrationFieldRegistryInterface;
use App\Domain\Events\RegistrationFieldsSubmitted;
use Illuminate\Support\Facades\Event;

app(RegistrationFieldRegistryInterface::class)->register(SubscribeOnRegistrationField::class, 'newsletter');
Event::fake([RegistrationFieldsSubmitted::class]);

$this->post('/registro', [
    'name' => 'Persona de ejemplo',
    'email' => 'persona@example.com',
    'password' => 'secret-password',
    'password_confirmation' => 'secret-password',
    'registration_fields' => ['newsletter.subscribe' => true],
]);

Event::assertDispatched(RegistrationFieldsSubmitted::class, fn ($event): bool => $event->ticked('newsletter.subscribe'));
```

Test the listener on its own with a `RegistrationFieldsSubmitted` built by hand.
