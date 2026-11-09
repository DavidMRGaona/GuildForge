<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Publications;

use App\Application\Publications\DTOs\PublicationMembershipDTO;
use App\Application\Publications\DTOs\PublicationRosterDTO;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PublicationRosterDTOTest extends TestCase
{
    private const string PLAYER = '0195f3a2-7c4e-7b1a-9d3f-2a6b8c4e1f07';

    private const string DIRECTOR = '0195f3a2-7c4e-7b1a-9d3f-2a6b8c4e1f08';

    public function test_it_keeps_members_and_leaders_once_and_in_lowercase(): void
    {
        $roster = new PublicationRosterDTO(
            memberIds: [self::PLAYER, strtoupper(self::PLAYER), self::DIRECTOR],
            leaderIds: [strtoupper(self::DIRECTOR), self::DIRECTOR],
        );

        $this->assertSame([self::PLAYER, self::DIRECTOR], $roster->memberIds);
        $this->assertSame([self::DIRECTOR], $roster->leaderIds);
    }

    public function test_a_leader_need_not_be_a_member(): void
    {
        $roster = new PublicationRosterDTO(memberIds: [self::PLAYER], leaderIds: [self::DIRECTOR]);

        $this->assertSame([self::DIRECTOR], $roster->leaderIds);
        $this->assertSame([self::PLAYER], $roster->memberIds);
    }

    public function test_an_empty_roster_is_valid(): void
    {
        $roster = new PublicationRosterDTO;

        $this->assertSame([], $roster->memberIds);
        $this->assertSame([], $roster->leaderIds);
    }

    public function test_ids_that_are_not_user_ids_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PublicationRosterDTO(memberIds: ['not-a-uuid']);
    }

    public function test_a_membership_names_its_publication(): void
    {
        $membership = new PublicationMembershipDTO(type: 'campaign', id: 'campaign-1', isLeader: true);

        $this->assertSame('campaign', $membership->type);
        $this->assertSame('campaign-1', $membership->id);
        $this->assertTrue($membership->isLeader);
    }

    public function test_a_membership_needs_the_type_and_the_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PublicationMembershipDTO(type: 'campaign', id: ' ', isLeader: false);
    }
}
