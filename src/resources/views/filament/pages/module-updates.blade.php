<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Available updates section --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                {{ __('filament.updates.modules.available.title') }}
            </h2>

            @if($availableUpdates->isEmpty())
                <div class="mt-4 text-center py-8">
                    <x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-success-500" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('filament.updates.modules.available.empty') }}
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        {{ __('filament.updates.modules.available.check_hint') }}
                    </p>
                </div>
            @else
                <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ __('filament.updates.modules.available.module') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ __('filament.updates.modules.available.current') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ __('filament.updates.modules.available.available') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ __('filament.updates.modules.available.published') }}
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ __('filament.updates.modules.available.type') }}
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ __('filament.updates.modules.available.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            @foreach($availableUpdates as $update)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $update->moduleName }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                        v{{ $update->currentVersion }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                        v{{ $update->availableVersion }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $update->publishedAt->format('d/m/Y') }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                                        @if($update->isMajorUpdate)
                                            <span class="inline-flex items-center rounded-full bg-danger-100 px-2.5 py-0.5 text-xs font-medium text-danger-800 dark:bg-danger-900 dark:text-danger-200">
                                                {{ __('filament.updates.modules.available.major') }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-success-100 px-2.5 py-0.5 text-xs font-medium text-success-800 dark:bg-success-900 dark:text-success-200">
                                                {{ __('filament.updates.modules.available.minor') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm">
                                        <div class="flex items-center justify-end gap-2">
                                            <button
                                                type="button"
                                                wire:click="previewUpdate('{{ $update->moduleName }}')"
                                                class="inline-flex items-center rounded-md bg-white px-2.5 py-1.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-white dark:ring-gray-600 dark:hover:bg-gray-600"
                                            >
                                                <x-heroicon-m-eye class="h-4 w-4 mr-1" />
                                                {{ __('filament.updates.modules.available.preview') }}
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="updateModule('{{ $update->moduleName }}')"
                                                wire:loading.attr="disabled"
                                                @if($isUpdating) disabled @endif
                                                class="inline-flex items-center rounded-md bg-primary-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                            >
                                                @if($isUpdating && $updatingModule === $update->moduleName)
                                                    <x-heroicon-m-arrow-path class="h-4 w-4 mr-1 animate-spin" />
                                                    {{ __('filament.updates.modules.available.updating') }}
                                                @else
                                                    <x-heroicon-m-arrow-down-tray class="h-4 w-4 mr-1" />
                                                    {{ __('filament.updates.modules.available.update') }}
                                                @endif
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Update history section --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                {{ __('filament.updates.modules.history.title') }}
            </h2>

            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
