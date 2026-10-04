{{-- Notice for pages whose actions need the public site in maintenance mode --}}
<x-filament::section
    icon="heroicon-o-wrench-screwdriver"
    icon-color="warning"
    :heading="$heading"
    :description="$description"
>
    @if($settingsUrl !== null)
        <x-filament::link :href="$settingsUrl" icon="heroicon-m-arrow-right">
            {{ __('filament.maintenance.settings_link') }}
        </x-filament::link>
    @endif
</x-filament::section>
