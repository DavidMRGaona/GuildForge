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
        Schema::table('modules', function (Blueprint $table) {
            $table->string('source_owner')->nullable()->after('dependencies');
            $table->string('source_repo')->nullable()->after('source_owner');
            $table->string('latest_available_version')->nullable()->after('source_repo');
            $table->timestamp('last_update_check_at')->nullable()->after('latest_available_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn([
                'source_owner',
                'source_repo',
                'latest_available_version',
                'last_update_check_at',
            ]);
        });
    }
};
