{{-- bg-custom-* reads --color-*: the inline style points it at the info palette (read only) or the warning one (simulate, the actions run and are undone). The panel theme scans this folder; ImpersonationPanelBannerTest pins the inline style --}}
<div
    role="status"
    class="flex flex-wrap items-center justify-center gap-x-3 gap-y-2 bg-custom-600 px-4 py-2 text-center text-sm font-medium text-white dark:bg-custom-500"
    style="--color-500: var(--{{ $color }}-500); --color-600: var(--{{ $color }}-600);"
>
    <span class="font-semibold">{{ __('filament.impersonation.banner.viewing_as', ['name' => $name]) }}</span>
    <span class="basis-full sm:basis-auto">{{ $modeText }}</span>
    <form method="POST" action="{{ route('impersonation.stop') }}">
        @csrf
        <button type="submit" class="rounded-md bg-white/20 px-3 py-1 font-semibold underline hover:bg-white/30">
            {{ __('filament.impersonation.banner.stop') }}
        </button>
    </form>
</div>
