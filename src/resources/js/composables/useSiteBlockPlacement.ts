import { computed, inject, type ComputedRef, type InjectionKey } from 'vue';
import type { SiteBlockPlacement } from '@/types/siteBlocks';

/**
 * Where the site block being drawn is shown (core#718). `SiteBlock.vue` provides it to the block it
 * draws, so a block never has to look at the markup around it.
 */
export const SITE_BLOCK_PLACEMENT: InjectionKey<ComputedRef<SiteBlockPlacement>> =
    Symbol('siteBlockPlacement');

/** What a block is drawn in when nothing says otherwise: the footer, the first placement there was. */
export const DEFAULT_SITE_BLOCK_PLACEMENT: SiteBlockPlacement = 'footer';

/**
 * The placement the calling block is drawn in: `footer` (always dark, in both colour schemes) or
 * `popup` (follows the page's colour scheme). Reactive; outside `SiteBlock.vue` (a spec mounting the
 * component alone) it is the footer.
 */
export function useSiteBlockPlacement(): ComputedRef<SiteBlockPlacement> {
    return inject(
        SITE_BLOCK_PLACEMENT,
        computed(() => DEFAULT_SITE_BLOCK_PLACEMENT),
        true
    );
}
