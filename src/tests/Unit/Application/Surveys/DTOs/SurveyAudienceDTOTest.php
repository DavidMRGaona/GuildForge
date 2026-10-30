<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Surveys\DTOs;

use App\Application\Surveys\DTOs\SurveyAudienceDTO;
use App\Application\Surveys\DTOs\SurveyGuestDTO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SurveyAudienceDTOTest extends TestCase
{
    public function test_duplicate_user_ids_are_collapsed(): void
    {
        $audience = new SurveyAudienceDTO(userIds: ['u1', 'u2', 'u1']);

        $this->assertSame(['u1', 'u2'], $audience->userIds);
    }

    public function test_count_adds_users_and_guests(): void
    {
        $audience = new SurveyAudienceDTO(
            userIds: ['u1', 'u2'],
            guests: [new SurveyGuestDTO('participant-1', 'Jane Doe', 'jane@example.com')],
        );

        $this->assertSame(3, $audience->count());
    }

    public function test_empty_has_no_one(): void
    {
        $audience = SurveyAudienceDTO::empty();

        $this->assertSame([], $audience->userIds);
        $this->assertSame([], $audience->guests);
        $this->assertSame(0, $audience->count());
    }

    public function test_two_guests_with_the_same_reference_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SurveyAudienceDTO(guests: [
            new SurveyGuestDTO('participant-1', 'Jane Doe', 'jane@example.com'),
            new SurveyGuestDTO('participant-1', 'John Doe', 'john@example.com'),
        ]);
    }

    public function test_a_guest_needs_a_valid_email(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SurveyGuestDTO('participant-1', 'Jane Doe', 'not-an-email');
    }

    public function test_a_guest_needs_a_reference(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SurveyGuestDTO('  ', 'Jane Doe', 'jane@example.com');
    }
}
