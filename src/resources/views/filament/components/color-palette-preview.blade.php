@php
    use App\Domain\ValueObjects\HexColor;
    use App\Infrastructure\Services\Color\OklchColorPaletteGenerator;

    $generator = new OklchColorPaletteGenerator();

    // Get colors from Livewire component's form data
    $primaryColorValue = $this->data['theme_primary_base_color'] ?? '#D97706';
    $accentColorValue = $this->data['theme_accent_base_color'] ?? '#0EA5E9';

    // Generate palettes
    try {
        $primaryHex = new HexColor($primaryColorValue);
        $accentHex = new HexColor($accentColorValue);

        $primaryPalette = $generator->generate('primary', $primaryHex);
        $accentPalette = $generator->generate('accent', $accentHex);
        $neutralPalette = $generator->generateNeutral('neutral', $accentHex);

        // Detect warnings
        $primaryWarnings = $generator->detectWarnings($primaryHex);
        $accentWarnings = $generator->detectWarnings($accentHex);

        // Calculate optimal text color for buttons based on luminance
        $lightButtonBg = $primaryPalette->shade(600);
        $darkButtonBg = $primaryPalette->shade(500);

        $lightButtonText = $lightButtonBg->relativeLuminance() >= 0.5
            ? $neutralPalette->shade(900)
            : new HexColor('#FFFFFF');

        $darkButtonText = $darkButtonBg->relativeLuminance() >= 0.5
            ? $neutralPalette->shade(900)
            : new HexColor('#FFFFFF');

        $hasError = false;
    } catch (\Exception $e) {
        $hasError = true;
        $errorMessage = $e->getMessage();
    }

    $shadeLabels = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];
@endphp

