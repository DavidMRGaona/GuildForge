<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\SettingsServiceInterface;
use Inertia\Inertia;
use Inertia\Response;

final class AboutController extends Controller
{
    public function __invoke(): Response
    {
        $settings = app(SettingsServiceInterface::class);

        return Inertia::render('About', [
            'associationName' => $settings->get('association_name', config('app.name')),
            'aboutHistory' => $settings->get('about_history', ''),
            'contactEmail' => $settings->get('contact_email', ''),
            'contactPhone' => $settings->get('contact_phone', ''),
            'contactAddress' => $settings->get('contact_address', ''),
        ]);
    }
}
