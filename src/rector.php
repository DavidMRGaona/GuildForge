<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\Php80\Rector\Ternary\TernaryToNullsafeCoalesceRector;
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
        // Framework signatures: a listener's handle() names the event it listens to, and a
        // policy method receives the model the Gate checks, even when they do not read them.
        // Plain patterns, so they also match a module's src/ (scripts/check-module-quality.sh).
        RemoveUnusedPublicMethodParameterRector::class => ['*/Listeners/*', '*/Policies/*'],
        // Writes `$a?->b ?? $c`, which PHPStan rejects (nullsafe.neverNull: ?? already covers
        // the null); `$a->b ?? $c` is what both accept.
        TernaryToNullsafeCoalesceRector::class,
    ]);
