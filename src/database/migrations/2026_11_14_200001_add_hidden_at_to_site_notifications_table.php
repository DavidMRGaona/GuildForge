<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A notice the person hid from their notification centre (core#670): out of the bell, the
 * «Avisos» page and the counter, for that person only, until the daily clean-up deletes it
 * with the read ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_notifications', function (Blueprint $table): void {
            $table->timestamp('hidden_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_notifications', function (Blueprint $table): void {
            $table->dropColumn('hidden_at');
        });
    }
};
