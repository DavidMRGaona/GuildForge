<?php

declare(strict_types=1);

use App\Application\Content\Services\TrixContentMigratorInterface;
use Illuminate\Database\Migrations\Migration;

/**
 * Rich text written with the Trix editor is converted once, on deploy, before any admin
 * opens it with the TipTap editor (which would otherwise drop paragraphs and image
 * captions on the first save). The original values go to storage/app/backups/rich-text.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(TrixContentMigratorInterface::class)->migrate();
    }

    public function down(): void
    {
        // The converted HTML still renders on the previous editor; to get the original
        // values back: php artisan content:convert-trix --restore=<backup file>
    }
};
