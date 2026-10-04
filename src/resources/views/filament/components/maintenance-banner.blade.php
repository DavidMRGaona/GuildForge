<div role="status" class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-warning-500 px-4 py-2 text-center text-sm font-medium text-white dark:bg-warning-600">
    <span>{{ __('filament.maintenance.banner') }}</span>
    @if ($settingsUrl !== null)
        <a href="{{ $settingsUrl }}" class="underline underline-offset-2 hover:no-underline">
            {{ __('filament.maintenance.banner_link') }}
        </a>
    @endif
</div>
