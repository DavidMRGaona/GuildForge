# Event proposal requests

People with access can propose events from the web (core ≥ the release with #581); the administration reviews and approves them in the panel. A module can add **requests** to that form: short questions such as «¿Quieres inscripciones?» or «¿Habrá mesas de rol?», each with a few fields. Whoever reviews confirms or adjusts the values, and on approval the module creates its real configuration for the event. This page explains how a module offers one. For the rest of the module SDK, see the [module development guide](./README.md).

## How it works

- The module declares the request and its fields; the core draws them below the event proposal form (no module Vue), checks every value on the server, stores them with the draft in `event_proposal_requests` and shows them in «Mis propuestas», in the panel and in the notice reviewers get.
- The person ticks the request's question to ask for it. Unticked, nothing is stored.
- In the panel the event's record shows the section «Peticiones de módulos»: every request with its values, editable, and an «Aplicar al aprobar» toggle. Only whoever holds `events.review` changes them, and only while the proposal awaits review.
- «Aprobar y publicar» runs, inside the approval transaction, `apply()` of every ticked request with the approved values. If one throws, or its module is no longer active or available, **nothing is approved**: the proposal stays in review and the panel says which request failed. A request of a module that is gone can be unticked to approve without it.
- Someone with `events.publish_proposals_directly` publishes on submitting: then `apply()` gets the requested values, and if one fails the proposal waits for review like any other.
- While the event is an unapproved proposal, the tabs and form sections a module adds to `EventResource` (`extendRelations()`, `extendFormSections()`) are hidden. The request is the only way in until approval, so `apply()` normally finds nothing configured.

Requirement: set `requires.core` in `module.json` to the core release that introduces proposal requests. An older core does not know `registerEventProposalRequests()` and the module would silently offer nothing.

## The contract

Register the classes from the provider:

```php
public function registerEventProposalRequests(): array
{
    return [RegistrationsProposalRequest::class];
}
```

Each implements `App\Application\Proposals\Contracts\EventProposalRequestInterface`:

| Method | Returns |
|--------|---------|
| `key()` | Stable key: the module name, a dot and a kebab-case name (`event-registrations.registrations`). Stored with every request: never rename it |
| `label()` | Short translated name («Inscripciones») for lists, the panel and the mails |
| `question()` | The translated yes/no question that opens it on the web («¿Quieres inscripciones?») |
| `order()` | Position among the requests (lower first; ties by key) |
| `fields()` | `list<ProposalRequestFieldDTO>`, possibly empty. Read every time, so select options may come from the module's records |
| `isAvailable()` | Whether it is offered now (the module's settings may turn it off). An unavailable request is not offered and blocks the approval while ticked |
| `apply(EventId $eventId, array $values)` | Creates the configuration from scratch; see below |
| `describe(array $values)` | One translated plain-text line for the values («Inscripciones con aforo de 20 y lista de espera»). The core escapes it |

The registry fails closed: a class that cannot be built or is not a request, a key that does not match the pattern or does not start with its module (`EventProposalRequestRegistryInterface::KEY_PATTERN`), a key already taken (the first registered wins), `fields()` that throws, a repeated field name or a dependency on a field that is not an earlier one: that request is left out and logged. A request whose `label()`, `isAvailable()` or `describe()` throws is treated as not offered or without a line.

## Fields

Build them with the named constructors of `App\Application\Proposals\DTOs\ProposalRequestFieldDTO`:

| Constructor | Value `apply()` gets |
|-------------|----------------------|
| `boolean($name, $label, default: false, helperText: null)` | `bool`; left empty it is `false`. Never required: no is an answer |
| `number($name, $label, min: 0, max: null, required: false, default: null, helperText: null)` | `int` within the limits, or `null` |
| `select($name, $label, $options, required: false, default: null, helperText: null)` | the option's key as a `string`, or `null`. `$options` is `key => translated label` |
| `text($name, $label, maxLength: 255, required: false, helperText: null)` | a trimmed one-line `string` of at most `maxLength` (1–255), or `null` |

- Names are snake_case (`ProposalRequestFieldDTO::NAME_PATTERN`). Labels are translated.
- `default` only sets what the web form starts with.
- **Dependencies**: `->visibleWhen('field', ...$values)` shows the field only while an earlier field holds one of the values (`true`, `'members'`, `3`: the type of that field); `->visibleWhenFilled('field')` while it holds anything but `null`, `false`, `0` or `''`. A hidden field is never checked and is `null`.
- `apply()` and `describe()` always receive every field of `fields()`, already checked and cast: nothing else ever reaches them (a forged form, a field that was removed). The core checks the values again right before `apply()`, so an option that is gone by then stops the approval instead of reaching the module.

An invalid field (a name that is not snake_case, an empty label, a minimum above the maximum, a select without options, a default that does not fit) throws `InvalidArgumentException` when built, which leaves the whole request out.

## `apply()`

- Runs inside the approval transaction. Create the module's configuration for the event **from scratch** with the values; leave the rest at the module's defaults, for the administration to adjust in the module's tab once the event is approved.
- **Throw when it cannot be done, and also when the event already has the module's configuration.** Never overwrite or merge: since the module's tabs are hidden until approval, existing configuration can only come from an error or from data changed by hand.
- Anything thrown rolls the whole approval back. Throw `App\Domain\Proposals\Exceptions\ProposalRequestRefusedException::because(__('…'))` with a translated message when the reviewer can do something about it («El espacio ya no está libre el 5 de diciembre»): the panel shows that message. Any other exception is reported and shown as a generic failure.
- Read the event through the core if you need its dates or place; never trust anything else the proposal holds.
- Do not send mail or announce anything from `apply()` that cannot be undone: listen to the event's normal publication instead (it is published right after the requests apply, in the same transaction).

## Reviewing in the panel

By default the core builds the review form from `fields()`: a toggle, a number input, a select or a text input per field, with the same limits and dependencies. A request that needs something else implements `App\Filament\Proposals\Contracts\ReviewsProposalRequestInPanel` and returns its components from `reviewComponents()`. They must be named after the fields (their state is the request's values), and the core still checks what they hold against `fields()` before saving, so a looser component never lets more through.

## Example

```php
final readonly class RegistrationsProposalRequest implements EventProposalRequestInterface
{
    public function __construct(private RegistrationConfigRepository $configs) {}

    public function key(): string
    {
        return 'event-registrations.registrations';
    }

    public function label(): string
    {
        return __('event-registrations::messages.proposal_request.label');
    }

    public function question(): string
    {
        return __('event-registrations::messages.proposal_request.question');
    }

    public function order(): int
    {
        return 10;
    }

    public function fields(): array
    {
        return [
            ProposalRequestFieldDTO::number('capacity', __('event-registrations::messages.proposal_request.capacity'), min: 1, max: 1000),
            ProposalRequestFieldDTO::boolean('waitlist', __('event-registrations::messages.proposal_request.waitlist'))
                ->visibleWhenFilled('capacity'),
        ];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function apply(EventId $eventId, array $values): void
    {
        if ($this->configs->existsFor($eventId)) {
            throw ProposalRequestRefusedException::because(__('event-registrations::messages.proposal_request.already_configured'));
        }

        $this->configs->create($eventId, capacity: $values['capacity'], waitlist: $values['waitlist'] === true);
    }

    public function describe(array $values): string
    {
        return is_int($values['capacity'])
            ? __('event-registrations::messages.proposal_request.with_capacity', ['capacity' => $values['capacity']])
            : __('event-registrations::messages.proposal_request.without_capacity');
    }
}
```

## Early warnings (optional, core ≥ the release with #612)

What depends on the module's state (a space already booked, a clash with another event) cannot be known from the values alone, so it only fails when someone approves. A request can warn the author earlier by also implementing `App\Application\Proposals\Contracts\ChecksProposalRequestEarly`:

```php
public function warnings(ProposedEventDTO $event, array $values): array; // list<string>
```

- `ProposedEventDTO` (`App\Application\Proposals\DTOs`) carries `startDate` and `endDate` (instants, `DateTimeImmutable`) and `location` (`?string`) as the author wrote them: the event may be a draft that is not published, so never look it up by id.
- `$values` is what `apply()` would receive: every field of `fields()`, already checked by `ProposalRequestValueRule`, for the values in force (approved ones, else requested).
- Return plain translated sentences (`__('…')`); the core escapes them. Return `[]` when there is nothing to say. **Only read**: no writes and no reservations.
- A warning never blocks saving or submitting. The core asks again every time the proposal is shown (the edit form and «Mis propuestas» in the web, and the «Peticiones de módulos» section of the panel), so nothing is stored and the answer follows what changed since; keep the check cheap.
- It fails closed to «no warning»: a call that throws is logged (request class and call, never the values) and an answer that is not a list of non-empty texts is trimmed to those. A request that is not offered any more gives none.
- `apply()` stays the real check, inside the approval transaction.

```php
final class SpaceProposalRequest implements EventProposalRequestInterface, ChecksProposalRequestEarly
{
    public function warnings(ProposedEventDTO $event, array $values): array
    {
        if ($values['space'] === null || $this->spaces->isFree((string) $values['space'], $event->startDate, $event->endDate)) {
            return [];
        }

        return [__('venue-bookings::proposals.space_busy')];
    }
}
```

## Testing

Register the request in the registry of a test and go through the core's flow:

```php
app(EventProposalRequestRegistryInterface::class)->register(RegistrationsProposalRequest::class, 'event-registrations');
```

Cover at least: the fields and their dependencies (`ProposalRequestValueRule::check()`), `apply()` creating the configuration with the values, `apply()` refusing when the configuration exists, `describe()`, `warnings()` (with a clash and without, and that it throws nothing), and that no query, reminder or notice of the module treats an unapproved proposal as a real event. The core's own fixture, `tests/Support/Proposals/FixtureEventProposalRequest.php`, and `tests/Feature/Proposals/ProposalModuleRequestsTest.php` show the whole path.
