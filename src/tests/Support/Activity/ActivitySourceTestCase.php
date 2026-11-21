<?php

declare(strict_types=1);

namespace Tests\Support\Activity;

use Tests\Support\Modules\ModuleTestCase;

/**
 * Base for a module's activity source test (core#774): extend it and say how to build the source
 * and a subject that counts. The checks are in ActivitySourceContractTests, which a test with
 * another base class can use directly.
 */
abstract class ActivitySourceTestCase extends ModuleTestCase
{
    use ActivitySourceContractTests;
}
