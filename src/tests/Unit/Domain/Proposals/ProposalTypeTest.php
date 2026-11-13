<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Proposals;

use App\Domain\Proposals\Enums\ProposalType;
use PHPUnit\Framework\TestCase;

final class ProposalTypeTest extends TestCase
{
    public function test_each_type_names_its_permissions(): void
    {
        $this->assertSame('events.propose', ProposalType::Event->proposePermission());
        $this->assertSame('events.review', ProposalType::Event->reviewPermission());
        $this->assertSame('events.publish_proposals_directly', ProposalType::Event->publishDirectlyPermission());

        $this->assertSame('articles.propose', ProposalType::Article->proposePermission());
        $this->assertSame('articles.review', ProposalType::Article->reviewPermission());
        $this->assertSame('articles.publish_proposals_directly', ProposalType::Article->publishDirectlyPermission());
    }
}
