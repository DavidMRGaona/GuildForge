<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mark existing core menu items with module = 'core' to protect them from deletion.
     */
    public function up(): void
    {
        DB::table('menu_items')
            ->whereNull('module')
            ->whereIn('route', [
                'home',
                'events.index',
                'calendar',
                'articles.index',
                'galleries.index',
                'about',
            ])
            ->update(['module' => 'core']);
    }

    /**
     * Revert core menu items to module = null.
     */
    public function down(): void
    {
        DB::table('menu_items')
            ->where('module', 'core')
            ->update(['module' => null]);
    }
};
