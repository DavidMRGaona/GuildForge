{{-- Filament components plus Tailwind utilities: the panel theme (resources/css/filament/admin/theme.css) compiles the classes used in resources/views/filament --}}
<x-filament-panels::page>
    @php($stats = $this->getStats())
    <div class="flex flex-col gap-6">
        @unless ($this->isDeletionEnabled())
            <x-filament::section
                icon="heroicon-o-lock-closed"
                icon-color="warning"
                :heading="__('filament.media_library.deletion_disabled.title')"
                :description="__('filament.media_library.deletion_disabled.body')"
                data-media-deletion-disabled
            />
        @endunless

        @if ($stats['lastSyncFailedAt'] !== null)
            <x-filament::section
                icon="heroicon-o-exclamation-triangle"
                icon-color="danger"
                :heading="__('filament.media_library.sync_failed.title')"
                :description="__('filament.media_library.sync_failed.body', ['date' => $stats['lastSyncFailedAt']])"
                data-media-sync-failed
            />
        @endif

        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5" data-media-library-stats>
            @foreach ([
                'files' => $stats['files'],
                'bytes' => $stats['bytes'],
                'unused_files' => $stats['unusedFiles'],
                'unused_bytes' => $stats['unusedBytes'],
                'last_synced' => $stats['lastSyncedAt'],
            ] as $key => $value)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('filament.media_library.stats.'.$key) }}</dt>
                    <dd class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
