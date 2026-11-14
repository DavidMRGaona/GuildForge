<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The roles an administrator viewed the site as, kept by id, name and display name (roles
 * can be renamed or deleted later). Null for an impersonation of an account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impersonation_log', function (Blueprint $table): void {
            $table->json('viewed_roles')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('impersonation_log', function (Blueprint $table): void {
            $table->dropColumn('viewed_roles');
        });
    }
};
