import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import KnowledgeGuideBody from '../KnowledgeGuideBody.vue';

const mocks = vi.hoisted(() => ({ visit: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router: { visit: mocks.visit },
}));

function mountBody(html: string): ReturnType<typeof mount> {
    return mount(KnowledgeGuideBody, { props: { html }, attachTo: document.body });
}

/** Clicks like a person would and reports whether the browser would still follow the link. */
function click(element: Element): boolean {
    const event = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
    let followed = true;
    document.addEventListener(
        'click',
        (e) => {
            followed = !e.defaultPrevented;
            // The test environment would otherwise navigate for real
            e.preventDefault();
        },
        { once: true }
    );
    element.dispatchEvent(event);

    return followed;
}

describe('KnowledgeGuideBody', () => {
    beforeEach(() => {
        mocks.visit.mockReset();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('opens an internal link, such as an action button, with Inertia', () => {
        const wrapper = mountBody(
            '<p><a class="kb-action" href="/partidas/crear">Crear mesa</a></p>'
        );

        const followed = click(wrapper.get('a').element);

        expect(followed).toBe(false);
        expect(mocks.visit).toHaveBeenCalledWith('/partidas/crear');
        wrapper.unmount();
    });

    it('leaves panel links to the browser', () => {
        const wrapper = mountBody('<p><a href="/admin/ayuda">Panel</a></p>');

        const followed = click(wrapper.get('a').element);

        expect(followed).toBe(true);
        expect(mocks.visit).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    it('leaves links to other sites to the browser', () => {
        const wrapper = mountBody('<p><a href="https://example.org/">Fuera</a></p>');

        const followed = click(wrapper.get('a').element);

        expect(followed).toBe(true);
        expect(mocks.visit).not.toHaveBeenCalled();
        wrapper.unmount();
    });
});
