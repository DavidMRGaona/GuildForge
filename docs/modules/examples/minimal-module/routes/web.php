<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::prefix('minimal-module')
    ->name('minimal_module.')
    ->group(function (): void {
        Route::get('/', function () {
            return 'Hello from Minimal Module!';
        })->name('index');
    });
