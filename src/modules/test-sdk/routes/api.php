<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| TestSdk API Routes
|--------------------------------------------------------------------------
|
| API routes for the TestSdk module.
|
*/

Route::prefix('api/test-sdk')
    ->name('test_sdk.api.')
    ->middleware('api')
    ->group(function (): void {
        // Define your API routes here
    });
