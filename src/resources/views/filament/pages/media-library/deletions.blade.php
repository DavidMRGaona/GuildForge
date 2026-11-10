{{-- Deletions log of the media library (slide-over of MediaLibraryPage); rows are never removed --}}
<div class="flex flex-col gap-4" data-media-deletions>
    @if ($rows === [])
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('filament.media_library.deletions.empty') }}</p>
    @else
        @if (count($rows) >= $limit)
            {{-- Only when older deletions were left out --}}
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ trans_choice('filament.media_library.deletions.limit', $limit, ['count' => $limit]) }}</p>
        @endif

        <ul class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($rows as $row)
                <li class="flex flex-col gap-1 py-2.5 first:pt-0">
                    <p class="text-sm font-medium break-all text-gray-950 dark:text-white">{{ $row['fileId'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $row['origin'] }} · {{ $row['size'] }}@if ($row['dimensions'] !== null) · {{ $row['dimensions'] }}@endif · {{ $row['outcome'] }}@if ($row['automatic']) · {{ __('filament.media_library.deletions.automatic') }}@endif
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        @if ($row['deletedBy'] === null)
                            {{ __('filament.media_library.deletions.by_nobody', ['date' => $row['deletedAt']]) }}
                        @else
                            {{ __('filament.media_library.deletions.by', ['name' => $row['deletedBy'], 'date' => $row['deletedAt']]) }}
                        @endif
                    </p>
                </li>
            @endforeach
        </ul>
    @endif
</div>
