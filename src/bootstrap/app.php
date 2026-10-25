<?php

declare(strict_types=1);

use App\Http\Middleware\BlockBotsMiddleware;
use App\Http\Middleware\EnsureEmailIsVerifiedWhenRequired;
use App\Http\Middleware\EnsureLoginIsEnabled;
use App\Http\Middleware\EnsureRegistrationIsEnabled;
use App\Http\Middleware\EnsureSiteIsNotInMaintenance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Infrastructure\Time\StorageTimezoneResolver;
use App\Providers\ModulesServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        ModulesServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: StorageTimezoneResolver::HEALTH_PATH,
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*'),
            headers: Request::HEADER_X_FORWARDED_FOR |
                     Request::HEADER_X_FORWARDED_HOST |
                     Request::HEADER_X_FORWARDED_PORT |
                     Request::HEADER_X_FORWARDED_PROTO |
                     Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->web(append: [
            HandleInertiaRequests::class,
            SecurityHeadersMiddleware::class,
            EnsureSiteIsNotInMaintenance::class,
            EnsureEmailIsVerifiedWhenRequired::class,
        ]);

        // Bot protection - runs on ALL requests
        $middleware->append(BlockBotsMiddleware::class);

        $middleware->alias([
            'registration.enabled' => EnsureRegistrationIsEnabled::class,
            'login.enabled' => EnsureLoginIsEnabled::class,
            'bot-protection' => BlockBotsMiddleware::class,
            EnsureEmailIsVerifiedWhenRequired::ALIAS => EnsureEmailIsVerifiedWhenRequired::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

// Before any provider registers (framework, package or app), so no date is created before the
// storage zone is known. The marker lives in the database, so its provider comes first.
$app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
    $app->register(DatabaseServiceProvider::class);

    (new StorageTimezoneResolver)->apply($app);
});

return $app;
