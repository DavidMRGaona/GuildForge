/**
 * Glob import for all module locale files.
 * Each module can have locales at modules/<module>/resources/js/locales/es.ts (and en.ts)
 * Using the same relative path pattern as app.ts uses for module pages.
 */
const moduleLocales = import.meta.glob<{ default: Record<string, unknown> }>(
    '../../../modules/*/resources/js/locales/{es,en}.ts',
    { eager: true }
);

// Debug: log what files were matched
if (import.meta.env.DEV) {
    console.log('[i18n] Module locales found:', Object.keys(moduleLocales));
}

/**
 * Track which modules have already had their translations loaded.
 */
const loadedModules = new Set<string>();

/**
 * Extract module name from a locale file path.
 * E.g., "../../../modules/game-tables/resources/js/locales/es.ts" -> "game-tables"
 */
function extractModuleName(path: string): string | null {
    const match = path.match(/modules\/([^/]+)\/resources/);
    return match?.[1] ?? null;
}

/**
 * Extract locale from a locale file path.
 * E.g., "../../../modules/game-tables/resources/js/locales/es.ts" -> "es"
 */
function extractLocale(path: string): string | null {
    const match = path.match(/locales\/([^.]+)\.ts$/);
    return match?.[1] ?? null;
}

/**
 * I18n instance with global composer for merging translations.
 * Using a minimal interface to avoid type incompatibility with vue-i18n's strict typing.
 */
interface I18nGlobal {
    getLocaleMessage(locale: string): Record<string, unknown>;
    setLocaleMessage(locale: string, messages: Record<string, unknown>): void;
}

/**
 * Load all module translations into the i18n instance.
 * This should be called during app initialization.
 */
export function loadAllModuleTranslations(i18n: { global: I18nGlobal }): void {
    for (const [path, module] of Object.entries(moduleLocales)) {
        const moduleName = extractModuleName(path);
        const locale = extractLocale(path);

        if (!moduleName || !locale || locale === 'index') {
            continue;
        }

        // Skip if this is the index file
        if (path.endsWith('index.ts')) {
            continue;
        }

        // Merge module translations into the existing locale messages
        const existingMessages = i18n.global.getLocaleMessage(locale);
        const moduleMessages = module.default;

        // Merge the module messages into the existing messages
        i18n.global.setLocaleMessage(locale, {
            ...existingMessages,
            ...moduleMessages,
        });

        loadedModules.add(`${moduleName}:${locale}`);
    }
}

/**
 * Check if a module's translations have been loaded for a specific locale.
 */
export function isModuleTranslationsLoaded(moduleName: string, locale: string): boolean {
    return loadedModules.has(`${moduleName}:${locale}`);
}
