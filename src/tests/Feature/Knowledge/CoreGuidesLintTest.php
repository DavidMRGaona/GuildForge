<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Infrastructure\Knowledge\Lint\KnowledgeLinter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class CoreGuidesLintTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_core_guides_have_no_lint_issues(): void
    {
        $issues = $this->app->make(KnowledgeLinter::class)->lint();

        $this->assertSame(
            [],
            array_map(static fn ($issue): string => $issue->file.': '.$issue->message, $issues),
        );
    }
}
