<?php

declare(strict_types=1);

namespace Tests\Unit\Application\SiteNotifications;

use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Application\SiteNotifications\DTOs\SiteNotificationGroupDTO;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SiteNotificationDTOTest extends TestCase
{
    public function test_it_keeps_what_the_notice_says(): void
    {
        $notice = new SiteNotificationDTO(
            title: 'Ana respondió en «La tumba de los horrores»',
            url: '/foro/temas/la-tumba?mensaje=42#m42',
            body: 'Tiro iniciativa y entro en la cripta.',
            icon: 'message-square',
            groupKey: 'forum.topic:0193b2c4',
            actorId: '0193b2c4-7f1e-7a00-9c3d-5e6f7a8b9c0d',
        );

        $this->assertSame('Ana respondió en «La tumba de los horrores»', $notice->title);
        $this->assertSame('/foro/temas/la-tumba?mensaje=42#m42', $notice->url);
        $this->assertSame('Tiro iniciativa y entro en la cripta.', $notice->body);
        $this->assertSame('message-square', $notice->icon);
        $this->assertSame('forum.topic:0193b2c4', $notice->groupKey);
        $this->assertSame('0193b2c4-7f1e-7a00-9c3d-5e6f7a8b9c0d', $notice->actorId);
    }

    public function test_only_the_title_and_the_url_are_required(): void
    {
        $notice = new SiteNotificationDTO(title: 'Nueva encuesta', url: '/encuestas/abc');

        $this->assertNull($notice->body);
        $this->assertNull($notice->icon);
        $this->assertNull($notice->groupKey);
        $this->assertNull($notice->actorId);
        $this->assertNull($notice->groupedTitle);
    }

    public function test_the_title_is_kept_on_one_line_and_cut(): void
    {
        $notice = new SiteNotificationDTO(title: "  Ana\n respondió\t\ten «Tema» \r\n", url: '/');

        $this->assertSame('Ana respondió en «Tema»', $notice->title);

        $long = new SiteNotificationDTO(title: str_repeat('a', 300), url: '/');

        $this->assertSame(SiteNotificationDTO::TITLE_MAX_LENGTH, mb_strlen($long->title));
        $this->assertStringEndsWith('…', $long->title);
    }

    public function test_the_body_is_plain_text_on_one_line_and_cut(): void
    {
        $notice = new SiteNotificationDTO(title: 'Título', url: '/', body: "Línea uno\n\nLínea dos");

        $this->assertSame('Línea uno Línea dos', $notice->body);

        $long = new SiteNotificationDTO(title: 'Título', url: '/', body: str_repeat('b', 500));

        $this->assertSame(SiteNotificationDTO::BODY_MAX_LENGTH, mb_strlen((string) $long->body));
    }

    public function test_an_empty_body_is_no_body(): void
    {
        $this->assertNull((new SiteNotificationDTO(title: 'Título', url: '/', body: "  \n "))->body);
    }

    public function test_an_empty_title_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SiteNotificationDTO(title: " \n\t ", url: '/');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function urlsOutsideTheSite(): array
    {
        return [
            'absolute' => ['https://example.org/foro'],
            'protocol relative' => ['//example.org/foro'],
            'backslash' => ['/\\example.org'],
            'javascript' => ['javascript:alert(1)'],
            'relative without slash' => ['foro/temas'],
            'empty' => [''],
            'control characters' => ["/foro\n/temas"],
        ];
    }

    #[DataProvider('urlsOutsideTheSite')]
    public function test_the_url_must_be_a_path_of_the_site(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SiteNotificationDTO(title: 'Título', url: $url);
    }

    public function test_the_icon_must_be_a_lucide_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SiteNotificationDTO(title: 'Título', url: '/', icon: 'Message Square');
    }

    public function test_the_group_key_must_be_storable(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SiteNotificationDTO(title: 'Título', url: '/', groupKey: 'tema con espacios');
    }

    public function test_the_grouped_title_is_built_from_the_group(): void
    {
        $notice = new SiteNotificationDTO(
            title: 'Ana respondió en «Tema»',
            url: '/',
            groupKey: 'forum.topic:1',
            groupedTitle: static fn (SiteNotificationGroupDTO $group): string => sprintf('Ana y %d más respondieron en «Tema»', $group->otherActors),
        );

        $this->assertSame(
            'Ana y 3 más respondieron en «Tema»',
            $notice->titleFor(new SiteNotificationGroupDTO(count: 5, otherActors: 3)),
        );
    }

    public function test_a_single_notice_or_one_without_a_grouped_title_keeps_its_title(): void
    {
        $grouped = new SiteNotificationDTO(
            title: 'Ana respondió en «Tema»',
            url: '/',
            groupKey: 'forum.topic:1',
            groupedTitle: static fn (SiteNotificationGroupDTO $group): string => 'Agrupado',
        );
        $plain = new SiteNotificationDTO(title: 'Ana respondió en «Tema»', url: '/', groupKey: 'forum.topic:1');

        $this->assertSame('Ana respondió en «Tema»', $grouped->titleFor(new SiteNotificationGroupDTO(count: 1, otherActors: 0)));
        $this->assertSame('Ana respondió en «Tema»', $plain->titleFor(new SiteNotificationGroupDTO(count: 4, otherActors: 2)));
    }

    public function test_the_grouped_title_is_cleaned_like_the_title(): void
    {
        $notice = new SiteNotificationDTO(
            title: 'Uno',
            url: '/',
            groupKey: 'forum.topic:1',
            groupedTitle: static fn (SiteNotificationGroupDTO $group): string => "Ana y 1 más\nrespondieron",
        );

        $this->assertSame('Ana y 1 más respondieron', $notice->titleFor(new SiteNotificationGroupDTO(count: 2, otherActors: 1)));
    }

    public function test_an_empty_grouped_title_falls_back_to_the_title(): void
    {
        $notice = new SiteNotificationDTO(
            title: 'Uno',
            url: '/',
            groupKey: 'forum.topic:1',
            groupedTitle: static fn (SiteNotificationGroupDTO $group): string => '   ',
        );

        $this->assertSame('Uno', $notice->titleFor(new SiteNotificationGroupDTO(count: 2, otherActors: 1)));
    }
}
