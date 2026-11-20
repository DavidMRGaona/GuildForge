<?php

declare(strict_types=1);

namespace App\Http\Requests\WebPush;

use App\Application\WebPush\DTOs\WebPushSubscriptionInputDTO;
use App\Application\WebPush\Services\WebPushConfigurationInterface;
use App\Domain\WebPush\WebPushEndpointRule;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A browser's push subscription (PushSubscription.toJSON()): an endpoint of a push service the
 * site accepts (never another host, core#803) and the browser's P-256 key and auth secret.
 */
final class StoreWebPushSubscriptionRequest extends FormRequest
{
    /** Uncompressed P-256 point (65 bytes) and auth secret (16 bytes), base64url without padding. */
    private const string P256DH_PATTERN = '/^[A-Za-z0-9_-]{87}\z/';

    private const string AUTH_PATTERN = '/^[A-Za-z0-9_-]{22}\z/';

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
            'endpoint' => ['required', 'string', 'max:'.WebPushEndpointRule::MAX_LENGTH, $this->pushService(...)],
            'keys' => ['required', 'array:p256dh,auth'],
            'keys.p256dh' => ['required', 'string', 'regex:'.self::P256DH_PATTERN],
            'keys.auth' => ['required', 'string', 'regex:'.self::AUTH_PATTERN],
        ];
    }

    public function toDto(): WebPushSubscriptionInputDTO
    {
        return new WebPushSubscriptionInputDTO(
            endpoint: $this->string('endpoint')->toString(),
            p256dh: $this->string('keys.p256dh')->toString(),
            auth: $this->string('keys.auth')->toString(),
            userAgent: mb_substr((string) $this->userAgent(), 0, 512),
        );
    }

    /**
     * @param  Closure(string): void  $fail
     */
    private function pushService(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! app(WebPushConfigurationInterface::class)->endpointRule()->allows($value)) {
            $fail(__('web_push.validation.endpoint'));
        }
    }
}
