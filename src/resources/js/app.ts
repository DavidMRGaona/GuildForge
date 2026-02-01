import '../css/app.css';

import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createHead } from '@unhead/vue/client';
import { createPinia } from 'pinia';
import { createI18n } from 'vue-i18n';

import es from '@/locales/es';
import en from '@/locales/en';
import { createPageResolver, setModulePageMapping } from '@/utils/resolveModulePage';
import { loadAllModuleTranslations } from '@/utils/moduleTranslations';

// Direct import of module translations for merging
import gameTablesEs from '../../modules/game-tables/resources/js/locales/es';
import gameTablesEn from '../../modules/game-tables/resources/js/locales/en';

const appName = import.meta.env.VITE_APP_NAME ?? 'Laravel';

const i18n = createI18n({
    legacy: false,
    locale: 'es',
    fallbackLocale: 'en',
    messages: {
        es: { ...es, ...gameTablesEs },
        en: { ...en, ...gameTablesEn },
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

// Extract module page mapping from initial page data (embedded in #app element by Inertia)
function extractInitialModulePages(): Record<string, string> {
    const appElement = document.getElementById('app');
    if (!appElement) return {};

    try {
        const dataPage = appElement.getAttribute('data-page');
        if (!dataPage) return {};

        const pageData = JSON.parse(dataPage);
        return pageData.props?.modulePages ?? {};
    } catch {
        return {};
    }
}

// Set module page mapping before Inertia boots
setModulePageMapping(extractInitialModulePages());

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
