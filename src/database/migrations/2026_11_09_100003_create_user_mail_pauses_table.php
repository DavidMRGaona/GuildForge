<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A person's pause of the optional mail: a row means "paused", and a null end means "until the
 * person resumes". Whether it is still running is decided when a mail is sent (the end is an
 * instant in the storage zone), so nothing has to clean it up. Resuming deletes the row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_mail_pauses', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->timestamp('paused_until')->nullable();
            $table->timestamps();

            $table->index('paused_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_mail_pauses');
    }
};
