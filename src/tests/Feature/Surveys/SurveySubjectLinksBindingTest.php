<?php

declare(strict_types=1);

namespace Tests\Feature\Surveys;

use App\Application\Surveys\Contracts\SurveySubjectLinksInterface;
use App\Application\Surveys\DTOs\SurveySubjectLinksDTO;
use Tests\TestCase;

final class SurveySubjectLinksBindingTest extends TestCase
{
    public function test_without_a_surveys_module_the_container_gives_no_links(): void
    {
        $links = $this->app->make(SurveySubjectLinksInterface::class);

        $this->assertTrue($links->forSubject('example-thing', 'subject-1', 'user-1')->isEmpty());
        $this->assertTrue($links->forSubject('example-thing', 'subject-1', null)->isEmpty());
    }

    public function test_the_default_is_shared(): void
    {
        $this->assertSame(
            $this->app->make(SurveySubjectLinksInterface::class),
            $this->app->make(SurveySubjectLinksInterface::class),
        );
    }

    public function test_a_module_binding_replaces_the_default(): void
    {
        $moduleLinks = new class implements SurveySubjectLinksInterface
        {
            public function forSubject(string $sourceType, string $subjectId, ?string $userId): SurveySubjectLinksDTO
            {
                return new SurveySubjectLinksDTO(manageUrl: "https://example.com/{$sourceType}/{$subjectId}");
            }
        };

        $this->app->singleton(SurveySubjectLinksInterface::class, fn (): SurveySubjectLinksInterface => $moduleLinks);

        $this->assertSame(
            'https://example.com/example-thing/subject-1',
            $this->app->make(SurveySubjectLinksInterface::class)->forSubject('example-thing', 'subject-1', null)->manageUrl,
        );
    }
}
