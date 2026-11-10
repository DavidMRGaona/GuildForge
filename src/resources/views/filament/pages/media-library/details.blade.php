{{-- Details of a media library file (slide-over of MediaLibraryPage) --}}
<div class="flex flex-col gap-6" data-media-details="{{ $fileId }}">
    @if ($imageUrl !== null)
        <a href="{{ $imageUrl }}" target="_blank" rel="noopener noreferrer" class="block">
            <img
                src="{{ $imageUrl }}"
                alt=""
                loading="lazy"
                class="max-h-96 w-full rounded-lg bg-gray-100 object-contain dark:bg-white/5"
            />
        </a>
    @endif

    <dl class="divide-y divide-gray-200 dark:divide-white/10">
        @foreach ([
            'status' => null,
            'origin' => $origin,
            'dimensions' => $dimensions,
            'size' => $size,
            'format' => $format,
            'uploaded_at' => $uploadedAt,
            'folder' => $folder,
        ] as $key => $value)
            <div class="grid grid-cols-3 items-start gap-3 py-2.5 first:pt-0 last:pb-0">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('filament.media_library.details.'.$key) }}</dt>
                <dd class="col-span-2 text-sm break-all text-gray-950 dark:text-white">
                    @if ($key === 'status')
                        <x-filament::badge :color="$statusColor" class="w-fit">{{ $statusLabel }}</x-filament::badge>
                    @elseif ($value === null || $value === '')
                        <span class="text-gray-400 dark:text-gray-500">—</span>
                    @else
                        {{ $value }}
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>

    <section class="flex flex-col gap-2">
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('filament.media_library.details.usages') }}</h3>

        @if ($usages === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('filament.media_library.details.no_usages') }}</p>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($usages as $usage)
                    <li class="flex items-start justify-between gap-3 py-2">
                        <div class="min-w-0">
                            <p class="text-sm font-medium break-words text-gray-950 dark:text-white">{{ $usage['title'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $usage['origin'] }}@if ($usage['role'] !== $usage['title']) · {{ $usage['role'] }}@endif</p>
                        </div>
                        @if ($usage['editUrl'] !== null)
                            <a
                                href="{{ $usage['editUrl'] }}"
                                target="_blank"
                                rel="noopener"
                                class="shrink-0 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                            >{{ __('filament.media_library.details.edit') }} ↗</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
