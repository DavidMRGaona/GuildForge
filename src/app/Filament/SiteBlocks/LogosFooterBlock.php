<?php

declare(strict_types=1);

namespace App\Filament\SiteBlocks;

use App\Application\Footer\Services\FooterLayoutServiceInterface;
use App\Domain\Footer\FooterLogos;
use App\Domain\SiteBlocks\LinkTargetRule;
use App\Filament\Forms\Components\MediaLibraryPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * A row of logos (sponsors, federations, partners) (core#698): images uploaded to `footer/logos/` or
 * chosen from the media library, each with an optional link and an alternative text that is required.
 * A logo without text, or without a usable image, is not drawn: a footer image with no name would be
 * silent for screen readers.
 */
final class LogosFooterBlock extends CoreFooterBlock
{
    public function key(): string
    {
        return FooterLogos::BLOCK_KEY;
    }

    public function label(): string
    {
        return __('filament.footer.blocks.logos.label');
    }

    public function settingsSchema(): array
    {
        return [
            Repeater::make(FooterLogos::FIELD)
                ->label(__('filament.footer.blocks.logos.logos'))
                ->schema([
                    MediaLibraryPicker::attachTo(
                        FileUpload::make('image')
                            ->label(__('filament.footer.blocks.logos.image'))
                            ->image()
                            ->disk('images')
                            ->directory(FooterLogos::DIRECTORY)
                            ->getUploadedFileNameForStorageUsing(
                                static fn (TemporaryUploadedFile $file): string => Str::uuid()->toString().'.'.$file->getClientOriginalExtension()
                            )
                            ->maxSize(2048)
                            ->required(),
                        storedPaths: static fn (): array => self::storedPaths(),
                    ),
                    TextInput::make('alt')
                        ->label(__('filament.footer.blocks.logos.alt'))
                        ->helperText(__('filament.footer.blocks.logos.alt_help'))
                        ->required()
                        ->maxLength(FooterLogos::MAX_ALT_LENGTH),
                    self::linkField('url', __('filament.footer.blocks.logos.url'), required: false),
                ])
                ->itemLabel(static fn (array $state): ?string => is_string($state['alt'] ?? null) && $state['alt'] !== '' ? $state['alt'] : null)
                ->addActionLabel(__('filament.footer.blocks.logos.add'))
                ->maxItems(FooterLogos::MAX_LOGOS)
                ->defaultItems(0)
                ->collapsible()
                ->columnSpanFull(),
        ];
    }

    public function component(): string
    {
        return 'components/siteBlocks/LogosBlock.vue';
    }

    public function props(array $settings, ?Authenticatable $user): array
    {
        $disk = Storage::disk('images');
        $logos = [];

        foreach (FooterLogos::itemsIn($settings) as $item) {
            if ($item['alt'] === '') {
                continue;
            }

            $logos[] = [
                'src' => $disk->url($item['image']),
                'alt' => mb_substr($item['alt'], 0, FooterLogos::MAX_ALT_LENGTH),
                'url' => self::link($item, 'url'),
                'external' => ($url = self::link($item, 'url')) !== null && LinkTargetRule::isExternal($url),
            ];
        }

        return ['logos' => $logos];
    }

    /**
     * Every image any logo block of the stored footer already holds: always accepted by the upload
     * fields, as the settings page has no record to compare with.
     *
     * @return list<string>
     */
    private static function storedPaths(): array
    {
        $paths = [];

        foreach (resolve(FooterLayoutServiceInterface::class)->layout()->columns as $column) {
            foreach ($column->blocks as $block) {
                if ($block->key === FooterLogos::BLOCK_KEY) {
                    array_push($paths, ...FooterLogos::pathsIn($block->settings));
                }
            }
        }

        return $paths;
    }
}
