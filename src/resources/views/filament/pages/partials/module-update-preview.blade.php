{{-- Body of the "Ver detalles" modal on the module updates page. Only utility classes present in Filament's compiled CSS --}}
@if($error !== null)
    <div class="flex flex-col gap-1 text-sm">
        <p class="font-medium text-gray-950 dark:text-white">{{ __('filament.updates.modules.preview.failed') }}</p>
        <p class="text-gray-500 dark:text-gray-400">{{ $error }}</p>
    </div>
@else
    <div class="flex flex-col gap-6">
        <dl class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('filament.updates.modules.preview.current') }}</dt>
                <dd class="font-medium text-gray-950 dark:text-white">v{{ $preview['from_version'] }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ __('filament.updates.modules.preview.new') }}</dt>
                <dd class="flex items-center gap-2 font-medium text-gray-950 dark:text-white">
                    v{{ $preview['to_version'] }}
                    @if($preview['is_major_update'])
                        <x-filament::badge color="danger">{{ __('filament.updates.modules.available.major') }}</x-filament::badge>
                    @endif
                </dd>
            </div>
        </dl>

        @if($preview['is_major_update'])
            <p class="text-sm text-danger-600 dark:text-danger-400">{{ __('filament.updates.modules.preview.major_warning') }}</p>
        @endif

        <div class="flex flex-col gap-1 text-sm">
            <h3 class="font-medium text-gray-950 dark:text-white">{{ __('filament.updates.modules.preview.compatibility') }}</h3>
            @if($preview['core_compatible'])
                <p class="text-gray-950 dark:text-white">{{ __('filament.updates.modules.preview.compatible') }}</p>
            @else
                <p class="font-medium text-danger-600 dark:text-danger-400">{{ __('filament.updates.modules.preview.incompatible') }}</p>
            @endif
            @if($preview['core_requirement'] !== null)
                <p class="text-gray-500 dark:text-gray-400">{{ __('filament.updates.modules.preview.core_requirement', ['constraint' => $preview['core_requirement']]) }}</p>
            @endif
            @foreach($preview['compatibility_reasons'] as $reason)
                <p class="text-danger-600 dark:text-danger-400">{{ $reason }}</p>
            @endforeach
        </div>

        <div class="flex flex-col gap-2">
            <h3 class="text-sm font-medium text-gray-950 dark:text-white">{{ __('filament.updates.modules.preview.changelog') }}</h3>

            @if($preview['changelog_html'] !== null)
                {{-- Rendered with html_input=strip and no unsafe links --}}
                <div class="prose prose-sm max-w-none dark:prose-invert">{!! $preview['changelog_html'] !!}</div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('filament.updates.modules.preview.no_changelog') }}</p>
            @endif
        </div>
    </div>
@endif
