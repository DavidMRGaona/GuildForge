<?php

declare(strict_types=1);

namespace App\Infrastructure\Proposals\Services;

use App\Application\Content\Services\EditorImageUploadAuthorizerInterface;
use App\Application\Proposals\Services\ProposalEligibilityServiceInterface;
use App\Domain\Content\Enums\EditorImageOrigin;
use App\Domain\Proposals\Enums\ProposalType;
use App\Infrastructure\Content\Services\PanelEditorImageUploadAuthorizer;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

/**
 * Whoever may create articles in the panel (core#573) and whoever may propose articles from the
 * web (core#579) may upload the images of the web rich editor. Nobody loses what they had.
 */
final readonly class ProposalEditorImageUploadAuthorizer implements EditorImageUploadAuthorizerInterface
{
    public function __construct(
        private PanelEditorImageUploadAuthorizer $panel,
        private ProposalEligibilityServiceInterface $eligibility,
    ) {}

    public function canUpload(UserModel $user, EditorImageOrigin $origin): bool
    {
        if ($this->panel->canUpload($user, $origin)) {
            return true;
        }

        return $origin === EditorImageOrigin::Articles
            && $this->eligibility->check(ProposalType::Article, (string) $user->getKey())->allowed;
    }
}
