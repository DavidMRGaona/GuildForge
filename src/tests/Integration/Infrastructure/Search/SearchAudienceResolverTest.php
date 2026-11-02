<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Search;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Search\Services\SearchAudienceResolverInterface;
use App\Application\Services\EmailVerificationPolicyInterface;
use App\Domain\Search\Enums\SearchAudience;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use stdClass;
use Tests\TestCase;

final class SearchAudienceResolverTest extends TestCase
{
    public function test_guest_gets_public_only(): void
    {
        $this->assertSame([SearchAudience::Public], $this->resolver()->for(null));
    }

    public function test_user_who_must_verify_gets_public_only(): void
    {
        $this->fakePolicies(mustVerify: true, member: true);

        $this->assertSame([SearchAudience::Public], $this->resolver()->for(new stdClass));
    }

    public function test_signed_in_user_gets_authenticated_audience(): void
    {
        $this->fakePolicies(mustVerify: false, member: false);

        $this->assertSame(
            [SearchAudience::Public, SearchAudience::Authenticated],
            $this->resolver()->for(new stdClass),
        );
    }

    public function test_member_gets_members_audience(): void
    {
        $this->fakePolicies(mustVerify: false, member: true);

        $this->assertSame(
            [SearchAudience::Public, SearchAudience::Authenticated, SearchAudience::Members],
            $this->resolver()->for(new stdClass),
        );
    }

    public function test_failing_membership_check_fails_closed(): void
    {
        $log = Log::spy();
        $this->fakePolicies(mustVerify: false, member: new RuntimeException('roles table missing'));

        $this->assertSame([SearchAudience::Public], $this->resolver()->for(new stdClass));
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_failing_verification_check_fails_closed(): void
    {
        $log = Log::spy();
        $this->fakePolicies(mustVerify: new RuntimeException('settings unreadable'), member: true);

        $this->assertSame([SearchAudience::Public], $this->resolver()->for(new stdClass));
        $log->shouldHaveReceived('warning')->once();
    }

    private function resolver(): SearchAudienceResolverInterface
    {
        return $this->app->make(SearchAudienceResolverInterface::class);
    }

    private function fakePolicies(bool|RuntimeException $mustVerify, bool|RuntimeException $member): void
    {
        $verification = Mockery::mock(EmailVerificationPolicyInterface::class);
        $verificationExpectation = $verification->allows('mustVerify');
        $mustVerify instanceof RuntimeException
            ? $verificationExpectation->andThrow($mustVerify)
            : $verificationExpectation->andReturn($mustVerify);

        $membership = Mockery::mock(AssociationMembershipServiceInterface::class);
        $membershipExpectation = $membership->allows('isAssociationMember');
        $member instanceof RuntimeException
            ? $membershipExpectation->andThrow($member)
            : $membershipExpectation->andReturn($member);

        $this->app->instance(EmailVerificationPolicyInterface::class, $verification);
        $this->app->instance(AssociationMembershipServiceInterface::class, $membership);
    }
}
