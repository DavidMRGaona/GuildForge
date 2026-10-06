{{-- Filament's compiled CSS has no bg-danger-* utilities: the custom color utilities read --c-* --}}
<div
    role="alert"
    class="flex flex-col items-center gap-y-1 bg-custom-600 px-4 py-2 text-center text-sm font-medium text-white dark:bg-custom-500"
    style="--c-500: var(--danger-500); --c-600: var(--danger-600);"
>
    <span>{{ trans_choice('modules.compatibility.banner', count($modules), ['count' => count($modules)]) }}</span>
    <ul>
        @foreach ($modules as $module)
            <li><span class="font-semibold">{{ $module['display_name'] }}</span>: {{ $module['reasons'] }}</li>
        @endforeach
    </ul>
    @if ($modulesUrl !== null)
        <a href="{{ $modulesUrl }}" class="underline">
            {{ __('modules.compatibility.banner_link') }}
        </a>
    @endif
</div>
