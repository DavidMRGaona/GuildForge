<?php

declare(strict_types=1);

use App\Http\Controllers\Api\SesWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/ses', SesWebhookController::class)->name('api.webhooks.ses');
