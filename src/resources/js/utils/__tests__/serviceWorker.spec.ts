import { describe, expect, it, vi, type Mock } from 'vitest';
import { registerServiceWorker, SERVICE_WORKER_URL } from '../serviceWorker';

type Register = ServiceWorkerContainer['register'];
type Registrar = { register: Mock<Register> };

function environment(options: { secure?: boolean; registrar?: Registrar | null } = {}): {
    navigator: { serviceWorker?: Registrar };
    isSecureContext: boolean;
} {
    const registrar =
        options.registrar === undefined
            ? { register: vi.fn<Register>().mockResolvedValue({} as ServiceWorkerRegistration) }
            : options.registrar;

    return {
        navigator: registrar === null ? {} : { serviceWorker: registrar },
        isSecureContext: options.secure ?? true,
    };
}

describe('registerServiceWorker', () => {
    it('registers the worker at its fixed URL for the whole site, never from the HTTP cache', async () => {
        const env = environment();

        await registerServiceWorker(env);

        expect(SERVICE_WORKER_URL).toBe('/sw.js');
        expect(env.navigator.serviceWorker?.register).toHaveBeenCalledWith('/sw.js', {
            scope: '/',
            updateViaCache: 'none',
        });
    });

    it('does nothing in a browser without service workers', async () => {
        await expect(registerServiceWorker(environment({ registrar: null }))).resolves.toBeNull();
    });

    it('does nothing outside a secure context', async () => {
        const env = environment({ secure: false });

        await expect(registerServiceWorker(env)).resolves.toBeNull();
        expect(env.navigator.serviceWorker?.register).not.toHaveBeenCalled();
    });

    it('only warns when the registration fails', async () => {
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
        const env = environment({
            registrar: { register: vi.fn<Register>().mockRejectedValue(new Error('404')) },
        });

        await expect(registerServiceWorker(env)).resolves.toBeNull();
        expect(warn).toHaveBeenCalledOnce();
    });
});
