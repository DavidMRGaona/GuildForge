{{-- Uses Filament components and only utility classes present in Filament's compiled CSS: this panel has no custom theme --}}
<x-filament-panels::page>
    @php($maintenanceEnabled = $this->isMaintenanceModeEnabled())
    <div class="flex flex-col gap-6" @if($queuedModules !== []) wire:poll.3s="pollUpdates" @endif>
        @unless($maintenanceEnabled)
            @include('filament.components.maintenance-required', [
                'heading' => __('filament.updates.modules.maintenance_required.title'),
                'description' => __('filament.updates.modules.maintenance_required.description'),
                'settingsUrl' => $this->getMaintenanceSettingsUrl(),
            ])
        @endunless

        @if($checkErrors !== [])
            <x-filament::section
                icon="heroicon-o-exclamation-triangle"
                icon-color="danger"
                :heading="__('filament.updates.modules.check_errors.title')"
            >
                <ul class="flex flex-col gap-1 text-sm">
                    @foreach($checkErrors as $module => $error)
                        <li><span class="font-medium">{{ $module }}</span>: {{ $error }}</li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif

        @if($modulesWithoutSource !== [])
            <x-filament::section
                icon="heroicon-o-link-slash"
                icon-color="warning"
                :heading="__('filament.updates.modules.without_source.title')"
                :description="__('filament.updates.modules.without_source.hint')"
            >
                <div class="flex flex-wrap gap-2">
                    @foreach($modulesWithoutSource as $module)
                        <x-filament::badge color="warning">{{ $module }}</x-filament::badge>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        <x-filament::section :heading="__('filament.updates.modules.available.title')">
            @if($availableUpdates === [])
                <div class="flex flex-col items-center py-8 text-center">
                    <x-heroicon-o-check-circle style="width: 3rem; height: 3rem; color: rgb(var(--success-500));" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('filament.updates.modules.available.empty') }}
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        {{ __('filament.updates.modules.available.check_hint') }}
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm" style="width: 100%;">
                        <thead>
                            <tr class="text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3 font-medium">{{ __('filament.updates.modules.available.module') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('filament.updates.modules.available.current') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('filament.updates.modules.available.available') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('filament.updates.modules.available.published') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('filament.updates.modules.available.type') }}</th>
                                <th class="px-4 py-3 text-end font-medium">{{ __('filament.updates.modules.available.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($availableUpdates as $update)
                                @php($isQueued = in_array($update['module_name'], $queuedModules, true))
                                <tr class="border-t border-gray-200 dark:border-white/10">
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-950 dark:text-white">
                                        {{ $update['module_name'] }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">
                                        v{{ $update['current_version'] }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">
                                        v{{ $update['available_version'] }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-gray-500 dark:text-gray-400">
                                        {{ $update['published_at'] !== null ? \Illuminate\Support\Carbon::parse($update['published_at'])->format('d/m/Y') : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div class="flex">
                                            @if($update['is_major_update'])
                                                <x-filament::badge color="danger">{{ __('filament.updates.modules.available.major') }}</x-filament::badge>
                                            @else
                                                <x-filament::badge color="success">{{ __('filament.updates.modules.available.minor') }}</x-filament::badge>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            <x-filament::button
                                                size="sm"
                                                color="gray"
                                                icon="heroicon-m-eye"
                                                wire:click="mountAction('preview', { module: '{{ $update['module_name'] }}' })"
                                            >
                                                {{ __('filament.updates.modules.available.preview') }}
                                            </x-filament::button>

                                            <x-filament::button
                                                size="sm"
                                                :icon="$isQueued ? 'heroicon-m-arrow-path' : 'heroicon-m-arrow-down-tray'"
                                                :disabled="$isQueued || ! $maintenanceEnabled"
                                                wire:click="updateModule('{{ $update['module_name'] }}')"
                                            >
                                                {{ $isQueued ? __('filament.updates.modules.available.updating') : __('filament.updates.modules.available.update') }}
                                            </x-filament::button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>

        @if($blockedReleases !== [])
            <x-filament::section
                icon="heroicon-o-no-symbol"
                icon-color="danger"
                :heading="__('filament.updates.modules.blocked.heading')"
            >
                {{-- No update button: these releases need a newer core --}}
                <ul class="flex flex-col gap-1 text-sm">
                    @foreach($blockedReleases as $blocked)
                        <li>
                            <span class="font-medium">{{ $blocked['module_name'] }}</span>:
                            {{ __('filament.updates.modules.blocked.line', [
                                'version' => $blocked['blocked_version'],
                                'reasons' => app(\App\View\Modules\CompatibilityIssueFormatter::class)->summary($blocked['issues'], short: true),
                            ]) }}
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif

        <x-filament::section :heading="__('filament.updates.modules.history.title')">
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
