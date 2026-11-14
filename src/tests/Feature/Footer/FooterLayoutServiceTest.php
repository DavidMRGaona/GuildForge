<?php

declare(strict_types=1);

namespace Tests\Feature\Footer;

use App\Application\Footer\Services\FooterLayoutServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Footer\ValueObjects\FooterBlock;
use App\Domain\Footer\ValueObjects\FooterColumn;
use App\Domain\Footer\ValueObjects\FooterLayout;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class FooterLayoutServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_without_a_row_the_layout_is_empty(): void
    {
        $this->assertTrue(app(FooterLayoutServiceInterface::class)->layout()->isEmpty());
    }

    public function test_a_saved_layout_is_read_back(): void
    {
        $layout = FooterLayout::of([
            new FooterColumn([new FooterBlock('core.text', 'Quiénes somos', ['content' => '<p>Hola <a href="https://example.com/a">aquí</a></p>'])]),
            new FooterColumn([new FooterBlock('core.links', null, ['menu' => 'footer_2'])]),
        ]);

        app(FooterLayoutServiceInterface::class)->save($layout);

        $this->assertEquals($layout, app(FooterLayoutServiceInterface::class)->layout());
    }

    public function test_it_stores_readable_json(): void
    {
        app(FooterLayoutServiceInterface::class)->save(FooterLayout::of([
            new FooterColumn([new FooterBlock('core.text', 'Quiénes somos', ['content' => '<img src="https://example.com/a.png">'])]),
        ]));

        $stored = (string) DB::table('settings')->where('key', 'footer_layout')->value('value');

        // Unescaped slashes and accents: the media library reads URLs in the stored text
        $this->assertStringContainsString('https://example.com/a.png', $stored);
        $this->assertStringContainsString('Quiénes somos', $stored);
        $this->assertStringStartsWith('{"version":1,', $stored);
    }

    public function test_saving_an_empty_layout_clears_it(): void
    {
        $service = app(FooterLayoutServiceInterface::class);
        $service->save(FooterLayout::of([new FooterColumn([new FooterBlock('core.text', null, [])])]));

        $service->save(FooterLayout::of([new FooterColumn([])]));

        $this->assertSame('', app(SettingsServiceInterface::class)->get('footer_layout'));
        $this->assertTrue($service->layout()->isEmpty());
    }

    public function test_an_unreadable_row_is_an_empty_layout(): void
    {
        app(SettingsServiceInterface::class)->set('footer_layout', '{no es json');

        $this->assertTrue(app(FooterLayoutServiceInterface::class)->layout()->isEmpty());
    }
}
