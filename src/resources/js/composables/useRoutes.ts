/**
 * Centralized route definitions for the application.
 * Eliminates hardcoded URLs throughout the codebase.
 */

export interface RouteDefinitions {
    // Public pages
    home: string;
    about: string;
    search: string;

    // Content sections
    events: {
        index: string;
        show: (slug: string) => string;
    };
    articles: {
        index: string;
        show: (slug: string) => string;
    };
    gallery: {
        index: string;
        show: (slug: string) => string;
    };
    calendar: string;

    // Auth
    auth: {
        login: string;
        register: string;
        logout: string;
        forgotPassword: string;
        resetPassword: string;
        verifyEmail: string;
    };

    // User
    profile: string;

    // Admin
    admin: string;

    // Contact
    contact: string;
}

const routes: RouteDefinitions = {
    // Public pages
    home: '/',
    about: '/sobre-nosotros',
    search: '/buscar',

    // Content sections
    events: {
        index: '/eventos',
        show: (slug: string) => `/eventos/${slug}`,
    },
    articles: {
        index: '/articulos',
        show: (slug: string) => `/articulos/${slug}`,
    },
    gallery: {
        index: '/galeria',
        show: (slug: string) => `/galeria/${slug}`,
    },
    calendar: '/calendario',

    // Auth
    auth: {
        login: '/iniciar-sesion',
        register: '/registro',
        logout: '/cerrar-sesion',
        forgotPassword: '/olvide-contrasena',
        resetPassword: '/restablecer-contrasena',
        verifyEmail: '/verificar-email',
    },

    // User
    profile: '/perfil',

    // Admin
    admin: '/admin',

    // Contact
    contact: '/contacto',
};

export function useRoutes(): RouteDefinitions {
    return routes;
}

// Export routes directly for use in non-composable contexts
export { routes };
