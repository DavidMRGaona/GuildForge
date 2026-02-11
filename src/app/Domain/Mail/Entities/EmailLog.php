<?php

declare(strict_types=1);

namespace App\Domain\Mail\Entities;

use App\Domain\Mail\Enums\EmailStatus;
use App\Domain\Mail\ValueObjects\EmailLogId;
use DateTimeImmutable;

final class EmailLog
{
    public function __construct(
        private readonly EmailLogId $id,
        private readonly string $recipient,
        private readonly ?string $sender,
        private readonly ?string $subject,
        private readonly ?string $mailer,
        private EmailStatus $status,
        private readonly ?string $errorMessage = null,
        private readonly ?string $messageId = null,
        private readonly ?DateTimeImmutable $sentAt = null,
        private readonly ?DateTimeImmutable $createdAt = null,
    ) {}

    public static function createSent(
        EmailLogId $id,
        string $recipient,
        ?string $sender,
        ?string $subject,
        string $mailer,
        DateTimeImmutable $sentAt,
    ): self {
        return new self(
            id: $id,
            recipient: $recipient,
            sender: $sender,
            subject: $subject,
            mailer: $mailer,
            status: EmailStatus::Sent,
            sentAt: $sentAt,
        );
    }

    public function id(): EmailLogId
    {
        return $this->id;
    }

    public function recipient(): string
    {
        return $this->recipient;
    }

    public function sender(): ?string
    {
        return $this->sender;
    }

    public function subject(): ?string
    {
        return $this->subject;
    }

    public function mailer(): ?string
    {
        return $this->mailer;
    }

    public function status(): EmailStatus
    {
        return $this->status;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function messageId(): ?string
    {
        return $this->messageId;
    }

    public function sentAt(): ?DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function markBounced(): void
    {
        $this->status = EmailStatus::Bounced;
    }

    public function markComplained(): void
    {
        $this->status = EmailStatus::Complained;
    }
}
