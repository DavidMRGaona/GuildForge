import type { DefineComponent } from 'vue';

/**
 * Mapping of page prefixes to module names.
 * This is populated from the backend via Inertia shared props.
 */
let modulePageMapping: Record<string, string> = {};

/**
 * Set the module page mapping from Inertia shared props.
 */
export function setModulePageMapping(mapping: Record<string, string>): void {
    modulePageMapping = mapping;
}

/**
 * Get the current module page mapping.
 */
export function getModulePageMapping(): Record<string, string> {
    return modulePageMapping;
}

/**
 * Extract the prefix from a page name.
 * For example, "GameTables/Index" returns "GameTables".
 */
function extractPrefix(pageName: string): string {
    const slashIndex = pageName.indexOf('/');
    return slashIndex !== -1 ? pageName.substring(0, slashIndex) : pageName;
}

/**
 * Get the module name for a page prefix.
 * Returns null if the prefix is not registered as a module page.
 */
export function getModuleForPage(pageName: string): string | null {
    const prefix = extractPrefix(pageName);
    return modulePageMapping[prefix] ?? null;
}

/**
 * Check if a page name belongs to a module.
 */
export function isModulePage(pageName: string): boolean {
    return getModuleForPage(pageName) !== null;
}

/**
 * Glob patterns for core pages and module pages.
 * Core pages are in ./pages/, module pages are in modules/<module>/resources/js/pages/
 */
type PageGlob = Record<string, () => Promise<DefineComponent>>;

interface ResolveOptions {
    corePages: PageGlob;
    modulePages: PageGlob;
}

/**
 * Create a page resolver that handles both core and module pages.
 * This is used in app.ts to configure Inertia's page resolution.
 */
export function createPageResolver(options: ResolveOptions) {
    return async (name: string): Promise<DefineComponent> => {
        const moduleName = getModuleForPage(name);

        if (moduleName !== null) {
            // This is a module page - look in module pages glob
            const modulePath = `../../modules/${moduleName}/resources/js/pages/${name}.vue`;

            if (options.modulePages[modulePath]) {
                return options.modulePages[modulePath]();
            }

            // Try without the prefix in the path (e.g., GameTables/Index -> modules/game-tables/resources/js/pages/Index.vue)
            const prefix = extractPrefix(name);
            const relativeName = name.substring(prefix.length + 1); // Remove "GameTables/" prefix
            const alternativePath = `../../modules/${moduleName}/resources/js/pages/${relativeName}.vue`;

            if (options.modulePages[alternativePath]) {
                return options.modulePages[alternativePath]();
            }

            throw new Error(
                `Module page not found: ${name}. Looked in module "${moduleName}" for ${modulePath} or ${alternativePath}`
            );
        }

        // This is a core page
        const corePath = `./pages/${name}.vue`;

        if (options.corePages[corePath]) {
            return options.corePages[corePath]();
        }

        throw new Error(`Core page not found: ${name}. Looked for ${corePath}`);
    };
}
