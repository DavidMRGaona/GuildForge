<?php

declare(strict_types=1);

namespace Tests\Integration\Filament\Media\Sources;

use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Filament\Media\Sources\SiteSettingsMediaSource;
use App\Filament\Pages\Settings\AboutPageSettings;
use App\Filament\Pages\SiteSettings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

final class SiteSettingsMediaSourceTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private function source(): SiteSettingsMediaSource
    {
        return app(SiteSettingsMediaSource::class);
    }

    private function set(string $key, string $value): void
    {
        app(SettingsServiceInterface::class)->set($key, $value);
    }

    public function test_blank_settings_are_not_usages(): void
    {
        $this->set('site_logo_light', '');
        $this->set('site_logo_dark', '   ');

        $this->assertSame([], iterator_to_array($this->source()->usages(), false));
    }

    public function test_usages_list_each_image_setting_with_its_key_and_role(): void
    {
        $this->set('site_logo_light', 'branding/logo.png');
        $this->set('about_hero_image', 'about/hero.jpg');

        $usages = iterator_to_array($this->source()->usages(), false);

        $this->assertCount(2, $usages);
        $this->assertSame('site_logo_light', $usages[0]->recordId);
        $this->assertSame('Logo claro', $usages[0]->role);
        $this->assertSame('about_hero_image', $usages[1]->recordId);
        $this->assertSame('Imagen de Nosotros', $usages[1]->role);
    }

    public function test_usages_for_one_key_and_unknown_key(): void
    {
        $this->set('site_favicon_dark', 'branding/f.png');

        $this->assertCount(1, $this->source()->usagesFor('site_favicon_dark'));
        $this->assertSame([], $this->source()->usagesFor('site_favicon_light'));
        $this->assertSame([], $this->source()->usagesFor('guild_name'));
    }

    public function test_uses_file_matches_only_the_exact_id(): void
    {
        $this->set('site_logo_email', 'branding/mail.png');

        $this->assertTrue($this->source()->usesFile(new MediaFileId('branding/mail')));
        $this->assertFalse($this->source()->usesFile(new MediaFileId('branding/mail2')));
        $this->assertFalse($this->source()->usesFile(new MediaFileId('branding/MAIL')));
    }

    public function test_edit_url_follows_the_permission_of_each_page(): void
    {
        $settings = $this->editorWithPermissions(['settings.manage']);
        $pages = $this->editorWithPermissions(['pages.manage']);
        $nobody = $this->editorWithPermissions([]);
        $source = $this->source();

        $this->assertSame(SiteSettings::getUrl(), $source->editUrl('site_logo_light', $settings));
        $this->assertNull($source->editUrl('site_logo_light', $pages));
        $this->assertSame(AboutPageSettings::getUrl(), $source->editUrl('about_hero_image', $pages));
        $this->assertNull($source->editUrl('about_hero_image', $settings));
        $this->assertNull($source->editUrl('site_logo_light', $nobody));
        $this->assertNull($source->editUrl('unknown', $settings));
    }

    public function test_editor_content_folder_is_unclaimed_until_text_sources_exist(): void
    {
        $registry = app(MediaSourceRegistryInterface::class);

        $this->assertNull($registry->claimingSource(new MediaFileId('articles/content/2026/11/x')));
        $this->assertSame('articles', $registry->claimingSource(new MediaFileId('articles/2026/11/x'))?->type());
        $this->assertSame('settings', $registry->claimingSource(new MediaFileId('branding/x'))?->type());
        $this->assertSame('avatars', $registry->claimingSource(new MediaFileId('users/x'))?->type());
        $this->assertCount(6, $registry->sources());
    }
}
