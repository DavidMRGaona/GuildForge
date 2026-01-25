<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs;

use App\Application\DTOs\ThemeSettingsDTO;
use App\Application\Services\SettingsServiceInterface;
use PHPUnit\Framework\TestCase;

final class ThemeSettingsDTOTest extends TestCase
{
    public function test_defaults_returns_dto_with_default_values(): void
    {
        $dto = ThemeSettingsDTO::defaults();

        $this->assertEquals('#D97706', $dto->primaryColor);
        $this->assertEquals('#F59E0B', $dto->primaryColorDark);
        $this->assertEquals('#57534E', $dto->secondaryColor);
        $this->assertEquals('#A8A29E', $dto->secondaryColorDark);
        $this->assertEquals('#D97706', $dto->accentColor);
        $this->assertEquals('#FAFAF9', $dto->backgroundColor);
        $this->assertEquals('#1C1917', $dto->backgroundColorDark);
        $this->assertEquals('#FFFFFF', $dto->surfaceColor);
        $this->assertEquals('#292524', $dto->surfaceColorDark);
        $this->assertEquals('#1C1917', $dto->textColor);
        $this->assertEquals('#F5F5F4', $dto->textColorDark);
        $this->assertEquals('Inter', $dto->fontHeading);
        $this->assertEquals('Inter', $dto->fontBody);
        $this->assertEquals('16px', $dto->fontSizeBase);
        $this->assertEquals('0.5rem', $dto->borderRadius);
        $this->assertEquals('0.5', $dto->shadowIntensity);
        $this->assertEquals('solid', $dto->buttonStyle);
        $this->assertFalse($dto->darkModeDefault);
        $this->assertTrue($dto->darkModeToggleVisible);
    }

    public function test_from_settings_returns_dto_with_values_from_settings_service(): void
    {
        $settingsService = $this->createMock(SettingsServiceInterface::class);
        $settingsService->method('get')->willReturnMap([
            // Color fields use '' as default in getColorOrDefault
            ['theme_primary_color', '', '#3B82F6'],
            ['theme_primary_color_dark', '', '#60A5FA'],
            ['theme_secondary_color', '', '#64748B'],
            ['theme_secondary_color_dark', '', '#94A3B8'],
            ['theme_accent_color', '', '#EF4444'],
            ['theme_background_color', '', '#FAFAFA'],
            ['theme_background_color_dark', '', '#0F172A'],
            ['theme_surface_color', '', '#F3F4F6'],
            ['theme_surface_color_dark', '', '#1E293B'],
            ['theme_text_color', '', '#1F2937'],
            ['theme_text_color_dark', '', '#FFFFFF'],
            ['theme_text_secondary_color', '', '#64748B'],
            ['theme_text_secondary_color_dark', '', '#94A3B8'],
            ['theme_text_muted_color', '', '#9CA3AF'],
            ['theme_text_muted_color_dark', '', '#9CA3AF'],
            ['theme_border_color', '', '#D1D5DB'],
            ['theme_border_color_dark', '', '#374151'],
            // String fields use '' as default in getStringOrDefault
            ['theme_font_heading', '', 'Roboto'],
            ['theme_font_body', '', 'Open Sans'],
            ['theme_button_style', '', 'outlined'],
            // Mapped fields use descriptors as defaults
            ['theme_font_size_base', 'normal', 'large'],
            ['theme_border_radius', 'medium', 'large'],
            ['theme_shadow_intensity', 'subtle', 'pronounced'],
            // Boolean fields
            ['theme_dark_mode_default', false, true],
            ['theme_dark_mode_toggle_visible', true, false],
        ]);

        $dto = ThemeSettingsDTO::fromSettings($settingsService);

        $this->assertEquals('#3B82F6', $dto->primaryColor);
        $this->assertEquals('#60A5FA', $dto->primaryColorDark);
        $this->assertEquals('#64748B', $dto->secondaryColor);
        $this->assertEquals('#94A3B8', $dto->secondaryColorDark);
        $this->assertEquals('#EF4444', $dto->accentColor);
        $this->assertEquals('#FAFAFA', $dto->backgroundColor);
        $this->assertEquals('#0F172A', $dto->backgroundColorDark);
        $this->assertEquals('#F3F4F6', $dto->surfaceColor);
        $this->assertEquals('#1E293B', $dto->surfaceColorDark);
        $this->assertEquals('#1F2937', $dto->textColor);
        $this->assertEquals('#FFFFFF', $dto->textColorDark);
        $this->assertEquals('Roboto', $dto->fontHeading);
        $this->assertEquals('Open Sans', $dto->fontBody);
        $this->assertEquals('18px', $dto->fontSizeBase);
        $this->assertEquals('0.75rem', $dto->borderRadius);
        $this->assertEquals('1.5', $dto->shadowIntensity);
        $this->assertEquals('outlined', $dto->buttonStyle);
        $this->assertTrue($dto->darkModeDefault);
        $this->assertFalse($dto->darkModeToggleVisible);
    }

