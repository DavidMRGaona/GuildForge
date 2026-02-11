<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mail\Entities;

use App\Domain\Mail\Entities\EmailLog;
use App\Domain\Mail\Enums\EmailStatus;
use App\Domain\Mail\ValueObjects\EmailLogId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EmailLogTest extends TestCase
{
    public function test_can_create_email_log(): void
    {
        $id = EmailLogId::generate();
        $recipient = 'user@example.com';
        $sender = 'noreply@guildforge.com';
        $subject = 'Welcome to GuildForge';
        $mailer = 'ses';
        $status = EmailStatus::Sent;
        $errorMessage = null;
        $messageId = 'ses-message-id-12345';
        $sentAt = new DateTimeImmutable('2026-02-11 10:00:00');
        $createdAt = new DateTimeImmutable('2026-02-11 09:59:59');

        $emailLog = new EmailLog(
            id: $id,
            recipient: $recipient,
            sender: $sender,
            subject: $subject,
            mailer: $mailer,
            status: $status,
            errorMessage: $errorMessage,
            messageId: $messageId,
            sentAt: $sentAt,
            createdAt: $createdAt,
        );

        $this->assertSame($id, $emailLog->id());
        $this->assertEquals($recipient, $emailLog->recipient());
        $this->assertEquals($sender, $emailLog->sender());
        $this->assertEquals($subject, $emailLog->subject());
        $this->assertEquals($mailer, $emailLog->mailer());
        $this->assertSame($status, $emailLog->status());
        $this->assertNull($emailLog->errorMessage());
        $this->assertEquals($messageId, $emailLog->messageId());
        $this->assertEquals($sentAt, $emailLog->sentAt());
        $this->assertEquals($createdAt, $emailLog->createdAt());
    }

    public function test_create_sent_factory_method(): void
    {
        $id = EmailLogId::generate();
        $recipient = 'member@guildforge.com';
        $sender = 'noreply@guildforge.com';
        $subject = 'Event reminder';
        $mailer = 'ses';
        $sentAt = new DateTimeImmutable('2026-02-11 12:00:00');

        $emailLog = EmailLog::createSent(
            id: $id,
            recipient: $recipient,
            sender: $sender,
            subject: $subject,
            mailer: $mailer,
            sentAt: $sentAt,
        );

        $this->assertSame($id, $emailLog->id());
        $this->assertEquals($recipient, $emailLog->recipient());
        $this->assertEquals($sender, $emailLog->sender());
        $this->assertEquals($subject, $emailLog->subject());
        $this->assertEquals($mailer, $emailLog->mailer());
        $this->assertSame(EmailStatus::Sent, $emailLog->status());
        $this->assertEquals($sentAt, $emailLog->sentAt());
        $this->assertNull($emailLog->errorMessage());
        $this->assertNull($emailLog->messageId());
    }

    public function test_mark_bounced_changes_status(): void
    {
        $emailLog = $this->createEmailLog();

        $this->assertSame(EmailStatus::Sent, $emailLog->status());

        $emailLog->markBounced();

        $this->assertSame(EmailStatus::Bounced, $emailLog->status());
    }

    public function test_mark_complained_changes_status(): void
    {
        $emailLog = $this->createEmailLog();

        $this->assertSame(EmailStatus::Sent, $emailLog->status());

        $emailLog->markComplained();

        $this->assertSame(EmailStatus::Complained, $emailLog->status());
    }

    public function test_optional_fields_default_to_null(): void
    {
        $id = EmailLogId::generate();
        $recipient = 'user@example.com';
        $status = EmailStatus::Sent;

        $emailLog = new EmailLog(
            id: $id,
            recipient: $recipient,
            sender: null,
            subject: null,
            mailer: null,
            status: $status,
        );

        $this->assertSame($id, $emailLog->id());
        $this->assertEquals($recipient, $emailLog->recipient());
        $this->assertNull($emailLog->sender());
        $this->assertNull($emailLog->subject());
        $this->assertNull($emailLog->mailer());
        $this->assertSame($status, $emailLog->status());
        $this->assertNull($emailLog->errorMessage());
        $this->assertNull($emailLog->messageId());
        $this->assertNull($emailLog->sentAt());
        $this->assertNull($emailLog->createdAt());
    }

    private function createEmailLog(
        ?EmailLogId $id = null,
        string $recipient = 'user@example.com',
        ?string $sender = 'noreply@guildforge.com',
        ?string $subject = 'Test email subject',
        string $mailer = 'ses',
        EmailStatus $status = EmailStatus::Sent,
        ?DateTimeImmutable $sentAt = null,
    ): EmailLog {
        return EmailLog::createSent(
            id: $id ?? EmailLogId::generate(),
            recipient: $recipient,
            sender: $sender,
            subject: $subject,
            mailer: $mailer,
            sentAt: $sentAt ?? new DateTimeImmutable,
        );
    }
}
