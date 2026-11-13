<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Application\Media\Services\MediaFileIdResolverInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Domain\Media\Enums\MediaFileStatus;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Filament\Media\Picker\MediaPickerCriteria;
use App\Filament\Pages\MediaLibraryPage;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaFileModel;
use App\Infrastructure\Media\Services\MediaLibraryQueryInterface;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * «Elegir de la biblioteca» on an image upload field: the person picks a file already in the media library
 * instead of uploading it again, and the record points to the same file. Uploading keeps working.
 *
 * Only for fields whose files are released through the core (the trait DeletesCloudinaryImages or
 * MediaFileReleaserInterface): a chosen file is shared, and deleting it by hand would break another record.
 * The action shows only to people with media.view_any, and only when a source that releases through the core
 * claims the field's folder. Saving accepts a path that is not the record's own only if it is a reusable file
 * of the library this field would accept; Filament refuses any other (preventFilePathTampering).
 */
final class MediaLibraryPicker
{
    public const string ACTION = 'chooseFromLibrary';

    public const string PERMISSION = 'media.view_any';

    /** Request attribute holding the paths the picker's fields stored from uploads in this request (core#608). */
    private const string STORED_IN_THIS_REQUEST = 'media_picker.stored_in_this_request';

    /**
     * @param  (Closure(): list<string>)|null  $storedPaths  paths always accepted, for pages without a record (settings)
     * @param  (Closure(Builder<MediaFileModel>): Builder<MediaFileModel>)|null  $constrain  the field's own rule on top of the library's
     */
    public static function attachTo(FileUpload $field, ?Closure $storedPaths = null, ?Closure $constrain = null): FileUpload
    {
        return self::rememberWhatItStores($field)
            ->hintAction(self::action($constrain))
            ->preventFilePathTampering(
                true,
                allowFilePathUsing: static fn (string $file, FileUpload $component): bool => self::isStored($file, $component, $storedPaths)
                    || self::isPickable($file, $component, $constrain),
            )
            // A chosen file deleted from the library while the form was open says so, instead of «tampered».
            // The field's own messages are kept: Filament has no getter for them before it is mounted
            ->validationMessages([
                ...(fn (): array => $this->validationMessages)->call($field),
                'tampered' => static fn (FileUpload $component): ?string => self::holdsAFileGoneFromTheLibrary($component, $storedPaths)
                    ? (string) __('filament.media_picker.errors.missing')
                    : null,
            ]);
    }

    /**
     * core#608: a repeater saves its relationship by reading each item's state again, once the field has already
     * stored the upload under its final path. Filament's own way of storing it is kept; the path it returns is
     * remembered for this request only, so isStored() accepts it. Nothing else can reach that list: it only holds
     * paths this field wrote from a file uploaded in this same request.
     */
    private static function rememberWhatItStores(FileUpload $field): FileUpload
    {
        $store = (fn (): ?Closure => $this->saveUploadedFileUsing)->call($field);

        if (! $store instanceof Closure) {
            return $field;
        }

        return $field->saveUploadedFileUsing(static function (FileUpload $component, TemporaryUploadedFile $file) use ($store): mixed {
            $stored = $component->evaluate($store, ['file' => $file]);

            if (is_string($stored) && $stored !== '') {
                $request = request();
                $paths = (array) $request->attributes->get(self::STORED_IN_THIS_REQUEST, []);
                $paths[] = $stored;
                $request->attributes->set(self::STORED_IN_THIS_REQUEST, $paths);
            }

            return $stored;
        });
    }

    /**
     * The path the field stores for a file of the library, as an upload would have stored it.
     */
    public static function storedPathOf(MediaFileModel $file): string
    {
        return $file->file_id.'.'.$file->format;
    }

