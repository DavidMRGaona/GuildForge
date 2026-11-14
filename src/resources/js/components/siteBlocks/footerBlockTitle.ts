import type { InjectionKey } from 'vue';

/**
 * The id of the title drawn above a footer block, or null when it has none (core#697), so a block can
 * name its landmark after it (`aria-labelledby`).
 */
export const FOOTER_BLOCK_TITLE_ID: InjectionKey<string | null> = Symbol('footerBlockTitleId');
