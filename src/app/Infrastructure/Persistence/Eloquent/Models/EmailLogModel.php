<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Domain\Mail\Enums\EmailStatus;
use Database\Factories\EmailLogModelFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $recipient
 * @property string|null $sender
 * @property string|null $subject
 * @property string|null $mailer
 * @property EmailStatus $status
 * @property string|null $error_message
 * @property string|null $message_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class EmailLogModel extends Model
{
    /** @use HasFactory<EmailLogModelFactory> */
    use HasFactory;

    use HasUuids;

    protected static function newFactory(): EmailLogModelFactory
    {
        return EmailLogModelFactory::new();
    }

    protected $table = 'email_logs';

    protected $fillable = [
        'id',
        'recipient',
        'sender',
        'subject',
        'mailer',
        'status',
        'error_message',
        'message_id',
        'metadata',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EmailStatus::class,
            'metadata' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
