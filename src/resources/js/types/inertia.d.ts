import type { AuthSettings, User } from './models';
import type { SeoProps } from './seo';
import type { RealtimeSharedProps } from './realtime';
import type { ImpersonationSharedProps } from './impersonation';
import type { SiteNotificationsSharedProps } from './siteNotifications';
import type { ModuleSlots } from './slots';

interface ThemeSettings {
    cssVariables: string;
    darkModeDefault: boolean;
    darkModeToggleVisible: boolean;
    fontHeading: string;
    fontBody: string;
}

interface FaviconSettings {
    light: string | null;
    dark: string | null;
}

declare module '@inertiajs/vue3' {
    interface PageProps {
        appName: string;
        appDescription: string;
        siteLogoLight: string | null;
        siteLogoDark: string | null;
        favicons: FaviconSettings;
        theme: ThemeSettings;
        auth: {
            user: User | null;
        };
        /** Unread notices of the bell (core#491); null for guests. */
        siteNotifications: SiteNotificationsSharedProps | null;
        realtime: RealtimeSharedProps | null;
        /** Whom an administrator is viewing the site as (core#580); null outside an impersonation. */
        impersonation: ImpersonationSharedProps | null;
        authSettings: AuthSettings;
        flash: {
            success?: string;
            error?: string;
            warning?: string;
            info?: string;
        };
        errors: Record<string, string>;
        moduleSlots: ModuleSlots;
        /** Page prefix => module name, for every module page prefix */
        modulePages: Record<string, string>;
        /** Module page prefixes whose pages use the core layout instead of wrapping their own */
        coreLayoutPagePrefixes: string[];
        seo: SeoProps;
    }
}

export type { ThemeSettings, FaviconSettings };
