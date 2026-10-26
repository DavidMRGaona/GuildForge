{{--
    Permission search of the role editor. Browser only: nothing here is bound to Livewire, so the
    query is never sent, validated or saved, and wire:ignore keeps the input, its state and the
    generated stylesheet through every re-render of the form.

    Filtering is done with a stylesheet built from the search index (one entry per option: its
    permission id and the text shown for it), not by touching Filament's markup: Livewire puts that
    markup back on every update, a stylesheet keeps applying to it. It relies on:
      - [data-permission-tabs]                       the Tabs container (RoleResource)
      - [data-permission-section="<tab>.<section>"]  each section's fieldset (RoleResource)
      - button[data-tab-key="permission-tab-<tab>"]  each tab button (Filament, aria-selected marks the open one)
      - .fi-fo-checkbox-list-option-ctn with its input[value="<permission id>"]  each option (Filament)
      - .fi-fo-checkbox-list-actions                 «Marcar todos» / «Desmarcar todos» (Filament)
      - .fi-grid-col > .fi-sc-component > fieldset   the grid cell around a section (Filament)
--}}
@php
    $texts = [
        'results' => [__('filament.roles.permissionSearch.resultsOne'), __('filament.roles.permissionSearch.resultsMany')],
        'tabMatches' => [__('filament.roles.permissionSearch.tabMatchesOne'), __('filament.roles.permissionSearch.tabMatchesMany')],
    ];
@endphp
<div
    wire:ignore
    data-permission-search
    data-permission-index="{{ json_encode($index, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}"
    data-permission-texts="{{ json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}"
    x-data="{
        query: '',
        active: false,
        total: 0,
        css: '',
        index: [],
        texts: {},
        tabs: null,
        tabBeforeSearch: null,
        autoTab: null,

        init() {
            this.texts = JSON.parse(this.$el.dataset.permissionTexts);
            this.index = JSON.parse(this.$el.dataset.permissionIndex).map((tab) => ({
                tab: tab.tab,
                sections: tab.sections.map((section) => ({
                    key: section.key,
                    options: section.options.map((option) => ({ id: option.id, text: this.normalize(option.text) })),
                })),
            }));
            this.tabs = (this.$el.closest('form') ?? document).querySelector('[data-permission-tabs]');
            this.$watch('query', () => this.apply());
        },

        // Case and accent insensitive
        normalize(value) {
            return String(value).normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase().trim();
        },

        plural(pair, count) {
            return pair[count === 1 ? 0 : 1].replace(':count', count);
        },

        quote(value) {
            return '\'' + String(value).replace(/['\\]/g, '\\$&') + '\'';
        },

        currentTab() {
            return this.tabs?.querySelector('[data-tab-key][aria-selected=true]')?.dataset.tabKey ?? null;
        },

        openTab(key) {
            this.tabs?.querySelector('[data-tab-key=' + this.quote(key) + ']')?.click();
        },

        apply() {
            const needle = this.normalize(this.query);
            const wasActive = this.active;
            this.active = needle !== '';

            if (! this.active) {
                this.css = '';
                this.total = 0;
                // Back to the tab the user had, unless they picked another one while searching
                if (wasActive && this.autoTab !== null && this.currentTab() === this.autoTab && this.tabBeforeSearch !== null) {
                    this.openTab(this.tabBeforeSearch);
                }
                this.autoTab = null;

                return;
            }

            if (! wasActive) {
                this.tabBeforeSearch = this.currentTab();
                this.autoTab = null;
            }

            const scope = '[data-permission-tabs] ';
            // Kept apart: a browser without :has() drops the whole rule it appears in
            const hidden = [];
            const hiddenHas = [];
            const rules = [];
            const counts = {};
            this.total = 0;

            for (const tab of this.index) {
                counts[tab.tab] = 0;
                for (const section of tab.sections) {
                    let shown = 0;
                    for (const option of section.options) {
                        if (option.text.includes(needle)) {
                            shown++;
                        } else {
                            hiddenHas.push(scope + '.fi-fo-checkbox-list-option-ctn:has(input[value=' + this.quote(option.id) + '])');
                        }
                    }
                    if (shown === 0) {
                        const fieldset = '[data-permission-section=' + this.quote(section.key) + ']';
                        hidden.push(scope + fieldset);
                        hiddenHas.push(scope + '.fi-grid-col:has(> .fi-sc-component > ' + fieldset + ')');
                    }
                    counts[tab.tab] += shown;
                }
                this.total += counts[tab.tab];

                const button = scope + '[data-tab-key=' + this.quote(tab.tab) + ']';
                rules.push(counts[tab.tab] > 0
                    ? button + '::after { content: ' + this.quote(this.plural(this.texts.tabMatches, counts[tab.tab])) + '; }'
                    : button + ' { opacity: 0.5; }');
            }

            // Bulk toggles would tick hidden boxes too
            hidden.push(scope + '.fi-fo-checkbox-list-actions');
            this.css = [hidden, hiddenHas]
                .filter((selectors) => selectors.length > 0)
                .map((selectors) => selectors.join(',\n') + ' { display: none !important; }')
                .concat(rules)
                .join('\n');

            const current = this.currentTab();
            if (this.total > 0 && ! counts[current]) {
                this.autoTab = this.index.find((tab) => counts[tab.tab] > 0).tab;
                this.openTab(this.autoTab);
            }
        },
    }"
    class="flex flex-col gap-2"
>
    <style>
        [data-permission-tabs] [data-tab-key]::after {
            border-radius: 0.375rem;
            padding: 0.125rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            line-height: 1rem;
            white-space: nowrap;
            color: var(--primary-700);
            background-color: color-mix(in oklab, var(--primary-500) 12%, transparent);
        }

        .dark [data-permission-tabs] [data-tab-key]::after {
            color: var(--primary-300);
            background-color: color-mix(in oklab, var(--primary-400) 18%, transparent);
        }
    </style>
    <style x-text="css"></style>

    <label for="permission-search-input" class="sr-only">{{ __('filament.roles.permissionSearch.label') }}</label>
    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
        <x-filament::input
            id="permission-search-input"
            type="search"
            autocomplete="off"
            x-model="query"
            {{-- Enter would submit the role form --}}
            x-on:keydown.enter.prevent=""
            :placeholder="__('filament.roles.permissionSearch.placeholder')"
        />
    </x-filament::input.wrapper>

    <div role="status" aria-live="polite" class="text-sm text-gray-500 dark:text-gray-400">
        <p x-cloak x-show="active && total === 0">{{ __('filament.roles.permissionSearch.noResults') }}</p>
        <p x-cloak x-show="active && total > 0" class="sr-only" x-text="plural(texts.results, total)"></p>
    </div>
</div>
