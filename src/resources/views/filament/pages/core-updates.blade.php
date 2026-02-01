<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Current version info --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                {{ __('filament.updates.core.current.title') }}
            </h2>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ __('filament.updates.core.current.version') }}
                    </dt>
                    <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                        v{{ $currentVersion }}
                    </dd>
                </div>
                <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ __('filament.updates.core.current.commit') }}
                    </dt>
                    <dd class="mt-1 font-mono text-lg text-gray-900 dark:text-white">
                        {{ Str::limit($currentCommit, 12) }}
                    </dd>
                </div>
            </div>
        </div>

        {{-- Available update section --}}
        @if($latestRelease !== null)
            <div class="rounded-xl border-2 {{ $this->isMajorUpdate() ? 'border-danger-500' : 'border-primary-500' }} bg-white p-6 dark:bg-gray-800">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0">
                        @if($this->isMajorUpdate())
                            <x-heroicon-o-exclamation-triangle class="h-8 w-8 text-danger-500" />
                        @else
                            <x-heroicon-o-arrow-up-circle class="h-8 w-8 text-primary-500" />
                        @endif
                    </div>
                    <div class="flex-1">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                            {{ __('filament.updates.core.available.title') }}
                        </h2>

                        @if($this->isMajorUpdate())
                            <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">
                                {{ __('filament.updates.core.available.major_warning') }}
                            </p>
                        @endif

                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    {{ __('filament.updates.core.available.new_version') }}
                                </dt>
                                <dd class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">
                                    v{{ $latestRelease->version->value() }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    {{ __('filament.updates.core.available.published') }}
                                </dt>
                                <dd class="mt-1 text-gray-900 dark:text-white">
                                    {{ $latestRelease->publishedAt->format('d/m/Y H:i') }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    {{ __('filament.updates.core.available.type') }}
                                </dt>
                                <dd class="mt-1">
                                    @if($latestRelease->isPrerelease)
                                        <span class="inline-flex items-center rounded-full bg-warning-100 px-2.5 py-0.5 text-xs font-medium text-warning-800 dark:bg-warning-900 dark:text-warning-200">
                                            {{ __('filament.updates.core.available.prerelease') }}
                                        </span>
                                    @elseif($this->isMajorUpdate())
                                        <span class="inline-flex items-center rounded-full bg-danger-100 px-2.5 py-0.5 text-xs font-medium text-danger-800 dark:bg-danger-900 dark:text-danger-200">
                                            {{ __('filament.updates.core.available.major') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-success-100 px-2.5 py-0.5 text-xs font-medium text-success-800 dark:bg-success-900 dark:text-success-200">
                                            {{ __('filament.updates.core.available.stable') }}
                                        </span>
                                    @endif
                                </dd>
                            </div>
                        </div>

                        @if($latestRelease->releaseNotes)
                            <div class="mt-4">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    {{ __('filament.updates.core.available.release_notes') }}
                                </dt>
                                <dd class="mt-2 prose prose-sm dark:prose-invert max-w-none rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
                                    {!! Str::markdown($latestRelease->releaseNotes) !!}
                                </dd>
                            </div>
                        @endif

                        <div class="mt-6 rounded-lg bg-gray-50 p-4 dark:bg-gray-900">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-white">
                                {{ __('filament.updates.core.available.instructions_title') }}
                            </h3>
                            <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                <p>{{ __('filament.updates.core.available.instructions_text') }}</p>
                                <pre class="mt-2 overflow-x-auto rounded bg-gray-800 p-3 text-xs text-gray-100">git fetch origin
git checkout tags/v{{ $latestRelease->version->value() }}
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <div class="text-center py-8">
                    <x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-success-500" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('filament.updates.core.available.up_to_date') }}
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        {{ __('filament.updates.core.available.check_hint') }}
                    </p>
                </div>
            </div>
        @endif

        {{-- Update history section --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                {{ __('filament.updates.core.history.title') }}
            </h2>

            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
