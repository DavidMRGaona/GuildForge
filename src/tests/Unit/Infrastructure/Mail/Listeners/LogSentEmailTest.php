<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Mail\Listeners;

use App\Domain\Mail\Entities\EmailLog;
use App\Domain\Mail\Enums\EmailStatus;
use App\Domain\Mail\Repositories\EmailLogRepositoryInterface;
use App\Infrastructure\Mail\Listeners\LogSentEmail;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class LogSentEmailTest extends TestCase
{
    private MockObject&EmailLogRepositoryInterface $repository;

    private LogSentEmail $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(EmailLogRepositoryInterface::class);
        $this->listener = new LogSentEmail($this->repository);
    }

    public function test_it_creates_email_log_on_message_sent(): void
    {
        $event = $this->createMessageSentEvent(
            from: 'sender@example.com',
            to: ['recipient@example.com'],
            subject: 'Test subject',
        );

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (EmailLog $emailLog): bool {
                return $emailLog->recipient() === 'recipient@example.com'
                    && $emailLog->subject() === 'Test subject'
                    && $emailLog->status() === EmailStatus::Sent;
            }));

        $this->listener->handle($event);
    }

    public function test_it_logs_sender_address(): void
    {
        $event = $this->createMessageSentEvent(
            from: 'noreply@guildforge.com',
            to: ['user@example.com'],
            subject: 'Welcome',
        );

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (EmailLog $emailLog): bool {
                return $emailLog->sender() === 'noreply@guildforge.com';
            }));

        $this->listener->handle($event);
    }

    public function test_it_handles_multiple_recipients(): void
    {
        $event = $this->createMessageSentEvent(
            from: 'sender@example.com',
            to: ['first@example.com', 'second@example.com'],
            subject: 'Multi-recipient',
        );

        $this->repository->expects($this->atLeastOnce())
            ->method('save');

        $this->listener->handle($event);
    }

    /**
     * @param  array<string>  $to
     */
    private function createMessageSentEvent(string $from, array $to, string $subject): MessageSent
    {
        $email = new Email;
        $email->from(new Address($from));
        $email->subject($subject);
        $email->text('Test body');

        $toAddresses = array_map(fn (string $addr) => new Address($addr), $to);
        $email->to(...$toAddresses);

        $envelope = Envelope::create($email);
        $symfonySentMessage = new \Symfony\Component\Mailer\SentMessage($email, $envelope);
        $laravelSentMessage = new SentMessage($symfonySentMessage);

        return new MessageSent($laravelSentMessage);
    }
}
