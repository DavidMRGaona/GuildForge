import type { AuthSettings, User } from './models';
import type { ModuleSlots } from './slots';

interface ThemeSettings {
    cssVariables: string;
    darkModeDefault: boolean;
    darkModeToggleVisible: boolean;
    fontHeading: string;
    fontBody: string;
}

declare module '@inertiajs/vue3' {
    interface PageProps {
        appName: string;
        appDescription: string;
        siteLogoLight: string | null;
        siteLogoDark: string | null;
        theme: ThemeSettings;
        auth: {
            user: User | null;
        };
        authSettings: AuthSettings;
        flash: {
            success?: string;
            error?: string;
            warning?: string;
            info?: string;
        };
        errors: Record<string, string>;
        moduleSlots: ModuleSlots;
    }
}

export type { ThemeSettings };
