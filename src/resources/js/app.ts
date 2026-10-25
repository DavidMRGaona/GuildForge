import '../css/app.css';

import { createApp, h, type DefineComponent, type VNode } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createHead } from '@unhead/vue/client';
import { createPinia } from 'pinia';
import { createI18n } from 'vue-i18n';

import es from '@/locales/es';
import en from '@/locales/en';
import { createPageResolver, setModulePageMapping } from '@/utils/resolveModulePage';
import { resolveDefaultLayout } from '@/utils/pageLayout';
import {
    loadAllModuleTranslations,
    loadModuleTranslationsFromProps,
    type ModuleTranslationsPayload,
} from '@/utils/moduleTranslations';
import { setCloudinaryConfig, type CloudinaryConfig } from '@/utils/cloudinary';
import { removeServerSeoTags, useServerSeo } from '@/composables/useSeo';

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
// Module pages from their sources only in development (hot reload). A production build
// loads them from each module's own build, as on a site built without modules, even
// when it sees src/modules (scripts/verify-build.mjs checks it)
const modulePages: Record<string, () => Promise<DefineComponent>> = import.meta.env.DEV
    ? import.meta.glob<DefineComponent>('../../modules/*/resources/js/pages/**/*.vue')
    : {};

/**
 * Shared props read from the initial page the server embeds in the HTML.
 */
interface InitialPageData {
    modulePages: Record<string, string>;
    moduleTranslations: ModuleTranslationsPayload | undefined;
    cloudinary: CloudinaryConfig | undefined;
}

const EMPTY_INITIAL_DATA: InitialPageData = {
    modulePages: {},
    moduleTranslations: undefined,
    cloudinary: undefined,
};

/**
 * Read the raw initial page JSON from the script element the server embeds.
 */
function readInitialPageJson(): string | null {
    const script = document.querySelector<HTMLScriptElement>(
        'script[data-page="app"][type="application/json"]'
    );

    return script?.textContent || null;
}

/**
 * Extract the shared props the app needs before Vue mounts.
 */
function extractInitialPageData(): InitialPageData {
    const json = readInitialPageJson();
    if (json === null) {
        return EMPTY_INITIAL_DATA;
    }

    try {
        const pageData = JSON.parse(json);
        return {
            modulePages: pageData.props?.modulePages ?? {},
            moduleTranslations: pageData.props?.moduleTranslations ?? undefined,
            cloudinary: pageData.props?.cloudinary ?? undefined,
        };
    } catch {
        return EMPTY_INITIAL_DATA;
    }
}

// Extract initial data before Inertia boots
const initialData = extractInitialPageData();

// Publish Cloudinary delivery settings before any component builds an image URL.
// Module bundles read these at runtime, so their assets carry no installation config.
if (initialData.cloudinary) {
    setCloudinaryConfig(initialData.cloudinary);
}

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
    // unhead owns <title> (useServerSeo). An empty default keeps Inertia from
    // adding a second <title data-inertia> to the head.
    title: (title: string): string => title,
    resolve: pageResolver,
    // Persistent layout for pages that declare none (see resolveDefaultLayout)
    layout: resolveDefaultLayout,
    setup({ el, App, props, plugin }) {
        // Update module pages mapping when props change (for subsequent navigations)
        const modulePagesProp = (props.initialPage.props as Record<string, unknown>).modulePages as
            Record<string, string> | undefined;
        if (modulePagesProp) {
            setModulePageMapping(modulePagesProp);
        }

        // The server rendered the first page's SEO tags for crawlers; unhead
        // owns the head from here on.
        removeServerSeoTags();

        createApp({
            setup(): () => VNode {
                useServerSeo();
                return () => h(App, props);
            },
        })
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
