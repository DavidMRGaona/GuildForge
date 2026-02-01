@php
    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
    $initialValue = $getState() ?? '';
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="timePicker({ initialValue: @js($initialValue), statePath: @js($statePath) })"
        x-on:click.outside="closeDropdown()"
        x-on:keydown.escape.window="closeDropdown()"
        class="relative"
        wire:ignore
    >
        {{-- Input with clock button --}}
        <div class="time-picker-input-wrapper flex rounded-lg shadow-sm ring-1 transition duration-75 bg-white dark:bg-white/5 ring-gray-950/10 dark:ring-white/20">
            <input
                type="text"
                x-model="state"
                x-on:input="handleInput($event)"
                x-on:focus="handleFocus($event)"
                x-on:blur="validateAndFormat()"
                x-on:keydown.enter.prevent="validateAndFormat(); closeDropdown()"
                x-on:keydown.tab="closeDropdown()"
                x-on:keydown.up.prevent="handleArrowKey($event)"
                x-on:keydown.down.prevent="handleArrowKey($event)"
                class="fi-input block w-full border-none bg-transparent py-1.5 px-3 text-base text-gray-950 outline-none placeholder:text-gray-400 focus:ring-0 disabled:text-gray-500 disabled:[-webkit-text-fill-color:theme(colors.gray.500)] sm:text-sm sm:leading-6 dark:text-white dark:placeholder:text-gray-500"
                placeholder="HH:MM"
                autocomplete="off"
                @disabled($isDisabled)
            />
            <button
                type="button"
                x-on:click="toggleDropdown()"
                class="flex items-center justify-center px-3 text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400"
                tabindex="-1"
                @disabled($isDisabled)
            >
                <x-heroicon-m-clock class="h-5 w-5" />
            </button>
        </div>

        {{-- Dropdown with wheel picker --}}
        <div
            x-show="isOpen"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 mt-1 w-full rounded-lg bg-white shadow-lg ring-1 ring-gray-950/10 dark:bg-gray-800 dark:ring-white/20 overflow-hidden"
            tabindex="-1"
            x-cloak
        >
            {{-- Header with background --}}
            <div class="time-wheel-header flex border-b border-gray-200 dark:border-gray-700">
                <div class="flex-1 px-3 py-2 text-center text-xs font-medium text-gray-600 dark:text-gray-300">
                    {{ __('Hora') }}
                </div>
                <div class="flex-1 px-3 py-2 text-center text-xs font-medium text-gray-600 dark:text-gray-300">
                    {{ __('Minuto') }}
                </div>
            </div>

            {{-- Wheel picker columns --}}
            <div class="flex">
                {{-- Hours column --}}
                <div class="time-wheel-container flex-1 border-r border-gray-200 dark:border-gray-700">
                    {{-- Selection indicator --}}
                    <div class="time-wheel-indicator"></div>

                    {{-- Scrollable wheel (tripled for infinite scroll) --}}
                    <div
                        x-ref="hoursWheel"
                        x-on:scroll="handleScroll('hours')"
                        class="time-wheel"
                        tabindex="-1"
                    >
                        <template x-for="(hour, index) in hoursTripled" :key="'h-' + index">
                            <button
                                type="button"
                                tabindex="-1"
                                x-on:click="selectHour(hour)"
                                x-text="hour"
                                class="time-wheel-item"
                            ></button>
                        </template>
                    </div>
                </div>

                {{-- Minutes column --}}
                <div class="time-wheel-container flex-1">
                    {{-- Selection indicator --}}
                    <div class="time-wheel-indicator"></div>

                    {{-- Scrollable wheel (tripled for infinite scroll) --}}
                    <div
                        x-ref="minutesWheel"
                        x-on:scroll="handleScroll('minutes')"
                        class="time-wheel"
                        tabindex="-1"
                    >
                        <template x-for="(minute, index) in minutesTripled" :key="'m-' + index">
                            <button
                                type="button"
                                tabindex="-1"
                                x-on:click="selectMinute(minute)"
                                x-text="minute"
                                class="time-wheel-item"
                            ></button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>
