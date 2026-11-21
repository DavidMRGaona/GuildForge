<?php

declare(strict_types=1);

namespace Tests\Support\Activity;

use LogicException;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Base for a module's activity source test (core#774): set `$moduleName`, and say how to build the
 * source and a subject that counts. The module is discovered and enabled before each test, so its
 * provider registers the source as on a site. The checks are in ActivitySourceContractTests, which
 * a test with another base class can use directly (booting the module itself).
 */
abstract class ActivitySourceTestCase extends ModuleTestCase
{
    use ActivitySourceContractTests;

    protected bool $autoEnableModule = true;

    protected function activitySourceOwner(): string
    {
        return $this->moduleName ?? throw new LogicException('Set $moduleName to the module that registers the source.');
    }
}
