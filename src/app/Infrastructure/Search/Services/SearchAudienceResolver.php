<?php

declare(strict_types=1);

namespace App\Infrastructure\Search\Services;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Search\Services\SearchAudienceResolverInterface;
use App\Application\Services\EmailVerificationPolicyInterface;
use App\Domain\Search\Enums\SearchAudience;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class SearchAudienceResolver implements SearchAudienceResolverInterface
{
    public function __construct(
        private EmailVerificationPolicyInterface $emailVerification,
        private AssociationMembershipServiceInterface $membership,
        private LoggerInterface $logger,
    ) {}

    public function for(?object $user): array
    {
        if ($user === null) {
            return [SearchAudience::Public];
        }

        try {
            // Same rule as the help guides: an account that must verify searches as a guest
            if ($this->emailVerification->mustVerify($user)) {
                return [SearchAudience::Public];
            }

            $audiences = [SearchAudience::Public, SearchAudience::Authenticated];
            if ($this->membership->isAssociationMember($user)) {
                $audiences[] = SearchAudience::Members;
            }

            return $audiences;
        } catch (Throwable $e) {
            $this->logger->warning('[SiteSearch] Could not resolve the search audiences, searching public documents only: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return [SearchAudience::Public];
        }
    }
}
