<?php

declare(strict_types=1);

namespace App\Http\Requests\Profiles;

use App\Application\Profiles\Services\ProfileEditorInterface;
use LogicException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The requests of the owner's editor (core#743): only the owner of the profile at `{handle}` passes,
 * before anything is validated, and anybody else gets the 404 of an unknown address, so nobody learns
 * whether it exists. ProfileEditorInterface::ownerId() decides, failing closed.
 */
trait OwnsProfile
{
    private ?string $ownerId = null;

    public function authorize(ProfileEditorInterface $editor): bool
    {
        $handle = $this->route('handle');
        $this->ownerId = is_string($handle) ? $editor->ownerId($handle, $this->user()) : null;

        return $this->ownerId !== null;
    }

    /**
     * The id of the owner, once authorize() let them through.
     */
    public function ownerId(): string
    {
        return $this->ownerId ?? throw new LogicException('The owner is only known once the request is authorized.');
    }

    protected function failedAuthorization(): never
    {
        throw new NotFoundHttpException;
    }
}
