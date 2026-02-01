<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center gap-4">
            <div class="flex-shrink-0">
                @if($updatesCount > 0)
                    <div class="relative">
                        <x-heroicon-o-arrow-path class="h-8 w-8 text-primary-500" />
                        <span class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-danger-500 text-xs font-bold text-white">
                            {{ $updatesCount }}
                        </span>
                    </div>
                @else
                    <x-heroicon-o-check-circle class="h-8 w-8 text-success-500" />
                @endif
            </div>
            <div class="flex-1">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ __('filament.updates.widget.title') }}
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    @if($updatesCount > 0)
                        {{ trans_choice('filament.updates.widget.updates_available', $updatesCount, ['count' => $updatesCount]) }}
                    @else
                        {{ __('filament.updates.widget.up_to_date') }}
                    @endif
                </p>
            </div>
            <div class="flex-shrink-0">
                @if($updatesCount > 0)
                    <a
                        href="{{ \App\Filament\Pages\ModuleUpdatesPage::getUrl() }}"
                        class="inline-flex items-center rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500"
                    >
                        {{ __('filament.updates.widget.view') }}
                    </a>
                @else
                    <button
                        type="button"
                        wire:click="refreshCount"
                        class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-white dark:ring-gray-600 dark:hover:bg-gray-600"
                    >
                        <x-heroicon-m-arrow-path class="h-4 w-4 mr-1" wire:loading.class="animate-spin" />
                        {{ __('filament.updates.widget.refresh') }}
                    </button>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
