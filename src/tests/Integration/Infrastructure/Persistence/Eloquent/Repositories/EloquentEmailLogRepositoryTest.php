<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Mail\Entities\EmailLog;
use App\Domain\Mail\Enums\EmailStatus;
use App\Domain\Mail\Repositories\EmailLogRepositoryInterface;
use App\Domain\Mail\ValueObjects\EmailLogId;
use App\Infrastructure\Persistence\Eloquent\Models\EmailLogModel;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentEmailLogRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class EloquentEmailLogRepositoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private EloquentEmailLogRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentEmailLogRepository;
    }

    public function test_it_implements_email_log_repository_interface(): void
    {
        $this->assertInstanceOf(EmailLogRepositoryInterface::class, $this->repository);
    }

    public function test_saves_email_log(): void
    {
        $id = EmailLogId::generate();
        $sentAt = new DateTimeImmutable;

        $emailLog = EmailLog::createSent(
            id: $id,
            recipient: 'user@example.com',
            sender: 'noreply@guildforge.com',
            subject: 'Test email',
            mailer: 'smtp',
            sentAt: $sentAt,
        );

        $this->repository->save($emailLog);

        $this->assertDatabaseHas('email_logs', [
            'id' => $id->value,
            'recipient' => 'user@example.com',
            'sender' => 'noreply@guildforge.com',
            'subject' => 'Test email',
            'status' => EmailStatus::Sent->value,
            'mailer' => 'smtp',
        ]);
    }

    public function test_find_by_date_range(): void
    {
        $this->createEmailLog([
            'subject' => 'Old email',
            'created_at' => now()->subDays(10),
        ]);
        $this->createEmailLog([
            'subject' => 'In range email',
            'created_at' => now()->subDays(3),
        ]);
        $this->createEmailLog([
            'subject' => 'Recent email',
            'created_at' => now()->subDay(),
        ]);
        $this->createEmailLog([
            'subject' => 'Future email',
            'created_at' => now()->addDay(),
        ]);

        $from = now()->subDays(5);
        $to = now();

        $results = $this->repository->findByDateRange($from, $to);

        $this->assertCount(2, $results);

        $firstResult = $results->first();
        $this->assertInstanceOf(EmailLog::class, $firstResult);

        $subjects = $results->map(fn (EmailLog $log): ?string => $log->subject())->toArray();
        $this->assertContains('In range email', $subjects);
        $this->assertContains('Recent email', $subjects);
        $this->assertNotContains('Old email', $subjects);
        $this->assertNotContains('Future email', $subjects);
    }

    public function test_count_by_status_since(): void
    {
        $this->createEmailLog([
            'status' => EmailStatus::Sent,
            'created_at' => now()->subHours(2),
        ]);
        $this->createEmailLog([
            'status' => EmailStatus::Sent,
            'created_at' => now()->subHour(),
        ]);
        $this->createEmailLog([
            'status' => EmailStatus::Failed,
            'created_at' => now()->subHour(),
        ]);
        $this->createEmailLog([
            'status' => EmailStatus::Sent,
            'created_at' => now()->subDays(2),
        ]);

        $since = now()->subDays(1);

        $sentCount = $this->repository->countByStatusSince(EmailStatus::Sent, $since);
        $failedCount = $this->repository->countByStatusSince(EmailStatus::Failed, $since);

        $this->assertEquals(2, $sentCount);
        $this->assertEquals(1, $failedCount);
    }

    public function test_get_recent_failed(): void
    {
        $this->createEmailLog([
            'status' => EmailStatus::Sent,
            'subject' => 'Success email',
            'created_at' => now()->subHour(),
        ]);
        $this->createEmailLog([
            'status' => EmailStatus::Failed,
            'subject' => 'Failed email 1',
            'created_at' => now()->subHours(3),
        ]);
        $this->createEmailLog([
            'status' => EmailStatus::Failed,
            'subject' => 'Failed email 2',
            'created_at' => now()->subHours(2),
        ]);
        $this->createEmailLog([
            'status' => EmailStatus::Failed,
            'subject' => 'Failed email 3',
            'created_at' => now()->subHour(),
        ]);

        $results = $this->repository->getRecentFailed(2);

        $this->assertCount(2, $results);

        $firstResult = $results->first();
        $this->assertInstanceOf(EmailLog::class, $firstResult);

        // Should be ordered by most recent first
        $subjects = $results->map(fn (EmailLog $log): ?string => $log->subject())->toArray();
        $this->assertEquals('Failed email 3', $subjects[0]);
        $this->assertEquals('Failed email 2', $subjects[1]);

        // Should not contain success emails
        $this->assertNotContains('Success email', $subjects);
    }

    public function test_get_recent_failed_returns_empty_when_no_failures(): void
    {
        $this->createEmailLog([
            'status' => EmailStatus::Sent,
            'subject' => 'Success email',
        ]);

        $results = $this->repository->getRecentFailed();

        $this->assertCount(0, $results);
    }

    public function test_find_by_date_range_returns_empty_when_no_matches(): void
    {
        $this->createEmailLog([
            'created_at' => now()->subDays(30),
        ]);

        $from = now()->subDays(5);
        $to = now();

        $results = $this->repository->findByDateRange($from, $to);

        $this->assertCount(0, $results);
    }

    public function test_update_status_by_message_id(): void
    {
        $messageId = 'ses-message-id-'.Str::uuid()->toString();

        $this->createEmailLog([
            'status' => EmailStatus::Sent,
            'message_id' => $messageId,
        ]);

        $this->repository->updateStatusByMessageId($messageId, EmailStatus::Bounced);

        $this->assertDatabaseHas('email_logs', [
            'message_id' => $messageId,
            'status' => EmailStatus::Bounced->value,
        ]);
    }

    /**
     * Helper to create an EmailLogModel with default values.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createEmailLog(array $overrides = []): EmailLogModel
    {
        $model = new EmailLogModel;
        $model->id = $overrides['id'] ?? Str::uuid()->toString();
        $model->recipient = $overrides['recipient'] ?? 'test@example.com';
        $model->sender = $overrides['sender'] ?? 'noreply@guildforge.com';
        $model->subject = $overrides['subject'] ?? 'Test email';
        $model->status = $overrides['status'] ?? EmailStatus::Sent;
        $model->mailer = $overrides['mailer'] ?? 'smtp';
        $model->message_id = $overrides['message_id'] ?? null;
        $model->save();

        if (isset($overrides['created_at'])) {
            $model->created_at = $overrides['created_at'];
            $model->timestamps = false;
            $model->save();
            $model->timestamps = true;
        }

        return $model;
    }
}
