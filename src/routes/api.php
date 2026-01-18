<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/events/calendar', [CalendarController::class, 'index'])->name('api.events.calendar');
Route::get('/settings/location', [SettingsController::class, 'location'])->name('api.settings.location');
