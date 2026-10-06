<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table): void {
            $table->string('latest_blocked_version')->nullable();
            // list of CompatibilityIssue::toArray(): structured, translated when shown
            $table->json('latest_blocked_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table): void {
            $table->dropColumn(['latest_blocked_version', 'latest_blocked_reason']);
        });
    }
};
