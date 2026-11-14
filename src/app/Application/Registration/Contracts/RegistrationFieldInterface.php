<?php

declare(strict_types=1);

namespace App\Application\Registration\Contracts;

use App\Domain\Registration\Enums\RegistrationFieldType;

/**
 * An optional field a module adds to the registration form (core#699): «Quiero recibir el
 * boletín». Modules return their implementations from ModuleServiceProvider::registerRegistrationFields().
 *
 * The core draws the field from these methods (no module Vue), checks the value on the server and,
 * once the account exists, dispatches RegistrationFieldsSubmitted with the values, which the module
 * reads from a queued listener. A field never makes the registration fail: a method that throws
 * leaves the field out (not drawn, not checked, its value dropped) and is logged.
 * Guide: docs/modules/registration-fields.md.
 */
interface RegistrationFieldInterface
{
    /**
     * Stable key, the module name, a dot and a kebab-case name (`newsletter.subscribe`). It names
     * the value in RegistrationFieldsSubmitted: never rename it.
     */
    public function key(): string;

    /**
     * The translated text next to the checkbox («Quiero recibir el boletín»). Plain text, escaped
     * by the page.
     */
    public function label(): string;

    /**
     * An optional translated line below the label («Puedes darte de baja cuando quieras»). Plain
     * text, escaped by the page; null for none.
     */
    public function description(): ?string;

    /**
     * Only RegistrationFieldType::Checkbox for now. Checkboxes always start unticked.
     */
    public function type(): RegistrationFieldType;

    /**
     * Position among the fields (lower first; ties by key).
     */
    public function order(): int;

    /**
     * Whether the field is offered now (the module's settings, a provider that is down…). Asked
     * when drawing the page and again when the form arrives: a value sent for a field that is not
     * available by then is dropped.
     */
    public function isAvailable(): bool;

    /**
     * Extra Laravel validation rules for the value, checked after the core's own `boolean`. Most
     * fields need none: they are optional, so never make them required (`accepted`) unless the
     * site cannot register anyone without it.
     *
     * @return array<int, mixed>
     */
    public function rules(): array;
}
