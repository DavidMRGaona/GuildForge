{{-- Compact reminder in the sticky top bar (core#600): the band at the top scrolls away on long pages, this stays. Same palette trick as impersonation-banner: bg-custom-* reads --color-*. The panel theme scans this folder --}}
<div
    data-impersonation-reminder
    class="flex items-center gap-1 rounded-full bg-custom-600 p-1 text-xs sm:ps-3 font-medium text-white dark:bg-custom-500"
    style="--color-500: var(--{{ $color }}-500); --color-600: var(--{{ $color }}-600);"
>
    <span class="hidden max-w-40 truncate sm:inline">{{ __('filament.impersonation.reminder', ['name' => $name]) }}</span>
    <form method="POST" action="{{ route('impersonation.stop') }}">
        @csrf
        <button
            type="submit"
            class="flex size-6 items-center justify-center rounded-full bg-white/20 hover:bg-white/30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
            aria-label="{{ __('filament.impersonation.banner.stop') }}"
            title="{{ __('filament.impersonation.banner.stop') }}"
        >
            <x-filament::icon icon="heroicon-m-arrow-uturn-left" class="size-4" />
        </button>
    </form>
</div>
