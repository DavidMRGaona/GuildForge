<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gametables_slug_redirects')) {
            return;
        }

        DB::table('gametables_slug_redirects')
            ->orderBy('created_at')
            ->each(function (object $row): void {
                DB::table('slug_redirects')->insert([
                    'id' => $row->id,
                    'old_slug' => $row->old_slug,
                    'new_slug' => $row->new_slug,
                    'entity_type' => $row->entity_type,
                    'entity_id' => $row->entity_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            });

        Schema::drop('gametables_slug_redirects');
    }

    public function down(): void
    {
        // Create the old table structure
        if (! Schema::hasTable('gametables_slug_redirects')) {
            Schema::create('gametables_slug_redirects', function ($table): void {
                $table->uuid('id')->primary();
                $table->string('old_slug')->index();
                $table->string('new_slug')->index();
                $table->string('entity_type');
                $table->uuid('entity_id')->index();
                $table->timestamps();

                $table->unique(['old_slug', 'entity_type']);
            });
        }

        // Move data back (only game_table and campaign entity types)
        DB::table('slug_redirects')
            ->whereIn('entity_type', ['game_table', 'campaign'])
            ->orderBy('created_at')
            ->each(function (object $row): void {
                DB::table('gametables_slug_redirects')->insert([
                    'id' => $row->id,
                    'old_slug' => $row->old_slug,
                    'new_slug' => $row->new_slug,
                    'entity_type' => $row->entity_type,
                    'entity_id' => $row->entity_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            });

        // Delete migrated records from the new table
        DB::table('slug_redirects')
            ->whereIn('entity_type', ['game_table', 'campaign'])
            ->delete();
    }
};
