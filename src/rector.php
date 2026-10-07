<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector;
use Rector\Php85\Rector\Property\AddOverrideAttributeToOverriddenPropertiesRector;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/app'])
    ->withPhpSets(php85: true)
    ->withPreparedSets(deadCode: true, codeQuality: true)
    ->withSkip([
        // Adding #[\Override] to every overridden property would churn about 100 files for no runtime gain.
        AddOverrideAttributeToOverriddenPropertiesRector::class,
        // Skipped to avoid rewriting existing `(new Foo())->bar()` calls in bulk; the PHP 8.4 form is allowed in new code.
        NewMethodCallWithoutParenthesesRector::class,
    ]);
