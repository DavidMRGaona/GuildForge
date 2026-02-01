/**
 * TimePicker Alpine.js component for Filament forms.
 *
 * iOS-style wheel picker with infinite circular scroll.
 * Items are tripled to create seamless wrap-around effect.
 * Arrow keys adjust hours/minutes based on caret position.
 */

// Constants for scroll calculation
const ITEM_HEIGHT = 32;
const CENTER_OFFSET = 64; // 2 items from top = visual center (2 × 32px)

document.addEventListener('alpine:init', () => {
    Alpine.data('timePicker', (config) => ({
        state: config.initialValue || '',
        statePath: config.statePath,
        isOpen: false,
        hours: Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0')),
        minutes: Array.from({ length: 60 }, (_, i) => String(i).padStart(2, '0')),
        scrollTimeouts: { hours: null, minutes: null },
        isAdjusting: { hours: false, minutes: false },

        // Tripled arrays for infinite scroll effect
        get hoursTripled() {
            return [...this.hours, ...this.hours, ...this.hours];
        },

        get minutesTripled() {
            return [...this.minutes, ...this.minutes, ...this.minutes];
        },

        get selectedHour() {
            if (!this.state) return '00';
            const parts = String(this.state).split(':');
            return parts[0]?.padStart(2, '0') || '00';
        },

        get selectedMinute() {
            if (!this.state) return '00';
            const parts = String(this.state).split(':');
            return parts[1]?.padStart(2, '0') || '00';
        },

        init() {
            this.$watch('isOpen', (open) => {
                if (open) {
                    this.$nextTick(() => this.scrollToSelected());
                }
            });
        },

        handleScroll(type) {
            // Skip if we're programmatically adjusting scroll
            if (this.isAdjusting[type]) return;

            clearTimeout(this.scrollTimeouts[type]);
            this.scrollTimeouts[type] = setTimeout(() => {
                this.processScroll(type);
            }, 100);
        },

        getValueAtIndicator(type) {
            const container = type === 'hours'
                ? this.$refs.hoursWheel
                : this.$refs.minutesWheel;

            if (!container) return null;

            const items = type === 'hours' ? this.hours : this.minutes;
            const itemCount = items.length;
            const scrollTop = container.scrollTop;

            // The item at the indicator (centerOffset from top of visible area)
            const tripledIndex = Math.round((scrollTop + CENTER_OFFSET) / ITEM_HEIGHT);
            const actualIndex = ((tripledIndex % itemCount) + itemCount) % itemCount;

            return items[actualIndex];
        },

        processScroll(type) {
            const container = type === 'hours'
                ? this.$refs.hoursWheel
                : this.$refs.minutesWheel;

            if (!container) return;

            const items = type === 'hours' ? this.hours : this.minutes;
            const itemCount = items.length;
            const totalHeight = itemCount * ITEM_HEIGHT;
            const middleSetStart = totalHeight;

            const scrollTop = container.scrollTop;
            const value = this.getValueAtIndicator(type);
            const actualIndex = items.indexOf(value);

            // Update state only if value changed
            if (type === 'hours' && value !== this.selectedHour) {
                this.state = value + ':' + this.selectedMinute;
                this.syncState();
            } else if (type === 'minutes' && value !== this.selectedMinute) {
                this.state = this.selectedHour + ':' + value;
                this.syncState();
            }

            // Calculate target scroll position for snap
            let targetScrollTop = middleSetStart + (actualIndex * ITEM_HEIGHT) - CENTER_OFFSET;

            // Wrap around: if scrolled into first or last set, jump to middle set first
            const minScroll = totalHeight * 0.3;
            const maxScroll = totalHeight * 1.7;
            if (scrollTop < minScroll || scrollTop > maxScroll) {
                // Instant jump to middle set, then snap
                this.isAdjusting[type] = true;
                container.scrollTop = targetScrollTop;
                setTimeout(() => {
                    this.isAdjusting[type] = false;
                }, 150);
            } else {
                // Smooth snap to nearest item
                this.isAdjusting[type] = true;
                container.scrollTo({
                    top: targetScrollTop,
                    behavior: 'smooth'
                });
                setTimeout(() => {
                    this.isAdjusting[type] = false;
                }, 350);
            }
        },

        scrollToSelected() {
            this.$nextTick(() => {
                const hourIndex = this.hours.indexOf(this.selectedHour);
                const minuteIndex = this.minutes.indexOf(this.selectedMinute);
                const middleSetStart = this.hours.length * ITEM_HEIGHT;
                const minuteMiddleSetStart = this.minutes.length * ITEM_HEIGHT;

                // Position so selected item is at the indicator (centerOffset from top)
                if (this.$refs.hoursWheel && hourIndex >= 0) {
                    this.$refs.hoursWheel.scrollTop = middleSetStart + (hourIndex * ITEM_HEIGHT) - CENTER_OFFSET;
                }
                if (this.$refs.minutesWheel && minuteIndex >= 0) {
                    this.$refs.minutesWheel.scrollTop = minuteMiddleSetStart + (minuteIndex * ITEM_HEIGHT) - CENTER_OFFSET;
                }

                // Unblock scroll processing after scroll stabilizes
                setTimeout(() => {
                    this.isAdjusting.hours = false;
                    this.isAdjusting.minutes = false;
                }, 50);
            });
        },

        selectHour(hour) {
            this.state = hour + ':' + this.selectedMinute;
            this.syncState();

            const index = this.hours.indexOf(hour);
            const middleSetStart = this.hours.length * ITEM_HEIGHT;
            if (this.$refs.hoursWheel && index >= 0) {
                this.isAdjusting.hours = true;
                this.$refs.hoursWheel.scrollTo({
                    top: middleSetStart + (index * ITEM_HEIGHT) - CENTER_OFFSET,
                    behavior: 'smooth'
                });
                setTimeout(() => {
                    this.isAdjusting.hours = false;
                }, 350);
            }
        },

        selectMinute(minute) {
            this.state = this.selectedHour + ':' + minute;
            this.syncState();

            const index = this.minutes.indexOf(minute);
            const middleSetStart = this.minutes.length * ITEM_HEIGHT;
            if (this.$refs.minutesWheel && index >= 0) {
                this.isAdjusting.minutes = true;
                this.$refs.minutesWheel.scrollTo({
                    top: middleSetStart + (index * ITEM_HEIGHT) - CENTER_OFFSET,
                    behavior: 'smooth'
                });
                setTimeout(() => {
                    this.isAdjusting.minutes = false;
                }, 350);
            }
            this.isOpen = false;
        },

        toggleDropdown() {
            if (!this.isOpen) {
                // Block scroll processing BEFORE opening
                this.isAdjusting.hours = true;
                this.isAdjusting.minutes = true;
            }
            this.isOpen = !this.isOpen;
        },

        openDropdown() {
            if (!this.isOpen) {
                // Block scroll processing BEFORE opening
                this.isAdjusting.hours = true;
                this.isAdjusting.minutes = true;
                this.isOpen = true;
            }
        },

        handleFocus(event) {
            event.target.select();
            this.openDropdown();
        },

        handleInput(event) {
            const value = this.state;
            if (/^\d{2}$/.test(value)) {
                this.state = value + ':';
                this.$nextTick(() => {
                    const input = event.target;
                    input.selectionStart = input.selectionEnd = input.value.length;
                });
            }
        },

        /**
         * Handle arrow key navigation based on caret position.
         * - Caret before ":" (positions 0-2): adjust hours
         * - Caret after ":" (positions 3+): adjust minutes
         */
        handleArrowKey(event) {
            if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return;

            // Ensure we have a valid time format first
            this.validateAndFormat();

            const input = event.target;
            const caretPos = input.selectionStart;
            const colonPos = this.state.indexOf(':');

            // Determine if we're adjusting hours or minutes based on caret position
            const adjustHours = colonPos === -1 || caretPos <= colonPos;
            const delta = event.key === 'ArrowUp' ? 1 : -1;

            if (adjustHours) {
                let hour = parseInt(this.selectedHour, 10);
                hour = (hour + delta + 24) % 24; // Wrap around 0-23
                this.state = String(hour).padStart(2, '0') + ':' + this.selectedMinute;
            } else {
                let minute = parseInt(this.selectedMinute, 10);
                minute = (minute + delta + 60) % 60; // Wrap around 0-59
                this.state = this.selectedHour + ':' + String(minute).padStart(2, '0');
            }

            this.syncState();

            // Restore caret position
            this.$nextTick(() => {
                input.selectionStart = input.selectionEnd = caretPos;
            });

            // Update wheel if dropdown is open
            if (this.isOpen) {
                this.scrollToSelected();
            }

            event.preventDefault();
        },

        closeDropdown() {
            this.isOpen = false;
        },

        syncState() {
            this.$wire.set(this.statePath, this.state);
        },

        validateAndFormat() {
            if (!this.state) return;

            const value = String(this.state).trim();
            const match = value.match(/^(\d{1,2}):?(\d{0,2})$/);

            if (match) {
                let hour = parseInt(match[1], 10);
                let minute = parseInt(match[2] || '0', 10);

                hour = Math.min(23, Math.max(0, hour));
                minute = Math.min(59, Math.max(0, minute));

                this.state = String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
            } else if (/^\d{1,2}$/.test(value)) {
                let hour = parseInt(value, 10);
                hour = Math.min(23, Math.max(0, hour));
                this.state = String(hour).padStart(2, '0') + ':00';
            }
            this.syncState();
        }
    }));
});
