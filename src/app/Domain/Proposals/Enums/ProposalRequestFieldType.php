<?php

declare(strict_types=1);

namespace App\Domain\Proposals\Enums;

/**
 * The kinds of field a module request may ask for in an event proposal (core#581). The core draws
 * them on the web and in the panel and checks every value on the server: modules bring no Vue.
 */
enum ProposalRequestFieldType: string
{
    /** Yes or no; the value is a bool */
    case Boolean = 'boolean';

    /** A whole number between its limits; the value is an int */
    case Number = 'number';

    /** One of its options; the value is the option's key (a string) */
    case Select = 'select';

    /** One line of text up to its length; the value is a trimmed string */
    case Text = 'text';
}
