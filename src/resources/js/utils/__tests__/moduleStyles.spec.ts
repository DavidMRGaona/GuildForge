import { beforeEach, describe, expect, it, vi } from 'vitest';

type ModuleStyles = typeof import('../moduleStyles');

const SOURCES = {
    'cookie-consent': 'http://localhost:5173/modules/cookie-consent/resources/css/module.css',
};

/** The module keeps the sheets it attached, so each test gets a fresh copy. */
async function loadModuleStyles(): Promise<ModuleStyles> {
    vi.resetModules();

    return import('../moduleStyles');
}

function moduleStyleElements(): HTMLStyleElement[] {
    return Array.from(
        document.head.querySelectorAll<HTMLStyleElement>('style[data-module-stylesheet]')
    );
}

describe('moduleStyles', () => {
    beforeEach(() => {
        document.head.innerHTML = '';
    });

    describe('attachModuleStylesheet', () => {
        it('imports the sheet inside the module cascade layer', async () => {
            const { attachModuleStylesheet } = await loadModuleStyles();

            attachModuleStylesheet('/build/modules/cookie-consent/assets/module-styles.css');

            expect(moduleStyleElements()).toHaveLength(1);
            expect(moduleStyleElements()[0]?.textContent).toBe(
                '@import url("/build/modules/cookie-consent/assets/module-styles.css") layer(module-utilities);'
            );
        });

        it('attaches a sheet only once', async () => {
            const { attachModuleStylesheet } = await loadModuleStyles();

            attachModuleStylesheet('/build/modules/cookie-consent/assets/module-styles.css');
            attachModuleStylesheet('/build/modules/cookie-consent/assets/module-styles.css');

            expect(moduleStyleElements()).toHaveLength(1);
        });
    });

    describe('attachModuleSourceStylesheet', () => {
        it('attaches the stylesheet compiled from the module sources, inside the module layer', async () => {
            const { attachModuleSourceStylesheet } = await loadModuleStyles();

            attachModuleSourceStylesheet('cookie-consent', SOURCES);

            expect(moduleStyleElements()).toHaveLength(1);
            expect(moduleStyleElements()[0]?.textContent).toBe(
                `@import url("${SOURCES['cookie-consent']}") layer(module-utilities);`
            );
        });

        it('does nothing for a module without a stylesheet', async () => {
            const { attachModuleSourceStylesheet } = await loadModuleStyles();

            attachModuleSourceStylesheet('announcements', SOURCES);

            expect(moduleStyleElements()).toHaveLength(0);
        });
    });

    describe('refreshModuleStylesheets', () => {
        it('imports the updated sheet again, still inside the module layer', async () => {
            const { attachModuleSourceStylesheet, refreshModuleStylesheets } =
                await loadModuleStyles();
            attachModuleSourceStylesheet('cookie-consent', SOURCES);

            refreshModuleStylesheets(
                '/modules/cookie-consent/resources/css/module.css?direct',
                1793027056719
            );

            expect(moduleStyleElements()).toHaveLength(1);
            expect(moduleStyleElements()[0]?.textContent).toBe(
                `@import url("${SOURCES['cookie-consent']}?t=1793027056719") layer(module-utilities);`
            );
        });

        it('leaves alone the sheets the update is not about', async () => {
            const { attachModuleStylesheet, refreshModuleStylesheets } = await loadModuleStyles();
            attachModuleStylesheet('/build/modules/cookie-consent/assets/module-styles.css');

            refreshModuleStylesheets('/resources/css/app.css', 1793027056719);

            expect(moduleStyleElements()[0]?.textContent).toBe(
                '@import url("/build/modules/cookie-consent/assets/module-styles.css") layer(module-utilities);'
            );
        });
    });
});
