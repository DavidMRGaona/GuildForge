<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Filament\Forms\RichEditor\EditorImages;
use Illuminate\Foundation\Http\FormRequest;

final class EditorImageUploadRequest extends FormRequest
{
    /** The controller decides who may upload */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // Real content type, not the name: no SVG (scripts), same types and size as the panel editor
            'image' => ['required', 'file', 'mimetypes:'.implode(',', EditorImages::ACCEPTED_TYPES), 'max:'.EditorImages::MAX_SIZE],
        ];
    }
}
