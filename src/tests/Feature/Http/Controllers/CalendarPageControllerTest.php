<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class CalendarPageControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_page_offers_the_public_subscription_to_guests(): void
    {
        $feedUrl = route('calendar.public.ics');
        $webcalUrl = (string) preg_replace('#^https?://#', 'webcal://', $feedUrl);

        $this->get('/calendario')->assertInertia(fn (Assert $page): Assert => $page
            ->component('Calendar/Index')
            ->where('publicCalendarFeed.feedUrl', $feedUrl)
            ->where('publicCalendarFeed.webcalUrl', $webcalUrl)
            ->where('publicCalendarFeed.googleUrl', fn (string $url): bool => str_starts_with($url, 'https://calendar.google.com/calendar/r?cid='))
            ->where('publicCalendarFeed.outlookUrl', fn (string $url): bool => str_starts_with($url, 'https://outlook.live.com/calendar/0/addfromweb?'))
        );
    }

    public function test_the_page_offers_the_same_subscription_to_signed_in_people(): void
    {
        $this->actingAs(UserModel::factory()->create())->get('/calendario')
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('publicCalendarFeed.feedUrl', route('calendar.public.ics'))
            );
    }
}
