<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Listeners;

use App\Domain\Mail\Entities\EmailLog;
use App\Domain\Mail\Repositories\EmailLogRepositoryInterface;
use App\Domain\Mail\ValueObjects\EmailLogId;
use DateTimeImmutable;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Email;

final readonly class LogSentEmail
{
    public function __construct(
        private EmailLogRepositoryInterface $repository,
    ) {}

    public function handle(MessageSent $event): void
    {
        $message = $event->sent->getOriginalMessage();

        if (! $message instanceof Email) {
            return;
        }

        $sender = $this->extractSender($message);
        $subject = $message->getSubject() ?? '';
        $mailer = $event->data['mailer'] ?? config('mail.default', 'smtp');

        foreach ($message->getTo() as $recipient) {
            $emailLog = EmailLog::createSent(
                id: EmailLogId::generate(),
                recipient: $recipient->getAddress(),
                sender: $sender,
                subject: $subject,
                mailer: is_string($mailer) ? $mailer : 'smtp',
                sentAt: new DateTimeImmutable,
            );

            $this->repository->save($emailLog);
        }
    }

    private function extractSender(Email $message): ?string
    {
        $from = $message->getFrom();

        if ($from === []) {
            return null;
        }

        return $from[0]->getAddress();
    }
}
