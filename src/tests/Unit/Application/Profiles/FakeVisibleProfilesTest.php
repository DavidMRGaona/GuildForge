<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Profiles;

use App\Application\Profiles\DTOs\VisibleProfileDTO;
use App\Application\Profiles\Services\VisibleProfilesInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\Profiles\FakeVisibleProfiles;

/**
 * The test double modules use in place of VisibleProfilesInterface (core#840): it reads the ids as the
 * core does, so a module's tests fail where the core would.
 */
final class FakeVisibleProfilesTest extends TestCase
{
    private const string ANA = '0195f3a2-7c1e-7d4b-9a8e-2f6c1b3d4e5f';

    private const string LUIS = '0195f3a2-7c1e-7d4b-9a8e-2f6c1b3d4e60';

    private const string NOBODY = '0195f3a2-7c1e-7d4b-9a8e-2f6c1b3d4e61';

    public function test_it_answers_the_profiles_given_among_the_ids_in_their_order(): void
    {
        $ana = FakeVisibleProfiles::profile(self::ANA, 'ana-garcia', 'Ana García');
        $luis = FakeVisibleProfiles::profile(self::LUIS, 'luis', 'Luis');
        $fake = new FakeVisibleProfiles([$ana, $luis]);

        $this->assertSame([$luis, $ana], $fake->among([self::LUIS, self::NOBODY, strtoupper(self::ANA), 'no es un id', self::LUIS], null));
        $this->assertSame([['userIds' => [self::LUIS, self::NOBODY, self::ANA], 'viewer' => null]], $fake->calls);
    }

    public function test_a_closure_decides_what_each_viewer_sees(): void
    {
        $fake = new FakeVisibleProfiles(
            [FakeVisibleProfiles::profile(self::ANA)],
            static fn (VisibleProfileDTO $profile, ?Authenticatable $viewer): bool => $viewer instanceof Authenticatable,
        );

        $this->assertSame([], $fake->among([self::ANA], null));
    }

    public function test_more_than_the_limit_is_refused_like_the_core_does(): void
    {
        $ids = [];
        for ($i = 0; $i <= VisibleProfilesInterface::MAX_IDS; $i++) {
            $ids[] = sprintf('0195f3a2-7c1e-7d4b-9a8e-%012x', $i);
        }

        $this->expectException(InvalidArgumentException::class);

        (new FakeVisibleProfiles)->among($ids, null);
    }
}
