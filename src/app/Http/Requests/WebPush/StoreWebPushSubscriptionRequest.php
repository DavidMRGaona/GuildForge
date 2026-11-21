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
    private const string BASE64URL = '/^[A-Za-z0-9_-]+=*\z/';

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
            // An uncompressed P-256 point (65 bytes starting with 0x04) and a 16-byte secret
            'keys.p256dh' => ['required', 'string', 'max:100', $this->p256dh(...)],
            'keys.auth' => ['required', 'string', 'max:40', $this->authSecret(...)],
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
    private function p256dh(string $attribute, mixed $value, Closure $fail): void
    {
        $bytes = $this->decoded($value);

        if ($bytes === null || strlen($bytes) !== 65 || $bytes[0] !== "\x04") {
            $fail(__('web_push.validation.keys'));
        }
    }

    /**
     * @param  Closure(string): void  $fail
     */
    private function authSecret(string $attribute, mixed $value, Closure $fail): void
    {
        $bytes = $this->decoded($value);

        if ($bytes === null || strlen($bytes) !== 16) {
            $fail(__('web_push.validation.keys'));
        }
    }

    private function decoded(mixed $value): ?string
    {
        if (! is_string($value) || preg_match(self::BASE64URL, $value) !== 1) {
            return null;
        }

        $bytes = base64_decode(strtr(rtrim($value, '='), '-_', '+/'), true);

        return $bytes === false ? null : $bytes;
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
