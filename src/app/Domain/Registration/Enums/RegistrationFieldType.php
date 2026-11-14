<?php

declare(strict_types=1);

namespace App\Domain\Registration\Enums;

/**
 * The kinds of field a module may add to the registration form (core#699). Only a checkbox for
 * now: it always starts unticked, so ticking it is the person's explicit choice.
 */
enum RegistrationFieldType: string
{
    case Checkbox = 'checkbox';
}
