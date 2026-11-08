import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import type { ButtonGroupInfo, ButtonInfo, ToolbarSectionInfo } from '@fullcalendar/vue3';
import { calendarClassOptions, calendarToolbarOptions } from '../calendarTheme';

type ClassGenerator<T> = (info: T) => string;

function button(overrides: Partial<ButtonInfo> = {}): ButtonInfo {
    return {
        name: 'today',
        text: 'Hoy',
        isPrimary: false,
        isSelected: false,
        isDisabled: false,
        isIconOnly: false,
        buttonGroup: null,
        ...overrides,
    };
}

describe('calendarTheme', () => {
    it('imports no stylesheet: as a public API entry its CSS would not reach module pages', () => {
        const source = readFileSync(resolve(import.meta.dirname, '../calendarTheme.ts'), 'utf8');

        expect(source).not.toMatch(/import\s+['"][^'"]+\.css['"]/);
    });

    describe('calendarToolbarOptions', () => {
        it('puts the title with ‹ › and, below, «Hoy» with the view buttons', () => {
            expect(calendarToolbarOptions('listMonth,dayGridMonth')).toEqual({
                headerToolbar: {
                    start: 'title',
                    center: 'today listMonth,dayGridMonth',
                    end: 'prev,next',
                },
                headerToolbarClass: 'calendar-toolbar--responsive',
            });
        });

        it('keeps «Hoy» next to ‹ › when there is no view to choose', () => {
            expect(calendarToolbarOptions('')).toEqual({
                headerToolbar: { start: 'title', center: '', end: 'today prev,next' },
                headerToolbarClass: 'calendar-toolbar--responsive',
            });
        });
    });

    describe('class names for the toolbar', () => {
        it('names the toolbar and each of its sections', () => {
            const sectionClass =
                calendarClassOptions.toolbarSectionClass as ClassGenerator<ToolbarSectionInfo>;

            expect(calendarClassOptions.toolbarClass).toBe('calendar-toolbar');
            expect(sectionClass({ name: 'start' })).toBe(
                'calendar-toolbar-section calendar-toolbar-section--start'
            );
            expect(sectionClass({ name: 'end' })).toBe(
                'calendar-toolbar-section calendar-toolbar-section--end'
            );
        });

        it('tells the group of view buttons from the other groups', () => {
            const groupClass =
                calendarClassOptions.buttonGroupClass as ClassGenerator<ButtonGroupInfo>;

            expect(groupClass({ hasSelection: true })).toBe(
                'calendar-button-group calendar-button-group--views'
            );
            expect(groupClass({ hasSelection: false })).toBe('calendar-button-group');
        });

        it('marks the selected view and the icon-only buttons', () => {
            const buttonClass = calendarClassOptions.buttonClass as ClassGenerator<ButtonInfo>;

            expect(buttonClass(button())).toBe('calendar-button');
            expect(buttonClass(button({ name: 'listMonth', isSelected: true }))).toBe(
                'calendar-button calendar-button--selected'
            );
            expect(buttonClass(button({ name: 'prev', text: '', isIconOnly: true }))).toBe(
                'calendar-button calendar-button--icon'
            );
        });
    });
});
