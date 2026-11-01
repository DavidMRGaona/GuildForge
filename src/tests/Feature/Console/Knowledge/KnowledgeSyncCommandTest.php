<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Knowledge;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Knowledge\KnowledgeFixtures;
use Tests\TestCase;

final class KnowledgeSyncCommandTest extends TestCase
{
    use KnowledgeFixtures;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpKnowledgeSandbox();
    }

    protected function tearDown(): void
    {
        $this->tearDownKnowledgeSandbox();
        parent::tearDown();
    }

    public function test_command_prints_the_report_and_succeeds_with_errors(): void
    {
        $this->writeCoreGuide('a.md', 'account.one');
        $this->writeFile($this->corePath().'/es/broken.md', "nothing\n");

        $this->artisanCommand('knowledge:sync')
            ->expectsOutputToContain('Inserted: 1')
            ->expectsOutputToContain('broken.md')
            ->assertSuccessful();
    }
}
