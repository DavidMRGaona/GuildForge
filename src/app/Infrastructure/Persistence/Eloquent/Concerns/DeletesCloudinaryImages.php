<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * Trait to handle automatic deletion of Cloudinary images when the model is deleted or the image is updated.
 *
 * Models using this trait must define the `$cloudinaryImageFields` property:
 * protected array $cloudinaryImageFields = ['image_public_id'];
 */
trait DeletesCloudinaryImages
{
    protected static function bootDeletesCloudinaryImages(): void
    {
        // Delete images when the model is deleted
        static::deleting(static function (self $model): void {
            $model->deleteCloudinaryImages();
        });

        // Delete an old image when updating to a new one
        static::updating(static function (self $model): void {
            $model->deleteOldCloudinaryImages();
        });
    }

    /**
     * Delete all Cloudinary images associated with this model.
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
     * Delete old Cloudinary images when the field is being updated.
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
        return $this->cloudinaryImageFields ?? [];
    }
}
