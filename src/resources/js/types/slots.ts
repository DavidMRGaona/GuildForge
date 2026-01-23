export type SlotPosition =
    | 'before-header'
    | 'after-header'
    | 'before-content'
    | 'after-content'
    | 'before-footer'
    | 'after-footer';

export interface SlotRegistration {
    slot: string;
    component: string;
    module: string;
    order: number;
    props: Record<string, unknown>;
    dataKeys: string[];
}

export type ModuleSlots = Record<string, SlotRegistration[]>;
