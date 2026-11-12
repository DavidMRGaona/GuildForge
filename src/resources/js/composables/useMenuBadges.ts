import { readonly, ref, type DeepReadonly, type Ref } from 'vue';

/**
 * Marks on the site menu's links that modules switch on and off, for something that needs the
 * reader's attention (unread discussions, pending requests). A link is named by its `key`
 * (`module:route`, as `MenuItem.key` carries it), never by its text or address: an admin may
 * change those, and the menu is drawn again by the mobile menu and by every Inertia visit.
 *
 * The state lives here, outside any page, so it survives navigation, and there is one copy per
 * page (the module build resolves this file through the import map). The menu draws a dot and
 * the `label` for screen readers; announcing a change politely is up to the module that sets it.
 */
const badges = ref<Record<string, string>>({});

export interface MenuBadges {
    /** The screen reader text of each marked link, by key */
    badges: Readonly<Ref<DeepReadonly<Record<string, string>>>>;
    /** Marks the link; the text says what the mark means, e.g. «3 temas sin leer» */
    setBadge: (key: string, label: string) => void;
    /** Takes the mark off */
    clearBadge: (key: string) => void;
}

export function setMenuBadge(key: string, label: string): void {
    badges.value = { ...badges.value, [key]: label };
}

export function clearMenuBadge(key: string): void {
    if (!(key in badges.value)) {
        return;
    }

    badges.value = Object.fromEntries(
        Object.entries(badges.value).filter(([name]) => name !== key)
    );
}

export function useMenuBadges(): MenuBadges {
    return { badges: readonly(badges), setBadge: setMenuBadge, clearBadge: clearMenuBadge };
}
