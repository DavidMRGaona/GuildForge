import type { Tag } from '@/types/models';

export type ProposalStatus = 'draft' | 'pending_review' | 'approved' | 'rejected';
export type ProposalKind = 'event' | 'article';

/** What the author may still do with a proposal (decided by the server, the same rule as the service). */
export interface ProposalPermissions {
    edit: boolean;
    submit: boolean;
    withdraw: boolean;
    delete: boolean;
}

/** A proposal as its author sees it: the review state and what the form holds. */
export interface Proposal {
    id: string;
    type: ProposalKind;
    status: ProposalStatus;
    title: string;
    /** The event's description or the article's content (HTML). */
    body: string | null;
    excerpt: string | null;
    location: string | null;
    /** Wall times in the display zone, as the form shows them. */
    startDate: string | null;
    startTime: string | null;
    endDate: string | null;
    endTime: string | null;
    startsAt: string | null;
    imagePublicId: string | null;
    tagIds: string[];
    note: string | null;
    /** Why the last review returned or rejected it. */
    reason: string | null;
    submittedAt: string | null;
    reviewedAt: string | null;
    updatedAt: string | null;
    /** Address of the published page, once approved. */
    publicPath: string | null;
    /** What an event's author asked the modules for (always empty for articles). */
    requests: OwnProposalRequest[];
    can: ProposalPermissions;
}

export type ProposalRequestFieldType = 'boolean' | 'number' | 'select' | 'text';
export type ProposalRequestValue = boolean | number | string | null;

/** One field of a module request, drawn by the core; the server checks every value again. */
export interface ProposalRequestField {
    name: string;
    type: ProposalRequestFieldType;
    label: string;
    required: boolean;
    helperText: string | null;
    min: number | null;
    max: number | null;
    maxLength: number | null;
    options: { value: string; label: string }[];
    default: ProposalRequestValue;
    /** Shown only while an earlier field holds one of the values (any value but empty when null). */
    visibleWhen: { field: string; values: (boolean | number | string)[] | null } | null;
}

/** A request an enabled module adds below the event proposal form. */
export interface ProposalRequestDefinition {
    key: string;
    label: string;
    /** The yes/no question that opens it. */
    question: string;
    fields: ProposalRequestField[];
}

/** What the form sends for one request. */
export interface ProposalRequestState {
    wanted: boolean;
    values: Record<string, ProposalRequestValue>;
}

/** A request stored with an event proposal, as its author sees it. */
export interface OwnProposalRequest {
    key: string;
    label: string;
    /** False when its module is no longer active. */
    active: boolean;
    values: Record<string, ProposalRequestValue>;
    /** The module's line for the values in force. */
    description: string | null;
    /** Early warnings of the module for the values in force; they never block anything. */
    warnings: string[];
    applied: boolean;
}

export interface ProposalLimits {
    pendingUsed: number;
    pendingMax: number;
    draftsUsed: number;
    draftsMax: number;
    submissionsLeft: number;
    submissionsPerHour: number;
}

/** The «Mis propuestas» tab data of the profile page. */
export interface ProfileProposals {
    /** Whether the tab shows: the person may propose or already has proposals. */
    available: boolean;
    canPropose: { event: boolean; article: boolean };
    items: Proposal[];
    limits: ProposalLimits;
}

export interface ProposalFormProps {
    /** Null when creating. */
    proposal: Proposal | null;
    /** Additional tags (subtags): whoever proposes picks only these (core#654). */
    tags: Tag[];
    /** Categories, offered only to whoever may publish the type directly; empty otherwise. */
    categories: Tag[];
}

export interface EventProposalFormProps extends ProposalFormProps {
    /** The requests the enabled modules offer right now. */
    moduleRequests: ProposalRequestDefinition[];
}
