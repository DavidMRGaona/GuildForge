{{-- «Ver detalles» (core#399): every column of a table row, as the table draws it (cells are HtmlString) --}}
<dl class="fi-row-details divide-y divide-gray-200 dark:divide-white/10">
    @foreach ($entries as $entry)
        {{-- Cells bring the table's padding: drop it so values line up with their labels --}}
        <div data-row-details-entry="{{ $entry['name'] }}" class="grid grid-cols-3 items-start gap-3 py-2.5 first:pt-0 last:pb-0">
            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $entry['label'] }}</dt>
            <dd class="col-span-2 text-sm text-gray-950 *:p-0! dark:text-white">
                @if ($entry['empty'])
                    <span class="text-gray-400 dark:text-gray-500">—</span>
                @else
                    {{ $entry['html'] }}
                @endif
            </dd>
        </div>
    @endforeach
</dl>
