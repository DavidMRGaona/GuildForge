<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\RoutedModule\Http\PingController;

Route::get('/routed-module/ping', static fn (): string => 'pong')->name('routed-module.ping');

Route::get('/routed-module/action', [PingController::class, 'show']);
