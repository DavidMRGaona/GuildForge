<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class FakeSoftDeletingModel extends Model
{
    use SoftDeletes;

    protected $table = 'fake_media_records';

    protected $guarded = [];

    public static function createTable(): void
    {
        Schema::create('fake_media_records', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->default('');
            $table->string('image')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }
}
