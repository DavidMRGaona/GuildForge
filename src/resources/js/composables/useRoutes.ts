/**
 * Centralized route definitions for the application.
 * Eliminates hardcoded URLs throughout the codebase.
 */

export interface RouteDefinitions {
    // Public pages
    home: string;
    about: string;
    search: string;
    searchSuggestions: string;

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

    // Help centre
    knowledge: {
        index: string;
        search: string;
        visible: string;
        category: (id: string) => string;
        show: (id: string) => string;
        vote: (id: string) => string;
    };

    // Legal pages (route legal.show): link these, never a hand-written URL
    legal: {
        privacy: string;
        notice: string;
        cookies: string;
        terms: string;
    };

    // Auth
    auth: {
        login: string;
        register: string;
        logout: string;
        forgotPassword: string;
        resetPassword: string;
        verifyEmail: string;
        resendVerification: string;
        stopImpersonation: string;
    };

    // User
    profile: string;
    // Notification centre (core#491)
    notifications: {
        index: string;
        count: string;
        recent: string;
        readAll: string;
        /** One notice from its menu (core#670) */
        read: (id: string) => string;
        unread: (id: string) => string;
        hide: (id: string) => string;
    };

    // Proposals of events and articles from the web (core#579)
    proposals: {
        /** «Mis propuestas» tab of the profile */
        mine: string;
        events: {
            create: string;
            store: string;
            edit: (id: string) => string;
            update: (id: string) => string;
        };
        articles: {
            create: string;
            store: string;
            edit: (id: string) => string;
            update: (id: string) => string;
        };
        submit: (type: 'event' | 'article', id: string) => string;
        withdraw: (type: 'event' | 'article', id: string) => string;
        destroy: (type: 'event' | 'article', id: string) => string;
        /** Where the web rich editor uploads the images of an article */
        articleImages: string;
    };

    // Admin
    admin: string;

    // Contact
    contact: string;
}

const routes: RouteDefinitions = {
    // Public pages
    home: '/',
    about: '/nosotros',
    search: '/buscar',
    searchSuggestions: '/buscar/sugerencias',

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

    // Help centre
    knowledge: {
        index: '/ayuda',
        search: '/ayuda/buscar',
        visible: '/ayuda/visibles',
        category: (id: string) => `/ayuda/categoria/${id}`,
        show: (id: string) => `/ayuda/${id}`,
        vote: (id: string) => `/ayuda/${id}/voto`,
    },

    // Legal pages
    legal: {
        privacy: '/politica-de-privacidad',
        notice: '/aviso-legal',
        cookies: '/politica-de-cookies',
        terms: '/terminos-y-condiciones',
    },

    // Auth
    auth: {
        login: '/iniciar-sesion',
        register: '/registro',
        logout: '/cerrar-sesion',
        forgotPassword: '/olvide-contrasena',
        resetPassword: '/restablecer-contrasena',
        verifyEmail: '/verificar-email',
        resendVerification: '/verificar-email/reenviar',
        stopImpersonation: '/impersonar/salir',
    },

    // User
    profile: '/perfil',
    notifications: {
        index: '/avisos',
        count: '/avisos/contador',
        recent: '/avisos/recientes',
        readAll: '/avisos/leidos',
        read: (id: string) => `/avisos/${id}/leido`,
        unread: (id: string) => `/avisos/${id}/no-leido`,
        hide: (id: string) => `/avisos/${id}/ocultar`,
    },

    // Proposals
    proposals: {
        mine: '/perfil?tab=proposals',
        events: {
            create: '/propuestas/eventos/nuevo',
            store: '/propuestas/eventos',
            edit: (id: string) => `/propuestas/eventos/${id}/editar`,
            update: (id: string) => `/propuestas/eventos/${id}`,
        },
        articles: {
            create: '/propuestas/articulos/nuevo',
            store: '/propuestas/articulos',
            edit: (id: string) => `/propuestas/articulos/${id}/editar`,
            update: (id: string) => `/propuestas/articulos/${id}`,
        },
        submit: (type, id) =>
            `/propuestas/${type === 'event' ? 'eventos' : 'articulos'}/${id}/enviar`,
        withdraw: (type, id) =>
            `/propuestas/${type === 'event' ? 'eventos' : 'articulos'}/${id}/retirar`,
        destroy: (type, id) => `/propuestas/${type === 'event' ? 'eventos' : 'articulos'}/${id}`,
        articleImages: '/editor/imagenes/articulos',
    },

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
