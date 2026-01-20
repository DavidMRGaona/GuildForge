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
        $this->assertEquals('subtle', $dto->shadowIntensity);
        $this->assertEquals('solid', $dto->buttonStyle);
        $this->assertFalse($dto->darkModeDefault);
        $this->assertTrue($dto->darkModeToggleVisible);
    }

    public function test_from_settings_returns_dto_with_values_from_settings_service(): void
    {
        $settingsService = $this->createMock(SettingsServiceInterface::class);
        $settingsService->method('get')->willReturnMap([
            ['theme_primary_color', '#D97706', '#3B82F6'],
            ['theme_primary_color_dark', '#F59E0B', '#60A5FA'],
            ['theme_secondary_color', '#57534E', '#64748B'],
            ['theme_secondary_color_dark', '#A8A29E', '#94A3B8'],
            ['theme_accent_color', '#D97706', '#EF4444'],
            ['theme_background_color', '#FAFAF9', '#FAFAFA'],
            ['theme_background_color_dark', '#1C1917', '#0F172A'],
            ['theme_surface_color', '#FFFFFF', '#F3F4F6'],
            ['theme_surface_color_dark', '#292524', '#1E293B'],
            ['theme_text_color', '#1C1917', '#1F2937'],
            ['theme_text_color_dark', '#F5F5F4', '#FFFFFF'],
            ['theme_font_heading', 'Inter', 'Roboto'],
            ['theme_font_body', 'Inter', 'Open Sans'],
            ['theme_font_size_base', '16px', '18px'],
            ['theme_border_radius', '0.5rem', '0.75rem'],
            ['theme_shadow_intensity', 'subtle', 'medium'],
            ['theme_button_style', 'solid', 'outlined'],
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
        $this->assertEquals('medium', $dto->shadowIntensity);
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
        $this->assertEquals('subtle', $dto->shadowIntensity);
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
            ['theme_primary_color', '#D97706', '#D97706'],
            ['theme_primary_color_dark', '#F59E0B', '#F59E0B'],
            ['theme_secondary_color', '#57534E', '#57534E'],
            ['theme_secondary_color_dark', '#A8A29E', '#A8A29E'],
            ['theme_accent_color', '#D97706', '#D97706'],
            ['theme_background_color', '#FAFAF9', '#FAFAF9'],
            ['theme_background_color_dark', '#1C1917', '#1C1917'],
            ['theme_surface_color', '#FFFFFF', '#FFFFFF'],
            ['theme_surface_color_dark', '#292524', '#292524'],
            ['theme_text_color', '#1C1917', '#1C1917'],
            ['theme_text_color_dark', '#F5F5F4', '#F5F5F4'],
            ['theme_font_heading', 'Inter', 'Inter'],
            ['theme_font_body', 'Inter', 'Inter'],
            ['theme_font_size_base', '16px', '16px'],
            ['theme_border_radius', '0.5rem', '0.5rem'],
            ['theme_shadow_intensity', 'subtle', 'subtle'],
            ['theme_button_style', 'solid', 'solid'],
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
