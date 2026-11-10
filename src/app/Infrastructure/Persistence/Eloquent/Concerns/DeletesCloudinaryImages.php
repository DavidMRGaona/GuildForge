<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Concerns;

use App\Application\Media\Services\MediaFileReleaserInterface;
use Illuminate\Support\Facades\Storage;

/**
 * Lets go of the Cloudinary images of a model when it is deleted or an image is replaced or cleared.
 *
 * A file can be chosen from the media library, so it may be shared with other records: once the save
 * commits, it is released through MediaFileReleaserInterface, which deletes it only if nobody uses it.
 * Models whose files are never shared (avatars, which must go at once when an account is anonymized)
 * override sharesCloudinaryImages() to delete them right away, as before.
 *
 * Models using this trait must define the `$cloudinaryImageFields` property:
 * protected array $cloudinaryImageFields = ['image_public_id'];
 */
trait DeletesCloudinaryImages
{
    protected static function bootDeletesCloudinaryImages(): void
    {
        static::updating(static function (self $model): void {
            if (! $model->sharesCloudinaryImages()) {
                $model->deleteOldCloudinaryImages();
            }
        });

        static::deleting(static function (self $model): void {
            if (! $model->sharesCloudinaryImages()) {
                $model->deleteCloudinaryImages();
            }
        });

        // After the write, so the sources read the new values; the release itself waits for the commit
        static::updated(static function (self $model): void {
            if ($model->sharesCloudinaryImages()) {
                $model->releaseReplacedCloudinaryImages();
            }
        });

        static::deleted(static function (self $model): void {
            if ($model->sharesCloudinaryImages()) {
                $model->releaseCloudinaryImages();
            }
        });
    }

    /**
     * Whether the images of this model may be shared with other records (chosen from the media library).
     */
    protected function sharesCloudinaryImages(): bool
    {
        return true;
    }

    protected function releaseCloudinaryImages(): void
    {
        foreach ($this->getCloudinaryImageFields() as $field) {
            $path = $this->getAttribute($field);

            if (is_string($path) && $path !== '') {
                resolve(MediaFileReleaserInterface::class)->release($path);
            }
        }
    }

    protected function releaseReplacedCloudinaryImages(): void
    {
        foreach ($this->getCloudinaryImageFields() as $field) {
            $old = $this->getOriginal($field);

            if ($this->wasChanged($field) && is_string($old) && $old !== '') {
                resolve(MediaFileReleaserInterface::class)->release($old);
            }
        }
    }

    /**
     * Delete all Cloudinary images associated with this model (models that never share them).
     */
    protected function deleteCloudinaryImages(): void
    {
        foreach ($this->getCloudinaryImageFields() as $field) {
            $publicId = $this->getAttribute($field);
            if ($publicId !== null) {
                $this->deleteFromCloudinary($publicId);
            }
        }
    }

    /**
     * Delete old Cloudinary images when the field is being updated (models that never share them).
     */
    protected function deleteOldCloudinaryImages(): void
    {
        foreach ($this->getCloudinaryImageFields() as $field) {
            if ($this->isDirty($field)) {
                $oldPublicId = $this->getOriginal($field);
                if ($oldPublicId !== null) {
                    $this->deleteFromCloudinary($oldPublicId);
                }
            }
        }
    }

    /**
     * Delete a file from Cloudinary.
     */
    protected function deleteFromCloudinary(string $publicId): void
    {
        Storage::disk('images')->delete($publicId);
    }

    /**
     * Get the Cloudinary image fields for this model.
     *
     * @return array<string>
     */
    protected function getCloudinaryImageFields(): array
    {
        return $this->cloudinaryImageFields;
    }
}
