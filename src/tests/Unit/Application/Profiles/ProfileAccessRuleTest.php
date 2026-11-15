<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Profiles;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Profiles\DTOs\PublicProfileDTO;
use App\Application\Profiles\Services\ProfileAccessRule;
use App\Application\Services\EmailVerificationPolicyInterface;
use App\Domain\Profiles\ProfileVisibility;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

/**
 * Who sees a public profile: every visibility × every kind of viewer × every state of the owner.
 */
final class ProfileAccessRuleTest extends TestCase
{
    private const string OWNER = 'owner-id';

    /** Viewers whose account must still verify its email. */
    private const array UNVERIFIED = ['unverified-member', 'unverified-socio'];

    /** Viewers who are association members. */
    private const array SOCIOS = ['socio', 'unverified-socio'];

    /**
     * What an active owner with a public name allows, per visibility and viewer.
     *
     * @var array<string, array<string, bool>>
     */
    private const array ACTIVE_OWNER_WITH_NAME = [
        'public' => ['guest' => true, 'owner' => true, 'member' => true, 'unverified-member' => true, 'socio' => true, 'unverified-socio' => true],
        'members' => ['guest' => false, 'owner' => true, 'member' => true, 'unverified-member' => false, 'socio' => true, 'unverified-socio' => false],
        'association' => ['guest' => false, 'owner' => true, 'member' => false, 'unverified-member' => false, 'socio' => true, 'unverified-socio' => false],
        'hidden' => ['guest' => false, 'owner' => true, 'member' => false, 'unverified-member' => false, 'socio' => false, 'unverified-socio' => false],
    ];

    /** @var list<string> */
    private array $logged = [];

    /**
     * @return iterable<string, array{ProfileVisibility, string, string, bool}>
     */
    public static function matrix(): iterable
    {
        foreach (ProfileVisibility::cases() as $visibility) {
            foreach (self::ACTIVE_OWNER_WITH_NAME[$visibility->value] as $viewer => $expected) {
                yield "{$visibility->value} · {$viewer} · active" => [$visibility, $viewer, 'active', $expected];
                // Without a public name only the owner sees it, with a prompt to choose one
                yield "{$visibility->value} · {$viewer} · without public name" => [$visibility, $viewer, 'nameless', $viewer === 'owner'];
                // A deactivated or anonymized account is seen by nobody, its owner included
                yield "{$visibility->value} · {$viewer} · deactivated" => [$visibility, $viewer, 'deactivated', false];
                yield "{$visibility->value} · {$viewer} · anonymized" => [$visibility, $viewer, 'anonymized', false];
            }
        }
    }

    #[DataProvider('matrix')]
    public function test_who_sees_the_profile(ProfileVisibility $visibility, string $viewer, string $ownerState, bool $expected): void
    {
        $rule = $this->rule();

        $this->assertSame($expected, $rule->canView($this->profile($visibility, $ownerState), $this->viewer($viewer)));
        $this->assertSame([], $this->logged);
    }

    public function test_a_blank_public_name_counts_as_none(): void
    {
        $profile = $this->profile(ProfileVisibility::Public, 'active', publicName: "   \t");

        $this->assertFalse($this->rule()->canView($profile, null));
        $this->assertTrue($this->rule()->canView($profile, $this->viewer('owner')));
    }

    public function test_the_owner_is_recognised_by_the_account_id(): void
    {
        $rule = $this->rule();
        $profile = $this->profile(ProfileVisibility::Hidden, 'active');

        $this->assertTrue($rule->isOwner($profile, $this->viewer('owner')));
        $this->assertFalse($rule->isOwner($profile, $this->viewer('member')));
        $this->assertFalse($rule->isOwner($profile, null));
    }

    public function test_a_failing_email_check_hides_the_profile_and_is_logged(): void
    {
        $rule = $this->rule(emailsFail: true);

        $this->assertFalse($rule->canView($this->profile(ProfileVisibility::Members, 'active'), $this->viewer('member')));
        $this->assertSame(['warning'], $this->logged);
    }

    public function test_a_failing_membership_check_hides_the_profile_and_is_logged(): void
    {
        $rule = $this->rule(membershipFails: true);

        $this->assertFalse($rule->canView($this->profile(ProfileVisibility::Association, 'active'), $this->viewer('socio')));
        $this->assertSame(['warning'], $this->logged);
    }

    public function test_the_owner_needs_no_check_that_could_fail(): void
    {
        $rule = $this->rule(emailsFail: true, membershipFails: true);

        $this->assertTrue($rule->canView($this->profile(ProfileVisibility::Association, 'active'), $this->viewer('owner')));
        $this->assertSame([], $this->logged);
    }

    public function recordLog(string $level): void
    {
        $this->logged[] = $level;
    }

    private function rule(bool $emailsFail = false, bool $membershipFails = false): ProfileAccessRule
    {
        $emails = new readonly class($emailsFail, self::UNVERIFIED) implements EmailVerificationPolicyInterface
        {
            /** @param list<string> $unverified */
            public function __construct(private bool $fail, private array $unverified) {}

            public function mustVerify(object $user): bool
            {
                if ($this->fail) {
                    throw new RuntimeException('settings unreadable');
                }

                return $user instanceof Authenticatable && in_array($user->getAuthIdentifier(), $this->unverified, true);
            }
        };

        $membership = new readonly class($membershipFails, self::SOCIOS) implements AssociationMembershipServiceInterface
        {
            /** @param list<string> $socios */
            public function __construct(private bool $fail, private array $socios) {}

            public function roleName(): string
            {
                return 'socio';
            }

            public function isAssociationMember(object $user): bool
            {
                if ($this->fail) {
                    throw new RuntimeException('roles unreadable');
                }

                return $user instanceof Authenticatable && in_array($user->getAuthIdentifier(), $this->socios, true);
            }
        };

        $test = $this;
        $log = new class($test) extends AbstractLogger
        {
            public function __construct(private readonly ProfileAccessRuleTest $test) {}

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->test->recordLog((string) $level);
            }
        };

        return new ProfileAccessRule($emails, $membership, $log);
    }

    private function profile(ProfileVisibility $visibility, string $state, ?string $publicName = 'Ana Prueba'): PublicProfileDTO
    {
        return new PublicProfileDTO(
            userId: self::OWNER,
            handle: 'ana-prueba',
            publicName: $state === 'nameless' ? null : $publicName,
            visibility: $visibility,
            deactivated: $state === 'deactivated',
            anonymized: $state === 'anonymized',
        );
    }

    private function viewer(string $kind): ?Authenticatable
    {
        return match ($kind) {
            'guest' => null,
            'owner' => new GenericUser(['id' => self::OWNER]),
            default => new GenericUser(['id' => $kind]),
        };
    }
}
