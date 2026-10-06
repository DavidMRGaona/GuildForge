<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Records that this file was compiled: the compatibility gate must never let that happen
$GLOBALS['incompatible_module_compiled'][] = __FILE__;
$marker = getenv('INCOMPATIBLE_MODULE_MARKER');
if (is_string($marker) && $marker !== '') {
    file_put_contents($marker, __FILE__.PHP_EOL, FILE_APPEND);
}

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('incompatible_widgets', function (Blueprint $table): void {
            $table->id();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incompatible_widgets');
    }
};
