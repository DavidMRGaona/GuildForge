/**
 * Navigation menu item received from the backend.
 */
export interface MenuItem {
    id: string;
    label: string;
    href: string;
    target: '_self' | '_blank';
    icon: string | null;
    children: MenuItem[];
    isActive: boolean;
    /**
     * What a module names the link by: its module and route (`forum:forum.index`). Null for links
     * with no module or no route (an admin's own), which cannot carry a badge.
     */
    key: string | null;
}

/**
 * Navigation structure shared via Inertia.
 */
export interface Navigation {
    header: MenuItem[];
    footer: MenuItem[];
}
