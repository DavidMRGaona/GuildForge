<?php

declare(strict_types=1);

namespace Tests\Integration\Notifications;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\VerifyPendingEmailNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mailer\SentMessage;
use Tests\TestCase;

final class VerifyPendingEmailNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_link_to_confirm_a_new_email_goes_to_the_new_address(): void
    {
        $user = UserModel::factory()->create(['email' => 'old@example.com', 'pending_email' => 'new@example.com']);

        $user->notify(new VerifyPendingEmailNotification('new@example.com'));

        $this->assertSame(['new@example.com'], $this->recipientsOfTheLastMessage());
    }

    public function test_other_notifications_still_go_to_the_current_address(): void
    {
        $user = UserModel::factory()->unverified()->create(['email' => 'old@example.com', 'pending_email' => 'new@example.com']);

        $user->notify(new VerifyEmail);

        $this->assertSame(['old@example.com'], $this->recipientsOfTheLastMessage());
    }

    public function test_a_notification_cannot_change_the_recipient_without_the_contract(): void
    {
        $user = UserModel::factory()->create(['email' => 'old@example.com']);

        // Same method names as the contract and as Laravel's notifiable hook, but no contract
        $user->notify(new class extends Notification
        {
            /** @return list<string> */
            public function via(object $notifiable): array
            {
                return ['mail'];
            }

            public function toMail(object $notifiable): MailMessage
            {
                return (new MailMessage)->line('Hello');
            }

            public function mailRecipient(): string
            {
                return 'someone-else@example.com';
            }

            public function routeNotificationForMail(): string
            {
                return 'someone-else@example.com';
            }
        });

        $this->assertSame(['old@example.com'], $this->recipientsOfTheLastMessage());
    }

    /**
     * @return list<string>
     */
    private function recipientsOfTheLastMessage(): array
    {
        $transport = app('mailer')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $message = $transport->messages()->last();
        $this->assertInstanceOf(SentMessage::class, $message, 'No mail was sent');

        return array_values(array_map(
            static fn ($address): string => $address->getAddress(),
            $message->getEnvelope()->getRecipients(),
        ));
    }
}
