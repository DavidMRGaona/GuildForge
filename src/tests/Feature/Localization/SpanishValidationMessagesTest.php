<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use Tests\TestCase;

final class SpanishValidationMessagesTest extends TestCase
{
    public function test_selection_messages_do_not_assume_the_gender_of_the_field(): void
    {
        // Filament passes the field label as the attribute: "página", "categoría", "estado"…
        foreach (['in', 'not_in', 'enum', 'exists'] as $rule) {
            $this->assertSame(
                'El valor seleccionado en el campo página no es válido.',
                __("validation.{$rule}", ['attribute' => 'página'], 'es'),
                "validation.{$rule}",
            );
        }
    }
}
