<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| TestSdk Web Routes
|--------------------------------------------------------------------------
|
| Web routes for the TestSdk module.
|
*/

Route::prefix('test-sdk')
    ->name('test_sdk.')
    ->group(function (): void {
        // Define your web routes here
    });
