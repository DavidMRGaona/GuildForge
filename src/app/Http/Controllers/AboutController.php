<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\SettingsServiceInterface;
use Inertia\Inertia;
use Inertia\Response;
use JsonException;

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
            'aboutHeroImage' => $settings->get('about_hero_image', ''),
            'aboutTagline' => $settings->get('about_tagline', ''),
            'activities' => $this->parseActivities($settings->get('about_activities', '')),
            'joinSteps' => $this->parseJoinSteps($settings->get('join_steps', '')),
        ]);
    }

    /**
     * Parse activities JSON string into array.
     *
     * @return array<int, array{icon: string, title: string, description: string}>
     */
    private function parseActivities(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }

        try {
            $activities = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($activities)) {
                return [];
            }

            return array_values(array_filter(
                $activities,
                fn ($activity) => is_array($activity)
                    && isset($activity['icon'], $activity['title'], $activity['description'])
            ));
        } catch (JsonException) {
            return [];
        }
    }

    /**
     * @return array<int, array{title: string, description: string|null}>
     */
    private function parseJoinSteps(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        try {
            $joinSteps = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($joinSteps)) {
                return [];
            }
            $filtered = array_filter(
                $joinSteps,
                fn ($step) => is_array($step) && isset($step['title'])
            );

            return array_values(array_map(
                fn (array $step): array => [
                    'title' => (string) $step['title'],
                    'description' => isset($step['description']) ? (string) $step['description'] : null,
                ],
                $filtered
            ));
        } catch (JsonException) {
            return [];
        }
    }
}
