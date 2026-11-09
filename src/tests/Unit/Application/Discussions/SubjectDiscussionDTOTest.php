<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Discussions;

use App\Application\Discussions\DTOs\DiscussionActivityDTO;
use App\Application\Discussions\DTOs\SubjectDiscussionDTO;
use App\Application\Discussions\DTOs\TopicActivityDTO;
use App\Application\Services\TimezoneServiceInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use Tests\TestCase;

final class SubjectDiscussionDTOTest extends TestCase
{
    public function test_it_gives_the_page_the_discussion_and_its_topics_with_dates_in_the_display_zone(): void
    {
        $lastPostedAt = new DateTimeImmutable('2027-01-02T09:30:00Z');
        $dto = new SubjectDiscussionDTO(
            new DiscussionActivityDTO('https://forum.example.org/d/31', 'https://web.example.org/foro/entrar', 4, $lastPostedAt),
            [new TopicActivityDTO('Sistema de ejemplo', 'https://forum.example.org/t/sistema', 3)],
        );
        $timezone = $this->app->make(TimezoneServiceInterface::class);

        $this->assertSame([
            'discussion' => [
                'url' => 'https://forum.example.org/d/31',
                'actionUrl' => 'https://web.example.org/foro/entrar',
                'commentCount' => 4,
                'lastPostedAt' => $timezone->iso($lastPostedAt),
            ],
            'topics' => [[
                'label' => 'Sistema de ejemplo',
                'url' => 'https://forum.example.org/t/sistema',
                'discussionCount' => 3,
                'lastPostedAt' => null,
            ]],
        ], $dto->toArray($timezone));
    }

    public function test_nothing_to_show_is_null_not_an_empty_answer(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SubjectDiscussionDTO(null, []);
    }

    public function test_links_must_be_absolute_http_urls(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DiscussionActivityDTO('javascript:alert(1)', 'https://web.example.org/foro/entrar', 0);
    }

    public function test_counts_cannot_be_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TopicActivityDTO('Sistema', 'https://forum.example.org/t/sistema', -1);
    }

    public function test_a_topic_needs_a_label(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TopicActivityDTO(' ', 'https://forum.example.org/t/sistema', 1);
    }
}
