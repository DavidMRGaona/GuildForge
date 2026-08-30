/**
 * Cascade layer every module stylesheet is imported into.
 *
 * The layer and its position are declared in resources/css/app.css; see the
 * comment there for why it sits where it does.
 */
const MODULE_STYLE_LAYER = 'module-utilities';

/** Stylesheets already attached, so a sheet shared by several module entries is imported once. */
const attached = new Set<string>();

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

    attached.add(url);

    const style = document.createElement('style');
    style.dataset.moduleStylesheet = url;
    // JSON.stringify quotes and escapes the URL for the url() token.
    style.textContent = `@import url(${JSON.stringify(url)}) layer(${MODULE_STYLE_LAYER});`;

    document.head.appendChild(style);
}
