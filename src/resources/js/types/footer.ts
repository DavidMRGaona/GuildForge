import type { ResolvedSiteBlock } from './siteBlocks';

/** One block of a footer column, with the optional title drawn above it (core#697) */
export interface FooterBlockEntry {
    title: string | null;
    block: ResolvedSiteBlock;
}

export interface FooterColumn {
    blocks: FooterBlockEntry[];
}

export interface FooterLink {
    label: string;
    href: string;
}

/**
 * The configurable footer, already resolved for the person viewing: only columns with a block left
 * to draw, and the fixed bottom bar. The shared prop is null while the site keeps its fixed footer.
 */
export interface ConfiguredFooter {
    columns: FooterColumn[];
    siteName: string;
    legalLinks: FooterLink[];
}
