<?php

declare(strict_types=1);

namespace App\Http\Requests\WebPush;

use App\Domain\WebPush\WebPushEndpointRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * This browser stops receiving push messages: its endpoint names it (core#803).
 */
final class DestroyWebPushSubscriptionRequest extends FormRequest
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
