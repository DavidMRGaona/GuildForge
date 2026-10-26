<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The role history: every role assignment and removal, and every change to a role's
 * definition. No foreign keys on purpose: the entries outlive the account, the role and the
 * actor they name (role_name keeps the role's name once the role is gone).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_audit_log', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // The account affected; null for a change to the role's definition
            $table->uuid('user_id')->nullable()->index();
            $table->uuid('role_id')->nullable()->index();
            $table->string('role_name');
            // assigned, removed, role_created, role_updated or role_deleted
            $table->string('event');
            $table->json('changes')->nullable();
            // Who made the change; null when nobody was signed in
            $table->uuid('actor_id')->nullable();
            // panel, web, command, queue or system
            $table->string('source');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_audit_log');
    }
};
