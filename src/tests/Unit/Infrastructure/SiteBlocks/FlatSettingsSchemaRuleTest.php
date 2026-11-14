<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\SiteBlocks;

use App\Infrastructure\SiteBlocks\FlatSettingsSchemaRule;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class FlatSettingsSchemaRuleTest extends TestCase
{
    public function test_an_empty_schema_is_flat(): void
    {
        $this->assertTrue((new FlatSettingsSchemaRule)->allows([]));
    }

    public function test_short_text_numbers_links_choices_and_switches_are_flat(): void
    {
        $this->assertTrue((new FlatSettingsSchemaRule)->allows([
            TextInput::make('heading'),
            TextInput::make('limit')->numeric()->minValue(1)->maxValue(5),
            TextInput::make('link')->url(),
            Select::make('style')->options(['primary' => 'Principal', 'secondary' => 'Secundario']),
            Radio::make('size')->options(['sm' => 'Pequeño', 'md' => 'Mediano']),
            ToggleButtons::make('align')->options(['start' => 'Inicio', 'center' => 'Centro']),
            Toggle::make('show_name'),
            Checkbox::make('compact'),
        ]));
    }

    /**
     * @return array<string, array{Closure(): object}>
     */
    public static function nonFlatFields(): array
    {
        return [
            'upload' => [static fn (): object => FileUpload::make('image')],
            'rich editor' => [static fn (): object => RichEditor::make('body')],
            'long text' => [static fn (): object => Textarea::make('intro')],
            'hidden' => [static fn (): object => Hidden::make('secret')],
            'repeater' => [static fn (): object => Repeater::make('items')->schema([TextInput::make('label')])],
            'layout with fields inside' => [static fn (): object => Section::make()->schema([TextInput::make('heading')])],
            'multiple choice' => [static fn (): object => Select::make('tags')->multiple()->options(['a' => 'A'])],
            'multiple toggle buttons' => [static fn (): object => ToggleButtons::make('days')->multiple()->options(['mon' => 'Lunes'])],
            'nested state path' => [static fn (): object => TextInput::make('button.label')],
            'not a component' => [static fn (): object => new \stdClass],
        ];
    }

    /**
     * @param  Closure(): object  $field
     */
    #[DataProvider('nonFlatFields')]
    public function test_anything_else_is_not_flat(Closure $field): void
    {
        $this->assertFalse((new FlatSettingsSchemaRule)->allows([TextInput::make('heading'), $field()]));
    }

    public function test_a_field_whose_settings_cannot_be_read_is_not_flat(): void
    {
        $field = Select::make('style')->multiple(static fn (): bool => throw new \RuntimeException('Not now'));

        $this->assertFalse((new FlatSettingsSchemaRule)->allows([$field]));
    }
}
