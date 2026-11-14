/**
 * Options of `FormSelect` and how its list is searched and drawn (core#648).
 *
 * The list is flat and ordered. An option with `level: 1` is a child of the nearest option of
 * level 0 above it (a category and its subcategories): it is drawn indented under it, and both
 * are found by either name.
 */
export interface SelectOption {
    value: string | number;
    label: string;
    /** 0 (default) for a top-level option, 1 for a child of the level-0 option above it. */
    level?: 0 | 1;
    disabled?: boolean;
}

/** One row of the open list. */
export interface SelectRow {
    option: SelectOption;
    level: 0 | 1;
    /** Label of the parent, for children: what assistive technology reads before the label. */
    parentLabel: string | null;
    /** A level-0 option followed by children. */
    hasChildren: boolean;
    /** A divider goes above: the row starts a block with children, or ends one. */
    dividerBefore: boolean;
}

/** From how many options the list gets its search box when the field does not say. */
export const SEARCH_THRESHOLD = 8;

/** Lower case, without accents or repeated spaces: «Campañas» matches «campanas» and «7º» matches «7o». */
export function normalizeForSearch(text: string): string {
    return text
        .normalize('NFKD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

interface Indexed {
    option: SelectOption;
    level: 0 | 1;
    parent: number | null;
}

function index(options: readonly SelectOption[]): Indexed[] {
    let parent: number | null = null;

    return options.map((option, position): Indexed => {
        // A child with no level-0 option above it has nothing to hang from: drawn as top level
        const level: 0 | 1 = option.level === 1 && parent !== null ? 1 : 0;
        if (level === 0) {
            parent = position;
        }

        return { option, level, parent: level === 1 ? parent : null };
    });
}

/**
 * The rows the open list shows for a query. An empty query shows every option. Otherwise an
 * option shows when its label holds every word of the query, or when its parent's does (looking
 * for «Campañas» lists all of them); a matching child brings its parent along, as context.
 */
export function visibleRows(options: readonly SelectOption[], query: string): SelectRow[] {
    const indexed = index(options);
    const words = normalizeForSearch(query)
        .split(' ')
        .filter((word) => word !== '');
    const labels = indexed.map((item) => normalizeForSearch(item.option.label));

    const matches = (position: number): boolean => {
        const item = indexed[position];
        if (item === undefined) {
            return false;
        }
        const haystack =
            item.parent === null ? labels[position] : `${labels[item.parent]} ${labels[position]}`;

        return words.every((word) => haystack?.includes(word) === true);
    };

    const shown = new Set<number>();
    indexed.forEach((item, position) => {
        if (words.length === 0 || matches(position)) {
            shown.add(position);
            if (item.parent !== null) {
                shown.add(item.parent);
            }
        }
    });

    const kept = indexed
        .map((item, position) => ({ item, position }))
        .filter(({ position }) => shown.has(position));

    return kept.map(({ item }, at): SelectRow => {
        const next = kept[at + 1]?.item;
        const previous = kept[at - 1]?.item;
        const hasChildren = item.level === 0 && next?.level === 1;
        const parentLabel =
            item.parent === null ? null : (indexed[item.parent]?.option.label ?? null);

        return {
            option: item.option,
            level: item.level,
            parentLabel,
            hasChildren,
            dividerBefore:
                previous !== undefined && item.level === 0 && (hasChildren || previous.level === 1),
        };
    });
}

/** Whether the list gets a search box: as the field says, or from SEARCH_THRESHOLD options. */
export function wantsSearch(searchable: boolean | undefined, count: number): boolean {
    return searchable ?? count >= SEARCH_THRESHOLD;
}
