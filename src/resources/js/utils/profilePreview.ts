import type { ProfileEditor, ProfileEditorForm, ProfileLayoutChoice } from '@/types/profileEditor';
import type {
    ProfileRegion,
    ProfileTextSegment,
    ResolvedProfile,
    ResolvedProfileSection,
    ResolvedProfileStat,
} from '@/types/profileSections';

/** The core's «Sobre mí» section: the only one the preview can draw before it is saved */
export const ABOUT_SECTION = 'core.about';

/** Controls other than tabs and line breaks, and the marks that turn text around, as on the server */
// eslint-disable-next-line no-control-regex
const INVISIBLE = /[\u0000-\u0008\u000B-\u001F\u007F-\u009F؜‎‏‪-‮⁦-⁩]/gu;

/** Characters as the server counts them (code points), not UTF-16 units */
export function codePointLength(text: string): number {
    return Array.from(text).length;
}

/**
 * «Sobre mí» while it is being written, as `ProfileBioText` will cut it: control characters dropped,
 * cut to the limit, lines trimmed and runs of blank lines kept as one. Plain text only: the server
 * decides which addresses become links once it is saved.
 */
export function bioPreviewLines(text: string, limit: number): ProfileTextSegment[][] {
    const clean = text.replace(/\r\n?/g, '\n').replace(INVISIBLE, '').trim();
    const cut = Array.from(clean).slice(0, limit).join('').trimEnd();

    if (cut === '') {
        return [];
    }

    const lines: ProfileTextSegment[][] = [];
    let blank = false;

    for (const raw of cut.split('\n')) {
        const line = raw.replace(/^[ \t]+|[ \t]+$/g, '');

        if (line === '') {
            if (!blank) {
                lines.push([]);
            }
            blank = true;
            continue;
        }

        blank = false;
        lines.push([{ text: line, href: null }]);
    }

    return lines;
}

/**
 * Items in the order of the owner's choices, turned off ones left out; anything the choices do not
 * name keeps its place after them.
 */
function inChosenOrder<T extends { key: string }>(items: T[], choices: ProfileLayoutChoice[]): T[] {
    const byKey = new Map(items.map((item) => [item.key, item]));
    const ordered: T[] = [];

    for (const choice of choices) {
        const item = byKey.get(choice.key);

        if (item && choice.enabled) {
            ordered.push(item);
        }

        byKey.delete(choice.key);
    }

    return [...ordered, ...items.filter((item) => byKey.has(item.key))];
}

/**
 * «Sobre mí» as the editor shows it: what the server drew while the text is the saved one (with its
 * links), else the text being written, or nothing for an empty one.
 */
function aboutSection(
    drawn: ResolvedProfileSection | undefined,
    editor: ProfileEditor,
    bio: string
): ResolvedProfileSection | null {
    if (drawn && bio === editor.bio) {
        return drawn;
    }

    const lines = bioPreviewLines(bio, editor.limits.bio);

    if (lines.length === 0) {
        return null;
    }

    if (drawn) {
        return { ...drawn, props: { ...drawn.props, lines } };
    }

    const item = editor.sections.find((section) => section.key === ABOUT_SECTION);

    if (!item?.component || !item.region) {
        return null;
    }

    return {
        key: ABOUT_SECTION,
        module: item.module ?? null,
        component: item.component,
        region: item.region,
        label: item.label,
        props: { lines },
    };
}

/**
 * The profile as it will look once the editor's choices are saved, as far as the page can know it:
 * the server only drew what was on, so a section or stat turned on now shows after saving. «Sobre mí»
 * is the exception, drawn from the text being written.
 */
export function previewProfile(
    profile: ResolvedProfile,
    editor: ProfileEditor,
    form: ProfileEditorForm
): ResolvedProfile {
    const drawn: ResolvedProfileSection[] = [...profile.sections.main, ...profile.sections.aside];
    const drawnAbout = drawn.find((section) => section.key === ABOUT_SECTION);
    const about = aboutSection(drawnAbout, editor, form.bio);
    const candidates = [
        ...drawn.filter((section) => section.key !== ABOUT_SECTION),
        ...(about ? [about] : []),
    ];
    const sections = inChosenOrder(candidates, form.sections);
    const byRegion = (region: ProfileRegion): ResolvedProfileSection[] =>
        sections.filter((section) => section.region === region);
    const stats: ResolvedProfileStat[] = inChosenOrder(profile.stats, form.stats);

    return {
        header: {
            ...profile.header,
            bannerPublicId: editor.bannerPublicId,
            showsMembership: editor.canShowMembership && form.show_membership,
        },
        stats,
        sections: { main: byRegion('main'), aside: byRegion('aside') },
    };
}
