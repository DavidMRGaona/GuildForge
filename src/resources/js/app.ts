import '../css/app.css';

import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createHead } from '@unhead/vue/client';
import { createPinia } from 'pinia';
import { createI18n } from 'vue-i18n';

import es from '@/locales/es';
import en from '@/locales/en';
import { createPageResolver, setModulePageMapping } from '@/utils/resolveModulePage';
import {
    loadAllModuleTranslations,
    loadModuleTranslationsFromProps,
    type ModuleTranslationsPayload,
} from '@/utils/moduleTranslations';

const appName = import.meta.env.VITE_APP_NAME ?? 'GuildForge';

const i18n = createI18n({
    legacy: false,
    locale: 'es',
    fallbackLocale: 'en',
    messages: {
        es,
        en,
    },
});

// Load all module translations into the i18n instance
// Type assertion needed because vue-i18n's strict typing conflicts with our minimal interface
loadAllModuleTranslations(i18n as Parameters<typeof loadAllModuleTranslations>[0]);

const pinia = createPinia();
const head = createHead();

// Glob patterns for core and module pages
const corePages = import.meta.glob<DefineComponent>('./pages/**/*.vue');
const modulePages = import.meta.glob<DefineComponent>(
    '../../modules/*/resources/js/pages/**/*.vue'
);

/**
 * Initial page data extracted from Inertia's data-page attribute.
 */
interface InitialPageData {
    modulePages: Record<string, string>;
    moduleTranslations: ModuleTranslationsPayload | undefined;
}

/**
 * Extract initial page data from the #app element (embedded by Inertia).
 * This allows us to access shared props before the Vue app is mounted.
 */
function extractInitialPageData(): InitialPageData {
    const appElement = document.getElementById('app');
    if (!appElement) {
        return { modulePages: {}, moduleTranslations: undefined };
    }

    try {
        const dataPage = appElement.getAttribute('data-page');
        if (!dataPage) {
            return { modulePages: {}, moduleTranslations: undefined };
        }

        const pageData = JSON.parse(dataPage);
        return {
            modulePages: pageData.props?.modulePages ?? {},
            moduleTranslations: pageData.props?.moduleTranslations ?? undefined,
        };
    } catch {
        return { modulePages: {}, moduleTranslations: undefined };
    }
}

// Extract initial data before Inertia boots
const initialData = extractInitialPageData();

// Set module page mapping
setModulePageMapping(initialData.modulePages);

// Load module translations from Inertia props (runtime fallback for modules installed after build)
loadModuleTranslationsFromProps(
    i18n as Parameters<typeof loadModuleTranslationsFromProps>[0],
    initialData.moduleTranslations
);

// Create page resolver that handles both core and module pages
const pageResolver = createPageResolver({
    corePages,
    modulePages,
});

void createInertiaApp({
    title: (title: string): string => (title ? `${title} - ${appName}` : appName),
    resolve: pageResolver,
    setup({ el, App, props, plugin }) {
        // Update module pages mapping when props change (for subsequent navigations)
        const modulePagesProp = (props.initialPage.props as Record<string, unknown>).modulePages as
            | Record<string, string>
            | undefined;
        if (modulePagesProp) {
            setModulePageMapping(modulePagesProp);
        }

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(head)
            .use(pinia)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
