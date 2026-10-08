<?php

declare(strict_types=1);

namespace App\Http\Middleware\Concerns;

use Illuminate\Http\Request;
use Livewire\LivewireManager;

trait IdentifiesLivewireRequests
{
    /**
     * Livewire 4 serves every endpoint under `/livewire-{hash}/`, the hash derived
     * from APP_KEY, so it differs per tenant and cannot be written down.
     */
    private function isLivewireRequest(Request $request): bool
    {
        $prefix = ltrim(app(LivewireManager::class)->getUriPrefix(), '/');

        return $request->is($prefix.'/*');
    }
}
