<?php

declare(strict_types=1);

use App\Http\Middleware\BlockBotsMiddleware;
use App\Http\Middleware\EnsureLoginIsEnabled;
use App\Http\Middleware\EnsureRegistrationIsEnabled;
use App\Http\Middleware\EnsureSiteIsNotInMaintenance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Providers\ModulesServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        ModulesServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
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
        ]);

        // Bot protection - runs on ALL requests
        $middleware->append(BlockBotsMiddleware::class);

        $middleware->alias([
            'registration.enabled' => EnsureRegistrationIsEnabled::class,
            'login.enabled' => EnsureLoginIsEnabled::class,
            'bot-protection' => BlockBotsMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
