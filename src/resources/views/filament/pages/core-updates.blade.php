{{-- Uses Filament components and only utility classes present in Filament's compiled CSS: this panel has no custom theme --}}
<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        <x-filament::section :heading="__('filament.updates.core.deployed.title')">
            <dl class="grid grid-cols-3 gap-4 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('filament.updates.core.deployed.commit') }}</dt>
                    <dd class="font-mono font-medium text-gray-950 dark:text-white">
                        @if($deployedCommit === 'unknown')
                            {{ __('filament.updates.core.deployed.unknown') }}
                        @else
                            <x-filament::link :href="$repositoryUrl.'/commit/'.$deployedCommit" target="_blank">
                                {{ substr($deployedCommit, 0, 7) }}
                            </x-filament::link>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('filament.updates.core.deployed.branch') }}</dt>
                    <dd class="font-mono font-medium text-gray-950 dark:text-white">{{ $branch }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('filament.updates.core.deployed.at') }}</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">{{ $deployedAt ?? '—' }}</dd>
                </div>
            </dl>
        </x-filament::section>

        @if($checkError !== null)
            <x-filament::section
                icon="heroicon-o-exclamation-triangle"
                icon-color="danger"
                :heading="__('filament.updates.core.status.check_failed')"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $checkError }}</p>
            </x-filament::section>
        @elseif($status === null)
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('filament.updates.core.status.never_checked') }}</p>
            </x-filament::section>
        @else
            <x-filament::section
                :icon="$status['behind_by'] === 0 ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle'"
                :icon-color="$status['behind_by'] === 0 ? 'success' : 'warning'"
                :heading="$status['behind_by'] === 0
                    ? __('filament.updates.core.status.up_to_date', ['branch' => $status['branch']])
                    : trans_choice('filament.updates.core.status.behind', $status['behind_by'], ['count' => $status['behind_by'], 'branch' => $status['branch']])"
                :description="__('filament.updates.core.status.checked_at', ['date' => \Illuminate\Support\Carbon::parse($checkedAt)->format('d/m/Y H:i')])"
            >
                <div class="flex flex-col gap-4 text-sm">
                    @if($status['diverged'])
                        <p class="text-danger-600 dark:text-danger-400">{{ __('filament.updates.core.status.diverged', ['branch' => $status['branch']]) }}</p>
                    @endif

                    @if($status['behind_by'] > 0)
                        <p class="text-gray-500 dark:text-gray-400">{{ __('filament.updates.core.status.behind_hint') }}</p>

                        @unless($this->isMaintenanceModeEnabled())
                            <p class="flex items-center gap-2 font-medium text-warning-600 dark:text-warning-400">
                                <x-heroicon-m-wrench-screwdriver style="width: 1.25rem; height: 1.25rem;" />
                                {{ __('filament.updates.core.status.maintenance_warning', ['branch' => $status['branch']]) }}
                            </p>
                        @endunless

                        <ul class="flex flex-col gap-2">
                            @foreach($status['commits'] as $commit)
                                <li class="flex items-center gap-3">
                                    <x-filament::link :href="$commit['url']" target="_blank" class="font-mono">
                                        {{ substr($commit['sha'], 0, 7) }}
                                    </x-filament::link>
                                    <span class="text-gray-950 dark:text-white">{{ $commit['message'] }}</span>
                                    @if($commit['date'] !== null)
                                        <span class="text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Carbon::parse($commit['date'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        @if(count($status['commits']) < $status['behind_by'])
                            <x-filament::link :href="$repositoryUrl.'/compare/'.$status['deployed_commit'].'...'.$status['branch']" target="_blank">
                                {{ __('filament.updates.core.status.view_all') }}
                            </x-filament::link>
                        @endif
                    @endif
                </div>
            </x-filament::section>
        @endif

        {{ $this->table }}
    </div>
</x-filament-panels::page>
