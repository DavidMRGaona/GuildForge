import type { Component } from 'vue';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import { extractPrefix, getModuleForPage } from '@/utils/resolveModulePage';

/**
 * The layout Inertia renders around a page that declares none (createInertiaApp `layout`).
 *
 * Core pages, and module pages whose prefix uses the core layout (PagePrefixDTO::$usesCoreLayout),
 * get the one DefaultLayout instance, kept across visits: header, footer and module slots keep
 * their state. Pages of other module prefixes still wrap a layout of their own, so they get none
 * here; giving them this one would show the layout twice.
 */
export function resolveDefaultLayout(
    name: string,
    page: { props: Record<string, unknown> }
): Component | undefined {
    if (getModuleForPage(name) === null) {
        return DefaultLayout;
    }

    const coreLayoutPrefixes = (page.props.coreLayoutPagePrefixes as string[] | undefined) ?? [];

    return coreLayoutPrefixes.includes(extractPrefix(name)) ? DefaultLayout : undefined;
}
