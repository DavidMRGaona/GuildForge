<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Profiles;

use App\Domain\Profiles\ProfileHandleChangeRule;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProfileHandleChangeRuleTest extends TestCase
{
    public function test_an_address_never_chosen_can_be_chosen_now(): void
    {
        $now = new DateTimeImmutable('2026-11-15T10:00:00+00:00');

        $this->assertTrue(ProfileHandleChangeRule::allows(null, $now));
        $this->assertNull(ProfileHandleChangeRule::nextChangeAt(null, $now));
    }

    public function test_a_chosen_address_can_change_again_thirty_days_later(): void
    {
        $chosen = new DateTimeImmutable('2026-10-16T10:00:00+00:00');

        $this->assertFalse(ProfileHandleChangeRule::allows($chosen, new DateTimeImmutable('2026-11-15T09:59:59+00:00')));
        $this->assertTrue(ProfileHandleChangeRule::allows($chosen, new DateTimeImmutable('2026-11-15T10:00:00+00:00')));
        $this->assertTrue(ProfileHandleChangeRule::allows($chosen, new DateTimeImmutable('2027-01-01T00:00:00+00:00')));
    }

    public function test_the_next_change_is_given_only_while_it_is_not_allowed(): void
    {
        $chosen = new DateTimeImmutable('2026-10-16T10:00:00+00:00');

        $this->assertEquals(
            new DateTimeImmutable('2026-11-15T10:00:00+00:00'),
            ProfileHandleChangeRule::nextChangeAt($chosen, new DateTimeImmutable('2026-11-01T00:00:00+00:00')),
        );
        $this->assertNull(ProfileHandleChangeRule::nextChangeAt($chosen, new DateTimeImmutable('2026-11-15T10:00:00+00:00')));
    }

    public function test_a_choice_dated_in_the_future_waits_thirty_days_from_it(): void
    {
        // A clock set back after the choice never shortens the wait
        $chosen = new DateTimeImmutable('2026-12-01T00:00:00+00:00');

        $this->assertFalse(ProfileHandleChangeRule::allows($chosen, new DateTimeImmutable('2026-11-15T00:00:00+00:00')));
        $this->assertEquals(new DateTimeImmutable('2026-12-31T00:00:00+00:00'), ProfileHandleChangeRule::nextChangeAt($chosen, new DateTimeImmutable('2026-11-15T00:00:00+00:00')));
    }
}
