{{-- Tailwind utilities here are compiled by the panel theme (resources/css/filament/admin/theme.css), which scans resources/views/filament --}}
<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
        <aside class="flex flex-col gap-4">
            <form wire:submit="submitSearch" role="search">
                <label for="kb-search" class="sr-only">{{ __('knowledge.panel.search_label') }}</label>
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input
                        id="kb-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        :placeholder="__('knowledge.panel.search_placeholder')"
                        autocomplete="off"
                    />
                </x-filament::input.wrapper>
            </form>

            <nav aria-label="{{ __('knowledge.panel.categories') }}" class="flex flex-col gap-4">
                <button
                    type="button"
                    wire:click="showHome"
                    class="text-start text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                >
                    {{ __('knowledge.panel.home') }}
                </button>

                @foreach($categoryPages as $page)
                    <div>
                        <h2 class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ $page->category->label }}
                        </h2>
                        <ul class="flex flex-col">
                            @foreach($page->guides as $item)
                                <li wire:key="nav-{{ $item->id }}">
                                    <button
                                        type="button"
                                        wire:click="openGuide(@js($item->id))"
                                        @if($selectedGuide !== null && $selectedGuide->id === $item->id) aria-current="page" @endif
                                        @class([
                                            'w-full rounded-lg px-2 py-1.5 text-start text-sm transition hover:bg-gray-100 dark:hover:bg-white/5',
                                            'bg-gray-100 font-semibold text-gray-950 dark:bg-white/10 dark:text-white' => $selectedGuide !== null && $selectedGuide->id === $item->id,
                                            'text-gray-700 dark:text-gray-300' => $selectedGuide === null || $selectedGuide->id !== $item->id,
                                        ])
                                    >
                                        {{ $item->title }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>
        </aside>

        <div class="min-w-0">
            @if($hits !== null)
                <x-filament::section :heading="__('knowledge.panel.results')">
                    @if($hits === [])
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('knowledge.panel.no_results') }}</p>
                    @else
                        <ul class="flex flex-col divide-y divide-gray-200 dark:divide-white/10">
                            @foreach($hits as $hit)
                                <li wire:key="hit-{{ $hit->id }}" class="py-3 first:pt-0 last:pb-0">
                                    <button
                                        type="button"
                                        wire:click="openGuide(@js($hit->id))"
                                        class="text-start text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        {{ $hit->title }}
                                    </button>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        @foreach($hit->snippet as $segment)
                                            @if($segment['match'])<mark class="rounded bg-primary-100 px-0.5 text-gray-950 dark:bg-primary-500/30 dark:text-white">{{ $segment['text'] }}</mark>@else{{ $segment['text'] }}@endif
                                        @endforeach
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-filament::section>
            @elseif($unavailable)
                <x-filament::section
                    icon="heroicon-o-exclamation-triangle"
                    icon-color="warning"
                    :heading="__('knowledge.panel.unavailable')"
                >
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('knowledge.panel.unavailable_hint') }}</p>
                </x-filament::section>
            @elseif($selectedGuide !== null)
                <x-filament::section>
                    <x-slot name="heading">
                        {{ $selectedGuide->title }}
                        @if($selectedGuide->badge !== null)
                            <x-filament::badge size="sm" class="ms-2 inline-flex" :color="$selectedGuide->badge === 'new' ? 'success' : 'info'">
                                {{ $selectedGuide->badge === 'new' ? __('knowledge.panel.badge_new') : __('knowledge.panel.badge_updated') }}
                            </x-filament::badge>
                        @endif
                    </x-slot>
                    <x-slot name="description">{{ $selectedGuide->category->label }}</x-slot>

                    <article class="kb-prose">{!! $selectedGuide->html !!}</article>
                </x-filament::section>

                @if($selectedGuide->related !== [])
                    <x-filament::section class="mt-6" :heading="__('knowledge.panel.related')">
                        <ul class="flex flex-col gap-2">
                            @foreach($selectedGuide->related as $related)
                                <li wire:key="related-{{ $related->id }}">
                                    <button
                                        type="button"
                                        wire:click="openGuide(@js($related->id))"
                                        class="text-start text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        {{ $related->title }}
                                    </button>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $related->summary }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </x-filament::section>
                @endif
            @else
                @if($home->categories === [])
                    <x-filament::section>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('knowledge.panel.empty') }}</p>
                    </x-filament::section>
                @else
                    <div class="flex flex-col gap-6">
                        @if($home->featured !== [])
                            <x-filament::section :heading="__('knowledge.panel.featured')">
                                <ul class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    @foreach($home->featured as $item)
                                        <li wire:key="featured-{{ $item->id }}">
                                            <button
                                                type="button"
                                                wire:click="openGuide(@js($item->id))"
                                                class="block w-full rounded-lg p-3 text-start ring-1 ring-gray-950/5 transition hover:bg-gray-50 dark:ring-white/10 dark:hover:bg-white/5"
                                            >
                                                <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $item->title }}</span>
                                                <span class="mt-1 block text-sm text-gray-500 dark:text-gray-400">{{ $item->summary }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            </x-filament::section>
                        @endif

                        <x-filament::section :heading="__('knowledge.panel.categories')">
                            <ul class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                @foreach($categoryPages as $page)
                                    <li wire:key="cat-{{ $page->category->id }}" class="rounded-lg p-3 ring-1 ring-gray-950/5 dark:ring-white/10">
                                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $page->category->label }}</h3>
                                        <ul class="mt-2 flex flex-col gap-1">
                                            @foreach($page->guides as $item)
                                                <li wire:key="cat-guide-{{ $item->id }}">
                                                    <button
                                                        type="button"
                                                        wire:click="openGuide(@js($item->id))"
                                                        class="text-start text-sm text-primary-600 hover:underline dark:text-primary-400"
                                                    >
                                                        {{ $item->title }}
                                                    </button>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </li>
                                @endforeach
                            </ul>
                        </x-filament::section>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-filament-panels::page>
