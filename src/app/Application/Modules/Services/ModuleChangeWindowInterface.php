<?php

declare(strict_types=1);

namespace App\Application\Modules\Services;

use App\Domain\Modules\ValueObjects\ModuleSetFingerprint;
use Closure;

/**
 * Marks the time during which the modules change (enable, disable, install, update, uninstall),
 * so long-running queue workers stay in step: while a change runs they take no job, and once it
 * ends they compare the modules they booted with against the ones it left and stop when those
 * differ. A worker booted before a module was enabled has no autoloader for its classes, so a
 * job of that module would fail to unserialize there (core#624).
 *
 * Windows nest within a process: only the outermost one opens and closes it for the others.
 * It never makes a change fail: a cache it cannot reach is logged and the change goes on.
 */
interface ModuleChangeWindowInterface
{
    /**
     * Run a change of the modules inside the window; it ends even when the change throws.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $change
     * @return TResult
     */
    public function run(Closure $change): mixed;

    /**
     * Open the window by hand, for a change whose steps span a try/catch; pair it with close().
     */
    public function open(): void;

    /**
     * Close a window opened with open(); without one open it does nothing.
     */
    public function close(): void;

    /**
     * Whether any process is changing the modules now. False when it cannot be told.
     */
    public function inProgress(): bool;

    /**
     * The modules the last change left enabled. Null when no change recorded them or it cannot be told.
     */
    public function currentFingerprint(): ?ModuleSetFingerprint;
}
