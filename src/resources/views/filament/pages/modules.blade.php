<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters and Search --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            {{-- Filter Tabs --}}
            <div class="flex gap-2">
                <x-filament::button
                    :color="$filter === 'all' ? 'primary' : 'gray'"
                    size="sm"
                    wire:click="setFilter('all')"
                >
                    {{ __('modules.filament.filters.all') }}
                </x-filament::button>
                <x-filament::button
                    :color="$filter === 'enabled' ? 'primary' : 'gray'"
                    size="sm"
                    wire:click="setFilter('enabled')"
                >
                    {{ __('modules.filament.filters.enabled') }}
                </x-filament::button>
                <x-filament::button
                    :color="$filter === 'disabled' ? 'primary' : 'gray'"
                    size="sm"
                    wire:click="setFilter('disabled')"
                >
                    {{ __('modules.filament.filters.disabled') }}
                </x-filament::button>
            </div>

            {{-- Search --}}
            <div class="w-full sm:w-64">
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        :placeholder="__('modules.filament.search.placeholder')"
                    />
                </x-filament::input.wrapper>
            </div>
        </div>

        {{-- Modules Grid --}}
        @php
            $moduleManager = app(\App\Application\Modules\Services\ModuleManagerServiceInterface::class);
            $modules = $this->getModules($moduleManager);
        @endphp

        @if($modules->isEmpty())
            <x-filament::section>
                <div class="flex flex-col items-center justify-center py-8 text-center">
                    <div class="mb-3 rounded-full bg-neutral-100 p-3 dark:bg-neutral-800">
                        <x-heroicon-o-puzzle-piece style="width: 1.5rem; height: 1.5rem;" class="text-neutral-400 dark:text-neutral-500" />
                    </div>
                    <h3 class="text-base font-medium text-neutral-900 dark:text-white">
                        {{ __('modules.filament.empty.title') }}
                    </h3>
                    <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('modules.filament.empty.description') }}
                    </p>
                </div>
            </x-filament::section>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($modules as $module)
                    @php
                        $dependents = $this->getDependents($module->name()->value, $moduleManager);
                        $dependencies = $module->dependencies();
                        $depCheck = $moduleManager->checkDependencies($module->name());
                    @endphp
                    <div wire:key="module-{{ $module->name()->value }}">
                        <x-filament::section
                            class="transition-all duration-200 hover:shadow-md {{ $module->isEnabled() ? 'border-l-4 border-l-green-500 dark:border-l-green-400' : 'border-l-4 border-l-neutral-300 dark:border-l-neutral-600' }}"
                        >
                            {{-- Header: Module Name + Status Badge --}}
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-xl font-semibold text-neutral-900 dark:text-white">
                                        {{ $module->displayName() ?: $module->name()->value }}
                                    </h3>
                                    <p class="mt-1.5 text-xs text-neutral-500 dark:text-neutral-400">
                                        v{{ $module->version()->value() }}
                                        @if($module->author())
                                            <span class="mx-1">•</span>
                                            {{ $module->author() }}
                                        @endif
                                        @if($module->enabledAt() || $module->createdAt())
                                            <span class="mx-1">•</span>
                                            {{ $module->enabledAt()?->format('d/m/Y') ?? $module->createdAt()->format('d/m/Y') }}
                                        @endif
                                    </p>
                                </div>
                                <div class="shrink-0">
                                    @if($module->isEnabled())
                                        <x-filament::badge
                                            color="success"
                                            icon="heroicon-m-check-circle"
                                        >
                                            {{ __('modules.status.enabled') }}
                                        </x-filament::badge>
                                    @else
                                        <x-filament::badge
                                            color="gray"
                                            icon="heroicon-m-minus-circle"
                                        >
                                            {{ __('modules.status.disabled') }}
                                        </x-filament::badge>
                                    @endif
                                </div>
                            </div>

                            {{-- Description --}}
                            @if($module->description())
                                <div class="py-4">
                                    <p class="line-clamp-2 text-sm leading-relaxed text-neutral-600 dark:text-neutral-200">
                                        {{ $module->description() }}
                                    </p>
                                </div>
                            @endif

                            {{-- Dependencies & Required By --}}
                            @if(!empty($dependencies) || !empty($dependents))
                                <div class="mt-5 space-y-2">
                                    @if(!empty($dependencies))
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400">
                                                {{ __('modules.filament.card.dependencies') }}:
                                            </span>
                                            @foreach($dependencies as $dep)
                                                <x-filament::badge size="sm" color="info">
                                                    {{ $dep }}
                                                </x-filament::badge>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if(!empty($dependents))
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400">
                                                {{ __('modules.filament.card.required_by') }}:
                                            </span>
                                            @foreach($dependents as $dependent)
                                                <x-filament::badge size="sm" color="warning">
                                                    {{ $dependent->name()->value }}
                                                </x-filament::badge>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- Dependency Warning --}}
                            @if($depCheck->hasErrors() && $module->isDisabled())
                                <div class="mt-4 flex items-start gap-2 rounded-lg bg-yellow-50 p-3 text-sm text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-200">
                                    <x-heroicon-m-exclamation-triangle class="shrink-0" style="width: 1.25rem; height: 1.25rem;" />
                                    <div class="space-y-1">
                                        @foreach($depCheck->getErrorMessages() as $error)
                                            <p>{{ $error }}</p>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Actions --}}
                            <div class="mt-4 flex flex-wrap items-center justify-end gap-3 border-t border-neutral-200 pt-4 dark:border-neutral-700">
                                @if($module->isEnabled())
                                    <x-filament::button
                                        color="gray"
                                        tag="a"
                                        :href="\App\Filament\Pages\ModuleSettingsPage::getUrl(['module' => $module->name()->value])"
                                        icon="heroicon-m-cog-6-tooth"
                                    >
                                        {{ __('modules.filament.actions.settings') }}
                                    </x-filament::button>

                                    <x-filament::button
                                        color="warning"
                                        wire:click="disableModule('{{ $module->name()->value }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="disableModule('{{ $module->name()->value }}')"
                                        icon="heroicon-m-pause"
                                    >
                                        <span wire:loading.remove wire:target="disableModule('{{ $module->name()->value }}')">
                                            {{ __('modules.filament.actions.disable') }}
                                        </span>
                                        <span wire:loading wire:target="disableModule('{{ $module->name()->value }}')">
                                            {{ __('modules.filament.actions.disabling') }}
                                        </span>
                                    </x-filament::button>
                                @else
                                    <x-filament::button
                                        color="success"
                                        wire:click="enableModule('{{ $module->name()->value }}')"
                                        wire:loading.attr="disabled"
                                        wire:target="enableModule('{{ $module->name()->value }}')"
                                        :disabled="$depCheck->hasErrors()"
                                        icon="heroicon-m-play"
                                    >
                                        <span wire:loading.remove wire:target="enableModule('{{ $module->name()->value }}')">
                                            {{ __('modules.filament.actions.enable') }}
                                        </span>
                                        <span wire:loading wire:target="enableModule('{{ $module->name()->value }}')">
                                            {{ __('modules.filament.actions.enabling') }}
                                        </span>
                                    </x-filament::button>

                                    <x-filament::button
                                        color="danger"
                                        wire:click="confirmUninstall('{{ $module->name()->value }}')"
                                        icon="heroicon-m-trash"
                                    >
                                        {{ __('modules.filament.actions.uninstall') }}
                                    </x-filament::button>
                                @endif
                            </div>
                        </x-filament::section>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Shared Uninstall Confirmation Modal --}}
        @if($confirmingUninstall)
            @php
                $uninstallModule = $moduleManager->find(new \App\Domain\Modules\ValueObjects\ModuleName($confirmingUninstall));
                $uninstallDisplayName = $uninstallModule?->displayName() ?: $confirmingUninstall;
            @endphp
            <x-filament::modal id="confirm-uninstall">
                <x-slot name="heading">
                    {{ __('modules.filament.confirm.uninstall_title') }}
                </x-slot>

                <x-slot name="description">
                    {{ __('modules.filament.confirm.uninstall_description', ['name' => $uninstallDisplayName]) }}
                </x-slot>

                {{-- Delete data checkbox --}}
                <div class="mt-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input
                            type="checkbox"
                            wire:model="confirmDeleteData"
                            class="fi-checkbox-input rounded border-neutral-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-neutral-600 dark:bg-neutral-700 mt-0.5"
                        />
                        <span class="text-sm">
                            <span class="font-medium text-neutral-700 dark:text-neutral-200">
                                {{ __('modules.filament.confirm.delete_data_label') }}
                            </span>
                            <span class="block text-neutral-500 dark:text-neutral-400 mt-1">
                                {{ __('modules.filament.confirm.delete_data_help') }}
                            </span>
                        </span>
                    </label>
                </div>

                <x-slot name="footerActions">
                    <x-filament::button
                        color="gray"
                        wire:click="cancelUninstall"
                    >
                        {{ __('modules.filament.confirm.uninstall_cancel') }}
                    </x-filament::button>

                    <x-filament::button
                        color="danger"
                        wire:click="uninstallModule"
                        wire:loading.attr="disabled"
                        wire:target="uninstallModule"
                        icon="heroicon-m-trash"
                    >
                        {{ __('modules.filament.confirm.uninstall_confirm') }}
                    </x-filament::button>
                </x-slot>
            </x-filament::modal>
        @endif
    </div>
</x-filament-panels::page>
