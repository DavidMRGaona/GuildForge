<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        {{-- Map Preview Section --}}
        @if($this->getLocationLat() && $this->getLocationLng())
        <x-filament::section>
            <x-slot name="heading">
                {{ __('filament.settings.location.preview') }}
            </x-slot>
            <div class="rounded-lg overflow-hidden">
                <iframe
                    src="https://www.openstreetmap.org/export/embed.html?bbox={{ (float)$this->getLocationLng() - 0.01 }}%2C{{ (float)$this->getLocationLat() - 0.01 }}%2C{{ (float)$this->getLocationLng() + 0.01 }}%2C{{ (float)$this->getLocationLat() + 0.01 }}&amp;layer=mapnik&amp;marker={{ $this->getLocationLat() }}%2C{{ $this->getLocationLng() }}"
                    style="border: 1px solid #ccc"
                    class="w-full h-64 rounded-lg"
                    title="Map preview"
                ></iframe>
            </div>
        </x-filament::section>
        @endif

        <x-filament-panels::form.actions
            :actions="$this->getFormActions()"
        />
    </x-filament-panels::form>
</x-filament-panels::page>
