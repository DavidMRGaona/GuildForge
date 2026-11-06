<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * How long a site search query may be, for the results page and the header panel alike.
 */
final class SearchQueryLength
{
    public const int MIN = 2;

    public const int MAX = 100;
}
