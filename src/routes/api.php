<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CalendarController;
use Illuminate\Support\Facades\Route;

Route::get('/events/calendar', [CalendarController::class, 'index'])->name('api.events.calendar');
