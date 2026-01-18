<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Rename event_date to start_date
            $table->renameColumn('event_date', 'start_date');
        });

        Schema::table('events', function (Blueprint $table) {
            // Add end_date (nullable - if null, single-day event)
            $table->dateTime('end_date')->nullable()->after('start_date');

            // Add pricing (nullable - if null, free event)
            $table->decimal('member_price', 8, 2)->nullable()->after('location');
            $table->decimal('non_member_price', 8, 2)->nullable()->after('member_price');

            // Update indexes
            $table->index('start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['start_date']);

            $table->dropColumn(['end_date', 'member_price', 'non_member_price']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('start_date', 'event_date');
        });
    }
};
