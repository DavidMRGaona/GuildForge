<?php

declare(strict_types=1);

namespace App\Application\Mail\Contracts;

/**
 * The daily digest itself (core#333). Each notice in it already went through the person's
 * choices, so it has no category of its own and only an anonymized account stops it.
 */
interface IsMailDigest
{
    /**
     * @return list<string> Keys of the categories whose notices it gathers, in the order shown.
     */
    public function digestCategories(): array;
}
