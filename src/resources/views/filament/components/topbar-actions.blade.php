{{-- Top bar actions after the global search. Tailwind utilities here are compiled by the panel theme --}}
@props(['createGroups', 'helpUrl', 'siteUrl', 'notifications' => null])

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

        @if($notifications !== null)
            {{-- The site's bell (core#687): the unread count, asked again like the site does without
                 real time (every minute while visible, every five while hidden, on return), and a link
                 to the «Avisos» page, where the notices are read, hidden or marked --}}
            @php($bellLabel = $notifications['count'] > 0 ? str_replace(':count', (string) $notifications['count'], $notifications['labelUnread']) : $notifications['label'])
            <a
                href="{{ $notifications['url'] }}"
                aria-label="{{ $bellLabel }}"
                title="{{ $bellLabel }}"
                x-bind:aria-label="label()"
                x-bind:title="label()"
                class="relative flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 outline-none transition duration-75 hover:text-gray-500 focus-visible:ring-2 focus-visible:ring-primary-600 dark:text-gray-500 dark:hover:text-gray-400 dark:focus-visible:ring-primary-500"
                data-panel-notifications-bell
                data-count-url="{{ $notifications['countUrl'] }}"
                data-label="{{ $notifications['label'] }}"
                data-label-unread="{{ $notifications['labelUnread'] }}"
                x-data="{
                    count: {{ $notifications['count'] }},
                    askedAt: Date.now(),
                    timer: null,
                    onVisible: null,
                    badge() {
                        return this.count > 9 ? '9+' : String(this.count)
                    },
                    label() {
                        return this.count > 0
                            ? this.$root.dataset.labelUnread.replace(':count', this.count)
                            : this.$root.dataset.label
                    },
                    async refresh() {
                        this.askedAt = Date.now()
                        try {
                            const response = await fetch(this.$root.dataset.countUrl, {
                                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                                credentials: 'same-origin',
                            })
                            if (! response.ok) return
                            const unread = (await response.json()).unread
                            if (Number.isInteger(unread) && unread >= 0) this.count = unread
                        } catch (error) {
                            // Offline or signed out elsewhere: keep the last count
                        }
                    },
                    init() {
                        this.timer = setInterval(() => {
                            if (document.visibilityState !== 'hidden' || Date.now() - this.askedAt >= 300000) {
                                this.refresh()
                            }
                        }, 60000)
                        this.onVisible = () => {
                            if (document.visibilityState === 'visible') this.refresh()
                        }
                        document.addEventListener('visibilitychange', this.onVisible)
                    },
                    destroy() {
                        clearInterval(this.timer)
                        document.removeEventListener('visibilitychange', this.onVisible)
                    },
                }"
            >
                <x-filament::icon icon="heroicon-o-bell" class="h-6 w-6" />
                <span
                    data-panel-notifications-badge
                    @if($notifications['count'] === 0) hidden @endif
                    x-bind:hidden="count === 0"
                    x-text="badge()"
                    aria-hidden="true"
                    class="absolute -end-0.5 -top-0.5 flex h-[1.125rem] min-w-[1.125rem] items-center justify-center rounded-full bg-primary-600 px-1 text-[0.6875rem] font-semibold leading-none text-white dark:bg-primary-500"
                >{{ $notifications['badge'] }}</span>
            </a>
        @endif

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
