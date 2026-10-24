/**
 * Props of AuthLayout. Pages using it declare it as their persistent layout and set these with
 * setLayoutProps(); Inertia resets them on every page change.
 */
export interface AuthLayoutProps {
    title?: string;
    subtitle?: string;
    showBackLink?: boolean;
}