    public function test_from_settings_uses_defaults_when_settings_are_empty(): void
    {
        $settingsService = $this->createMock(SettingsServiceInterface::class);
        $settingsService->method('get')->willReturnCallback(
            fn (string $key, mixed $default = null): mixed => $default
        );

        $dto = ThemeSettingsDTO::fromSettings($settingsService);

        $this->assertEquals('#D97706', $dto->primaryColor);
        $this->assertEquals('#F59E0B', $dto->primaryColorDark);
        $this->assertEquals('#57534E', $dto->secondaryColor);
        $this->assertEquals('#A8A29E', $dto->secondaryColorDark);
        $this->assertEquals('#D97706', $dto->accentColor);
        $this->assertEquals('#FAFAF9', $dto->backgroundColor);
        $this->assertEquals('#1C1917', $dto->backgroundColorDark);
        $this->assertEquals('#FFFFFF', $dto->surfaceColor);
        $this->assertEquals('#292524', $dto->surfaceColorDark);
        $this->assertEquals('#1C1917', $dto->textColor);
        $this->assertEquals('#F5F5F4', $dto->textColorDark);
        $this->assertEquals('Inter', $dto->fontHeading);
        $this->assertEquals('Inter', $dto->fontBody);
        $this->assertEquals('16px', $dto->fontSizeBase);
        $this->assertEquals('0.5rem', $dto->borderRadius);
        $this->assertEquals('0.5', $dto->shadowIntensity);
        $this->assertEquals('solid', $dto->buttonStyle);
        $this->assertFalse($dto->darkModeDefault);
        $this->assertTrue($dto->darkModeToggleVisible);
    }

    public function test_constructor_correctly_assigns_all_properties(): void
    {
        $dto = new ThemeSettingsDTO(
            primaryColor: '#FF0000',
            primaryColorDark: '#FF5555',
            secondaryColor: '#00FF00',
            secondaryColorDark: '#55FF55',
            accentColor: '#0000FF',
            backgroundColor: '#FFFFFF',
            backgroundColorDark: '#000000',
            surfaceColor: '#F0F0F0',
            surfaceColorDark: '#101010',
            textColor: '#222222',
            textColorDark: '#DDDDDD',
            fontHeading: 'Arial',
            fontBody: 'Verdana',
            fontSizeBase: '14px',
            borderRadius: '1rem',
            shadowIntensity: 'strong',
            buttonStyle: 'outlined',
            darkModeDefault: true,
            darkModeToggleVisible: false,
        );

        $this->assertEquals('#FF0000', $dto->primaryColor);
        $this->assertEquals('#FF5555', $dto->primaryColorDark);
        $this->assertEquals('#00FF00', $dto->secondaryColor);
        $this->assertEquals('#55FF55', $dto->secondaryColorDark);
        $this->assertEquals('#0000FF', $dto->accentColor);
        $this->assertEquals('#FFFFFF', $dto->backgroundColor);
        $this->assertEquals('#000000', $dto->backgroundColorDark);
        $this->assertEquals('#F0F0F0', $dto->surfaceColor);
        $this->assertEquals('#101010', $dto->surfaceColorDark);
        $this->assertEquals('#222222', $dto->textColor);
        $this->assertEquals('#DDDDDD', $dto->textColorDark);
        $this->assertEquals('Arial', $dto->fontHeading);
        $this->assertEquals('Verdana', $dto->fontBody);
        $this->assertEquals('14px', $dto->fontSizeBase);
        $this->assertEquals('1rem', $dto->borderRadius);
        $this->assertEquals('strong', $dto->shadowIntensity);
        $this->assertEquals('outlined', $dto->buttonStyle);
        $this->assertTrue($dto->darkModeDefault);
        $this->assertFalse($dto->darkModeToggleVisible);
    }

    public function test_from_settings_handles_boolean_conversion(): void
    {
        $settingsService = $this->createMock(SettingsServiceInterface::class);
        $settingsService->method('get')->willReturnMap([
            // Color fields use '' as default
            ['theme_primary_color', '', '#D97706'],
            ['theme_primary_color_dark', '', '#F59E0B'],
            ['theme_secondary_color', '', '#57534E'],
            ['theme_secondary_color_dark', '', '#A8A29E'],
            ['theme_accent_color', '', '#D97706'],
            ['theme_background_color', '', '#FAFAF9'],
            ['theme_background_color_dark', '', '#1C1917'],
            ['theme_surface_color', '', '#FFFFFF'],
            ['theme_surface_color_dark', '', '#292524'],
            ['theme_text_color', '', '#1C1917'],
            ['theme_text_color_dark', '', '#F5F5F4'],
            ['theme_text_secondary_color', '', '#57534E'],
            ['theme_text_secondary_color_dark', '', '#D6D3D1'],
            ['theme_text_muted_color', '', '#A8A29E'],
            ['theme_text_muted_color_dark', '', '#A8A29E'],
            ['theme_border_color', '', '#E7E5E4'],
            ['theme_border_color_dark', '', '#44403C'],
            // String fields use '' as default
            ['theme_font_heading', '', 'Inter'],
            ['theme_font_body', '', 'Inter'],
            ['theme_button_style', '', 'solid'],
            // Mapped fields
            ['theme_font_size_base', 'normal', 'normal'],
            ['theme_border_radius', 'medium', 'medium'],
            ['theme_shadow_intensity', 'subtle', 'subtle'],
            // Boolean fields - testing string conversion
            ['theme_dark_mode_default', false, '1'],
            ['theme_dark_mode_toggle_visible', true, '0'],
        ]);

        $dto = ThemeSettingsDTO::fromSettings($settingsService);

        $this->assertTrue($dto->darkModeDefault);
        $this->assertFalse($dto->darkModeToggleVisible);
        $this->assertIsBool($dto->darkModeDefault);
        $this->assertIsBool($dto->darkModeToggleVisible);
    }

    public function test_dto_is_readonly(): void
    {
        $dto = ThemeSettingsDTO::defaults();

        $reflection = new \ReflectionClass($dto);
        $this->assertTrue($reflection->isReadOnly());
    }
}
