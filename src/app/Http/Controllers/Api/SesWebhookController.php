<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Mail\Enums\EmailStatus;
use App\Domain\Mail\Events\SesBounceReceived;
use App\Domain\Mail\Events\SesComplaintReceived;
use App\Domain\Mail\Repositories\EmailLogRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Infrastructure\Mail\Ses\SesUsageTracker;
use App\Infrastructure\Mail\Ses\SnsMessageValidatorInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class SesWebhookController extends Controller
{
    public function __construct(
        private readonly SnsMessageValidatorInterface $validator,
        private readonly SesUsageTracker $usageTracker,
        private readonly EmailLogRepositoryInterface $emailLogRepository,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed>|null $payload */
        $payload = $request->json()->all();

        if ($payload === null || $payload === [] || ! isset($payload['Type'])) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        if (! $this->validator->isValid($payload)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        return match ($payload['Type']) {
            'SubscriptionConfirmation' => $this->handleSubscriptionConfirmation($payload),
            'Notification' => $this->handleNotification($payload),
            default => response()->json(['status' => 'ok']),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleSubscriptionConfirmation(array $payload): JsonResponse
    {
        $subscribeUrl = $payload['SubscribeURL'] ?? null;

        if (is_string($subscribeUrl)) {
            Http::get($subscribeUrl);
            Log::info('SES SNS subscription confirmed', ['topic' => $payload['TopicArn'] ?? 'unknown']);
        }

        return response()->json(['status' => 'confirmed']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function handleNotification(array $payload): JsonResponse
    {
        $messageJson = $payload['Message'] ?? '';

        if (! is_string($messageJson)) {
            return response()->json(['status' => 'ok']);
        }

        /** @var array<string, mixed>|null $message */
        $message = json_decode($messageJson, true);

        if (! is_array($message)) {
            return response()->json(['status' => 'ok']);
        }

        $notificationType = $message['notificationType'] ?? '';

        match ($notificationType) {
            'Bounce' => $this->handleBounce($message),
            'Complaint' => $this->handleComplaint($message),
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleBounce(array $message): void
    {
        $bounce = $message['bounce'] ?? [];
        $mail = $message['mail'] ?? [];
        $messageId = $mail['messageId'] ?? null;

        if (! is_array($bounce) || ! is_string($messageId)) {
            return;
        }

        $recipients = $this->extractRecipients($bounce['bouncedRecipients'] ?? []);
        $bounceType = is_string($bounce['bounceType'] ?? null) ? $bounce['bounceType'] : 'Unknown';

        $this->emailLogRepository->updateStatusByMessageId($messageId, EmailStatus::Bounced);
        $this->usageTracker->incrementBounces();

        event(new SesBounceReceived($messageId, $bounceType, $recipients));
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handleComplaint(array $message): void
    {
        $complaint = $message['complaint'] ?? [];
        $mail = $message['mail'] ?? [];
        $messageId = $mail['messageId'] ?? null;

        if (! is_array($complaint) || ! is_string($messageId)) {
            return;
        }

        $recipients = $this->extractRecipients($complaint['complainedRecipients'] ?? []);

        $this->emailLogRepository->updateStatusByMessageId($messageId, EmailStatus::Complained);
        $this->usageTracker->incrementComplaints();

        event(new SesComplaintReceived($messageId, $recipients));
    }

    /**
     * @param  array<int, array<string, mixed>>  $recipientList
     * @return array<int, string>
     */
    private function extractRecipients(array $recipientList): array
    {
        return collect($recipientList)
            ->pluck('emailAddress')
            ->filter(fn (mixed $email): bool => is_string($email))
            ->values()
            ->all();
    }
}
