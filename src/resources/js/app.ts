import '../css/app.css';

import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createHead } from '@unhead/vue/client';
import { createPinia } from 'pinia';
import { createI18n } from 'vue-i18n';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

import es from '@/locales/es';
import en from '@/locales/en';

const appName = import.meta.env.VITE_APP_NAME ?? 'Laravel';

const i18n = createI18n({
    legacy: false,
    locale: 'es',
    fallbackLocale: 'en',
    messages: {
        es,
        en,
    },
});

const pinia = createPinia();
const head = createHead();

void createInertiaApp({
    title: (title: string): string => (title ? `${title} - ${appName}` : appName),
    resolve: (name: string): Promise<DefineComponent> =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue')
        ),
    setup({ el, App, props, plugin }) {
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