<div class="space-y-6">
    @if($hasError)
        <div class="p-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
            <p class="text-sm text-red-700 dark:text-red-300">
                {{ __('Error al generar la paleta') }}: {{ $errorMessage ?? 'Error desconocido' }}
            </p>
        </div>
    @else
        {{-- Warnings --}}
        @if(!empty($primaryWarnings) || !empty($accentWarnings))
            <div class="p-4 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5"/>
                    <div class="space-y-1">
                        @foreach($primaryWarnings as $warning)
                            <p class="text-sm text-amber-700 dark:text-amber-300">
                                <strong>{{ __('Primario') }}:</strong> {{ $warning }}
                            </p>
                        @endforeach
                        @foreach($accentWarnings as $warning)
                            <p class="text-sm text-amber-700 dark:text-amber-300">
                                <strong>{{ __('Acento') }}:</strong> {{ $warning }}
                            </p>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Primary palette --}}
        <div>
            <h4 class="text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                {{ __('Paleta primaria') }}
            </h4>
            <div class="flex rounded-lg overflow-hidden shadow-sm">
                @foreach($shadeLabels as $shade)
                    @php
                        $color = $primaryPalette->shade($shade);
                        $isLight = $shade <= 400;
                    @endphp
                    <div
                        class="flex-1 h-16 flex items-end justify-center pb-1 transition-all hover:scale-105 hover:z-10 cursor-default group relative"
                        style="background-color: {{ $color->value }}"
                        title="{{ $color->value }}"
                    >
                        <span class="text-[10px] font-medium {{ $isLight ? 'text-neutral-800' : 'text-white' }} opacity-80 group-hover:opacity-100">
                            {{ $shade }}
                        </span>
                        <span class="absolute inset-x-0 top-1 text-center text-[8px] font-mono {{ $isLight ? 'text-neutral-700' : 'text-white/70' }} opacity-0 group-hover:opacity-100 transition-opacity">
                            {{ $color->value }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Accent palette --}}
        <div>
            <h4 class="text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                {{ __('Paleta de acento') }}
            </h4>
            <div class="flex rounded-lg overflow-hidden shadow-sm">
                @foreach($shadeLabels as $shade)
                    @php
                        $color = $accentPalette->shade($shade);
                        $isLight = $shade <= 400;
                    @endphp
                    <div
                        class="flex-1 h-16 flex items-end justify-center pb-1 transition-all hover:scale-105 hover:z-10 cursor-default group relative"
                        style="background-color: {{ $color->value }}"
                        title="{{ $color->value }}"
                    >
                        <span class="text-[10px] font-medium {{ $isLight ? 'text-neutral-800' : 'text-white' }} opacity-80 group-hover:opacity-100">
                            {{ $shade }}
                        </span>
                        <span class="absolute inset-x-0 top-1 text-center text-[8px] font-mono {{ $isLight ? 'text-neutral-700' : 'text-white/70' }} opacity-0 group-hover:opacity-100 transition-opacity">
                            {{ $color->value }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Neutral palette --}}
        <div>
            <h4 class="text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">
                {{ __('Paleta de neutros') }}
                <span class="text-xs font-normal text-neutral-500 dark:text-neutral-400">
                    ({{ __('derivada del acento') }})
                </span>
            </h4>
            <div class="flex rounded-lg overflow-hidden shadow-sm">
                @foreach($shadeLabels as $shade)
                    @php
                        $color = $neutralPalette->shade($shade);
                        $isLight = $shade <= 400;
                    @endphp
                    <div
                        class="flex-1 h-16 flex items-end justify-center pb-1 transition-all hover:scale-105 hover:z-10 cursor-default group relative"
                        style="background-color: {{ $color->value }}"
                        title="{{ $color->value }}"
                    >
                        <span class="text-[10px] font-medium {{ $isLight ? 'text-neutral-800' : 'text-white' }} opacity-80 group-hover:opacity-100">
                            {{ $shade }}
                        </span>
                        <span class="absolute inset-x-0 top-1 text-center text-[8px] font-mono {{ $isLight ? 'text-neutral-700' : 'text-white/70' }} opacity-0 group-hover:opacity-100 transition-opacity">
                            {{ $color->value }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Preview components --}}
        <div class="pt-4 border-t border-neutral-200 dark:border-neutral-700">
            <h4 class="text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-4">
                {{ __('Vista previa de componentes') }}
            </h4>
            <div class="grid grid-cols-2 gap-4">
                {{-- Light mode preview --}}
                <div class="p-4 rounded-lg" style="background-color: {{ $neutralPalette->shade(50)->value }}">
                    <p class="text-xs text-neutral-500 mb-3">{{ __('Modo claro') }}</p>
                    <div class="space-y-3">
                        <button
                            class="px-4 py-2 rounded-md text-sm font-medium transition-colors"
                            style="background-color: {{ $lightButtonBg->value }}; color: {{ $lightButtonText->value }}"
                        >
                            {{ __('Botón primario') }}
                        </button>
                        <p style="color: {{ $neutralPalette->shade(900)->value }}">
                            {{ __('Texto principal') }}
                        </p>
                        <p class="text-sm" style="color: {{ $neutralPalette->shade(600)->value }}">
                            {{ __('Texto secundario') }}
                        </p>
                        <a href="#" class="text-sm underline" style="color: {{ $primaryPalette->shade(700)->value }}">
                            {{ __('Enlace de ejemplo') }}
                        </a>
                        <span
                            class="inline-block px-2 py-1 rounded text-xs font-medium"
                            style="background-color: {{ $accentPalette->shade(100)->value }}; color: {{ $accentPalette->shade(700)->value }}"
                        >
                            {{ __('Badge') }}
                        </span>
                    </div>
                </div>

                {{-- Dark mode preview --}}
                <div class="p-4 rounded-lg" style="background-color: {{ $neutralPalette->shade(900)->value }}">
                    <p class="text-xs mb-3" style="color: {{ $neutralPalette->shade(400)->value }}">{{ __('Modo oscuro') }}</p>
                    <div class="space-y-3">
                        <button
                            class="px-4 py-2 rounded-md text-sm font-medium transition-colors"
                            style="background-color: {{ $darkButtonBg->value }}; color: {{ $darkButtonText->value }}"
                        >
                            {{ __('Botón primario') }}
                        </button>
                        <p style="color: {{ $neutralPalette->shade(50)->value }}">
                            {{ __('Texto principal') }}
                        </p>
                        <p class="text-sm" style="color: {{ $neutralPalette->shade(300)->value }}">
                            {{ __('Texto secundario') }}
                        </p>
                        <a href="#" class="text-sm underline" style="color: {{ $primaryPalette->shade(400)->value }}">
                            {{ __('Enlace de ejemplo') }}
                        </a>
                        <span
                            class="inline-block px-2 py-1 rounded text-xs font-medium"
                            style="background-color: {{ $accentPalette->shade(900)->value }}; color: {{ $accentPalette->shade(200)->value }}"
                        >
                            {{ __('Badge') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- WCAG Contrast info --}}
        <div class="pt-4 border-t border-neutral-200 dark:border-neutral-700">
            <h4 class="text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-3">
                {{ __('Contraste WCAG') }}
            </h4>
            @php
                $lightBg = $neutralPalette->shade(50);
                $darkBg = $neutralPalette->shade(950);
                $primary600 = $primaryPalette->shade(600);
                $primary500 = $primaryPalette->shade(500);

                $lightContrast = round($primary600->contrastRatio($lightBg), 2);
                $darkContrast = round($primary500->contrastRatio($darkBg), 2);

                $lightMeetsAA = $lightContrast >= 4.5;
                $lightMeetsAALarge = $lightContrast >= 3.0;
                $darkMeetsAA = $darkContrast >= 4.5;
                $darkMeetsAALarge = $darkContrast >= 3.0;
            @endphp
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="flex items-center gap-2">
                    <span class="text-neutral-600 dark:text-neutral-400">{{ __('Modo claro') }}:</span>
                    <span class="font-mono">{{ $lightContrast }}:1</span>
                    @if($lightMeetsAA)
                        <span class="px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">AA</span>
                    @elseif($lightMeetsAALarge)
                        <span class="px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">AA Large</span>
                    @else
                        <span class="px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">{{ __('Falla') }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-neutral-600 dark:text-neutral-400">{{ __('Modo oscuro') }}:</span>
                    <span class="font-mono">{{ $darkContrast }}:1</span>
                    @if($darkMeetsAA)
                        <span class="px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">AA</span>
                    @elseif($darkMeetsAALarge)
                        <span class="px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">AA Large</span>
                    @else
                        <span class="px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">{{ __('Falla') }}</span>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
