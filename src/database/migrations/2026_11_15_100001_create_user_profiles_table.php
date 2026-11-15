<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The public profile of each account (core#740), created with it. Existing accounts get theirs through
 * `profiles:create-missing`, never here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->uuid('user_id')->primary();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            // ProfileHandle: lowercase, 3 to 30 characters
            $table->string('handle', 30)->unique();
            // Until the owner picks one, the handle follows the public name
            $table->boolean('handle_chosen')->default(false);
            // ProfileVisibility
            $table->string('visibility', 16)->default('members');
            // Plain text, at most 500 characters (checked by the application)
            $table->text('bio')->nullable();
            $table->string('banner_public_id')->nullable();
            // Membership is personal data: shown only when the owner says so
            $table->boolean('show_membership')->default(false);
            // ProfileLayout: {"sections":[{"key","enabled"}],"stats":[{"key","enabled"}]}
            $table->json('layout')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
