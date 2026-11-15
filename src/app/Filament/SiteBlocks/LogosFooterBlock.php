<?php

declare(strict_types=1);

namespace App\Filament\SiteBlocks;

use App\Application\Footer\Services\FooterLayoutServiceInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\SiteBlocks\Exceptions\NothingToDrawException;
use App\Domain\Footer\FooterLogos;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Filament\Forms\Components\MediaLibraryPicker;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Seo\CloudinaryImageUrlBuilder;
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
    /** Height, in CSS pixels, the logos are drawn at at most (`h-10`) */
    public const int DRAWN_HEIGHT = 40;

    public function __construct(
        private readonly MediaFileIdResolverInterface $resolver,
        private readonly CloudinaryImageUrlBuilder $urls,
    ) {}

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
        $items = array_values(array_filter(FooterLogos::itemsIn($settings), static fn (array $item): bool => $item['alt'] !== ''));

        // Nothing usable: only the block's title would be left
        if ($items === []) {
            throw NothingToDrawException::make();
        }

        $known = $this->knownFiles($items);
        $logos = [];

        foreach ($items as $item) {
            $url = self::link($item, 'url');
            $logos[] = [...$this->image($item['image'], $known), 'alt' => mb_substr($item['alt'], 0, FooterLogos::MAX_ALT_LENGTH), 'url' => $url];
        }

        return ['logos' => $logos];
    }

    /**
     * The image to draw: a Cloudinary file of the library comes scaled to twice the drawn height (sharp on
     * dense screens) in the best format and quality, with the size it is drawn at so the page does not
     * jump while it loads; anything else is the file as stored, without a size.
     *
     * @param  array<string, MediaFileModel>  $known
     * @return array{src: string, width: int|null, height: int|null}
     */
    private function image(string $path, array $known): array
    {
        $id = $this->resolver->fromStoredPath($path);
        $file = $id instanceof MediaFileId ? ($known[$id->value] ?? null) : null;
        $scaled = $file instanceof MediaFileModel
            ? $this->urls->mediaHeightLimited($file->file_id, self::DRAWN_HEIGHT * 2, $file->format)
            : null;

        if (! $file instanceof MediaFileModel || $scaled === null) {
            return ['src' => Storage::disk('images')->url($path), 'width' => null, 'height' => null];
        }

        $width = $file->width;
        $height = $file->height;
        $drawn = $height !== null && $height > 0 && $width !== null && $width > 0
            ? min(self::DRAWN_HEIGHT, $height)
            : null;

        return [
            'src' => $scaled,
            'width' => $drawn === null ? null : (int) round($width * $drawn / $height),
            'height' => $drawn,
        ];
    }

    /**
     * The library's record of each logo, in one query.
     *
     * @param  list<array{image: string, alt: string, url: string}>  $items
     * @return array<string, MediaFileModel>
     */
    private function knownFiles(array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            $id = $this->resolver->fromStoredPath($item['image']);

            if ($id instanceof MediaFileId) {
                $ids[] = $id->value;
            }
        }

        return $ids === [] ? [] : MediaFileModel::query()->whereIn('file_id', array_unique($ids))->get()->keyBy('file_id')->all();
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
