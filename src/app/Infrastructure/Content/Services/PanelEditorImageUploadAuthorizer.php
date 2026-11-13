<?php

declare(strict_types=1);

namespace App\Infrastructure\Content\Services;

use App\Application\Content\Services\EditorImageUploadAuthorizerInterface;
use App\Domain\Content\Enums\EditorImageOrigin;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Auth\Access\Gate;

/**
 * Default rule: whoever may create articles in the panel may upload their images; no other origin.
 */
final readonly class PanelEditorImageUploadAuthorizer implements EditorImageUploadAuthorizerInterface
{
    public function __construct(
        private Gate $gate,
    ) {}

    public function canUpload(UserModel $user, EditorImageOrigin $origin): bool
    {
        return match ($origin) {
            EditorImageOrigin::Articles => $this->gate->forUser($user)->allows('create', ArticleModel::class),
            default => false,
        };
    }
}
