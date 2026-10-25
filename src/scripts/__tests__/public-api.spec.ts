import { existsSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { isPrivateCoreImport, isSharedWithModules, readPublicApi } from '../public-api.mjs';

const SRC = resolve(import.meta.dirname, '../..');

const ENTRIES = [
    '@/components/form',
    '@/components/layout/ModuleSlot.vue',
    '@/components/ui/BaseButton.vue',
    '@/components/ui/BaseCard.vue',
    '@/components/ui/ConfirmDialog.vue',
    '@/components/ui/EmptyState.vue',
    '@/components/ui/LoadingSpinner.vue',
    '@/composables/useAuth',
    '@/composables/useCalendarLocale',
    '@/composables/useFlashMessages',
    '@/composables/useGridLayout',
    '@/composables/useNotifications',
    '@/composables/usePagination',
    '@/composables/useRoutes',
    '@/composables/useVerificationGate',
    '@/contracts/cookieConsent',
    '@/utils/calendarTheme',
    '@/utils/cloudinary',
    '@/utils/csrf',
    '@/utils/datetime',
];

const PACKAGES = [
    'vue',
    'pinia',
    'vue-i18n',
    '@inertiajs/vue3',
    '@unhead/vue',
    '@fullcalendar/vue3',
    '@fullcalendar/vue3/daygrid',
    '@fullcalendar/vue3/timegrid',
    '@fullcalendar/vue3/interaction',
    '@fullcalendar/vue3/themes/classic',
];

describe('public frontend API', () => {
    const api = readPublicApi(SRC);

    it('lists the entries modules may import', () => {
        expect(Object.keys(api.entries).sort()).toEqual([...ENTRIES].sort());
    });

    it('lists the packages the page shares with modules', () => {
        expect(Object.keys(api.packages).sort()).toEqual([...PACKAGES].sort());
    });

    it('points every entry at a file of the core', () => {
        for (const [specifier, path] of Object.entries(api.entries)) {
            expect(existsSync(resolve(SRC, path)), specifier).toBe(true);
        }
    });

    it('points every package at a vendor export of the core', () => {
        const missing = Object.entries(api.packages).filter(([, path]) => !existsSync(resolve(SRC, path)));

        expect(missing).toEqual([]);
    });

    it('keeps the shared packages and the public entries out of module bundles', () => {
        expect(isSharedWithModules('vue', api)).toBe(true);
        expect(isSharedWithModules('@/utils/datetime', api)).toBe(true);
        expect(isSharedWithModules('@fullcalendar/vue3/daygrid', api)).toBe(true);
        expect(isSharedWithModules('chart.js', api)).toBe(false);
    });

    it('tells a private core import from a public one', () => {
        expect(isPrivateCoreImport('@/utils/icons', api)).toBe(true);
        expect(isPrivateCoreImport('@/utils/datetime', api)).toBe(false);
        expect(isPrivateCoreImport('vue', api)).toBe(false);
    });
});
