{{-- Top bar actions after the global search. Tailwind utilities here are compiled by the panel theme --}}
@props(['createGroups', 'helpUrl', 'siteUrl'])

<div class="flex items-center gap-x-4">
    @if($createGroups !== [])
        <div data-panel-create-menu>
            <x-filament::dropdown placement="bottom-end" teleport shift width="2xl">
                <x-slot name="trigger">
                    <x-filament::button size="sm" icon="heroicon-m-plus" labeled-from="sm" :tooltip="__('filament.topbar.create_tooltip')">
                        {{ __('filament.topbar.create') }}
                    </x-filament::button>
                </x-slot>

                {{-- Groups flow into columns, each kept whole, so a short group never leaves a gap beside a
                     long one. Narrow screens get one column, and anything taller than the window scrolls. --}}
                <div data-panel-create-columns class="max-h-[min(70vh,32rem)] overflow-y-auto sm:columns-2 sm:gap-x-0 lg:columns-3">
                    @foreach($createGroups as $group)
                        <div data-panel-create-group class="break-inside-avoid">
                            @if($group['label'] !== null)
                                <x-filament::dropdown.header color="gray">{{ $group['label'] }}</x-filament::dropdown.header>
                            @endif

                            <x-filament::dropdown.list>
                                @foreach($group['items'] as $item)
                                    <x-filament::dropdown.list.item tag="a" :href="$item['url']" :icon="$item['icon']">
                                        {{ $item['label'] }}
                                    </x-filament::dropdown.list.item>
                                @endforeach
                            </x-filament::dropdown.list>
                        </div>
                    @endforeach
                </div>
            </x-filament::dropdown>
        </div>
    @endif

    <div class="flex items-center gap-x-2">
        {{-- On a page with its own guide (the «Ayuda sobre esta pantalla» button carries data-panel-guide-url)
             the link opens that guide; anywhere else, the help home --}}
        <x-filament::icon-button
            tag="a"
            :href="$helpUrl"
            icon="heroicon-o-question-mark-circle"
            icon-size="lg"
            color="gray"
            :label="__('filament.topbar.help')"
            :tooltip="__('filament.topbar.help')"
            data-panel-help-link
            x-data
            x-on:click="const guide = document.querySelector('[data-panel-guide-url]'); if (guide) $el.href = guide.dataset.panelGuideUrl"
            x-on:auxclick="const guide = document.querySelector('[data-panel-guide-url]'); if (guide) $el.href = guide.dataset.panelGuideUrl"
        />

        <x-filament::icon-button
            tag="a"
            :href="$siteUrl"
            target="_blank"
            icon="heroicon-o-globe-alt"
            icon-size="lg"
            color="gray"
            :label="__('filament.topbar.site')"
            :tooltip="__('filament.topbar.site')"
            data-panel-site-link
        />
    </div>
</div>
