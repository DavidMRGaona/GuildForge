<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Forum\Events;

use App\Domain\Forum\Events\ForumDiscussionStarted;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ForumDiscussionStartedTest extends TestCase
{
    public function test_it_keeps_the_announcement_data(): void
    {
        $event = new ForumDiscussionStarted('31', 'Busco grupo de rol', 'https://forum.example.test/d/31', 'Hola a todos', ['General', 'Rol']);

        $this->assertSame('31', $event->discussionId);
        $this->assertSame('Busco grupo de rol', $event->title);
        $this->assertSame('https://forum.example.test/d/31', $event->url);
        $this->assertSame('Hola a todos', $event->excerpt);
        $this->assertSame(['General', 'Rol'], $event->tags);
    }

    public function test_excerpt_and_tags_are_optional(): void
    {
        $event = new ForumDiscussionStarted('31', 'Busco grupo de rol', 'http://localhost/d/31');

        $this->assertSame('', $event->excerpt);
        $this->assertSame([], $event->tags);
    }

    public function test_it_rejects_a_blank_discussion_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ForumDiscussionStarted(' ', 'Título', 'https://forum.example.test/d/1');
    }

    public function test_it_rejects_a_blank_title(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ForumDiscussionStarted('1', '  ', 'https://forum.example.test/d/1');
    }

    public function test_it_rejects_a_url_that_is_not_absolute_http(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ForumDiscussionStarted('1', 'Título', 'javascript:alert(1)');
    }
}
