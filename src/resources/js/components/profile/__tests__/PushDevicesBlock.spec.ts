import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import PushDevicesBlock from '../PushDevicesBlock.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { PushDevice } from '@/types/mailPreferences';

const router = vi.hoisted(() => ({ reload: vi.fn() }));
const webPush = vi.hoisted(() => ({
    thisDevicePushState: vi.fn(),
    subscribeThisDevice: vi.fn(),
    syncThisDevice: vi.fn(),
    removeDevice: vi.fn(),
    sendTestNotice: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({ router }));
vi.mock('@/utils/webPush', () => webPush);

const PHONE: PushDevice = {
    id: 'device-phone',
    browser: 'chrome',
    platform: 'android',
    current: true,
    createdAt: '2026-11-21T10:00:00+01:00',
    lastUsedAt: '2026-11-22T09:30:00+01:00',
};

const LAPTOP: PushDevice = {
    id: 'device-laptop',
    browser: 'firefox',
    platform: 'linux',
    current: false,
    createdAt: '2026-11-01T10:00:00+01:00',
    lastUsedAt: null,
};

async function mountBlock(devices: PushDevice[] = [], suggestedOff = 0): Promise<VueWrapper> {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });
    const wrapper = mount(PushDevicesBlock, {
        props: { devices, publicKey: 'key', userId: 'user-a', suggestedOff },
        global: { plugins: [i18n], stubs: { HelpLink: true } },
    });
    await flushPromises();

    return wrapper;
}

describe('PushDevicesBlock', () => {
    beforeEach(() => {
        for (const mock of Object.values(webPush)) {
            mock.mockReset();
        }
        router.reload.mockReset();
        webPush.thisDevicePushState.mockResolvedValue('available');
    });

    it('asks for the browser permission only when the button is clicked', async () => {
        webPush.subscribeThisDevice.mockResolvedValue(true);
        const wrapper = await mountBlock();

        expect(webPush.subscribeThisDevice).not.toHaveBeenCalled();
        await wrapper.find('[data-action="enable"]').trigger('click');
        await flushPromises();

        expect(webPush.subscribeThisDevice).toHaveBeenCalledWith('key', 'user-a');
        expect(wrapper.find('[data-state="subscribed"]').exists()).toBe(true);
        expect(router.reload).toHaveBeenCalledWith({ only: ['mailPreferences'] });
    });

    it('explains how to undo a refusal in the browser prompt', async () => {
        webPush.subscribeThisDevice.mockResolvedValue(false);
        const wrapper = await mountBlock();
        webPush.thisDevicePushState.mockResolvedValue('denied');

        await wrapper.find('[data-action="enable"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[data-state="denied"]').exists()).toBe(true);
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('says so when turning it on failed for another reason', async () => {
        webPush.subscribeThisDevice.mockResolvedValue(false);
        const wrapper = await mountBlock();

        await wrapper.find('[data-action="enable"]').trigger('click');
        await flushPromises();

        expect(wrapper.find('[role="alert"]').text()).toContain('No se han podido activar');
        expect(wrapper.find('[data-action="enable"]').exists()).toBe(true);
    });

    it.each(['unsupported', 'home-screen', 'denied'] as const)(
        'offers no button where the browser cannot: %s',
        async (state) => {
            webPush.thisDevicePushState.mockResolvedValue(state);
            const wrapper = await mountBlock();

            expect(wrapper.find(`[data-state="${state}"]`).exists()).toBe(true);
            expect(wrapper.find('[data-action="enable"]').exists()).toBe(false);
        }
    );

    it('lists the devices, this one marked, with their last notice or the day they were added', async () => {
        webPush.thisDevicePushState.mockResolvedValue('subscribed');
        const wrapper = await mountBlock([PHONE, LAPTOP]);

        const phone = wrapper.find('[data-device="device-phone"]');
        expect(phone.text()).toContain('Chrome en Android');
        expect(phone.text()).toContain('Este dispositivo');
        expect(phone.text()).toContain('Último aviso el 22 de noviembre de 2026');

        const laptop = wrapper.find('[data-device="device-laptop"]');
        expect(laptop.text()).toContain('Firefox en Linux');
        expect(laptop.text()).not.toContain('Este dispositivo');
        expect(laptop.text()).toContain('Activado el 1 de noviembre de 2026');
        expect(laptop.find('[data-action="remove"]').attributes('aria-label')).toBe(
            'Dejar de recibir avisos en Firefox en Linux'
        );
    });

    it('removes a device and reloads the list', async () => {
        webPush.thisDevicePushState.mockResolvedValue('subscribed');
        webPush.removeDevice.mockResolvedValue(true);
        const wrapper = await mountBlock([PHONE, LAPTOP]);

        await wrapper.find('[data-device="device-laptop"] [data-action="remove"]').trigger('click');
        await flushPromises();

        expect(webPush.removeDevice).toHaveBeenCalledWith('device-laptop', 'user-a', false);
        expect(router.reload).toHaveBeenCalled();
    });

    it('sends a test notice to this device and says it went out', async () => {
        webPush.thisDevicePushState.mockResolvedValue('subscribed');
        webPush.sendTestNotice.mockResolvedValue('sent');
        const wrapper = await mountBlock([PHONE]);

        await wrapper.find('[data-action="test"]').trigger('click');
        await flushPromises();

        expect(webPush.sendTestNotice).toHaveBeenCalledWith('user-a');
        expect(wrapper.find('[role="status"]').text()).toContain('Aviso de prueba enviado');
    });

    it('offers no test while this browser is not one of the devices', async () => {
        const wrapper = await mountBlock([LAPTOP]);

        expect(wrapper.find('[data-action="test"]').exists()).toBe(false);
    });

    it('tells the site about a subscription it has not marked yet, then reloads to mark it', async () => {
        webPush.thisDevicePushState.mockResolvedValue('subscribed');
        await mountBlock([{ ...PHONE, current: false }]);

        expect(webPush.syncThisDevice).toHaveBeenCalledWith('key', 'user-a');
        expect(router.reload).toHaveBeenCalledWith({ only: ['mailPreferences'] });
    });

    it('asks the tab to turn on the suggested ones', async () => {
        const wrapper = await mountBlock([LAPTOP], 2);

        await wrapper.find('[data-action="enable-suggested"]').trigger('click');

        expect(wrapper.emitted('enable-suggested')).toHaveLength(1);
    });
});
