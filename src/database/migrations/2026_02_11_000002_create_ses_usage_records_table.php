<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ses_usage_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('date')->unique();
            $table->integer('emails_sent')->default(0);
            $table->decimal('estimated_cost', 10, 4)->default(0);
            $table->integer('bounces_count')->default(0);
            $table->integer('complaints_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ses_usage_records');
    }
};
