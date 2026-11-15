/**
 * Where a site block is drawn (core#718), the same values as `App\Domain\SiteBlocks\SiteBlockPlacement`:
 * the footer (dark in both colour schemes) or a popup (follows the page's colour scheme).
 */
export type SiteBlockPlacement = 'footer' | 'popup';

/**
 * A site block resolved on the server for the person viewing the page (core#696): the footer and
 * the popups send their blocks like this, already filtered to what can be drawn.
 */
export interface ResolvedSiteBlock {
    /** `core.<block>` or `<module>.<block>` */
    key: string;
    /** The module that draws it; null for a core block */
    module: string | null;
    /** Relative to the owner's `resources/js`, e.g. `components/SubscribeBlock.vue` */
    component: string;
    props: Record<string, unknown>;
}
