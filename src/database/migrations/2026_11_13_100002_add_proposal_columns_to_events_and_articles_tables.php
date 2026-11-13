<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Events and articles proposed from the web (core#570): a proposal is an unpublished row with its
 * review data. Rows created in the panel keep every column null.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const array TABLES = ['events', 'articles'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('proposal_status', 32)->nullable();
                $table->foreignUuid('proposed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('proposal_note')->nullable();
                $table->text('proposal_reason')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

                $table->index(['proposal_status', 'submitted_at']);
                $table->index(['proposed_by', 'proposal_status']);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropIndex(['proposal_status', 'submitted_at']);
                $table->dropIndex(['proposed_by', 'proposal_status']);
                $table->dropForeign(['proposed_by']);
                $table->dropForeign(['reviewed_by']);
                $table->dropColumn([
                    'proposal_status',
                    'proposed_by',
                    'proposal_note',
                    'proposal_reason',
                    'submitted_at',
                    'reviewed_at',
                    'reviewed_by',
                ]);
            });
        }
    }
};
