<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Services\SettingsServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsServiceInterface $settingsService,
    ) {}

    public function location(): JsonResponse
    {
        $settings = $this->settingsService->getLocationSettings();

        return response()->json($settings)
            ->header('Cache-Control', 'max-age=3600, public');
    }
}
