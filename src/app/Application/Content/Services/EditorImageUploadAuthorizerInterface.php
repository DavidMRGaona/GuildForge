<?php

declare(strict_types=1);

namespace App\Application\Content\Services;

use App\Domain\Content\Enums\EditorImageOrigin;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

/**
 * Who may upload images from the web rich editor (core#573). The core refuses whoever cannot
 * create the thing in the panel; the feature that opens the editor to more people (article
 * proposals) binds its own rule here. It fails closed: anything but `true` refuses.
 */
interface EditorImageUploadAuthorizerInterface
{
    public function canUpload(UserModel $user, EditorImageOrigin $origin): bool;
}
