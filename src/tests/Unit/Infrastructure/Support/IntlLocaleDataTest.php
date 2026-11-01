<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Support;

use Illuminate\Support\Number;
use IntlDateFormatter;
use PHPUnit\Framework\TestCase;

/**
 * Without the full ICU data (Alpine ships English only unless icu-data-full is
 * installed) ICU silently falls back to the root locale: dots as decimal
 * separators and English month names.
 */
final class IntlLocaleDataTest extends TestCase
{
    public function test_numbers_use_the_spanish_decimal_separator(): void
    {
        $this->assertSame('1,36', Number::format(1.36, 2, locale: 'es'));
    }

    public function test_dates_use_spanish_month_names(): void
    {
        $formatter = new IntlDateFormatter('es', IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'UTC');

        $this->assertSame('1 de enero de 1970', $formatter->format(0));
    }
}
