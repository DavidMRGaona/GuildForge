<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Back Link --}}
        <div>
            <x-filament::link
                :href="\App\Filament\Pages\ModulesPage::getUrl()"
                icon="heroicon-o-arrow-left"
            >
                {{ __('modules.filament.settings_page.back') }}
            </x-filament::link>
        </div>

        {{-- Module Info Card --}}
        @if($moduleDisplayName)
            <x-filament::section>
                <div class="flex items-start gap-4">
                    <div class="flex-1">
                        <h2 class="text-xl font-semibold text-neutral-900 dark:text-white">
                            {{ $moduleDisplayName ?: $module }}
                        </h2>
                        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                            {{ __('modules.filament.card.version') }}: {{ $moduleVersion }}
                            @if($moduleAuthor)
                                | {{ __('modules.filament.card.author') }}: {{ $moduleAuthor }}
                            @endif
                        </p>
                        @if($moduleDescription)
                            <p class="mt-2 text-neutral-600 dark:text-neutral-300">
                                {{ $moduleDescription }}
                            </p>
                        @endif
                    </div>
                    <x-filament::badge color="success">
                        {{ __('modules.status.enabled') }}
                    </x-filament::badge>
                </div>
            </x-filament::section>
        @endif

        {{-- Settings Form --}}
        @if($this->hasSettings())
            <x-filament::section>
                <x-filament-panels::form wire:submit="save">
                    {{ $this->form }}

                    <x-filament-panels::form.actions
                        :actions="$this->getFormActions()"
                    />
                </x-filament-panels::form>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="flex flex-col items-center justify-center py-8 text-center">
                    <div class="mb-3 rounded-full bg-neutral-100 p-3 dark:bg-neutral-800">
                        <x-heroicon-o-cog-6-tooth style="width: 1.5rem; height: 1.5rem;" class="text-neutral-400 dark:text-neutral-500" />
                    </div>
                    <h3 class="text-base font-medium text-neutral-900 dark:text-white">
                        {{ __('modules.filament.settings_page.no_settings') }}
                    </h3>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
