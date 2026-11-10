{{-- Grid of the media library picker (MediaLibraryPicker) and the preview of the chosen file --}}
@php
    use Illuminate\Support\Js;

    $statePath = $getStatePath();
    $chosen = $getState();
    $result = $getPage();
    $preview = $getPreview();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="grid min-w-0 gap-4 md:grid-cols-3" data-media-browser>
        <div class="flex min-w-0 flex-col gap-3 md:col-span-2">
            @if ($result['items'] === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('filament.media_picker.empty') }}</p>
            @else
                <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($result['items'] as $item)
                        @php($isChosen = $chosen === $item['fileId'])
                        <li class="min-w-0">
                            <button
                                type="button"
                                data-media-file="{{ $item['fileId'] }}"
                                x-on:click="$wire.set({{ Js::from($statePath) }}, {{ Js::from($item['fileId']) }})"
                                @class([
                                    'flex w-full min-w-0 flex-col gap-1.5 rounded-lg p-1.5 text-start ring-1 transition hover:bg-gray-50 dark:hover:bg-white/5',
                                    'ring-2 ring-primary-600 dark:ring-primary-500' => $isChosen,
                                    'ring-gray-950/10 dark:ring-white/10' => ! $isChosen,
                                ])
                                aria-pressed="{{ $isChosen ? 'true' : 'false' }}"
                            >
                                @if ($item['thumbnailUrl'] !== null)
                                    <img src="{{ $item['thumbnailUrl'] }}" alt="" loading="lazy" class="aspect-square w-full rounded-md bg-gray-100 object-cover dark:bg-white/5" />
                                @else
                                    <span class="aspect-square w-full rounded-md bg-gray-100 dark:bg-white/5"></span>
                                @endif
                                <span class="line-clamp-2 text-xs font-medium break-words text-gray-950 dark:text-white">{{ $item['title'] ?? $item['origin'] }}</span>
                                <span class="flex flex-wrap items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                    @if ($item['title'] !== null)
                                        <span class="truncate">{{ $item['origin'] }}</span>
                                    @endif
                                    <x-filament::badge :color="$item['statusColor']" size="sm">{{ $item['statusLabel'] }}</x-filament::badge>
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>

                @if ($result['pages'] > 1)
                    <nav class="flex items-center justify-between gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <x-filament::icon-button
                            icon="heroicon-m-chevron-left"
                            color="gray"
                            :disabled="$result['page'] <= 1"
                            :label="__('filament.media_picker.previous_page')"
                            x-on:click="$wire.set({{ Js::from($getPageStatePath()) }}, {{ $result['page'] - 1 }})"
                        />
                        <span>{{ __('filament.media_picker.page', ['page' => $result['page'], 'pages' => $result['pages']]) }}</span>
                        <x-filament::icon-button
                            icon="heroicon-m-chevron-right"
                            color="gray"
                            :disabled="$result['page'] >= $result['pages']"
                            :label="__('filament.media_picker.next_page')"
                            x-on:click="$wire.set({{ Js::from($getPageStatePath()) }}, {{ $result['page'] + 1 }})"
                        />
                    </nav>
                @endif
            @endif
        </div>

        <aside class="flex min-w-0 flex-col gap-3 rounded-lg bg-gray-50 p-3 dark:bg-white/5" data-media-preview="{{ $preview['fileId'] ?? '' }}">
            @if ($preview === null)
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('filament.media_picker.no_preview') }}</p>
            @else
                @if ($preview['imageUrl'] !== null)
                    <img src="{{ $preview['imageUrl'] }}" alt="" class="max-h-72 w-full rounded-md bg-gray-100 object-contain dark:bg-white/5" />
                @endif
                <dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                    @foreach (['dimensions' => $preview['dimensions'], 'format' => $preview['format'], 'size' => $preview['size'], 'uploaded_at' => $preview['uploadedAt']] as $key => $value)
                        <dt class="text-gray-500 dark:text-gray-400">{{ __('filament.media_library.details.'.$key) }}</dt>
                        <dd class="text-gray-950 dark:text-white">{{ $value ?? '—' }}</dd>
                    @endforeach
                </dl>
                <section class="flex flex-col gap-1">
                    <h3 class="text-xs font-semibold text-gray-950 dark:text-white">{{ __('filament.media_picker.usages') }}</h3>
                    @if ($preview['usages'] === [])
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('filament.media_picker.no_usages') }}</p>
                    @else
                        <ul class="flex flex-col gap-1">
                            @foreach ($preview['usages'] as $usage)
                                <li class="text-xs break-words text-gray-700 dark:text-gray-300">{{ $usage['origin'] }} · {{ $usage['title'] }} · {{ $usage['role'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
        </aside>
    </div>
</x-dynamic-component>
