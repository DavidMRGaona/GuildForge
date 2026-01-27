export type SlotPosition =
    | 'before-header'
    | 'after-header'
    | 'before-content'
    | 'after-content'
    | 'before-footer'
    | 'after-footer'
    // Page-specific slots
    | 'event-detail-actions'
    | 'game-table-registration'
    | 'campaign-detail-actions';

export interface SlotRegistration {
    slot: string;
    component: string;
    module: string;
    order: number;
    props: Record<string, unknown>;
    dataKeys: string[];
}

export type ModuleSlots = Record<string, SlotRegistration[]>;
