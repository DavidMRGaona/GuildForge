{{-- Tailwind utilities here are compiled by the panel theme (resources/css/filament/admin/theme.css), which scans resources/views/filament --}}
<x-filament-panels::page>
    @if($this->foldsPanelSidebar())
        {{-- Only isOpen changes, not the saved isOpenDesktop: the next page opens the sidebar
             as it was left. 1024px is the desktop breakpoint of Filament's sidebar store, which
             copies isOpenDesktop back on livewire:navigated (fired on the first load too). --}}
        <div
            hidden
            wire:ignore
            data-folds-panel-sidebar
            x-data
            x-init="
                const fold = () => { if (window.innerWidth >= 1024) $store.sidebar.isOpen = false }
                fold()
                document.addEventListener('livewire:navigated', fold, { once: true })
            "
        ></div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
        {{-- Below lg the index folds behind a bar that says where you are, so the guide is not pushed
             under a list that grows with every guide. Choosing a guide folds it; the page then scrolls
             to the guide (KnowledgeBasePage::scrollToContent()). Rules in dark mode use the site's
             border colour (gray-700): white at 10% is lost on the dark background. --}}
        <aside
            class="flex flex-col gap-4 lg:border-e lg:border-gray-950/10 lg:pe-6 dark:lg:border-gray-700"
            x-data="{ indexOpen: false }"
        >
            <form wire:submit="submitSearch" role="search">
                <label for="kb-search" class="sr-only">{{ __('knowledge.panel.search_label') }}</label>
                <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                    <x-filament::input
                        id="kb-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        {{-- The results are what comes next, not the index above them --}}
                        x-on:input="indexOpen = false"
                        :placeholder="__('knowledge.panel.search_placeholder')"
                        autocomplete="off"
                    />
                </x-filament::input.wrapper>
            </form>

            <button
                type="button"
                data-kb-index-toggle
                aria-controls="kb-index"
                aria-expanded="false"
                x-bind:aria-expanded="indexOpen"
                x-on:click="indexOpen = ! indexOpen"
                class="flex w-full items-center gap-2 rounded-lg bg-white px-3 py-2 text-start text-sm shadow-xs ring-1 ring-gray-950/10 lg:hidden dark:bg-white/5 dark:ring-white/20"
            >
                <x-filament::icon icon="heroicon-o-book-open" class="size-5 shrink-0 text-gray-400 dark:text-gray-500" />
                <span class="min-w-0 flex-1 truncate">
                    <span class="font-semibold text-gray-950 dark:text-white">{{ __('knowledge.panel.index') }}</span>
                    @if($selectedGuide !== null)
                        <span class="text-gray-500 dark:text-gray-400">· {{ $selectedGuide->category->label }} › {{ $selectedGuide->title }}</span>
                    @endif
                </span>
                <x-filament::icon
                    icon="heroicon-m-chevron-down"
                    class="size-5 shrink-0 text-gray-400 transition-transform dark:text-gray-500"
                    x-bind:class="indexOpen && 'rotate-180'"
                />
            </button>

            <nav
                id="kb-index"
                aria-label="{{ __('knowledge.panel.categories') }}"
                class="kb-index flex flex-col gap-5"
                data-open="false"
                x-bind:data-open="indexOpen ? 'true' : 'false'"
                wire:ignore.self
            >
                <button
                    type="button"
                    wire:click="showHome"
                    x-on:click="indexOpen = false"
                    class="text-start text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                >
                    {{ __('knowledge.panel.home') }}
                </button>

                @foreach($categoryPages as $page)
                    <div>
                        <h2 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $page->category->label }}
                        </h2>
                        {{-- Guides hang from a guide line; the open one marks it with the primary colour --}}
                        <ul class="flex flex-col border-s border-gray-950/10 dark:border-gray-700">
                            @foreach($page->guides as $item)
                                <li wire:key="nav-{{ $item->id }}">
                                    <button
                                        type="button"
                                        wire:click="openGuide(@js($item->id))"
                                        x-on:click="indexOpen = false"
                                        @if($selectedGuide !== null && $selectedGuide->id === $item->id) aria-current="page" @endif
                                        @class([
                                            '-ms-px w-full rounded-e-lg border-s-2 py-1.5 ps-3 pe-2 text-start text-sm transition',
                                            'border-primary-600 bg-primary-50 font-semibold text-primary-700 dark:border-primary-400 dark:bg-primary-400/10 dark:text-primary-300' => $selectedGuide !== null && $selectedGuide->id === $item->id,
                                            'border-transparent text-gray-600 hover:border-gray-400 hover:bg-gray-50 hover:text-gray-950 dark:text-gray-400 dark:hover:border-gray-500 dark:hover:bg-white/5 dark:hover:text-white' => $selectedGuide === null || $selectedGuide->id !== $item->id,
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

        <div id="kb-content" class="min-w-0 scroll-mt-24">
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
