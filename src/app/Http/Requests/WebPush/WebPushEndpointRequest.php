<?php

declare(strict_types=1);

namespace App\Http\Requests\WebPush;

use App\Domain\WebPush\WebPushEndpointRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A browser named by its push endpoint: to refresh or forget its subscription (core#803).
 */
final class WebPushEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:'.WebPushEndpointRule::MAX_LENGTH],
        ];
    }
}
