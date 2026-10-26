<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Php80\Rector\Ternary\TernaryToNullsafeCoalesceRector;
use Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector;
use Rector\Php85\Rector\Property\AddOverrideAttributeToOverriddenPropertiesRector;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/app', __DIR__.'/database', __DIR__.'/tests'])
    ->withPhpSets(php85: true)
    ->withPreparedSets(deadCode: true, codeQuality: true)
    ->withSkip([
        // Migrations already ran on live sites, and fixtures are inputs kept exactly as written.
        // Plain patterns, so they also match a module's database/ and tests/ (scripts/check-module-quality.sh).
        '*/database/migrations/*',
        '*/tests/Fixtures/*',
        // The test builds this module class at runtime, so ::class would name a class static analysis cannot find
        StringClassNameToClassConstantRector::class => [__DIR__.'/tests/Feature/Modules/ModuleTestCaseRouteLookupTest.php'],
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
