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
}

/**
 * Navigation structure shared via Inertia.
 */
export interface Navigation {
    header: MenuItem[];
    footer: MenuItem[];
}
