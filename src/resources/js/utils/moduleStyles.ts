/**
 * Cascade layer every module stylesheet is imported into.
 *
 * The layer and its position are declared in resources/css/app.css; see the
 * comment there for why it sits where it does.
 */
const MODULE_STYLE_LAYER = 'module-utilities';

/** Stylesheets already attached, by URL, so a sheet shared by several module entries is imported once. */
const attached = new Map<string, HTMLStyleElement>();

/**
 * Each module's `resources/css/module.css`, by module name, as the dev server
 * compiles it from the module's sources. Development only, like the component
 * and page globs: a production build takes the sheet from the module's own build
 * manifest instead.
 *
 * Only the glob's keys are used, resolved against this file's URL on the dev
 * server. The glob stays lazy on purpose: an eager one would add static imports
 * that survive the dead branch and pull module sheets into the core build.
 *
 * Path: from utils/ -> js/ -> resources/ -> src/ -> modules/
 */
const sourceStylesheets: Record<string, string> = import.meta.env.DEV
    ? Object.fromEntries(
          Object.keys(import.meta.glob('../../../modules/*/resources/css/module.css')).map(
              (path) => [path.split('/')[4], new URL(path, import.meta.url).href]
          )
      )
    : {};

function importRule(url: string): string {
    // JSON.stringify quotes and escapes the URL for the url() token.
    return `@import url(${JSON.stringify(url)}) layer(${MODULE_STYLE_LAYER});`;
}

/**
 * Attach a module stylesheet to the document, inside the module cascade layer.
 *
 * A `<link>` cannot carry a layer, so the sheet is pulled in through an
 * `@import` that names one. That detail is the whole point: left unlayered, a
 * module sheet's rules beat every layered rule in the core regardless of source
 * order or specificity, and a module carrying its own `.hidden` was enough to
 * hide the site header for good.
 */
export function attachModuleStylesheet(url: string): void {
    if (attached.has(url)) {
        return;
    }

    const style = document.createElement('style');
    style.dataset.moduleStylesheet = url;
    style.textContent = importRule(url);

    attached.set(url, style);
    document.head.appendChild(style);
}

/**
 * Attach the stylesheet the dev server compiles from a module's sources, for
 * the pages and components loaded from those sources.
 *
 * The core stylesheet never sees module classes, so without this a module's own
 * utilities would be missing in development while present in production. It goes
 * through attachModuleStylesheet() to land in the same cascade layer as the built
 * sheet. Does nothing for a module without a stylesheet, or outside development.
 */
export function attachModuleSourceStylesheet(
    module: string,
    sources: Record<string, string> = sourceStylesheets
): void {
    const url = sources[module];

    if (url !== undefined) {
        attachModuleStylesheet(url);
    }
}

/**
 * Import again the attached stylesheets served at `path`, after the dev server
 * has rebuilt it.
 *
 * Vite's client only swaps `<link>` elements on a CSS update, and a module sheet
 * is imported from a `<style>` instead (see attachModuleStylesheet()), so the
 * update has to be applied here. The timestamp busts the browser cache; the dev
 * server ignores it.
 */
export function refreshModuleStylesheets(path: string, timestamp: number): void {
    const pathname = new URL(path, document.baseURI).pathname;

    for (const [url, style] of attached) {
        const fresh = new URL(url, document.baseURI);

        if (fresh.pathname === pathname) {
            fresh.searchParams.set('t', String(timestamp));
            style.textContent = importRule(fresh.href);
        }
    }
}

// The dev server reports a rebuilt module sheet (Tailwind rescans the module's
// sources whenever one of them changes) as a CSS update for its path.
if (import.meta.hot) {
    import.meta.hot.on('vite:beforeUpdate', (payload) => {
        for (const update of payload.updates) {
            if (update.type === 'css-update') {
                refreshModuleStylesheets(update.path, update.timestamp);
            }
        }
    });
}
