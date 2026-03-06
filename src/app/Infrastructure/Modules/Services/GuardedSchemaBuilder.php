<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use Closure;
use Illuminate\Database\Schema\Builder;

final class GuardedSchemaBuilder extends Builder
{
    private ModuleSchemaGuard $guard;

    public function setGuard(ModuleSchemaGuard $guard): void
    {
        $this->guard = $guard;
    }

    public function create(mixed $table, Closure $callback): void
    {
        if (isset($this->guard)) {
            $this->guard->assertPermitted('create', (string) $table);
        }

        parent::create($table, $callback);
    }

    public function table(mixed $table, Closure $callback): void
    {
        if (isset($this->guard)) {
            $this->guard->assertPermitted('table', (string) $table);
        }

        parent::table($table, $callback);
    }

    public function drop(mixed $table): void
    {
        if (isset($this->guard)) {
            $this->guard->assertPermitted('drop', (string) $table);
        }

        parent::drop($table);
    }

    public function dropIfExists(mixed $table): void
    {
        if (isset($this->guard)) {
            $this->guard->assertPermitted('dropIfExists', (string) $table);
        }

        parent::dropIfExists($table);
    }

    public function rename(mixed $from, mixed $to): void
    {
        if (isset($this->guard)) {
            $this->guard->assertPermitted('rename', (string) $from);
            $this->guard->assertPermitted('rename', (string) $to);
        }

        parent::rename($from, $to);
    }
}
