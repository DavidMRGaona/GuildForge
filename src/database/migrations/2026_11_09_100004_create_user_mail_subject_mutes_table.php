<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The things (a game, a campaign, a tournament...) each person muted from a mail footer: a row
 * means "no optional mail about it". The names are stored as they were when muted, so the
 * profile still shows them once the thing or its module is gone. Unmuting deletes the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_mail_subject_mutes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_type', 100);
            $table->string('subject_id', 64);
            $table->string('label', 160);
            $table->string('type_label', 150)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'subject_type', 'subject_id']);
            // A module forgets a thing it deleted, for every person at once
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_mail_subject_mutes');
    }
};
