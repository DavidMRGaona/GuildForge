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

        $this->assertEquals('Inter', $dto->fontHeading);
        $this->assertEquals('Inter', $dto->fontBody);
        $this->assertEquals('16px', $dto->fontSizeBase);
        $this->assertEquals('0.5rem', $dto->borderRadius);
        $this->assertEquals('1', $dto->shadowIntensity);
        $this->assertEquals('solid', $dto->buttonStyle);
        $this->assertFalse($dto->darkModeDefault);
        $this->assertTrue($dto->darkModeToggleVisible);
    }

    public function test_from_settings_returns_dto_with_values_from_settings_service(): void
    {
        $settingsService = $this->createStub(SettingsServiceInterface::class);
        $settingsService->method('get')->willReturnMap([
            // String fields use '' as default in getStringOrDefault
            ['theme_font_heading', '', 'Roboto'],
            ['theme_font_body', '', 'Open Sans'],
            ['theme_button_style', '', 'outline'],
            // Mapped fields use descriptors as defaults
            ['theme_font_size_base', 'normal', 'large'],
            ['theme_border_radius', 'medium', 'large'],
            ['theme_shadow_intensity', 'medium', 'pronounced'],
            // Boolean fields
            ['theme_dark_mode_default', false, true],
            ['theme_dark_mode_toggle_visible', true, false],
        ]);

        $dto = ThemeSettingsDTO::fromSettings($settingsService);

        $this->assertEquals('Roboto', $dto->fontHeading);
        $this->assertEquals('Open Sans', $dto->fontBody);
        $this->assertEquals('18px', $dto->fontSizeBase);
        $this->assertEquals('0.75rem', $dto->borderRadius);
        $this->assertEquals('1.5', $dto->shadowIntensity);
        $this->assertEquals('outline', $dto->buttonStyle);
        $this->assertTrue($dto->darkModeDefault);
        $this->assertFalse($dto->darkModeToggleVisible);
    }

    public function test_from_settings_uses_defaults_when_settings_are_empty(): void
    {
        $settingsService = $this->createStub(SettingsServiceInterface::class);
        $settingsService->method('get')->willReturnCallback(
            fn (string $key, mixed $default = null): mixed => $default
        );

        $dto = ThemeSettingsDTO::fromSettings($settingsService);

        $this->assertEquals('Inter', $dto->fontHeading);
        $this->assertEquals('Inter', $dto->fontBody);
        $this->assertEquals('16px', $dto->fontSizeBase);
        $this->assertEquals('0.5rem', $dto->borderRadius);
        $this->assertEquals('1', $dto->shadowIntensity);
        $this->assertEquals('solid', $dto->buttonStyle);
        $this->assertFalse($dto->darkModeDefault);
        $this->assertTrue($dto->darkModeToggleVisible);
    }

    public function test_constructor_correctly_assigns_all_properties(): void
    {
        $dto = new ThemeSettingsDTO(
            fontHeading: 'Arial',
            fontBody: 'Verdana',
            fontSizeBase: '14px',
            borderRadius: '1rem',
            shadowIntensity: 'strong',
            buttonStyle: 'outlined',
            darkModeDefault: true,
            darkModeToggleVisible: false,
        );

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
        $settingsService = $this->createStub(SettingsServiceInterface::class);
        $settingsService->method('get')->willReturnMap([
            // String fields use '' as default
            ['theme_font_heading', '', 'Inter'],
            ['theme_font_body', '', 'Inter'],
            ['theme_button_style', '', 'solid'],
            // Mapped fields
            ['theme_font_size_base', 'normal', 'normal'],
            ['theme_border_radius', 'medium', 'medium'],
            ['theme_shadow_intensity', 'medium', 'subtle'],
            // Boolean fields - testing string conversion
            ['theme_dark_mode_default', false, '1'],
            ['theme_dark_mode_toggle_visible', true, '0'],
        ]);

        $dto = ThemeSettingsDTO::fromSettings($settingsService);

        $this->assertTrue($dto->darkModeDefault);
        $this->assertFalse($dto->darkModeToggleVisible);
    }

    public function test_from_settings_accepts_playfair_display_for_headings_only(): void
    {
        $dto = ThemeSettingsDTO::fromSettings($this->settings([
            'theme_font_heading' => 'Playfair Display',
            'theme_font_body' => 'Playfair Display',
        ]));

        $this->assertSame('Playfair Display', $dto->fontHeading);
        $this->assertSame('Inter', $dto->fontBody);
    }

    public function test_from_settings_falls_back_to_inter_for_fonts_outside_the_list(): void
    {
        $dto = ThemeSettingsDTO::fromSettings($this->settings([
            'theme_font_heading' => "Comic Sans'; } body { display: none",
            'theme_font_body' => 'Arial',
        ]));

        $this->assertSame('Inter', $dto->fontHeading);
        $this->assertSame('Inter', $dto->fontBody);
    }

    public function test_from_settings_falls_back_to_solid_for_unknown_button_styles(): void
    {
        $dto = ThemeSettingsDTO::fromSettings($this->settings(['theme_button_style' => 'outlined']));

        $this->assertSame('solid', $dto->buttonStyle);
    }

    public function test_every_listed_option_is_accepted(): void
    {
        foreach (ThemeSettingsDTO::HEADING_FONTS as $font) {
            $this->assertSame($font, ThemeSettingsDTO::fromSettings($this->settings(['theme_font_heading' => $font]))->fontHeading);
        }
        foreach (ThemeSettingsDTO::BODY_FONTS as $font) {
            $this->assertSame($font, ThemeSettingsDTO::fromSettings($this->settings(['theme_font_body' => $font]))->fontBody);
        }
        foreach (ThemeSettingsDTO::BUTTON_STYLES as $style) {
            $this->assertSame($style, ThemeSettingsDTO::fromSettings($this->settings(['theme_button_style' => $style]))->buttonStyle);
        }
    }

    /**
     * @param  array<string, string>  $values
     */
    private function settings(array $values): SettingsServiceInterface
    {
        $settingsService = $this->createStub(SettingsServiceInterface::class);
        $settingsService->method('get')->willReturnCallback(
            fn (string $key, mixed $default = null): mixed => $values[$key] ?? $default
        );

        return $settingsService;
    }

    public function test_dto_is_readonly(): void
    {
        $dto = ThemeSettingsDTO::defaults();

        $reflection = new \ReflectionClass($dto);
        $this->assertTrue($reflection->isReadOnly());
    }
}
