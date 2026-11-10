<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Services;

use App\Application\Content\Services\EditorImageUrlsInterface;
use App\Domain\Content\Enums\EditorImageOrigin;
use Illuminate\Support\Facades\Storage;

final readonly class EditorImageUrls implements EditorImageUrlsInterface
{
    public const string DISK = 'images';

    public function urlFor(string $path): ?string
    {
        if (! EditorImageOrigin::ofPath($path) instanceof EditorImageOrigin) {
            return null;
        }

        return Storage::disk(self::DISK)->url($path);
    }
}
