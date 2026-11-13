<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Content\Services\EditorImageUploadAuthorizerInterface;
use App\Domain\Content\Enums\EditorImageOrigin;
use App\Filament\Forms\RichEditor\EditorImages;
use App\Http\Requests\EditorImageUploadRequest;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores an image inserted in the web rich editor in the articles' editor image folder
 * ("articles/content/<Y>/<m>/"), the one the editor text and media sources claim (core#573).
 * Answers the stored path (the editor's "data-id") and its delivery URL.
 */
final class EditorImageUploadController extends Controller
{
    public function __invoke(EditorImageUploadRequest $request, EditorImageUploadAuthorizerInterface $authorizer): JsonResponse
    {
        $user = $request->user();
        $origin = EditorImageOrigin::Articles;

        abort_unless($user instanceof UserModel && $authorizer->canUpload($user, $origin), 403);

        $file = $request->file('image');
        abort_if($file === null || is_array($file), 422);

        $extension = $file->guessExtension() ?? 'jpg';
        $path = Storage::disk(EditorImages::DISK)->putFileAs(
            $origin->directory(now()),
            $file,
            Str::uuid()->toString().'.'.$extension,
            ['visibility' => 'public', 'mimetype' => $file->getMimeType()],
        );

        abort_if(! is_string($path), 500);

        return response()->json([
            'id' => $path,
            'url' => Storage::disk(EditorImages::DISK)->url($path),
        ], 201);
    }
}