    /**
     * @param  (Closure(Builder<MediaFileModel>): Builder<MediaFileModel>)|null  $constrain
     */
    private static function action(?Closure $constrain): Action
    {
        return Action::make(self::ACTION)
            ->label(__('filament.media_picker.action'))
            ->icon(Heroicon::OutlinedPhoto)
            ->visible(static fn (FileUpload $component): bool => self::canPick($component))
            ->modalHeading(__('filament.media_picker.heading'))
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel(__('filament.media_picker.submit'))
            ->schema(static fn (FileUpload $component): array => [
                Grid::make(['default' => 1, 'sm' => 3])->schema([
                    TextInput::make('search')
                        ->label(__('filament.media_picker.search'))
                        ->placeholder(__('filament.media_picker.search_placeholder'))
                        ->live(debounce: 400)
                        ->afterStateUpdated(static fn (Set $set): mixed => $set('page', 1)),
                    Select::make('origin')
                        ->label(__('filament.media_library.filters.origin'))
                        ->options(self::originOptions())
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(static fn (Set $set): mixed => $set('page', 1)),
                    Select::make('status')
                        ->label(__('filament.media_library.filters.status'))
                        ->options(self::statusOptions())
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(static fn (Set $set): mixed => $set('page', 1)),
                ]),
                Hidden::make('page')->default(1),
                MediaLibraryBrowser::make('file')
                    ->hiddenLabel()
                    ->criteria(MediaPickerCriteria::fromField($component, $constrain))
                    ->required()
                    ->validationMessages(['required' => __('filament.media_picker.errors.required')]),
            ])
            ->action(static function (array $data, FileUpload $component) use ($constrain): void {
                $fileId = is_string($data['file'] ?? null) ? $data['file'] : '';
                $file = self::canPick($component)
                    ? MediaPickerCriteria::fromField($component, $constrain)
                        ->apply(resolve(MediaLibraryQueryInterface::class)->query())
                        ->where('media_files.file_id', $fileId)
                        ->first()
                    : null;

                if (! $file instanceof MediaFileModel) {
                    Notification::make()->danger()->title(__('filament.media_picker.errors.unavailable'))->send();

                    return;
                }

                $component->state([(string) Str::uuid() => self::storedPathOf($file)]);
                $component->callAfterStateUpdated();
            });
    }

    private static function canPick(FileUpload $component): bool
    {
        try {
            $user = auth()->user();

            return $user !== null
                && ! $component->isDisabled()
                && resolve(AuthorizationServiceInterface::class)->can($user, self::PERMISSION)
                && resolve(MediaLibraryQueryInterface::class)->isReusableFolder($component->getDirectory() ?? '');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  (Closure(): list<string>)|null  $storedPaths
     */
    private static function isStored(string $file, FileUpload $component, ?Closure $storedPaths): bool
    {
        if (in_array($file, $component->getOriginalFilePaths(), true)
            || in_array($file, (array) request()->attributes->get(self::STORED_IN_THIS_REQUEST, []), true)) {
            return true;
        }

        if (! $storedPaths instanceof Closure) {
            return false;
        }

        return in_array($file, (array) $component->evaluate($storedPaths), true);
    }

    /**
     * @param  (Closure(Builder<MediaFileModel>): Builder<MediaFileModel>)|null  $constrain
     */
    private static function isPickable(string $file, FileUpload $component, ?Closure $constrain): bool
    {
        $id = self::idOf($file);

        if (! $id instanceof MediaFileId || ! self::canPick($component)) {
            return false;
        }

        $chosen = MediaPickerCriteria::fromField($component, $constrain)
            ->apply(resolve(MediaLibraryQueryInterface::class)->query())
            ->where('media_files.file_id', $id->value)
            ->first();

        // Only the path an upload would have stored: another spelling of the same file (other extension,
        // a URL, a leading slash) would break its resource type or its delivery URL
        return $chosen instanceof MediaFileModel && $file === self::storedPathOf($chosen);
    }

    /**
     * @param  (Closure(): list<string>)|null  $storedPaths
     */
    private static function holdsAFileGoneFromTheLibrary(FileUpload $component, ?Closure $storedPaths): bool
    {
        foreach ((array) $component->getRawState() as $file) {
            if (! is_string($file) || self::isStored($file, $component, $storedPaths)) {
                continue;
            }

            $id = self::idOf($file);

            if ($id instanceof MediaFileId && ! MediaFileModel::query()->where('file_id', $id->value)->exists()) {
                return true;
            }
        }

        return false;
    }

    private static function idOf(string $file): ?MediaFileId
    {
        try {
            return $file !== '' ? resolve(MediaFileIdResolverInterface::class)->fromStoredPath($file) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Sources whose files may be chosen, by label.
     *
     * @return array<string, string>
     */
    private static function originOptions(): array
    {
        $options = [];

        foreach (resolve(MediaSourceRegistryInterface::class)->sources() as $source) {
            try {
                if ($source instanceof ReleasesMediaThroughCoreInterface && ! $source->hidden()) {
                    $options[$source->type()] = $source->label();
                }
            } catch (Throwable) {
                // A source that cannot describe itself is left out of the filter
            }
        }

        return $options;
    }

    /**
     * Files of unknown origin are never offered, so that status is not a filter here.
     *
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $options = [];

        foreach ([MediaFileStatus::InUse, MediaFileStatus::Unused, MediaFileStatus::Recent] as $status) {
            $options[$status->value] = MediaLibraryPage::statusLabel($status);
        }

        return $options;
    }
}
