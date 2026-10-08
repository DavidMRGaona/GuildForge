{{-- Filament's compiled CSS has no bg-warning-* utilities: the custom color utilities read --color-* --}}
<div
    role="status"
    class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-custom-600 px-4 py-2 text-center text-sm font-medium text-white dark:bg-custom-500"
    style="--color-500: var(--warning-500); --color-600: var(--warning-600);"
>
    <span>{{ __('filament.maintenance.banner') }}</span>
    @if ($settingsUrl !== null)
        <a href="{{ $settingsUrl }}" class="underline">
            {{ __('filament.maintenance.banner_link') }}
        </a>
    @endif
</div>
