<?php

declare(strict_types=1);

namespace App\Http\Requests\Profiles;

use App\Application\Profiles\DTOs\ProfileEditorDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

/**
 * `POST /miembros/{handle}/banner` (core#743): a JPEG, PNG or WebP image of 5 MB at most, checked by
 * its content, not only by its name.
 */
final class UploadProfileBannerRequest extends FormRequest
{
    use OwnsProfile;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'banner' => [
                'required',
                'file',
                'image',
                'mimes:'.implode(',', ProfileEditorDTO::BANNER_TYPES),
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.ProfileEditorDTO::BANNER_MAX_KILOBYTES,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'banner.image' => __('profiles.editor.errors.banner_type'),
            'banner.mimes' => __('profiles.editor.errors.banner_type'),
            'banner.mimetypes' => __('profiles.editor.errors.banner_type'),
            'banner.max' => __('profiles.editor.errors.banner_size'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['banner' => __('profiles.editor.attributes.banner')];
    }

    public function banner(): UploadedFile
    {
        $file = $this->file('banner');

        return $file instanceof UploadedFile ? $file : throw new LogicException('The banner is only read once it was validated.');
    }
}
