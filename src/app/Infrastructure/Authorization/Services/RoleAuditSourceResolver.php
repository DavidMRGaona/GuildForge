<?php

declare(strict_types=1);

namespace App\Infrastructure\Authorization\Services;

use App\Domain\Authorization\Enums\RoleAuditSource;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Livewire\LivewireManager;
use Throwable;

/**
 * Where a change recorded in the role history is being made:
 *
 * - Queue: while a queued job runs (also on the sync connection). The queue events keep the
 *   count (AuthorizationServiceProvider), so this is a singleton, never scoped: the worker
 *   drops scoped instances when a job starts.
 * - Panel / Web: while a request is handled (it has a route, or the process is not a console
 *   one). Panel when the admin panel serves it: the panel's own middleware set the current
 *   panel, the path is under the panel's path, or it is a Livewire update whose page is under
 *   it. That page comes from the component snapshot (memo.path, the same value as
 *   LivewireManager::originalPath()), which Livewire checks against its checksum before any
 *   component code runs; the Referer header is not used.
 * - Command: a console process outside any request or job.
 * - System: none of the above.
 *
 * A module's change gets the source of the context it runs in (with its actor), not a
 * source of its own: the authorization service cannot tell who calls it.
 *
 * RoleAuditContext may impose another source (a module update's finish process records the
 * queued job's); the history reads the source through it.
 */
final class RoleAuditSourceResolver
{
    private const string DEFAULT_PANEL_PATH = 'admin';

    private int $runningJobs = 0;

    public function jobStarted(): void
    {
        $this->runningJobs++;
    }

    public function jobFinished(): void
    {
        $this->runningJobs = max(0, $this->runningJobs - 1);
    }

    public function resolve(): RoleAuditSource
    {
        if ($this->runningJobs > 0) {
            return RoleAuditSource::Queue;
        }

        $request = app()->bound('request') ? app('request') : null;

        if ($request instanceof Request && ($request->route() !== null || ! app()->runningInConsole())) {
            return $this->isPanelRequest($request) ? RoleAuditSource::Panel : RoleAuditSource::Web;
        }

        if (app()->runningInConsole()) {
            return RoleAuditSource::Command;
        }

        return RoleAuditSource::System;
    }

    private function isPanelRequest(Request $request): bool
    {
        try {
            if (Filament::getCurrentPanel() !== null) {
                return true;
            }

            $panelPath = trim(Filament::getDefaultPanel()->getPath(), '/');
        } catch (Throwable) {
            $panelPath = self::DEFAULT_PANEL_PATH;
        }

        if ($this->isUnder($request->path(), $panelPath)) {
            return true;
        }

        $livewirePrefix = trim(app(LivewireManager::class)->getUriPrefix(), '/');

        if (! $request->is($livewirePrefix.'/*')) {
            return false;
        }

        $page = $this->livewirePagePath($request);

        return $page !== null && $this->isUnder($page, $panelPath);
    }

    private function livewirePagePath(Request $request): ?string
    {
        $snapshot = $request->input('components.0.snapshot');

        if (! is_string($snapshot)) {
            return null;
        }

        try {
            $decoded = json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        $path = is_array($decoded) && is_array($decoded['memo'] ?? null) ? ($decoded['memo']['path'] ?? null) : null;

        return is_string($path) ? $path : null;
    }

    private function isUnder(string $path, string $prefix): bool
    {
        $path = trim($path, '/');

        if ($prefix === '') {
            return true;
        }

        return $path === $prefix || str_starts_with($path, $prefix.'/');
    }
}
